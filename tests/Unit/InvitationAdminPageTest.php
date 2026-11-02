<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\Organization\InvitationAdminController;
use PHPUnit\Framework\TestCase;

/** Back-office › Invitations : saisie de plusieurs adresses, routes et page refaite. */
final class InvitationAdminPageTest extends TestCase
{
    public function testParsesSeveralAddressesWithoutDuplicates(): void
    {
        $p = InvitationAdminController::parseEmails("A@b.fr, c@d.fr;\n<e@f.fr> a@B.fr pas-une-adresse");
        self::assertSame(['a@b.fr', 'c@d.fr', 'e@f.fr'], $p['valid']);
        self::assertSame(['pas-une-adresse'], $p['invalid']);
        self::assertSame(['valid' => [], 'invalid' => []], InvitationAdminController::parseEmails('  '));
    }

    public function testRoutesAndPageWiring(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        foreach (["'/back-office/invitations'", "'/back-office/invitations/envoyees'", "'/back-office/invitations/renvoyer'", "'/back-office/invitations/lien'", "'/back-office/invitations/revoke'"] as $r) {
            self::assertStringContainsString($r, $routes);
        }
        $view = (string) file_get_contents($root . '/views/admin/organization/invitations.php');
        self::assertStringContainsString('back-office/recruitments/liens-invitation', $view);
        self::assertStringContainsString("url('platform/upgrade') . '?from=max_members'", $view);
        self::assertStringContainsString("views/partials/ui/stepper.php", $view);
        self::assertStringContainsString('if ($capFull)', $view, 'message de plafond seulement si le plafond est atteint');
        self::assertFileExists($root . '/public/assets/css/invitations-ath.css');
        self::assertFileExists($root . '/public/assets/js/invitations-admin.js');
        self::assertFileDoesNotExist($root . '/views/admin/organization/invitations_sent.php');
    }
}
