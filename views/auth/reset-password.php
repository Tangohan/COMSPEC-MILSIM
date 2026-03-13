<?php
$base = url('');
$token = $token ?? '';
$error = $error ?? \App\Core\Session::getFlash('error');
$title = $title ?? 'Nouveau mot de passe';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> — Athena</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <?php if (is_file(base_path('public/assets/css/styles.css'))): ?>
    <link href="<?= $base ?>/assets/css/styles.css" rel="stylesheet">
    <?php endif; ?>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .input-field {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            width: 100%;
            outline: none;
            transition: all 0.2s;
            font-size: 0.875rem;
        }
        .input-field:focus {
            border-color: #0f172a;
            box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.08);
        }
        .section-title {
            font-size: 9px;
            font-weight: 900;
            letter-spacing: 0.3em;
            text-transform: uppercase;
            color: #94a3b8;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .section-title::after {
            content: "";
            flex: 1;
            height: 1px;
            background: #e2e8f0;
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen text-slate-900 antialiased">

    <header class="sticky top-0 z-50 w-full bg-slate-50/95 backdrop-blur border-b border-slate-200">
        <div class="max-w-5xl mx-auto px-6 h-14 flex items-center justify-between">
            <a href="<?= $base ?>/" class="text-sm font-black tracking-widest uppercase text-slate-900">Athena</a>
            <nav class="flex items-center gap-6">
                <a href="<?= $base ?>/" class="text-xs font-semibold uppercase text-slate-600 hover:text-slate-900">Accueil</a>
                <a href="<?= url('login') ?>" class="text-xs font-semibold uppercase text-slate-600 hover:text-slate-900">Connexion</a>
            </nav>
        </div>
    </header>

    <main class="max-w-md mx-auto px-6 py-16">
        <div class="section-title">Réinitialisation</div>
        <h1 class="text-2xl font-black tracking-tight text-slate-900 mb-2">Nouveau mot de passe</h1>
        <p class="text-slate-600 text-sm mb-8">Choisissez un nouveau mot de passe (minimum 8 caractères).</p>

        <?php if ($error): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 text-sm rounded-xl"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="bg-white border border-slate-200 rounded-2xl p-8 shadow-sm">
            <form method="post" action="<?= url('reset-password') ?>" class="space-y-5">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <div>
                    <label for="password" class="block text-[10px] font-bold text-slate-500 tracking-widest uppercase mb-2">Nouveau mot de passe</label>
                    <input type="password" name="password" id="password" required minlength="8" autocomplete="new-password" class="input-field" placeholder="••••••••">
                </div>
                <div>
                    <label for="password_confirmation" class="block text-[10px] font-bold text-slate-500 tracking-widest uppercase mb-2">Confirmer le mot de passe</label>
                    <input type="password" name="password_confirmation" id="password_confirmation" required minlength="8" autocomplete="new-password" class="input-field" placeholder="••••••••">
                </div>
                <button type="submit" class="w-full py-3.5 bg-slate-900 text-white font-bold rounded-xl tracking-wider uppercase text-[11px] hover:bg-slate-800 active:scale-[0.99] transition-all">
                    Réinitialiser le mot de passe
                </button>
            </form>
        </div>

        <p class="mt-8 text-center text-[11px] text-slate-500">
            <a href="<?= url('login') ?>" class="underline hover:text-slate-700">Retour à la connexion</a>
            <span class="mx-2">·</span>
            <a href="<?= $base ?>/" class="underline hover:text-slate-700">Accueil</a>
        </p>
    </main>

    <footer class="border-t border-slate-200 py-6 mt-12">
        <div class="max-w-5xl mx-auto px-6 text-center text-[10px] text-slate-400 tracking-widest uppercase">Athena — SaaS RH tactique MILSIM Arma 3</div>
    </footer>
</body>
</html>
