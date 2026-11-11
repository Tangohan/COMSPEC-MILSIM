<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SseFileText;
use App\Support\SseFileVisibility;
use PHPUnit\Framework\TestCase;

/**
 * Fichiers SSE : droits sur chaque route (portail SSE, CSRF sur chaque POST, MJ pour le lore, gestion pour le
 * back-office), menu « Opérateur » de la barre du site (membres connectés, aucun outil d'encadrement), droits déclarés
 * et migrations inscrites, contrat avec le jeu, écrans sans petit libellé au-dessus des titres.
 */
final class SseFilesAccessWiringTest extends TestCase
{
    private static function read(string $rel): string
    {
        $path = dirname(__DIR__, 2) . '/' . $rel;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /** Corps d'une méthode publique d'un contrôleur (jusqu'à la méthode suivante). */
    private static function methodBody(string $src, string $method): string
    {
        $start = strpos($src, 'public function ' . $method . '(');
        self::assertNotFalse($start, $method);
        $next = strpos($src, "\n    public function ", $start + 10);
        $nextPrivate = strpos($src, "\n    private function ", $start + 10);
        $end = min(array_filter([$next, $nextPrivate, strlen($src)], static fn ($v): bool => $v !== false));

        return substr($src, $start, $end - $start);
    }

    public function testEveryNewPortalRouteIsBehindTheSsePortalAndEveryPostChecksCsrf(): void
    {
        $routes = self::read('routes/web.php');
        $controllers = [
            '$sseRec' => self::read('app/Controllers/Web/SseFileRecordController.php'),
            '$sseLore' => self::read('app/Controllers/Web/SseLoreController.php'),
        ];
        preg_match_all('/\$router->(get|post)\(\'(\/atak\/sse\/fichiers[^\']*)\', \[(\$sseRec|\$sseLore), \'(\w+)\'\], ([^)]+)\);/', $routes, $m, PREG_SET_ORDER);
        self::assertGreaterThan(30, count($m), 'routes des fichiers déclarées');
        foreach ($m as [, $verb, $path, $ctrl, $method, $mw]) {
            self::assertSame('$mwSsePortal', trim($mw), $path . ' : derrière le portail SSE');
            $body = self::methodBody($controllers[$ctrl], $method);
            if ($verb === 'post') {
                self::assertMatchesRegularExpression('/csrfOk\(/', $body, $path . ' : jeton CSRF vérifié');
            }
            if ($ctrl === '$sseLore') {
                self::assertMatchesRegularExpression('/canLore\(\)|\$this->session\(/', $body, $path . ' : réservé au MJ');
            }
        }
        // Écrire demande un compte (jamais une session invitée) ; modifier une fiche demande la gestion.
        $rec = $controllers['$sseRec'];
        foreach (['profileUpdate', 'aliasStore', 'aliasDelete', 'linkStore', 'linkDelete', 'phoneAttach'] as $method) {
            self::assertStringContainsString('canManage()', self::methodBody($rec, $method), $method);
        }
        foreach (['noteStore', 'xrefDecide', 'watchSubject', 'watchStore', 'photoUpload'] as $method) {
            self::assertStringContainsString('canWrite()', self::methodBody($rec, $method), $method);
        }
        self::assertStringContainsString('return $this->canWrite() && self::can([\'atak.sse.case.manage\'', self::read('app/Controllers/Web/SseFilesContext.php'));
        self::assertStringContainsString("return !\$this->isGuest() && \$this->userId() !== null;", self::read('app/Controllers/Web/SseFilesContext.php'));
    }

    public function testBackOfficeRoutesRequireTheFilesPermission(): void
    {
        $routes = self::read('routes/web.php');
        self::assertStringContainsString("\$router->get('/back-office/renseignement/fichiers', [\$boSseFiles, 'index'], [AuthMiddleware::class]);", $routes);
        $src = self::read('app/Controllers/Admin/AdminSseFilesController.php');
        foreach (['index', 'merge', 'moderate', 'settingsSave'] as $method) {
            self::assertStringContainsString('self::allowed()', self::methodBody($src, $method), $method);
        }
        foreach (['merge', 'moderate', 'settingsSave'] as $method) {
            self::assertStringContainsString('Csrf::validate', self::methodBody($src, $method), $method);
        }
        self::assertStringContainsString("can('atak.sse.files.admin')", $src);
        self::assertStringContainsString("url('back-office/renseignement/fichiers')", self::read('views/partials/ath_sidebar_nav.php'));
        self::assertStringContainsString("'path' => 'back-office/renseignement/fichiers'", self::read('config/back_office_pages.php'));
    }

    public function testPermissionsAndMigrationsAreDeclared(): void
    {
        $catalog = self::read('app/Authorization/TenantPermissionCatalog.php');
        self::assertStringContainsString("'slug' => 'atak.sse.files.admin'", $catalog);
        self::assertStringContainsString("'slug' => 'atak.sse.lore.manage'", $catalog);
        $registry = self::read('database/steps/registry.php');
        foreach (['4340_sse_files_records.php', '4350_sse_phone_lore.php', '4360_sse_files_permissions.php'] as $step) {
            self::assertStringContainsString("'file' => '" . $step . "'", $registry);
            self::assertFileExists(dirname(__DIR__, 2) . '/database/steps/' . $step);
        }
        self::assertStringContainsString('atak.sse.case.manage', self::read('database/steps/4360_sse_files_permissions.php'), 'droits donnés aux rôles qui gèrent déjà le SSE');
    }

    public function testOperatorMenuGroupsMemberToolsOnly(): void
    {
        $nav = require dirname(__DIR__, 2) . '/config/navigation.php';
        $menu = null;
        foreach ($nav['menu'] as $item) {
            if (($item['label'] ?? '') === 'Opérateur') {
                $menu = $item;
            }
        }
        self::assertIsArray($menu, 'menu « Opérateur » présent');
        self::assertTrue($menu['auth_only'], 'membres connectés seulement');
        self::assertArrayNotHasKey('featured', $menu, 'pas de carte avec petit libellé au-dessus du titre');
        $openToAll = 0;
        foreach ($menu['sections'] as $section) {
            foreach ($section['links'] as $link) {
                self::assertNotSame('', trim((string) ($link['description'] ?? '')), $link['label'] . ' : une ligne de description');
                self::assertStringStartsNotWith('back-office', (string) $link['path'], 'aucun outil d’encadrement ou d’administration');
                if (str_starts_with((string) $link['path'], 'atak/sse')) {
                    self::assertContains('atak.sse.access', $link['any_permissions'] ?? [], $link['label'] . ' : suit le droit d’accès au Bureau SSE');
                } elseif (!isset($link['any_permissions']) && !isset($link['permission'])) {
                    $openToAll++;
                }
            }
        }
        self::assertGreaterThanOrEqual(2, $openToAll, 'des outils ouverts à tout membre connecté : le menu s’affiche pour tous');
    }

    public function testGameContractAndNoEyebrowOnScreens(): void
    {
        $api = self::read('app/Controllers/Api/SseIdentityApiController.php');
        self::assertStringContainsString('SsePhoneExtractionService::KIND', $api);
        self::assertStringContainsString('SseLoreService::KINDS', $api);
        self::assertStringContainsString('FileRateLimiter', $api, 'débit borné');
        $icd = self::read('docs/technical/ICD-ATHENA-ATAK.md');
        self::assertStringContainsString('ICD-SSE-PHONE-001', $icd);
        self::assertStringContainsString('ICD-SSE-LORE-001', $icd);
        foreach (glob(dirname(__DIR__, 2) . '/views/atak/sse/files/*.php') ?: [] as $file) {
            $src = (string) file_get_contents($file);
            foreach (['eyebrow', 'overline', 'kicker'] as $bad) {
                self::assertStringNotContainsString($bad, $src, basename($file));
            }
        }
        self::assertStringNotContainsString('kicker', self::read('views/admin/sse_files/index.php'));
        self::assertStringContainsString('aucune reconnaissance faciale', self::read('views/atak/sse/files/photobook.php'));
        self::assertStringNotContainsString('@keyframes', self::read('public/assets/css/sse-files-kit.css'), 'aucune animation au changement de page');
    }

    public function testTextNormalisationAndVisibilityRules(): void
    {
        self::assertSame('leila aziz', SseFileText::norm('  Leïla   AZIZ '));
        self::assertSame(SseFileText::phonetic('Haddad'), SseFileText::phonetic('Hadad'));
        self::assertSame(SseFileText::phonetic('Mercier'), SseFileText::phonetic('Mercer'));
        self::assertSame(SseFileText::phonetic('Youssef'), SseFileText::phonetic('Yusuf'));
        self::assertSame('550142398', SseFileText::phoneKey('+1 (555) 014-2398'));
        self::assertSame(SseFileText::phoneKey('555-014-2398'), SseFileText::phoneKey('+1 555 014 2398'));
        $tokens = array_column(SseFileText::tokens('Contact avec Nikos, plaque AB-123-CD, tél. +33 6 12 84 77 01.'), 'token');
        self::assertContains('nikos', $tokens);
        self::assertContains('AB123CD', $tokens);
        self::assertContains(SseFileText::phoneKey('+33 6 12 84 77 01'), $tokens);
        self::assertNotContains('avec', $tokens, 'mots vides écartés');
        self::assertSame('…certain <mark>Nikos</mark> près du…', SseFileText::snippetHtml(SseFileText::snippet('Contact avec un certain Nikos près du garage.', 'nikos', 8)));

        $profile = ['visibility' => ['physical' => ['v' => 'known', 'phase' => 2], 'marks' => ['v' => 'hidden', 'phase' => 0]], 'lore_session_id' => 4, 'height_cm' => 180, 'marks' => 'x', 'knowledge' => 'secret', 'ai_answers' => ['answers' => ['a']], 'caution_codes' => [], 'in_files' => true];
        self::assertSame(1, SseFileVisibility::phaseOf($profile, [4 => 1]));
        $op = SseFileVisibility::operatorProfile($profile, 1);
        self::assertNull($op['height_cm'], 'révélé en phase 2');
        self::assertNull($op['marks'], 'caché MJ');
        self::assertNull($op['knowledge'], 'ce que la personne sait : jamais pour un opérateur');
        self::assertSame([], $op['ai_answers']);
        self::assertSame(180, SseFileVisibility::operatorProfile($profile, 2)['height_cm']);
    }
}
