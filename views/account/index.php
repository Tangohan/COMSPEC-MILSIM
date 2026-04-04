<?php
$baseUrl = url('');
$health = $systemHealth ?? [];
$db = $health['database'] ?? [];
$api = $health['api'] ?? [];
?>
<div class="max-w-3xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-2">Paramètres</h1>
    <p class="text-slate-600 mb-8">Gérez vos préférences, votre adresse email, votre photo et votre mot de passe.</p>
    <nav class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <a href="<?= url('account/preferences') ?>" class="block p-4 border border-slate-200 rounded-lg hover:bg-slate-50">
            <span class="font-semibold text-slate-900">Préférences</span>
            <p class="text-sm text-slate-500 mt-1">Nom d'affichage, indicatif, fuseau horaire, langue</p>
        </a>
        <a href="<?= url('account/mail') ?>" class="block p-4 border border-slate-200 rounded-lg hover:bg-slate-50">
            <span class="font-semibold text-slate-900">Adresse email</span>
            <p class="text-sm text-slate-500 mt-1">Modifier votre adresse de connexion</p>
        </a>
        <a href="<?= url('account/image') ?>" class="block p-4 border border-slate-200 rounded-lg hover:bg-slate-50">
            <span class="font-semibold text-slate-900">Photo de compte</span>
            <p class="text-sm text-slate-500 mt-1">Avatar plateforme (nav, forum, session)</p>
        </a>
        <a href="<?= url('account/portrait') ?>" class="block p-4 border border-slate-200 rounded-lg hover:bg-slate-50">
            <span class="font-semibold text-slate-900">Portrait opérateur</span>
            <p class="text-sm text-slate-500 mt-1">Image in-universe (fiche, ORBAT, briefing)</p>
        </a>
        <a href="<?= url('account/password') ?>" class="block p-4 border border-slate-200 rounded-lg hover:bg-slate-50">
            <span class="font-semibold text-slate-900">Mot de passe</span>
            <p class="text-sm text-slate-500 mt-1">Modifier votre mot de passe</p>
        </a>
    </nav>

    <!-- État de santé du système -->
    <section class="mt-10 border border-slate-200 rounded-lg overflow-hidden">
        <div class="bg-slate-50 px-4 py-3 border-b border-slate-200">
            <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider">État de santé du système</h2>
            <p class="text-xs text-slate-500 mt-0.5">Base de données, tables, API ATAK</p>
        </div>
        <div class="p-4 space-y-4 bg-white">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="inline-flex w-2 h-2 rounded-full <?= !empty($db['ok']) ? 'bg-emerald-500' : 'bg-rose-500' ?>"></span>
                    <span class="text-sm font-semibold text-slate-800">Base de données</span>
                </div>
                <p class="text-xs text-slate-600 ml-4"><?= !empty($db['ok']) ? htmlspecialchars($db['message']) : htmlspecialchars($db['message'] ?? 'Indisponible') ?></p>
                <?php if (!empty($db['tables'])): ?>
                <div class="ml-4 mt-2 border border-slate-100 rounded p-2 bg-slate-50/50">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Tables principales</p>
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                        <?php foreach ($db['tables'] as $table => $info): ?>
                        <div class="flex justify-between">
                            <dt class="text-slate-600"><?= htmlspecialchars($table) ?></dt>
                            <dd class="font-mono text-slate-800">
                                <?php if (!empty($info['exists'])): ?>
                                    <?php if (isset($info['rows'])): ?><?= (int) $info['rows'] ?> ligne(s)<?php else: ?>—<?php endif; ?>
                                    <?php if (!empty($info['error'])): ?><span class="text-rose-600" title="<?= htmlspecialchars($info['error']) ?>">erreur</span><?php endif; ?>
                                <?php else: ?>
                                    <span class="text-amber-600">absente</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <?php endforeach; ?>
                    </dl>
                </div>
                <?php endif; ?>
            </div>
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="inline-flex w-2 h-2 rounded-full <?= !empty($api['ok']) ? 'bg-emerald-500' : 'bg-slate-400' ?>"></span>
                    <span class="text-sm font-semibold text-slate-800">API ATAK (C2)</span>
                </div>
                <p class="text-xs text-slate-600 ml-4"><?= htmlspecialchars($api['message'] ?? 'Non vérifiée') ?></p>
                <?php if (!empty($api['url'])): ?>
                <p class="text-[10px] text-slate-500 ml-4 mt-0.5 font-mono truncate" title="<?= htmlspecialchars($api['url']) ?>"><?= htmlspecialchars($api['url']) ?></p>
                <?php elseif (empty($api['ok']) && ($api['message'] ?? '') !== 'Non vérifiée (base indisponible)'): ?>
                <p class="text-xs text-slate-500 ml-4 mt-1">Renseignez l’URL du serveur C2 (node_url) dans <a href="<?= url('admin/atak-config') ?>" class="underline text-indigo-600 hover:text-indigo-800">Admin → Configuration ATAK</a>.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <p class="mt-8 text-sm text-slate-500"><a href="<?= url('dashboard') ?>" class="underline">Retour au dashboard</a></p>
</div>
