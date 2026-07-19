<?php $parents = $parents ?? []; $users = $users ?? []; ?>
<div class="max-w-2xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-6">Nouveau groupe</h1>
    <form method="post" action="<?= url('back-office/groups/store') ?>" class="space-y-4">
        <?= \App\Core\Csrf::field() ?>
        <div>
            <label for="name" class="block text-sm font-medium text-slate-700">Nom *</label>
            <input type="text" id="name" name="name" required class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm">
        </div>
        <div>
            <label for="slug" class="block text-sm font-medium text-slate-700">Adresse courte dans l’URL</label>
            <input type="text" id="slug" name="slug" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm">
        </div>
        <div>
            <label for="code" class="block text-sm font-medium text-slate-700">Code</label>
            <input type="text" id="code" name="code" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm">
        </div>
        <div>
            <label for="parent_id" class="block text-sm font-medium text-slate-700">Parent</label>
            <select id="parent_id" name="parent_id" class="<?= htmlspecialchars(bo_select_class('mt-1'), ENT_QUOTES, 'UTF-8') ?>">
                <option value="">—</option>
                <?php foreach ($parents as $p): ?>
                <option value="<?= (int) $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="commander_user_id" class="block text-sm font-medium text-slate-700">Responsable</label>
            <select id="commander_user_id" name="commander_user_id" class="<?= htmlspecialchars(bo_select_class('mt-1'), ENT_QUOTES, 'UTF-8') ?>">
                <option value="">—</option>
                <?php foreach ($users as $u): ?>
                <option value="<?= (int) $u['id'] ?>"><?= htmlspecialchars($u['display_name'] ?? $u['email']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="display_order" class="block text-sm font-medium text-slate-700">Ordre</label>
            <input type="number" id="display_order" name="display_order" value="0" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm">
        </div>
        <div class="border-t border-slate-200 pt-4 space-y-3">
            <p class="text-xs font-bold text-slate-800">Fiche publique</p>
            <p class="text-xs text-slate-500">Ces informations apparaissent sur la page publique de la communauté et sur la fiche dédiée de l’unité.</p>
            <label class="flex items-start gap-2 text-sm text-slate-700">
                <input type="checkbox" name="show_on_public_page" value="1" class="mt-0.5" checked>
                <span>Afficher cette unité sur la page publique de la communauté</span>
            </label>
            <div>
                <label for="public_blurb" class="block text-sm font-medium text-slate-700">Présentation publique</label>
                <p class="mt-0.5 text-xs text-slate-500">Texte court visible sur la vitrine et la fiche de l’unité.</p>
                <textarea id="public_blurb" name="public_blurb" rows="3" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm text-sm"></textarea>
            </div>
            <div>
                <label for="public_tags" class="block text-sm font-medium text-slate-700">Mots-clés affichés (un par ligne)</label>
                <textarea id="public_tags" name="public_tags" rows="3" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm text-sm"></textarea>
            </div>
        </div>
        <div class="flex gap-3 pt-4">
            <button type="submit" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Créer</button>
            <a href="<?= url('back-office/groups') ?>" class="px-4 py-2 text-slate-600 text-sm hover:underline">Annuler</a>
        </div>
    </form>
</div>
