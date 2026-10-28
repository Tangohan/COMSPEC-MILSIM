<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use App\Support\UserPublicName;
use PDO;

/**
 * Contributeurs du site et traduction communautaire (migrations/20261025000001_site_contributors_translation.sql).
 *
 * Rôles : une ligne par membre et par rôle (plusieurs rôles possibles). Une demande refusée ou retirée peut
 * être refaite. Traduction : une traduction validée par texte et par langue, plus les propositions en attente.
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

    /**
     * Accorde un rôle sans toucher aux autres. Un rôle déjà actif reste actif (ses langues de traducteur
     * peuvent être mises à jour). Un rôle absent est créé actif, en reprenant le pseudo Discord et le choix
     * « citer mon nom » d’une demande déjà enregistrée pour cette personne.
     *
     * @return 'created'|'activated'|'kept'
     */
    public function grantRole(int $userId, string $role, int $adminId, string $note, ?string $languages): string
    {
        if (!in_array($role, self::ROLES, true) || $userId < 1) {
            return 'kept';
        }
        $st = $this->pdo()->prepare('SELECT * FROM site_contributor_roles WHERE user_id = ? AND role = ?');
        $st->execute([$userId, $role]);
        $existing = $st->fetch(PDO::FETCH_ASSOC);
        if (is_array($existing) && (string) $existing['status'] === self::STATUS_ACTIVE) {
            if ($role === 'translator' && $languages !== null && $languages !== (string) $existing['languages']) {
                $this->pdo()->prepare('UPDATE site_contributor_roles SET languages = ? WHERE id = ?')
                    ->execute([$languages, (int) $existing['id']]);
            }

            return 'kept';
        }
        if (is_array($existing)) {
            $this->decide(
                (int) $existing['id'],
                self::STATUS_ACTIVE,
                $adminId,
                $note,
                $role === 'translator' ? $languages : null
            );

            return 'activated';
        }
        $template = null;
        foreach ($this->rolesOfUser($userId) as $sibling) {
            if ($template === null || (int) $sibling['id'] > (int) $template['id']) {
                $template = $sibling;
            }
        }
        $langs = $role === 'translator' ? (string) ($languages ?? '') : '';
        $this->pdo()->prepare(
            'INSERT INTO site_contributor_roles
                (user_id, role, languages, status, motivation, discord_handle, public_credit, admin_note, decided_by, decided_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        )->execute([
            $userId,
            $role,
            $langs,
            self::STATUS_ACTIVE,
            mb_substr($template !== null ? (string) $template['motivation'] : '', 0, 1500),
            mb_substr($template !== null ? (string) $template['discord_handle'] : '', 0, 64),
            $template !== null && (int) $template['public_credit'] === 1 ? 1 : ($template === null ? 1 : 0),
            mb_substr($note, 0, 500),
            $adminId,
        ]);
        \App\Services\Platform\StaffMarkService::forget();

        return 'created';
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
        \App\Services\Platform\StaffMarkService::forget();
    }

    /**
     * Toutes les demandes et rôles, avec le membre (en attente d'abord, les plus anciennes en premier).
     *
     * @return list<array<string, mixed>>
     */
    public function allRoles(): array
    {
        UserPublicName::ensure($this->pdo());
        $st = $this->pdo()->query(
            'SELECT r.*, u.display_name, u.email, u.callsign, ' . UserPublicName::column('u') . '
               FROM site_contributor_roles r LEFT JOIN users u ON u.id = r.user_id
              ORDER BY (r.status = \'pending\') DESC, r.status, r.created_at'
        );

        return $st !== false ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    /**
     * Contributeurs actifs qui acceptent d'être cités : [nom, rôles].
     *
     * @return list<array{user_id: int, name: string, roles: list<string>, languages: string}>
     */
    public function publicCredits(): array
    {
        UserPublicName::ensure($this->pdo());
        $st = $this->pdo()->query(
            'SELECT r.user_id, r.role, r.languages, ' . UserPublicName::sql('u', 'Membre') . ' AS name
               FROM site_contributor_roles r JOIN users u ON u.id = r.user_id
              WHERE r.status = \'active\' AND r.public_credit = 1
              ORDER BY name'
        );
        $out = [];
        foreach ($st !== false ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [] as $row) {
            $uid = (int) $row['user_id'];
            $out[$uid] ??= ['user_id' => $uid, 'name' => (string) $row['name'], 'roles' => [], 'languages' => ''];
            $out[$uid]['roles'][] = (string) $row['role'];
            if ((string) $row['languages'] !== '') {
                $out[$uid]['languages'] = (string) $row['languages'];
            }
        }
        $order = array_flip(self::ROLES);
        foreach ($out as &$person) {
            usort($person['roles'], static fn (string $a, string $b): int => ($order[$a] ?? 9) <=> ($order[$b] ?? 9));
        }
        unset($person);

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

    /**
     * Classement des auteurs de textes validés.
     *
     * Le décompte ne joint pas les rôles : une personne peut en avoir plusieurs, et chaque rôle
     * répéterait les mêmes textes. Le nom public est le pseudo, sinon l’indicatif.
     *
     * @return list<array{user_id: int, name: string, validated: int, public_credit: bool, roles: list<string>, public_roles: list<string>}>
     */
    public function translationLeaders(int $limit = 12): array
    {
        $limit = max(1, min(30, $limit));
        UserPublicName::ensure($this->pdo());
        $name = UserPublicName::sql('u', 'Membre');
        $st = $this->pdo()->query(
            'SELECT t.user_id, MAX(' . $name . ') AS name, COUNT(*) AS validated
               FROM site_translations t
               LEFT JOIN users u ON u.id = t.user_id
              GROUP BY t.user_id
              ORDER BY validated DESC
              LIMIT ' . $limit
        );
        $rows = $st !== false ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        if ($rows === []) {
            return [];
        }
        $ids = [];
        foreach ($rows as $row) {
            $id = (int) $row['user_id'];
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $byUser = [];
        if ($ids !== []) {
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $roles = $this->pdo()->prepare(
                'SELECT user_id, role, status, public_credit FROM site_contributor_roles WHERE user_id IN (' . $marks . ')'
            );
            $roles->execute($ids);
            foreach ($roles->fetchAll(PDO::FETCH_ASSOC) ?: [] as $roleRow) {
                $byUser[(int) $roleRow['user_id']][] = $roleRow;
            }
        }
        $order = array_flip(self::ROLES);
        $sortRoles = static function (array $list) use ($order): array {
            $list = array_values(array_unique($list));
            usort($list, static fn (string $a, string $b): int => ($order[$a] ?? 9) <=> ($order[$b] ?? 9));

            return $list;
        };
        $out = [];
        foreach ($rows as $row) {
            $uid = (int) $row['user_id'];
            $active = [];
            $cited = [];
            foreach ($byUser[$uid] ?? [] as $roleRow) {
                $key = (string) $roleRow['role'];
                if ((string) $roleRow['status'] !== self::STATUS_ACTIVE || !in_array($key, self::ROLES, true)) {
                    continue;
                }
                $active[] = $key;
                if ((int) $roleRow['public_credit'] === 1) {
                    $cited[] = $key;
                }
            }
            $nameText = trim((string) $row['name']);
            $out[] = [
                'user_id' => $uid,
                'name' => $nameText !== '' ? $nameText : 'Membre',
                'validated' => (int) $row['validated'],
                'public_credit' => $cited !== [],
                'roles' => $sortRoles($active),
                'public_roles' => $sortRoles($cited),
            ];
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
        UserPublicName::ensure($this->pdo());
        $st = $this->pdo()->prepare(
            'SELECT s.id, s.src_hash, s.text, s.user_id, s.created_at,
                    ' . UserPublicName::sql('u', '?') . ' AS author
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
