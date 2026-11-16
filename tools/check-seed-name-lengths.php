<?php

declare(strict_types=1);

/**
 * Contrôle des longueurs des libellés semés à la création d'une communauté, sans base MySQL.
 *
 *   php tools/check-seed-name-lengths.php            dépassements (code de sortie 1 s'il y en a)
 *   php tools/check-seed-name-lengths.php --limits   en plus, longueurs des colonnes lues dans les migrations
 *
 * Voir App\Support\Schema\SeedNameLengthAudit : un libellé plus long que sa colonne fait échouer la création en MySQL
 * strict (SQLSTATE 22001, « Data too long for column 'name' »).
 */

$root = dirname(__DIR__);
require $root . '/app/Support/helpers.php';
spl_autoload_register(static function (string $class) use ($root): void {
    if (str_starts_with($class, 'App\\')) {
        $file = $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

use App\Support\Schema\SeedNameLengthAudit;

$limits = SeedNameLengthAudit::columnLimits($root);
if (in_array('--limits', $argv, true)) {
    foreach ($limits as $column => $len) {
        echo str_pad($column, 48) . ' VARCHAR(' . $len . ")\n";
    }
    echo "\n";
}
$violations = SeedNameLengthAudit::violations($root, $limits);
foreach ($violations as $line) {
    echo $line . "\n";
}
echo $violations === [] ? "[OK] Tous les libellés semés tiennent dans leur colonne.\n" : '[ERREUR] ' . count($violations) . " dépassement(s).\n";
exit($violations === [] ? 0 : 1);
