<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Monitoring\ReconNoteDiscordService;
use App\Services\Security\FileRateLimiter;
use PHPUnit\Framework\TestCase;

final class ReconNoteDiscordServiceTest extends TestCase
{
    public function testEmbedCarriesTypeTextGridAuthor(): void
    {
        $embed = ReconNoteDiscordService::buildEmbed([
            'tag' => 'vehicle',
            'text' => '2 pick-up armés @everyone https://x.y/z',
            'confidence' => 'vu_direct',
            'author' => 'TA1 · James',
            'pos_x' => 15334.2,
            'pos_y' => 17228.9,
        ]);

        self::assertSame('🔭 Reco · Véhicule', $embed['title']);
        self::assertStringContainsString('2 pick-up armés', $embed['description']);
        self::assertStringNotContainsString('@everyone', $embed['description']);
        self::assertStringNotContainsString('https://', $embed['description']);
        $fields = array_column($embed['fields'], 'value', 'name');
        self::assertSame('1533 1722', $fields['Carroyage']);
        self::assertSame('Vu direct', $fields['Fiabilité']);
        self::assertSame('TA1 · James', $fields['Auteur']);
    }

    public function testPostSendsOnceAndNothingWithoutWebhook(): void
    {
        $limiter = $this->createStub(FileRateLimiter::class);
        $limiter->method('hit')->willReturn(1);
        $sent = [];
        $svc = new ReconNoteDiscordService('https://discord.com/api/webhooks/1/abc', static function (string $url, array $embed) use (&$sent): array {
            $sent[] = [$url, $embed];

            return ['ok' => true];
        }, $limiter);
        self::assertSame('sent', $svc->post(['tag' => 'mine', 'text' => 'IED', 'pos_x' => 100, 'pos_y' => 200]));
        self::assertCount(1, $sent);

        $off = new ReconNoteDiscordService('', static fn (): array => ['ok' => true], $limiter);
        self::assertSame('disabled', $off->post(['tag' => 'mine']));
    }

    public function testRecoWiring(): void
    {
        $root = dirname(__DIR__, 2);
        $api = (string) file_get_contents($root . '/app/Controllers/Api/AtakApiController.php');
        $ext = (string) file_get_contents($root . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        $catalog = (string) file_get_contents($root . '/app/Support/DiscordWebhookCatalog.php');
        $page = (string) file_get_contents($root . '/mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/pages/fn_pageReco.sqf');
        $click = (string) file_get_contents($root . '/mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/map/fn_mapMouseButtonDown.sqf');
        // L'avertissement BattlEye vit dans l'état de connexion de l'app Athena (fn_athenaStatus, 2.1.0).
        $athena = (string) file_get_contents($root . '/mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/network/fn_athenaStatus.sqf');

        self::assertStringContainsString('ReconNoteDiscordService::defer($tenantId, $row)', $api);
        self::assertStringContainsString("'kind' => 'recon_note'", $api);
        self::assertStringContainsString('EnqueueOrSend(_baseUrl + "/api/recon/notes", EnrichAtakPayload(json))', $ext);
        self::assertStringContainsString('json = EnrichAtakPayload(json);', $ext);
        self::assertStringContainsString('KEY_ATAK_RECON_NOTES', $catalog);
        self::assertStringContainsString('POINTÉ CARTE', $page);
        self::assertStringNotContainsString('"SOUS MON REGARD"', $page);
        self::assertStringContainsString('recoPick', $click);
        self::assertStringContainsString('BattlEye actif', $athena);
    }
}
