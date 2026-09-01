<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Gate;
use App\Support\EmailPrivacy;
use PHPUnit\Framework\TestCase;

final class EmailPrivacyTest extends TestCase
{
    protected function tearDown(): void
    {
        Gate::getInstance()->setPermissions([]);
        parent::tearDown();
    }

    public function testMaskKeepsDomainAndStarsLocalPart(): void
    {
        self::assertSame('je***@exemple.fr', EmailPrivacy::mask('jean.dupont@exemple.fr'));
        self::assertSame('ab***@x.io', EmailPrivacy::mask('ab@x.io'));
        self::assertSame('a***@x.io', EmailPrivacy::mask('a@x.io'));
        self::assertSame('—', EmailPrivacy::mask(''));
        self::assertSame('je***@exemple.fr', EmailPrivacy::mask('je***@exemple.fr'));
    }

    public function testDisplayRevealsOnlyForSiteAdmin(): void
    {
        Gate::getInstance()->setPermissions(['admin.organization']);
        self::assertSame('je***@exemple.fr', EmailPrivacy::display('jean.dupont@exemple.fr'));

        Gate::getInstance()->setPermissions(['admin.system']);
        self::assertSame('jean.dupont@exemple.fr', EmailPrivacy::display('jean.dupont@exemple.fr'));
    }

    public function testMaskViewDataHidesOtherMembersButNotFormPrefill(): void
    {
        Gate::getInstance()->setPermissions(['admin.organization']);
        $data = EmailPrivacy::maskViewData([
            'content' => 'admin.organization.users.show',
            'email' => 'invite@exemple.fr',
            'user' => [
                'id' => 12,
                'display_name' => 'Jean',
                'email' => 'jean.dupont@exemple.fr',
                'status' => 'active',
            ],
            'boPageSubtitle' => 'jean.dupont@exemple.fr',
        ]);

        self::assertSame('admin.organization.users.show', $data['content']);
        self::assertSame('invite@exemple.fr', $data['email']);
        self::assertSame('je***@exemple.fr', $data['user']['email']);
        self::assertSame('je***@exemple.fr', $data['boPageSubtitle']);
    }

    public function testPersonnelFileEmailIsSiteAdminOnly(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/PersonnelController.php');
        self::assertStringContainsString("allows('admin.system')", $controller);
        self::assertStringNotContainsString("|| \$gateInst->allows('admin.organization')", $controller);

        $edit = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/organization/users/edit.php');
        self::assertStringContainsString('viewer_can_see_emails', $edit);
        self::assertStringContainsString('administration du site', $edit);

        $response = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Core/Response.php');
        self::assertStringContainsString('EmailPrivacy::maskViewData', $response);
    }
}
