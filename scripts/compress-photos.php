<?php

declare(strict_types=1);

/**
 * Allège d'un coup tout le stock de photos terrain (sans la limite de 90 s du passage automatique).
 * Même nom de fichier : identifiants et URL inchangés.
 *
 * Usage, à la racine du site :
 *   php scripts/compress-photos.php --dry-run   (montre la place gagnée, ne touche à rien)
 *   php scripts/compress-photos.php
 *
 * Le passage automatique (php scripts/cron-run.php, toutes les 5 minutes) fait la même chose par
 * tranches d'au plus 90 s, une fois par heure : job « photo_compress ».
 */

$root = dirname(__DIR__);

require_once $root . '/bootstrap/autoload.php';
require_once $root . '/bootstrap/app.php';

use App\Services\Cron\Jobs\PhotoCompressCronJob;

$dryRun = in_array('--dry-run', $argv, true);
$job = new PhotoCompressCronJob(null, PHP_INT_MAX, $dryRun);
$result = $job->run();

echo date('Y-m-d H:i:s') . ($dryRun ? ' — [SIMULATION] ' : ' — ') . $result['summary'] . "\n";
foreach ($result['details']['error_samples'] ?? [] as $line) {
    echo "  ! {$line}\n";
}

exit($result['ok'] ? 0 : 1);
