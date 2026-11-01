<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\AdminOverwatchServerControlController as Controller;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Session;
use App\Services\Tactical\AtakBridgeModulesService;
use App\Services\Tactical\AtakExperienceService;
use App\Support\ServerControlPresenter as P;
use App\Support\ServerControlWiring as W;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Contrôle de mission : chaque réglage est tracé jusqu'au jeu, s'enregistre, s'affiche avec son état réel,
 * et la page reste réservée aux gestionnaires ATAK.
 */
final class ServerControlPageTest extends TestCase
{
    private ?PDO $savedPdo = null;
    private bool $savedStarted = false;
    /** @var array<string, mixed> */
    private array $savedSession = [];
    /** @var array<string, mixed> */
    private array $savedServer = [];

    protected function setUp(): void
    {
        $db = new \ReflectionProperty(Database::class, 'pdo');
        $saved = $db->getValue();
        $this->savedPdo = $saved instanceof PDO ? $saved : null;
        $db->setValue(null, new PDO('sqlite::memory:'));
        $started = new \ReflectionProperty(Session::class, 'started');
        $this->savedStarted = (bool) $started->getValue();
        $started->setValue(null, true);
        $this->savedSession = $_SESSION ?? [];
        $_SESSION = ['_csrf_token' => str_repeat('a', 64)];
        $this->savedServer = $_SERVER;
    }

    protected function tearDown(): void
    {
        Gate::reset();
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->savedPdo);
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->savedStarted);
        $_SESSION = $this->savedSession;
        $_SERVER = $this->savedServer;
        $_POST = [];
    }

    private static function root(string $rel = ''): string
    {
        return dirname(__DIR__, 2) . ($rel !== '' ? '/' . $rel : '');
    }

    public function testEverySettingOfThePageIsTraced(): void
    {
        $exp = new AtakExperienceService();
        $rules = W::rules();
        $modules = W::modules();
        self::assertEqualsCanonicalizing(array_column($exp->catalog(), 'id'), array_keys($rules), 'Chaque règle du catalogue a son tracé, et rien de plus.');
        self::assertEqualsCanonicalizing(array_column((new AtakBridgeModulesService())->catalog(), 'id'), array_keys($modules));

        foreach ($rules + $modules as $id => $w) {
            self::assertArrayHasKey($w['state'], W::STATES, $id);
            self::assertArrayHasKey($w['theme'], W::THEMES, $id);
            self::assertNotSame('', trim($w['impact']), $id);
            if ($w['state'] === 'todo') {
                self::assertNull($w['reader'], $id . ' : un réglage sans effet n’a pas de lecteur.');
                self::assertNull($w['delay'], $id);
                continue;
            }
            self::assertArrayHasKey((string) $w['delay'], W::DELAYS, $id);
            self::assertNotNull($w['reader'], $id);
            self::assertFileExists(self::root((string) $w['reader']), $id);
        }
    }

    /** Le lecteur indiqué lit toujours la clé (ou la variable du jeu qui la reçoit) : sinon le badge « Actif » ment. */
    public function testReadersStillReadTheirSetting(): void
    {
        $apply = (string) file_get_contents(self::root('mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_applyTenantExperience.sqf'));
        preg_match_all('/\["([a-z_]+)", "([a-z_]+)"\] call _applyTri/', $apply, $m, PREG_SET_ORDER);
        $cba = [];
        foreach ($m as $pair) {
            $cba[$pair[1]] = $pair[2];
        }
        $ext = (string) file_get_contents(self::root('mod/UptoDate/COMSPECExtension/Extension.cs'));
        foreach (W::rules() as $id => $w) {
            if ($w['reader'] === null) {
                continue;
            }
            $src = (string) file_get_contents(self::root($w['reader']));
            $hit = str_contains($src, $id) || (isset($cba[$id]) && str_contains($src, $cba[$id]));
            self::assertTrue($hit, $id . ' n’est plus lu par ' . $w['reader']);
            // La DLL relaie la clé au jeu (liste fixe, ou toutes les clés net_*).
            self::assertTrue(str_starts_with($id, 'net_') || str_contains($ext, '"' . $id . '"') || in_array($id, ['realism', 'troll'], true), $id . ' n’est pas relayé par COMSPEC Link');
        }
        foreach (W::modules() as $id => $w) {
            if ($w['reader'] !== null) {
                self::assertStringContainsString($id, (string) file_get_contents(self::root($w['reader'])), $id);
            }
        }
        $apps = (string) file_get_contents(self::root(W::APPS['reader']));
        self::assertStringContainsString('net_apps_required', $apps);
        self::assertStringContainsString('net_apps_forbidden', $apps);
    }

    /** Les réglages rangés dans « Bientôt » n'ont vraiment aucun lecteur dans le mod maintenu (ATAK natif + connect). */
    public function testSoonSettingsHaveNoReaderInTheMaintainedMod(): void
    {
        $code = '';
        foreach (['mod/COMSPEC_ATAK_Native/Sources', 'mod/UptoDate/Sources/comspec-overwatch-addons/connect', 'mod/UptoDate/Sources/comspec-overwatch-addons/main'] as $dir) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::root($dir), \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                $name = $file->getFilename();
                if (str_ends_with($name, '.sqf') && !in_array($name, ['fn_applyTenantExperience.sqf', 'fn_tenantApply.sqf', 'fn_pageSettings.sqf', 'XEH_preInit.sqf', 'fn_pauseManagerJSDialog.sqf', 'fn_pauseManagerPageLoaded.sqf', 'fn_logDump.sqf'], true)) {
                    $code .= (string) file_get_contents($file->getPathname());
                }
            }
        }
        foreach (['comspec_overwatch_athena_feed_snapshot', 'comspec_overwatch_roleplay_sensor_failures', 'comspec_overwatch_ace_menus'] as $var) {
            self::assertStringNotContainsString($var, $code, $var . ' est lu en jeu : mettre à jour ServerControlWiring.');
        }
        foreach (W::modules() as $id => $w) {
            if ($w['state'] === 'todo') {
                self::assertDoesNotMatchRegularExpression('/\["' . $id . '"\] call [a-z_]+fnc_isModModuleEnabled|getOrDefault \["' . $id . '", true\]/', $code, $id . ' est lu en jeu : mettre à jour ServerControlWiring.');
            }
        }
    }

    public function testThemesCountsBadgesAndPreview(): void
    {
        $exp = new AtakExperienceService();
        $rules = [];
        foreach ($exp->catalog() as $row) {
            $row['value'] = $row['default'];
            $rules[] = $row;
        }
        $rules[array_search('native_compass', array_column($rules, 'id'), true)]['value'] = 'off';
        $mods = array_map(static fn (array $r): array => $r + ['enabled' => $r['id'] !== 'route_planning'], (new AtakBridgeModulesService())->catalog());
        $view = P::themes($rules, $mods, ['2.0.1.AB', '2.9.21.AG']);

        self::assertSame(count($rules) + count($mods), array_sum($view['counts']));
        $soonIds = array_column($view['soon'], 'id');
        foreach (['athena_feed', 'ace_menus', 'rp_sensor_failures', 'weather', 'iceman_bda'] as $id) {
            self::assertContains($id, $soonIds);
        }
        self::assertSame(count($view['soon']), $view['counts']['todo']);
        $all = array_merge(...array_map(static fn (array $t): array => $t['items'], array_values($view['themes'])));
        $byId = array_column($all, null, 'id');
        self::assertSame('Actif en jeu', $byId['native_compass']['state_label']);
        self::assertSame('Partiel', $byId['show_opfor']['state_label']);
        self::assertSame('Moins de 2 minutes', $byId['native_compass']['delay_label']);
        self::assertTrue($byId['native_compass']['imposed']);
        self::assertSame(1, $byId['native_compass']['below_min'], 'Un téléphone en 2.0.1 est plus ancien que 2.1.0.');
        self::assertSame(1, $byId['net_sim']['below_min']);
        self::assertFalse($byId['net_sim']['imposed']);

        $preview = P::preview($view, ['Dating' => 'forbidden', 'Map' => 'required'], $exp->phoneApps());
        $labels = array_column($preview, 'value', 'label');
        self::assertSame('Toujours masquer', $labels['Boussole sur la carte du téléphone']);
        self::assertSame('Désactivé', $labels['Planification d’itinéraire']);
        self::assertSame('Tinder', $labels['Apps interdites']);
        self::assertSame('Carte', $labels['Apps obligatoires']);
    }

    public function testEveryRuleIsSavedFromTheForm(): void
    {
        $exp = new AtakExperienceService();
        $post = [];
        $expected = [];
        foreach ($exp->catalog() as $row) {
            if ($row['type'] === 'bool') {
                $post['experience_' . $row['id']] = '1';
                $expected[$row['id']] = true;
                continue;
            }
            $values = array_column($row['choices'], 'value');
            $pick = (string) end($values) !== (string) $row['default'] ? (string) end($values) : (string) $values[1];
            $post['experience_' . $row['id']] = $pick;
            $expected[$row['id']] = $pick;
        }
        $in = Controller::incomingFromPost($post, $exp->catalog(), [], $exp->phoneApps());
        $merged = $exp->mergeSettings($exp->defaults(), $in['rules']);
        $expected['troll'] = false; // réalisme et troll s'excluent
        foreach ($expected as $id => $value) {
            self::assertSame($value, $merged[$id], $id);
        }
        // Un champ absent ne change rien ; une valeur inconnue reprend le défaut.
        $kept = $exp->mergeSettings($merged, Controller::incomingFromPost(['experience_net_sim' => 'piraté'], $exp->catalog(), [], [])['rules']);
        self::assertSame('player', $kept['net_sim']);
        self::assertSame($merged['native_compass'], $kept['native_compass']);
    }

    public function testModulesAndAppsAreParsedFieldByField(): void
    {
        $exp = new AtakExperienceService();
        $mods = new AtakBridgeModulesService();
        $in = Controller::incomingFromPost(['module_geo_roads' => '0', 'module_ecoti_hud' => '1', 'app_Dating' => 'forbidden', 'app_Map' => ['x']], [], $mods->catalog(), $exp->phoneApps());
        self::assertSame(['geo_roads' => false, 'ecoti_hud' => true], $in['modules']);
        self::assertSame(['Dating' => 'forbidden'], $in['apps']);
        self::assertSame(['Dating' => 'forbidden'], $exp->sanitizeAppPolicy($in['apps'] + ['Map' => 'nimporte']));

        $tenant = 987654;
        $file = self::root('storage/cache/atak-modules/t' . $tenant . '.json');
        try {
            $mods->put($tenant, $in['modules']);
            $state = $mods->get($tenant)['modules'];
            self::assertFalse($state['geo_roads']);
            self::assertTrue($state['route_planning'], 'Un module absent du formulaire garde son état.');
        } finally {
            @unlink($file);
        }
    }

    public function testChangesAndJournalAreReadable(): void
    {
        $items = [
            'rule:realism' => ['label' => 'Mode réalisme', 'type' => 'bool', 'default' => false],
            'rule:native_compass' => ['label' => 'Boussole', 'type' => 'tri', 'default' => 'player', 'choices' => [['value' => 'player', 'label' => 'Au choix'], ['value' => 'off', 'label' => 'Toujours masquer']]],
            'module:geo_roads' => ['label' => 'Réseau routier', 'type' => 'module', 'default' => true],
        ];
        $changes = P::changes($items, ['rule:realism' => false, 'rule:native_compass' => 'player'], ['rule:realism' => true, 'rule:native_compass' => 'player', 'module:geo_roads' => false]);
        self::assertSame([
            ['label' => 'Mode réalisme', 'from' => 'Désactivé', 'to' => 'Activé'],
            ['label' => 'Réseau routier', 'from' => 'Activé', 'to' => 'Désactivé'],
        ], $changes);

        $journal = P::journal([[
            'action' => Controller::AUDIT_UPDATED,
            'old_value' => json_encode(array_column($changes, 'from', 'label')),
            'new_value' => json_encode(array_column($changes, 'to', 'label')),
            'actor_callsign' => 'Ghost',
            'created_at' => '2026-11-01 10:00:00',
        ]]);
        self::assertSame('Ghost', $journal[0]['who']);
        self::assertSame('a modifié 2 réglages', $journal[0]['title']);
        self::assertSame('Mode réalisme : Désactivé → Activé', $journal[0]['changes'][0]);
    }

    public function testPresenceSummary(): void
    {
        $now = gmmktime(12, 0, 0, 11, 1, 2026);
        $at = static fn (int $ago): string => gmdate('Y-m-d H:i:s', $now - $ago);
        $p = P::presence([
            ['last_seen_at' => $at(120), 'mod_version' => '2.9.21.AG', 'extension_version' => '2.0.111'],
            ['last_seen_at' => $at(300), 'mod_version' => '2.9.21.AG', 'extension_version' => '2.0.111'],
            ['last_seen_at' => $at(3 * 86400), 'mod_version' => '2.8.3.AB', 'extension_version' => '2.0.99'],
            ['last_seen_at' => $at(40 * 86400), 'mod_version' => '1.0.0'],
            ['last_seen_at' => null],
        ], [['server_name' => 'COMSPEC #1', 'mission' => 'Op Ardoise', 'world' => 'Altis', 'seen_at' => $at(60)]], $now);
        self::assertTrue($p['has_data']);
        self::assertSame(2, $p['online']);
        self::assertSame(3, $p['recent']);
        self::assertSame($now - 60, $p['last_seen']);
        self::assertSame('2.9.21.AG', $p['mod_top']);
        self::assertSame('2.8.3.AB', $p['mod_oldest']);
        self::assertSame('2.0.111', $p['link_top']);
        self::assertSame('COMSPEC #1', $p['server']);
        self::assertSame(1, P::countBelow($p['versions'], '2.9.0'));
        self::assertFalse(P::presence([], [], $now)['has_data']);
    }

    public function testPageRendersStatesInPlainLanguage(): void
    {
        $exp = new AtakExperienceService();
        $rules = array_map(static fn (array $r): array => $r + ['value' => $r['default']], $exp->catalog());
        $mods = array_map(static fn (array $r): array => $r + ['enabled' => true], (new AtakBridgeModulesService())->catalog());
        $view = P::themes($rules, $mods, []);
        $vars = [
            'csrfToken' => str_repeat('a', 64),
            'experienceSchemaReady' => true,
            'themes' => $view['themes'],
            'soon' => $view['soon'],
            'stateCounts' => $view['counts'],
            'preview' => P::preview($view, ['Dating' => 'forbidden'], $exp->phoneApps()),
            'presence' => P::presence([['last_seen_at' => gmdate('Y-m-d H:i:s'), 'mod_version' => '2.9.21.AG']], [], time()),
            'now' => time(),
            'netLive' => ['conditions' => ['net_sim' => 'off'], 'applied_by' => 'Zeus', 'at' => gmdate('c')],
            'phoneApps' => $exp->phoneApps(),
            'appsPolicy' => ['Dating' => 'forbidden'],
            'relays' => [['display_name' => 'Mât 1', 'alive' => 1, 'ip_addr' => '10.0.0.1']],
            'journal' => [],
            'success' => '1 réglage enregistré : Mode réalisme.',
        ];
        $html = (static function (array $__vars): string {
            extract($__vars, EXTR_SKIP);
            ob_start();
            require dirname(__DIR__, 2) . '/views/admin/atak/server_control.php';

            return (string) ob_get_clean();
        })($vars);

        foreach (['Actif en jeu', 'Partiel', 'Pas encore branché', 'Moins de 2 minutes', 'À la prochaine connexion d’un joueur', 'Au prochain démarrage de mission', 'Immédiat', 'ATAK natif 2.3.0 ou plus récent', '<details class="sc-card sc-soon" id="bientot">', 'id="apps"', 'name="app_Dating"', 'name="module_geo_roads"', 'name="experience_net_pli"', 'Conditions appliquées en jeu', 'Exiger un relais pour la liaison', 'adresse réseau', 'joueur en jeu en ce moment', 'Journal des changements', '1 réglage enregistré'] as $needle) {
            self::assertStringContainsString($needle, $html, $needle);
        }
        self::assertStringContainsString("controle-serveur/regles", $html);
        $visible = strtolower(html_entity_decode(strip_tags(preg_replace('#<script.*?</script>#s', '', $html) ?? '')));
        foreach (['json', 'endpoint', 'net_', 'experience_', 'cba', 'sqf', 'api'] as $jargon) {
            self::assertStringNotContainsString($jargon, $visible, 'Jargon visible : ' . $jargon);
        }
    }

    public function testOnlyAtakManagersReachThePageOrSave(): void
    {
        $_SESSION['tenant_id'] = 7;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['_csrf_token' => str_repeat('a', 64), 'experience_realism' => '1'];
        Gate::getInstance()->setPermissions(['admin.members.view']); // aucun droit ATAK
        $ctrl = new Controller();

        $get = $ctrl->index(new Request());
        self::assertSame(302, $get->statusCode());
        self::assertStringContainsString('back-office/atak', (string) $get->headerValue('Location'));

        $post = $ctrl->storeRules(new Request());
        self::assertSame(302, $post->statusCode());
        self::assertStringNotContainsString('controle-serveur', (string) $post->headerValue('Location'), 'Refusé avant tout enregistrement.');
        self::assertStringContainsString('droits', (string) ($_SESSION['_flash']['error'] ?? ''));

        $_POST['_csrf_token'] = 'mauvais';
        Gate::getInstance()->setPermissions(['atak.terminals.manage']);
        $expired = $ctrl->storeRules(new Request());
        self::assertStringContainsString('rien n’a été enregistré', (string) ($_SESSION['_flash']['error'] ?? ''));
        self::assertSame(302, $expired->statusCode());

        unset($_SESSION['tenant_id']);
        self::assertStringContainsString('login', (string) $ctrl->index(new Request())->headerValue('Location'));
    }

    public function testRoutesAndRightsAreUnchanged(): void
    {
        $routes = (string) file_get_contents(self::root('routes/web.php'));
        foreach (['', '/regles', '/reseau-adopter', '/fonctions', '/apps'] as $suffix) {
            self::assertMatchesRegularExpression('#\'/back-office/atak/controle-serveur' . preg_quote($suffix, '#') . '\', \[[^\]]+\], \[AuthMiddleware::class, TenantResourceAdminMiddleware::class\]#', $routes, $suffix);
        }
        $label = \App\Services\Audit\AuditActionLabel::toFrench(Controller::AUDIT_UPDATED);
        self::assertSame('Contrôle de mission : réglages modifiés', $label);
    }
}
