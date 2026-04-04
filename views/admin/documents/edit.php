<?php
$document = $document ?? null;
$versions = $versions ?? [];
$links = $links ?? [];
$collaborators = $collaborators ?? [];
$permissions = $permissions ?? [];
$children = $children ?? [];
$categories = $categories ?? [];
$trainings = $trainings ?? [];
$equipmentClasses = $equipmentClasses ?? [];
$units = $units ?? [];
$users = $users ?? [];
if (!$document) {
    echo '<p>Document non trouvé.</p>';
    return;
}
$linkTraining = null;
$linkEquipment = null;
$linkUnit = null;
$linkUser = null;
foreach ($links as $l) {
    if ($l['entity_type'] === 'training') $linkTraining = (int)$l['entity_id'];
    if ($l['entity_type'] === 'equipment_class') $linkEquipment = (int)$l['entity_id'];
    if ($l['entity_type'] === 'unit') $linkUnit = (int)$l['entity_id'];
    if ($l['entity_type'] === 'user') $linkUser = (int)$l['entity_id'];
}
$parentRelation = $document['parent_document_id'] ?? null;
$documentTypes = ['manuel' => 'Manuel', 'procedure' => 'Procédure', 'note' => 'Note', 'compte_rendu' => 'Compte rendu', 'directive' => 'Directive', 'annexe' => 'Annexe', 'support_formation' => 'Support de formation', 'fiche_equipement' => 'Fiche équipement', 'document_operationnel' => 'Document opérationnel', 'piece_jointe' => 'Pièce jointe'];
$classificationLevels = ['public' => 'Public', 'interne' => 'Interne service', 'restreint' => 'Diffusion restreinte', 'sensible' => 'Donnée sensible', 'confidentiel' => 'Confidentiel opérationnel', 'operationnel' => 'Accès commandement'];
$visibilityScopes = ['private' => 'Privé', 'collaborators' => 'Collaborateurs', 'unit' => 'Unité', 'role' => 'Rôle autorisé', 'organization' => 'Organisation', 'controlled' => 'Publication contrôlée'];
$statuses = ['draft' => 'Brouillon', 'review' => 'En relecture', 'approval' => 'À valider', 'published' => 'Publié', 'suspended' => 'Suspendu', 'archived' => 'Archivé', 'obsolete' => 'Obsolète'];
$relationTypes = ['annexe' => 'Annexe', 'piece_jointe' => 'Pièce jointe', 'reference' => 'Référence', 'support_formation' => 'Support formation', 'procedure_associee' => 'Procédure associée', 'document_lie' => 'Document lié'];
$allDocuments = $allDocuments ?? [];
$docTags = $document['tags'] ?? null;
if (is_string($docTags)) {
    $docTags = json_decode($docTags, true);
}
$tagsStr = is_array($docTags) ? implode(', ', $docTags) : '';
?>
<div class="max-w-6xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-6">Modifier le document</h1>
    <?php if (\App\Core\Session::get('success')): ?>
    <p class="mb-4 text-sm text-emerald-600"><?= htmlspecialchars(\App\Core\Session::get('success')) ?></p>
    <?php \App\Core\Session::forget('success'); endif; ?>
    <?php if (\App\Core\Session::get('error')): ?>
    <p class="mb-4 text-sm text-red-600"><?= htmlspecialchars(\App\Core\Session::get('error')) ?></p>
    <?php \App\Core\Session::forget('error'); endif; ?>

    <form action="<?= url('documents/gestion/' . $document['id'] . '/modifier') ?>" method="post" class="mb-10">
        <?= \App\Core\Csrf::field() ?>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div class="space-y-6">
                <section class="bg-white border border-slate-200 rounded-lg p-4">
                    <h2 class="text-sm font-bold text-slate-800 mb-3">Identité du document</h2>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Titre *</label>
                            <input type="text" name="title" required class="w-full border border-slate-200 rounded px-3 py-2" value="<?= htmlspecialchars($document['title']) ?>" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Slug</label>
                            <input type="text" name="slug" class="w-full border border-slate-200 rounded px-3 py-2" value="<?= htmlspecialchars($document['slug'] ?? '') ?>" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Résumé court</label>
                            <input type="text" name="short_description" maxlength="500" class="w-full border border-slate-200 rounded px-3 py-2" value="<?= htmlspecialchars($document['short_description'] ?? '') ?>" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                            <textarea name="description" rows="3" class="w-full border border-slate-200 rounded px-3 py-2"><?= htmlspecialchars($document['description'] ?? '') ?></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Type de document</label>
                            <select name="document_type" class="w-full border border-slate-200 rounded px-3 py-2">
                                <option value="">—</option>
                                <?php foreach ($documentTypes as $k => $v): ?>
                                <option value="<?= htmlspecialchars($k) ?>" <?= ($document['document_type'] ?? '') === $k ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Catégorie</label>
                            <select name="category" class="w-full border border-slate-200 rounded px-3 py-2">
                                <option value="">—</option>
                                <?php foreach ($categories as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= (isset($document['document_category_id']) && (int)$document['document_category_id'] === (int)$c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Tags</label>
                            <input type="text" name="tags_text" class="w-full border border-slate-200 rounded px-3 py-2" value="<?= htmlspecialchars($tagsStr) ?>" placeholder="tag1, tag2" />
                        </div>
                    </div>
                </section>
            </div>
            <div class="space-y-6">
                <section class="bg-white border border-slate-200 rounded-lg p-4">
                    <h2 class="text-sm font-bold text-slate-800 mb-3">Classification & sécurité</h2>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Niveau de classification</label>
                            <select name="classification_level" class="w-full border border-slate-200 rounded px-3 py-2">
                                <?php foreach ($classificationLevels as $k => $v): ?>
                                <option value="<?= htmlspecialchars($k) ?>" <?= ($document['classification_level'] ?? 'interne') === $k ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Visibilité</label>
                            <select name="visibility_scope" class="w-full border border-slate-200 rounded px-3 py-2">
                                <?php foreach ($visibilityScopes as $k => $v): ?>
                                <option value="<?= htmlspecialchars($k) ?>" <?= ($document['visibility_scope'] ?? 'private') === $k ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="download_allowed" value="1" <?= !empty($document['download_allowed']) ? 'checked' : '' ?> /> Téléchargement autorisé</label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="print_allowed" value="1" <?= !empty($document['print_allowed']) ? 'checked' : '' ?> /> Impression autorisée</label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="locked" value="1" <?= !empty($document['locked']) ? 'checked' : '' ?> /> Document verrouillé</label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="inherit_parent_security" value="1" <?= !empty($document['inherit_parent_security']) ? 'checked' : '' ?> /> Hériter des restrictions du document parent</label>
                    </div>
                </section>
                <section class="bg-white border border-slate-200 rounded-lg p-4">
                    <h2 class="text-sm font-bold text-slate-800 mb-3">Hiérarchie documentaire</h2>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Document parent</label>
                            <select name="parent_document_id" class="w-full border border-slate-200 rounded px-3 py-2 text-sm">
                                <option value="">— Aucun</option>
                                <?php foreach ($allDocuments as $d): if ((int)$d['id'] === (int)$document['id']) continue; ?>
                                <option value="<?= (int)$d['id'] ?>" <?= $parentRelation && (int)$parentRelation === (int)$d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Type de liaison</label>
                            <select name="relation_type" class="w-full border border-slate-200 rounded px-3 py-2 text-sm">
                                <?php foreach ($relationTypes as $k => $v): ?>
                                <option value="<?= htmlspecialchars($k) ?>" <?= ($document['relation_type'] ?? 'document_lie') === $k ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Ordre</label>
                            <input type="number" name="sort_order" value="<?= (int)($document['sort_order'] ?? 0) ?>" min="0" class="w-full border border-slate-200 rounded px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Version / libellé</label>
                            <input type="text" name="version_label" class="w-full border border-slate-200 rounded px-3 py-2 text-sm" value="<?= htmlspecialchars($document['version_label'] ?? '') ?>" />
                        </div>
                    </div>
                </section>
                <section class="bg-white border border-slate-200 rounded-lg p-4">
                    <h2 class="text-sm font-bold text-slate-800 mb-3">Liaisons métier</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Formation</label>
                            <select name="link_training" class="w-full border border-slate-200 rounded px-3 py-2 text-sm">
                                <option value="">—</option>
                                <?php foreach ($trainings as $t): ?>
                                <option value="<?= (int)$t['id'] ?>" <?= $linkTraining === (int)$t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Classe d'équipement</label>
                            <select name="link_equipment_class" class="w-full border border-slate-200 rounded px-3 py-2 text-sm">
                                <option value="">—</option>
                                <?php foreach ($equipmentClasses as $e): ?>
                                <option value="<?= (int)$e['id'] ?>" <?= $linkEquipment === (int)$e['id'] ? 'selected' : '' ?>><?= htmlspecialchars($e['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Unité</label>
                            <select name="link_unit" class="w-full border border-slate-200 rounded px-3 py-2 text-sm">
                                <option value="">—</option>
                                <?php foreach ($units as $u): ?>
                                <option value="<?= (int)$u['id'] ?>" <?= $linkUnit === (int)$u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Opérateur</label>
                            <select name="link_user" class="w-full border border-slate-200 rounded px-3 py-2 text-sm">
                                <option value="">—</option>
                                <?php foreach ($users as $u): ?>
                                <option value="<?= (int)$u['id'] ?>" <?= $linkUser === (int)$u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['display_name'] ?? $u['email']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-slate-600 mb-1">Mission</label>
                            <input type="text" name="mission_id" class="w-full border border-slate-200 rounded px-3 py-2 text-sm" value="<?= htmlspecialchars($document['mission_id'] ?? '') ?>" />
                        </div>
                    </div>
                </section>
                <section class="bg-white border border-slate-200 rounded-lg p-4">
                    <h2 class="text-sm font-bold text-slate-800 mb-3">Collaboration</h2>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Propriétaire</label>
                            <select name="owner_user_id" class="w-full border border-slate-200 rounded px-3 py-2 text-sm">
                                <?php foreach ($users as $u): ?>
                                <option value="<?= (int)$u['id'] ?>" <?= (int)($document['owner_user_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['display_name'] ?? $u['email']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Auteur principal</label>
                            <select name="author_user_id" class="w-full border border-slate-200 rounded px-3 py-2 text-sm">
                                <?php foreach ($users as $u): ?>
                                <option value="<?= (int)$u['id'] ?>" <?= (int)($document['author_user_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['display_name'] ?? $u['email']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if (!empty($collaborators)): ?>
                        <p class="text-xs text-slate-600 font-medium">Collaborateurs actuels</p>
                        <ul class="text-sm text-slate-600 space-y-1">
                            <?php foreach ($collaborators as $col): ?>
                            <li><?= htmlspecialchars($col['role']) ?> — <?= (int)$col['user_id'] ?> (modifier via <a href="<?= url('documents/gestion/' . $document['id'] . '/acces') ?>" class="underline">Gestion des accès</a>)</li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    </div>
                </section>
                <section class="bg-white border border-slate-200 rounded-lg p-4">
                    <h2 class="text-sm font-bold text-slate-800 mb-3">Cycle de vie</h2>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Statut</label>
                            <select name="status" class="w-full border border-slate-200 rounded px-3 py-2">
                                <?php foreach ($statuses as $k => $v): ?>
                                <option value="<?= htmlspecialchars($k) ?>" <?= ($document['status'] ?? 'draft') === $k ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Date d'effet</label>
                            <input type="datetime-local" name="effective_at" class="w-full border border-slate-200 rounded px-3 py-2 text-sm" value="<?= !empty($document['effective_at']) ? date('Y-m-d\TH:i', strtotime($document['effective_at'])) : '' ?>" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Date de révision</label>
                            <input type="datetime-local" name="review_due_at" class="w-full border border-slate-200 rounded px-3 py-2 text-sm" value="<?= !empty($document['review_due_at']) ? date('Y-m-d\TH:i', strtotime($document['review_due_at'])) : '' ?>" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Date d'expiration</label>
                            <input type="datetime-local" name="expires_at" class="w-full border border-slate-200 rounded px-3 py-2 text-sm" value="<?= !empty($document['expires_at']) ? date('Y-m-d\TH:i', strtotime($document['expires_at'])) : '' ?>" />
                        </div>
                    </div>
                </section>
            </div>
        </div>
        <div class="flex gap-3 pt-6 mt-6 border-t border-slate-200">
            <button type="submit" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Enregistrer</button>
            <a href="<?= url('documents/gestion') ?>" class="px-4 py-2 border border-slate-200 text-slate-700 text-sm rounded hover:bg-slate-50">Retour liste</a>
        </div>
    </form>

    <?php if (!empty($children)): ?>
    <section class="border-t border-slate-200 pt-8 mb-8">
        <h2 class="text-lg font-bold text-slate-900 mb-4">Sous-documents</h2>
        <ul class="space-y-2">
            <?php foreach ($children as $ch): ?>
            <li class="flex items-center gap-2 text-sm">
                <span class="text-slate-500"><?= htmlspecialchars($ch['relation_type'] ?? '') ?></span>
                <a href="<?= url('documents/gestion/' . $ch['id'] . '/modifier') ?>" class="text-slate-900 hover:underline"><?= htmlspecialchars($ch['title']) ?></a>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <section class="border-t border-slate-200 pt-8 mb-8">
        <h2 class="text-lg font-bold text-slate-900 mb-4">Versions</h2>
        <?php if (empty($versions)): ?>
        <p class="text-slate-500 text-sm">Aucune version enregistrée.</p>
        <?php else: ?>
        <ul class="space-y-2 mb-6">
            <?php foreach ($versions as $v): ?>
            <li class="flex items-center justify-between py-2 border-b border-slate-100 text-sm">
                <span>Version <?= (int)($v['version_number'] ?? 0) ?> — <?= !empty($v['created_at']) ? date('d.m.Y H:i', strtotime($v['created_at'])) : '' ?> <?= !empty($v['change_notes']) ? ' — ' . htmlspecialchars($v['change_notes']) : '' ?></span>
                <?php if (!empty($v['is_current'])): ?><span class="text-xs font-semibold text-emerald-600">Actuelle</span><?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <form action="<?= url('documents/gestion/' . $document['id'] . '/nouvelle-version') ?>" method="post" enctype="multipart/form-data" class="p-4 bg-slate-50 rounded-lg space-y-3">
            <?= \App\Core\Csrf::field() ?>
            <h3 class="text-sm font-semibold text-slate-700">Upload nouvelle version</h3>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Fichier *</label>
                <input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png,.webp,.mp4,application/pdf,image/jpeg,image/png,image/webp,video/mp4" class="w-full border border-slate-200 rounded px-3 py-2 text-sm" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Notes de version</label>
                <input type="text" name="change_notes" class="w-full border border-slate-200 rounded px-3 py-2 text-sm" placeholder="Optionnel" />
            </div>
            <button type="submit" class="px-3 py-2 bg-slate-800 text-white text-sm font-semibold rounded hover:bg-slate-700">Enregistrer la nouvelle version</button>
        </form>
    </section>

    <p class="mt-8 text-sm text-slate-500">
        <a href="<?= url('documents/gestion/' . $document['id'] . '/historique') ?>" class="underline">Historique / audit</a>
        —
        <a href="<?= url('documents/gestion/' . $document['id'] . '/acces') ?>" class="underline">Gestion des accès</a>
        —
        <a href="<?= url('documents/gestion') ?>" class="underline">Retour liste documents</a>
    </p>
</div>
