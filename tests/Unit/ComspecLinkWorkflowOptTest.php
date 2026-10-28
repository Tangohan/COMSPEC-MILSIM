<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Non-régression structurale Link 2.0.107+ (OPT-01..08) : plafonds HTTP, files BACKGROUND/PRIORITY, caches, MapCap/auth.
 * Ne lance pas Arma ni Native AOT — assertions sur sources uniquement.
 */
final class ComspecLinkWorkflowOptTest extends TestCase
{
    private function ext(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/COMSPECExtension/Extension.cs');
    }

    private function slides(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/COMSPECExtension/GoogleSlidesDeck.cs');
    }

    private function syncStats(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/COMSPECExtension/Extension_SyncStats.cs');
    }

    public function testMaxConnectionsPerServerIsCapped(): void
    {
        $cs = $this->ext();
        self::assertStringContainsString('MaxConnectionsPerServer = 8', $cs);
        self::assertStringContainsString('PooledConnectionLifetime', $cs);
    }

    public function testBackgroundQueuedEndpointsCoverPlaytimeRelaysRf(): void
    {
        $cs = $this->ext();
        self::assertStringContainsString('IsBackgroundQueuedEndpoint', $cs);
        self::assertStringContainsString('/api/atak/playtime', $cs);
        self::assertStringContainsString('/api/atak/relays', $cs);
        self::assertStringContainsString('/api/atak/rf-hits', $cs);
        self::assertStringContainsString('IsBackgroundQueuedEndpoint(url)', $cs);
    }

    public function testPriorityQueuedEndpointsCoverChatCasIntel(): void
    {
        $cs = $this->ext();
        self::assertStringContainsString('IsPriorityQueuedEndpoint', $cs);
        self::assertStringContainsString('/api/chat', $cs);
        self::assertStringContainsString('/api/cas/', $cs);
        self::assertStringContainsString('/api/atak/intel', $cs);
        self::assertStringContainsString('IsPriorityQueuedEndpoint(url)', $cs);
    }

    public function testMemStatsExposesQueueDepths(): void
    {
        $cs = $this->ext();
        self::assertStringContainsString('fifo={4}|markers={5}|tel={6}|pos={7}|poll={8}|drain={9}', $cs);
    }

    public function testPollGetAndCooldownPruneExist(): void
    {
        $cs = $this->ext();
        self::assertStringContainsString('MaybePrunePollGetCache', $cs);
        self::assertStringContainsString('MaybePruneEndpointCooldowns', $cs);
        self::assertStringContainsString('PollGetCacheMaxEntries = 64', $cs);
    }

    public function testGoogleSlidesPageIdCacheHasTtlAndCancelClears(): void
    {
        $slides = $this->slides();
        self::assertStringContainsString('PageIdCacheTtlMs', $slides);
        self::assertStringContainsString('PageIdCacheMax = 8', $slides);
        self::assertMatchesRegularExpression('/public static string Cancel\(\)[\s\S]*?PageIdCache\.Clear\(\)/', $slides);
    }

    public function testTrafficChannelsSoftCap(): void
    {
        $ss = $this->syncStats();
        self::assertStringContainsString('TrafficChannelsMax = 64', $ss);
        self::assertStringContainsString('MaybePruneTrafficChannels', $ss);
    }

    public function testLinkVersionAlignedWithNative(): void
    {
        $cs = $this->ext();
        self::assertSame(1, preg_match('/private const string ExtensionVersion = "([0-9.]+)";/', $cs, $m));
        $ver = $m[1];
        $hpp = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/COMSPEC_ATAK_Native/Sources/addons/main/script_component.hpp'
        );
        self::assertStringContainsString('LINK_VERSION_STR "' . $ver . '"', $hpp);
        $proj = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/COMSPECExtension/COMSPECExtension.csproj'
        );
        self::assertStringContainsString('<Version>' . $ver . '</Version>', $proj);
        self::assertTrue(version_compare($ver, '2.0.109', '>='));
    }

    public function testMapCapScanIsQueuedWithPoll(): void
    {
        $map = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/COMSPECExtension/Extension_MapCapture.cs'
        );
        self::assertStringContainsString('case "MapCap.ScanPoll"', $map);
        self::assertStringContainsString('return "OK|queued"', $map);
        self::assertStringContainsString('OK|ready|', $map);
        $sqf = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/camera/fn_mapCapture.sqf'
        );
        self::assertStringContainsString('MapCap.ScanPoll', $sqf);
    }

    public function testAuthLoginUsesExtAwait(): void
    {
        $pass = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_submitPassword.sqf'
        );
        self::assertStringContainsString('AuthPassword', $pass);
        self::assertStringContainsString('extAwait', $pass);
        $steam = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_loginSteam.sqf'
        );
        self::assertStringContainsString('AuthSteam', $steam);
        self::assertStringContainsString('extAwait', $steam);
        $enter = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_enterAthena.sqf'
        );
        self::assertStringContainsString('ConnectC2', $enter);
        self::assertStringContainsString('extAwait', $enter);
    }
}
