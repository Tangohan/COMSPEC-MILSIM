<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Response::view() rend une page vide si le gabarit n'existe pas (aucune erreur, aucun journal) :
 * les pages /back-office/documents/{nomenclature,types,publier,compliance} pointaient vers « layout.back_office »,
 * qui n'a jamais existé. On vérifie aussi les partials inclus par chemin littéral.
 */
final class ViewTemplatesExistTest extends TestCase
{
    public function testLiteralViewsAndIncludesExist(): void
    {
        $root = str_replace('\\', '/', dirname(__DIR__, 2));
        $missing = [];
        foreach (['app', 'views', 'routes', 'mail'] as $dir) {
            if (!is_dir($root . '/' . $dir)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if (!str_ends_with($file->getFilename(), '.php')) {
                    continue;
                }
                $path = str_replace('\\', '/', $file->getPathname());
                $rel = substr($path, strlen($root) + 1);
                $src = (string) file_get_contents($path);
                $here = dirname($path);

                if (preg_match_all(
                    '/\b(?:require|include)(?:_once)?\s*\(?\s*(?:base_path\(\s*\'([^\'$]+\.php)\'\s*\)|__DIR__\s*\.\s*\'([^\'$]+\.php)\'|dirname\(__DIR__(?:,\s*(\d+))?\)\s*\.\s*\'([^\'$]+\.php)\')\s*\)?\s*;/',
                    $src,
                    $m,
                    PREG_SET_ORDER
                )) {
                    foreach ($m as $mm) {
                        if (($mm[1] ?? '') !== '') {
                            $target = $root . '/' . ltrim($mm[1], '/');
                            if (str_contains($mm[1], '.local.php')) {
                                continue; // fichiers de configuration propres au serveur
                            }
                        } elseif (($mm[2] ?? '') !== '') {
                            $target = $here . $mm[2];
                        } else {
                            $target = dirname($here, (int) (($mm[3] ?? '') !== '' ? $mm[3] : 1)) . $mm[4];
                        }
                        if (!is_file($target)) {
                            $missing[] = $rel . ' : ' . trim($mm[0]);
                        }
                    }
                }

                if (preg_match_all(
                    '/\bResponse::view\(\s*\'([A-Za-z0-9_\-\.\/]+)\'|[\'"]content[\'"]\s*=>\s*\'([A-Za-z0-9_\-\.\/]+)\'/',
                    $src,
                    $m,
                    PREG_SET_ORDER
                )) {
                    foreach ($m as $mm) {
                        $view = ($mm[1] ?? '') !== '' ? $mm[1] : ($mm[2] ?? '');
                        if ($view === '' || !str_contains($view, '.') && !str_contains($view, '/')) {
                            continue;
                        }
                        if (!is_file($root . '/views/' . str_replace('.', '/', $view) . '.php')) {
                            $missing[] = $rel . ' : vue ' . $view;
                        }
                    }
                }
            }
        }

        self::assertSame([], array_values(array_unique($missing)));
    }
}
