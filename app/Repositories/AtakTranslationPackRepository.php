<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use PDO;

/**
 * Packs de langue partagés de l'atelier public /traduire-atak (migrations/20261018000003_atak_translation_packs.sql).
 *
 * Sans compte : un pack s'ouvre avec un jeton secret porté par le lien (fragment #, jamais dans les journaux du
 * serveur). Jeton propriétaire : tout (réglages, validation des propositions, import, nouveau lien, suppression).
 * Jeton contributeur : écrit les phrases, ou les propose seulement si le pack est en mode « review ». Seules les
 * empreintes SHA-256 des jetons sont gardées ; changer le lien contributeur coupe l'accès de l'ancien lien.
 *
 * Chaque écriture augmente `rev` du pack et marque la phrase / proposition de ce numéro : un navigateur demande
 * « tout ce qui a changé depuis rev N » ; une écriture faite à partir d'une version périmée d'une phrase (base_rev
 * différent) et d'un texte différent est refusée en conflit, avec la version actuelle.
 */
final class AtakTranslationPackRepository
{
    use LazyDatabaseConnection;

    public const MAX_TEXT = 4000;
    public const MAX_BULK = 2500;
    public const HISTORY_LIMIT = 30;
    public const ROLE_OWNER = 'owner';
    public const ROLE_EDITOR = 'editor';

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
            $st->execute(['atak_translation_history']);
            $ok = (bool) $st->fetchColumn();
        } catch (\Throwable) {
            $ok = false;
        }
        if ($ok) {
            self::$ready = true;
        }

        return $ok;
    }

    private static ?bool $workshopReady = null;

    /** Tables du Workshop des langues présentes (migrations/20261019000010_atak_translation_workshop.sql) ? */
    public function workshopReady(): bool
    {
        if (self::$workshopReady === true) {
            return true;
        }
        try {
            $st = $this->pdo()->prepare(
                'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            $st->execute(['atak_translation_releases']);
            $ok = (bool) $st->fetchColumn();
        } catch (\Throwable) {
            $ok = false;
        }
        if ($ok) {
            self::$workshopReady = true;
        }

        return $ok;
    }

    private static ?bool $accountReady = null;

    /** Colonne user_id présente (migration qui rattache un pack au compte, sans toucher aux packs historiques) ? */
    public function accountReady(): bool
    {
        if (self::$accountReady === true) {
            return true;
        }
        try {
            $st = $this->pdo()->prepare(
                'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
            );
            $st->execute(['atak_translation_packs', 'user_id']);
            $ok = (bool) $st->fetchColumn();
        } catch (\Throwable) {
            $ok = false;
        }
        if ($ok) {
            self::$accountReady = true;
        }

        return $ok;
    }

    // ---------- Valeurs reçues ----------

    public static function validCode(mixed $code): ?string
    {
        return is_string($code) && preg_match('/^[a-z]{2}$/', $code) === 1 && !in_array($code, ['fr', 'en'], true) ? $code : null;
    }

    public static function validPublicId(mixed $id): ?string
    {
        return is_string($id) && preg_match('/^[a-z0-9]{12}$/', $id) === 1 ? $id : null;
    }

    public static function validKey(mixed $h): ?string
    {
        return is_string($h) && preg_match('/^[0-9a-f]{16}$/', $h) === 1 ? $h : null;
    }

    /** Texte libre d'une ligne : caractères de contrôle retirés, espaces resserrés, longueur bornée. */
    public static function cleanLabel(mixed $s, int $max): string
    {
        $s = is_string($s) ? $s : '';
        $s = (string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $s);
        $s = trim((string) preg_replace('/\s+/u', ' ', $s));

        return mb_substr($s, 0, $max);
    }

    /** Pseudo d'un contributeur (libre, pas un compte) : « Anonyme » s'il est vide. */
    public static function cleanAuthor(mixed $s): string
    {
        $a = self::cleanLabel($s, 32);

        return $a === '' ? 'Anonyme' : $a;
    }

    /** Traduction reçue : null si invalide (pas une chaîne, trop longue, UTF-8 cassé). Vide = phrase effacée. */
    public static function cleanText(mixed $s): ?string
    {
        if (!is_string($s) || mb_check_encoding($s, 'UTF-8') === false || mb_strlen($s) > self::MAX_TEXT) {
            return null;
        }

        return str_replace("\0", '', $s);
    }

    /**
     * Identifiant d'un objet Steam Workshop : chiffres seuls, ou tiré d'une adresse
     * steamcommunity.com/sharedfiles/filedetails/?id=…, steam://url/CommunityFilePage/…. '' : effacé ; null : invalide.
     */
    public static function workshopId(mixed $s): ?string
    {
        $s = is_string($s) ? trim($s) : '';
        if ($s === '') {
            return '';
        }
        if (preg_match('/^[0-9]{6,15}$/', $s) === 1) {
            return $s;
        }
        if (preg_match('~^(?:https?://(?:www\.)?steamcommunity\.com/(?:sharedfiles|workshop)/filedetails/?\?(?:[^#]*&)?id=([0-9]{6,15})|steam://url/CommunityFilePage/([0-9]{6,15}))(?:[&#/].*)?$~i', $s, $m) === 1) {
            return $m[1] !== '' ? $m[1] : $m[2];
        }

        return null;
    }

    /** Description du pack dans le Workshop : texte libre sur plusieurs lignes, contrôles retirés, 600 caractères. */
    public static function cleanDescription(mixed $s): string
    {
        $s = is_string($s) && mb_check_encoding($s, 'UTF-8') ? $s : '';
        $s = str_replace(["\r\n", "\r"], "\n", $s);
        $s = (string) preg_replace('/[\x00-\x09\x0B-\x1F\x7F]+/u', ' ', $s);
        $s = (string) preg_replace("/\n{3,}/", "\n\n", $s);

        return trim(mb_substr($s, 0, 600));
    }

    public static function newToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
    }

    public static function tokenHash(string $token): string
    {
        return hash('sha256', $token);
    }

    // ---------- Pack ----------

    /**
     * @param array{code: string, name: string, game: string, author_label: string, review_mode: string} $meta
     * @return array{public_id: string, owner_token: string, edit_token: string}
     */
    public function create(array $meta): array
    {
        $owner = self::newToken();
        $edit = self::newToken();
        for ($i = 0; $i < 5; $i++) {
            $publicId = '';
            $alphabet = 'abcdefghijkmnpqrstuvwxyz23456789';
            for ($j = 0; $j < 12; $j++) {
                $publicId .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            try {
                $st = $this->pdo()->prepare(
                    'INSERT INTO atak_translation_packs (public_id, lang_code, lang_name, game_language, author_label, review_mode, owner_token_hash, edit_token_hash)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $st->execute([
                    $publicId, $meta['code'], $meta['name'], $meta['game'], $meta['author_label'],
                    $meta['review_mode'] === 'review' ? 'review' : 'direct', self::tokenHash($owner), self::tokenHash($edit),
                ]);

                return ['public_id' => $publicId, 'owner_token' => $owner, 'edit_token' => $edit];
            } catch (\PDOException $e) {
                if ($i === 4 || !str_contains($e->getMessage(), 'uk_atak_tr_packs_public')) {
                    throw $e;
                }
            }
        }
        throw new \RuntimeException('pack id');
    }

    /**
     * Pack du compte pour cette langue, ou un pack neuf rattaché au compte (jamais listé au catalogue).
     * Un pack déjà ouvert seulement par son lien (user_id vide) n'est pas repris.
     *
     * @param array{code: string, name: string, game: string, author_label: string, review_mode: string} $meta
     * @return array{public_id: string, owner_token?: string, edit_token?: string, resumed: bool}
     */
    public function createForUser(int $userId, array $meta): array
    {
        $existing = $this->findUserPack($userId, $meta['code']);
        if ($existing !== null) {
            return ['public_id' => (string) $existing['public_id'], 'resumed' => true];
        }
        $created = $this->create($meta);
        $this->pdo()->prepare('UPDATE atak_translation_packs SET user_id = ? WHERE public_id = ?')->execute([$userId, $created['public_id']]);
        $created['resumed'] = false;

        return $created;
    }

    /** @return array<string, mixed>|null */
    public function findUserPack(int $userId, string $code): ?array
    {
        $st = $this->pdo()->prepare(
            'SELECT * FROM atak_translation_packs WHERE user_id = ? AND lang_code = ? ORDER BY updated_at DESC, id DESC LIMIT 1'
        );
        $st->execute([$userId, $code]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Pack de ce compte seulement. Un autre membre, ou un pack sans compte, ne passe pas.
     *
     * @return array<string, mixed>|null
     */
    public function ownedBy(string $publicId, int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }
        $st = $this->pdo()->prepare('SELECT * FROM atak_translation_packs WHERE public_id = ? AND user_id = ? LIMIT 1');
        $st->execute([$publicId, $userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Langues enregistrées sur le compte. Pas de jeton : un autre membre ne peut pas ouvrir ces lignes.
     *
     * @return list<array{id: string, code: string, name: string, game: string, author_label: string, rev: int, updated_at: string, translated: int}>
     */
    public function listForUser(int $userId): array
    {
        $st = $this->pdo()->prepare(
            'SELECT p.public_id, p.lang_code, p.lang_name, p.game_language, p.author_label, p.rev, p.updated_at,
                    (SELECT COUNT(*) FROM atak_translation_strings s WHERE s.pack_id = p.id AND s.text <> \'\') AS translated
             FROM atak_translation_packs p
             WHERE p.user_id = ?
             ORDER BY p.updated_at DESC, p.id DESC
             LIMIT 40'
        );
        $st->execute([$userId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[] = [
                'id' => (string) $row['public_id'],
                'code' => (string) $row['lang_code'],
                'name' => (string) $row['lang_name'],
                'game' => (string) $row['game_language'],
                'author_label' => (string) $row['author_label'],
                'rev' => (int) $row['rev'],
                'updated_at' => (string) $row['updated_at'],
                'translated' => (int) $row['translated'],
            ];
        }

        return $out;
    }

    /** Nouveau lien propriétaire : l'ancien ne donne plus accès. Le compte, lui, continue d'ouvrir le pack. */
    public function rotateOwnerToken(int $packId): string
    {
        $token = self::newToken();
        $st = $this->pdo()->prepare('UPDATE atak_translation_packs SET owner_token_hash = ?, updated_at = NOW() WHERE id = ?');
        $st->execute([self::tokenHash($token), $packId]);

        return $token;
    }

    /**
     * Pack ouvert par ce jeton, et rôle du jeton ; null si le pack n'existe pas ou si le jeton ne lui va pas.
     *
     * @return array{pack: array<string, mixed>, role: string}|null
     */
    public function open(string $publicId, string $token): ?array
    {
        if ($token === '' || strlen($token) > 64) {
            return null;
        }
        $st = $this->pdo()->prepare('SELECT * FROM atak_translation_packs WHERE public_id = ? LIMIT 1');
        $st->execute([$publicId]);
        $pack = $st->fetch(PDO::FETCH_ASSOC);
        if (!is_array($pack)) {
            return null;
        }
        $h = self::tokenHash($token);
        if (hash_equals((string) $pack['owner_token_hash'], $h)) {
            return ['pack' => $pack, 'role' => self::ROLE_OWNER];
        }
        if (hash_equals((string) $pack['edit_token_hash'], $h)) {
            return ['pack' => $pack, 'role' => self::ROLE_EDITOR];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $pack
     * @return array<string, mixed>
     */
    public static function publicMeta(array $pack): array
    {
        return [
            'id' => (string) $pack['public_id'],
            'code' => (string) $pack['lang_code'],
            'name' => (string) $pack['lang_name'],
            'game' => (string) $pack['game_language'],
            'author_label' => (string) $pack['author_label'],
            'review_mode' => (string) $pack['review_mode'],
            'created_at' => (string) $pack['created_at'],
            'rev' => (int) $pack['rev'],
            'listed' => (bool) ($pack['listed'] ?? false),
            'blocked' => (bool) ($pack['blocked'] ?? false),
            'description' => (string) ($pack['description'] ?? ''),
            'workshop_id' => (string) ($pack['workshop_id'] ?? ''),
            'downloads' => (int) ($pack['downloads'] ?? 0),
        ];
    }

    /** @param array{name: string, game: string, author_label: string, review_mode: string} $meta */
    public function updateSettings(int $packId, array $meta): void
    {
        $st = $this->pdo()->prepare(
            'UPDATE atak_translation_packs SET lang_name = ?, game_language = ?, author_label = ?, review_mode = ?, updated_at = NOW() WHERE id = ?'
        );
        $st->execute([$meta['name'], $meta['game'], $meta['author_label'], $meta['review_mode'] === 'review' ? 'review' : 'direct', $packId]);
    }

    /** Nouveau lien contributeur : l'ancien ne donne plus accès. */
    public function rotateEditToken(int $packId): string
    {
        $token = self::newToken();
        $st = $this->pdo()->prepare('UPDATE atak_translation_packs SET edit_token_hash = ?, updated_at = NOW() WHERE id = ?');
        $st->execute([self::tokenHash($token), $packId]);

        return $token;
    }

    public function delete(int $packId): void
    {
        $this->pdo()->prepare('DELETE FROM atak_translation_packs WHERE id = ?')->execute([$packId]);
    }

    /** Réserve le numéro d'écriture suivant du pack (dans la transaction en cours). */
    private function nextRev(int $packId): int
    {
        $this->pdo()->prepare('UPDATE atak_translation_packs SET rev = LAST_INSERT_ID(rev + 1), updated_at = NOW() WHERE id = ?')->execute([$packId]);

        return (int) $this->pdo()->query('SELECT LAST_INSERT_ID()')->fetchColumn();
    }

    // ---------- Lecture ----------

    /**
     * Tout ce qui a changé depuis $since : phrases, propositions (toutes, avec leur état), contributeurs.
     *
     * @return array{rev: int, strings: list<array<string, mixed>>, suggestions: list<array<string, mixed>>, contributors: list<array{name: string, count: int}>}
     */
    public function changes(int $packId, int $since): array
    {
        $st = $this->pdo()->prepare('SELECT rev FROM atak_translation_packs WHERE id = ?');
        $st->execute([$packId]);
        $rev = (int) $st->fetchColumn();

        $st = $this->pdo()->prepare(
            'SELECT key_hash AS h, text, author AS `by`, rev, UNIX_TIMESTAMP(updated_at) AS at FROM atak_translation_strings WHERE pack_id = ? AND rev > ? ORDER BY rev'
        );
        $st->execute([$packId, $since]);
        $strings = array_map(static fn (array $r): array => ['h' => $r['h'], 'text' => $r['text'], 'by' => $r['by'], 'rev' => (int) $r['rev'], 'at' => (int) $r['at']], $st->fetchAll(PDO::FETCH_ASSOC));

        // Propositions : au premier chargement, seules celles en attente ; ensuite toutes celles qui ont bougé
        // (une proposition acceptée ou refusée disparaît des écrans).
        $sql = 'SELECT id, key_hash AS h, text, author AS `by`, status, rev, UNIX_TIMESTAMP(created_at) AS at FROM atak_translation_suggestions WHERE pack_id = ? AND rev > ?'
            . ($since === 0 ? " AND status = 'pending'" : '') . ' ORDER BY id';
        $st = $this->pdo()->prepare($sql);
        $st->execute([$packId, $since]);
        $suggestions = array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'h' => $r['h'], 'text' => $r['text'], 'by' => $r['by'], 'status' => $r['status'], 'rev' => (int) $r['rev'], 'at' => (int) $r['at']], $st->fetchAll(PDO::FETCH_ASSOC));

        return ['rev' => $rev, 'strings' => $strings, 'suggestions' => $suggestions, 'contributors' => $this->contributors($packId)];
    }

    /** @return list<array{name: string, count: int}> pseudos par nombre de phrases écrites ou proposées */
    public function contributors(int $packId): array
    {
        $st = $this->pdo()->prepare(
            'SELECT author, COUNT(*) AS n FROM (
                SELECT author FROM atak_translation_history WHERE pack_id = ?
                UNION ALL SELECT author FROM atak_translation_suggestions WHERE pack_id = ? AND status = \'pending\'
             ) t GROUP BY author ORDER BY n DESC, author LIMIT 50'
        );
        $st->execute([$packId, $packId]);

        return array_map(static fn (array $r): array => ['name' => (string) $r['author'], 'count' => (int) $r['n']], $st->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> dernières modifications d'une phrase, la plus récente d'abord */
    public function history(int $packId, string $h): array
    {
        $st = $this->pdo()->prepare(
            'SELECT id, old_text, new_text, author AS `by`, action, UNIX_TIMESTAMP(created_at) AS at FROM atak_translation_history
             WHERE pack_id = ? AND key_hash = ? ORDER BY id DESC LIMIT ' . self::HISTORY_LIMIT
        );
        $st->execute([$packId, $h]);

        return array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'old' => $r['old_text'], 'new' => $r['new_text'], 'by' => $r['by'], 'action' => $r['action'], 'at' => (int) $r['at']], $st->fetchAll(PDO::FETCH_ASSOC));
    }

    // ---------- Écriture ----------

    /**
     * Écrit une phrase. $baseRev : version de la phrase que l'auteur avait sous les yeux (0 : aucune).
     *
     * @return array{ok: bool, rev?: int, conflict?: array{text: string, by: string, rev: int}}
     */
    public function write(int $packId, string $h, string $text, string $author, int $baseRev, string $action = 'edit'): array
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('SELECT text, author, rev FROM atak_translation_strings WHERE pack_id = ? AND key_hash = ? FOR UPDATE');
            $st->execute([$packId, $h]);
            $cur = $st->fetch(PDO::FETCH_ASSOC);
            $old = is_array($cur) ? (string) $cur['text'] : '';
            $curRev = is_array($cur) ? (int) $cur['rev'] : 0;
            if ($old === $text) {
                $pdo->commit();

                return ['ok' => true, 'rev' => $curRev];
            }
            if ($curRev !== $baseRev && $action === 'edit') {
                $pdo->commit();

                return ['ok' => false, 'conflict' => ['text' => $old, 'by' => is_array($cur) ? (string) $cur['author'] : '', 'rev' => $curRev]];
            }
            $rev = $this->nextRev($packId);
            $pdo->prepare(
                'INSERT INTO atak_translation_strings (pack_id, key_hash, text, author, rev, updated_at) VALUES (?, ?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE text = VALUES(text), author = VALUES(author), rev = VALUES(rev), updated_at = NOW()'
            )->execute([$packId, $h, $text, $author, $rev]);
            $pdo->prepare('INSERT INTO atak_translation_history (pack_id, key_hash, old_text, new_text, author, action) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$packId, $h, $old, $text, $author, $action]);
            $pdo->commit();

            return ['ok' => true, 'rev' => $rev];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Proposition d'un contributeur (pack en mode « review ») : une seule en attente par phrase et par pseudo. */
    public function suggest(int $packId, string $h, string $text, string $author): int
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $rev = $this->nextRev($packId);
            // L'ancienne proposition du même pseudo pour cette phrase est remplacée.
            $pdo->prepare("UPDATE atak_translation_suggestions SET status = 'rejected', rev = ?, decided_at = NOW() WHERE pack_id = ? AND key_hash = ? AND author = ? AND status = 'pending'")
                ->execute([$rev, $packId, $h, $author]);
            if ($text !== '') {
                $pdo->prepare('INSERT INTO atak_translation_suggestions (pack_id, key_hash, text, author, rev) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$packId, $h, $text, $author, $rev]);
            }
            $pdo->commit();

            return $rev;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Décision du propriétaire sur une proposition. Acceptée : la phrase prend son texte (au nom de son auteur).
     *
     * @return array<string, mixed>|null la proposition, null si introuvable ou déjà tranchée
     */
    public function decide(int $packId, int $suggestionId, bool $accept): ?array
    {
        $st = $this->pdo()->prepare("SELECT * FROM atak_translation_suggestions WHERE id = ? AND pack_id = ? AND status = 'pending'");
        $st->execute([$suggestionId, $packId]);
        $s = $st->fetch(PDO::FETCH_ASSOC);
        if (!is_array($s)) {
            return null;
        }
        if ($accept) {
            $this->write($packId, (string) $s['key_hash'], (string) $s['text'], (string) $s['author'], 0, 'accept');
        }
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $rev = $this->nextRev($packId);
            $pdo->prepare('UPDATE atak_translation_suggestions SET status = ?, rev = ?, decided_at = NOW() WHERE id = ?')
                ->execute([$accept ? 'accepted' : 'rejected', $rev, $suggestionId]);
            // Accepter une proposition clôt les autres de la même phrase.
            if ($accept) {
                $pdo->prepare("UPDATE atak_translation_suggestions SET status = 'rejected', rev = ?, decided_at = NOW() WHERE pack_id = ? AND key_hash = ? AND status = 'pending'")
                    ->execute([$rev, $packId, $s['key_hash']]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return $s;
    }

    /** Revenir à une ancienne version d'une phrase (historique), par le propriétaire. */
    public function revert(int $packId, int $historyId, string $author): ?int
    {
        $st = $this->pdo()->prepare('SELECT key_hash, old_text FROM atak_translation_history WHERE id = ? AND pack_id = ?');
        $st->execute([$historyId, $packId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        $r = $this->write($packId, (string) $row['key_hash'], (string) $row['old_text'], $author, 0, 'revert');

        return $r['rev'] ?? null;
    }

    /**
     * Import en lot par le propriétaire (fichier .txt, travail local) : remplace les phrases données, sans
     * contrôle de conflit ni historique ligne à ligne (un import de 12 000 phrases resterait sinon illisible).
     *
     * @param array<string, string> $items clé => texte (déjà validés)
     */
    public function bulk(int $packId, array $items, string $author, bool $overwrite): int
    {
        if ($items === []) {
            return 0;
        }
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $rev = $this->nextRev($packId);
            $n = 0;
            $sql = $overwrite
                ? 'INSERT INTO atak_translation_strings (pack_id, key_hash, text, author, rev, updated_at) VALUES (?, ?, ?, ?, ?, NOW())
                   ON DUPLICATE KEY UPDATE text = VALUES(text), author = VALUES(author), rev = VALUES(rev), updated_at = NOW()'
                : 'INSERT IGNORE INTO atak_translation_strings (pack_id, key_hash, text, author, rev, updated_at) VALUES (?, ?, ?, ?, ?, NOW())';
            $st = $pdo->prepare($sql);
            foreach ($items as $h => $text) {
                $st->execute([$packId, $h, $text, $author, $rev]);
                $n += $st->rowCount() > 0 ? 1 : 0;
            }
            $pdo->commit();

            return $n;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    // ---------- Workshop des langues ----------

    /** @return array<string, string> phrases actuelles du pack (clé => texte), sans les vides */
    public function strings(int $packId): array
    {
        $st = $this->pdo()->prepare("SELECT key_hash, text FROM atak_translation_strings WHERE pack_id = ? AND text <> ''");
        $st->execute([$packId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(string) $r['key_hash']] = (string) $r['text'];
        }

        return $out;
    }

    /**
     * Fige une nouvelle version du pack (v1, v2…) et le met dans le catalogue.
     *
     * @param array<string, string> $map phrases publiées (clé => texte)
     * @param array{atak_version: string, template_fp: string, template_total: int, translated: int, note: string, audit_fp: string, audit_json: string} $info
     * @param array{description: string, workshop_id: string} $listing
     */
    public function publish(int $packId, array $map, array $info, array $listing): int
    {
        $payload = gzcompress((string) json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 6);
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('SELECT COALESCE(MAX(version), 0) + 1 FROM atak_translation_releases WHERE pack_id = ? FOR UPDATE');
            $st->execute([$packId]);
            $version = (int) $st->fetchColumn();
            $pdo->prepare(
                'INSERT INTO atak_translation_releases (pack_id, version, atak_version, template_fp, template_total, translated, note, payload, audit_fp, audit_json)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $packId, $version, $info['atak_version'], $info['template_fp'], $info['template_total'], $info['translated'],
                $info['note'], $payload, $info['audit_fp'], $info['audit_json'],
            ]);
            $pdo->prepare('UPDATE atak_translation_packs SET listed = 1, description = ?, workshop_id = ?, published_at = NOW(), updated_at = NOW() WHERE id = ?')
                ->execute([$listing['description'], $listing['workshop_id'], $packId]);
            $pdo->commit();

            return $version;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Fiche du catalogue sans nouvelle version : retrait / remise en ligne, description, objet Steam. */
    public function updateListing(int $packId, bool $listed, string $description, string $workshopId): void
    {
        $this->pdo()->prepare('UPDATE atak_translation_packs SET listed = ?, description = ?, workshop_id = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$listed ? 1 : 0, $description, $workshopId, $packId]);
    }

    /** Retrait par l'administration du site (contenu inapproprié) : le pack disparaît du catalogue. */
    public function setBlocked(int $packId, bool $blocked): void
    {
        $this->pdo()->prepare('UPDATE atak_translation_packs SET blocked = ? WHERE id = ?')->execute([$blocked ? 1 : 0, $packId]);
    }

    public function countDownload(int $packId): void
    {
        $this->pdo()->prepare('UPDATE atak_translation_packs SET downloads = downloads + 1 WHERE id = ?')->execute([$packId]);
    }

    private const RELEASE_COLS = 'r.id AS release_id, r.version, r.atak_version, r.template_fp, r.template_total, r.translated, r.note, r.audit_fp, r.audit_json, r.created_at AS released_at';

    /**
     * Packs du catalogue, chacun avec sa dernière version.
     *
     * @return list<array<string, mixed>>
     */
    public function catalog(): array
    {
        $st = $this->pdo()->query(
            'SELECT p.id, p.public_id, p.lang_code, p.lang_name, p.game_language, p.author_label, p.description, p.workshop_id, p.downloads, p.published_at, ' . self::RELEASE_COLS . '
             FROM atak_translation_packs p
             JOIN atak_translation_releases r ON r.pack_id = p.id
              AND r.version = (SELECT MAX(r2.version) FROM atak_translation_releases r2 WHERE r2.pack_id = p.id)
             WHERE p.listed = 1 AND p.blocked = 0
             ORDER BY p.published_at DESC LIMIT 300'
        );

        return $st === false ? [] : array_values($st->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Fiche publique d'un pack (dernière version) ; null s'il n'a jamais été publié, ou retiré (sauf $evenHidden).
     *
     * @return array<string, mixed>|null
     */
    public function listedPack(string $publicId, bool $evenHidden = false): ?array
    {
        $st = $this->pdo()->prepare(
            'SELECT p.id, p.public_id, p.lang_code, p.lang_name, p.game_language, p.author_label, p.description, p.workshop_id, p.downloads, p.published_at, p.listed, p.blocked, ' . self::RELEASE_COLS . '
             FROM atak_translation_packs p
             JOIN atak_translation_releases r ON r.pack_id = p.id
              AND r.version = (SELECT MAX(r2.version) FROM atak_translation_releases r2 WHERE r2.pack_id = p.id)
             WHERE p.public_id = ? LIMIT 1'
        );
        $st->execute([$publicId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        if (!$evenHidden && ((int) $row['listed'] !== 1 || (int) $row['blocked'] === 1)) {
            return null;
        }

        return $row;
    }

    /** @return array<string, mixed>|null dernière version publiée d'un pack */
    public function latestRelease(int $packId): ?array
    {
        $st = $this->pdo()->prepare(
            'SELECT r.id AS release_id, r.version, r.atak_version, r.template_fp, r.template_total, r.translated, r.note, r.audit_fp, r.audit_json, r.created_at AS released_at
             FROM atak_translation_releases r WHERE r.pack_id = ? ORDER BY r.version DESC LIMIT 1'
        );
        $st->execute([$packId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return list<array<string, mixed>> versions publiées, la plus récente d'abord (journal des versions) */
    public function releases(int $packId, int $limit = 20): array
    {
        $st = $this->pdo()->prepare(
            'SELECT version, atak_version, template_total, translated, note, created_at FROM atak_translation_releases WHERE pack_id = ? ORDER BY version DESC LIMIT ' . max(1, $limit)
        );
        $st->execute([$packId]);

        return array_values($st->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, string> phrases d'une version publiée (clé => texte) */
    public function releasePayload(int $releaseId): array
    {
        $st = $this->pdo()->prepare('SELECT payload FROM atak_translation_releases WHERE id = ?');
        $st->execute([$releaseId]);
        $raw = $st->fetchColumn();
        $json = is_string($raw) ? @gzuncompress($raw) : false;
        $data = is_string($json) ? json_decode($json, true) : null;
        $out = [];
        foreach (is_array($data) ? $data : [] as $h => $t) {
            if (is_string($t)) {
                $out[(string) $h] = $t;
            }
        }

        return $out;
    }

    /**
     * Garde les clés du modèle du mod sous son empreinte (une fois par version du modèle).
     *
     * @param list<string> $keys
     */
    public function saveTemplateKeys(string $fp, string $atakVersion, array $keys): void
    {
        $this->pdo()->prepare('INSERT IGNORE INTO atak_translation_template_keys (fp, atak_version, total, keys_gz) VALUES (?, ?, ?, ?)')
            ->execute([$fp, $atakVersion, count($keys), gzcompress(implode('', $keys), 6)]);
    }

    /** @return list<string>|null clés du modèle de cette empreinte, null si elle n'a pas été gardée */
    public function templateKeys(string $fp): ?array
    {
        $st = $this->pdo()->prepare('SELECT keys_gz FROM atak_translation_template_keys WHERE fp = ?');
        $st->execute([$fp]);
        $raw = $st->fetchColumn();
        $flat = is_string($raw) ? @gzuncompress($raw) : false;
        if (!is_string($flat)) {
            return null;
        }

        return $flat === '' ? [] : str_split($flat, 16);
    }

    /** Résultat du contrôle d'une version contre le modèle actuel du mod (gardé jusqu'à la prochaine mise à jour). */
    public function saveAudit(int $releaseId, string $fp, string $json): void
    {
        $this->pdo()->prepare('UPDATE atak_translation_releases SET audit_fp = ?, audit_json = ? WHERE id = ?')->execute([$fp, $json, $releaseId]);
    }
}
