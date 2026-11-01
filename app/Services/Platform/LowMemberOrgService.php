<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Core\Container;
use App\Repositories\LowMemberOrgRepository;
use App\Repositories\MemberNotificationRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserRepository;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;
use App\Support\LowMemberOrgPolicy;
use App\Support\UtcDateTime;

/**
 * Communautés restées à un ou deux membres après trois mois : rappel aux responsables (e-mail + notification du
 * site, une fois par palier), puis liste « Organisations à supprimer » pour l'administration de la plateforme
 * (notification du site à chaque nouvelle entrée, résumé e-mail au plus une fois par semaine).
 * Aucune suppression ici : l'administration passe par le retrait existant (admin/tenants/{id}/retrait).
 */
final class LowMemberOrgService
{
    public const NOTIFICATION_MANAGER = 'org_low_members';
    public const NOTIFICATION_ADMIN = 'org_low_members_admin';
    public const ADMIN_EMAIL_EVERY_DAYS = 7;

    /**
     * @param \Closure(): list<array<string, mixed>>|null $tenantSource lignes annuaire (listOverviewForPlatform)
     * @param \Closure(int): int|null $memberCounter membres actifs (hors comptes techniques)
     * @param \Closure(array<string, mixed>, string, int): array{recipients: int, emails: int}|null $remind
     * @param \Closure(list<array<string, mixed>>, list<array<string, mixed>>|null, int): array{notified: int, emails: int}|null $alertAdmins
     *        nouvelles entrées (notification du site), liste complète si le résumé e-mail est dû (sinon null)
     */
    public function __construct(
        private ?LowMemberOrgRepository $repo = null,
        private ?\Closure $tenantSource = null,
        private ?\Closure $memberCounter = null,
        private ?\Closure $remind = null,
        private ?\Closure $alertAdmins = null,
    ) {
    }

    private function repo(): LowMemberOrgRepository
    {
        return $this->repo ??= new LowMemberOrgRepository();
    }

    public function schemaReady(): bool
    {
        try {
            return $this->repo()->schemaReady();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Communautés concernées (et archivées après rappel), avec leur état.
     *
     * @return list<array{id: int, name: string, slug: string, created_at: ?string, age_days: int, members: int,
     *                    last_activity: ?string, reminders: array<string, string>, kept: bool, kept_at: ?string,
     *                    archived: bool, status: string, listed_at: ?string, admin_notified_at: ?string,
     *                    tenant: array<string, mixed>}>
     * status : pending (pas encore de rappel), warned (rappel envoyé), ready (à supprimer), kept, archived
     */
    public function evaluate(?int $now = null): array
    {
        $now ??= time();
        if (!$this->schemaReady()) {
            return [];
        }
        $reminders = $this->repo()->remindersByTenant();
        $watch = $this->repo()->watchByTenant();
        $out = [];
        foreach (($this->tenantSource ?? $this->defaultTenantSource(...))() as $t) {
            $id = (int) ($t['id'] ?? 0);
            $archived = !empty($t['archive']);
            $sent = $reminders[$id] ?? [];
            // Archivée après nos rappels : la suppression définitive reste à faire.
            if ($archived && $sent === []) {
                continue;
            }
            $probe = $archived ? array_merge($t, ['archive' => null]) : $t;
            if (LowMemberOrgPolicy::isProtected($probe)) {
                continue;
            }
            $age = LowMemberOrgPolicy::ageDays($t['created_at'] ?? null, $now);
            if ($age === null || $age < LowMemberOrgPolicy::FIRST_AFTER_DAYS) {
                continue;
            }
            $members = ($this->memberCounter ?? $this->defaultMemberCounter(...))($id);
            if (!LowMemberOrgPolicy::isLowMemberCount($members)) {
                continue;
            }
            $w = $watch[$id] ?? [];
            $kept = !empty($w['kept_at']);
            $status = match (true) {
                $kept => 'kept',
                $archived => 'archived',
                LowMemberOrgPolicy::readyForDeletion($sent, $now) => 'ready',
                $sent !== [] => 'warned',
                default => 'pending',
            };
            $lastLogin = (string) ($t['last_login_at'] ?? '');
            $lastGame = (string) ($t['last_game_at'] ?? '');
            $last = max($lastLogin, $lastGame);
            $out[] = [
                'id' => $id,
                'name' => (string) ($t['name'] ?? ''),
                'slug' => (string) ($t['slug'] ?? ''),
                'created_at' => isset($t['created_at']) ? (string) $t['created_at'] : null,
                'age_days' => $age,
                'members' => $members,
                'last_activity' => $last !== '' ? $last : null,
                'reminders' => $sent,
                'kept' => $kept,
                'kept_at' => $kept ? (string) $w['kept_at'] : null,
                'archived' => $archived,
                'status' => $status,
                'listed_at' => !empty($w['listed_at']) ? (string) $w['listed_at'] : null,
                'admin_notified_at' => !empty($w['admin_notified_at']) ? (string) $w['admin_notified_at'] : null,
                'tenant' => $t,
            ];
        }
        usort($out, static fn (array $a, array $b): int => strcmp((string) $a['created_at'], (string) $b['created_at']));

        return $out;
    }

    /**
     * Passage planifié (idempotent) : rappels dus, liste à supprimer, alertes de l'administration.
     *
     * @return array{ready: bool, reminders: int, emails: int, listed: int, admin_notified: int, admin_emails: int}
     */
    public function run(?int $now = null): array
    {
        $now ??= time();
        $res = ['ready' => false, 'reminders' => 0, 'emails' => 0, 'listed' => 0, 'admin_notified' => 0, 'admin_emails' => 0];
        if (!$this->schemaReady()) {
            return $res;
        }
        $res['ready'] = true;
        $remind = $this->remind ?? $this->deliverReminder(...);

        $rows = $this->evaluate($now);
        $byId = [];
        foreach ($rows as $row) {
            $byId[$row['id']] = $row;
            if ($row['kept'] || $row['archived']) {
                continue;
            }
            $stage = LowMemberOrgPolicy::dueStage($row['tenant'], $row['members'], $row['reminders'], false, $now);
            if ($stage === null) {
                continue;
            }
            // Palier réservé avant l'envoi : jamais deux rappels du même palier.
            if (!$this->repo()->claimReminder($row['id'], $stage, $row['members'], $now)) {
                continue;
            }
            try {
                $sent = $remind($row, $stage, $now);
            } catch (\Throwable) {
                $sent = ['recipients' => 0, 'emails' => 0];
            }
            $this->repo()->completeReminder($row['id'], $stage, (int) $sent['recipients'], (int) $sent['emails']);
            $res['reminders']++;
            $res['emails'] += (int) $sent['emails'];
        }

        // Liste à supprimer : entrées et sorties.
        $watch = $this->repo()->watchByTenant();
        foreach ($watch as $tenantId => $w) {
            if (!empty($w['listed_at']) && (($byId[$tenantId]['status'] ?? '') !== 'ready')) {
                $this->repo()->clearListed((int) $tenantId);
            }
        }
        $fresh = [];
        $ready = [];
        $unmailed = 0;
        foreach ($rows as $row) {
            if ($row['status'] !== 'ready') {
                continue;
            }
            $this->repo()->markListed($row['id'], $now);
            $ready[] = $row;
            $w = $watch[$row['id']] ?? [];
            $listedAt = !empty($w['listed_at']) ? (string) $w['listed_at'] : null;
            if ($listedAt === null || empty($w['admin_notified_at'])) {
                $fresh[] = $row;
            }
            // Pas encore dans un résumé depuis son (ré)entrée dans la liste.
            if ($listedAt === null || empty($w['admin_emailed_at']) || (string) $w['admin_emailed_at'] < $listedAt) {
                $unmailed++;
            }
        }
        $res['listed'] = count($ready);
        $last = UtcDateTime::timestamp($this->repo()->lastAdminEmailAt());
        $emailDue = $unmailed > 0 && ($last === null || $now - $last >= self::ADMIN_EMAIL_EVERY_DAYS * 86400);
        if ($fresh !== [] || $emailDue) {
            try {
                $a = ($this->alertAdmins ?? $this->alertPlatformAdmins(...))($fresh, $emailDue ? $ready : null, $now);
            } catch (\Throwable) {
                $a = ['notified' => 0, 'emails' => 0];
            }
            if ($fresh !== []) {
                $this->repo()->markAdminNotified(array_column($fresh, 'id'), $now);
            }
            if ($emailDue) {
                $this->repo()->markAdminEmailed(array_column($ready, 'id'), $now);
            }
            $res['admin_notified'] = (int) $a['notified'];
            $res['admin_emails'] = (int) $a['emails'];
        }

        return $res;
    }

    /** Garder la communauté : plus de rappel, plus dans la liste. */
    public function keep(int $tenantId, ?int $userId, ?int $now = null): void
    {
        $this->repo()->keep($tenantId, $userId, $now ?? time());
        $this->repo()->clearListed($tenantId);
    }

    public function unkeep(int $tenantId): void
    {
        $this->repo()->unkeep($tenantId);
    }

    /** @return list<array<string, mixed>> */
    private function defaultTenantSource(): array
    {
        return Container::get(TenantRepository::class)->listOverviewForPlatform();
    }

    private function defaultMemberCounter(int $tenantId): int
    {
        return Container::get(UserRepository::class)->countActiveHumanMembers($tenantId);
    }

    /**
     * Rappel aux responsables (propriétaire, gouvernance, administrateurs de la communauté) : notification du site
     * pour chacun, e-mail sauf s'il l'a coupé dans ses préférences (le quota quotidien s'applique comme partout).
     *
     * @param array<string, mixed> $row
     * @return array{recipients: int, emails: int}
     */
    private function deliverReminder(array $row, string $stage, int $now): array
    {
        $tenantId = (int) $row['id'];
        $full = Container::get(TenantRepository::class)->findById($tenantId);
        if (!is_array($full)) {
            return ['recipients' => 0, 'emails' => 0];
        }
        $email = Container::get(EmailService::class);
        $prefs = Container::get(UserNotificationPreferencesRepository::class);
        $notifications = Container::get(MemberNotificationRepository::class);
        $deletionFrom = LowMemberOrgPolicy::deletionFrom($now);
        $slug = (string) ($full['slug'] ?? '');
        $links = [
            'invitations' => url('back-office/invitations'),
            'new_member' => url('back-office/ressources/effectifs/nouveau'),
            'invite_codes' => url('back-office/recruitments/codes-invitation'),
            'public_page' => $slug !== '' ? url('c/' . $slug) : url('communities'),
        ];
        $recipients = 0;
        $emails = 0;
        foreach ((new PlanGiftService())->recipients($full) as $user) {
            $userId = (int) $user['id'];
            $to = (string) $user['email'];
            $lang = LowMemberOrgPolicy::locale($email->recipientLocale($to));
            $copy = LowMemberOrgPolicy::copy($lang, $stage, (string) $row['name'], (int) $row['members'], $deletionFrom);
            $recipients++;
            try {
                $notifications->create($tenantId, $userId, self::NOTIFICATION_MANAGER, [
                    'stage' => $stage,
                    'title' => $copy['notice_title'],
                    'detail' => $copy['notice_detail'],
                    'href' => $links['invitations'],
                ]);
            } catch (\Throwable) {
            }
            if (!$prefs->isEmailEventEnabled($userId, EmailEvents::ORG_LOW_MEMBERS_REMINDER)) {
                continue;
            }
            try {
                $name = trim((string) ($user['callsign'] ?? '')) ?: trim((string) ($user['display_name'] ?? ''));
                if ($email->sendLowMemberOrgReminder($to, $lang, $name, (string) $row['name'], (int) $row['members'], $stage, $deletionFrom, $links, $tenantId)) {
                    $emails++;
                }
            } catch (\Throwable) {
            }
        }

        return ['recipients' => $recipients, 'emails' => $emails];
    }

    /**
     * Administration de la plateforme : notification du site pour les nouvelles entrées, résumé e-mail (hebdomadaire au plus).
     *
     * @param list<array<string, mixed>> $fresh
     * @param list<array<string, mixed>>|null $digest
     * @return array{notified: int, emails: int}
     */
    private function alertPlatformAdmins(array $fresh, ?array $digest, int $now): array
    {
        $listUrl = url('admin/organisations-a-supprimer');
        $notified = 0;
        if ($fresh !== []) {
            $n = count($fresh);
            $names = implode(', ', array_slice(array_map(static fn (array $r): string => (string) $r['name'], $fresh), 0, 5)) . ($n > 5 ? '…' : '');
            $notifications = Container::get(MemberNotificationRepository::class);
            foreach ($this->repo()->platformAdminAccounts() as $admin) {
                try {
                    $notifications->create($admin['tenant_id'], $admin['id'], self::NOTIFICATION_ADMIN, [
                        'title' => $n . ' organisation' . ($n > 1 ? 's' : '') . ' à supprimer',
                        'detail' => $names . ' — toujours 1 ou 2 membres après les rappels.',
                        'href' => $listUrl,
                    ]);
                    $notified++;
                } catch (\Throwable) {
                }
            }
        }
        $emails = 0;
        if ($digest !== null && $digest !== []) {
            $email = Container::get(EmailService::class);
            foreach (Container::get(UserRepository::class)->listPlatformAdminEmails() as $to) {
                try {
                    if ($email->sendLowMemberOrgAdminDigest($to, $digest, $listUrl)) {
                        $emails++;
                    }
                } catch (\Throwable) {
                }
            }
        }

        return ['notified' => $notified, 'emails' => $emails];
    }
}
