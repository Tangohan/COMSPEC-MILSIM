<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Database;

/**
 * Moteur de tools/sse-sync-member-identities.php : parcourt les membres qui ont une identité officielle, liste les écarts avec
 * leur état civil et, avec $apply, les aligne un par un (une transaction par membre : un échec n'en défait ni n'en perd aucun
 * autre). Simulation par défaut. Idempotent : un membre aligné n'apparaît plus. Ne touche jamais aux identités de couverture,
 * falsifiées, générées ni aux sujets non membres (SseMemberCivilIdentityService), ne supprime rien.
 *
 * Chaque identité alignée est ajoutée au journal (une ligne JSON par membre) avec ses anciennes valeurs, en plus de
 * l'historique restaurable de la base.
 */
final class SseMemberCivilIdentityBatch
{
    private Database $db;
    private SseMemberCivilIdentityService $service;

    public function __construct(?Database $db = null, ?SseMemberCivilIdentityService $service = null, private ?string $logFile = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->service = $service ?? new SseMemberCivilIdentityService($this->db);
    }

    /**
     * @param ?int $tenant une seule communauté (toutes sinon)
     * @param ?int $limit nombre maximal de membres écartés traités (listés en simulation, alignés avec $apply)
     * @param bool $createMissing créer aussi l'identité officielle des membres qui n'en ont pas (jamais sans cette option)
     * @return array{
     *     apply: bool,
     *     rows: list<array<string, mixed>>,
     *     notes: list<string>,
     *     totals: array{scanned: int, aligned: int, misaligned: int, applied: int, created: int, errors: int, override: int, no_civil_state: int, anonymized: int, duplicates: int, missing: int, official_on_other: int}
     * }
     */
    public function run(bool $apply, ?int $tenant = null, ?int $limit = null, bool $createMissing = false): array
    {
        $totals = ['scanned' => 0, 'aligned' => 0, 'misaligned' => 0, 'applied' => 0, 'created' => 0, 'errors' => 0, 'override' => 0, 'no_civil_state' => 0, 'anonymized' => 0, 'duplicates' => 0, 'missing' => 0, 'official_on_other' => 0];
        $rows = [];
        $notes = [];
        $handled = 0;
        foreach ($this->tenants($tenant) as $tenantId) {
            $after = 0;
            while (true) {
                $ids = $this->candidates($tenantId, $after, $createMissing);
                if ($ids === []) {
                    break;
                }
                foreach ($ids as $userId) {
                    $after = max($after, $userId);
                    $totals['scanned']++;
                    $info = $this->service->inspect($tenantId, $userId);
                    $state = (string) $info['state'];
                    if ($info['duplicates'] !== []) {
                        $totals['duplicates']++;
                        $notes[] = sprintf('communauté %d, membre %d : plusieurs identités civiles (%s), seule la plus ancienne est alignée, les autres sont à fusionner à la main', $tenantId, $userId, implode(', ', array_merge([(int) ($info['identity']['id'] ?? 0)], $info['duplicates'])));
                    }
                    if (in_array($state, [SseMemberCivilIdentityService::ALIGNED, SseMemberCivilIdentityService::OVERRIDE, SseMemberCivilIdentityService::NO_CIVIL_STATE, SseMemberCivilIdentityService::ANONYMIZED], true)) {
                        $key = $state === SseMemberCivilIdentityService::ALIGNED ? 'aligned' : $state;
                        $totals[$key]++;

                        continue;
                    }
                    if ($state === SseMemberCivilIdentityService::UNKNOWN) {
                        continue;
                    }
                    $isMissing = $state === SseMemberCivilIdentityService::MISSING;
                    if ($isMissing) {
                        $totals['missing']++;
                        if (!$createMissing) {
                            continue;
                        }
                    } else {
                        $totals['misaligned']++;
                    }
                    if ($limit !== null && $handled >= $limit) {
                        continue;
                    }
                    $handled++;
                    $row = [
                        'tenant_id' => $tenantId, 'user_id' => $userId, 'state' => $state, 'identity_id' => (int) ($info['identity']['id'] ?? 0),
                        'registry' => $isMissing ? '' : trim((string) $info['identity']['first_name'] . ' ' . mb_strtoupper((string) $info['identity']['last_name'])),
                        'profile' => trim((string) ($info['target']['first_name'] ?? '') . ' ' . mb_strtoupper((string) ($info['target']['last_name'] ?? ''))),
                        'changes' => $info['changes'],
                        'documents' => array_map(static fn (array $d): string => $d['ref'] . ' ' . $d['country'] . ' ' . $d['number'], $info['documents']),
                        'result' => $apply ? 'pending' : 'dry-run',
                    ];
                    if ($apply) {
                        $res = $this->service->align($tenantId, $userId, null, 'cli', null, true);
                        $row['result'] = $res['ok'] ? 'applied' : 'error';
                        $row['message'] = $res['message'];
                        if ($res['ok']) {
                            $totals[$isMissing ? 'created' : 'applied']++;
                            $row['identity_id'] = (int) ($res['identity_id'] ?? $row['identity_id']);
                        } else {
                            $totals['errors']++;
                        }
                        $this->journal($row);
                    }
                    $rows[] = $row;
                }
            }
            $totals['official_on_other'] += $this->officialOnOtherKinds($tenantId, $notes);
        }

        return ['apply' => $apply, 'rows' => $rows, 'notes' => $notes, 'totals' => $totals];
    }

    /** @return list<int> */
    private function tenants(?int $tenant): array
    {
        if ($tenant !== null) {
            return [$tenant];
        }

        return array_map(static fn (array $r): int => (int) $r['id'], $this->db->fetchAll('SELECT id FROM tenants ORDER BY id ASC'));
    }

    /** @return list<int> membres d'une communauté, par lots, après $after */
    private function candidates(int $tenantId, int $after, bool $withMissing): array
    {
        $sql = $withMissing
            ? "SELECT u.id AS uid FROM users u WHERE u.tenant_id = :t AND u.id > :a ORDER BY u.id ASC LIMIT 200"
            : "SELECT DISTINCT i.user_id AS uid FROM sse_identities i WHERE i.tenant_id = :t AND i.kind = 'civil' AND i.user_id IS NOT NULL AND i.user_id > :a ORDER BY i.user_id ASC LIMIT 200";
        try {
            $rows = $this->db->fetchAll($sql, ['t' => $tenantId, 'a' => $after]);
        } catch (\Throwable) {
            return [];
        }

        return array_map(static fn (array $r): int => (int) $r['uid'], $rows);
    }

    /**
     * Membres dont une identité d'emprunt, générée ou falsifiée porte des papiers « délivrés officiellement » : signalé, jamais modifié.
     *
     * @param list<string> $notes
     */
    private function officialOnOtherKinds(int $tenantId, array &$notes): int
    {
        try {
            $rows = $this->db->fetchAll(
                "SELECT i.user_id AS uid, i.id AS iid, i.kind AS kind, COUNT(d.id) AS n
                 FROM sse_identities i JOIN sse_id_documents d ON d.identity_id = i.id AND d.tenant_id = i.tenant_id AND d.st_origin = 'official'
                 WHERE i.tenant_id = :t AND i.user_id IS NOT NULL AND i.user_id > 0 AND i.kind <> 'civil' GROUP BY i.user_id, i.id, i.kind ORDER BY i.user_id, i.id LIMIT 500",
                ['t' => $tenantId]
            );
        } catch (\Throwable) {
            return 0;
        }
        foreach ($rows as $r) {
            $notes[] = sprintf('communauté %d, membre %d : l\'identité %d (%s) porte %d papier(s) délivré(s) officiellement, laissée telle quelle (ce n\'est pas son identité civile)', $tenantId, (int) $r['uid'], (int) $r['iid'], (string) $r['kind'], (int) $r['n']);
        }

        return count($rows);
    }

    /** @param array<string, mixed> $row */
    private function journal(array $row): void
    {
        if ($this->logFile === null || $this->logFile === '') {
            return;
        }
        $dir = dirname($this->logFile);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            error_log('Alignement des identités : dossier du journal introuvable : ' . $dir);

            return;
        }
        $line = json_encode(['at' => gmdate('c')] + $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        file_put_contents($this->logFile, $line . "\n", FILE_APPEND | LOCK_EX);
    }
}
