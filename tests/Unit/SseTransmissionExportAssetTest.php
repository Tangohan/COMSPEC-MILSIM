<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Integrations\DiscordWebhookService;
use App\Services\Sse\SseTransmissionDiscordService;
use App\Services\Sse\SseTransmissionPdfService;
use PHPUnit\Framework\TestCase;

final class SseTransmissionExportAssetTest extends TestCase
{
    public function testPdfJournalHtmlListsNatureAndOperator(): void
    {
        $html = (new SseTransmissionPdfService())->journalHtml([
            [
                'event_time' => '2026-08-28 00:28:00',
                'event_type_label' => 'Rapport reçu',
                'source_system_label' => 'Tablette',
                'summary' => 'Fiche de renseignement FR-2026-000001',
                'author_label' => 'YA1 / Bravo',
                'confidence_code' => 'C3',
            ],
        ], '28/08/2026 02:00');

        self::assertStringContainsString('Journal des transmissions terrain', $html);
        self::assertStringContainsString('Fiche de renseignement FR-2026-000001', $html);
        self::assertStringContainsString('YA1 / Bravo', $html);
        self::assertStringContainsString('Rapport reçu', $html);
    }

    public function testDiscordConfigIgnoresEmptyRelaysAndMasksUrl(): void
    {
        $cfg = SseTransmissionDiscordService::normalizeConfig([
            'integrations' => [
                'sse_transmissions' => [
                    'use_community_relay' => true,
                    'relays' => [
                        ['id' => 'ab12', 'label' => 'Renseignement', 'url' => 'https://discord.com/api/webhooks/1/secret', 'created_at' => ''],
                        ['id' => '', 'url' => 'https://example.com'],
                    ],
                ],
            ],
        ]);

        self::assertTrue($cfg['use_community_relay']);
        self::assertCount(1, $cfg['relays']);
        self::assertSame('Renseignement', $cfg['relays'][0]['label']);
        self::assertSame('Salon Discord relié', SseTransmissionDiscordService::maskRelayUrl($cfg['relays'][0]['url']));
    }

    public function testDiscordUrlValidationAcceptsOfficialHostsOnly(): void
    {
        $svc = new DiscordWebhookService();
        self::assertTrue($svc->isValidWebhookUrl('https://discord.com/api/webhooks/1/abc'));
        self::assertFalse($svc->isValidWebhookUrl('https://evil.example/api/webhooks/1/abc'));
        self::assertFalse($svc->isValidWebhookUrl('http://discord.com/api/webhooks/1/abc'));
    }

    public function testJournalOffersPdfAndDiscordRelayUi(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/atak/sse/transmissions.php');
        $show = (string) file_get_contents(dirname(__DIR__, 2) . '/views/atak/sse/transmission_show.php');
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');

        self::assertStringContainsString('Télécharger le journal (PDF)', $view);
        self::assertStringContainsString('Relais Discord', $view);
        self::assertStringContainsString('Ajouter le relais', $view);
        self::assertStringContainsString('Télécharger en PDF', $show);
        self::assertStringContainsString('Envoyer vers Discord', $show);
        self::assertStringContainsString("/atak/sse/transmissions/pdf", $routes);
        self::assertStringContainsString("/atak/sse/transmissions/relais", $routes);
        self::assertStringContainsString("/atak/sse/transmissions/{id}/pdf", $routes);
        self::assertStringContainsString("/atak/sse/transmissions/{id}/discord", $routes);
    }
}
