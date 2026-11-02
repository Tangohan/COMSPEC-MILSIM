<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AtakDataRepository;
use App\Repositories\AtakMapRepository;
use App\Services\Atak\AtakMobileLinkService;
use App\Services\Security\FileRateLimiter;
use App\Services\Tactical\NineLineBoardService;
use App\Support\AtakMobileReportCatalog;

/**
 * « Sur mon téléphone » : versions mobiles des apps de l'ATAK en jeu, ouvertes en scannant le QR code affiché dans
 * le téléphone du jeu. /m/{jeton} rachète le jeton (usage unique, 5 min) et ouvre une session limitée à ces pages :
 *   /m/cr            compte rendu rapide (CONTACT, SALUTE, SPOTREP, blessé, libre) → confié à l'ATAK du joueur
 *   /m/compte-rendu  CR de fin d'opération ouvert dans le téléphone en jeu → confié à l'ATAK du joueur
 *   /m/carte         carte tactique de la partie : amis, marqueurs, poser / modifier / retirer ses marqueurs
 *   /m/briefs        briefs air (9-LINE reçus par l'appareil), seulement pour un pilote ou un membre d'équipage
 * Écritures en POST avec jeton CSRF (en-tête X-CSRF-Token), limitées en débit ; lectures GET sans corps.
 */
final class AtakMobileAppsController
{
    /** Marqueurs proposés sur le téléphone : [type Arma, couleur Arma, libellé]. */
    public const MARKER_KINDS = [
        'point' => ['hd_dot', 'ColorBlack', 'Point'],
        'ennemi' => ['hd_destroy', 'ColorRed', 'Ennemi'],
        'danger' => ['hd_warning', 'ColorOrange', 'Danger'],
        'objectif' => ['hd_objective', 'ColorYellow', 'Objectif'],
        'ami' => ['hd_flag', 'ColorBlue', 'Ami'],
        'lz' => ['hd_pickup', 'ColorGreen', 'Zone de poser'],
    ];

    public function __construct(
        private ?AtakMobileLinkService $links = null,
        private ?AtakDataRepository $atak = null,
        private ?FileRateLimiter $limiter = null,
    ) {
    }

    /** Scan du QR code : jeton racheté une seule fois, puis la page de l'app. */
    public function open(Request $request, array $params = []): Response
    {
        $token = strtolower(trim((string) ($params['token'] ?? '')));
        $ipKey = 'atak_mobile_open:' . sha1($request->ip());
        if ($this->limiter()->tooManyAttempts($ipKey, 20, 600)) {
            return $this->expired('Trop de tentatives depuis ce téléphone. Patientez quelques minutes, puis régénérez le QR code dans le jeu.');
        }
        $this->limiter()->hit($ipKey, 600);
        $link = $this->links()->redeem($token, $request->ip(), (string) ($request->header('User-Agent') ?? ''));
        if ($link === null) {
            return $this->expired('Ce QR code a déjà servi ou a expiré (5 minutes). Dans le jeu, appuyez sur RÉGÉNÉRER et scannez le nouveau.');
        }
        AtakMobileLinkService::remember($link);
        $page = AtakMobileLinkService::APPS[(string) $link['app']][0] ?? 'm';

        return Response::redirect(url($page));
    }

    public function home(Request $request, array $params = []): Response
    {
        $link = $this->links()->current();
        if ($link === null) {
            return $this->expired();
        }

        return $this->page('home', $link, ['aircrew' => $this->links()->aircrew($link)]);
    }

    public function cr(Request $request, array $params = []): Response
    {
        $link = $this->links()->current();
        if ($link === null) {
            return $this->expired();
        }

        return $this->page('cr', $link, [
            'types' => AtakMobileReportCatalog::types(),
            'priorities' => AtakMobileReportCatalog::PRIORITIES,
            'myGrid' => $this->myGrid($link),
        ]);
    }

    /** Compte rendu rempli sur le téléphone : contrôlé, puis confié à l'ATAK du joueur (avis « mobile_report »). */
    public function crSubmit(Request $request, array $params = []): Response
    {
        $link = $this->guardPost($request);
        if ($link instanceof Response) {
            return $link;
        }
        $code = strtoupper(trim((string) $request->input('type', '')));
        $fields = $request->input('f', []);
        [$vals, $errors] = AtakMobileReportCatalog::clean($code, is_array($fields) ? $fields : []);
        if ($errors !== []) {
            return Response::json(['ok' => false, 'error' => 'invalid', 'fields' => $errors], 422);
        }
        $type = AtakMobileReportCatalog::types()[$code];
        $id = $this->links()->queueNotice($link, AtakMobileLinkService::NOTICE_REPORT, 'Compte rendu depuis le téléphone · ' . $type['label'], AtakMobileReportCatalog::noticeBody($code, $vals));
        if ($id < 1) {
            return Response::json(['ok' => false, 'error' => 'unavailable'], 503);
        }

        return Response::json(['ok' => true, 'notice' => $id]);
    }

    public function debrief(Request $request, array $params = []): Response
    {
        $link = $this->links()->current();
        if ($link === null) {
            return $this->expired();
        }

        return $this->page('compte_rendu', $link, ['sheet' => self::sheet(AtakMobileLinkService::context($link))]);
    }

    /** CR de fin d'opération rempli (ou lu) sur le téléphone : confié à l'ATAK du joueur (avis « mobile_debrief »). */
    public function debriefSubmit(Request $request, array $params = []): Response
    {
        $link = $this->guardPost($request);
        if ($link instanceof Response) {
            return $link;
        }
        $sheet = self::sheet(AtakMobileLinkService::context($link));
        if ($sheet === null) {
            return Response::json(['ok' => false, 'error' => 'no_sheet'], 404);
        }
        $answers = $request->input('a', []);
        $answers = is_array($answers) ? $answers : [];
        $pairs = [];
        $missing = [];
        if ($sheet['mode'] === 'fill') {
            foreach ($sheet['questions'] as $q) {
                $v = $answers[$q['id']] ?? '';
                $v = is_scalar($v) ? trim((string) $v) : '';
                $v = mb_substr((string) preg_replace('/[\x00-\x09\x0B-\x1F\x7F]+/u', ' ', $v), 0, 1500);
                if ($q['options'] !== [] && $v !== '' && !in_array($v, $q['options'], true)) {
                    $v = '';
                }
                if ($q['required'] && $v === '') {
                    $missing[] = $q['id'];
                }
                if ($v !== '') {
                    $pairs[] = '[' . AtakMobileReportCatalog::sqfString($q['id']) . ',' . AtakMobileReportCatalog::sqfString(str_replace("\n", ' / ', $v)) . ']';
                }
            }
        }
        if ($missing !== []) {
            return Response::json(['ok' => false, 'error' => 'invalid', 'fields' => $missing], 422);
        }
        $body = '["MDB1",' . AtakMobileReportCatalog::sqfString($sheet['id']) . ',[' . implode(',', $pairs) . '],'
            . AtakMobileReportCatalog::sqfString($sheet['mode'] === 'read' ? 'read' : 'send') . ']';
        $id = $this->links()->queueNotice($link, AtakMobileLinkService::NOTICE_DEBRIEF, 'CR fin d’op depuis le téléphone · ' . $sheet['title'], $body);
        if ($id < 1) {
            return Response::json(['ok' => false, 'error' => 'unavailable'], 503);
        }

        return Response::json(['ok' => true, 'notice' => $id]);
    }

    /** Suivi d'un envoi confié à l'ATAK : en attente du jeu, reçu, envoyé. */
    public function noticeStatus(Request $request, array $params = []): Response
    {
        $link = $this->links()->current();
        if ($link === null) {
            return Response::json(['ok' => false, 'error' => 'session'], 401);
        }

        return Response::json(['ok' => true, 'state' => $this->links()->noticeState($link, (int) ($params['id'] ?? 0))]);
    }

    public function carte(Request $request, array $params = []): Response
    {
        $link = $this->links()->current();
        if ($link === null) {
            return $this->expired();
        }

        return $this->page('carte', $link, ['markerKinds' => self::MARKER_KINDS]);
    }

    /** Carte de la partie : ma position, amis, marqueurs (les miens modifiables). */
    public function carteData(Request $request, array $params = []): Response
    {
        $link = $this->links()->current();
        if ($link === null) {
            return Response::json(['ok' => false, 'error' => 'session'], 401);
        }
        $tenantId = (int) $link['tenant_id'];
        $mapId = (int) $link['map_id'];
        $me = $this->me($link);
        $mySide = strtolower((string) ($me['side'] ?? ''));
        $units = [];
        foreach ($this->atak()->getUnits($tenantId, $mapId) as $u) {
            $side = strtolower((string) ($u['side'] ?? ''));
            $hostile = str_contains($side, 'east') || str_contains($side, 'enemy') || str_contains($side, 'hostile') || $side === 'opfor';
            if ($hostile || ($mySide !== '' && $side !== '' && $side !== $mySide) || !isset($u['pos_x'], $u['pos_y'])) {
                continue;
            }
            $units[] = [
                'callsign' => (string) ($u['call_sign'] ?? $u['display_call_sign'] ?? ''),
                'x' => (float) $u['pos_x'],
                'y' => (float) $u['pos_y'],
                'status' => (string) ($u['status'] ?? ''),
                'me' => $me !== null && (int) ($u['id'] ?? 0) === (int) ($me['id'] ?? -1),
            ];
        }
        $markers = [];
        try {
            $rows = $this->atak()->getMarkers($tenantId, $mapId);
        } catch (\Throwable) {
            $rows = [];
        }
        foreach ($rows as $m) {
            $d = json_decode((string) ($m['markerData'] ?? ''), true);
            if (!is_array($d)) {
                continue;
            }
            $x = $d['pos'][0] ?? $d['pos_x'] ?? $d['x'] ?? null;
            $y = $d['pos'][1] ?? $d['pos_y'] ?? $d['y'] ?? null;
            if (!is_numeric($x) || !is_numeric($y)) {
                continue;
            }
            $markers[] = [
                'id' => (int) $m['id'],
                'x' => (float) $x,
                'y' => (float) $y,
                'text' => mb_substr((string) ($d['text'] ?? $d['label'] ?? ''), 0, 80),
                'type' => (string) ($d['type'] ?? ''),
                'color' => (string) ($d['color'] ?? ''),
                'mine' => (int) ($d['mobile_user'] ?? 0) === (int) $link['user_id'],
            ];
        }

        return Response::json([
            'ok' => true,
            'me' => $me !== null ? ['x' => (float) $me['pos_x'], 'y' => (float) $me['pos_y'], 'grid' => NineLineBoardService::grid((float) $me['pos_x'], (float) $me['pos_y'])] : $this->linkPos($link),
            'units' => $units,
            'markers' => $markers,
        ]);
    }

    /** Marqueur posé sur le téléphone : même canal que les marqueurs du site, redescend en jeu au sondage suivant. */
    public function markerStore(Request $request, array $params = []): Response
    {
        $link = $this->guardPost($request);
        if ($link instanceof Response) {
            return $link;
        }
        $data = $this->markerData($request, $link);
        if ($data === null) {
            return Response::json(['ok' => false, 'error' => 'invalid'], 422);
        }
        $row = $this->atak()->addMarker((int) $link['tenant_id'], (int) $link['map_id'], 1, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');

        return Response::json(['ok' => true, 'id' => (int) ($row['id'] ?? 0)]);
    }

    public function markerUpdate(Request $request, array $params = []): Response
    {
        $link = $this->guardPost($request);
        if ($link instanceof Response) {
            return $link;
        }
        $id = (int) ($params['id'] ?? 0);
        if (!$this->ownsMarker($link, $id)) {
            return Response::json(['ok' => false, 'error' => 'forbidden'], 403);
        }
        $data = $this->markerData($request, $link);
        if ($data === null) {
            return Response::json(['ok' => false, 'error' => 'invalid'], 422);
        }
        $this->atak()->updateMarker((int) $link['tenant_id'], $id, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');

        return Response::json(['ok' => true, 'id' => $id]);
    }

    public function markerDelete(Request $request, array $params = []): Response
    {
        $link = $this->guardPost($request);
        if ($link instanceof Response) {
            return $link;
        }
        $id = (int) ($params['id'] ?? 0);
        if (!$this->ownsMarker($link, $id)) {
            return Response::json(['ok' => false, 'error' => 'forbidden'], 403);
        }
        $this->atak()->deleteMarker((int) $link['tenant_id'], $id);

        return Response::json(['ok' => true]);
    }

    public function briefs(Request $request, array $params = []): Response
    {
        $link = $this->links()->current();
        if ($link === null) {
            return $this->expired();
        }
        $aircraft = $this->links()->aircrew($link);
        if ($aircraft === []) {
            return $this->page('briefs_denied', $link, [], 403);
        }

        return $this->page('briefs', $link, ['aircraft' => $aircraft]);
    }

    /** Briefs reçus par mon appareil (9-LINE adressés à son indicatif), plus récents d'abord. */
    public function briefsData(Request $request, array $params = []): Response
    {
        $link = $this->links()->current();
        if ($link === null) {
            return Response::json(['ok' => false, 'error' => 'session'], 401);
        }
        $aircraft = $this->links()->aircrew($link);
        if ($aircraft === []) {
            return Response::json(['ok' => false, 'error' => 'not_aircrew'], 403);
        }

        return Response::json(['ok' => true, 'aircraft' => $aircraft, 'briefs' => $this->briefsFor((int) $link['tenant_id'], $aircraft)]);
    }

    /** WILCO / UNABLE sur un brief reçu par mon appareil. */
    public function briefRespond(Request $request, array $params = []): Response
    {
        $link = $this->guardPost($request);
        if ($link instanceof Response) {
            return $link;
        }
        $aircraft = $this->links()->aircrew($link);
        if ($aircraft === []) {
            return Response::json(['ok' => false, 'error' => 'not_aircrew'], 403);
        }
        $id = (int) ($params['id'] ?? 0);
        $answer = strtoupper(trim((string) $request->input('answer', '')));
        if (!in_array($answer, ['WILCO', 'UNABLE'], true)) {
            return Response::json(['ok' => false, 'error' => 'invalid'], 422);
        }
        $mine = array_filter($this->briefsFor((int) $link['tenant_id'], $aircraft), static fn (array $b): bool => $b['id'] === $id);
        if ($mine === []) {
            return Response::json(['ok' => false, 'error' => 'not_found'], 404);
        }
        $ok = $this->respondBrief($link, $id, $answer, $aircraft);

        return Response::json(['ok' => $ok], $ok ? 200 : 503);
    }

    public function logout(Request $request, array $params = []): Response
    {
        $link = $this->guardPost($request);
        if ($link instanceof Response) {
            return $link;
        }
        $this->links()->revoke((int) $link['tenant_id'], (int) $link['user_id'], (int) $link['id']);
        AtakMobileLinkService::forget();

        return Response::json(['ok' => true]);
    }

    // ── Briefs air ──────────────────────────────────────────────────────────

    /**
     * @param list<string> $aircraft
     * @return list<array<string, mixed>>
     */
    private function briefsFor(int $tenantId, array $aircraft): array
    {
        $wanted = array_map('mb_strtolower', $aircraft);
        $out = [];
        foreach ((new NineLineBoardService())->history($tenantId, 0, false, null, 60) as $b) {
            $to = array_map(static fn (string $s): string => mb_strtolower(trim($s)), explode(',', (string) $b['assigned_aircraft']));
            if (array_intersect($wanted, $to) === []) {
                continue;
            }
            $lines = [];
            for ($n = 1; $n <= 9; $n++) {
                $lines[$n] = (string) ($b['line' . $n] ?? '');
            }
            $out[] = [
                'id' => (int) $b['id'],
                'kind' => (string) $b['mission_kind'],
                'author' => (string) $b['author'],
                'to' => (string) $b['assigned_aircraft'],
                'status' => (string) $b['status'],
                'at' => (string) ($b['created_at'] ?: $b['updated_at']),
                'lines' => $lines,
            ];
        }

        return $out;
    }

    /** @param list<string> $aircraft */
    private function respondBrief(array $link, int $id, string $answer, array $aircraft): bool
    {
        try {
            $repo = new \App\Repositories\CasNineLineRepository();
            $row = $answer === 'WILCO' ? $repo->ackCas((int) $link['tenant_id'], $id) : $repo->updateCasStatus((int) $link['tenant_id'], $id, 'ABORTED');

            return $row !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    // ── Outils ──────────────────────────────────────────────────────────────

    /**
     * Fiche du CR fin d'op envoyée par le téléphone en jeu avec la demande du QR code (contexte du lien).
     *
     * @param array<string, mixed> $c
     * @return array{id: string, mode: string, title: string, intro: string, read: string, done: bool, questions: list<array{id: string, type: string, label: string, help: string, required: bool, options: list<string>}>, vals: array<string, string>}|null
     */
    public static function sheet(array $c): ?array
    {
        $id = trim((string) ($c['id'] ?? ''));
        if ($id === '' || preg_match('/^[A-Za-z0-9_\-]{1,40}$/', $id) !== 1) {
            return null;
        }
        $qs = [];
        foreach (is_array($c['qs'] ?? null) ? array_slice($c['qs'], 0, 30) : [] as $q) {
            if (!is_array($q) || count($q) < 3) {
                continue;
            }
            $opts = is_array($q[5] ?? null) ? array_values(array_map('strval', array_filter($q[5], 'is_scalar'))) : [];
            $qs[] = [
                'id' => mb_substr((string) $q[0], 0, 40),
                'type' => in_array((string) $q[1], ['text', 'textarea', 'select', 'checkbox'], true) ? (string) $q[1] : 'text',
                'label' => mb_substr((string) $q[2], 0, 200),
                'help' => mb_substr((string) ($q[3] ?? ''), 0, 200),
                'required' => (int) ($q[4] ?? 0) === 1 || ($q[4] ?? null) === true,
                'options' => array_slice($opts, 0, 20),
            ];
        }
        $vals = [];
        foreach (is_array($c['vals'] ?? null) ? $c['vals'] : [] as $k => $v) {
            if (is_scalar($v)) {
                $vals[(string) $k] = (string) $v;
            }
        }

        return [
            'id' => $id,
            'mode' => ($c['mode'] ?? '') === 'read' ? 'read' : 'fill',
            'title' => mb_substr(trim((string) ($c['title'] ?? '')), 0, 120) ?: 'Fin d’opération',
            'intro' => mb_substr((string) ($c['intro'] ?? ''), 0, 2000),
            'read' => mb_substr((string) ($c['read'] ?? ''), 0, 8000),
            'done' => ($c['state'] ?? '') === 'done',
            'questions' => $qs,
            'vals' => $vals,
        ];
    }

    /** @return array<string, mixed>|Response session mobile valable, CSRF et débit contrôlés */
    private function guardPost(Request $request): array|Response
    {
        $link = $this->links()->current();
        if ($link === null) {
            return Response::json(['ok' => false, 'error' => 'session'], 401);
        }
        if (!Csrf::verify($request)) {
            return Response::json(['ok' => false, 'error' => 'csrf'], 419);
        }
        $key = 'atak_mobile_post:' . (int) $link['id'];
        if ($this->limiter()->tooManyAttempts($key, 40, 60)) {
            return Response::json(['ok' => false, 'error' => 'rate_limited'], 429);
        }
        $this->limiter()->hit($key, 60);

        return $link;
    }

    /** @return array<string, mixed>|null */
    private function markerData(Request $request, array $link): ?array
    {
        $kind = (string) $request->input('kind', 'point');
        $def = self::MARKER_KINDS[$kind] ?? null;
        $x = $request->input('x');
        $y = $request->input('y');
        if ($def === null || !is_numeric($x) || !is_numeric($y) || (float) $x < 0 || (float) $y < 0 || (float) $x > 100000 || (float) $y > 100000) {
            return null;
        }
        $text = mb_substr(trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $request->input('text', ''))), 0, 60);
        $author = trim((string) ($link['player_name'] ?? '')) ?: 'Téléphone';

        return [
            'type' => $def[0],
            'color' => $def[1],
            'text' => $text !== '' ? $text : $def[2],
            'label' => $text !== '' ? $text : $def[2],
            'author' => $author,
            'pos' => [round((float) $x, 1), round((float) $y, 1)],
            'pos_x' => round((float) $x, 1),
            'pos_y' => round((float) $y, 1),
            'grid' => NineLineBoardService::grid((float) $x, (float) $y),
            'source' => 'web',
            'origin' => 'mobile',
            'mobile_kind' => $kind,
            'mobile_user' => (int) $link['user_id'],
        ];
    }

    private function ownsMarker(array $link, int $id): bool
    {
        $m = $id > 0 ? $this->atak()->getMarkerById((int) $link['tenant_id'], $id) : null;
        if ($m === null) {
            return false;
        }
        $d = json_decode((string) ($m['markerData'] ?? $m['marker_data'] ?? ''), true);

        return is_array($d) && (int) ($d['mobile_user'] ?? 0) === (int) $link['user_id'];
    }

    /** @return array<string, mixed>|null mon unité en jeu (position remontée par mon ATAK) */
    private function me(array $link): ?array
    {
        $steam = (string) ($link['steam_uid'] ?? '');
        if ($steam === '') {
            return null;
        }
        try {
            $u = $this->atak()->findUnitBySteamUid((int) $link['tenant_id'], (int) $link['map_id'], $steam);
        } catch (\Throwable) {
            return null;
        }

        return is_array($u) && isset($u['pos_x'], $u['pos_y']) ? $u : null;
    }

    /** @return array{x: float, y: float, grid: string}|null position envoyée avec la demande du QR code */
    private function linkPos(array $link): ?array
    {
        if (!is_numeric($link['pos_x'] ?? null) || !is_numeric($link['pos_y'] ?? null)) {
            return null;
        }

        return ['x' => (float) $link['pos_x'], 'y' => (float) $link['pos_y'], 'grid' => NineLineBoardService::grid((float) $link['pos_x'], (float) $link['pos_y'])];
    }

    private function myGrid(array $link): string
    {
        $me = $this->me($link);
        if ($me !== null) {
            return NineLineBoardService::grid((float) $me['pos_x'], (float) $me['pos_y']);
        }

        return $this->linkPos($link)['grid'] ?? '';
    }

    /** @param array<string, mixed> $data */
    private function page(string $view, array $link, array $data = [], int $status = 200): Response
    {
        $map = null;
        if ($view === 'carte' || $view === 'cr') {
            try {
                $maps = new AtakMapRepository();
                $map = $maps->getById((int) $link['map_id']) ?? $maps->getDefaultForTenant((int) $link['tenant_id']);
            } catch (\Throwable) {
            }
        }
        $app = AtakMobileLinkService::APPS;
        $response = Response::view('m.' . $view, $data + [
            'title' => ($app[$view === 'compte_rendu' ? 'compte-rendu' : $view][1] ?? 'Sur mon téléphone') . ' — Athena',
            'mobileLink' => $link,
            'mobileView' => $view,
            'mapConfig' => $map !== null ? self::mapConfig($map) : null,
            'csrf' => Csrf::token(),
            // Entrée « Briefs air » du menu : seulement pour un pilote ou un membre d'équipage.
            'showBriefs' => ($data['aircraft'] ?? $data['aircrew'] ?? $this->links()->aircrew($link)) !== [],
            'expiresAt' => (string) ($link['session_expires_at'] ?? ''),
        ]);
        if ($status !== 200) {
            $response->setStatusCode($status);
        }

        return $response;
    }

    /**
     * Fond de carte (tuiles et repère) de la carte de la communauté, comme la carte ATAK mobile.
     *
     * @param array<string, mixed> $map
     * @return array<string, mixed>
     */
    public static function mapConfig(array $map): array
    {
        $c = is_array($map['config'] ?? null) ? $map['config'] : [];
        $slug = (string) ($map['slug'] ?? 'altis');
        $tp = (string) ($map['tile_pattern'] ?? '');

        return [
            'slug' => $slug,
            'tilePattern' => function_exists('atak_resolve_tile_pattern') ? atak_resolve_tile_pattern($tp, $slug) : $tp,
            'center' => $c['center'] ?? [15000, 15000],
            'defaultZoom' => (int) ($c['defaultZoom'] ?? 3),
            'minZoom' => (int) ($c['minZoom'] ?? 0),
            'maxZoom' => (int) ($c['maxZoom'] ?? 6),
            'tileSize' => (int) ($c['tileSize'] ?? 212),
            'worldSize' => (int) ($c['worldSize'] ?? 30720),
            'crs' => $c['crs'] ?? ['factorx' => 0.006839, 'factory' => 0.006836, 'tileWidth' => 212],
        ];
    }

    private function expired(string $why = ''): Response
    {
        return Response::view('m.expired', [
            'title' => 'Sur mon téléphone — Athena',
            'why' => $why !== '' ? $why : 'Cette page s’ouvre en scannant le QR code « Sur mon téléphone » dans le téléphone du jeu. La session est terminée ou a été coupée depuis votre profil.',
        ])->setStatusCode(403);
    }

    private function links(): AtakMobileLinkService
    {
        return $this->links ??= new AtakMobileLinkService();
    }

    private function atak(): AtakDataRepository
    {
        return $this->atak ??= new AtakDataRepository();
    }

    private function limiter(): FileRateLimiter
    {
        return $this->limiter ??= new FileRateLimiter();
    }
}
