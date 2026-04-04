<?php
$document = $document ?? null;
$collaborators = $collaborators ?? [];
$permissions = $permissions ?? [];
$users = $users ?? [];
$roles = $roles ?? [];
$units = $units ?? [];
if (!$document) {
    echo '<p>Document non trouvé.</p>';
    return;
}
$docId = (int)$document['id'];
$collaboratorRoles = ['owner' => 'Propriétaire', 'author' => 'Auteur', 'editor' => 'Éditeur', 'reviewer' => 'Relecteur', 'approver' => 'Validateur', 'reader' => 'Lecteur'];
$permissionTypes = ['role' => 'Rôle', 'unit' => 'Unité', 'user' => 'Utilisateur', 'group' => 'Groupe'];
$accessLevels = ['read' => 'Lecture', 'comment' => 'Commenter', 'edit' => 'Modifier', 'approve' => 'Valider', 'manage' => 'Gérer'];
?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <div class="mb-6">
        <a href="<?= url('documents/gestion/' . $docId . '/modifier') ?>" class="text-sm text-slate-500 hover:text-slate-900">← Retour au document</a>
        <h1 class="text-2xl font-black text-slate-900 mt-2">Gestion des accès — <?= htmlspecialchars($document['title']) ?></h1>
    </div>

    <div class="space-y-8">
        <section class="bg-white border border-slate-200 rounded-lg p-4">
            <h2 class="text-sm font-bold text-slate-800 mb-3">Collaborateurs actuels</h2>
            <?php if (empty($collaborators)): ?>
            <p class="text-slate-500 text-sm">Aucun collaborateur explicite (le propriétaire et l'auteur du document ont tous les droits).</p>
            <?php else: ?>
            <ul class="space-y-2 text-sm">
                <?php foreach ($collaborators as $c): ?>
                <li class="flex items-center justify-between">
                    <span><?= htmlspecialchars($collaboratorRoles[$c['role']] ?? $c['role']) ?> — utilisateur #<?= (int)$c['user_id'] ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <p class="text-slate-500 text-xs mt-2">Pour ajouter ou retirer des collaborateurs, utilisez la section « Collaboration » dans la page d'édition du document (propriétaire, auteur) ou des permissions ciblées ci-dessous.</p>
        </section>

        <section class="bg-white border border-slate-200 rounded-lg p-4">
            <h2 class="text-sm font-bold text-slate-800 mb-3">Permissions ciblées</h2>
            <?php if (empty($permissions)): ?>
            <p class="text-slate-500 text-sm">Aucune permission explicite (accès selon visibilité et classification).</p>
            <?php else: ?>
            <ul class="space-y-2 text-sm">
                <?php foreach ($permissions as $p): ?>
                <li>
                    <?= htmlspecialchars($permissionTypes[$p['permission_type']] ?? $p['permission_type']) ?>
                    = <?= htmlspecialchars($p['permission_value']) ?>
                    — <?= htmlspecialchars($accessLevels[$p['access_level']] ?? $p['access_level']) ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <p class="text-slate-500 text-xs mt-2">Les permissions ciblées (rôle, unité, utilisateur, groupe) se gèrent via l'API ou un formulaire dédié à venir. Pour l'instant, la visibilité du document et les collaborateurs (propriétaire, auteur, etc.) définissent l'accès.</p>
        </section>

        <section class="bg-white border border-slate-200 rounded-lg p-4">
            <h2 class="text-sm font-bold text-slate-800 mb-2">Résumé</h2>
            <p class="text-sm text-slate-600">
                Niveau de classification : <strong><?= htmlspecialchars($document['classification_level'] ?? 'interne') ?></strong>.
                Visibilité : <strong><?= htmlspecialchars($document['visibility_scope'] ?? 'private') ?></strong>.
                L'accès est déterminé par le service DocumentAccessService (admin, propriétaire, collaborateurs, classification, permissions explicites, visibilité et statut).
            </p>
        </section>
    </div>
</div>
