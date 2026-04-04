<?php
$entries = $entries ?? [];
?>
<style>
    .hub-card {
        background: white;
        border: 2px solid #e2e8f0;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
    }
    .hub-card:hover {
        border-color: #2563eb;
        transform: translateY(-4px);
        box-shadow: 0 12px 24px rgba(0, 21, 41, 0.1);
    }
</style>

<div class="max-w-5xl mx-auto px-6 py-12">
    <div class="mb-12 bg-blue-50 border-l-4 border-blue-600 p-6 rounded-lg">
        <p class="text-sm text-slate-700 leading-relaxed">
            Choisissez la zone sur laquelle vous souhaitez intervenir.
        </p>
    </div>

    <div class="text-center mb-12">
        <h2 class="text-4xl md:text-5xl font-black text-[#001529] tracking-tighter uppercase italic">Sélection du hub</h2>
        <p class="text-slate-500 text-lg mt-4 leading-relaxed">Accédez aux modules opérationnels et d'administration.</p>
        <div class="h-1.5 w-24 bg-blue-600 mx-auto mt-8"></div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <?php foreach ($entries as $entry): ?>
        <a href="<?= htmlspecialchars($entry['url'], ENT_QUOTES, 'UTF-8') ?>" class="hub-card p-8 rounded-lg flex flex-col justify-between group">
            <div>
                <div class="flex justify-between items-start mb-6">
                    <div class="w-12 h-12 bg-blue-600 text-white flex items-center justify-center font-black italic text-xl flex-shrink-0">
                        <?= htmlspecialchars((string)($entry['letter'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <?php if (!empty($entry['badge'])): ?>
                    <span class="text-[10px] font-black text-blue-600 bg-blue-50 px-3 py-1 uppercase tracking-widest rounded"><?= htmlspecialchars((string)$entry['badge'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </div>
                <h3 class="text-2xl font-black text-[#001529] uppercase italic mb-2"><?= htmlspecialchars((string)($entry['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h3>
                <p class="text-slate-500 text-sm leading-relaxed"><?= htmlspecialchars((string)($entry['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="flex items-center gap-2 text-xs font-black uppercase tracking-widest text-slate-400 group-hover:text-blue-600 transition-colors mt-6">
                <span>Accéder</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <p class="mt-8 text-sm text-slate-500"><a href="<?= url('dashboard') ?>" class="underline">Retour au dashboard</a></p>
</div>
