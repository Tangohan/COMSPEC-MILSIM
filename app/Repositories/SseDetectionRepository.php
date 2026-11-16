<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use PDO;

/**
 * Cerbère-Lecture en base (étape sse_detection_rules) :
 *   - sse_detection_rule_settings : réglages d'une règle par communauté (active, poids, termes, exclusions, contexte requis) ;
 *   - sse_detection_decisions     : ce qu'une personne a fait d'une proposition (appliquée, ignorée, mauvaise détection) et
 *                                   ce que la re-détection a retiré (withdrawn), une ligne par remontée, cible et valeur.
 * Chaque écriture est aussi inscrite au journal SSE (sse_audit_log, actions « cerbere.lecture.* »).
 *
 * Sans l'étape : ready() est faux, aucun réglage n'est lu (le lexique livré s'applique), les propositions restent calculées et
 * applicables, mais « Ignorer » et « Mauvaise détection » ne peuvent pas être gardés.
 */
class SseDetectionRepository
{
    use LazyDatabaseConnection;

    public const DECISIONS = ['applied', 'ignored', 'rejected', 'withdrawn'];

    /** Décisions qui retirent une proposition de la fiche (une proposition retirée par la re-détection peut revenir). */
    public const HIDING = ['applied', 'ignored', 'rejected'];

    /** @var array<int, bool> */
    private static array $ready = [];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public static function forgetReady(): void
    {
        self::$ready = [];
    }

    public function ready(): bool
    {
        try {
            $pdo = $this->pdo();
        } catch (\Throwable) {
            return false;
        }
        $key = spl_object_id($pdo);
        if (!isset(self::$ready[$key])) {
            $schema = new \App\Support\Migrations\SchemaInspector($pdo);
            try {
                self::$ready[$key] = $schema->tableExists('sse_detection_rule_settings') && $schema->tableExists('sse_detection_decisions');
            } catch (\Throwable) {
                self::$ready[$key] = false;
            }
        }

        return self::$ready[$key];
    }

    private function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    /**
     * Réglages de la communauté, par règle.
     *
     * @return array<string, array{enabled: bool, weight: int|null, terms: array<string, int|null>|null, exclusions: list<string>|null, requires: list<string>|null, updated_at: string, updated_by: string}>
     */
    public function settings(int $tenantId): array
    {
        if ($tenantId < 1 || !$this->ready()) {
            return [];
        }
        $out = [];
        try {
            $st = $this->pdo()->prepare('SELECT * FROM sse_detection_rule_settings WHERE tenant_id = ?');
            $st->execute([$tenantId]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $json = static function (mixed $v): ?array {
                    $d = is_string($v) && $v !== '' ? json_decode($v, true) : null;

                    return is_array($d) ? $d : null;
                };
                $out[(string) $r['rule_id']] = [
                    'enabled' => (int) $r['enabled'] === 1,
                    'weight' => $r['weight'] !== null ? (int) $r['weight'] : null,
                    'terms' => $json($r['terms_json'] ?? null),
                    'exclusions' => $json($r['exclusions_json'] ?? null),
                    'requires' => $json($r['requires_json'] ?? null),
                    'updated_at' => (string) ($r['updated_at'] ?? ''),
                    'updated_by' => (string) ($r['updated_by_label'] ?? ''),
                ];
            }
        } catch (\Throwable) {
            return [];
        }

        return $out;
    }

    /**
     * Enregistre le réglage d'une règle (remplace le précédent) et l'inscrit au journal avec l'avant et l'après.
     *
     * @param array{enabled: bool, weight: int|null, terms: array<string, int|null>|null, exclusions: list<string>|null, requires: list<string>|null} $data
     * @param array{id: int, label: string} $actor
     * @param array<string, mixed> $before
     */
    public function saveRule(int $tenantId, string $ruleId, array $data, array $actor, array $before, string $reason = ''): bool
    {
        if ($tenantId < 1 || !$this->ready()) {
            return false;
        }
        $enc = static fn (?array $v): ?string => $v === null ? null : json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $params = [
            (int) $data['enabled'], $data['weight'], $enc($data['terms']), $enc($data['exclusions']), $enc($data['requires']),
            $actor['id'] > 0 ? $actor['id'] : null, mb_substr($actor['label'], 0, 160), $this->now(),
        ];
        $pdo = $this->pdo();
        $st = $pdo->prepare('SELECT id FROM sse_detection_rule_settings WHERE tenant_id = ? AND rule_id = ?');
        $st->execute([$tenantId, $ruleId]);
        $id = (int) $st->fetchColumn();
        if ($id > 0) {
            $pdo->prepare('UPDATE sse_detection_rule_settings SET enabled = ?, weight = ?, terms_json = ?, exclusions_json = ?, requires_json = ?, updated_by = ?, updated_by_label = ?, updated_at = ? WHERE id = ?')
                ->execute([...$params, $id]);
        } else {
            $pdo->prepare('INSERT INTO sse_detection_rule_settings (enabled, weight, terms_json, exclusions_json, requires_json, updated_by, updated_by_label, updated_at, tenant_id, rule_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([...$params, $tenantId, $ruleId]);
        }
        $this->audit($tenantId, $actor, 'cerbere.lecture.rule', 'sse_detection_rule', 0, $ruleId, $reason, $before, $data);

        return true;
    }

    /**
     * Rétablit le lexique livré : retire tous les réglages de la communauté (journalisé avec ce qui est retiré).
     *
     * @param array{id: int, label: string} $actor
     */
    public function resetAll(int $tenantId, array $actor): int
    {
        if ($tenantId < 1 || !$this->ready()) {
            return 0;
        }
        $before = $this->settings($tenantId);
        if ($before === []) {
            return 0;
        }
        $this->pdo()->prepare('DELETE FROM sse_detection_rule_settings WHERE tenant_id = ?')->execute([$tenantId]);
        $this->audit($tenantId, $actor, 'cerbere.lecture.reset', 'sse_detection_rule', 0, '*', 'Lexique livré rétabli', $before, []);

        return count($before);
    }

    /**
     * Décisions déjà prises sur une remontée : « cible|valeur » => décision.
     *
     * @return array<string, array{decision: string, reason: string, at: string, by: string}>
     */
    public function decisions(int $tenantId, string $source, int $sourceId): array
    {
        if ($tenantId < 1 || !$this->ready()) {
            return [];
        }
        try {
            $st = $this->pdo()->prepare('SELECT target, value, decision, reason, decided_at, actor_label FROM sse_detection_decisions WHERE tenant_id = ? AND source = ? AND source_id = ?');
            $st->execute([$tenantId, $source, $sourceId]);
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $out[$r['target'] . '|' . $r['value']] = ['decision' => (string) $r['decision'], 'reason' => (string) ($r['reason'] ?? ''), 'at' => (string) $r['decided_at'], 'by' => (string) ($r['actor_label'] ?? '')];
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Note la décision d'une personne (ou de la re-détection). Une proposition déjà appliquée n'est jamais modifiée.
     *
     * @param list<string> $ruleIds
     * @param array{id: int, label: string} $actor
     * @return bool vrai si la décision est gardée
     */
    public function recordDecision(int $tenantId, string $source, int $sourceId, string $target, string $value, array $ruleIds, int $confidence, string $decision, string $reason, array $actor, string $version): bool
    {
        if ($tenantId < 1 || !in_array($decision, self::DECISIONS, true) || !$this->ready()) {
            return false;
        }
        $pdo = $this->pdo();
        $st = $pdo->prepare('SELECT id, decision FROM sse_detection_decisions WHERE tenant_id = ? AND source = ? AND source_id = ? AND target = ? AND value = ?');
        $st->execute([$tenantId, $source, $sourceId, $target, $value]);
        $existing = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($existing !== null && (string) $existing['decision'] === 'applied') {
            return false;
        }
        $rules = mb_substr(implode(',', $ruleIds), 0, 190);
        $reason = mb_substr(trim($reason), 0, 200);
        $params = [$rules, max(0, min(100, $confidence)), $decision, $reason !== '' ? $reason : null, mb_substr($version, 0, 24), $actor['id'] > 0 ? $actor['id'] : null, mb_substr($actor['label'], 0, 160), $this->now()];
        if ($existing !== null) {
            $pdo->prepare('UPDATE sse_detection_decisions SET rule_ids = ?, confidence = ?, decision = ?, reason = ?, lexicon_version = ?, actor_user_id = ?, actor_label = ?, decided_at = ? WHERE id = ?')
                ->execute([...$params, (int) $existing['id']]);
        } else {
            $pdo->prepare('INSERT INTO sse_detection_decisions (rule_ids, confidence, decision, reason, lexicon_version, actor_user_id, actor_label, decided_at, tenant_id, source, source_id, target, value) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([...$params, $tenantId, $source, $sourceId, $target, $value]);
        }
        $this->audit($tenantId, $actor, 'cerbere.lecture.' . $decision, 'intel_intake_' . $source, $sourceId, $target . ':' . $value, $reason,
            $existing !== null ? ['decision' => (string) $existing['decision']] : [], ['rules' => $ruleIds, 'confidence' => $confidence, 'target' => $target, 'value' => $value, 'version' => $version]);

        return true;
    }

    /**
     * Décisions des 30 derniers jours, comptées par règle.
     *
     * @return array<string, array{applied: int, ignored: int, rejected: int, withdrawn: int, reasons: list<string>}>
     */
    public function ruleStats(int $tenantId, int $days = 30): array
    {
        if ($tenantId < 1 || !$this->ready()) {
            return [];
        }
        $out = [];
        try {
            $st = $this->pdo()->prepare('SELECT rule_ids, decision, reason FROM sse_detection_decisions WHERE tenant_id = ? AND decided_at >= ? ORDER BY id DESC LIMIT 5000');
            $st->execute([$tenantId, gmdate('Y-m-d H:i:s', time() - $days * 86400)]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                foreach (array_filter(explode(',', (string) $r['rule_ids'])) as $rule) {
                    $out[$rule] ??= ['applied' => 0, 'ignored' => 0, 'rejected' => 0, 'withdrawn' => 0, 'reasons' => []];
                    $d = (string) $r['decision'];
                    if (isset($out[$rule][$d])) {
                        $out[$rule][$d]++;
                    }
                    if ($d === 'rejected' && ($why = trim((string) ($r['reason'] ?? ''))) !== '' && count($out[$rule]['reasons']) < 5) {
                        $out[$rule]['reasons'][] = $why;
                    }
                }
            }
        } catch (\Throwable) {
            return [];
        }

        return $out;
    }

    /**
     * Propositions retirées par la re-détection (pour l'outil, qui ne les retire pas deux fois).
     *
     * @return array<string, true> « source|id|cible|valeur »
     */
    public function decidedKeys(int $tenantId): array
    {
        if ($tenantId < 1 || !$this->ready()) {
            return [];
        }
        $st = $this->pdo()->prepare('SELECT source, source_id, target, value FROM sse_detection_decisions WHERE tenant_id = ?');
        $st->execute([$tenantId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $out[$r['source'] . '|' . $r['source_id'] . '|' . $r['target'] . '|' . $r['value']] = true;
        }

        return $out;
    }

    /**
     * Derniers changements du lexique de la communauté (journal SSE).
     *
     * @return list<array{at: string, actor: string, action: string, rule: string, reason: string, before: array<string, mixed>, after: array<string, mixed>}>
     */
    public function history(int $tenantId, int $limit = 40): array
    {
        try {
            $st = $this->pdo()->prepare("SELECT created_at, actor_label, action, object_uuid, reason, before_json, after_json FROM sse_audit_log WHERE tenant_id = ? AND action IN ('cerbere.lecture.rule', 'cerbere.lecture.reset') ORDER BY id DESC LIMIT " . max(1, min(200, $limit)));
            $st->execute([$tenantId]);
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $dec = static fn (mixed $v): array => is_string($v) && is_array($d = json_decode($v, true)) ? $d : [];
                $out[] = ['at' => (string) $r['created_at'], 'actor' => (string) ($r['actor_label'] ?? ''), 'action' => (string) $r['action'], 'rule' => (string) ($r['object_uuid'] ?? ''), 'reason' => (string) ($r['reason'] ?? ''), 'before' => $dec($r['before_json'] ?? null), 'after' => $dec($r['after_json'] ?? null)];
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Journal SSE (append-only). Sans la table du journal, rien n'est écrit et rien n'échoue.
     *
     * @param array{id: int, label: string} $actor
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    public function audit(int $tenantId, array $actor, string $action, string $objectType, int $objectId, string $uuid, string $reason, array $before, array $after): void
    {
        $enc = static fn (array $v): ?string => $v === [] ? null : (json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null);
        try {
            $this->pdo()->prepare('INSERT INTO sse_audit_log (tenant_id, actor_user_id, actor_label, action, object_type, object_id, object_uuid, reason, before_json, after_json, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$tenantId, $actor['id'] > 0 ? $actor['id'] : null, mb_substr($actor['label'] !== '' ? $actor['label'] : 'Cerbère-Lecture', 0, 160), $action, $objectType, $objectId > 0 ? $objectId : null, $uuid !== '' ? $uuid : null, $reason !== '' ? mb_substr($reason, 0, 500) : null, $enc($before), $enc($after), $this->now()]);
        } catch (\Throwable) {
        }
    }
}
