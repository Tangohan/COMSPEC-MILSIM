<?php

declare(strict_types=1);

namespace App\Services\MemberIntegration;

use App\Repositories\MemberIntegrationRepository;
use App\Repositories\MemberIntegrationTemplateRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserRepository;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;
use App\Support\MemberIntegrationCatalog;
use DateTimeImmutable;
use Throwable;

final class MemberIntegrationAutomationService
{
    public function __construct(
        private MemberIntegrationService $service,
        private MemberIntegrationRepository $integrations,
        private MemberIntegrationTemplateRepository $templates,
        private UserRepository $users,
        private TenantRepository $tenants,
        private EmailService $email,
        private UserNotificationPreferencesRepository $prefs,
    ) {}

    /**
     * Idempotent : une seule intégration active par membre.
     *
     * @param array{role_ids?: list<int>, unit_ids?: list<int>} $context
     * @return array{ok: bool, created: bool, integration_id?: int, message?: string}
     */
    public function ensureForNewMember(
        int $tenantId,
        int $userId,
        int $actorUserId,
        string $source,
        array $context = []
    ): array {
        if (!$this->integrations->tablesExist()) {
            return ['ok' => true, 'created' => false];
        }
        try {
            if (!$this->templates->hasActiveTemplate($tenantId)) {
                $this->templates->ensureDefaultRecruitTemplate($tenantId, $actorUserId > 0 ? $actorUserId : null);
            }
            $result = $this->service->instantiateForUser($tenantId, $userId, $actorUserId, $source, null, $context);
            if (!empty($result['created']) && !empty($result['integration_id'])) {
                $this->sendWelcome($tenantId, $userId, (int) $result['integration_id']);
            }

            return $result;
        } catch (Throwable $e) {
            return ['ok' => false, 'created' => false, 'message' => 'Le suivi d’intégration n’a pas pu être ouvert.'];
        }
    }

    /**
     * Ouverture seulement si un modèle a des règles auto qui correspondent.
     *
     * @param array{role_ids?: list<int>, unit_ids?: list<int>} $context
     * @return array{ok: bool, created: bool, integration_id?: int, message?: string}
     */
    public function maybeStartOnAssignmentChange(
        int $tenantId,
        int $userId,
        int $actorUserId,
        array $context = []
    ): array {
        if (!$this->integrations->tablesExist()) {
            return ['ok' => true, 'created' => false];
        }
        if ($this->integrations->findActiveForUser($tenantId, $userId)) {
            return ['ok' => true, 'created' => false];
        }
        $context['source'] = MemberIntegrationCatalog::SOURCE_ROLE_CHANGE;
        $template = $this->templates->matchTemplateByRules($tenantId, $context);
        if ($template === null) {
            return ['ok' => true, 'created' => false];
        }
        try {
            $result = $this->service->instantiateForUser(
                $tenantId,
                $userId,
                $actorUserId,
                MemberIntegrationCatalog::SOURCE_ROLE_CHANGE,
                (int) $template['id'],
                $context
            );
            if (!empty($result['created']) && !empty($result['integration_id'])) {
                $this->sendWelcome($tenantId, $userId, (int) $result['integration_id']);
            }

            return $result;
        } catch (Throwable) {
            return ['ok' => false, 'created' => false, 'message' => 'Le suivi d’intégration n’a pas pu être ouvert.'];
        }
    }

    public function runDaily(int $tenantId): array
    {
        $rows = $this->integrations->listDashboard($tenantId, [
            'status' => '',
        ], 400);
        $refreshed = 0;
        $reminded = 0;
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if (MemberIntegrationCatalog::isTerminalStatus($status)) {
                continue;
            }
            $id = (int) $row['id'];
            $this->service->refresh($tenantId, $id, null);
            $refreshed++;
            $fresh = $this->integrations->findForTenant($tenantId, $id);
            if ($fresh && (int) ($fresh['overdue_count'] ?? 0) > 0) {
                $this->remindOverdue($tenantId, $fresh);
                $reminded++;
            }
        }

        return ['refreshed' => $refreshed, 'reminded' => $reminded];
    }

    /**
     * @return array{candidates: list<array<string, mixed>>, would_create: int, ignored: int}
     */
    public function previewBackfill(int $tenantId, ?string $sinceDate, array $userIds = []): array
    {
        $candidates = $this->listBackfillCandidates($tenantId, $sinceDate, $userIds);
        $active = array_flip($this->integrations->listActiveUserIds($tenantId));
        $would = 0;
        $ignored = 0;
        $out = [];
        foreach ($candidates as $c) {
            $uid = (int) $c['id'];
            $skip = isset($active[$uid]);
            if ($skip) {
                $ignored++;
            } else {
                $would++;
            }
            $c['already_tracked'] = $skip;
            $out[] = $c;
        }

        return ['candidates' => $out, 'would_create' => $would, 'ignored' => $ignored];
    }

    /**
     * @return array{created: int, ignored: int, errors: int, details: list<string>}
     */
    public function executeBackfill(int $tenantId, int $actorUserId, ?string $sinceDate, array $userIds = []): array
    {
        $preview = $this->previewBackfill($tenantId, $sinceDate, $userIds);
        $created = 0;
        $ignored = 0;
        $errors = 0;
        $details = [];
        foreach ($preview['candidates'] as $c) {
            $uid = (int) $c['id'];
            if (!empty($c['already_tracked'])) {
                $ignored++;
                $details[] = 'Ignoré (suivi déjà ouvert) : ' . (string) ($c['display_name'] ?? $uid);
                continue;
            }
            $res = $this->ensureForNewMember($tenantId, $uid, $actorUserId, MemberIntegrationCatalog::SOURCE_BACKFILL);
            if (!empty($res['created'])) {
                $created++;
                $details[] = 'Créé : ' . (string) ($c['display_name'] ?? $uid);
            } elseif (!empty($res['ok'])) {
                $ignored++;
                $details[] = 'Ignoré : ' . (string) ($c['display_name'] ?? $uid);
            } else {
                $errors++;
                $details[] = 'Erreur : ' . (string) ($c['display_name'] ?? $uid);
            }
        }

        return ['created' => $created, 'ignored' => $ignored, 'errors' => $errors, 'details' => $details];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listBackfillCandidates(int $tenantId, ?string $sinceDate, array $userIds): array
    {
        $pdo = \App\Core\Database::getPdo();
        $where = ['u.tenant_id = ?', "u.status = 'active'"];
        $params = [$tenantId];
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $v): bool => $v > 0)));
        if ($ids !== []) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $where[] = 'u.id IN (' . $ph . ')';
            $params = array_merge($params, $ids);
        } elseif ($sinceDate !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $sinceDate)) {
            $where[] = 'u.created_at >= ?';
            $params[] = $sinceDate . ' 00:00:00';
        } else {
            $where[] = 'u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
        }
        $st = $pdo->prepare(
            'SELECT u.id, u.display_name, u.callsign, u.created_at, u.email
             FROM users u WHERE ' . implode(' AND ', $where) . ' ORDER BY u.created_at DESC LIMIT 400'
        );
        $st->execute($params);

        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    private function sendWelcome(int $tenantId, int $userId, int $integrationId): void
    {
        try {
            if ($this->integrations->hasEventType($tenantId, $integrationId, 'welcome_sent')) {
                return;
            }
            if (!$this->prefs->isEmailEventEnabled($userId, EmailEvents::MEMBER_INTEGRATION_STARTED)) {
                $this->integrations->addEvent($tenantId, $integrationId, 'welcome_sent', MemberIntegrationCatalog::VISIBILITY_STAFF, 'Message de bienvenue non envoyé (préférence).', null);
                return;
            }
            $user = $this->users->findById($userId, $tenantId);
            $tenant = $this->tenants->findById($tenantId);
            $to = trim((string) ($user['email'] ?? ''));
            if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                return;
            }
            $this->email->sendMemberIntegrationStarted(
                $to,
                (string) ($user['display_name'] ?? 'Membre'),
                (string) ($tenant['name'] ?? 'Communauté'),
                url('mon-integration'),
                $tenantId
            );
            $this->integrations->addEvent(
                $tenantId,
                $integrationId,
                'welcome_sent',
                MemberIntegrationCatalog::VISIBILITY_MEMBER,
                'Le message de bienvenue a été envoyé.',
                null
            );
        } catch (Throwable) {
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function remindOverdue(int $tenantId, array $row): void
    {
        $userId = (int) $row['user_id'];
        $integrationId = (int) $row['id'];
        try {
            if ($this->integrations->hasEventType($tenantId, $integrationId, 'overdue_reminded_' . (new DateTimeImmutable())->format('Y-m-d'))) {
                return;
            }
            if (!$this->prefs->isEmailEventEnabled($userId, EmailEvents::MEMBER_INTEGRATION_REMINDER)) {
                return;
            }
            $user = $this->users->findById($userId, $tenantId);
            $to = trim((string) ($user['email'] ?? ''));
            if ($to === '') {
                return;
            }
            $this->email->sendMemberIntegrationNotice(
                EmailEvents::MEMBER_INTEGRATION_REMINDER,
                $to,
                (string) ($user['display_name'] ?? 'Membre'),
                'Échéance du parcours d’intégration',
                '',
                url('mon-integration'),
                url('mon-integration'),
                $tenantId
            );
            $this->integrations->addEvent(
                $tenantId,
                $integrationId,
                'overdue_reminded_' . (new DateTimeImmutable())->format('Y-m-d'),
                MemberIntegrationCatalog::VISIBILITY_STAFF,
                'Relance envoyée pour une étape en retard.',
                null
            );
        } catch (Throwable) {
        }
    }
}
