<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Le masquage de l'e-mail « à l'affichage » n'avait aucun effet réel (la fiche du dossier affichait l'adresse
 * complète à côté du masque, les listes et les messages au staff l'affichent en clair) : l'option est retirée, l'e-mail
 * du compte est transmis à l'équipe et le consentement le dit.
 */
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

    public function testNoCandidateFormOffersTheMaskOption(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['views/enlistment.php', 'views/enlistment/simple.php', 'views/enlistment/discord.php', 'views/enlistment/_account_identity.php', 'views/enlistment/_account_consent.php'] as $file) {
            $view = (string) file_get_contents($root . '/' . $file);
            self::assertStringNotContainsString('name="mask_email"', $view, $file);
            self::assertStringNotContainsString('name="share_email"', $view, $file);
        }
    }

    public function testConsentNamesTheCommunityAndTheEmailTransmission(): void
    {
        $consent = (string) file_get_contents(dirname(__DIR__, 2) . '/views/enlistment/_account_consent.php');

        self::assertStringContainsString('l’e-mail de mon compte', $consent);
        self::assertStringContainsString('$tenantName', $consent);
        self::assertStringContainsString('name="consent_data_sharing"', $consent);
    }

    public function testControllerNoLongerReadsOrStoresTheMaskPreference(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/EnlistmentController.php');

        self::assertStringNotContainsString("input('mask_email')", $controller);
        self::assertStringNotContainsString("'mask_email' => \$maskEmail", $controller);
        self::assertStringContainsString("'share_email' => true", $controller);
    }

    public function testStaffDossierStillReadsTheMaskOfOlderApplications(): void
    {
        $show = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/recruitments/show.php');

        self::assertStringContainsString("sharedFields['mask_email']", $show);
    }
}
