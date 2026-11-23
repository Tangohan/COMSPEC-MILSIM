<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Core\Database;
use App\Support\SteamId;
use App\Support\UtcDateTime;
use PDO;

/**
 * Joueurs ATAK connectés de la communauté, en direct, pour la fiche d'une session (pointage et lancement de l'enregistrement).
 *
 * Deux sources existantes, lues sans rien écrire, fusionnées par compte Steam :
 *  - atak_units : fiche de liaison du téléphone (indicatif, groupe, état linked / delayed / offline), mise à jour par le mod
 *    à chaque envoi de position ; updated_at est écrit par MySQL en UTC ;
 *  - tenant_game_server_presence : serveur et mission signalés par le mod (POST /api/atak/server-presence, toutes les 10 min),
 *    avec le serveur déclaré de la communauté reconnu ou non ; seen_at en UTC.
 * Le compte Athena est retrouvé par users.steam_id dans la communauté. Seuls les joueurs de la communauté sont lus.
 */
final class RoleplaySessionLiveRoster
{
    /** Liaison ATAK : fiche mise à jour depuis moins de 3 min = connecté. */
    public const LINK_FRESH_S = 180;
    /** Serveur de jeu : signe de vie depuis moins de 12 min = connecté (le mod l'envoie toutes les 10 min). */
    public const PRESENCE_FRESH_S = 720;
    /** Fenêtre de la liste en direct (au-delà : seulement dans « Pourquoi je ne le vois pas ? »). */
    public const LIST_WINDOW_S = 1800;
    /** Fenêtre des diagnostics. */
    public const DIAG_WINDOW_S = 7200;

    private ?PDO $pdo;
    /** @var callable(): int */
    private $clock;

    public function __construct(?PDO $pdo = null, ?callable $clock = null)
    {
        $this->pdo = $pdo;
        $this->clock = $clock ?? static fn (): int => time();
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    /**
     * @param list<array<string, mixed>> $members participants de la session (roleplay_game_session_members + users)
     * @return array{players: list<array<string, mixed>>, connected: int, server_time: int}
     */
    public function roster(int $tenantId, array $members): array
    {
        $now = ($this->clock)();
        $byUser = [];
        foreach ($members as $m) {
            if ((int) ($m['user_id'] ?? 0) > 0) {
                $byUser[(int) $m['user_id']] = $m;
            }
        }
        $players = [];
        foreach ($this->signals($tenantId, $now - self::LIST_WINDOW_S) as $steam => $sig) {
            $user = $sig['user'];
            $member = $user !== null ? ($byUser[(int) $user['id']] ?? null) : null;
            $players[] = [
                'key' => substr(hash('sha256', $tenantId . '|' . $steam), 0, 12),
                'user_id' => $user !== null ? (int) $user['id'] : null,
                'label' => $user !== null ? $user['label'] : ($sig['player_name'] !== '' ? $sig['player_name'] : 'Joueur non relié'),
                'callsign' => $sig['callsign'],
                'unit' => $sig['unit'],
                'server' => $sig['server'],
                'server_known' => $sig['server_known'],
                'mission' => $sig['mission'],
                'last_seen' => $sig['last_seen'],
                'age_s' => max(0, $now - $sig['last_seen']),
                'link' => $sig['link'],
                'connected' => $sig['connected'],
                'linked_account' => $user !== null,
                'in_session' => $member !== null,
                'checked_in' => $member !== null && trim((string) ($member['checked_in_at'] ?? '')) !== '',
            ];
        }
        usort($players, static fn (array $a, array $b): int => [$b['connected'], $a['label']] <=> [$a['connected'], $b['label']]);

        return [
            'players' => $players,
            'connected' => count(array_filter($players, static fn (array $p): bool => $p['connected'])),
            'server_time' => $now,
        ];
    }

    /**
     * Comptes des membres connectés à pointer (« Pointer tous les connectés »).
     *
     * @return list<int>
     */
    public function connectedUserIds(int $tenantId): array
    {
        $ids = [];
        foreach ($this->signals($tenantId, ($this->clock)() - self::LIST_WINDOW_S) as $sig) {
            if ($sig['connected'] && $sig['user'] !== null) {
                $ids[] = (int) $sig['user']['id'];
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * « Pourquoi je ne le vois pas ? » : joueurs vus récemment mais absents de la liste ou non pointables.
     *
     * @return list<array{label: string, reason: string, detail: string}>
     */
    public function diagnostics(int $tenantId): array
    {
        $now = ($this->clock)();
        $out = [];
        foreach ($this->signals($tenantId, $now - self::DIAG_WINDOW_S) as $sig) {
            $label = $sig['user'] !== null ? $sig['user']['label'] : ($sig['player_name'] !== '' ? $sig['player_name'] : ($sig['callsign'] !== '' ? $sig['callsign'] : 'Joueur'));
            $age = max(0, $now - $sig['last_seen']);
            if ($sig['user'] === null) {
                $out[] = ['label' => $label, 'reason' => 'account_unlinked', 'detail' => 'Son compte Steam n’est lié à aucun membre de la communauté.'];
            } elseif ($sig['user']['status'] !== 'active') {
                $out[] = ['label' => $label, 'reason' => 'account_inactive', 'detail' => 'Son compte n’est pas actif dans la communauté.'];
            } elseif (!$sig['connected']) {
                $out[] = ['label' => $label, 'reason' => 'no_signal', 'detail' => 'Pas de signe de vie depuis ' . self::ago($age) . '.'];
            } elseif ($sig['server'] !== '' && !$sig['server_known']) {
                $out[] = ['label' => $label, 'reason' => 'server_unknown', 'detail' => 'Il joue sur « ' . $sig['server'] . ' », qui n’est pas un serveur déclaré de la communauté.'];
            }
        }

        return array_slice($out, 0, 40);
    }

    public static function ago(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . ' s';
        }
        if ($seconds < 3600) {
            return intdiv($seconds, 60) . ' min';
        }

        return intdiv($seconds, 3600) . ' h ' . str_pad((string) intdiv($seconds % 3600, 60), 2, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, array{user: ?array{id: int, label: string, status: string}, player_name: string, callsign: string, unit: string, server: string, server_known: bool, mission: string, last_seen: int, link: string, connected: bool}>
     */
    private function signals(int $tenantId, int $since): array
    {
        $now = ($this->clock)();
        $sinceUtc = gmdate('Y-m-d H:i:s', $since);
        $sig = [];
        $blank = static fn (): array => ['user' => null, 'player_name' => '', 'callsign' => '', 'unit' => '', 'server' => '', 'server_known' => false, 'mission' => '', 'last_seen' => 0, 'link' => 'none', 'link_at' => 0, 'presence_at' => 0, 'connected' => false];
        try {
            $st = $this->pdo()->prepare('SELECT call_sign, group_name, status, updated_at, extra FROM atak_units WHERE tenant_id = ? AND updated_at >= ?');
            $st->execute([$tenantId, $sinceUtc]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $u) {
                $extra = is_string($u['extra'] ?? null) ? (json_decode((string) $u['extra'], true) ?: []) : [];
                $steam = SteamId::normalize((string) ($extra['steam_uid'] ?? $extra['steamId'] ?? $extra['player_uid'] ?? '')) ?? '';
                $at = UtcDateTime::timestamp($u['updated_at'] ?? null) ?? 0;
                if ($steam === '' || $at === 0 || (isset($sig[$steam]) && $sig[$steam]['link_at'] >= $at)) {
                    continue;
                }
                $row = $sig[$steam] ?? $blank();
                $row['callsign'] = trim((string) ($u['call_sign'] ?? ''));
                $row['unit'] = trim((string) ($u['group_name'] ?? ''));
                $row['link'] = in_array((string) ($u['status'] ?? ''), ['linked', 'delayed', 'offline'], true) ? (string) $u['status'] : 'offline';
                $row['link_at'] = $at;
                $sig[$steam] = $row;
            }
        } catch (\Throwable) {
            // Table des unités absente : la présence serveur seule.
        }
        try {
            $st = $this->pdo()->prepare(
                'SELECT p.steam_uid, p.player_name, p.server_name, p.mission, p.matched_server_id, p.seen_at, s.label AS matched_label
                 FROM tenant_game_server_presence p
                 LEFT JOIN tenant_game_servers s ON s.id = p.matched_server_id AND s.tenant_id = p.tenant_id
                 WHERE p.tenant_id = ? AND p.seen_at >= ?'
            );
            $st->execute([$tenantId, $sinceUtc]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $p) {
                $steam = SteamId::normalize((string) ($p['steam_uid'] ?? '')) ?? '';
                if ($steam === '') {
                    continue;
                }
                $row = $sig[$steam] ?? $blank();
                $row['player_name'] = trim((string) ($p['player_name'] ?? ''));
                $row['server'] = trim((string) ($p['matched_label'] ?? '')) ?: trim((string) ($p['server_name'] ?? ''));
                $row['server_known'] = (int) ($p['matched_server_id'] ?? 0) > 0;
                $row['mission'] = trim((string) ($p['mission'] ?? ''));
                $row['presence_at'] = UtcDateTime::timestamp($p['seen_at'] ?? null) ?? 0;
                $sig[$steam] = $row;
            }
        } catch (\Throwable) {
        }
        if ($sig === []) {
            return [];
        }
        $users = [];
        try {
            $steams = array_keys($sig);
            $st = $this->pdo()->prepare(
                'SELECT id, display_name, callsign, steam_id, status FROM users WHERE tenant_id = ? AND steam_id IN (' . implode(',', array_fill(0, count($steams), '?')) . ')'
            );
            $st->execute(array_merge([$tenantId], array_map('strval', $steams)));
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $u) {
                $users[(string) $u['steam_id']] = [
                    'id' => (int) $u['id'],
                    'label' => function_exists('member_label')
                        ? member_label(['id' => (int) $u['id'], 'display_name' => (string) ($u['display_name'] ?? ''), 'callsign' => (string) ($u['callsign'] ?? '')], 'list')
                        : (trim((string) ($u['display_name'] ?? '')) ?: trim((string) ($u['callsign'] ?? ''))),
                    'status' => (string) ($u['status'] ?? 'active'),
                ];
            }
        } catch (\Throwable) {
        }
        foreach ($sig as $steam => &$row) {
            $row['user'] = $users[(string) $steam] ?? null;
            $row['last_seen'] = max($row['link_at'], $row['presence_at']);
            $linkLive = in_array($row['link'], ['linked', 'delayed'], true) && $now - $row['link_at'] <= self::LINK_FRESH_S;
            $row['connected'] = $linkLive || ($row['presence_at'] > 0 && $now - $row['presence_at'] <= self::PRESENCE_FRESH_S);
            if ($row['link'] === 'none') {
                $row['link'] = $row['connected'] ? 'server_only' : 'offline';
            } elseif (!$linkLive) {
                $row['link'] = 'offline';
            }
            unset($row['link_at'], $row['presence_at']);
        }
        unset($row);

        return $sig;
    }
}
