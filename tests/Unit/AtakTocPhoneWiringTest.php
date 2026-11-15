<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Authorization\TenantPermissionCatalog;
use App\Repositories\AtakWebCommandRepository;
use App\Services\AtakToc\TocPhoneService;
use App\Services\Rbac\AccessRightsCatalog;
use PHPUnit\Framework\TestCase;

/**
 * Câblage des actions sur les téléphones : droit atak.toc.phone déclaré (catalogue, explication, sensibilité, dépendance),
 * migration numérotée avec retour arrière, routes derrière le middleware de membre, file des commandes web (genre
 * « phone »), mod (équilibre des fichiers SQF, aucun toast ni popup, dictionnaires, changelog) et vues.
 */
final class AtakTocPhoneWiringTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';
    private const CONNECT = 'mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/';

    private static function read(string $rel): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(self::ROOT . '/' . $rel));
    }

    /** Texte SQF sans commentaires ni chaînes (pour compter les délimiteurs et chercher des appels). */
    private static function code(string $sqf): string
    {
        $sqf = (string) preg_replace('#/\*.*?\*/#s', '', $sqf);
        $sqf = (string) preg_replace('#//[^\n]*#', '', $sqf);
        $sqf = (string) preg_replace('/"(?:[^"]|"")*"/s', '""', $sqf);

        return (string) preg_replace("/'(?:[^']|'')*'/s", "''", $sqf);
    }

    public function testRightIsDeclaredSensitiveAndDependsOnTheStationRight(): void
    {
        self::assertContains('atak.toc.phone', TenantPermissionCatalog::allSlugs());
        $def = array_values(array_filter(TenantPermissionCatalog::definitions(), static fn (array $d): bool => $d['slug'] === 'atak.toc.phone'))[0];
        self::assertSame('atak', $def['module']);
        self::assertStringContainsString('téléphones', $def['name']);
        $meta = AccessRightsCatalog::meta('atak.toc.phone');
        self::assertSame('toc', $meta['domain']);
        self::assertSame(AccessRightsCatalog::SENSITIVITY_CRITICAL, $meta['sensitivity'], 'droit sensible');
        self::assertSame(['atak.toc.use'], $meta['requires']);
        self::assertStringContainsString('désactivé par défaut', $meta['explanation']);
        // Aucun niveau ne le reçoit par la matrice : il se coche ou vient de l'étape 4740 (gestionnaires du poste).
        $matrix = self::read('app/Services/Rbac/RolePermissionMatrixCatalog.php');
        self::assertStringNotContainsString('atak.toc.phone', $matrix);
        $access = self::read('app/Services/AtakToc/TocAccess.php');
        self::assertStringContainsString("public const PHONE = 'atak.toc.phone';", $access);
        self::assertStringContainsString('return $this->canUse() && (($this->can)(self::PHONE)', $access);
    }

    public function testRightLabelsAreTranslatedForTheEnglishInterface(): void
    {
        $en = require self::ROOT . '/lang/en/site_auto.php';
        $def = array_values(array_filter(TenantPermissionCatalog::definitions(), static fn (array $d): bool => $d['slug'] === 'atak.toc.phone'))[0];
        self::assertArrayHasKey($def['name'], $en, 'libellé du droit sans anglais');
        self::assertArrayHasKey(AccessRightsCatalog::meta('atak.toc.phone')['explanation'], $en, 'explication du droit sans anglais');
        self::assertNotSame('', trim((string) $en[$def['name']]));
    }

    public function testStepIsNumberedRegisteredAndReversible(): void
    {
        $registry = require self::ROOT . '/database/steps/registry.php';
        $entry = array_values(array_filter($registry, static fn (array $e): bool => $e['id'] === 'atak_toc_phone'))[0] ?? null;
        self::assertNotNull($entry);
        self::assertSame('schema', $entry['group']);
        self::assertSame('4740_atak_toc_phone.php', $entry['file']);
        self::assertTrue($entry['rerun']);
        self::assertStringContainsString('migrations/rollback/atak_toc_phone_down.sql', $entry['label']);
        self::assertStringContainsString('sans cette étape la fonction reste désactivée', $entry['label']);
        self::assertFileExists(self::ROOT . '/database/steps/4740_atak_toc_phone.php');
        $nums = array_map(static fn (array $e): int => (int) substr((string) $e['file'], 0, 4), $registry);
        self::assertSame(1, count(array_keys($nums, 4740, true)), 'numéro unique');
        $step = self::read('database/steps/4740_atak_toc_phone.php');
        // Droit donné aux seuls niveaux qui ont atak.toc.manage : jamais « tous les rôles ».
        self::assertStringContainsString("'atak.toc.manage'", $step);
        self::assertStringContainsString("'atak.toc.phone'", $step);
        self::assertStringNotContainsString('community_owner', $step);
        self::assertStringNotContainsString('ADD UNIQUE KEY', $step);
        self::assertFileExists(self::ROOT . '/migrations/2026_11_15_140_atak_toc_phone.sql');
        self::assertFileExists(self::ROOT . '/migrations/rollback/atak_toc_phone_down.sql');
    }

    public function testRoutesAreBehindTheMemberMiddlewareAndSpecificOnesComeFirst(): void
    {
        $routes = self::read('routes/web.php');
        preg_match_all("#router->(get|post)\('/atak/toc/api/phones[^\n]*#", $routes, $m);
        self::assertCount(9, $m[0]);
        foreach ($m[0] as $line) {
            self::assertStringContainsString('$mwAtakMemberOnly', $line, $line);
        }
        self::assertLessThan(strpos($routes, "'/atak/toc/api/phones/{ref}', [\$tocPhone"), strpos($routes, "'/atak/toc/api/phones/events'"));
        self::assertLessThan(strpos($routes, "'/atak/toc/api/phones/{ref}', [\$tocPhone"), strpos($routes, "'/atak/toc/api/phones/settings'"));
        foreach (['notify', 'ring', 'ring-stop', 'locate', 'triangulate'] as $a) {
            self::assertStringContainsString("router->post('/atak/toc/api/phones/{ref}/" . $a . "'", $routes);
        }
        $ctl = self::read('app/Controllers/Api/AtakTocPhoneController.php');
        self::assertStringContainsString('$this->access->canPhone()', $ctl);
        self::assertStringContainsString("guardWrite(\$request, 'phone'", $ctl);
        self::assertStringContainsString('canManage()', $ctl, 'le réglage est réservé à la gestion du poste');
    }

    public function testWebCommandQueueCarriesThePhoneKindWithAnAsciiSafeWire(): void
    {
        self::assertContains('phone', AtakWebCommandRepository::KINDS);
        $msg = str_repeat('Évacuation prête à Pyrgos : confirmez ! ', 4);
        $msg = mb_substr($msg, 0, 160);
        $enc = TocPhoneService::wireText($msg);
        $wire = AtakWebCommandRepository::buildWire('phone', '76561198000000001', 'notify', '', ['lv' => 'u', 'm' => $enc, 'ak' => 1, 'by' => TocPhoneService::wireText('Cdt Mercier')]);
        self::assertLessThanOrEqual(480, strlen($wire), 'le fil tient dans 480 caractères');
        self::assertMatchesRegularExpression('/^WC1~phone~76561198000000001~notify~-~lv=u~m=[A-Za-z0-9 .,:@_\-]+~ak=1~by=[A-Za-z0-9 .,:@_\-]+$/', $wire);
        self::assertStringContainsString('m=' . $enc, $wire, 'texte long gardé en entier (120 caractères pour les autres clés)');
        self::assertSame($msg, TocPhoneService::unwireText($enc));
        $repo = self::read('app/Repositories/AtakWebCommandRepository.php');
        self::assertStringContainsString("kind'] === 'phone' && (\$uid === '' || \$uid !== \$row['target_uid'])", $repo, 'seul le téléphone visé répond');
        self::assertStringContainsString("NOT (kind = 'phone' AND cmd = 'notify')", $repo, 'une pile de messages ne retarde pas les commandes en temps réel');
    }

    public function testJournalIsKeptSixMonthsByTheRetentionJob(): void
    {
        $tables = array_column(\App\Services\Cron\Jobs\PlayerDataRetentionCronJob::RULES, 0);
        self::assertContains('atak_toc_phone_events', $tables);
        $rule = array_values(array_filter(\App\Services\Cron\Jobs\PlayerDataRetentionCronJob::RULES, static fn (array $r): bool => $r[0] === 'atak_toc_phone_events'))[0];
        self::assertStringContainsString("status NOT IN ('queued', 'delivered', 'running')", $rule[1], 'une demande ouverte n’est jamais purgée');
    }

    public function testNoDllChangeLinkVersionStaysAligned(): void
    {
        $hpp = self::read(self::MAIN . 'script_component.hpp');
        preg_match('/#define LINK_VERSION_STR "([^"]+)"/', $hpp, $m);
        $csproj = self::read('mod/UptoDate/COMSPECExtension/COMSPECExtension.csproj');
        self::assertStringContainsString('<Version>' . $m[1] . '</Version>', $csproj);
        $cs = self::read('mod/UptoDate/COMSPECExtension/Extension.cs');
        self::assertStringContainsString('ExtensionVersion = "' . $m[1] . '"', $cs);
        $log = self::read('CHANGELOG-ATAK.md');
        self::assertStringContainsString('(ATAK natif 4.23.0.AG - Washington)', $log);
        self::assertStringContainsString('COMSPEC Link inchangé', $log);
        $steam = self::read('mod/COMSPEC_ATAK_Native/publisher/STEAM_CHANGELOG.txt');
        self::assertStringStartsWith('[h1]COMSPEC ATAK 4.23.0 Washington', $steam);
    }

    public function testPhoneSqfIsBalancedAndNeverShowsAnAlertToThePlayer(): void
    {
        $files = [
            self::MAIN . 'functions/toc/fn_tocPhone.sqf', self::CONNECT . 'fn_webCmdDispatch.sqf',
            self::MAIN . 'functions/pages/fn_pageAlerts.sqf', self::MAIN . 'functions/map/fn_mapPinnedApp.sqf',
        ];
        foreach ($files as $f) {
            $code = self::code(self::read($f));
            foreach (['{' => '}', '[' => ']', '(' => ')'] as $o => $c) {
                self::assertSame(substr_count($code, $o), substr_count($code, $c), $f . ' : ' . $o . $c . ' déséquilibré');
            }
        }
        $src = self::code(self::read(self::MAIN . 'functions/toc/fn_tocPhone.sqf'));
        foreach (['systemChat', 'hint ', 'hintSilent', 'hintC', 'cutText', 'cutRsc', 'titleText', 'titleRsc', 'BIS_fnc_showNotification', 'BIS_fnc_', 'comspec_atak_native_fnc_notify', 'addScreenToast', 'showNotification', 'globalChat', 'sideChat', 'groupChat', 'ctrlCreate', 'createDialog', 'createDisplay'] as $bad) {
            self::assertStringNotContainsString($bad, $src, 'interdit : ' . $bad);
        }
        // Boîte du téléphone, journal des alertes, vibration par les fonctions existantes ; position par le GPS du téléphone.
        foreach (['comspec_atak_native_fnc_alertsLog', 'comspec_atak_native_fnc_vibrate', 'comspec_atak_native_fnc_gpsPos', 'comspec_atak_native_fnc_gpsFix', 'comspec_overwatch_connect_fnc_webCmdAck', 'comspec_overwatch_connect_fnc_playAtakNotification', '"inbox"'] as $need) {
            self::assertStringContainsString($need, self::read(self::MAIN . 'functions/toc/fn_tocPhone.sqf'), $need);
        }
        // Jamais de position inventée : sans fix, le compte rendu est un échec « position inconnue ».
        self::assertStringContainsString('Position inconnue : pas de fix GPS', self::read(self::MAIN . 'functions/toc/fn_tocPhone.sqf'));
        $disp = self::read(self::CONNECT . 'fn_webCmdDispatch.sqf');
        self::assertStringContainsString('case "phone"', $disp);
        self::assertStringContainsString('comspec_atak_native_fnc_tocPhone', $disp);
        self::assertStringContainsString('class tocPhone {}', self::read(self::MAIN . 'config.cpp'));
        $alerts = self::read(self::MAIN . 'functions/pages/fn_pageAlerts.sqf');
        foreach (['TOC_INFO', 'TOC_ALERT', 'TOC_URGENT', 'TOC_TRACE', 'ALERTE DU TOC', 'SUIVI PAR LE TOC'] as $t) {
            self::assertStringContainsString($t, $alerts);
        }
        self::assertStringContainsString('["read"] call comspec_atak_native_fnc_tocPhone;', $alerts, 'ouvrir l’app Alertes = « lu »');
    }

    public function testPhoneTextsAreInTheDictionariesWithStrictFormat(): void
    {
        $en = self::read(self::MAIN . 'i18n/en.txt');
        $xx = self::read('mod/COMSPEC_ATAK_Native/localisation/template/xx.txt');
        foreach (['ALERTE DU TOC' => 'TOC ALERT', 'ALERTE URGENTE DU TOC' => 'URGENT TOC ALERT', 'SUIVI PAR LE TOC' => 'TRACKED BY THE TOC', 'Votre téléphone sonne (%1 s)' => 'Your phone is ringing (%1 s)',
            'Votre téléphone vibre (%1 s)' => 'Your phone is vibrating (%1 s)', 'Localisé par le TOC à %1' => 'Located by the TOC at %1', 'Triangulé par le TOC à %1' => 'Triangulated by the TOC at %1'] as $fr => $eng) {
            self::assertStringContainsString('["' . $fr . '", "' . $eng . '"]', $en, $fr);
            self::assertStringContainsString('["' . $fr . '", "", "' . $eng . '"]', $xx, $fr);
        }
        // Dernière ligne sans virgule, virgule entre toutes les autres (AtakI18nFormatTest vérifie le reste).
        $lines = explode("\n", rtrim($en));
        self::assertSame(']', $lines[count($lines) - 1]);
        self::assertFalse(str_ends_with($lines[count($lines) - 2], ','), 'aucune virgule après la dernière entrée');
        self::assertStringEndsWith('],', $lines[count($lines) - 3]);
        // Chaque texte du téléphone passe par fn_tr : les cartes du journal sont traduites à l'affichage.
        $sqf = self::read(self::MAIN . 'functions/toc/fn_tocPhone.sqf');
        self::assertStringContainsString('"Votre téléphone sonne (%1 s)"', $sqf);
        self::assertStringContainsString('"Localisé par le TOC à %1"', $sqf);
    }

    public function testViewsLoadTheNewTabAndItsStyles(): void
    {
        $console = self::read('views/atak/toc/console.php');
        self::assertLessThan(strpos($console, 'assets/js/toc/toc-console.js'), strpos($console, 'assets/js/toc/toc-phones.js'), 'le module avant la console');
        self::assertStringContainsString('atak-toc-phones.css', self::read('views/atak/toc/_head.php'));
        $js = self::read('public/assets/js/toc/toc-console.js');
        self::assertStringContainsString("['telephones', 'Téléphones']", $js);
        self::assertStringContainsString('telephones: function () { return window.TocPhones ? window.TocPhones.panel() : \'\'; }', $js);
        self::assertStringContainsString('window.TocHost = {', $js);
        self::assertStringContainsString('window.TocPhones.mapReady(S.tm)', $js);
    }

    public function testPhonesUiFollowsTheHouseRules(): void
    {
        $js = self::read('public/assets/js/toc/toc-phones.js');
        $css = self::read('public/assets/css/atak-toc-phones.css');
        // Confirmation modale pour « Faire sonner » et « Urgent » ; jamais une fenêtre native.
        self::assertStringContainsString("title: 'Envoyer un message urgent ?'", $js);
        self::assertStringContainsString("'Faire sonner ce téléphone ?'", $js);
        self::assertStringContainsString('confirm: true', $js);
        self::assertStringContainsString('role="alertdialog"', $js);
        self::assertDoesNotMatchRegularExpression('/\b(window\.)?(alert|confirm|prompt)\(/', $js);
        // Pas d'« eyebrow » : aucune petite étiquette au-dessus d'un titre.
        self::assertDoesNotMatchRegularExpression('/eyebrow|kicker|overline/i', $js . $css);
        // Textes de 13 px au moins, cibles de 44 px au moins.
        preg_match_all('/font(?:-size)?:\s*(?:[0-9]{3}\s+)?(\d+)px/', $css, $sizes);
        self::assertNotEmpty($sizes[1]);
        foreach ($sizes[1] as $px) {
            self::assertGreaterThanOrEqual(13, (int) $px, 'texte sous 13 px');
        }
        preg_match_all('/min-height:\s*(\d+)px/', $css, $heights);
        self::assertGreaterThanOrEqual(8, count($heights[1]));
        foreach ($heights[1] as $px) {
            self::assertGreaterThanOrEqual(44, (int) $px, 'cible sous 44 px');
        }
        // Pas de bloc empilé : tiroir, onglets et fenêtre.
        self::assertStringContainsString('UI.drawer.open', $js);
        self::assertStringContainsString('role="tablist"', $js);
        // Aucun compte Steam ni adresse IP dans le script.
        self::assertDoesNotMatchRegularExpression('/steam|76561|ip_addr|geolocation|navigator\.geolocation/i', $js);
        // Les écritures passent par l'API du site (jeton CSRF ajouté par la console).
        self::assertStringContainsString("method: 'POST'", $js);
        self::assertStringContainsString('/atak/toc/api/phones/', $js);
    }

    public function testDocumentationDescribesTheFeature(): void
    {
        $doc = self::read('docs/TOC-WEB.md');
        foreach (['atak.toc.phone', 'Téléphones', 'atak_toc_phone_events', '4740', 'PhoneTriangulation', 'fn_tocPhone', 'position inconnue'] as $needle) {
            self::assertStringContainsString($needle, $doc, $needle);
        }
    }
}
