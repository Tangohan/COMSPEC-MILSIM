<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\TrainingQuickAccess;
use PHPUnit\Framework\TestCase;

/** Résumé « Mes formations » du hub et du rail : compteurs, avancement, prochain parcours. */
final class TrainingQuickAccessTest extends TestCase
{
    public function testSummarizeCountsAndPicksInProgressFirst(): void
    {
        $s = TrainingQuickAccess::summarize([
            ['status' => 'assigned', 'course_title' => 'Tir de précision', 'course_slug' => 'tir'],
            ['status' => 'in_progress', 'course_title' => 'Brevet radio', 'course_slug' => 'radio'],
            ['status' => 'completed', 'course_title' => 'Secourisme', 'course_slug' => 'sec'],
            ['status' => 'withdrawn', 'course_title' => 'Ancien', 'course_slug' => 'old'],
            ['status' => 'pending_approval', 'course_title' => 'Chef de groupe', 'course_slug' => 'cdg'],
        ]);
        self::assertSame(4, $s['total'], 'les inscriptions annulées ne comptent pas');
        self::assertSame(1, $s['in_progress']);
        self::assertSame(1, $s['assigned']);
        self::assertSame(1, $s['completed']);
        self::assertSame(1, $s['pending']);
        self::assertSame(25, $s['pct']);
        self::assertSame('Brevet radio', $s['next']['title']);
        self::assertSame('in_progress', $s['next']['status']);
    }

    public function testSummarizeEmpty(): void
    {
        $s = TrainingQuickAccess::summarize([]);
        self::assertSame(0, $s['total']);
        self::assertSame(0, $s['pct']);
        self::assertSame(null, $s['next']);
    }
}
