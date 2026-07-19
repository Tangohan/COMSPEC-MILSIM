<?php
declare(strict_types=1);

$title = $title ?? 'Engagement de confidentialité';
$ttlHours = (int) ($ttlHours ?? ($sessionHours ?? 1));
$sessionHours = (int) ($sessionHours ?? $ttlHours);
$claimMinutes = (int) ($claimMinutes ?? 25);
$claimExpiresAt = (string) ($claimExpiresAt ?? '');
$error = $error ?? null;
$observedIp = is_string($observedIp ?? null) ? $observedIp : '';
$showObservedIp = !empty($showObservedIp);
$brand = email_brand_name();
$claimLabel = '';
if ($claimExpiresAt !== '') {
    try {
        $dt = new DateTimeImmutable($claimExpiresAt);
        $claimLabel = $dt->format('d/m/Y à H:i');
    } catch (Throwable) {
        $claimLabel = '';
    }
}
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
            max-width: 34rem;
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
            font-size: clamp(3.5rem, 16vw, 6.5rem);
            font-weight: 900;
            font-style: normal;
            text-transform: uppercase;
            letter-spacing: -0.05em;
            line-height: 0.9;
            color: #fff;
        }
        .brand span { color: var(--accent); font-style: normal; }
        .lead {
            margin: 1.5rem auto 0;
            max-width: 28rem;
            font-size: 1rem;
            font-weight: 500;
            line-height: 1.65;
            color: var(--muted);
        }
        form {
            margin: 2.25rem auto 0;
            max-width: 22rem;
        }
        label {
            display: block;
            font-size: 0.6875rem;
            font-weight: 600;
            letter-spacing: 0.28em;
            text-transform: uppercase;
            color: var(--dim);
        }
        .code {
            display: block;
            width: 100%;
            margin-top: 0.75rem;
            padding: 0.9rem 0.5rem;
            background: transparent;
            border: 0;
            border-bottom: 1px solid var(--line);
            color: #fff;
            font-family: var(--font);
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: 0.28em;
            text-align: center;
            text-transform: uppercase;
        }
        .code::placeholder { color: rgba(244, 244, 240, 0.25); }
        .code:focus {
            outline: none;
            border-bottom-color: var(--accent);
        }
        .hint {
            margin: 0.65rem 0 0;
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--dim);
        }
        .error {
            margin: 0 0 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: #fca5a5;
        }
        .meta {
            margin: 0.75rem 0 0;
            font-size: 0.8125rem;
            font-weight: 500;
            color: rgba(52, 211, 153, 0.85);
        }
        button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 3rem;
            margin-top: 1.5rem;
            padding: 0.85rem 1.5rem;
            border: 0;
            background: var(--accent);
            color: #052e1c;
            font-family: var(--font);
            font-size: 0.6875rem;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        button:hover { background: #6ee7b7; }
        .rules {
            margin: 2.75rem auto 0;
            padding-top: 2rem;
            border-top: 1px solid var(--line);
            max-width: 32rem;
            text-align: left;
        }
        .rules h2 {
            margin: 0;
            font-size: 0.6875rem;
            font-weight: 600;
            letter-spacing: 0.28em;
            text-transform: uppercase;
            color: var(--dim);
            text-align: center;
        }
        .rules p {
            margin: 1rem 0 0;
            font-size: 0.9rem;
            font-weight: 500;
            line-height: 1.7;
            color: var(--muted);
        }
        .rules strong { color: #fff; font-weight: 700; }
        .foot {
            margin: 2rem 0 0;
            font-size: 0.65rem;
            font-weight: 600;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--dim);
            text-align: center;
        }
    </style>
</head>
<body>
    <main class="wrap">
        <p class="kicker">TTRD.FR · Démonstration</p>
        <h1 class="brand"><?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?><span>.</span></h1>
        <p class="lead">
            Produit de démonstration. En entrant, vous acceptez de ne pas divulguer ce que vous verrez ici.
        </p>

        <form method="post" action="<?= htmlspecialchars(url(ltrim(\App\Services\DemoNda\DemoNdaGateService::GATE_PATH, '/')), ENT_QUOTES, 'UTF-8') ?>">
            <?= \App\Core\Csrf::field() ?>
            <?php if (is_string($error) && $error !== ''): ?>
                <p class="error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <label for="access_code">Code d’accès</label>
            <input
                type="text"
                id="access_code"
                name="access_code"
                class="code"
                required
                autocomplete="one-time-code"
                spellcheck="false"
                maxlength="20"
                placeholder="XXXX-XXXX"
                autofocus
            >
            <p class="hint">Code communiqué par TTRD.FR</p>
            <?php if ($claimLabel !== ''): ?>
                <p class="meta">Saisie du code jusqu’au <?= htmlspecialchars($claimLabel, ENT_QUOTES, 'UTF-8') ?> · puis <?= (int) $sessionHours ?> h d’accès</p>
            <?php endif; ?>
            <?php if ($showObservedIp && $observedIp !== ''): ?>
                <p class="hint">Adresse observée : <?= htmlspecialchars($observedIp, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <button type="submit">J’accepte et j’entre</button>
        </form>

        <section class="rules" aria-label="Engagement de confidentialité">
            <h2>Engagement de confidentialité</h2>
            <p>
                Cette démonstration est réalisée par <strong>TTRD.FR</strong>. Ce n’est pas un service public ni une version définitive.
                Les écrans et contenus sont fournis <strong>à titre illustratif</strong> et peuvent évoluer.
            </p>
            <p>
                Vous vous engagez à <strong>ne pas divulguer</strong> d’informations issues de cette démonstration
                (captures, détails, données de test, identifiants d’essai) hors du cadre convenu avec TTRD.FR.
            </p>
            <p>
                L’accès est <strong>personnel et temporaire</strong> :
                <?= (int) $claimMinutes ?> minutes pour saisir le code après la première visite,
                puis <?= (int) $sessionHours ?> h d’accès après validation, ensuite fermeture définitive pour cette connexion.
            </p>
        </section>

        <p class="foot">Démonstration · <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?> · TTRD.FR</p>
    </main>
</body>
</html>
