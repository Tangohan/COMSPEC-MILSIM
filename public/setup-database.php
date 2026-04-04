<?php

/**
 * Interface web — même traitement que `php setup-database.php` (texte brut).
 */

header('Content-Type: text/plain; charset=utf-8');
require dirname(__DIR__) . '/setup-database.php';
