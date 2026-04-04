<?php
$categories = $categories ?? [];
$trainings = $trainings ?? [];
$equipmentClasses = $equipmentClasses ?? [];
$units = $units ?? [];
$users = $users ?? [];
$documentTypes = [
    'manuel' => 'Manuel',
    'procedure' => 'Procédure',
    'note' => 'Note',
    'compte_rendu' => 'Compte rendu',
    'directive' => 'Directive',
    'annexe' => 'Annexe',
    'support_formation' => 'Support de formation',
    'fiche_equipement' => 'Fiche équipement',
    'document_operationnel' => 'Document opérationnel',
    'piece_jointe' => 'Pièce jointe',
];
$classificationLevels = [
    'public' => 'Public',
    'interne' => 'Interne service',
    'restreint' => 'Diffusion restreinte',
    'sensible' => 'Donnée sensible',
    'confidentiel' => 'Confidentiel opérationnel',
    'operationnel' => 'Accès commandement',
];
$visibilityScopes = [
    'private' => 'Privé',
    'collaborators' => 'Collaborateurs',
    'unit' => 'Unité',
    'role' => 'Rôle autorisé',
    'organization' => 'Organisation',
    'controlled' => 'Publication contrôlée',
];
$statuses = ['draft' => 'Brouillon', 'review' => 'En relecture', 'approval' => 'À valider', 'published' => 'Publié', 'suspended' => 'Suspendu', 'archived' => 'Archivé', 'obsolete' => 'Obsolète'];
$collaboratorRoles = ['owner' => 'Propriétaire', 'author' => 'Auteur', 'editor' => 'Éditeur', 'reviewer' => 'Relecteur', 'approver' => 'Validateur', 'reader' => 'Lecteur'];
$relationTypes = ['annexe' => 'Annexe', 'piece_jointe' => 'Pièce jointe', 'reference' => 'Référence', 'support_formation' => 'Support formation', 'procedure_associee' => 'Procédure associée', 'document_lie' => 'Document lié'];
$currentUserId = (int)($currentUserId ?? 0);
?>
<div class="max-w-6xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-6">Nouveau document</h1>
    <?php if (\App\Core\Session::get('error')): ?>
    <p class="mb-4 text-sm text-red-600"><?= htmlspecialchars(\App\Core\Session::get('error')) ?></p>
    <?php \App\Core\Session::forget('error'); endif; ?>
    <form action="<?= url('documents/gestion/ajout') ?>" method="post" enctype="multipart/form-data" id="document-upload-form">
        <?= \App\Core\Csrf::field() ?>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Colonne gauche -->
            <div class="space-y-6">
                <section class="bg-white border border-slate-200 rounded-lg p-4">
                    <h2 class="text-sm font-bold text-slate-800 mb-3">Identité du document</h2>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Titre *</label>
                            <input type="text" name="title" required class="w-full border border-slate-200 rounded px-3 py-2" id="doc-title" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Slug (optionnel)</label>
                            <input type="text" name="slug" class="w-full border border-slate-200 rounded px-3 py-2" placeholder="Auto depuis le titre si vide" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Résumé court</label>
                            <input type="text" name="short_description" maxlength="500" class="w-full border border-slate-200 rounded px-3 py-2" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Description détaillée</label>
                            <textarea name="description" rows="3" class="w-full border border-slate-200 rounded px-3 py-2"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Type de document</label>
                            <select name="document_type" class="w-full border border-slate-200 rounded px-3 py-2">
                                <option value="">—</option>
                                <?php foreach ($documentTypes as $k => $v): ?>
                                <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($v) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Catégorie</label>
                            <select name="category" class="w-full border border-slate-200 rounded px-3 py-2">
                                <option value="">—</option>
                                <?php foreach ($categories as $c): ?>
                                <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Tags (séparés par des virgules)</label>
                            <input type="text" name="tags_text" class="w-full border border-slate-200 rounded px-3 py-2" placeholder="tag1, tag2" />
                        </div>
                    </div>
                </section>

                <section class="bg-white border border-slate-200 rounded-lg p-4">
                    <h2 class="text-sm font-bold text-slate-800 mb-3">Fichier / contenu</h2>
                    <div class="space-y-3">
                        <label class="flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" name="document_without_file" value="1" id="doc-without-file" />
                            Document sans fichier (fiche métier uniquement)
                        </label>
                        <div id="file-zone">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Fichier (PDF, images, vidéo — max 10 Mo)</label>
                            <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,.webp,.mp4,application/pdf,image/jpeg,image/png,image/webp,video/mp4" class="w-full border border-slate-200 rounded px-3 py-2 border-dashed" />
                        </div>
                    </div>
                </section>
            </div>

            <!-- Colonne droite -->
            <div class="space-y-6">
                <section class="bg-white border border-slate-200 rounded-lg p-4">
                    <h2 class="text-sm font-bold text-slate-800 mb-3">Classification & sécurité</h2>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Niveau de classification</label>
                            <select name="classification_level" class="w-full border border-slate-200 rounded px-3 py-2">
                                <?php foreach ($classificationLevels as $k => $v): ?>
                                <option value="<?= htmlspecialchars($k) ?>" <?= $k === 'interne' ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Visibilité</label>
                            <select name="visibility_scope" class="w-full border border-slate-200 rounded px-3 py-2">
                                <?php foreach ($visibilityScopes as $k => $v): ?>
                                <option value="<?= htmlspecialchars($k) ?>" <?= $k === 'private' ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <p class="text-xs text-slate-500">Unité autorisée : voir « Liaisons métier » ci-dessous.</p>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="download_allowed" value="1" checked /> Téléchargement autorisé</label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="print_allowed" value="1" checked /> Impression autorisée</label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="locked" value="1" /> Document verrouillé</label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="inherit_parent_security" value="1" /> Hériter des restrictions du document parent</label>
                    </div>
                </section>

                <section class="bg-white border border-slate-200 rounded-lg p-4">
                    <h2 class="text-sm font-bold text-slate-800 mb-3">Hiérarchie documentaire</h2>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Document parent</label>
                            <select name="parent_document_id" class="w-full border border-slate-200 rounded px-3 py-2 text-sm">
                                <option value="">— Aucun</option>
                                <?php
                                $allDocs = $allDocuments ?? [];
                                foreach ($allDocs as $d):
                                ?>
                                <option value="<?= (int)$d['id'] ?>"><?= htmlspecialchars($d['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Type de liaison</label>
                            <select name="relation_type" class="w-full border border-slate-200 rounded px-3 py-2 text-sm">
                                <?php foreach ($relationTypes as $k => $v): ?>
                                <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($v) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Ordre d'affichage</label>
                            <input type="number" name="sort_order" value="0" min="0" class="w-full border border-slate-200 rounded px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Version / libellé</label>
                            <input type="text" name="version_label" class="w-full border border-slate-200 rounded px-3 py-2 text-sm" placeholder="ex. 1.0" />
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
                                <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Classe d'équipement</label>
                            <select name="link_equipment_class" class="w-full border border-slate-200 rounded px-3 py-2 text-sm">
                                <option value="">—</option>
                                <?php foreach ($equipmentClasses as $e): ?>
                                <option value="<?= (int)$e['id'] ?>"><?= htmlspecialchars($e['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Unité</label>
                            <select name="link_unit" class="w-full border border-slate-200 rounded px-3 py-2 text-sm">
                                <option value="">—</option>
                                <?php foreach ($units as $u): ?>
                                <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Opérateur</label>
                            <select name="link_user" class="w-full border border-slate-200 rounded px-3 py-2 text-sm">
                                <option value="">—</option>
                                <?php foreach ($users as $u): ?>
                                <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['display_name'] ?? $u['email']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-slate-600 mb-1">Mission (référence)</label>
                            <input type="text" name="mission_id" class="w-full border border-slate-200 rounded px-3 py-2 text-sm" placeholder="ex. op_tanoa_07" />
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
                                <option value="<?= (int)$u['id'] ?>" <?= (int)$u['id'] === $currentUserId ? 'selected' : '' ?>><?= htmlspecialchars($u['display_name'] ?? $u['email']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Auteur principal</label>
                            <select name="author_user_id" class="w-full border border-slate-200 rounded px-3 py-2 text-sm">
                                <?php foreach ($users as $u): ?>
                                <option value="<?= (int)$u['id'] ?>" <?= (int)$u['id'] === $currentUserId ? 'selected' : '' ?>><?= htmlspecialchars($u['display_name'] ?? $u['email']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <p class="text-xs text-slate-500">Collaborateurs supplémentaires : à gérer après création du document (onglet Modifier).</p>
                    </div>
                </section>

                <section class="bg-white border border-slate-200 rounded-lg p-4">
                    <h2 class="text-sm font-bold text-slate-800 mb-3">Cycle de vie</h2>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Statut</label>
                            <select name="status" class="w-full border border-slate-200 rounded px-3 py-2">
                                <?php foreach ($statuses as $k => $v): ?>
                                <option value="<?= htmlspecialchars($k) ?>" <?= $k === 'draft' ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Date d'effet</label>
                            <input type="datetime-local" name="effective_at" class="w-full border border-slate-200 rounded px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Date de révision</label>
                            <input type="datetime-local" name="review_due_at" class="w-full border border-slate-200 rounded px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Date d'expiration</label>
                            <input type="datetime-local" name="expires_at" class="w-full border border-slate-200 rounded px-3 py-2 text-sm" />
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <div class="flex gap-3 pt-6 mt-6 border-t border-slate-200">
            <button type="submit" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Créer le document</button>
            <a href="<?= url('documents/gestion') ?>" class="px-4 py-2 border border-slate-200 text-slate-700 text-sm rounded hover:bg-slate-50">Annuler</a>
        </div>
    </form>
</div>
<script>
document.getElementById('doc-without-file').addEventListener('change', function() {
    var zone = document.getElementById('file-zone');
    zone.style.display = this.checked ? 'none' : 'block';
    var fileInput = zone.querySelector('input[type="file"]');
    if (fileInput) fileInput.removeAttribute('required');
});
</script>
