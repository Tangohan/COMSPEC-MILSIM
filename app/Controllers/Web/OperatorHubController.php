<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\OperatorHubRepository;
use App\Services\Operator\OperatorHubCatalog;
use App\Services\Operator\OperatorIdentityService;
use App\Services\Security\FileRateLimiter;
use App\Support\SseFileSubjectCatalog;
use App\Support\SseIdentityCatalog;

/**
 * Outils opérateur : page d'accueil (/operateur) et « Mon identité » (/operateur/identite : identités, papiers,
 * couvertures) de chaque membre.
 *
 * Toutes les routes : membre connecté (AuthMiddleware) d'une communauté ; il n'agit que sur son propre compte (aucun
 * identifiant de membre n'est lu dans la requête). Écritures : POST + jeton CSRF + limite de fréquence par membre.
 * Les dépendances se remplacent dans les tests (contexte, droits, rendu, jeton, messages).
 */
final class OperatorHubController
{
    public const TABS = ['identites' => 'Identités', 'documents' => 'Papiers', 'couverture' => 'Couvertures'];
    private const WRITES_PER_10_MIN = 40;
    private const UPLOADS_PER_HOUR = 12;

    public function __construct(
        private ?OperatorHubRepository $hub = null,
        private ?OperatorIdentityService $identity = null,
        /** @var (\Closure(): (array{tenant: int, user: int, row: array<string, mixed>}|null))|null */
        private ?\Closure $context = null,
        /** @var (\Closure(string): bool)|null */
        private ?\Closure $can = null,
        /** @var (\Closure(string): bool)|null */
        private ?\Closure $allowsPath = null,
        /** @var (\Closure(array<string, mixed>): Response)|null */
        private ?\Closure $render = null,
        /** @var (\Closure(Request): bool)|null */
        private ?\Closure $csrf = null,
        /** @var (\Closure(string, string): void)|null */
        private ?\Closure $flash = null,
        private ?FileRateLimiter $limiter = null,
        /** @var (\Closure(int, int): list<array<string, mixed>>)|null terminaux ATAK du membre */
        private ?\Closure $terminals = null,
    ) {
        $this->can ??= static fn (string $p): bool => function_exists('can') && can($p);
        $this->allowsPath ??= static fn (string $path): bool => !function_exists('portal_nav_href_permission_allowed') || portal_nav_href_permission_allowed(url($path));
        $this->render ??= static fn (array $data): Response => Response::view('layout.main', $data);
        $this->csrf ??= static fn (Request $r): bool => Csrf::validate((string) $r->input('_csrf_token', '')) || Csrf::verify($r);
        $this->flash ??= static function (string $type, string $message): void {
            Session::flash($type, $message);
        };
        $this->context ??= static function (): ?array {
            $userId = (int) (Session::get('user_id') ?? 0);
            $tenantId = (int) (Session::get('tenant_id') ?? 0);
            if ($userId < 1 || $tenantId < 1) {
                return null;
            }
            $row = (new \App\Repositories\UserRepository())->findById($userId, $tenantId);

            return is_array($row) ? ['tenant' => $tenantId, 'user' => $userId, 'row' => $row] : null;
        };
        $this->terminals ??= static function (int $tenantId, int $userId): array {
            try {
                return \App\Core\Container::get(\App\Repositories\AtakRealismRepository::class)->listPhysicalTerminalsForUser($tenantId, $userId);
            } catch (\Throwable) {
                return [];
            }
        };
    }

    // ------------------------------------------------------------------ Page d'accueil des outils

    public function index(Request $request, array $params = []): Response
    {
        $ctx = ($this->context)();
        if ($ctx === null) {
            return Response::redirect(url('dashboard'));
        }

        return ($this->render)([
            'title' => 'Outils opérateur',
            'content' => 'operateur.hub',
            'athena_header_section' => 'Outils opérateur',
            'csrfToken' => Csrf::token(),
        ] + $this->hubData($ctx));
    }

    /**
     * Données de la page : outils visibles rangés (favoris, groupes), bande d'en-tête, pastilles.
     *
     * @param array{tenant: int, user: int, row: array<string, mixed>} $ctx
     * @return array<string, mixed>
     */
    public function hubData(array $ctx): array
    {
        [$tenantId, $userId] = [$ctx['tenant'], $ctx['user']];
        $tools = OperatorHubCatalog::visible($this->can, $this->allowsPath);
        $favorites = [];
        try {
            $favorites = $this->hub()->favorites($tenantId, $userId);
        } catch (\Throwable $e) {
            error_log('Outils opérateur : favoris indisponibles : ' . $e->getMessage());
        }
        $summary = null;
        try {
            $summary = $this->identity()->summary($tenantId, $userId);
        } catch (\Throwable $e) {
            error_log('Outils opérateur : synthèse d’identité indisponible : ' . $e->getMessage());
        }
        $atak = self::atakLink(($this->terminals)($tenantId, $userId));
        $badges = self::badges($summary, $atak);
        $row = $ctx['row'];
        $callsign = trim((string) ($row['callsign'] ?? '')) ?: trim((string) ($row['display_name'] ?? '')) ?: 'Opérateur';
        $unit = '';
        if (function_exists('portal_header_context')) {
            try {
                $unit = (string) (portal_header_context()['tenant_label'] ?? '');
            } catch (\Throwable) {
                $unit = '';
            }
        }

        return [
            'hubLayout' => OperatorHubCatalog::layout($tools, $favorites, $badges),
            'hubCallsign' => $callsign,
            'hubUnit' => $unit,
            'hubFigures' => self::figures($summary, $atak),
            'hubToolCount' => count($tools),
        ];
    }

    /**
     * Liaison ATAK d'après les terminaux du membre : relié si un terminal n'est pas révoqué ; dernière activité.
     *
     * @param list<array<string, mixed>> $terminals
     * @return array{linked: bool, last_seen: ?string}
     */
    public static function atakLink(array $terminals): array
    {
        $linked = false;
        $last = null;
        foreach ($terminals as $t) {
            if (strtolower((string) ($t['status'] ?? '')) === 'revoked') {
                continue;
            }
            $linked = true;
            $seen = (string) ($t['last_seen_at'] ?? '');
            if ($seen !== '' && ($last === null || $seen > $last)) {
                $last = $seen;
            }
        }

        return ['linked' => $linked, 'last_seen' => $last];
    }

    /**
     * @param array<string, mixed>|null $summary OperatorIdentityService::summary()
     * @param array{linked: bool, last_seen: ?string} $atak
     * @return list<array{label: string, value: string, hint: string, tone: string, href: string}>
     */
    public static function figures(?array $summary, array $atak): array
    {
        $seen = $atak['last_seen'] !== null ? substr($atak['last_seen'], 8, 2) . '/' . substr($atak['last_seen'], 5, 2) . '/' . substr($atak['last_seen'], 0, 4) : '';
        $out = [[
            'label' => 'ATAK',
            'value' => $atak['linked'] ? 'Relié' : 'Non relié',
            'hint' => $atak['linked']
                ? ($atak['last_seen'] !== null ? 'Dernière session le ' . $seen : 'Aucune session encore')
                : 'Reliez le téléphone du jeu',
            'tone' => $atak['linked'] ? 'ok' : 'warn',
            'href' => 'lier',
        ]];
        if ($summary === null) {
            return $out;
        }
        $missing = (int) $summary['identity_missing'];
        $out[] = [
            'label' => 'Identité',
            'value' => !$summary['has_primary'] ? 'À créer' : ($missing > 0 ? $missing . ' à compléter' : 'Complète'),
            'hint' => !$summary['has_primary'] ? 'Votre identité en jeu' : ($missing > 0 ? 'Champs manquants' : 'Rien à ajouter'),
            'tone' => $summary['has_primary'] && $missing === 0 ? 'ok' : 'warn',
            'href' => 'operateur/identite',
        ];
        $attention = (int) $summary['papers_attention'];
        $pending = (int) $summary['papers_pending'];
        $valid = (int) $summary['papers_valid'];
        $out[] = [
            'label' => 'Papiers',
            'value' => $attention > 0 ? $attention . ' à traiter' : ($valid > 0 ? 'À jour' : ($pending > 0 ? 'En attente' : 'Aucun')),
            'hint' => $attention > 0 ? 'Expiré, perdu ou volé' : ($valid > 0 ? $valid . ' en cours de validité' : ($pending > 0 ? $pending . ' demande(s)' : 'Demandez votre premier papier')),
            'tone' => $attention > 0 ? 'bad' : ($valid > 0 ? 'ok' : 'warn'),
            'href' => 'operateur/identite/documents',
        ];
        $out[] = [
            'label' => 'Couverture',
            'value' => $summary['cover_active'] !== null ? 'Active' : ((int) $summary['covers'] > 0 ? 'Aucune active' : 'Aucune'),
            'hint' => $summary['cover_active'] !== null ? (string) $summary['cover_active'] : 'Facultatif',
            'tone' => $summary['cover_active'] !== null ? 'ok' : 'idle',
            'href' => 'operateur/identite/couverture',
        ];

        return $out;
    }

    /**
     * Pastilles d'état des cartes.
     *
     * @param array<string, mixed>|null $summary
     * @param array{linked: bool, last_seen: ?string} $atak
     * @return array<string, array{label: string, tone: string}>
     */
    public static function badges(?array $summary, array $atak): array
    {
        $out = ['lier' => $atak['linked'] ? ['label' => 'Relié', 'tone' => 'ok'] : ['label' => 'À relier', 'tone' => 'warn']];
        if ($summary === null) {
            return $out;
        }
        $missing = (int) $summary['identity_missing'];
        $out['identites'] = !$summary['has_primary'] ? ['label' => 'À créer', 'tone' => 'warn']
            : ($missing > 0 ? ['label' => $missing . ' à compléter', 'tone' => 'warn'] : ['label' => 'À jour', 'tone' => 'ok']);
        if ((int) $summary['papers_attention'] > 0) {
            $out['documents'] = ['label' => (int) $summary['papers_attention'] . ' à traiter', 'tone' => 'bad'];
        } elseif ((int) $summary['papers_pending'] > 0) {
            $out['documents'] = ['label' => (int) $summary['papers_pending'] . ' en attente', 'tone' => 'wait'];
        } elseif ((int) $summary['papers_valid'] > 0) {
            $out['documents'] = ['label' => 'À jour', 'tone' => 'ok'];
        }
        if ($summary['cover_active'] !== null) {
            $out['couverture'] = ['label' => 'Active', 'tone' => 'ok'];
        }

        return $out;
    }

    /** Épingle ou retire un outil (réponse JSON pour le bouton, sinon retour à la page). */
    public function favorite(Request $request, array $params = []): Response
    {
        $ctx = ($this->context)();
        $json = str_contains((string) $request->header('Accept'), 'application/json');
        $fail = fn (string $message, int $code): Response => $json
            ? Response::json(['ok' => false, 'message' => $message], $code)
            : $this->back('operateur', 'error', $message);
        if ($ctx === null) {
            return $json ? Response::json(['ok' => false, 'message' => 'Connectez-vous.'], 401) : Response::redirect(url('login'));
        }
        if (!($this->csrf)($request)) {
            return $fail('Session expirée. Réessayez.', 419);
        }
        $key = $request->inputString('tool');
        // Seul un outil que le membre peut ouvrir s'épingle.
        $visible = array_column(OperatorHubCatalog::visible($this->can, $this->allowsPath), 'key');
        if (!in_array($key, $visible, true)) {
            return $fail('Outil inconnu.', 404);
        }
        if ($this->throttled('fav', $ctx['user'], 60, 600)) {
            return $fail('Trop de changements en peu de temps. Réessayez dans quelques minutes.', 429);
        }
        $state = $this->hub()->toggleFavorite($ctx['tenant'], $ctx['user'], $key);
        if ($state === null) {
            return $fail('Vous avez déjà ' . OperatorHubRepository::MAX_FAVORITES . ' outils épinglés.', 422);
        }

        return $json ? Response::json(['ok' => true, 'favorite' => $state]) : Response::redirect(url('operateur'));
    }

    // ------------------------------------------------------------------ Mon identité

    public function identities(Request $request, array $params = []): Response
    {
        return $this->area('identites', $request);
    }

    public function documents(Request $request, array $params = []): Response
    {
        return $this->area('documents', $request);
    }

    public function covers(Request $request, array $params = []): Response
    {
        return $this->area('couverture', $request);
    }

    private function area(string $tab, Request $request): Response
    {
        $ctx = ($this->context)();
        if ($ctx === null) {
            return Response::redirect(url('dashboard'));
        }

        return ($this->render)([
            'title' => 'Mon identité',
            'content' => 'operateur.identite',
            'athena_header_section' => 'Outils opérateur',
            'csrfToken' => Csrf::token(),
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
        ] + $this->areaData($ctx, $tab, (int) $request->queryString('modifier', '0')));
    }

    /**
     * @param array{tenant: int, user: int, row: array<string, mixed>} $ctx
     * @return array<string, mixed>
     */
    public function areaData(array $ctx, string $tab, int $editId = 0): array
    {
        [$tenantId, $userId] = [$ctx['tenant'], $ctx['user']];
        $tab = isset(self::TABS[$tab]) ? $tab : 'identites';
        $svc = $this->identity();
        $data = [
            'idTab' => $tab,
            'idTabs' => self::TABS,
            'idIdentities' => [],
            'idDocuments' => [],
            'idCovers' => [],
            'idCountries' => [],
            'idHistory' => [],
            'idSync' => null,
            'idCoverQuota' => 0,
            'idEditId' => $editId,
            'idUnavailable' => false,
            'idSteamLinked' => preg_replace('/\D/', '', (string) ($ctx['row']['steam_id'] ?? '')) !== '',
            'idHair' => SseFileSubjectCatalog::HAIR,
            'idEyes' => SseFileSubjectCatalog::EYES,
            'idDocTypes' => SseIdentityCatalog::DOC_TYPES,
            // Responsables : lien vers la gestion des papiers des membres (Bureau SSE), selon leurs droits habituels.
            'idManagerUrl' => (($this->can)('atak.sse.case.manage') || ($this->can)('atak.sse.grant') || ($this->can)('admin.organization')) && ($this->allowsPath)('atak/sse/papiers/membres')
                ? url('atak/sse/papiers/membres') : null,
        ];
        try {
            $data['idIdentities'] = $svc->identities($tenantId, $userId);
            $data['idCountries'] = $svc->countries($tenantId);
            if ($tab !== 'couverture') {
                $data['idDocuments'] = $svc->documents($tenantId, $userId);
            }
            if ($tab === 'documents') {
                $data['idSync'] = self::syncSummary($tenantId, $userId, (string) ($ctx['row']['steam_id'] ?? ''));
            }
            if ($tab === 'couverture') {
                $data['idCovers'] = $svc->covers($tenantId, $userId);
                $data['idCoverQuota'] = $svc->coverQuota($tenantId, $userId);
            }
            $data['idHistory'] = $svc->history($tenantId, $userId, 12);
        } catch (\Throwable $e) {
            error_log('Mon identité indisponible : ' . $e->getMessage());
            $data['idUnavailable'] = true;
        }

        return $data;
    }

    /**
     * État de la remise des papiers en jeu (même diagnostic que la fiche membre, « papiers-synchro »), en clair.
     *
     * @return array{state: string, state_label: string, delivery: string, title: string, why: string, todo: string, at: string}|null
     */
    public static function syncSummary(int $tenantId, int $userId, string $steamId): ?array
    {
        try {
            $section = (new \App\Services\Sse\MemberIdentityPapersService())->section($tenantId, $userId, $steamId, true);
            $sync = \App\Controllers\Admin\MemberIdentityPapersController::syncFor($tenantId, $userId, $section, true);
            $diag = $sync['diag'];
            $delivery = $diag['delivery'] ?? [];

            return [
                'state' => (string) ($diag['state'] ?? 'blocked'),
                'state_label' => \App\Services\Sse\MemberIdentitySyncService::STATES[(string) ($diag['state'] ?? '')] ?? '—',
                'delivery' => (string) ($delivery['state'] ?? 'unknown'),
                'title' => (string) ($delivery['title'] ?? ''),
                'why' => (string) ($delivery['why'] ?? ''),
                'todo' => (string) ($delivery['todo'] ?? ''),
                'at' => (string) ($delivery['at'] ?? ''),
            ];
        } catch (\Throwable $e) {
            error_log('Mon identité : état de remise en jeu indisponible : ' . $e->getMessage());

            return null;
        }
    }

    // ---- Écritures : identités

    public function identitySave(Request $request, array $params = []): Response
    {
        $ctx = $this->guardWrite($request, 'operateur/identite');
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $id = (int) ($params['id'] ?? 0);
        $data = [];
        foreach (['last_name', 'first_name', 'alias', 'sex', 'birth_date', 'birth_place', 'nationality', 'profession', 'height_cm', 'weight_kg', 'hair', 'eyes', 'marks'] as $k) {
            $data[$k] = trim($request->inputString($k));
        }
        $res = $this->identity()->saveIdentity($ctx['tenant'], $ctx['user'], $id > 0 ? $id : null, $data);

        return $res['ok']
            ? $this->back('operateur/identite#identite-' . (int) $res['id'], 'success', $id > 0 ? 'Identité enregistrée.' : 'Identité créée.')
            : $this->back('operateur/identite' . ($id > 0 ? '?modifier=' . $id . '#identite-' . $id : '#nouvelle-identite'), 'error', (string) $res['error']);
    }

    public function identityPhoto(Request $request, array $params = []): Response
    {
        $ctx = $this->guardWrite($request, 'operateur/identite');
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $id = (int) ($params['id'] ?? 0);
        $back = 'operateur/identite#identite-' . $id;
        if ($this->identity()->ownedIdentity($ctx['tenant'], $ctx['user'], $id) === null) {
            return $this->back('operateur/identite', 'error', 'Identité introuvable.');
        }
        if ($request->inputString('photo_remove') === '1') {
            $this->identity()->setPhoto($ctx['tenant'], $ctx['user'], $id, null);

            return $this->back($back, 'success', 'Photo d’identité retirée.');
        }
        if ($this->throttled('upload', $ctx['user'], self::UPLOADS_PER_HOUR, 3600)) {
            return $this->back($back, 'error', 'Trop d’envois de photos. Réessayez dans une heure.');
        }
        // Type vérifié sur le contenu, image recadrée (35 × 45), réencodée, métadonnées retirées, nom tiré au hasard.
        $photo = (new \App\Services\Sse\SseIdImageService())->storeUpload($_FILES['photo'] ?? null, 'photo', $ctx['tenant']);
        if (!$photo['ok'] || !isset($photo['path'])) {
            return $this->back($back, 'error', !empty($photo['none']) ? 'Choisissez une photo à envoyer.' : 'Photo : ' . ($photo['error'] ?? 'image refusée.'));
        }
        $this->identity()->setPhoto($ctx['tenant'], $ctx['user'], $id, (string) $photo['path']);

        return $this->back($back, 'success', 'Photo d’identité enregistrée : elle apparaît sur les papiers de cette identité.');
    }

    public function identityArchive(Request $request, array $params = []): Response
    {
        $ctx = $this->guardWrite($request, 'operateur/identite');
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $archive = $request->inputString('restaurer') !== '1';
        $res = $this->identity()->archiveIdentity($ctx['tenant'], $ctx['user'], (int) ($params['id'] ?? 0), $archive);

        return $this->back('operateur/identite', $res['ok'] ? 'success' : 'error', $res['ok'] ? ($archive ? 'Identité archivée.' : 'Identité restaurée.') : (string) $res['error']);
    }

    public function identityActivate(Request $request, array $params = []): Response
    {
        $ctx = $this->guardWrite($request, 'operateur/identite');
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $res = $this->identity()->activateIdentity($ctx['tenant'], $ctx['user'], (int) ($params['id'] ?? 0));

        return $this->back('operateur/identite', $res['ok'] ? 'success' : 'error', $res['message']);
    }

    // ---- Écritures : papiers

    public function documentRequest(Request $request, array $params = []): Response
    {
        $ctx = $this->guardWrite($request, 'operateur/identite/documents');
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $res = $this->identity()->requestDocument(
            $ctx['tenant'], $ctx['user'], (int) $request->inputString('identity_id'), $request->inputString('doc_type'), strtoupper(trim($request->inputString('country_code')))
        );

        return $this->back('operateur/identite/documents', $res['ok'] ? 'success' : 'error', $res['ok']
            ? 'Demande envoyée : un responsable valide et émet le papier, il apparaîtra ici.'
            : (string) ($res['error'] ?? 'Demande refusée.'));
    }

    public function documentAction(Request $request, array $params = []): Response
    {
        $ctx = $this->guardWrite($request, 'operateur/identite/documents');
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $res = $this->identity()->documentAction($ctx['tenant'], $ctx['user'], (int) ($params['id'] ?? 0), $request->inputString('action'));

        return $this->back('operateur/identite/documents', $res['ok'] ? 'success' : 'error', $res['message']);
    }

    /** Spécimen d'un papier du membre : page seule, imprimable (PDF) et enregistrable en image, mention SPÉCIMEN · SIMULATION. */
    public function documentSpecimen(Request $request, array $params = []): Response
    {
        $ctx = ($this->context)();
        if ($ctx === null) {
            return Response::redirect(url('dashboard'));
        }
        $doc = null;
        try {
            $doc = $this->identity()->ownedDocumentView($ctx['tenant'], $ctx['user'], (int) ($params['id'] ?? 0));
        } catch (\Throwable $e) {
            error_log('Spécimen indisponible : ' . $e->getMessage());
        }
        if ($doc === null) {
            return $this->back('operateur/identite/documents', 'error', 'Papier introuvable.');
        }
        $doc['photo_data'] = self::photoDataUri($doc['photo_path'] ?? null);

        return Response::view('operateur.specimen', ['doc' => $doc])
            ->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex');
    }

    /**
     * Photo d'identité en donnée intégrée (le spécimen s'enregistre en image sans autre requête). Seulement un fichier
     * image du dossier public, 600 Kio au plus ; sinon null.
     */
    public static function photoDataUri(?string $path, ?string $publicDir = null): ?string
    {
        if ($path === null || $path === '' || str_contains($path, "\0")) {
            return null;
        }
        $public = realpath($publicDir ?? base_path('public'));
        $file = $public !== false ? realpath($public . '/' . ltrim($path, '/')) : false;
        if ($public === false || $file === false || !str_starts_with($file, $public . DIRECTORY_SEPARATOR) || !is_file($file) || filesize($file) > 600 * 1024) {
            return null;
        }
        $bytes = (string) file_get_contents($file);
        $mime = \App\Services\Sse\SseIdImageService::sniff($bytes);

        return in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true) ? 'data:' . $mime . ';base64,' . base64_encode($bytes) : null;
    }

    // ---- Écritures : couvertures

    public function coverGenerate(Request $request, array $params = []): Response
    {
        $ctx = $this->guardWrite($request, 'operateur/identite/couverture');
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $back = 'operateur/identite/couverture';
        $bytes = null;
        $file = $_FILES['photo'] ?? null;
        if (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $tmp = (string) ($file['tmp_name'] ?? '');
            if (($file['error'] ?? 1) !== UPLOAD_ERR_OK || $tmp === '' || !is_uploaded_file($tmp) || filesize($tmp) > 4 * 1024 * 1024) {
                return $this->back($back, 'error', 'Photo : envoi impossible (4 Mo au plus, PNG, JPG ou WebP).');
            }
            $bytes = (string) @file_get_contents($tmp);
        }
        $source = $request->inputString('source') === 'athena' ? 'athena' : 'random';
        $res = $this->identity()->generateCover(
            $ctx['tenant'], $ctx['user'], $source, strtoupper(trim($request->inputString('country'))),
            strtoupper($request->inputString('sex')) ?: null, $request->inputString('lang', 'fr'), $bytes
        );
        if ($res['ok']) {
            return $this->back($back . '?modifier=' . (int) $res['id'] . '#couverture-' . (int) $res['id'], 'success', 'Couverture créée : ' . $res['name'] . '. Relisez la légende et ajustez-la.');
        }

        return $this->back($back, 'error', match ($res['error']) {
            'limit' => 'Limite de générations atteinte pour aujourd’hui. Réessayez dans 24 heures.',
            'country' => 'Pays inconnu ou archivé pour cette communauté.',
            'photo' => 'Photo refusée : PNG, JPG ou WebP lisible, 40 pixels au moins.',
            'no_identity' => 'Pas d’identité principale complète (nom et prénom) : créez-la d’abord.',
            'no_paper' => 'Aucun papier émis et valable pour l’identité principale : demandez-en un d’abord.',
            default => 'Aucune couverture libre n’a pu être tirée. Réessayez.',
        });
    }

    public function coverAction(Request $request, array $params = []): Response
    {
        $ctx = $this->guardWrite($request, 'operateur/identite/couverture');
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $id = (int) ($params['id'] ?? 0);
        $back = 'operateur/identite/couverture';
        $svc = $this->identity();
        $action = $request->inputString('action');
        [$res, $ok, $anchor] = match ($action) {
            'edit' => [$svc->editCover($ctx['tenant'], $ctx['user'], $id, array_intersect_key($this->coverInput($request), array_flip(array_merge(array_keys(OperatorIdentityService::COVER_EDITABLE), ['history'])))),
                'Légende enregistrée. En jeu : téléphone › Ma couverture › Synchroniser avec Athena pour la mettre à jour.', '#couverture-' . $id],
            'duplicate' => [$svc->duplicateCover($ctx['tenant'], $ctx['user'], $id), 'Couverture dupliquée.', ''],
            'revoke' => [$svc->revokeCover($ctx['tenant'], $ctx['user'], $id), 'Couverture révoquée : elle n’est plus téléchargeable ni proposée au téléphone.', ''],
            'activate' => [$svc->activateCover($ctx['tenant'], $ctx['user'], $id), 'Couverture active choisie.', '#couverture-' . $id],
            default => [['ok' => false, 'error' => 'Action inconnue.'], '', ''],
        };

        return $res['ok']
            ? $this->back($back . $anchor, 'success', $ok)
            : $this->back($back . ($action === 'edit' ? '?modifier=' . $id . '#couverture-' . $id : ''), 'error', (string) ($res['error'] ?? 'Action refusée.'));
    }

    /** @return array<string, string> */
    private function coverInput(Request $request): array
    {
        $out = ['history' => $request->inputString('history')];
        foreach (array_keys(OperatorIdentityService::COVER_EDITABLE) as $k) {
            $out[$k] = $request->inputString($k);
        }

        return $out;
    }

    public function coverDownload(Request $request, array $params = []): Response
    {
        $ctx = ($this->context)();
        if ($ctx === null) {
            return Response::redirect(url('dashboard'));
        }
        if ($this->throttled('dl', $ctx['user'], 30, 600)) {
            return $this->back('operateur/identite/couverture', 'error', 'Trop de téléchargements en peu de temps. Réessayez dans quelques minutes.');
        }
        $res = null;
        try {
            $res = $this->identity()->downloadCover($ctx['tenant'], $ctx['user'], (int) ($params['id'] ?? 0));
        } catch (\Throwable $e) {
            error_log('Téléchargement de couverture impossible : ' . $e->getMessage());
        }
        if ($res === null) {
            return $this->back('operateur/identite/couverture', 'error', 'Fichier de couverture introuvable.');
        }
        $name = 'comspec-couverture-' . preg_replace('/[^A-Za-z0-9-]/', '', (string) $res['row']['ref']) . '.json';

        return (new Response())
            ->header('Content-Type', 'application/json; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="' . $name . '"')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Cache-Control', 'no-store')
            ->setBody($res['json']);
    }

    // ------------------------------------------------------------------ Outils

    /**
     * Contrôles communs à toute écriture : membre connecté de la communauté, jeton CSRF, limite de fréquence.
     *
     * @return array{tenant: int, user: int, row: array<string, mixed>}|Response
     */
    private function guardWrite(Request $request, string $back): array|Response
    {
        $ctx = ($this->context)();
        if ($ctx === null) {
            return Response::redirect(url('login'));
        }
        if (!($this->csrf)($request)) {
            return $this->back($back, 'error', 'Session expirée. Réessayez.');
        }
        if ($this->throttled('write', $ctx['user'], self::WRITES_PER_10_MIN, 600)) {
            return $this->back($back, 'error', 'Trop de modifications en peu de temps. Réessayez dans quelques minutes.');
        }

        return $ctx;
    }

    private function throttled(string $bucket, int $userId, int $max, int $seconds): bool
    {
        try {
            $this->limiter ??= new FileRateLimiter();
            // tooManyAttempts() compte la tentative et dit si le plafond de la fenêtre est dépassé.
            return $this->limiter->tooManyAttempts('operator_hub:' . $bucket . ':' . $userId, $max, $seconds);
        } catch (\Throwable $e) {
            error_log('Outils opérateur : limite de fréquence indisponible : ' . $e->getMessage());
        }

        return false;
    }

    private function back(string $path, string $type, string $message): Response
    {
        ($this->flash)($type, $message);

        return Response::redirect(url($path));
    }

    private function hub(): OperatorHubRepository
    {
        return $this->hub ??= new OperatorHubRepository();
    }

    private function identity(): OperatorIdentityService
    {
        return $this->identity ??= new OperatorIdentityService();
    }
}
