<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SseWorkspaceUi;
use PHPUnit\Framework\TestCase;

final class SseWorkspaceUiTest extends TestCase
{
    public function testEventTypesNeverExposeUnderscores(): void
    {
        self::assertSame('Exigence créée', SseWorkspaceUi::eventTypeLabel('REQUIREMENT_CREATED'));
        self::assertSame('Acquisition biométrique', SseWorkspaceUi::eventTypeLabel('BIOMETRIC_SCAN'));
        self::assertStringNotContainsString('_', SseWorkspaceUi::eventTypeLabel('SOME_UNKNOWN_CODE'));
    }

    public function testIdentityAndEntityAreFrench(): void
    {
        self::assertSame('Identité documentaire', SseWorkspaceUi::identityTierLabel('DOCUMENTARY'));
        self::assertSame('Personne', SseWorkspaceUi::entityTypeLabel('PERSON'));
        self::assertSame('Dossier', SseWorkspaceUi::entityTypeLabel('CASE'));
    }

    public function testInboxCollapseDropsDuplicatePersonEvents(): void
    {
        $out = SseWorkspaceUi::collapseInbox([
            ['kind' => 'event', 'title' => 'Fiche personne reçue : PrenomUlu NomZul'],
            ['kind' => 'event', 'title' => 'Fiche personne reçue : PrenomUlu NomZul'],
            ['kind' => 'event', 'title' => 'Fiche de renseignement FR-2026-000002'],
        ]);
        self::assertCount(2, $out);
    }

    public function testTimelineCollapseKeepsLatestDuplicate(): void
    {
        $out = SseWorkspaceUi::collapseTimeline([
            ['event_type' => 'IDENTIFIED', 'summary' => 'PrenomUlu', 'entity_uuid' => 'u1', 'event_time' => '2026-08-23 02:33:07', 'id' => 2],
            ['event_type' => 'IDENTIFIED', 'summary' => 'PrenomUlu', 'entity_uuid' => 'u1', 'event_time' => '2026-08-23 02:29:58', 'id' => 1],
        ]);
        self::assertCount(1, $out);
        self::assertSame(2, $out[0]['repeat_count']);
        self::assertSame('2026-08-23 02:33:07', $out[0]['event_time']);
    }

    public function testInboxCardsCssDoesNotRelyOnDisplayContents(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/sse_workspace.css');
        self::assertStringContainsString('.iw-intel-list--cards > .iw-feed-item > a.iw-feed-link', $css);
        self::assertStringContainsString('grid-template-columns: 2rem minmax(0, 1fr)', $css);
        self::assertStringNotContainsString('.iw-feed-link {\n  display: contents;', $css);
    }
}
