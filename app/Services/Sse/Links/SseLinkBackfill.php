<?php

declare(strict_types=1);

namespace App\Services\Sse\Links;

use App\Support\SseCerbere;
use App\Support\SseLinkCatalog as C;
use PDO;

/**
 * Rétro-remplissage PRUDENT et idempotent des liens de provenance : reconstruit dans sse_entity_links les liens que les colonnes
 * existantes portent déjà (identité → personne, papier → identité, contrôle → papier, contrôle → identité, dossier de la fiche...),
 * avec la règle « cerbere.backfill.column », la date de l'élément d'origine et le motif de la colonne quand elle en a un.
 *
 * Garanties : ne modifie, ne supprime ni ne remplace aucune autre donnée (une seule table reçoit des lignes) ; un couple déjà lié
 * (dans un sens ou dans l'autre) n'est jamais doublé, donc relancer ne crée rien ; par défaut SIMULATION (rien n'est écrit) ;
 * avec $apply, par lots bornés, chaque lot dans une transaction ; reprenable avec le curseur « communauté:définition:ligne »
 * rendu par chaque appel. Commande : tools/sse-backfill-links.php.
 */
final class SseLinkBackfill
{
    public const RULE = 'cerbere.backfill.column';

    public const BATCH_MAX = 2000;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function ready(): bool
    {
        try {
            $this->pdo->query('SELECT 1 FROM sse_entity_links WHERE 1 = 0');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array{ready: bool, apply: bool, scanned: int, existing: int, missing: int, created: int, errors: int, byDef: array<string, array{scanned: int, missing: int, created: int}>, cursor: ?string, done: bool, messages: list<string>}
     */
    public function run(bool $apply, ?int $tenantId = null, int $batch = 500, ?string $resume = null, ?int $maxBatches = null): array
    {
        $batch = max(1, min(self::BATCH_MAX, $batch));
        $report = ['ready' => $this->ready(), 'apply' => $apply, 'scanned' => 0, 'existing' => 0, 'missing' => 0, 'created' => 0, 'errors' => 0, 'byDef' => [], 'cursor' => null, 'done' => false, 'messages' => []];
        if (!$report['ready']) {
            $report['messages'][] = 'La table sse_entity_links n’existe pas : passez d’abord l’étape de migration « sse_entity_links ».';

            return $report;
        }
        $tenants = $tenantId !== null ? [$tenantId] : array_map('intval', $this->pdo->query('SELECT id FROM tenants ORDER BY id')->fetchAll(PDO::FETCH_COLUMN) ?: []);
        [$rt, $rd, $rr] = $this->parseCursor($resume);
        $batches = 0;
        foreach ($tenants as $t) {
            if ($t < $rt) {
                continue;
            }
            foreach (SseLinkEdges::DEFS as $d => $def) {
                if ($t === $rt && $d < $rd) {
                    continue;
                }
                $last = ($t === $rt && $d === $rd) ? $rr : 0;
                while (true) {
                    if ($maxBatches !== null && $batches >= $maxBatches) {
                        $report['cursor'] = $t . ':' . $d . ':' . $last;

                        return $report;
                    }
                    try {
                        $rows = $this->page($t, $def, $last, $batch);
                    } catch (\Throwable) {
                        // Table ou colonne absente (ancienne base) : cette définition est ignorée.
                        break;
                    }
                    if ($rows === []) {
                        break;
                    }
                    ++$batches;
                    $key = $def['table'] . ' → ' . $def['a'][0] . '/' . $def['b'][0] . ' (' . $def['relation'] . ')';
                    $report['byDef'][$key] ??= ['scanned' => 0, 'missing' => 0, 'created' => 0];
                    $todo = $this->missing($t, $def, $rows, $report);
                    $report['scanned'] += count($rows);
                    $report['byDef'][$key]['scanned'] += count($rows);
                    $report['missing'] += count($todo);
                    $report['byDef'][$key]['missing'] += count($todo);
                    if ($apply && $todo !== []) {
                        $n = $this->insert($t, $def, $todo, $report);
                        $report['created'] += $n;
                        $report['byDef'][$key]['created'] += $n;
                    }
                    $last = (int) $rows[count($rows) - 1]['_row'];
                    $report['cursor'] = $t . ':' . $d . ':' . $last;
                    if (count($rows) < $batch) {
                        break;
                    }
                }
            }
        }
        $report['done'] = true;
        $report['cursor'] = null;

        return $report;
    }

    /** @return array{0: int, 1: int, 2: int} */
    private function parseCursor(?string $resume): array
    {
        if ($resume !== null && preg_match('/^(\d+):(\d+):(\d+)$/', $resume, $m) === 1) {
            return [(int) $m[1], (int) $m[2], (int) $m[3]];
        }

        return [0, 0, 0];
    }

    /**
     * @param array<string, mixed> $def
     * @return list<array<string, mixed>>
     */
    private function page(int $tenant, array $def, int $after, int $limit): array
    {
        $cols = ['id AS _row', $def['a'][1] . ' AS a_id', $def['b'][1] . ' AS b_id', $def['date'] . ' AS at'];
        if (isset($def['actor'])) {
            $cols[] = $def['actor'] . ' AS actor_id';
        }
        if (isset($def['reasonCol'])) {
            $cols[] = $def['reasonCol'] . ' AS reason_text';
        }
        $sql = 'SELECT ' . implode(', ', $cols) . ' FROM ' . $def['table'] . ' WHERE tenant_id = :t AND id > :after' . (isset($def['where']) ? ' AND ' . $def['where'] : '') . ' ORDER BY id ASC LIMIT ' . $limit;
        $st = $this->pdo->prepare($sql);
        $st->execute(['t' => $tenant, 'after' => $after]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Les lignes dont le couple n'est pas encore lié (dans un sens ou dans l'autre).
     *
     * @param array<string, mixed> $def
     * @param list<array<string, mixed>> $rows
     * @param array<string, mixed> $report
     * @return list<array<string, mixed>>
     */
    private function missing(int $tenant, array $def, array $rows, array &$report): array
    {
        [$aType] = $def['a'];
        [$bType] = $def['b'];
        $aIds = array_values(array_unique(array_map(static fn (array $r): string => (string) (int) $r['a_id'], $rows)));
        $known = [];
        // Paramètres tous distincts : MySQL refuse un même nom utilisé deux fois quand l'émulation des requêtes préparées est coupée.
        $params = ['t' => $tenant, 'at' => $aType, 'bt' => $bType, 'at2' => $aType, 'bt2' => $bType];
        $marks = [];
        $marks2 = [];
        foreach ($aIds as $i => $id) {
            $params['a' . $i] = $id;
            $params['c' . $i] = $id;
            $marks[] = ':a' . $i;
            $marks2[] = ':c' . $i;
        }
        // Dans les deux sens, pour les éléments de la page seulement (une requête par page).
        $st = $this->pdo->prepare('SELECT source_type, source_id, target_type, target_id FROM sse_entity_links WHERE tenant_id = :t AND ((source_type = :at AND source_id IN (' . implode(',', $marks) . ') AND target_type = :bt) OR (target_type = :at2 AND target_id IN (' . implode(',', $marks2) . ') AND source_type = :bt2))');
        $st->execute($params);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $l) {
            $known[self::pairKey((string) $l['source_type'], (string) $l['source_id'], (string) $l['target_type'], (string) $l['target_id'])] = true;
        }
        $todo = [];
        foreach ($rows as $r) {
            $a = (string) (int) $r['a_id'];
            $b = (string) (int) $r['b_id'];
            if ($a === '0' || $b === '0' || ($aType === $bType && $a === $b)) {
                continue;
            }
            $k = self::pairKey($aType, $a, $bType, $b);
            if (isset($known[$k])) {
                ++$report['existing'];
                continue;
            }
            $known[$k] = true;
            $todo[] = ['a' => $a, 'b' => $b] + $r;
        }

        return $todo;
    }

    private static function pairKey(string $t1, string $i1, string $t2, string $i2): string
    {
        $x = $t1 . ':' . $i1;
        $y = $t2 . ':' . $i2;

        return $x < $y ? $x . '|' . $y : $y . '|' . $x;
    }

    /**
     * @param array<string, mixed> $def
     * @param list<array<string, mixed>> $todo
     * @param array<string, mixed> $report
     */
    private function insert(int $tenant, array $def, array $todo, array &$report): int
    {
        $n = 0;
        $this->pdo->beginTransaction();
        try {
            $st = $this->pdo->prepare('INSERT INTO sse_entity_links (tenant_id, source_type, source_id, target_type, target_id, relation, reason, rule_code, created_by, created_by_user_id, created_at)
                VALUES (:t, :st, :si, :tt, :ti, :rel, :reason, :rule, \'system\', :uid, :at)');
            foreach ($todo as $r) {
                $note = trim((string) ($r['reason_text'] ?? ''));
                $reason = 'Lien reconstruit depuis les données existantes. ' . (string) $def['note'] . ($note !== '' ? ' Motif noté à l’époque : ' . $note : '');
                $at = (string) ($r['at'] ?? '');
                $when = preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}/', $at) === 1 ? str_replace('T', ' ', substr($at, 0, 19)) : null;
                $st->execute([
                    't' => $tenant, 'st' => $def['a'][0], 'si' => $r['a'], 'tt' => $def['b'][0], 'ti' => $r['b'], 'rel' => isset(C::RELATIONS[$def['relation']]) ? $def['relation'] : 'related',
                    'reason' => mb_substr($reason, 0, C::MANUAL_REASON_MAX), 'rule' => self::RULE,
                    'uid' => isset($r['actor_id']) && (int) $r['actor_id'] > 0 ? (int) $r['actor_id'] : null,
                    'at' => $when ?? gmdate('Y-m-d H:i:s'),
                ]);
                ++$n;
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            ++$report['errors'];
            $report['messages'][] = 'Lot annulé (' . $def['table'] . ') : ' . $e->getMessage();

            return 0;
        }

        return $n;
    }

    /** Explication d'une règle de rétro-remplissage (affichée par la commande). */
    public static function ruleSentence(): string
    {
        return SseCerbere::reasonOf(self::RULE);
    }
}
