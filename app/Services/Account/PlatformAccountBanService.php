<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Core\Container;
use App\Core\Database;
use App\Repositories\BlockedIndicatorRepository;
use App\Repositories\PlatformAccountBanRepository;
use App\Repositories\UserRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\Moderation\IndicatorBlocklistService;
use App\Support\SqlText;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * Bannissement d’une personne sur tout le site.
 *
 * Bannir = toutes les fiches de l’adresse passent « inactif » (sessions fermées à la requête suivante),
 * l’adresse e-mail est refusée à la connexion et à l’inscription, le Steam ID est refusé par le mod,
 * et les adresses réseau cochées sont bloquées. Lever le bannissement remet chaque fiche dans son état
 * d’avant et retire uniquement les entrées créées par ce bannissement.
 *
 * Un bannissement peut être temporaire : les blocages portent alors la même date de fin (ils s’éteignent
 * seuls) et les fiches sont remises en état par {@see expireDue()}, appelée à chaque tentative de connexion
 * de la personne et à l’ouverture de l’annuaire.
 */
final class PlatformAccountBanService
{
    /** Durées proposées dans le formulaire (clé => secondes) ; « definitif » et « date » sont gérées à part. */
    public const DURATIONS = [
        '1j' => 86400,
        '7j' => 7 * 86400,
        '30j' => 30 * 86400,
        '90j' => 90 * 86400,
    ];

    public const INACTIVITY_ACCOUNT_STATUSES = ['active', 'inactive'];

    public const MIN_REASON_LENGTH = 5;
    public const MAX_REASON_LENGTH = 500;
    /** Une sanction temporaire ne dépasse pas cinq ans : au-delà, c’est un bannissement sans fin. */
    private const MAX_TEMPORARY_SECONDS = 1826 * 86400;

    public function __construct(
        private ?UserRepository $users = null,
        private ?PlatformAccountBanRepository $bans = null,
        private ?IndicatorBlocklistService $blocklist = null,
        private ?BlockedIndicatorRepository $indicators = null,
        private ?PDO $pdo = null,
        private ?AuditService $audit = null,
    ) {
    }

    private function users(): UserRepository
    {
        return $this->users ??= new UserRepository($this->pdo());
    }

    private function bans(): PlatformAccountBanRepository
    {
        return $this->bans ??= new PlatformAccountBanRepository($this->pdo);
    }

    private function blocklist(): IndicatorBlocklistService
    {
        return $this->blocklist ??= Container::get(IndicatorBlocklistService::class);
    }

    private function indicators(): BlockedIndicatorRepository
    {
        return $this->indicators ??= Container::get(BlockedIndicatorRepository::class);
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    private function audit(): AuditService
    {
        return $this->audit ??= new AuditService();
    }

    /** Initialise les anciens schémas avant une transaction de bannissement ou de levée. */
    public function prepareWrites(): void
    {
        $this->bans()->ready();
        $this->blocklist();
    }

    /**
     * Traduit le choix du formulaire (durée + date) en date de fin UTC.
     *
     * @return array{ok: bool, error?: string, endsAt?: DateTimeImmutable|null}
     */
    public static function resolveEnd(string $duration, string $date, ?int $now = null): array
    {
        $now ??= time();
        $duration = trim($duration);
        if ($duration === '' || $duration === 'definitif') {
            return ['ok' => true, 'endsAt' => null];
        }
        $utc = new DateTimeZone('UTC');
        if (isset(self::DURATIONS[$duration])) {
            return ['ok' => true, 'endsAt' => (new DateTimeImmutable('@' . ($now + self::DURATIONS[$duration])))->setTimezone($utc)];
        }
        if ($duration !== 'date') {
            return ['ok' => false, 'error' => 'Durée de sanction inconnue.'];
        }
        $date = trim($date);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('Europe/Paris'));
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            return ['ok' => false, 'error' => 'Indiquez la date de fin de la sanction (jour/mois/année).'];
        }
        // La sanction court jusqu’à la fin du jour choisi, à l’heure de Paris.
        $end = $parsed->setTime(23, 59, 59)->setTimezone($utc);
        if ($end->getTimestamp() <= $now) {
            return ['ok' => false, 'error' => 'La date de fin doit être dans le futur.'];
        }
        if ($end->getTimestamp() - $now > self::MAX_TEMPORARY_SECONDS) {
            return ['ok' => false, 'error' => 'Une sanction temporaire ne peut pas dépasser cinq ans : choisissez « Sans fin ».'];
        }

        return ['ok' => true, 'endsAt' => $end];
    }

    /**
     * @param list<string> $ips
     * @param list<int>|null $expectedUserIds
     * @return array{ok: bool, error?: string, ban_id?: int, blocked_ips?: int, steam_blocked?: bool, accounts?: int, ends_at?: string|null}
     */
    public function ban(
        string $email,
        string $reason,
        array $ips,
        bool $blockSteam,
        int $actorId,
        ?DateTimeImmutable $endsAt = null,
        ?PlatformBanNotice $notice = null,
        ?int $sourceTenantId = null,
        ?string $batch = null,
        ?array $expectedUserIds = null,
        ?string $requiredInactivity = null,
    ): array {
        $email = PlatformAccountBanRepository::normalizeEmail($email);
        $reason = trim($reason);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || str_ends_with($email, '@deleted.invalid')
            || str_ends_with($email, '@merged.invalid') || str_ends_with($email, '@internal.local')) {
            return ['ok' => false, 'error' => 'Adresse du compte introuvable.'];
        }
        if (mb_strlen($reason) < self::MIN_REASON_LENGTH) {
            return ['ok' => false, 'error' => 'Indiquez le motif du bannissement (au moins ' . self::MIN_REASON_LENGTH . ' caractères) : il est gardé dans l’historique et le journal.'];
        }
        $reason = mb_substr($reason, 0, self::MAX_REASON_LENGTH);
        if (!$this->bans()->ready()) {
            return ['ok' => false, 'error' => 'La table des bannissements n’a pas pu être créée. Lancez les migrations puis réessayez.'];
        }
        if ($endsAt !== null && !$this->bans()->supportsEndDate()) {
            return ['ok' => false, 'error' => 'Les sanctions temporaires demandent la dernière mise à jour de la base : lancez les migrations puis réessayez.'];
        }
        $notice = $notice?->isEmpty() === false ? $notice : null;
        if (($notice !== null || $batch !== null) && !$this->bans()->supportsNotices()) {
            return ['ok' => false, 'error' => 'La mise à jour des sanctions et contestations est nécessaire.'];
        }
        if ($notice?->appealClosed && !$this->bans()->supportsAppealClosure()) {
            return ['ok' => false, 'error' => 'Appliquez la migration de fermeture des contestations avant de bannir avec cette option.'];
        }
        // Une sanction échue doit être soldée avant d’en décider une nouvelle.
        $this->expireDue($email);

        $ids = array_values(array_filter(
            array_map('intval', $this->users()->listIdsByEmailNormalized($email)),
            static fn (int $id): bool => $id > 0
        ));
        if ($ids === []) {
            return ['ok' => false, 'error' => 'Aucun compte trouvé pour cette adresse.'];
        }
        if (in_array($actorId, $ids, true)) {
            return ['ok' => false, 'error' => 'Vous ne pouvez pas vous bannir vous-même.'];
        }

        $this->prepareWrites();
        sort($ids, SORT_NUMERIC);
        return $this->inTransaction(function () use ($ids, $email, $reason, $ips, $blockSteam, $actorId, $endsAt, $notice, $sourceTenantId, $batch, $expectedUserIds, $requiredInactivity): array {
            // Les deux demandes visant une même personne prennent les verrous dans le même ordre.
            $in = implode(',', array_fill(0, count($ids), '?'));
            $lock = SqlText::isMysqlFamily($this->pdo()) ? ' FOR UPDATE' : '';
            $stmt = $this->pdo()->prepare('SELECT * FROM users WHERE id IN (' . $in . ') ORDER BY id' . $lock);
            $stmt->execute($ids);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($this->bans()->findActiveForEmail($email) !== null) {
                return ['ok' => false, 'error' => 'Cette personne est déjà bannie.'];
            }
            if ($this->users()->emailHasPlatformAdmin($email)) {
                return ['ok' => false, 'error' => 'Un administrateur du site ne peut pas être banni : retirez-lui d’abord ce rôle.'];
            }

            $snapshot = [];
            $label = '';
            $steamIds = [];
            foreach ($rows as $row) {
                if (PlatformAccountBanRepository::normalizeEmail((string) $row['email']) !== $email
                    || !empty($row['is_service_account']) || !empty($row['deleted_at'])
                    || in_array($row['status'] ?? '', ['merged', 'deleted'], true)) {
                    continue;
                }
                $snapshot[(int) $row['id']] = (string) $row['status'];
                $label = $label ?: (trim((string) ($row['callsign'] ?? '')) ?: trim((string) ($row['display_name'] ?? '')));
                $steam = \App\Support\SteamId::normalize((string) ($row['steam_id'] ?? ''));
                if ($steam !== null) {
                    $steamIds[$steam] = $steam;
                }
            }
            if ($snapshot === []) {
                return ['ok' => false, 'error' => 'Aucun compte utilisateur à bannir pour cette adresse.'];
            }
            if ($expectedUserIds !== null && array_keys($snapshot) !== $expectedUserIds) {
                return ['ok' => false, 'error' => 'Les comptes liés à cette adresse ont changé depuis l’aperçu.'];
            }
            if ($requiredInactivity !== null && (array_intersect(self::INACTIVITY_ACCOUNT_STATUSES, $snapshot) === []
                || !in_array($email, $this->users()->inactiveEmailsForPlatformSelection([$email], $requiredInactivity), true))) {
                return ['ok' => false, 'error' => 'Le compte ne correspond plus aux critères d’inactivité.'];
            }

            $note = 'Bannissement site : ' . mb_substr($reason, 0, 200);
            $indicatorIds = [$this->blocklist()->addEmailBlock($actorId, 'global', null, $email, $note, $endsAt)];
            if ($blockSteam) {
                foreach ($steamIds as $steam) {
                    $indicatorIds[] = $this->blocklist()->addSteamBlock($actorId, 'global', null, $steam, $note, $endsAt);
                }
            }
            $blockedIps = 0;
            foreach (self::cleanIps($ips) as $ip) {
                if ($this->indicators()->isIpBlockedGlobally($ip)) {
                    continue;
                }
                $indicatorIds[] = $this->blocklist()->addIpBlock($actorId, 'global', null, $ip, $note, $endsAt);
                $blockedIps++;
            }

            $this->setStatuses(array_fill_keys(array_keys($snapshot), 'inactive'), true);
            $metadata = $notice !== null || $batch !== null ? [
                'rule_reference' => $notice?->rule ?: null,
                'public_reason' => $notice?->message ?: null,
                'source_tenant_id' => $sourceTenantId,
                'batch_ref' => $batch,
                'appeal_closed' => $notice?->appealClosed ?? false,
            ] : null;
            $banId = $this->bans()->create(
                $email,
                array_key_first($snapshot),
                $label !== '' ? $label : $email,
                $reason,
                $indicatorIds,
                $snapshot,
                $actorId > 0 ? $actorId : null,
                $endsAt?->format('Y-m-d H:i:s'),
                $metadata,
            );
            if ($banId < 1) {
                throw new \RuntimeException('La décision de bannissement n’a pas été enregistrée.');
            }

            return [
                'ok' => true,
                'ban_id' => $banId,
                'blocked_ips' => $blockedIps,
                'steam_blocked' => $blockSteam && $steamIds !== [],
                'accounts' => count($snapshot),
                'ends_at' => $endsAt?->format('Y-m-d H:i:s'),
            ];
        });
    }

    /**
     * @return array{ok: bool, error?: string, restored?: int}
     */
    public function lift(string $email, int $actorId, ?string $note = null, ?int $expectedBanId = null): array
    {
        $this->prepareWrites();
        return $this->inTransaction(function () use ($email, $actorId, $note, $expectedBanId): array {
            $ban = $this->bans()->findActiveForEmail($email);
            if ($ban === null || ($expectedBanId !== null && (int) $ban['id'] !== $expectedBanId)
                || !$this->bans()->lift((int) $ban['id'], $actorId > 0 ? $actorId : null, $note)) {
                return ['ok' => false, 'error' => 'Le bannissement visé n’est plus en cours.'];
            }
            foreach (json_decode((string) ($ban['indicator_ids'] ?? '[]'), true) ?: [] as $indicatorId) {
                if ((int) $indicatorId > 0) {
                    $this->blocklist()->revokeIndicator($actorId, (int) $indicatorId, null);
                }
            }

            return ['ok' => true, 'restored' => $this->restoreSnapshot($ban)];
        });
    }

    /**
     * @template T
     * @param callable(): T $change
     * @return T
     */
    private function inTransaction(callable $change): mixed
    {
        $pdo = $this->pdo();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $result = $change();
            if ($ownsTransaction) {
                $pdo->commit();
            }

            return $result;
        } catch (\Throwable $error) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $error;
        }
    }

    /**
     * Solde les sanctions temporaires arrivées à échéance : chaque fiche retrouve son état d’avant.
     * Les blocages (e-mail, Steam, IP) portent déjà la même date de fin et ne sont plus appliqués.
     * Ne lève jamais : une panne ici ne doit pas empêcher une connexion.
     *
     * @return int nombre de sanctions soldées
     */
    public function expireDue(?string $email = null, int $limit = 50): int
    {
        $done = 0;
        try {
            foreach ($this->bans()->listDue($email, $limit) as $ban) {
                $id = (int) ($ban['id'] ?? 0);
                $own = !$this->pdo()->inTransaction() && $this->pdo()->beginTransaction();
                try {
                    // La levée est revendiquée d’abord : deux connexions simultanées ne remettent pas les fiches deux fois.
                    if (!$this->bans()->lift($id, null, 'Fin de la sanction : date d’échéance atteinte.')) {
                        if ($own) {
                            $this->pdo()->commit();
                        }
                        continue;
                    }
                    $restored = $this->restoreSnapshot($ban);
                    if ($own) {
                        $this->pdo()->commit();
                    }
                } catch (\Throwable $e) {
                    if ($own && $this->pdo()->inTransaction()) {
                        $this->pdo()->rollBack();
                    }
                    error_log('[ban.expire] ' . $e->getMessage());
                    continue;
                }
                $done++;
                try {
                    $this->audit()->logChange(
                        AuditAction::USER_BAN_EXPIRED,
                        0,
                        null,
                        'user',
                        0,
                        ['banned' => true],
                        ['banned' => false, 'ban_id' => $id, 'restored_accounts' => $restored],
                    );
                } catch (\Throwable $e) {
                    error_log('[ban.expire.audit] ' . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            error_log('[ban.expire] ' . $e->getMessage());
        }

        return $done;
    }

    /**
     * Remet les fiches dans l’état d’avant la sanction.
     *
     * @param array<string, mixed> $ban
     */
    private function restoreSnapshot(array $ban): int
    {
        $snapshot = [];
        foreach (json_decode((string) ($ban['status_snapshot'] ?? '{}'), true) ?: [] as $id => $status) {
            $status = (string) $status;
            // Une fiche déjà anonymisée ou fusionnée entre-temps garde son état actuel.
            $row = $this->users()->findById((int) $id, null);
            if ($row === null || !empty($row['deleted_at']) || (string) ($row['status'] ?? '') === 'merged') {
                continue;
            }
            $snapshot[(int) $id] = $status !== '' ? $status : 'active';
        }
        $this->setStatuses($snapshot);

        return count($snapshot);
    }

    /**
     * @param list<string> $ips
     * @return list<string>
     */
    public static function cleanIps(array $ips): array
    {
        $out = [];
        foreach ($ips as $raw) {
            foreach (preg_split('/[\s,;]+/', (string) $raw) ?: [] as $ip) {
                $ip = trim($ip);
                if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                    $out[$ip] = true;
                }
            }
        }

        return array_keys($out);
    }

    /** @param array<int, string> $statuses */
    private function setStatuses(array $statuses, bool $closeSessions = false): void
    {
        $hasEpoch = $closeSessions && SqlText::isMysqlFamily($this->pdo()) && $this->users()->hasSessionEpochColumn();
        $epochSql = $hasEpoch ? ', session_epoch = GREATEST(COALESCE(session_epoch, 0), ?)' : '';
        $st = $this->pdo()->prepare('UPDATE users SET status = ?, updated_at = ?' . $epochSql . ' WHERE id = ?');
        $epoch = time();
        $params = [gmdate('Y-m-d H:i:s', $epoch)];
        if ($hasEpoch) {
            $params[] = $epoch;
        }
        foreach ($statuses as $id => $status) {
            $st->execute([$status, ...$params, (int) $id]);
        }
    }
}
