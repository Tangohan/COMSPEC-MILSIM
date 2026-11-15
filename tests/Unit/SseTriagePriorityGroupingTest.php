<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sse\SseTriageGrouping;
use App\Services\Sse\SseTriagePriority;
use PHPUnit\Framework\TestCase;

/**
 * Priorité calculée (et son explication) et regroupement automatique : purs, sans base.
 */
final class SseTriagePriorityGroupingTest extends TestCase
{
    private const NOW = 1794744000; // 2026-11-15 12:00 UTC

    /** @return array<string, mixed> */
    private function item(string $key, string $type, string $label, string $sev, int $days, array $subject = [], string $reason = ''): array
    {
        return ['key' => $key, 'source' => 'anomalies', 'source_label' => 'Anomalies', 'type_key' => $type, 'type_label' => $label, 'title' => $label, 'severity' => $sev,
            'at' => gmdate('Y-m-d H:i:s', self::NOW - $days * 86400), 'priority' => 0, 'reason' => $reason, 'subject' => $subject ?: null, 'score' => 0] + [];
    }

    public function testSeverityDominatesThenAge(): void
    {
        $critical = SseTriagePriority::score(['severity' => 'critical'], 0, 0);
        $oldInfo = SseTriagePriority::score(['severity' => 'info'], 60, 0);
        $high = SseTriagePriority::score(['severity' => 'high'], 3, 0);

        self::assertSame(40, $critical['score']);
        self::assertSame(4 + SseTriagePriority::AGE_MAX, $oldInfo['score'], 'l’âge plafonne à 20 points');
        self::assertGreaterThan($oldInfo['score'], $high['score']);
        self::assertGreaterThan($high['score'], $critical['score']);
    }

    public function testEveryPointIsExplainedAndTheExplanationAddsUp(): void
    {
        $r = SseTriagePriority::score(
            ['severity' => 'high', 'subject' => ['wanted' => true, 'classification' => 'confidentiel']],
            12,
            3,
            5
        );
        $labels = array_column($r['why'], 'label');

        self::assertSame(['Gravité haute', 'Ancienneté : 12 j', 'Sujet sous avis de recherche actif', 'Classification : Secret (S)', '3 autres éléments sur le même sujet', 'Majoration d’une règle Cerbère-Tri'], $labels);
        self::assertSame(25 + 8 + 20 + 7 + 9 + 5, $r['score']);
        self::assertSame($r['score'], array_sum(array_column($r['why'], 'points')), 'le score est exactement la somme expliquée');
    }

    public function testTheScoreIsCappedAtOneHundredAndNeverNegative(): void
    {
        self::assertSame(100, SseTriagePriority::score(['severity' => 'critical', 'subject' => ['wanted' => true, 'classification' => 'tres_restreint']], 90, 9, 50)['score']);
        self::assertSame(0, SseTriagePriority::score(['severity' => 'info'], 0, 0, -90)['score']);
    }

    public function testSeverityFallsBackToTheQueuePriority(): void
    {
        self::assertSame('critical', SseTriagePriority::severityOf(['priority' => 2]));
        self::assertSame('high', SseTriagePriority::severityOf(['priority' => 1]));
        self::assertSame('info', SseTriagePriority::severityOf(['priority' => 0]));
        self::assertSame('medium', SseTriagePriority::severityOf(['priority' => 2, 'severity' => 'medium']));
    }

    public function testGroupingByTypeTurnsThirtyFourLinesIntoOne(): void
    {
        $items = [];
        for ($i = 1; $i <= 34; $i++) {
            $items[] = $this->item('signal:' . $i, 'signal:gap_auto', 'Photographie manquante', 'medium', $i % 29, ['kind' => 'case', 'key' => 'case:' . $i, 'label' => 'DOS-' . $i], 'Disposer d’une photographie exploitable.');
        }
        for ($i = 1; $i <= 3; $i++) {
            $items[] = $this->item('signal:b' . $i, 'signal:anomaly', 'Contradiction non résolue', 'high', 2, ['kind' => 'case', 'key' => 'case:' . $i, 'label' => 'DOS-' . $i]);
        }
        $groups = SseTriageGrouping::group($items, 'type', self::NOW);

        self::assertCount(2, $groups);
        $photo = array_values(array_filter($groups, static fn (array $g): bool => $g['title'] === 'Photographie manquante'))[0];
        self::assertSame(34, $photo['count']);
        self::assertSame('dossier', $photo['noun']);
        self::assertSame('34 dossiers', SseTriageGrouping::countLabel($photo['count'], $photo['noun']));
        self::assertSame('Disposer d’une photographie exploitable.', $photo['reason']);
        self::assertSame(28, $photo['oldest_days']);
        self::assertCount(34, $photo['items']);
    }

    public function testGroupsAreOrderedByTheirMostUrgentItem(): void
    {
        $a = $this->item('a:1', 'a', 'Faible', 'info', 1);
        $b = $this->item('b:1', 'b', 'Urgent', 'critical', 1);
        $a['score'] = 10;
        $b['score'] = 80;
        $groups = SseTriageGrouping::group([$a, $b], 'type', self::NOW);

        self::assertSame(['Urgent', 'Faible'], array_column($groups, 'title'));
        self::assertSame(80, $groups[0]['top_score']);
    }

    public function testGroupingBySubjectSourceAndAge(): void
    {
        $x = $this->item('s:1', 't1', 'T1', 'info', 0, ['kind' => 'case', 'key' => 'case:1', 'label' => 'DOS-1']);
        $y = $this->item('s:2', 't2', 'T2', 'info', 3, ['kind' => 'case', 'key' => 'case:1', 'label' => 'DOS-1']);
        $z = $this->item('s:3', 't3', 'T3', 'info', 45);
        $z['source'] = 'fiches';
        $z['source_label'] = 'Fiches';

        $bySubject = SseTriageGrouping::group([$x, $y, $z], 'sujet', self::NOW);
        self::assertSame(['DOS-1' => 2, 'Sans sujet identifié' => 1], array_column(array_map(static fn (array $g): array => ['t' => $g['title'], 'c' => $g['count']], $bySubject), 'c', 't'));
        $bySource = SseTriageGrouping::group([$x, $y, $z], 'source', self::NOW);
        self::assertSame(['Anomalies' => 2, 'Fiches' => 1], array_column(array_map(static fn (array $g): array => ['t' => $g['title'], 'c' => $g['count']], $bySource), 'c', 't'));
        $byAge = SseTriageGrouping::group([$x, $y, $z], 'anciennete', self::NOW);
        self::assertEqualsCanonicalizing(['Aujourd’hui et hier', 'De 2 à 7 jours', 'Plus de 30 jours'], array_column($byAge, 'title'));
    }

    public function testAnUnknownModeFallsBackToType(): void
    {
        self::assertSame('type', SseTriageGrouping::mode('n’importe quoi'));
        self::assertSame('sujet', SseTriageGrouping::mode('sujet'));
        self::assertSame(['type', 'sujet', 'source', 'anciennete'], array_keys(SseTriageGrouping::MODES));
    }

    public function testCountLabelsAreSingularAndPlural(): void
    {
        self::assertSame('1 dossier', SseTriageGrouping::countLabel(1, 'dossier'));
        self::assertSame('2 identités', SseTriageGrouping::countLabel(2, 'identité'));
        self::assertSame('1 élément', SseTriageGrouping::countLabel(1, 'élément'));
        self::assertSame('7 éléments', SseTriageGrouping::countLabel(7, 'élément'));
    }

    public function testMixedSubjectsGroupAsGenericItems(): void
    {
        $a = $this->item('a:1', 't', 'Mixte', 'info', 1, ['kind' => 'case', 'key' => 'case:1', 'label' => 'D']);
        $b = $this->item('a:2', 't', 'Mixte', 'info', 1, ['kind' => 'identity', 'key' => 'identity:2', 'label' => 'I']);

        self::assertSame('élément', SseTriageGrouping::group([$a, $b], 'type', self::NOW)[0]['noun']);
    }
}
