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

        // Bug historisé : PHP fermé puis source brut « Alertes locales » affiché en HTML.
        $closeThenComment = '/' . preg_quote('?' . '>', '/') . '\s*\/\/\s*----\s*Alertes locales/';
        self::assertDoesNotMatchRegularExpression(
            $closeThenComment,
            $src,
            'Le bloc Alertes locales ne doit pas suivre une fermeture PHP'
        );

        $pos = strpos($src, '// ---- Alertes locales ----');
        self::assertNotFalse($pos);
        $before = substr($src, 0, $pos);
        $open = substr_count($before, '<' . '?php');
        $close = substr_count($before, '?' . '>');
        self::assertGreaterThan($close, $open, 'Le bloc Alertes locales doit rester dans un contexte PHP ouvert');
    }
}
