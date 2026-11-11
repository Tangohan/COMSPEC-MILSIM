<?php

declare(strict_types=1);

/**
 * Recopie le registre des papiers du mod SSE (CfgComspecDocuments.hpp, extensions, countries.json) dans
 * config/sse_document_types.php, pour que le site propose exactement les types de papiers du jeu, pays par pays.
 *
 *   php tools/sse/sync_document_types.php            réécrit config/sse_document_types.php
 *   php tools/sse/sync_document_types.php --check    0 si identique, 1 sinon
 */

$root = dirname(__DIR__, 2);
require_once $root . '/app/Support/SseDocumentTypes.php';

use App\Support\SseDocumentTypes;

$check = in_array('--check', array_slice($argv, 1), true);
$file = $root . '/config/sse_document_types.php';

try {
    $data = SseDocumentTypes::fromMod($root);
} catch (Throwable $e) {
    fwrite(STDERR, 'Registre du mod illisible : ' . $e->getMessage() . "\n");
    exit(2);
}
if ($data['types'] === [] || $data['countries'] === []) {
    fwrite(STDERR, "Registre du mod vide (CfgComspecDocuments.hpp ou countries.json).\n");
    exit(2);
}
$new = SseDocumentTypes::export($data);
// Comparaison sans tenir compte des fins de ligne (dépôt cloné en CRLF sous Windows).
$cur = is_file($file) ? str_replace("\r\n", "\n", (string) file_get_contents($file)) : '';
if ($check) {
    echo $new === $cur ? "Types de papiers identiques.\n" : "Types de papiers différents : lancer php tools/sse/sync_document_types.php\n";
    exit($new === $cur ? 0 : 1);
}
file_put_contents($file, $new);
echo count($data['types']) . ' types de papiers et ' . count($data['countries']) . " pays recopiés dans config/sse_document_types.php\n";
