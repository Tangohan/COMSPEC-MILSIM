<?php

declare(strict_types=1);

/**
 * Écrit addons/generator/functions/fn_kitPools.sqf depuis les kits de contenu du site (config/sse_kits/kits.json,
 * App\Support\SseKitCatalog) : viviers de prénoms, noms, surnoms, nationalités, langues, indicatifs et véhicules de
 * chaque région narrative du générateur SSE (comspec_sse_fnc_getNarrativePools), en jeu comme sur Athena.
 *
 *   php tools/gen_kit_pools.php          # régénère le fichier (versionné)
 *   php tools/gen_kit_pools.php --check  # échoue si le fichier versionné diffère
 */

$repo = dirname(__DIR__, 3);
require $repo . '/app/Support/SseKitCatalog.php';
require $repo . '/app/Support/SseIdRealCountryCatalog.php';
require $repo . '/app/Services/Sse/SseIdentityGeneratorService.php';

use App\Services\Sse\SseIdentityGeneratorService as Gen;
use App\Support\SseKitCatalog as Kits;

$out = dirname(__DIR__) . '/addons/generator/functions/fn_kitPools.sqf';
$sqf = Kits::narrativeSqf(static fn (string $region): array => Gen::regionPool($region));

if (in_array('--check', $argv, true)) {
    $have = is_file($out) ? str_replace("\r\n", "\n", (string) file_get_contents($out)) : '';
    if ($have !== $sqf) {
        fwrite(STDERR, "fn_kitPools.sqf n'est pas à jour : php tools/gen_kit_pools.php\n");
        exit(1);
    }
    echo 'fn_kitPools.sqf à jour (' . count(Kits::REGIONS) . " régions).\n";
    exit(0);
}
file_put_contents($out, $sqf);
echo 'fn_kitPools.sqf écrit (' . count(Kits::REGIONS) . " régions).\n";
