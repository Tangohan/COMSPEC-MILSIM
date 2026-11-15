<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sse\SseTriageInbox;
use App\Support\SseTriageBadges;
use PHPUnit\Framework\TestCase;

/** Pastilles de la barre latérale SSE : seuils, 99+, masquage à zéro, nom accessible, somme des sections. */
final class SseTriageBadgesTest extends TestCase
{
    public function testLabelIsHiddenAtZeroAndCappedAtNinetyNine(): void
    {
        self::assertSame('', SseTriageBadges::label(0));
        self::assertSame('', SseTriageBadges::label(-3));
        self::assertSame('1', SseTriageBadges::label(1));
        self::assertSame('99', SseTriageBadges::label(99));
        self::assertSame('99+', SseTriageBadges::label(100));
        self::assertSame('99+', SseTriageBadges::label(4000));
    }

    public function testBadgeCarriesCountToneAndAnAccessibleName(): void
    {
        $html = SseTriageBadges::badge('papiers-frm', 5, 'warn');
        self::assertStringContainsString('sb-badge--warn', $html);
        self::assertStringContainsString('aria-label="5 à trier"', $html);
        self::assertStringContainsString('role="img"', $html);
        self::assertStringContainsString('data-sb-nav="papiers-frm"', $html);
        self::assertStringContainsString('>5</span>', $html);
        self::assertStringContainsString('sb-badge--bad', SseTriageBadges::badge('x', 3, 'bad'));
        self::assertStringContainsString('sb-badge--neutral', SseTriageBadges::badge('x', 3, 'nimporte'));
        self::assertStringContainsString('aria-label="99+ à trier"', SseTriageBadges::badge('x', 250, 'neutral'));
        self::assertSame('2 to sort', SseTriageBadges::aria(2, 'en'));
    }

    public function testBadgeIsHiddenWhenTheQueueIsEmpty(): void
    {
        $html = SseTriageBadges::badge('anomalies', 0, 'neutral');
        self::assertStringContainsString(' hidden', $html);
        self::assertStringNotContainsString('aria-label', $html);
        self::assertStringContainsString('data-sb-nav="anomalies"', $html, 'présente pour l’actualisation');
    }

    public function testWorstToneWins(): void
    {
        self::assertSame('bad', SseTriageBadges::worst('warn', 'bad'));
        self::assertSame('warn', SseTriageBadges::worst('neutral', 'warn'));
        self::assertSame('bad', SseTriageBadges::worst('bad', 'neutral'));
        self::assertSame('neutral', SseTriageBadges::worst('neutral', 'neutral'));
    }

    public function testNavigationDataSumsSectionsAndKeepsTheDistinctTotal(): void
    {
        $now = time();
        $it = static fn (string $k, int $age, int $p = 0): array => ['key' => $k, 'title' => $k, 'meta' => '', 'at' => gmdate('Y-m-d H:i:s', $now - $age * 86400), 'href' => '#', 'priority' => $p];
        $inbox = new SseTriageInbox([
            ['key' => 'fiches', 'nav' => 'fiches', 'label' => 'F', 'tab' => 'F', 'path' => 'atak/sse/fiches', 'load' => static fn (int $t): array => [$it('n:1', 9), $it('n:2', 1)]],
            ['key' => 'interet', 'nav' => 'interet', 'label' => 'I', 'tab' => 'I', 'path' => 'atak/sse/interet', 'load' => static fn (int $t): array => [$it('n:1', 9), $it('i:2', 1, 2)]],
        ], fn (): bool => true);
        $sections = [['id' => 'renseignement', 'label' => 'R', 'items' => [['id' => 'fiches'], ['id' => 'interet'], ['id' => 'dossiers']]], ['id' => 'pilotage', 'label' => 'P', 'items' => [['id' => 'accueil']]]];
        $d = SseTriageBadges::forNav($sections, $inbox, 1, 7);

        self::assertTrue($d['enabled']);
        self::assertSame(3, $d['total'], 'n:1 figure dans deux files');
        self::assertSame(['count' => 2, 'tone' => 'warn'], $d['entries']['fiches']);
        self::assertSame(['count' => 2, 'tone' => 'bad'], $d['entries']['interet']);
        self::assertSame(['count' => 4, 'tone' => 'bad'], $d['sections']['renseignement']);
        self::assertSame(['count' => 0, 'tone' => 'neutral'], $d['sections']['pilotage']);
        self::assertFalse(isset($d['entries']['dossiers']));
        self::assertStringContainsString('hidden', SseTriageBadges::pill('pilotage', 0, 'neutral'));
        self::assertStringContainsString('aria-label="4 à trier"', SseTriageBadges::pill('renseignement', 4, 'bad'));
    }
}
