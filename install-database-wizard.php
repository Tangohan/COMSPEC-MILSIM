<?php

declare(strict_types=1);

/**
 * Point d’entrée racine : délègue à l’assistant web dans public/.
 * URL typique : https://hôte/public/install-database-wizard.php (document root = public/)
 * ou, si le vhost pointe sur la racine du projet : /install-database-wizard.php
 *
 * Après saisie MySQL, l’assistant exécute setup-database.php → run-migrations.php (incl. rbac_three_layer).
 */
require __DIR__ . '/public/install-database-wizard.php';
