<?php

declare(strict_types=1);

/**
 * Injecte le secret du module officiel dans le fichier de compilation (ignoré par Git)
 * et, s'il manque, dans le .env local. Ne l'affiche pas.
 *
 * Usage : php tools/embed-client-attest.php
 */

$root = dirname(__DIR__);
require $root . '/app/Support/OfficialClientAttestation.php';

$envPath = $root . DIRECTORY_SEPARATOR . '.env';
$secret = '';
$lines = [];
if (is_file($envPath)) {
    $raw = file_get_contents($envPath);
    $lines = preg_split("/\r\n|\n|\r/", $raw === false ? '' : $raw) ?: [];
    foreach ($lines as $line) {
        if (preg_match('/^\s*COMSPEC_CLIENT_ATTEST_SECRET\s*=\s*([0-9a-fA-F]{64})\s*$/', $line, $m)) {
            $secret = strtolower($m[1]);
            break;
        }
    }
}

$created = false;
if ($secret === '') {
    $secret = bin2hex(random_bytes(32));
    $created = true;
    $block = ($lines !== [] && end($lines) !== '' ? "\n" : '')
        . "COMSPEC_CLIENT_ATTEST_SECRET={$secret}\n";
    if (is_file($envPath)) {
        file_put_contents($envPath, $block, FILE_APPEND);
    } else {
        file_put_contents($envPath, $block);
    }
}

$binary = hex2bin($secret);
if ($binary === false) {
    fwrite(STDERR, "Secret illisible.\n");
    exit(1);
}
$packed = \App\Support\OfficialClientAttestation::xorPack($binary);
$bytes = implode(', ', array_map(static fn (int $b): string => '0x' . strtoupper(sprintf('%02X', $b)), $packed));
$cs = <<<CS
namespace COMSPECExtension;

/// <summary>Généré par tools/embed-client-attest.php. Ne pas commiter.</summary>
internal static class ClientAttestMaterialLocal
{
    internal static readonly byte[] Packed =
    {
        {$bytes}
    };
}

CS;

$target = $root . '/mod/UptoDate/COMSPECExtension/ClientAttestMaterial.Local.cs';
file_put_contents($target, $cs);

echo $created
    ? "Secret créé dans .env et matériel de compilation écrit. Copiez COMSPEC_CLIENT_ATTEST_SECRET sur le serveur en même temps que le pack.\n"
    : "Matériel de compilation réécrit depuis le secret déjà présent dans .env.\n";
