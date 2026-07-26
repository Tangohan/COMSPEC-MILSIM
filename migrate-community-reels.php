<?php

declare(strict_types=1);

/**
 * Migration Reels / feed communautaire — lançable dans le navigateur.
 *
 * URL (selon vhost / document root) :
 *   - http://votre-hote/migrate-community-reels.php
 *   - ou via le même chemin que run-migrations.php
 *
 * Authentification : même mot de passe que le portail migrations
 * (MIGRATIONS_WEB_PASSWORD dans .env, ou empreinte config/migrations_web.php).
 *
 * Idempotent : safe à relancer. N’exécute que la couche reels (+ community_media de base si absente).
 */

$root = dirname(__FILE__);

require_once $root . '/bootstrap/migrations_web_ui.php';

migrations_web_boot_session();
migrations_web_load_env($root);

$script = htmlspecialchars(basename($_SERVER['SCRIPT_NAME'] ?? 'migrate-community-reels.php'), ENT_QUOTES, 'UTF-8');
$error = '';
$ran = false;
$log = '';

if (isset($_GET['logout'])) {
    migrations_web_set_authenticated(false);
    header('Location: ' . $script);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if (migrations_web_lock_remaining() > 0) {
        $error = 'Trop de tentatives. Réessayez plus tard.';
    } elseif (migrations_web_password_ok((string) ($_POST['password'] ?? ''))) {
        migrations_web_set_authenticated(true);
        header('Location: ' . $script);
        exit;
    } else {
        migrations_web_register_failed_attempt();
        $error = 'Mot de passe incorrect.';
    }
}

$authenticated = migrations_web_is_authenticated();

if ($authenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run'])) {
    $ran = true;
    ob_start();
    try {
        $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1';
        $name = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: '';
        $user = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: '';
        $pass = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '';
        $port = (int) ($_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: 3306);
        $charset = $_ENV['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4';

        if ($name === '' || $user === '') {
            throw new RuntimeException('DB_NAME / DB_USER manquants dans .env');
        }

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        echo "[OK] Connexion MySQL ({$name}@{$host})\n\n";

        echo "=== Prérequis community_media ===\n";
        $baseMigrate = require $root . '/bootstrap/community_media_migration.php';
        $baseMigrate($pdo);
        echo "\n";

        echo "=== Migration community_media reels ===\n";
        $reelsMigrate = require $root . '/bootstrap/community_media_reels_migration.php';
        $reelsMigrate($pdo);
        echo "\n[OK] Terminé.\n";
    } catch (Throwable $e) {
        echo '[ERREUR] ' . $e->getMessage() . "\n";
    }
    $log = (string) ob_get_clean();
}

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Migration Reels — ATHENA</title>
    <style>
        :root {
            --bg: #070a0c;
            --panel: rgba(15, 23, 32, 0.88);
            --line: rgba(148, 163, 184, 0.18);
            --text: #e2e8f0;
            --muted: #94a3b8;
            --accent: #34d399;
            --accent-dim: rgba(52, 211, 153, 0.14);
            --danger: #f87171;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", system-ui, sans-serif;
            color: var(--text);
            background:
                radial-gradient(1200px 600px at 10% -10%, rgba(52, 211, 153, 0.12), transparent 55%),
                radial-gradient(900px 500px at 100% 0%, rgba(56, 189, 248, 0.08), transparent 50%),
                var(--bg);
        }
        .wrap {
            max-width: 720px;
            margin: 0 auto;
            padding: 2.5rem 1.25rem 3rem;
        }
        h1 {
            margin: 0 0 0.35rem;
            font-size: 1.55rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        .sub {
            margin: 0 0 1.75rem;
            color: var(--muted);
            line-height: 1.5;
            font-size: 0.95rem;
        }
        .card {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 1.35rem 1.4rem;
            backdrop-filter: blur(12px);
        }
        .list {
            margin: 0 0 1.25rem;
            padding-left: 1.1rem;
            color: var(--muted);
            font-size: 0.9rem;
            line-height: 1.55;
        }
        .list strong { color: var(--text); font-weight: 600; }
        label {
            display: block;
            font-size: 0.8rem;
            color: var(--muted);
            margin-bottom: 0.4rem;
        }
        input[type="password"] {
            width: 100%;
            padding: 0.7rem 0.85rem;
            border-radius: 10px;
            border: 1px solid var(--line);
            background: rgba(2, 6, 12, 0.55);
            color: var(--text);
            font-size: 1rem;
            margin-bottom: 1rem;
        }
        input:focus {
            outline: 2px solid rgba(52, 211, 153, 0.45);
            outline-offset: 1px;
        }
        .row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
            align-items: center;
        }
        .btn {
            appearance: none;
            border: 0;
            border-radius: 999px;
            padding: 0.7rem 1.2rem;
            font-weight: 650;
            font-size: 0.95rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, #34d399, #10b981);
            color: #042f1e;
        }
        .btn-primary:hover { filter: brightness(1.05); }
        .btn-ghost {
            background: transparent;
            color: var(--muted);
            border: 1px solid var(--line);
        }
        .btn-ghost:hover { color: var(--text); }
        .err {
            margin: 0 0 1rem;
            padding: 0.75rem 0.9rem;
            border-radius: 10px;
            background: rgba(248, 113, 113, 0.12);
            border: 1px solid rgba(248, 113, 113, 0.35);
            color: var(--danger);
            font-size: 0.9rem;
        }
        .ok-pill {
            display: inline-block;
            margin-bottom: 1rem;
            padding: 0.35rem 0.7rem;
            border-radius: 999px;
            background: var(--accent-dim);
            color: var(--accent);
            font-size: 0.8rem;
            font-weight: 650;
        }
        pre {
            margin: 1.25rem 0 0;
            padding: 1rem 1.1rem;
            border-radius: 12px;
            background: #020617;
            border: 1px solid var(--line);
            color: #cbd5e1;
            font-size: 0.78rem;
            line-height: 1.45;
            overflow: auto;
            max-height: 55vh;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .hint {
            margin-top: 1.25rem;
            font-size: 0.8rem;
            color: var(--muted);
        }
        code {
            font-family: ui-monospace, Consolas, monospace;
            font-size: 0.85em;
            color: #a7f3d0;
        }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Migration Reels</h1>
    <p class="sub">
        Prépare la base pour le feed vidéo vertical (extension <code>community_media_*</code>,
        commentaires, abonnements, signalements). Opération sans risque à relancer.
    </p>

    <?php if (!$authenticated): ?>
        <div class="card">
            <?php if ($error !== ''): ?>
                <p class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <form method="post" autocomplete="current-password">
                <label for="password">Mot de passe migrations</label>
                <input id="password" type="password" name="password" required autofocus>
                <div class="row">
                    <button class="btn btn-primary" type="submit" name="login" value="1">Se connecter</button>
                    <a class="btn btn-ghost" href="run-migrations.php">Portail migrations complet</a>
                </div>
            </form>
            <p class="hint">Même accès que <code>run-migrations.php</code> (variable d’environnement ou config dédiée).</p>
        </div>
    <?php else: ?>
        <div class="card">
            <span class="ok-pill">Connecté</span>
            <ul class="list">
                <li>Assure les tables de base <strong>médias communauté</strong> si besoin</li>
                <li>Ajoute les colonnes reels (origine, modération, compteurs, miniatures…)</li>
                <li>Crée <strong>commentaires</strong>, <strong>abonnements</strong> et <strong>signalements</strong></li>
                <li>Recalcule les compteurs de mentions « j’aime » existants</li>
            </ul>
            <form method="post" onsubmit="this.querySelector('button[name=run]').disabled=true; this.querySelector('button[name=run]').textContent='Exécution…';">
                <div class="row">
                    <button class="btn btn-primary" type="submit" name="run" value="1">Lancer la migration</button>
                    <a class="btn btn-ghost" href="<?= $script ?>?logout=1">Se déconnecter</a>
                    <a class="btn btn-ghost" href="run-migrations.php">Pipeline complet</a>
                </div>
            </form>
            <?php if ($ran): ?>
                <pre><?= htmlspecialchars($log, ENT_QUOTES, 'UTF-8') ?></pre>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
