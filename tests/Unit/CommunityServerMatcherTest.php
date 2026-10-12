<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Atak\CommunityServerMatcher;
use PHPUnit\Framework\TestCase;

final class CommunityServerMatcherTest extends TestCase
{
    private function servers(): array
    {
        return [
            ['id' => 1, 'label' => 'Ops', 'ip_address' => '203.0.113.10', 'server_name' => null],
            ['id' => 2, 'label' => 'Entraînement', 'ip_address' => null, 'server_name' => '[FR] COMSPEC | Training'],
            ['id' => 3, 'label' => 'Hôte', 'ip_address' => 'arma.example.org', 'server_name' => ''],
        ];
    }

    private function matcher(): CommunityServerMatcher
    {
        return new CommunityServerMatcher(static fn (string $h): array => $h === 'arma.example.org' ? ['198.51.100.7'] : []);
    }

    public function testMatchesByIpIgnoringPort(): void
    {
        $m = $this->matcher()->match($this->servers(), '203.0.113.10:2302', '');
        self::assertSame(1, $m['id'] ?? null);
    }

    public function testMatchesHostnameThroughDns(): void
    {
        self::assertSame(3, $this->matcher()->match($this->servers(), '198.51.100.7', '')['id'] ?? null);
    }

    public function testFallsBackToServerNameContained(): void
    {
        $m = $this->matcher()->match($this->servers(), '', '[FR]  COMSPEC | training | Altis 12/40');
        self::assertSame(2, $m['id'] ?? null);
    }

    public function testOtherServerIsNotMatched(): void
    {
        self::assertNull($this->matcher()->match($this->servers(), '192.0.2.55', 'Public Zeus #3'));
    }

    public function testNormalizeHost(): void
    {
        self::assertSame('2001:db8::1', CommunityServerMatcher::normalizeHost('[2001:DB8::1]:2302'));
        self::assertSame('', CommunityServerMatcher::normalizeHost('pas une adresse'));
        self::assertSame('arma.example.org', CommunityServerMatcher::normalizeHost('Arma.Example.org'));
    }
}
