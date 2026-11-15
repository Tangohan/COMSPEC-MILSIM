<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Poids et vitesse d'affichage côté navigateur : contrôles statiques sur les gabarits.
 *
 *  - aucun <script src> bloquant nouveau (sans defer, async ni type="module") dans un gabarit ;
 *  - aucune image nouvelle sans loading="lazy" ni fetchpriority dans un gabarit ;
 *  - aucune feuille ou script local nouveau hors asset_url() (version du déploiement, donc cache fiable) ;
 *  - polices Google : une seule feuille dans le gabarit principal, display=swap et preconnect partout ;
 *  - le mode nuit du back-office ne bloque plus l'affichage du mode jour ;
 *  - aucune image de plus de 300 Ko nouvellement nommée par le code.
 *
 * L'existant est figé dans tests/fixtures/perf-front/baseline.json (fichier => nombre). Le test échoue quand un
 * fichier fait pire que sa ligne ou qu'un fichier absent de la liste apparaît ; il ne dit rien d'une amélioration.
 * Pour réécrire la liste après un nettoyage :  UPDATE_PERF_BASELINE=1 php vendor/bin/phpunit tests/Unit/PerfVagueFrontTest.php
 */
final class PerfVagueFrontTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    /** Gabarits : coques de page, fragments partagés, pages publiques d'entrée. */
    private const TEMPLATE_DIRS = ['views/layout', 'views/partials', 'views/auth', 'views/home'];

    private const TEMPLATE_FILES = [
        'views/jnet/_page.php',
        'views/admin/effectifs_workspace/partials/effectifs_lms_head.php',
    ];

    private const SCRIPT_TAG = '/<script\b[^>]*\bsrc\s*=[^>]*>/i';

    private const NON_BLOCKING = '/\b(?:defer|async)\b|type\s*=\s*["\']module["\']/i';

    /** Référence locale à un CSS ou JS écrite à la main : « ?>/assets/css/x.css », sans asset_url(). */
    private const UNVERSIONED_ASSET = '#\?>/assets/(?:css|js)/[A-Za-z0-9_./-]+\.(?:css|js)\b#';

    private const CODE_IMAGE = '#assets/(?:images|img)/[A-Za-z0-9_./-]+\.(?:jpe?g|png|gif|webp)#i';

    private const HEAVY_IMAGE_BYTES = 300 * 1024;

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

    /** Source dont les « <?= … ?> » sont neutralisés : leur « > » ne doit pas clore la balise HTML qui les contient. */
    private static function markup(string $relative): string
    {
        return (string) preg_replace('/<\?=.*?\?>/s', 'PHP', self::read($relative));
    }

    /** @return list<string> */
    private static function templates(): array
    {
        $files = array_merge(self::files(self::TEMPLATE_DIRS, 'php'), self::TEMPLATE_FILES);
        sort($files);

        return $files;
    }

    /**
     * @return array{blocking_scripts: array<string,int>, eager_images: array<string,int>, unversioned_assets: array<string,int>, heavy_images: array<string,int>}
     */
    private static function scan(): array
    {
        $result = ['blocking_scripts' => [], 'eager_images' => [], 'unversioned_assets' => [], 'heavy_images' => []];
        foreach (self::templates() as $file) {
            $src = self::markup($file);
            preg_match_all(self::SCRIPT_TAG, $src, $scripts);
            $blocking = count(array_filter($scripts[0], static fn (string $tag): bool => preg_match(self::NON_BLOCKING, $tag) !== 1));
            if ($blocking > 0) {
                $result['blocking_scripts'][$file] = $blocking;
            }
            preg_match_all('/<img\b[^>]*>/is', $src, $images);
            $eager = count(array_filter($images[0], static fn (string $tag): bool => preg_match('/\b(?:loading|fetchpriority)\s*=/i', $tag) !== 1));
            if ($eager > 0) {
                $result['eager_images'][$file] = $eager;
            }
            if (($n = preg_match_all(self::UNVERSIONED_ASSET, self::read($file))) > 0) {
                $result['unversioned_assets'][$file] = $n;
            }
        }
        $code = array_merge(
            self::files(['views', 'app'], 'php'),
            self::files(['public/assets/css'], 'css'),
            array_values(array_filter(self::files(['public/assets/js'], 'js'), static fn (string $f): bool => !str_contains($f, '/vendor/')))
        );
        foreach ($code as $file) {
            if (preg_match_all(self::CODE_IMAGE, self::read($file), $m) === 0) {
                continue;
            }
            foreach (array_unique($m[0]) as $image) {
                $path = self::ROOT . '/public/' . $image;
                if (is_file($path) && filesize($path) > self::HEAVY_IMAGE_BYTES) {
                    $result['heavy_images'][$image] = (int) filesize($path);
                }
            }
        }
        foreach ($result as &$part) {
            ksort($part);
        }

        return $result;
    }

    private static function baselineFile(): string
    {
        return self::ROOT . '/tests/fixtures/perf-front/baseline.json';
    }

    /** @return array<string, array<string,int>> */
    private static function baseline(): array
    {
        if (getenv('UPDATE_PERF_BASELINE') === '1') {
            $scan = self::scan();
            if (!is_dir(dirname(self::baselineFile()))) {
                mkdir(dirname(self::baselineFile()), 0775, true);
            }
            file_put_contents(self::baselineFile(), json_encode($scan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

            return $scan;
        }
        $data = json_decode((string) file_get_contents(self::baselineFile()), true);
        self::assertIsArray($data, 'baseline.json absent ou illisible (UPDATE_PERF_BASELINE=1 pour le créer)');

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

    public function testNoNewBlockingScriptInTemplates(): void
    {
        self::assertSame(
            [],
            self::worse(self::scan()['blocking_scripts'], self::baseline()['blocking_scripts']),
            'Un <script src> bloquant de plus dans un gabarit : ajouter defer (ou async), après avoir vérifié les scripts en ligne qui en dépendent.'
        );
    }

    public function testMainLayoutLoadsNoBlockingScript(): void
    {
        $scan = self::scan()['blocking_scripts'];
        foreach (['views/layout/main.php', 'views/layout/marketing.php', 'views/layout/legal.php', 'views/partials/auth/head.php', 'views/home/index.php', 'views/jnet/_page.php'] as $file) {
            self::assertArrayNotHasKey($file, $scan, $file . ' ne doit charger aucun script bloquant.');
        }
        $layout = self::read('views/layout/main.php');
        self::assertStringContainsString('<script defer src="<?= htmlspecialchars(asset_url(\'assets/js/img-fallback.js\')', $layout);
        // Le repli reste correct en différé : au démarrage, bind() traite les images déjà en échec.
        $fallback = self::read('public/assets/js/img-fallback.js');
        self::assertStringContainsString('img.complete', $fallback);
        self::assertStringContainsString('naturalWidth === 0', $fallback);
        self::assertStringContainsString("document.addEventListener('error', onError, true)", $fallback);
    }

    public function testNoNewEagerImageInTemplates(): void
    {
        self::assertSame(
            [],
            self::worse(self::scan()['eager_images'], self::baseline()['eager_images']),
            'Une image de plus sans loading="lazy" dans un gabarit (ou fetchpriority="high" si elle est en haut de page).'
        );
    }

    public function testNoNewUnversionedAssetInTemplates(): void
    {
        $scan = self::scan()['unversioned_assets'];
        self::assertSame(
            [],
            self::worse($scan, self::baseline()['unversioned_assets']),
            'CSS ou JS local référencé sans asset_url() : sans la version du déploiement, le cache de 7 jours sert l’ancien fichier.'
        );
        foreach (self::files(['views/layout'], 'php') as $file) {
            self::assertArrayNotHasKey($file, $scan, $file . ' : passer par asset_url().');
        }
    }

    public function testGoogleFontsAreOneRequestWithSwapAndPreconnect(): void
    {
        $layout = self::read('views/layout/main.php');
        self::assertSame(1, substr_count($layout, 'fonts.googleapis.com/css2'), 'Gabarit principal : une seule feuille Google Fonts (les familles s’ajoutent à $gfFamilies).');
        self::assertStringContainsString("implode('&family=', \$gfFamilies)", $layout);
        foreach (['Inter:wght@400;500;600;700;900', 'Archivo:wght@400;500;600;700;800;900', 'JetBrains+Mono:wght@400;500;700', 'Manrope:wght@400;500;600;700;800', 'Space+Grotesk:wght@500;600;700', 'Barlow+Semi+Condensed:wght@600;700', 'IBM+Plex+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400', 'Libre+Baskerville:ital,wght@0,400;0,700;1,400', 'Pinyon+Script'] as $family) {
            self::assertStringContainsString("'" . $family . "'", $layout, 'famille ou graisses retirées : ' . $family);
        }
        foreach (self::templates() as $file) {
            $src = self::markup($file);
            if (preg_match_all('#<link\b[^>]*fonts\.googleapis\.com/css2[^>]*>#', $src, $links) === 0) {
                continue;
            }
            foreach ($links[0] as $link) {
                self::assertStringContainsString('display=swap', $link, $file . ' : display=swap manquant.');
            }
            if (str_starts_with($file, 'views/layout/') && $file !== 'views/layout/leave.php') {
                self::assertStringContainsString('<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>', $src, $file);
            }
        }
    }

    public function testDarkModeSheetDoesNotBlockLightMode(): void
    {
        $layout = self::read('views/layout/main.php');
        self::assertStringContainsString('back-office-dark.generated.css', $layout);
        self::assertStringContainsString('data-bo-dark-css', $layout);
        self::assertStringContainsString("(dark ? '' : ' media=\"print\"')", $layout);
        self::assertStringContainsString("attributeFilter: ['data-bo-theme']", $layout);
        self::assertStringContainsString("asset_url('assets/css/back-office-dark.css')", $layout, 'back-office-dark.css porte l’interrupteur de thème : chargement normal.');

        // Condition de la manœuvre : la feuille générée n'a aucun effet hors html[data-bo-theme="dark"].
        $css = (string) preg_replace('#/\*.*?\*/#s', '', self::read('public/assets/css/back-office-dark.generated.css'));
        preg_match_all('/(?:^|[{}])\s*([^{}@]+?)\s*\{/', $css, $m);
        self::assertGreaterThan(1000, count($m[1]));
        $unscoped = array_values(array_filter($m[1], static fn (string $selector): bool => !str_starts_with(trim($selector), 'html[data-bo-theme="dark"]')));
        self::assertSame([], array_slice($unscoped, 0, 5), 'Règle sans préfixe html[data-bo-theme="dark"] dans la feuille générée : elle ne peut plus être différée.');
    }

    public function testNoNewHeavyImageNamedByCode(): void
    {
        self::assertSame(
            [],
            array_keys(array_diff_key(self::scan()['heavy_images'], self::baseline()['heavy_images'])),
            'Image de plus de 300 Ko nommée par le code : fournir une variante légère (public/assets/images/opt, srcset ou image-set).'
        );
    }

    public function testLightVariantsExistAndAreWired(): void
    {
        $variants = [
            'assets/images/opt/fog-team-960.webp' => 'views/partials/auth/visual.php',
            'assets/images/opt/fog-team-2048.webp' => 'views/partials/auth/visual.php',
            'assets/images/opt/academy-hero-960.webp' => 'views/academy/home_body.php',
            'assets/images/opt/academy-hero-1818.webp' => 'views/academy/home_body.php',
            'assets/images/opt/formation-de-specialite-1600.jpg' => 'app/Support/helpers.php',
            'assets/img/atak-link-lost-icon-96.png' => 'public/assets/js/atak-roleplay-effects.js',
            'assets/img/atak-fx/broken-screen.webp' => 'public/assets/css/atak.css',
            'assets/img/atak-fx/glitch-hud.webp' => 'public/assets/css/atak.css',
        ];
        foreach ($variants as $image => $user) {
            self::assertFileExists(self::ROOT . '/public/' . $image);
            self::assertLessThan(450 * 1024, filesize(self::ROOT . '/public/' . $image), $image);
            self::assertStringContainsString($image, self::read($user), $user);
        }
        // Les originaux restent en place et en repli : ils peuvent être nommés depuis la base.
        foreach (['assets/images/fog-team.jpg', 'assets/images/academy-hero.jpg', 'assets/images/formation-de-specialite.jpg', 'assets/img/atak-link-lost-icon.png', 'assets/img/atak-fx/broken-screen.png', 'assets/img/atak-fx/glitch-hud.png'] as $original) {
            self::assertFileExists(self::ROOT . '/public/' . $original);
        }
        self::assertStringContainsString("asset_url('assets/images/fog-team.jpg')", self::read('views/partials/auth/visual.php'));
        self::assertStringContainsString("url('/assets/img/atak-fx/broken-screen.png')", self::read('public/assets/css/atak.css'));
    }

    public function testStudioImageLibraryUsesThumbnails(): void
    {
        $view = self::read('views/admin/training/studio_edit.php');
        self::assertStringContainsString("'assets/images/_thumbs/' . sha1(basename(\$sip)) . '.webp'", $view);
        self::assertStringContainsString('data-pres-lib-url="<?= htmlspecialchars($siu) ?>"', $view, 'le choix garde l’original');
        self::assertMatchesRegularExpression('/<img src="<\?= htmlspecialchars\(\$siThumb\) \?>"[^>]*loading="lazy"[^>]*decoding="async"/', $view);
        // Le dossier des vignettes n'est pas listé par la médiathèque (premier niveau seulement, fichiers seulement).
        self::assertStringContainsString('if (!is_file($abs))', self::read('app/Services/Training/TrainingPublicSiteImageCatalog.php'));
        self::assertFileExists(self::ROOT . '/public/assets/images/_thumbs/' . sha1('fog-team.jpg') . '.webp');
        self::assertLessThan(60 * 1024, filesize(self::ROOT . '/public/assets/images/_thumbs/' . sha1('fog-team.jpg') . '.webp'));
    }

    public function testPollingAndScrollListenersAreBounded(): void
    {
        $alerts = self::read('public/assets/js/portal-alerts.js');
        self::assertStringContainsString("window.addEventListener('scroll', queuePlace, { passive: true })", $alerts);
        self::assertStringContainsString('requestAnimationFrame', $alerts);
        foreach (['public/assets/js/mission-cycle-badge.js', 'views/admin/atak_hub/index.php', 'views/admin/briefing_slides/index.php', 'public/assets/js/back-office-menu.js', 'public/assets/js/app-version-check.js'] as $file) {
            $src = self::read($file);
            self::assertStringContainsString('setInterval', $src, $file);
            self::assertMatchesRegularExpression('/document\.hidden|visibilityState/', $src, $file . ' : interrogation périodique active onglet masqué.');
        }
    }
}
