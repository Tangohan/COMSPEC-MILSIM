<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Le nom d'un membre dans l'annuaire JNET est celui de sa fiche (prénom + nom d'état civil), pas seulement son indicatif. */
final class JnetPersonnelNameTest extends TestCase
{
    private function service(): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(__DIR__ . '/../../app/Services/Jnet/JnetDashboardService.php'));
    }

    public function testRosterRowKeepsTheDirectoryFirstAndLastName(): void
    {
        // Le roster enrichi n'a pas first_name/last_name : la ligne d'annuaire les fournit.
        self::assertStringContainsString('$e = ($byId[$id] ?? []) + $row;', $this->service());
    }

    public function testNamePrefersTheFileNameThenCharacterThenCallsign(): void
    {
        $src = $this->service();
        $card = substr($src, (int) strpos($src, 'function normalizePersonCard'));
        $card = substr($card, 0, (int) strpos($card, 'return ['));
        self::assertStringContainsString("member_name([", $card);
        self::assertStringContainsString("'first_name' => \$first", $card);
        self::assertStringContainsString("'last_name' => \$last", $card);
        self::assertStringContainsString('\App\Support\MemberName::FALLBACK', $card);
        $civil = (int) strpos($card, '$name = $civil');
        $character = (int) strpos($card, '$character', $civil);
        $callsign = (int) strpos($card, '$callsign !== \'\' ? $callsign', $civil);
        self::assertTrue($civil > 0 && $character > $civil && $callsign > $character, 'ordre : état civil, personnage, indicatif');
    }
}
