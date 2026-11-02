<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\JobCatalogCollectionRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\TenantRepository;
use App\Services\Community\CommunityMediaUploadService;
use App\Services\Personnel\JobPrerequisiteService;
use App\Services\Personnel\JobPrerequisiteViewData;
use App\Services\Personnel\PersonnelJobRoleAssignmentsSettings;
use App\Support\EffectifsLmsAccess;
use App\Support\JobCatalog\CommunityJobCatalog;
use App\Support\JobCatalog\JobCatalog;
use InvalidArgumentException;

/**
 * Emplois & fonctions : catalogue d’emplois de référence (fenêtre « Chercher dans le catalogue ») — catalogues
 * officiels par pays et catalogues de communauté (création, emplois, images, partage) —, prérequis d’un emploi,
 * dérogations et réglage « prérequis bloquants ou indicatifs ».
 */
final class JobCatalogController
{
    public function __construct(
        private ?PersonnelJobRoleRepository $jobRoles = null,
        private ?TenantRepository $tenants = null,
        private ?JobPrerequisiteService $prerequisites = null,
        private ?JobCatalogCollectionRepository $collections = null,
        private ?CommunityMediaUploadService $media = null,
    ) {
    }

    private function collections(): JobCatalogCollectionRepository
    {
        return $this->collections ??= new JobCatalogCollectionRepository();
    }

    private function media(): CommunityMediaUploadService
    {
        return $this->media ??= new CommunityMediaUploadService();
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

    /** Catalogue tous pays dans la langue du lecteur (JSON lu par la fenêtre de recherche, qui filtre elle-même). */
    public function catalogue(Request $request, array $params = []): Response
    {
        if ($denied = $this->denied()) {
            return $denied;
        }
        $lang = JobCatalog::lang(function_exists('locale') ? locale() : 'fr');
        $query = trim((string) $request->queryString('q', ''));
        $filters = [];
        foreach (JobCatalog::FILTERS as $f) {
            $filters[$f] = mb_substr(trim((string) $request->queryString($f, '')), 0, 40);
        }
        $entries = $query !== '' || array_filter($filters) !== [] ? JobCatalog::search(mb_substr($query, 0, 120), $filters, $lang) : JobCatalog::entries($lang);
        $community = $this->communityCatalog((int) Session::get('tenant_id'), $lang);

        return Response::json([
            'ok' => true,
            'lang' => $lang,
            'packs' => array_merge(JobCatalog::packSummaries($lang), $community['packs']),
            'countries' => array_merge(JobCatalog::countries($lang), $community['countries']),
            'facets' => JobCatalog::facets($lang),
            'entries' => array_merge($entries, $query === '' && array_filter($filters) === [] ? $community['entries'] : []),
            'community_ready' => $this->collections()->schemaReady(),
            // Qualifications du référentiel proposées pour relier celles que citent les emplois des catalogues de la communauté.
            'referentiel' => $community['referentiel'],
        ])->header('Cache-Control', 'private, no-cache')->header('Vary', 'Cookie');
    }

    /**
     * Catalogues de la communauté et catalogues partagés par d’autres, au format de la fenêtre.
     *
     * @return array{countries: list<array<string, mixed>>, packs: list<array<string, mixed>>, entries: list<array<string, mixed>>, referentiel: list<array{id: int, code: string, name: string}>}
     */
    private function communityCatalog(int $tenantId, string $lang): array
    {
        try {
            $collections = $this->collections()->visibleCollections($tenantId);
            $entries = $this->collections()->entriesFor(array_map(static fn (array $c): int => (int) $c['id'], $collections));
            $own = array_filter($collections, static fn (array $c): bool => (int) $c['tenant_id'] === $tenantId) !== [];
            $definitions = $own && $this->collections()->linksReady() ? $this->collections()->referentielDefinitions($tenantId) : [];
            $gate = Gate::getInstance();
            $canLink = EffectifsLmsAccess::canManageAssignments($gate) || EffectifsLmsAccess::canManageRoles($gate);

            return CommunityJobCatalog::toCatalog($collections, $entries, $tenantId, $lang, static fn (string $path): string => url($path), $definitions)
                + ['referentiel' => $canLink ? array_values($definitions) : []];
        } catch (\Throwable $e) {
            error_log('Job catalog collections: ' . $e->getMessage());

            return ['countries' => [], 'packs' => [], 'entries' => [], 'referentiel' => []];
        }
    }

    /** Relie une qualification citée par un emploi d’un catalogue de la communauté à une qualification de son référentiel (0 : délie). */
    public function linkQualification(Request $request, array $params = []): Response
    {
        if ($denied = $this->collectionGuard($request)) {
            return $denied;
        }
        $definitionId = (int) $request->inputString('definition_id', '0');
        try {
            $this->collections()->linkQualification((int) Session::get('tenant_id'), (int) $request->inputString('entry_id', '0'), (string) $request->input('text', ''), $definitionId, (int) Session::get('user_id'));
        } catch (InvalidArgumentException $e) {
            return $this->answer(false, $e->getMessage(), [], 422);
        } catch (\Throwable $e) {
            error_log('Job catalog qualification link: ' . $e->getMessage());

            return $this->answer(false, 'Le lien n’a pas été enregistré. Réessayez.', [], 503);
        }

        return $this->answer(true, $definitionId > 0 ? 'Qualification reliée à votre référentiel.' : 'Lien vers le référentiel retiré.');
    }

    /** Réponse JSON des écritures de la fenêtre (catalogues de communauté). */
    private function answer(bool $ok, string $message, array $extra = [], int $status = 422): Response
    {
        return Response::json(['ok' => $ok, 'message' => $message] + $extra, $ok ? 200 : $status)->header('Cache-Control', 'private, no-store');
    }

    /** Mêmes personnes que la gestion des emplois ; jeton CSRF ; tables présentes. */
    private function collectionGuard(Request $request): ?Response
    {
        if (!(int) Session::get('user_id') || !(int) Session::get('tenant_id')) {
            return $this->answer(false, 'Session expirée. Reconnectez-vous.', [], 401);
        }
        $gate = Gate::getInstance();
        if (!EffectifsLmsAccess::canManageAssignments($gate) && !EffectifsLmsAccess::canManageRoles($gate)) {
            return $this->answer(false, 'Vous n’êtes pas habilité à modifier les emplois.', [], 403);
        }
        if (!Csrf::verify($request)) {
            return $this->answer(false, 'Session expirée. Rechargez la page avant de réessayer.', [], 419);
        }
        if (!$this->collections()->schemaReady()) {
            return $this->answer(false, 'Les catalogues de communauté arrivent avec la prochaine mise à jour du site.', [], 503);
        }

        return null;
    }

    /**
     * Image envoyée (JPG, PNG ou WebP, 8 Mo maximum, contrôlée par CommunityMediaUploadService) : chemin enregistré,
     * '' si l’image est retirée, null si rien ne change.
     *
     * @param list<string> $replaced chemins remplacés, à effacer après l’enregistrement
     */
    private function upload(string $field, int $tenantId, Request $request, string $current, array &$replaced): ?string
    {
        $file = $_FILES[$field] ?? null;
        if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $stored = $this->media()->storeImage($file, $tenantId);
            if ($stored['error'] !== null || $stored['path'] === null) {
                throw new InvalidArgumentException((string) ($stored['error'] ?? 'Image non enregistrée.'));
            }
            if ($current !== '') {
                $replaced[] = $current;
            }

            return $stored['path'];
        }
        if ($request->input('remove_' . $field) === '1') {
            if ($current !== '') {
                $replaced[] = $current;
            }

            return '';
        }

        return null;
    }

    /** Crée ou modifie un catalogue de la communauté (nom, description, icône, couleur, emblème, bannière, partage). */
    public function saveCollection(Request $request, array $params = []): Response
    {
        if ($denied = $this->collectionGuard($request)) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) $request->inputString('collection_id', '0');
        $replaced = [];
        try {
            $data = CommunityJobCatalog::normalizeCollection([
                'name' => $request->input('name', ''), 'description' => $request->input('description', ''), 'icon' => $request->input('icon', ''),
                'color' => $request->input('color', ''), 'is_public' => $request->input('is_public', '0'),
            ]);
            $current = $id > 0 ? $this->collections()->findOwn($tenantId, $id) : null;
            if ($id > 0 && $current === null) {
                return $this->answer(false, 'Catalogue introuvable.', [], 404);
            }
            $emblem = $this->upload('emblem', $tenantId, $request, (string) ($current['emblem_path'] ?? ''), $replaced);
            $banner = $this->upload('banner', $tenantId, $request, (string) ($current['banner_path'] ?? ''), $replaced);
            $saved = $this->collections()->saveCollection($tenantId, $id, $data, $emblem, $banner, (int) Session::get('user_id'));
        } catch (InvalidArgumentException $e) {
            return $this->answer(false, $e->getMessage());
        } catch (\Throwable $e) {
            error_log('Job catalog collection save: ' . $e->getMessage());

            return $this->answer(false, 'Le catalogue n’a pas été enregistré. Réessayez.', [], 503);
        }
        array_map([$this->media(), 'deleteStoredFile'], $replaced);

        return $this->answer(true, $id > 0 ? 'Catalogue enregistré.' : 'Catalogue créé. Ajoutez-y vos emplois.', ['code' => CommunityJobCatalog::code($saved['id'])]);
    }

    /** Supprime un catalogue de la communauté, ses emplois et ses images. */
    public function deleteCollection(Request $request, array $params = []): Response
    {
        if ($denied = $this->collectionGuard($request)) {
            return $denied;
        }
        try {
            $paths = $this->collections()->deleteCollection((int) Session::get('tenant_id'), (int) $request->inputString('collection_id', '0'), (int) Session::get('user_id'));
        } catch (InvalidArgumentException $e) {
            return $this->answer(false, $e->getMessage(), [], 404);
        }
        array_map([$this->media(), 'deleteStoredFile'], $paths);

        return $this->answer(true, 'Catalogue supprimé avec ses emplois.');
    }

    /** Crée ou modifie un emploi d’un catalogue de la communauté. */
    public function saveEntry(Request $request, array $params = []): Response
    {
        if ($denied = $this->collectionGuard($request)) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $collectionId = (int) $request->inputString('collection_id', '0');
        $id = (int) $request->inputString('entry_id', '0');
        $replaced = [];
        try {
            $data = CommunityJobCatalog::normalizeEntry([
                'name' => $request->input('name', ''), 'abbreviation' => $request->input('abbreviation', ''), 'category' => $request->input('category', ''),
                'unit' => $request->input('unit', ''), 'type' => $request->input('type', ''), 'description' => $request->input('description', ''),
                'qualifications' => $request->input('qualifications', ''), 'icon' => $request->input('icon', ''), 'tags' => $request->input('tags', ''),
            ]);
            $current = $id > 0 ? $this->collections()->findOwnEntry($tenantId, $id) : null;
            if ($id > 0 && $current === null) {
                return $this->answer(false, 'Emploi du catalogue introuvable.', [], 404);
            }
            $image = $this->upload('image', $tenantId, $request, (string) ($current['image_path'] ?? ''), $replaced);
            $saved = $this->collections()->saveEntry($tenantId, $collectionId, $id, $data, $image, (int) Session::get('user_id'));
        } catch (InvalidArgumentException $e) {
            return $this->answer(false, $e->getMessage());
        } catch (\Throwable $e) {
            error_log('Job catalog entry save: ' . $e->getMessage());

            return $this->answer(false, 'L’emploi n’a pas été enregistré. Réessayez.', [], 503);
        }
        array_map([$this->media(), 'deleteStoredFile'], $replaced);

        return $this->answer(true, $id > 0 ? 'Emploi du catalogue enregistré.' : 'Emploi ajouté au catalogue.', ['id' => CommunityJobCatalog::code($collectionId) . '-' . $saved['id']]);
    }

    /** Supprime un emploi d’un catalogue de la communauté. */
    public function deleteEntry(Request $request, array $params = []): Response
    {
        if ($denied = $this->collectionGuard($request)) {
            return $denied;
        }
        try {
            $path = $this->collections()->deleteEntry((int) Session::get('tenant_id'), (int) $request->inputString('entry_id', '0'), (int) Session::get('user_id'));
        } catch (InvalidArgumentException $e) {
            return $this->answer(false, $e->getMessage(), [], 404);
        }
        $this->media()->deleteStoredFile($path);

        return $this->answer(true, 'Emploi retiré du catalogue.');
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
