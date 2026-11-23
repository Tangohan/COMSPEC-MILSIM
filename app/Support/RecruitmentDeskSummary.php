<?php

declare(strict_types=1);

namespace App\Support;

use App\Repositories\EnlistmentRepository;
use DateTimeImmutable;

/**
 * Indicateurs de l'en-tête du bureau recrutement, calculés sur les candidatures déjà enregistrées.
 * Calcul pur (aucun accès base) : testable sans MySQL.
 */
final class RecruitmentDeskSummary
{
    /** Étapes du parcours actif, dans l'ordre de l'entonnoir. */
    public const FUNNEL_STAGES = [
        'submitted' => 'À traiter',
        'interview_scheduled' => 'Entretien',
        'on_hold' => 'En attente',
        'acceptance_onboarding' => 'Intégration',
        'accepted' => 'Acceptées',
    ];

    /** Fenêtre (jours) du délai moyen de décision. */
    public const DELAY_WINDOW_DAYS = 90;

    /**
     * @param list<array<string, mixed>> $rows lignes avec status, pipeline_stage (optionnel), created_at, reviewed_at
     * @return array{
     *   total: int,
     *   to_process: int,
     *   in_interview: int,
     *   accepted_this_month: int,
     *   avg_delay_days: ?float,
     *   funnel: array<string, int>,
     *   closed: array<string, int>
     * }
     */
    public static function compute(array $rows, DateTimeImmutable $now): array
    {
        $funnel = array_fill_keys(array_keys(self::FUNNEL_STAGES), 0);
        $closed = ['rejected' => 0, 'blocked' => 0, 'cancelled' => 0];
        $monthKey = $now->format('Y-m');
        $acceptedThisMonth = 0;
        $delaySum = 0.0;
        $delayN = 0;
        $since = $now->modify('-' . self::DELAY_WINDOW_DAYS . ' days')->getTimestamp();

        foreach ($rows as $row) {
            $stage = EnlistmentRepository::effectivePipelineStage($row);
            if (isset($funnel[$stage])) {
                ++$funnel[$stage];
            } elseif (isset($closed[$stage])) {
                ++$closed[$stage];
            }
            $reviewed = self::ts($row['reviewed_at'] ?? null);
            if ($stage === 'accepted' && $reviewed !== null && gmdate('Y-m', $reviewed) === $monthKey) {
                ++$acceptedThisMonth;
            }
            $created = self::ts($row['created_at'] ?? null);
            if (($stage === 'accepted' || $stage === 'rejected') && $reviewed !== null && $created !== null
                && $reviewed >= $created && $reviewed >= $since) {
                $delaySum += ($reviewed - $created) / 86400;
                ++$delayN;
            }
        }

        return [
            'total' => count($rows),
            'to_process' => $funnel['submitted'],
            'in_interview' => $funnel['interview_scheduled'],
            'accepted_this_month' => $acceptedThisMonth,
            'avg_delay_days' => $delayN > 0 ? round($delaySum / $delayN, 1) : null,
            'funnel' => $funnel,
            'closed' => $closed,
        ];
    }

    private static function ts(mixed $raw): ?int
    {
        $raw = trim((string) $raw);
        if ($raw === '' || str_starts_with($raw, '0000')) {
            return null;
        }
        $t = strtotime($raw . (preg_match('/[zZ+]|UTC/', $raw) ? '' : ' UTC'));

        return $t === false ? null : $t;
    }
}
