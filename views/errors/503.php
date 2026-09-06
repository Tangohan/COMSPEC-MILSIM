<?php

declare(strict_types=1);

$title = $title ?? 'Maintenance opérationnelle';
$message = $message ?? 'Le portail Athena est fermé le temps d’une intervention de fond.';
$appName = $appName ?? (function_exists('config') ? (string) config('app.name', 'Athena') : 'Athena');

require __DIR__ . '/maintenance.php';
