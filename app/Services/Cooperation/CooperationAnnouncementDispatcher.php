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
     * @param array<string, mixed> $extra invited_tenant_id, partner_tenant_id, notify_user_ids, role_label, stage_label…
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
        $vars = $this->buildVars($mission, $actorUserId, $actorTenantId, $extra + ['__event' => $eventKey]);
        foreach (['email', 'in_app', 'forum'] as $channel) {
            $tpl = $this->resolveTemplate($leadTid, $eventKey, $channel);
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

    /**
     * @return array<string, mixed>|null
     */
    private function resolveTemplate(int $leadTid, string $eventKey, string $channel): ?array
    {
        $tpl = $this->templateRepository->findResolved($leadTid, $eventKey, $channel);
        if ($tpl) {
            return $tpl;
        }
        // Gabarit présent mais désactivé par l’administration : le canal reste coupé
        // (le gabarit intégré ne doit pas le réactiver en douce).
        if ($this->templateRepository->findExact($leadTid, $eventKey, $channel) !== null
            || $this->templateRepository->findExact(0, $eventKey, $channel) !== null) {
            return null;
        }
        if ($channel === 'in_app') {
            $builtin = CooperationAnnouncementEvents::builtinInApp($eventKey);
            if ($builtin) {
                return [
                    'subject' => $builtin['subject'],
                    'body' => $builtin['body'],
                    'min_interval_hours' => 0,
                    'is_active' => 1,
                ];
            }
        }
        if ($channel === 'email') {
            $builtin = CooperationAnnouncementEvents::builtinEmail($eventKey);
            if ($builtin) {
                return [
                    'subject' => $builtin['subject'],
                    'body' => $builtin['body'],
                    'min_interval_hours' => 24,
                    'is_active' => 1,
                ];
            }
        }

        return null;
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
        $head = $this->emailHead($eventKey, $mission, $vars);
        $html = CooperationEmailLayout::html($head, $body);
        $text = CooperationEmailLayout::text($head, $body);
        $userIds = $this->resolveNotifyUserIds($eventKey, $mission, $extra);
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
                $text,
                (int) ($u['tenant_id'] ?? 0) ?: null,
                null,
                ['cooperation_event' => $eventKey, 'mission_id' => (int) ($mission['id'] ?? 0)]
            );
        }
    }

    /**
     * Encadré commun des courriels : coopération, unité émettrice, attendu, échéance, bouton.
     *
     * @param array<string, mixed> $mission
     * @param array<string, string> $vars
     * @return array{title: string, issuer: string, expected: string, deadline: string, cta: string, url: string, action_required: bool}
     */
    private function emailHead(string $eventKey, array $mission, array $vars): array
    {
        $action = CooperationEmailLayout::action($eventKey);
        $deadline = match ($eventKey) {
            CooperationAnnouncementEvents::INVITATION_SENT,
            CooperationAnnouncementEvents::INVITATION_REMINDER => (string) ($vars['date_limite'] ?? ''),
            CooperationAnnouncementEvents::CONSENT_EXPIRING => (string) ($vars['fin_autorisation'] ?? ''),
            default => '',
        };

        return [
            'title' => (string) ($vars['titre_cooperation'] ?? ''),
            'issuer' => (string) ($vars['unite_support'] ?? ''),
            'expected' => $action['expected'],
            'deadline' => $deadline,
            'cta' => $action['cta'],
            'url' => $this->actionUrl($action['target'], (int) ($mission['id'] ?? 0)),
            'action_required' => $action['action_required'],
        ];
    }

    private function actionUrl(string $target, int $missionId): string
    {
        if ($missionId < 1) {
            return cooperation_mission_index_url();
        }

        return match ($target) {
            CooperationEmailLayout::TARGET_PARTICIPANTS => cooperation_mission_show_url($missionId) . '#participants',
            CooperationEmailLayout::TARGET_CONDUCT => cooperation_mission_show_url($missionId) . '#conduite',
            CooperationEmailLayout::TARGET_NEGOTIATE => cooperation_mission_negotiate_url($missionId),
            CooperationEmailLayout::TARGET_CONSENT => cooperation_mission_consent_url($missionId),
            CooperationEmailLayout::TARGET_EXCHANGE => cooperation_mission_exchange_url($missionId),
            CooperationEmailLayout::TARGET_REX => cooperation_mission_rex_url($missionId),
            CooperationEmailLayout::TARGET_INDEX => cooperation_mission_index_url(),
            default => cooperation_mission_show_url($missionId),
        };
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
        $href = $this->actionUrl(CooperationEmailLayout::action($eventKey)['target'], (int) ($mission['id'] ?? 0));
        $userIds = $this->resolveNotifyUserIds($eventKey, $mission, $extra);
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
        if ($actorUserId < 1) {
            return;
        }
        $this->forumPostRepository->create(
            $leadTid,
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
    /** @param array<string, mixed> $extra */
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
        $roleLabel = trim((string) ($extra['role_label'] ?? ''));
        $stageLabel = trim((string) ($extra['stage_label'] ?? ''));
        $memberName = trim((string) ($extra['member_display_name'] ?? ''));
        $reason = trim((string) ($extra['reason'] ?? ''));
        $consentUntil = '';
        $cu = trim((string) ($extra['consent_until'] ?? ''));
        if ($cu !== '' && ($cts = strtotime($cu)) !== false) {
            $consentUntil = date('d/m/Y H:i', $cts);
        }
        $sitrep = trim((string) ($extra['sitrep_summary'] ?? ''));
        if (mb_strlen($sitrep) > 200) {
            $sitrep = mb_substr($sitrep, 0, 197) . '…';
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
            'role_attribue' => $roleLabel !== '' ? $roleLabel : 'un rôle',
            'etape_conduite' => $stageLabel !== '' ? $stageLabel : 'mise à jour',
            'membre_designe' => $memberName,
            'motif' => $reason !== '' ? 'Motif : ' . $reason : '',
            'echeance_texte' => $deadline !== '' ? ' avant le ' . $deadline : '',
            'lien_autorisation' => cooperation_mission_consent_url($mid),
            'fin_autorisation' => $consentUntil,
            'resume_sitrep' => $sitrep,
            'attendu' => CooperationEmailLayout::action((string) ($extra['__event'] ?? ''))['expected'],
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

    /**
     * @param array<string, mixed> $mission
     * @param array<string, mixed> $extra
     * @return list<int>
     */
    private function resolveNotifyUserIds(string $eventKey, array $mission, array $extra): array
    {
        $targetTenants = $this->targetTenantIds($eventKey, $mission, $extra);
        $fromTenants = array_merge(
            $this->collectNotifyUserIds($targetTenants),
            $this->missionDesigneeIds((int) ($mission['id'] ?? 0), $targetTenants)
        );
        $explicit = [];
        $raw = $extra['notify_user_ids'] ?? null;
        if (is_array($raw)) {
            foreach ($raw as $id) {
                $uid = (int) $id;
                if ($uid > 0) {
                    $explicit[] = $uid;
                }
            }
        }
        $single = (int) ($extra['notify_user_id'] ?? 0);
        if ($single > 0) {
            $explicit[] = $single;
        }

        $exclude = (int) ($extra['exclude_user_id'] ?? 0);

        return array_values(array_filter(
            array_unique(array_merge($fromTenants, $explicit)),
            static fn (int $id): bool => $id > 0 && $id !== $exclude
        ));
    }

    /**
     * Membres désignés sur la coopération (rôles nominatifs) appartenant aux unités ciblées.
     *
     * @param list<int> $tenantIds
     * @return list<int>
     */
    private function missionDesigneeIds(int $missionId, array $tenantIds): array
    {
        if ($missionId < 1 || $tenantIds === []) {
            return [];
        }
        $ids = [];
        foreach ($this->missionRepository->listMissionMembers($missionId) as $m) {
            if (in_array((int) ($m['tenant_id'] ?? 0), $tenantIds, true)) {
                $ids[] = (int) ($m['user_id'] ?? 0);
            }
        }

        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }

    /** @param array<string, mixed> $mission */
    /** @param array<string, mixed> $extra */
    /** @return list<int> */
    private function targetTenantIds(string $eventKey, array $mission, array $extra): array
    {
        $lead = (int) ($mission['created_by_tenant_id'] ?? 0);
        $mid = (int) ($mission['id'] ?? 0);
        $partner = (int) ($extra['invited_tenant_id'] ?? $extra['partner_tenant_id'] ?? 0);

        return match ($eventKey) {
            CooperationAnnouncementEvents::INVITATION_SENT => $partner > 0 ? [$partner] : [],
            CooperationAnnouncementEvents::PARTNER_ACCEPTED,
            CooperationAnnouncementEvents::PARTNER_DECLINED => $lead > 0 ? [$lead] : [],
            CooperationAnnouncementEvents::MISSION_CREATED,
            CooperationAnnouncementEvents::PROPOSAL_UPDATED => $lead > 0 ? [$lead] : [],
            CooperationAnnouncementEvents::MISSION_ACTIVATED => $this->participantTenantIds($mid, true),
            CooperationAnnouncementEvents::MISSION_CLOSED => $this->participantTenantIds($mid, false),
            CooperationAnnouncementEvents::MEMBER_DESIGNATED => $lead > 0 ? [$lead] : [],
            CooperationAnnouncementEvents::CO_LEAD_DESIGNATED => $partner > 0 ? [$partner] : [],
            CooperationAnnouncementEvents::COUNTER_PROPOSAL_SUBMITTED => $lead > 0 ? [$lead] : [],
            CooperationAnnouncementEvents::COUNTER_PROPOSAL_ACCEPTED,
            CooperationAnnouncementEvents::COUNTER_PROPOSAL_DECLINED => $partner > 0 ? [$partner] : [],
            CooperationAnnouncementEvents::OPERATIONAL_STAGE_UPDATED => $this->participantTenantIds($mid, true),
            CooperationAnnouncementEvents::PARTNER_REMOVED => $partner > 0 ? [$partner] : [],
            CooperationAnnouncementEvents::PROPOSAL_CANCELLED => $this->tenantIdsFromExtra($extra, 'notify_tenant_ids'),
            CooperationAnnouncementEvents::INVITATION_REMINDER => $partner > 0 ? [$partner] : [],
            CooperationAnnouncementEvents::MISSION_SUSPENDED,
            CooperationAnnouncementEvents::MISSION_RESUMED,
            CooperationAnnouncementEvents::SITREP_ADDED => $this->participantTenantIds($mid, true),
            // Personnel : seul l’auteur de l’autorisation est prévenu (notify_user_id).
            CooperationAnnouncementEvents::CONSENT_EXPIRING => [],
            default => [],
        };
    }

    /**
     * @param array<string, mixed> $extra
     * @return list<int>
     */
    private function tenantIdsFromExtra(array $extra, string $key): array
    {
        $raw = $extra[$key] ?? null;
        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $raw), static fn (int $id): bool => $id > 0)));
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
