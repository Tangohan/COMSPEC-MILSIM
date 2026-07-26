<?php
declare(strict_types=1);
$title = (string) ($title ?? 'Connexion téléphone — Athena ATAK');
$entryUrlLabel = (string) ($entryUrlLabel ?? 'athena.ttrd.fr/connect');
$err = \App\Core\Session::getFlash('error');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0b1220">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        :root {
            --ink: #0f172a;
            --muted: #64748b;
            --accent: #059669;
            --accent-soft: #34d399;
            --line: rgba(148, 163, 184, .35);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.25rem;
            padding-bottom: calc(1.25rem + env(safe-area-inset-bottom));
            background:
                radial-gradient(900px 420px at 12% -8%, #1e293b 0%, transparent 55%),
                radial-gradient(700px 380px at 100% 0%, rgba(5, 150, 105, .18) 0%, transparent 50%),
                linear-gradient(165deg, #0b1220 0%, #020617 100%);
            font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
            color: #e2e8f0;
        }
        .shell {
            width: 100%;
            max-width: 22.5rem;
        }
        .brand {
            text-align: center;
            margin-bottom: 1rem;
        }
        .brand .eyebrow {
            margin: 0 0 .35rem;
            font-size: .62rem;
            font-weight: 800;
            letter-spacing: .22em;
            text-transform: uppercase;
            color: var(--accent-soft);
        }
        .brand h1 {
            margin: 0;
            font-size: 1.35rem;
            font-weight: 900;
            letter-spacing: .04em;
            color: #f8fafc;
        }
        .card {
            background: #fff;
            border-radius: 1.25rem;
            padding: 1.65rem 1.35rem 1.5rem;
            text-align: center;
            box-shadow: 0 22px 50px -18px rgba(0, 0, 0, .55);
            color: var(--ink);
        }
        .card h2 {
            margin: 0 0 .45rem;
            font-size: 1.05rem;
            font-weight: 900;
            letter-spacing: .03em;
        }
        .card p {
            margin: 0 0 1.15rem;
            font-size: .875rem;
            line-height: 1.5;
            color: var(--muted);
        }
        .addr {
            display: inline-block;
            margin: 0 0 1.15rem;
            padding: .4rem .75rem;
            border-radius: .65rem;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            font-size: .78rem;
            font-weight: 700;
            letter-spacing: .02em;
            color: #0f172a;
            word-break: break-all;
        }
        label {
            display: block;
            margin: 0 0 .45rem;
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: #475569;
            text-align: left;
        }
        input {
            width: 100%;
            font-size: 1.55rem;
            text-align: center;
            letter-spacing: .28em;
            text-transform: uppercase;
            padding: .85rem .65rem;
            border-radius: .85rem;
            border: 2px solid #cbd5e1;
            margin-bottom: 1rem;
            font-weight: 800;
            color: var(--ink);
            background: #f8fafc;
        }
        input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, .2);
            background: #fff;
        }
        button {
            width: 100%;
            padding: .9rem;
            border: 0;
            border-radius: .85rem;
            background: var(--accent);
            color: #fff;
            font-weight: 800;
            font-size: .82rem;
            letter-spacing: .06em;
            text-transform: uppercase;
            cursor: pointer;
        }
        button:active { filter: brightness(.96); }
        .err {
            background: #fee2e2;
            color: #991b1b;
            border-radius: .7rem;
            padding: .65rem .8rem;
            font-size: .8rem;
            line-height: 1.4;
            margin-bottom: 1rem;
            text-align: left;
        }
        .hint {
            margin: 1rem 0 0 !important;
            font-size: .75rem !important;
            color: #94a3b8 !important;
        }
    </style>
</head>
<body>
    <div class="shell">
        <div class="brand">
            <p class="eyebrow">Athena · ATAK</p>
            <h1>Connexion téléphone</h1>
        </div>
        <div class="card">
            <h2>Entrez le code</h2>
            <p>Demandez le code affiché sur Athena (ordinateur, bouton <strong>Téléphone</strong>) ou sur l’écran de liaison en jeu, puis saisissez-le ici.</p>
            <div class="addr" aria-hidden="true"><?= htmlspecialchars($entryUrlLabel, ENT_QUOTES, 'UTF-8') ?></div>
            <?php if ($err): ?><div class="err" role="alert"><?= htmlspecialchars((string) $err, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
            <form method="post" action="<?= htmlspecialchars(url('connect/code'), ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
                <?= \App\Core\Csrf::field() ?>
                <label for="connect-code">Code d’appariement</label>
                <input
                    id="connect-code"
                    type="text"
                    name="code"
                    maxlength="8"
                    inputmode="text"
                    autocapitalize="characters"
                    autocomplete="one-time-code"
                    spellcheck="false"
                    placeholder="······"
                    required
                    autofocus
                >
                <button type="submit">Continuer</button>
            </form>
            <p class="hint">Le code expire rapidement. S’il ne fonctionne plus, générez-en un nouveau depuis Athena.</p>
        </div>
    </div>
</body>
</html>
