<?php

declare(strict_types=1);

/**
 * Pipeline complet + tous les fichiers migrations/*.sql (sauf schema.sql) + rapport BDD / APP.
 * Exemple d’URL : …/public/migrations-complet.php
 *
 * Toute personne connaissant l’URL peut lancer ce script. Protégez ou supprimez ce fichier si l’instance est exposée.
 */

$root = dirname(__DIR__);

require_once $root . '/bootstrap/env.php';
load_env($root);

define('COMSPEC_MIGRATIONS_WEB_FULL', true);

require_once $root . '/bootstrap/migrations_web_stream.php';
migrations_web_begin_plain_response();

require $root . '/setup-database.php';
