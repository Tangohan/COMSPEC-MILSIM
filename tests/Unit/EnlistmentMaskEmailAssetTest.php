<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EnlistmentMaskEmailAssetTest extends TestCase
{
    public function testMaskEmailHelperUsesStars(): void
    {
        require_once dirname(__DIR__, 2) . '/app/Support/helpers.php';

        self::assertSame('je***@exemple.fr', mask_email_for_display('jean.dupont@exemple.fr'));
        self::assertSame('ab***@x.io', mask_email_for_display('ab@x.io'));
        self::assertSame('a***@x.io', mask_email_for_display('a@x.io'));
        self::assertSame('—', mask_email_for_display(''));
    }

    public function testEnlistmentFormOffersMaskInsteadOfOptionalShare(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/enlistment.php');

        self::assertStringContainsString('name="mask_email"', $view);
        self::assertStringContainsString('Masquer mon', $view);
        self::assertStringContainsString('toujours transmis au staff', $view);
        self::assertStringNotContainsString('name="share_email"', $view);
    }

    public function testSimpleEnlistmentFormOffersMaskWithoutRequiredShare(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/enlistment/simple.php');

        self::assertStringContainsString('name="mask_email"', $view);
        self::assertStringContainsString('Masquer mon', $view);
        self::assertStringNotContainsString('name="share_email"', $view);
        self::assertStringNotContainsString('share_email" value="1" checked class="rounded border-slate-300" x-bind:disabled="flow !== \'account\'" required', $view);
    }

    public function testAccountFlowAlwaysStoresEmailAndMaskPreference(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/EnlistmentController.php');

        self::assertStringContainsString("input('mask_email')", $controller);
        self::assertStringContainsString("'mask_email' => \$maskEmail", $controller);
        self::assertStringContainsString("'share_email' => true", $controller);
        self::assertStringNotContainsString('Une adresse email de contact est requise (partage email)', $controller);
        self::assertStringNotContainsString('$email = $shareEmail ? trim((string) ($user[\'email\'] ?? \'\')) : \'\'', $controller);
    }
}
