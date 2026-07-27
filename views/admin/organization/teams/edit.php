<?php
$team = $team ?? null;
$parents = $parents ?? [];
$users = $users ?? [];
if (!$team) {
    echo '<p>Équipe introuvable.</p>';
    return;
}
$tid = (int) $team['id'];
$publicTagsLines = '';
$pt = $team['public_tags'] ?? null;
if (is_string($pt) && $pt !== '') {
    $dec = json_decode($pt, true);
    $publicTagsLines = is_array($dec) ? implode("\n", $dec) : $pt;
}
$sop = array_key_exists('show_on_public_page', $team) ? (int) $team['show_on_public_page'] === 1 : true;
?>
<div class="max-w-2xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-6">Modifier l'équipe</h1>
    <form method="post" action="<?= url('back-office/teams/' . $tid . '/update') ?>" class="space-y-4">
        <?= \App\Core\Csrf::field() ?>
        <div>
            <label for="name" class="block text-sm font-medium text-slate-700">Nom *</label>
            <input type="text" id="name" name="name" required class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm" value="<?= htmlspecialchars($team['name'] ?? '') ?>">
        </div>
        <div>
            <label for="slug" class="block text-sm font-medium text-slate-700">Adresse courte dans l’URL</label>
            <input type="text" id="slug" name="slug" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm" value="<?= htmlspecialchars($team['slug'] ?? '') ?>">
        </div>
        <div>
            <label for="code" class="block text-sm font-medium text-slate-700">Code</label>
            <input type="text" id="code" name="code" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm" value="<?= htmlspecialchars($team['code'] ?? '') ?>">
        </div>
        <div>
            <label for="parent_id" class="block text-sm font-medium text-slate-700">Parent</label>
            <select id="parent_id" name="parent_id" class="<?= htmlspecialchars(bo_select_class('mt-1'), ENT_QUOTES, 'UTF-8') ?>">
                <option value="">—</option>
                <?php foreach ($parents as $p): ?>
                <option value="<?= (int) $p['id'] ?>" <?= (int) ($team['parent_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="commander_user_id" class="block text-sm font-medium text-slate-700">Responsable</label>
            <select id="commander_user_id" name="commander_user_id" class="<?= htmlspecialchars(bo_select_class('mt-1'), ENT_QUOTES, 'UTF-8') ?>">
                <option value="">—</option>
                <?php foreach ($users as $u): ?>
                <option value="<?= (int) $u['id'] ?>" <?= (int) ($team['commander_user_id'] ?? 0) === (int) $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['display_name'] ?? $u['email']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="display_order" class="block text-sm font-medium text-slate-700">Ordre</label>
            <input type="number" id="display_order" name="display_order" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm" value="<?= (int) ($team['display_order'] ?? 0) ?>">
        </div>
        <div class="border-t border-slate-200 pt-4 space-y-3">
            <p class="text-xs font-bold text-slate-800">Fiche publique</p>
            <p class="text-xs text-slate-500">Ces informations apparaissent sur la page publique de la communauté et sur la fiche dédiée de l’unité.</p>
            <label class="flex items-start gap-2 text-sm text-slate-700">
                <input type="checkbox" name="show_on_public_page" value="1" class="mt-0.5" <?= $sop ? 'checked' : '' ?>>
                <span>Afficher cette unité sur la page publique de la communauté</span>
            </label>
            <div>
                <label for="public_blurb" class="block text-sm font-medium text-slate-700">Présentation publique</label>
                <p class="mt-0.5 text-xs text-slate-500">Texte court visible sur la vitrine et la fiche de l’unité.</p>
                <textarea id="public_blurb" name="public_blurb" rows="3" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm text-sm"><?= htmlspecialchars((string) ($team['public_blurb'] ?? '')) ?></textarea>
            </div>
            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label for="public_capacity" class="block text-sm font-medium text-slate-700">Effectif max</label>
                    <input type="number" min="0" id="public_capacity" name="public_capacity" value="<?= htmlspecialchars((string) ($team['public_capacity'] ?? '')) ?>" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm text-sm" placeholder="32">
                </div>
                <div>
                    <label for="public_open_slots" class="block text-sm font-medium text-slate-700">Places ouvertes</label>
                    <input type="text" id="public_open_slots" name="public_open_slots" value="<?php
                        $slots = $team['public_open_slots'] ?? null;
                        echo htmlspecialchars($slots === null || $slots === '' ? '' : ((int) $slots === -1 ? 'ouvert' : (string) (int) $slots));
                    ?>" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm text-sm" placeholder="2 ou ouvert">
                    <p class="mt-0.5 text-[11px] text-slate-500">Nombre, « ouvert », ou vide pour masquer.</p>
                </div>
                <div>
                    <label for="public_accent_color" class="block text-sm font-medium text-slate-700">Couleur de bandeau</label>
                    <input type="text" id="public_accent_color" name="public_accent_color" value="<?= htmlspecialchars((string) ($team['public_accent_color'] ?? '')) ?>" maxlength="7" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm text-sm font-mono" placeholder="#0B8A5C">
                </div>
            </div>
            <div>
                <label for="public_tags" class="block text-sm font-medium text-slate-700">Mots-clés affichés (un par ligne)</label>
                <textarea id="public_tags" name="public_tags" rows="3" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm text-sm"><?= htmlspecialchars($publicTagsLines) ?></textarea>
            </div>
        </div>
        <div class="flex gap-3 pt-4">
            <button type="submit" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Enregistrer</button>
            <a href="<?= url('back-office/teams/' . $tid) ?>" class="px-4 py-2 text-slate-600 text-sm hover:underline">Annuler</a>
        </div>
    </form>
</div>
