<?php

declare(strict_types=1);

namespace App\Services\Effectifs\Availability;

use App\Repositories\ArmaPlaytimeRepository;
use App\Repositories\MemberAvailabilityCheckRepository;
use App\Repositories\PlayerSessionRepository;
use Closure;
use PDO;
use Throwable;

/**
 * Relève, pour un membre, les données déjà enregistrées sur le site qui disent s'il est disponible. Rien n'est écrit.
 *
 * Chaque source est lue à part : une table absente ou une erreur rend la source « absente » (null, ou false pour le journal
 * des actions) sans empêcher les autres. Sources : sessions de jeu (player_sessions, via PlayerSessionRepository), compte
 * (users.last_login_at, users.status), journal des actions (audit_logs), absences déclarées (personnel_absences),
 * heures de jeu remontées par le mod (user_arma_playtime, un cumul sans détail par session, avec l'instantané du cumul de la
 * dernière vérification enregistrée), position administrative, convocations (community_events et community_event_rsvps), sanctions et suivi du dossier.
 * Les trois dernières passent par des lecteurs injectés (services existants de la fiche) ; les sanctions ne sont lues que
 * si le lecteur a le droit de les voir.
 */
final class AvailabilityFactsCollector
{
    /**
     * @param (Closure(int, int): ?array{slug: string, label: string, service: string})|null $position
     * @param (Closure(int, int): ?array{active: int})|null $sanctions null : le lecteur ne voit pas les sanctions
     * @param (Closure(int, int): ?array{open: int, late: int})|null $followup
     */
    public function __construct(
        private PDO $pdo,
        private ?Closure $position = null,
        private ?Closure $sanctions = null,
        private ?Closure $followup = null,
    ) {
    }

    /** Lecteurs branchés sur les services de la fiche (requêtes web). */
    public static function live(PDO $pdo, bool $withSanctions): self
    {
        $position = static function (int $tenantId, int $userId): ?array {
            $slug = \App\Core\Container::get(\App\Services\Personnel\PersonnelDutyPositionService::class)->currentDutySlug($tenantId, $userId);
            if ($slug === null) {
                return null;
            }
            foreach (\App\Support\AdministrativePositionCatalog::all($tenantId) as $p) {
                if ((string) $p['slug'] === $slug) {
                    return ['slug' => $slug, 'label' => (string) $p['label'], 'service' => (string) $p['service']];
                }
            }

            return ['slug' => $slug, 'label' => \App\Support\AdministrativePositionCatalog::label($slug, $tenantId), 'service' => ''];
        };
        $sanctions = $withSanctions ? static function (int $tenantId, int $userId): array {
            $rows = (new \App\Repositories\ModerationRepository())->listTenantActionsForTarget($tenantId, $userId);

            return ['active' => \App\Services\Effectifs\MemberSanctions::summarize($rows, new \DateTimeImmutable('now'))['active']];
        } : null;
        $followup = static function (int $tenantId, int $userId): array {
            $summary = \App\Services\Effectifs\Dossier\MemberDossierTimeline::make()->forUser($tenantId, $userId)['summary'];

            return ['open' => (int) ($summary['open'] ?? 0), 'late' => (int) ($summary['late'] ?? 0)];
        };

        return new self($pdo, $position, $sanctions, $followup);
    }

    /**
     * @param array{min_minutes: int, gap_minutes: int} $rules règle de la communauté (durée qui compte, coupure tolérée)
     * @return array<string, mixed>
     */
    public function collect(int $tenantId, int $userId, array $rules, int $now, string $timezone): array
    {
        $rules = [
            'min_minutes' => max(1, (int) ($rules['min_minutes'] ?? 60)),
            'gap_minutes' => max(1, (int) ($rules['gap_minutes'] ?? 25)),
        ];
        $account = $this->safely(fn (): ?array => $this->account($tenantId, $userId), null);

        return [
            'now' => $now,
            'tz' => $timezone,
            'rules' => $rules,
            'sessions' => $this->safely(fn (): array => (new PlayerSessionRepository($this->pdo))->panelForUser(
                $tenantId,
                $userId,
                $rules['min_minutes'] * 60,
                $rules['gap_minutes'] * 60,
                $now,
                $timezone,
                3
            ), null),
            'playtime' => $this->safely(fn (): ?array => $this->playtime($tenantId, $userId), null),
            'last_login_at' => $account['last_login_at'] ?? null,
            'account_status' => $account['status'] ?? 'active',
            'site_activity_at' => $this->safely(fn (): ?string => $this->lastAction($tenantId, $userId), false),
            'absences' => $this->safely(fn (): array => $this->absences($tenantId, $userId, $now, $timezone), null),
            'position' => $this->position !== null ? $this->safely(fn (): ?array => ($this->position)($tenantId, $userId), null) : null,
            'events' => $this->safely(fn (): ?array => $this->events($tenantId, $userId, $now, (string) ($account['created_at'] ?? '')), null),
            'sanctions' => $this->sanctions !== null ? $this->safely(fn (): ?array => ($this->sanctions)($tenantId, $userId), null) : null,
            'followup' => $this->followup !== null ? $this->safely(fn (): ?array => ($this->followup)($tenantId, $userId), null) : null,
        ];
    }

    /**
     * Heures de jeu remontées (cumul) et instantané du cumul enregistré à la dernière vérification de disponibilité.
     * null : la table du cumul n'existe pas sur ce site.
     *
     * @return array{total_seconds: int, server_seconds: int, zeus_seconds: int, editor_seconds: int, last_report_at: ?string, previous: ?array{total_seconds: int, checked_at: string}}|null
     */
    private function playtime(int $tenantId, int $userId): ?array
    {
        $repo = new ArmaPlaytimeRepository($this->pdo);
        if (!$repo->schemaReady()) {
            return null;
        }
        $sum = $repo->getSummaryForUser($tenantId, $userId)
            ?? ['total_seconds' => 0, 'server_seconds' => 0, 'zeus_seconds' => 0, 'editor_seconds' => 0, 'last_report_at' => null];
        $previous = null;
        try {
            $last = (new MemberAvailabilityCheckRepository($this->pdo))->listForUser($tenantId, $userId, 1)[0] ?? null;
            $snap = is_array($last) ? (json_decode((string) ($last['facts_json'] ?? ''), true)['playtime'] ?? null) : null;
            if (is_array($snap) && isset($snap['total_seconds'])) {
                $previous = ['total_seconds' => (int) $snap['total_seconds'], 'checked_at' => (string) $last['created_at']];
            }
        } catch (Throwable) {
            $previous = null;
        }

        return $sum + ['previous' => $previous];
    }

    /** @return array{last_login_at: ?string, status: string, created_at: string}|null */
    private function account(int $tenantId, int $userId): ?array
    {
        try {
            $st = $this->pdo->prepare('SELECT last_login_at, status, created_at FROM users WHERE id = ? AND tenant_id = ?');
            $st->execute([$userId, $tenantId]);
        } catch (Throwable) {
            // Base sans last_login_at : le statut seul.
            $st = $this->pdo->prepare('SELECT status FROM users WHERE id = ? AND tenant_id = ?');
            $st->execute([$userId, $tenantId]);
        }
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return [
            'last_login_at' => trim((string) ($row['last_login_at'] ?? '')) !== '' ? (string) $row['last_login_at'] : null,
            'status' => (string) ($row['status'] ?? 'active'),
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    private function lastAction(int $tenantId, int $userId): ?string
    {
        $st = $this->pdo->prepare('SELECT MAX(created_at) FROM audit_logs WHERE user_id = ? AND tenant_id = ?');
        $st->execute([$userId, $tenantId]);
        $v = $st->fetchColumn();

        return is_string($v) && $v !== '' ? $v : null;
    }

    /** @return list<array<string, mixed>> absences en cours ou à venir (les plus récentes d'abord) */
    private function absences(int $tenantId, int $userId, int $now, string $timezone): array
    {
        $today = (new \DateTimeImmutable('@' . $now))->setTimezone(new \DateTimeZone($timezone))->format('Y-m-d');
        $st = $this->pdo->prepare(
            'SELECT starts_on, ends_on, reason, status FROM personnel_absences
             WHERE tenant_id = ? AND user_id = ? AND (ends_on IS NULL OR ends_on >= ?)
             ORDER BY starts_on DESC LIMIT 20'
        );
        $st->execute([$tenantId, $userId, $today]);

        return array_values($st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /**
     * Convocations des 90 derniers jours (événements passés non annulés, postérieurs à l'arrivée du membre).
     *
     * @return array{sessions_90: int, present_90: int, last_present_at: ?string}
     */
    private function events(int $tenantId, int $userId, int $now, string $joinedAt): array
    {
        $from = date('Y-m-d H:i:s', $now - 90 * 86400);
        $join = substr(trim($joinedAt), 0, 19);
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $join) === 1 && strcmp($join, $from) > 0) {
            $from = $join;
        }
        $to = date('Y-m-d H:i:s', $now);
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM community_events WHERE tenant_id = ? AND cancelled_at IS NULL AND starts_at >= ? AND starts_at < ?');
        $st->execute([$tenantId, $from, $to]);
        $total = (int) $st->fetchColumn();
        $st = $this->pdo->prepare(
            "SELECT COUNT(*) AS n, MAX(ce.starts_at) AS last_at FROM community_event_rsvps r
             INNER JOIN community_events ce ON ce.id = r.event_id
             WHERE r.user_id = ? AND ce.tenant_id = ? AND ce.cancelled_at IS NULL AND ce.starts_at >= ? AND ce.starts_at < ?
               AND r.checked_in_at IS NOT NULL AND r.checked_in_at <> ''"
        );
        $st->execute([$userId, $tenantId, $from, $to]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'sessions_90' => $total,
            'present_90' => (int) ($row['n'] ?? 0),
            'last_present_at' => trim((string) ($row['last_at'] ?? '')) !== '' ? (string) $row['last_at'] : null,
        ];
    }

    /**
     * @template T
     * @param callable(): T $fn
     * @param T $fallback
     * @return T
     */
    private function safely(callable $fn, mixed $fallback): mixed
    {
        try {
            return $fn();
        } catch (Throwable $e) {
            error_log('[vérification de disponibilité] source indisponible : ' . $e->getMessage());

            return $fallback;
        }
    }
}
