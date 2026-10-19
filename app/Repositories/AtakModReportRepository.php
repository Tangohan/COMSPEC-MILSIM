<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Services\Monitoring\AtakModReportGravity as Gravity;
use PDO;

/**
 * Rapports d’erreurs / bugs remontés par le mod Overwatch.
 */
final class AtakModReportRepository
{
    public const STATUS_NEW = 'new';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_FIXED = 'fixed';
    public const STATUS_DISMISSED = 'dismissed';

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        self::STATUS_NEW => 'Nouveau',
        self::STATUS_IN_PROGRESS => 'En cours',
        self::STATUS_FIXED => 'Corrigé',
        self::STATUS_DISMISSED => 'Sans suite',
    ];

    /** Nombre maximal de rapports touchés par une action groupée (« tout traiter », « tout supprimer »). */
    public const BULK_LIMIT = 5000;

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
        try {
            $migration = dirname(__DIR__, 2) . '/bootstrap/atak_mod_reports_migration.php';
            if (is_file($migration)) {
                require_once $migration;
                if (function_exists('run_atak_mod_reports_migration')) {
                    run_atak_mod_reports_migration($this->pdo);
                }
            }
        } catch (\Throwable) {
            // Table via run-migrations / admin ; ne pas casser le boot API.
        }
    }

    /**
     * @param array{
     *   severity?:string,
     *   gravity?:?string,
     *   channel?:string,
     *   message:string,
     *   detail_text?:?string,
     *   context_json?:?string,
     *   fingerprint?:?string,
     *   source?:string,
     *   steam_uid?:?string,
     *   player_uid?:?string,
     *   player_name?:?string,
     *   callsign?:?string,
     *   client_ip?:?string,
     *   mod_version?:?string,
     *   extension_version?:?string,
     *   arma_build?:?string
     * } $data
     * @return array{id:int, created:bool, hit_count:int, gravity:string}
     */
    public function upsert(array $data): array
    {
        $severity = $this->normalizeSeverity((string) ($data['severity'] ?? 'error'));
        $channel = $this->clip((string) ($data['channel'] ?? 'Core'), 64) ?: 'Core';
        $message = $this->clip((string) ($data['message'] ?? ''), 512);
        if ($message === '') {
            throw new \InvalidArgumentException('message_required');
        }

        $fingerprint = $this->clip((string) ($data['fingerprint'] ?? ''), 64);
        // Le pack Link (fn_reportDiag) envoie les codes caractères de « error|tx|… » tronqués à 40 chiffres :
        // tous les échecs Tx d'un joueur avaient la même empreinte et se fondaient en un seul rapport
        // (message du premier, détail du dernier). On recalcule depuis le message dans ce cas.
        if (preg_match('/^\d{30,}$/', $fingerprint) === 1) {
            $fingerprint = '';
        }
        if ($fingerprint === '') {
            $fingerprint = substr(hash('sha256', strtolower($severity . '|' . $channel . '|' . $message)), 0, 40);
        }

        $steam = $this->clip((string) ($data['steam_uid'] ?? ''), 32);
        $steam = $steam !== '' ? $steam : null;
        $source = $this->clip((string) ($data['source'] ?? 'auto'), 32) ?: 'auto';
        $gravity = Gravity::classify(
            $source,
            $severity,
            $message,
            (string) ($data['detail_text'] ?? ''),
            isset($data['gravity']) ? (string) $data['gravity'] : null
        );

        // Dédup : même empreinte + même Steam dans les 30 dernières minutes → incrément
        $st = $this->pdo->prepare(
            'SELECT id, hit_count FROM atak_mod_reports
             WHERE fingerprint = ?
               AND ( (? IS NULL AND steam_uid IS NULL) OR steam_uid = ? )
               AND last_seen_at >= (NOW() - INTERVAL 30 MINUTE)
             ORDER BY id DESC LIMIT 1'
        );
        $st->execute([$fingerprint, $steam, $steam]);
        $existing = $st->fetch(PDO::FETCH_ASSOC);

        if (is_array($existing)) {
            $id = (int) $existing['id'];
            $upd = $this->pdo->prepare(
                'UPDATE atak_mod_reports SET
                    hit_count = hit_count + 1,
                    last_seen_at = CURRENT_TIMESTAMP,
                    detail_text = COALESCE(?, detail_text),
                    context_json = COALESCE(?, context_json),
                    player_name = COALESCE(?, player_name),
                    callsign = COALESCE(?, callsign),
                    mod_version = COALESCE(?, mod_version),
                    extension_version = COALESCE(?, extension_version),
                    arma_build = COALESCE(?, arma_build),
                    client_ip = COALESCE(?, client_ip)
                 WHERE id = ?'
            );
            $upd->execute([
                $this->nullableText($data['detail_text'] ?? null, 8000),
                $this->nullableText($data['context_json'] ?? null, 16000),
                $this->nullableClip($data['player_name'] ?? null, 128),
                $this->nullableClip($data['callsign'] ?? null, 64),
                $this->nullableClip($data['mod_version'] ?? null, 32),
                $this->nullableClip($data['extension_version'] ?? null, 32),
                $this->nullableClip($data['arma_build'] ?? null, 64),
                $this->nullableClip($data['client_ip'] ?? null, 45),
                $id,
            ]);
            $hits = (int) $existing['hit_count'] + 1;

            return ['id' => $id, 'created' => false, 'hit_count' => $hits, 'gravity' => $this->gravityOf($id, $gravity)];
        }

        $ins = $this->pdo->prepare(
            'INSERT INTO atak_mod_reports (
                severity, gravity, channel, message, detail_text, context_json, fingerprint, source,
                steam_uid, player_uid, player_name, callsign, client_ip,
                mod_version, extension_version, arma_build
             ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $ins->execute([
            $severity,
            $gravity,
            $channel,
            $message,
            $this->nullableText($data['detail_text'] ?? null, 8000),
            $this->nullableText($data['context_json'] ?? null, 16000),
            $fingerprint,
            $source,
            $steam,
            $this->nullableClip($data['player_uid'] ?? null, 64),
            $this->nullableClip($data['player_name'] ?? null, 128),
            $this->nullableClip($data['callsign'] ?? null, 64),
            $this->nullableClip($data['client_ip'] ?? null, 45),
            $this->nullableClip($data['mod_version'] ?? null, 32),
            $this->nullableClip($data['extension_version'] ?? null, 32),
            $this->nullableClip($data['arma_build'] ?? null, 64),
        ]);

        return ['id' => (int) $this->pdo->lastInsertId(), 'created' => true, 'hit_count' => 1, 'gravity' => $gravity];
    }

    /** Gravité gardée sur un rapport regroupé (elle a pu être corrigée à la main depuis). */
    private function gravityOf(int $id, string $fallback): string
    {
        try {
            $st = $this->pdo->prepare('SELECT gravity FROM atak_mod_reports WHERE id = ? LIMIT 1');
            $st->execute([$id]);

            return Gravity::normalize((string) $st->fetchColumn()) ?? $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }

    /**
     * Rapports automatiques critiques créés depuis N minutes pour le même joueur (Steam, sinon IP).
     * Sert de limite pour l'e-mail envoyé à l'exploitation.
     */
    public function countRecentCriticalAutoReports(?string $steamUid, ?string $clientIp, int $minutes = 60): int
    {
        $minutes = max(1, min(1440, $minutes));
        if ($steamUid !== null && $steamUid !== '') {
            $where = 'steam_uid = ?';
            $param = $steamUid;
        } elseif ($clientIp !== null && $clientIp !== '') {
            $where = 'steam_uid IS NULL AND client_ip = ?';
            $param = $clientIp;
        } else {
            return 0;
        }
        $st = $this->pdo->prepare(
            "SELECT COUNT(*) FROM atak_mod_reports WHERE source NOT LIKE 'player%' AND gravity = 'critical' AND {$where}
               AND created_at >= (NOW() - INTERVAL {$minutes} MINUTE)"
        );
        $st->execute([$param]);

        return (int) $st->fetchColumn();
    }

    /**
     * Signalements joueur (source « player… ») créés depuis N minutes par le même joueur
     * (Steam, sinon adresse IP). Sert de limite pour l'e-mail envoyé à l'exploitation.
     */
    public function countRecentPlayerReports(?string $steamUid, ?string $clientIp, int $minutes = 60): int
    {
        $minutes = max(1, min(1440, $minutes));
        if ($steamUid !== null && $steamUid !== '') {
            $st = $this->pdo->prepare(
                "SELECT COUNT(*) FROM atak_mod_reports WHERE source LIKE 'player%' AND steam_uid = ?
                   AND created_at >= (NOW() - INTERVAL {$minutes} MINUTE)"
            );
            $st->execute([$steamUid]);
        } elseif ($clientIp !== null && $clientIp !== '') {
            $st = $this->pdo->prepare(
                "SELECT COUNT(*) FROM atak_mod_reports WHERE source LIKE 'player%' AND steam_uid IS NULL AND client_ip = ?
                   AND created_at >= (NOW() - INTERVAL {$minutes} MINUTE)"
            );
            $st->execute([$clientIp]);
        } else {
            return 0;
        }

        return (int) $st->fetchColumn();
    }

    public const SORT_RECENT = 'recent';
    public const SORT_GRAVITY = 'gravity';

    /** @return list<array<string, mixed>> */
    public function listRecent(
        int $limit = 100,
        ?string $severity = null,
        ?string $workflowStatus = null,
        ?string $search = null,
        ?string $gravity = null,
        string $sort = self::SORT_RECENT
    ): array {
        $limit = max(1, min(500, $limit));
        [$whereSql, $params] = $this->filterSql($severity, $workflowStatus, $search, $gravity);
        $order = $sort === self::SORT_GRAVITY
            ? "CASE gravity WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END, last_seen_at DESC, id DESC"
            : 'last_seen_at DESC, id DESC';
        $sql = 'SELECT * FROM atak_mod_reports' . $whereSql . ' ORDER BY ' . $order . ' LIMIT ' . $limit;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    public function countAll(?string $severity = null, ?string $workflowStatus = null, ?string $search = null, ?string $gravity = null): int
    {
        [$whereSql, $params] = $this->filterSql($severity, $workflowStatus, $search, $gravity);
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM atak_mod_reports' . $whereSql);
        $st->execute($params);

        return (int) $st->fetchColumn();
    }

    /**
     * Nombre de rapports par statut de suivi (tous types confondus).
     *
     * @return array<string, int>
     */
    public function countByStatus(): array
    {
        $out = array_fill_keys(array_keys(self::STATUS_LABELS), 0);
        $rows = $this->pdo->query('SELECT workflow_status, COUNT(*) AS n FROM atak_mod_reports GROUP BY workflow_status');
        foreach ($rows ? $rows->fetchAll(PDO::FETCH_ASSOC) : [] as $row) {
            $key = $this->normalizeWorkflowStatus((string) ($row['workflow_status'] ?? ''));
            $out[$key] += (int) ($row['n'] ?? 0);
        }

        return $out;
    }

    /**
     * Rapports à traiter (nouveaux ou en cours) par gravité.
     *
     * @return array<string, int>
     */
    public function countOpenByGravity(): array
    {
        $out = array_fill_keys(Gravity::LEVELS, 0);
        $rows = $this->pdo->query(
            "SELECT gravity, COUNT(*) AS n FROM atak_mod_reports
              WHERE workflow_status IN ('new', 'in_progress') GROUP BY gravity"
        );
        foreach ($rows ? $rows->fetchAll(PDO::FETCH_ASSOC) : [] as $row) {
            $key = Gravity::normalize((string) ($row['gravity'] ?? '')) ?? Gravity::NORMAL;
            $out[$key] += (int) ($row['n'] ?? 0);
        }

        return $out;
    }

    /**
     * Corrige la gravité à la main.
     *
     * @param list<int> $ids
     * @return int rapports changés
     */
    public function updateGravityMany(array $ids, string $gravity): int
    {
        $ids = self::cleanIds($ids);
        $norm = Gravity::normalize($gravity);
        if ($ids === [] || $norm === null) {
            return 0;
        }
        $st = $this->pdo->prepare(
            'UPDATE atak_mod_reports SET gravity = ?, last_seen_at = last_seen_at
              WHERE gravity <> ? AND id IN (' . self::placeholders($ids) . ')'
        );
        $st->execute(array_merge([$norm, $norm], $ids));

        return $st->rowCount();
    }

    /**
     * Identifiants des rapports correspondant à un filtre (actions « tout traiter » sur la vue filtrée).
     *
     * @return list<int>
     */
    public function idsMatching(?string $severity = null, ?string $workflowStatus = null, ?string $search = null, ?string $gravity = null): array
    {
        [$whereSql, $params] = $this->filterSql($severity, $workflowStatus, $search, $gravity);
        $st = $this->pdo->prepare('SELECT id FROM atak_mod_reports' . $whereSql . ' ORDER BY id DESC LIMIT ' . self::BULK_LIMIT);
        $st->execute($params);

        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT * FROM atak_mod_reports WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param list<int> $ids
     * @return list<array<string, mixed>>
     */
    public function findByIds(array $ids): array
    {
        $ids = self::cleanIds($ids);
        if ($ids === []) {
            return [];
        }
        $st = $this->pdo->prepare('SELECT * FROM atak_mod_reports WHERE id IN (' . self::placeholders($ids) . ')');
        $st->execute($ids);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Statut actuel de quelques rapports (fenêtre « réponse à votre signalement » du joueur).
     *
     * @param list<int> $ids
     * @return array<int, string> id → statut normalisé
     */
    public function workflowStatusesByIds(array $ids): array
    {
        $ids = self::cleanIds($ids);
        if ($ids === []) {
            return [];
        }
        $st = $this->pdo->prepare('SELECT id, workflow_status FROM atak_mod_reports WHERE id IN (' . self::placeholders($ids) . ')');
        $st->execute($ids);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[(int) $row['id']] = $this->normalizeWorkflowStatus((string) ($row['workflow_status'] ?? ''));
        }

        return $out;
    }

    public function updateWorkflowStatus(int $id, string $status): bool
    {
        return $id > 0 && $this->updateWorkflowStatusMany([$id], $status) > 0;
    }

    /**
     * @param list<int> $ids
     * @return int rapports dont le statut a changé
     */
    public function updateWorkflowStatusMany(array $ids, string $status): int
    {
        $ids = self::cleanIds($ids);
        if ($ids === []) {
            return 0;
        }
        // last_seen_at = last_seen_at : la colonne a ON UPDATE CURRENT_TIMESTAMP ; changer le suivi
        // ne doit pas faire passer le rapport pour « vu à l'instant ».
        $st = $this->pdo->prepare(
            'UPDATE atak_mod_reports
                SET workflow_status = ?, status_changed_at = CURRENT_TIMESTAMP, last_seen_at = last_seen_at
              WHERE workflow_status <> ? AND id IN (' . self::placeholders($ids) . ')'
        );
        $norm = $this->normalizeWorkflowStatus($status);
        $st->execute(array_merge([$norm, $norm], $ids));

        return $st->rowCount();
    }

    /**
     * Garde la réponse envoyée au joueur sur le rapport (visible sur la page même sans compte lié).
     *
     * @param list<string> $channels canaux réellement utilisés : site, email
     */
    public function saveReply(int $id, string $text, string $by, array $channels): bool
    {
        if ($id < 1) {
            return false;
        }
        $st = $this->pdo->prepare(
            'UPDATE atak_mod_reports
                SET reply_text = ?, replied_at = CURRENT_TIMESTAMP, replied_by = ?, reply_channels = ?,
                    last_seen_at = last_seen_at
              WHERE id = ?'
        );
        $st->execute([
            $this->nullableText($text, 4000),
            $this->nullableClip($by, 128),
            $channels !== [] ? implode(',', array_values(array_unique($channels))) : null,
            $id,
        ]);

        return $st->rowCount() > 0;
    }

    /**
     * Compte Athena lié au Steam de chaque joueur (pour le prévenir sur le site ou par e-mail).
     * Comptes membres (users.steam_id) d'abord, puis comptes Athena globaux (athena_accounts.steam_id).
     * « lang » : langue du profil Athena du joueur (fr par défaut, en si son profil est en anglais).
     *
     * @param list<string> $steamUids
     * @return array<string, array{user_id:int, tenant_id:int, email:string, name:string, lang:string}>
     */
    public function linkedAccountsBySteam(array $steamUids): array
    {
        $steamUids = array_values(array_unique(array_filter(
            array_map(static fn ($s): string => trim((string) $s), $steamUids),
            static fn (string $s): bool => $s !== ''
        )));
        if ($steamUids === []) {
            return [];
        }
        $out = [];
        try {
            $st = $this->pdo->prepare(
                'SELECT id, tenant_id, email, display_name, callsign, steam_id FROM users
                  WHERE steam_id IN (' . self::placeholders($steamUids) . ')
                  ORDER BY id DESC'
            );
            $st->execute($steamUids);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $u) {
                $steam = trim((string) ($u['steam_id'] ?? ''));
                if ($steam === '' || isset($out[$steam])) {
                    continue;
                }
                $out[$steam] = [
                    'user_id' => (int) ($u['id'] ?? 0),
                    'tenant_id' => (int) ($u['tenant_id'] ?? 0),
                    'email' => self::validEmail($u['email'] ?? null),
                    'name' => trim((string) ($u['callsign'] ?? '')) ?: trim((string) ($u['display_name'] ?? '')),
                ];
            }
        } catch (\Throwable) {
            // Table users absente (environnement de test) : pas de compte lié.
        }

        $missing = array_values(array_filter($steamUids, static fn (string $s): bool => !isset($out[$s])));
        if ($missing !== []) {
            try {
                $st = $this->pdo->prepare(
                    "SELECT a.steam_id, a.email, m.user_id, m.tenant_id
                       FROM athena_accounts a
                       LEFT JOIN account_tenant_memberships m ON m.account_id = a.id AND m.status = 'active'
                      WHERE a.steam_id IN (" . self::placeholders($missing) . ')
                      ORDER BY m.user_id DESC'
                );
                $st->execute($missing);
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $a) {
                    $steam = trim((string) ($a['steam_id'] ?? ''));
                    if ($steam === '' || isset($out[$steam])) {
                        continue;
                    }
                    $out[$steam] = [
                        'user_id' => (int) ($a['user_id'] ?? 0),
                        'tenant_id' => (int) ($a['tenant_id'] ?? 0),
                        'email' => self::validEmail($a['email'] ?? null),
                        'name' => '',
                    ];
                }
            } catch (\Throwable) {
                // Comptes Athena globaux absents : on garde les comptes membres trouvés.
            }
        }

        $langs = $this->profileLanguages(array_map(static fn (array $a): int => $a['user_id'], array_values($out)));
        foreach ($out as $steam => $account) {
            $out[$steam]['lang'] = $langs[$account['user_id']] ?? 'fr';
        }

        return $out;
    }

    /**
     * Langue choisie par chaque membre sur Athena (user_profiles.language) ; absente = français.
     *
     * @param list<int> $userIds
     * @return array<int, string> fr ou en
     */
    public function profileLanguages(array $userIds): array
    {
        $userIds = self::cleanIds($userIds);
        if ($userIds === []) {
            return [];
        }
        $out = [];
        try {
            $st = $this->pdo->prepare(
                'SELECT user_id, language FROM user_profiles WHERE user_id IN (' . self::placeholders($userIds) . ')'
            );
            $st->execute($userIds);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $lang = strtolower(trim((string) ($row['language'] ?? '')));
                if ($lang !== '') {
                    $out[(int) $row['user_id']] = str_starts_with($lang, 'en') ? 'en' : 'fr';
                }
            }
        } catch (\Throwable) {
            // Pas de table des profils : français.
        }

        return $out;
    }

    public function deleteById(int $id): bool
    {
        return $id > 0 && $this->deleteMany([$id]) > 0;
    }

    /**
     * @param list<int> $ids
     */
    public function deleteMany(array $ids): int
    {
        $ids = self::cleanIds($ids);
        if ($ids === []) {
            return 0;
        }
        $st = $this->pdo->prepare('DELETE FROM atak_mod_reports WHERE id IN (' . self::placeholders($ids) . ')');
        $st->execute($ids);

        return $st->rowCount();
    }

    public function normalizeWorkflowStatus(string $raw): string
    {
        $s = strtolower(trim($raw));

        return match ($s) {
            'new', 'nouveau', 'open' => self::STATUS_NEW,
            'in_progress', 'en_cours', 'progress', 'triaged', 'wip' => self::STATUS_IN_PROGRESS,
            'fixed', 'corrige', 'corrigé', 'done', 'closed', 'resolved' => self::STATUS_FIXED,
            'dismissed', 'sans_suite', 'ignored', 'wontfix', 'duplicate' => self::STATUS_DISMISSED,
            default => self::STATUS_NEW,
        };
    }

    public static function statusLabel(string $status): string
    {
        $key = strtolower(trim($status));

        return self::STATUS_LABELS[$key] ?? self::STATUS_LABELS[self::STATUS_NEW];
    }

    /**
     * @return array{0:string, 1:list<string>} clause WHERE (avec espace initial) et paramètres
     */
    private function filterSql(?string $severity, ?string $workflowStatus, ?string $search, ?string $gravity = null): array
    {
        $where = [];
        $params = [];
        $gravity = Gravity::normalize($gravity);
        if ($gravity !== null) {
            $where[] = 'gravity = ?';
            $params[] = $gravity;
        }
        if ($severity !== null && $severity !== '') {
            $where[] = 'severity = ?';
            $params[] = $this->normalizeSeverity($severity);
        }
        if ($workflowStatus !== null && $workflowStatus !== '') {
            $where[] = 'workflow_status = ?';
            $params[] = $this->normalizeWorkflowStatus($workflowStatus);
        }
        $search = trim((string) $search);
        if ($search !== '') {
            $like = '%' . addcslashes(mb_substr($search, 0, 80), '%_\\') . '%';
            $where[] = '(message LIKE ? OR player_name LIKE ? OR callsign LIKE ? OR steam_uid LIKE ? OR channel LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
        }

        return [$where !== [] ? ' WHERE ' . implode(' AND ', $where) : '', $params];
    }

    /**
     * @param array<mixed> $ids
     * @return list<int>
     */
    private static function cleanIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));

        return array_slice($ids, 0, self::BULK_LIMIT);
    }

    /** @param list<mixed> $values */
    private static function placeholders(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }

    private static function validEmail(mixed $raw): string
    {
        $email = trim((string) $raw);

        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }

    private function normalizeSeverity(string $raw): string
    {
        $s = strtolower(trim($raw));

        return match ($s) {
            'error', 'err', 'fatal', 'critical' => 'error',
            'warn', 'warning' => 'warn',
            'info' => 'info',
            'bug', 'player', 'user' => 'bug',
            default => 'error',
        };
    }

    private function clip(string $s, int $max): string
    {
        $s = trim($s);
        if (mb_strlen($s) <= $max) {
            return $s;
        }

        return mb_substr($s, 0, $max);
    }

    private function nullableClip(mixed $raw, int $max): ?string
    {
        if ($raw === null) {
            return null;
        }
        $s = $this->clip((string) $raw, $max);

        return $s !== '' ? $s : null;
    }

    private function nullableText(mixed $raw, int $max): ?string
    {
        if ($raw === null) {
            return null;
        }
        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }
        if (mb_strlen($s) > $max) {
            $s = mb_substr($s, 0, $max);
        }

        return $s;
    }
}
