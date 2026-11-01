<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\HtmlTranslator;
use App\Support\I18n\UntranslatedFrenchScanner;
use PHPUnit\Framework\TestCase;

/**
 * Oublis de traduction anglaise : l'outil (tools/i18n/untranslated.php) et la non-régression des vues et scripts
 * traduits. Un texte français ajouté en dur dans une de ces vues doit recevoir sa traduction dans
 * lang/en/site_auto.php (python3 tools/i18n/site_i18n.py merge), ou, s'il ne se traduit pas (nom propre, identique en
 * anglais), une ligne justifiée dans tools/i18n/untranslated_keep.tsv.
 */
final class UntranslatedFrenchScannerTest extends TestCase
{
    /** Vues et scripts traduits, surveillés (dossier = tous ses fichiers). */
    private const WATCHED = [
        'views',
        'public/assets/js',
    ];

    protected function tearDown(): void
    {
        UntranslatedFrenchScanner::useDictionary(null);
        HtmlTranslator::reset();
    }

    public function testFindsHardcodedFrenchButNotTranslationKeysUserContentOrComments(): void
    {
        UntranslatedFrenchScanner::useDictionary(['Fermer' => 'Close', 'Bienvenue, %1 !' => 'Welcome, %1!']);
        $view = <<<'PHP'
            <?php $label = 'Rôle à pourvoir'; $k = __('nav.home'); // commentaire d’essai ?>
            <h1>Bienvenue, <?= e($name) ?> !</h1>
            <button title="Supprimer l’emploi">Fermer</button>
            <span translate="no">Unité saisie</span>
            <script>alert('Texte du script');</script>
            <p>Signature du document obligatoire</p>
            PHP;
        $segments = UntranslatedFrenchScanner::viewSegments($view);
        self::assertContains('Rôle à pourvoir', $segments);
        self::assertContains('Supprimer l’emploi', $segments);
        self::assertContains('Signature du document obligatoire', $segments);
        self::assertContains('Bienvenue, %1 !', $segments);
        self::assertNotContains('nav.home', $segments);
        self::assertNotContains('Unité saisie', $segments);
        self::assertNotContains('Texte du script', $segments);
        foreach ($segments as $s) {
            self::assertStringNotContainsString('commentaire', $s);
        }
        self::assertTrue(UntranslatedFrenchScanner::isTranslated('Bienvenue, %1 !'));
        self::assertFalse(UntranslatedFrenchScanner::isTranslated('Rôle à pourvoir'));
    }

    public function testJsStringsConcatenationsAndCommentsWithApostrophes(): void
    {
        $js = "// l'onglet est visible (toutes les 2 min, au retour sur l'onglet)\n"
            . "var re = /l'id/g;\n"
            . "if (!confirm('Supprimer ' + name + ' de la fonction ?')) { return; }\n"
            . "el.textContent = `Affectation de \${who} enregistrée`;\n"
            . "/* bloc : d'un poste à l'autre */ var x = 'role_id';\n";
        $segments = UntranslatedFrenchScanner::jsSegments($js);
        self::assertContains('Supprimer %1 de la fonction ?', $segments);
        self::assertContains('Affectation de %1 enregistrée', $segments);
        foreach ($segments as $s) {
            self::assertStringNotContainsString('onglet', $s);
            self::assertStringNotContainsString('poste à', $s);
        }
        self::assertNotContains('role_id', $segments);
    }

    public function testAppCodeReportsComposedTextsOnly(): void
    {
        $php = <<<'PHP'
            <?php
            $a = 'Unité fictive : ' . $label;
            $b = "Il reste {$n} jours avant la clôture";
            $c = sprintf('%d membre(s) retiré(s) de « %s ».', $n, $name);
            $d = 'Libellé seul affiché';
            $e = __('site.key_' . $x);
            error_log('Échec de synchro : ' . $err);
            throw new \RuntimeException('Fichier introuvable : ' . $path);
            $sql = 'SELECT nom FROM unités WHERE id = ' . $id;
            $u = url('admin') . '?vue=calendrier#nouveau';
            PHP;
        $segments = UntranslatedFrenchScanner::appSegments($php);
        self::assertContains('Unité fictive : %1', $segments);
        self::assertContains('Il reste %1 jours avant la clôture', $segments);
        self::assertContains('%1 membre(s) retiré(s) de « %2 ».', $segments);
        self::assertNotContains('Libellé seul affiché', $segments, 'littéral seul : extract-app');
        foreach ($segments as $s) {
            self::assertStringNotContainsString('synchro', $s);
            self::assertStringNotContainsString('introuvable', $s);
            self::assertStringNotContainsString('unités', $s);
            self::assertStringNotContainsString('calendrier', $s);
        }
    }

    public function testWatchedViewsAndScriptsHaveNoUntranslatedFrench(): void
    {
        $root = dirname(__DIR__, 2);
        $missing = [];
        foreach (self::WATCHED as $target) {
            $path = $root . '/' . $target;
            $files = is_file($path) ? [$path] : [];
            if (is_dir($path)) {
                $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
                foreach ($it as $f) {
                    $p = str_replace('\\', '/', (string) $f);
                    if (preg_match('~\.min\.js$|/vendor/|i18n_en_dict\.js$~', $p)) {
                        continue;
                    }
                    if (str_ends_with($p, '.php') || str_ends_with($p, '.js')) {
                        $files[] = $p;
                    }
                }
            }
            foreach ($files as $file) {
                foreach (UntranslatedFrenchScanner::missingInFile($file) as $s) {
                    $missing[] = substr(str_replace('\\', '/', $file), strlen($root) + 1) . ' : ' . $s;
                }
            }
        }
        self::assertSame([], array_slice($missing, 0, 40), count($missing) . ' texte(s) français sans traduction anglaise '
            . '(php tools/i18n/untranslated.php) : ajoutez-les à lang/en/site_auto.php ou, justifiés, à '
            . 'tools/i18n/untranslated_keep.tsv.');
    }
}
