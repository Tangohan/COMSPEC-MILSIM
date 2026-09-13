<?php
$definition = $definition ?? null;
$categories = $categories ?? [];
$types = $types ?? [];
$templates = $templates ?? [];
$levels = $levels ?? [];
$prerequisites = $prerequisites ?? [];
$customFields = $customFields ?? [];
$permissionGrants = $permissionGrants ?? [];
$holders = $holders ?? [];
$allDefinitions = $allDefinitions ?? [];
$badgeUrl = $badgeUrl ?? url('assets/img/qualification-badge-default.svg');
$isEdit = is_array($definition);
$action = $isEdit
    ? url('back-office/referentiels/qualifications/' . (int) $definition['id'] . '/update')
    : url('back-office/referentiels/qualifications/store');
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');
$v = static function (string $key, mixed $default = '') use ($definition): string {
    if (!is_array($definition)) {
        return (string) $default;
    }

    return (string) ($definition[$key] ?? $default);
};
$checked = static function (string $key) use ($definition): bool {
    return is_array($definition) && !empty($definition[$key]);
};
?>
<div class="max-w-5xl mx-auto px-6 py-12">
    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <a href="<?= url('back-office/referentiels/qualifications') ?>" class="text-sm text-slate-500 hover:text-slate-800">← Référentiel</a>
            <h1 class="text-2xl font-black text-slate-900 mt-2"><?= $isEdit ? 'Modifier la qualification' : 'Nouvelle qualification' ?></h1>
        </div>
        <?php if ($isEdit && empty($definition['archived_at'])): ?>
        <form method="post" action="<?= url('back-office/referentiels/qualifications/' . (int) $definition['id'] . '/archive') ?>" onsubmit="return confirm('Archiver cette qualification ? Les attributions existantes sont conservées.');">
            <?= \App\Core\Csrf::field() ?>
            <button class="px-3 py-2 text-sm border border-amber-300 text-amber-900 rounded hover:bg-amber-50">Archiver</button>
        </form>
        <?php endif; ?>
    </div>

    <?php if ($flashSuccess): ?><p class="mb-4 text-sm text-emerald-700 bg-emerald-50 px-3 py-2 rounded"><?= htmlspecialchars($flashSuccess) ?></p><?php endif; ?>
    <?php if ($flashError): ?><p class="mb-4 text-sm text-red-700 bg-red-50 px-3 py-2 rounded"><?= htmlspecialchars($flashError) ?></p><?php endif; ?>

    <form method="post" action="<?= $action ?>" class="space-y-6 rounded-lg border border-slate-200 bg-white p-6">
        <?= \App\Core\Csrf::field() ?>
        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Code (stable)</label>
                <input name="code" required <?= $isEdit ? 'readonly' : '' ?> value="<?= htmlspecialchars($v('code')) ?>" class="w-full border border-slate-300 rounded px-3 py-2 text-sm <?= $isEdit ? 'bg-slate-50' : '' ?>">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Nom</label>
                <input name="name" required value="<?= htmlspecialchars($v('name')) ?>" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Nom court</label>
                <input name="short_name" value="<?= htmlspecialchars($v('short_name')) ?>" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Portée</label>
                <select name="qualification_scope" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
                    <option value="global" <?= $v('qualification_scope', 'global') === 'global' ? 'selected' : '' ?>>Globale</option>
                    <option value="unit" <?= $v('qualification_scope') === 'unit' ? 'selected' : '' ?>>Unité</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Catégorie</label>
                <select name="category_id" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
                    <option value="">—</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= (int) $v('category_id') === (int) $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Type</label>
                <select name="type_id" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
                    <option value="">—</option>
                    <?php foreach ($types as $t): ?>
                        <option value="<?= (int) $t['id'] ?>" <?= (int) $v('type_id') === (int) $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Description</label>
            <textarea name="description" rows="3" class="w-full border border-slate-300 rounded px-3 py-2 text-sm"><?= htmlspecialchars($v('description')) ?></textarea>
        </div>
        <div class="grid md:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Validité (mois)</label>
                <input type="number" min="0" name="default_validity_months" value="<?= htmlspecialchars($v('default_validity_months')) ?>" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Alerte avant expiration (jours)</label>
                <input type="number" min="0" name="alert_before_expiry_days" value="<?= htmlspecialchars($v('alert_before_expiry_days', '30')) ?>" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Période de grâce (jours)</label>
                <input type="number" min="0" name="grace_period_days" value="<?= htmlspecialchars($v('grace_period_days')) ?>" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
            </div>
        </div>
        <div class="flex flex-wrap gap-4 text-sm">
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="uses_levels" value="1" <?= $checked('uses_levels') ? 'checked' : '' ?>> Utilise des niveaux</label>
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_permanent" value="1" <?= $checked('is_permanent') ? 'checked' : '' ?>> Permanente</label>
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="enforce_level_progression" value="1" <?= $checked('enforce_level_progression') ? 'checked' : '' ?>> Progression des niveaux obligatoire</label>
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="requires_panel" value="1" <?= $checked('requires_panel') ? 'checked' : '' ?>> Jury de validation</label>
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="requires_exam" value="1" <?= $checked('requires_exam') ? 'checked' : '' ?>> Examen requis</label>
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="renewal_required" value="1" <?= $checked('renewal_required') ? 'checked' : '' ?>> Renouvellement requis</label>
        </div>
        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Gabarit de brevet</label>
                <select name="certificate_template_id" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
                    <option value="">Par défaut (Classique)</option>
                    <?php foreach ($templates as $tpl): ?>
                        <option value="<?= (int) $tpl['id'] ?>" <?= (int) $v('certificate_template_id') === (int) $tpl['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $tpl['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-slate-500 mt-1">
                    Modèles vierges :
                    <a class="underline text-emerald-800" href="<?= htmlspecialchars(asset_url('docs/qualification-certificate-templates/template_classique_vierge.pdf'), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Classique</a>
                    ·
                    <a class="underline text-emerald-800" href="<?= htmlspecialchars(asset_url('docs/qualification-certificate-templates/template_moderne_vierge.pdf'), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Moderne</a>
                </p>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Format du numéro de brevet</label>
                <input name="certificate_number_format" placeholder="QUAL-{code}-{year}-{seq}" value="<?= htmlspecialchars($v('certificate_number_format')) ?>" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
            </div>
        </div>
        <div class="flex justify-end">
            <button class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800"><?= $isEdit ? 'Enregistrer' : 'Créer' ?></button>
        </div>
    </form>

    <?php if ($isEdit): ?>
    <div id="badge" class="mt-8 rounded-lg border border-slate-200 bg-white p-6">
        <h2 class="text-sm font-black uppercase tracking-widest text-slate-500 mb-4">Insigne</h2>
        <div class="flex items-center gap-6">
            <img src="<?= htmlspecialchars($badgeUrl) ?>" alt="Insigne" class="w-20 h-20 object-contain border border-slate-200 rounded bg-slate-50 p-2">
            <form method="post" enctype="multipart/form-data" action="<?= url('back-office/referentiels/qualifications/' . (int) $definition['id'] . '/badge') ?>" class="space-y-2">
                <?= \App\Core\Csrf::field() ?>
                <input type="file" name="badge" accept=".png,.webp,.svg,image/png,image/webp,image/svg+xml" required class="text-sm">
                <p class="text-xs text-slate-500">PNG, WebP ou SVG — 2 Mo max.</p>
                <button class="px-3 py-1.5 text-sm bg-slate-900 text-white rounded">Téléverser</button>
            </form>
        </div>
    </div>

    <div id="niveaux" class="mt-8 rounded-lg border border-slate-200 bg-white p-6">
        <h2 class="text-sm font-black uppercase tracking-widest text-slate-500 mb-4">Niveaux</h2>
        <ul class="divide-y divide-slate-100 mb-4">
            <?php foreach ($levels as $lvl): ?>
                <li class="py-2 flex items-center justify-between gap-3 text-sm">
                    <span><strong><?= htmlspecialchars((string) $lvl['name']) ?></strong><?php if (!empty($lvl['short_name'])): ?> <span class="text-slate-500">(<?= htmlspecialchars((string) $lvl['short_name']) ?>)</span><?php endif; ?></span>
                    <form method="post" action="<?= url('back-office/referentiels/qualifications/' . (int) $definition['id'] . '/niveaux/' . (int) $lvl['id'] . '/supprimer') ?>">
                        <?= \App\Core\Csrf::field() ?>
                        <button class="text-xs text-red-700 underline">Retirer</button>
                    </form>
                </li>
            <?php endforeach; ?>
            <?php if ($levels === []): ?><li class="text-sm text-slate-500 py-2">Aucun niveau.</li><?php endif; ?>
        </ul>
        <form method="post" action="<?= url('back-office/referentiels/qualifications/' . (int) $definition['id'] . '/niveaux') ?>" class="grid md:grid-cols-4 gap-2 items-end">
            <?= \App\Core\Csrf::field() ?>
            <input name="name" required placeholder="Nom" class="border border-slate-300 rounded px-2 py-1.5 text-sm">
            <input name="short_name" placeholder="Court" class="border border-slate-300 rounded px-2 py-1.5 text-sm">
            <select name="previous_level_id" class="border border-slate-300 rounded px-2 py-1.5 text-sm">
                <option value="">Niveau précédent</option>
                <?php foreach ($levels as $lvl): ?>
                    <option value="<?= (int) $lvl['id'] ?>"><?= htmlspecialchars((string) $lvl['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="px-3 py-1.5 text-sm bg-slate-900 text-white rounded">Ajouter</button>
        </form>
    </div>

    <div id="prerequis" class="mt-8 rounded-lg border border-slate-200 bg-white p-6">
        <h2 class="text-sm font-black uppercase tracking-widest text-slate-500 mb-4">Prérequis</h2>
        <ul class="mb-4 space-y-1 text-sm">
            <?php foreach ($prerequisites as $p): ?>
                <li class="flex justify-between gap-3">
                    <span><?= htmlspecialchars((string) ($p['required_name'] ?? '')) ?> — <?= htmlspecialchars((string) $p['requirement_type']) ?></span>
                    <form method="post" action="<?= url('back-office/referentiels/qualifications/' . (int) $definition['id'] . '/prerequis/' . (int) $p['id'] . '/supprimer') ?>">
                        <?= \App\Core\Csrf::field() ?>
                        <button class="text-xs text-red-700 underline">Retirer</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
        <form method="post" action="<?= url('back-office/referentiels/qualifications/' . (int) $definition['id'] . '/prerequis') ?>" class="flex flex-wrap gap-2 items-end">
            <?= \App\Core\Csrf::field() ?>
            <select name="required_qualification_id" required class="border border-slate-300 rounded px-2 py-1.5 text-sm">
                <option value="">Qualification requise</option>
                <?php foreach ($allDefinitions as $d): if ((int) $d['id'] === (int) $definition['id']) continue; ?>
                    <option value="<?= (int) $d['id'] ?>"><?= htmlspecialchars((string) $d['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="requirement_type" class="border border-slate-300 rounded px-2 py-1.5 text-sm">
                <option value="obtention">Obtention</option>
                <option value="recyclage">Recyclage</option>
            </select>
            <button class="px-3 py-1.5 text-sm bg-slate-900 text-white rounded">Ajouter</button>
        </form>
    </div>

    <div id="champs" class="mt-8 rounded-lg border border-slate-200 bg-white p-6">
        <h2 class="text-sm font-black uppercase tracking-widest text-slate-500 mb-4">Champs personnalisés</h2>
        <ul class="mb-4 space-y-1 text-sm">
            <?php foreach ($customFields as $cf): ?>
                <li class="flex justify-between gap-3">
                    <span><?= htmlspecialchars((string) $cf['name']) ?> <span class="text-slate-400 font-mono text-xs"><?= htmlspecialchars((string) $cf['code']) ?></span></span>
                    <form method="post" action="<?= url('back-office/referentiels/qualifications/' . (int) $definition['id'] . '/champs/' . (int) $cf['id'] . '/supprimer') ?>">
                        <?= \App\Core\Csrf::field() ?>
                        <button class="text-xs text-red-700 underline">Retirer</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
        <form method="post" action="<?= url('back-office/referentiels/qualifications/' . (int) $definition['id'] . '/champs') ?>" class="grid md:grid-cols-4 gap-2 items-end">
            <?= \App\Core\Csrf::field() ?>
            <input name="name" required placeholder="Libellé" class="border border-slate-300 rounded px-2 py-1.5 text-sm">
            <input name="code" required placeholder="CODE" class="border border-slate-300 rounded px-2 py-1.5 text-sm uppercase">
            <select name="field_type" class="border border-slate-300 rounded px-2 py-1.5 text-sm">
                <option value="text_short">Texte court</option>
                <option value="text_long">Texte long</option>
                <option value="number">Nombre</option>
                <option value="date">Date</option>
                <option value="boolean">Oui / Non</option>
                <option value="url">Lien / document</option>
            </select>
            <button class="px-3 py-1.5 text-sm bg-slate-900 text-white rounded">Ajouter</button>
        </form>
    </div>

    <div id="droits" class="mt-8 rounded-lg border border-slate-200 bg-white p-6">
        <h2 class="text-sm font-black uppercase tracking-widest text-slate-500 mb-2">Droits accordés à l’obtention</h2>
        <p class="text-xs text-slate-500 mb-4">Les accès d’administration de la plateforme sont exclus automatiquement.</p>
        <ul class="mb-4 space-y-1 text-sm">
            <?php foreach ($permissionGrants as $g): ?>
                <li class="flex justify-between gap-3">
                    <span class="font-mono text-xs"><?= htmlspecialchars((string) $g['permission_code']) ?></span>
                    <form method="post" action="<?= url('back-office/referentiels/qualifications/' . (int) $definition['id'] . '/droits/' . (int) $g['id'] . '/supprimer') ?>">
                        <?= \App\Core\Csrf::field() ?>
                        <button class="text-xs text-red-700 underline">Retirer</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
        <form method="post" action="<?= url('back-office/referentiels/qualifications/' . (int) $definition['id'] . '/droits') ?>" class="flex flex-wrap gap-2 items-end">
            <?= \App\Core\Csrf::field() ?>
            <input name="permission_code" required placeholder="ex. trainings.create" class="border border-slate-300 rounded px-2 py-1.5 text-sm min-w-[16rem]">
            <button class="px-3 py-1.5 text-sm bg-slate-900 text-white rounded">Lier</button>
        </form>
    </div>

    <div class="mt-8 rounded-lg border border-slate-200 bg-white p-6">
        <h2 class="text-sm font-black uppercase tracking-widest text-slate-500 mb-4">Détenteurs</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase text-slate-500">
                    <tr><th class="py-2 pr-3">Personnel</th><th class="py-2 pr-3">Niveau</th><th class="py-2 pr-3">Obtenue</th><th class="py-2 pr-3">Expire</th><th class="py-2"></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                <?php foreach ($holders as $h): ?>
                    <tr>
                        <td class="py-2 pr-3"><?= htmlspecialchars((string) ($h['display_name'] ?: $h['username'] ?? '')) ?></td>
                        <td class="py-2 pr-3"><?= htmlspecialchars((string) ($h['level_name'] ?? $h['level'] ?? '—')) ?></td>
                        <td class="py-2 pr-3"><?= htmlspecialchars((string) ($h['obtained_at'] ?? '—')) ?></td>
                        <td class="py-2 pr-3"><?= htmlspecialchars((string) ($h['expires_at'] ?? '—')) ?></td>
                        <td class="py-2">
                            <a class="underline text-emerald-800" href="<?= url('back-office/ressources/effectifs/membres/' . (int) $h['user_id']) ?>#qualifications">Fiche</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($holders === []): ?>
                    <tr><td colspan="5" class="py-4 text-slate-500">Aucun détenteur pour le moment.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <a href="<?= url('back-office/referentiels/qualifications/attribuer') ?>?definition_id=<?= (int) $definition['id'] ?>" class="inline-block mt-4 text-sm font-semibold text-emerald-800 underline">Attribuer cette qualification</a>
    </div>
    <?php endif; ?>
</div>
