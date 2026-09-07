<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PersonnelDossierPortraitAssetTest extends TestCase
{
    public function testDossierEditHostsOperatorPortraitUploadAndFileLinksToIt(): void
    {
        $root = dirname(__DIR__, 2);
        $edit = (string) file_get_contents($root . '/views/personnel/edit.php');
        $file = (string) file_get_contents($root . '/views/personnel/file.php');
        $controller = (string) file_get_contents($root . '/app/Controllers/Web/PersonnelController.php');
        $routes = (string) file_get_contents($root . '/routes/web.php');

        self::assertStringContainsString("'id' => 'edit-portrait'", $edit);
        self::assertStringContainsString('id="edit-portrait"', $edit);
        self::assertStringContainsString("url('personnel/' . (int) (\$targetUser['id'] ?? 0) . '/portrait')", $edit);
        self::assertStringContainsString('enctype="multipart/form-data"', $edit);
        self::assertStringContainsString('name="portrait"', $edit);
        self::assertStringContainsString('Portrait opérateur', $edit);
        self::assertStringNotContainsString("url('account/portrait')", $edit);

        self::assertStringContainsString('#edit-portrait', $file);
        self::assertStringNotContainsString("url('account/image')", $file);
        self::assertStringNotContainsString('Photo de compte', $file);

        self::assertStringContainsString('function updatePortrait', $controller);
        self::assertStringContainsString("updatePortraitPath(\$uid, 'uploads/portraits/' . \$name)", $controller);
        self::assertStringContainsString("/personnel/{id}/portrait", $routes);
        self::assertStringContainsString("'updatePortrait'", $routes);
    }
}
