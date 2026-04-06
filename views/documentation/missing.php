<?php
declare(strict_types=1);
$docTitle = $docTitle ?? 'Document';
?>
<div class="site-docs">
    <div class="site-docs__shell site-docs__shell--refs">
        <a href="<?= htmlspecialchars(url('documentation/references'), ENT_QUOTES, 'UTF-8') ?>" class="site-docs__refs-back">← Références projet</a>
        <div class="site-docs__file-card mt-6">
            <header>
                <h1>Document introuvable</h1>
                <p class="site-docs__file-meta"><?= htmlspecialchars((string) $docTitle, ENT_QUOTES, 'UTF-8') ?></p>
            </header>
            <div class="site-docs__file-body">
                <p class="text-slate-600 m-0 text-sm leading-relaxed">Le fichier de documentation n’a pas été trouvé sur ce serveur.</p>
                <p class="mt-4 flex flex-wrap gap-4 text-sm">
                    <a href="<?= htmlspecialchars(url('documentation'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-sky-700 hover:underline">Guide du portail</a>
                    <a href="<?= htmlspecialchars(url('documentation/references'), ENT_QUOTES, 'UTF-8') ?>" class="text-slate-600 hover:underline">Références projet</a>
                </p>
            </div>
        </div>
    </div>
</div>
