<?php

declare(strict_types=1);

namespace App\Services\Cooperation;

use App\Repositories\CooperationAnnouncementTemplateRepository;
use App\Repositories\CooperationForumAnnouncementLogRepository;
use App\Repositories\ForumNotificationRepository;
use App\Repositories\ForumPostRepository;
use App\Repositories\ForumTopicRepository;
use App\Repositories\InterteamMissionRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserRepository;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;

/**
 * Applique les gabarits d’annonces coopération (courriel, portail, forum) après une action métier.
 */
final class CooperationAnnouncementDispatcher
{
    private const EMAIL_PREF_KEY = 'cooperation.announcement';

    private const COOP_NOTIFY_PERMS = [
        'cooperation.missions.manage',
        'cooperation.missions.respond',
        'interteam.missions.manage',
        'interteam.missions.respond',
        'admin.organization',
        'admin.access',
    ];

    public function __construct(
        private InterteamMissionRepository $missionRepository,
        private TenantRepository $tenantRepository,
        private CooperationAnnouncementTemplateRepository $templateRepository,
        private CooperationAnnouncementRenderer $renderer,
        private UserRepository $userRepository,
        private UserNotificationPreferencesRepository $notificationPreferencesRepository,
        private EmailService $emailService,
        private ForumNotificationRepository $forumNotificationRepository,
        private ForumPostRepository $forumPostRepository,
        private ForumTopicRepository $forumTopicRepository,
        private CooperationForumAnnouncementLogRepository $forumAnnouncementLogRepository
    ) {}

    /**
     * @param array<string, mixed> $extra invited_tenant_id, partner_tenant_id (alias)
     */
    public function dispatch(string $eventKey, int $missionId, int $actorUserId, int $actorTenantId, array $extra = []): void
    {
        if (!CooperationAnnouncementEvents::isKnown($eventKey) || $missionId < 1) {
            return;
        }
        $mission = $this->missionRepository->findById($missionId);
        if (!$mission) {
            return;
        }
        $leadTid = (int) ($mission['created_by_tenant_id'] ?? 0);
        if ($leadTid < 1) {
            return;
        }
        $vars = $this->buildVars($mission, $actorUserId, $actorTenantId, $extra);
        foreach (['email', 'in_app', 'forum'] as $channel) {
            $tpl = $this->templateRepository->findResolved($leadTid, $eventKey, $channel);
            if (!$tpl) {
                continue;
            }
            match ($channel) {
                'email' => $this->dispatchEmail($eventKey, $tpl, $vars, $mission, $extra),
                'in_app' => $this->dispatchInApp($eventKey, $tpl, $vars, $mission, $extra),
                'forum' => $this->dispatchForum($eventKey, $tpl, $vars, $mission, $actorUserId, $leadTid),
                default => null,
            };
        }
    }

    /** @param array<string, mixed> $extra */
    private function dispatchEmail(string $eventKey, array $tpl, array $vars, array $mission, array $extra): void
    {
        $body = $this->renderer->render((string) ($tpl['body'] ?? ''), $vars);
        if (trim($body) === '') {
            return;
        }
        $subject = $this->renderer->render((string) ($tpl['subject'] ?? ''), $vars);
        if (trim($subject) === '') {
            $subject = 'Coopération inter-unités';
        }
        $tenantIds = $this->targetTenantIds($eventKey, $mission, $extra);
        $userIds = $this->collectNotifyUserIds($tenantIds);
        $html = '<p>' . nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')) . '</p>';
        foreach ($userIds as $uid) {
            if (!$this->notificationPreferencesRepository->isEmailEventEnabled($uid, self::EMAIL_PREF_KEY)) {
                continue;
            }
            $u = $this->userRepository->findById($uid);
            if (!$u) {
                continue;
            }
            $email = strtolower(trim((string) ($u['email'] ?? '')));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $this->emailService->send(
                EmailEvents::COOPERATION_ANNOUNCEMENT,
                $email,
                $subject,
                $html,
                strip_tags($body),
                (int) ($u['tenant_id'] ?? 0) ?: null,
                null,
                ['cooperation_event' => $eventKey, 'mission_id' => (int) ($mission['id'] ?? 0)]
            );
        }
    }

    /** @param array<string, mixed> $extra */
    private function dispatchInApp(string $eventKey, array $tpl, array $vars, array $mission, array $extra): void
    {
        if (!$this->forumNotificationRepository->tableExists()) {
            return;
        }
        $raw = $this->renderer->render((string) ($tpl['body'] ?? ''), $vars);
        if (trim($raw) === '') {
            return;
        }
        $subjTpl = trim((string) ($tpl['subject'] ?? ''));
        $title = $subjTpl !== ''
            ? $this->renderer->render($subjTpl, $vars)
            : (CooperationAnnouncementEvents::labels()[$eventKey] ?? 'Coopération inter-unités');
        if (trim($title) === '') {
            $title = CooperationAnnouncementEvents::labels()[$eventKey] ?? 'Coopération inter-unités';
        }
        $detail = mb_strlen($raw) > 220 ? mb_substr($raw, 0, 217) . '…' : $raw;
        $href = (string) ($vars['lien_synthese'] ?? '');
        $tenantIds = $this->targetTenantIds($eventKey, $mission, $extra);
        $userIds = $this->collectNotifyUserIds($tenantIds);
        foreach ($userIds as $uid) {
            $u = $this->userRepository->findById($uid);
            if (!$u) {
                continue;
            }
            $tid = (int) ($u['tenant_id'] ?? 0);
            if ($tid < 1) {
                continue;
            }
            $this->forumNotificationRepository->create($tid, $uid, 'cooperation_announcement', [
                'title' => $title,
                'detail' => $detail,
                'href' => $href !== '' ? $href : url('back-office/cooperation/missions'),
                'mission_id' => (int) ($mission['id'] ?? 0),
            ]);
        }
    }

    private function dispatchForum(string $eventKey, array $tpl, array $vars, array $mission, int $actorUserId, int $leadTid): void
    {
        $body = $this->renderer->render((string) ($tpl['body'] ?? ''), $vars);
        if (trim($body) === '') {
            return;
        }
        $mid = (int) ($mission['id'] ?? 0);
        $minH = max(0, (int) ($tpl['min_interval_hours'] ?? 24));
        if ($minH > 0 && $this->forumAnnouncementLogRepository->tableExists()) {
            $elapsed = $this->forumAnnouncementLogRepository->secondsSinceLastPost($mid, $eventKey);
            if ($elapsed !== null && $elapsed < $minH * 3600) {
                return;
            }
        }
        $rawSettings = $tpl['forum_settings_json'] ?? null;
        $settings = [];
        if (is_string($rawSettings) && $rawSettings !== '') {
            $d = json_decode($rawSettings, true);
            $settings = is_array($d) ? $d : [];
        }
        $topicId = (int) ($settings['topic_id'] ?? 0);
        if ($topicId < 1) {
            $fresh = $this->missionRepository->findById($mid);
            if ($fresh) {
                $topicId = (int) ($fresh['coop_forum_topic_id'] ?? 0);
            }
        }
        if ($topicId < 1) {
            return;
        }
        $topic = $this->forumTopicRepository->findById($topicId, $leadTid);
        if (!$topic) {
            return;
        }
        $asDraft = !empty($settings['as_draft']);
        $postTenantId = $leadTid;
        if ($actorUserId < 1) {
            return;
        }
        $this->forumPostRepository->create(
            $postTenantId,
            $topicId,
            $actorUserId,
            $body,
            null,
            $leadTid,
            'info',
            $asDraft,
            null
        );
        if ($this->forumAnnouncementLogRepository->tableExists()) {
            $this->forumAnnouncementLogRepository->touch($mid, $eventKey);
        }
    }

    /** @param array<string, mixed> $mission */
    private function buildVars(array $mission, int $actorUserId, int $actorTenantId, array $extra): array
    {
        $mid = (int) ($mission['id'] ?? 0);
        $leadTid = (int) ($mission['created_by_tenant_id'] ?? 0);
        $supportName = $this->tenantLabel($leadTid);
        $destTid = (int) ($extra['invited_tenant_id'] ?? $extra['partner_tenant_id'] ?? $actorTenantId);
        $destName = $this->tenantLabel($destTid);
        $deadline = '';
        $dl = $mission['proposal_deadline_at'] ?? null;
        if ($dl) {
            $ts = strtotime((string) $dl);
            if ($ts !== false) {
                $deadline = date('d/m/Y H:i', $ts);
            }
        }

        return [
            'titre_cooperation' => (string) ($mission['title'] ?? ''),
            'unite_support' => $supportName,
            'unite_destinataire' => $destName,
            'date_limite' => $deadline,
            'lien_synthese' => cooperation_mission_show_url($mid),
            'lien_proposition' => cooperation_mission_edit_url($mid),
            'lien_espace_commun' => cooperation_mission_exchange_url($mid),
            'lien_negociation' => cooperation_mission_negotiate_url($mid),
        ];
    }

    private function tenantLabel(int $tenantId): string
    {
        if ($tenantId < 1) {
            return '';
        }
        $t = $this->tenantRepository->findById($tenantId);

        return trim((string) ($t['name'] ?? '')) !== '' ? trim((string) $t['name']) : ('Communauté #' . $tenantId);
    }

    /** @param array<string, mixed> $mission */
    /** @param array<string, mixed> $extra */
    /** @return list<int> */
    private function targetTenantIds(string $eventKey, array $mission, array $extra): array
    {
        $lead = (int) ($mission['created_by_tenant_id'] ?? 0);
        $mid = (int) ($mission['id'] ?? 0);
        return match ($eventKey) {
            CooperationAnnouncementEvents::INVITATION_SENT => (static function () use ($extra): array {
                $i = (int) ($extra['invited_tenant_id'] ?? $extra['partner_tenant_id'] ?? 0);

                return $i > 0 ? [$i] : [];
            })(),
            CooperationAnnouncementEvents::PARTNER_ACCEPTED,
            CooperationAnnouncementEvents::PARTNER_DECLINED => $lead > 0 ? [$lead] : [],
            CooperationAnnouncementEvents::MISSION_CREATED,
            CooperationAnnouncementEvents::PROPOSAL_UPDATED => $lead > 0 ? [$lead] : [],
            CooperationAnnouncementEvents::MISSION_ACTIVATED => $this->participantTenantIds($mid, true),
            CooperationAnnouncementEvents::MISSION_CLOSED => $this->participantTenantIds($mid, false),
            default => [],
        };
    }

    /** @return list<int> */
    private function participantTenantIds(int $missionId, bool $activeOnly): array
    {
        if ($missionId < 1) {
            return [];
        }
        $parts = $this->missionRepository->listParticipants($missionId);
        $ids = [];
        foreach ($parts as $p) {
            $st = (string) ($p['status'] ?? '');
            if ($activeOnly && $st !== 'active') {
                continue;
            }
            if (!$activeOnly && ($st === 'left' || $st === '')) {
                continue;
            }
            $tid = (int) ($p['tenant_id'] ?? 0);
            if ($tid > 0) {
                $ids[] = $tid;
            }
        }

        return array_values(array_unique($ids));
    }

    /** @param list<int> $tenantIds */
    /** @return list<int> */
    private function collectNotifyUserIds(array $tenantIds): array
    {
        $ids = [];
        foreach ($tenantIds as $tid) {
            if ($tid < 1) {
                continue;
            }
            foreach ($this->userRepository->listActiveUserIdsWithAnyPermissionSlug($tid, self::COOP_NOTIFY_PERMS) as $uid) {
                $ids[] = $uid;
            }
        }

        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }
}
