<?php
/** @var array<string, mixed> $appConfig */
$c = $appConfig ?? [];
?>
<div class="max-w-3xl mx-auto px-6 py-12">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-black text-slate-900">Paramètres applicatifs</h1>
        <a href="<?= url('admin') ?>" class="text-sm font-medium text-slate-600 hover:text-slate-900 underline">Retour</a>
    </div>
    <p class="text-slate-600 mb-6 text-sm">Vue lecture seule des valeurs exposées par la configuration (fichier d’environnement et <code class="bg-slate-100 px-1 rounded">app/Config/app.php</code>). Les secrets (clés API, mots de passe) ne sont jamais affichés ici.</p>

    <dl class="rounded-xl border border-slate-200 bg-white divide-y divide-slate-100">
        <?php foreach ($c as $key => $val): ?>
            <div class="px-4 py-3 flex flex-wrap justify-between gap-2">
                <dt class="text-sm font-medium text-slate-700"><?= htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') ?></dt>
                <dd class="text-sm text-slate-900 font-mono break-all">
                    <?php if (is_bool($val)): ?>
                        <?= $val ? 'true' : 'false' ?>
                    <?php else: ?>
                        <?= htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                </dd>
            </div>
        <?php endforeach; ?>
    </dl>

    <p class="mt-8 text-sm text-slate-500">
        Pour les erreurs applicatives en production, branchez un outil type Sentry (SDK PHP) dans le point d’entrée et logguez les exceptions — non inclus par défaut dans ce dépôt.
    </p>
</div>
