<?php

declare(strict_types=1);

namespace App\Services\Sse\Attach;

use App\Core\Database;

/**
 * Compatibilité : un rattachement continue d'écrire la colonne ou la table historique (case_id, interest_case_id,
 * sse_case_persons, sse_interest_case_links) EN PLUS du lien, pour que les listes, compteurs, PDF, exports et l'API du jeu
 * restent justes sans rien changer à leur code.
 *
 * Une colonne à valeur unique (le dossier d'une fiche) garde le premier dossier rattaché : un second rattachement est un lien
 * de plus et ne déplace rien (« Déplacer » le fait explicitement). Au détachement, la colonne n'est vidée que si elle désignait
 * bien cette cible. Jamais bloquant : une table absente d'une ancienne base est ignorée.
 */
final class SseAttachLegacy
{
    /** Colonnes à valeur unique : [type de pièce][type de cible] => [table, colonne]. */
    private const COLUMNS = [
        'note' => ['case' => ['sse_field_notes', 'case_id'], 'interest' => ['sse_field_notes', 'interest_case_id']],
        'document' => ['case' => ['sse_documents', 'case_id']],
        'transmission' => ['case' => ['sse_intel_events', 'case_id'], 'interest' => ['sse_intel_events', 'interest_case_id']],
        'mesh' => ['case' => ['sse_meshes', 'case_id']],
        'photo' => ['identity' => ['sse_file_photos', 'identity_id']],
        'identity' => ['person' => ['sse_identities', 'person_id']],
    ];

    /** Types d'éléments liés d'un dossier d'intérêt (SseInterestCaseCatalog::LINK_TYPES). */
    private const INTEREST_TYPES = ['person' => 'person', 'identity' => 'identity', 'site' => 'site', 'transmission' => 'transmission', 'note' => 'fiche', 'doc_issue' => 'document', 'case' => 'case'];

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public static function handles(string $sourceType, string $targetType): bool
    {
        return isset(self::COLUMNS[$sourceType][$targetType]) || ($sourceType === 'person' && $targetType === 'case')
            || ($targetType === 'interest' && isset(self::INTEREST_TYPES[$sourceType]));
    }

    /**
     * Écrit la trace historique du rattachement.
     *
     * @return string|null ce qui a été écrit (« sse_field_notes.case_id »), ou null si rien n'était à écrire
     */
    public function write(int $tenantId, string $sourceType, int $sourceId, string $targetType, int $targetId, ?int $userId, string $reason, string $label = '', bool $force = false): ?string
    {
        $done = null;
        try {
            if (isset(self::COLUMNS[$sourceType][$targetType])) {
                [$table, $col] = self::COLUMNS[$sourceType][$targetType];
                $row = $this->db->fetchOne("SELECT {$col} AS v FROM {$table} WHERE tenant_id = :t AND id = :id", ['t' => $tenantId, 'id' => $sourceId]);
                if ($row !== null && ($force || (int) ($row['v'] ?? 0) < 1)) {
                    $this->db->execute("UPDATE {$table} SET {$col} = :v WHERE tenant_id = :t AND id = :id", ['v' => $targetId, 't' => $tenantId, 'id' => $sourceId]);
                    $done = $table . '.' . $col;
                }
            } elseif ($sourceType === 'person' && $targetType === 'case') {
                $has = $this->db->fetchOne('SELECT id FROM sse_case_persons WHERE tenant_id = :t AND case_id = :c AND person_id = :p', ['t' => $tenantId, 'c' => $targetId, 'p' => $sourceId]);
                if ($has === null) {
                    $this->db->insert(
                        'INSERT INTO sse_case_persons (case_id, person_id, tenant_id, linked_by, note, created_at) VALUES (:c, :p, :t, :u, :n, :at)',
                        ['c' => $targetId, 'p' => $sourceId, 't' => $tenantId, 'u' => $userId, 'n' => mb_substr($reason, 0, 250), 'at' => gmdate('Y-m-d H:i:s')]
                    );
                    $done = 'sse_case_persons';
                }
            }
        } catch (\Throwable) {
            // Ancienne base : la colonne ou la table manque, le lien suffit.
        }
        if ($targetType === 'interest' && isset(self::INTEREST_TYPES[$sourceType])) {
            try {
                $type = self::INTEREST_TYPES[$sourceType];
                $has = $this->db->fetchOne('SELECT id FROM sse_interest_case_links WHERE tenant_id = :t AND interest_case_id = :c AND link_type = :ty AND target_id = :id', ['t' => $tenantId, 'c' => $targetId, 'ty' => $type, 'id' => (string) $sourceId]);
                if ($has === null) {
                    $this->db->insert(
                        'INSERT INTO sse_interest_case_links (tenant_id, interest_case_id, link_type, target_id, label, note, created_by, created_at) VALUES (:t, :c, :ty, :id, :l, :n, :u, :at)',
                        ['t' => $tenantId, 'c' => $targetId, 'ty' => $type, 'id' => (string) $sourceId, 'l' => mb_substr($label !== '' ? $label : 'Élément rattaché', 0, 200), 'n' => mb_substr($reason, 0, 300), 'u' => $userId, 'at' => gmdate('Y-m-d H:i:s')]
                    );
                    $done = ($done !== null ? $done . ' + ' : '') . 'sse_interest_case_links';
                }
            } catch (\Throwable) {
            }
        }

        return $done;
    }

    /** Retire la trace historique, seulement si elle désignait cette cible. */
    public function remove(int $tenantId, string $sourceType, int $sourceId, string $targetType, int $targetId): ?string
    {
        $done = null;
        try {
            if (isset(self::COLUMNS[$sourceType][$targetType])) {
                [$table, $col] = self::COLUMNS[$sourceType][$targetType];
                if ($this->db->execute("UPDATE {$table} SET {$col} = NULL WHERE tenant_id = :t AND id = :id AND {$col} = :v", ['t' => $tenantId, 'id' => $sourceId, 'v' => $targetId]) > 0) {
                    $done = $table . '.' . $col;
                }
            } elseif ($sourceType === 'person' && $targetType === 'case') {
                if ($this->db->execute('DELETE FROM sse_case_persons WHERE tenant_id = :t AND case_id = :c AND person_id = :p', ['t' => $tenantId, 'c' => $targetId, 'p' => $sourceId]) > 0) {
                    $done = 'sse_case_persons';
                }
            }
        } catch (\Throwable) {
        }
        if ($targetType === 'interest' && isset(self::INTEREST_TYPES[$sourceType])) {
            try {
                $this->db->execute('DELETE FROM sse_interest_case_links WHERE tenant_id = :t AND interest_case_id = :c AND link_type = :ty AND target_id = :id', ['t' => $tenantId, 'c' => $targetId, 'ty' => self::INTEREST_TYPES[$sourceType], 'id' => (string) $sourceId]);
            } catch (\Throwable) {
            }
        }

        return $done;
    }

    /** La cible actuellement portée par la colonne historique (0 : aucune, ou pas de colonne). */
    public function current(int $tenantId, string $sourceType, int $sourceId, string $targetType): int
    {
        if (!isset(self::COLUMNS[$sourceType][$targetType])) {
            return 0;
        }
        [$table, $col] = self::COLUMNS[$sourceType][$targetType];
        try {
            $row = $this->db->fetchOne("SELECT {$col} AS v FROM {$table} WHERE tenant_id = :t AND id = :id", ['t' => $tenantId, 'id' => $sourceId]);
        } catch (\Throwable) {
            return 0;
        }

        return (int) ($row['v'] ?? 0);
    }
}
