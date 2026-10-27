<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Personnel\PersonnelFieldValueFormatter;
use PHPUnit\Framework\TestCase;

final class PersonnelFieldValueFormatterTest extends TestCase
{
    public function testAssignmentsAndJobsAreNeverShownAsRawLists(): void
    {
        $fmt = new PersonnelFieldValueFormatter();

        self::assertSame([], $fmt->lines('unit_assignments', '[]', 1));
        self::assertSame([], $fmt->lines('job_roles', '[]', 1));
        self::assertSame('', $fmt->display('job_roles', '[]', 1));
        self::assertSame('Aucune équipe', PersonnelFieldValueFormatter::emptyPhrase('unit_assignments'));
        self::assertSame('Aucun emploi', PersonnelFieldValueFormatter::emptyPhrase('job_roles'));

        $shown = $fmt->display('unit_assignments', '[{"unit_id":55,"role_name":"","is_primary":1}]', 1);
        self::assertStringNotContainsString('unit_id', $shown);
        self::assertStringNotContainsString('{', $shown);
        self::assertStringNotContainsString('[', $shown);
        self::assertNotSame('', $shown);
    }

    public function testPlainValuesStayReadable(): void
    {
        $fmt = new PersonnelFieldValueFormatter();

        self::assertSame('Oui', $fmt->display('deployable', '1'));
        self::assertSame('Non', $fmt->display('deployable', '0'));
        self::assertSame('Interne', $fmt->display('rp_recruitment_origin', 'internal'));
        self::assertSame(['Alpha · Bravo'], $fmt->lines('extra_callsigns', "Alpha\nBravo"));
    }
}
