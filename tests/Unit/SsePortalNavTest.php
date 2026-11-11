<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SsePortalNav;
use PHPUnit\Framework\TestCase;

/**
 * Bureau SSE : navigation unique rangée par usage, toutes les pages accessibles, liens selon les droits,
 * fil d'Ariane, accueil du bureau, aucune animation au chargement d'une page, parcours d'accès corrigé.
 */
final class SsePortalNavTest extends TestCase
{
    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private static function read(string $rel): string
    {
        return (string) file_get_contents(self::root() . '/' . $rel);
    }

    /** @return list<array<string, mixed>> */
    private static function items(array $sections): array
    {
        $out = [];
        foreach ($sections as $s) {
            foreach ($s['items'] as $i) {
                $out[] = $i;
            }
        }

        return $out;
    }

    /** @return array<string, string> id => path */
    private static function section(array $sections, string $id): array
    {
        foreach ($sections as $s) {
            if ($s['id'] === $id) {
                return array_column($s['items'], 'path', 'id');
            }
        }

        return [];
    }

    public function testSectionsAreGroupedByUseAndEachHasAnIntroduction(): void
    {
        $sections = SsePortalNav::sections(true, true, false, true, true);
        self::assertSame(
            ['pilotage', 'renseignement', 'referentiel', 'analyse', 'production', 'identite', 'fichiers', 'roleplay', 'outils'],
            array_column($sections, 'id')
        );
        foreach ($sections as $s) {
            self::assertNotSame('', trim((string) $s['hint']), $s['id']);
            self::assertNotEmpty($s['items'], $s['id']);
            foreach ($s['items'] as $i) {
                self::assertNotSame('', trim((string) $i['hint']), $i['id']);
            }
        }
        // Même avec le moins de droits, aucune section n'est vide.
        foreach (SsePortalNav::sections(false, false, true) as $s) {
            self::assertNotEmpty($s['items'], $s['id']);
        }
    }

    public function testEveryPageAppearsOnceAndIdsAreUnique(): void
    {
        $items = self::items(SsePortalNav::sections(true, true, false, true, true));
        $paths = array_column($items, 'path');
        $ids = array_column($items, 'id');
        $labels = array_column($items, 'label');
        self::assertSame(count($paths), count(array_unique($paths)), 'Une page apparaît deux fois dans la navigation');
        self::assertSame(count($ids), count(array_unique($ids)));
        self::assertSame(count($labels), count(array_unique($labels)), 'Deux pages portent le même nom');
        foreach (['atak/sse/accueil', 'atak/sse/operations', 'atak/sse/toiles', 'atak/sse/dossiers', 'atak/sse/presentation', 'atak/sse/acces', 'atak/sse/guide', 'atak/sse/fiches', 'atak/sse/fichiers', 'atak/sse/fichiers/journal', 'atak/sse/fichiers/mentions', 'atak/sse/fichiers/seek', 'atak/sse/editeur', 'atak/sse/papiers/profils-ia'] as $p) {
            self::assertContains($p, $paths);
        }
    }

    public function testEveryInternalPathIsARealRoute(): void
    {
        $routes = self::read('routes/web.php');
        foreach (self::items(SsePortalNav::sections(true, true, false, true, true)) as $item) {
            if (!empty($item['external'])) {
                continue;
            }
            $route = '/' . strtok((string) $item['path'], '#');
            // Registres d'objets : route générique /atak/sse/objets/{type}.
            if (str_starts_with($route, '/atak/sse/objets/')) {
                $route = '/atak/sse/objets/{type}';
            }
            self::assertStringContainsString("get('" . $route . "'", $routes, 'Route absente : ' . $route);
        }
    }

    /** Aucune page de liste du portail n'est orpheline : chacune a son lien dans la navigation. */
    public function testNoPortalPageIsOrphaned(): void
    {
        preg_match_all("~get\\('/(atak/sse/[a-z-]+(?:/[a-z-]+)?)'~", self::read('routes/web.php'), $m);
        $nav = [];
        foreach (self::items(SsePortalNav::sections(true, true, false, true, true)) as $item) {
            foreach (array_merge([strtok((string) $item['path'], '#')], $item['aliases'] ?? []) as $p) {
                $nav[$p] = true;
            }
        }
        // Hors navigation par nature : sas d'entrée, recherche, formulaires et sous-pages ouverts depuis leur page.
        $outside = '~^atak/sse/(entrer|commandement|quitter|apparence|confidentialite|recherche|objets)(/|$)|/(nouveau|nouvelle|reels|requete|export|mrz|partagees|importer|suggestions|pdf)$|^atak/sse/(exploitation-numerique|dev)/~';
        $orphans = [];
        foreach (array_unique($m[1]) as $route) {
            if (!isset($nav[$route]) && !preg_match($outside, $route)) {
                $orphans[] = $route;
            }
        }
        self::assertSame([], $orphans, 'Pages sans lien dans la navigation du bureau');
    }

    public function testVisibilityFollowsRights(): void
    {
        $guestIds = array_column(self::items(SsePortalNav::sections(false, false, true)), 'id');
        foreach (['acces', 'maitre-jeu', 'editeur', 'papiers-membres', 'papiers-atelier', 'papiers-profils-ia', 'papiers-preparation'] as $id) {
            self::assertNotContains($id, $guestIds, $id);
        }
        self::assertContains('fichiers', $guestIds);
        $grantGuest = array_column(self::items(SsePortalNav::sections(true, true, true)), 'id');
        self::assertNotContains('acces', $grantGuest, 'Un invité ne voit jamais l’administration');
        $staff = array_column(self::items(SsePortalNav::sections(true, true)), 'id');
        self::assertContains('acces', $staff);
        self::assertContains('maitre-jeu', $staff);

        // L'accueil explique ce qui manque, et rien quand tout est ouvert.
        $missing = array_column(SsePortalNav::restricted(false, false, true, false, false), 'label');
        self::assertCount(4, $missing);
        self::assertContains('Maître du jeu', $missing);
        self::assertSame([], SsePortalNav::restricted(true, true, false, true, true));
    }

    public function testIdentityAndFilesSectionsListTheirPagesByRight(): void
    {
        $withMembers = self::section(SsePortalNav::sections(true, false, false, true), 'identite');
        self::assertSame(['papiers-tableau', 'papiers', 'papiers-identites', 'papiers-membres', 'papiers-pays', 'papiers-atelier', 'papiers-kits', 'papiers-briefs', 'papiers-echanges'], array_keys($withMembers));
        self::assertArrayNotHasKey('papiers-membres', self::section(SsePortalNav::sections(false, false, true), 'identite'), 'Papiers des membres : droit dédié');
        self::assertArrayNotHasKey('papiers-membres', self::section(SsePortalNav::sections(true, true), 'identite'));

        $files = self::section(SsePortalNav::sections(false, false, true), 'fichiers');
        self::assertSame(['fichiers', 'fichiers-photos', 'fichiers-telephones', 'fichiers-journal', 'fichiers-mentions', 'fichiers-seek', 'papiers-controles', 'papiers-frm'], array_keys($files));
        self::assertArrayHasKey('fichiers-lore', self::section(SsePortalNav::sections(true, false, false), 'fichiers'), 'Lore des sessions : gestion SSE ou MJ, jamais en invité');
        self::assertArrayNotHasKey('fichiers-lore', self::section(SsePortalNav::sections(true, false, true), 'fichiers'));
        self::assertSame('atak/sse/papiers/tableau#frm', $files['papiers-frm']);
        self::assertSame(['papiers-profils-ia', 'papiers-preparation', 'campagnes', 'dev', 'editeur', 'maitre-jeu'], array_keys(self::section(SsePortalNav::sections(true, false, false, true, true), 'roleplay')));

        $s = SsePortalNav::sections(true, true, false, true);
        foreach ([
            '/public/atak/sse/accueil' => 'accueil',
            '/public/atak/sse/papiers' => 'papiers',
            '/public/atak/sse/papiers/documents/7' => 'papiers',
            '/public/atak/sse/papiers/tableau?op=x' => 'papiers-tableau',
            '/public/atak/sse/papiers/identites/3' => 'papiers-identites',
            '/public/atak/sse/papiers/controles/9' => 'papiers-controles',
            '/public/atak/sse/papiers/membres/2/documents/nouveau' => 'papiers-membres',
            '/public/atak/sse/papiers/pays/nouveau' => 'papiers-pays',
            '/public/atak/sse/papiers/profils-ia/4' => 'papiers-profils-ia',
            '/public/atak/sse/fichiers/requete?type=QW' => 'fichiers',
            '/public/atak/sse/fichiers/journal' => 'fichiers-journal',
            '/public/atak/sse/identites/4' => 'identites',
        ] as $path => $id) {
            self::assertSame($id, SsePortalNav::activeId($s, $path), $path);
        }
        self::assertSame('fichiers', SsePortalNav::sectionOf($s, 'papiers-controles'));
        self::assertSame('roleplay', SsePortalNav::sectionOf($s, 'papiers-profils-ia'));
        $palette = SsePortalNav::paletteEntries($s, static fn (string $p): string => '/' . $p, true, false);
        self::assertContains('/atak/sse/papiers/controles', array_column($palette, 'href'));
        self::assertContains('/atak/sse/fichiers', array_column($palette, 'href'));
        self::assertStringContainsString('id="frm"', self::read('views/atak/sse/papers/dashboard.php'));
    }

    public function testActiveItemComesFromTheUrl(): void
    {
        $s = SsePortalNav::sections(true, true);
        self::assertSame('dossiers', SsePortalNav::activeId($s, '/public/atak/sse/dossiers/12?onglet=preuves'));
        self::assertSame('vehicules', SsePortalNav::activeId($s, '/public/atak/sse/objets/vehicules'));
        self::assertSame('identites', SsePortalNav::activeId($s, '/public/atak/sse/personnes'));
        self::assertSame('labnum', SsePortalNav::activeId($s, '/public/atak/sse/exploitation-numerique/rapports'));
        self::assertSame('toiles', SsePortalNav::activeId($s, '/public/atak/sse/toiles/3'));
        // Pas de correspondance : ancienne valeur de $activeNav traduite.
        self::assertSame('toiles', SsePortalNav::activeId($s, '/public/atak/sse/objets/nouveau', 'graphe'));
        self::assertSame('referentiel', SsePortalNav::sectionOf($s, 'vehicules'));
    }

    public function testBreadcrumbNamesSectionPageAndDetail(): void
    {
        $s = SsePortalNav::sections(true, true);
        $url = static fn (string $p): string => '/' . $p;
        $labels = static fn (array $c): array => array_column($c, 'label');

        $detail = SsePortalNav::breadcrumb($s, 'dossiers', '/public/atak/sse/dossiers/12', 'Affaire Karim — Dossier', $url);
        self::assertSame(['Bureau SSE', 'Renseignement et enquêtes', 'Dossiers validés', 'Affaire Karim'], $labels($detail));
        self::assertSame('/atak/sse/accueil', $detail[0]['href']);
        self::assertSame('/atak/sse/accueil#renseignement', $detail[1]['href']);
        self::assertSame('/atak/sse/dossiers', $detail[2]['href']);
        self::assertNull($detail[3]['href']);

        $page = SsePortalNav::breadcrumb($s, 'fichiers-journal', '/public/atak/sse/fichiers/journal', 'Journal des consultations', $url);
        self::assertSame(['Bureau SSE', 'Fichiers et contrôles', 'Journal des consultations'], $labels($page));
        self::assertNull($page[2]['href']);

        self::assertSame(['Bureau SSE'], $labels(SsePortalNav::breadcrumb($s, 'accueil', '/public/atak/sse/accueil', 'Bureau SSE', $url)));
    }

    public function testPaletteListsActionsAndPages(): void
    {
        $s = SsePortalNav::sections(true, false);
        $entries = SsePortalNav::paletteEntries($s, static fn (string $p): string => '/x/' . $p, true, false);
        $labels = array_column($entries, 'label');
        self::assertContains('Rédiger une fiche de renseignement', $labels);
        self::assertContains('Ouvrir une investigation', $labels);
        self::assertContains('Dossiers validés', $labels);
        self::assertSame(count(self::items($s)) + 4, count($entries));
        $guest = SsePortalNav::paletteEntries(SsePortalNav::sections(false, false, true), static fn (string $p): string => $p, false, true);
        self::assertNotContains('Rédiger une fiche de renseignement', array_column($guest, 'label'));
    }

    public function testHomePageListsEverySpace(): void
    {
        self::assertStringContainsString("get('/atak/sse/accueil', [SsePortalController::class, 'home'], \$mwSsePortal)", self::read('routes/web.php'));
        self::assertStringContainsString("'atak.sse.home'", self::read('app/Controllers/Web/SsePortalController.php'));
        $home = self::read('views/atak/sse/home.php');
        self::assertStringContainsString("SsePortalNav::forSession(", $home);
        self::assertStringContainsString("['restricted']", $home);
        self::assertStringContainsString("\$section['hint']", $home);
        self::assertStringNotContainsString('kicker', $home);
        self::assertStringNotContainsString('eyebrow', $home);
    }

    public function testLayoutUsesTheSingleNavigationAndRealClearance(): void
    {
        $layout = self::read('views/atak/sse/_layout.php');
        self::assertStringContainsString('SsePortalNav::forSession', $layout);
        self::assertStringContainsString('SsePortalNav::breadcrumb', $layout);
        self::assertStringContainsString('sx-crumbs', $layout);
        self::assertStringContainsString('sx-tabs', $layout);
        self::assertStringContainsString('SseClearanceService', $layout);
        self::assertStringContainsString('sx-palette', $layout);
        self::assertStringContainsString('sse-shell.js', $layout);
        self::assertStringContainsString('url(SsePortalNav::HOME)', $layout, 'La marque mène à l’accueil du bureau');
        // Les pastilles d'état écrites en dur ont disparu.
        self::assertStringNotContainsString('title="Sources serveur">3 src', $layout);
        self::assertFileExists(self::root() . '/public/assets/css/sse_shell.css');
        self::assertStringNotContainsString('--line: var(--line);', self::read('public/assets/css/sse_workspace.css'));
    }

    /** « L'effet à chaque page » : rideaux, texte brouillé, apparitions et compteurs animés retirés. */
    public function testNoEffectIsPlayedWhenAPageLoads(): void
    {
        $layout = self::read('views/atak/sse/_layout.php');
        $js = self::read('public/assets/js/sse-shell.js');
        foreach ([$layout, $js, self::read('public/assets/css/sse_shell.css')] as $src) {
            self::assertStringNotContainsString('curtain', $src);
            self::assertStringNotContainsString('scramble', $src);
        }
        self::assertStringNotContainsString('fonts.googleapis.com', $layout, 'Polices servies par le site et préchargées');
        self::assertStringContainsString('rel="preload"', $layout);
        self::assertStringNotContainsString('.iw-anim', self::read('public/assets/css/sse_workspace.css'));
        $ops = self::read('views/atak/sse/operations.php');
        self::assertStringNotContainsString('data-count', $ops);
        self::assertStringNotContainsString('iw-anim', $ops);
        self::assertStringContainsString('prefers-reduced-motion', self::read('public/assets/css/sse_shell.css'));
        foreach (['gate', 'confidentiality'] as $v) {
            self::assertStringNotContainsString('data-scramble', self::read('views/atak/sse/' . $v . '.php'));
        }
    }

    /** Plus de sur-titre au-dessus des titres de page : le fil d'Ariane de la coque situe la page. */
    public function testPagesHaveNoEyebrowOrHandWrittenBreadcrumb(): void
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::root() . '/views/atak/sse'));
        foreach ($it as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $src = (string) file_get_contents($file->getPathname());
            self::assertStringNotContainsString('page-heading-overline', $src, $file->getFilename());
            self::assertStringNotContainsString('class="breadcrumb"', $src, $file->getFilename());
            self::assertDoesNotMatchRegularExpression('~Réf\. ATH-SSE-~', $src, $file->getFilename());
        }
    }

    public function testAccessFlowFixes(): void
    {
        $svc = self::read('app/Services/Sse/SseAccessCodeService.php');
        $mw = self::read('app/Middleware/SsePortalAccessMiddleware.php');
        $ctrl = self::read('app/Controllers/Web/SsePortalController.php');
        // « Quitter » sort vraiment : pas de réouverture automatique.
        self::assertStringContainsString('public function leave()', $svc);
        self::assertStringContainsString('hasLeft()', $mw);
        self::assertStringContainsString('!$this->access->hasLeft()', $ctrl);
        self::assertStringContainsString('$this->access->leave();', $ctrl);
        // L'engagement signé pendant la connexion survit au renouvellement de session.
        self::assertStringContainsString('SESSION_STAFF_ACK', $svc);
        // La « lecture complète » sans vue ne casse plus.
        self::assertStringNotContainsString("'atak.sse.case_reader'", $ctrl);
        $gate = self::read('views/atak/sse/gate.php');
        self::assertStringNotContainsString('le code membre confirme votre habilitation', $gate);
        self::assertStringContainsString('Entrer sans code', $gate);
    }
}
