<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Csrf;
use App\Repositories\ForumTopicRepository;
use App\Repositories\ForumReportRepository;
use App\Repositories\ForumPostRepository;
use App\Repositories\UserRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\Community\CommunityReportNotificationService;
use App\Services\Moderation\ModerationService;

class ForumModerationController
{
    public function __construct(
        private ForumTopicRepository $topicRepository,
        private ForumReportRepository $reportRepository,
        private ForumPostRepository $postRepository,
        private AuditService $auditService,
        private ModerationService $moderationService,
        private UserRepository $userRepository,
        private CommunityReportNotificationService $communityReportNotificationService,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        return Response::redirect(url('back-office/forum-moderation'));
    }

    public function handleReport(Request $request, array $params = []): Response
    {
        $ok = function_exists('forum_user_can_moderate') && forum_user_can_moderate();
        if (!$ok) {
            Session::flash('error', 'Accès refusé.');

            return Response::redirect(url('forum'));
        }

        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }

        if ($request->method() !== 'POST' || !Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Requête invalide.');

            return Response::redirect(url('back-office/forum-moderation'));
        }

        $id = (int) ($params['id'] ?? 0);
        $report = $this->reportRepository->findById($id, $tenantId);
        if (!$report) {
            Session::flash('error', 'Signalement introuvable.');

            return Response::redirect(url('back-office/forum-moderation'));
        }

        $pid = (int) ($report['post_id'] ?? 0);
        if ($pid > 0) {
            $post = $this->postRepository->findById($pid, $tenantId);
            if ($post) {
                $report['post_author_id'] = (int) ($post['user_id'] ?? 0);
                if ((int) ($report['topic_id'] ?? 0) < 1) {
                    $report['post_topic_id'] = (int) ($post['topic_id'] ?? 0);
                }
            }
        }

        $followUp = trim((string) $request->input('follow_up', 'close'));
        $note = trim((string) $request->input('moderator_note', ''));
        if (strlen($note) > 500) {
            $note = mb_substr($note, 0, 500);
        }

        $outcomes = [];

        try {
            $this->applyFollowUp($followUp, $report, $tenantId, $userId, $id, $note, $outcomes);
        } catch (\Throwable) {
            Session::flash('error', 'L’action n’a pas pu être enregistrée. Vérifiez les droits ou réessayez.');

            return Response::redirect(url('back-office/forum-moderation'));
        }

        $this->reportRepository->markHandled($id, $tenantId, $userId);
        $this->reportRepository->saveResolution($id, $tenantId, $followUp, $note);
        try {
            $measureHuman = self::followUpPublicLabel($followUp);
            $actionLabel = $followUp === '' || $followUp === 'close'
                ? 'Dossier clôturé sans mesure supplémentaire'
                : 'Dossier clôturé — ' . $measureHuman;
            $this->reportRepository->addTimelineEvent(
                $tenantId,
                $id,
                $userId,
                'report_closed',
                $actionLabel,
                $note !== '' ? $note : null
            );
        } catch (\Throwable) {
        }

        try {
            $this->communityReportNotificationService->notifyReportHandled(
                $tenantId,
                $id,
                (int) ($report['reporter_id'] ?? 0),
                $userId
            );
        } catch (\Throwable) {
        }

        $summary = $outcomes !== [] ? implode(' ', $outcomes) . ' ' : '';
        Session::flash('success', $summary . 'Dossier clôturé.');

        return Response::redirect(url('back-office/forum-moderation'));
    }

    /**
     * Remet un dossier clos dans la file d’attente et alerte l’équipe (e-mails selon préférences).
     */
    public function reopenReport(Request $request, array $params = []): Response
    {
        $ok = function_exists('forum_user_can_moderate') && forum_user_can_moderate();
        if (!$ok) {
            Session::flash('error', 'Accès refusé.');

            return Response::redirect(url('forum'));
        }

        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }

        if ($request->method() !== 'POST' || !Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Requête invalide.');

            return Response::redirect(url('back-office/forum-moderation'));
        }

        $id = (int) ($params['id'] ?? 0);
        $report = $this->reportRepository->findById($id, $tenantId);
        if (!$report || (string) ($report['status'] ?? '') !== 'handled') {
            Session::flash('error', 'Ce dossier n’est pas clos ou est introuvable.');

            return Response::redirect(url('back-office/forum-moderation'));
        }

        $note = trim((string) $request->input('reopen_note', ''));
        if (strlen($note) > 500) {
            $note = mb_substr($note, 0, 500);
        }
        $notifyReporter = $request->input('notify_reporter') === '1';

        if (!$this->reportRepository->reopenToPending($id, $tenantId)) {
            Session::flash('error', 'La réouverture n’a pas pu être enregistrée.');

            return Response::redirect(url('back-office/forum-moderation'));
        }

        $timelineDetail = $note !== '' ? $note : null;
        try {
            $this->reportRepository->addTimelineEvent(
                $tenantId,
                $id,
                $userId,
                'report_reopened',
                'Dossier rouvert et remis en file d’attente',
                $timelineDetail
            );
        } catch (\Throwable) {
        }

        $reasonLine = trim((string) ($report['reason'] ?? ''));
        try {
            $this->communityReportNotificationService->notifyReportReopened(
                $tenantId,
                $id,
                $userId,
                (int) ($report['reporter_id'] ?? 0),
                $reasonLine !== '' ? $reasonLine : 'Dossier rouvert',
                $notifyReporter,
                $note !== '' ? $note : null
            );
        } catch (\Throwable) {
        }

        Session::flash('success', 'Le dossier a été rouvert. Les modérateurs habilités ont été alertés.');

        return Response::redirect(url('back-office/forum-moderation'));
    }

    /**
     * Mesures sur un dossier déjà clos (contenu, suivi, sanctions membre) sans changer le statut handled.
     */
    public function postCloseFollowUp(Request $request, array $params = []): Response
    {
        $ok = function_exists('forum_user_can_moderate') && forum_user_can_moderate();
        if (!$ok) {
            Session::flash('error', 'Accès refusé.');

            return Response::redirect(url('forum'));
        }

        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }

        if ($request->method() !== 'POST' || !Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Requête invalide.');

            return Response::redirect(url('back-office/forum-moderation'));
        }

        $id = (int) ($params['id'] ?? 0);
        $report = $this->reportRepository->findById($id, $tenantId);
        if (!$report || (string) ($report['status'] ?? '') !== 'handled') {
            Session::flash('error', 'Ce dossier n’est pas clos ou est introuvable.');

            return Response::redirect(url('back-office/forum-moderation'));
        }

        $pid = (int) ($report['post_id'] ?? 0);
        if ($pid > 0) {
            $post = $this->postRepository->findById($pid, $tenantId);
            if ($post) {
                $report['post_author_id'] = (int) ($post['user_id'] ?? 0);
                if ((int) ($report['topic_id'] ?? 0) < 1) {
                    $report['post_topic_id'] = (int) ($post['topic_id'] ?? 0);
                }
            }
        }

        $action = strtolower(trim((string) $request->input('post_close_action', '')));
        $allowed = [
            'hide_post', 'delete_post', 'lock_topic', 'hide_topic',
            'request_correction', 'escalate_support', 'watch_report',
            'sanction_warn',
            'sanction_mute_24h', 'sanction_mute_7d', 'sanction_mute_30d',
            'sanction_suspend_7d', 'sanction_suspend_30d',
            'sanction_ban',
        ];
        if (!in_array($action, $allowed, true)) {
            Session::flash('error', 'Cette mesure n’est pas disponible pour ce dossier.');

            return Response::redirect(url('back-office/forum-moderation'));
        }

        if ($action === 'sanction_ban' && (string) $request->input('confirm_permanent_ban') !== '1') {
            Session::flash('error', 'Pour une exclusion définitive, confirmez l’action dans le formulaire.');

            return Response::redirect(url('back-office/forum-moderation'));
        }

        $note = trim((string) $request->input('moderator_note', ''));
        if (strlen($note) > 500) {
            $note = mb_substr($note, 0, 500);
        }

        $outcomes = [];
        try {
            if (str_starts_with($action, 'sanction_mute_') || str_starts_with($action, 'sanction_suspend_') || $action === 'sanction_ban') {
                $expires = match ($action) {
                    'sanction_mute_24h' => (new \DateTimeImmutable('+24 hours')),
                    'sanction_mute_7d' => (new \DateTimeImmutable('+7 days')),
                    'sanction_mute_30d' => (new \DateTimeImmutable('+30 days')),
                    'sanction_suspend_7d' => (new \DateTimeImmutable('+7 days')),
                    'sanction_suspend_30d' => (new \DateTimeImmutable('+30 days')),
                    'sanction_ban' => null,
                    default => throw new \InvalidArgumentException('sanction'),
                };
                $type = str_starts_with($action, 'sanction_mute_') ? 'mute' : (str_starts_with($action, 'sanction_suspend_') ? 'suspend' : 'ban');
                $logModeration = function (string $a, ?string $d = null) use ($tenantId, $userId, $id): void {
                    $payload = ['report_id' => $id, 'follow_up' => $a];
                    if ($d !== null && $d !== '') {
                        $payload['detail'] = $d;
                    }
                    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $this->auditService->log(
                        AuditAction::FORUM_MODERATION,
                        $tenantId,
                        $userId,
                        'forum_report_resolution',
                        $id,
                        null,
                        $json !== false ? $json : $a
                    );
                };
                $this->followUpSanctionMuteSuspendBan(
                    $report,
                    $tenantId,
                    $userId,
                    $id,
                    $type,
                    $expires,
                    $note,
                    $logModeration,
                    $outcomes
                );
            } elseif ($action === 'sanction_warn') {
                $this->applyFollowUp('sanction_warn', $report, $tenantId, $userId, $id, $note, $outcomes);
            } else {
                $this->applyFollowUp($action, $report, $tenantId, $userId, $id, $note, $outcomes);
            }
        } catch (\Throwable) {
            Session::flash('error', 'La mesure n’a pas pu être enregistrée. Vérifiez les droits, le contenu visé ou le membre concerné.');

            return Response::redirect(url('back-office/forum-moderation'));
        }

        $timelineTitle = self::postCloseActionTimelineTitle($action);
        try {
            $this->reportRepository->addTimelineEvent(
                $tenantId,
                $id,
                $userId,
                'post_close_measure',
                $timelineTitle,
                $note !== '' ? $note : null
            );
        } catch (\Throwable) {
        }

        $summary = $outcomes !== [] ? implode(' ', $outcomes) . ' ' : '';
        Session::flash('success', $summary . 'Mesure enregistrée sur le dossier clos.');

        return Response::redirect(url('back-office/forum-moderation'));
    }

    public function claimReport(Request $request, array $params = []): Response
    {
        return $this->setClaimState($request, $params, true);
    }

    public function unclaimReport(Request $request, array $params = []): Response
    {
        return $this->setClaimState($request, $params, false);
    }

    public function addReportComment(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }
        if ($request->method() !== 'POST' || !Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Requête invalide.');

            return Response::redirect(url('back-office/forum-moderation'));
        }
        $id = (int) ($params['id'] ?? 0);
        $report = $this->reportRepository->findById($id, $tenantId);
        if ($id < 1 || !$report) {
            Session::flash('error', 'Dossier introuvable.');

            return Response::redirect(url('back-office/forum-moderation'));
        }
        $comment = trim((string) $request->input('timeline_comment', ''));
        if ($comment === '') {
            Session::flash('error', 'Le commentaire est vide.');

            return Response::redirect(url('back-office/forum-moderation'));
        }
        if (mb_strlen($comment) > 1200) {
            $comment = mb_substr($comment, 0, 1200);
        }
        try {
            $this->reportRepository->addTimelineEvent($tenantId, $id, $userId, 'comment', 'Commentaire modération', $comment);
        } catch (\Throwable) {
            Session::flash('error', 'Impossible d’enregistrer le commentaire.');

            return Response::redirect(url('back-office/forum-moderation'));
        }
        Session::flash('success', 'Commentaire ajouté au dossier.');

        return Response::redirect(url('back-office/forum-moderation'));
    }

    /**
     * @param list<string> $outcomes
     */
    private function applyFollowUp(
        string $followUp,
        array $report,
        int $tenantId,
        int $actorUserId,
        int $reportId,
        string $moderatorNote,
        array &$outcomes
    ): void {
        if ($followUp === '' || $followUp === 'close') {
            return;
        }

        $logModeration = function (string $action, ?string $detail = null) use ($tenantId, $actorUserId, $reportId): void {
            $payload = ['report_id' => $reportId, 'follow_up' => $action];
            if ($detail !== null && $detail !== '') {
                $payload['detail'] = $detail;
            }
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $this->auditService->log(
                AuditAction::FORUM_MODERATION,
                $tenantId,
                $actorUserId,
                'forum_report_resolution',
                $reportId,
                null,
                $json !== false ? $json : $action
            );
        };

        $canModerateContent = function_exists('forum_user_can_moderate') && forum_user_can_moderate();

        match ($followUp) {
            'hide_post' => $this->followUpHidePost($report, $tenantId, $canModerateContent, $logModeration, $outcomes),
            'delete_post' => $this->followUpDeletePost($report, $tenantId, $logModeration, $outcomes),
            'lock_topic' => $this->followUpLockTopic($report, $tenantId, $canModerateContent, $logModeration, $outcomes),
            'hide_topic' => $this->followUpHideTopic($report, $tenantId, $canModerateContent, $logModeration, $outcomes),
            'sanction_warn' => $this->followUpSanctionWarn($report, $tenantId, $actorUserId, $reportId, $moderatorNote, $logModeration, $outcomes),
            'request_correction' => $this->followUpRequestCorrection($moderatorNote, $logModeration, $outcomes),
            'escalate_support' => $this->followUpEscalateSupport($moderatorNote, $logModeration, $outcomes),
            'watch_report' => $this->followUpWatchReport($moderatorNote, $logModeration, $outcomes),
            default => throw new \InvalidArgumentException('Action non reconnue.'),
        };
    }

    /**
     * @param callable(string, ?string): void $logModeration
     * @param list<string> $outcomes
     */
    private function followUpHidePost(
        array $report,
        int $tenantId,
        bool $canModerateContent,
        callable $logModeration,
        array &$outcomes
    ): void {
        if (!$canModerateContent) {
            throw new \RuntimeException('hide_post denied');
        }
        $postId = (int) ($report['post_id'] ?? 0);
        if ($postId < 1) {
            throw new \RuntimeException('no post');
        }
        $post = $this->postRepository->findById($postId, $tenantId);
        if (!$post) {
            throw new \RuntimeException('post missing');
        }
        $this->postRepository->setHidden($postId, $tenantId, true);
        $logModeration('hide_post', (string) $postId);
        $outcomes[] = 'Le message a été masqué pour les membres.';
    }

    /**
     * @param callable(string, ?string): void $logModeration
     * @param list<string> $outcomes
     */
    private function followUpDeletePost(
        array $report,
        int $tenantId,
        callable $logModeration,
        array &$outcomes
    ): void {
        if (!function_exists('can') || !can('forum.post.delete_any')) {
            throw new \RuntimeException('delete denied');
        }
        $postId = (int) ($report['post_id'] ?? 0);
        if ($postId < 1) {
            throw new \RuntimeException('no post');
        }
        $post = $this->postRepository->findById($postId, $tenantId);
        if (!$post) {
            throw new \RuntimeException('post missing');
        }
        $topicId = (int) $post['topic_id'];
        $this->postRepository->delete($postId, $tenantId);
        $this->topicRepository->touchUpdatedAt($topicId);
        $logModeration('delete_post', (string) $postId);
        $outcomes[] = 'Le message a été supprimé.';
    }

    /**
     * @param callable(string, ?string): void $logModeration
     * @param list<string> $outcomes
     */
    private function followUpLockTopic(
        array $report,
        int $tenantId,
        bool $canModerateContent,
        callable $logModeration,
        array &$outcomes
    ): void {
        if (!$canModerateContent) {
            throw new \RuntimeException('lock denied');
        }
        $topicId = $this->resolveTopicIdFromReport($report, $tenantId);
        if ($topicId < 1) {
            throw new \RuntimeException('no topic');
        }
        $topic = $this->topicRepository->findById($topicId, $tenantId);
        if (!$topic) {
            throw new \RuntimeException('topic missing');
        }
        $this->topicRepository->update($topicId, $tenantId, ['is_locked' => 1]);
        $logModeration('lock_topic', (string) $topicId);
        $outcomes[] = 'Le sujet a été verrouillé.';
    }

    /**
     * @param callable(string, ?string): void $logModeration
     * @param list<string> $outcomes
     */
    private function followUpHideTopic(
        array $report,
        int $tenantId,
        bool $canModerateContent,
        callable $logModeration,
        array &$outcomes
    ): void {
        if (!$canModerateContent) {
            throw new \RuntimeException('hide topic denied');
        }
        $topicId = $this->resolveTopicIdFromReport($report, $tenantId);
        if ($topicId < 1) {
            throw new \RuntimeException('no topic');
        }
        $topic = $this->topicRepository->findById($topicId, $tenantId);
        if (!$topic) {
            throw new \RuntimeException('topic missing');
        }
        $this->topicRepository->update($topicId, $tenantId, ['is_hidden' => 1]);
        $logModeration('hide_topic', (string) $topicId);
        $outcomes[] = 'Le sujet a été retiré de la vue des membres.';
    }

    /**
     * @param callable(string, ?string): void $logModeration
     * @param list<string> $outcomes
     */
    private function followUpSanctionWarn(
        array $report,
        int $tenantId,
        int $actorUserId,
        int $reportId,
        string $moderatorNote,
        callable $logModeration,
        array &$outcomes
    ): void {
        if (!$this->mayApplyFormalMemberWarning()) {
            throw new \RuntimeException('sanction denied');
        }
        $targetId = function_exists('forum_report_resolve_target_user_id')
            ? forum_report_resolve_target_user_id($report)
            : null;
        if ($targetId === null || $targetId < 1) {
            throw new \RuntimeException('no target user');
        }
        if ($targetId === $actorUserId) {
            throw new \RuntimeException('self target');
        }
        if ((int) ($report['reporter_id'] ?? 0) === $targetId) {
            throw new \RuntimeException('reporter is target');
        }
        $user = $this->userRepository->findById($targetId, $tenantId);
        if (!$user) {
            throw new \RuntimeException('user missing');
        }
        $reason = 'Suite au signalement n° ' . $reportId . '.';
        if ($moderatorNote !== '') {
            $reason .= "\n" . $moderatorNote;
        }
        $this->moderationService->applySanction(
            $tenantId,
            $actorUserId,
            $targetId,
            'warn',
            $reason,
            null,
            []
        );
        $logModeration('sanction_warn', (string) $targetId);
        $outcomes[] = 'Un avertissement formel a été enregistré sur la fiche du membre.';
    }

    /**
     * @param callable(string, ?string): void $logModeration
     * @param list<string> $outcomes
     */
    private function followUpSanctionMuteSuspendBan(
        array $report,
        int $tenantId,
        int $actorUserId,
        int $reportId,
        string $sanctionType,
        ?\DateTimeImmutable $expiresAt,
        string $moderatorNote,
        callable $logModeration,
        array &$outcomes
    ): void {
        if (!$this->mayApplyFormalMemberWarning()) {
            throw new \RuntimeException('sanction denied');
        }
        if (!in_array($sanctionType, ['mute', 'suspend', 'ban'], true)) {
            throw new \InvalidArgumentException('bad type');
        }
        $targetId = function_exists('forum_report_resolve_target_user_id')
            ? forum_report_resolve_target_user_id($report)
            : null;
        if ($targetId === null || $targetId < 1) {
            throw new \RuntimeException('no target user');
        }
        if ($targetId === $actorUserId) {
            throw new \RuntimeException('self target');
        }
        if ((int) ($report['reporter_id'] ?? 0) === $targetId) {
            throw new \RuntimeException('reporter is target');
        }
        $user = $this->userRepository->findById($targetId, $tenantId);
        if (!$user) {
            throw new \RuntimeException('user missing');
        }
        $reason = 'Mesure après clôture du signalement n° ' . $reportId . '.';
        if ($moderatorNote !== '') {
            $reason .= "\n" . $moderatorNote;
        }
        $this->moderationService->applySanction(
            $tenantId,
            $actorUserId,
            $targetId,
            $sanctionType,
            $reason,
            $expiresAt,
            []
        );
        $logModeration('sanction_' . $sanctionType, (string) $targetId);
        $outcomes[] = match ($sanctionType) {
            'mute' => 'Un silence temporaire a été appliqué sur le compte du membre concerné.',
            'suspend' => 'Une suspension temporaire a été appliquée sur le compte du membre concerné.',
            'ban' => 'Une exclusion définitive a été enregistrée sur le compte du membre concerné.',
            default => 'Sanction enregistrée sur le compte du membre concerné.',
        };
    }

    /**
     * @param callable(string, ?string): void $logModeration
     * @param list<string> $outcomes
     */
    private function followUpRequestCorrection(string $moderatorNote, callable $logModeration, array &$outcomes): void
    {
        $logModeration('request_correction', $moderatorNote);
        $outcomes[] = 'Mesure enregistrée : demande de correction du contenu signalé.';
    }

    /**
     * @param callable(string, ?string): void $logModeration
     * @param list<string> $outcomes
     */
    private function followUpEscalateSupport(string $moderatorNote, callable $logModeration, array &$outcomes): void
    {
        $logModeration('escalate_support', $moderatorNote);
        $outcomes[] = 'Mesure enregistrée : dossier escaladé vers l’assistance plateforme.';
    }

    /**
     * @param callable(string, ?string): void $logModeration
     * @param list<string> $outcomes
     */
    private function followUpWatchReport(string $moderatorNote, callable $logModeration, array &$outcomes): void
    {
        $logModeration('watch_report', $moderatorNote);
        $outcomes[] = 'Mesure enregistrée : contenu conservé sous surveillance.';
    }

    private function mayApplyFormalMemberWarning(): bool
    {
        if (!function_exists('can')) {
            return false;
        }

        return can('admin.members.moderate');
    }

    private static function followUpPublicLabel(string $followUp): string
    {
        $a = strtolower(trim($followUp));

        return match ($a) {
            'request_correction' => 'demande de correction du contenu',
            'escalate_support' => 'escalade vers l’assistance plateforme',
            'watch_report' => 'surveillance sans retrait du contenu',
            'hide_post' => 'masquage du message signalé',
            'delete_post' => 'suppression du message signalé',
            'lock_topic' => 'verrouillage du sujet',
            'hide_topic' => 'retrait du sujet de la liste',
            'sanction_warn' => 'avertissement formel au membre concerné',
            'close', '' => 'clôture sans autre mesure',
            default => 'mesure enregistrée',
        };
    }

    private static function postCloseActionTimelineTitle(string $action): string
    {
        return match ($action) {
            'hide_post' => 'Après clôture : message masqué',
            'delete_post' => 'Après clôture : message supprimé',
            'lock_topic' => 'Après clôture : sujet verrouillé',
            'hide_topic' => 'Après clôture : sujet retiré de la liste',
            'request_correction' => 'Après clôture : demande de correction enregistrée',
            'escalate_support' => 'Après clôture : escalade assistance plateforme',
            'watch_report' => 'Après clôture : surveillance sans retrait',
            'sanction_warn' => 'Après clôture : avertissement formel',
            'sanction_mute_24h' => 'Après clôture : silence 24 h sur le compte',
            'sanction_mute_7d' => 'Après clôture : silence 7 jours sur le compte',
            'sanction_mute_30d' => 'Après clôture : silence 30 jours sur le compte',
            'sanction_suspend_7d' => 'Après clôture : suspension 7 jours sur le compte',
            'sanction_suspend_30d' => 'Après clôture : suspension 30 jours sur le compte',
            'sanction_ban' => 'Après clôture : exclusion définitive du compte',
            default => 'Après clôture : mesure enregistrée',
        };
    }

    private function setClaimState(Request $request, array $params, bool $claim): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }
        if ($request->method() !== 'POST' || !Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Requête invalide.');

            return Response::redirect(url('back-office/forum-moderation'));
        }
        $id = (int) ($params['id'] ?? 0);
        $report = $this->reportRepository->findById($id, $tenantId);
        if ($id < 1 || !$report || (string) ($report['status'] ?? '') !== 'pending') {
            Session::flash('error', 'Dossier non disponible.');

            return Response::redirect(url('back-office/forum-moderation'));
        }
        $ok = $claim
            ? $this->reportRepository->assignTo($id, $tenantId, $userId)
            : $this->reportRepository->unassign($id, $tenantId);
        if (!$ok) {
            Session::flash('error', 'Action impossible sur l’affectation.');

            return Response::redirect(url('back-office/forum-moderation'));
        }
        try {
            $this->reportRepository->addTimelineEvent(
                $tenantId,
                $id,
                $userId,
                $claim ? 'claimed' : 'released',
                $claim ? 'Dossier pris en charge' : 'Dossier remis dans la file',
                null
            );
        } catch (\Throwable) {
        }
        Session::flash('success', $claim ? 'Dossier pris en charge.' : 'Affectation retirée.');

        return Response::redirect(url('back-office/forum-moderation'));
    }

    private function resolveTopicIdFromReport(array $report, int $tenantId): int
    {
        $tid = (int) ($report['topic_id'] ?? 0);
        if ($tid > 0) {
            return $tid;
        }
        $tid = (int) ($report['post_topic_id'] ?? 0);
        if ($tid > 0) {
            return $tid;
        }
        $pid = (int) ($report['post_id'] ?? 0);
        if ($pid < 1) {
            return 0;
        }
        $post = $this->postRepository->findById($pid, $tenantId);

        return $post ? (int) $post['topic_id'] : 0;
    }

    public function lockTopic(Request $request, array $params = []): Response
    {
        return $this->setTopicLock($request, $params, true);
    }

    public function unlockTopic(Request $request, array $params = []): Response
    {
        return $this->setTopicLock($request, $params, false);
    }

    public function pinTopic(Request $request, array $params = []): Response
    {
        return $this->setTopicPin($request, $params, true);
    }

    public function unpinTopic(Request $request, array $params = []): Response
    {
        return $this->setTopicPin($request, $params, false);
    }

    private function setTopicLock(Request $request, array $params, bool $locked): Response
    {
        if (!function_exists('can') || !can('forum.topic.lock')) {
            Session::flash('error', 'Accès refusé.');

            return Response::redirect(url('forum'));
        }

        $tenantId = Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $topic = $this->topicRepository->findById($id, $tenantId);
        if (!$topic) {
            Session::flash('error', 'Sujet introuvable.');

            return Response::redirect(url('forum'));
        }

        if ($request->method() === 'POST' && Csrf::validate($request->input('_csrf_token'))) {
            $this->topicRepository->update($id, $tenantId, ['is_locked' => $locked ? 1 : 0]);
            Session::flash('success', $locked ? 'Sujet verrouillé.' : 'Sujet déverrouillé.');
        }

        return Response::redirect(url('forum/topic/' . $id));
    }

    private function setTopicPin(Request $request, array $params, bool $pinned): Response
    {
        if (!function_exists('can') || !can('forum.topic.pin')) {
            Session::flash('error', 'Accès refusé.');

            return Response::redirect(url('forum'));
        }

        $tenantId = Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $topic = $this->topicRepository->findById($id, $tenantId);
        if (!$topic) {
            Session::flash('error', 'Sujet introuvable.');

            return Response::redirect(url('forum'));
        }

        if ($request->method() === 'POST' && Csrf::validate($request->input('_csrf_token'))) {
            $this->topicRepository->update($id, $tenantId, ['is_pinned' => $pinned ? 1 : 0]);
            Session::flash('success', $pinned ? 'Sujet épinglé.' : 'Sujet désépinglé.');
        }

        return Response::redirect(url('forum/topic/' . $id));
    }
}
