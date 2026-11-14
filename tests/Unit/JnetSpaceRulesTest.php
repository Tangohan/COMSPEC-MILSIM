<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Jnet\JnetSpaceRules as R;
use App\Services\Jnet\JnetUnitFiles;
use App\Services\Jnet\JnetUnitWorkspaceService as WS;
use App\Support\JnetSpaceTree;
use PHPUnit\Framework\TestCase;

/**
 * JNET : réglages par espace d'unité (prise en compte obligatoire, relais automatique, publication réservée, accès par
 * unité, gestionnaires), partage, relance et sources des photos et documents. Logique pure testée directement ; câblage
 * (routes, CSRF, migration) vérifié sur les sources.
 */
final class JnetSpaceRulesTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';

    private function read(string $path): string
    {
        return (string) file_get_contents(self::ROOT . $path);
    }

    /** 1 Bataillon > 2 Compagnie > (3 Section A, 4 Section B) ; 1 > 5 Autre compagnie > 6 Section C. */
    private function tree(): JnetSpaceTree
    {
        return JnetSpaceTree::fromRows([[1, 0, 'Bataillon'], [2, 1, 'Compagnie'], [3, 2, 'Section A'], [4, 2, 'Section B'], [5, 1, 'Autre compagnie'], [6, 5, 'Section C']]);
    }

    /**
     * @param list<int> $units
     * @param list<int> $commanded
     * @param array<string, mixed> $rules
     * @return array{read: bool, write: bool, lead: bool, publish: bool, chief: bool}
     */
    private function rights(array $units, array $rules = [], int $user = 10, array $commanded = [], bool $chief = false, bool $orgWriter = false): array
    {
        return R::rights($this->tree(), 2, $user, $units, $commanded, $orgWriter, false, $chief, $rules + R::defaults());
    }

    public function testDefaultsChangeNothingForExistingSpaces(): void
    {
        $member = $this->rights([2]);
        self::assertSame(['read' => true, 'write' => true, 'lead' => false, 'publish' => true, 'chief' => false], $member);
        // Un lecteur d'une autre branche voit l'espace (comme avant) mais n'y écrit pas.
        $other = $this->rights([6]);
        self::assertTrue($other['read']);
        self::assertFalse($other['write']);
        self::assertFalse($other['publish']);
        // Un membre d'une sous-unité n'a pas le droit de publier au nom de l'unité au-dessus (règle de l'ORBAT inchangée).
        self::assertFalse($this->rights([3])['write']);
    }

    public function testAccessListOpensToolsOnlyAndNeverPublishing(): void
    {
        $rules = ['access' => [5 => 'write']];
        $other = $this->rights([6], $rules);
        self::assertTrue($other['read']);
        self::assertTrue($other['write'], 'la ligne « lecture et écriture » ouvre les outils');
        self::assertFalse($other['publish'], 'jamais le droit de publier un ordre au nom de l’unité');
        self::assertFalse($other['lead']);
        $lead = $this->rights([6], ['access' => [5 => 'lead']]);
        self::assertTrue($lead['write']);
        self::assertTrue($lead['lead']);
        self::assertFalse($lead['publish']);
    }

    public function testAccessListRestrictsReadingAndWriting(): void
    {
        // Une unité non listée perd la lecture dès qu'il y a des lignes ; la filiation et les listées la gardent.
        $rules = ['access' => [3 => 'read']];
        self::assertFalse($this->rights([6], $rules)['read']);
        self::assertTrue($this->rights([2], $rules)['read']);
        self::assertTrue($this->rights([1], $rules)['read'], 'l’encadrement au-dessus garde l’accès');
        self::assertTrue($this->rights([3], $rules)['read']);
        // « Lecture seule » retire l'écriture à l'unité listée, mais pas au chef.
        $readOnly = ['access' => [1 => 'read']];
        self::assertFalse($this->rights([1], $readOnly)['write']);
        self::assertTrue($this->rights([1], $readOnly, 10, [1], true)['write']);
        // L'organisation entière peut être listée.
        self::assertTrue($this->rights([6], ['access' => [0 => 'read']])['read']);
        // Un lecteur de toute l'organisation passe toujours.
        self::assertTrue(R::rights($this->tree(), 2, 10, [6], [], false, true, false, ['access' => [3 => 'read']] + R::defaults())['read']);
    }

    public function testManagersAreModeratorsAndWriters(): void
    {
        $m = $this->rights([6], ['managers' => [10]]);
        self::assertTrue($m['write']);
        self::assertTrue($m['lead']);
        self::assertFalse($m['publish']);
        self::assertFalse($this->rights([6], ['managers' => [11]])['write']);
    }

    public function testChiefKeepsEverythingAndRulesNeverOverrideTheTree(): void
    {
        $chief = $this->rights([2], ['access' => [3 => 'read'], 'post_min' => 'unit'], 10, [2], true);
        self::assertTrue($chief['chief'] && $chief['lead'] && $chief['write'] && $chief['publish']);
        // Un chef hors de la filiation ne gagne pas le droit d'écrire par le seul réglage.
        self::assertFalse($this->rights([6], [], 10, [], true)['write']);
        // Une unité absente de l'arbre du lecteur n'a pas d'espace : aucun droit.
        $none = R::rights($this->tree(), 99, 10, [2], [], false, true, false, R::defaults());
        self::assertFalse($none['read']);
        self::assertFalse($none['write']);
    }

    public function testPublicationCanOnlyBeNarrowed(): void
    {
        $tree = $this->tree();
        self::assertTrue($this->rights([2], ['post_min' => 'member'])['publish']);
        self::assertFalse($this->rights([2], ['post_min' => 'unit'])['publish']);
        self::assertFalse($this->rights([2], ['post_min' => 'team'])['publish']);
        self::assertTrue($this->rights([2], ['post_min' => 'unit'], 10, [2])['publish'], 'le chef de l’unité');
        self::assertTrue($this->rights([2], ['post_min' => 'unit'], 10, [1])['publish'], 'le chef d’une unité au-dessus');
        self::assertTrue($this->rights([2], ['post_min' => 'team'], 10, [3])['publish'], 'chef d’une sous-unité = chef d’équipe');
        self::assertFalse($this->rights([2], ['post_min' => 'unit'], 10, [3])['publish']);
        self::assertFalse($this->rights([2], ['post_min' => 'team'], 10, [5])['publish'], 'une autre branche ne publie pas');
        // Même sans réglage, qui n'a pas le droit de base ne publie pas.
        self::assertFalse($this->rights([6], ['post_min' => 'member'])['publish']);
        self::assertTrue(R::meetsPostMin('unit', $tree, 2, [], true));
        self::assertSame('member', R::postMin('n’importe quoi'));
        self::assertSame('team', R::postMin('team'));
    }

    public function testAccessRowsFromTheFormAreCleaned(): void
    {
        $tree = $this->tree();
        $rows = R::accessFromForm([3 => 'write', 4 => 'remove', 5 => 'lead', 99 => 'read', 6 => 'inconnu', 2 => 'read'], '0', 'read', $tree, 2);
        self::assertSame([0 => 'read', 3 => 'write', 5 => 'lead'], $rows, 'unité inconnue, droit inconnu, l’espace lui-même et les lignes retirées sont ignorés');
        self::assertSame([], R::accessFromForm([], 'none', 'read', $tree, 2));
        self::assertSame([6 => 'write'], R::accessFromForm([], '6', 'write', $tree, 2));
        self::assertSame([], R::accessFromForm('pas un tableau', '', 'write', $tree, 2));
        $many = [];
        foreach (range(1000, 1100) as $i) {
            $many[$i] = 'read';
        }
        self::assertSame([], R::normalizeAccess($many, $tree, 2), 'aucune unité inconnue');
    }

    public function testManagersAreDesignatedByCallsignAmongAllowedMembersOnly(): void
    {
        $pool = [10 => 'Viper', 11 => 'echo 2', 12 => ''];
        $r = R::managersFromCallsigns("viper ; Echo  2,\nFantôme, viper", $pool);
        self::assertSame([10, 11], $r['ids']);
        self::assertSame(['Fantôme'], $r['unknown']);
        self::assertSame([], R::managersFromCallsigns('', $pool)['ids']);
        self::assertSame([], R::managersFromCallsigns('Viper', [])['ids']);
    }

    public function testOnlyUnreadOrdersAskForAcknowledgement(): void
    {
        $ex = [
            ['id' => 1, 'kind' => 'ordre', 'requiresAck' => true, 'readByMe' => false],
            ['id' => 2, 'kind' => 'ordre', 'requiresAck' => true, 'readByMe' => true],
            ['id' => 3, 'kind' => 'info', 'requiresAck' => false, 'readByMe' => false],
            ['id' => 1, 'kind' => 'ordre', 'requiresAck' => true, 'readByMe' => false],
            ['id' => 4, 'kind' => 'compte_rendu', 'requiresAck' => true, 'readByMe' => false],
        ];
        $pending = R::pendingOrders($ex);
        self::assertCount(1, $pending);
        self::assertSame(1, $pending[0]['id']);
        self::assertCount(5, R::pendingOrders(array_map(static fn (int $i): array => ['id' => $i, 'kind' => 'ordre', 'requiresAck' => true, 'readByMe' => false], range(1, 9))));
    }

    public function testReminderTargetsOnlyUnitMembersWithoutAnswer(): void
    {
        $event = ['title' => 'Briefing', 'byMember' => [1 => 'yes', 2 => 'none', 3 => 'maybe', 4 => 'none', 5 => 'no']];
        self::assertSame([2, 4], WS::unansweredIds($event, [1, 2, 3, 4, 5], 99));
        self::assertSame([4], WS::unansweredIds($event, [1, 2, 3, 4, 5], 2), 'l’expéditeur est exclu');
        self::assertSame([6], WS::unansweredIds($event, [6], 99), 'un membre absent des réponses compte comme sans réponse');
        self::assertSame([], WS::unansweredIds(null, [1, 2], 99));
        self::assertSame(WS::reminderSubject('Briefing', '1re Cie'), WS::reminderSubject('Briefing', '1re Cie'), 'objet stable : le rappel n’est pas renvoyé en boucle');
        self::assertLessThanOrEqual(150, mb_strlen(WS::reminderSubject(str_repeat('é', 400), str_repeat('u', 400))));
    }

    public function testExtraMediaSourcesAreMergedNewestFirstAndStayHonest(): void
    {
        $files = ['ready' => false, 'photos' => [], 'documents' => [], 'folders' => []];
        // Sans fichier partagé ni autre source, la rubrique est vide et n'invente rien.
        $empty = JnetUnitFiles::merge($files, ['photos' => [], 'documents' => []]);
        self::assertFalse($empty['ready']);
        self::assertSame([], $empty['photos']);
        $old = JnetUnitFiles::mediaItem(1, 'Vieille', '', 'Photo terrain', 'Viper', '2026-11-01 10:00:00', '/jnet', 'https://x/a.jpg', 'exchange', 'exchange');
        $new = JnetUnitFiles::mediaItem(2, 'Récente', '', 'Photo terrain', 'Echo', '2026-11-12 10:00:00', '/jnet', 'https://x/b.jpg', 'exchange', 'exchange');
        $lib = JnetUnitFiles::mediaItem(0, 'Procédure', 'DOC', 'Bibliothèque', '', '2026-11-05 10:00:00', 'https://x/doc', '', 'library', '');
        $merged = JnetUnitFiles::merge($files, ['photos' => [$old, $new], 'documents' => [$lib]]);
        self::assertTrue($merged['ready'], 'une autre source suffit à montrer la rubrique');
        self::assertSame(['Récente', 'Vieille'], array_column($merged['photos'], 'title'));
        self::assertSame('12/11/2026', $merged['photos'][0]['date']);
        self::assertSame('', $merged['documents'][0]['shareKind'], 'un document de la bibliothèque ne se partage pas par cette fenêtre');
        self::assertSame('Echo', $merged['photos'][0]['by']);
    }

    public function testMigrationAddsOnlyWhatIsMissingAndIsReversible(): void
    {
        $sql = $this->read('migrations/2026_11_14_060_jnet_unit_space_rules.sql');
        self::assertSame(3, preg_match_all('/CREATE TABLE IF NOT EXISTS (jnet_unit_\w+)/', $sql));
        self::assertStringNotContainsString('DROP ', $sql);
        self::assertStringNotContainsString('DELETE FROM', $sql);
        $step = $this->read('database/steps/4540_jnet_unit_space_rules.php');
        foreach (['require_ack', 'auto_relay', 'post_min'] as $col) {
            self::assertStringContainsString("'" . $col . "'", $step);
        }
        self::assertStringContainsString('addColumnIfMissing', $step);
        self::assertStringContainsString('tableExists', $step);
        $down = $this->read('migrations/rollback/jnet_unit_space_rules_down.sql');
        foreach (['jnet_unit_space_access', 'jnet_unit_space_managers', 'jnet_unit_auto_relays', 'post_min', 'auto_relay', 'require_ack'] as $name) {
            self::assertStringContainsString($name, $down, $name);
        }
        $registry = $this->read('database/steps/registry.php');
        self::assertStringContainsString("'id' => 'jnet_unit_space_rules'", $registry);
        self::assertStringContainsString('4540_jnet_unit_space_rules.php', $registry);
        self::assertFileExists(self::ROOT . 'database/steps/4540_jnet_unit_space_rules.php');
        // Chaque identifiant d'étape est unique.
        preg_match_all("/'id' => '([a-z0-9_]+)'/", $registry, $m);
        self::assertSame(count($m[1]), count(array_unique($m[1])));
    }

    public function testRulesAreAppliedServerSideEverywhereTheSpaceIsOpenedOrWritten(): void
    {
        $svc = $this->read('app/Services/Jnet/JnetSpaceService.php');
        self::assertStringContainsString('JnetSpaceRules::rights(', $svc);
        self::assertStringContainsString("['publish']", $svc, 'publier un échange passe par le droit « publish »');
        self::assertStringContainsString('claimAutoRelay', $svc, 'relais automatique : une seule fois par espace et par échange');
        self::assertStringContainsString('DIR_DOWN', $svc, 'relais automatique : seulement vers le bas');
        $ctl = $this->read('app/Controllers/Web/JnetUnitWorkspaceController.php');
        self::assertStringContainsString("rights(\$tenantId, \$userId, \$unitId)", $ctl);
        self::assertStringContainsString("\$rights['write']", $ctl);
        self::assertStringContainsString('$g[\'rights\'][\'publish\']', $ctl, 'la relance demande de pouvoir publier');
        $portal = $this->read('app/Controllers/Web/JnetPortalController.php');
        self::assertSame(3, substr_count($portal, '$this->spaces->canRead('), 'ouvrir, entrer et chercher refont le contrôle de lecture');
        self::assertStringContainsString('JnetSpaceRules::accessFromForm', $portal);
        self::assertStringContainsString('saveManagers', $portal);
        $settings = $this->read('app/Services/Jnet/JnetUnitSpaceSettings.php');
        self::assertStringContainsString('INSERT IGNORE INTO jnet_unit_auto_relays', $settings);
        self::assertStringContainsString('tenant_id = ? AND unit_id = ?', $settings);
    }

    public function testNewWritesAreRoutedPostOnlyWithCsrf(): void
    {
        $routes = $this->read('routes/web.php');
        foreach (['/jnet/u/{id}/partager' => 'share', '/jnet/u/{id}/relancer' => 'remind', '/jnet/u/{id}/pages/ordre' => 'reorderPages'] as $path => $method) {
            self::assertStringContainsString("\$router->post('" . $path . "', [\\App\\Controllers\\Web\\JnetUnitWorkspaceController::class, '" . $method . "'], [AuthMiddleware::class]);", $routes, $path);
            self::assertStringNotContainsString("\$router->get('" . $path . "'", $routes, $path);
        }
        self::assertLessThan(strpos($routes, "'/jnet/u/{id}/pages/{pid}'"), strpos($routes, "'/jnet/u/{id}/pages/ordre'"), 'la route fixe passe avant {pid}');
        $ctl = $this->read('app/Controllers/Web/JnetUnitWorkspaceController.php');
        self::assertSame(15, preg_match_all('/\$this->guard\(\$request, \$params,/', $ctl), 'chaque écriture passe par le même garde-fou (CSRF, droits)');
        self::assertStringContainsString('application/json', $ctl);
        // Le partage et la relance ne se fient à rien venu du navigateur : destinations et destinataires sont recalculés.
        self::assertStringContainsString('shareTargets(', $ctl);
        self::assertStringContainsString('unansweredIds', $this->read('app/Services/Jnet/JnetUnitWorkspaceService.php'));
        self::assertStringNotContainsString("input('recipients'", $ctl);
        self::assertStringContainsString("'info'", $ctl, 'un partage part comme une information, jamais comme un ordre');
        self::assertSame(0, preg_match("/post\\([^;]*'ordre'/", $ctl));
        // Les formulaires de la page portent leur jeton.
        foreach (['unit/_share_form.php', 'unit/_gate.php', 'unit/_tracking.php'] as $form) {
            $src = $this->read('views/jnet/' . $form);
            self::assertSame(substr_count($src, 'method="post"'), substr_count($src, 'Csrf::field()'), $form);
        }
    }

    public function testRequiredAcknowledgementIsABlockingDialogWithAnInlineFallback(): void
    {
        $gate = $this->read('views/jnet/unit/_gate.php');
        self::assertStringContainsString('<dialog class="uw-gate" data-uw-gate open', $gate, 'ouvert sans JavaScript : bandeau en ligne');
        self::assertStringContainsString('J’ai pris connaissance', $gate);
        self::assertStringContainsString("url('jnet/echanges/' . (int) \$e['id'] . '/lu')", $gate, 'même accusé de lecture que partout');
        self::assertStringNotContainsString('Fermer', $gate, 'aucune autre sortie que l’accusé');
        $js = $this->read('public/assets/js/jnet_unit_workspace.js');
        self::assertStringContainsString("gate.addEventListener('cancel', function (e) { e.preventDefault(); });", $js, 'Échap ne ferme pas');
        self::assertStringContainsString('gate.showModal()', $js);
        $css = $this->read('public/assets/css/jnet_unit_workspace.css');
        self::assertStringContainsString('.uw-gate:modal', $css);
        $portal = $this->read('app/Controllers/Web/JnetPortalController.php');
        self::assertStringContainsString('application/json', $portal, 'l’accusé peut être envoyé sans rechargement');
    }

    public function testProgressiveEnhancementKeepsTheFormFallbacks(): void
    {
        $js = $this->read('public/assets/js/jnet_unit_workspace.js');
        foreach (['data-uw-kanban', 'data-uw-reorder', 'data-uw-blocks', 'data-uw-photo', 'data-uw-share', 'data-uw-remind', 'localStorage', "e.key === 'Backspace'", "e.key === '/'", "e.key === 'Enter'", 'ArrowLeft', 'ArrowRight', "'X-Requested-With'", '_csrf_token'] as $needle) {
            self::assertStringContainsString($needle, $js, $needle);
        }
        self::assertStringNotContainsString('innerHTML', $js);
        self::assertStringNotContainsString('alert(', $js);
        self::assertStringNotContainsString('confirm(', $js);
        // Les boutons du repli restent dans les vues.
        $tasks = $this->read('views/jnet/unit/_tasks.php');
        self::assertStringContainsString('name="act" value="status"', $tasks);
        self::assertStringContainsString('data-uw-kanban', $tasks);
        $pages = $this->read('views/jnet/unit/_pages.php');
        self::assertStringContainsString('aria-label="Monter la page"', $pages);
        $page = $this->read('views/jnet/unit/_page.php');
        self::assertStringContainsString('value="up:<?= $i ?>"', $page);
        self::assertStringContainsString('data-uw-block-tpl', $page);
        // Le mot « Entrée » ou « Échap » n'est jamais un petit libellé au-dessus d'un titre.
        foreach (['views/jnet/unit/_gate.php', 'views/jnet/unit/_share.php', 'views/jnet/unit/_share_form.php', 'views/jnet/unit/_files.php', 'views/jnet/customize.php'] as $file) {
            self::assertStringNotContainsString('class="kk"', $this->read($file), $file);
            self::assertSame(0, preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $this->read($file)), 'aucun emoji : ' . $file);
        }
    }

    public function testPhotosAndDocumentsFollowTheDesignWithStripedPlaceholdersAndRealSources(): void
    {
        $view = $this->read('views/jnet/unit/_files.php');
        foreach (['uw-drop--tile', 'uw-drop--bar', 'uw-stripe', 'photo à déposer', 'document à déposer', 'ja-chip', 'data-uw-photo', 'Déposer'] as $needle) {
            self::assertStringContainsString($needle, $view, $needle);
        }
        self::assertStringNotContainsString('jn-empty', $view, 'plus de cadre vide : des cases rayées');
        $svc = $this->read('app/Services/Jnet/JnetSpaceService.php');
        self::assertStringContainsString('function unitMedia', $svc);
        self::assertStringContainsString('SOURCE_IMAGE', $svc);
        self::assertStringContainsString("\$image !== ''", $svc, 'un cliché protégé (sans vignette) n’est jamais montré');
        self::assertStringContainsString('visibleExchanges(', $svc, 'mêmes filtres de visibilité que les échanges');
        self::assertStringContainsString('libraryDocuments', $svc);
        $css = $this->read('public/assets/css/jnet_unit_workspace.css');
        self::assertStringContainsString('repeating-linear-gradient(135deg, #0f1412 0 12px, #151b1a 12px 24px)', $css);
        self::assertStringContainsString('.uw-viewer', $css);
        $js = $this->read('public/assets/js/jnet_unit_workspace.js');
        self::assertStringContainsString('Déposée par', $js);
    }
}
