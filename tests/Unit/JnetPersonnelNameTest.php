<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Le nom d'un membre dans l'annuaire JNET est celui de sa fiche (prénom + nom du personnage), puis indicatif, puis nom du compte : règle unique MemberIdentity. */
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

    public function testPersonnelCardsUseTheCharacterIdentityNotTheLegalOne(): void
    {
        $src = $this->service();
        self::assertStringContainsString('(new \App\Services\Personnel\MemberNameService())->withIdentities($tenantId, $raw)', $src);
    }

    public function testNameComesFromTheSingleDisplayRuleAndIgnoresTheCharacterName(): void
    {
        $src = $this->service();
        $card = substr($src, (int) strpos($src, 'function normalizePersonCard'));
        $card = substr($card, 0, (int) strpos($card, 'return ['));
        self::assertStringContainsString('\App\Support\MemberIdentity::display([', $card);
        self::assertStringContainsString("'first_name' => (string) (\$row['first_name'] ?? '')", $card);
        self::assertStringNotContainsString('character_name', $card);
        self::assertStringNotContainsString('$character', $card);
    }
}
