<?php
declare(strict_types=1);

$title = $title ?? 'Engagement de confidentialité';
$ttlHours = (int) ($ttlHours ?? 3);
$claimExpiresAt = (string) ($claimExpiresAt ?? '');
$error = $error ?? null;
$baseUrl = url('');
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
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="robots" content="noindex,nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
<?php
    $tailwindBaseUrl = $baseUrl;
    require base_path('views/partials/tailwind_cdn_or_build.php');
?>
    <style>
        :root {
            --nda-ink: #e8eef4;
            --nda-muted: #9aafc0;
            --nda-accent: #c4a35a;
            --nda-panel: rgba(8, 16, 24, 0.72);
        }
        body.nda-body {
            margin: 0;
            min-height: 100vh;
            font-family: Manrope, system-ui, sans-serif;
            color: var(--nda-ink);
            background:
                radial-gradient(1200px 600px at 12% -10%, rgba(196, 163, 90, 0.18), transparent 55%),
                radial-gradient(900px 500px at 90% 10%, rgba(56, 120, 160, 0.22), transparent 50%),
                linear-gradient(165deg, #050a10 0%, #0c1822 42%, #071018 100%);
        }
        .nda-brand {
            font-family: "Space Grotesk", Manrope, sans-serif;
            letter-spacing: 0.08em;
        }
        .nda-panel {
            background: var(--nda-panel);
            border: 1px solid rgba(232, 238, 244, 0.12);
            backdrop-filter: blur(12px);
        }
        .nda-scroll {
            max-height: min(42vh, 28rem);
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: rgba(196, 163, 90, 0.45) transparent;
        }
        .nda-input {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(232, 238, 244, 0.18);
            color: var(--nda-ink);
            letter-spacing: 0.28em;
            text-transform: uppercase;
        }
        .nda-input:focus {
            outline: none;
            border-color: rgba(196, 163, 90, 0.7);
            box-shadow: 0 0 0 3px rgba(196, 163, 90, 0.18);
        }
        .nda-cta {
            background: linear-gradient(135deg, #d4b56a, #a8873d);
            color: #12100a;
        }
        .nda-cta:hover { filter: brightness(1.05); }
        @keyframes nda-rise {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .nda-anim { animation: nda-rise 0.55s ease-out both; }
        .nda-anim-delay { animation-delay: 0.12s; }
    </style>
</head>
<body class="nda-body">
    <div class="mx-auto flex min-h-screen max-w-3xl flex-col justify-center px-4 py-12 sm:px-6">
        <header class="nda-anim mb-8 text-center">
            <p class="nda-brand text-xs font-semibold uppercase text-[color:var(--nda-accent)]">TTRD.FR</p>
            <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-white sm:text-4xl"><?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="mt-3 text-base text-[color:var(--nda-muted)]">Produit de démonstration — accès conditionné</p>
        </header>

        <section class="nda-anim nda-anim-delay nda-panel rounded-2xl p-6 sm:p-8">
            <h2 class="text-lg font-bold text-white">Engagement de confidentialité</h2>
            <div class="nda-scroll mt-5 space-y-4 text-sm leading-relaxed text-[color:var(--nda-muted)]">
                <p>
                    Vous allez accéder à une <strong class="font-semibold text-white">démonstration</strong> réalisée par
                    <strong class="font-semibold text-white">TTRD.FR</strong>. Il ne s’agit pas d’un service ouvert au public,
                    ni d’une version définitive destinée à une exploitation libre.
                </p>
                <p>
                    Les écrans, contenus, organisation, libellés, processus et éléments présentés ici sont fournis
                    <strong class="font-semibold text-white">à titre illustratif</strong>. Ils peuvent évoluer, être incomplets
                    ou différer d’une éventuelle mise en production ultérieure.
                </p>
                <p>
                    En poursuivant, vous vous engagez à <strong class="font-semibold text-white">ne pas divulguer</strong>
                    d’informations issues de cette démonstration : captures d’écran diffusées sans accord, détails techniques
                    ou fonctionnels, données de test, identifiants communiqués pour l’essai, ou tout élément permettant
                    de reconstituer l’architecture ou le positionnement du produit hors du cadre prévu avec TTRD.FR.
                </p>
                <p>
                    L’accès est <strong class="font-semibold text-white">personnel et temporaire</strong>. Un code vous est
                    remis par TTRD.FR. Dès votre première visite, une fenêtre de
                    <strong class="font-semibold text-white"><?= (int) $ttlHours ?> heure<?= $ttlHours > 1 ? 's' : '' ?></strong>
                    s’ouvre pour saisir ce code. Après validation, votre accès reste actif pendant
                    <strong class="font-semibold text-white"><?= (int) $ttlHours ?> heure<?= $ttlHours > 1 ? 's' : '' ?></strong>,
                    puis se ferme définitivement pour cette connexion.
                </p>
                <p>
                    Toute utilisation hors démonstration, toute rediffusion non autorisée ou toute tentative de contourner
                    ces règles est interdite. En cas de doute, contactez TTRD.FR avant de partager quoi que ce soit.
                </p>
                <?php if ($claimLabel !== ''): ?>
                    <p class="rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-[color:var(--nda-ink)]">
                        Fenêtre de saisie ouverte jusqu’au <strong class="font-semibold"><?= htmlspecialchars($claimLabel, ENT_QUOTES, 'UTF-8') ?></strong>.
                    </p>
                <?php endif; ?>
            </div>

            <?php if (is_string($error) && $error !== ''): ?>
                <p class="mt-6 rounded-lg border border-red-400/30 bg-red-500/10 px-3 py-2 text-sm text-red-200" role="alert">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </p>
            <?php endif; ?>

            <form method="post" action="<?= htmlspecialchars(url(ltrim(\App\Services\DemoNda\DemoNdaGateService::GATE_PATH, '/')), ENT_QUOTES, 'UTF-8') ?>" class="mt-8 space-y-4">
                <?= \App\Core\Csrf::field() ?>
                <div>
                    <label for="access_code" class="block text-xs font-semibold uppercase tracking-wider text-[color:var(--nda-muted)]">Code d’accès</label>
                    <input
                        type="text"
                        id="access_code"
                        name="access_code"
                        required
                        autocomplete="one-time-code"
                        spellcheck="false"
                        maxlength="20"
                        placeholder="XXXX-XXXX"
                        class="nda-input mt-2 w-full rounded-xl px-4 py-3 text-center text-lg font-semibold"
                    >
                    <p class="mt-2 text-xs text-[color:var(--nda-muted)]">Saisissez le code communiqué par TTRD.FR.</p>
                </div>
                <button type="submit" class="nda-cta inline-flex w-full items-center justify-center rounded-xl px-5 py-3 text-sm font-bold tracking-wide transition">
                    J’accepte l’engagement et j’entre
                </button>
            </form>
        </section>

        <p class="mt-8 text-center text-xs text-[color:var(--nda-muted)]">
            Démonstration · <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?> · réalisé par TTRD.FR
        </p>
    </div>
</body>
</html>
