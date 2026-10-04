<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakExplosiveTimerRepository;
use App\Repositories\AtakVehicleTrackingRepository;
use App\Repositories\AtakWebCommandRepository;
use App\Support\AtakPlanAccess;
use App\Support\ComspecApiKeyAuth;
use App\Support\SteamId;

/**
 * Commandes du poste web vers les téléphones en jeu, et notifications du téléphone du joueur connecté.
 *
 *  - Drones appairés : fonctions du drone (pas le pilotage), photo de la caméra du drone.
 *  - Charges ACE suivies : raccorder / rendre au déclencheur local, mise à feu, séquence.
 *  - Mon téléphone ATAK : dernières notifications reçues, tout marquer lu, préférences.
 *
 * Mêmes droits que le déclenchement de charge depuis la carte : compte connecté au portail
 * (session web, jamais la clé d’accès du mod) + jeton CSRF. Le téléphone du pilote ou du
 * propriétaire de la charge exécute, puis rend compte (succès ou échec et sa raison).
 */
final class AtakWebCommandApiController
{
    private const DEFAULT_MAP_ID = 1;

    /** Ordres drone acceptés → durée de vie (s) en file. */
    private const UAV_CMDS = [
        'hover' => 90, 'home' => 90, 'rth' => 90, 'land' => 90, 'takeoff' => 90, 'standby' => 90,
        'follow_me' => 90, 'follow_unit' => 90, 'goto' => 90, 'loiter' => 90, 'observe' => 90,
        'hunt' => 90, 'alt' => 90, 'speed' => 90, 'resume' => 90, 'force' => 90, 'photo' => 60,
    ];

    private const EXPLO_ACTIONS = ['arm', 'disarm', 'fire', 'sequence'];

    /** @var array<string, mixed>|null */
    private ?array $bodyCache = null;

    private AtakWebCommandRepository $commands;

    public function __construct(?AtakWebCommandRepository $commands = null)
    {
        $this->commands = $commands ?? new AtakWebCommandRepository();
    }

    /* ------------------------------------------------------------------ */
    /* Points d’entrée appelés par AtakApiController (canal des charges)    */
    /* ------------------------------------------------------------------ */

    /**
     * Lignes « @wc » ajoutées à GET /api/atak/explosive-timers/commands.
     *
     * @return list<array{id:int,charge_id:string,requested_by:string}>
     */
    public static function wireRowsForGame(int $tenantId, int $mapId): array
    {
        try {
            return (new AtakWebCommandRepository())->wireRows($tenantId, $mapId);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * POST /api/atak/explosive-timers venant du mod : compte rendu de commande (web_cmd_ack)
     * ou miroir des notifications du téléphone (atak_notifs). Null = pas pour nous.
     *
     * @param array<string, mixed> $body
     */
    public static function ingestFromGame(int $tenantId, array $body, ?string $steamUid): ?Response
    {
        $hasAck = isset($body['web_cmd_ack']) && is_array($body['web_cmd_ack']);
        $hasNotifs = isset($body['atak_notifs']) && is_array($body['atak_notifs']);
        if (!$hasAck && !$hasNotifs) {
            return null;
        }
        if (ComspecApiKeyAuth::extractPresentedKey() === '') {
            return Response::json(['error' => 'forbidden', 'message' => 'Réservé au mod en jeu.'], 403);
        }
        $steam = SteamId::normalize($steamUid ?? (string) ($body['steam_uid'] ?? '')) ?? '';
        $repo = new AtakWebCommandRepository();
        if (!$repo->ensureSchema()) {
            return Response::json(['ok' => false, 'error' => 'migration_required'], 503);
        }
        $out = ['ok' => true];
        if ($hasAck) {
            $a = $body['web_cmd_ack'];
            $status = strtolower(trim((string) ($a['status'] ?? '')));
            if ($status === '') {
                $status = !empty($a['ok']) ? 'done' : 'failed';
            }
            $data = isset($a['data']) && is_array($a['data']) ? $a['data'] : [];
            $out['acked'] = $repo->ack(
                $tenantId,
                (int) ($a['id'] ?? 0),
                $steam !== '' ? $steam : null,
                $status,
                trim((string) ($a['msg'] ?? '')),
                $data
            );
        }
        if ($hasNotifs) {
            if ($steam === '') {
                return Response::json(['ok' => false, 'error' => 'steam_required'], 400);
            }
            $unread = isset($body['atak_notif_unread']) ? (int) $body['atak_notif_unread'] : null;
            $out['notifs'] = $repo->ingestNotifs($tenantId, $steam, array_values($body['atak_notifs']), $unread);
        }

        return Response::json($out);
    }

    /* ------------------------------------------------------------------ */
    /* Web : journal des commandes                                         */
    /* ------------------------------------------------------------------ */

    /** GET /api/atak/web-commands?mapId=&kind= */
    public function index(Request $request, array $params = []): Response
    {
        $ctx = $this->webContext($request, false);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $kind = $request->query('kind');
        $kind = is_string($kind) && $kind !== '' ? $kind : null;

        return Response::json([
            'ok' => true,
            'commands' => $this->commands->listRecent($ctx['tenant_id'], $this->mapId($request), $kind, 40),
        ]);
    }

    /** POST /api/atak/web-commands/{id}/cancel */
    public function cancel(Request $request, array $params = []): Response
    {
        $ctx = $this->webContext($request, true);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $ok = $this->commands->cancel($ctx['tenant_id'], (int) ($params['id'] ?? 0));

        return Response::json([
            'ok' => $ok,
            'message' => $ok ? 'Commande annulée.' : 'Commande déjà prise par le téléphone, ou terminée.',
        ], $ok ? 200 : 409);
    }

    /* ------------------------------------------------------------------ */
    /* Web : drones                                                        */
    /* ------------------------------------------------------------------ */

    /**
     * POST /api/atak/web-commands/uav
     * Corps : { vehicle_callsign, cmd, x?, y?, r?, value?, callsign?, kind? }
     * Ordres : voir UAV_CMDS ; « force » = forcer l’exécution du dernier ordre (remise d’aplomb de l’IA du drone).
     */
    public function uav(Request $request, array $params = []): Response
    {
        $ctx = $this->webContext($request, true);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $tenantId = $ctx['tenant_id'];
        $body = $this->body($request);
        $mapId = $this->mapId($request);
        $cmd = strtolower(trim((string) ($body['cmd'] ?? '')));
        if (!isset(self::UAV_CMDS[$cmd])) {
            return $this->fail('Ordre drone inconnu.', 422);
        }
        $key = trim((string) ($body['vehicle_callsign'] ?? $body['uav'] ?? ''));
        if ($key === '') {
            return $this->fail('Drone non précisé.', 422);
        }
        $vehicle = (new AtakVehicleTrackingRepository())->findByCallsign($tenantId, $mapId, $key);
        $props = is_array($vehicle['properties'] ?? null) ? $vehicle['properties'] : [];
        if ($vehicle === null || strtoupper((string) ($vehicle['vehicle_class'] ?? '')) !== 'UAV' || ($props['kind'] ?? '') !== 'uav') {
            return $this->fail('Ce drone n’est plus suivi sur la carte.', 404);
        }
        $pilotUid = preg_replace('/\D/', '', (string) ($props['pilot_uid'] ?? '')) ?? '';
        $netId = (string) ($props['net_id'] ?? '');
        if ($pilotUid === '' || $netId === '') {
            return $this->fail('Ce drone n’est pas appairé à un téléphone : il ne reçoit pas d’ordres du poste.', 409);
        }
        $lastSeen = strtotime((string) ($vehicle['last_seen_at'] ?? $vehicle['updated_at'] ?? ''));
        if ($lastSeen !== false && (time() - $lastSeen) > 120) {
            return $this->fail('Le drone ne donne plus de nouvelles depuis plus de 2 minutes.', 409);
        }

        $wire = [];
        $args = ['cmd' => $cmd];
        $needPos = in_array($cmd, ['goto', 'loiter', 'observe', 'hunt'], true);
        if ($needPos) {
            $x = $this->num($body['x'] ?? null);
            $y = $this->num($body['y'] ?? null);
            if ($x === null || $y === null || $x < 0 || $y < 0 || $x > 100000 || $y > 100000) {
                return $this->fail('Point invalide : cliquez sur la carte.', 422);
            }
            $wire['x'] = (int) round($x);
            $wire['y'] = (int) round($y);
            $args['x'] = $wire['x'];
            $args['y'] = $wire['y'];
        }
        if (in_array($cmd, ['loiter', 'observe'], true)) {
            $r = (int) round($this->num($body['r'] ?? null) ?? 150);
            $wire['r'] = max(30, min(800, $r));
            $args['r'] = $wire['r'];
        }
        if ($cmd === 'hunt') {
            // Recherche sur zone : centre + rayon (orbite de recherche du drone, 50 à 1000 m).
            $r = (int) round($this->num($body['r'] ?? null) ?? 250);
            $wire['r'] = max(50, min(1000, $r));
            $args['r'] = $wire['r'];
        }
        if ($cmd === 'standby') {
            $k = strtoupper(trim((string) ($body['kind'] ?? '')));
            if (in_array($k, ['LAND', 'HOVER'], true)) {
                $wire['k'] = $k;
                $args['kind'] = $k;
            }
        }
        if ($cmd === 'alt') {
            $v = (int) round($this->num($body['value'] ?? null) ?? 0);
            if ($v < 2 || $v > 500) {
                return $this->fail('Altitude hors limites (2 à 500 m).', 422);
            }
            $wire['v'] = $v;
            $args['value'] = $v;
        }
        if ($cmd === 'speed') {
            $v = (int) round($this->num($body['value'] ?? null) ?? 0);
            if ($v < 5 || $v > 150) {
                return $this->fail('Vitesse hors limites (5 à 150 km/h).', 422);
            }
            $wire['v'] = $v;
            $args['value'] = $v;
        }
        if ($cmd === 'follow_unit') {
            $cs = AtakWebCommandRepository::wireValue(trim((string) ($body['callsign'] ?? '')), 60);
            if ($cs === '') {
                return $this->fail('Choisissez l’unité à suivre.', 422);
            }
            $wire['cs'] = $cs;
            $args['callsign'] = $cs;
        }
        $label = trim((string) ($vehicle['vehicle_name'] ?? $key));
        $row = $this->commands->enqueue(
            $tenantId,
            $mapId,
            'uav',
            $cmd,
            $pilotUid,
            $netId,
            $label,
            $args,
            $wire,
            $ctx['by'],
            $ctx['user_id'],
            self::UAV_CMDS[$cmd]
        );
        if ($row === null) {
            return $this->fail('Commande non enregistrée (base indisponible ?).', 503);
        }

        return Response::json([
            'ok' => true,
            'command' => $row,
            'message' => 'Ordre en file : le téléphone du pilote le prendra dans quelques secondes.',
        ], 201);
    }

    /* ------------------------------------------------------------------ */
    /* Web : charges                                                       */
    /* ------------------------------------------------------------------ */

    /**
     * POST /api/atak/web-commands/explo
     * Corps : { action: arm|disarm|fire|sequence, ids: [id timer...], delay?, gap?, confirm? }
     */
    public function explo(Request $request, array $params = []): Response
    {
        $ctx = $this->webContext($request, true);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $tenantId = $ctx['tenant_id'];
        $body = $this->body($request);
        $mapId = $this->mapId($request);
        $action = strtolower(trim((string) ($body['action'] ?? '')));
        if (!in_array($action, self::EXPLO_ACTIONS, true)) {
            return $this->fail('Action inconnue.', 422);
        }
        if (in_array($action, ['fire', 'sequence'], true) && empty($body['confirm'])) {
            return $this->fail('Confirmez la mise à feu.', 422);
        }
        $ids = array_values(array_unique(array_filter(
            array_map('intval', is_array($body['ids'] ?? null) ? $body['ids'] : [$body['id'] ?? 0]),
            static fn (int $i): bool => $i > 0
        )));
        if ($ids === []) {
            return $this->fail('Aucune charge choisie.', 422);
        }
        if (count($ids) > 12) {
            return $this->fail('12 charges au plus par ordre.', 422);
        }
        $timers = new AtakExplosiveTimerRepository();
        $byId = [];
        foreach ($timers->listForMap($tenantId, $mapId, 60) as $row) {
            $byId[(int) ($row['id'] ?? 0)] = $row;
        }
        $rows = [];
        foreach ($ids as $id) {
            $row = $byId[$id] ?? null;
            if ($row === null || ($row['status'] ?? '') !== 'armed') {
                return $this->fail('Une des charges n’est plus armée (déjà sautée ou désamorcée).', 409);
            }
            $kind = (string) ($row['trigger_kind'] ?? '');
            if ($kind === 'timer') {
                return $this->fail('Minuterie lancée : la charge sautera seule, elle ne se commande pas à distance.', 409);
            }
            if ($kind === 'mine') {
                return $this->fail('Une mine ne se commande pas à distance.', 409);
            }
            if (AtakWebCommandRepository::wireValue((string) $row['charge_id'], 96) !== (string) $row['charge_id']) {
                return $this->fail('Identifiant de charge illisible par le mod.', 422);
            }
            $rows[] = $row;
        }

        $delay = max(0, min(120, (int) round($this->num($body['delay'] ?? null) ?? 5)));
        $gap = max(0, min(30, (float) ($this->num($body['gap'] ?? null) ?? 1)));
        // Un ordre par propriétaire : chaque téléphone ne traite que ses charges.
        $groups = [];
        foreach ($rows as $row) {
            $groups[mb_strtolower(trim((string) ($row['author'] ?? '')))][] = $row;
        }
        if ($action !== 'sequence') {
            $groups = [];
            foreach ($rows as $row) {
                $groups[] = [$row];
            }
        }
        $created = [];
        foreach ($groups as $group) {
            $cids = array_map(static fn (array $r): string => (string) $r['charge_id'], $group);
            $labels = array_map(static fn (array $r): string => trim((string) ($r['magazine_label'] ?? 'Charge')) ?: 'Charge', $group);
            $owner = trim((string) ($group[0]['author'] ?? ''));
            $wire = [];
            $args = ['action' => $action, 'charges' => $cids, 'timer_ids' => array_map(static fn (array $r): int => (int) $r['id'], $group)];
            if ($action === 'sequence') {
                $wire['d'] = $delay;
                $wire['g'] = $gap;
                $args['delay'] = $delay;
                $args['gap'] = $gap;
            }
            $label = (count($group) > 1 ? count($group) . ' charges' : $labels[0]) . ($owner !== '' ? ' — ' . $owner : '');
            $row = $this->commands->enqueue(
                $tenantId,
                $mapId,
                'explo',
                $action,
                '*',
                implode(',', $cids),
                $label,
                $args,
                $wire,
                $ctx['by'],
                $ctx['user_id'],
                120
            );
            if ($row !== null) {
                $created[] = $row;
            }
        }
        if ($created === []) {
            return $this->fail('Commande non enregistrée (base indisponible ?).', 503);
        }

        return Response::json([
            'ok' => true,
            'commands' => $created,
            'message' => 'Ordre transmis : le téléphone du propriétaire doit être allumé et à portée.',
        ], 201);
    }

    /* ------------------------------------------------------------------ */
    /* Web : mon téléphone ATAK                                            */
    /* ------------------------------------------------------------------ */

    /** GET /api/atak/my-phone/notifs */
    public function myNotifs(Request $request, array $params = []): Response
    {
        $ctx = $this->webContext($request, false);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $steam = $this->sessionSteam($ctx['user_id']);
        if ($steam === '') {
            return Response::json([
                'ok' => true,
                'linked' => false,
                'items' => [],
                'prefs' => $this->commands->getPrefs($ctx['tenant_id'], ''),
                'message' => 'Liez votre compte Steam à Athena pour voir les notifications de votre téléphone.',
            ]);
        }
        $items = $this->commands->listNotifs($ctx['tenant_id'], $steam, 60);

        return Response::json([
            'ok' => true,
            'linked' => true,
            'items' => $items,
            'unread' => count(array_filter($items, static fn (array $i): bool => !$i['read'])),
            'prefs' => $this->commands->getPrefs($ctx['tenant_id'], $steam),
        ]);
    }

    /** POST /api/atak/my-phone/notifs/read — marque tout lu ici et sur le téléphone. */
    public function myNotifsRead(Request $request, array $params = []): Response
    {
        $ctx = $this->webContext($request, true);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $steam = $this->sessionSteam($ctx['user_id']);
        if ($steam === '') {
            return $this->fail('Compte Steam non lié.', 409);
        }
        $n = $this->commands->markAllRead($ctx['tenant_id'], $steam);
        $this->commands->supersede($ctx['tenant_id'], 'notif', 'read', $steam);
        $this->commands->enqueue(
            $ctx['tenant_id'],
            $this->mapId($request),
            'notif',
            'read',
            $steam,
            '',
            'Mon téléphone',
            [],
            [],
            $ctx['by'],
            $ctx['user_id'],
            600
        );

        return Response::json(['ok' => true, 'marked' => $n]);
    }

    /** POST /api/atak/my-phone/notif-prefs — { muted: [..], silent, banners, toast_seconds } */
    public function myNotifPrefs(Request $request, array $params = []): Response
    {
        $ctx = $this->webContext($request, true);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $steam = $this->sessionSteam($ctx['user_id']);
        if ($steam === '') {
            return $this->fail('Compte Steam non lié.', 409);
        }
        $body = $this->body($request);
        $muted = is_array($body['muted'] ?? null) ? $body['muted'] : [];
        $silent = !empty($body['silent']);
        $banners = !array_key_exists('banners', $body) || !empty($body['banners']);
        $toast = (int) round($this->num($body['toast_seconds'] ?? null) ?? 0);
        $this->commands->savePrefs($ctx['tenant_id'], $steam, $muted, $silent, $banners, $toast);
        $prefs = $this->commands->getPrefs($ctx['tenant_id'], $steam);
        // Appliquées au prochain passage du joueur en jeu (gardées 24 h en file, la dernière remplace les autres).
        $this->commands->supersede($ctx['tenant_id'], 'notif', 'prefs', $steam);
        $this->commands->enqueue(
            $ctx['tenant_id'],
            $this->mapId($request),
            'notif',
            'prefs',
            $steam,
            '',
            'Mon téléphone',
            $prefs,
            [
                'mute' => implode(',', $prefs['muted']),
                'silent' => $prefs['silent'],
                'banners' => $prefs['banners'],
                'toast' => $prefs['toast_seconds'],
            ],
            $ctx['by'],
            $ctx['user_id'],
            86400
        );

        return Response::json([
            'ok' => true,
            'prefs' => $prefs,
            'message' => 'Préférences enregistrées : appliquées au téléphone dès qu’il est en jeu.',
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Outils                                                              */
    /* ------------------------------------------------------------------ */

    /**
     * Compte web connecté (pas la clé du mod), communauté et offre ATAK, CSRF pour les écritures.
     *
     * @return array{tenant_id:int,user_id:int,by:string}|Response
     */
    private function webContext(Request $request, bool $write): array|Response
    {
        if (ComspecApiKeyAuth::extractPresentedKey() !== '') {
            return Response::json([
                'error' => 'forbidden',
                'message' => 'Ces commandes partent du poste de commandement, pas du terrain.',
            ], 403);
        }
        $userId = (int) (Session::get('user_id') ?? 0);
        if ($userId < 1) {
            return Response::json([
                'error' => 'forbidden',
                'message' => 'Connectez-vous au portail pour commander depuis la carte.',
            ], 403);
        }
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if ($tenantId < 1) {
            return Response::json(['error' => 'tenant_context_required', 'message' => 'Communauté non sélectionnée.'], 403);
        }
        if (!AtakPlanAccess::allows($tenantId)) {
            return AtakPlanAccess::deniedJson();
        }
        if ($write) {
            $body = $this->body($request);
            $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $request->input('_csrf_token') ?? ($body['_csrf_token'] ?? ''));
            if (!Csrf::validate($token)) {
                return Response::json(['error' => 'csrf_invalid', 'message' => 'Session expirée : rechargez la page.'], 403);
            }
        }
        if (!$this->commands->ensureSchema()) {
            return Response::json([
                'error' => 'migration_required',
                'message' => 'Les commandes depuis le poste ne sont pas encore disponibles sur cette communauté.',
            ], 503);
        }
        $by = trim((string) (Session::get('callsign') ?? ''));
        if ($by === '') {
            $by = trim((string) (Session::get('display_name') ?? ''));
        }

        return [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'by' => $by !== '' ? $by : 'Poste de commandement',
        ];
    }

    private function sessionSteam(int $userId): string
    {
        try {
            $pdo = \App\Core\Database::getPdo();
            $st = $pdo->prepare('SELECT steam_id FROM users WHERE id = ? LIMIT 1');
            $st->execute([$userId]);
            $raw = $st->fetchColumn();
        } catch (\Throwable) {
            return '';
        }

        return is_string($raw) ? (SteamId::normalize($raw) ?? '') : '';
    }

    private function mapId(Request $request): int
    {
        $body = $this->body($request);
        $raw = $body['mapId'] ?? $body['map_id'] ?? $request->query('mapId');
        $id = ($raw !== null && $raw !== '') ? (int) $raw : self::DEFAULT_MAP_ID;

        return $id < 1 ? self::DEFAULT_MAP_ID : $id;
    }

    private function num(mixed $v): ?float
    {
        if ($v === null || $v === '' || !is_numeric($v)) {
            return null;
        }
        $f = (float) $v;

        return is_finite($f) ? $f : null;
    }

    private function fail(string $message, int $code): Response
    {
        return Response::json(['ok' => false, 'error' => 'refused', 'message' => $message], $code);
    }

    /** @return array<string, mixed> */
    private function body(Request $request): array
    {
        if ($this->bodyCache !== null) {
            return $this->bodyCache;
        }
        $raw = file_get_contents('php://input');
        $decoded = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : null;
        $this->bodyCache = is_array($decoded) ? $decoded : [];

        return $this->bodyCache;
    }
}
