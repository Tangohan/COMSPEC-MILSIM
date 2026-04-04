<?php

declare(strict_types=1);

/**
 * Assistant web : saisie des paramètres MySQL, écriture du .env, exécution des migrations.
 *
 * Sécurité : désactivé si storage/install.lock existe (supprimez ce fichier pour réinstaller la BDD).
 * En production, supprimez ce fichier après la première installation.
 */

$root = dirname(__DIR__);
$lockFile = $root . '/storage/install.lock';

header('Content-Type: text/html; charset=utf-8');

if (!is_dir($root . '/storage')) {
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>Erreur</title></head><body><p>Le dossier <code>storage/</code> est absent. Exécutez d’abord <code>php install.php</code> à la racine du projet.</p></body></html>';
    exit(1);
}

if (is_file($lockFile)) {
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>Installation BDD</title><style>body{font-family:system-ui,sans-serif;max-width:40rem;margin:2rem auto;padding:0 1rem;}code{background:#f0f0f0;padding:2px 6px;}</style></head><body>';
    echo '<h1>Assistant désactivé</h1>';
    echo '<p>Un fichier <code>storage/install.lock</code> indique que l’installation a déjà été effectuée.</p>';
    echo '<p>Pour réutiliser l’assistant, supprimez ce fichier sur le serveur (à vos risques).</p>';
    echo '</body></html>';
    exit(0);
}

session_start();

function mergeEnvDb(string $envPath, array $db): void
{
    $lines = is_file($envPath) ? file($envPath, FILE_IGNORE_NEW_LINES) : [];
    if ($lines === false) {
        $lines = [];
    }
    $keys = [
        'DB_HOST' => $db['host'],
        'DB_NAME' => $db['name'],
        'DB_USER' => $db['user'],
        'DB_PASSWORD' => $db['password'],
        'DB_CHARSET' => $db['charset'],
    ];
    $out = [];
    $seen = [];
    foreach ($lines as $line) {
        $trim = ltrim($line);
        if ($trim === '' || str_starts_with($trim, '#')) {
            $out[] = $line;
            continue;
        }
        if (preg_match('/^([A-Z0-9_]+)=(.*)$/', $line, $m)) {
            $k = $m[1];
            if (isset($keys[$k])) {
                $seen[$k] = true;
                $v = $keys[$k];
                $out[] = $k . '=' . (preg_match('/[\s#"]/', $v) ? '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $v) . '"' : $v);
                continue;
            }
        }
        $out[] = $line;
    }
    foreach ($keys as $k => $v) {
        if (!isset($seen[$k])) {
            $out[] = $k . '=' . (preg_match('/[\s#"]/', $v) ? '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $v) . '"' : $v);
        }
    }
    if (!@file_put_contents($envPath, implode("\n", $out) . "\n")) {
        throw new RuntimeException('Impossible d’écrire le fichier .env');
    }
}

function readEnvDb(string $envPath): array
{
    $defaults = [
        'host' => '127.0.0.1',
        'name' => 'athena',
        'user' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ];
    if (!is_file($envPath)) {
        return $defaults;
    }
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return $defaults;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v, " \t\"'");
        match ($k) {
            'DB_HOST' => $defaults['host'] = $v,
            'DB_NAME' => $defaults['name'] = $v,
            'DB_USER' => $defaults['user'] = $v,
            'DB_PASSWORD' => $defaults['password'] = $v,
            'DB_CHARSET' => $defaults['charset'] = $v,
            default => null,
        };
    }
    return $defaults;
}

$envPath = $root . '/.env';
$envExample = $root . '/.env.example';
$error = '';
$output = '';
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        $error = 'Session expirée ou jeton CSRF invalide. Rechargez la page.';
    } else {
        $host = trim((string) ($_POST['db_host'] ?? ''));
        $name = trim((string) ($_POST['db_name'] ?? ''));
        $user = trim((string) ($_POST['db_user'] ?? ''));
        $password = (string) ($_POST['db_password'] ?? '');
        $charset = trim((string) ($_POST['db_charset'] ?? 'utf8mb4')) ?: 'utf8mb4';

        if ($host === '' || $name === '' || $user === '') {
            $error = 'Hôte, nom de base et utilisateur sont obligatoires.';
        } else {
            if (!is_file($envPath) && is_file($envExample)) {
                if (!@copy($envExample, $envPath)) {
                    $error = 'Impossible de créer .env à partir de .env.example.';
                }
            }
            if ($error === '') {
                try {
                    mergeEnvDb($envPath, [
                        'host' => $host,
                        'name' => $name,
                        'user' => $user,
                        'password' => $password,
                        'charset' => $charset,
                    ]);
                } catch (Throwable $e) {
                    $error = $e->getMessage();
                }
            }
            if ($error === '') {
                $setup = $root . '/setup-database.php';
                if (!is_file($setup)) {
                    $error = 'setup-database.php introuvable.';
                } else {
                    $desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
                    $cmd = 'php ' . escapeshellarg($setup);
                    $proc = proc_open($cmd, $desc, $pipes, $root);
                    if (!is_resource($proc)) {
                        $error = 'Impossible de lancer le script de migration.';
                    } else {
                        $output = stream_get_contents($pipes[1]);
                        $errOut = stream_get_contents($pipes[2]);
                        fclose($pipes[1]);
                        fclose($pipes[2]);
                        $code = proc_close($proc);
                        if ($errOut !== '') {
                            $output .= "\n--- stderr ---\n" . $errOut;
                        }
                        if ($code === 0) {
                            @file_put_contents($lockFile, date('c') . " — install-database-wizard\n");
                            $done = true;
                        } else {
                            $error = 'Les migrations se sont terminées avec le code ' . $code . '. Voir la sortie ci-dessous.';
                        }
                    }
                }
            }
        }
    }
}

$_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];
$pref = readEnvDb($envPath);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Installation base de données — COMSPEC / Athena</title>
    <style>
        :root { color-scheme: light dark; }
        body { font-family: system-ui, sans-serif; max-width: 32rem; margin: 2rem auto; padding: 0 1rem; line-height: 1.5; }
        h1 { font-size: 1.25rem; }
        label { display: block; margin-top: 1rem; font-weight: 600; }
        input[type=text], input[type=password] { width: 100%; max-width: 100%; box-sizing: border-box; padding: 0.5rem; margin-top: 0.25rem; }
        button { margin-top: 1.5rem; padding: 0.6rem 1.2rem; cursor: pointer; }
        .err { background: #fee; padding: 1rem; border-radius: 6px; margin: 1rem 0; }
        .ok { background: #efe; padding: 1rem; border-radius: 6px; margin: 1rem 0; }
        pre { background: #111; color: #eee; padding: 1rem; overflow: auto; font-size: 0.8rem; border-radius: 6px; white-space: pre-wrap; }
        .hint { color: #666; font-size: 0.9rem; margin-top: 0.25rem; }
    </style>
</head>
<body>
<h1>Connexion MySQL et migrations</h1>
<p>Indiquez les identifiants de la base (créez la base vide au préalable si besoin). Le fichier <code>.env</code> sera mis à jour, puis <code>setup-database.php</code> sera exécuté (schéma complet, migrations plateforme, <strong>RBAC 3 couches</strong> site / communauté / intra, seed éventuel).</p>

<?php if ($error !== ''): ?>
    <div class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<?php if ($done): ?>
    <div class="ok">
        <strong>Migrations terminées.</strong> Le fichier <code>storage/install.lock</code> a été créé pour désactiver cet assistant.
        Supprimez <code>public/install-database-wizard.php</code> en production si vous le souhaitez.
    </div>
<?php endif; ?>

<?php if ($output !== ''): ?>
    <h2>Sortie</h2>
    <pre><?= htmlspecialchars($output, ENT_QUOTES, 'UTF-8') ?></pre>
<?php endif; ?>

<?php if (!$done): ?>
<form method="post" action="">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

    <label for="db_host">Hôte MySQL</label>
    <input type="text" id="db_host" name="db_host" value="<?= htmlspecialchars($pref['host'], ENT_QUOTES, 'UTF-8') ?>" required autocomplete="off">
    <p class="hint">Ex. <code>127.0.0.1</code> ou le hostname d’un hébergeur distant.</p>

    <label for="db_name">Nom de la base</label>
    <input type="text" id="db_name" name="db_name" value="<?= htmlspecialchars($pref['name'], ENT_QUOTES, 'UTF-8') ?>" required autocomplete="off">

    <label for="db_user">Utilisateur</label>
    <input type="text" id="db_user" name="db_user" value="<?= htmlspecialchars($pref['user'], ENT_QUOTES, 'UTF-8') ?>" required autocomplete="username">

    <label for="db_password">Mot de passe</label>
    <input type="password" id="db_password" name="db_password" value="<?= htmlspecialchars($pref['password'], ENT_QUOTES, 'UTF-8') ?>" autocomplete="current-password">

    <label for="db_charset">Charset</label>
    <input type="text" id="db_charset" name="db_charset" value="<?= htmlspecialchars($pref['charset'], ENT_QUOTES, 'UTF-8') ?>">

    <button type="submit">Enregistrer et lancer les migrations</button>
</form>
<?php endif; ?>

<p class="hint">Alternative CLI : <code>php install.php</code> puis <code>php setup-database.php</code> avec un <code>.env</code> déjà renseigné.</p>
</body>
</html>
