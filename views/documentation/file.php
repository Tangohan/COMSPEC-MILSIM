<?php
declare(strict_types=1);
$docTitle = $docTitle ?? 'Document';
$docBody = $docBody ?? '';
$docKey = $docKey ?? '';
?>
<div class="site-docs">
    <div class="site-docs__shell site-docs__shell--refs">
        <nav class="site-docs__refs-nav mb-4 text-sm" aria-label="Navigation documentation">
            <a href="<?= htmlspecialchars(url('documentation'), ENT_QUOTES, 'UTF-8') ?>" class="site-docs__refs-back">← Guide du portail</a>
            <a href="<?= htmlspecialchars(url('documentation/references'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-slate-600 underline decoration-slate-300 underline-offset-2 hover:text-sky-800">Références projet</a>
        </nav>

        <article class="site-docs__file-card">
            <header>
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div>
                        <h1><?= htmlspecialchars($docTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
                        <p class="site-docs__file-meta">Fiche source pour l’équipe (texte brut).</p>
                    </div>
                    <?php if (\App\Core\Session::get('user_id') && $docKey !== ''): ?>
                    <button type="button" data-community-report data-cr-type="help_page" data-cr-id="0" data-cr-doc-key="<?= htmlspecialchars($docKey, ENT_QUOTES, 'UTF-8') ?>" data-cr-summary="Signalement concernant cette page d’aide du portail." class="shrink-0 rounded-xl border border-rose-200 bg-rose-50 px-4 py-2 text-[10px] font-black uppercase tracking-wide text-rose-900 hover:bg-rose-100">Signaler cette page</button>
                    <?php endif; ?>
                </div>
            </header>
            <div class="site-docs__file-body">
                <pre><?= htmlspecialchars($docBody, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></pre>
            </div>
        </article>
    </div>
</div>
