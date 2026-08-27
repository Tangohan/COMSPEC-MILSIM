<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\FlashAlertTitle;
use PHPUnit\Framework\TestCase;

final class FlashAlertTitleTest extends TestCase
{
    public function testAuthRequiredIsConnexionNotAccesRefuse(): void
    {
        self::assertSame('Connexion requise', FlashAlertTitle::for('error', 'Connectez-vous pour continuer.'));
        self::assertSame('Connexion requise', FlashAlertTitle::for('error', 'Authentification requise.'));
    }

    public function testSessionExpiredTitle(): void
    {
        self::assertSame('Session expirée', FlashAlertTitle::for('error', 'Session expirée. Merci de réessayer.'));
        self::assertSame('Session expirée', FlashAlertTitle::for('error', 'Session invalide. Merci de vous reconnecter.'));
    }

    public function testRealAccessDeniedKeepsAccesRefuse(): void
    {
        self::assertSame(
            'Accès refusé',
            FlashAlertTitle::for('error', 'Cette zone est réservée aux personnes habilitées à administrer la communauté. Si vous pensez devoir y accéder, contactez un administrateur.')
        );
        self::assertSame(
            'Accès refusé',
            FlashAlertTitle::for('error', 'Votre accès à cette page est restreint par les règles de sécurité de la communauté.')
        );
    }

    public function testMissingResourceTitle(): void
    {
        self::assertSame(
            'Introuvable',
            FlashAlertTitle::for('error', 'Cette fiche personnel est introuvable ou n’est plus accessible dans votre communauté.')
        );
    }

    public function testAccountInaccessibleTitle(): void
    {
        self::assertSame(
            'Compte inaccessible',
            FlashAlertTitle::for('error', 'Ce compte n’existe plus ou la session est invalide.')
        );
        self::assertSame(
            'Compte inaccessible',
            FlashAlertTitle::for('error', 'Ce compte n’est plus actif.')
        );
    }

    public function testEmailConfirmationStillSpecialCased(): void
    {
        self::assertSame(
            'Confirmation requise',
            FlashAlertTitle::for('error', 'Confirmez votre adresse e-mail avant de vous connecter.')
        );
    }

    public function testNonErrorVariants(): void
    {
        self::assertSame('Succès', FlashAlertTitle::for('success', 'Enregistré.'));
        self::assertSame('Attention', FlashAlertTitle::for('warning', 'Attention.'));
        self::assertSame('Information', FlashAlertTitle::for('info', 'Info.'));
        self::assertSame('Erreur', FlashAlertTitle::for('error', 'Quelque chose d’imprévu.'));
    }
}
