#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Copie complète des données (base MySQL + fichiers métier) pour un rollback.
 *
 *   php scripts/data-snapshot.php create [--label=avant-migration] [--keep=10] [--db-only] [--storage-only]
 *   php scripts/data-snapshot.php list
 *   php scripts/data-snapshot.php show <id>
 *   php scripts/data-snapshot.php verify <id>
 *   php scripts/data-snapshot.php restore <id> --yes [--dry-run] [--db-only] [--storage-only] [--prune-storage]
 *   php scripts/data-snapshot.php prune [--keep=10]
 *
 * Détail : docs/technique/sauvegarde-donnees-completes.md
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce script s’utilise en ligne de commande uniquement.\n");
    exit(1);
}

@ini_set('memory_limit', '1024M');
@set_time_limit(0);

$root = dirname(__DIR__);
require_once $root . '/bootstrap/autoload.php';
require_once $root . '/bootstrap/app.php';

use App\Services\Backup\CompleteDataSnapshotService;

$argvList = array_values($argv);
array_shift($argvList);
$command = strtolower((string) ($argvList[0] ?? 'help'));
$args = array_slice($argvList, 1);

$log = static function (string $line): void {
    echo $line . "\n";
};

try {
    $service = CompleteDataSnapshotService::fromApp();
} catch (Throwable $e) {
    if (in_array($command, ['help', '-h', '--help'], true)) {
        echo snapshot_help();
        exit(0);
    }
    $storageOnly = in_array('--storage-only', $argvList, true);
    $withoutDb = ['list', 'show', 'verify', 'prune'];
    if ($storageOnly || in_array($command, $withoutDb, true)) {
        $service = new CompleteDataSnapshotService($root);
        $log('Base indisponible — opération limitée aux fichiers et au catalogue des copies.');
    } else {
        fwrite(STDERR, 'Connexion impossible : ' . $e->getMessage() . "\n");
        exit(1);
    }
}

switch ($command) {
    case 'help':
    case '-h':
    case '--help':
        echo snapshot_help();
        exit(0);

    case 'create':
        $flags = snapshot_flags($args);
        $manifest = $service->create(
            (string) ($flags['label'] ?? ''),
            !($flags['storage-only'] ?? false),
            !($flags['db-only'] ?? false),
            isset($flags['keep']) ? (int) $flags['keep'] : null,
            $log,
        );
        echo "\nID : " . $manifest['id'] . "\n";
        echo 'Dossier : ' . $service->snapshotDir((string) $manifest['id']) . "\n";
        echo "Restauration : php scripts/data-snapshot.php restore " . $manifest['id'] . " --yes\n";
        exit(0);

    case 'list':
        $rows = $service->list();
        if ($rows === []) {
            echo "Aucune copie. Dossier : " . $service->snapshotRoot() . "\n";
            exit(0);
        }
        foreach ($rows as $row) {
            $db = !empty($row['database']['included']) ? 'base' : '-';
            $st = !empty($row['storage']['included']) ? 'fichiers' : '-';
            echo sprintf(
                "%s  %s  %s+%s  %s\n",
                (string) ($row['id'] ?? ''),
                (string) ($row['created_at'] ?? ''),
                $db,
                $st,
                (string) ($row['label'] ?? ''),
            );
        }
        exit(0);

    case 'show':
        $id = snapshot_id($args, $service);
        $manifest = $service->readManifest($id);
        if ($manifest === null) {
            fwrite(STDERR, "Copie introuvable.\n");
            exit(1);
        }
        echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        exit(0);

    case 'verify':
        $id = snapshot_id($args, $service);
        $check = $service->verify($id);
        if ($check['ok']) {
            echo "Copie intacte : {$id}\n";
            exit(0);
        }
        fwrite(STDERR, "Copie altérée : {$id}\n");
        foreach ($check['errors'] as $err) {
            fwrite(STDERR, '  - ' . $err . "\n");
        }
        exit(1);

    case 'restore':
        $flags = snapshot_flags($args);
        $id = snapshot_id($args, $service);
        $dry = (bool) ($flags['dry-run'] ?? false);
        $yes = (bool) ($flags['yes'] ?? false);
        if (!$dry && !$yes) {
            fwrite(STDERR, "La restauration écrase les données actuelles. Relancez avec --yes (ou --dry-run).\n");
            exit(1);
        }
        $result = $service->restore(
            $id,
            !($flags['storage-only'] ?? false),
            !($flags['db-only'] ?? false),
            (bool) ($flags['prune-storage'] ?? false),
            $dry,
            $log,
        );
        echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        exit(0);

    case 'prune':
        $flags = snapshot_flags($args);
        $removed = $service->prune(isset($flags['keep']) ? (int) $flags['keep'] : null);
        echo $removed === 0 ? "Rien à retirer.\n" : "Copies retirées : {$removed}.\n";
        exit(0);

    default:
        fwrite(STDERR, "Commande inconnue : {$command}\n\n");
        echo snapshot_help();
        exit(1);
}

/**
 * @param list<string> $args
 * @return array<string, mixed>
 */
function snapshot_flags(array $args): array
{
    $out = [];
    foreach ($args as $arg) {
        if ($arg === '--yes' || $arg === '-y') {
            $out['yes'] = true;
            continue;
        }
        if ($arg === '--dry-run') {
            $out['dry-run'] = true;
            continue;
        }
        if ($arg === '--db-only') {
            $out['db-only'] = true;
            continue;
        }
        if ($arg === '--storage-only') {
            $out['storage-only'] = true;
            continue;
        }
        if ($arg === '--prune-storage') {
            $out['prune-storage'] = true;
            continue;
        }
        if (str_starts_with($arg, '--label=')) {
            $out['label'] = substr($arg, 8);
            continue;
        }
        if (str_starts_with($arg, '--keep=')) {
            $out['keep'] = substr($arg, 7);
        }
    }

    return $out;
}

/**
 * @param list<string> $args
 */
function snapshot_id(array $args, CompleteDataSnapshotService $service): string
{
    foreach ($args as $arg) {
        if (str_starts_with($arg, '--')) {
            continue;
        }
        return $service->assertSnapshotId($arg);
    }
    throw new RuntimeException('Indiquez l’identifiant de la copie.');
}

function snapshot_help(): string
{
    return <<<TXT
Copie complète des données Athena (base + fichiers) pour rollback.

  php scripts/data-snapshot.php create [--label=avant-migration] [--keep=10]
  php scripts/data-snapshot.php create --db-only
  php scripts/data-snapshot.php create --storage-only --label=photos
  php scripts/data-snapshot.php list
  php scripts/data-snapshot.php show <id>
  php scripts/data-snapshot.php verify <id>
  php scripts/data-snapshot.php restore <id> --dry-run
  php scripts/data-snapshot.php restore <id> --yes
  php scripts/data-snapshot.php restore <id> --yes --prune-storage
  php scripts/data-snapshot.php prune [--keep=10]

Avant une migration ou une opération irréversible :
  php scripts/data-snapshot.php create --label=avant-migration

Les copies vont dans storage/snapshots/ (hors Git, hors web). Voir
docs/technique/sauvegarde-donnees-completes.md

TXT;
}
