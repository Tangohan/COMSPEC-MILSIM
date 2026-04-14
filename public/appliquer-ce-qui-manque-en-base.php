<?php

declare(strict_types=1);

/**
 * Met à jour la base : schéma, extensions DDL, bootstraps, compléments (même enchaînement que setup-database).
 * Idempotent — ne fait qu’ajouter ce qui manque.
 *
 * URL (exemple avec préfixe /public) :
 *   …/public/appliquer-ce-qui-manque-en-base.php
 *
 * Attention : toute personne connaissant l’URL peut lancer ce script. Protégez ce fichier côté serveur
 * (accès restreint, pare-feu, suppression après usage) si l’instance est exposée sur Internet.
 */

$root = dirname(__DIR__);

require_once $root . '/bootstrap/env.php';
load_env($root);

require_once $root . '/bootstrap/migrations_web_stream.php';
migrations_web_begin_plain_response();

require $root . '/setup-database.php';
