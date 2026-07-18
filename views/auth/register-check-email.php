<?php
$base = url('');
$brand = function_exists('email_brand_name') ? email_brand_name() : 'Athena';
$emailAddr = (string) ($email ?? '');
$error = $error ?? null;
$fileMailerNotice = \email_file_mailer_notice();
?>
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Confirmez votre e-mail', ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="theme-color" content="#050505">
    <meta name="robots" content="noindex,nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <?php $tailwindBaseUrl = $base; require base_path('views/partials/tailwind_cdn_or_build.php'); ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/home-impact.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <style>
        html, body { background: #050505; min-height: 100%; }
        .login-panel {
            border: 1px solid rgba(244, 244, 240, 0.1);
            background: linear-gradient(165deg, rgba(244, 244, 240, 0.06) 0%, rgba(244, 244, 240, 0.02) 100%);
            backdrop-filter: blur(12px);
            border-radius: 1.25rem;
            box-shadow: 0 24px 64px -28px rgba(0, 0, 0, 0.65);
        }
    </style>
</head>
<body class="home-impact min-h-[100svh] bg-[var(--hi-void,#050505)] text-[var(--hi-ink,#f4f4f0)] antialiased">

<div class="pointer-events-none fixed inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(52,211,153,0.07),transparent_50%)]" aria-hidden="true"></div>

<header class="relative z-20 border-b border-white/5 bg-black/80 backdrop-blur-md">
    <div class="mx-auto flex h-14 max-w-[100rem] items-center justify-between px-5 md:px-8">
        <a href="<?= htmlspecialchars(url(''), ENT_QUOTES, 'UTF-8') ?>" class="hi-kicker text-white/45 transition hover:text-white">Accueil</a>
        <span class="text-[11px] font-black uppercase tracking-[0.32em] text-white"><?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?></span>
        <a href="<?= htmlspecialchars(url('login'), ENT_QUOTES, 'UTF-8') ?>" class="hi-kicker text-emerald-400/90 transition hover:text-emerald-300">Connexion</a>
    </div>
</header>

<main class="relative z-10 mx-auto flex min-h-[calc(100svh-3.5rem)] w-full max-w-lg items-center px-5 py-12">
    <div class="w-full">
        <p class="hi-kicker text-emerald-400/90">Presque terminé</p>
        <h1 class="mt-3 text-3xl font-black tracking-tight text-white">Confirmez votre e-mail</h1>
        <p class="mt-3 text-sm leading-relaxed text-white/55">
            Votre compte est créé. Ouvrez le message reçu pour activer l’accès.
        </p>

        <div class="login-panel mt-8 p-6 sm:p-8 text-center">
            <?php if (!empty($error)): ?>
                <?php $flash_variant = 'error'; $flash_message = (string) $error; $flash_surface = 'dark'; $flash_margin_class = 'mb-6 text-left'; require base_path('views/partials/flash_message.php'); ?>
            <?php endif; ?>

            <?php if ($fileMailerNotice !== ''): ?>
                <p class="mb-4 rounded-xl border border-amber-400/30 bg-amber-500/10 px-4 py-3 text-left text-sm text-amber-100/90">
                    <?= htmlspecialchars($fileMailerNotice, ENT_QUOTES, 'UTF-8') ?>
                </p>
                <p class="mb-2 text-sm text-white/50">Adresse concernée</p>
            <?php else: ?>
                <p class="mb-2 text-sm text-white/50">Un lien a été envoyé à</p>
            <?php endif; ?>

            <p class="break-all text-base font-bold text-emerald-300"><?= htmlspecialchars($emailAddr, ENT_QUOTES, 'UTF-8') ?></p>
            <p class="mt-4 text-sm leading-relaxed text-white/45">
                <?= $fileMailerNotice !== ''
                    ? 'Consultez le message généré côté serveur, ou configurez l’envoi d’e-mails pour recevoir un vrai message.'
                    : 'Le lien est valable 15 minutes. Pensez à vérifier vos indésirables.' ?>
            </p>

            <form method="post" action="<?= htmlspecialchars(url('resend-verification'), ENT_QUOTES, 'UTF-8') ?>" class="mt-6">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="email" value="<?= htmlspecialchars($emailAddr, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="hi-cta hi-cta-solid w-full justify-center">
                    Renvoyer le lien
                </button>
            </form>

            <a href="<?= htmlspecialchars(url('login'), ENT_QUOTES, 'UTF-8') ?>" class="mt-6 inline-block text-sm font-semibold text-white/55 transition hover:text-emerald-300">
                Retour à la connexion
            </a>
        </div>

        <div class="mt-10 flex flex-wrap justify-center gap-x-3 gap-y-1 text-center text-[10px] text-white/30">
            <?php
            $legal_link_class = 'font-semibold text-white/40 hover:text-emerald-400';
            require base_path('views/partials/legal_site_links.php');
            ?>
        </div>
    </div>
</main>

<?php require base_path('views/partials/cookie_banner.php'); ?>
</body>
</html>
