<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\UxFeedbackAdminPresentation as Ux;
use PHPUnit\Framework\TestCase;

final class UxFeedbackAdminPresentationTest extends TestCase
{
    public function testSatisfactionBands(): void
    {
        self::assertSame('a-ameliorer', Ux::satisfactionFromScore(1)['key']);
        self::assertSame('À améliorer', Ux::satisfactionFromScore(2)['label']);
        self::assertSame('rose', Ux::satisfactionFromScore(2.4)['pill']);
        self::assertSame('correct', Ux::satisfactionFromScore(3)['key']);
        self::assertSame('Correct', Ux::satisfactionFromScore(3.9)['label']);
        self::assertSame('satisfaisant', Ux::satisfactionFromScore(4)['key']);
        self::assertSame('mint', Ux::satisfactionFromScore(5)['pill']);
    }

    public function testFiltersKeepUnknownValuesEmpty(): void
    {
        self::assertSame('', Ux::normalizeType('json'));
        self::assertSame('avis', Ux::normalizeType('avis'));
        self::assertSame('questionnaires', Ux::normalizeType('questionnaires'));
        self::assertSame('', Ux::normalizeSatisfaction('pending'));
        self::assertSame('a-ameliorer', Ux::normalizeSatisfaction('a-ameliorer'));
        self::assertTrue(Ux::matchesSatisfaction(2, 'a-ameliorer'));
        self::assertFalse(Ux::matchesSatisfaction(5, 'a-ameliorer'));
        self::assertTrue(Ux::matchesSatisfaction(5, ''));
    }

    public function testSurveyScoreAndIssuesStayHuman(): void
    {
        $score = Ux::surveyScore([
            'ease_rating' => 2,
            'clarity_rating' => 2,
            'design_rating' => 4,
            'usefulness_rating' => 4,
        ]);
        self::assertSame(3.0, $score);
        self::assertSame('Correct', Ux::satisfactionFromScore($score)['label']);

        $labels = Ux::decodeIssues('["navigation","labels","unknown_slug"]');
        self::assertSame(['Navigation confuse', 'Libellés peu clairs', 'unknown_slug'], $labels);
        self::assertSame('Parcours trop long', Ux::issueLabel('workflow'));
    }

    public function testScreenMatchingAndLocation(): void
    {
        $row = ['page_key' => 'back-office/personnel', 'page_title' => 'Effectifs'];
        self::assertTrue(Ux::rowMatchesScreen($row, ''));
        self::assertTrue(Ux::rowMatchesScreen($row, 'back-office/personnel'));
        self::assertFalse(Ux::rowMatchesScreen($row, 'admin/users'));
        self::assertSame('back office · personnel', Ux::screenLocation('/public/back-office/personnel'));
        self::assertSame('back office · personnel', Ux::screenLocation('back-office/personnel?foo=1'));
        self::assertSame('3 / 5', Ux::scoreLabel(3, 0));
        self::assertSame('4,5 / 5', Ux::scoreLabel(4.5));
        self::assertSame('—', Ux::formatDateTime(null));
        self::assertStringContainsString('02/09/2026', Ux::formatDateTime('2026-09-02 14:00:00'));

        $options = Ux::screenOptions([
            ['page_key' => 'b', 'page_title' => 'Beta'],
            ['page_key' => 'a', 'page_title' => 'Alpha'],
            ['page_key' => 'b', 'page_title' => 'Beta encore'],
        ]);
        self::assertCount(2, $options);
        self::assertSame('Alpha', $options[0]['title']);
    }
}
