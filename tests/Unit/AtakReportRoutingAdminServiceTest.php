<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tactical\AtakReportRoutingAdminService as Svc;
use PHPUnit\Framework\TestCase;

/** Le simulateur applique les mêmes conditions que le moteur de diffusion. */
final class AtakReportRoutingAdminServiceTest extends TestCase
{
    private static function rule(array $cond, bool $active = true): array
    {
        return ['is_active' => $active, 'trigger_conditions' => json_encode($cond)];
    }

    public function testEvaluateMatchesEngineConditions(): void
    {
        $report = ['report_type' => 'TIC', 'priority' => 'FLASH', 'summary' => 'Embuscade sur l’axe NEPTUNE', 'details' => ''];

        self::assertTrue(Svc::evaluate($report, self::rule([]))[0]);
        self::assertTrue(Svc::evaluate($report, self::rule(['report_types' => ['TIC', 'CONTACT']]))[0]);
        self::assertFalse(Svc::evaluate($report, self::rule(['report_types' => ['LOGREP']]))[0]);
        self::assertFalse(Svc::evaluate($report, self::rule(['priorities' => ['ROUTINE']]))[0]);
        self::assertTrue(Svc::evaluate($report, self::rule(['keywords' => ['EMBUSCADE']]))[0], 'mots-clés insensibles à la casse');
        self::assertFalse(Svc::evaluate($report, self::rule(['keywords' => ['IED']]))[0]);
        // Une règle désactivée correspond mais ne s'applique pas : l'explication le dit.
        [$ok, $why] = Svc::evaluate($report, self::rule([], false));
        self::assertTrue($ok);
        self::assertSame('Règle désactivée', end($why)['text']);
    }

    public function testJsonList(): void
    {
        self::assertSame(['a', 'b'], Svc::jsonList('["a","b"]'));
        self::assertSame([], Svc::jsonList('{"a":1}'));
        self::assertSame([], Svc::jsonList(null));
    }
}
