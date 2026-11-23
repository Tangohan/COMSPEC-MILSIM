<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Effectifs\EffectifsDeskPayload;
use PHPUnit\Framework\TestCase;

final class EffectifsDeskPayloadTest extends TestCase
{
    public function testMembersCarryAccountStateAndCallsign(): void
    {
        $rows = [
            ['id' => 7, 'callsign' => 'ALPHA 6', 'display_name' => 'Fictif', 'status' => 'active', 'grade_short' => 'LTN', 'unit_id' => 3, 'unit_name' => 'Section A', 'assignment_path' => 'Cie 1 / Section A', 'completion_score' => 80, 'seniority_days' => 40],
            ['id' => 8, 'callsign' => '', 'display_name' => '', 'status' => 'active', 'unit_id' => 0],
        ];
        $noAccount = [['id' => 8, 'status' => 'invitee', 'email' => 'x@example.test', 'invite_id' => 2, 'invite_at' => '2026-11-01 10:00:00', 'invite_expired' => false, 'source_label' => 'Saisie à la main', 'last_activity' => '2026-11-02 08:00:00']];
        $out = EffectifsDeskPayload::members($rows, [], $noAccount);
        self::assertCount(2, $out);
        self::assertSame('ALPHA 6', $out[0]['callsign']);
        self::assertSame('actif', $out[0]['account']);
        self::assertSame('Section A', $out[0]['unit']);
        self::assertSame('Cie 1', $out[0]['parent']);
        self::assertSame('invitee', $out[1]['account']);
        self::assertSame('Invitation envoyée', $out[1]['accountLabel']);
        self::assertSame('x@example.test', $out[1]['email']);
    }

    public function testUnitsDropUnknownParents(): void
    {
        $units = [['id' => 1, 'parent_id' => 0, 'name' => 'Racine', 'assignment_path' => 'Racine'], ['id' => 2, 'parent_id' => 99, 'name' => 'Orpheline', 'assignment_path' => 'Orpheline']];
        $out = EffectifsDeskPayload::units($units, [1 => ['code' => 'R1']], [1 => 'Chef Fictif']);
        self::assertSame(0, $out[1]['parentId']);
        self::assertSame('R1', $out[0]['code']);
        self::assertSame('Chef Fictif', $out[0]['commander']);
    }
}
