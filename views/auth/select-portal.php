<?php
/** @var bool $canTba */
/** @var string $tenantName */
/** @var string $displayName */
/** @var string $callsign */
$error = \App\Core\Session::getFlash('error');
$title = $title ?? 'Choisir un espace';
$who = trim($displayName !== '' ? $displayName : $callsign);
$community = trim((string) $tenantName);
$base = url('');
$brand = function_exists('email_brand_name') ? email_brand_name() : 'Athena';
$brandText = htmlspecialchars($brand, ENT_QUOTES, 'UTF-8');
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$canTba = !empty($canTba);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(html_lang(), ENT_QUOTES, 'UTF-8') ?>" class="h-full bg-black">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $h($title) ?> — <?= $brandText ?></title>
    <meta name="theme-color" content="#050505">
    <meta name="robots" content="noindex,nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <?php $tailwindBaseUrl = $base; require base_path('views/partials/tailwind_cdn_or_build.php'); ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/home-impact.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <style>
        html, body { background: #050505; min-height: 100%; }
        .portal-pick-section {
            padding-block: clamp(2.5rem, 6vh, 4.5rem);
            padding-inline: clamp(1.25rem, 4vw, 4rem);
        }
        .portal-card {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 0.9rem;
            align-items: start;
            padding: 1.1rem 1.15rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.045), rgba(255, 255, 255, 0.02));
            cursor: pointer;
            transition: border-color 0.2s ease, background 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
        }
        .portal-card:hover {
            border-color: rgba(5, 150, 105, 0.45);
            background: linear-gradient(135deg, rgba(5, 150, 105, 0.12), rgba(5, 150, 105, 0.06));
            box-shadow: 0 0 16px rgba(5, 150, 105, 0.12);
            transform: translateY(-1px);
        }
        .portal-card:has(:checked) {
            border-color: rgba(52, 211, 153, 0.65);
            background: linear-gradient(135deg, rgba(5, 150, 105, 0.18), rgba(5, 150, 105, 0.08));
            box-shadow: 0 0 0 1px rgba(52, 211, 153, 0.25), 0 0 20px rgba(5, 150, 105, 0.15);
        }
        .portal-card__abbr {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 2.85rem;
            height: 2rem;
            padding: 0 0.45rem;
            border: 1px solid rgba(52, 211, 153, 0.5);
            border-radius: 6px;
            background: linear-gradient(135deg, rgba(52, 211, 153, 0.15), rgba(52, 211, 153, 0.08));
            color: #6ee7b7;
            font-size: 10px;
            font-weight: 900;
            letter-spacing: 0.14em;
        }
        .portal-card__abbr--cmd {
            border-color: rgba(251, 191, 36, 0.45);
            background: linear-gradient(135deg, rgba(251, 191, 36, 0.14), rgba(251, 191, 36, 0.06));
            color: #fbbf24;
        }
        .portal-card:has(:checked) .portal-card__abbr {
            border-color: rgba(52, 211, 153, 0.85);
            color: #a7f3d0;
        }
        .portal-check {
            width: 1rem;
            height: 1rem;
            border-radius: 0.25rem;
            border: 1px solid rgba(244, 244, 240, 0.28);
            background: rgba(244, 244, 240, 0.04);
            accent-color: #059669;
        }
        @media (prefers-reduced-motion: reduce) {
            .portal-card { transition: none; }
            .portal-card:hover { transform: none; }
        }
    </style>
</head>
<body class="home-impact min-h-[100svh] bg-[var(--hi-void,#050505)] text-[var(--hi-ink,#f4f4f0)] antialiased selection:bg-emerald-500 selection:text-slate-950">

<section class="relative flex min-h-[100svh] flex-col bg-[var(--hi-void,#050505)]">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,rgba(52,211,153,0.06),transparent_55%)]" aria-hidden="true"></div>

    <header class="relative z-10 shrink-0 border-b border-white/5 bg-black">
        <div class="mx-auto flex h-12 max-w-[100rem] items-center justify-between gap-3 px-5 md:px-8">
            <span class="hi-kicker text-white/45">Session</span>
            <span class="text-[11px] font-black uppercase tracking-[0.32em] text-white"><?= $brandText ?></span>
            <form method="post" action="<?= url('logout') ?>" class="m-0">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="hi-kicker text-emerald-400/85 transition hover:text-emerald-300">
                    Se déconnecter
                </button>
            </form>
        </div>
    </header>

    <div class="relative z-10 flex flex-1 flex-col justify-center portal-pick-section">
        <p class="hi-kicker text-emerald-400/90">Commandement</p>
        <h1 class="mt-4 max-w-3xl text-3xl font-black tracking-tight text-white sm:text-4xl">
            Où voulez-vous entrer&nbsp;?
        </h1>
        <?php if ($who !== '' || $community !== ''): ?>
            <p class="mt-4 text-sm text-white/55">
                <?php if ($who !== ''): ?><span class="text-white/80"><?= $h($who) ?></span><?php endif; ?>
                <?php if ($who !== '' && $community !== ''): ?> · <?php endif; ?>
                <?php if ($community !== ''): ?><?= $h($community) ?><?php endif; ?>
            </p>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mt-6 max-w-3xl">
                <?php $flash_variant = 'error'; $flash_message = $error; $flash_surface = 'dark'; require base_path('views/partials/flash_message.php'); ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= url('login/choisir-espace') ?>" class="mt-10 max-w-3xl space-y-6">
            <?= \App\Core\Csrf::field() ?>

            <div class="grid gap-3 sm:grid-cols-2">
                <?php if ($canTba): ?>
                <label class="portal-card">
                    <input type="radio" name="portal" value="tba" class="sr-only" required>
                    <span class="portal-card__abbr portal-card__abbr--cmd">CMD</span>
                    <span>
                        <span class="block text-[10px] font-black uppercase tracking-[0.22em] text-amber-400/90">Administration</span>
                        <span class="mt-1.5 block text-base font-bold text-white">Tableau de bord administratif</span>
                        <span class="mt-1.5 block text-sm leading-relaxed text-white/50">Paramétrage, effectifs, recrutement, modération et outils d’état-major.</span>
                    </span>
                </label>
                <?php endif; ?>

                <label class="portal-card<?= $canTba ? '' : ' sm:col-span-2' ?>">
                    <input type="radio" name="portal" value="jnet" class="sr-only"<?= $canTba ? '' : ' checked' ?> required>
                    <span class="portal-card__abbr">JNT</span>
                    <span>
                        <span class="block text-[10px] font-black uppercase tracking-[0.22em] text-emerald-400/90">Extranet opérationnel</span>
                        <span class="mt-1.5 block text-base font-bold text-white">JNET Extranet</span>
                        <span class="mt-1.5 block text-sm leading-relaxed text-white/50">Situation d’unité, personnel, opérations, cibles et renseignement.</span>
                    </span>
                </label>
            </div>

            <label class="flex cursor-pointer select-none items-center gap-3 text-sm text-white/55">
                <input type="checkbox" name="remember" value="1" class="portal-check">
                Se souvenir de ce choix sur cet appareil
            </label>

            <button type="submit" class="hi-cta hi-cta-solid w-full justify-center sm:w-auto sm:min-w-[14rem]">
                Entrer
            </button>
        </form>
    </div>
</section>

<?php require base_path('views/partials/cookie_banner.php'); ?>
</body>
</html>
