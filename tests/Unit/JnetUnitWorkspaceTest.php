<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Jnet\JnetUnitFiles;
use App\Services\Jnet\JnetUnitWorkspaceService as WS;
use PHPUnit\Framework\TestCase;

/**
 * JNET : outils de l'espace d'une unité (tâches, pages écrites ensemble, suivi des membres, photos et documents,
 * recherche). Logique pure testée directement ; câblage (routes, CSRF, droits, migration) vérifié sur les sources.
 */
final class JnetUnitWorkspaceTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';

    private function read(string $path): string
    {
        return (string) file_get_contents(self::ROOT . $path);
    }

    public function testBlocksAreSanitizedAndBounded(): void
    {
        $blocks = WS::sanitizeBlocks([
            ['t' => 'p', 'text' => "  Bonjour\r\nle monde \x07 "],
            ['t' => 'inconnu', 'text' => 'ignoré'],
            ['t' => 'h', 'text' => "Titre\nsur deux lignes"],
            ['t' => 'todo', 'text' => 'À faire', 'done' => '1'],
            ['t' => 'poll', 'text' => 'Quel jour ?', 'opts' => "Jeudi\n\n  \nDimanche"],
            'pas un bloc',
        ]);
        self::assertCount(4, $blocks);
        self::assertSame("Bonjour\nle monde", $blocks[0]['text']);
        self::assertSame('Titre sur deux lignes', $blocks[1]['text']);
        self::assertTrue($blocks[2]['done']);
        self::assertSame(['Jeudi', 'Dimanche'], $blocks[3]['opts']);
        foreach ($blocks as $b) {
            self::assertMatchesRegularExpression('/^[a-f0-9]{8}$/', $b['id']);
        }
        $many = WS::sanitizeBlocks(array_fill(0, 400, ['t' => 'p', 'text' => 'x']));
        self::assertCount(WS::BLOCKS_MAX, $many);
        $long = WS::sanitizeBlocks([['t' => 'p', 'text' => str_repeat('é', 5000)]]);
        self::assertSame(WS::TEXT_MAX, mb_strlen($long[0]['text']));
    }

    public function testPollKeepsTwoToEightChoicesAndUniqueBlockIds(): void
    {
        $poll = WS::sanitizeBlocks([['t' => 'poll', 'text' => 'Q', 'opts' => ['seul']]])[0];
        self::assertCount(2, $poll['opts']);
        $big = WS::sanitizeBlocks([['t' => 'poll', 'text' => 'Q', 'opts' => range(1, 20)]])[0];
        self::assertCount(WS::POLL_OPTIONS_MAX, $big['opts']);
        $dup = WS::sanitizeBlocks([['id' => 'aabbccdd', 't' => 'p', 'text' => 'a'], ['id' => 'aabbccdd', 't' => 'p', 'text' => 'b']]);
        self::assertNotSame($dup[0]['id'], $dup[1]['id']);
    }

    public function testBlockGesturesAlwaysLeaveAtLeastOneBlock(): void
    {
        $blocks = WS::sanitizeBlocks([['t' => 'p', 'text' => 'a'], ['t' => 'p', 'text' => 'b']]);
        $moved = WS::applyBlockAction($blocks, 'down:0');
        self::assertSame('b', $moved[0]['text']);
        self::assertSame($blocks, WS::applyBlockAction($blocks, 'up:0'));
        self::assertCount(3, WS::applyBlockAction($blocks, 'add:todo'));
        self::assertCount(2, WS::applyBlockAction($blocks, 'add:../../etc'));
        $none = WS::applyBlockAction([$blocks[0]], 'del:0');
        self::assertCount(1, $none);
        self::assertSame('p', $none[0]['t']);
        self::assertSame([], WS::blocksFromJson('pas du json'));
    }

    public function testBlocksRoundTripThroughJson(): void
    {
        $blocks = WS::sanitizeBlocks([['t' => 'todo', 'text' => 'Kit chargé', 'done' => true], ['t' => 'call', 'text' => 'Briefing 20:30']]);
        self::assertSame($blocks, WS::blocksFromJson(WS::blocksToJson($blocks)));
    }

    public function testHiddenModulesParsingAndFormValues(): void
    {
        self::assertSame(['pages', 'photos'], WS::parseHidden('pages, photos ,inconnu,pages'));
        self::assertSame([], WS::parseHidden(null));
        self::assertSame('photos,documents', WS::hiddenFromShown(['taches', 'pages']));
        self::assertSame('taches,pages,photos,documents', WS::hiddenFromShown([]));
        self::assertSame('', WS::hiddenFromShown(['taches', 'pages', 'photos', 'documents', 'x']));
    }

    public function testTasksGroupingAndEditRights(): void
    {
        $tasks = [
            ['id' => 1, 'status' => 'done', 'created_by' => 5, 'assignee_user_id' => null],
            ['id' => 2, 'status' => 'bizarre', 'created_by' => 5, 'assignee_user_id' => 7],
            ['id' => 3, 'status' => 'doing', 'created_by' => 6, 'assignee_user_id' => 7],
        ];
        $by = WS::tasksByStatus($tasks);
        self::assertCount(1, $by['done']);
        self::assertCount(1, $by['doing']);
        self::assertCount(1, $by['todo']);
        self::assertTrue(WS::canEditTask($tasks[0], 5, false), 'auteur');
        self::assertTrue(WS::canEditTask($tasks[1], 7, false), 'responsable');
        self::assertFalse(WS::canEditTask($tasks[0], 9, false), 'autre membre');
        self::assertTrue(WS::canEditTask($tasks[0], 9, true), 'chef de l’espace');
    }

    public function testTrackingRowsBuildTheThingsToHandle(): void
    {
        $members = [
            ['id' => 1, 'name' => 'Martin', 'duty' => 'active', 'initials' => 'MA'],
            ['id' => 2, 'name' => 'Dubois', 'duty' => 'off', 'initials' => 'DU'],
            ['id' => 3, 'name' => 'Viper', 'duty' => 'active', 'initials' => 'VI'],
        ];
        $rows = WS::trackingRows($members, 1, [10, 11], [10 => [1, 3], 11 => [1]], [1 => 'yes', 3 => 'no'], [3], [2 => true]);
        self::assertSame('active', $rows[0]['status']);
        self::assertTrue($rows[0]['me']);
        self::assertSame(2, $rows[0]['read']);
        self::assertSame([], $rows[0]['todo']);
        self::assertSame('off', $rows[1]['status']);
        self::assertSame('none', $rows[1]['answer']);
        $labels = array_column($rows[1]['todo'], 'label');
        self::assertContains('2 consignes à lire', $labels);
        self::assertContains('Réponse de présence attendue', $labels);
        self::assertContains('Qualification échue', $labels);
        self::assertSame('absence', $rows[2]['status']);
        self::assertContains('1 consigne à lire', array_column($rows[2]['todo'], 'label'));
    }

    public function testTrackingWithoutEventAsksNoAnswer(): void
    {
        $rows = WS::trackingRows([['id' => 1, 'name' => 'A', 'duty' => 'active']], 9, [], [], null, []);
        self::assertSame('', $rows[0]['answer']);
        self::assertSame([], $rows[0]['todo']);
    }

    public function testCountdown(): void
    {
        $now = new \DateTimeImmutable('2026-11-14 10:00:00');
        self::assertSame(3, WS::daysUntil('2026-11-17 21:00:00', $now));
        self::assertSame(0, WS::daysUntil('2026-11-14 23:30:00', $now));
        self::assertNull(WS::daysUntil('2026-11-13 21:00:00', $now));
        self::assertNull(WS::daysUntil('pas une date', $now));
        self::assertSame('J-3', WS::countdownLabel(3));
        self::assertSame('Aujourd’hui', WS::countdownLabel(0));
        self::assertSame('', WS::countdownLabel(null));
    }

    public function testFrenchDateLabel(): void
    {
        self::assertSame('Mar. 17 nov. · 21:00', WS::frenchDate((int) strtotime('2026-11-17 21:00:00')));
    }

    public function testFilesHelpers(): void
    {
        self::assertSame('1,2 Mo', JnetUnitFiles::sizeLabel(1_200_000));
        self::assertSame('340 Ko', JnetUnitFiles::sizeLabel(340_000));
        self::assertSame('1 Ko', JnetUnitFiles::sizeLabel(10));
        self::assertSame('12/11/2026', JnetUnitFiles::dateLabel('2026-11-12 08:00:00'));
        self::assertSame(['Cartes' => 1, 'Ordres' => 2], JnetUnitFiles::albums([['album' => 'Ordres'], ['album' => 'Cartes'], ['album' => 'Ordres']]));
    }

    public function testWritesAreRoutedPostOnlyWithCsrfAndServerSideRights(): void
    {
        $routes = $this->read('routes/web.php');
        foreach (['/jnet/u/{id}/taches', '/jnet/u/{id}/taches/{tid}', '/jnet/u/{id}/pages', '/jnet/u/{id}/pages/{pid}', '/jnet/u/{id}/pages/{pid}/supprimer', '/jnet/u/{id}/pages/{pid}/vote', '/jnet/u/{id}/pages/{pid}/reaction', '/jnet/u/{id}/pages/{pid}/commentaire', '/jnet/u/{id}/pages/{pid}/case', '/jnet/u/{id}/pages/{pid}/deplacer', '/jnet/u/{id}/presence', '/jnet/u/{id}/echanges/{eid}/relayer'] as $path) {
            self::assertMatchesRegularExpression('#\$router->post\(\'' . preg_quote($path, '#') . '\', \[\\\\App\\\\Controllers\\\\Web\\\\JnetUnitWorkspaceController::class, \'\w+\'\], \[AuthMiddleware::class\]\);#', $routes, $path);
            self::assertStringNotContainsString("\$router->get('" . $path . "'", $routes, $path);
        }
        self::assertStringContainsString("\$router->get('/jnet/u/{id}/recherche'", $routes);

        $ctl = $this->read('app/Controllers/Web/JnetUnitWorkspaceController.php');
        self::assertStringContainsString('Csrf::validate', $ctl);
        self::assertStringContainsString('rights($tenantId, $userId, $unitId)', $ctl);
        self::assertStringContainsString('hidden_modules', $ctl);
        self::assertStringContainsString('canEditTask', $ctl);
        // Chaque action passe par le même garde-fou.
        self::assertSame(15, preg_match_all('/\$this->guard\(\$request, \$params,/', $ctl));
        // Aucune redirection ne vient du client : toutes partent de l'adresse de l'espace.
        self::assertStringNotContainsString("input('back'", $ctl);
        self::assertStringNotContainsString("input('redirect'", $ctl);
    }

    public function testNoNewRightIsInvented(): void
    {
        $ctl = $this->read('app/Controllers/Web/JnetUnitWorkspaceController.php');
        $svc = $this->read('app/Services/Jnet/JnetUnitWorkspaceService.php');
        self::assertStringNotContainsString('->allows(\'jnet', $ctl . $svc);
        self::assertStringContainsString('organization.orbat.manage', $this->read('app/Services/Jnet/JnetSpaceService.php'), 'même droit que « Personnaliser »');
        self::assertStringContainsString('Droits : aucun droit nouveau', $svc);
    }

    public function testMigrationIsIdempotentAndReversible(): void
    {
        $sql = $this->read('migrations/2026_11_14_050_jnet_unit_workspace.sql');
        self::assertSame(5, preg_match_all('/CREATE TABLE IF NOT EXISTS (jnet_unit_\w+)/', $sql));
        self::assertStringNotContainsString('DROP ', $sql);
        $step = $this->read('database/steps/4530_jnet_unit_workspace.php');
        self::assertStringContainsString('tableExists', $step);
        self::assertStringContainsString('addColumnIfMissing(\'jnet_unit_spaces\', \'hidden_modules\'', $step);
        $down = $this->read('migrations/rollback/jnet_unit_workspace_down.sql');
        foreach (['jnet_unit_tasks', 'jnet_unit_pages', 'jnet_unit_page_reactions', 'jnet_unit_page_comments', 'jnet_unit_page_votes', 'hidden_modules'] as $name) {
            self::assertStringContainsString($name, $down, $name);
            self::assertStringContainsString($name, $sql . $step, $name);
        }
        $registry = $this->read('database/steps/registry.php');
        self::assertStringContainsString("'id' => 'jnet_unit_workspace'", $registry);
        self::assertStringContainsString('4530_jnet_unit_workspace.php', $registry);
        self::assertFileExists(self::ROOT . 'database/steps/4530_jnet_unit_workspace.php');
    }

    public function testRepositoryScopesEveryQueryToCommunityAndUnit(): void
    {
        $repo = $this->read('app/Repositories/JnetUnitWorkspaceRepository.php');
        foreach (['UPDATE jnet_unit_tasks', 'DELETE FROM jnet_unit_tasks', 'UPDATE jnet_unit_pages'] as $q) {
            self::assertStringContainsString($q, $repo);
        }
        self::assertSame(0, preg_match('/UPDATE jnet_unit_tasks SET [^\']* WHERE id = \?/', $repo), 'une tâche ne se modifie jamais par son seul identifiant');
        self::assertStringContainsString('tenant_id = ? AND unit_id = ? AND id = ?', $repo);
        self::assertStringContainsString('updated_at = ?', $repo, 'contrôle de version à l’enregistrement d’une page');
    }

    public function testViewsAreHonestAndQuietAndLeakNothing(): void
    {
        $files = ['views/jnet/unit_space.php', 'views/jnet/unit/_home.php', 'views/jnet/unit/_tasks.php', 'views/jnet/unit/_pages.php', 'views/jnet/unit/_page.php', 'views/jnet/unit/_tracking.php', 'views/jnet/unit/_files.php', 'views/jnet/unit/_event_card.php', 'public/assets/js/jnet_unit_workspace.js'];
        foreach ($files as $file) {
            $src = $this->read($file);
            self::assertStringNotContainsString('class="kk"', $src, $file);
            self::assertStringNotContainsString('alert(', $src, $file);
            self::assertStringNotContainsString('confirm(', $src, $file);
            self::assertStringNotContainsString('innerHTML', $src, $file);
            self::assertSame(0, preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $src), 'aucun emoji : ' . $file);
        }
        foreach (['unit/_tasks.php', 'unit/_pages.php', 'unit/_page.php', 'unit/_home.php'] as $form) {
            $src = $this->read('views/jnet/' . $form);
            self::assertSame(substr_count($src, 'method="post"'), substr_count($src, 'Csrf::field()'), 'un jeton par formulaire : ' . $form);
        }
        $tracking = $this->read('views/jnet/unit/_tracking.php');
        self::assertStringNotContainsString('Lorem', $tracking);
        $controller = $this->read('app/Controllers/Web/JnetPortalController.php');
        self::assertStringContainsString("'taches', 'pages', 'page', 'photos', 'documents'", $controller);
        self::assertStringContainsString('canPost', $controller);
    }

    public function testPagesAreWrittenAsADocumentButKeepTheStorageFormatAndFormFallback(): void
    {
        $page = $this->read('views/jnet/unit/_page.php');
        // Format des blocs et gestes serveur inchangés : le formulaire marche sans JavaScript.
        foreach (['name="<?= $name ?>[id]"', 'name="<?= $name ?>[t]"', '[text]', '[done]', '[opts][]', 'name="version"', 'value="del:<?= $i ?>"', 'value="add:<?= $h($type) ?>"'] as $needle) {
            self::assertStringContainsString($needle, $page, $needle);
        }
        // Plus de cadre gris ni de libellé de type au-dessus du texte : le texte se modifie en place.
        self::assertStringNotContainsString('uw-block__type', $page);
        self::assertStringNotContainsString('<fieldset', $page);
        self::assertStringContainsString('data-uw-editor', $page);
        self::assertStringContainsString('data-uw-status', $page);
        $pages = $this->read('views/jnet/unit/_pages.php');
        self::assertStringContainsString('data-uw-reorder', $pages);
        self::assertStringContainsString('uw-pages-empty', $pages);
    }

    public function testFilesComeOnlyFromTheSharedFilesModuleWithItsOwnAccessControl(): void
    {
        $svc = $this->read('app/Services/Jnet/JnetUnitFiles.php');
        self::assertStringContainsString('FileShareWorkspace', $svc);
        self::assertStringContainsString('fileLevel($f) < FileShareAccess::VIEW', $svc);
        self::assertStringContainsString("url('partage/fichiers/'", $svc);
        self::assertStringNotContainsString('move_uploaded_file', $svc);
    }

    public function testCustomizeFormOffersTheModulesOnlyWhenTheColumnExists(): void
    {
        $view = $this->read('views/jnet/customize.php');
        self::assertStringContainsString('customizeModulesReady', $view);
        self::assertStringContainsString('modules_shown[]', $view);
        $ctl = $this->read('app/Controllers/Web/JnetPortalController.php');
        self::assertStringContainsString('saveHiddenModules', $ctl);
        $settings = $this->read('app/Services/Jnet/JnetUnitSpaceSettings.php');
        self::assertStringContainsString('function modulesReady', $settings);
        self::assertStringContainsString('SELECT hidden_modules FROM jnet_unit_spaces LIMIT 0', $settings);
    }

    public function testSpacingGuttersAreAlignedOnTheColumnGutter(): void
    {
        $css = $this->read('public/assets/css/jnet_unit_shell.css');
        self::assertStringContainsString('.jn-flow .jn-compose {', $css);
        self::assertStringContainsString('padding: 12px 18px;', $css);
        $hub = $this->read('public/assets/css/jnet_hub.css');
        self::assertStringContainsString('calc(100% - 2 * clamp(16px, 3vw, 40px))', $hub);
        $view = $this->read('views/jnet/unit_space.php');
        self::assertStringContainsString("\$moreHref !== '' && \$moreLabel !== ''", $view, 'pas de flèche seule à côté d’un titre sans lien');
    }
}
