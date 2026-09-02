<?php
$c = $courrier ?? [];
$baseUrl = url('');
$h = static fn (string $s): string => $baseUrl . '/courrier/history?status=' . rawurlencode($s);
?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-10">
    <?php if (\App\Core\Session::get('success')): ?>
    <p class="mb-4 text-sm text-emerald-600"><?= htmlspecialchars((string)\App\Core\Session::get('success')) ?></p>
    <?php \App\Core\Session::forget('success'); endif; ?>
    <?php if (\App\Core\Session::get('error')): ?>
    <p class="mb-4 text-sm text-red-600"><?= htmlspecialchars((string)\App\Core\Session::get('error')) ?></p>
    <?php \App\Core\Session::forget('error'); endif; ?>

    <header class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-6 mb-10 pb-8 border-b border-slate-200/80">
        <div>
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-sky-700 mb-1">Bureau Courrier</p>
            <h1 class="text-3xl sm:text-4xl font-black text-slate-900 tracking-tight">Tableau de bord</h1>
            <p class="mt-2 text-sm text-slate-600 max-w-xl">Brouillons, validation, signature et envoi — suivez tout le circuit documentaire.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <?php $nu = (int)($c['courrier_notif_unread'] ?? 0); ?>
            <a href="<?= $baseUrl ?>/courrier/notifications" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-sm font-semibold text-slate-800 shadow-sm hover:border-sky-300 hover:bg-sky-50 transition-colors">
                Notifications
                <?php if ($nu > 0): ?>
                <span class="min-w-[1.25rem] h-6 px-1.5 rounded-full bg-sky-600 text-white text-xs font-bold flex items-center justify-center"><?= $nu > 99 ? '99+' : $nu ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= $baseUrl ?>/courrier/signature" class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-sm font-semibold text-slate-800 shadow-sm hover:border-sky-300 hover:bg-sky-50 transition-colors">
                Ma signature
            </a>
            <a href="<?= $baseUrl ?>/courrier/editor" class="inline-flex items-center justify-center px-5 py-2.5 rounded-xl bg-slate-900 text-white text-sm font-black uppercase tracking-wide shadow-md hover:bg-emerald-600 transition-colors">
                Nouveau document
            </a>
        </div>
    </header>

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4 mb-12">
        <a href="<?= $h('draft') ?>" class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm hover:shadow-md hover:border-slate-300 transition-all">
            <p class="text-2xl sm:text-3xl font-black text-slate-900 tabular-nums"><?= (int)($c['draft_count'] ?? 0) ?></p>
            <p class="mt-1 text-[10px] font-bold uppercase tracking-wider text-slate-500 group-hover:text-slate-800">Brouillons</p>
        </a>
        <a href="<?= $h('pending_validation') ?>" class="group rounded-2xl border border-amber-200/80 bg-amber-50/80 p-5 shadow-sm hover:shadow-md transition-all">
            <p class="text-2xl sm:text-3xl font-black text-amber-700 tabular-nums"><?= (int)($c['pending_count'] ?? 0) ?></p>
            <p class="mt-1 text-[10px] font-bold uppercase tracking-wider text-amber-800/80">À valider</p>
        </a>
        <a href="<?= $h('validated') ?>" class="group rounded-2xl border border-sky-200/80 bg-sky-50/60 p-5 shadow-sm hover:shadow-md transition-all">
            <p class="text-2xl sm:text-3xl font-black text-sky-800 tabular-nums"><?= (int)($c['validated_count'] ?? 0) ?></p>
            <p class="mt-1 text-[10px] font-bold uppercase tracking-wider text-sky-800/70">Validés</p>
        </a>
        <a href="<?= $h('signed') ?>" class="group rounded-2xl border border-emerald-200/90 bg-emerald-50/80 p-5 shadow-sm hover:shadow-md transition-all ring-1 ring-emerald-200/50">
            <p class="text-2xl sm:text-3xl font-black text-emerald-700 tabular-nums"><?= (int)($c['signed_count'] ?? 0) ?></p>
            <p class="mt-1 text-[10px] font-bold uppercase tracking-wider text-emerald-900/80">Signés</p>
        </a>
        <a href="<?= $h('sent') ?>" class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm hover:shadow-md transition-all">
            <p class="text-2xl sm:text-3xl font-black text-emerald-600 tabular-nums"><?= (int)($c['sent_count'] ?? 0) ?></p>
            <p class="mt-1 text-[10px] font-bold uppercase tracking-wider text-slate-500">Envoyés</p>
        </a>
        <a href="<?= $h('rejected') ?>" class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm hover:shadow-md transition-all">
            <p class="text-2xl sm:text-3xl font-black text-rose-600 tabular-nums"><?= (int)($c['rejected_count'] ?? 0) ?></p>
            <p class="mt-1 text-[10px] font-bold uppercase tracking-wider text-slate-500">Refusés</p>
        </a>
        <div class="rounded-2xl border border-slate-900 bg-slate-900 p-5 text-white shadow-md">
            <p class="text-2xl sm:text-3xl font-black tabular-nums"><?= (int)($c['today_count'] ?? 0) ?></p>
            <p class="mt-1 text-[10px] font-bold uppercase tracking-wider text-white/70">Créés aujourd’hui</p>
        </div>
        <a href="<?= $baseUrl ?>/courrier/archives" class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm hover:shadow-md transition-all">
            <p class="text-2xl sm:text-3xl font-black text-slate-400 tabular-nums"><?= (int)($c['archived_count'] ?? 0) ?></p>
            <p class="mt-1 text-[10px] font-bold uppercase tracking-wider text-slate-500">Archivés</p>
        </a>
    </div>

    <div class="grid lg:grid-cols-3 gap-6 lg:gap-8">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xs font-black uppercase tracking-[0.15em] text-slate-900 mb-4 flex items-center gap-2">
                <span class="h-6 w-1 rounded-full bg-slate-800"></span>
                Brouillons récents
            </h2>
            <?php $recent = $c['recent_drafts'] ?? []; if (empty($recent)): ?>
            <p class="text-sm text-slate-500 py-8 text-center">Aucun brouillon.</p>
            <?php else: ?>
            <ul class="space-y-2">
                <?php foreach (array_slice($recent, 0, 5) as $d): ?>
                <li>
                    <a href="<?= $baseUrl ?>/courrier/read/<?= (int)$d['id'] ?>" class="flex items-center justify-between gap-3 rounded-xl px-3 py-3 hover:bg-slate-50 transition-colors">
                        <span class="text-sm font-semibold text-slate-800 truncate"><?= htmlspecialchars($d['title'] ?: 'Sans titre') ?></span>
                        <span class="shrink-0 text-[10px] font-mono text-slate-400"><?= !empty($d['reference_number']) ? htmlspecialchars($d['reference_number']) : '#' . (int)$d['id'] ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xs font-black uppercase tracking-[0.15em] text-amber-900 mb-4 flex items-center gap-2">
                <span class="h-6 w-1 rounded-full bg-amber-500"></span>
                En attente de validation
            </h2>
            <?php $pending = $c['recent_pending'] ?? []; if (empty($pending)): ?>
            <p class="text-sm text-slate-500 py-8 text-center">Aucun document en file.</p>
            <?php else: ?>
            <ul class="space-y-2">
                <?php foreach (array_slice($pending, 0, 5) as $d): ?>
                <li>
                    <a href="<?= $baseUrl ?>/courrier/read/<?= (int)$d['id'] ?>" class="flex items-center justify-between gap-3 rounded-xl px-3 py-3 hover:bg-amber-50/80 transition-colors">
                        <span class="text-sm font-semibold text-slate-800 truncate"><?= htmlspecialchars($d['title'] ?: 'Sans titre') ?></span>
                        <span class="shrink-0 text-[10px] font-mono text-slate-400"><?= !empty($d['reference_number']) ? htmlspecialchars($d['reference_number']) : '#' . (int)$d['id'] ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>

        <section class="rounded-2xl border border-emerald-200/80 bg-emerald-50/30 p-6 shadow-sm">
            <h2 class="text-xs font-black uppercase tracking-[0.15em] text-emerald-900 mb-1 flex items-center gap-2">
                <span class="h-6 w-1 rounded-full bg-emerald-500"></span>
                Signés (en attente d’envoi)
            </h2>
            <p class="text-xs text-emerald-800/80 mb-4">Après signature, le document passe en « Signé » jusqu’à l’envoi officiel.</p>
            <?php $signed = $c['recent_signed'] ?? []; if (empty($signed)): ?>
            <p class="text-sm text-slate-600 py-6 text-center">Aucun courrier signé pour l’instant.</p>
            <?php else: ?>
            <ul class="space-y-2">
                <?php foreach (array_slice($signed, 0, 5) as $d): ?>
                <li>
                    <a href="<?= $baseUrl ?>/courrier/read/<?= (int)$d['id'] ?>" class="flex items-center justify-between gap-3 rounded-xl px-3 py-3 hover:bg-white/90 transition-colors border border-transparent hover:border-emerald-200">
                        <span class="text-sm font-semibold text-slate-800 truncate"><?= htmlspecialchars($d['title'] ?: 'Sans titre') ?></span>
                        <span class="shrink-0 text-[10px] font-mono text-emerald-700/80"><?= !empty($d['reference_number']) ? htmlspecialchars($d['reference_number']) : '#' . (int)$d['id'] ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <p class="mt-4"><a href="<?= $h('signed') ?>" class="text-xs font-bold text-emerald-800 hover:underline">Voir tous les signés →</a></p>
        </section>
    </div>

    <nav class="mt-12 flex flex-wrap gap-2 pt-8 border-t border-slate-200">
        <a href="<?= $baseUrl ?>/courrier/signature" class="px-4 py-2 rounded-lg text-sm font-semibold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50">Ma signature</a>
        <a href="<?= $baseUrl ?>/courrier/templates" class="px-4 py-2 rounded-lg text-sm font-semibold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50">Modèles</a>
        <a href="<?= $baseUrl ?>/courrier/presets" class="px-4 py-2 rounded-lg text-sm font-semibold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50">Formats</a>
        <a href="<?= $baseUrl ?>/courrier/history" class="px-4 py-2 rounded-lg text-sm font-semibold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50">Historique complet</a>
        <a href="<?= $baseUrl ?>/courrier/archives" class="px-4 py-2 rounded-lg text-sm font-semibold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50">Archives</a>
        <a href="<?= $baseUrl ?>/courrier/traceabilite" class="px-4 py-2 rounded-lg text-sm font-semibold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50">Traçabilité décisionnelle</a>
    </nav>
</div>
