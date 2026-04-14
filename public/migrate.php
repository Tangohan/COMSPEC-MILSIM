<?php

declare(strict_types=1);

/**
 * Point d’entrée web — même rendu que setup-database / run-migrations / appliquer-ce-qui-manque-en-base.
 */

require dirname(__DIR__) . '/migrate.php';
