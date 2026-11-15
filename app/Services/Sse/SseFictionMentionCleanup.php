<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Support\SseDocumentChromeCatalog;
use App\Support\SseFictionMentions;
use PDO;

/**
 * Rattrapage des mentions de jeu ENREGISTRÉES avant leur retrait des documents (tools/sse-strip-fiction-mentions.php).
 *
 * Ce qui est corrigé, et seulement cela (valeur EXACTE livrée par défaut ; un texte saisi par une communauté n'est jamais touché) :
 * - sse_document_prefabs.footer : ancien pied de page livré (« … usage RP … preuve judiciaire … ») => vide ;
 * - sse_document_prefabs.description : ancienne description livrée citant le « RP » => description actuelle ;
 * - sse_wanted_settings.settings_json : autorité par défaut « … — RP », service par défaut « … (fictive) » => sans la mention ;
 * - sse_wanted_notices.contact_label / authority : nom d'un service livré suivi de « (fictif) » => sans la mention ;
 * - sse_wanted_notice_meta.service_json : instantané du service livré (noms fr, en, autorités) => sans la mention.
 *
 * Simulation par défaut (rien n'est écrit). Idempotent : une valeur corrigée n'est plus reconnue, relancer ne fait rien.
 * Une table absente est ignorée. Chaque ligne est écrite seule : une erreur n'arrête pas les autres. Compatible sqlite (tests).
 * L'affichage n'attend pas ce rattrapage : la lecture retire déjà ces mentions (SseFictionMentions, SseDocumentChromeCatalog).
 */
final class SseFictionMentionCleanup
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return array{applied: bool, found: int, written: int, failed: int, by_table: array<string, int>, changes: list<array{table: string, id: string, tenant: int, column: string, before: string, after: string}>, errors: list<string>}
     */
    public function run(bool $apply = false, ?int $tenantId = null): array
    {
        $report = ['applied' => $apply, 'found' => 0, 'written' => 0, 'failed' => 0, 'by_table' => [], 'changes' => [], 'errors' => []];
        $this->prefabs($report, $apply, $tenantId);
        $this->wantedSettings($report, $apply, $tenantId);
        $this->wantedNotices($report, $apply, $tenantId);
        $this->wantedMeta($report, $apply, $tenantId);

        return $report;
    }

    /** @param array<string, mixed> $report */
    private function prefabs(array &$report, bool $apply, ?int $tenantId): void
    {
        foreach ($this->rows('sse_document_prefabs', 'id, tenant_id, footer, description', $tenantId) as $row) {
            $footer = trim((string) ($row['footer'] ?? ''));
            if (in_array($footer, SseDocumentChromeCatalog::LEGACY_FOOTERS, true)) {
                $this->change($report, $apply, 'sse_document_prefabs', 'id', (string) $row['id'], (int) $row['tenant_id'], 'footer', $footer, '');
            }
            $description = trim((string) ($row['description'] ?? ''));
            if (isset(SseFictionMentions::LEGACY_PREFAB_DESCRIPTIONS[$description])) {
                $this->change($report, $apply, 'sse_document_prefabs', 'id', (string) $row['id'], (int) $row['tenant_id'], 'description', $description, SseFictionMentions::LEGACY_PREFAB_DESCRIPTIONS[$description]);
            }
        }
    }

    /** @param array<string, mixed> $report */
    private function wantedSettings(array &$report, bool $apply, ?int $tenantId): void
    {
        foreach ($this->rows('sse_wanted_settings', 'tenant_id, settings_json', $tenantId) as $row) {
            $json = (string) ($row['settings_json'] ?? '');
            $data = json_decode($json, true);
            if (!is_array($data)) {
                continue;
            }
            $changed = false;
            foreach (['authority_fr', 'authority_en', 'contact_label'] as $key) {
                if (isset($data[$key]) && is_string($data[$key]) && ($clean = SseFictionMentions::cleanName($data[$key])) !== $data[$key]) {
                    $data[$key] = $clean;
                    $changed = true;
                }
            }
            if ($changed) {
                $this->change($report, $apply, 'sse_wanted_settings', 'tenant_id', (string) $row['tenant_id'], (int) $row['tenant_id'], 'settings_json', $json, (string) json_encode($data, JSON_UNESCAPED_UNICODE));
            }
        }
    }

    /** @param array<string, mixed> $report */
    private function wantedNotices(array &$report, bool $apply, ?int $tenantId): void
    {
        foreach ($this->rows('sse_wanted_notices', 'id, tenant_id, contact_label, authority', $tenantId) as $row) {
            foreach (['contact_label', 'authority'] as $column) {
                $value = (string) ($row[$column] ?? '');
                if ($value !== '' && ($clean = SseFictionMentions::cleanName($value)) !== $value) {
                    $this->change($report, $apply, 'sse_wanted_notices', 'id', (string) $row['id'], (int) $row['tenant_id'], $column, $value, $clean);
                }
            }
        }
    }

    /** @param array<string, mixed> $report */
    private function wantedMeta(array &$report, bool $apply, ?int $tenantId): void
    {
        foreach ($this->rows('sse_wanted_notice_meta', 'notice_id, tenant_id, service_json', $tenantId) as $row) {
            $json = (string) ($row['service_json'] ?? '');
            $data = json_decode($json, true);
            if (!is_array($data)) {
                continue;
            }
            $clean = SseFictionMentions::cleanServiceSnapshot($data);
            if ($clean !== $data) {
                $this->change($report, $apply, 'sse_wanted_notice_meta', 'notice_id', (string) $row['notice_id'], (int) $row['tenant_id'], 'service_json', $json, (string) json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
        }
    }

    /**
     * Lignes d'une table, ou rien si elle n'existe pas (ou n'a pas ces colonnes).
     *
     * @return list<array<string, mixed>>
     */
    private function rows(string $table, string $columns, ?int $tenantId): array
    {
        try {
            $st = $this->pdo->prepare('SELECT ' . $columns . ' FROM ' . $table . ($tenantId !== null ? ' WHERE tenant_id = :t' : ''));
            $st->execute($tenantId !== null ? ['t' => $tenantId] : []);

            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    /** @param array<string, mixed> $report */
    private function change(array &$report, bool $apply, string $table, string $keyColumn, string $id, int $tenant, string $column, string $before, string $after): void
    {
        ++$report['found'];
        $report['by_table'][$table] = ($report['by_table'][$table] ?? 0) + 1;
        $report['changes'][] = ['table' => $table, 'id' => $id, 'tenant' => $tenant, 'column' => $column, 'before' => $before, 'after' => $after];
        if (!$apply) {
            return;
        }
        try {
            // La valeur d'origine est dans la condition : une ligne modifiée entre-temps n'est pas écrasée.
            $st = $this->pdo->prepare('UPDATE ' . $table . ' SET ' . $column . ' = :after WHERE ' . $keyColumn . ' = :id AND tenant_id = :t AND ' . $column . ' = :before');
            $st->execute(['after' => $after, 'id' => $id, 't' => $tenant, 'before' => $before]);
            ++$report['written'];
        } catch (\Throwable $e) {
            ++$report['failed'];
            $report['errors'][] = $table . ' ' . $id . ' (' . $column . ') : ' . $e->getMessage();
        }
    }
}
