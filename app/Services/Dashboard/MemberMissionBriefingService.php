<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Repositories\CommunityEventRepository;
use App\Repositories\TrainingCourseLmsSocialRepository;
use App\Repositories\TrainingEnrollmentRepository;
use App\Repositories\TrainingProgressRepository;
use App\Repositories\UserRepository;
use App\Services\Platform\FeatureGateService;
use App\Services\Training\TrainingService;

/**
 * Synthèse « mission du jour » pour le tableau de bord membre (OP, formations, modpack, consignes courtes).
 */
final class MemberMissionBriefingService
{
    public function __construct(
        private CommunityEventRepository $events,
        private FeatureGateService $featureGate,
        private TrainingEnrollmentRepository $enrollmentRepository,
        private TrainingService $trainingService,
        private TrainingProgressRepository $progressRepository,
        private TrainingCourseLmsSocialRepository $lmsSocialRepository,
        private UserRepository $userRepository,
    ) {}

    /**
     * @param list<array{id: int, kind: string, label: string, href: ?string, notice_text: ?string}> $dashboardPins
     * @return array{
     *   next_op: ?array{title: string, starts_at: string, list_href: string, rsvp_label: ?string, summary?: string},
     *   upcoming_ops: list<array{title: string, starts_at: string, list_href: string, rsvp_label: ?string, summary: string}>,
     *   trainings: list<array<string, mixed>>,
     *   modpack: ?array{title: string, detail_href: string, has_pack: bool},
     *   consigne_excerpt: ?string,
     *   pins_anchor_href: string
     * }
     */
    public function buildForViewer(
        int $tenantId,
        int $userId,
        ?array $modpack,
        array $dashboardPins,
        bool $trainingFeatureEnabled,
    ): array {
        $nextOp = null;
        $upcomingOps = [];
        if ($this->featureGate->allowsLimitedFeatureModule($tenantId, 'events')) {
            $rows = $this->events->upcomingForTenantWithUserRsvp($tenantId, $userId, 5);
            foreach ($rows as $ev) {
                $title = trim((string) ($ev['title'] ?? ''));
                if ($title === '') {
                    $title = 'Opération à venir';
                }
                $starts = (string) ($ev['starts_at'] ?? '');
                $rsvp = isset($ev['rsvp_status']) ? (string) $ev['rsvp_status'] : '';
                $item = [
                    'id' => (int) ($ev['id'] ?? 0),
                    'title' => $title,
                    'starts_at' => $starts,
                    'list_href' => url('evenements'),
                    'rsvp_status' => $rsvp,
                    'rsvp_label' => self::rsvpLabel($rsvp),
                    'summary' => trim(preg_replace('/\s+/', ' ', strip_tags((string) ($ev['description'] ?? ''))) ?? ''),
                ];
                if (mb_strlen($item['summary']) > 120) {
                    $item['summary'] = mb_substr($item['summary'], 0, 117) . '…';
                }
                $upcomingOps[] = $item;
            }
            if ($upcomingOps !== []) {
                $nextOp = $upcomingOps[0];
            }
        }

        $trainings = [];
        if ($trainingFeatureEnabled) {
            $enrollments = $this->enrollmentRepository->listByUserId($userId, $tenantId);
            $open = array_values(array_filter(
                $enrollments,
                static fn (array $e): bool => in_array((string) ($e['status'] ?? ''), ['assigned', 'in_progress', 'pending_approval'], true)
            ));
            usort($open, static function (array $a, array $b): int {
                $ma = !empty($a['is_mandatory']) ? 1 : 0;
                $mb = !empty($b['is_mandatory']) ? 1 : 0;
                if ($ma !== $mb) {
                    return $mb <=> $ma;
                }
                $ea = (string) ($a['expires_at'] ?? '');
                $eb = (string) ($b['expires_at'] ?? '');
                if ($ea !== '' && $eb !== '') {
                    return strcmp($ea, $eb);
                }
                if ($ea !== '') {
                    return -1;
                }
                if ($eb !== '') {
                    return 1;
                }

                return strcmp((string) ($b['assigned_at'] ?? ''), (string) ($a['assigned_at'] ?? ''));
            });
            foreach (array_slice($open, 0, 3) as $e) {
                $trainings[] = $this->buildTrainingCard($e, $tenantId);
            }
        }

        $modpackBlock = null;
        if ($modpack && !empty($modpack['id'])) {
            $slug = trim((string) ($modpack['slug'] ?? ''));
            $detailHref = $slug !== '' ? url('modpacks/' . rawurlencode($slug)) : url('modpacks');
            $mtitle = trim((string) ($modpack['name'] ?? $modpack['title'] ?? ''));
            if ($mtitle === '') {
                $mtitle = 'Modpack';
            }
            $modpackBlock = [
                'title' => $mtitle,
                'detail_href' => $detailHref,
                'has_pack' => true,
            ];
        } else {
            $modpackBlock = [
                'title' => 'Modpack communautaire',
                'detail_href' => url('modpacks'),
                'has_pack' => false,
            ];
        }

        $consigneExcerpt = null;
        foreach ($dashboardPins as $pin) {
            if (($pin['kind'] ?? '') === 'notice' && !empty($pin['notice_text'])) {
                $raw = trim(preg_replace('/\s+/', ' ', (string) $pin['notice_text']) ?? '');
                if ($raw !== '') {
                    $consigneExcerpt = mb_strlen($raw) > 160 ? mb_substr($raw, 0, 157) . '…' : $raw;
                }
                break;
            }
        }

        return [
            'next_op' => $nextOp,
            'upcoming_ops' => $upcomingOps,
            'trainings' => $trainings,
            'modpack' => $modpackBlock,
            'consigne_excerpt' => $consigneExcerpt,
            'pins_anchor_href' => url('dashboard') . '#dashboard-community-pins',
        ];
    }

    /**
     * @param array<string, mixed> $e
     * @return array<string, mixed>
     */
    private function buildTrainingCard(array $e, int $tenantId): array
    {
        $eid = (int) ($e['id'] ?? 0);
        $courseId = (int) ($e['course_id'] ?? 0);
        $slug = trim((string) ($e['course_slug'] ?? ''));
        $title = trim((string) ($e['course_title'] ?? ''));
        if ($title === '') {
            $title = 'Formation';
        }
        $href = $slug !== '' ? url('formations/' . rawurlencode($slug)) : url('formations/mes-formations');
        $pct = $eid > 0 ? (int) round($this->trainingService->getGlobalProgress($eid)) : 0;
        $pct = max(0, min(100, $pct));
        $mandatory = !empty($e['is_mandatory']);
        $certifying = !empty($e['is_certifying']);
        $status = (string) ($e['status'] ?? '');
        $exp = trim((string) ($e['expires_at'] ?? ''));
        $urgent = $mandatory || self::expiresWithinDays($exp, 14);

        $category = trim((string) ($e['category'] ?? ''));
        $levelKey = trim((string) ($e['level'] ?? ''));
        $levelLabel = self::levelLabelFr($levelKey);
        $estimatedMinutes = max(0, (int) ($e['estimated_minutes'] ?? 0));
        $remainingMinutes = null;
        if ($estimatedMinutes > 0 && $pct < 100) {
            $remainingMinutes = (int) max(1, (int) round($estimatedMinutes * (100 - $pct) / 100));
        } elseif ($estimatedMinutes > 0 && $pct >= 100) {
            $remainingMinutes = 0;
        }

        $expiresLabel = null;
        if ($exp !== '') {
            $expTs = strtotime($exp);
            if ($expTs !== false) {
                $expiresLabel = date('d/m/Y', $expTs);
            }
        }

        $progressRows = $eid > 0 ? $this->progressRepository->listByEnrollmentId($eid) : [];
        $course = $courseId > 0 ? $this->trainingService->getCourseWithStructure($courseId, $tenantId) : null;
        $nextStep = $this->resolveNextStepLabel($course, $status, $progressRows);
        $lastActivityLabel = $this->resolveLastActivityLabel($e, $progressRows);
        $sessionInfo = $courseId > 0 ? $this->resolveNextSessionInfo($courseId) : null;

        $deadlineLabel = $expiresLabel;
        $deadlineKind = $expiresLabel !== null ? 'expires' : null;
        if ($deadlineLabel === null && $sessionInfo !== null) {
            $deadlineLabel = $sessionInfo['date_label'];
            $deadlineKind = 'session';
        }

        $metaParts = [];
        if ($category !== '') {
            $metaParts[] = $category;
        }
        if ($levelLabel !== null) {
            $metaParts[] = $levelLabel;
        }
        if ($certifying) {
            $metaParts[] = 'Certifiant';
        }
        $statusLabel = self::enrollmentStatusLabelFr($status);
        if ($statusLabel !== null) {
            $metaParts[] = $statusLabel;
        }

        $detailParts = [];
        if ($nextStep !== null) {
            $detailParts[] = $nextStep;
        }
        if ($lastActivityLabel !== null) {
            $detailParts[] = 'Dernière activité : ' . $lastActivityLabel;
        }
        if ($sessionInfo !== null && $deadlineKind !== 'session') {
            $detailParts[] = 'Prochaine session : ' . $sessionInfo['date_label'];
        }
        $instructorName = is_array($sessionInfo) ? ($sessionInfo['instructor'] ?? null) : null;
        if (is_string($instructorName) && $instructorName !== '') {
            $detailParts[] = 'Formateur : ' . $instructorName;
        } else {
            $ownerName = $this->resolvePedagogicalOwnerName($course);
            if ($ownerName !== null) {
                $detailParts[] = 'Référent : ' . $ownerName;
            }
        }

        $remainingLabel = null;
        if ($remainingMinutes !== null && $remainingMinutes > 0) {
            $remainingLabel = self::formatMinutesFr($remainingMinutes) . ' restantes (estimation)';
        } elseif ($estimatedMinutes > 0 && $pct >= 100) {
            $remainingLabel = null;
        } elseif ($estimatedMinutes > 0) {
            $remainingLabel = self::formatMinutesFr($estimatedMinutes) . ' estimées';
        }

        return [
            'title' => $title,
            'href' => $href,
            'subtitle' => implode(' · ', $metaParts),
            'detail_line' => implode(' · ', $detailParts),
            'urgent' => $urgent,
            'mandatory' => $mandatory,
            'optional' => !$mandatory,
            'certifying' => $certifying,
            'status' => $status,
            'status_label' => $statusLabel,
            'progress_pct' => $pct,
            'category' => $category !== '' ? $category : null,
            'level_label' => $levelLabel,
            'estimated_minutes' => $estimatedMinutes > 0 ? $estimatedMinutes : null,
            'remaining_minutes' => $remainingMinutes,
            'remaining_label' => $remainingLabel,
            'next_step_label' => $nextStep,
            'last_activity_label' => $lastActivityLabel,
            'instructor_label' => is_string($instructorName) && $instructorName !== '' ? $instructorName : null,
            'next_session_label' => is_array($sessionInfo) ? ($sessionInfo['date_label'] ?? null) : null,
            'expires_at' => $exp !== '' ? $exp : null,
            'expires_label' => $expiresLabel,
            'deadline_label' => $deadlineLabel,
            'deadline_kind' => $deadlineKind,
            'action_label' => self::actionLabelFr($status),
        ];
    }

    /**
     * @param array<string, mixed>|null $course
     * @param list<array<string, mixed>> $progressRows
     */
    private function resolveNextStepLabel(?array $course, string $status, array $progressRows): ?string
    {
        if ($status === 'pending_approval') {
            return 'Prochaine étape : validation de l’inscription';
        }
        if ($course === null) {
            return null;
        }

        $moduleTitleById = [];
        foreach ($course['modules'] ?? [] as $mod) {
            if (!is_array($mod)) {
                continue;
            }
            $mid = (int) ($mod['id'] ?? 0);
            $mt = trim((string) ($mod['title'] ?? ''));
            if ($mid > 0 && $mt !== '') {
                $moduleTitleById[$mid] = $mt;
            }
        }

        if (!function_exists('training_lms_ordered_lessons') || !function_exists('training_lms_next_incomplete_lesson')) {
            return null;
        }

        $ordered = training_lms_ordered_lessons($course);
        if ($ordered === []) {
            return 'Prochaine étape : ouvrir le parcours';
        }

        $nextLesson = training_lms_next_incomplete_lesson($ordered, $progressRows);
        if ($nextLesson === null) {
            return 'Prochaine étape : finaliser le parcours';
        }

        $lessonTitle = trim((string) ($nextLesson['title'] ?? ''));
        $moduleId = (int) ($nextLesson['module_id'] ?? 0);
        $moduleTitle = $moduleTitleById[$moduleId] ?? '';

        if ($moduleTitle !== '' && $lessonTitle !== '') {
            return 'En cours : ' . $moduleTitle . ' — ' . $lessonTitle;
        }
        if ($lessonTitle !== '') {
            return 'Prochaine leçon : ' . $lessonTitle;
        }
        if ($moduleTitle !== '') {
            return 'Module en cours : ' . $moduleTitle;
        }

        return $status === 'assigned' ? 'Prochaine étape : commencer le parcours' : 'Prochaine étape : reprendre le parcours';
    }

    /**
     * @param array<string, mixed> $e
     * @param list<array<string, mixed>> $progressRows
     */
    private function resolveLastActivityLabel(array $e, array $progressRows): ?string
    {
        $bestTs = null;
        foreach ($progressRows as $row) {
            foreach (['viewed_at', 'completed_at'] as $key) {
                $raw = trim((string) ($row[$key] ?? ''));
                if ($raw === '') {
                    continue;
                }
                $ts = strtotime($raw);
                if ($ts !== false && ($bestTs === null || $ts > $bestTs)) {
                    $bestTs = $ts;
                }
            }
        }
        if ($bestTs === null) {
            foreach (['started_at', 'assigned_at'] as $key) {
                $raw = trim((string) ($e[$key] ?? ''));
                if ($raw === '') {
                    continue;
                }
                $ts = strtotime($raw);
                if ($ts !== false) {
                    $bestTs = $ts;
                    break;
                }
            }
        }
        if ($bestTs === null) {
            return null;
        }

        return date('d/m/Y', $bestTs);
    }

    /**
     * @return array{date_label: string, instructor: ?string}|null
     */
    private function resolveNextSessionInfo(int $courseId): ?array
    {
        $sessions = $this->lmsSocialRepository->listSessionsForCourse($courseId);
        $now = time();
        $next = null;
        foreach ($sessions as $session) {
            if (!is_array($session)) {
                continue;
            }
            $starts = trim((string) ($session['starts_at'] ?? ''));
            if ($starts === '') {
                continue;
            }
            $ts = strtotime($starts);
            if ($ts === false || $ts < $now) {
                continue;
            }
            $next = $session;
            break;
        }
        if ($next === null) {
            return null;
        }

        $startsTs = strtotime((string) $next['starts_at']);
        if ($startsTs === false) {
            return null;
        }

        $label = trim((string) ($next['label'] ?? ''));
        $dateLabel = date('d/m/Y', $startsTs);
        if ($label !== '') {
            $dateLabel = $label . ' · ' . $dateLabel;
        } else {
            $dateLabel = 'Session le ' . $dateLabel;
        }

        $instructor = null;
        $instructorId = (int) ($next['instructor_user_id'] ?? 0);
        if ($instructorId > 0) {
            $user = $this->userRepository->findById($instructorId, null);
            if (is_array($user)) {
                $name = trim((string) ($user['display_name'] ?? ''));
                if ($name === '') {
                    $name = trim((string) ($user['callsign'] ?? ''));
                }
                if ($name !== '') {
                    $instructor = $name;
                }
            }
        }

        return [
            'date_label' => $dateLabel,
            'instructor' => $instructor,
        ];
    }

    /** @param array<string, mixed>|null $course */
    private function resolvePedagogicalOwnerName(?array $course): ?string
    {
        if ($course === null) {
            return null;
        }
        $ownerId = (int) ($course['pedagogical_owner_user_id'] ?? 0);
        if ($ownerId < 1) {
            return null;
        }
        $user = $this->userRepository->findById($ownerId, null);
        if (!is_array($user)) {
            return null;
        }
        $name = trim((string) ($user['display_name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($user['callsign'] ?? ''));
        }

        return $name !== '' ? $name : null;
    }

    private static function levelLabelFr(string $levelKey): ?string
    {
        if ($levelKey === '') {
            return null;
        }
        if (function_exists('training_course_level_labels_fr')) {
            $map = training_course_level_labels_fr();
            if (isset($map[$levelKey])) {
                return $map[$levelKey];
            }
        }

        return match ($levelKey) {
            'initiation' => 'Initiation',
            'intermediaire' => 'Intermédiaire',
            'avance' => 'Avancé',
            'expert' => 'Expert',
            default => null,
        };
    }

    private static function enrollmentStatusLabelFr(string $status): ?string
    {
        return match ($status) {
            'assigned' => 'Non démarré',
            'in_progress' => 'En cours',
            'pending_approval' => 'En attente de validation',
            'completed' => 'Terminé',
            default => null,
        };
    }

    private static function actionLabelFr(string $status): string
    {
        return match ($status) {
            'pending_approval' => 'Voir',
            'assigned' => 'Commencer',
            default => 'Reprendre',
        };
    }

    private static function formatMinutesFr(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes . ' min';
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        if ($m === 0) {
            return $h . ' h';
        }

        return $h . ' h ' . $m . ' min';
    }

    private static function rsvpLabel(string $status): ?string
    {
        return match ($status) {
            'yes' => 'Vous participez',
            'no' => 'Vous ne participez pas',
            'maybe' => 'Peut-être',
            '' => 'Réponse non renseignée',
            default => null,
        };
    }

    private static function expiresWithinDays(string $expiresAtIso, int $days): bool
    {
        if ($expiresAtIso === '') {
            return false;
        }
        $t = strtotime($expiresAtIso);
        if ($t === false) {
            return false;
        }

        return $t <= strtotime('+' . $days . ' days');
    }
}
