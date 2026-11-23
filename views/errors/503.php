<?php

declare(strict_types=1);

$title = $title ?? '';
$message = $message ?? '';
$appName = $appName ?? (function_exists('config') ? (string) config('app.name', 'Athena') : 'Athena');

require __DIR__ . '/maintenance.php';
