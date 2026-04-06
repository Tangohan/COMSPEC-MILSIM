<?php
declare(strict_types=1);

$role = $role ?? null;
$badgeStyle = is_array($badgeStyle ?? null) ? $badgeStyle : [];
if (!$role) {
    echo '<p>Rôle introuvable.</p>';
    return;
}
$rid = (int) $role['id'];
$slug = (string) ($role['slug'] ?? '');
$critical = !empty($role['is_system_critical']);
$readonlyName = $critical || $slug === 'community_owner';
$tier = (string) ($role['semantic_tier'] ?? 'function');
$tierLabel = match ($tier) {
    'authority' => 'Autorité (pouvoir effectif)',
    'status' => 'Statut affiché',
    default => 'Fonction opérationnelle',
};
$colors = ['slate' => 'Gris ardoise', 'blue' => 'Bleu', 'indigo' => 'Indigo', 'emerald' => 'Vert', 'amber' => 'Ambre', 'red' => 'Rouge', 'purple' => 'Violet'];
$icons = ['none' => 'Aucune', 'shield' => 'Bouclier', 'star' => 'Étoile', 'user' => 'Personne', 'flag' => 'Drapeau', 'briefcase' => 'Mallette', 'award' => 'Distinction', 'megaphone' => 'Annonce'];
$variants = ['soft' => 'Discret', 'solid' => 'Plein', 'outline' => 'Contour'];
$curColor = (string) ($badgeStyle['color'] ?? '');
$curIcon = (string) ($badgeStyle['icon'] ?? '');
$curVariant = (string) ($badgeStyle['variant'] ?? '');
?>
<div class="max-w-2xl mx-auto px-6 py-12">
    <div class="mb-6 flex items-center justify-between gap-4">
        <h1 class="text-2xl font-black text-slate-900">Présentation du rôle</h1>
        <a href="<?= url('back-office/roles/' . $rid) ?>" class="text-sm font-medium text-slate-600 hover:underline">← Fiche</a>
    </div>
    <p class="text-sm text-slate-600 mb-6">L’intitulé et la description sont visibles par les membres. La référence courte du rôle n’est pas modifiable ici.</p>

    <form method="post" action="<?= url('back-office/roles/' . $rid . '/edit-presentation') ?>" class="space-y-6">
        <?= \App\Core\Csrf::field() ?>

        <div class="rounded-lg border border-slate-200 bg-slate-50/80 p-4 text-sm">
            <p class="text-slate-600"><span class="font-semibold text-slate-800">Type d’usage :</span> <?= htmlspecialchars($tierLabel) ?></p>
            <?php if (!empty($role['is_visual_only'])): ?>
            <p class="mt-2 text-amber-800">Ce rôle est prévu pour l’affichage (reconnaissance, statut) : il ne doit pas porter d’habilitations effectives.</p>
            <?php endif; ?>
        </div>

        <div>
            <label for="role_name" class="block text-sm font-medium text-slate-700">Intitulé affiché</label>
            <?php if ($readonlyName): ?>
            <input type="hidden" name="name" value="<?= htmlspecialchars((string) ($role['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <p class="mt-1 text-sm text-slate-900 font-medium"><?= htmlspecialchars((string) ($role['name'] ?? '')) ?></p>
            <p class="mt-1 text-xs text-slate-500">Cet intitulé est fixé pour préserver la cohérence institutionnelle ou la sécurité de la plateforme.</p>
            <?php else: ?>
            <input type="text" id="role_name" name="name" required maxlength="160" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm" value="<?= htmlspecialchars((string) ($role['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>
        </div>

        <div>
            <label for="role_description" class="block text-sm font-medium text-slate-700">Description</label>
            <textarea id="role_description" name="description" rows="4" maxlength="500" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm"><?= htmlspecialchars((string) ($role['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>

        <fieldset class="rounded-lg border border-slate-200 p-4 space-y-4">
            <legend class="text-sm font-semibold text-slate-800 px-1">Style du badge (optionnel)</legend>
            <div>
                <label for="badge_color" class="block text-xs font-medium text-slate-600">Couleur</label>
                <select id="badge_color" name="badge_color" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md text-sm">
                    <option value="">— Par défaut —</option>
                    <?php foreach ($colors as $val => $lab): ?>
                    <option value="<?= htmlspecialchars($val) ?>" <?= $curColor === $val ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="badge_icon" class="block text-xs font-medium text-slate-600">Icône</label>
                <select id="badge_icon" name="badge_icon" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md text-sm">
                    <?php foreach ($icons as $val => $lab): ?>
                    <option value="<?= htmlspecialchars($val) ?>" <?= ($curIcon === $val || ($curIcon === '' && $val === 'none')) ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="badge_variant" class="block text-xs font-medium text-slate-600">Style graphique</label>
                <select id="badge_variant" name="badge_variant" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md text-sm">
                    <option value="">— Par défaut —</option>
                    <?php foreach ($variants as $val => $lab): ?>
                    <option value="<?= htmlspecialchars($val) ?>" <?= $curVariant === $val ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </fieldset>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Enregistrer</button>
            <a href="<?= url('back-office/roles/' . $rid) ?>" class="px-4 py-2 text-slate-600 text-sm hover:underline">Annuler</a>
        </div>
    </form>
</div>
