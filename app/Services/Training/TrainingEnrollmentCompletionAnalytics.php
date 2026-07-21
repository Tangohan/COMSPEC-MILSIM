<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Repositories\TrainingEnrollmentRepository;
use App\Repositories\TrainingQuizRepository;

/**
 * Synthèses lisibles (durée de parcours, quiz) pour le fil d’activité formations du back-office.
 */
final class TrainingEnrollmentCompletionAnalytics
{
    public function __construct(
        private TrainingEnrollmentRepository $enrollments,
        private TrainingQuizRepository $quizzes,
    ) {}

    /**
     * @param list<array<string, mixed>> $feedRows lignes tenant_community_feed
     * @return array<int, array{lines: list<string>}> clé = id du fil
     */
    public function buildForTrainingFeedRows(int $tenantId, array $feedRows): array
    {
        $feedIdToEnrollment = [];
        foreach ($feedRows as $row) {
            if (($row['category'] ?? '') !== 'training_course_completed') {
                continue;
            }
            $eid = isset($row['related_enrollment_id']) ? (int) $row['related_enrollment_id'] : 0;
            $fid = (int) ($row['id'] ?? 0);
            if ($eid < 1 || $fid < 1) {
                continue;
            }
            $feedIdToEnrollment[$fid] = $eid;
        }
        if ($feedIdToEnrollment === []) {
            return [];
        }
        $enrollmentIds = array_values(array_unique(array_values($feedIdToEnrollment)));
        $enrollmentRows = $this->enrollments->findByIdsForTenant($enrollmentIds, $tenantId);
        $quizByEnrollment = $this->quizzes->summarizeSubmittedAttemptsForEnrollments($enrollmentIds);

        $out = [];
        foreach ($feedIdToEnrollment as $feedId => $enrollmentId) {
            $enr = $enrollmentRows[$enrollmentId] ?? null;
            if ($enr === null) {
                continue;
            }
            $lines = [];
            $duration = $this->formatCompletionDurationLabel($enr);
            if ($duration !== null) {
                $lines[] = 'Durée du parcours : ' . $duration . '.';
            }
            foreach ($this->formatQuizSummaryLines($quizByEnrollment[$enrollmentId] ?? []) as $qLine) {
                $lines[] = $qLine;
            }
            if ($lines !== []) {
                $out[$feedId] = ['lines' => $lines];
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $enrollment */
    private function formatCompletionDurationLabel(array $enrollment): ?string
    {
        $completedRaw = $enrollment['completed_at'] ?? null;
        if ($completedRaw === null || $completedRaw === '') {
            return null;
        }
        $end = strtotime((string) $completedRaw);
        if ($end === false) {
            return null;
        }
        $startRaw = !empty($enrollment['started_at']) ? (string) $enrollment['started_at'] : (string) ($enrollment['assigned_at'] ?? '');
        if ($startRaw === '') {
            return null;
        }
        $start = strtotime($startRaw);
        if ($start === false || $end < $start) {
            return null;
        }
        $sec = $end - $start;

        return self::formatDurationSeconds($sec);
    }

    /** Formatage lisible d'une durée en secondes (réutilisé par le fil d'activité et les rapports de conformité). */
    public static function formatDurationSeconds(int $sec): string
    {
        if ($sec < 60) {
            return 'moins d’une minute';
        }
        if ($sec < 3600) {
            $m = intdiv($sec, 60);

            return $m . ' minute' . ($m > 1 ? 's' : '');
        }
        if ($sec < 86400) {
            $h = intdiv($sec, 3600);

            return $h . ' h';
        }
        $d = intdiv($sec, 86400);

        return $d . ' jour' . ($d > 1 ? 's' : '');
    }

    /**
     * @param list<array{quiz_id: int, quiz_title: string, submitted_attempts: int, best_score: ?float, passed_any: int}> $perQuiz
     * @return list<string>
     */
    private function formatQuizSummaryLines(array $perQuiz): array
    {
        $active = array_values(array_filter($perQuiz, static fn (array $q) => ($q['submitted_attempts'] ?? 0) > 0));
        if ($active === []) {
            return ['Aucune tentative d’évaluation enregistrée pour ce parcours.'];
        }
        $totalAttempts = 0;
        $bestOverall = null;
        foreach ($active as $q) {
            $totalAttempts += (int) ($q['submitted_attempts'] ?? 0);
            $bs = $q['best_score'] ?? null;
            if ($bs !== null) {
                $bestOverall = $bestOverall === null ? (float) $bs : max($bestOverall, (float) $bs);
            }
        }
        $main = 'Évaluations : ' . $totalAttempts . ' tentative' . ($totalAttempts > 1 ? 's' : '') . ' enregistrée' . ($totalAttempts > 1 ? 's' : '');
        if ($bestOverall !== null) {
            $main .= ', meilleur résultat : ' . self::formatScorePercent($bestOverall) . ' %';
        }
        $main .= '.';
        $lines = [$main];
        if (count($active) >= 2) {
            $parts = [];
            $slice = array_slice($active, 0, 2);
            foreach ($slice as $q) {
                $t = trim((string) ($q['quiz_title'] ?? ''));
                if ($t === '') {
                    $t = 'Évaluation';
                }
                $n = (int) ($q['submitted_attempts'] ?? 0);
                $chunk = '« ' . $t . ' » : ' . $n . ' tentative' . ($n > 1 ? 's' : '');
                $bs = $q['best_score'] ?? null;
                if ($bs !== null) {
                    $chunk .= ', meilleur score ' . self::formatScorePercent((float) $bs) . ' %';
                }
                $parts[] = $chunk;
            }
            $suffix = count($active) > 2 ? ' … et d’autres évaluations.' : '';
            $lines[] = 'Détail : ' . implode(' · ', $parts) . $suffix;
        }

        return $lines;
    }

    /** Formatage lisible d'un score (réutilisé par le fil d'activité et les rapports de conformité). */
    public static function formatScorePercent(float $score): string
    {
        $r = round($score, 1);

        return (string) ($r == floor($r) ? (int) $r : $r);
    }
}
