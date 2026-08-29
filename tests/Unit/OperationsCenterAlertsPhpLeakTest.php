<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class OperationsCenterAlertsPhpLeakTest extends TestCase
{
    public function testAlertesLocalesBlockStaysInsidePhp(): void
    {
        $path = dirname(__DIR__, 2) . '/views/admin/organization/operations_center.php';
        $src = (string) file_get_contents($path);

        self::assertStringContainsString('Alertes locales actives', $src);
        self::assertStringContainsString('// ---- Alertes locales ----', $src);

        // Bug historisé : PHP fermé puis source brut « Alertes locales » affiché en HTML.
        $closeThenComment = '/' . preg_quote('?' . '>', '/') . '\s*\/\/\s*----\s*Alertes locales/';
        self::assertDoesNotMatchRegularExpression(
            $closeThenComment,
            $src,
            'Le bloc Alertes locales ne doit pas suivre une fermeture PHP'
        );

        // Le commentaire métier doit être immédiatement précédé d'un endif (même bloc PHP).
        self::assertMatchesRegularExpression(
            '/endif;\s*\/\/ ---- Alertes locales ----/',
            $src
        );
    }
}
