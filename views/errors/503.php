<?php

declare(strict_types=1);

$title = $title ?? 'Nous revenons bientôt';
$message = $message ?? 'Athena est momentanément fermé le temps d’une mise à jour.';
$appName = $appName ?? (function_exists('config') ? (string) config('app.name', 'Athena') : 'Athena');

require __DIR__ . '/maintenance.php';
