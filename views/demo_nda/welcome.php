<?php
declare(strict_types=1);

use App\Support\DemoPortalAccounts;

$title = $title ?? 'Bienvenue dans la démonstration';
$ttlHours = (int) ($ttlHours ?? ($sessionHours ?? 1));
$sessionHours = (int) ($sessionHours ?? $ttlHours);
$continueUrl = is_string($continueUrl ?? null) && $continueUrl !== '' ? $continueUrl : url('');
$loginUrl = is_string($loginUrl ?? null) && $loginUrl !== '' ? $loginUrl : url('login');
$communityUrl = is_string($communityUrl ?? null) && $communityUrl !== ''
    ? $communityUrl
    : url('c/' . DemoPortalAccounts::TENANT_SLUG);
$accounts = is_array($accounts ?? null) ? $accounts : DemoPortalAccounts::announcedAccounts();
$sharedPassword = is_string($sharedPassword ?? null) && $sharedPassword !== ''
    ? $sharedPassword
    : DemoPortalAccounts::SHARED_PASSWORD;
$tenantName = is_string($tenantName ?? null) && $tenantName !== ''
    ? $tenantName
    : DemoPortalAccounts::TENANT_NAME;
$brand = email_brand_name();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#050505">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,600;0,700;0,900;1,900&display=swap">
    <style>
        :root {
            --ink: #f4f4f0;
            --muted: rgba(244, 244, 240, 0.62);
            --dim: rgba(244, 244, 240, 0.38);
            --accent: #34d399;
            --void: #050505;
            --line: rgba(244, 244, 240, 0.14);
            --panel: rgba(244, 244, 240, 0.04);
            --font: Inter, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            min-height: 100%;
            background: var(--void);
            color: var(--ink);
            font-family: var(--font);
            -webkit-font-smoothing: antialiased;
        }
        body {
            min-height: 100svh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2.5rem 1.25rem 3rem;
        }
        .wrap {
            width: 100%;
            max-width: 36rem;
            text-align: center;
        }
        .kicker {
            margin: 0;
            font-size: 0.6875rem;
            font-weight: 600;
            letter-spacing: 0.28em;
            text-transform: uppercase;
            color: var(--accent);
        }
        .brand {
            margin: 1.25rem 0 0;
            font-size: clamp(2.75rem, 12vw, 4.5rem);
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: -0.05em;
            line-height: 0.95;
            color: #fff;
        }
        .brand span { color: var(--accent); }
        .lead {
            margin: 1.35rem auto 0;
            max-width: 30rem;
            font-size: 1rem;
            font-weight: 500;
            line-height: 1.65;
            color: var(--muted);
        }
        .password-box {
            margin: 2rem auto 0;
            max-width: 22rem;
            padding: 1.25rem 1.35rem;
            background: var(--panel);
            border: 1px solid var(--line);
            text-align: center;
        }
        .password-box .label {
            margin: 0;
            font-size: 0.6875rem;
            font-weight: 600;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: var(--dim);
        }
        .password-box .value {
            margin: 0.65rem 0 0;
            font-size: 1.75rem;
            font-weight: 900;
            letter-spacing: 0.12em;
            color: var(--accent);
            font-variant-numeric: tabular-nums;
        }
        .password-box .note {
            margin: 0.55rem 0 0;
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--dim);
        }
        .accounts {
            margin: 2rem 0 0;
            text-align: left;
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
        }
        .accounts h2 {
            margin: 0 0 0.35rem;
            font-size: 0.6875rem;
            font-weight: 600;
            letter-spacing: 0.28em;
            text-transform: uppercase;
            color: var(--dim);
            text-align: center;
        }
        .account {
            padding: 1rem 1.15rem;
            background: var(--panel);
            border: 1px solid var(--line);
        }
        .account .role {
            margin: 0;
            font-size: 0.8125rem;
            font-weight: 700;
            color: #fff;
        }
        .account .email {
            margin: 0.35rem 0 0;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--accent);
            word-break: break-all;
        }
        .account .hint {
            margin: 0.45rem 0 0;
            font-size: 0.8125rem;
            font-weight: 500;
            line-height: 1.5;
            color: var(--muted);
        }
        .actions {
            margin: 2.25rem auto 0;
            max-width: 22rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 3rem;
            padding: 0.85rem 1.5rem;
            border: 0;
            text-decoration: none;
            font-family: var(--font);
            font-size: 0.6875rem;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            cursor: pointer;
            transition: background-color 0.2s ease, color 0.2s ease;
        }
        .btn-primary {
            background: var(--accent);
            color: #052e1c;
        }
        .btn-primary:hover { background: #6ee7b7; }
        .btn-ghost {
            background: transparent;
            color: var(--ink);
            border: 1px solid var(--line);
        }
        .btn-ghost:hover {
            border-color: rgba(244, 244, 240, 0.35);
            color: #fff;
        }
        .disclaimer {
            margin: 2rem auto 0;
            max-width: 30rem;
            font-size: 0.8125rem;
            font-weight: 500;
            line-height: 1.6;
            color: var(--dim);
        }
        .foot {
            margin: 2rem 0 0;
            font-size: 0.65rem;
            font-weight: 600;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--dim);
        }
    </style>
</head>
<body>
    <main class="wrap">
        <p class="kicker">Accès validé · <?= (int) $sessionHours ?> h</p>
        <h1 class="brand"><?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?><span>.</span></h1>
        <p class="lead">
            Voici les comptes d’essai pour explorer <strong style="color:#fff;font-weight:700"><?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?></strong>.
            Aucun de ces comptes n’administre la plateforme : ils servent uniquement à la démonstration de l’organisation.
        </p>

        <div class="password-box" role="region" aria-label="Mot de passe commun">
            <p class="label">Mot de passe commun</p>
            <p class="value"><?= htmlspecialchars($sharedPassword, ENT_QUOTES, 'UTF-8') ?></p>
            <p class="note">Le même pour tous les comptes ci-dessous</p>
        </div>

        <section class="accounts" aria-label="Comptes d’essai">
            <h2>Comptes d’essai</h2>
            <?php foreach ($accounts as $account): ?>
                <?php
                $email = (string) ($account['email'] ?? '');
                $roleLabel = (string) ($account['role_label'] ?? '');
                $hint = (string) ($account['hint'] ?? '');
                if ($email === '') {
                    continue;
                }
                ?>
                <article class="account">
                    <p class="role"><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="email"><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if ($hint !== ''): ?>
                        <p class="hint"><?= htmlspecialchars($hint, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>

        <div class="actions">
            <a class="btn btn-primary" href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>">Se connecter</a>
            <a class="btn btn-ghost" href="<?= htmlspecialchars($communityUrl, ENT_QUOTES, 'UTF-8') ?>">Voir l’entité de démo</a>
            <a class="btn btn-ghost" href="<?= htmlspecialchars($continueUrl, ENT_QUOTES, 'UTF-8') ?>">Continuer sans se connecter</a>
        </div>

        <p class="disclaimer">
            Données fictives, à usage de démonstration uniquement. Ne pas réutiliser ces identifiants hors de ce portail d’essai.
        </p>
        <p class="foot">Démonstration · <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?> · TTRD.FR</p>
    </main>
</body>
</html>
