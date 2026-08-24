<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AtakRealismRepository;
use PHPUnit\Framework\TestCase;

final class AtakRealismTelemetryTest extends TestCase
{
    public function testExtractClientIpTakesFirstForwardedAddress(): void
    {
        self::assertSame('203.0.113.10', AtakRealismRepository::extractClientIp('203.0.113.10, 10.0.0.1'));
        self::assertSame('203.0.113.10', AtakRealismRepository::extractClientIp('203.0.113.10:443'));
        self::assertSame('2001:db8::1', AtakRealismRepository::extractClientIp('[2001:db8::1]:8443'));
        self::assertSame('', AtakRealismRepository::extractClientIp(''));
    }

    public function testComputeServerSignatureIsStableAndHostBound(): void
    {
        $a = AtakRealismRepository::computeServerSignature(12, 'Athena.example.fr:443');
        $b = AtakRealismRepository::computeServerSignature(12, 'athena.example.fr');
        $c = AtakRealismRepository::computeServerSignature(12, 'other.example.fr');

        self::assertSame(16, strlen($a));
        self::assertSame($a, $b);
        self::assertNotSame($a, $c);
        self::assertSame('', AtakRealismRepository::computeServerSignature(0, 'athena.example.fr'));
        self::assertSame('', AtakRealismRepository::computeServerSignature(12, ''));
        self::assertSame(
            strtoupper(substr(hash('sha256', 'athena-atak|12|athena.example.fr'), 0, 16)),
            $a
        );
    }

    public function testFormatServerSignatureGroupsHex(): void
    {
        self::assertSame('A1B2 C3D4 E5F6 7890', AtakRealismRepository::formatServerSignature('a1b2c3d4e5f67890'));
        self::assertSame('', AtakRealismRepository::formatServerSignature(''));
    }

    public function testTrustChainLabelCoversEstablishedRevokedExpiredAndMissing(): void
    {
        self::assertSame('Non établie', AtakRealismRepository::trustChainLabel([]));
        self::assertSame(
            'Établie · Réseau ami',
            AtakRealismRepository::trustChainLabel([
                'certificate_status' => 'issued',
                'crypto_domain_label' => 'Réseau ami',
            ])
        );
        self::assertSame(
            'Révoquée',
            AtakRealismRepository::trustChainLabel(['certificate_status' => 'revoked'])
        );
        self::assertSame(
            'Expirée',
            AtakRealismRepository::trustChainLabel([
                'certificate_status' => 'issued',
                'certificate_expires_at' => '2020-01-01 00:00:00',
            ])
        );
    }

    public function testLiaisonIdentityFormatsHumanFieldsWithoutInventingValues(): void
    {
        $empty = AtakRealismRepository::liaisonIdentity([]);
        self::assertSame('Non établie', $empty['trust']);
        self::assertSame('—', $empty['authority']);
        self::assertSame('—', $empty['versions']);
        self::assertSame('—', $empty['signature']);
        self::assertSame('—', $empty['ip']);

        $full = AtakRealismRepository::liaisonIdentity([
            'certificate_status' => 'active',
            'crypto_domain_label' => 'Réseau ami',
            'certificate_authority' => 'Autorité ATAK locale',
            'mod_version' => '1.0.47',
            'extension_version' => '1.17',
            'server_signature' => 'a1b2c3d4e5f67890',
            'server_host' => 'athena.example.fr',
            'last_client_ip' => '203.0.113.10',
        ]);
        self::assertSame('Établie · Réseau ami', $full['trust']);
        self::assertSame('Autorité ATAK locale', $full['authority']);
        self::assertSame('Mod 1.0.47 · DLL 1.17', $full['versions']);
        self::assertSame('A1B2 C3D4 E5F6 7890', $full['signature']);
        self::assertSame('athena.example.fr', $full['host']);
        self::assertSame('203.0.113.10', $full['ip']);
    }
}
