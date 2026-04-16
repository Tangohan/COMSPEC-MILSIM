<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\ForumPostRepository;
use App\Repositories\ForumReportRepository;
use App\Repositories\UserRepository;
use App\Support\NonDefaultTenantContextGuard;

/**
 * Données dossier + indicateurs signalements (console modération, JSON).
 */
final class ForumModerationReportInsightApiController
{
    public function __construct(
        private ForumReportRepository $reportRepository,
        private ForumPostRepository $postRepository,
        private UserRepository $userRepository,
    ) {}

    public function show(Request $request, array $params = []): Response
    {
        $guard = $this->jsonGuard();
        if ($guard !== null) {
            return $guard;
        }

        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if ($id < 1) {
            return Response::json(['ok' => false, 'error' => 'Dossier introuvable.'], 404);
        }

        $report = $this->reportRepository->findEnrichedForConsole($id, $tenantId);
        if ($report === null) {
            return Response::json(['ok' => false, 'error' => 'Dossier introuvable.'], 404);
        }

        $pid = (int) ($report['post_id'] ?? 0);
        if ($pid > 0 && empty($report['post_author_id'])) {
            $post = $this->postRepository->findById($pid, $tenantId);
            if ($post) {
                $report['post_author_id'] = (int) ($post['user_id'] ?? 0);
                if ((int) ($report['topic_id'] ?? 0) < 1) {
                    $report['post_topic_id'] = (int) ($post['topic_id'] ?? 0);
                }
            }
        }

        $reporterId = (int) ($report['reporter_id'] ?? 0);
        $targetId = function_exists('forum_report_resolve_target_user_id')
            ? forum_report_resolve_target_user_id($report)
            : null;

        $reporterName = trim((string) ($report['reporter_name'] ?? ''));
        if ($reporterName === '' && $reporterId > 0) {
            $ru = $this->userRepository->findById($reporterId, $tenantId);
            $reporterName = trim((string) ($ru['display_name'] ?? '')) ?: ('Membre nº ' . $reporterId);
        }

        $targetName = null;
        if ($targetId !== null && $targetId > 0) {
            $tu = $this->userRepository->findById($targetId, $tenantId);
            $targetName = trim((string) ($tu['display_name'] ?? '')) ?: ('Membre nº ' . $targetId);
        }

        $base = rtrim((string) url(''), '/');
        $personnel = static fn (int $uid): string => $base . '/personnel/' . $uid;
        $gate = Gate::getInstance();
        $canBackOfficeMember = $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('site.support');
        $canFormalWarn = function_exists('can') && can('admin.members.moderate');

        $userCard = static function (int $uid, string $name) use ($personnel, $base, $canBackOfficeMember): array {
            $out = [
                'id' => $uid,
                'name' => $name,
                'personnel_url' => $personnel($uid),
            ];
            if ($canBackOfficeMember) {
                $out['back_office_url'] = $base . '/back-office/users/' . $uid;
            }

            return $out;
        };

        $filed = $reporterId > 0 ? $this->reportRepository->countReportsFiledByReporter($tenantId, $reporterId) : 0;
        $filedOutcome = $reporterId > 0 ? $this->reportRepository->countReportsFiledWithSubstantiveOutcome($tenantId, $reporterId) : 0;

        $onPosts = 0;
        $onPostsOutcome = 0;
        $profileMentions = 0;
        if ($targetId !== null && $targetId > 0) {
            $onPosts = $this->reportRepository->countReportsOnAuthoredPosts($tenantId, $targetId);
            $onPostsOutcome = $this->reportRepository->countReportsOnAuthoredPostsWithSubstantiveOutcome($tenantId, $targetId);
            $profileMentions = $this->reportRepository->countProfileStyleReportsMentioningUser($tenantId, $targetId);
        }

        $followUpRaw = trim((string) ($report['last_follow_up_action'] ?? ''));
        $measureLabel = self::followUpFrenchLabel($followUpRaw);

        $topicId = (int) ($report['topic_id'] ?? 0) ?: (int) ($report['post_topic_id'] ?? 0);
        $topicUrl = $topicId > 0 ? $base . '/forum/topic/' . $topicId : null;

        $status = (string) ($report['status'] ?? '');
        $statusLabel = $status === 'handled' ? 'Clôturé' : ($status === 'pending' ? 'En attente' : '—');

        return Response::json([
            'ok' => true,
            'report' => [
                'id' => $id,
                'status' => $status,
                'status_label' => $statusLabel,
                'reason' => (string) ($report['reason'] ?? ''),
                'comment' => (string) ($report['comment'] ?? ''),
                'reported_url' => (string) ($report['reported_url'] ?? ''),
                'content_kind' => (string) ($report['content_kind'] ?? ''),
                'created_at' => (string) ($report['created_at'] ?? ''),
                'handled_at' => (string) ($report['handled_at'] ?? ''),
                'handled_by_name' => (string) ($report['handled_by_name'] ?? ''),
                'measure_label' => $measureLabel,
                'resolution_note' => (string) ($report['resolution_note'] ?? ''),
                'topic_id' => $topicId,
                'topic_title' => (string) ($report['topic_title'] ?? ''),
                'topic_url' => $topicUrl,
                'post_id' => (int) ($report['post_id'] ?? 0),
                'reporter' => $reporterId > 0 ? $userCard($reporterId, $reporterName) : null,
                'target' => ($targetId !== null && $targetId > 0 && $targetName !== null)
                    ? $userCard($targetId, $targetName)
                    : null,
            ],
            'stats' => [
                'reporter' => [
                    'reports_filed' => $filed,
                    'reports_with_content_action' => $filedOutcome,
                    'hint' => 'Un écart fort entre signalements déposés et mesures sur le contenu peut orienter vers des signalements peu fondés.',
                ],
                'target' => ($targetId !== null && $targetId > 0) ? [
                    'reports_on_forum_messages' => $onPosts,
                    'reports_on_messages_with_action' => $onPostsOutcome,
                    'profile_or_fiche_mentions' => $profileMentions,
                    'hint' => 'Vue partielle : messages du fil et mentions explicites dans le texte du signalement (fiche, compte).',
                ] : null,
            ],
            'capabilities' => [
                'sanction_after_close' => $status === 'handled' && $canFormalWarn
                    && $targetId !== null && $targetId > 0
                    && (int) ($report['reporter_id'] ?? 0) !== $targetId,
            ],
        ]);
    }

    private function jsonGuard(): ?Response
    {
        if (!Session::get('user_id')) {
            return Response::json(['ok' => false, 'error' => 'Authentification requise.'], 401);
        }
        if (!function_exists('forum_user_can_moderate') || !forum_user_can_moderate()) {
            return Response::json(['ok' => false, 'error' => 'Accès réservé à la modération.'], 403);
        }
        $blocked = NonDefaultTenantContextGuard::redirectIfInvalid();
        if ($blocked !== null) {
            return Response::json([
                'ok' => false,
                'error' => 'Sélectionnez une communauté dédiée pour utiliser cet outil.',
            ], 409);
        }

        return null;
    }

    private static function followUpFrenchLabel(string $code): string
    {
        $a = strtolower(trim($code));

        return match ($a) {
            'request_correction' => 'Demande de correction',
            'escalate_support' => 'Escalade assistance plateforme',
            'watch_report' => 'Surveillance sans retrait',
            'hide_post' => 'Message masqué',
            'delete_post' => 'Message supprimé',
            'lock_topic' => 'Sujet verrouillé',
            'hide_topic' => 'Sujet masqué',
            'sanction_warn' => 'Avertissement formel',
            'close', '' => 'Clôture sans mesure',
            default => $a !== '' ? 'Mesure enregistrée' : '—',
        };
    }
}
