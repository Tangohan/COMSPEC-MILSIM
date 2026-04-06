<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Confirmez votre e-mail') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white rounded-xl shadow border border-slate-200 p-8 text-center">
        <?php if (!empty($error)): ?>
        <?php $flash_variant = 'error'; $flash_message = (string) $error; $flash_margin_class = 'mb-6 text-left'; require base_path('views/partials/flash_message.php'); ?>
        <?php endif; ?>
        <?php $fileMailerNotice = \email_file_mailer_notice(); ?>
        <h1 class="text-xl font-bold text-slate-900 mb-2">Presque terminé</h1>
        <?php if ($fileMailerNotice !== ''): ?>
        <p class="text-amber-900 text-sm mb-4 text-left rounded-lg border border-amber-200 bg-amber-50 px-4 py-3"><?= htmlspecialchars($fileMailerNotice, ENT_QUOTES, 'UTF-8') ?></p>
        <p class="text-slate-600 text-sm mb-2">Adresse concernée :</p>
        <?php else: ?>
        <p class="text-slate-600 text-sm mb-4">Un lien de confirmation a été envoyé à</p>
        <?php endif; ?>
        <p class="font-mono text-sm font-semibold text-slate-800 break-all"><?= htmlspecialchars((string) ($email ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
        <p class="text-slate-500 text-sm mt-4"><?= $fileMailerNotice !== '' ? 'Ouvrez le fichier .eml généré sur le serveur, ou configurez SMTP pour recevoir un vrai e-mail.' : 'Cliquez sur le lien dans l’e-mail (valide 15 min) pour activer votre compte.' ?></p>
        <form method="post" action="<?= htmlspecialchars(url('resend-verification'), ENT_QUOTES, 'UTF-8') ?>" class="mt-6">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="email" value="<?= htmlspecialchars((string) ($email ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="w-full rounded-lg bg-emerald-600 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-700 transition-colors">
                Renvoyer le lien par e-mail
            </button>
        </form>
        <a href="<?= htmlspecialchars(url('login'), ENT_QUOTES, 'UTF-8') ?>" class="inline-block mt-6 text-sm font-semibold text-blue-700 hover:underline">Retour à la connexion</a>
        <div class="mt-8 flex flex-wrap justify-center gap-x-3 gap-y-1 text-[10px] text-slate-400 px-1">
            <?php
            $legal_link_class = 'font-semibold hover:text-emerald-700';
            require base_path('views/partials/legal_site_links.php');
            ?>
        </div>
    </div>
<?php require base_path('views/partials/cookie_banner.php'); ?>
</body>
</html>
