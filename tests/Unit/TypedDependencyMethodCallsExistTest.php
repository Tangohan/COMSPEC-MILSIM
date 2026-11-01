<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * Méthodes appelées sur une dépendance typée qui n'existent pas (renommées ou jamais écrites) :
 * `$this->events->listUpcomingForTenant()` (sessions Arma), `$this->enlistmentRepository->findCandidatePortalMessage()`
 * (signalement depuis le portail candidat), `$request->getParsedBody()` (passerelle ATAK, erreur 500).
 * PHPStan (niveau 0 en CI) ne vérifie pas ces appels.
 */
final class TypedDependencyMethodCallsExistTest extends TestCase
{
    public function testMethodsCalledOnTypedPropertiesAndRequestExist(): void
    {
        $root = str_replace('\\', '/', dirname(__DIR__, 2));
        $problems = [];
        $checked = 0;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/app', \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (!str_ends_with($file->getFilename(), '.php')) {
                continue;
            }
            $path = str_replace('\\', '/', $file->getPathname());
            $rel = substr($path, strlen($root) + 1);
            $src = (string) file_get_contents($path);

            if (preg_match_all('/\$request->(\w+)\s*\(/', $src, $m, PREG_OFFSET_CAPTURE)) {
                foreach ($m[1] as [$method, $offset]) {
                    $checked++;
                    if (!method_exists(Request::class, $method)) {
                        $problems[] = $rel . ':' . (substr_count(substr($src, 0, $offset), "\n") + 1) . ' Request::' . $method . '()';
                    }
                }
            }

            if (!preg_match('/^namespace\s+([^;]+);/m', $src, $ns)
                || !preg_match('/^(?:final\s+|abstract\s+|readonly\s+)*class\s+(\w+)/m', $src, $cls)) {
                continue;
            }
            $class = trim($ns[1]) . '\\' . $cls[1];
            try {
                if (!class_exists($class)) {
                    continue;
                }
            } catch (\Throwable) {
                continue; // classe qui étend une bibliothèque absente de l'environnement de test
            }
            $types = [];
            foreach ((new \ReflectionClass($class))->getProperties() as $property) {
                $type = $property->getType();
                if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                    $types[$property->getName()] = in_array($type->getName(), ['self', 'static'], true) ? $class : $type->getName();
                }
            }
            if ($types === [] || !preg_match_all('/\$this->(\w+)->(\w+)\s*\(/', $src, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($m as $call) {
                $property = $call[1][0];
                $method = $call[2][0];
                if (!isset($types[$property])) {
                    continue;
                }
                $target = $types[$property];
                try {
                    if (!class_exists($target) && !interface_exists($target)) {
                        continue;
                    }
                } catch (\Throwable) {
                    continue;
                }
                $checked++;
                if (!method_exists($target, $method) && !method_exists($target, '__call')) {
                    $problems[] = $rel . ':' . (substr_count(substr($src, 0, $call[0][1]), "\n") + 1) . ' ' . $target . '::' . $method . '()';
                }
            }
        }

        self::assertGreaterThan(5000, $checked);
        self::assertSame([], array_values(array_unique($problems)));
    }
}
