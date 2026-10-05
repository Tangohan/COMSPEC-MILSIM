<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use PDO;

/**
 * Temps d'écran du téléphone ATAK et temps de jeu par rôle (COMSPEC Link « ScreenTime.Report »,
 * POST /api/atak/screen-time). Une ligne par membre, jour, type et code :
 *   screen : total | hand (en main) | carry (porté en miniature)
 *   app    : page de l'app (MAP, CHAT, BFT…)
 *   role   : rôle tenu (PIL, CDE, MED… ou SLOT:<rôle du slot>)
 */
class AtakScreenTimeRepository
{
    use LazyDatabaseConnection;

    public const KINDS = ['screen', 'app', 'role'];

    private static ?bool $ready = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public function schemaReady(): bool
    {
        if (self::$ready === null) {
            try {
                $st = $this->pdo()->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atak_screen_time' LIMIT 1");
                self::$ready = $st !== false && (bool) $st->fetchColumn();
            } catch (\Throwable) {
                self::$ready = false;
            }
        }

        return self::$ready;
    }

    /**
     * Garde les éléments valides d'un envoi du jeu (type connu, code non vide, 1 à 7200 s chacun).
     *
     * @param mixed $items
     * @return list<array{kind: string, key: string, label: string, seconds: int}>
     */
    public static function normalizeItems(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }
        $out = [];
        foreach (array_slice($items, 0, 80) as $it) {
            if (!is_array($it)) {
                continue;
            }
            $kind = strtolower(trim((string) ($it['kind'] ?? '')));
            $key = trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string) ($it['key'] ?? '')) ?? '');
            $seconds = (int) round((float) ($it['seconds'] ?? 0));
            if (!in_array($kind, self::KINDS, true) || $key === '' || $seconds < 1) {
                continue;
            }
            $label = trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string) ($it['label'] ?? '')) ?? '');
            $key = mb_substr($kind === 'role' ? $key : strtoupper($key), 0, 64);
            $id = $kind . '|' . $key;
            if (isset($out[$id])) {
                $out[$id]['seconds'] = min(7200, $out[$id]['seconds'] + $seconds);
                continue;
            }
            $out[$id] = ['kind' => $kind, 'key' => $key, 'label' => mb_substr($label !== '' ? $label : $key, 0, 120), 'seconds' => min(7200, $seconds)];
        }

        return array_values($out);
    }

    /**
     * @param list<array{kind: string, key: string, label: string, seconds: int}> $items
     */
    public function addItems(int $tenantId, int $userId, array $items, ?string $day = null): int
    {
        if ($tenantId < 1 || $userId < 1 || $items === [] || !$this->schemaReady()) {
            return 0;
        }
        $day ??= date('Y-m-d');
        $st = $this->pdo()->prepare(
            'INSERT INTO atak_screen_time (tenant_id, user_id, day, kind, item_key, item_label, seconds)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE seconds = seconds + VALUES(seconds), item_label = VALUES(item_label)'
        );
        $n = 0;
        foreach ($items as $it) {
            $st->execute([$tenantId, $userId, $day, $it['kind'], $it['key'], $it['label'], $it['seconds']]);
            $n++;
        }

        return $n;
    }

    /**
     * Bilan par membre sur la période : écran (total, en main, porté), top apps et temps par rôle.
     *
     * @return list<array{user_id: int, display_name: string, callsign: string, screen: int, hand: int, carry: int, apps: list<array{key: string, label: string, seconds: int}>, roles: list<array{key: string, label: string, seconds: int}>, last_day: string}>
     */
    public function summaryByUser(int $tenantId, int $days): array
    {
        if ($tenantId < 1 || !$this->schemaReady()) {
            return [];
        }
        $where = 't.tenant_id = ?';
        $args = [$tenantId];
        if ($days > 0) {
            $where .= ' AND t.day >= (CURRENT_DATE - INTERVAL ? DAY)';
            $args[] = $days - 1;
        }
        $st = $this->pdo()->prepare(
            "SELECT t.user_id, t.kind, t.item_key, MAX(t.item_label) AS item_label, SUM(t.seconds) AS seconds, MAX(t.day) AS last_day,
                    u.display_name, u.callsign
             FROM atak_screen_time t LEFT JOIN users u ON u.id = t.user_id
             WHERE {$where}
             GROUP BY t.user_id, t.kind, t.item_key, u.display_name, u.callsign"
        );
        $st->execute($args);
        $users = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $uid = (int) $r['user_id'];
            $users[$uid] ??= [
                'user_id' => $uid,
                'display_name' => (string) ($r['display_name'] ?? ''),
                'callsign' => (string) ($r['callsign'] ?? ''),
                'screen' => 0, 'hand' => 0, 'carry' => 0, 'apps' => [], 'roles' => [], 'last_day' => '',
            ];
            $sec = (int) $r['seconds'];
            $users[$uid]['last_day'] = max($users[$uid]['last_day'], (string) $r['last_day']);
            $key = (string) $r['item_key'];
            switch ($r['kind']) {
                case 'screen':
                    $slot = match ($key) { 'TOTAL' => 'screen', 'HAND' => 'hand', 'CARRY' => 'carry', default => null };
                    if ($slot !== null) {
                        $users[$uid][$slot] += $sec;
                    }
                    break;
                case 'app':
                    $users[$uid]['apps'][] = ['key' => $key, 'label' => (string) $r['item_label'], 'seconds' => $sec];
                    break;
                case 'role':
                    $users[$uid]['roles'][] = ['key' => $key, 'label' => (string) $r['item_label'], 'seconds' => $sec];
                    break;
            }
        }
        $bySeconds = static fn (array $a, array $b): int => $b['seconds'] <=> $a['seconds'];
        foreach ($users as &$u) {
            usort($u['apps'], $bySeconds);
            usort($u['roles'], $bySeconds);
        }
        unset($u);
        $list = array_values($users);
        usort($list, static fn (array $a, array $b): int => (array_sum(array_column($b['roles'], 'seconds')) + $b['screen']) <=> (array_sum(array_column($a['roles'], 'seconds')) + $a['screen']));

        return $list;
    }

    /**
     * Totaux par rôle pour toute la communauté sur la période.
     *
     * @return list<array{key: string, label: string, seconds: int, members: int}>
     */
    public function roleTotals(int $tenantId, int $days): array
    {
        if ($tenantId < 1 || !$this->schemaReady()) {
            return [];
        }
        $sql = "SELECT item_key, MAX(item_label) AS label, SUM(seconds) AS seconds, COUNT(DISTINCT user_id) AS members
                FROM atak_screen_time WHERE tenant_id = ? AND kind = 'role'";
        $args = [$tenantId];
        if ($days > 0) {
            $sql .= ' AND day >= (CURRENT_DATE - INTERVAL ? DAY)';
            $args[] = $days - 1;
        }
        $st = $this->pdo()->prepare($sql . ' GROUP BY item_key ORDER BY seconds DESC');
        $st->execute($args);

        return array_map(static fn (array $r): array => [
            'key' => (string) $r['item_key'], 'label' => (string) $r['label'], 'seconds' => (int) $r['seconds'], 'members' => (int) $r['members'],
        ], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }
}
