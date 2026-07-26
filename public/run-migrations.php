<?php

declare(strict_types=1);

/**
 * Point d’entrée web (document root = public/).
 * Délègue au pipeline racine run-migrations.php.
 */
require dirname(__DIR__) . '/run-migrations.php';
