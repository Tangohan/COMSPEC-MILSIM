<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SsePortalNav;
use PHPUnit\Framework\TestCase;

/**
 * Bureau SSE : navigation unique, rangée par cycle, sans doublon, et parcours d'accès corrigé.
 */
final class SsePortalNavTest extends TestCase
{
    private static function root(): string
    {
        return dirname(__DIR__, 2);
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

    public function testEveryPageAppearsOnceAndIdsAreUnique(): void
    {
        $items = self::items(SsePortalNav::sections(true, true));
        $paths = array_column($items, 'path');
        $ids = array_column($items, 'id');
        self::assertSame(count($paths), count(array_unique($paths)), 'Une page apparaît deux fois dans la navigation');
        self::assertSame(count($ids), count(array_unique($ids)));
        foreach (['atak/sse/operations', 'atak/sse/toiles', 'atak/sse/dossiers', 'atak/sse/presentation', 'atak/sse/acces', 'atak/sse/guide', 'atak/sse/fiches'] as $p) {
            self::assertContains($p, $paths);
        }
    }

    public function testEveryInternalPathIsARealRoute(): void
    {
        $routes = (string) file_get_contents(self::root() . '/routes/web.php');
        foreach (self::items(SsePortalNav::sections(true, true)) as $item) {
            if (!empty($item['external'])) {
                continue;
            }
            $route = '/' . $item['path'];
            // Registres d'objets : route générique /atak/sse/objets/{type}.
            if (str_starts_with($route, '/atak/sse/objets/')) {
                $route = '/atak/sse/objets/{type}';
            }
            self::assertStringContainsString("'" . $route . "'", $routes, 'Route absente : ' . $route);
        }
    }

    public function testVisibilityFollowsRights(): void
    {
        $guestIds = array_column(self::items(SsePortalNav::sections(false, false, true)), 'id');
        self::assertNotContains('acces', $guestIds);
        self::assertNotContains('maitre-jeu', $guestIds);
        $grantGuest = array_column(self::items(SsePortalNav::sections(true, true, true)), 'id');
        self::assertNotContains('acces', $grantGuest, 'Un invité ne voit jamais l’administration');
        $staff = array_column(self::items(SsePortalNav::sections(true, true)), 'id');
        self::assertContains('acces', $staff);
        self::assertContains('maitre-jeu', $staff);
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

    public function testLayoutUsesTheSingleNavigationAndRealClearance(): void
    {
        $layout = (string) file_get_contents(self::root() . '/views/atak/sse/_layout.php');
        self::assertStringContainsString('SsePortalNav::sections', $layout);
        self::assertStringContainsString('SseClearanceService', $layout);
        self::assertStringContainsString('sx-palette', $layout);
        self::assertStringContainsString('sse-shell.js', $layout);
        // Les pastilles d'état écrites en dur ont disparu.
        self::assertStringNotContainsString('title="Sources serveur">3 src', $layout);
        self::assertFileExists(self::root() . '/public/assets/css/sse_shell.css');
        $css = (string) file_get_contents(self::root() . '/public/assets/css/sse_workspace.css');
        self::assertStringNotContainsString('--line: var(--line);', $css);
    }

    public function testAccessFlowFixes(): void
    {
        $svc = (string) file_get_contents(self::root() . '/app/Services/Sse/SseAccessCodeService.php');
        $mw = (string) file_get_contents(self::root() . '/app/Middleware/SsePortalAccessMiddleware.php');
        $ctrl = (string) file_get_contents(self::root() . '/app/Controllers/Web/SsePortalController.php');
        // « Quitter » sort vraiment : pas de réouverture automatique.
        self::assertStringContainsString('public function leave()', $svc);
        self::assertStringContainsString('hasLeft()', $mw);
        self::assertStringContainsString('!$this->access->hasLeft()', $ctrl);
        self::assertStringContainsString('$this->access->leave();', $ctrl);
        // L'engagement signé pendant la connexion survit au renouvellement de session.
        self::assertStringContainsString('SESSION_STAFF_ACK', $svc);
        // La « lecture complète » sans vue ne casse plus.
        self::assertStringNotContainsString("'atak.sse.case_reader'", $ctrl);
        $gate = (string) file_get_contents(self::root() . '/views/atak/sse/gate.php');
        self::assertStringNotContainsString('le code membre confirme votre habilitation', $gate);
        self::assertStringContainsString('Entrer sans code', $gate);
    }
}
