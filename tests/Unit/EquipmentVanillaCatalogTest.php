<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\GameItemSpecRepository;
use App\Services\GameEquipment\Configurator\ConfiguratorCatalog;
use App\Services\GameEquipment\Configurator\VanillaCatalog;
use App\Services\GameEquipment\Configurator\VanillaCatalogLoader;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Catalogue de base d'Arma 3 (database/data/arma3_catalogue_vanilla.json) : fichier livré compact et complet, chargement
 * paresseux par lots au premier accès, idempotent, jamais par-dessus une fiche importée, isolé par communauté ; droits
 * de l'action ; noms FR / EN ; interface (guide, établi, tenues, vue d'ensemble) et ses assets.
 */
final class EquipmentVanillaCatalogTest extends TestCase
{
    private static function db(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("CREATE TABLE game_item_specs (
            tenant_id INTEGER NOT NULL, class_name TEXT NOT NULL, root TEXT NOT NULL DEFAULT 'w', type_main TEXT NOT NULL DEFAULT '',
            type_sub TEXT NOT NULL DEFAULT '', name TEXT NOT NULL DEFAULT '', mass REAL, max_load INTEGER, slot_type INTEGER,
            ammo_count INTEGER, allowed_slots TEXT NOT NULL DEFAULT '', compat_json TEXT, mags_json TEXT, mod_name TEXT NOT NULL DEFAULT '',
            imported_by INTEGER, imported_at TEXT NOT NULL, source TEXT NOT NULL DEFAULT 'import', name_en TEXT NOT NULL DEFAULT '',
            PRIMARY KEY (tenant_id, class_name))");
        $pdo->exec("CREATE TABLE game_item_vanilla_loads (tenant_id INTEGER PRIMARY KEY, version TEXT NOT NULL DEFAULT '',
            total INTEGER NOT NULL DEFAULT 0, done INTEGER NOT NULL DEFAULT 0, started_at TEXT NOT NULL, finished_at TEXT)");

        return $pdo;
    }

    /** @return array<string, int> */
    private static function counts(PDO $pdo, int $tenant): array
    {
        $st = $pdo->prepare('SELECT source, COUNT(*) FROM game_item_specs WHERE tenant_id = ? GROUP BY source');
        $st->execute([$tenant]);

        return array_map('intval', $st->fetchAll(PDO::FETCH_KEY_PAIR) ?: []);
    }

    public function testShippedFileIsCompactAndCoversEveryFamily(): void
    {
        $path = VanillaCatalog::defaultPath();
        self::assertFileExists($path);
        self::assertLessThan(3 * 1024 * 1024, filesize($path), 'fichier livré < 3 Mo');
        $raw = (string) file_get_contents($path);
        self::assertDoesNotMatchRegularExpression('/\.(paa|p3d|wss|ogg)\b/i', $raw, 'aucun chemin de fichier du jeu : métadonnées seulement');
        VanillaCatalog::forget();
        $file = VanillaCatalog::load();
        self::assertTrue($file['ok']);
        self::assertGreaterThan(1000, count($file['rows']));
        $families = VanillaCatalog::familyCounts($file['rows']);
        foreach (['primary' => 100, 'handgun' => 8, 'secondary' => 10, 'optic' => 30, 'muzzle' => 15, 'pointer' => 3, 'bipod' => 8,
            'magazine' => 100, 'uniform' => 100, 'vest' => 50, 'backpack' => 100, 'headgear' => 100, 'goggles' => 30, 'nvg' => 5,
            'binocular' => 3, 'radio' => 1, 'map' => 1, 'gps' => 1, 'compass' => 1, 'watch' => 1, 'medical' => 2] as $kind => $min) {
            self::assertGreaterThanOrEqual($min, $families[$kind] ?? 0, $kind);
        }
        $by = [];
        foreach ($file['rows'] as $r) {
            $by[strtolower($r['class_name'])] = $r;
        }
        $mx = $by['arifle_mx_f'];
        self::assertSame('MX 6,5 mm', str_replace("\u{a0}", ' ', $mx['name']), 'nom français');
        self::assertSame('MX 6.5 mm', str_replace("\u{a0}", ' ', $mx['name_en']), 'nom anglais');
        self::assertSame(1, $mx['slot_type']);
        self::assertContains('optic_Aco', $mx['compat']['optic']);
        self::assertContains('muzzle_snds_H', $mx['compat']['muzzle']);
        self::assertContains('30Rnd_65x39_caseless_mag', $mx['mags'], 'chargeurs (magazines[] + magazineWell[])');
        self::assertContains('1Rnd_HE_Grenade_shell', $by['arifle_mx_gl_f']['mags'], 'grenades du lance-grenades (muzzles[])');
        self::assertNotContains('optic_Aco', $by['hgun_p07_f']['compat']['optic'] ?? [], 'pas d’optique de fusil sur un pistolet');
        self::assertSame(140, $by['v_platecarrier1_rgr']['max_load'], 'contenance du gilet (containerClass >> maximumLoad)');
        self::assertSame(160, $by['b_assaultpack_mcamo']['max_load']);
        self::assertSame(30, $by['30rnd_65x39_caseless_mag']['ammo_count']);
        self::assertGreaterThan(0, (float) $by['u_b_combatuniform_mcam']['mass']);
        self::assertArrayNotHasKey('arifle_mx_aco_pointer_f', $by, 'variantes pré-équipées écartées (baseWeapon)');
    }

    public function testLazyLoadIsIdempotentInBatchesAndNeverOverwritesImportedRows(): void
    {
        $pdo = self::db();
        // Fiche importée en jeu par un responsable : le catalogue de base ne la touche jamais.
        $pdo->exec("INSERT INTO game_item_specs (tenant_id, class_name, root, type_main, type_sub, name, mass, imported_at, source)
            VALUES (7, 'arifle_MX_F', 'w', 'Weapon', 'AssaultRifle', 'MX de la communauté', 999, '2026-11-01 10:00:00', 'import')");
        $specs = new GameItemSpecRepository($pdo);
        $loader = new VanillaCatalogLoader($specs);
        $total = count(VanillaCatalog::load()['rows']);

        $status = $loader->status(7);
        self::assertTrue($status['available']);
        self::assertTrue($status['needed'], 'premier accès : chargement à faire');
        self::assertSame(0, $status['done']);

        $first = $loader->step(7);
        self::assertTrue($first['ok']);
        self::assertSame(min($total, VanillaCatalogLoader::BATCH), $first['done'], 'un lot par appel (avancement)');
        self::assertFalse($first['finished']);
        $steps = 1;
        $r = $first;
        while (!$r['finished'] && $steps < 50) {
            $r = $loader->step(7);
            $steps++;
        }
        self::assertTrue($r['finished']);
        self::assertSame((int) ceil($total / VanillaCatalogLoader::BATCH), $steps);
        self::assertFalse($loader->status(7)['needed'], 'chargé : plus rien à faire au prochain accès');

        $counts = self::counts($pdo, 7);
        self::assertSame(1, $counts['import']);
        self::assertSame($total - 1, $counts['vanilla']);
        $mx = $pdo->query("SELECT name, mass, source FROM game_item_specs WHERE tenant_id = 7 AND class_name = 'arifle_MX_F'")->fetch(PDO::FETCH_ASSOC);
        self::assertSame(['name' => 'MX de la communauté', 'mass' => 999.0, 'source' => 'import'], ['name' => $mx['name'], 'mass' => (float) $mx['mass'], 'source' => $mx['source']]);

        // Fini : un nouvel appel ne réécrit rien ; « Recharger » réécrit les mêmes fiches (idempotent).
        $again = $loader->step(7);
        self::assertSame(0, $again['written']);
        $reload = $loader->loadAll(7, true);
        self::assertTrue($reload['finished']);
        self::assertSame($counts, self::counts($pdo, 7));
        self::assertSame('MX de la communauté', $pdo->query("SELECT name FROM game_item_specs WHERE tenant_id = 7 AND class_name = 'arifle_MX_F'")->fetchColumn());

        // Une autre communauté n'est pas touchée.
        self::assertSame([], self::counts($pdo, 8));
        self::assertTrue($loader->status(8)['needed']);
    }

    public function testNewFileVersionReloadsAndPrunesRemovedBaseRows(): void
    {
        $pdo = self::db();
        $specs = new GameItemSpecRepository($pdo);
        $loader = new VanillaCatalogLoader($specs);
        $loader->loadAll(3);
        $pdo->exec("INSERT INTO game_item_specs (tenant_id, class_name, name, imported_at, source) VALUES (3, 'old_removed_item', 'Ancien', '2026-01-01', 'vanilla')");
        $specs->saveVanillaState(3, 'ancienne-version', 10, 10, true);
        self::assertTrue($loader->status(3)['needed'], 'fichier livré changé : rechargé au prochain accès');
        $loader->loadAll(3);
        self::assertSame(0, (int) $pdo->query("SELECT COUNT(*) FROM game_item_specs WHERE class_name = 'old_removed_item'")->fetchColumn());
        self::assertFalse($loader->status(3)['needed']);
    }

    public function testBaseRowsUseTheSiteLanguageAndYieldToTheNameSeenInGame(): void
    {
        $row = ['class_name' => 'arifle_MX_F', 'root' => 'w', 'type_main' => 'Weapon', 'type_sub' => 'AssaultRifle', 'name' => 'MX 6,5 mm', 'name_en' => 'MX 6.5 mm',
            'mass' => 100, 'max_load' => null, 'slot_type' => 1, 'ammo_count' => null, 'allowed_slots' => '901', 'compat_json' => null, 'mags_json' => null, 'mod_name' => 'Arma 3', 'source' => 'vanilla'];
        self::assertSame('MX 6,5 mm', ConfiguratorCatalog::merge([$row], [], 'fr')['arifle_mx_f']['name']);
        self::assertSame('MX 6.5 mm', ConfiguratorCatalog::merge([$row], [], 'en')['arifle_mx_f']['name']);
        $seen = [['class' => 'arifle_MX_F', 'name' => 'MX (vu en jeu)', 'kind' => 'primary', 'players' => 3]];
        $merged = ConfiguratorCatalog::merge([$row], $seen, 'en')['arifle_mx_f'];
        self::assertSame('MX (vu en jeu)', $merged['name'], 'le nom vu en jeu passe avant');
        self::assertSame(3, $merged['players']);
        self::assertTrue($merged['base']);
        $compact = ConfiguratorCatalog::compact(['arifle_mx_f' => $merged]);
        self::assertSame(1, $compact['items'][0]['b']);
        self::assertSame(1, $compact['stats']['base']);
        $imported = ConfiguratorCatalog::merge([['source' => 'import'] + $row], $seen, 'en')['arifle_mx_f'];
        self::assertSame('MX 6,5 mm', $imported['name'], 'fiche importée : son nom');
        self::assertFalse($imported['base']);
    }

    public function testActionRightsTenantAndImportReplaceKeepTheBase(): void
    {
        $root = dirname(__DIR__, 2);
        $src = (string) file_get_contents($root . '/app/Controllers/Web/EquipmentConfiguratorController.php');
        $start = (int) strpos($src, 'function baseCatalog(');
        $body = substr($src, $start, (int) strpos($src, "\n    /**", $start) - $start);
        self::assertStringContainsString('$this->gate(true)', $body, 'session et formule');
        self::assertStringContainsString('Csrf::validate', $body);
        self::assertStringContainsString('$restart && !$this->canManageCatalog()', $body, 'tout recharger : gestion du catalogue');
        self::assertStringContainsString('EquipmentAudit::log', $body);
        self::assertStringNotContainsString("input('tenant", $body);
        self::assertStringContainsString("'base' => \$this->vanillaStatus(\$tenantId)", $src, 'état passé à la page');
        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertStringContainsString("\$router->post('/equipment/configurateur/catalogue/base'", $routes);
        $repo = (string) file_get_contents($root . '/app/Repositories/GameItemSpecRepository.php');
        self::assertStringContainsString("source <> 'vanilla'", $repo, 'remplacer un import garde le catalogue de base');
        self::assertStringContainsString("source = 'import'", $repo, 'un import remplace une fiche de base');
        $step = (string) file_get_contents($root . '/database/steps/4290_equipment_vanilla_catalog.php');
        self::assertStringContainsString("addColumnIfMissing('game_item_specs', 'source'", $step);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS game_item_vanilla_loads', $step);
        self::assertStringContainsString('tenant_id INT UNSIGNED NOT NULL', $step);
        $registry = require $root . '/database/steps/registry.php';
        $found = array_values(array_filter($registry, static fn (array $s): bool => ($s['file'] ?? '') === '4290_equipment_vanilla_catalog.php'));
        self::assertCount(1, $found);
        self::assertTrue($found[0]['rerun']);
        $tool = (string) file_get_contents($root . '/tools/arma/extraire_catalogue_vanilla.py');
        self::assertStringContainsString('compatibleItems', $tool);
        self::assertStringContainsString('magazineWell', $tool);
        self::assertStringNotContainsString('urllib', $tool, 'extraction hors ligne');
    }

    public function testInterfaceAssetsGuideBenchOutfitsAndOverview(): void
    {
        $root = dirname(__DIR__, 2);
        $js = (string) file_get_contents($root . '/public/assets/js/equipment-configurator.js');
        foreach (['AthenaEquipmentBase', 'data-cfg-base', "'athena.cfg.tour.v2.' + String(boot.user", 'loadExample', 'previewSlot', 'data-bench-slot',
            "data-cfg-drop=\"bench\"", 'Choisissez une arme à gauche', 'data-sel-copy', 'Mettre dans le sac', 'role="meter"', 'aria-selected'] as $needle) {
            self::assertStringContainsString($needle, $js, $needle);
        }
        $base = (string) file_get_contents($root . '/public/assets/js/equipment-base-catalog.js');
        self::assertStringContainsString("_csrf_token", $base);
        self::assertStringContainsString("credentials: 'same-origin'", $base);
        foreach ([$js, $base] as $code) {
            self::assertDoesNotMatchRegularExpression('#https?://#', $code, 'aucune ressource externe');
            self::assertStringNotContainsString('eval(', $code);
        }
        $css = (string) file_get_contents($root . '/public/assets/css/equipment-configurator.css');
        foreach (['.cfg-tour__step', '@keyframes cfg-tour-drag', '.cfg-bslot.is-bad', '.cfg-bslot.is-ok', '.cfg-cell.is-selected', '.cfg-load--over',
            'prefers-reduced-motion', '[data-cfg-theme="dark"]', '@media (max-width: 560px)', 'focus-visible'] as $needle) {
            self::assertStringContainsString($needle, $css, $needle);
        }
        self::assertDoesNotMatchRegularExpression('#url\(\s*["\']?https?://#', $css);
        $view = (string) file_get_contents($root . '/views/equipment/configurator.php');
        foreach (['data-cfg-act="help"', 'Essayer sur un exemple', 'Établi d’arme', 'Tenue et sacs', 'Recharger le catalogue de base', 'role="progressbar"', 'equipment-base-catalog.js'] as $needle) {
            self::assertStringContainsString($needle, $view, $needle);
        }
        self::assertSame(4, substr_count($view, '<li class="cfg-tour__step'), 'guide en 4 étapes');
        self::assertSame(4, substr_count($view, '<b>Exemple :</b>'), 'un exemple par étape');
        $overview = (string) file_get_contents($root . '/views/equipment/_overview.php');
        self::assertSame(4, substr_count($overview, '<article class="eqo-card'), '4 sections');
        self::assertSame(4, substr_count($overview, 'eqo-card__ex'), 'un exemple par section');
        self::assertStringContainsString('data-eq-base', $overview);
        $refonte = (string) file_get_contents($root . '/public/assets/css/equipment-refonte.css');
        self::assertStringContainsString('.eqo-card', $refonte);
    }
}
