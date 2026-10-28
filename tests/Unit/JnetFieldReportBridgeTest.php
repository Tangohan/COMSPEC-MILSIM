<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Jnet\JnetFieldReportBridge as Bridge;
use PHPUnit\Framework\TestCase;

/**
 * Relais terrain → JNET : règles de conversion des reco, photos et rapports du jeu.
 */
final class JnetFieldReportBridgeTest extends TestCase
{
    public function testFieldDataGoesUpTheChain(): void
    {
        self::assertSame([3], Bridge::targetsFor('up', 5, 3, []));
        // Unité de tête : reste dans son propre espace.
        self::assertSame([5], Bridge::targetsFor('up', 5, 0, []));
        // Un FRAGO descend vers les sous-unités, ou reste dans l'unité si elle n'en a pas.
        self::assertSame([7, 8], Bridge::targetsFor('down', 5, 3, [7, 8, 5, 0]));
        self::assertSame([5], Bridge::targetsFor('down', 5, 3, []));
    }

    public function testOperatorCanEscalateHigherFromTheGame(): void
    {
        self::assertSame(1, Bridge::escalationLevels([]));
        self::assertSame(1, Bridge::escalationLevels(['jnet_escalate' => '1']));
        self::assertSame(2, Bridge::escalationLevels(['jnet_escalate' => '2']));
        self::assertSame(20, Bridge::escalationLevels(['jnet_escalate' => 'TOP']));
        self::assertSame(1, Bridge::escalationLevels(['jnet_escalate' => 'n/a']));
        $sitrep = Bridge::buildReport(['report_type' => 'SITREP', 'structured_data' => ['jnet_escalate' => '2', 'kind' => 'sitrep', 'next' => 'Tenir le carrefour']]);
        self::assertSame(2, $sitrep['escalate']);
        // Le choix d'échelon et les clés techniques ne s'affichent pas dans l'échange.
        self::assertStringNotContainsString('jnet_escalate', $sitrep['body']);
        self::assertStringNotContainsString('sitrep', $sitrep['body']);
        self::assertStringContainsString('Intentions : Tenir le carrefour', $sitrep['body']);
    }

    public function testReconNoteBecomesIntel(): void
    {
        $b = Bridge::buildReconNote(['tag' => 'vehicle', 'text' => "  technical\n au carrefour ", 'confidence' => 'seen', 'mission_id' => 'OP-41']);
        self::assertSame('renseignement', $b['kind']);
        self::assertSame('up', $b['direction']);
        self::assertSame('Reco · Véhicule : technical au carrefour', $b['title']);
        self::assertStringContainsString('Fiabilité : vu direct', $b['body']);
        self::assertStringContainsString('Mission : OP-41', $b['body']);
        self::assertSame('atak', $b['link_url']);
        self::assertNull($b['image_url']);
    }

    public function testConfidentialPhotoNeverShowsItsImage(): void
    {
        $public = Bridge::buildReconImage(['id' => 412, 'title' => 'compound nord', 'device_type' => 'CTAB', 'grid_ref' => '38S MB 412 776', 'heading' => 283.6], 'https://ex/uploads/recon/a.jpg');
        self::assertSame('Photo de reco · compound nord', $public['title']);
        self::assertSame('https://ex/uploads/recon/a.jpg', $public['image_url']);
        self::assertSame('recon/images/412', $public['link_url']);
        self::assertStringContainsString('grille 38S MB 412 776', $public['body']);
        self::assertStringContainsString('AZ 284°', $public['body']);

        $secret = Bridge::buildReconImage(['id' => 413, 'title' => 'Nom du contact', 'description' => 'détail sensible', 'is_confidential' => 1], null);
        self::assertSame('Photo de reco confidentielle', $secret['title']);
        self::assertNull($secret['image_url']);
        self::assertStringNotContainsString('Nom du contact', $secret['title'] . $secret['body']);
        self::assertStringNotContainsString('détail sensible', $secret['body']);
        self::assertStringContainsString('accès contrôlé', $secret['body']);

        $sse = Bridge::buildReconImage(['id' => 414, 'is_sse' => 1, 'device_type' => 'UAV'], null);
        self::assertNull($sse['image_url']);
        self::assertStringContainsString('drone', $sse['body']);
        self::assertStringContainsString('bureau SSE', $sse['body']);
    }

    public function testReportsAreClassifiedAndRestrictedOnesStayOut(): void
    {
        $salute = Bridge::buildReport(['report_type' => 'SALUTE', 'summary' => '6 combattants', 'priority' => 'ROUTINE', 'grid_reference' => '38S MB 412 776', 'report_number' => 'SAL-12']);
        self::assertSame('renseignement', $salute['kind']);
        self::assertSame('up', $salute['direction']);
        self::assertStringEndsWith(': 6 combattants', $salute['title']);
        self::assertStringContainsString('Grille : 38S MB 412 776', $salute['body']);
        self::assertStringContainsString('Réf. SAL-12', $salute['body']);

        self::assertSame('compte_rendu', Bridge::buildReport(['report_type' => 'BDA'])['kind']);
        $frago = Bridge::buildReport(['report_type' => 'FRAGO', 'summary' => 'Exfil nord']);
        self::assertSame('ordre', $frago['kind']);
        self::assertSame('down', $frago['direction']);
        self::assertStringStartsWith('URGENT · ', Bridge::buildReport(['report_type' => 'EAGLE_DOWN'])['title']);

        self::assertTrue(Bridge::reportIsShareable(['visibility' => 'ALL']));
        self::assertTrue(Bridge::reportIsShareable([]));
        self::assertFalse(Bridge::reportIsShareable(['visibility' => 'COMMAND']));
    }

    public function testTitlesFitTheExchangeLimit(): void
    {
        $b = Bridge::buildReconNote(['tag' => 'other', 'text' => str_repeat('a', 400)]);
        self::assertSame(160, mb_strlen($b['title']));
        self::assertStringEndsWith('…', $b['title']);
    }

    public function testBridgeIsWiredIntoGameEndpointsAndSwitchable(): void
    {
        $root = dirname(__DIR__, 2);
        $api = (string) file_get_contents($root . '/app/Controllers/Api/AtakApiController.php');
        $modules = (string) file_get_contents($root . '/app/Services/Tactical/AtakBridgeModulesService.php');
        $view = (string) file_get_contents($root . '/views/jnet/_exchange.php');

        self::assertStringContainsString('relayReconNote(', $api);
        self::assertStringContainsString('relayReconImage(', $api);
        self::assertStringContainsString('relayTacticalReport(', $api);
        self::assertStringContainsString("'id' => 'jnet_bridge'", $modules);
        // Jamais au nom d'un Steam seulement déclaré : session vérifiée ou clé personnelle.
        self::assertStringContainsString('empty($actor[\'session_ok\'])', $api);
        self::assertStringContainsString('Cliché protégé', $view);
        self::assertStringContainsString('jn-ex__source', $view);
    }
}
