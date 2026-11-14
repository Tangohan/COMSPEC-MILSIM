<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Audit du back-office, lot 7.9 : contrôles statiques de l'interface, sur les vues et scripts du back-office.
 *
 *  - un seul <h1> par page : l'en-tête du shell (views/partials/back_office_page_head.php) porte le titre, une vue
 *    de contenu ne doit pas en ajouter un second ;
 *  - aucun confirm() natif nouveau : la confirmation passe par data-ui-confirm (lot 4.5) ;
 *  - aucun surtitre (kicker, eyebrow, overline) : consigne d'interface, pas de petit libellé au-dessus d'un titre ;
 *  - tout tableau d'une vue a un état vide.
 *
 * L'existant est figé dans tests/fixtures/audit-backoffice/ui-baseline.json (fichier => nombre). Le test échoue quand
 * un fichier fait pire que sa ligne, ou qu'un fichier absent de la liste apparaît ; il ne dit rien d'une amélioration.
 * Pour réécrire la liste après un nettoyage :  UPDATE_UI_BASELINE=1 php vendor/bin/phpunit tests/Unit/BackOfficeUiStaticChecksTest.php
 */
final class BackOfficeUiStaticChecksTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    /** Vues de contenu du back-office de communauté et de la plateforme. */
    private const VIEW_DIRS = ['views/admin', 'views/back_office'];

    /** Pages de la plateforme (/admin) : pas d'en-tête de shell, elles portent leur propre titre. */
    private const PLATFORM_VIEW_DIR = 'views/admin/system/';

    private const KICKER_CLASS = '/(?:class|id)="[^"]*\b(?:eyebrow|kicker|overline|surtitre)\b/i';

    private const NATIVE_CONFIRM = '/(?<![A-Za-z0-9_.$-])(?:window\.)?confirm\(/';

    private const EMPTY_STATE = '/(Aucun|aucun|empty-state|ath-empty|__empty|empty\(|=== \[\]|count\([^)]*\) (?:===|==) 0|=== 0)/';

    /** @return list<string> chemins relatifs à la racine, avec des « / » */
    private static function files(array $dirs, string $extension): array
    {
        $out = [];
        foreach ($dirs as $dir) {
            $base = self::ROOT . '/' . $dir;
            if (!is_dir($base)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if ($file->isFile() && $file->getExtension() === $extension) {
                    $out[] = $dir . substr(str_replace('\\', '/', $file->getPathname()), strlen(str_replace('\\', '/', $base)));
                }
            }
        }
        sort($out);

        return $out;
    }

    private static function read(string $relative): string
    {
        return (string) file_get_contents(self::ROOT . '/' . $relative);
    }

    /** @return list<string> scripts du back-office : ceux dont le nom l'indique */
    private static function scripts(): array
    {
        return array_values(array_filter(
            self::files(['public/assets/js'], 'js'),
            static fn (string $f): bool => preg_match('#/(back-office|bo-|eff-|effectifs)#', $f) === 1
        ));
    }

    /** @return array{h1: array<string,int>, confirm: array<string,int>, kicker: array<string,int>, no_empty_state: list<string>} */
    private static function scan(): array
    {
        $views = self::files(self::VIEW_DIRS, 'php');
        $views[] = 'views/partials/back_office_sidebar.php';
        $views[] = 'views/partials/ath_sidebar_nav.php';
        $views[] = 'views/partials/back_office_topbar.php';
        $views[] = 'views/partials/back_office_page_head.php';
        $result = ['h1' => [], 'confirm' => [], 'kicker' => [], 'no_empty_state' => []];
        foreach ($views as $file) {
            $src = self::read($file);
            if (!str_starts_with($file, self::PLATFORM_VIEW_DIR) && !str_starts_with($file, 'views/partials/') && ($n = preg_match_all('/<h1\b/', $src)) > 0) {
                $result['h1'][$file] = $n;
            }
            if (($n = preg_match_all(self::NATIVE_CONFIRM, $src)) > 0) {
                $result['confirm'][$file] = $n;
            }
            if (($n = preg_match_all(self::KICKER_CLASS, $src)) > 0) {
                $result['kicker'][$file] = $n;
            }
            if (str_contains($src, '<table') && preg_match(self::EMPTY_STATE, $src) !== 1) {
                $result['no_empty_state'][] = $file;
            }
        }
        foreach (self::scripts() as $file) {
            if (($n = preg_match_all(self::NATIVE_CONFIRM, self::read($file))) > 0) {
                $result['confirm'][$file] = $n;
            }
        }
        foreach ($result as &$part) {
            ksort($part);
        }

        return $result;
    }

    private static function baselineFile(): string
    {
        return self::ROOT . '/tests/fixtures/audit-backoffice/ui-baseline.json';
    }

    /** @return array{h1: array<string,int>, confirm: array<string,int>, kicker: array<string,int>, no_empty_state: list<string>} */
    private static function baseline(): array
    {
        $scan = self::scan();
        if (getenv('UPDATE_UI_BASELINE') === '1') {
            if (!is_dir(dirname(self::baselineFile()))) {
                mkdir(dirname(self::baselineFile()), 0775, true);
            }
            file_put_contents(self::baselineFile(), json_encode($scan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

            return $scan;
        }
        $data = json_decode((string) file_get_contents(self::baselineFile()), true);
        self::assertIsArray($data, 'ui-baseline.json absent ou illisible (UPDATE_UI_BASELINE=1 pour le créer)');

        return $data;
    }

    /**
     * @param array<string,int> $now
     * @param array<string,int> $known
     * @return list<string>
     */
    private static function worse(array $now, array $known): array
    {
        $bad = [];
        foreach ($now as $file => $n) {
            if ($n > ($known[$file] ?? 0)) {
                $bad[] = $file . ' : ' . $n . ' (liste : ' . ($known[$file] ?? 0) . ')';
            }
        }

        return $bad;
    }

    public function testNoNewNativeConfirm(): void
    {
        $known = self::baseline()['confirm'];
        self::assertGreaterThan(50, array_sum($known), 'la liste de l’existant doit recenser les confirm() natifs actuels');
        self::assertSame(
            [],
            self::worse(self::scan()['confirm'], $known),
            'confirm() natif nouveau dans le back-office : utiliser data-ui-confirm (composant de confirmation, lot 4.5).'
        );
    }

    public function testNoNewOwnH1InBackOfficeViews(): void
    {
        $known = self::baseline()['h1'];
        self::assertSame(
            [],
            self::worse(self::scan()['h1'], $known),
            'Un <h1> de plus dans une vue du back-office : le titre de la page vient de l’en-tête du shell (back_office_page_head.php).'
        );
    }

    public function testNoViewHasTwoH1(): void
    {
        $double = array_filter(self::scan()['h1'], static fn (int $n): bool => $n > 1);
        $known = array_filter(self::baseline()['h1'], static fn (int $n): bool => $n > 1);
        self::assertSame([], array_diff_key($double, $known), 'Une vue de plus avec deux <h1>.');
    }

    public function testNoNewKicker(): void
    {
        self::assertSame(
            [],
            self::worse(self::scan()['kicker'], self::baseline()['kicker']),
            'Surtitre (eyebrow, kicker, overline) nouveau : pas de petit libellé au-dessus d’un titre dans le back-office.'
        );
    }

    public function testPageHeadHasNoKickerAndRendersOneTitle(): void
    {
        $head = self::read('views/partials/back_office_page_head.php');
        self::assertSame(1, substr_count($head, '<h1'), 'l’en-tête du shell porte l’unique <h1>');
        self::assertStringNotContainsString('__kicker', $head);
        self::assertSame(1, substr_count(self::read('views/layout/main.php'), 'back_office_page_head.php'), 'le shell inclut l’en-tête une seule fois');
        self::assertStringNotContainsString("'kicker'", self::read('config/back_office_pages.php'), 'plus de surtitre dans les métadonnées des pages');
    }

    public function testEveryTableViewKeepsAnEmptyState(): void
    {
        $known = self::baseline()['no_empty_state'];
        self::assertSame(
            [],
            array_values(array_diff(self::scan()['no_empty_state'], $known)),
            'Vue avec un tableau et aucun état vide : prévoir « Aucun … » quand la liste est vide.'
        );
    }
}
