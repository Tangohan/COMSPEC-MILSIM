<?php
declare(strict_types=1);
$docTitle = $docTitle ?? 'Document';
$docBody = $docBody ?? '';
?>
<div class="site-docs">
    <div class="site-docs__shell site-docs__shell--refs">
        <nav class="site-docs__refs-nav mb-4 text-sm" aria-label="Navigation documentation">
            <a href="<?= htmlspecialchars(url('documentation'), ENT_QUOTES, 'UTF-8') ?>" class="site-docs__refs-back">← Guide du portail</a>
            <a href="<?= htmlspecialchars(url('documentation/references'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-slate-600 underline decoration-slate-300 underline-offset-2 hover:text-sky-800">Références projet</a>
        </nav>

        <article class="site-docs__file-card">
            <header>
                <h1><?= htmlspecialchars($docTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="site-docs__file-meta">Fiche source pour l’équipe (texte brut).</p>
            </header>
            <div class="site-docs__file-body">
                <pre><?= htmlspecialchars($docBody, ENT_QUOTES, 'UTF-8') ?></pre>
            </div>
        </article>
    </div>
</div>
