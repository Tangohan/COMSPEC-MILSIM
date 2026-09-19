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
        self::assertStringContainsString('ATAK_MARKER_ICONS_CDN', $view);
        self::assertStringContainsString('ow-brand-word">ATHENA', $view);
        self::assertStringContainsString('id="ow-stat-traffic"', $view);
        self::assertStringContainsString('id="ow-follow-chip"', $view);
        self::assertStringContainsString('id="ow-map-tl"', $view);
        self::assertStringContainsString('id="ow-look-arrow"', $view);
        self::assertStringContainsString('id="ow-predict"', $view);
        self::assertStringContainsString('id="ow-progress-trail"', $view);
        self::assertStringContainsString('id="ow-geo-places"', $view);
        self::assertStringContainsString('id="ow-df-list"', $view);
        self::assertStringNotContainsString('Catalogue satellites', $view);
        self::assertStringNotContainsString('data-ctx="ring250"', $view);

        self::assertStringContainsString('headingArrowShape', $ops);
        self::assertStringContainsString('renderProgressTrail', $ops);
        self::assertStringContainsString('motionSpeedMs', $ops);
        self::assertStringContainsString('ow-look-head', $ops);
        self::assertStringContainsString('ow-progress-trail', $ops);
        self::assertStringContainsString("shaftPx", $ops);
        self::assertStringNotContainsString('headingPoint(loc, heading, 28)', $ops);
        self::assertStringContainsString('openSitrep', $ops);
        self::assertStringContainsString('name="urgency"', $ops);
        self::assertStringContainsString('Envoyer aussi sur le canal Commandement', $ops);
        self::assertStringContainsString('promptShape', $ops);
        self::assertStringContainsString('dropPendingPreview', $ops);
        self::assertStringContainsString('fill_style', $ops);
        self::assertStringContainsString('ColorGreen', $ops);
        self::assertStringContainsString('name="description"', $ops);
        self::assertStringContainsString('ow-marker-list', $ops);
        self::assertStringContainsString('Peut-être plus là', $ops);
        self::assertStringContainsString('Allers-retours', $ops);
        self::assertStringContainsString('n’est peut-être plus présente', $ops);
        self::assertStringContainsString('renderMarkerIntel', $ops);
        self::assertStringContainsString('dashArray: \'5 6\'', $ops);
        self::assertStringContainsString('ow-los-block', $ops);
        self::assertStringContainsString("color: '#00d69a'", $ops);
        self::assertStringContainsString('/api/atak/ingest-traffic', $ops);
        self::assertStringContainsString('Relais ATAK', $ops);
        self::assertStringContainsString('registerScratch', $ops);
        self::assertStringContainsString("api.registerScratch(group, 'los'", $ops);
        self::assertStringContainsString('var group = L.layerGroup()', $ops);
        self::assertStringContainsString('/api/atak/relays/', $ops);
        self::assertStringContainsString('Clic droit pour le retirer', $ops);
        self::assertStringContainsString('/api/atak/sigint/zones', $ops);
        self::assertStringContainsString('/api/replay/mission/', $ops);
        self::assertStringContainsString('circleByRadius', $ops);

        self::assertStringContainsString('appendTrack(id, trueLoc)', $beta);
        self::assertStringNotContainsString('if (tracksOn) appendTrack(id, trueLoc)', $beta);
        self::assertStringContainsString("if (window.OverwatchOps && window.OverwatchOps.afterRenderMap) window.OverwatchOps.afterRenderMap()", $beta);
        self::assertStringContainsString('if (lastGroupKey) html += \'</div></div>\'', $beta);
        self::assertStringContainsString('ow-raw-preview', $beta);
        self::assertStringContainsString('is-delayed', $beta);
        self::assertStringContainsString('circleLatLngs', $beta);
        self::assertStringContainsString('L.polygon(circleLatLngs', $beta);
        self::assertStringNotContainsString('Catalogue satellites', $beta);
        self::assertStringContainsString('registerScratch: registerScratch', $beta);
        self::assertStringContainsString("removeScratch('los'", $beta);
        self::assertStringContainsString("toast('Visée retirée.')", $beta);
        self::assertStringContainsString("toast('Anneaux retirés.')", $beta);
        self::assertStringContainsString('getRangeLayer', $beta);
        self::assertStringContainsString('losGroups.forEach', $beta);
        self::assertStringContainsString('HIT_POINT', $beta);
        self::assertStringContainsString('dropMarkerLocal', $beta);
        self::assertStringContainsString('Ce repère n’est plus au poste.', $beta);
        self::assertStringNotContainsString('[DEBUG INIT]', $beta);
        self::assertStringNotContainsString('[DEBUG sendChat]', $beta);

        self::assertStringNotContainsString('celestrak', strtolower($gotak));
        self::assertStringContainsString('__owInterceptLine', $gotak);
        self::assertStringContainsString("registerScratch(window.__owInterceptLine, 'intercept'", $gotak);

        self::assertStringContainsString('.ow-map-tl{position:absolute', $css);
        self::assertStringContainsString('.ow-follow-chip[hidden]{display:none!important}', $css);
        self::assertStringContainsString('.ow-wx[hidden]{display:none!important}', $css);
        self::assertStringNotContainsString('.ow-wx{position:absolute;left:52px;top:10px', $css);
        self::assertStringNotContainsString('.ow-follow-chip{position:absolute;left:52px;top:10px', $css);
        self::assertStringContainsString('owDelayed', $css);
        self::assertStringContainsString('.ow-rail-extra{position:absolute', $css);
        self::assertStringContainsString('.ow-rail-extra[hidden]{display:none!important}', $css);
        self::assertStringContainsString('overflow:visible', $css);
        self::assertStringContainsString('ow-hatch-diag', $css);
        self::assertStringContainsString('ow-marker-list-row', $css);
        self::assertStringContainsString('ow-marker-age-chip', $css);
        self::assertStringContainsString('isHostileMarker', $ops);
        self::assertStringContainsString('Unités hostiles', $ops);
        self::assertStringContainsString('.ow-confirm[hidden]{display:none!important}', $css);
        self::assertStringContainsString('#ow-chat-log', $css);
        self::assertStringNotContainsString('grid-template-rows:auto auto auto minmax(0,1fr) auto auto', $css);

        self::assertStringContainsString('dashArray: \'6 6\'', $terrain);
        self::assertStringContainsString('iconSize', $tools);
        self::assertStringContainsString('getRangeLayer', $tools);
        self::assertStringContainsString("registerScratch(group, 'range'", $tools);
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
        self::assertStringContainsString('function relaysDelete', file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Api/AtakApiController.php'));
        self::assertStringContainsString('UpdateRelay', $ext);
        self::assertStringContainsString('ATAK_LINK_VIA_RELAYS_V1', $catalog);
        self::assertStringContainsString('ATAK_LINK_VIA_RELAYS_V1', $seed);
        self::assertStringContainsString('back-office/atak/roleplay#liaison-relais', $catalog);
        self::assertStringContainsString('OVERWATCH_SERVER_CONTROL_V1', $catalog);
        self::assertStringContainsString('OVERWATCH_SERVER_CONTROL_V1', $seed);
    }
}
