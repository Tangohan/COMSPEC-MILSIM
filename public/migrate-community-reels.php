<?php

declare(strict_types=1);

/**
 * Point d’entrée web (document root = public/).
 * Délègue au script racine migrate-community-reels.php.
 */
require dirname(__DIR__) . '/migrate-community-reels.php';
