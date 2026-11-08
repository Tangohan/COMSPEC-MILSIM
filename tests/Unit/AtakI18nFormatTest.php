<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Dictionnaires du mod, lus par parseSimpleArray (fn_langApply) : une seule faute et tout le reste du fichier était perdu en jeu.
 * Après la 4.0.0, une virgule oubliée dans en.txt coupait le dictionnaire anglais à la ligne 19 785, sans rien dire en CI.
 */
final class AtakI18nFormatTest extends TestCase
{
    private const MOD = __DIR__ . '/../../mod/COMSPEC_ATAK_Native';

    /** @return array<string, array{string}> */
    public static function dictionnaires(): array
    {
        $out = [];
        foreach (glob(self::MOD . '/Sources/addons/main/i18n/*.txt') ?: [] as $f) {
            $out[basename($f)] = [$f];
        }
        $out['template xx.txt'] = [self::MOD . '/localisation/template/xx.txt'];

        return $out;
    }

    #[DataProvider('dictionnaires')]
    public function testFormatStrict(string $path): void
    {
        $src = (string) file_get_contents($path);
        $src = (string) preg_replace('/^\xEF\xBB\xBF/', '', $src);
        $lines = preg_split('/\r?\n/', rtrim($src)) ?: [];
        self::assertGreaterThan(2, count($lines));
        self::assertSame('[', $lines[0], 'première ligne');
        self::assertSame(']', $lines[count($lines) - 1], 'dernière ligne');

        $last = count($lines) - 2;
        $vues = [];
        for ($i = 1; $i <= $last; $i++) {
            $n = $i + 1;
            self::assertSame(
                1,
                preg_match('/^\[("(?:[^"]|"")*")(?:, "(?:[^"]|"")*"){1,2}\](,?)$/', $lines[$i], $m),
                "ligne {$n} mal formée : " . substr($lines[$i], 0, 80)
            );
            self::assertSame($i === $last ? '' : ',', $m[2], "ligne {$n} : virgule de fin " . ($i === $last ? 'en trop' : 'manquante'));
            // Clé en double : admise si la traduction est la même, sinon la dernière écrase la première sans rien dire.
            $key = $m[1];
            if (isset($vues[$key])) {
                self::assertSame($vues[$key], rtrim($lines[$i], ','), "ligne {$n} : clé en double, autre traduction");
            }
            $vues[$key] = rtrim($lines[$i], ',');
        }
    }

    public function testUneLigneInvalideNePerdPasLeDictionnaire(): void
    {
        $sqf = (string) file_get_contents(self::MOD . '/Sources/addons/main/functions/ui/fn_langApply.sqf');
        self::assertStringContainsString('private _parseLang = {', $sqf);
        self::assertStringContainsString('splitString (toString [13, 10])', $sqf);
        self::assertStringNotContainsString('else { parseSimpleArray _raw }', $sqf);
    }
}
