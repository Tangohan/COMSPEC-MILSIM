<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use PDO;

/**
 * Contributeurs du site et traduction communautaire (migrations/20261025000001_site_contributors_translation.sql).
 *
 * Rôles : une demande par membre et par rôle (refaite après un refus ou un retrait). Traduction : une traduction
 * validée par texte et par langue, plus les propositions des membres en attente d'un traducteur.
 */
final class SiteContributorRepository
{
    use LazyDatabaseConnection;

    public const ROLES = ['translator', 'tester', 'content', 'developer', 'support'];
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_REVOKED = 'revoked';

    private static ?bool $ready = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public function schemaReady(): bool
    {
        if (self::$ready === true) {
            return true;
        }
        try {
            $st = $this->pdo()->prepare(
                'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            $st->execute(['site_translation_suggestions']);
            $ok = (bool) $st->fetchColumn();
        } catch (\Throwable) {
            $ok = false;
        }
        if ($ok) {
            self::$ready = true;
        }

        return $ok;
    }

    // ---- Rôles -------------------------------------------------------------------------------------------------

    /** @return list<array<string, mixed>> */
    public function rolesOfUser(int $userId): array
    {
        $st = $this->pdo()->prepare('SELECT * FROM site_contributor_roles WHERE user_id = ? ORDER BY role');
        $st->execute([$userId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Demande (ou nouvelle demande après refus / retrait) ; une demande en attente est mise à jour, un rôle actif
     * garde son statut et prend les nouvelles langues / le pseudo Discord.
     */
    public function apply(int $userId, string $role, string $languages, string $motivation, string $discord, bool $publicCredit): void
    {
        $st = $this->pdo()->prepare(
            'INSERT INTO site_contributor_roles (user_id, role, languages, status, motivation, discord_handle, public_credit)
             VALUES (?, ?, ?, \'pending\', ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               languages = VALUES(languages), motivation = VALUES(motivation), discord_handle = VALUES(discord_handle),
               public_credit = VALUES(public_credit),
               status = IF(status = \'active\', \'active\', \'pending\'),
               decided_by = IF(status = \'active\', decided_by, NULL),
               decided_at = IF(status = \'active\', decided_at, NULL)'
        );
        $st->execute([$userId, $role, $languages, $motivation, $discord, $publicCredit ? 1 : 0]);
    }

    public function withdraw(int $userId, string $role): void
    {
        $st = $this->pdo()->prepare('DELETE FROM site_contributor_roles WHERE user_id = ? AND role = ? AND status = \'pending\'');
        $st->execute([$userId, $role]);
    }

    /** @return array<string, mixed>|null */
    public function findRole(int $id): ?array
    {
        $st = $this->pdo()->prepare('SELECT * FROM site_contributor_roles WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function decide(int $id, string $status, int $adminId, string $note, ?string $languages = null): void
    {
        $sql = 'UPDATE site_contributor_roles SET status = ?, decided_by = ?, decided_at = NOW(), admin_note = ?'
            . ($languages !== null ? ', languages = ?' : '') . ' WHERE id = ?';
        $args = [$status, $adminId, mb_substr($note, 0, 500)];
        if ($languages !== null) {
            $args[] = $languages;
        }
        $args[] = $id;
        $this->pdo()->prepare($sql)->execute($args);
    }

    /**
     * Toutes les demandes et rôles, avec le membre (en attente d'abord, les plus anciennes en premier).
     *
     * @return list<array<string, mixed>>
     */
    public function allRoles(): array
    {
        $st = $this->pdo()->query(
            'SELECT r.*, u.display_name, u.email, u.callsign
               FROM site_contributor_roles r LEFT JOIN users u ON u.id = r.user_id
              ORDER BY (r.status = \'pending\') DESC, r.status, r.created_at'
        );

        return $st !== false ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    /**
     * Contributeurs actifs qui acceptent d'être cités : [nom, rôles].
     *
     * @return list<array{name: string, roles: list<string>, languages: string}>
     */
    public function publicCredits(): array
    {
        $st = $this->pdo()->query(
            'SELECT r.user_id, r.role, r.languages, COALESCE(NULLIF(u.callsign, \'\'), NULLIF(u.display_name, \'\'), \'?\') AS name
               FROM site_contributor_roles r JOIN users u ON u.id = r.user_id
              WHERE r.status = \'active\' AND r.public_credit = 1
              ORDER BY name'
        );
        $out = [];
        foreach ($st !== false ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [] as $row) {
            $uid = (int) $row['user_id'];
            $out[$uid] ??= ['name' => (string) $row['name'], 'roles' => [], 'languages' => ''];
            $out[$uid]['roles'][] = (string) $row['role'];
            if ((string) $row['languages'] !== '') {
                $out[$uid]['languages'] = (string) $row['languages'];
            }
        }

        return array_values($out);
    }

    /** Le membre est-il traducteur actif pour cette langue ? */
    public function isTranslatorFor(int $userId, string $lang): bool
    {
        $st = $this->pdo()->prepare('SELECT languages FROM site_contributor_roles WHERE user_id = ? AND role = \'translator\' AND status = \'active\'');
        $st->execute([$userId]);
        $langs = $st->fetchColumn();

        return is_string($langs) && in_array($lang, self::splitLanguages($langs), true);
    }

    /** @return list<string> */
    public static function splitLanguages(string $raw): array
    {
        $out = [];
        foreach (preg_split('/[\s,;]+/', strtolower($raw)) ?: [] as $code) {
            if ($code !== '' && \App\Services\I18n\CommunityLanguages::validCode($code) && !in_array($code, $out, true)) {
                $out[] = $code;
            }
        }

        return array_slice($out, 0, 8);
    }

    // ---- Langues -----------------------------------------------------------------------------------------------

    /** @return list<array<string, mixed>> */
    public function languages(): array
    {
        $st = $this->pdo()->query('SELECT * FROM site_translation_languages ORDER BY code');

        return $st !== false ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    /** @return array<string, mixed>|null */
    public function language(string $code): ?array
    {
        $st = $this->pdo()->prepare('SELECT * FROM site_translation_languages WHERE code = ?');
        $st->execute([$code]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function createLanguage(string $code, string $name, int $userId): void
    {
        $st = $this->pdo()->prepare('INSERT IGNORE INTO site_translation_languages (code, name, created_by) VALUES (?, ?, ?)');
        $st->execute([$code, mb_substr($name, 0, 60), $userId]);
    }

    public function updateLanguage(string $code, string $name, bool $published): void
    {
        $st = $this->pdo()->prepare(
            'UPDATE site_translation_languages SET name = ?, status = ?, published_at = IF(? = \'published\', COALESCE(published_at, NOW()), NULL) WHERE code = ?'
        );
        $status = $published ? 'published' : 'draft';
        $st->execute([mb_substr($name, 0, 60), $status, $status, $code]);
    }

    /** @return array<string, string> code => nom des langues publiées */
    public function publishedLanguages(): array
    {
        $out = [];
        foreach ($this->languages() as $row) {
            if ((string) $row['status'] === 'published') {
                $out[(string) $row['code']] = (string) $row['name'];
            }
        }

        return $out;
    }

    // ---- Traductions -------------------------------------------------------------------------------------------

    /** @return array<string, string> empreinte => texte validé */
    public function translations(string $lang): array
    {
        $st = $this->pdo()->prepare('SELECT src_hash, text FROM site_translations WHERE lang = ?');
        $st->execute([$lang]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_NUM) ?: [] as [$h, $t]) {
            $out[(string) $h] = (string) $t;
        }

        return $out;
    }

    /** @return array<string, int> code de langue => nombre de textes validés */
    public function translatedCounts(): array
    {
        $st = $this->pdo()->query('SELECT lang, COUNT(*) FROM site_translations GROUP BY lang');
        $out = [];
        foreach ($st !== false ? ($st->fetchAll(PDO::FETCH_NUM) ?: []) : [] as [$lang, $n]) {
            $out[(string) $lang] = (int) $n;
        }

        return $out;
    }

    public function saveTranslation(string $lang, string $hash, string $text, int $userId): void
    {
        $st = $this->pdo()->prepare(
            'INSERT INTO site_translations (lang, src_hash, text, user_id) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE text = VALUES(text), user_id = VALUES(user_id)'
        );
        $st->execute([$lang, $hash, $text, $userId]);
        // Les propositions encore ouvertes sur ce texte sont closes par la traduction validée.
        $this->pdo()->prepare(
            'UPDATE site_translation_suggestions SET status = IF(text = ?, \'accepted\', \'rejected\'), decided_by = ?, decided_at = NOW()
              WHERE lang = ? AND src_hash = ? AND status = \'pending\''
        )->execute([$text, $userId, $lang, $hash]);
    }

    public function deleteTranslation(string $lang, string $hash): void
    {
        $this->pdo()->prepare('DELETE FROM site_translations WHERE lang = ? AND src_hash = ?')->execute([$lang, $hash]);
    }

    /** Une seule proposition ouverte par membre et par texte : la nouvelle remplace l'ancienne. */
    public function suggest(string $lang, string $hash, string $text, int $userId): void
    {
        $this->pdo()->prepare(
            'DELETE FROM site_translation_suggestions WHERE lang = ? AND src_hash = ? AND user_id = ? AND status = \'pending\''
        )->execute([$lang, $hash, $userId]);
        $this->pdo()->prepare(
            'INSERT INTO site_translation_suggestions (lang, src_hash, text, user_id) VALUES (?, ?, ?, ?)'
        )->execute([$lang, $hash, $text, $userId]);
    }

    /**
     * Propositions ouvertes d'une langue, par texte : empreinte => [[id, texte, auteur, date], ...].
     *
     * @return array<string, list<array{id: int, text: string, author: string, user_id: int, at: string}>>
     */
    public function pendingSuggestions(string $lang): array
    {
        $st = $this->pdo()->prepare(
            'SELECT s.id, s.src_hash, s.text, s.user_id, s.created_at,
                    COALESCE(NULLIF(u.callsign, \'\'), NULLIF(u.display_name, \'\'), \'?\') AS author
               FROM site_translation_suggestions s LEFT JOIN users u ON u.id = s.user_id
              WHERE s.lang = ? AND s.status = \'pending\' ORDER BY s.created_at'
        );
        $st->execute([$lang]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[(string) $row['src_hash']][] = [
                'id' => (int) $row['id'],
                'text' => (string) $row['text'],
                'author' => (string) $row['author'],
                'user_id' => (int) $row['user_id'],
                'at' => (string) $row['created_at'],
            ];
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function suggestion(int $id): ?array
    {
        $st = $this->pdo()->prepare('SELECT * FROM site_translation_suggestions WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function rejectSuggestion(int $id, int $userId): void
    {
        $this->pdo()->prepare(
            'UPDATE site_translation_suggestions SET status = \'rejected\', decided_by = ?, decided_at = NOW() WHERE id = ? AND status = \'pending\''
        )->execute([$userId, $id]);
    }

    /** Propositions ouvertes par langue (tableau de bord). @return array<string, int> */
    public function pendingCounts(): array
    {
        $st = $this->pdo()->query('SELECT lang, COUNT(*) FROM site_translation_suggestions WHERE status = \'pending\' GROUP BY lang');
        $out = [];
        foreach ($st !== false ? ($st->fetchAll(PDO::FETCH_NUM) ?: []) : [] as [$lang, $n]) {
            $out[(string) $lang] = (int) $n;
        }

        return $out;
    }
}
