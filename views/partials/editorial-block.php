<?php
$label = $label ?? 'Operational_Notes_v2.0';
$text = $text ?? '';
$ref = $ref ?? 'ATH-01';
$date = $date ?? date('d.m.Y');
?>
<div class="editorial-block max-w-2xl border-l-2 border-emerald-500/20 pl-8 my-12">
    <span class="editorial-block__label text-[10px] font-black uppercase tracking-[0.4em] text-slate-400 mb-4">
        <?= htmlspecialchars($label) ?>
    </span>
    <?php if ($text !== ''): ?>
    <p class="editorial-block__text text-lg text-slate-600 leading-relaxed font-serif italic">
        «&nbsp;<?= htmlspecialchars($text) ?>&nbsp;»
    </p>
    <?php endif; ?>
    <div class="editorial-block__meta mt-6 flex items-center gap-4">
        <span class="editorial-block__ref text-[9px] font-bold text-slate-900 bg-slate-200/50 px-2 py-1 rounded">REF: <?= htmlspecialchars($ref) ?></span>
        <span class="editorial-block__date text-[9px] font-medium text-slate-400 uppercase tracking-widest">Mis à jour le <?= htmlspecialchars($date) ?></span>
    </div>
</div>
