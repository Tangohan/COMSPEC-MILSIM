<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use PDO;

/**
 * Files d'envoi de COMSPEC Link (POST /api/atak/send-queue/report) : chaque joueur (client) et chaque
 * serveur Arma envoie toutes les ~30 s, quand quelque chose a changé, l'état de sa file d'envoi vers Athena :
 * nombre d'envois en attente / en cours / à réessayer / en échec, temps de réponse d'Athena, refus
 * « trop de requêtes » (429), erreurs serveur (5xx), délais dépassés, et les 100 derniers envois avec
 * leurs étapes (en attente → envoi → nouvel essai → envoyé / échec).
 *
 *   atak_send_queue_snapshots : dernier bilan de chaque client (communauté, Steam ID, type de client) ;
 *   atak_send_queue_history   : un résumé par bilan, gardé 48 h.
 */
class AtakSendQueueRepository
{
    use LazyDatabaseConnection;

    public const STATES = ['queued', 'sending', 'retry', 'failed', 'done'];
    public const OVERLOADS = ['ok', 'slow', 'rate_limited', 'offline'];
    public const CLIENT_KINDS = ['client', 'server'];

    public const MAX_BODY_BYTES = 262144;
    public const MAX_ITEMS = 100;
    public const MAX_STEPS = 30;
    public const HISTORY_HOURS = 48;
    /** Un bilan plus ancien ne compte plus dans l'état d'Athena. */
    public const FRESH_SECONDS = 300;
    /** Au-delà, la ligne du client est grisée (le jeu envoie toutes les ~30 s quand quelque chose change). */
    public const STALE_SECONDS = 120;

    /** Clé de stockage d'un serveur Arma qui n'envoie pas de Steam ID. */
    public const SERVER_KEY = 'server';

    private const SUMMARY_INTS = [
        'queued' => 100000, 'sending' => 100000, 'retry' => 100000, 'failed' => 100000, 'done' => 1000000,
        'oldest_queued_s' => 604800, 'rate_limited_for_s' => 86400, 'backoff_for_s' => 86400,
        'avg_latency_ms' => 600000, 'p95_latency_ms' => 600000,
        'http_429' => 1000000, 'http_5xx' => 1000000, 'timeouts' => 1000000, 'window_s' => 86400,
    ];

    private static ?bool $ready = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public function schemaReady(): bool
    {
        if (self::$ready === null) {
            try {
                $st = $this->pdo()->query(
                    "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()
                     AND TABLE_NAME IN ('atak_send_queue_snapshots', 'atak_send_queue_history')"
                );
                self::$ready = $st !== false && (int) $st->fetchColumn() === 2;
            } catch (\Throwable) {
                self::$ready = false;
            }
        }

        return self::$ready;
    }

    /**
     * Nettoie un bilan envoyé par le jeu. $steamUid : Steam ID déjà vérifié par la garde d'écriture (prioritaire).
     * Null si le bilan est inutilisable (client sans Steam ID valide).
     *
     * @param array<string, mixed> $body
     * @return array{steam_uid: string, client_kind: string, call_sign: string, extension_version: string, mod_version: string, summary: array<string, int|string>, items: list<array<string, mixed>>}|null
     */
    public static function normalizeReport(array $body, ?string $steamUid = null): ?array
    {
        $kind = strtolower(trim(self::scalar($body['client_kind'] ?? 'client')));
        if (!in_array($kind, self::CLIENT_KINDS, true)) {
            $kind = 'client';
        }
        $uid = $steamUid ?? trim(self::scalar($body['steam_uid'] ?? $body['player_uid'] ?? ''));
        if (!preg_match('/^7656\d{13}$/', $uid)) {
            if ($kind !== 'server') {
                return null;
            }
            $uid = self::SERVER_KEY;
        }

        return [
            'steam_uid' => $uid,
            'client_kind' => $kind,
            'call_sign' => self::text($body['call_sign'] ?? '', 64),
            'extension_version' => self::text($body['extension_version'] ?? '', 32),
            'mod_version' => self::text($body['mod_version'] ?? '', 32),
            'summary' => self::normalizeSummary($body['summary'] ?? []),
            'items' => self::normalizeItems($body['items'] ?? []),
        ];
    }

    /**
     * @return array<string, int|string>
     */
    public static function normalizeSummary(mixed $summary): array
    {
        $summary = is_array($summary) ? $summary : [];
        $out = [];
        foreach (self::SUMMARY_INTS as $key => $max) {
            $out[$key] = self::int($summary[$key] ?? 0, 0, $max);
        }
        $overload = strtolower(trim(self::scalar($summary['overload'] ?? 'ok')));
        $out['overload'] = in_array($overload, self::OVERLOADS, true) ? $overload : 'ok';

        return $out;
    }

    /**
     * Envois suivis : 100 au plus, état connu, plus récent d'abord.
     *
     * @return list<array{id: string, kind: string, label: string, state: string, attempts: int, bytes: int, created_at: int, updated_at: int, last_http: int, last_error: string, steps: list<array{0: string, 1: int, 2: string}>}>
     */
    public static function normalizeItems(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }
        $out = [];
        $seen = [];
        foreach (array_slice(array_values($items), 0, self::MAX_ITEMS) as $i => $it) {
            if (!is_array($it)) {
                continue;
            }
            $state = strtolower(trim(self::scalar($it['state'] ?? '')));
            if (!in_array($state, self::STATES, true)) {
                continue;
            }
            $id = preg_replace('/[^A-Za-z0-9_.:\-]/', '', self::scalar($it['id'] ?? '')) ?? '';
            $id = substr($id, 0, 64);
            if ($id === '' || isset($seen[$id])) {
                $id = 'n' . $i;
            }
            $seen[$id] = true;
            $steps = [];
            foreach (array_slice(is_array($it['steps'] ?? null) ? array_values($it['steps']) : [], 0, self::MAX_STEPS) as $s) {
                if (!is_array($s)) {
                    continue;
                }
                $stepState = $s[0] ?? $s['state'] ?? '';
                $stepState = strtolower(preg_replace('/[^a-z_]/i', '', self::scalar($stepState)) ?? '');
                if ($stepState === '') {
                    continue;
                }
                $steps[] = [
                    substr($stepState, 0, 16),
                    self::int($s[1] ?? $s['at'] ?? 0, 0, 4102444800),
                    self::text($s[2] ?? $s['note'] ?? '', 120),
                ];
            }
            $out[] = [
                'id' => $id,
                'kind' => self::text($it['kind'] ?? '', 120),
                'label' => self::text($it['label'] ?? '', 120),
                'state' => $state,
                'attempts' => self::int($it['attempts'] ?? 0, 0, 10000),
                'bytes' => self::int($it['bytes'] ?? 0, 0, 1073741824),
                'created_at' => self::int($it['created_at'] ?? 0, 0, 4102444800),
                'updated_at' => self::int($it['updated_at'] ?? 0, 0, 4102444800),
                'last_http' => self::int($it['last_http'] ?? 0, 0, 999),
                'last_error' => self::text($it['last_error'] ?? '', 160),
                'steps' => $steps,
            ];
        }
        // Plus récent d'abord (ordre d'arrivée conservé à égalité).
        $order = array_keys($out);
        usort($order, static fn (int $a, int $b): int => [max($out[$b]['updated_at'], $out[$b]['created_at']), $a] <=> [max($out[$a]['updated_at'], $out[$a]['created_at']), $b]);

        return array_map(static fn (int $k): array => $out[$k], $order);
    }

    /**
     * Enregistre un bilan : remplace le dernier bilan du client et ajoute une ligne d'historique (purge > 48 h).
     *
     * @param array{steam_uid: string, client_kind: string, call_sign: string, extension_version: string, mod_version: string, summary: array<string, int|string>, items: list<array<string, mixed>>} $report
     */
    public function record(int $tenantId, array $report): void
    {
        if ($tenantId < 1 || !$this->schemaReady()) {
            return;
        }
        $s = $report['summary'];
        $itemsJson = json_encode($report['items'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        $pdo = $this->pdo();
        $st = $pdo->prepare(
            'INSERT INTO atak_send_queue_snapshots
                (tenant_id, steam_uid, client_kind, call_sign, extension_version, mod_version, queued, sending, retry, failed, done,
                 oldest_queued_s, overload, rate_limited_for_s, backoff_for_s, avg_latency_ms, p95_latency_ms, http_429, http_5xx, timeouts,
                 window_s, items_count, items_json, received_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                call_sign = VALUES(call_sign), extension_version = VALUES(extension_version), mod_version = VALUES(mod_version),
                queued = VALUES(queued), sending = VALUES(sending), retry = VALUES(retry), failed = VALUES(failed), done = VALUES(done),
                oldest_queued_s = VALUES(oldest_queued_s), overload = VALUES(overload), rate_limited_for_s = VALUES(rate_limited_for_s),
                backoff_for_s = VALUES(backoff_for_s), avg_latency_ms = VALUES(avg_latency_ms), p95_latency_ms = VALUES(p95_latency_ms),
                http_429 = VALUES(http_429), http_5xx = VALUES(http_5xx), timeouts = VALUES(timeouts), window_s = VALUES(window_s),
                items_count = VALUES(items_count), items_json = VALUES(items_json), received_at = VALUES(received_at)'
        );
        $st->execute([
            $tenantId, $report['steam_uid'], $report['client_kind'],
            $report['call_sign'] !== '' ? $report['call_sign'] : null,
            $report['extension_version'] !== '' ? $report['extension_version'] : null,
            $report['mod_version'] !== '' ? $report['mod_version'] : null,
            $s['queued'], $s['sending'], $s['retry'], $s['failed'], $s['done'], $s['oldest_queued_s'], $s['overload'],
            $s['rate_limited_for_s'], $s['backoff_for_s'], $s['avg_latency_ms'], $s['p95_latency_ms'],
            $s['http_429'], $s['http_5xx'], $s['timeouts'], $s['window_s'],
            count($report['items']), $itemsJson === false ? '[]' : $itemsJson,
        ]);
        $pdo->prepare(
            'INSERT INTO atak_send_queue_history
                (tenant_id, steam_uid, client_kind, overload, queued, retry, failed, avg_latency_ms, http_429, http_5xx, timeouts, received_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        )->execute([
            $tenantId, $report['steam_uid'], $report['client_kind'], $s['overload'], $s['queued'], $s['retry'], $s['failed'],
            $s['avg_latency_ms'], $s['http_429'], $s['http_5xx'], $s['timeouts'],
        ]);
        // Purge bornée : chaque bilan retire au plus 500 lignes de plus de 48 h (toutes communautés).
        $pdo->exec('DELETE FROM atak_send_queue_history WHERE received_at < (NOW() - INTERVAL ' . self::HISTORY_HOURS . ' HOUR) LIMIT 500');
    }

    /**
     * Derniers bilans de la communauté (sans le détail des envois), plus récent d'abord.
     *
     * @return list<array<string, mixed>>
     */
    public function listSnapshots(int $tenantId): array
    {
        if ($tenantId < 1 || !$this->schemaReady()) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT steam_uid, client_kind, call_sign, extension_version, mod_version, queued, sending, retry, failed, done,
                    oldest_queued_s, overload, rate_limited_for_s, backoff_for_s, avg_latency_ms, p95_latency_ms,
                    http_429, http_5xx, timeouts, window_s, items_count, UNIX_TIMESTAMP(received_at) AS received_ts
             FROM atak_send_queue_snapshots WHERE tenant_id = ? ORDER BY received_at DESC LIMIT 500'
        );
        $st->execute([$tenantId]);

        return array_map([self::class, 'castSnapshot'], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /**
     * Dernier bilan d'un client, avec ses envois suivis.
     *
     * @return array<string, mixed>|null
     */
    public function findSnapshot(int $tenantId, string $steamUid, string $clientKind): ?array
    {
        if ($tenantId < 1 || $steamUid === '' || !in_array($clientKind, self::CLIENT_KINDS, true) || !$this->schemaReady()) {
            return null;
        }
        $st = $this->pdo()->prepare(
            'SELECT steam_uid, client_kind, call_sign, extension_version, mod_version, queued, sending, retry, failed, done,
                    oldest_queued_s, overload, rate_limited_for_s, backoff_for_s, avg_latency_ms, p95_latency_ms,
                    http_429, http_5xx, timeouts, window_s, items_count, items_json, UNIX_TIMESTAMP(received_at) AS received_ts
             FROM atak_send_queue_snapshots WHERE tenant_id = ? AND steam_uid = ? AND client_kind = ? LIMIT 1'
        );
        $st->execute([$tenantId, $steamUid, $clientKind]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        $snap = self::castSnapshot($row);
        $items = json_decode((string) ($row['items_json'] ?? ''), true);
        // Relu tel quel du stockage, mais renormalisé par prudence (forme garantie pour la vue).
        $snap['items'] = self::normalizeItems(is_array($items) ? $items : []);

        return $snap;
    }

    /**
     * Historique par tranche : pour chaque tranche et chaque client, son pire état et ses compteurs les plus hauts,
     * puis le total de la tranche. Tranches sans bilan incluses (vides), plus ancienne d'abord.
     *
     * @return list<array{start: int, clients: int, reports: int, overloaded: int, slow: int, offline: int, queued: int, retry: int, failed: int, avg_latency_ms: int, http_429: int, http_5xx: int, timeouts: int}>
     */
    public function historyBuckets(int $tenantId, int $hours = 6, int $bucketMinutes = 15, ?int $now = null): array
    {
        $hours = max(1, min(self::HISTORY_HOURS, $hours));
        $bucket = max(60, $bucketMinutes * 60);
        $now ??= time();
        $first = intdiv($now - $hours * 3600, $bucket) * $bucket + $bucket;
        $buckets = [];
        for ($t = $first; $t <= $now; $t += $bucket) {
            $buckets[$t] = [
                'start' => $t, 'clients' => 0, 'reports' => 0, 'overloaded' => 0, 'slow' => 0, 'offline' => 0,
                'queued' => 0, 'retry' => 0, 'failed' => 0, 'avg_latency_ms' => 0, 'http_429' => 0, 'http_5xx' => 0, 'timeouts' => 0,
            ];
        }
        if ($tenantId < 1 || !$this->schemaReady()) {
            return array_values($buckets);
        }
        $st = $this->pdo()->prepare(
            "SELECT FLOOR(UNIX_TIMESTAMP(received_at) / ?) * ? AS b, steam_uid, client_kind, COUNT(*) AS reports,
                    MAX(CASE overload WHEN 'offline' THEN 3 WHEN 'rate_limited' THEN 2 WHEN 'slow' THEN 1 ELSE 0 END) AS worst,
                    MAX(queued) AS queued, MAX(retry) AS retry, MAX(failed) AS failed, AVG(NULLIF(avg_latency_ms, 0)) AS latency,
                    MAX(http_429) AS http_429, MAX(http_5xx) AS http_5xx, MAX(timeouts) AS timeouts
             FROM atak_send_queue_history
             WHERE tenant_id = ? AND received_at >= FROM_UNIXTIME(?)
             GROUP BY b, steam_uid, client_kind"
        );
        $st->execute([$bucket, $bucket, $tenantId, $first]);
        $latency = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $b = (int) $r['b'];
            if (!isset($buckets[$b])) {
                continue;
            }
            $buckets[$b]['clients']++;
            $buckets[$b]['reports'] += (int) $r['reports'];
            $worst = (int) $r['worst'];
            if ($worst === 3) {
                $buckets[$b]['offline']++;
            } elseif ($worst === 2) {
                $buckets[$b]['overloaded']++;
            } elseif ($worst === 1) {
                $buckets[$b]['slow']++;
            }
            foreach (['queued', 'retry', 'failed', 'http_429', 'http_5xx', 'timeouts'] as $k) {
                $buckets[$b][$k] += (int) $r[$k];
            }
            if ($r['latency'] !== null && (float) $r['latency'] > 0) {
                $latency[$b][] = (float) $r['latency'];
            }
        }
        foreach ($latency as $b => $list) {
            $buckets[$b]['avg_latency_ms'] = (int) round(array_sum($list) / count($list));
        }

        return array_values($buckets);
    }

    /**
     * État d'Athena vu par les clients qui ont envoyé un bilan ces 5 dernières minutes.
     * level : none | normal | slow (Ralenti) | overloaded (Surchargé) | offline (Hors ligne).
     *
     * @param list<array<string, mixed>> $snapshots lignes de listSnapshots()
     * @return array{level: string, label: string, explanation: string, clients: int, rate_limited: int, slow: int, offline: int, queued: int, retry: int, failed: int, oldest_queued_s: int, avg_latency_ms: int, p95_latency_ms: int, http_429: int, http_5xx: int, timeouts: int}
     */
    public static function computeStatus(array $snapshots, ?int $now = null): array
    {
        $now ??= time();
        $s = [
            'level' => 'none', 'label' => 'Aucune donnée', 'explanation' => '',
            'clients' => 0, 'rate_limited' => 0, 'slow' => 0, 'offline' => 0,
            'queued' => 0, 'retry' => 0, 'failed' => 0, 'oldest_queued_s' => 0,
            'avg_latency_ms' => 0, 'p95_latency_ms' => 0, 'http_429' => 0, 'http_5xx' => 0, 'timeouts' => 0,
        ];
        $latencies = [];
        foreach ($snapshots as $r) {
            if ((int) ($r['received_ts'] ?? 0) < $now - self::FRESH_SECONDS) {
                continue;
            }
            $s['clients']++;
            $overload = (string) ($r['overload'] ?? 'ok');
            if (isset($s[$overload]) && $overload !== 'ok') {
                $s[$overload]++;
            }
            foreach (['queued', 'retry', 'failed', 'http_429', 'http_5xx', 'timeouts'] as $k) {
                $s[$k] += (int) ($r[$k] ?? 0);
            }
            $s['oldest_queued_s'] = max($s['oldest_queued_s'], (int) ($r['oldest_queued_s'] ?? 0));
            $s['p95_latency_ms'] = max($s['p95_latency_ms'], (int) ($r['p95_latency_ms'] ?? 0));
            if ((int) ($r['avg_latency_ms'] ?? 0) > 0) {
                $latencies[] = (int) $r['avg_latency_ms'];
            }
        }
        if ($latencies !== []) {
            $s['avg_latency_ms'] = (int) round(array_sum($latencies) / count($latencies));
        }
        $n = $s['clients'];
        if ($n === 0) {
            $s['explanation'] = 'Aucun joueur ni serveur n’a envoyé de bilan ces 5 dernières minutes (personne en jeu, ou version de COMSPEC Link trop ancienne).';

            return $s;
        }
        $errors = $s['http_5xx'] + $s['timeouts'];
        if ($s['offline'] * 2 >= $n) {
            $s['level'] = 'offline';
            $s['label'] = 'Hors ligne';
            $s['explanation'] = sprintf('%d client(s) sur %d n’arrivent plus à joindre Athena : les envois attendent dans les files et partiront au retour de la liaison.', $s['offline'], $n);
        } elseif ($s['rate_limited'] > 0 || $s['http_429'] > 0 || $errors >= 10 || $s['p95_latency_ms'] >= 5000) {
            $s['level'] = 'overloaded';
            $s['label'] = 'Surchargé';
            $s['explanation'] = $s['rate_limited'] > 0 || $s['http_429'] > 0
                ? sprintf('Athena refuse une partie des envois (trop de requêtes) : %d client(s) attendent avant de renvoyer.', max($s['rate_limited'], 1))
                : sprintf('Athena répond mal : %d erreur(s) serveur ou délai(s) dépassé(s), réponses lentes. Les envois sont réessayés.', $errors);
        } elseif ($s['slow'] > 0 || $s['offline'] > 0 || $s['retry'] > 0 || $errors > 0 || $s['p95_latency_ms'] >= 2000 || $s['oldest_queued_s'] >= 60) {
            $s['level'] = 'slow';
            $s['label'] = 'Ralenti';
            $s['explanation'] = $s['offline'] > 0
                ? sprintf('%d client(s) n’arrivent pas à joindre Athena (souvent leur propre connexion) ; les autres passent.', $s['offline'])
                : 'Athena répond, mais plus lentement que d’habitude : certains envois attendent ou sont réessayés.';
        } else {
            $s['level'] = 'normal';
            $s['label'] = 'Normal';
            $s['explanation'] = 'Athena répond vite et les envois partent sans attendre.';
        }

        return $s;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function castSnapshot(array $row): array
    {
        $out = [
            'steam_uid' => (string) $row['steam_uid'],
            'client_kind' => (string) $row['client_kind'],
            'call_sign' => (string) ($row['call_sign'] ?? ''),
            'extension_version' => (string) ($row['extension_version'] ?? ''),
            'mod_version' => (string) ($row['mod_version'] ?? ''),
            'overload' => in_array((string) $row['overload'], self::OVERLOADS, true) ? (string) $row['overload'] : 'ok',
            'items_count' => (int) ($row['items_count'] ?? 0),
            'received_ts' => (int) ($row['received_ts'] ?? 0),
        ];
        foreach (array_keys(self::SUMMARY_INTS) as $k) {
            $out[$k] = (int) ($row[$k] ?? 0);
        }

        return $out;
    }

    private static function scalar(mixed $v): string
    {
        return is_scalar($v) ? (string) $v : '';
    }

    private static function text(mixed $v, int $max): string
    {
        $s = self::scalar($v);
        if (!mb_check_encoding($s, 'UTF-8')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
        }

        return mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', $s) ?? ''), 0, $max);
    }

    private static function int(mixed $v, int $min, int $max): int
    {
        if (!is_int($v) && !is_float($v) && !(is_string($v) && is_numeric(trim($v)))) {
            return $min;
        }
        $f = (float) $v;
        if (!is_finite($f)) {
            return $min;
        }

        return (int) max($min, min($max, round($f)));
    }
}
