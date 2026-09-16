<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakOverwatchBetaOpsAssetTest extends TestCase
{
    public function testBetaSurfaceWiresRealActionsAndChrome(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/atak-overwatch-beta.php');
        $ops = (string) file_get_contents($root . '/public/assets/js/atak-overwatch-ops.js');
        $beta = (string) file_get_contents($root . '/public/assets/js/atak-overwatch-beta.js');
        $gotak = (string) file_get_contents($root . '/public/assets/js/atak-overwatch-gotak.js');
        $css = (string) file_get_contents($root . '/public/assets/css/atak-overwatch-beta.css');
        $tools = (string) file_get_contents($root . '/public/assets/js/atak-overwatch-tools.js');
        $terrain = (string) file_get_contents($root . '/public/assets/js/atak-terrain-tools.js');

        self::assertStringContainsString('family=Inter', $view);
        self::assertStringContainsString('ow-brand-word">ATHENA', $view);
        self::assertStringContainsString('id="ow-stat-traffic"', $view);
        self::assertStringContainsString('id="ow-follow-chip"', $view);
        self::assertStringContainsString('id="ow-look-arrow"', $view);
        self::assertStringContainsString('id="ow-predict"', $view);
        self::assertStringContainsString('id="ow-geo-places"', $view);
        self::assertStringContainsString('id="ow-df-list"', $view);
        self::assertStringNotContainsString('Catalogue satellites', $view);
        self::assertStringNotContainsString('data-ctx="ring250"', $view);

        self::assertStringContainsString('openSitrep', $ops);
        self::assertStringContainsString('name="urgency"', $ops);
        self::assertStringContainsString('Envoyer aussi sur le canal Commandement', $ops);
        self::assertStringContainsString('promptShape', $ops);
        self::assertStringContainsString('fill_style', $ops);
        self::assertStringContainsString('ColorGreen', $ops);
        self::assertStringContainsString('dashArray: \'6 6\'', $ops);
        self::assertStringContainsString('/api/atak/ingest-traffic', $ops);
        self::assertStringContainsString('/api/atak/relays', $ops);
        self::assertStringContainsString('/api/atak/sigint/zones', $ops);
        self::assertStringContainsString('/api/replay/mission/', $ops);
        self::assertStringContainsString('circleByRadius', $ops);

        self::assertStringContainsString('if (lastGroupKey) html += \'</div></div>\'', $beta);
        self::assertStringContainsString('ow-raw-preview', $beta);
        self::assertStringContainsString('is-delayed', $beta);
        self::assertStringContainsString('circleLatLngs', $beta);
        self::assertStringContainsString('L.polygon(circleLatLngs', $beta);
        self::assertStringNotContainsString('Catalogue satellites', $beta);
        self::assertStringContainsString('Aucun contact en liaison', $beta);

        self::assertStringNotContainsString('celestrak', strtolower($gotak));
        self::assertStringContainsString('__owInterceptLine', $gotak);

        self::assertStringContainsString('white-space:nowrap', $css);
        self::assertStringContainsString('owDelayed', $css);
        self::assertStringContainsString('.ow-rail-extra{position:absolute', $css);
        self::assertStringContainsString('ow-hatch-diag', $css);

        self::assertStringContainsString('dashArray: \'6 6\'', $terrain);
        self::assertStringContainsString('iconSize', $tools);
    }

    public function testIngestTrafficShowsZeroWhenEmpty(): void
    {
        $root = dirname(__DIR__, 2);
        $repo = (string) file_get_contents($root . '/app/Repositories/AtakIngestTrafficRepository.php');
        $ops = (string) file_get_contents($root . '/public/assets/js/atak-overwatch-ops.js');
        self::assertStringContainsString("'kbps_now' => 0.0", $repo);
        self::assertStringContainsString('Aucune remontée', $repo);
        self::assertStringContainsString("kbps > 0 ? kbps.toFixed(1) + ' ko/s' : '0'", $ops);
        self::assertStringNotContainsString('Math.random', $ops);
    }

    public function testRelaysAndRoleplayStayOnExistingScreen(): void
    {
        $root = dirname(__DIR__, 2);
        $roleplay = (string) file_get_contents($root . '/views/admin/atak/roleplay.php');
        $can = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_canTransmit.sqf');
        $ext = (string) file_get_contents($root . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        $catalog = (string) file_get_contents($root . '/app/Services/ConfigurationUpdate/ConfigurationUpdateCatalog.php');
        $seed = (string) file_get_contents($root . '/bootstrap/configuration_updates_migration.php');

        self::assertStringContainsString('Liaison ATAK par relais', $roleplay);
        self::assertStringContainsString('name="link_via_relays"', $roleplay);
        self::assertStringContainsString('COMSPEC_LinkViaRelays', $can);
        self::assertStringContainsString('no_relay', $can);
        self::assertStringContainsString('link_via_relays', $ext);
        self::assertStringContainsString('UpdateRelay', $ext);
        self::assertStringContainsString('ATAK_LINK_VIA_RELAYS_V1', $catalog);
        self::assertStringContainsString('ATAK_LINK_VIA_RELAYS_V1', $seed);
        self::assertStringContainsString('back-office/atak/roleplay#liaison-relais', $catalog);
    }
}
