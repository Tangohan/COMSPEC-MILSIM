<?php

declare(strict_types=1);

namespace App\Services\AtakToc;

use App\Core\Database;
use PDO;

/**
 * Salle TOC partagée entre le jeu et le TOC web (une par communauté, carte et camp).
 *
 * Règle d'autorité (docs/TOC-WEB.md) :
 *  - le serveur du jeu fait foi quand il est en liaison (instantané reçu depuis moins de 2 min) : son état remplace
 *    celui du site à chaque envoi (BriefingPlan.Push, canal « toc_room ») ;
 *  - une action du web est gardée (atak_toc_actions) et envoyée au jeu par la file des commandes web (fil WC1, genre
 *    « toc ») ; elle reste affichée par-dessus l'état du jeu, marquée « en attente », jusqu'à ce que l'instantané du
 *    jeu la cite (liste « wc » des commandes appliquées) ; deux actions sur la même ligne : le serveur du jeu les
 *    applique dans l'ordre d'arrivée, la dernière gagne ;
 *  - sans jeu en liaison, le web tient la salle seul : ses actions restent affichées (12 h) et partent en jeu à la
 *    prochaine liaison du même terrain si leur délai le permet (FLASH 3 min, chronos 10 min, le reste 12 h) ;
 *    une action dont la commande a expiré sans être appliquée disparaît dès que le jeu est de nouveau en liaison.
 */
final class TocRoomService
{
    public const LIVE_SECONDS = 120;
    public const KEEP_SECONDS = 43200;
    public const SIDES = ['WEST', 'EAST', 'GUER', 'CIV'];

    /** Délai de livraison en jeu d'une action (secondes). */
    public const TTL = ['flash' => 180, 'flashClear' => 180, 'timerStart' => 600, 'timerStop' => 600, 'timerReset' => 600];
    public const TTL_DEFAULT = 43200;

    /** @var callable(int, int, string, string, array<string, scalar>, int, string, ?int): ?int */
    private $enqueue;

    /** @var callable(int, int): ?string */
    private $commandStatus;

    private ?PDO $db;

    /**
     * @param callable(int, int, string, string, array<string, scalar>, int, string, ?int): ?int|null $enqueue file des commandes web (tenant, carte, commande, référence, arguments, délai, auteur, membre) => id
     * @param callable(int, int): ?string|null $commandStatus statut d'une commande web (tenant, id)
     */
    public function __construct(?PDO $pdo = null, ?callable $enqueue = null, ?callable $commandStatus = null)
    {
        $this->db = $pdo;
        $this->enqueue = $enqueue ?? static function (int $tenantId, int $mapId, string $cmd, string $ref, array $args, int $ttl, string $who, ?int $userId): ?int {
            $repo = new \App\Repositories\AtakWebCommandRepository();
            $row = $repo->enqueue($tenantId, $mapId, 'toc', $cmd, '*', $ref, 'Poste TOC web', ['by' => $who], $args, $who, $userId, $ttl);

            return is_array($row) ? (int) $row['id'] : null;
        };
        $this->commandStatus = $commandStatus ?? static function (int $tenantId, int $id): ?string {
            $row = (new \App\Repositories\AtakWebCommandRepository())->find($tenantId, $id);

            return is_array($row) ? (string) $row['status'] : null;
        };
    }

    private function pdo(): PDO
    {
        return $this->db ??= Database::getPdo();
    }

    private static function dt(int $ts): string
    {
        return gmdate('Y-m-d H:i:s', $ts);
    }

    /** @return array<string, mixed>|null */
    private function row(int $tenantId, int $mapId, string $side): ?array
    {
        $st = $this->pdo()->prepare('SELECT * FROM atak_toc_rooms WHERE tenant_id = ? AND map_id = ? AND side = ? LIMIT 1');
        $st->execute([$tenantId, $mapId, $side]);
        $r = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($r) ? $r : null;
    }

    /**
     * Instantané reçu du jeu. Une révision plus ancienne de la même mission est ignorée.
     *
     * @param array<string, mixed> $payload {side, mission_key, world, rev, room{...}, wc[]}
     * @return array{ok: bool, reason: string}
     */
    public function ingestFromGame(int $tenantId, int $mapId, array $payload, ?int $now = null): array
    {
        $now ??= time();
        $side = strtoupper((string) ($payload['side'] ?? ''));
        if (!in_array($side, self::SIDES, true) || !is_array($payload['room'] ?? null)) {
            return ['ok' => false, 'reason' => 'invalid'];
        }
        $mission = PlanValidator::text($payload['mission_key'] ?? '', 190);
        $world = PlanValidator::world($payload['world'] ?? '');
        $rev = max(0, (int) ($payload['rev'] ?? 0));
        $prev = $this->row($tenantId, $mapId, $side);
        if ($prev !== null && (string) $prev['mission_key'] === $mission && (int) $prev['game_rev'] > $rev) {
            return ['ok' => false, 'reason' => 'stale'];
        }
        $state = TocRoom::fromGame($payload['room'], $now);
        $json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        if ($prev === null) {
            $this->pdo()->prepare('INSERT INTO atak_toc_rooms (tenant_id, map_id, side, mission_key, world, game_rev, game_seen_at, state_json, web_rev, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)')
                ->execute([$tenantId, $mapId, $side, $mission, $world, $rev, self::dt($now), $json, self::dt($now)]);
        } else {
            $this->pdo()->prepare('UPDATE atak_toc_rooms SET mission_key = ?, world = ?, game_rev = ?, game_seen_at = ?, state_json = ?, web_rev = web_rev + 1, updated_at = ? WHERE id = ?')
                ->execute([$mission, $world, $rev, self::dt($now), $json, self::dt($now), (int) $prev['id']]);
        }
        // Actions du web reprises par le jeu (liste des commandes appliquées), ou perdues (commande expirée).
        $applied = array_values(array_filter(array_map('intval', is_array($payload['wc'] ?? null) ? $payload['wc'] : []), static fn (int $i): bool => $i > 0));
        $pending = $this->pendingActions($tenantId, $mapId, $side, $now);
        $mark = $this->pdo()->prepare('UPDATE atak_toc_actions SET status = ?, applied_at = ? WHERE id = ?');
        foreach ($pending as $a) {
            $cmd = (int) ($a['web_command_id'] ?? 0);
            if ($cmd > 0 && in_array($cmd, $applied, true)) {
                $mark->execute(['applied', self::dt($now), $a['id']]);
                continue;
            }
            $status = $cmd > 0 ? ($this->commandStatus)($tenantId, $cmd) : null;
            if ($status === 'done') {
                $mark->execute(['applied', self::dt($now), $a['id']]);
            } elseif ($cmd < 1 || in_array($status, ['expired', 'failed', 'cancelled', null], true)) {
                $mark->execute(['dropped', self::dt($now), $a['id']]);
            }
        }

        return ['ok' => true, 'reason' => ''];
    }

    /** @return list<array<string, mixed>> */
    private function pendingActions(int $tenantId, int $mapId, string $side, int $now): array
    {
        $st = $this->pdo()->prepare(
            "SELECT * FROM atak_toc_actions WHERE tenant_id = ? AND map_id = ? AND side = ? AND status IN ('queued', 'sent') AND created_at >= ? ORDER BY id ASC LIMIT 400"
        );
        $st->execute([$tenantId, $mapId, $side, self::dt($now - self::KEEP_SECONDS)]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $args = json_decode((string) $r['args_json'], true);
            $out[] = [
                'id' => (int) $r['id'], 'act' => (string) $r['act'], 'args' => is_array($args) ? $args : [],
                'who' => (string) $r['actor_label'], 'at' => (int) strtotime((string) $r['created_at'] . ' UTC'),
                'web_command_id' => $r['web_command_id'] !== null ? (int) $r['web_command_id'] : null,
            ];
        }

        return $out;
    }

    /**
     * État affiché : dernier état du jeu, puis les actions du web pas encore reprises, rejouées dans l'ordre.
     *
     * @return array{state: array<string, mixed>, game: array<string, mixed>, version: string}
     */
    public function view(int $tenantId, int $mapId, string $side, ?int $now = null): array
    {
        $now ??= time();
        $row = $this->row($tenantId, $mapId, $side);
        $state = TocRoom::blank();
        $seen = null;
        if ($row !== null) {
            $dec = json_decode((string) $row['state_json'], true);
            if (is_array($dec)) {
                $state = array_merge($state, $dec);
            }
            $seen = $row['game_seen_at'] !== null ? (int) strtotime((string) $row['game_seen_at'] . ' UTC') : null;
        }
        $live = $seen !== null && ($now - $seen) <= self::LIVE_SECONDS;
        $pending = $this->pendingActions($tenantId, $mapId, $side, $now);
        foreach ($pending as $a) {
            $state = TocRoom::apply($state, $a['act'], $a['args'], $a['who'], $a['at'], 'W' . $a['id']);
        }
        if (is_array($state['flash'] ?? null) && ($now - (int) ($state['flash']['at'] ?? 0)) > 180) {
            $state['flash'] = null;
        }
        foreach ($state['timers'] as $i => $t) {
            [$txt, $run] = TocRoom::timerText($t, $now);
            $state['timers'][$i]['text'] = $txt;
            $state['timers'][$i]['state'] = $run;
        }
        $game = [
            'live' => $live,
            'seen_at' => $seen,
            'mission_key' => $row !== null ? (string) $row['mission_key'] : '',
            'world' => $row !== null ? (string) $row['world'] : '',
            'rev' => $row !== null ? (int) $row['game_rev'] : 0,
            'pending' => count($pending),
        ];
        $version = substr(sha1(($row['web_rev'] ?? 0) . '|' . ($row['game_rev'] ?? 0) . '|' . json_encode(array_column($pending, 'id')) . '|' . ($live ? 1 : 0)), 0, 16);

        return ['state' => $state, 'game' => $game, 'version' => $version];
    }

    /**
     * Action du TOC web : gardée, envoyée au jeu, rendue tout de suite dans l'état affiché.
     *
     * @return array{ok: bool, reason: string, action_id?: int}
     */
    public function act(int $tenantId, int $mapId, string $side, ?int $userId, string $who, string $act, mixed $args, ?int $now = null): array
    {
        $now ??= time();
        if (!in_array($side, self::SIDES, true)) {
            return ['ok' => false, 'reason' => 'side_invalid'];
        }
        [$clean, $reason] = TocRoom::validateAction($act, $args);
        if ($clean === null) {
            return ['ok' => false, 'reason' => $reason];
        }
        $who = PlanValidator::text($who, 60);
        $this->pdo()->prepare('INSERT INTO atak_toc_actions (tenant_id, map_id, side, user_id, actor_label, act, args_json, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$tenantId, $mapId, $side, $userId, $who, $act, json_encode($clean, JSON_UNESCAPED_UNICODE) ?: '{}', 'queued', self::dt($now)]);
        $actionId = (int) $this->pdo()->lastInsertId();
        [$cmd, $ref, $wire] = TocRoom::wire($act, $clean, $side, 'W' . $actionId, $who);
        $cmdId = null;
        try {
            $cmdId = ($this->enqueue)($tenantId, $mapId, $cmd, $ref, $wire, self::TTL[$act] ?? self::TTL_DEFAULT, $who, $userId);
        } catch (\Throwable) {
            $cmdId = null;
        }
        $this->pdo()->prepare('UPDATE atak_toc_actions SET status = ?, web_command_id = ? WHERE id = ?')->execute([$cmdId !== null ? 'sent' : 'queued', $cmdId, $actionId]);

        return ['ok' => true, 'reason' => '', 'action_id' => $actionId];
    }

    /**
     * Journal des actions du web (onglet « Réseau » / audit du poste).
     *
     * @return list<array<string, mixed>>
     */
    public function recentActions(int $tenantId, int $mapId, string $side, int $limit = 40): array
    {
        $st = $this->pdo()->prepare('SELECT id, actor_label, act, status, created_at, applied_at FROM atak_toc_actions WHERE tenant_id = ? AND map_id = ? AND side = ? ORDER BY id DESC LIMIT ' . max(1, min(200, $limit)));
        $st->execute([$tenantId, $mapId, $side]);

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'], 'who' => (string) $r['actor_label'], 'act' => (string) $r['act'], 'status' => (string) $r['status'],
            'created_at' => (string) $r['created_at'], 'applied_at' => $r['applied_at'] !== null ? (string) $r['applied_at'] : null,
        ], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /** Remise à zéro de la salle web (configuration du poste) : état et actions en attente. */
    public function reset(int $tenantId, int $mapId, string $side): void
    {
        $this->pdo()->prepare('DELETE FROM atak_toc_rooms WHERE tenant_id = ? AND map_id = ? AND side = ?')->execute([$tenantId, $mapId, $side]);
        $this->pdo()->prepare("UPDATE atak_toc_actions SET status = 'dropped' WHERE tenant_id = ? AND map_id = ? AND side = ? AND status IN ('queued', 'sent')")->execute([$tenantId, $mapId, $side]);
    }
}
