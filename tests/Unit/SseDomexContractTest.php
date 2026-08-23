<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SseDomexContract;
use PHPUnit\Framework\TestCase;

final class SseDomexContractTest extends TestCase
{
    public function testNormalizeNodeMapsAliasesAndKeepsIdentity(): void
    {
        $node = SseDomexContract::normalizeNode([
            'nodeId' => 'pc-kestrel-04',
            'deviceType' => 'LAPTOP',
            'owner' => 'HASSAN Karim',
            'org' => 'KESTREL',
            'network' => 'KESTREL-LAN',
            'security' => 'high',
            'profile' => 'logistics',
            'duration' => 120,
            'accessRemote' => 'oui',
        ]);

        self::assertSame('PC-KESTREL-04', $node['node_id']);
        self::assertSame('ordinateur', $node['device_type']);
        self::assertSame('HASSAN Karim', $node['owner_label']);
        self::assertSame('KESTREL', $node['organization_label']);
        self::assertSame('KESTREL-LAN', $node['fictional_network']);
        self::assertSame('elevee', $node['security_tier']);
        self::assertSame('logistique', $node['content_profile']);
        self::assertSame(120, $node['duration_s']);
        self::assertTrue($node['access_remote']);
        self::assertTrue($node['access_physical']);
    }

    public function testNormalizePacketNeverConfirmsAndFlagsDecoy(): void
    {
        $packet = SseDomexContract::normalizePacket([
            'type' => 'message',
            'text' => 'Le prochain convoi quittera le dépôt avant l’aube.',
            'quality' => 'decoy',
            'confidence' => 'confirmed',
            'entities' => "KANDAR | lieu\nKESTREL | organisation",
        ], 'PC-KESTREL-04', 1);

        self::assertNotNull($packet);
        self::assertSame('PC-KESTREL-04-P1', $packet['packet_uid']);
        self::assertSame('leurre_possible', $packet['quality']);
        self::assertTrue($packet['is_decoy']);
        self::assertFalse($packet['is_complete']);
        self::assertSame('a_corroborer', $packet['confidence']);
        self::assertSame('a_exploiter', $packet['status']);
        self::assertCount(2, $packet['linked_entities']);
        self::assertSame('KANDAR', $packet['linked_entities'][0]['label']);
        self::assertSame('lieu', $packet['linked_entities'][0]['kind']);
    }

    public function testZeusLiveCoordinateShowsOnMapWithoutCaseLink(): void
    {
        $packet = SseDomexContract::normalizePacket([
            'type' => 'coordinate',
            'text' => 'Dépôt signalé au nord du village.',
            'origin' => 'zeus_live',
            'position' => [1234.5, 6789.0, 12],
            'grid' => '123045',
            'show_on_map' => true,
        ], 'ZEUS-MAP-1', 1);

        self::assertNotNull($packet);
        self::assertSame('coordinate', $packet['packet_type']);
        self::assertSame('zeus_live', $packet['origin']);
        self::assertSame(1234.5, $packet['pos_x']);
        self::assertSame(6789.0, $packet['pos_y']);
        self::assertSame('123045', $packet['grid_reference']);
        self::assertTrue($packet['show_on_map']);
        self::assertTrue(SseDomexContract::shouldShowOnMap($packet));
    }

    public function testScenarioCoordinateStaysOffMapUntilLinked(): void
    {
        $packet = SseDomexContract::normalizePacket([
            'type' => 'coordinate',
            'text' => 'Point lu sur un manifeste.',
            'origin' => 'scenario',
            'pos_x' => 10,
            'pos_y' => 20,
            'show_on_map' => false,
        ], 'PC-KESTREL-04', 3);

        self::assertNotNull($packet);
        self::assertFalse(SseDomexContract::shouldShowOnMap($packet));
        $packet['status'] = 'rattache';
        self::assertTrue(SseDomexContract::shouldShowOnMap($packet));
        $packet['status'] = 'ecarte';
        self::assertFalse(SseDomexContract::shouldShowOnMap($packet));
    }

    public function testDelayedPacketStaysQueued(): void
    {
        $packet = SseDomexContract::normalizePacket([
            'type' => 'document',
            'text' => 'Manifeste incomplet.',
            'reveal' => 'access_established',
            'fragment' => true,
        ], 'PC-KESTREL-04', 2);

        self::assertNotNull($packet);
        self::assertSame('en_attente', $packet['status']);
        self::assertTrue($packet['is_fragment']);
        self::assertSame('a_corroborer', $packet['confidence']);
    }

    public function testEmptyPacketIsRejected(): void
    {
        self::assertNull(SseDomexContract::normalizePacket(['type' => '', 'text' => ''], 'X', 1));
    }

    public function testQualityNoteNeverCallsADecoyConfirmed(): void
    {
        $note = SseDomexContract::qualityNote(1, 1, 3);
        self::assertStringContainsString('3 paquets', $note);
        self::assertStringContainsString('fragment', $note);
        self::assertStringContainsString('corroborer', $note);
        self::assertStringNotContainsString('leurre confirmé', $note);
    }
}
