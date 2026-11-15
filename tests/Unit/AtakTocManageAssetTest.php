<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Authorization\TenantPermissionCatalog;
use App\Services\AtakToc\Purge\TocElementStore;
use App\Services\AtakToc\TocAccess;
use App\Services\Rbac\AccessRightsCatalog;
use PHPUnit\Framework\TestCase;
use Tests\Support\ModVersion;

/**
 * Câblage du chantier « suppression, corbeille et purge de la carte du poste TOC » : vues et scripts branchés, aucune
 * confirmation native, règles d'interface (textes de 13 px, cibles de 44 px, pas de blocs empilés, pas de petit titre au-dessus
 * d'un titre), droit déclaré et jamais attribué d'office, migration et retour arrière, pierres tombales du jeu (site, DLL
 * COMSPEC Link, SQF) sans aucun message dans Arma, numéros de version alignés, journal des versions.
 */
final class AtakTocManageAssetTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    private function file(string $path): string
    {
        $raw = (string) file_get_contents(self::ROOT . '/' . $path);
        self::assertNotSame('', $raw, $path);

        return str_replace("\r\n", "\n", $raw);
    }

    public function testConsoleViewLoadsTheModuleAndHasTheMarkup(): void
    {
        $view = $this->file('views/atak/toc/console.php');
        foreach (['assets/css/atak-toc-manage.css', 'assets/js/toc/toc-manage.js'] as $asset) {
            self::assertStringContainsString($asset, $view);
            self::assertFileExists(self::ROOT . '/public/' . $asset);
        }
        // Le module se charge avant la console (scripts différés, dans l'ordre).
        self::assertLessThan(strpos($view, 'assets/js/toc/toc-console.js'), strpos($view, 'assets/js/toc/toc-manage.js'));
        foreach (['id="toc-manage"', 'id="toc-manage-btn"', 'id="toc-selbar"', 'id="toc-selrect"', 'id="toc-dlg"', 'id="toc-status-undo"', 'id="toc-purge-btn"', 'data-map="select"', 'id="toc-mg-items"', 'data-mg="view-trash"', 'data-sb="delete"', 'data-sb="pin"', 'data-sb="archive"'] as $needle) {
            self::assertStringContainsString($needle, $view, $needle);
        }
        self::assertStringContainsString('data-ui-confirm="1"', $view, 'dialogue du poste, pas de confirm() natif');
        self::assertStringContainsString('Annuler la suppression', $view);
        // Un titre est suivi de sa ligne de description ; jamais de petit libellé au-dessus.
        self::assertMatchesRegularExpression('#<h2 id="toc-manage-title">[^<]+</h2>.*?</div>\s*<p class="toc-manage-desc">#s', $view);
        self::assertDoesNotMatchRegularExpression('#eyebrow|kicker|overline#i', $view);
        self::assertStringNotContainsStringIgnoringCase('simulation', $view);
    }

    public function testNoNativeConfirmAnywhereInTheNewCode(): void
    {
        $manage = $this->file('public/assets/js/toc/toc-manage.js');
        self::assertDoesNotMatchRegularExpression('/\b(window\.)?(confirm|alert|prompt)\s*\(/', $manage);
        $console = $this->file('public/assets/js/toc/toc-console.js');
        self::assertStringNotContainsString("window.confirm('Supprimer le marqueur", $console);
        self::assertStringContainsString('S.manage.askDelete(', $console);
        self::assertStringNotContainsString("method: 'DELETE'", $console, 'le poste ne supprime plus par DELETE /api/markers : corbeille et annulation');
    }

    public function testModuleWiresSelectionKeyboardTrashUndoAndPurge(): void
    {
        $js = $this->file('public/assets/js/toc/toc-manage.js');
        foreach ([
            "'/atak/toc/api/elements/delete'", "'/atak/toc/api/elements/undo'", "'/atak/toc/api/elements/trash'", "'/atak/toc/api/elements/pin'", "'/atak/toc/api/elements/archive'",
            "'/atak/toc/api/purge/preview'", "'/atak/toc/api/purge/start'", "'/atak/toc/api/purge/step'", "'/atak/toc/api/purge/cancel'", "'/atak/toc/api/purge/status'", "'/atak/toc/api/purge/authors'",
            "e.key !== 'Delete'", 'pointerdown', 'pointermove', 'pointerup', 'PERMANENT', "=== 'PURGER'", 'confirmPinned', 'expected: P.preview', 'applyRoom', 'tickUndo', 'archiveFirst', 'keepPinned',
        ] as $needle) {
            self::assertStringContainsString($needle, $js, $needle);
        }
        // Aucune communauté dans les requêtes : seulement la carte.
        self::assertDoesNotMatchRegularExpression('/tenant_?[iI]d/', $js);
        $console = $this->file('public/assets/js/toc/toc-console.js');
        foreach (['TocManage.create', "c === 'select'", 'S.manage.applyRoom(j.map)', 'S.manage.escape()', 'onElements', 'elementActions', 'BOOT.canPurge', 'BOOT.softDelete'] as $needle) {
            self::assertStringContainsString($needle, $console, $needle);
        }
        $layers = $this->file('public/assets/js/toc/toc-layers.js');
        foreach (['elements: function ()', 'elems.marker', 'elems.shape', 'shapeKind(', 'opts.elementActions'] as $needle) {
            self::assertStringContainsString($needle, $layers, $needle);
        }
        $room = $this->file('app/Controllers/Api/AtakTocApiController.php');
        self::assertStringContainsString("'toc_map:'", $room);
        $boot = $this->file('app/Controllers/Web/AtakTocController.php');
        self::assertStringContainsString("'canPurge' => \$this->access->canPurge()", $boot);
    }

    public function testStylesheetKeepsTheInterfaceRules(): void
    {
        $css = $this->file('public/assets/css/atak-toc-manage.css');
        // Textes de 13 px au moins.
        preg_match_all('/font(?:-size)?\s*:\s*(?:[0-9]{3}\s+)?(?:[0-9.]+(?:px|em|rem)\s*\/\s*)?([0-9.]+)px/', $css, $m);
        foreach ($m[1] as $px) {
            self::assertGreaterThanOrEqual(13.0, (float) $px, 'police de ' . $px . ' px');
        }
        // Cibles tactiles de 44 px.
        self::assertStringContainsString('min-height: 44px', $css);
        self::assertMatchesRegularExpression('/\.toc-manage \.toc-btn[^{]*\{[^}]*min-height: 44px/', $css);
        self::assertStringContainsString('.toc-mg-chk { display: grid;', $css);
        self::assertMatchesRegularExpression('/\.toc-mg-chk[^{]*\{[^}]*min-height: 44px/', $css);
        self::assertMatchesRegularExpression('/\.toc-chip-check[^{]*\{[^}]*min-height: 44px/', $css);
        // Mobile : feuille plein écran et boîte de dialogue plein écran.
        self::assertStringContainsString('@media (max-width: 1100px)', $css);
        self::assertStringContainsString('@media (max-width: 640px)', $css);
        self::assertMatchesRegularExpression('/\.toc-manage \{ position: fixed; inset: 0/', $css);
        self::assertStringContainsString('.toc-dlg::backdrop', $css);
        self::assertStringNotContainsStringIgnoringCase('simulation', $css);
        self::assertStringNotContainsStringIgnoringCase('simulation', $this->file('public/assets/js/toc/toc-manage.js'));
    }

    public function testEnglishDictionaryCoversTheNewTextsAndKeepsThePhrases(): void
    {
        $dict = (array) require self::ROOT . '/lang/en/site_auto.php';
        foreach ([
            'Purger la carte', '%1 éléments supprimés · %2', 'Supprimer %1 éléments', 'Tapez PURGER pour confirmer', 'Tapez PERMANENT pour les supprimer aussi',
            'Purger la carte du poste TOC web (suppression en masse par périmètre)', 'Archiver la mission avant de purger', 'Conserver les éléments épinglés',
        ] as $fr) {
            self::assertArrayHasKey($fr, $dict, $fr);
            self::assertNotSame('', trim((string) $dict[$fr]));
        }
        // La phrase à taper reste la même dans les deux langues (le serveur compare PURGER et PERMANENT).
        self::assertStringContainsString('PURGER', (string) $dict['Tapez PURGER pour confirmer']);
        self::assertStringContainsString('PERMANENT', (string) $dict['Tapez PERMANENT pour les supprimer aussi']);
        self::assertStringContainsString('PURGER', (string) $dict['Toute la carte est concernée : tapez PURGER pour confirmer']);
        self::assertStringContainsString('Purger la carte', (string) file_get_contents(self::ROOT . '/public/assets/js/i18n_en_dict.js'), 'bundle du navigateur régénéré');
    }

    public function testPurgeRightIsDeclaredSensitiveDependentAndNeverGrantedByDefault(): void
    {
        self::assertContains('atak.toc.purge', TenantPermissionCatalog::allSlugs());
        self::assertSame('critical', AccessRightsCatalog::sensitivityFor('atak.toc.purge'));
        self::assertSame(['atak.toc.manage'], AccessRightsCatalog::requiresFor('atak.toc.purge'));
        self::assertSame('toc', AccessRightsCatalog::domainFor('atak.toc.purge'));
        $meta = AccessRightsCatalog::meta('atak.toc.purge');
        self::assertStringContainsString('Purger la carte', $meta['explanation']);
        // Aucun niveau d'accès ne le reçoit : ni la matrice des modules, ni l'étape qui crée le droit.
        self::assertStringNotContainsString('atak.toc.purge', $this->file('app/Services/Rbac/RolePermissionMatrixCatalog.php'));
        $step = $this->file('database/steps/4790_atak_toc_purge_permission.php');
        self::assertStringNotContainsString('role_permissions', $step);
        self::assertStringContainsString("'atak.toc.purge'", $step);
        self::assertFileExists(self::ROOT . '/migrations/rollback/atak_toc_purge_permission_down.sql');
        // Droit effectif : la gestion du poste ne purge pas, l'administration de la communauté oui.
        $manage = new TocAccess(static fn (string $s): bool => in_array($s, ['atak.toc.use', 'atak.toc.manage'], true), static fn (): array => []);
        self::assertTrue($manage->canManage());
        self::assertFalse($manage->canPurge());
        $purge = new TocAccess(static fn (string $s): bool => $s === 'atak.toc.purge', static fn (): array => []);
        self::assertTrue($purge->canPurge());
        $admin = new TocAccess(static fn (string $s): bool => $s === 'admin.organization', static fn (): array => []);
        self::assertTrue($admin->canPurge());
        self::assertTrue($admin->canManage());
        self::assertFalse((new TocAccess(static fn (): bool => false, static fn (): array => []))->canPurge());
    }

    public function testMigrationStepsAreRegisteredNumberedAndReversible(): void
    {
        $registry = $this->file('database/steps/registry.php');
        foreach (['4780_atak_toc_soft_delete.php' => 'atak_toc_soft_delete', '4790_atak_toc_purge_permission.php' => 'atak_toc_purge_permission'] as $file => $id) {
            self::assertMatchesRegularExpression("/\\['id' => '" . $id . "', 'label' => '[^\\n]+', 'group' => 'schema', 'file' => '" . preg_quote($file, '/') . "', 'rerun' => true\\]/", $registry);
            self::assertFileExists(self::ROOT . '/database/steps/' . $file);
        }
        self::assertSame(1, substr_count($registry, "'file' => '4780_"));
        self::assertSame(1, substr_count($registry, "'file' => '4790_"));
        $step = $this->file('database/steps/4780_atak_toc_soft_delete.php');
        self::assertStringNotContainsString('ADD UNIQUE KEY', $step, 'compatible sqlite');
        self::assertStringContainsString('addColumnIfMissing', $step);
        self::assertStringContainsString('addIndexIfMissing', $step);
        $sql = $this->file('migrations/2026_11_15_140_atak_toc_purges.sql');
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS atak_toc_purges', $sql);
        self::assertStringNotContainsString('DROP', $sql);
        $down = $this->file('migrations/rollback/atak_toc_purges_down.sql');
        foreach (['DROP TABLE IF EXISTS atak_toc_purges', 'DROP COLUMN deleted_at', 'DROP INDEX idx_atak_markers_trash', 'DROP INDEX idx_atak_map_shapes_trash'] as $needle) {
            self::assertStringContainsString($needle, $down);
        }
        // Le cron vide la corbeille.
        self::assertStringContainsString('TocElementStore', $this->file('app/Services/Cron/Jobs/AtakMarkerCycleCronJob.php'));
        // Lecture : tracés supprimés jamais servis, seulement quand la migration est là.
        self::assertStringContainsString('TocSoftDelete::liveSql', $this->file('app/Repositories/MapShapeRepository.php'));
        self::assertStringContainsString('TocSoftDelete::liveSql', $this->file('app/Services/Atak/MarkerCycle/PdoMarkerCycleStore.php'));
    }

    public function testSiteTombstoneContractIsAdditive(): void
    {
        $api = $this->file('app/Controllers/Api/AtakApiController.php');
        $i = strpos($api, "parseCursor((string) \$request->query('deleted_since', ''))");
        self::assertNotFalse($i);
        $block = substr($api, (int) $i, 900);
        self::assertStringContainsString('if ($cursor !== null) {', $block);
        self::assertStringContainsString('gameTombstones(', $block);
        self::assertStringContainsString('array_merge(\App\Services\AtakToc\Purge\TocElementStore::tombstoneRows($tombs), $out)', $block, 'toujours une liste : un ancien client lit la même forme');
        // Sans le paramètre : le chemin d'avant (15 dernières minutes) reste en place, après le bloc.
        self::assertStringContainsString('recentlySuppressedGameMarkers($tenantId, $mapId)', substr($api, (int) $i + 900, 1800));
        self::assertNull(TocElementStore::parseCursor(''));
        self::assertNull(TocElementStore::parseCursor('x:1'));
        self::assertNotNull(TocElementStore::parseCursor('1794751200:12'));
    }

    public function testLinkDllReadsTombstonesWithABoundedMonotonicCursor(): void
    {
        $cs = $this->file('mod/UptoDate/COMSPECExtension/Extension.cs');
        foreach ([
            '"GetMarkers.T"', 'deleted_since=', 'TombstoneCursorText()', 'AdvanceTombstoneCursor(', 'TombLookbackSeconds = 3600', 'TombMaxAgeSeconds = 172800',
            'ts > now + 120', 'ts > _tombTs || (ts == _tombTs && id > _tombId)', 'bytes + rowBytes <= budget', 'StartsWith("del:"', 'MaxOutputBytes - 4 - 3',
        ] as $needle) {
            self::assertStringContainsString($needle, $cs, $needle);
        }
        self::assertStringContainsString('string.Equals((args[1] ?? "").Trim(), "tomb"', $cs);
        // Les identifiants texte ne font plus perdre toute la liste.
        $start = strpos($cs, 'private static string SimplifyMarkersJson(string json, bool trackTombstones = false)');
        self::assertNotFalse($start);
        $fn = substr($cs, (int) $start, 6000);
        self::assertStringNotContainsString('el.GetProperty("id").GetInt32()', $fn);
        self::assertStringContainsString('else continue;', $fn);
        // Le curseur n'avance que sur les lignes réellement transmises, une fois la liste lue en entier.
        self::assertLessThan(strpos($fn, 'AdvanceTombstoneCursor('), strpos($fn, 'foreach (var el in doc.RootElement.EnumerateArray())'));
        self::assertStringContainsString('if (trackTombstones && tombTs > 0) AdvanceTombstoneCursor(tombTs, tombId);', $fn);
        self::assertSame(1, substr_count($fn, 'AdvanceTombstoneCursor('));
        // Les relevés sans « tomb » (pollMarkers, anciennes DLL) ne touchent jamais le curseur.
        self::assertStringContainsString('SimplifyMarkersJson(body, trackTombstones)', $cs);
        self::assertSame(0, substr_count($cs, 'SimplifyMarkersJson(body))'));
    }

    public function testSqfSweepAsksForTombstonesWithoutAnyMessageInArma(): void
    {
        $path = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/map/fn_markerWebSweep.sqf';
        $sqf = $this->file($path);
        self::assertStringContainsString('["GetMarkers", ["world:" + worldName, "tomb"]]', $sqf);
        self::assertStringContainsString('"game_del"', $sqf);
        self::assertStringContainsString('deleteMarker _n;', $sqf);
        foreach (['systemChat', 'hint ', 'hintSilent', 'hintC', 'cutText', 'titleText', 'BIS_fnc_showNotification', 'BIS_fnc_dynamicText', 'cutRsc', 'titleRsc', 'ctrlSetText'] as $word) {
            self::assertStringNotContainsString($word, $sqf, $word);
        }
        // Parenthèses, crochets et accolades équilibrés (hors commentaires et chaînes).
        $code = preg_replace('#//[^\n]*#', '', $sqf);
        $code = preg_replace('/"(?:[^"]|"")*"/', '""', (string) $code);
        self::assertSame(substr_count((string) $code, '{'), substr_count((string) $code, '}'));
        self::assertSame(substr_count((string) $code, '['), substr_count((string) $code, ']'));
        self::assertSame(substr_count((string) $code, '('), substr_count((string) $code, ')'));
    }

    public function testVersionsAreAlignedAndTheChangelogCarriesBothSummaries(): void
    {
        // Versions minimales (chaque sortie suivante ne casse pas ce test) ; l'alignement de Link, lui, est exact.
        $hpp = $this->file('mod/COMSPEC_ATAK_Native/Sources/addons/main/script_component.hpp');
        ModVersion::assertAtLeast('4.24.0', ModVersion::match('/#define VERSION_STR "([0-9.]+)"/', $hpp));
        $link = ModVersion::match('/#define LINK_VERSION_STR "([0-9.]+)"/', $hpp);
        ModVersion::assertAtLeast('2.0.126', $link);
        self::assertStringContainsString('const string ExtensionVersion = "' . $link . '";', $this->file('mod/UptoDate/COMSPECExtension/Extension.cs'));
        $a = $this->file('mod/UptoDate/COMSPECExtension/COMSPECExtension.csproj');
        self::assertStringContainsString('<Version>' . $link . '</Version>', $a);
        self::assertStringContainsString('<InformationalVersion>' . $link . '</InformationalVersion>', $a);
        $b = $this->file('mod/COMSPEC_ATAK_Native/COMSPECATAKNativeExtension/COMSPECATAKNativeExtension.csproj');
        self::assertStringContainsString('<Version>' . $link . '</Version>', $b);
        self::assertStringContainsString('<InformationalVersion>' . $link . '-native</InformationalVersion>', $b);
        $log = $this->file('CHANGELOG-ATAK.md');
        self::assertMatchesRegularExpression('/
## Poste TOC : suppression des marqueurs[^
]+\(ATAK natif 4\.24\.0\.AG - Washington, COMSPEC Link 2\.0\.126\)

### Résumé joueur
Type : nouveauté
FR : [^
]+
(- [^
]+
)+EN : [^
]+
(- [^
]+
)+/', $log);
        $steam = $this->file('mod/COMSPEC_ATAK_Native/publisher/STEAM_CHANGELOG.txt');
        self::assertStringContainsString('[h1]COMSPEC ATAK 4.24.0 Washington', $steam);
    }
}
