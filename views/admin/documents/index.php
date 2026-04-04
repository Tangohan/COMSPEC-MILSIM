<?php
$documents = $documents ?? [];
$users = $users ?? [];
$categories = $categories ?? [];
$filters = $filters ?? ['category' => null, 'status' => null, 'q' => '', 'document_type' => '', 'classification_level' => ''];
$statuses = ['draft' => 'Brouillon', 'review' => 'En relecture', 'approval' => 'À valider', 'published' => 'Publié', 'suspended' => 'Suspendu', 'archived' => 'Archivé', 'obsolete' => 'Obsolète'];
$documentTypes = ['manuel' => 'Manuel', 'procedure' => 'Procédure', 'note' => 'Note', 'annexe' => 'Annexe', 'support_formation' => 'Support formation', 'fiche_equipement' => 'Fiche équipement', 'document_operationnel' => 'Document opérationnel', 'piece_jointe' => 'Pièce jointe'];
$classificationLevels = ['public' => 'Public', 'interne' => 'Interne', 'restreint' => 'Restreint', 'sensible' => 'Sensible', 'confidentiel' => 'Confidentiel', 'operationnel' => 'Opérationnel'];
?>
<div class="max-w-5xl mx-auto px-6 py-12">
    <div class="flex items-center justify-between gap-4 mb-6">
        <h1 class="text-2xl font-black text-slate-900">Documents</h1>
        <div class="flex gap-2">
            <a href="<?= url('documents/gestion/arborescence') ?>" class="px-4 py-2 border border-slate-200 text-slate-700 text-sm font-medium rounded hover:bg-slate-50">Arborescence</a>
            <a href="<?= url('documents/gestion/ajout') ?>" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Upload document</a>
        </div>
    </div>
    <?php if (\App\Core\Session::get('success')): ?>
    <p class="mb-4 text-sm text-emerald-600"><?= htmlspecialchars((string)\App\Core\Session::get('success')) ?></p>
    <?php \App\Core\Session::forget('success'); endif; ?>
    <?php if (\App\Core\Session::get('error')): ?>
    <p class="mb-4 text-sm text-red-600"><?= htmlspecialchars((string)\App\Core\Session::get('error')) ?></p>
    <?php \App\Core\Session::forget('error'); endif; ?>

    <form method="get" action="<?= url('documents/gestion') ?>" class="mb-6 p-4 bg-slate-50 rounded-lg border border-slate-200 flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Recherche</label>
            <input type="text" name="q" value="<?= htmlspecialchars((string)($filters['q'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="border border-slate-200 rounded px-3 py-2 text-sm w-48" placeholder="Titre, description" />
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Catégorie</label>
            <select name="category" class="border border-slate-200 rounded px-3 py-2 text-sm w-40">
                <option value="">Toutes</option>
                <?php foreach ($categories as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= (int)($filters['category'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)($c['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Statut</label>
            <select name="status" class="border border-slate-200 rounded px-3 py-2 text-sm w-36">
                <option value="">Tous</option>
                <?php foreach ($statuses as $k => $v): ?>
                <option value="<?= htmlspecialchars($k) ?>" <?= ($filters['status'] ?? '') === $k ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Type</label>
            <select name="document_type" class="border border-slate-200 rounded px-3 py-2 text-sm w-40">
                <option value="">Tous</option>
                <?php foreach ($documentTypes as $k => $v): ?>
                <option value="<?= htmlspecialchars($k) ?>" <?= ($filters['document_type'] ?? '') === $k ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Classification</label>
            <select name="classification_level" class="border border-slate-200 rounded px-3 py-2 text-sm w-36">
                <option value="">Toutes</option>
                <?php foreach ($classificationLevels as $k => $v): ?>
                <option value="<?= htmlspecialchars($k) ?>" <?= ($filters['classification_level'] ?? '') === $k ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="px-3 py-2 bg-slate-700 text-white text-sm font-medium rounded hover:bg-slate-600">Filtrer</button>
        <a href="<?= url('documents/gestion') ?>" class="px-3 py-2 border border-slate-200 text-slate-700 text-sm rounded hover:bg-slate-100">Réinitialiser</a>
    </form>

    <?php if (empty($documents)): ?>
    <p class="text-slate-500">Aucun document. <a href="<?= url('documents/gestion/ajout') ?>" class="underline">Uploader un document</a>.</p>
    <?php else: ?>
    <table class="w-full border border-slate-200 rounded-lg overflow-hidden">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Titre</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Catégorie</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Version</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Statut</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Auteur</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Date</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($documents as $d): ?>
            <tr class="border-b border-slate-100 hover:bg-slate-50">
                <td class="p-3 font-medium"><?= htmlspecialchars((string)($d['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td class="p-3"><?= htmlspecialchars($d['category_name'] ?? '—') ?></td>
                <td class="p-3"><?= isset($d['version_number']) ? 'v' . (int)$d['version_number'] : '—' ?></td>
                <td class="p-3">
                    <span class="px-2 py-0.5 text-xs rounded <?= $d['status'] === 'published' ? 'bg-emerald-100 text-emerald-800' : ($d['status'] === 'archived' ? 'bg-slate-200 text-slate-600' : 'bg-amber-100 text-amber-800') ?>"><?= htmlspecialchars((string)($d['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                </td>
                <td class="p-3"><?= htmlspecialchars((string)($users[$d['owner_user_id'] ?? $d['created_by'] ?? 0] ?? '#' . ($d['owner_user_id'] ?? $d['created_by'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                <td class="p-3"><?= !empty($d['updated_at']) ? date('d.m.Y', strtotime($d['updated_at'])) : (!empty($d['created_at']) ? date('d.m.Y', strtotime($d['created_at'])) : '—') ?></td>
                <td class="p-3">
                    <a href="<?= url('documents/gestion/' . $d['id']) ?>" class="text-slate-600 hover:text-slate-900 text-sm underline">Détail</a>
                    <?php if ($d['status'] === 'published'): ?>
                    <a href="<?= url('documents/' . htmlspecialchars((string)($d['slug'] ?? ''), ENT_QUOTES, 'UTF-8')) ?>" class="text-slate-600 hover:text-slate-900 text-sm underline ml-2">Voir (public)</a>
                    <?php endif; ?>
                    <a href="<?= url('documents/gestion/' . $d['id'] . '/modifier') ?>" class="text-slate-600 hover:text-slate-900 text-sm underline ml-2">Modifier</a>
                    <?php if ($d['status'] !== 'archived' && \App\Core\Gate::getInstance()->allows('documents.archive')): ?>
                    <form action="<?= url('documents/gestion/' . $d['id'] . '/archiver') ?>" method="post" class="inline ml-2" onsubmit="return confirm('Archiver ce document ?');">
                        <?= \App\Core\Csrf::field() ?>
                        <button type="submit" class="text-amber-600 hover:text-amber-800 text-sm underline">Archiver</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <p class="mt-6 text-sm text-slate-500"><a href="<?= url('admin') ?>" class="underline">Retour administration</a></p>
</div>
