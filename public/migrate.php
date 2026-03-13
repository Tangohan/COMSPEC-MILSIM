<?php

/**
 * Point d'entrée web pour lancer les migrations (sans charger l'app).
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

require dirname(__DIR__) . '/migrate.php';
