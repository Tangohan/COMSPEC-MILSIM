<?php $st = (string) ($status ?? 'draft');
$map=['draft'=>'bg-slate-100 text-slate-700','review'=>'bg-amber-100 text-amber-800','scheduled'=>'bg-indigo-100 text-indigo-800','published'=>'bg-emerald-100 text-emerald-800','archived'=>'bg-rose-100 text-rose-800']; ?>
<span class="inline-flex items-center rounded-full px-2 py-1 text-[11px] font-bold <?= $map[$st] ?? $map['draft'] ?>"><?= htmlspecialchars($st) ?></span>
