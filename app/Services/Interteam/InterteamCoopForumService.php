<?php

declare(strict_types=1);

namespace App\Services\Interteam;

use App\Repositories\ForumCategoryRepository;
use App\Repositories\ForumPostRepository;
use App\Repositories\ForumTopicRepository;
use App\Repositories\InterteamMissionRepository;
use App\Services\Cooperation\CooperationWorkflowService;

/**
 * Création de l’espace forum coopératif et clôture propre.
 */
final class InterteamCoopForumService
{
    public function __construct(
        private InterteamMissionRepository $missions,
        private ForumCategoryRepository $categories,
        private ForumTopicRepository $topics,
        private ForumPostRepository $posts,
        private CooperationWorkflowService $cooperationWorkflow
    ) {}

    /**
     * Catégorie + sujet d’accueil sur le tenant hôte, partage avec chaque partenaire actif.
     */
    public function ensureCooperativeSpace(int $missionId): void
    {
        $m = $this->missions->findById($missionId);
        if (!$m || ($m['status'] ?? '') !== 'active') {
            return;
        }
        if (!$this->missions->columnExists('interteam_missions', 'coop_forum_topic_id')) {
            return;
        }
        $existing = (int) ($m['coop_forum_topic_id'] ?? 0);
        if ($existing > 0) {
            return;
        }
        $hostTenantId = (int) ($m['created_by_tenant_id'] ?? 0);
        $creatorUserId = (int) ($m['created_by_user_id'] ?? 0);
        if ($hostTenantId <= 0 || $creatorUserId <= 0) {
            return;
        }
        $title = trim((string) ($m['title'] ?? 'Coopération'));
        $catName = 'Coopération — ' . $title;
        $catSlug = 'coop-m-' . $missionId . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
        $categoryId = $this->categories->create($hostTenantId, [
            'name' => $catName,
            'slug' => $catSlug,
            'description' => 'Espace d’échange lié à une coopération inter-unités. Il peut être clôturé lorsque la mission est terminée.',
            'display_order' => 990,
        ]);
        $topicTitle = 'Fil commun — ' . $title;
        $topicSlug = 'coop-fil-' . $missionId . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
        $topicId = $this->topics->create($hostTenantId, $categoryId, $creatorUserId, $topicTitle, $topicSlug);
        $mid = $missionId;
        $meetingUrl = function_exists('cooperation_mission_meeting_url') ? cooperation_mission_meeting_url($mid) : '';
        $orbatUrl = function_exists('cooperation_mission_orbat_url') ? cooperation_mission_orbat_url($mid) : '';
        $exchangeUrl = function_exists('cooperation_mission_exchange_url') ? cooperation_mission_exchange_url($mid) : '';
        $partners = $this->missions->listParticipants($missionId);
        $body = $this->cooperationWorkflow->buildPinnedWelcomeBody($m, $partners, $meetingUrl, $orbatUrl, $exchangeUrl);
        $this->posts->create($hostTenantId, $topicId, $creatorUserId, $body, null, null, 'information', false, null);
        $partners = $this->missions->listParticipants($missionId);
        foreach ($partners as $p) {
            $tid = (int) ($p['tenant_id'] ?? 0);
            $st = (string) ($p['status'] ?? '');
            $role = (string) ($p['role'] ?? '');
            if ($st !== 'active' || $tid <= 0 || $tid === $hostTenantId) {
                continue;
            }
            if ($role === 'partner' || $role === 'co_lead') {
                $this->missions->addForumGrant($missionId, 'topic', $topicId, $hostTenantId, $tid);
            }
        }
        $this->missions->setCoopForumIds($missionId, $categoryId, $topicId);
        $this->missions->logEvent($missionId, $creatorUserId, $hostTenantId, 'coop_forum_opened', [
            'category_id' => $categoryId,
            'topic_id' => $topicId,
        ]);
    }

    public function closeMission(int $missionId): void
    {
        $this->missions->updateMissionStatus($missionId, 'archived');
        if ($this->missions->columnExists('interteam_missions', 'cooperation_ends_at')) {
            $pdo = \App\Core\Database::getPdo();
            $pdo->prepare('UPDATE interteam_missions SET cooperation_ends_at = NOW() WHERE id = ? LIMIT 1')->execute([$missionId]);
        }
        $this->missions->deleteAllGrantsForMission($missionId);
        $m = $this->missions->findById($missionId);
        if (!$m) {
            return;
        }
        $hostId = (int) ($m['created_by_tenant_id'] ?? 0);
        $tid = isset($m['coop_forum_topic_id']) ? (int) $m['coop_forum_topic_id'] : 0;
        if ($hostId > 0 && $tid > 0) {
            $this->topics->update($tid, $hostId, ['is_locked' => 1]);
        }
    }
}
