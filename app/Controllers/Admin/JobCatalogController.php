<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\TenantRepository;
use App\Services\Personnel\JobPrerequisiteService;
use App\Services\Personnel\JobPrerequisiteViewData;
use App\Services\Personnel\PersonnelJobRoleAssignmentsSettings;
use App\Support\EffectifsLmsAccess;
use App\Support\JobCatalog\JobCatalog;

/**
 * Emplois & fonctions : catalogue d’emplois de référence (fenêtre « Chercher dans le catalogue »), prérequis d’un
 * emploi, dérogations et réglage « prérequis bloquants ou indicatifs ».
 */
final class JobCatalogController
{
    public function __construct(
        private ?PersonnelJobRoleRepository $jobRoles = null,
        private ?TenantRepository $tenants = null,
        private ?JobPrerequisiteService $prerequisites = null,
    ) {
    }

    private function jobRoles(): PersonnelJobRoleRepository
    {
        return $this->jobRoles ??= new PersonnelJobRoleRepository();
    }

    private function tenants(): TenantRepository
    {
        return $this->tenants ??= new TenantRepository();
    }

    private function prerequisites(): JobPrerequisiteService
    {
        return $this->prerequisites ??= new JobPrerequisiteService();
    }

    private function denied(): ?Response
    {
        if (!(int) Session::get('user_id') || !(int) Session::get('tenant_id')) {
            return Response::json(['ok' => false, 'message' => 'Session expirée. Reconnectez-vous.'], 401);
        }
        if (!EffectifsLmsAccess::allows(Gate::getInstance())) {
            return Response::json(['ok' => false, 'message' => 'Accès réservé au pilotage des effectifs.'], 403);
        }

        return null;
    }

    /** Catalogue dans la langue du lecteur (JSON lu par la fenêtre de recherche). */
    public function catalogue(Request $request, array $params = []): Response
    {
        if ($denied = $this->denied()) {
            return $denied;
        }
        $lang = JobCatalog::lang(function_exists('locale') ? locale() : 'fr');
        $query = trim((string) $request->queryString('q', ''));
        $filters = [];
        foreach (['branch', 'command', 'type', 'pack'] as $f) {
            $filters[$f] = mb_substr(trim((string) $request->queryString($f, '')), 0, 40);
        }
        $entries = $query !== '' || array_filter($filters) !== [] ? JobCatalog::search(mb_substr($query, 0, 120), $filters, $lang) : JobCatalog::entries($lang);

        return Response::json([
            'ok' => true,
            'lang' => $lang,
            'packs' => JobCatalog::packSummaries($lang),
            'facets' => JobCatalog::facets($lang),
            'entries' => $entries,
        ])->header('Cache-Control', 'private, max-age=600')->header('Vary', 'Cookie');
    }

    private function writeGuard(Request $request): ?Response
    {
        if (!(int) Session::get('user_id') || !(int) Session::get('tenant_id')) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if (!EffectifsLmsAccess::canManageAssignments($gate) && !EffectifsLmsAccess::canManageRoles($gate)) {
            return $this->reply($request, false, 'Vous n’êtes pas habilité à modifier les emplois.', [], 403);
        }
        if (!Csrf::verify($request)) {
            return $this->reply($request, false, 'Session expirée. Rechargez la page avant de réessayer.', [], 419);
        }

        return null;
    }

    /** @param array<string, int|string> $query */
    private function reply(Request $request, bool $ok, string $message, array $query = [], int $status = 422, ?string $target = null): Response
    {
        $target ??= effectifs_workspace_url('fonctions') . ($query !== [] ? '?' . http_build_query($query) : '');
        if ($request->header('X-Effectifs-Fragment') === 'jobs') {
            return Response::json(['ok' => $ok, 'message' => $message, 'redirect' => $target], $ok ? 200 : $status)->header('Cache-Control', 'private, no-store');
        }
        Session::flash($ok ? 'success' : 'error', $message);

        return Response::redirect($target, 303);
    }

    /** Prérequis d’un emploi : remplace la liste (vide = aucun prérequis). */
    public function savePrerequisites(Request $request, array $params = []): Response
    {
        if ($denied = $this->writeGuard($request)) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $roleId = (int) $request->inputString('job_role_id', '0');
        $back = ['emploi' => $roleId];
        $context = $request->inputString('_workspace_category', '0');
        if (ctype_digit($context) && (int) $context > 0) {
            $back['categorie_emplois'] = (int) $context;
        }
        if ($roleId < 1 || !$this->jobRoles()->findRoleById($roleId, $tenantId)) {
            return $this->reply($request, false, 'Emploi introuvable.', [], 404);
        }
        if (!$this->prerequisites()->schemaReady()) {
            return $this->reply($request, false, 'Les prérequis arrivent avec la prochaine mise à jour du site.', $back, 503);
        }
        $raw = $request->input('crit', []);
        $rows = [];
        foreach (is_array($raw) ? $raw : [] as $row) {
            if (is_array($row) && empty($row['remove'])) {
                // Un périmètre par mesure dans le formulaire (heures de jeu, heures de vol).
                $type = (string) ($row['type'] ?? '');
                $row['scope'] = $type === 'metric:flight_hours' ? ($row['flight_scope'] ?? '') : ($type === 'metric:playtime_hours' ? ($row['playtime_scope'] ?? '') : '');
                $rows[] = $row;
            }
        }
        $items = JobPrerequisiteService::normalize($rows);
        $pdo = Database::getPdo();
        try {
            $pdo->beginTransaction();
            $this->prerequisites()->saveRequirements($tenantId, $roleId, $items, (int) Session::get('user_id'));
            $pdo->commit();
        } catch (\InvalidArgumentException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return $this->reply($request, false, $e->getMessage(), $back);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Job prerequisites save: ' . $e->getMessage());

            return $this->reply($request, false, 'Les prérequis n’ont pas été enregistrés. Réessayez.', $back, 503);
        }

        return $this->reply($request, true, $items === [] ? 'Aucun prérequis pour cet emploi.' : sprintf('%d prérequis enregistré(s). Les titulaires actuels gardent l’emploi.', count($items)), $back);
    }

    /** Révoque une dérogation : le membre garde l’emploi, ses prérequis manquants s’affichent de nouveau. */
    public function revokeWaiver(Request $request, array $params = []): Response
    {
        $memberId = (int) $request->inputString('membre', '0');
        $target = $memberId > 0 ? effectifs_workspace_url('membres/' . $memberId) . '#fonctions' : null;
        if (!(int) Session::get('user_id') || !(int) Session::get('tenant_id')) {
            return Response::redirect(url('login'));
        }
        if (!JobPrerequisiteViewData::canWaive(Gate::getInstance())) {
            return $this->reply($request, false, 'Vous n’êtes pas habilité à gérer les dérogations.', [], 403, $target);
        }
        if (!Csrf::verify($request)) {
            return $this->reply($request, false, 'Session expirée. Rechargez la page avant de réessayer.', [], 419, $target);
        }
        $tenantId = (int) Session::get('tenant_id');
        $row = $this->prerequisites()->revokeWaiver($tenantId, (int) $request->inputString('waiver_id', '0'), (int) Session::get('user_id'), (string) $request->input('revoke_reason', ''));
        if ($row === null) {
            return $this->reply($request, false, 'Dérogation introuvable ou déjà révoquée.', [], 404, $target);
        }

        return $this->reply($request, true, 'Dérogation révoquée. Le membre garde l’emploi ; ses prérequis manquants s’affichent de nouveau.', ['emploi' => (int) $row['job_role_id']], 422, $target);
    }

    /** Réglage de la communauté : prérequis bloquants (dérogation motivée obligatoire) ou indicatifs. */
    public function saveSettings(Request $request, array $params = []): Response
    {
        if ($denied = $this->writeGuard($request)) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        try {
            $settings = PersonnelJobRoleAssignmentsSettings::mergePatch($this->tenants()->getSettings($tenantId), [
                'prerequisites_blocking' => $request->input('prerequisites_blocking') === '1',
            ]);
            $this->tenants()->updateSettings($tenantId, $settings);
        } catch (\Throwable $e) {
            error_log('Job prerequisites settings: ' . $e->getMessage());

            return $this->reply($request, false, 'Le réglage n’a pas pu être enregistré.', ['vue' => 'reglages'], 503);
        }

        return $this->reply($request, true, 'Réglage des prérequis enregistré.', ['vue' => 'reglages']);
    }
}
