<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Services\Moderation\NameFilter;
use PDO;

/**
 * Filtre des noms (communautés, unités) : liste de mots, noms autorisés, alertes.
 * Tables créées au premier usage ; la liste par défaut y est copiée une seule fois.
 */
final class NameFilterRepository
{
    private ?PDO $pdo;
    private static bool $schemaReady = false;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    public function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }
        $pdo = $this->pdo();
        $pdo->exec('CREATE TABLE IF NOT EXISTS name_filter_words (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            word VARCHAR(120) NOT NULL,
            lang VARCHAR(8) NOT NULL DEFAULT "all",
            match_mode VARCHAR(16) NOT NULL DEFAULT "word",
            created_by_user_id INT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uq_name_filter_word (word)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $pdo->exec('CREATE TABLE IF NOT EXISTS name_filter_allowed (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name_norm VARCHAR(255) NOT NULL,
            created_by_user_id INT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uq_name_filter_allowed (name_norm)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $pdo->exec('CREATE TABLE IF NOT EXISTS name_filter_alerts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            subject_type VARCHAR(16) NOT NULL,
            subject_id INT NOT NULL,
            tenant_id INT NULL,
            name VARCHAR(255) NOT NULL,
            matched_words VARCHAR(500) NOT NULL DEFAULT "",
            user_id INT NULL,
            status VARCHAR(16) NOT NULL DEFAULT "open",
            created_at DATETIME NOT NULL,
            resolved_at DATETIME NULL,
            resolved_by_user_id INT NULL,
            KEY idx_name_filter_alerts_status (status, created_at),
            KEY idx_name_filter_alerts_subject (subject_type, subject_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $count = (int) $pdo->query('SELECT COUNT(*) FROM name_filter_words')->fetchColumn();
        if ($count === 0) {
            $st = $pdo->prepare('INSERT IGNORE INTO name_filter_words (word, lang, match_mode, created_at) VALUES (?, ?, ?, NOW())');
            foreach (NameFilter::DEFAULT_WORDS as $w) {
                $st->execute([$w['word'], $w['lang'], $w['mode']]);
            }
        }
        self::$schemaReady = true;
    }

    /**
     * Lecture appelée à chaque page publique : pas de CREATE TABLE tant que les tables existent.
     *
     * @return list<mixed>
     */
    private function readWithSchema(string $sql, int $fetchMode): array
    {
        if (!self::$schemaReady) {
            try {
                $st = $this->pdo()->query($sql);

                return $st ? ($st->fetchAll($fetchMode) ?: []) : [];
            } catch (\PDOException) {
                $this->ensureSchema();
            }
        }
        $st = $this->pdo()->query($sql);

        return $st ? ($st->fetchAll($fetchMode) ?: []) : [];
    }

    /** Le CREATE TABLE validerait la transaction en cours : dans ce cas on suppose les tables présentes. */
    private function ensureSchemaOutsideTransaction(): void
    {
        if (!$this->pdo()->inTransaction()) {
            $this->ensureSchema();
        }
    }

    /** @return list<array{id: int, word: string, lang: string, mode: string, created_at: string}> */
    public function listWords(): array
    {
        $rows = $this->readWithSchema(
            'SELECT id, word, lang, match_mode, created_at FROM name_filter_words ORDER BY lang ASC, word ASC',
            PDO::FETCH_ASSOC
        );

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'word' => (string) $r['word'],
            'lang' => (string) $r['lang'],
            'mode' => (string) $r['match_mode'],
            'created_at' => (string) $r['created_at'],
        ], $rows);
    }

    public function addWord(string $word, string $lang, string $mode, ?int $userId): bool
    {
        $this->ensureSchema();
        $st = $this->pdo()->prepare('INSERT IGNORE INTO name_filter_words (word, lang, match_mode, created_by_user_id, created_at) VALUES (?, ?, ?, ?, NOW())');
        $st->execute([$word, $lang, $mode, $userId]);

        return $st->rowCount() > 0;
    }

    /** @return 'ok'|'duplicate'|'missing' */
    public function updateWord(int $id, string $word, string $lang, string $mode): string
    {
        $this->ensureSchema();
        $st = $this->pdo()->prepare('SELECT id FROM name_filter_words WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        if (!$st->fetchColumn()) {
            return 'missing';
        }
        $st = $this->pdo()->prepare('SELECT id FROM name_filter_words WHERE word = ? AND id <> ? LIMIT 1');
        $st->execute([$word, $id]);
        if ($st->fetchColumn()) {
            return 'duplicate';
        }
        $st = $this->pdo()->prepare('UPDATE name_filter_words SET word = ?, lang = ?, match_mode = ? WHERE id = ?');
        $st->execute([$word, $lang, $mode, $id]);

        return 'ok';
    }

    public function deleteWord(int $id): bool
    {
        $this->ensureSchema();
        $st = $this->pdo()->prepare('DELETE FROM name_filter_words WHERE id = ?');
        $st->execute([$id]);

        return $st->rowCount() > 0;
    }

    /** Remet la liste livrée par défaut (ajoute les mots manquants, garde les ajouts). */
    public function restoreDefaults(): int
    {
        $this->ensureSchema();
        $st = $this->pdo()->prepare('INSERT IGNORE INTO name_filter_words (word, lang, match_mode, created_at) VALUES (?, ?, ?, NOW())');
        $added = 0;
        foreach (NameFilter::DEFAULT_WORDS as $w) {
            $st->execute([$w['word'], $w['lang'], $w['mode']]);
            $added += $st->rowCount();
        }

        return $added;
    }

    /** @return list<string> */
    public function listAllowedNames(): array
    {
        return array_map('strval', $this->readWithSchema('SELECT name_norm FROM name_filter_allowed', PDO::FETCH_COLUMN));
    }

    public function allowName(string $nameNorm, ?int $userId): void
    {
        $this->ensureSchema();
        $st = $this->pdo()->prepare('INSERT IGNORE INTO name_filter_allowed (name_norm, created_by_user_id, created_at) VALUES (?, ?, NOW())');
        $st->execute([mb_substr($nameNorm, 0, 255), $userId]);
    }

    /** Ouvre une alerte, sauf si la même (sujet + nom) est déjà ouverte. @return int id créé, 0 si doublon */
    public function openAlert(string $subjectType, int $subjectId, ?int $tenantId, string $name, array $words, ?int $userId): int
    {
        $this->ensureSchemaOutsideTransaction();
        $name = mb_substr($name, 0, 255);
        $dup = $this->pdo()->prepare('SELECT id FROM name_filter_alerts WHERE subject_type = ? AND subject_id = ? AND name = ? AND status = \'open\' LIMIT 1');
        $dup->execute([$subjectType, $subjectId, $name]);
        if ($dup->fetchColumn()) {
            return 0;
        }
        $st = $this->pdo()->prepare('INSERT INTO name_filter_alerts (subject_type, subject_id, tenant_id, name, matched_words, user_id, status, created_at) VALUES (?, ?, ?, ?, ?, ?, \'open\', NOW())');
        $st->execute([$subjectType, $subjectId, $tenantId, $name, mb_substr(implode(', ', $words), 0, 500), $userId]);

        return (int) $this->pdo()->lastInsertId();
    }

    /** Ferme les alertes ouvertes d’un sujet dont le nom a été corrigé. */
    public function closeAlertsForSubject(string $subjectType, int $subjectId): void
    {
        $this->ensureSchemaOutsideTransaction();
        $st = $this->pdo()->prepare('UPDATE name_filter_alerts SET status = \'renamed\', resolved_at = NOW() WHERE subject_type = ? AND subject_id = ? AND status = \'open\'');
        $st->execute([$subjectType, $subjectId]);
    }

    public function findAlert(int $id): ?array
    {
        $this->ensureSchema();
        $st = $this->pdo()->prepare('SELECT * FROM name_filter_alerts WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function resolveAlert(int $id, string $status, ?int $userId): bool
    {
        $this->ensureSchema();
        $st = $this->pdo()->prepare('UPDATE name_filter_alerts SET status = ?, resolved_at = NOW(), resolved_by_user_id = ? WHERE id = ? AND status = \'open\'');
        $st->execute([$status, $userId, $id]);

        return $st->rowCount() > 0;
    }

    public function countOpenAlerts(): int
    {
        $this->ensureSchema();

        return (int) $this->pdo()->query('SELECT COUNT(*) FROM name_filter_alerts WHERE status = \'open\'')->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function listAlerts(bool $openOnly, int $limit = 200): array
    {
        $this->ensureSchema();
        $limit = max(1, min(500, $limit));
        $sql = 'SELECT a.*, t.name AS tenant_name, t.slug AS tenant_slug, u.display_name AS user_display_name, u.callsign AS user_callsign, u.email AS user_email,
                       un.name AS unit_current_name
                FROM name_filter_alerts a
                LEFT JOIN tenants t ON t.id = a.tenant_id
                LEFT JOIN users u ON u.id = a.user_id
                LEFT JOIN units un ON a.subject_type = \'unit\' AND un.id = a.subject_id'
            . ($openOnly ? ' WHERE a.status = \'open\'' : '')
            . ' ORDER BY a.created_at DESC, a.id DESC LIMIT ' . $limit;

        return $this->pdo()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Tous les noms existants, pour l’analyse rétroactive. @return list<array{type: string, id: int, tenant_id: ?int, name: string}> */
    public function listAllNames(): array
    {
        $out = [];
        foreach ($this->pdo()->query('SELECT id, name FROM tenants')->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $out[] = ['type' => 'tenant', 'id' => (int) $r['id'], 'tenant_id' => (int) $r['id'], 'name' => (string) $r['name']];
        }
        foreach ($this->pdo()->query('SELECT id, tenant_id, name FROM units')->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $out[] = ['type' => 'unit', 'id' => (int) $r['id'], 'tenant_id' => (int) $r['tenant_id'], 'name' => (string) $r['name']];
        }

        return $out;
    }
}
