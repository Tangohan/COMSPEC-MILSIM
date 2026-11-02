<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Scripts cron, étapes de migration, vues : PHPStan n'analyse que app/, une erreur de syntaxe ailleurs
 * ne se voit qu'en production (scripts/training-expire.php ne s'exécutait plus : parenthèse en trop).
 */
final class PhpSourcesParseTest extends TestCase
{
    public function testEveryPhpFileOutsideAppParses(): void
    {
        $root = dirname(__DIR__, 2);
        $files = glob($root . '/*.php') ?: [];
        foreach (['scripts', 'routes', 'database', 'bootstrap', 'views', 'mail', 'lang', 'config'] as $dir) {
            if (!is_dir($root . '/' . $dir)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if (str_ends_with($file->getFilename(), '.php')) {
                    $files[] = $file->getPathname();
                }
            }
        }
        self::assertGreaterThan(500, count($files));
        $errors = [];
        foreach ($files as $file) {
            try {
                token_get_all((string) file_get_contents($file), TOKEN_PARSE);
            } catch (\ParseError $e) {
                $errors[] = substr(str_replace('\\', '/', $file), strlen(str_replace('\\', '/', $root)) + 1)
                    . ':' . $e->getLine() . ' ' . $e->getMessage();
            }
        }

        self::assertSame([], $errors);
    }
}
