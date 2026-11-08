<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EnlistmentIdentityFormCleanupTest extends TestCase
{
    public function testFormNoLongerAsksForWhatTheAccountAlreadyKnows(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/enlistment.php');
        self::assertStringNotContainsString('guest_rp_first_name', $view);
        self::assertStringNotContainsString('Prénom du personnage', $view);
        self::assertStringNotContainsString('id="input-full-name"', $view);
        self::assertStringNotContainsString('id="enlist-age"', $view);
        self::assertStringNotContainsString('id="input-email"', $view);
        self::assertStringContainsString('_account_identity.php', $view);

        $identity = (string) file_get_contents(dirname(__DIR__, 2) . '/views/enlistment/_account_identity.php');
        self::assertStringContainsString('Vous candidatez en tant que', $identity);
        self::assertStringContainsString('Voir mon profil', $identity);
    }

    public function testControllerTakesIdentityFromTheAccount(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/EnlistmentController.php');
        self::assertStringContainsString('CandidateAccountIdentity::fromAccount', $src);
        self::assertStringNotContainsString("input('guest_rp_first_name')", $src);
        self::assertStringNotContainsString("input('full_name')", $src);
        self::assertStringNotContainsString("input('email')", $src);
    }

    public function testFieldLabelsStayGluedToTheirFields(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/community-enlistment.css');
        self::assertStringNotContainsString('min-height: 2.75rem;\n  display: flex;\n  align-items: flex-end', $css);
        self::assertStringContainsString('.ce-label-optional', $css);
    }
}
