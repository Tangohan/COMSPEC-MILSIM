<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Processus d'un avis de recherche (table fille sse_wanted_notice_meta) : service demandeur (code et instantané), classification,
 * avis renouvelé, état de validation, service lévant et historique des prolongations. Un avis sans ligne ici est un avis posé à la
 * main et diffusé d'office : il fonctionne comme avant. Sans la table (étape de base de données en attente), tout renvoie « vide ».
 */
final class SseWantedMetaRepository
{
    public const LIVE = 'live';

    public const PENDING = 'pending';

    public const REJECTED = 'rejected';

    private Database $db;

    /** @var \WeakMap<PDO, array{ok: bool, at: int}>|null */
    private static ?\WeakMap $readyByConnection = null;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public static function ready(): bool
    {
        try {
            $pdo = Database::getPdo();
            self::$readyByConnection ??= new \WeakMap();
            $known = self::$readyByConnection[$pdo] ?? null;
            if (is_array($known) && ($known['ok'] || time() - $known['at'] < 20)) {
                return $known['ok'];
            }
            try {
                $pdo->query('SELECT 1 FROM sse_wanted_notice_meta WHERE 1 = 0');
                self::$readyByConnection[$pdo] = ['ok' => true, 'at' => time()];

                return true;
            } catch (\Throwable) {
                self::$readyByConnection[$pdo] = ['ok' => false, 'at' => time()];

                return false;
            }
        } catch (\Throwable) {
            return false;
        }
    }

    public static function forgetReady(): void
    {
        self::$readyByConnection = null;
    }

    /**
     * @param list<int> $noticeIds
     * @return array<int, array<string, mixed>> un avis sans ligne : valeurs « vides » ({@see self::hydrate()})
     */
    public function forNotices(int $tenantId, array $noticeIds): array
    {
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $noticeIds), static fn (int $i): bool => $i > 0))), 0, 500);
        $out = [];
        foreach ($ids as $id) {
            $out[$id] = self::hydrate(null);
        }
        if ($tenantId < 1 || $ids === [] || !self::ready()) {
            return $out;
        }
        $params = ['t' => $tenantId];
        $marks = [];
        foreach ($ids as $k => $id) {
            $marks[] = ':n' . $k;
            $params['n' . $k] = $id;
        }
        try {
            foreach ($this->db->fetchAll('SELECT * FROM sse_wanted_notice_meta WHERE tenant_id = :t AND notice_id IN (' . implode(', ', $marks) . ')', $params) as $row) {
                $out[(int) $row['notice_id']] = self::hydrate($row);
            }
        } catch (\Throwable $e) {
            error_log('Avis de recherche : processus illisible : ' . $e->getMessage());
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public function forNotice(int $tenantId, int $noticeId): array
    {
        return $this->forNotices($tenantId, [$noticeId])[$noticeId] ?? self::hydrate(null);
    }

    /**
     * Crée ou complète la ligne d'un avis. Seules les clés données sont écrites.
     *
     * @param array<string, mixed> $fields service_code, service (tableau), classification, parent_notice_id, validation_state,
     *                                      submitted_by_user_id, closing_service_code
     */
    public function save(int $tenantId, int $noticeId, array $fields): bool
    {
        if ($tenantId < 1 || $noticeId < 1 || !self::ready()) {
            return false;
        }
        $cols = [];
        foreach (['service_code', 'classification', 'parent_notice_id', 'validation_state', 'submitted_by_user_id', 'closing_service_code', 'validated_by_user_id', 'validated_at', 'validation_reason'] as $k) {
            if (array_key_exists($k, $fields)) {
                $cols[$k] = $fields[$k];
            }
        }
        if (array_key_exists('service', $fields)) {
            $cols['service_json'] = is_array($fields['service']) ? json_encode($fields['service'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        }
        $now = gmdate('Y-m-d H:i:s');
        $exists = $this->db->fetchOne('SELECT notice_id FROM sse_wanted_notice_meta WHERE notice_id = :n AND tenant_id = :t', ['n' => $noticeId, 't' => $tenantId]) !== null;
        if ($exists) {
            $sets = ['updated_at = :now'];
            $params = ['now' => $now, 'n' => $noticeId, 't' => $tenantId];
            foreach ($cols as $k => $v) {
                $sets[] = $k . ' = :' . $k;
                $params[$k] = $v;
            }
            $this->db->execute('UPDATE sse_wanted_notice_meta SET ' . implode(', ', $sets) . ' WHERE notice_id = :n AND tenant_id = :t', $params);

            return true;
        }
        $cols += ['validation_state' => self::LIVE];
        $names = array_merge(['notice_id', 'tenant_id', 'updated_at'], array_keys($cols));
        $params = ['notice_id' => $noticeId, 'tenant_id' => $tenantId, 'updated_at' => $now] + $cols;
        $this->db->execute(
            'INSERT INTO sse_wanted_notice_meta (' . implode(', ', $names) . ') VALUES (' . implode(', ', array_map(static fn (string $n): string => ':' . $n, $names)) . ')',
            $params
        );

        return true;
    }

    /** Décision du responsable : « live » (diffusé) ou « rejected », avec le motif. */
    public function decide(int $tenantId, int $noticeId, string $state, ?int $userId, string $reason): bool
    {
        if (!in_array($state, [self::LIVE, self::REJECTED], true) || !self::ready()) {
            return false;
        }

        return $this->db->execute(
            "UPDATE sse_wanted_notice_meta SET validation_state = :s, validated_by_user_id = :u, validated_at = :now, validation_reason = :r, updated_at = :now2
             WHERE notice_id = :n AND tenant_id = :t AND validation_state = 'pending'",
            ['s' => $state, 'u' => $userId, 'now' => gmdate('Y-m-d H:i:s'), 'r' => mb_substr($reason, 0, 500), 'now2' => gmdate('Y-m-d H:i:s'), 'n' => $noticeId, 't' => $tenantId]
        ) > 0;
    }

    /** Ajoute une version à l'historique (prolongation). */
    public function addHistory(int $tenantId, int $noticeId, array $entry): bool
    {
        if (!self::ready()) {
            return false;
        }
        $meta = $this->forNotice($tenantId, $noticeId);
        $history = $meta['history'];
        $history[] = $entry;
        $history = array_slice($history, -50);
        if ($this->db->fetchOne('SELECT notice_id FROM sse_wanted_notice_meta WHERE notice_id = :n AND tenant_id = :t', ['n' => $noticeId, 't' => $tenantId]) === null) {
            $this->save($tenantId, $noticeId, []);
        }

        return $this->db->execute(
            'UPDATE sse_wanted_notice_meta SET history_json = :h, updated_at = :now WHERE notice_id = :n AND tenant_id = :t',
            ['h' => json_encode($history, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'now' => gmdate('Y-m-d H:i:s'), 'n' => $noticeId, 't' => $tenantId]
        ) > 0;
    }

    /** Nombre d'avis qui portent ce service (un service utilisé n'est jamais supprimé, seulement désactivé). */
    public function countUsing(int $tenantId, string $serviceCode): int
    {
        if (!self::ready()) {
            return 0;
        }
        try {
            $row = $this->db->fetchOne(
                'SELECT COUNT(*) AS c FROM sse_wanted_notice_meta WHERE tenant_id = :t AND (service_code = :c OR closing_service_code = :c2)',
                ['t' => $tenantId, 'c' => $serviceCode, 'c2' => $serviceCode]
            );

            return (int) ($row['c'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Nombre d'avis par service de la communauté. @return array<string, int> */
    public function countsByService(int $tenantId): array
    {
        $out = [];
        if (!self::ready()) {
            return $out;
        }
        try {
            foreach ($this->db->fetchAll('SELECT service_code, COUNT(*) AS c FROM sse_wanted_notice_meta WHERE tenant_id = :t AND service_code IS NOT NULL GROUP BY service_code', ['t' => $tenantId]) as $r) {
                $out[(string) $r['service_code']] = (int) $r['c'];
            }
        } catch (\Throwable) {
            return [];
        }

        return $out;
    }

    /** Dernier service utilisé par cet utilisateur pour poser un avis. */
    public function lastServiceCode(int $tenantId, int $userId): ?string
    {
        if (!self::ready() || $userId < 1) {
            return null;
        }
        try {
            $row = $this->db->fetchOne(
                'SELECT service_code FROM sse_wanted_notice_meta WHERE tenant_id = :t AND submitted_by_user_id = :u AND service_code IS NOT NULL ORDER BY notice_id DESC LIMIT 1',
                ['t' => $tenantId, 'u' => $userId]
            );

            return $row !== null ? (string) $row['service_code'] : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Identifiants des avis à valider (file « À trier »), les plus anciens d'abord.
     *
     * @return list<int>
     */
    public function pendingIds(int $tenantId, int $limit = 100): array
    {
        if (!self::ready()) {
            return [];
        }
        try {
            return array_map(
                static fn (array $r): int => (int) $r['notice_id'],
                $this->db->fetchAll("SELECT notice_id FROM sse_wanted_notice_meta WHERE tenant_id = :t AND validation_state = 'pending' ORDER BY notice_id ASC LIMIT " . max(1, min(300, $limit)), ['t' => $tenantId])
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param array<string, mixed>|null $row
     * @return array{service_code: ?string, service: array<string, mixed>, classification: ?string, parent_notice_id: ?int, state: string, submitted_by: ?int, validated_by: ?int, validated_at: ?string, validation_reason: ?string, closing_service_code: ?string, history: list<array<string, mixed>>}
     */
    public static function hydrate(?array $row): array
    {
        $service = json_decode((string) ($row['service_json'] ?? ''), true);
        // Instantané enregistré avant le retrait des mentions de jeu : le nom livré perd son ancien « (fictif) ».
        $service = is_array($service) ? \App\Support\SseFictionMentions::cleanServiceSnapshot($service) : $service;
        $history = json_decode((string) ($row['history_json'] ?? ''), true);
        $str = static fn (mixed $v): ?string => $v === null || $v === '' ? null : (string) $v;
        $state = (string) ($row['validation_state'] ?? self::LIVE);

        return [
            'service_code' => $str($row['service_code'] ?? null),
            'service' => is_array($service) ? $service : [],
            'classification' => $str($row['classification'] ?? null),
            'parent_notice_id' => ($row['parent_notice_id'] ?? null) !== null ? (int) $row['parent_notice_id'] : null,
            'state' => in_array($state, [self::LIVE, self::PENDING, self::REJECTED], true) ? $state : self::LIVE,
            'submitted_by' => ($row['submitted_by_user_id'] ?? null) !== null ? (int) $row['submitted_by_user_id'] : null,
            'validated_by' => ($row['validated_by_user_id'] ?? null) !== null ? (int) $row['validated_by_user_id'] : null,
            'validated_at' => $str($row['validated_at'] ?? null),
            'validation_reason' => $str($row['validation_reason'] ?? null),
            'closing_service_code' => $str($row['closing_service_code'] ?? null),
            'history' => is_array($history) ? array_values(array_filter($history, 'is_array')) : [],
        ];
    }
}
