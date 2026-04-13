<?php

declare(strict_types=1);

/**
 * Alias web — même pipeline et même rendu que setup-database.php et appliquer-ce-qui-manque-en-base.php.
 */

$root = dirname(__DIR__);

require_once $root . '/bootstrap/env.php';
load_env($root);

require_once $root . '/bootstrap/migrations_web_stream.php';
migrations_web_begin_plain_response();

require $root . '/setup-database.php';
