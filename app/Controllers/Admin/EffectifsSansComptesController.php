<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\RosterFicheEventRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use App\Services\Effectifs\NoAccountDirectory;
use App\Services\Effectifs\RosterOnlyMemberService;
use App\Services\Identity\UserIdentityMergeService;
use App\Support\EffectifsLmsAccess;
use App\Support\RosterOnlyMember;

/**
 * Effectifs › Sans compte : actions sur les personnes sans compte (création rapide, correction, rattachement à un
 * compte existant, import en masse). La page elle-même est servie par EffectifsWorkspaceController::sansComptes.
 *
 * Toutes les actions : connecté, droit sur les fiches, CSRF, et la personne visée doit appartenir à la communauté de
 * la session ET être réellement sans compte utilisable.
 */
final class EffectifsSansComptesController
{
    public function __construct(
        private ?UserRepository $users = null,
        private ?RosterFicheEventRepository $events = null,
    ) {
        $this->events ??= new RosterFicheEventRepository();
    }

    private function users(): UserRepository
    {
        return $this->users ??= Container::get(UserRepository::class);
    }

    /** POST /back-office/ressources/effectifs/sans-comptes/creer */
    public function create(Request $request, array $params = []): Response
    {
        if (($denied = $this->guard($request, 'create')) !== null) {
            return $denied;
        }
        [$tenantId, $actorId] = $this->ids();
        $first = $this->text($request, 'first_name', 60);
        $last = $this->text($request, 'last_name', 60);
        $callsign = $this->text($request, 'callsign', 50);
        $display = $this->text($request, 'display_name', 100);
        if ($display === '') {
            $display = trim($first . ' ' . $last);
        }
        if ($display === '') {
            $display = $callsign;
        }
        if ($display === '') {
            return $this->back(false, 'Indiquez au moins un nom ou un indicatif.');
        }
        if ($callsign !== '' && $this->users()->callsignExistsInTenant($tenantId, $callsign)) {
            return $this->back(false, 'L’indicatif « ' . $callsign . ' » est déjà porté dans la communauté.');
        }
        $plan = Container::get(\App\Services\Platform\FeatureGateService::class);
        if (!$plan->canAddMember($tenantId)) {
            return $this->back(false, 'Limite de membres du plan atteinte.');
        }
        try {
            $id = $this->roster()->create($tenantId, $actorId, [
                'first_name' => $first,
                'last_name' => $last,
                'display_name' => $display,
                'callsign' => $callsign,
            ]);
        } catch (\Throwable $e) {
            error_log('[sans-compte] création : ' . $e->getMessage());

            return $this->back(false, 'Impossible de créer la fiche pour le moment.');
        }
        $this->events->record($tenantId, $id, $actorId, 'created', 'manual', $display);

        return $this->back(true, 'Fiche créée. Vous pouvez maintenant l’inviter à créer son compte.', $id);
    }

    /** POST /back-office/ressources/effectifs/sans-comptes/{id}/modifier */
    public function update(Request $request, array $params = []): Response
    {
        if (($denied = $this->guard($request, 'edit')) !== null) {
            return $denied;
        }
        [$tenantId, $actorId] = $this->ids();
        $id = (int) ($params['id'] ?? 0);
        $row = $this->noAccountRow($tenantId, $id);
        if ($row === null) {
            return $this->back(false, 'Cette personne n’est pas (ou plus) sans compte dans la communauté.');
        }
        $display = $this->text($request, 'display_name', 100);
        $callsign = $this->text($request, 'callsign', 50);
        if ($display === '' && $callsign === '') {
            return $this->back(false, 'Indiquez au moins un nom ou un indicatif.', $id);
        }
        if ($callsign !== '' && $this->users()->callsignExistsInTenant($tenantId, $callsign, $id)) {
            return $this->back(false, 'L’indicatif « ' . $callsign . ' » est déjà porté dans la communauté.', $id);
        }
        $changes = [];
        $before = [(string) ($row['display_name'] ?? ''), (string) ($row['callsign'] ?? '')];
        $data = ['display_name' => $display !== '' ? $display : ($row['display_name'] ?? null), 'callsign' => $callsign !== '' ? $callsign : null];
        try {
            $this->users()->update($id, $tenantId, $data);
        } catch (\Throwable $e) {
            error_log('[sans-compte] correction : ' . $e->getMessage());

            return $this->back(false, 'Impossible d’enregistrer la correction.', $id);
        }
        if ($before[0] !== (string) $data['display_name']) {
            $changes[] = 'nom « ' . $before[0] . ' » → « ' . $data['display_name'] . ' »';
        }
        if ($before[1] !== (string) ($data['callsign'] ?? '')) {
            $changes[] = 'indicatif « ' . $before[1] . ' » → « ' . ($data['callsign'] ?? '') . ' »';
        }
        $this->events->record($tenantId, $id, $actorId, 'edited', '', $changes !== [] ? implode(' ; ', $changes) : 'aucun changement');

        return $this->back(true, 'Fiche corrigée.', $id);
    }

    /** POST /back-office/ressources/effectifs/sans-comptes/{id}/rattacher */
    public function attach(Request $request, array $params = []): Response
    {
        if (($denied = $this->guard($request, 'edit')) !== null) {
            return $denied;
        }
        [$tenantId, $actorId] = $this->ids();
        $ficheId = (int) ($params['id'] ?? 0);
        $fiche = $this->noAccountRow($tenantId, $ficheId);
        if ($fiche === null || !RosterOnlyMember::isRosterEmail((string) ($fiche['email'] ?? ''))) {
            return $this->back(false, 'Seule une fiche sans compte peut être rattachée à un compte.');
        }
        if ((string) $request->input('confirm', '') !== '1') {
            return $this->back(false, 'Cochez la case de confirmation : le rattachement ne se défait pas.', $ficheId);
        }
        $targetId = (int) $request->input('target_id', 0);
        if ($targetId < 1 && preg_match('/#(\d+)\s*$/', (string) $request->input('target', ''), $m) === 1) {
            $targetId = (int) $m[1];
        }
        $target = $targetId > 0 ? $this->users()->findById($targetId, $tenantId) : null;
        if ($target === null || (int) ($target['tenant_id'] ?? 0) !== $tenantId) {
            return $this->back(false, 'Choisissez un compte de la liste proposée.', $ficheId);
        }
        try {
            Container::get(UserIdentityMergeService::class)->absorbFicheIntoAccount($tenantId, $targetId, $ficheId);
        } catch (\InvalidArgumentException $e) {
            return $this->back(false, $e->getMessage(), $ficheId);
        } catch (\Throwable $e) {
            error_log('[sans-compte] rattachement : ' . $e->getMessage());

            return $this->back(false, 'Le rattachement a échoué ; rien n’a été modifié.', $ficheId);
        }
        $name = trim((string) ($target['display_name'] ?? '')) ?: ('compte #' . $targetId);
        $this->events->record($tenantId, $ficheId, $actorId, 'merged', '', 'rattachée à ' . $name . ' (#' . $targetId . ')');

        return $this->back(true, 'Fiche rattachée à ' . $name . ' : son dossier est maintenant sur ce compte.');
    }

    /** POST /back-office/ressources/effectifs/sans-comptes/importer */
    public function import(Request $request, array $params = []): Response
    {
        if (($denied = $this->guard($request, 'import')) !== null) {
            return $denied;
        }
        [$tenantId, $actorId] = $this->ids();
        $file = $_FILES['roster_file'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return $this->back(false, 'Choisissez un fichier CSV (dans Excel : Fichier → Enregistrer sous → CSV).');
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        $content = $tmp !== '' && is_readable($tmp) && (int) ($file['size'] ?? 0) <= 2 * 1024 * 1024 ? (string) file_get_contents($tmp) : '';
        if ($content === '') {
            return $this->back(false, 'Fichier illisible ou trop lourd (2 Mo au plus).');
        }
        $parsed = RosterOnlyMemberService::parseCsv($content);
        if ($parsed['error'] !== null) {
            return $this->back(false, $parsed['error']);
        }
        if (!Container::get(\App\Services\Platform\FeatureGateService::class)->canAddMember($tenantId)) {
            return $this->back(false, 'Limite de membres du plan atteinte.');
        }
        $result = $this->roster()->import($tenantId, $actorId, $parsed['rows'], function (int $id, string $name) use ($tenantId, $actorId): void {
            $this->events->record($tenantId, $id, $actorId, 'imported', 'import', $name);
        });
        $msg = $result['created'] . ' fiche(s) créée(s).';
        if ($result['skipped'] !== []) {
            $msg .= ' ' . count($result['skipped']) . ' ligne(s) ignorée(s) : ' . implode(' ', array_slice($result['skipped'], 0, 5)) . (count($result['skipped']) > 5 ? ' …' : '');
        }
        if ($result['unknown_grades'] !== []) {
            $msg .= ' Grades non reconnus (laissés vides) : ' . implode(', ', array_slice($result['unknown_grades'], 0, 8)) . '.';
        }
        if ($result['unknown_units'] !== []) {
            $msg .= ' Unités inconnues : ' . implode(', ', array_slice($result['unknown_units'], 0, 8)) . '.';
        }

        return $this->back($result['created'] > 0, $msg);
    }

    private function roster(): RosterOnlyMemberService
    {
        return new RosterOnlyMemberService(
            $this->users(),
            Container::get(UserProfileRepository::class),
            Container::get(\App\Repositories\PersonnelProfileRepository::class),
            Container::get(\App\Repositories\PersonnelAssignmentRepository::class),
            Container::get(\App\Repositories\GradeRepository::class),
            Container::get(\App\Repositories\UnitRepository::class),
        );
    }

    /**
     * La ligne users si la personne appartient à la communauté de la session ET n'a pas de compte utilisable.
     *
     * @return array<string, mixed>|null
     */
    private function noAccountRow(int $tenantId, int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $row = $this->users()->findById($id, $tenantId);
        if ($row === null || (int) ($row['tenant_id'] ?? 0) !== $tenantId) {
            return null;
        }

        return NoAccountDirectory::classify($row) === null ? null : $row;
    }

    private function guard(Request $request, string $right): ?Response
    {
        if (!(int) Session::get('user_id') || !(int) Session::get('tenant_id')) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        $allowed = match ($right) {
            'create' => EffectifsLmsAccess::canCreateMembers($gate),
            'import' => EffectifsLmsAccess::canCreateMembers($gate) && EffectifsLmsAccess::canEditProfiles($gate),
            default => EffectifsLmsAccess::canEditProfiles($gate),
        };
        if (!$allowed) {
            return $this->back(false, 'Vous n’êtes pas habilité à modifier ces fiches.');
        }
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            return $this->back(false, 'Session expirée. Rechargez la page.');
        }

        return null;
    }

    /** @return array{0: int, 1: int} */
    private function ids(): array
    {
        return [(int) Session::get('tenant_id'), (int) Session::get('user_id')];
    }

    private function text(Request $request, string $key, int $max): string
    {
        $v = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $request->input($key, '')) ?? '';

        return mb_substr(trim($v), 0, $max, 'UTF-8');
    }

    private function back(bool $ok, string $message, int $ficheId = 0): Response
    {
        Session::flash($ok ? 'success' : 'error', $message);

        return Response::redirect(effectifs_workspace_url('sans-comptes') . ($ficheId > 0 ? '?fiche=' . $ficheId : ''));
    }
}
