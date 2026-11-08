<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\CasNineLineRepository;
use App\Services\Audit\AuditService;
use App\Services\Personnel\MemberNameService;
use App\Services\Tactical\AirBriefService;
use App\Services\Tactical\AirCrewSpecialtyService;
use App\Services\Tactical\NineLineBoardService;
use App\Services\Qr\QrPngGenerator;
use App\Support\AtakPlanAccess;
use App\Support\LoginIntendedDestination;

/**
 * 9-LINE du JTAC sur un autre écran : page /9-line, ouverte en scannant le QR code de l'app JTAC de l'ATAK natif
 * (onglet 9-LINE) ou par son bouton OUVRIR LA PAGE 9-LINE (seconde fenêtre du PC).
 *
 * Rédaction ligne par ligne (9-LINE CAS, 5-LINE, MEDEVAC ; saisie libre possible) au thème noir et vert du
 * questionnaire de la preview (athena-preview.css, /retour-preview), en densité compacte (atak-nine-line.css) ;
 * destinataires parmi les appareils remontés par le jeu (pilote et équipage, joueurs uniquement au choix) ou saisis à
 * la main ; carte de transmission ; historique avec le statut de chaque destinataire (envoyé, reçu, lu, WILCO,
 * UNABLE), suppression (auteur ou responsable) et vidage (responsable). Indicatif JTAC du membre et indicatif affiché
 * des appareils réglables ici (atak_brief_callsigns). Une demande rédigée ici rejoint la même table que celles de
 * l'app JTAC (atak_nine_line) : visible en jeu (espace BRIEFS AIR du pilote) et dans Overwatch.
 */
final class AtakNineLineViewController
{
    public function show(Request $request, array $params = []): Response
    {
        if (!Session::get('user_id')) {
            LoginIntendedDestination::rememberFromRequest($request);

            return Response::redirect(url('login'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('connect'));
        }
        if (!AtakPlanAccess::allows($tenantId)) {
            return Response::view('layout.main', [
                'title' => '9-LINE',
                'content' => 'platform.upgrade',
                'feature' => 'atak',
                'planName' => 'standard',
            ]);
        }

        return Response::view('atak.nine_line_view', [
            'title' => '9-LINE — COMSPEC',
            'apiBase' => rtrim((string) url(''), '/'),
            'assetVer' => \App\Support\BuildVersion::id(),
            'lang' => function_exists('locale') && str_starts_with((string) locale(), 'fr') ? 'fr' : 'en',
            'jtacCallsign' => self::jtacCallsignFor($tenantId, (int) Session::get('user_id')),
            'defaultCallsign' => self::authorLabel($tenantId, (int) Session::get('user_id')),
            'mySpecs' => self::specsOf($tenantId, (int) Session::get('user_id')),
        ]);
    }

    /** Mot à taper pour vider l'historique (français, ou anglais sur la page traduite). */
    public const CLEAR_WORDS = ['SUPPRIMER', 'DELETE'];

    public function __construct(private ?NineLineBoardService $board = null, private ?AirBriefService $briefs = null)
    {
    }

    /**
     * Derniers 9-LINE de la communauté (toutes cartes ou ?carte=ID), du plus récent au plus ancien, avec le droit de
     * suppression de chacun ; cartes présentes dans l'historique ; droit de vider l'historique.
     */
    public function feed(Request $request, array $params = []): Response
    {
        $ctx = $this->context();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId] = $ctx;
        $mapId = (int) $request->queryString('carte', '0');
        $manager = self::isManager();
        try {
            $items = $this->board()->history($tenantId, $userId, $manager, $mapId > 0 ? $mapId : null);
            $maps = $this->board()->historyMaps($tenantId);
        } catch (\Throwable) {
            $items = [];
            $maps = [];
        }

        return self::noStore(['ok' => true, 'items' => $items, 'maps' => $maps, 'can_clear' => $manager, 'jtac_callsign' => self::jtacCallsignFor($tenantId, $userId) ?? '']);
    }

    /** Pilotes et JTAC de la communauté d'après les dossiers (qualifications et fonctions). */
    public function roster(Request $request, array $params = []): Response
    {
        $ctx = $this->context();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        try {
            $items = (new AirCrewSpecialtyService())->roster($ctx[0]);
        } catch (\Throwable) {
            $items = [];
        }

        return self::noStore(['ok' => true, 'items' => $items, 'me' => self::specsOf($ctx[0], $ctx[1])]);
    }

    /** Aéronefs avec équipage remontés par le jeu (pilote, équipage, position, fraîcheur). */
    public function aircraft(Request $request, array $params = []): Response
    {
        $ctx = $this->context();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        try {
            $items = $this->board()->aircraft($ctx[0]);
        } catch (\Throwable) {
            $items = [];
        }

        return self::noStore([
            'ok' => true,
            'items' => $items,
            'live_seconds' => NineLineBoardService::AIRCRAFT_LIVE_SECONDS,
            'window_seconds' => NineLineBoardService::AIRCRAFT_WINDOW_SECONDS,
        ]);
    }

    /**
     * POST /9-line : 9-LINE, 5-LINE ou MEDEVAC rédigé sur la page, envoyé aux appareils choisis (indicatifs remontés
     * par le jeu ou saisis à la main). Saisie libre (free=1) : seules les longueurs bloquent.
     */
    public function store(Request $request, array $params = []): Response
    {
        $ctx = $this->context(true, $request);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId] = $ctx;
        $kind = CasNineLineRepository::normalizeKind($request->inputString('kind'));
        $free = in_array($request->inputString('free'), ['1', 'on', 'true'], true);
        $lines = [];
        for ($n = 1; $n <= NineLineBoardService::LINE_COUNT[$kind]; $n++) {
            $lines[$n] = trim($request->inputString('line' . $n));
        }
        $errors = NineLineBoardService::validate($kind, $lines, $free);
        if ($errors !== []) {
            return Response::json(['ok' => false, 'error' => 'Vérifiez les lignes signalées.', 'errors' => $errors], 422);
        }
        $remarks = $kind === CasNineLineRepository::KIND_FIVE ? '' : mb_substr(trim($request->inputString('remarks')), 0, 1000);
        $rawAircraft = $request->input('aircraft', []);
        $aircraft = is_array($rawAircraft) ? array_map(static fn ($v): string => is_scalar($v) ? (string) $v : '', $rawAircraft) : [];
        $mapId = (int) $request->inputString('map_id', '1');
        try {
            $row = $this->board()->create($tenantId, $userId, self::authorFor($tenantId, $userId), $kind, $lines, $aircraft, $mapId, $remarks);
        } catch (\Throwable) {
            return Response::json(['ok' => false, 'error' => 'Enregistrement impossible pour le moment. Réessayez dans un instant.'], 503);
        }
        $id = (int) ($row['id'] ?? 0);
        self::audit('atak.nine_line.created', $tenantId, $userId, $id, [], [
            'kind' => $kind,
            'free' => $free,
            'map_id' => (int) ($row['map_id'] ?? $mapId),
            'assigned_aircraft' => (string) ($row['assigned_aircraft'] ?? ''),
            'target' => $lines[$kind === CasNineLineRepository::KIND_FIVE ? 3 : 6] ?? '',
        ]);

        return Response::json(['ok' => true, 'id' => $id, 'recipients' => $row['recipients'] ?? []], 201);
    }

    /** POST /9-line/indicatif : indicatif JTAC de ce membre (vide = son indicatif Athena). */
    public function jtacCallsign(Request $request, array $params = []): Response
    {
        $ctx = $this->context(true, $request);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId] = $ctx;
        $callsign = AirBriefService::cleanCallsign($request->inputString('callsign'));
        try {
            $ok = $this->briefs()->setJtacCallsign($tenantId, $userId, $callsign);
        } catch (\Throwable) {
            $ok = false;
        }
        if (!$ok) {
            return Response::json(['ok' => false, 'error' => 'Indicatif non enregistré : réessayez plus tard.'], 503);
        }
        self::audit('atak.nine_line.jtac_callsign', $tenantId, $userId, null, [], ['callsign' => $callsign]);

        return Response::json(['ok' => true, 'callsign' => $callsign, 'author' => self::authorFor($tenantId, $userId)]);
    }

    /** POST /9-line/appareils/indicatif : indicatif affiché d'un appareil remonté par le jeu (vide = celui du jeu). */
    public function aircraftCallsign(Request $request, array $params = []): Response
    {
        $ctx = $this->context(true, $request);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId] = $ctx;
        $reported = AirBriefService::cleanCallsign($request->inputString('callsign'));
        $label = AirBriefService::cleanCallsign($request->inputString('label'));
        if ($reported === '') {
            return Response::json(['ok' => false, 'error' => 'Appareil inconnu.'], 422);
        }
        try {
            $ok = $this->briefs()->setAircraftCallsign($tenantId, $reported, $label === $reported ? '' : $label, $userId);
        } catch (\Throwable) {
            $ok = false;
        }
        if (!$ok) {
            return Response::json(['ok' => false, 'error' => 'Indicatif non enregistré : réessayez plus tard.'], 503);
        }
        self::audit('atak.nine_line.aircraft_callsign', $tenantId, $userId, null, ['callsign' => $reported], ['label' => $label]);

        return Response::json(['ok' => true, 'callsign' => $reported, 'label' => $label !== '' ? $label : $reported]);
    }

    /** POST /9-line/{id}/delete : par son auteur ou un responsable, dans la communauté de la session. */
    public function delete(Request $request, array $params = []): Response
    {
        $ctx = $this->context(true, $request);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId] = $ctx;
        $id = (int) ($params['id'] ?? 0);
        $res = $this->board()->delete($tenantId, $id, $userId, self::isManager());
        if ($res['result'] === 'not_found') {
            return Response::json(['ok' => false, 'error' => 'Ce 9-LINE n’existe plus.'], 404);
        }
        if ($res['result'] === 'forbidden') {
            return Response::json(['ok' => false, 'error' => 'Seuls son auteur et les responsables peuvent supprimer ce 9-LINE.'], 403);
        }
        $row = $res['row'] ?? [];
        self::audit('atak.nine_line.deleted', $tenantId, $userId, $id, [
            'kind' => (string) ($row['mission_kind'] ?? ''),
            'map_id' => (int) ($row['map_id'] ?? 0),
            'author' => (string) ($row['author'] ?? ''),
            'assigned_aircraft' => (string) ($row['assigned_aircraft'] ?? ''),
            'line6' => (string) ($row['line6'] ?? ''),
        ], []);

        return Response::json(['ok' => true]);
    }

    /** POST /9-line/clear : vide l'historique (toutes cartes ou map_id), responsables seulement, mot de confirmation. */
    public function clear(Request $request, array $params = []): Response
    {
        $ctx = $this->context(true, $request);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId] = $ctx;
        if (!self::isManager()) {
            return Response::json(['ok' => false, 'error' => 'Réservé aux responsables ATAK de la communauté.'], 403);
        }
        if (!in_array(mb_strtoupper(trim($request->inputString('confirm'))), self::CLEAR_WORDS, true)) {
            return Response::json(['ok' => false, 'error' => 'Tapez SUPPRIMER pour confirmer.'], 422);
        }
        $mapId = (int) $request->inputString('map_id', '0');
        $count = $this->board()->clear($tenantId, $mapId > 0 ? $mapId : null);
        self::audit('atak.nine_line.cleared', $tenantId, $userId, null, ['count' => $count, 'map_id' => $mapId > 0 ? $mapId : 'all'], []);

        return Response::json(['ok' => true, 'deleted' => $count]);
    }

    /** Responsable : pilote le cycle de mission, gère la configuration ATAK, ou administre la communauté. */
    public static function isManager(): bool
    {
        foreach (['atak.mission_cycle.manage', 'atak.config.manage', 'atak.manage', 'admin.access', 'admin.organization'] as $perm) {
            if (can($perm)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Session, communauté, jeton CSRF pour une écriture, puis offre ATAK.
     *
     * @return array{0: int, 1: int}|Response
     */
    private function context(bool $write = false, ?Request $request = null): array|Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($userId < 1 || $tenantId < 1) {
            return Response::json(['ok' => false, 'error' => 'Connectez-vous à Athena pour voir les 9-LINE.'], 401);
        }
        if ($write && ($request === null || !Csrf::verify($request))) {
            return Response::json(['ok' => false, 'error' => 'Session expirée. Rechargez la page.'], 419);
        }
        if (!AtakPlanAccess::allows($tenantId)) {
            return Response::json(['ok' => false, 'error' => 'plan'], 403);
        }

        return [$tenantId, $userId];
    }

    /**
     * Ce que le dossier de ce membre dit de lui : pilote, JTAC (clé absente : rien au dossier).
     *
     * @return array<string, array<string, mixed>>
     */
    private static function specsOf(int $tenantId, int $userId): array
    {
        try {
            return (new AirCrewSpecialtyService())->forMembers($tenantId, [$userId])[$userId] ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** Auteur d'une demande rédigée ici : l'indicatif JTAC choisi sur la page, sinon celui d'Athena. */
    private static function authorFor(int $tenantId, int $userId): string
    {
        return self::jtacCallsignFor($tenantId, $userId) ?? self::authorLabel($tenantId, $userId);
    }

    private static function jtacCallsignFor(int $tenantId, int $userId): ?string
    {
        try {
            return (new AirBriefService())->jtacCallsign($tenantId, $userId);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Auteur affiché d'un 9-LINE rédigé sur le site : en C2, l'indicatif d'abord, sinon « Prénom N. ». */
    private static function authorLabel(int $tenantId, int $userId): string
    {
        try {
            $identity = (new MemberNameService())->identitiesFor($tenantId, [$userId])[$userId] ?? null;
            if (is_array($identity)) {
                $label = member_name_c2($identity);
                if ($label !== '') {
                    return $label;
                }
            }
        } catch (\Throwable) {
        }
        foreach (['callsign', 'display_name'] as $key) {
            $v = trim((string) (Session::get($key) ?? ''));
            if ($v !== '') {
                return $v;
            }
        }

        return 'JTAC';
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     */
    private static function audit(string $action, int $tenantId, int $userId, ?int $id, array $old, array $new): void
    {
        try {
            (new AuditService())->logChange($action, $tenantId, $userId > 0 ? $userId : null, 'atak_nine_line', $id, $old, $new);
        } catch (\Throwable $e) {
            error_log('[audit] ' . $action . ' : ' . $e->getMessage());
        }
    }

    /** @param array<string, mixed> $data */
    private static function noStore(array $data): Response
    {
        return (new Response())
            ->setStatusCode(200)
            ->header('Content-Type', 'application/json; charset=UTF-8')
            ->header('Cache-Control', 'no-store')
            ->setBody((string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function board(): NineLineBoardService
    {
        return $this->board ??= new NineLineBoardService();
    }

    private function briefs(): AirBriefService
    {
        return $this->briefs ??= new AirBriefService();
    }

    /** PNG du QR code de cette page (adresse publique du site). */
    public function qr(Request $request, array $params = []): Response
    {
        $png = (new QrPngGenerator())->png(url('9-line'), 360, 12, true);
        if ($png === null || strncmp($png['body'], "\x89PNG", 4) !== 0) {
            return (new Response())
                ->setStatusCode(503)
                ->header('Content-Type', 'text/plain; charset=UTF-8')
                ->setBody('QR unavailable');
        }

        return (new Response())
            ->setStatusCode(200)
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'public, max-age=3600')
            ->header('X-Content-Type-Options', 'nosniff')
            ->setBody($png['body']);
    }
}
