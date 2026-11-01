<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Le Container est écrit à la main : trois erreurs de prod en sont venues
 * (« Unknown service: …TablePdfExportService », « Too few arguments …DiscordShowcaseController::__construct »,
 * « DoctrinePublicationService::__construct(): Argument #8 must be UnitRepository, DoctrineNotificationService given »).
 * Ces tests relisent app/Core/Container*.php sans rien instancier.
 */
final class ContainerDefinitionsConsistencyTest extends TestCase
{
    private const FILES = ['app/Core/Container.php', 'app/Core/ContainerIntegrations.php'];

    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /** @return array<string, string> alias => classe */
    private static function uses(string $src): array
    {
        $out = [];
        preg_match_all('/^use\s+([A-Za-z0-9_\\\\]+)(?:\s+as\s+(\w+))?;/m', $src, $m, PREG_SET_ORDER);
        foreach ($m as $u) {
            $fq = ltrim($u[1], '\\');
            $alias = ($u[2] ?? '') !== '' ? $u[2] : substr($fq, (int) strrpos($fq, '\\') + (str_contains($fq, '\\') ? 1 : 0));
            $out[$alias] = $fq;
        }

        return $out;
    }

    private static function resolve(string $name, array $uses, string $namespace): string
    {
        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }
        $first = explode('\\', $name)[0];
        if (isset($uses[$first])) {
            return $uses[$first] . substr($name, strlen($first));
        }

        return ($namespace !== '' ? $namespace . '\\' : '') . $name;
    }

    private static function namespaceOf(string $src): string
    {
        return preg_match('/^namespace\s+([^;]+);/m', $src, $m) ? trim($m[1]) : '';
    }

    /** @return array<string, true> classes déclarées comme clés du Container (minuscules) */
    private static function containerKeys(): array
    {
        $keys = [];
        foreach (self::FILES as $file) {
            $src = (string) file_get_contents(self::root() . '/' . $file);
            $uses = self::uses($src);
            preg_match_all('/^\s*((?:\\\\?[A-Za-z0-9_\\\\]+::class\s*,\s*)*\\\\?[A-Za-z0-9_\\\\]+::class)\s*=>/m', $src, $m);
            foreach ($m[1] as $group) {
                preg_match_all('/(\\\\?[A-Za-z0-9_\\\\]+)::class/', $group, $names);
                foreach ($names[1] as $name) {
                    $keys[strtolower(self::resolve($name, $uses, 'App\\Core'))] = true;
                }
            }
        }

        return $keys;
    }

    public function testEveryContainerGetTargetIsDeclared(): void
    {
        $keys = self::containerKeys();
        self::assertGreaterThan(200, count($keys));
        $root = self::root();
        $missing = [];
        foreach (['app', 'routes', 'views', 'scripts', 'bootstrap'] as $dir) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if (!str_ends_with($file->getFilename(), '.php')) {
                    continue;
                }
                $path = str_replace('\\', '/', $file->getPathname());
                $rel = substr($path, strlen($root) + 1);
                $src = (string) file_get_contents($path);
                $inContainer = in_array($rel, self::FILES, true);
                $re = $inContainer
                    ? '/(?:Container::get|self::get)\(\s*(\\\\?[A-Za-z0-9_\\\\]+)::class/'
                    : '/Container::get\(\s*(\\\\?[A-Za-z0-9_\\\\]+)::class/';
                if (!preg_match_all($re, $src, $m, PREG_OFFSET_CAPTURE)) {
                    continue;
                }
                $uses = self::uses($src);
                $namespace = $inContainer ? 'App\\Core' : self::namespaceOf($src);
                foreach ($m[1] as [$name, $offset]) {
                    if (in_array(strtolower($name), ['self', 'static'], true)) {
                        continue;
                    }
                    $class = self::resolve($name, $uses, $namespace);
                    if (!isset($keys[strtolower($class)])) {
                        $missing[] = $rel . ':' . (substr_count(substr($src, 0, $offset), "\n") + 1) . ' ' . $class;
                    }
                }
            }
        }

        self::assertSame([], $missing, 'Container::get() vers un service absent : « Unknown service » en production');
    }

    public function testConstructorCallsInContainerMatchSignatures(): void
    {
        $problems = [];
        $checked = 0;
        foreach (self::FILES as $file) {
            $src = (string) file_get_contents(self::root() . '/' . $file);
            $uses = self::uses($src);
            $tokens = \PhpToken::tokenize($src);
            $count = count($tokens);
            for ($i = 0; $i < $count; $i++) {
                if (!$tokens[$i]->is(T_NEW)) {
                    continue;
                }
                $j = $i + 1;
                while ($j < $count && $tokens[$j]->isIgnorable()) {
                    $j++;
                }
                if (!$tokens[$j]->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])) {
                    continue;
                }
                $class = self::resolve($tokens[$j]->text, $uses, 'App\\Core');
                $line = $tokens[$j]->line;
                $j++;
                while ($j < $count && $tokens[$j]->isIgnorable()) {
                    $j++;
                }
                $args = [];
                if ($j < $count && $tokens[$j]->text === '(') {
                    [$args, $j] = self::readArguments($tokens, $j);
                }
                if (!class_exists($class)) {
                    continue;
                }
                $ctor = (new \ReflectionClass($class))->getConstructor();
                $params = $ctor?->getParameters() ?? [];
                $variadic = $params !== [] && end($params)->isVariadic();
                $where = $file . ':' . $line . ' new ' . $class;
                $positional = [];
                $named = [];
                foreach ($args as $arg) {
                    if (preg_match('/^([A-Za-z_]\w*)\s*:(?!:)\s*(.+)$/s', $arg, $nm)) {
                        $named[$nm[1]] = $nm[2];
                    } elseif (!str_starts_with($arg, '...')) {
                        $positional[] = $arg;
                    } else {
                        continue 2;
                    }
                }
                $checked++;
                if (!$variadic && count($positional) + count($named) > count($params)) {
                    $problems[] = $where . ' : ' . (count($positional) + count($named)) . ' argument(s) pour ' . count($params) . ' paramètre(s)';
                }
                foreach ($params as $k => $param) {
                    $expr = $named[$param->getName()] ?? ($positional[$k] ?? null);
                    if ($expr === null) {
                        if (!$param->isOptional()) {
                            $problems[] = $where . ' : paramètre obligatoire $' . $param->getName() . ' non fourni';
                        }
                        continue;
                    }
                    $given = self::serviceClassOf($expr, $uses);
                    $type = $param->getType();
                    if ($given === null || !$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                        continue;
                    }
                    if (!class_exists($given) && !interface_exists($given)) {
                        continue;
                    }
                    if (!is_a($given, $type->getName(), true)) {
                        $problems[] = $where . ' : argument $' . $param->getName() . ' attend ' . $type->getName() . ', reçoit ' . $given;
                    }
                }
            }
        }

        self::assertGreaterThan(300, $checked);
        self::assertSame([], $problems);
    }

    /**
     * @param list<\PhpToken> $tokens
     * @return array{0: list<string>, 1: int}
     */
    private static function readArguments(array $tokens, int $open): array
    {
        $depth = 0;
        $args = [];
        $current = '';
        for ($k = $open, $n = count($tokens); $k < $n; $k++) {
            $t = $tokens[$k]->text;
            if ($t === '(' || $t === '[' || $t === '{') {
                $depth++;
                if ($depth === 1) {
                    continue;
                }
            } elseif ($t === ')' || $t === ']' || $t === '}') {
                $depth--;
                if ($depth === 0) {
                    if (trim($current) !== '') {
                        $args[] = trim($current);
                    }

                    return [$args, $k];
                }
            } elseif ($t === ',' && $depth === 1) {
                $args[] = trim($current);
                $current = '';
                continue;
            }
            if (!$tokens[$k]->is([T_COMMENT, T_DOC_COMMENT])) {
                $current .= $t;
            }
        }

        return [$args, $open];
    }

    /** Classe obtenue par l'expression : self::get(X::class), Container::get(X::class) ou new X(...). */
    private static function serviceClassOf(string $expr, array $uses): ?string
    {
        if (preg_match('/^(?:self|static|\\\\?(?:App\\\\Core\\\\)?Container)::get\(\s*(\\\\?[A-Za-z0-9_\\\\]+)::class\s*\)$/', $expr, $m)
            || preg_match('/^new\s+(\\\\?[A-Za-z0-9_\\\\]+)\s*\(/', $expr, $m)) {
            return self::resolve($m[1], $uses, 'App\\Core');
        }

        return null;
    }
}
