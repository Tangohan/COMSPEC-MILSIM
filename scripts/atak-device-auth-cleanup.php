<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/app.php';
$result=(new \App\Repositories\AtakDeviceAuthRepository())->cleanup();
fwrite(STDOUT,json_encode(['ok'=>true]+$result,JSON_UNESCAPED_SLASHES).PHP_EOL);
