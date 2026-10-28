<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\AtakRoleDetection;
use App\Support\LazyDatabaseConnection;
use PDO;

/**
 * Temps d'écran du téléphone ATAK et temps de jeu par rôle (COMSPEC Link « ScreenTime.Report »,
 * POST /api/atak/screen-time). Une ligne par membre, jour, type et code :
 *   screen : total | hand (en main) | carry (porté en miniature)
 *   app    : page de l'app (MAP, CHAT, BFT…)
 *   role   : rôle tenu (PIL, CDE, MED… ou SLOT:<rôle du slot>)
 *   play   : total (temps de jeu dans la mission)
 * Le temps de jeu et les rôles viennent aussi du serveur Arma pour tous les joueurs (« ScreenTime.Batch »,
 * POST /api/atak/screen-time/batch), même quand le téléphone du joueur n'a plus de batterie ni de réseau.
 * source_mask garde qui a remonté chaque ligne (SOURCE_PHONE, SOURCE_SERVER) : colonne « Remonté par » du tableur
 * /back-office/atak/heures.
 */
class AtakScreenTimeRepository
{
    use LazyDatabaseConnection;

    public const KINDS = ['screen', 'app', 'role', 'play'];

    /** Types qu'un envoi du serveur Arma peut porter (le serveur ne voit pas l'écran des téléphones). */
    public const SERVER_KINDS = ['play', 'role'];

    /** Bits de source_mask : qui a remonté la ligne (cumulés si les deux l'ont fait). 0 = avant ce suivi, ou correction. */
    public const SOURCE_PHONE = 1;
    public const SOURCE_SERVER = 2;

    private static ?bool $ready = null;

    /** Écriture avec source_mask : null = pas encore essayée, false = colonne absente et migration impossible. */
    private static ?bool $sourceWritable = null;

    /** @var array<string, bool> colonnes optionnelles (mission_key, source_mask), lues une fois par processus */
    private static array $columns = [];

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
     * Envoi groupé du serveur Arma : [{player_uid, call_sign, items}…] → joueurs valides (Steam 17 chiffres),
     * seulement temps de jeu et rôles, 100 joueurs au plus, un même joueur fusionné.
     *
     * @param mixed $players
     * @return list<array{player_uid: string, call_sign: string, items: list<array{kind: string, key: string, label: string, seconds: int}>}>
     */
    public static function normalizeBatch(mixed $players): array
    {
        if (!is_array($players)) {
            return [];
        }
        $out = [];
        foreach (array_slice($players, 0, 100) as $p) {
            if (!is_array($p)) {
                continue;
            }
            $uid = trim((string) ($p['player_uid'] ?? $p['steam_uid'] ?? ''));
            if (!preg_match('/^7656\d{13}$/', $uid)) {
                continue;
            }
            $items = array_values(array_filter(
                self::normalizeItems($p['items'] ?? []),
                static fn (array $it): bool => in_array($it['kind'], self::SERVER_KINDS, true)
            ));
            if ($items === []) {
                continue;
            }
            if (isset($out[$uid])) {
                $items = self::normalizeItems(array_merge($out[$uid]['items'], $items));
            }
            $call = trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string) ($p['call_sign'] ?? '')) ?? '');
            if ($call === '' && isset($out[$uid])) {
                $call = $out[$uid]['call_sign'];
            }
            $out[$uid] = ['player_uid' => $uid, 'call_sign' => mb_substr($call, 0, 64), 'items' => $items];
        }

        return array_values($out);
    }

    /**
     * @param list<array{kind: string, key: string, label: string, seconds: int}> $items
     * @param string $missionKey clé de la session jouée (« nom@carte#UTC »), gardée par ligne quand la colonne existe
     * @param int $sourceMask qui remonte (SOURCE_PHONE, SOURCE_SERVER), cumulé par ligne quand la colonne existe
     */
    public function addItems(int $tenantId, int $userId, array $items, ?string $day = null, string $missionKey = '', int $sourceMask = 0): int
    {
        if ($tenantId < 1 || $userId < 1 || $items === [] || !$this->schemaReady()) {
            return 0;
        }
        $day ??= date('Y-m-d');
        $sourceMask = max(0, min(255, $sourceMask));
        // Avec la source : la colonne arrive avec la même migration que mission_key (bootstrap/atak_mission_key_migration.php).
        if ($sourceMask > 0 && self::$sourceWritable !== false) {
            for ($attempt = 0; $attempt < 2; $attempt++) {
                try {
                    $st = $this->pdo()->prepare(
                        'INSERT INTO atak_screen_time (tenant_id, user_id, day, mission_key, kind, item_key, item_label, seconds, source_mask)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE seconds = seconds + VALUES(seconds), item_label = VALUES(item_label),
                            source_mask = source_mask | VALUES(source_mask)'
                    );
                    $first = $items[0];
                    $st->execute([$tenantId, $userId, $day, $missionKey, $first['kind'], $first['key'], $first['label'], $first['seconds'], $sourceMask]);
                } catch (\PDOException $e) {
                    if (!\App\Support\AtakMissionKey::isMissingSchema($e)) {
                        throw $e;
                    }
                    if ($attempt === 0) {
                        \App\Support\AtakMissionKey::ensureSchema($this->pdo());
                        continue;
                    }
                    // Migration impossible : écriture sans la source pour le reste du processus.
                    self::$sourceWritable = false;
                    break;
                }
                self::$sourceWritable = true;
                foreach (array_slice($items, 1) as $it) {
                    $st->execute([$tenantId, $userId, $day, $missionKey, $it['kind'], $it['key'], $it['label'], $it['seconds'], $sourceMask]);
                }

                return count($items);
            }
        }
        $st = null;
        if ($missionKey !== '') {
            try {
                $st = $this->pdo()->prepare(
                    'INSERT INTO atak_screen_time (tenant_id, user_id, day, mission_key, kind, item_key, item_label, seconds)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE seconds = seconds + VALUES(seconds), item_label = VALUES(item_label)'
                );
                $first = $items[0];
                $st->execute([$tenantId, $userId, $day, $missionKey, $first['kind'], $first['key'], $first['label'], $first['seconds']]);
            } catch (\PDOException $e) {
                if (!\App\Support\AtakMissionKey::isMissingSchema($e)) {
                    throw $e;
                }
                \App\Support\AtakMissionKey::ensureSchema($this->pdo());
                $st = null;
            }
            if ($st !== null) {
                foreach (array_slice($items, 1) as $it) {
                    $st->execute([$tenantId, $userId, $day, $missionKey, $it['kind'], $it['key'], $it['label'], $it['seconds']]);
                }

                return count($items);
            }
        }
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
     * @return list<array{user_id: int, display_name: string, callsign: string, play: int, screen: int, hand: int, carry: int, apps: list<array{key: string, label: string, seconds: int}>, roles: list<array{key: string, label: string, seconds: int}>, last_day: string}>
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
                'play' => 0, 'screen' => 0, 'hand' => 0, 'carry' => 0, 'apps' => [], 'roles' => [], 'last_day' => '',
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
                case 'play':
                    if ($key === 'TOTAL') {
                        $users[$uid]['play'] += $sec;
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
        usort($list, static fn (array $a, array $b): int => [$b['play'], $b['screen']] <=> [$a['play'], $a['screen']]);

        return $list;
    }

    /**
     * Temps passé dans chaque rôle par un joueur (fiches du site), du plus long au plus court, avec sa dernière partie
     * dans ce rôle, comment il a été détecté (AtakRoleDetection) et qui l'a remonté. $days = 0 : depuis le début.
     *
     * @return list<array{key: string, label: string, seconds: int, last_day: string, source_mask: int, detection: string, detection_label: string}>
     */
    public function rolesForUser(int $tenantId, int $userId, int $days = 0): array
    {
        if ($tenantId < 1 || $userId < 1 || !$this->schemaReady()) {
            return [];
        }
        $where = "tenant_id = ? AND user_id = ? AND kind = 'role'";
        $args = [$tenantId, $userId];
        if ($days > 0) {
            $where .= ' AND day >= (CURRENT_DATE - INTERVAL ? DAY)';
            $args[] = $days - 1;
        }
        $mask = $this->hasColumn('source_mask') ? 'BIT_OR(source_mask)' : '0';
        $corrected = $this->correctedKeys($tenantId, $userId);
        try {
            $st = $this->pdo()->prepare(
                "SELECT item_key, MAX(item_label) AS item_label, SUM(seconds) AS seconds, MAX(day) AS last_day, {$mask} AS source_mask
                 FROM atak_screen_time WHERE {$where} GROUP BY item_key ORDER BY seconds DESC LIMIT 20"
            );
            $st->execute($args);
        } catch (\PDOException) {
            return [];
        }
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $sec = (int) $r['seconds'];
            if ($sec < 60) {
                continue;
            }
            $key = (string) $r['item_key'];
            $label = trim((string) ($r['item_label'] ?? ''));
            $srcMask = (int) ($r['source_mask'] ?? 0);
            $detection = AtakRoleDetection::category($key, $srcMask === 0 && isset($corrected[$key]));
            $out[] = [
                'key' => $key,
                'label' => $label !== '' ? $label : (str_starts_with($key, 'SLOT:') ? substr($key, 5) : $key),
                'seconds' => $sec,
                'last_day' => (string) ($r['last_day'] ?? ''),
                'source_mask' => $srcMask,
                'detection' => $detection,
                'detection_label' => AtakRoleDetection::label($detection),
            ];
        }

        return $out;
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

    /**
     * Temps de jeu d'un joueur (lignes play/TOTAL) pour la synthèse de sa fiche : 7 et 30 derniers jours,
     * sessions, dernière partie et 8 dernières semaines. Null si la table n'existe pas encore.
     *
     * @return array{play_7d: int, play_30d: int, sessions_30d: int, sessions_total: int, total_seconds: int, last_day: string, weeks: list<array{start: string, seconds: int}>}|null
     */
    public function playStatsForUser(int $tenantId, int $userId): ?array
    {
        if ($tenantId < 1 || $userId < 1 || !$this->schemaReady()) {
            return null;
        }
        $mk = $this->hasColumn('mission_key') ? 'mission_key' : "''";
        try {
            $st = $this->pdo()->prepare(
                "SELECT day, {$mk} AS mission_key, SUM(seconds) AS seconds FROM atak_screen_time
                 WHERE tenant_id = ? AND user_id = ? AND kind = 'play' AND item_key = 'TOTAL' AND seconds > 0
                 GROUP BY day, {$mk}"
            );
            $st->execute([$tenantId, $userId]);
        } catch (\PDOException) {
            return null;
        }

        return self::summarizePlay(array_map(static fn (array $r): array => [
            'day' => (string) $r['day'], 'mission_key' => (string) ($r['mission_key'] ?? ''), 'seconds' => (int) $r['seconds'],
        ], $st->fetchAll(PDO::FETCH_ASSOC) ?: []), date('Y-m-d'));
    }

    /**
     * Calcul pur de playStatsForUser. Une session = une clé de mission ; sans clé (anciennes lignes), une par jour.
     * Les semaines vont du lundi au dimanche, la plus ancienne d'abord, la semaine en cours en dernier.
     *
     * @param list<array{day: string, mission_key: string, seconds: int}> $rows
     * @return array{play_7d: int, play_30d: int, sessions_30d: int, sessions_total: int, total_seconds: int, last_day: string, weeks: list<array{start: string, seconds: int}>}
     */
    public static function summarizePlay(array $rows, string $today, int $weekCount = 8): array
    {
        $todayDt = new \DateTimeImmutable($today);
        $from7 = $todayDt->modify('-6 days')->format('Y-m-d');
        $from30 = $todayDt->modify('-29 days')->format('Y-m-d');
        $monday = $todayDt->modify('-' . ((int) $todayDt->format('N') - 1) . ' days');
        $weeks = [];
        for ($i = $weekCount - 1; $i >= 0; $i--) {
            $weeks[] = ['start' => $monday->modify('-' . (7 * $i) . ' days')->format('Y-m-d'), 'seconds' => 0];
        }
        $out = ['play_7d' => 0, 'play_30d' => 0, 'sessions_30d' => 0, 'sessions_total' => 0, 'total_seconds' => 0, 'last_day' => '', 'weeks' => $weeks];
        $sessions = [];
        $sessions30 = [];
        foreach ($rows as $r) {
            $day = (string) $r['day'];
            $sec = max(0, (int) $r['seconds']);
            if ($day === '' || $sec < 1) {
                continue;
            }
            $session = (string) ($r['mission_key'] ?? '') !== '' ? (string) $r['mission_key'] : 'jour:' . $day;
            $out['total_seconds'] += $sec;
            $out['last_day'] = max($out['last_day'], $day);
            $sessions[$session] = true;
            if ($day >= $from30) {
                $out['play_30d'] += $sec;
                $sessions30[$session] = true;
            }
            if ($day >= $from7) {
                $out['play_7d'] += $sec;
            }
            for ($w = $weekCount - 1; $w >= 0; $w--) {
                if ($day >= $out['weeks'][$w]['start']) {
                    $out['weeks'][$w]['seconds'] += $sec;
                    break;
                }
            }
        }
        $out['sessions_total'] = count($sessions);
        $out['sessions_30d'] = count($sessions30);

        return $out;
    }

    /**
     * Tableur des heures (back-office) : une ligne par joueur, type et élément sur la période, à plat.
     * $from / $to : jours inclus (AAAA-MM-JJ), null = sans borne. $userId = 0 : tous les joueurs.
     *
     * @return list<array{user_id: int, display_name: string, callsign: string, kind: string, key: string, label: string, seconds: int, sessions: int, days: int, first_day: string, last_day: string, source_mask: int, detection: string}>
     */
    public function sheetRows(int $tenantId, ?string $from = null, ?string $to = null, int $userId = 0): array
    {
        if ($tenantId < 1 || !$this->schemaReady()) {
            return [];
        }
        $where = 't.tenant_id = ? AND t.seconds > 0';
        $args = [$tenantId];
        if ($from !== null) {
            $where .= ' AND t.day >= ?';
            $args[] = $from;
        }
        if ($to !== null) {
            $where .= ' AND t.day <= ?';
            $args[] = $to;
        }
        if ($userId > 0) {
            $where .= ' AND t.user_id = ?';
            $args[] = $userId;
        }
        // Une session = une clé de mission ; les lignes d'avant la clé comptent une session par jour.
        $sessions = $this->hasColumn('mission_key')
            ? "COUNT(DISTINCT CASE WHEN t.mission_key <> '' THEN t.mission_key ELSE CONCAT('jour:', t.day) END)"
            : 'COUNT(DISTINCT t.day)';
        $mask = $this->hasColumn('source_mask') ? 'BIT_OR(t.source_mask)' : '0';
        $st = $this->pdo()->prepare(
            "SELECT t.user_id, t.kind, t.item_key, MAX(t.item_label) AS item_label, SUM(t.seconds) AS seconds,
                    {$sessions} AS sessions, COUNT(DISTINCT t.day) AS days, MIN(t.day) AS first_day, MAX(t.day) AS last_day,
                    {$mask} AS source_mask, u.display_name, u.callsign
             FROM atak_screen_time t LEFT JOIN users u ON u.id = t.user_id
             WHERE {$where}
             GROUP BY t.user_id, t.kind, t.item_key, u.display_name, u.callsign"
        );
        $st->execute($args);
        $corrected = $this->correctedKeysByUser($tenantId, $userId);
        $order = ['play' => 0, 'role' => 1, 'screen' => 2, 'app' => 3];
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $uid = (int) $r['user_id'];
            $kind = (string) $r['kind'];
            $key = (string) $r['item_key'];
            $mask = (int) ($r['source_mask'] ?? 0);
            $label = trim((string) ($r['item_label'] ?? ''));
            $out[] = [
                'user_id' => $uid,
                'display_name' => (string) ($r['display_name'] ?? ''),
                'callsign' => (string) ($r['callsign'] ?? ''),
                'kind' => $kind,
                'key' => $key,
                'label' => $label !== '' ? $label : AtakRoleDetection::fallbackLabel($key),
                'seconds' => (int) $r['seconds'],
                'sessions' => (int) $r['sessions'],
                'days' => (int) $r['days'],
                'first_day' => (string) ($r['first_day'] ?? ''),
                'last_day' => (string) ($r['last_day'] ?? ''),
                'source_mask' => $mask,
                'detection' => $kind === 'role' ? AtakRoleDetection::category($key, $mask === 0 && isset($corrected[$uid][$key])) : '',
            ];
        }
        usort($out, static function (array $a, array $b) use ($order): int {
            $na = mb_strtolower($a['callsign'] !== '' ? $a['callsign'] : $a['display_name']);
            $nb = mb_strtolower($b['callsign'] !== '' ? $b['callsign'] : $b['display_name']);

            return [$na, $a['user_id'], $order[$a['kind']] ?? 9, $b['seconds']] <=> [$nb, $b['user_id'], $order[$b['kind']] ?? 9, $a['seconds']];
        });

        return $out;
    }

    /**
     * Membres qui ont du temps enregistré (filtre du tableur), par nom.
     *
     * @return list<array{user_id: int, name: string}>
     */
    public function sheetMembers(int $tenantId): array
    {
        if ($tenantId < 1 || !$this->schemaReady()) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT DISTINCT t.user_id, u.display_name, u.callsign
             FROM atak_screen_time t LEFT JOIN users u ON u.id = t.user_id
             WHERE t.tenant_id = ?'
        );
        $st->execute([$tenantId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $call = trim((string) ($r['callsign'] ?? ''));
            $name = trim((string) ($r['display_name'] ?? ''));
            $label = $call !== '' ? ($name !== '' && $name !== $call ? $call . ' (' . $name . ')' : $call) : ($name !== '' ? $name : 'Membre #' . (int) $r['user_id']);
            $out[] = ['user_id' => (int) $r['user_id'], 'name' => $label];
        }
        usort($out, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $out;
    }

    /** Colonne optionnelle de atak_screen_time (mission_key, source_mask), lue une fois par processus. */
    public function hasColumn(string $column): bool
    {
        if (!isset(self::$columns[$column])) {
            try {
                $st = $this->pdo()->prepare(
                    "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atak_screen_time' AND COLUMN_NAME = ? LIMIT 1"
                );
                $st->execute([$column]);
                self::$columns[$column] = (bool) $st->fetchColumn();
            } catch (\Throwable) {
                self::$columns[$column] = false;
            }
        }

        return self::$columns[$column];
    }

    /**
     * Rôles d'arrivée des corrections manuelles encore actives (atak_role_time_moves), par membre.
     *
     * @return array<int, array<string, true>>
     */
    private function correctedKeysByUser(int $tenantId, int $userId = 0): array
    {
        try {
            $sql = 'SELECT user_id, to_key FROM atak_role_time_moves WHERE tenant_id = ? AND reverted_at IS NULL';
            $args = [$tenantId];
            if ($userId > 0) {
                $sql .= ' AND user_id = ?';
                $args[] = $userId;
            }
            $st = $this->pdo()->prepare($sql);
            $st->execute($args);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['user_id']][(string) $r['to_key']] = true;
        }

        return $out;
    }

    /** @return array<string, true> */
    private function correctedKeys(int $tenantId, int $userId): array
    {
        return $this->correctedKeysByUser($tenantId, $userId)[$userId] ?? [];
    }
}
