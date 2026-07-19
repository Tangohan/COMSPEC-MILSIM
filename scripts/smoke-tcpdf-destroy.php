<?php

/**
 * Reproduit Close() → _destroy(false) → __destruct → _destroy(true)
 * (crash historique : Undefined property TCPDF::$imagekeys).
 *
 * Usage : php scripts/smoke-tcpdf-destroy.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$tcpdf = $root . '/tcpdf/tcpdf.php';
if (!is_file($tcpdf)) {
    fwrite(STDERR, "tcpdf.php introuvable\n");
    exit(2);
}

$cache = $root . '/storage/app/tcpdf-cache';
if (!is_dir($cache)) {
    @mkdir($cache, 0775, true);
}
if (!defined('K_PATH_CACHE')) {
    define('K_PATH_CACHE', str_replace('\\', '/', $cache) . '/');
}

$seenImagekeys = false;

set_error_handler(static function (int $severity, string $message, string $file, int $line) use (&$seenImagekeys): bool {
    if (str_contains($message, 'TCPDF::$imagekeys')) {
        $seenImagekeys = true;
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
    // Bruits TCPDF hors sujet (float→int, deprecated…) : on ignore pour isoler imagekeys.
    if (str_contains(str_replace('\\', '/', $file), '/tcpdf.php')) {
        return true;
    }
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

require_once $tcpdf;

try {
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Write(0, 'Smoke TCPDF destroy');
    $out = $pdf->Output('', 'S');
    // Point critique : __destruct après Close()/_destroy(false) — peut être différé (refs circulaires).
    unset($pdf);
    if (function_exists('gc_collect_cycles')) {
        gc_collect_cycles();
    }

    if ($seenImagekeys) {
        fwrite(STDERR, "FAIL: warning TCPDF::\$imagekeys intercepté\n");
        exit(1);
    }
    if (!is_string($out) || $out === '' || !str_starts_with($out, '%PDF')) {
        fwrite(STDERR, "FAIL: sortie PDF invalide\n");
        exit(1);
    }

    echo "OK: Output(S) + GC sans Undefined property imagekeys (len=" . strlen($out) . ")\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n");
    exit(1);
}
