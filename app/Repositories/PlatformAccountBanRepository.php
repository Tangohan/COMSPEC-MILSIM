<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\Migrations\SchemaInspector;
use PDO;
use WeakMap;

/**
 * Bannissements décidés par l’administration du site (une ligne par décision).
 *
 * Une décision est « en cours » tant qu’elle n’est ni levée (`lifted_at`) ni arrivée à échéance (`ends_at`).
 * Les heures sont en UTC, comme la session MySQL de l’application.
 */
final class PlatformAccountBanRepository
{
    private ?PDO $pdo;

    /** @var callable(): int */
    private $clock;

    /** @var WeakMap<PDO, array{ready: bool, ends: bool}>|null */
    private static ?WeakMap $state = null;

    public function __construct(?PDO $pdo = null, ?callable $clock = null)
    {
        $this->pdo = $pdo;
        $this->clock = $clock ?? static fn (): int => time();
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    /** Heure courante (UTC) au format de la base. */
    private function now(): string
    {
        return gmdate('Y-m-d H:i:s', ($this->clock)());
    }

    /**
     * Crée la table et complète ses colonnes au premier usage, une fois par requête et par connexion
     * (l’étape de la file de migrations reste la voie officielle ; ceci est le filet de sécurité).
     */
    public function ready(): bool
    {
        $pdo = $this->pdo();
        self::$state ??= new WeakMap();
        if (isset(self::$state[$pdo])) {
            return self::$state[$pdo]['ready'];
        }
        $ready = false;
        $ends = false;
        // Cas courant (base à jour) : une seule requête légère, sans parcourir le schéma.
        try {
            $probe = $pdo->query('SELECT ends_at FROM platform_account_bans WHERE 1 = 0');
            if ($probe !== false) {
                self::$state[$pdo] = ['ready' => true, 'ends' => true];

                return true;
            }
        } catch (\Throwable) {
            // Table ou colonne absente : on passe par la mise à niveau ci-dessous.
        }
        try {
            require_once dirname(__DIR__, 2) . '/bootstrap/platform_account_bans_migration.php';
            ob_start();
            run_platform_account_bans_migration($pdo);
            ob_end_clean();
            $schema = new SchemaInspector($pdo);
            $ready = $schema->tableExists('platform_account_bans');
            $ends = $ready && $schema->columnExists('platform_account_bans', 'ends_at');
        } catch (\Throwable) {
            $ready = false;
            $ends = false;
        }
        self::$state[$pdo] = ['ready' => $ready, 'ends' => $ends];

        return $ready;
    }

    /** Vrai si la table sait porter une date de fin (étape de migration passée). */
    public function supportsEndDate(): bool
    {
        return $this->ready() && self::$state !== null && (self::$state[$this->pdo()]['ends'] ?? false);
    }

    /** Prédicat « décision pas encore arrivée à échéance » (sans effet tant que la colonne manque). */
    private function notExpiredSql(string $alias = ''): string
    {
        if (!$this->supportsEndDate()) {
            return '1 = 1';
        }
        $col = ($alias !== '' ? $alias . '.' : '') . 'ends_at';

        return '(' . $col . ' IS NULL OR ' . $col . ' > ' . $this->quoteNow() . ')';
    }

    private function quoteNow(): string
    {
        return $this->pdo()->quote($this->now());
    }

    /**
     * @param list<int> $indicatorIds
     * @param array<int, string> $statusSnapshot user_id => statut avant bannissement
     * @param string|null $endsAt date de fin (UTC, « Y-m-d H:i:s ») ; null = sans fin
     */
    public function create(
        string $email,
        ?int $userId,
        string $displayLabel,
        string $reason,
        array $indicatorIds,
        array $statusSnapshot,
        ?int $actorId,
        ?string $endsAt = null
    ): int {
        if (!$this->ready()) {
            return 0;
        }
        $fields = 'email_norm, user_id, display_label, reason, indicator_ids, status_snapshot, banned_by_user_id, created_at';
        $marks = '?, ?, ?, ?, ?, ?, ?, ?';
        $values = [
            self::normalizeEmail($email),
            $userId,
            mb_substr($displayLabel, 0, 255),
            $reason,
            json_encode(array_values(array_map('intval', $indicatorIds))),
            json_encode($statusSnapshot, JSON_UNESCAPED_UNICODE),
            $actorId,
            $this->now(),
        ];
        if ($endsAt !== null && $this->supportsEndDate()) {
            $fields .= ', ends_at';
            $marks .= ', ?';
            $values[] = $endsAt;
        }
        $st = $this->pdo()->prepare('INSERT INTO platform_account_bans (' . $fields . ') VALUES (' . $marks . ')');
        $st->execute($values);

        return (int) $this->pdo()->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function findActiveForEmail(string $email): ?array
    {
        $email = self::normalizeEmail($email);
        if ($email === '' || !$this->ready()) {
            return null;
        }
        $st = $this->pdo()->prepare(
            'SELECT * FROM platform_account_bans WHERE email_norm = ? AND lifted_at IS NULL AND '
            . $this->notExpiredSql() . ' ORDER BY id DESC LIMIT 1'
        );
        $st->execute([$email]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Décisions arrivées à échéance et pas encore levées : leurs fiches doivent être remises en état.
     *
     * @return list<array<string, mixed>>
     */
    public function listDue(?string $email = null, int $limit = 50): array
    {
        if (!$this->supportsEndDate()) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT * FROM platform_account_bans WHERE lifted_at IS NULL AND ends_at IS NOT NULL AND ends_at <= ' . $this->quoteNow();
        $params = [];
        if ($email !== null) {
            $norm = self::normalizeEmail($email);
            if ($norm === '') {
                return [];
            }
            $sql .= ' AND email_norm = ?';
            $params[] = $norm;
        }
        $st = $this->pdo()->prepare($sql . ' ORDER BY ends_at ASC, id ASC LIMIT ' . $limit);
        $st->execute($params);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Bannissements en cours indexés par e-mail normalisé.
     *
     * @param list<string> $emails
     * @return array<string, array<string, mixed>>
     */
    public function activeByEmails(array $emails): array
    {
        $emails = array_values(array_unique(array_filter(array_map([self::class, 'normalizeEmail'], $emails))));
        if ($emails === [] || !$this->ready()) {
            return [];
        }
        $in = implode(',', array_fill(0, count($emails), '?'));
        $st = $this->pdo()->prepare(
            "SELECT * FROM platform_account_bans WHERE lifted_at IS NULL AND {$this->notExpiredSql()} AND email_norm IN ({$in}) ORDER BY id ASC"
        );
        $st->execute($emails);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[(string) $row['email_norm']] = $row;
        }

        return $out;
    }

    /**
     * Nombre de décisions déjà prises (en cours, levées ou échues) par e-mail normalisé.
     *
     * @param list<string> $emails
     * @return array<string, int>
     */
    public function countsByEmails(array $emails): array
    {
        $emails = array_values(array_unique(array_filter(array_map([self::class, 'normalizeEmail'], $emails))));
        if ($emails === [] || !$this->ready()) {
            return [];
        }
        $in = implode(',', array_fill(0, count($emails), '?'));
        $st = $this->pdo()->prepare(
            "SELECT email_norm, COUNT(*) AS n FROM platform_account_bans WHERE email_norm IN ({$in}) GROUP BY email_norm"
        );
        $st->execute($emails);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[(string) $row['email_norm']] = (int) $row['n'];
        }

        return $out;
    }

    /**
     * Historique complet d’une personne, du plus récent au plus ancien, avec le nom de ceux qui ont décidé et levé.
     * Seuls un nom affiché et un indicatif sont remontés, jamais l’e-mail des administrateurs.
     *
     * @return list<array<string, mixed>>
     */
    public function historyForEmail(string $email, int $limit = 100): array
    {
        $email = self::normalizeEmail($email);
        if ($email === '' || !$this->ready()) {
            return [];
        }
        $limit = max(1, min(300, $limit));
        $ends = $this->supportsEndDate() ? 'b.ends_at, b.lift_note' : 'NULL AS ends_at, NULL AS lift_note';
        $st = $this->pdo()->prepare(
            "SELECT b.id, b.email_norm, b.reason, b.created_at, b.lifted_at, {$ends},
                    b.banned_by_user_id, b.lifted_by_user_id,
                    ab.display_name AS banned_by_name, ab.callsign AS banned_by_callsign,
                    al.display_name AS lifted_by_name, al.callsign AS lifted_by_callsign
             FROM platform_account_bans b
             LEFT JOIN users ab ON ab.id = b.banned_by_user_id
             LEFT JOIN users al ON al.id = b.lifted_by_user_id
             WHERE b.email_norm = ?
             ORDER BY b.created_at DESC, b.id DESC
             LIMIT {$limit}"
        );
        $st->execute([$email]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listActive(int $limit = 200): array
    {
        if (!$this->ready()) {
            return [];
        }
        $limit = max(1, min(500, $limit));
        $st = $this->pdo()->query(
            'SELECT * FROM platform_account_bans WHERE lifted_at IS NULL AND ' . $this->notExpiredSql()
            . ' ORDER BY created_at DESC, id DESC LIMIT ' . $limit
        );

        return $st ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    public function countActive(): int
    {
        if (!$this->ready()) {
            return 0;
        }
        $st = $this->pdo()->query('SELECT COUNT(*) FROM platform_account_bans WHERE lifted_at IS NULL AND ' . $this->notExpiredSql());

        return $st ? (int) $st->fetchColumn() : 0;
    }

    /**
     * Marque la décision comme levée. Sans acteur (échéance atteinte), la levée est automatique.
     * Renvoie faux si elle était déjà levée : l’appelant ne remet alors rien en état une seconde fois.
     */
    public function lift(int $id, ?int $actorId, ?string $note = null): bool
    {
        if ($id < 1 || !$this->ready()) {
            return false;
        }
        $note = $note !== null ? mb_substr(trim($note), 0, 500) : '';
        $sql = 'UPDATE platform_account_bans SET lifted_at = ?, lifted_by_user_id = ?'
            . ($this->supportsEndDate() ? ', lift_note = ?' : '') . ' WHERE id = ? AND lifted_at IS NULL';
        $params = [$this->now(), $actorId];
        if ($this->supportsEndDate()) {
            $params[] = $note !== '' ? $note : null;
        }
        $params[] = $id;
        $st = $this->pdo()->prepare($sql);
        $st->execute($params);

        return $st->rowCount() > 0;
    }

    /** Prédicat SQL « e-mail actuellement banni » pour l’annuaire (null si la table n’existe pas). */
    public function sqlActiveBanExists(string $emailColumn): ?string
    {
        if (!$this->ready()) {
            return null;
        }

        return 'EXISTS (SELECT 1 FROM platform_account_bans __pab WHERE __pab.lifted_at IS NULL AND '
            . $this->notExpiredSql('__pab') . ' AND __pab.email_norm = LOWER(TRIM(' . $emailColumn . ')))';
    }

    /** Prédicat SQL « e-mail déjà sanctionné au moins une fois » (null si la table n’existe pas). */
    public function sqlAnyBanExists(string $emailColumn): ?string
    {
        if (!$this->ready()) {
            return null;
        }

        return 'EXISTS (SELECT 1 FROM platform_account_bans __pab2 WHERE __pab2.email_norm = LOWER(TRIM(' . $emailColumn . ')))';
    }

    /** Sous-requête « e-mails actuellement bannis » pour une jointure de tri (null si la table n’existe pas). */
    public function sqlActiveBanEmailsDerived(): ?string
    {
        if (!$this->ready()) {
            return null;
        }

        return 'SELECT DISTINCT email_norm FROM platform_account_bans WHERE lifted_at IS NULL AND ' . $this->notExpiredSql();
    }

    /** Sous-requête « nombre de sanctions par e-mail » pour une jointure de tri (null si la table n’existe pas). */
    public function sqlBanCountsDerived(): ?string
    {
        if (!$this->ready()) {
            return null;
        }

        return 'SELECT email_norm, COUNT(*) AS n FROM platform_account_bans GROUP BY email_norm';
    }

    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }
}
