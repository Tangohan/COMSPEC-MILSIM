<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Steam\SteamOpenIdService;
use PHPUnit\Framework\TestCase;

final class SteamOpenIdServiceTest extends TestCase
{
    public function testClaimedSteamIdReadsOpenIdUrl(): void
    {
        $svc = new SteamOpenIdService();
        $id = $svc->claimedSteamId([
            'openid.claimed_id' => 'https://steamcommunity.com/openid/id/76561198000000000',
        ]);
        self::assertSame('76561198000000000', $id);
    }

    public function testVerificationSwitchesModeAndKeepsClaim(): void
    {
        $svc = new SteamOpenIdService();
        $fields = $svc->verificationFields([
            'openid.mode' => 'id_res',
            'openid.claimed_id' => 'https://steamcommunity.com/openid/id/76561198000000000',
            'openid.sig' => 'abc',
            'state' => 'not-openid',
        ]);
        self::assertSame('check_authentication', $fields['openid.mode']);
        self::assertSame('https://steamcommunity.com/openid/id/76561198000000000', $fields['openid.claimed_id']);
        self::assertArrayNotHasKey('state', $fields);
    }

    public function testPositiveAssertionParsesSteamKeyValue(): void
    {
        $svc = new SteamOpenIdService();
        self::assertTrue($svc->isPositiveAssertion("ns:http://specs.openid.net/auth/2.0\nis_valid:true\n"));
        self::assertFalse($svc->isPositiveAssertion("ns:http://specs.openid.net/auth/2.0\nis_valid:false\n"));
    }
}
