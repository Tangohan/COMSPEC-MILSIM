<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\OperatorGame\OperatorGameReconciliationService;
use PHPUnit\Framework\TestCase;

final class OperatorGameReconciliationServiceTest extends TestCase
{
    public function testSensitiveDifferencesAreQualifiedWithoutMutatingReference(): void
    {
        $reference = ['steam_id'=>'76561198000000001','blood_type'=>'O+','callsign'=>'EAGLE-2','display_name'=>'John DOE','sex'=>'M'];
        $observed = ['steam_id'=>'76561198000000001','blood_type'=>'A+','callsign'=>'EAGLE-3','display_name'=>'John DOE','sex'=>'M','versions'=>[]];
        $before = $reference;
        $result = (new OperatorGameReconciliationService())->reconcile($reference, $observed);

        self::assertSame($before, $reference);
        self::assertSame(['blood_type','callsign'], array_column($result, 'field'));
        self::assertSame(['CRITICAL','WARNING'], array_column($result, 'severity'));
    }

    public function testCallsignFormattingAndLeadingZeroAreNormalized(): void
    {
        $result = (new OperatorGameReconciliationService())->reconcile(
            ['callsign'=>'EAGLE-2'],
            ['callsign'=>'EAGLE 02', 'versions'=>[]]
        );
        self::assertSame([], $result);
    }

    public function testVersionsDistinguishRecommendedFromUnsupported(): void
    {
        $service = new OperatorGameReconciliationService();
        $policies = ['atak'=>['minimum'=>'1.7.0','recommended'=>'1.8.2']];
        $warning = $service->reconcile([], ['versions'=>['atak'=>'1.7.4']], $policies);
        $error = $service->reconcile([], ['versions'=>['atak'=>'1.6.9']], $policies);
        self::assertSame('WARNING', $warning[0]['severity']);
        self::assertSame('ERROR', $error[0]['severity']);
    }

    public function testUnknownObservedValuesNeverCreateInventedDiscrepancies(): void
    {
        $result = (new OperatorGameReconciliationService())->reconcile(
            ['blood_type'=>'O+', 'sex'=>'M'],
            ['blood_type'=>'', 'sex'=>null, 'versions'=>[]]
        );
        self::assertSame([], $result);
    }
}
