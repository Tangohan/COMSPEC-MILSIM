<?php

declare(strict_types=1);

// Overwatch est une composition alternative du véritable client ATAK. Cette vue
// ne duplique aucune donnée et ne simule aucun contact : atak.php conserve tous
// ses endpoints, sockets, contrôles de droits, cartes Leaflet et modules métier.
$atakOverwatchBeta = true;
require base_path('views/atak.php');
