<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Core\Router;
use PHPUnit\Framework\TestCase;

/**
 * ORBAT en page unique : rendu (gestionnaire / membre), anciennes adresses redirigées, plus d’écran en double.
 */
final class OrbatSinglePageTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    /** @param array<string, mixed> $vars */
    private function render(array $vars): string
    {
        // Libellés français attendus, quel que soit l’état laissé par un autre test.
        $savedLocale = $GLOBALS['__app_locale'] ?? null;
        $GLOBALS['__app_locale'] = 'fr';
        extract($vars);
        ob_start();
        try {
            require self::ROOT . '/views/partials/orbat/orbat_canvas.php';
        } finally {
            $html = (string) ob_get_clean();
            $GLOBALS['__app_locale'] = $savedLocale;
        }

        return $html;
    }

    /** @return array<string, mixed> */
    private function boot(string $html): array
    {
        self::assertSame(1, preg_match('#<script type="application/json" id="orbat-boot">(.*?)</script>#s', $html, $m));

        return (array) json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> */
    private function roster(): array
    {
        return [
            'id' => 'unit-1', 'unitId' => 1, 'label' => 'Bravo <b>', 'role' => 'B', 'unitCode' => 'SOAR-B', 'adminId' => '0042-0001',
            'symbolSidc' => 'SFGPUCI----B---', 'sidc' => 'SFGPUCI--------', 'echelon' => 'groupe', 'members' => [], 'children' => [],
        ];
    }

    public function testManagerGetsEditingToolsAndDataWithoutInlineScriptInjection(): void
    {
        $html = $this->render([
            'orbatRosterData' => $this->roster(),
            'orbatCanManage' => true,
            'orbatCommanderOptions' => [['id' => 4, 'label' => 'SGT Hale']],
            'orbatCsrfToken' => 'tok',
            'orbatRecruitmentHub' => false,
            'orbatOpen' => 'unite',
        ]);

        self::assertStringContainsString('id="orb-new"', $html);
        self::assertStringContainsString('id="orb-as"', $html, 'Voir comme : réservé aux gestionnaires');
        self::assertStringContainsString('assets/js/orbat-app.js', $html);
        self::assertStringContainsString('assets/js/nato-sidc-icons.js', $html);
        self::assertStringContainsString('assets/css/orbat-app.css', $html);
        self::assertStringNotContainsString('cdn.', $html, 'Uniquement nos fichiers (CSP).');
        self::assertStringNotContainsString('<b>', substr($html, (int) strpos($html, 'orbat-boot')), 'Les noms sont échappés dans les données.');

        $boot = $this->boot($html);
        self::assertTrue($boot['canManage']);
        self::assertSame('unite', $boot['open']);
        self::assertSame('Bravo <b>', $boot['roster']['label']);
        self::assertSame([['id' => 4, 'label' => 'SGT Hale']], $boot['commanders']);
        self::assertSame('Chef', $boot['i18n']['chef']);
        self::assertNotEmpty($boot['symbols']['echelons']);
        self::assertStringContainsString('milsymbol.js', $boot['msUrl']);
    }

    public function testMemberSeesTheChartButNoEditingTools(): void
    {
        $html = $this->render([
            'orbatRosterData' => $this->roster(),
            'orbatCanManage' => false,
            'orbatCommanderOptions' => [['id' => 4, 'label' => 'SGT Hale']],
            'orbatCsrfToken' => 'tok',
        ]);

        self::assertStringNotContainsString('id="orb-new"', $html);
        self::assertStringNotContainsString('id="orb-as"', $html);
        self::assertStringNotContainsString('id="orb-invite"', $html);
        $boot = $this->boot($html);
        self::assertFalse($boot['canManage']);
        self::assertSame([], $boot['commanders'], 'La liste des membres n’est pas exposée aux simples lecteurs.');
    }

    public function testEmptyCommunityShowsTheFirstUnitCall(): void
    {
        $html = $this->render(['orbatRosterData' => null, 'orbatCanManage' => true, 'orbatCsrfToken' => 'tok']);
        self::assertStringContainsString('id="orb-empty-new"', $html);
        self::assertNull($this->boot($html)['roster']);
    }

    public function testEnglishLabelsFallBackToFrench(): void
    {
        $fr = require self::ROOT . '/lang/fr/orbat.php';
        $en = require self::ROOT . '/lang/en/orbat.php';
        self::assertSame([], array_diff_key($en, $fr), 'Pas de clé anglaise orpheline.');
        self::assertSame('Deputy', $en['deputy']);
    }

    public function testPageUrlKeepsOnlyKnownParameters(): void
    {
        self::assertSame(url('orbat'), orbat_page_url());
        self::assertSame(url('orbat') . '?unit=12&modifier=1', orbat_page_url(['unit' => 12, 'modifier' => 1, 'x' => 'y']));
        self::assertSame(url('orbat') . '?ouvrir=equipe', orbat_page_url(['ouvrir' => 'equipe']));
        self::assertSame(url('orbat'), orbat_page_url(['ouvrir' => 'javascript:alert(1)']));
    }

    /** @return list<array{method: string, path: string, pattern: string, handler: mixed}> */
    private function routes(): array
    {
        $server = $_SERVER;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        try {
            $router = new Router(new Request());
            $register = require self::ROOT . '/routes/web.php';
            $register($router);
        } finally {
            $_SERVER = $server;
        }

        return (new \ReflectionProperty(Router::class, 'routes'))->getValue($router);
    }

    /** @return list<array{0: string, 1: array<string, string>, 2: array<string, string>, 3: string}> */
    public static function oldAddresses(): array
    {
        return [
            ['/back-office/organisation/structure', [], [], ''],
            ['/back-office/organisation/structure', ['ouvrir' => 'membre'], [], '?ouvrir=membre'],
            ['/back-office/units', [], [], ''],
            ['/back-office/units/create', [], [], '?ouvrir=unite'],
            ['/back-office/units/{id}/edit', [], ['id' => '42'], '?unit=42&modifier=1'],
            ['/back-office/groups/create', [], [], '?ouvrir=groupe'],
            ['/back-office/teams/create', [], [], '?ouvrir=equipe'],
            ['/admin/units/create', [], [], '?ouvrir=groupe'],
        ];
    }

    public function testOldOrbatAddressesRedirectToTheSinglePage(): void
    {
        $byPath = [];
        foreach ($this->routes() as $r) {
            if ($r['method'] === 'GET') {
                $byPath[$r['path']] = $r;
            }
        }
        self::assertArrayHasKey('/orbat', $byPath);
        $get = $_GET;
        try {
            foreach (self::oldAddresses() as [$path, $query, $params, $suffix]) {
                self::assertArrayHasKey($path, $byPath, $path);
                self::assertInstanceOf(\Closure::class, $byPath[$path]['handler'], $path . ' doit rediriger');
                $_GET = $query;
                $res = ($byPath[$path]['handler'])(new Request(), $params);
                self::assertSame(302, $res->statusCode(), $path);
                self::assertSame(url('orbat') . $suffix, $res->headerValue('Location'), $path);
            }
        } finally {
            $_GET = $get;
        }
        self::assertFileDoesNotExist(self::ROOT . '/views/admin/organization/structure_hub.php');
        self::assertFileDoesNotExist(self::ROOT . '/views/admin/units/index.php');
    }
}
