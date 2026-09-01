<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\OperatorTacticalIdentity;
use PHPUnit\Framework\TestCase;

final class OperatorTacticalIdentityTest extends TestCase
{
    public function testCallsignPrefersPersonnelThenUserNeverTenant(): void
    {
        $tenant = 'S.O.A.R - (The Special Operations Action Regiments) SOF MilSim Group';

        self::assertSame('YB1', OperatorTacticalIdentity::callsign(['YB1', $tenant], $tenant, $tenant));
        self::assertSame('TA1', OperatorTacticalIdentity::callsign(['', 'TA1'], $tenant));
        self::assertSame('YA1 / Bravo', OperatorTacticalIdentity::callsign(['YA1 / Bravo'], $tenant));
        self::assertSame('', OperatorTacticalIdentity::callsign([$tenant, $tenant], $tenant, $tenant));
        self::assertSame('', OperatorTacticalIdentity::callsign([mb_substr($tenant, 0, 50)], $tenant));
        self::assertSame('', OperatorTacticalIdentity::callsign(['https://athena.ttrd.fr/public/api/game/v1/branding/render/soar'], $tenant));
        self::assertSame('', OperatorTacticalIdentity::callsign(['']));
    }

    public function testUnitAssignmentKeepsEffectifsAffectation(): void
    {
        $tenant = 'S.O.A.R - (The Special Operations Action Regiments) SOF MilSim Group';

        self::assertSame(
            '24th STS Gold Team SOF TACP',
            OperatorTacticalIdentity::unitAssignment('24th STS Gold Team SOF TACP', $tenant)
        );
        self::assertSame(
            'B SQN, TRP A - BRAVO ASSAULT',
            OperatorTacticalIdentity::unitAssignment('B SQN, TRP A - BRAVO ASSAULT', $tenant)
        );
        self::assertSame('', OperatorTacticalIdentity::unitAssignment($tenant, $tenant));
        self::assertSame('', OperatorTacticalIdentity::unitAssignment('https://athena.ttrd.fr/public/api/game/v1/branding/render/soar'));
    }
}
