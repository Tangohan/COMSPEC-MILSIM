<?php

declare(strict_types=1);

/**
 * Interface web — même traitement que `php setup-database.php` (texte brut, flux identique à appliquer-ce-qui-manque-en-base.php).
 */

$root = dirname(__DIR__);

require_once $root . '/bootstrap/env.php';
load_env($root);

require_once $root . '/bootstrap/migrations_web_stream.php';
migrations_web_begin_plain_response();

require $root . '/setup-database.php';
