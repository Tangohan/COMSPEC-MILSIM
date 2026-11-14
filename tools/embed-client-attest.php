<?php

declare(strict_types=1);

/**
 * Écrit le matériel de compilation du module officiel (ClientAttestMaterial.Local.cs, ignoré par Git) pour la
 * version de COMSPEC Link de ce dépôt. N'affiche aucun secret.
 *
 * Le module ne reçoit que le secret de SA version, calculé depuis le maître (COMSPEC_CLIENT_ATTEST_MASTER) : le
 * maître reste dans le .env (machine de build et serveur). À relancer à chaque changement de version de la DLL ;
 * la compilation échoue si le matériel est celui d'une autre version.
 * L'ancien secret unique (COMSPEC_CLIENT_ATTEST_SECRET), s'il est encore dans le .env, est joint pour que le pack
 * reste accepté par un serveur qui n'a pas encore le maître.
 *
 * Usage : php tools/embed-client-attest.php [--env=CHEMIN_DU_.env]
 *   --env : .env qui porte les secrets (défaut : celui de ce dépôt). Depuis un worktree, pointer le dépôt principal.
 */

$root = dirname(__DIR__);
require $root . '/app/Support/OfficialClientAttestation.php';

use App\Support\OfficialClientAttestation;

$envPath = $root . DIRECTORY_SEPARATOR . '.env';
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--env=')) {
        $envPath = substr($arg, 6);
    } else {
        fwrite(STDERR, "Argument inconnu : {$arg}\n");
        exit(1);
    }
}

$source = (string) @file_get_contents($root . '/mod/UptoDate/COMSPECExtension/Extension.cs');
if (preg_match('/const string ExtensionVersion = "(\d+(?:\.\d+){1,3})";/', $source, $m) !== 1) {
    fwrite(STDERR, "Version de COMSPEC Link introuvable dans Extension.cs.\n");
    exit(1);
}
$version = $m[1];

$read = static function (string $name) use ($envPath): string {
    $raw = is_file($envPath) ? (string) file_get_contents($envPath) : '';

    return preg_match('/^\s*' . $name . '\s*=\s*([0-9a-fA-F]{64})\s*$/m', $raw, $m) === 1 ? strtolower($m[1]) : '';
};

$master = $read('COMSPEC_CLIENT_ATTEST_MASTER');
$created = false;
if ($master === '') {
    $master = bin2hex(random_bytes(32));
    $created = true;
    $raw = is_file($envPath) ? (string) file_get_contents($envPath) : '';
    $block = ($raw !== '' && !str_ends_with($raw, "\n") ? "\n" : '') . "COMSPEC_CLIENT_ATTEST_MASTER={$master}\n";
    if (file_put_contents($envPath, $block, FILE_APPEND) === false) {
        fwrite(STDERR, "Écriture impossible : {$envPath}\n");
        exit(1);
    }
}
$legacy = $read('COMSPEC_CLIENT_ATTEST_SECRET');

$bytes = static function (string $binary): string {
    return implode(', ', array_map(
        static fn (int $b): string => '0x' . strtoupper(sprintf('%02X', $b)),
        OfficialClientAttestation::xorPack($binary)
    ));
};
$packed = $bytes(OfficialClientAttestation::versionSecret((string) hex2bin($master), $version));
$legacyPacked = $legacy !== '' ? $bytes((string) hex2bin($legacy)) : '';
$cs = <<<CS
namespace COMSPECExtension;

/// <summary>Généré par tools/embed-client-attest.php pour COMSPEC Link {$version}. Ne pas commiter.</summary>
internal static class ClientAttestMaterialLocal
{
    internal const string Version = "{$version}";

    internal static readonly byte[] Packed =
    {
        {$packed}
    };

    internal static readonly byte[] LegacyPacked =
    {
        {$legacyPacked}
    };
}

CS;

if (file_put_contents($root . '/mod/UptoDate/COMSPECExtension/ClientAttestMaterial.Local.cs', $cs) === false) {
    fwrite(STDERR, "Écriture du matériel de compilation impossible.\n");
    exit(1);
}

echo "Matériel de compilation écrit pour COMSPEC Link {$version}"
    . ($legacy !== '' ? ', ancien secret joint' : ', sans ancien secret') . ".\n";
if ($created) {
    echo "Maître créé dans {$envPath} : copiez la ligne COMSPEC_CLIENT_ATTEST_MASTER sur le serveur avant de relever le plancher de version.\n";
}
