<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Support\SseSignalIdentity;
use App\Support\SseSignalSchema;
use PDO;

/**
 * Rattrapage des signaux du moteur analytique créés en double avant la déduplication (un nouveau signal chaque nuit pour la même
 * lacune du même dossier). Moteur de tools/sse-dedupe-signals.php.
 *
 * - Un groupe = les signaux ouverts ou en veille de même identité (dossier, type, règle, variante : SseSignalIdentity). Le plus ancien
 *   est la source : il garde son numéro et sa date de création, reçoit le contenu le plus récent (titre, détail, gravité, état) et la
 *   somme des observations ; les autres passent au statut « merged » (merged_into, date et auteur quand les colonnes existent).
 * - Rien n'est supprimé : une ligne fusionnée reste en base, avec son contenu.
 * - Un groupe est fusionné dans UNE transaction : une erreur l'annule en entier, les autres groupes continuent, le code de retour dit
 *   qu'il y a eu un échec, rien n'est perdu.
 * - Idempotent et reprenable : un signal fusionné n'est plus un doublon ; relancer ne traite que ce qui reste ($limit groupes par passage).
 * - Simulation par défaut : $apply = false ne fait aucune écriture.
 */
final class SseSignalDeduper
{
    public const BATCH = 5000;

    /** @param (\Closure(): string)|null $clock « Y-m-d H:i:s » en UTC */
    public function __construct(private PDO $pdo, private ?\Closure $clock = null)
    {
    }

    private function now(): string
    {
        return $this->clock !== null ? (string) ($this->clock)() : gmdate('Y-m-d H:i:s');
    }

    /**
     * @return array{applied: bool, tenants: int, signals: int, groups: int, duplicates: int, merged: int, failed: int, remaining: int, by_type: array<string, int>, samples: list<array<string, mixed>>, errors: list<string>, columns: array<string, bool>}
     */
    public function run(bool $apply = false, ?int $tenantId = null, ?int $limit = null): array
    {
        $report = ['applied' => $apply, 'tenants' => 0, 'signals' => 0, 'groups' => 0, 'duplicates' => 0, 'merged' => 0, 'failed' => 0, 'remaining' => 0, 'by_type' => [], 'samples' => [], 'errors' => [], 'columns' => SseSignalSchema::columns($this->pdo)];
        $groups = [];
        $tenants = [];
        $afterId = 0;
        while (true) {
            $st = $this->pdo->prepare(
                "SELECT * FROM sse_engine_signals WHERE status IN ('open', 'dormant') AND id > :after" . ($tenantId !== null ? ' AND tenant_id = :t' : '') . ' ORDER BY id ASC LIMIT ' . self::BATCH
            );
            $st->execute($tenantId !== null ? ['after' => $afterId, 't' => $tenantId] : ['after' => $afterId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            if ($rows === []) {
                break;
            }
            foreach ($rows as $row) {
                $afterId = (int) $row['id'];
                $report['signals']++;
                $tenants[(int) $row['tenant_id']] = true;
                $groups[(int) $row['tenant_id'] . '#' . SseSignalIdentity::keyFor($row)][] = $row;
            }
        }
        $report['tenants'] = count($tenants);
        $duplicated = array_values(array_filter($groups, static fn (array $g): bool => count($g) > 1));
        $report['groups'] = count($duplicated);
        foreach ($duplicated as $g) {
            $report['duplicates'] += count($g) - 1;
            $type = (string) $g[0]['signal_type'] . ':' . SseSignalIdentity::shortTitle((string) $g[0]['signal_type'], (string) $g[0]['title']);
            $report['by_type'][$type] = ($report['by_type'][$type] ?? 0) + count($g) - 1;
        }
        arsort($report['by_type']);
        $todo = $limit !== null ? array_slice($duplicated, 0, $limit) : $duplicated;
        foreach (array_slice($todo, 0, 5) as $g) {
            $report['samples'][] = ['tenant' => (int) $g[0]['tenant_id'], 'case_id' => $g[0]['case_id'] !== null ? (int) $g[0]['case_id'] : null, 'type' => (string) $g[0]['signal_type'], 'title' => (string) $g[0]['title'], 'source_id' => (int) $g[0]['id'], 'duplicates' => count($g) - 1];
        }
        if ($apply) {
            foreach ($todo as $group) {
                try {
                    $report['merged'] += $this->mergeGroup($group, $report['columns']);
                } catch (\Throwable $e) {
                    $report['failed']++;
                    $report['errors'][] = 'Groupe de ' . (int) $group[0]['id'] . ' : ' . $e->getMessage();
                }
            }
        }
        $report['remaining'] = max(0, $report['groups'] - ($apply ? count($todo) - $report['failed'] : 0));

        return $report;
    }

    /**
     * Fusionne un groupe dans une transaction : retourne le nombre de lignes fusionnées.
     *
     * @param list<array<string, mixed>> $group triées par numéro croissant
     * @param array<string, bool> $cols
     */
    private function mergeGroup(array $group, array $cols): int
    {
        $source = $group[0];
        $latest = $group[count($group) - 1];
        $now = $this->now();
        $this->pdo->beginTransaction();
        try {
            $set = ['title = :title', 'detail = :detail', 'severity = :sev', 'updated_at = :now'];
            $params = ['title' => $latest['title'], 'detail' => $latest['detail'], 'sev' => $latest['severity'], 'now' => $now, 'id' => $source['id']];
            if ((string) $latest['status'] === 'open' && (string) $source['status'] !== 'open') {
                $set[] = "status = 'open'";
            }
            if ($cols['dedup_key']) {
                $set[] = 'dedup_key = :dk';
                $params['dk'] = SseSignalIdentity::keyFor($source);
            }
            if ($cols['last_seen_at']) {
                $set[] = 'last_seen_at = :ls';
                $params['ls'] = (string) ($latest['updated_at'] ?? $now);
            }
            if ($cols['seen_count']) {
                $set[] = 'seen_count = :sc';
                $params['sc'] = count($group) + array_sum(array_map(static fn (array $r): int => max(0, (int) ($r['seen_count'] ?? 1) - 1), $group));
            }
            if ($cols['changed_at'] && ((string) $source['title'] !== (string) $latest['title'] || (string) ($source['detail'] ?? '') !== (string) ($latest['detail'] ?? ''))) {
                $set[] = 'changed_at = :ca';
                $params['ca'] = (string) ($latest['updated_at'] ?? $now);
            }
            $this->pdo->prepare('UPDATE sse_engine_signals SET ' . implode(', ', $set) . ' WHERE id = :id')->execute($params);

            $merged = 0;
            foreach (array_slice($group, 1) as $dup) {
                $set = ["status = 'merged'", 'updated_at = :now'];
                $params = ['now' => $now, 'id' => $dup['id']];
                if ($cols['merged_into']) {
                    $set[] = 'merged_into = :mi';
                    $params['mi'] = $source['id'];
                }
                if ($cols['resolved_at']) {
                    $set[] = 'resolved_at = :ra';
                    $params['ra'] = $now;
                }
                if ($cols['resolved_by']) {
                    $set[] = "resolved_by = 'cerbere-dedoublonnage'";
                }
                $merged += $this->pdo->prepare('UPDATE sse_engine_signals SET ' . implode(', ', $set) . " WHERE id = :id AND status IN ('open', 'dormant')")->execute($params) ? 1 : 0;
            }
            $this->pdo->commit();

            return $merged;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
