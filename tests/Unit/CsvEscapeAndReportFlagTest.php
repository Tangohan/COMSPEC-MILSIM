<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AtakTacticalReportRepository;
use PHPUnit\Framework\TestCase;

/**
 * Deux 500 de production (25/10) : fputcsv() sans $escape (déprécié en PHP 8.4, converti en exception)
 * et has_attachments = '' refusé par MariaDB sur POST /api/atak/reports.
 */
final class CsvEscapeAndReportFlagTest extends TestCase
{
    public function testEveryCsvCallPassesEscapeExplicitly(): void
    {
        // Arguments attendus pour que $escape soit fourni : fputcsv(h, fields, sep, encl, escape), fgetcsv(h, len, sep, encl, escape).
        $missing = [];
        $root = dirname(__DIR__, 2);
        foreach (['app', 'scripts'] as $dir) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }
                $tokens = token_get_all((string) file_get_contents($file->getPathname()));
                $n = count($tokens);
                for ($i = 0; $i < $n; $i++) {
                    $t = $tokens[$i];
                    if (!is_array($t) || $t[0] !== T_STRING || !in_array(strtolower($t[1]), ['fputcsv', 'fgetcsv'], true)) {
                        continue;
                    }
                    $j = $i + 1;
                    while ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                        $j++;
                    }
                    if (($tokens[$j] ?? null) !== '(') {
                        continue;
                    }
                    $depth = 0;
                    $args = 1;
                    for (; $j < $n; $j++) {
                        $c = is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
                        if (in_array($c, ['(', '[', '{'], true) || (is_array($tokens[$j]) && in_array($tokens[$j][0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                            $depth++;
                        } elseif (in_array($c, [')', ']', '}'], true)) {
                            $depth--;
                            if ($depth === 0) {
                                break;
                            }
                        } elseif ($c === ',' && $depth === 1) {
                            $args++;
                        }
                    }
                    if ($args < 5) {
                        $missing[] = substr($file->getPathname(), strlen($root) + 1) . ':' . $t[2];
                    }
                }
            }
        }

        self::assertSame([], $missing, 'fputcsv/fgetcsv sans $escape explicite');
    }

    public function testCsvExportKeepsLegacyOutputWithoutDeprecation(): void
    {
        $fh = fopen('php://memory', 'w+');
        self::assertIsResource($fh);
        fputcsv($fh, ['Dupont "Doc"', 'a;b', 'c\\d'], ';', '"', '\\');
        rewind($fh);
        self::assertSame("\"Dupont \"\"Doc\"\"\";\"a;b\";\"c\\d\"\n", stream_get_contents($fh));
    }

    public function testAttachmentFlagIsAlwaysAnInteger(): void
    {
        foreach (['', null, false, 0, '0', 'false', 'no', ' ', 'abc'] as $v) {
            self::assertSame(0, AtakTacticalReportRepository::flag($v), var_export($v, true));
        }
        foreach ([true, 1, '1', 'true', 'TRUE', 'yes', 'on', 2, '3'] as $v) {
            self::assertSame(1, AtakTacticalReportRepository::flag($v), var_export($v, true));
        }
    }
}
