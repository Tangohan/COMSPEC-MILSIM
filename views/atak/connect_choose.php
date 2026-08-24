<?php
declare(strict_types=1);
$title = (string) ($title ?? 'Choisir une destination — Athena ATAK');
$atakTenantName = trim((string) ($atakTenantName ?? 'Communauté'));
$slidesUrl = (string) ($slidesUrl ?? url('connect'));
$carteUrl = (string) ($carteUrl ?? url('atak'));
$chatUrl = (string) ($chatUrl ?? $carteUrl);
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
        .card > p {
            margin: 0 0 1.25rem;
            font-size: .875rem;
            line-height: 1.5;
            color: var(--muted);
        }
        .community {
            display: inline-block;
            margin: 0 0 1.25rem;
            padding: .4rem .75rem;
            border-radius: .65rem;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            font-size: .78rem;
            font-weight: 700;
            color: #0f172a;
            max-width: 100%;
            overflow-wrap: anywhere;
        }
        .choices {
            display: flex;
            flex-direction: column;
            gap: .85rem;
        }
        .choice {
            display: block;
            width: 100%;
            text-align: left;
            text-decoration: none;
            color: inherit;
            padding: 1.05rem 1.1rem;
            border-radius: 1rem;
            border: 2px solid #e2e8f0;
            background: #f8fafc;
            min-height: 5.25rem;
            transition: border-color .15s ease, background .15s ease, transform .12s ease;
            -webkit-tap-highlight-color: transparent;
        }
        .choice:active {
            transform: scale(.985);
        }
        .choice:focus-visible {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, .22);
        }
        .choice--map {
            border-color: rgba(5, 150, 105, .45);
            background: linear-gradient(165deg, #ecfdf5 0%, #f8fafc 70%);
        }
        .choice .label {
            display: block;
            margin: 0 0 .35rem;
            font-size: 1rem;
            font-weight: 900;
            letter-spacing: .02em;
            color: #0f172a;
        }
        .choice .desc {
            display: block;
            margin: 0;
            font-size: .8rem;
            line-height: 1.45;
            color: var(--muted);
        }
        .hint {
            margin: 1.1rem 0 0;
            font-size: .75rem;
            line-height: 1.45;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="shell">
        <div class="brand">
            <p class="eyebrow">Athena · ATAK</p>
            <h1>Téléphone relié</h1>
        </div>
        <div class="card">
            <h2>Que voulez-vous ouvrir&nbsp;?</h2>
            <p>Votre téléphone est relié à la communauté. Choisissez l’écran à afficher.</p>
            <div class="community"><?= htmlspecialchars($atakTenantName, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="choices">
                <a class="choice choice--map" href="<?= htmlspecialchars($carteUrl, ENT_QUOTES, 'UTF-8') ?>">
                    <span class="label">Terminal ATAK</span>
                    <span class="desc">Appareil Android de terrain avec la carte Arma, les effectifs en liaison, les ordres et les alertes — comme sur ATAK IceMan.</span>
                </a>
                <a class="choice" href="<?= htmlspecialchars($chatUrl, ENT_QUOTES, 'UTF-8') ?>">
                    <span class="label">Tchat délégué</span>
                    <span class="desc">Confiez les communications à un second opérateur sur mobile, sans encombrer la carte du poste principal.</span>
                </a>
                <a class="choice" href="<?= htmlspecialchars($slidesUrl, ENT_QUOTES, 'UTF-8') ?>">
                    <span class="label">Diapositives</span>
                    <span class="desc">Briefing et diaporama de mission, adaptés à l’écran du téléphone.</span>
                </a>
            </div>
            <p class="hint">Vous pourrez revenir ici tant que le code reste valide, en rescanant le QR ou en saisissant à nouveau le code.</p>
        </div>
    </div>
</body>
</html>
