<?php

declare(strict_types=1);

/**
 * Ancienne entrée de configuration fusionnée dans bootstrap (clé `maintenance`).
 * La maintenance applicative est pilotée par les tables `app_maintenance` / `app_maintenance_audit`
 * et l’écran super-admin `/admin/maintenance`.
 *
 * @deprecated Cette configuration n’est plus lue. L’ancien fichier `storage/maintenance.json`, lui, l’est
 * toujours : `public/index.php` le lit avant la maintenance planifiée. Il est posé par les mises à jour et
 * restaurations lancées depuis l’administration (ReleaseManager), par `public/maintenance-toggle.php` et par
 * `scripts/toggle-maintenance.php`. Pages : 503 HTML ; API : JSON « maintenance » (MaintenanceGuard).
 */
return [];
