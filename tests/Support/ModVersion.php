<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\Assert;

/**
 * Versions du mod dans les tests d'assets.
 *
 * Un test qui vérifie « le pack a bien été bumpé » doit exiger une version
 * minimale, pas la version exacte du jour : sinon chaque release suivante le
 * casse sans que rien ne soit cassé.
 */
final class ModVersion
{
    /** versionStr = "x.y.z" d'un config.cpp Arma (CfgPatches). */
    public static function fromConfigCpp(string $configCpp): string
    {
        if (preg_match('/versionStr\s*=\s*"([0-9][0-9.]*)"/', $configCpp, $m) !== 1) {
            Assert::fail('versionStr introuvable dans le config.cpp.');
        }

        return $m[1];
    }

    /** Première capture de $pattern dans $source (la version). */
    public static function match(string $pattern, string $source): string
    {
        if (preg_match($pattern, $source, $m) !== 1 || !isset($m[1])) {
            Assert::fail('Version introuvable avec ' . $pattern . '.');
        }

        return $m[1];
    }

    public static function assertAtLeast(string $minimum, string $actual, string $message = ''): void
    {
        Assert::assertTrue(
            version_compare($actual, $minimum, '>='),
            ($message !== '' ? $message . ' ' : '') . "Version $actual attendue >= $minimum."
        );
    }
}
