<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EnlistmentIdentityFormCleanupTest extends TestCase
{
    public function testGuestFormUsesPrenomNomWithoutLegacyFullNameField(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/enlistment.php');
        self::assertStringContainsString('guest_rp_first_name', $view);
        self::assertStringContainsString('guest_rp_last_name', $view);
        self::assertStringContainsString('Prénom du personnage', $view);
        self::assertStringNotContainsString('id="input-full-name"', $view);
        self::assertStringNotContainsString('guest-rp-detail', $view);
        self::assertStringNotContainsString('optionnel si le champ unique', $view);
        self::assertStringNotContainsString('ce-rp-box', $view);
        self::assertStringNotContainsString('Identité portée par la candidature', $view);
    }

    public function testControllerRequiresCharacterFirstLast(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/EnlistmentController.php');
        self::assertStringContainsString('prénom et le nom du personnage', $src);
        self::assertStringContainsString('guest_rp_first_name', $src);
        self::assertStringContainsString('guest_rp_last_name', $src);
    }

    public function testFieldGridAlignmentCssUsesCeField(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/community-enlistment.css');
        self::assertStringContainsString('min-height: 3rem', $css);
        self::assertStringContainsString('.ce-label-optional', $css);
        self::assertStringContainsString('#ce-identity-meta-grid', $css);
    }
}
