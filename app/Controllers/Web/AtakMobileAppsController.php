<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AtakDataRepository;
use App\Repositories\AtakMapRepository;
use App\Services\Atak\AtakMobileHistoryService;
use App\Services\Atak\AtakMobileLinkService;
use App\Services\Security\FileRateLimiter;
use App\Services\Tactical\AirBriefService;
use App\Services\Tactical\NineLineBoardService;
use App\Support\AtakMobileReportCatalog;

/**
 * « Sur mon téléphone » : versions mobiles des apps de l'ATAK en jeu, ouvertes en scannant le QR code affiché dans
 * le téléphone du jeu. /m/{jeton} rachète le jeton (usage unique, 5 min) et ouvre une session limitée à ces pages :
 *   /m/cr            compte rendu rapide (CONTACT, SALUTE, SPOTREP, blessé, libre) → confié à l'ATAK du joueur
 *   /m/cr/envoyes    historique des comptes rendus envoyés ; /m/cr/recus ceux qui me sont adressés ; /m/cr/lire/{id} lecture
 *   /m/radio         Radio et messages : canaux de tchat, messages privés, et ce que le jeu ne remonte pas (SMS, MPU, musique)
 *   /m/compte-rendu  CR de fin d'opération ouvert dans le téléphone en jeu → confié à l'ATAK du joueur
 *   /m/carte         carte Overwatch de la partie : amis, marqueurs, tracés, appareils, appuis ; poser / modifier /
 *                    retirer ses marqueurs (lectures aux adresses de l'Overwatch, AtakMobileOverwatchAccess)
 *   /m/briefs        briefs air (9-LINE reçus par l'appareil), seulement pour un pilote ou un membre d'équipage
 *   /m/controle      Contrôle ID (COMSPEC IDENTITY) : contrôles d'identité faits par le joueur dans la partie, détail par
 *                    niveau, photos ; « Joindre à une FRM » crée un brouillon de fiche à valider par le bureau SSE
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

    /** Intitulé des pages qui ne sont pas une app du QR code. */
    private const VIEW_TITLES = [
        'cr_list' => 'Historique des comptes rendus',
        'cr_read' => 'Compte rendu',
        'radio' => 'Radio et messages',
        'radio_thread' => 'Radio et messages',
        'radio_sms' => 'Radio et messages',
        'radio_mpu' => 'Radio et MPU5',
        'musique' => 'Musique',
    ];

    public function __construct(
        private ?AtakMobileLinkService $links = null,
        private ?AtakDataRepository $atak = null,
        private ?FileRateLimiter $limiter = null,
        private ?AirBriefService $air = null,
        private ?\App\Services\Sse\SseIdCheckInsightService $idChecks = null,
        private ?AtakMobileHistoryService $history = null,
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

        return $this->page('home', $link, [
            'aircrew' => $this->links()->aircrew($link),
            'unread' => $this->history()->unreadCount($link),
        ]);
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

    // ── Historique et lecture des comptes rendus ─────────────────────────────

    public function crSent(Request $request, array $params = []): Response
    {
        $link = $this->links()->current();
        if ($link === null) {
            return $this->expired();
        }

        return $this->page('cr_list', $link, ['mode' => 'sent', 'rows' => $this->history()->sent($link)]);
    }

    public function crReceived(Request $request, array $params = []): Response
    {
        $link = $this->links()->current();
        if ($link === null) {
            return $this->expired();
        }

        return $this->page('cr_list', $link, ['mode' => 'received', 'rows' => $this->history()->received($link)]);
    }

    /** Lecture d'un compte rendu : le mien, ou un compte rendu qui m'est adressé. */
    public function crRead(Request $request, array $params = []): Response
    {
        $link = $this->links()->current();
        if ($link === null) {
            return $this->expired();
        }
        $report = $this->history()->report($link, (int) ($params['id'] ?? 0));
        if ($report === null) {
            return $this->page('cr_read', $link, ['report' => null], 404);
        }

        return $this->page('cr_read', $link, ['report' => $report]);
    }

    /** « J'ai lu » : accusé de lecture d'un compte rendu reçu (même effet que dans l'ATAK en jeu). */
    public function crMarkRead(Request $request, array $params = []): Response
    {
        $link = $this->guardPost($request);
        if ($link instanceof Response) {
            return $link;
        }
        $id = (int) ($params['id'] ?? 0);
        $report = $this->history()->report($link, $id);
        if ($report === null || $report['mine'] || $report['routing_id'] < 1) {
            return Response::json(['ok' => false, 'error' => 'not_found'], 404);
        }
        $ok = $report['read'] || $this->history()->markRead($link, $id, (int) $report['routing_id']);

        return Response::json(['ok' => $ok], $ok ? 200 : 503);
    }

    // ── Radio et messages ────────────────────────────────────────────────────

    public function radio(Request $request, array $params = []): Response
    {
        $link = $this->links()->current();
        if ($link === null) {
            return $this->expired();
        }

        return $this->page('radio', $link, ['channels' => $this->history()->channels($link)]);
    }

    public function radioChannel(Request $request, array $params = []): Response
    {
        $link = $this->links()->current();
        if ($link === null) {
            return $this->expired();
        }
        $key = \App\Support\AtakChatChannel::normalizeKey((string) ($params['key'] ?? ''));
        $label = $key;
        foreach ($this->history()->channels($link) as $c) {
            if ($c['key'] === $key) {
                $label = $c['label'];
            }
        }

        return $this->page('radio_thread', $link, [
            'threadTitle' => $label,
            'messages' => $this->history()->channelMessages($link, $key),
            'private' => false,
        ]);
    }

    public function radioPrivate(Request $request, array $params = []): Response
    {
        $link = $this->links()->current();
        if ($link === null) {
            return $this->expired();
        }

        return $this->page('radio_thread', $link, [
            'threadTitle' => 'Messages privés',
            'messages' => $this->history()->privateMessages($link),
            'private' => true,
        ]);
    }

    /** Ancienne adresse de la page des données absentes : SMS seuls désormais, MPU5 et musique ont leur page. */
    public function radioAbsent(Request $request, array $params = []): Response
    {
        return Response::redirect(url('m/radio/sms'));
    }

    public function radioSms(Request $request, array $params = []): Response
    {
        $link = $this->links()->current();
        if ($link === null) {
            return $this->expired();
        }

        return $this->page('radio_sms', $link, ['privateCount' => count($this->history()->privateMessages($link))]);
    }

    public function radioMpu(Request $request, array $params = []): Response
    {
        $link = $this->links()->current();
        if ($link === null) {
            return $this->expired();
        }
        $units = $this->atak()->getUnits((int) $link['tenant_id'], (int) $link['map_id']);

        return $this->page('radio_mpu', $link, AtakMobileHistoryService::mpuBoard($units, (string) ($link['steam_uid'] ?? '')));
    }

    public function musique(Request $request, array $params = []): Response
    {
        $link = $this->links()->current();
        if ($link === null) {
            return $this->expired();
        }
        $vue = mb_substr(trim((string) ($request->query('vue') ?? 'global')), 0, 32);
        $q = mb_substr(trim((string) ($request->query('q') ?? '')), 0, 80);
        $data = ['vue' => $vue, 'heading' => '', 'tracks' => [], 'playlists' => [], 'q' => $q, 'failed' => false];
        try {
            $service = new \App\Services\Atak\AtakMobileMusicService(new \App\Repositories\MusicLibraryRepository());
            $data = $service->view((int) $link['tenant_id'], (int) $link['user_id'], $vue, $q) + $data;
        } catch (\Throwable) {
            $data['failed'] = true;
        }

        return $this->page('musique', $link, $data);
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

    /**
     * Ma position (mon unité remontée par mon ATAK, sinon la position envoyée avec le QR code) : grille des comptes
     * rendus, centrage et « Moi » sur la carte. Le reste de la carte (amis, marqueurs, tracés, appareils, appuis) est
     * lu aux adresses de l'Overwatch avec la session du téléphone (AtakMobileOverwatchAccess).
     */
    public function carteData(Request $request, array $params = []): Response
    {
        $link = $this->links()->current();
        if ($link === null) {
            return Response::json(['ok' => false, 'error' => 'session'], 401);
        }
        $me = $this->me($link);

        return Response::json([
            'ok' => true,
            'me' => $me !== null ? ['id' => (int) ($me['id'] ?? 0), 'x' => (float) $me['pos_x'], 'y' => (float) $me['pos_y'], 'grid' => NineLineBoardService::grid((float) $me['pos_x'], (float) $me['pos_y'])] : $this->linkPos($link),
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

        return Response::json(['ok' => true, 'aircraft' => $aircraft, 'briefs' => $this->briefsFor($link, $aircraft)]);
    }

    /** Accusé sur un brief reçu par mon appareil : read (lu), wilco ou unable. */
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
        $answer = strtolower(trim((string) $request->input('answer', '')));
        if (!in_array($answer, ['read', 'wilco', 'unable'], true)) {
            return Response::json(['ok' => false, 'error' => 'invalid'], 422);
        }
        $mine = array_values(array_filter($this->briefsFor($link, $aircraft), static fn (array $b): bool => $b['id'] === $id));
        if ($mine === []) {
            return Response::json(['ok' => false, 'error' => 'not_found'], 404);
        }
        $ok = $this->acknowledgeBrief($link, $mine[0], $answer);

        return Response::json(['ok' => $ok], $ok ? 200 : 503);
    }

    /** Contrôle ID : mes contrôles d'identité de la partie (le plus récent d'abord), détail par niveau et photos. */
    public function controle(Request $request, array $params = []): Response
    {
        $link = $this->links()->current();
        if ($link === null) {
            return $this->expired();
        }
        try {
            $checks = $this->idChecks()->forMobile($link);
        } catch (\Throwable) {
            $checks = [];
        }

        return $this->page('controle', $link, [
            'checks' => $checks,
            'noSteam' => trim((string) ($link['steam_uid'] ?? '')) === '',
            'openId' => max(0, (int) $request->queryString('id')),
        ]);
    }

    /**
     * « Joindre à une FRM » : brouillon de fiche de renseignement né du contrôle (texte du contrôle, remarque du
     * joueur), rattaché au contrôle et listé « à valider » dans le bureau SSE. Seulement sur un de mes contrôles.
     */
    public function controleFrm(Request $request, array $params = []): Response
    {
        $link = $this->guardPost($request);
        if ($link instanceof Response) {
            return $link;
        }
        $check = $this->idChecks()->mobileCheck($link, (int) ($params['id'] ?? 0));
        if ($check === null) {
            return Response::json(['ok' => false, 'error' => 'not_found'], 404);
        }
        $remark = mb_substr(trim((string) $request->input('remark', '')), 0, 400);
        $res = $this->idChecks()->draftFromCheck((int) $link['tenant_id'], $check, [
            'label' => trim((string) ($link['player_name'] ?? '')),
            'user_id' => (int) $link['user_id'],
            'steam_id' => (string) ($link['steam_uid'] ?? ''),
        ], $remark);
        if (!$res['ok']) {
            return Response::json($res, 503);
        }

        return Response::json($res);
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
     * Boîte du pilote (AirBriefService, la même que l'espace BRIEFS AIR du téléphone en jeu) : demandes adressées à mon
     * appareil ou à mon nom de jeu, des 3 dernières heures, la plus récente d'abord.
     *
     * @param list<string> $aircraft
     * @return list<array<string, mixed>>
     */
    private function briefsFor(array $link, array $aircraft): array
    {
        $names = array_merge($aircraft, [(string) ($link['player_name'] ?? '')]);
        $out = [];
        foreach ($this->air()->inbox((int) $link['tenant_id'], $names) as $b) {
            $out[] = [
                'id' => (int) $b['id'],
                'ref' => (string) $b['ref'],
                'format' => (string) $b['format'],
                'from' => (string) $b['from'],
                'to' => (string) $b['recipient'],
                'time' => (string) $b['time'],
                'status' => (string) $b['status'],
                'lines' => array_values(array_map('strval', (array) $b['lines'])),
                'remarks' => (string) ($b['remarks'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Accusé du pilote depuis le vrai téléphone : même enregistrement que l'ATAK en jeu (POST /api/cas/{ref}/status),
     * vu par le JTAC sur le site et dans son app JTAC.
     */
    private function acknowledgeBrief(array $link, array $brief, string $status): bool
    {
        try {
            $res = $this->air()->acknowledge((int) $link['tenant_id'], (string) $brief['ref'], $status, (string) $brief['to']);

            return $res['result'] === 'ok';
        } catch (\Throwable) {
            return false;
        }
    }

    private function idChecks(): \App\Services\Sse\SseIdCheckInsightService
    {
        return $this->idChecks ??= new \App\Services\Sse\SseIdCheckInsightService();
    }

    private function air(): \App\Services\Tactical\AirBriefService
    {
        return $this->air ??= new \App\Services\Tactical\AirBriefService();
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
        if (in_array($view, ['carte', 'cr', 'briefs'], true)) {
            try {
                $maps = new AtakMapRepository();
                $map = $maps->getById((int) $link['map_id']) ?? $maps->getDefaultForTenant((int) $link['tenant_id']);
            } catch (\Throwable) {
            }
        }
        $app = AtakMobileLinkService::APPS;
        $template = match ($view) {
            'cr' => 'm.cr',
            'compte_rendu' => 'm.compte_rendu',
            'carte' => 'm.carte',
            'briefs' => 'm.briefs',
            'briefs_denied' => 'm.briefs_denied',
            'controle' => 'm.controle',
            'cr_list' => 'm.cr_list',
            'cr_read' => 'm.cr_read',
            'radio' => 'm.radio',
            'radio_thread' => 'm.radio_thread',
            'radio_sms' => 'm.radio_sms',
            'radio_mpu' => 'm.radio_mpu',
            'musique' => 'm.musique',
            default => 'm.home',
        };
        $response = Response::view($template, $data + [
            'title' => (self::VIEW_TITLES[$view] ?? $app[$view === 'compte_rendu' ? 'compte-rendu' : $view][1] ?? 'Sur mon téléphone') . ' — Athena',
            'mobileLink' => $link,
            'mobileView' => $view,
            'mapConfig' => $map !== null ? self::mapConfig($map) : null,
            // Carte Overwatch du téléphone : carte de la partie (lectures /api/… avec la session du téléphone).
            'mapId' => (int) $link['map_id'],
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
     * Fond de carte (tuiles, repère, décalage, couche aérienne) : exactement celui de la carte Overwatch.
     *
     * @param array<string, mixed> $map
     * @return array<string, mixed>
     */
    public static function mapConfig(array $map): array
    {
        return \App\Support\AtakOverwatchMapConfig::fromMap($map);
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

    private function history(): AtakMobileHistoryService
    {
        return $this->history ??= new AtakMobileHistoryService();
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
