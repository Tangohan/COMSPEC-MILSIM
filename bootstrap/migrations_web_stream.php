<?php

declare(strict_types=1);

/**
 * Sortie navigateur pour le pipeline migrations (texte brut, flux immédiat).
 * Même comportement que l’ancien préambule de public/appliquer-ce-qui-manque-en-base.php.
 */
function migrations_web_begin_plain_response(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }

    @set_time_limit(0);
    @ini_set('max_execution_time', '0');
    @ini_set('output_buffering', '0');
    @ini_set('zlib.output_compression', '0');
    @ini_set('implicit_flush', '1');

    if (!headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Accel-Buffering: no');
    }

    while (ob_get_level() > 0) {
        @ob_end_flush();
    }

    // Remplissage : certains reverse-proxy / hébergeurs n’envoient la réponse qu’après ~2–4 Ko.
    echo str_repeat(' ', 2048) . "\n";
    @flush();

    static $fatalHandlerRegistered = false;
    if ($fatalHandlerRegistered) {
        return;
    }
    $fatalHandlerRegistered = true;

    register_shutdown_function(static function (): void {
        if (PHP_SAPI === 'cli') {
            return;
        }
        $err = error_get_last();
        if ($err === null) {
            return;
        }
        $t = (int) ($err['type'] ?? 0);
        if (!in_array($t, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }
        $msg = ($err['message'] ?? '') . ' — ' . ($err['file'] ?? '') . ':' . (string) ($err['line'] ?? '');
        echo "\n[ERREUR FATALE] " . $msg . "\n";
        @flush();
    });
}
