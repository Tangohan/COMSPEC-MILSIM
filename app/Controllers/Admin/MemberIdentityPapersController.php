<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakMapRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\SseCaseRepository;
use App\Repositories\SseIdCountryRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use App\Services\Sse\MemberIdentityPapersService;
use App\Services\Sse\MemberIdentityAutoService;
use App\Services\Sse\MemberIdentitySyncService;
use App\Services\Sse\SseIdMemberPhotoService;
use App\Repositories\SseIdSettingsRepository;
use App\Services\Sse\SseClearanceService;
use App\Services\Sse\SseIdentityGeneratorService;
use App\Support\EffectifsLmsAccess;
use App\Support\SseIdentityCatalog;

/**
 * Effectifs › fiche membre › onglet « Identité & papiers », et « Ma situation › Mes papiers » (lecture seule).
 *
 * Gestion : responsables RENS (gestion SSE) ou responsables des effectifs habilités Encadrement ; vérité de jeu
 * (couvertures, faux) avec l'habilitation Confidentiel. Toutes les écritures : POST + jeton CSRF, communauté de la
 * session, membre de cette communauté, journal d'audit SSE.
 */
final class MemberIdentityPapersController
{
    /** Requête servie par le bureau SSE (« Papiers des membres ») plutôt que par la fiche membre. */
    private bool $sse = false;

    public function __construct(
        private ?MemberIdentityPapersService $papers = null,
        private ?UserRepository $users = null,
        private ?SseIdCountryRepository $countries = null,
        private ?SseClearanceService $clearance = null,
    ) {
        $this->papers ??= new MemberIdentityPapersService();
        $this->users ??= new UserRepository();
        $this->countries ??= new SseIdCountryRepository();
        $this->clearance ??= new SseClearanceService();
    }

    /**
     * Droits de la session courante sur l'onglet.
     *
     * @return array{view: bool, manage: bool, truth: bool, cover: bool, sse: bool}
     */
    public static function currentRights(?SseClearanceService $clearance = null): array
    {
        $gate = Gate::getInstance();
        $clearance ??= new SseClearanceService();
        try {
            $command = $clearance->allows(SseCaseRepository::CLASS_COMMAND);
            $confidential = $clearance->allows(SseCaseRepository::CLASS_CONFIDENTIAL);
        } catch (\Throwable) {
            $command = $confidential = false;
        }

        $sseManage = $gate->allows('atak.sse.case.manage') || $gate->allows('atak.sse.grant') || $gate->allows('admin.access');
        $rights = MemberIdentityPapersService::rights($sseManage, EffectifsLmsAccess::canEditProfiles($gate), $command, $confidential);

        return $rights + ['sse' => self::sseSurfaceAllowed($sseManage, $command)];
    }

    /**
     * Accès direct depuis le bureau SSE (sans passer par la fiche membre ni exiger un droit Effectifs) : gestion SSE
     * ou habilitation Encadrement.
     */
    public static function sseSurfaceAllowed(bool $sseManage, bool $command): bool
    {
        return $sseManage || $command;
    }

    /** URL de base des actions « papiers » d'un membre, selon la surface (fiche membre ou bureau SSE). */
    public static function baseUrl(int $userId, bool $sse): string
    {
        return $sse ? url('atak/sse/papiers/membres/' . $userId) : effectifs_workspace_url('membres/' . $userId . '/papiers');
    }

    /**
     * Données de l'onglet pour la fiche membre (null = onglet masqué).
     *
     * @param array<string, mixed> $member ligne users
     * @return array<string, mixed>|null
     */
    public static function sectionFor(int $tenantId, array $member, bool $sse = false): ?array
    {
        try {
            $rights = self::currentRights();
            if (!$rights['view']) {
                return null;
            }
            $userId = (int) ($member['id'] ?? 0);
            $service = new MemberIdentityPapersService();
            $data = $service->section($tenantId, $userId, (string) ($member['steam_id'] ?? ''), $rights['truth']);
            $data['rights'] = $rights;
            $data['proposal'] = MemberIdentityPapersService::primaryProposal(
                (new UserProfileRepository())->getByUserId($userId) ?? [],
                (new PersonnelProfileRepository())->getByUserId($userId, $tenantId) ?? [],
                $data['countries']
            );
            $data['maps'] = self::maps();
            $data['base'] = self::baseUrl($userId, $sse);
            // Couverture née en jeu : vérité de jeu, habilitation Confidentiel seulement.
            $data['game_covers'] = [];
            if ($rights['truth']) {
                try {
                    $data['game_covers'] = (new \App\Services\Sse\SseIdGameCoverService())->forUser($tenantId, $userId);
                } catch (\Throwable $e) {
                    error_log('Fiche membre : couvertures du jeu indisponibles : ' . $e->getMessage());
                }
            }
            $data['sync'] = self::syncFor($tenantId, $userId, $data, $rights['truth']);
            // Photo de profil du membre (portrait opérateur), proposée pour la photo d'identité (avec confirmation).
            $data['portrait'] = trim((string) (((new PersonnelProfileRepository())->getByUserId($userId, $tenantId) ?? [])['character_portrait_path'] ?? ''));
            $data['photo_max_mb'] = (int) (SseIdMemberPhotoService::MAX_BYTES / (1024 * 1024));

            return $data;
        } catch (\Throwable $e) {
            error_log('Fiche membre : onglet papiers indisponible : ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Transmission en jeu (état par identité et par papier), différence avec le jeu et historique des valeurs écartées.
     * Indisponible (étape 4250 pas encore passée) : diagnostic seul.
     *
     * @param array<string, mixed> $section
     * @return array{diag: array<string, mixed>, reports: list<array<string, mixed>>, history: list<array<string, mixed>>}
     */
    public static function syncFor(int $tenantId, int $userId, array $section, bool $truth): array
    {
        $realWithCover = (new SseIdSettingsRepository())->realWithCover($tenantId);
        $diag = MemberIdentitySyncService::diagnose($section, (new SseIdCountryRepository())->listForTenant($tenantId, false), null, $realWithCover, $truth ? self::briefCoverId($tenantId, $userId) : 0);
        $reports = [];
        $history = [];
        try {
            $sync = new MemberIdentitySyncService();
            $reports = $sync->gameReports($tenantId, $userId, $section['identities'], $truth);
            $history = $sync->historyFor($tenantId, $userId, $section['identities']);
        } catch (\Throwable $e) {
            error_log('Fiche membre : différence avec le jeu indisponible : ' . $e->getMessage());
        }

        return ['diag' => $diag, 'reports' => $reports, 'history' => $history];
    }

    /** Identité de couverture que la fiche de couverture du membre lui attribue (0 : aucune). */
    private static function briefCoverId(int $tenantId, int $userId): int
    {
        try {
            foreach ((new \App\Repositories\SseIdOperationRepository())->briefsForUser($tenantId, $userId) as $b) {
                if ((int) ($b['identity_id'] ?? 0) > 0) {
                    return (int) $b['identity_id'];
                }
            }
        } catch (\Throwable) {
        }

        return 0;
    }

    /**
     * « Tout générer » : identité principale complète, papiers émis selon le pays et le grade, papiers de la couverture
     * (habilitation Confidentiel). Une ligne dit ce qui a été fait.
     */
    public function generateAll(Request $request, array $params = []): Response
    {
        $ctx = $this->guard($request, $params);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId, $rights] = $ctx;
        $member = $this->users->findById($userId, $tenantId) ?? [];
        $proposal = MemberIdentityPapersService::primaryProposal(
            (new UserProfileRepository())->getByUserId($userId) ?? [],
            (new PersonnelProfileRepository())->getByUserId($userId, $tenantId) ?? [],
            $this->countries->listForTenant($tenantId)
        );
        $res = (new MemberIdentityAutoService())->generateAll($tenantId, $userId, $proposal, (int) ($member['grade_id'] ?? 0) > 0, $rights['truth'], $this->actorId());
        Session::flash($res['ok'] ? 'success' : 'error', $res['message']);

        return Response::redirect($this->back($userId) . '-synchro');
    }

    /** « Ajouter au kit » / « Ajouter automatiquement » : un clic depuis « Transmission en jeu ». */
    public function kitAdd(Request $request, array $params = []): Response
    {
        $ctx = $this->guard($request, $params);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId, $rights] = $ctx;
        $raw = $request->input('document_ids', []);
        $res = (new MemberIdentitySyncService())->kitAdd($tenantId, $userId, is_array($raw) ? array_map('intval', $raw) : [], $rights['truth'], (new SseIdSettingsRepository())->realWithCover($tenantId), $this->actorId(), self::briefCoverId($tenantId, $userId));
        Session::flash($res['ok'] ? 'success' : 'error', $res['message']);

        return Response::redirect($this->back($userId) . '-synchro');
    }

    /**
     * Photo d'identité d'une identité du membre : fichier envoyé (JPEG / PNG, 4 Mo, recadré au carré, métadonnées
     * retirées, rangé hors de la racine web) ou photo de profil du membre (from_profile=1, confirmée dans la page).
     */
    public function photoUpload(Request $request, array $params = []): Response
    {
        $ctx = $this->guard($request, $params);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId, $rights] = $ctx;
        $identity = $this->papers->ownedIdentity($tenantId, $userId, (int) ($params['iid'] ?? 0), $rights['truth']);
        if ($identity === null) {
            Session::flash('error', 'Identité introuvable.');

            return Response::redirect($this->back($userId) . '-synchro');
        }
        $photos = new SseIdMemberPhotoService();
        $res = $request->inputString('from_profile') === '1'
            ? $photos->fromPortrait((string) (((new PersonnelProfileRepository())->getByUserId($userId, $tenantId) ?? [])['character_portrait_path'] ?? ''), $tenantId)
            : $photos->storeUpload($_FILES['photo'] ?? null, $tenantId);
        if (!$res['ok']) {
            Session::flash('error', 'Photo : ' . ($res['error'] ?? 'image refusée.'));

            return Response::redirect($this->back($userId) . '-synchro');
        }
        (new \App\Repositories\SseIdentityRepository())->setImages((int) $identity['id'], $tenantId, ['photo_path' => $res['path']]);
        try {
            (new \App\Repositories\SseAuditLogRepository())->write([
                'tenant_id' => $tenantId, 'actor_user_id' => $this->actorId(), 'actor_label' => 'Fiche membre', 'action' => 'id_member.photo_set',
                'object_type' => 'sse_identity', 'object_id' => (int) $identity['id'],
                'after' => ['member_user_id' => $userId, 'source' => $request->inputString('from_profile') === '1' ? 'profile' : 'upload'],
            ]);
        } catch (\Throwable $e) {
            error_log('Photo d’identité : journal d’audit indisponible : ' . $e->getMessage());
        }
        Session::flash('success', 'Photo enregistrée : elle part en jeu avec les papiers au prochain chargement du paquet.');

        return Response::redirect($this->back($userId) . '-synchro');
    }

    /**
     * Photo d'identité privée (storage/uploads/sse-id-photos) : connexion, même communauté, droit de voir les papiers
     * des membres (fiche membre ou bureau SSE).
     */
    public function photo(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $tenant = (int) ($params['tenant'] ?? 0);
        $name = (string) ($params['name'] ?? '');
        $rights = self::currentRights($this->clearance);
        $abs = (new SseIdMemberPhotoService())->absolute(SseIdMemberPhotoService::PREFIX . SseIdMemberPhotoService::DIR . '/' . $tenant . '/' . $name);
        if ((int) Session::get('user_id') < 1 || $tenantId < 1 || $tenant !== $tenantId || !($rights['view'] || $rights['sse']) || $abs === null || !is_file($abs)) {
            return Response::json(['error' => 'not_found'], 404);
        }
        $response = new Response();
        $response->header('Content-Type', str_ends_with($name, '.png') ? 'image/png' : 'image/jpeg')
            ->header('Cache-Control', 'private, max-age=3600')
            ->header('X-Content-Type-Options', 'nosniff')
            ->setBodyStream(static function () use ($abs): void {
                $fh = fopen($abs, 'rb');
                if ($fh !== false) {
                    fpassthru($fh);
                    fclose($fh);
                }
            });

        return $response;
    }

    /** « Différence avec le jeu » : reprise champ par champ, ou « Garder les deux ». */
    public function syncResolve(Request $request, array $params = []): Response
    {
        $ctx = $this->guard($request, $params);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId, $rights] = $ctx;
        $raw = $request->input('choice', []);
        $choices = [];
        if (is_array($raw)) {
            foreach ($raw as $field => $choice) {
                if (is_string($field) && isset(MemberIdentitySyncService::FIELDS[$field]) && in_array($choice, ['athena', 'game'], true)) {
                    $choices[$field] = $choice;
                }
            }
        }
        $res = (new MemberIdentitySyncService())->resolve(
            $tenantId,
            $userId,
            (int) $request->input('game_cover_id', 0),
            $choices,
            $request->inputString('keep_both') === '1',
            $rights['truth'],
            $this->actorId()
        );
        Session::flash($res['ok'] ? 'success' : 'error', $res['message']);

        return Response::redirect($this->back($userId) . '-synchro');
    }

    /** Historique : remettre une valeur écartée. */
    public function syncRestore(Request $request, array $params = []): Response
    {
        $ctx = $this->guard($request, $params);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId, $rights] = $ctx;
        $res = (new MemberIdentitySyncService())->restore($tenantId, $userId, (int) ($params['hid'] ?? 0), $rights['truth'], $this->actorId());
        Session::flash($res['ok'] ? 'success' : 'error', $res['message']);

        return Response::redirect($this->back($userId) . '-synchro');
    }

    /** « Envoyer la version Athena vers le jeu » : kit par défaut composé des papiers valables de l'identité. */
    public function syncSend(Request $request, array $params = []): Response
    {
        $ctx = $this->guard($request, $params);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId, $rights] = $ctx;
        $res = (new MemberIdentitySyncService())->sendToGame($tenantId, $userId, (int) $request->input('identity_id', 0), $rights['truth'], $this->actorId());
        Session::flash($res['ok'] ? 'success' : 'error', $res['message']);

        return Response::redirect($this->back($userId) . '-synchro');
    }

    public function savePrimary(Request $request, array $params = []): Response
    {
        $ctx = $this->guard($request, $params);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId, $rights] = $ctx;
        $res = $this->papers->savePrimary($tenantId, $userId, $this->identityInput($request), $this->actorId());
        Session::flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Identité principale enregistrée.' : (string) $res['error']);

        return Response::redirect($this->back($userId));
    }

    public function createAlias(Request $request, array $params = []): Response
    {
        $ctx = $this->guard($request, $params);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId, $rights] = $ctx;
        $input = $this->identityInput($request);
        $input['kind'] = $request->inputString('kind');
        if ($request->inputString('generate') === '1' && (trim($input['last_name']) === '' || trim($input['first_name']) === '')) {
            $country = $this->countries->findByCode($tenantId, $input['nationality']) ?? ($this->countries->listForTenant($tenantId)[0] ?? null);
            if ($country !== null) {
                $generated = (new SseIdentityGeneratorService())->generate($country, null, in_array($input['sex'], ['M', 'F'], true) ? $input['sex'] : null);
                unset($generated['kind']);
                $input = array_merge($input, $generated);
            }
        }
        $res = $this->papers->createAlias($tenantId, $userId, $input, $rights['truth'], $this->actorId());
        Session::flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Identité d’emprunt créée. Ajoutez-lui un papier.' : (string) $res['error']);

        return Response::redirect($this->back($userId));
    }

    /** Formulaire de création (identity_id, country, doc_type, generer) ou de modification ({docId}). */
    public function documentForm(Request $request, array $params = []): Response
    {
        $ctx = $this->guard($request, $params, false);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId, $rights] = $ctx;
        $member = $this->users->findById($userId, $tenantId) ?? [];
        $docId = (int) ($params['docId'] ?? 0);
        $doc = null;
        if ($docId > 0) {
            $doc = $this->papers->ownedDocument($tenantId, $userId, $docId, $rights['truth']);
            if ($doc === null) {
                Session::flash('error', 'Papier introuvable.');

                return Response::redirect($this->back($userId));
            }
        }
        $identityId = $doc !== null ? (int) $doc['identity_id'] : (int) $request->query('identity_id', 0);
        $identity = $this->papers->ownedIdentity($tenantId, $userId, $identityId, $rights['truth']);
        if ($identity === null) {
            Session::flash('error', 'Créez d’abord l’identité qui portera ce papier.');

            return Response::redirect($this->back($userId));
        }
        $countries = $this->countries->listForTenant($tenantId);
        $code = $doc !== null ? (string) $doc['country_code'] : strtoupper($request->queryString('country', (string) ($identity['nationality'] ?? '')));
        $country = $this->countries->findByCode($tenantId, $code);
        if ($country === null || ($doc === null && !$country['active'])) {
            $country = $countries[0] ?? null;
        }
        $type = $doc !== null ? (string) $doc['doc_type'] : SseIdentityCatalog::pick(SseIdentityCatalog::DOC_TYPES, $request->queryString('doc_type'), 'id_card');
        if ($country !== null && $doc === null && !in_array($type, $country['doc_types'], true)) {
            $type = (string) ($country['doc_types'][0] ?? 'id_card');
        }
        $proposal = $country !== null ? $this->papers->proposal($tenantId, $identity, $country, $type) : null;
        $values = $doc !== null ? [
            'doc_number' => $doc['doc_number'], 'issued_on' => (string) $doc['issued_on'], 'expires_on' => (string) $doc['expires_on'],
            'authority' => (string) $doc['authority'], 'context_id' => (int) ($doc['context_id'] ?? 0), 'op_key' => (string) ($doc['op_key'] ?? ''),
            'notes' => (string) ($doc['notes'] ?? ''),
        ] : [
            'doc_number' => $proposal['doc_number'] ?? '', 'issued_on' => $proposal['issued_on'] ?? '', 'expires_on' => $proposal['expires_on'] ?? '',
            'authority' => $proposal['authority'] ?? '', 'context_id' => (int) ($identity['context_id'] ?? 0), 'op_key' => (string) ($identity['op_key'] ?? ''),
            'notes' => '',
        ];
        if ($doc !== null && $request->queryString('generer') === '1' && $proposal !== null) {
            // « Générer » sur un papier existant : nouveau numéro et nouvelles dates proposés, rien n'est enregistré.
            $values['doc_number'] = $proposal['doc_number'];
            $values['issued_on'] = $proposal['issued_on'];
            $values['expires_on'] = $proposal['expires_on'];
        }
        $name = MemberIdentityPapersService::displayName($identity);

        $data = [
            'title' => $doc !== null ? 'Modifier le papier' : 'Nouveau papier',
            'content' => 'admin.effectifs_workspace.member_paper_form',
            'isBackOfficeShell' => true,
            'boPageGroup' => 'Effectifs',
            'boSkipPageHead' => false,
            'boPageTitle' => ($doc !== null ? 'Modifier le papier' : 'Nouveau papier') . ' — ' . $name,
            'boPageKicker' => 'EFFECTIFS · IDENTITÉ & PAPIERS',
            'boPageSubtitle' => 'Papier fictif pour le jeu, marqué SIMULATION. Il ne part en jeu qu’une fois émis.',
            'backOfficePageCss' => ['sse-papers.css', 'back-office-member-papers.css'],
            'member' => $member,
            'memberId' => $userId,
            'identity' => $identity,
            'identityName' => $name,
            'doc' => $doc,
            'docType' => $type,
            'country' => $country,
            'countries' => $countries,
            'values' => $values,
            'mrzPreview' => $proposal['mrz'] ?? [],
            'maps' => self::maps(),
            'canSeeTruth' => $rights['truth'],
            'base' => self::baseUrl($userId, $this->sse),
            'backUrl' => $this->back($userId),
            'regenUrl' => $doc !== null
                ? self::baseUrl($userId, $this->sse) . '/documents/' . (int) $doc['id'] . '/modifier?generer=1'
                : self::baseUrl($userId, $this->sse) . '/documents/nouveau?' . http_build_query([
                    'identity_id' => (int) $identity['id'], 'country' => (string) ($country['code'] ?? ''), 'doc_type' => $type, 'generer' => 1,
                ]),
            'csrfToken' => Csrf::token(),
        ];
        if ($this->sse) {
            $data['backLabel'] = 'Papiers de ';

            return (new \App\Controllers\Web\SseIdentityRegistryController())->page('atak.sse.papers.member_paper_form', $data + ['papersTab' => 'membres']);
        }

        return Response::view('layout.main', $data);
    }

    public function documentStore(Request $request, array $params = []): Response
    {
        return $this->saveDocument($request, $params, false);
    }

    public function documentUpdate(Request $request, array $params = []): Response
    {
        return $this->saveDocument($request, $params, true);
    }

    public function documentAction(Request $request, array $params = []): Response
    {
        $ctx = $this->guard($request, $params);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId, $rights] = $ctx;
        $doc = $this->papers->ownedDocument($tenantId, $userId, (int) ($params['docId'] ?? 0), $rights['truth']);
        $action = $request->inputString('do');
        if ($doc === null || !in_array($action, MemberIdentityPapersService::ACTIONS, true)) {
            Session::flash('error', 'Papier introuvable.');

            return Response::redirect($this->back($userId));
        }
        $res = $this->papers->action($tenantId, $userId, $doc, $action, $request->inputString('reason'), $this->actorId());
        Session::flash($res['ok'] ? 'success' : 'error', $res['message']);
        if ($res['ok'] && isset($res['id'])) {
            return Response::redirect(self::baseUrl($userId, $this->sse) . '/documents/' . (int) $res['id'] . '/modifier');
        }

        return Response::redirect($this->back($userId));
    }

    public function kitSave(Request $request, array $params = []): Response
    {
        $ctx = $this->guard($request, $params);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId, $rights] = $ctx;
        $raw = $request->input('document_ids', []);
        $ids = is_array($raw) ? array_map('intval', $raw) : [];
        $n = $this->papers->saveKit(
            $tenantId,
            $userId,
            (int) $request->input('context_id', 0),
            $request->inputString('op_key'),
            $ids,
            $rights['truth'],
            $this->actorId()
        );
        Session::flash('success', $n > 0 ? 'Kit de mission enregistré : ' . $n . ' papier(s).' : 'Kit de mission retiré pour cette portée.');

        return Response::redirect($this->back($userId) . '-kit');
    }

    /** « Ma situation › Mes papiers » : lecture seule de ses propres papiers. */
    public function mine(Request $request, array $params = []): Response
    {
        $userId = (int) Session::get('user_id');
        $tenantId = (int) Session::get('tenant_id');
        if ($userId < 1 || $tenantId < 1) {
            return Response::redirect(url('login'));
        }
        $user = $this->users->findById($userId, $tenantId) ?? [];
        $data = ['identities' => [], 'documents' => [], 'kits' => [], 'checks' => [], 'steam_linked' => false];
        try {
            $data = $this->papers->memberView($tenantId, $userId, (string) ($user['steam_id'] ?? ''));
        } catch (\Throwable $e) {
            error_log('Mes papiers indisponibles : ' . $e->getMessage());
        }
        $briefs = [];
        try {
            // Fiche de couverture remise au joueur (COMSPEC IDENTITY, lot 6) : la sienne seulement.
            $briefs = (new \App\Repositories\SseIdOperationRepository())->briefsForUser($tenantId, $userId);
        } catch (\Throwable $e) {
            error_log('Mes papiers : briefs indisponibles : ' . $e->getMessage());
        }

        $gameCovers = [];
        try {
            // Couverture née en jeu et remontée par son téléphone : la sienne seulement.
            $gameCovers = (new \App\Services\Sse\SseIdGameCoverService())->forUser($tenantId, $userId);
        } catch (\Throwable $e) {
            error_log('Mes papiers : couvertures du jeu indisponibles : ' . $e->getMessage());
        }

        $coverPanel = null;
        try {
            $coverPanel = (new \App\Services\Sse\SseCoverExportService())->panel($tenantId, $userId, false, true);
        } catch (\Throwable $e) {
            error_log('Mes papiers : couvertures générées indisponibles : ' . $e->getMessage());
        }

        return Response::view('layout.main', [
            'title' => 'Mes papiers',
            'content' => 'admin.member_situation.mes_papiers',
            'isBackOfficeShell' => true,
            'boPageGroup' => 'Opérateur',
            'boSkipPageHead' => false,
            'boPageTitle' => 'Mes papiers',
            'boPageKicker' => 'OPÉRATEUR · MES PAPIERS',
            'boPageSubtitle' => 'Les papiers d’identité fictifs que votre personnage porte en mission. Ils sont gérés par les responsables de votre communauté.',
            'backOfficePageCss' => ['back-office-member-situation.css', 'sse-papers.css', 'back-office-member-papers.css'],
            'papers' => $data,
            'briefs' => $briefs,
            'gameCovers' => $gameCovers,
            'coverStatus' => \App\Services\Sse\SseIdGameCoverService::status($data['steam_linked'], $briefs, $data['kits'], $gameCovers),
            'maps' => self::maps(),
            'coverPanel' => $coverPanel,
        ]);
    }

    // ------------------------------------------------------------------ Outils

    private function saveDocument(Request $request, array $params, bool $update): Response
    {
        $ctx = $this->guard($request, $params);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId, $rights] = $ctx;
        $doc = null;
        if ($update) {
            $doc = $this->papers->ownedDocument($tenantId, $userId, (int) ($params['docId'] ?? 0), $rights['truth']);
            if ($doc === null) {
                Session::flash('error', 'Papier introuvable.');

                return Response::redirect($this->back($userId));
            }
        }
        $identityId = $doc !== null ? (int) $doc['identity_id'] : (int) $request->input('identity_id', 0);
        $identity = $this->papers->ownedIdentity($tenantId, $userId, $identityId, $rights['truth']);
        if ($identity === null) {
            Session::flash('error', 'Identité introuvable.');

            return Response::redirect($this->back($userId));
        }
        $input = [];
        foreach (['doc_type', 'country_code', 'doc_number', 'issued_on', 'expires_on', 'authority', 'op_key', 'notes', 'st_origin', 'forgery_kind', 'forgery_quality'] as $k) {
            $input[$k] = $request->inputString($k);
        }
        $input['context_id'] = (int) $request->input('context_id', 0);
        $res = $this->papers->saveDocument($tenantId, $userId, $identity, $doc, $input, $rights['truth'], $this->actorId());
        if (!$res['ok']) {
            Session::flash('error', (string) $res['error']);
            $form = $doc !== null
                ? self::baseUrl($userId, $this->sse) . '/documents/' . (int) $doc['id'] . '/modifier'
                : self::baseUrl($userId, $this->sse) . '/documents/nouveau?identity_id=' . (int) $identity['id']
                    . '&country=' . urlencode($input['country_code']) . '&doc_type=' . urlencode($input['doc_type']);

            return Response::redirect($form);
        }
        Session::flash('success', $doc !== null ? 'Papier enregistré.' : 'Papier créé en brouillon. Émettez-le pour qu’il parte en jeu.');

        return Response::redirect($this->back($userId));
    }

    /**
     * Contrôles communs : connecté, droit de gestion (ou de lecture pour un GET), membre de la communauté, CSRF.
     *
     * @return array{0: int, 1: int, 2: array{view: bool, manage: bool, truth: bool, cover: bool}}|Response
     */
    private function guard(Request $request, array $params, bool $write = true): array|Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ((int) Session::get('user_id') < 1 || $tenantId < 1) {
            return Response::redirect(url('login'));
        }
        $this->sse = str_starts_with(ltrim($request->path(), '/'), 'atak/sse/');
        $home = $this->sse ? url('atak/sse/papiers/membres') : effectifs_workspace_url();
        $userId = (int) ($params['id'] ?? 0);
        $rights = self::currentRights($this->clearance);
        // Accès invité au portail SSE (code d'accès) : jamais de gestion des papiers des membres.
        $guest = $this->sse && (new \App\Services\Sse\SseAccessCodeService())->isGuest();
        if (!$rights['manage'] || ($this->sse && (!$rights['sse'] || $guest))) {
            Session::flash('error', 'Vous n’êtes pas habilité à gérer les papiers d’identité des membres.');

            return Response::redirect($userId > 0 && (!$this->sse || $rights['sse']) ? $this->back($userId) : ($this->sse ? url('atak/sse') : $home));
        }
        if ($userId < 1 || $this->users->findById($userId, $tenantId) === null) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect($home);
        }
        if ($write && !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($this->back($userId));
        }

        return [$tenantId, $userId, $rights];
    }

    /** @return array{last_name: string, first_name: string, sex: string, birth_date: string, birth_place: string, nationality: string, profession: string} */
    private function identityInput(Request $request): array
    {
        return [
            'last_name' => trim($request->inputString('last_name')),
            'first_name' => trim($request->inputString('first_name')),
            'sex' => strtoupper($request->inputString('sex')),
            'birth_date' => $request->inputString('birth_date'),
            'birth_place' => $request->inputString('birth_place'),
            'nationality' => strtoupper($request->inputString('nationality')),
            'profession' => $request->inputString('profession'),
        ];
    }

    private function back(int $userId): string
    {
        return ($this->sse ? self::baseUrl($userId, true) : effectifs_workspace_url('membres/' . $userId)) . '#papiers';
    }

    private function actorId(): ?int
    {
        $id = (int) Session::get('user_id');

        return $id > 0 ? $id : null;
    }

    /** @return list<array{id: int, label: string}> */
    public static function maps(): array
    {
        try {
            $out = [];
            foreach ((new AtakMapRepository())->getAll() as $m) {
                $out[] = ['id' => (int) $m['id'], 'label' => (string) ($m['name'] ?? $m['display_name'] ?? $m['slug'] ?? ('#' . $m['id']))];
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }
}
