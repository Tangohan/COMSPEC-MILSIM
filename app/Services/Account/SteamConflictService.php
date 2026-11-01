<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Repositories\MemberNotificationRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Support\LazyDatabaseConnection;
use App\Support\SteamId;
use PDO;

/**
 * Un même compte Steam porté par les fiches de deux personnes différentes (deux e-mails, donc deux comptes Athena).
 *
 * Le jeu reconnaît le joueur par son Steam : avec deux titulaires, il ne sait pas quel compte ouvrir, et la connexion
 * par Steam est refusée (GameAuthService::steamExchange). Les fiches d'une même personne dans plusieurs communautés
 * (même e-mail) ne sont pas un doublon : elles mènent au même compte Athena.
 *
 * Le joueur choisit (page /account/steam-doublon, imposée tant que rien n'est réglé) :
 * - keepHere : garder Steam ici, uniquement avec la preuve Steam (connexion Steam) ; retiré des autres titulaires ;
 * - release : « c'est mon autre compte » ; Steam retiré de ses propres fiches seulement ;
 * - report : « je ne reconnais pas l'autre compte » ; Steam mis de côté sur ses fiches, l'administration examine.
 * Jamais de retrait chez un tiers sans preuve de possession du Steam.
 */
class SteamConflictService
{
    use LazyDatabaseConnection;

    public const STATE_NONE = 'none';
    public const STATE_PENDING = 'pending';
    public const STATE_REPORTED = 'reported';

    /** Pages ouvertes pendant le choix : la page elle-même, la preuve Steam, la déconnexion, les pages légales. */
    private const OPEN_PREFIXES = [
        '/account/steam-doublon',
        '/account/steam/',
        '/account/donnees',
        '/logout',
        '/login',
        '/locale/',
        '/legal',
        '/cgu',
        '/mentions-legales',
        '/api/',
        '/assets/',
        '/admin/steam-doublons',
    ];

    public function __construct(
        ?PDO $pdo = null,
        private ?AuditService $audit = null,
        private ?MemberNotificationRepository $notifications = null,
    ) {
        $this->pdo = $pdo;
    }

    protected function onDatabaseConnected(PDO $pdo): void
    {
        \App\Support\SilentSchemaMigration::run(base_path('bootstrap/steam_conflict_reports_migration.php'), $pdo);
    }

    public static function pathIsOpen(string $path): bool
    {
        foreach (self::OPEN_PREFIXES as $prefix) {
            if ($path === rtrim($prefix, '/') || str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fiches vivantes d'autres personnes qui portent ce Steam.
     *
     * @return list<array{id: int, tenant_id: int, email: string, status: string, callsign: string, created_at: string}>
     */
    public function holders(string $steamId, string $exceptEmail = ''): array
    {
        $steam = SteamId::normalize($steamId);
        if ($steam === null) {
            return [];
        }
        $st = $this->pdo()->prepare(
            "SELECT id, tenant_id, email, status, callsign, created_at FROM users
             WHERE steam_id = ? AND deleted_at IS NULL AND email NOT LIKE '%@deleted.invalid'
             ORDER BY id"
        );
        $st->execute([$steam]);
        $except = self::email($exceptEmail);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            if ($except !== '' && self::email((string) $r['email']) === $except) {
                continue;
            }
            $out[] = [
                'id' => (int) $r['id'],
                'tenant_id' => (int) $r['tenant_id'],
                'email' => (string) $r['email'],
                'status' => (string) ($r['status'] ?? ''),
                'callsign' => trim((string) ($r['callsign'] ?? '')),
                'created_at' => (string) ($r['created_at'] ?? ''),
            ];
        }

        return $out;
    }

    /** Nombre de personnes (e-mails distincts) qui portent ce Steam : au-delà de 1, le jeu ne sait pas qui ouvrir. */
    public function ownerCount(string $steamId): int
    {
        $emails = [];
        foreach ($this->holders($steamId) as $h) {
            $emails[self::email($h['email'])] = true;
        }

        return count($emails);
    }

    /**
     * Doublon vu depuis la fiche connectée, ou null.
     *
     * @param array<string, mixed> $user ligne users (id, email, steam_id)
     * @return array{steam_id: string, others: list<array{id: int, tenant_id: int, email: string, status: string, callsign: string, created_at: string}>}|null
     */
    public function conflictFor(array $user): ?array
    {
        $steam = SteamId::normalize((string) ($user['steam_id'] ?? ''));
        $email = self::email((string) ($user['email'] ?? ''));
        if ($steam === null || $email === '') {
            return null;
        }
        $others = $this->holders($steam, $email);

        return $others === [] ? null : ['steam_id' => $steam, 'others' => $others];
    }

    /**
     * Choix « garder Steam ici ». $provenSteam vient de la connexion Steam (OpenID vérifié), jamais d'une saisie.
     *
     * @param array<string, mixed> $user
     * @return array{ok: bool, message: string, removed: list<int>}
     */
    public function keepHere(array $user, string $provenSteam): array
    {
        $steam = SteamId::normalize($provenSteam);
        $userId = (int) ($user['id'] ?? 0);
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        $email = self::email((string) ($user['email'] ?? ''));
        if ($steam === null || !SteamId::isSteam64($steam) || $userId < 1 || $email === '') {
            return ['ok' => false, 'message' => 'Steam n’a pas confirmé de compte reconnaissable.', 'removed' => []];
        }
        $others = $this->holders($steam, $email);
        foreach ($others as $o) {
            if (strtolower($o['status']) !== 'active') {
                return [
                    'ok' => false,
                    'message' => 'L’autre compte Athena est suspendu : l’administration doit trancher. Choisissez « Je ne reconnais pas l’autre compte » pour la prévenir.',
                    'removed' => [],
                ];
            }
        }
        $pdo = $this->pdo();
        $now = gmdate('Y-m-d H:i:s');
        $removed = [];
        $pdo->beginTransaction();
        try {
            $clear = $pdo->prepare('UPDATE users SET steam_id = NULL WHERE id = ? AND steam_id = ?');
            foreach ($others as $o) {
                $clear->execute([$o['id'], $steam]);
                if ($clear->rowCount() > 0) {
                    $removed[] = $o['id'];
                }
            }
            // Le compte Athena global garde une copie du Steam (unique) : la libérer, sinon le jeu ne peut pas la reprendre ici.
            $pdo->prepare('UPDATE athena_accounts SET steam_id = NULL WHERE steam_id = ? AND LOWER(TRIM(email)) <> ?')
                ->execute([$steam, $email]);
            // Un jeu ouvert sur l'autre compte avec ce Steam se reconnecte : il retombera sur le bon compte.
            if ($removed !== []) {
                $pdo->prepare(
                    'UPDATE game_sessions SET revoked_at = ? WHERE steam_id = ? AND revoked_at IS NULL AND user_id IN ('
                    . implode(',', array_fill(0, count($removed), '?')) . ')'
                )->execute([$now, $steam, ...$removed]);
            }
            $pdo->prepare('UPDATE users SET steam_id = ? WHERE id = ?')->execute([$steam, $userId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        try {
            // La preuve Steam règle aussi un signalement resté ouvert sur ce Steam.
            $pdo->prepare("UPDATE steam_conflict_reports SET status = 'resolved', resolved_at = ?, resolved_by = ? WHERE steam_id = ? AND status = 'open'")
                ->execute([$now, $userId, $steam]);
        } catch (\Throwable) {
            // Table des signalements pas encore créée : rien à clore.
        }

        $this->log(AuditAction::USER_STEAM_CONFLICT_KEPT, $tenantId, $userId, $userId, $steam, json_encode(['removed_from' => $removed]) ?: '');
        foreach ($others as $o) {
            if (!in_array($o['id'], $removed, true)) {
                continue;
            }
            $this->log(AuditAction::USER_STEAM_REMOVED_BY_OWNER, $o['tenant_id'], $userId, $o['id'], $steam, null);
            try {
                $this->notifications()->create($o['tenant_id'], $o['id'], 'steam_removed_by_owner', ['steam_end' => substr($steam, -4)]);
            } catch (\Throwable) {
                // La notification ne doit pas annuler une correction déjà enregistrée.
            }
        }

        return ['ok' => true, 'message' => 'C’est réglé : votre compte Steam est maintenant relié à ce compte uniquement. Le jeu vous reconnaîtra à la prochaine connexion.', 'removed' => $removed];
    }

    /**
     * Choix « c'est mon autre compte » : Steam retiré des fiches de cette personne (son propre compte, pas de preuve requise).
     *
     * @param array<string, mixed> $user
     * @return list<int> fiches dont le Steam a été retiré
     */
    public function release(array $user): array
    {
        $steam = SteamId::normalize((string) ($user['steam_id'] ?? ''));
        if ($steam === null) {
            return [];
        }
        $removed = $this->clearOwnRows($user, $steam);
        $this->log(AuditAction::USER_STEAM_CONFLICT_RELEASED, (int) ($user['tenant_id'] ?? 0), (int) $user['id'], (int) $user['id'], $steam, null);

        return $removed;
    }

    /**
     * Choix « je ne reconnais pas l'autre compte » : Steam mis de côté sur les fiches de cette personne (rendu par
     * l'administration si besoin), signalement ouvert. L'autre compte n'est pas touché : aucune preuve contre lui.
     *
     * @param array<string, mixed> $user
     */
    public function report(array $user): int
    {
        $conflict = $this->conflictFor($user);
        $steam = SteamId::normalize((string) ($user['steam_id'] ?? ''));
        if ($steam === null) {
            return 0;
        }
        $others = array_map(static fn (array $o): int => $o['id'], $conflict['others'] ?? []);
        $pdo = $this->pdo();
        // Signalement et mise de côté ensemble : jamais de Steam retiré sans trace pour le rendre.
        $pdo->beginTransaction();
        try {
            $removed = $this->clearOwnRows($user, $steam);
            $pdo->prepare(
                "INSERT INTO steam_conflict_reports (steam_id, reporter_user_id, reporter_tenant_id, removed_user_ids, other_user_ids, status, created_at)
                 VALUES (?, ?, ?, ?, ?, 'open', ?)"
            )->execute([$steam, (int) $user['id'], (int) ($user['tenant_id'] ?? 0), implode(',', $removed), implode(',', $others), gmdate('Y-m-d H:i:s')]);
            $id = (int) $pdo->lastInsertId();
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        $this->log(AuditAction::USER_STEAM_CONFLICT_REPORTED, (int) ($user['tenant_id'] ?? 0), (int) $user['id'], (int) $user['id'], $steam, json_encode(['report' => $id, 'others' => $others]) ?: '');

        return $id;
    }

    /**
     * État d'un Steam pour l'administration.
     */
    public function stateFor(string $steamId): string
    {
        $steam = SteamId::normalize($steamId);
        if ($steam === null) {
            return self::STATE_NONE;
        }
        if (isset($this->openReportSteams()[$steam])) {
            return self::STATE_REPORTED;
        }

        return $this->ownerCount($steam) > 1 ? self::STATE_PENDING : self::STATE_NONE;
    }

    /** @return array<string, true> Steam des signalements ouverts */
    public function openReportSteams(): array
    {
        $out = [];
        try {
            foreach ($this->pdo()->query("SELECT steam_id FROM steam_conflict_reports WHERE status = 'open'")?->fetchAll(PDO::FETCH_COLUMN) ?: [] as $s) {
                $out[(string) $s] = true;
            }
        } catch (\Throwable) {
            // Table pas encore créée : aucun signalement.
        }

        return $out;
    }

    /**
     * Vue d'ensemble pour l'administration : Steam en double + Steam signalés, avec leurs fiches.
     *
     * @return list<array{steam_id: string, state: string, holders: list<array<string, mixed>>, reports: list<array<string, mixed>>}>
     */
    public function adminCases(?string $onlySteam = null): array
    {
        $steams = [];
        $only = $onlySteam !== null ? SteamId::normalize($onlySteam) : null;
        if ($only !== null) {
            $steams[$only] = true;
        } else {
            foreach ($this->pdo()->query(
                "SELECT steam_id FROM users
                 WHERE steam_id IS NOT NULL AND steam_id <> '' AND deleted_at IS NULL AND email NOT LIKE '%@deleted.invalid'
                 GROUP BY steam_id HAVING COUNT(DISTINCT LOWER(TRIM(email))) > 1 LIMIT 300"
            )?->fetchAll(PDO::FETCH_COLUMN) ?: [] as $s) {
                $steams[(string) $s] = true;
            }
            $steams += $this->openReportSteams();
        }
        $out = [];
        foreach (array_keys($steams) as $steam) {
            $steam = (string) $steam;
            $out[] = [
                'steam_id' => $steam,
                'state' => $this->stateFor($steam),
                'holders' => $this->holders($steam),
                'reports' => $this->reportsFor($steam),
            ];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public function reportsFor(string $steam): array
    {
        try {
            $st = $this->pdo()->prepare('SELECT * FROM steam_conflict_reports WHERE steam_id = ? ORDER BY id DESC LIMIT 20');
            $st->execute([$steam]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Administration : rendre le Steam mis de côté au déclarant (restore) ou classer le signalement (close).
     */
    public function adminDecide(int $reportId, string $decision, int $adminId): bool
    {
        $st = $this->pdo()->prepare("SELECT * FROM steam_conflict_reports WHERE id = ? AND status = 'open'");
        $st->execute([$reportId]);
        $report = $st->fetch(PDO::FETCH_ASSOC);
        if (!is_array($report) || !in_array($decision, ['restore', 'close'], true)) {
            return false;
        }
        $steam = (string) $report['steam_id'];
        if ($decision === 'restore') {
            $put = $this->pdo()->prepare("UPDATE users SET steam_id = ? WHERE id = ? AND (steam_id IS NULL OR steam_id = '')");
            foreach (array_filter(array_map('intval', explode(',', (string) $report['removed_user_ids']))) as $id) {
                $put->execute([$steam, $id]);
            }
        }
        $this->pdo()->prepare('UPDATE steam_conflict_reports SET status = ?, resolved_at = ?, resolved_by = ? WHERE id = ?')
            ->execute([$decision === 'restore' ? 'restored' : 'closed', gmdate('Y-m-d H:i:s'), $adminId, $reportId]);
        $this->log(
            $decision === 'restore' ? AuditAction::ADMIN_STEAM_CONFLICT_RESTORED : AuditAction::ADMIN_STEAM_CONFLICT_CLOSED,
            (int) $report['reporter_tenant_id'],
            $adminId,
            (int) $report['reporter_user_id'],
            $steam,
            'report ' . $reportId
        );

        return true;
    }

    /**
     * Ce que le joueur peut voir de l'autre compte : indicatif masqué et mois de création, rien d'autre.
     *
     * @param array{callsign: string, created_at: string} $other
     */
    public static function maskedHint(array $other): string
    {
        $cs = trim($other['callsign']);
        $len = mb_strlen($cs);
        $masked = $len === 0 ? '' : ($len < 4 ? mb_substr($cs, 0, 1) . '•••' : mb_substr($cs, 0, 1) . '•••' . mb_substr($cs, -1));
        $ts = strtotime($other['created_at']);
        $months = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
        $created = $ts ? $months[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts) : '';
        $parts = [];
        if ($masked !== '') {
            $parts[] = 'indicatif ' . $masked;
        }
        if ($created !== '') {
            $parts[] = 'créé en ' . $created;
        }

        return $parts === [] ? 'compte sans indicatif' : implode(', ', $parts);
    }

    /**
     * @param array<string, mixed> $user
     * @return list<int>
     */
    private function clearOwnRows(array $user, string $steam): array
    {
        $email = self::email((string) ($user['email'] ?? ''));
        $ids = [(int) $user['id']];
        $st = $this->pdo()->prepare('SELECT id FROM users WHERE steam_id = ? AND LOWER(TRIM(email)) = ?');
        $st->execute([$steam, $email]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) ?: [] as $id) {
            $ids[] = (int) $id;
        }
        $ids = array_values(array_unique($ids));
        $clear = $this->pdo()->prepare('UPDATE users SET steam_id = NULL WHERE id = ? AND steam_id = ?');
        $removed = [];
        foreach ($ids as $id) {
            $clear->execute([$id, $steam]);
            if ($clear->rowCount() > 0) {
                $removed[] = $id;
            }
        }
        $this->pdo()->prepare('UPDATE athena_accounts SET steam_id = NULL WHERE steam_id = ? AND LOWER(TRIM(email)) = ?')
            ->execute([$steam, $email]);

        return $removed;
    }

    private function log(string $action, int $tenantId, int $actorId, int $targetUserId, ?string $old, ?string $new): void
    {
        try {
            ($this->audit ??= new AuditService())->log($action, $tenantId > 0 ? $tenantId : null, $actorId, 'user', $targetUserId, $old, $new);
        } catch (\Throwable) {
            error_log('[steam-conflict] audit impossible : ' . $action . ' user=' . $targetUserId);
        }
    }

    private function notifications(): MemberNotificationRepository
    {
        return $this->notifications ??= new MemberNotificationRepository();
    }

    private static function email(string $email): string
    {
        return strtolower(trim($email));
    }
}
