<?php

declare(strict_types=1);

/**
 * Plancher de version des DLL du jeu (SEC-001) : lecture, réglage, retrait, essai.
 *
 * À lancer sur le serveur, depuis la racine de l'application, avec le PHP du site :
 *
 *     php8.4 tools/game-client-gate.php status
 *     php8.4 tools/game-client-gate.php set 2.0.96 --atak 2.4.1      après la publication Steam du correctif
 *     php8.4 tools/game-client-gate.php clear                         retour arrière : plus aucun blocage
 *     php8.4 tools/game-client-gate.php check "COMSPECExtension/2.0.95" [--path /api/atak/units] [--min 2.0.96] [--atak 2.4.1]
 *
 * set et clear écrivent les réglages plateforme game_min_link_version et game_min_atak_version, les relisent,
 * retirent le cache du site (storage/cache/atak-gate/client-version-floor.json, recréé par PHP-FPM avec ses droits)
 * et laissent une trace dans le journal d'audit. Effet sur le site : 30 s au plus.
 * set sans --atak garde la version ATAK déjà réglée ; clear vide les deux réglages (état par défaut).
 * check n'écrit rien et ne contacte aucun serveur : il affiche la réponse qu'Athena ferait à ce User-Agent
 * (plancher en base, ou celui de --min).
 *
 * Code de retour : 0 succès, 1 échec, 2 usage incorrect.
 */

use App\Core\Database;
use App\Repositories\PlatformSettingsRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Support\GameClientVersionGate as Gate;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
require $root . '/bootstrap/app.php';

const GATE_KEYS = [Gate::SETTING_MIN_LINK_VERSION, Gate::SETTING_MIN_ATAK_VERSION];

$argvList = array_slice($argv ?? [], 1);
$command = (string) (array_shift($argvList) ?? '');
$positional = [];
$options = [];
for ($i = 0, $n = count($argvList); $i < $n; $i++) {
    $arg = (string) $argvList[$i];
    if (str_starts_with($arg, '--')) {
        $eq = strpos($arg, '=');
        if ($eq !== false) {
            $options[substr($arg, 2, $eq - 2)] = substr($arg, $eq + 1);
        } else {
            $options[substr($arg, 2)] = (string) ($argvList[$i + 1] ?? '');
            $i++;
        }
        continue;
    }
    $positional[] = $arg;
}

function gate_usage(string $error = ''): int
{
    if ($error !== '') {
        fwrite(STDERR, 'Erreur : ' . $error . "\n\n");
    }
    fwrite(STDERR, "Usage :\n"
        . "  php tools/game-client-gate.php status\n"
        . "  php tools/game-client-gate.php set <version Link> [--atak <version ATAK affichée>]\n"
        . "  php tools/game-client-gate.php clear\n"
        . "  php tools/game-client-gate.php check \"<User-Agent>\" [--path /api/atak/units] [--min <version Link>] [--atak <version ATAK>]\n");

    return 2;
}

/** @param array<string, string> $raw */
function gate_print_state(array $raw): void
{
    echo "Plancher de version des DLL du jeu (SEC-001)\n";
    foreach (GATE_KEYS as $key) {
        $value = $raw[$key] ?? null;
        $shown = $value === null ? '(absent)' : ($value === '' ? '(vide)' : $value);
        if ($value !== null && $value !== '' && Gate::normalizeFloor($value) === null) {
            $shown .= '  <- illisible, ignoré';
        }
        echo '  ' . str_pad($key, 22) . ' : ' . $shown . "\n";
    }
    $link = Gate::normalizeFloor($raw[Gate::SETTING_MIN_LINK_VERSION] ?? '') ?? '';
    $atak = Gate::normalizeFloor($raw[Gate::SETTING_MIN_ATAK_VERSION] ?? '') ?? '';
    if ($link === '') {
        echo "  Blocage : INACTIF (aucune DLL refusée)\n";
    } else {
        echo '  Blocage : ACTIF, DLL COMSPEC Link et native antérieures à ' . $link . " refusées\n";
        echo '  Message : ' . Gate::messages($link, $atak)['en'] . "\n";
    }
    $cache = Gate::cachePath();
    if ($cache !== null && is_file($cache)) {
        $age = time() - (int) @filemtime($cache);
        echo '  Cache du site : ' . trim((string) @file_get_contents($cache)) . ' (écrit il y a ' . $age . " s)\n";
    } else {
        echo "  Cache du site : absent (relu en base à la prochaine requête d'une DLL)\n";
    }
}

/**
 * @param array<string, string> $pairs
 */
function gate_write(PDO $pdo, array $pairs): int
{
    $repo = new PlatformSettingsRepository();
    if (!$repo->tableExists()) {
        fwrite(STDERR, "Échec : table platform_settings absente (lancer les migrations).\n");

        return 1;
    }
    $before = Gate::readRawSettings($pdo);
    $repo->setMany($pairs);
    $after = Gate::readRawSettings($pdo);
    foreach ($pairs as $key => $value) {
        if (($after[$key] ?? null) !== $value) {
            fwrite(STDERR, 'Échec : relecture de ' . $key . ' = « ' . ($after[$key] ?? '(absent)') . ' », attendu « ' . $value . " ».\n");

            return 1;
        }
    }
    Gate::forgetCache();

    $pick = static function (array $raw): string {
        $out = [];
        foreach (GATE_KEYS as $key) {
            $out[$key] = $raw[$key] ?? '';
        }

        return (string) json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    };
    try {
        (new AuditService())->log(
            AuditAction::PLATFORM_GAME_CLIENT_FLOOR_UPDATED,
            null,
            null,
            'platform_setting',
            null,
            $pick($before),
            $pick($after),
            'cli',
            'tools/game-client-gate.php'
        );
    } catch (\Throwable $e) {
        fwrite(STDERR, 'Avertissement : journal d\'audit non écrit (' . $e->getMessage() . "). Le réglage est bien enregistré.\n");
    }
    echo "Enregistré (effet sur le site : 30 s au plus).\n\n";
    gate_print_state($after);

    return 0;
}

try {
    switch ($command) {
        case 'status':
            gate_print_state(Gate::readRawSettings(Database::getPdo()));
            exit(0);

        case 'set':
            $link = Gate::normalizeFloor((string) ($positional[0] ?? ''));
            if ($link === null || $link === '') {
                exit(gate_usage('version Link attendue, au format 2.0.96.'));
            }
            $pairs = [Gate::SETTING_MIN_LINK_VERSION => $link];
            if (array_key_exists('atak', $options)) {
                $atak = Gate::normalizeFloor($options['atak']);
                if ($atak === null) {
                    exit(gate_usage('version ATAK attendue, au format 2.4.1 (ou vide pour la retirer).'));
                }
                $pairs[Gate::SETTING_MIN_ATAK_VERSION] = $atak;
            }
            exit(gate_write(Database::getPdo(), $pairs));

        case 'clear':
            exit(gate_write(Database::getPdo(), [Gate::SETTING_MIN_LINK_VERSION => '', Gate::SETTING_MIN_ATAK_VERSION => '']));

        case 'check':
            $ua = (string) ($positional[0] ?? '');
            $path = (string) ($options['path'] ?? '/api/atak/units');
            if (array_key_exists('min', $options)) {
                $floor = [
                    'link' => Gate::normalizeFloor($options['min']) ?? '',
                    'atak' => Gate::normalizeFloor((string) ($options['atak'] ?? '')) ?? '',
                ];
            } else {
                $floor = Gate::readFloorFromDatabase(Database::getPdo());
            }
            $decision = Gate::decide($ua, '', $floor['link']);
            echo 'User-Agent : ' . ($ua === '' ? '(vide)' : $ua) . "\n";
            echo 'Chemin     : ' . $path . "\n";
            echo 'Plancher   : ' . ($floor['link'] === '' ? '(vide)' : $floor['link'])
                . ($floor['atak'] !== '' ? ' (ATAK ' . $floor['atak'] . ')' : '') . "\n";
            echo 'Décision   : ' . ($decision['blocked'] ? 'REFUSÉ' : 'accepté') . ' (' . $decision['reason'] . ')'
                . ($decision['product'] !== null ? ', ' . $decision['product'] . ' ' . ($decision['version'] ?? '?') : '') . "\n";
            $response = Gate::check($path, ['HTTP_USER_AGENT' => $ua, 'REQUEST_METHOD' => 'GET'], $floor);
            if ($response === null) {
                echo "Réponse    : la requête continue normalement vers l'application.\n";
            } else {
                echo 'Réponse    : HTTP ' . $response->statusCode()
                    . ($response->headerValue('Retry-After') !== null ? ', Retry-After: ' . $response->headerValue('Retry-After') : '') . "\n";
                $body = json_decode($response->body(), true);
                echo json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
            }
            exit(0);

        default:
            exit(gate_usage($command === '' ? '' : 'commande inconnue « ' . $command . ' ».'));
    }
} catch (\Throwable $e) {
    fwrite(STDERR, 'Échec : ' . $e->getMessage() . "\n");
    exit(1);
}
