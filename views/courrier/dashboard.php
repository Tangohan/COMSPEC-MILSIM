<?php
$c = $courrier ?? [];
$baseUrl = url('');
?>
<div class="max-w-7xl mx-auto px-8 py-12 font-['JetBrains_Mono',_monospace] bg-slate-50 min-h-screen">
    <?php if (\App\Core\Session::get('success')): ?>
    <p class="mb-4 text-sm text-emerald-600"><?= htmlspecialchars((string)\App\Core\Session::get('success')) ?></p>
    <?php \App\Core\Session::forget('success'); endif; ?>
    <?php if (\App\Core\Session::get('error')): ?>
    <p class="mb-4 text-sm text-red-600"><?= htmlspecialchars((string)\App\Core\Session::get('error')) ?></p>
    <?php \App\Core\Session::forget('error'); endif; ?>

    <div class="flex items-end justify-between gap-4 mb-16 border-b-2 border-slate-900 pb-8">
        <div>
            <h1 class="text-[10px] font-black text-slate-400 tracking-[0.2em] uppercase mb-2">Service Administratif Cerbere</h1>
            <h2 class="text-4xl font-black text-slate-900 tracking-tighter uppercase">Bureau Courrier</h2>
        </div>
        <a href="<?= $baseUrl ?>/courrier/editor" class="px-8 py-4 bg-slate-900 text-white text-[10px] font-black rounded-full hover:bg-slate-800 transition-all uppercase tracking-[0.2em] shadow-lg">
            Nouveau Document
        </a>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-6 mb-16">
        <a href="<?= $baseUrl ?>/courrier/editor?status=draft" class="group p-8 bg-white border border-slate-200 rounded-3xl shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all">
            <p class="text-3xl font-black text-slate-900 mb-1"><?= (int)($c['draft_count'] ?? 0) ?></p>
            <p class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] group-hover:text-slate-900 transition-colors">Brouillons</p>
        </a>

        <a href="<?= $baseUrl ?>/courrier?pending=1" class="group p-8 bg-white border border-slate-200 rounded-3xl shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all">
            <p class="text-3xl font-black text-amber-600 mb-1"><?= (int)($c['pending_count'] ?? 0) ?></p>
            <p class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] group-hover:text-amber-600 transition-colors">À Valider</p>
        </a>

        <div class="p-8 bg-white border border-slate-200 rounded-3xl shadow-sm">
            <p class="text-3xl font-black text-emerald-600 mb-1"><?= (int)($c['sent_count'] ?? 0) ?></p>
            <p class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em]">Envoyés</p>
        </div>

        <div class="p-8 bg-white border border-slate-200 rounded-3xl shadow-sm">
            <p class="text-3xl font-black text-rose-600 mb-1"><?= (int)($c['rejected_count'] ?? 0) ?></p>
            <p class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em]">Refusés</p>
        </div>

        <div class="p-8 bg-slate-900 rounded-3xl shadow-xl">
            <p class="text-3xl font-black text-white mb-1"><?= (int)($c['today_count'] ?? 0) ?></p>
            <p class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em]">Aujourd'hui</p>
        </div>

        <div class="p-8 bg-white border border-slate-200 rounded-3xl shadow-sm">
            <p class="text-3xl font-black text-slate-300 mb-1"><?= (int)($c['archived_count'] ?? 0) ?></p>
            <p class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em]">Archivés</p>
        </div>
    </div>

    <div class="grid md:grid-cols-2 gap-12">
        <div class="bg-white p-10 rounded-[2.5rem] border border-slate-200 shadow-sm">
            <h3 class="text-[11px] font-black text-slate-900 uppercase tracking-[0.2em] mb-10 flex items-center gap-4">
                <span class="w-1.5 h-6 bg-slate-900"></span>
                Brouillons Récents
            </h3>
            <?php $recent = $c['recent_drafts'] ?? []; if (empty($recent)): ?>
            <div class="flex flex-col items-center justify-center py-12 opacity-30">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 text-center leading-relaxed">
                    Aucun brouillon
                </p>
            </div>
            <?php else: ?>
            <ul class="space-y-4">
                <?php foreach (array_slice($recent, 0, 5) as $d): ?>
                <li class="group">
                    <a href="<?= $baseUrl ?>/courrier/read/<?= (int)$d['id'] ?>" class="flex items-center justify-between p-6 rounded-2xl bg-slate-50 hover:bg-slate-100 border border-slate-100 transition-all">
                        <span class="text-sm font-bold text-slate-700 group-hover:text-slate-900 uppercase tracking-tighter"><?= htmlspecialchars($d['title'] ?: 'Sans Titre') ?></span>
                        <div class="flex items-center gap-4">
                            <span class="text-[8px] font-black text-slate-300 uppercase tracking-widest"><?= !empty($d['reference_number']) ? htmlspecialchars($d['reference_number']) : 'ID ' . str_pad((string)(int)$d['id'], 4, '0', STR_PAD_LEFT) ?></span>
                            <svg class="w-4 h-4 text-slate-300 group-hover:text-slate-900 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7"></path></svg>
                        </div>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>

        <div class="bg-white p-10 rounded-[2.5rem] border border-slate-200 shadow-sm">
            <h3 class="text-[11px] font-black text-slate-900 uppercase tracking-[0.2em] mb-10 flex items-center gap-4">
                <span class="w-1.5 h-6 bg-amber-500"></span>
                Attente Validation
            </h3>
            <?php $pending = $c['recent_pending'] ?? []; if (empty($pending)): ?>
            <div class="flex flex-col items-center justify-center py-12 opacity-30">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 text-center leading-relaxed">
                    Aucun document en file<br>de validation
                </p>
            </div>
            <?php else: ?>
            <ul class="space-y-4">
                <?php foreach (array_slice($pending, 0, 5) as $d): ?>
                <li class="group">
                    <a href="<?= $baseUrl ?>/courrier/read/<?= (int)$d['id'] ?>" class="flex items-center justify-between p-6 rounded-2xl bg-slate-50 hover:bg-slate-100 border border-slate-100 transition-all">
                        <span class="text-sm font-bold text-slate-700 group-hover:text-slate-900 uppercase tracking-tighter"><?= htmlspecialchars($d['title'] ?: 'Sans Titre') ?></span>
                        <div class="flex items-center gap-4">
                            <span class="text-[8px] font-black text-slate-300 uppercase tracking-widest"><?= !empty($d['reference_number']) ? htmlspecialchars($d['reference_number']) : 'ID ' . str_pad((string)(int)$d['id'], 4, '0', STR_PAD_LEFT) ?></span>
                            <svg class="w-4 h-4 text-slate-300 group-hover:text-slate-900 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7"></path></svg>
                        </div>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="mt-20 flex flex-wrap gap-4 border-t border-slate-200 pt-12">
        <a href="<?= $baseUrl ?>/courrier/templates" class="px-8 py-3 bg-white border border-slate-200 text-slate-900 text-[9px] font-black rounded-full hover:bg-slate-900 hover:text-white transition-all uppercase tracking-[0.2em]">
            Modèles
        </a>
        <a href="<?= $baseUrl ?>/courrier/presets" class="px-8 py-3 bg-white border border-slate-200 text-slate-900 text-[9px] font-black rounded-full hover:bg-slate-900 hover:text-white transition-all uppercase tracking-[0.2em]">
            Formats Presets
        </a>
        <a href="<?= $baseUrl ?>/courrier/history" class="px-8 py-3 bg-white border border-slate-200 text-slate-900 text-[9px] font-black rounded-full hover:bg-slate-900 hover:text-white transition-all uppercase tracking-[0.2em]">
            Historique
        </a>
        <a href="<?= $baseUrl ?>/courrier/archives" class="px-8 py-3 bg-white border border-slate-200 text-slate-900 text-[9px] font-black rounded-full hover:bg-slate-900 hover:text-white transition-all uppercase tracking-[0.2em]">
            Archives
        </a>
    </div>
</div>
