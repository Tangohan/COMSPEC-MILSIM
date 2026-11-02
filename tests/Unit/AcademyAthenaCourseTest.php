<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Core\Router;
use App\Services\Academy\AcademyBuiltInCourses;
use App\Services\Academy\AcademyCurriculum;
use App\Services\Academy\AcademyRichText;
use App\Services\Academy\BuiltIn\AthenaPlatformCourse;
use PHPUnit\Framework\TestCase;

/**
 * Cours intégré « ATHENA — utiliser et administrer la plateforme » : structure complète en FR et EN, et surtout
 * chaque adresse citée (`/…` entre accents graves) existe dans routes/*.php, pour que la formation ne renvoie
 * jamais vers un écran qui n’existe pas.
 */
final class AcademyAthenaCourseTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    /** @var list<array{method:string,path:string,pattern:string}> */
    private static array $routes = [];

    /** @return list<array{method:string,path:string,pattern:string}> */
    private static function routes(): array
    {
        if (self::$routes === []) {
            $server = $_SERVER;
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI'] = '/';
            $router = new Router(new Request());
            foreach (glob(self::ROOT . '/routes/*.php') ?: [] as $file) {
                $register = require $file;
                if (is_callable($register)) {
                    $register($router);
                }
            }
            self::$routes = (new \ReflectionProperty(Router::class, 'routes'))->getValue($router);
            $_SERVER = $server;
        }

        return self::$routes;
    }

    private static function both(mixed $text, string $where): void
    {
        self::assertIsArray($text, $where);
        foreach (['fr', 'en'] as $lang) {
            self::assertIsString($text[$lang] ?? null, $where . '.' . $lang);
            self::assertNotSame('', trim($text[$lang]), $where . '.' . $lang);
        }
    }

    public function testCourseIsShippedCompleteAndBilingual(): void
    {
        $course = AcademyBuiltInCourses::find(AthenaPlatformCourse::KEY);
        self::assertIsArray($course);
        self::assertSame($course, AcademyCurriculum::validate($course));
        self::assertSame(AthenaPlatformCourse::VERSION, $course['version']);
        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $course['version']);
        self::assertTrue($course['built_in']);
        self::assertTrue($course['published']);
        self::assertSame(AcademyBuiltInCourses::JTAC_KEY, array_key_first(AcademyBuiltInCourses::all()), 'le JTAC reste en tête');
        self::assertGreaterThanOrEqual(10, count($course['chapters']));
        foreach ($course['chapters'] as $i => $chapter) {
            $where = '#' . $i;
            foreach (['title', 'theory', 'demonstration'] as $field) {
                self::both($chapter[$field], $where . ' ' . $field);
            }
            self::both($chapter['guided']['hint'], $where . ' indice');
            self::assertArrayNotHasKey('hint', $chapter['simulation'], $where);
            self::assertNotSame($chapter['guided']['question'], $chapter['simulation']['question'], $where);
            self::assertGreaterThanOrEqual(5, count($chapter['assessment']['questions']), $where);
            self::assertGreaterThan(1, count(array_unique(array_column($chapter['assessment']['questions'], 'answer'))), $where . ' : bonne réponse toujours au même rang');
            // Texte riche : sections, encadré « À retenir » et listes, en français comme en anglais.
            foreach (['fr' => 'À retenir', 'en' => 'Key points'] as $lang => $keep) {
                $theory = $chapter['theory'][$lang];
                self::assertStringContainsString('## ', $theory, $where . ' ' . $lang);
                self::assertStringContainsString($keep, $theory . $chapter['demonstration'][$lang], $where . ' ' . $lang);
                $html = AcademyRichText::render($theory);
                self::assertStringContainsString('<h', $html, $where . ' ' . $lang);
                self::assertStringContainsString('<strong>', $html, $where . ' ' . $lang);
            }
            self::assertStringNotContainsString('OTAN', $chapter['validation']['brief']['fr'], $where);
            self::assertNotEmpty($chapter['resources']);
        }
        $q = $course['qualification'];
        self::assertSame('ACAD-ATHENA', $q['code']);
        self::assertSame($course['license_code'], $q['license_code']);
        self::assertArrayHasKey(AthenaPlatformCourse::KEY, AcademyBuiltInCourses::qualifications());
    }

    /** L’anglais est un vrai texte anglais, pas une copie du français. */
    public function testEnglishIsNotACopyOfTheFrench(): void
    {
        $course = AcademyBuiltInCourses::find(AthenaPlatformCourse::KEY);
        self::assertIsArray($course);
        $walk = static function (mixed $node, string $path) use (&$walk): void {
            if (is_array($node) && isset($node['fr'], $node['en']) && is_string($node['fr']) && is_string($node['en'])) {
                if (mb_strlen($node['fr']) > 60) {
                    self::assertNotSame($node['fr'], $node['en'], $path);
                }

                return;
            }
            if (is_array($node)) {
                foreach ($node as $k => $child) {
                    $walk($child, $path . '.' . $k);
                }
            }
        };
        $walk($course, 'course');
    }

    /** @return list<string> */
    private static function citedAddresses(): array
    {
        $course = AcademyBuiltInCourses::find(AthenaPlatformCourse::KEY);
        self::assertIsArray($course);
        $text = (string) json_encode($course, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        preg_match_all('#`(/[^`\s]*)`#u', $text, $m);

        return array_values(array_unique($m[1]));
    }

    public function testEveryAddressCitedInTheCourseIsARealRoute(): void
    {
        $addresses = self::citedAddresses();
        self::assertGreaterThan(40, count($addresses), 'la formation cite les adresses des écrans');
        $routes = self::routes();
        self::assertNotEmpty($routes);
        $missing = [];
        foreach ($addresses as $address) {
            $path = (string) parse_url($address, PHP_URL_PATH);
            $path = $path === '/' ? '/' : rtrim($path, '/');
            $first = explode('/', trim($path, '/'))[0] ?? '';
            // Comme Router::dispatch() : la première route GET qui correspond répond.
            $hit = null;
            foreach ($routes as $route) {
                if ($route['method'] === 'GET' && preg_match($route['pattern'], $path)) {
                    $hit = $route;
                    break;
                }
            }
            // Une route attrape-tout (« /{slug} ») ne suffit pas : le premier segment doit être écrit dans la route.
            $routeFirst = $hit !== null ? (explode('/', trim((string) $hit['path'], '/'))[0] ?? '') : null;
            if ($hit === null || ($path !== '/' && $routeFirst !== $first)) {
                $missing[] = $address;
                continue;
            }
            // Une adresse déclarée telle quelle ne doit pas être captée avant par une route à paramètre (« …/{id} »).
            foreach ($routes as $route) {
                if ($route['method'] === 'GET' && $route['path'] === $path && $hit['path'] !== $path) {
                    $missing[] = $address . ' (captée par ' . $hit['path'] . ')';
                    break;
                }
            }
        }
        self::assertSame([], $missing, 'adresses citées sans route GET');
    }

    public function testAcademySpacesCitedExist(): void
    {
        $spaces = ['dashboard', 'catalogue', 'courses', 'classroom', 'laboratory', 'simulators', 'exams', 'library', 'notebook', 'record', 'instructor', 'studio', 'droits'];
        foreach (self::citedAddresses() as $address) {
            $path = (string) parse_url($address, PHP_URL_PATH);
            if (preg_match('#^/academy/([^/]+)$#', $path, $m) && !in_array($m[1], ['qr', 'formations'], true)) {
                self::assertContains($m[1], $spaces, $address);
            }
        }
    }
}
