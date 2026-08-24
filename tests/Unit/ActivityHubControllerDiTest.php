<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\ActivityHubController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;

final class ActivityHubControllerDiTest extends TestCase
{
    public function testConstructorDependenciesAreWiredInContainerIntegrations(): void
    {
        $ctor = (new ReflectionClass(ActivityHubController::class))->getConstructor();
        self::assertNotNull($ctor);

        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Core/ContainerIntegrations.php');
        $needle = 'new \\App\\Controllers\\Web\\ActivityHubController(';
        $start = strpos($src, $needle);
        self::assertNotFalse($start);
        $open = $start + strlen($needle) - 1;
        $depth = 0;
        $end = null;
        $len = strlen($src);
        for ($i = $open; $i < $len; $i++) {
            $ch = $src[$i];
            if ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth--;
                if ($depth === 0) {
                    $end = $i;
                    break;
                }
            }
        }
        self::assertNotNull($end);
        $block = substr($src, $open, $end - $open);

        foreach ($ctor->getParameters() as $param) {
            $type = $param->getType();
            self::assertInstanceOf(ReflectionNamedType::class, $type);
            $class = $type->getName();
            $short = substr($class, strrpos($class, '\\') + 1);
            self::assertStringContainsString(
                $short . '::class',
                $block,
                'Le câblage DI de ActivityHubController omet ' . $class
            );
        }
    }

    public function testFifthDependencyIsOptionalForLegacyFourArgumentWiring(): void
    {
        $ctor = (new ReflectionClass(ActivityHubController::class))->getConstructor();
        self::assertNotNull($ctor);
        self::assertSame(5, $ctor->getNumberOfParameters());
        self::assertSame(4, $ctor->getNumberOfRequiredParameters());
    }
}
