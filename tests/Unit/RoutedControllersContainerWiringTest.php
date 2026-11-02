<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Un contrôleur routé dont le constructeur exige des dépendances doit être déclaré dans le Container :
 * sinon le Router fait `new $class()` et la page tombe en erreur 500 (incident /admin/recherche du 01/11/2026).
 */
final class RoutedControllersContainerWiringTest extends TestCase
{
    public function testEveryRoutedControllerWithRequiredDependenciesIsInContainer(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = '';
        foreach (glob($root . '/routes/*.php') ?: [] as $file) {
            $routes .= (string) file_get_contents($file);
        }
        $container = '';
        foreach (glob($root . '/app/Core/Container*.php') ?: [] as $file) {
            $container .= (string) file_get_contents($file);
        }
        preg_match_all('/\\\\?(App\\\\Controllers\\\\[A-Za-z0-9_\\\\]+)::class/', $routes, $m);
        // Contrôleurs importés par « use » et cités par leur nom court (codes prioritaires en erreur, 02/11/2026).
        preg_match_all('/^use\s+(App\\\\Controllers\\\\[A-Za-z0-9_\\\\]+);/m', $routes, $imports);
        $missing = [];
        foreach (array_unique(array_merge($m[1], $imports[1])) as $class) {
            if (!class_exists($class)) {
                continue;
            }
            $ctor = (new \ReflectionClass($class))->getConstructor();
            if ($ctor === null || $ctor->getNumberOfRequiredParameters() === 0) {
                continue;
            }
            $short = substr($class, strrpos($class, '\\') + 1);
            if (!preg_match('/\b' . preg_quote($short, '/') . '::class\s*=>/', $container)) {
                $missing[] = $class;
            }
        }
        self::assertSame([], $missing, 'Contrôleurs routés à déclarer dans app/Core/Container.php');
    }
}
