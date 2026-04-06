<?php
declare(strict_types=1);
$docTitle = $docTitle ?? 'Document';
$docBody = $docBody ?? '';
$docKey = $docKey ?? '';
?>
<div class="max-w-5xl mx-auto px-4 sm:px-6 py-8 lg:py-10">
    <nav class="mb-6 text-sm flex flex-wrap gap-x-4 gap-y-1">
        <a href="<?= url('documentation') ?>" class="font-semibold text-sky-700 hover:underline">← Guide du portail</a>
        <a href="<?= url('documentation/references') ?>" class="text-slate-600 hover:underline">Références projet</a>
    </nav>
    <article class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <header class="border-b border-slate-100 px-5 py-4 bg-slate-50/80">
            <h1 class="text-xl font-black text-slate-900"><?= htmlspecialchars($docTitle, ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="mt-1 text-xs text-slate-500">Fiche source pour l’équipe (texte brut).</p>
        </header>
        <div class="p-5 sm:p-6 overflow-x-auto">
            <pre class="text-xs sm:text-sm leading-relaxed text-slate-800 whitespace-pre-wrap font-mono"><?= htmlspecialchars($docBody, ENT_QUOTES, 'UTF-8') ?></pre>
        </div>
    </article>
</div>
