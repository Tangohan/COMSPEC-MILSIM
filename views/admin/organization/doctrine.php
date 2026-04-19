<?php
/** @var list<array<string, mixed>> $doctrine_documents */
$doctrine_documents = $doctrine_documents ?? [];
?>
<div class="mx-auto max-w-6xl space-y-6 px-4 py-8">
    <header class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="text-2xl font-black text-slate-900">Doctrine & SOP versionnées</h1>
        <p class="mt-2 text-sm text-slate-600">Publiez les SOP officielles, activez une version avec date d’effet et suivez les accusés de lecture par version.</p>
    </header>

    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Nouveau document doctrine</h2>
        <form method="post" action="<?= htmlspecialchars(url('back-office/doctrine'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 space-y-4">
            <?= \App\Core\Csrf::field() ?>
            <div class="grid gap-4 md:grid-cols-3">
                <label class="block text-sm text-slate-700">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Titre</span>
                    <input type="text" name="title" maxlength="180" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                </label>
                <label class="block text-sm text-slate-700">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Type</span>
                    <select name="document_type" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                        <option value="sop">SOP</option>
                        <option value="checklist">Checklist</option>
                        <option value="report_format">Format de rapport</option>
                    </select>
                </label>
                <label class="block text-sm text-slate-700">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Date d’effet</span>
                    <input type="date" name="effective_at" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                </label>
            </div>
            <div class="grid gap-4 md:grid-cols-4">
                <label class="block text-sm text-slate-700 md:col-span-1">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Version</span>
                    <input type="text" name="version_label" value="1.0.0" maxlength="20" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                </label>
                <label class="block text-sm text-slate-700 md:col-span-3">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Contenu (Markdown)</span>
                    <textarea name="content_markdown" rows="7" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" placeholder="# Objet&#10;## Procédure&#10;- Étape 1&#10;- Étape 2"></textarea>
                </label>
            </div>
            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-xs font-black uppercase tracking-wider text-white hover:bg-slate-800">Créer le document</button>
        </form>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Versions publiées</h2>
        <?php if ($doctrine_documents === []): ?>
            <p class="mt-3 text-sm text-slate-500">Aucun document doctrine pour le moment.</p>
        <?php else: ?>
            <div class="mt-4 space-y-4">
                <?php foreach ($doctrine_documents as $document): ?>
                    <?php $versions = is_array($document['versions'] ?? null) ? $document['versions'] : []; ?>
                    <article class="rounded-lg border border-slate-200 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="font-bold text-slate-900"><?= htmlspecialchars((string) ($document['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                <p class="text-xs uppercase tracking-wider text-slate-500"><?= htmlspecialchars((string) ($document['document_type'] ?? 'sop'), ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        </div>
                        <?php if ($versions === []): ?>
                            <p class="mt-2 text-sm text-slate-500">Aucune version disponible.</p>
                        <?php else: ?>
                            <ul class="mt-3 space-y-2">
                                <?php foreach ($versions as $version): ?>
                                    <?php $isActive = (int) ($document['current_version_id'] ?? 0) === (int) ($version['id'] ?? 0); ?>
                                    <li class="flex flex-wrap items-center justify-between gap-3 rounded border border-slate-200 bg-slate-50 px-3 py-2">
                                        <div class="text-sm text-slate-700">
                                            <strong><?= htmlspecialchars((string) ($version['version_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                                            · statut <?= htmlspecialchars((string) ($version['status'] ?? 'draft'), ENT_QUOTES, 'UTF-8') ?>
                                            · ACK <?= (int) ($version['ack_count'] ?? 0) ?>
                                            <?php if (!empty($version['effective_at'])): ?>
                                                · effet <?= htmlspecialchars((string) $version['effective_at'], ENT_QUOTES, 'UTF-8') ?>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!$isActive): ?>
                                            <form method="post" action="<?= htmlspecialchars(url('back-office/doctrine/versions/' . (int) ($version['id'] ?? 0) . '/activate'), ENT_QUOTES, 'UTF-8') ?>">
                                                <?= \App\Core\Csrf::field() ?>
                                                <button type="submit" class="rounded-lg border border-emerald-300 px-3 py-1 text-xs font-bold uppercase tracking-wider text-emerald-700 hover:bg-emerald-50">Activer</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="rounded-full bg-emerald-100 px-2 py-1 text-[11px] font-bold text-emerald-700">Active</span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
