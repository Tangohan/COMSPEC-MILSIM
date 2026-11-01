<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Effectifs\MemberSanctions;
use PHPUnit\Framework\TestCase;

/** Mesures disciplinaires de la fiche membre : en vigueur, échues, levées. */
final class MemberSanctionsTest extends TestCase
{
    public function testStatusesAndActiveCount(): void
    {
        $out = MemberSanctions::summarize([
            ['id' => 1, 'action_type' => 'warn', 'reason' => 'Retard répété', 'created_at' => '2026-10-02 20:00:00', 'actor_display_name' => 'Sgt. Mercer'],
            ['id' => 2, 'action_type' => 'mute', 'created_at' => '2026-09-01', 'expires_at' => '2026-09-08 00:00:00'],
            ['id' => 3, 'action_type' => 'warn', 'created_at' => '2026-08-01', 'revoked_at' => '2026-08-15 10:00:00', 'revoker_display_name' => 'Cpt. Reyes'],
            ['id' => 4, 'action_type' => 'suspend', 'created_at' => '2026-10-20', 'expires_at' => '2026-12-01 00:00:00'],
        ], new \DateTimeImmutable('2026-11-01 12:00:00'));

        self::assertSame(4, $out['total']);
        self::assertSame(2, $out['active']);
        self::assertSame(['active', 'expired', 'lifted', 'active'], array_column($out['items'], 'status'));
        self::assertSame('Avertissement', $out['items'][0]['label']);
        self::assertSame('Restriction d’activité', $out['items'][1]['label']);
        self::assertSame('Levée', $out['items'][2]['status_label']);
        self::assertSame('Cpt. Reyes', $out['items'][2]['lifted_by']);
    }

    public function testEmptyFileIsClean(): void
    {
        self::assertSame(['items' => [], 'active' => 0, 'total' => 0], MemberSanctions::summarize([], new \DateTimeImmutable()));
    }
}
