<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Api\LogisticsController;
use PHPUnit\Framework\TestCase;

final class LogisticsGameJsonTest extends TestCase
{
    public function testDoubledQuotesFromCallExtensionAreDecoded(): void
    {
        $raw = '{""missionId"":""mission_1_map_1"",""assetId"":""TA1"",""callsign"":""TA1"",""fuel_ratio"":0.8,""ammo_state_json"":{""magazinesCount"":6}}';
        $body = LogisticsController::decodeGameJson($raw);

        self::assertIsArray($body);
        self::assertSame('TA1', $body['assetId']);
        self::assertSame(6, $body['ammo_state_json']['magazinesCount']);
    }

    public function testQuotedSqfStringAndFrenchDecimalCommaAreDecoded(): void
    {
        $body = LogisticsController::decodeGameJson('"{""assetId"":""Alpha \""1\"""",""fuel_ratio"":0,75}"');

        self::assertIsArray($body);
        self::assertSame('Alpha "1"', $body['assetId']);
        self::assertSame(0.75, $body['fuel_ratio']);
    }

    public function testCleanJsonIsUnchangedAndGarbageIsRejected(): void
    {
        self::assertSame(['assetId' => 'B2', 'note' => ''], LogisticsController::decodeGameJson('{"assetId":"B2","note":""}'));
        self::assertNull(LogisticsController::decodeGameJson('pas du json'));
    }

    public function testExtensionNormalisesLogisticsPayloads(): void
    {
        $cs = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        self::assertMatchesRegularExpression('/"Logistics\.Update"[\s\S]{0,400}NormalizeArmaJson\(args\[0\]\)[\s\S]{0,80}\/api\/logistics\/update/', $cs);
    }
}
