<?php
$error = \App\Core\Session::getFlash('error');
$success = \App\Core\Session::getFlash('success');
/** @var list<array<string,mixed>> $paidPlans */
$paidPlans = $paidPlans ?? [];
$stripeConfigured = $stripeConfigured ?? false;
/** @var list<array<string,mixed>> $gradesFr */
$gradesFr = $gradesFr ?? [];
/** @var list<array<string,mixed>> $gradesUs */
$gradesUs = $gradesUs ?? [];
$defaultWizardUnitsJson = $defaultWizardUnitsJson ?? '[]';
$quickUnitsArr = json_decode($defaultWizardUnitsJson, true);
if (!is_array($quickUnitsArr)) {
    $quickUnitsArr = [];
}
$zones = \DateTimeZone::listIdentifiers();
$gradesFrGrouped = $gradesFrGrouped ?? [];
$gradesUsGrouped = $gradesUsGrouped ?? [];
$badgeLabels = $badgeLabels ?? \App\Services\Community\TenantCommunityProfileService::badgeLabels();
$wizardPermissionGroups = $wizardPermissionGroups ?? \App\Services\Community\CommunityOnboardingValidationService::wizardPermissionFieldGroups();
$baseUrl = url('');
$presentationUrl = url('back-office/community/presentation');
$presentationPackUrl = $presentationUrl . '#pack-milsim-editor';

$featureLabels = [
    'forum' => 'Forum & discussions',
    'documents' => 'Documents et pièces jointes',
    'training' => 'Formations & parcours',
    'atak' => 'Carte tactique ATAK',
    'analytics' => 'Tableaux de bord & statistiques',
    'events' => 'Événements & planning',
    'community_create' => null,
];

$planMarketing = static function (string $slug): array {
    return match ($slug) {
        'standard' => ['eyebrow' => 'Premium', 'title' => 'Pro', 'blurb' => 'Formations, documents et événements avec plafonds adaptés aux unités en croissance.'],
        'pro' => ['eyebrow' => 'Premium Plus', 'title' => 'Pro +', 'blurb' => 'Plafonds élargis, analytics et options avancées pour une communauté outillée.'],
        default => ['eyebrow' => 'Premium', 'title' => $slug, 'blurb' => ''],
    };
};

$renderPlanFeatures = static function (array $feat, array $limits, array $featureLabels): void {
    $seen = [];
    foreach ($feat as $k => $v) {
        if ($k === 'max_members' || !is_scalar($v)) {
            continue;
        }
        $lab = $featureLabels[$k] ?? $k;
        if ($lab === null || $lab === '') {
            continue;
        }
        $on = $v === true || $v === 1 || $v === '1';
        if (!$on) {
            continue;
        }
        $seen[$k] = true;
        echo '<li class="flex items-center gap-2 text-sm text-slate-700"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>' . htmlspecialchars((string) $lab) . '</li>';
    }
    $maxM = isset($feat['max_members']) ? (int) $feat['max_members'] : (isset($limits['max_members']) ? (int) $limits['max_members'] : 0);
    if ($maxM > 0) {
        echo '<li class="flex items-center gap-2 text-sm text-slate-700"><span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>Jusqu’à ' . (int) $maxM . ' membres (indicatif)</li>';
    }
};
?>
<div class="min-h-screen bg-slate-100">
    <div class="mx-auto max-w-[min(100%,100rem)] px-4 py-10 sm:px-6 lg:px-8 lg:py-12">
        <?php if ($error): ?>
        <div class="mb-6 overflow-hidden rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 shadow-sm"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="mb-6 overflow-hidden rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 shadow-sm"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="grid gap-8 lg:gap-10 2xl:grid-cols-[1.1fr_0.9fr]">

            <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-[0_20px_60px_-24px_rgba(15,23,42,0.18)]">
                <div class="border-b border-slate-200 bg-gradient-to-r from-slate-950 via-slate-900 to-slate-800 px-6 py-8 sm:px-8">
                    <div class="max-w-3xl">
                        <p class="text-[11px] font-black uppercase tracking-[0.28em] text-emerald-400">Athena Communities</p>
                        <p class="mt-3 text-xs text-slate-400">Après création, complétez la <strong class="text-slate-300">fiche registre</strong> (jeu, présentation, catalogue) depuis le back-office organisation.</p>
                        <h1 class="mt-3 text-3xl font-black tracking-tight text-white sm:text-4xl">Créer une communauté</h1>
                        <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300">
                            Assistant en 5 étapes : identité, rôles, ORBAT, grades et validation. Tout est configuré avant création effective.
                        </p>
                    </div>
                </div>

                <form method="post" action="<?= url('communities/create') ?>" enctype="multipart/form-data" class="space-y-8 px-6 py-8 sm:px-8" id="community-create-form" novalidate>
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="wizard_units_json" id="wizard-units-json" value="<?= htmlspecialchars($defaultWizardUnitsJson, ENT_QUOTES, 'UTF-8') ?>">

                    <?php if (!$stripeConfigured): ?>
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm leading-6 text-amber-900">
                        <strong class="font-semibold">Paiement en ligne indisponible</strong> — la clé serveur Stripe n’est pas configurée. Vous pouvez créer une communauté en <strong>Quartier libre</strong>.
                    </div>
                    <?php endif; ?>

                    <nav class="flex flex-wrap gap-2" aria-label="Étapes">
                        <?php foreach (['Identité', 'Rôles', 'Organisation', 'Grades', 'Validation'] as $i => $label): ?>
                        <button type="button" class="wizard-step-tab rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.15em] text-slate-500 data-[active=1]:border-emerald-500 data-[active=1]:bg-emerald-50 data-[active=1]:text-emerald-900" data-step-tab="<?= $i + 1 ?>" data-active="1"><?= $i + 1 ?>. <?= htmlspecialchars($label) ?></button>
                        <?php endforeach; ?>
                    </nav>

                    <div class="wizard-panel" data-step="1">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-2xl bg-slate-900 text-white">1</div>
                            <div>
                                <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Étape 1</p>
                                <h2 class="text-lg font-black tracking-tight text-slate-950">Identité & fuseau</h2>
                            </div>
                        </div>
                        <p class="mt-4 text-sm leading-6 text-slate-600">
                            Nom public de la communauté, langue et fuseau horaire par défaut. Vous pouvez aussi renseigner une présentation courte, un logo et une bannière pour la page d’accueil.
                        </p>
                        <div class="mt-5 grid gap-5 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <label class="mb-2 block text-[11px] font-black uppercase tracking-[0.22em] text-slate-500">Nom affiché</label>
                                <input type="text" name="name" required maxlength="255" class="h-14 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" placeholder="92e Régiment d'infanterie">
                            </div>
                            <div class="md:col-span-2">
                                <label class="mb-3 flex cursor-pointer items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                    <input type="checkbox" name="wizard_custom_community_slug" value="1" id="wizard-custom-community-slug" class="h-4 w-4 rounded border-slate-300 text-emerald-600">
                                    <span class="text-sm font-semibold text-slate-800">Définir un <strong>slug URL</strong> personnalisé</span>
                                </label>
                                <p class="mb-2 text-xs text-slate-500">Sans cette option, l’adresse web de la communauté est <strong>dérivée automatiquement</strong> du nom affiché (lettres minuscules et tirets).</p>
                                <div id="wizard-community-slug-wrap" class="hidden">
                                    <label class="mb-2 block text-[11px] font-black uppercase tracking-[0.22em] text-slate-500">Segment d’URL (slug)</label>
                                    <input type="text" name="slug" id="wizard-community-slug-input" pattern="[a-z0-9]([a-z0-9-]{0,48}[a-z0-9])?" class="h-14 w-full rounded-2xl border border-slate-200 bg-white px-4 font-mono text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" placeholder="ex. mon-unite">
                                </div>
                            </div>
                            <div>
                                <label class="mb-2 block text-[11px] font-black uppercase tracking-[0.22em] text-slate-500">Langue par défaut</label>
                                <select name="wizard_default_locale" class="h-14 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-900 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100">
                                    <option value="fr" selected>Français</option>
                                    <option value="en">English</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-2 block text-[11px] font-black uppercase tracking-[0.22em] text-slate-500">Fuseau horaire</label>
                                <select name="wizard_timezone" class="h-14 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-900 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100">
                                    <?php foreach ($zones as $z): ?>
                                    <option value="<?= htmlspecialchars($z, ENT_QUOTES, 'UTF-8') ?>" <?= $z === 'Europe/Paris' ? 'selected' : '' ?>><?= htmlspecialchars($z, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mt-8 space-y-5 border-t border-slate-200 pt-6">
                            <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-500">Identité publique (optionnel)</p>
                            <p class="text-xs text-slate-500">Ces éléments alimentent la page communauté et le registre ; vous pourrez tout affiner après création.</p>
                            <div class="rounded-2xl border border-emerald-100 bg-emerald-50/40 p-4 space-y-3">
                                <p class="text-[11px] font-black uppercase tracking-[0.2em] text-emerald-900">Fiche publique vitrine</p>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-slate-700">Modèle de page publique</label>
                                    <select name="wizard_public_page_layout" class="h-11 w-full max-w-md rounded-xl border border-slate-200 bg-white px-3 text-sm">
                                        <option value="legacy" selected>Classique (carte)</option>
                                        <option value="showcase">Vitrine (pleine page)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-slate-700">Accroche (sous le titre)</label>
                                    <textarea name="wizard_public_hero_subtitle" rows="2" maxlength="600" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Phrase d’introduction publique…"></textarea>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-slate-700">Doctrine (court)</label>
                                    <input type="text" name="wizard_public_doctrine" maxlength="200" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="ex. Commandement interarmes">
                                </div>
                            </div>
                            <div>
                                <p class="mb-2 text-[11px] font-black uppercase tracking-[0.22em] text-slate-500">Badges de style</p>
                                <div class="flex flex-wrap gap-3">
                                    <?php foreach ($badgeLabels as $slug => $blab): ?>
                                    <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                                        <input type="checkbox" name="wizard_style_badges[]" value="<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>" class="rounded border-slate-300 text-emerald-600">
                                        <?= htmlspecialchars($blab, ENT_QUOTES, 'UTF-8') ?>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="mb-2 block text-[11px] font-black uppercase tracking-[0.22em] text-slate-500">Mode de présentation</label>
                                    <select name="wizard_presentation_mode" class="h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm">
                                        <option value="simple" selected>Texte simple</option>
                                        <option value="military">Sections type militaire</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-2 block text-[11px] font-black uppercase tracking-[0.22em] text-slate-500">Jeu / théâtre (court)</label>
                                    <input type="text" name="wizard_game_label" maxlength="120" class="h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm" placeholder="Arma 3, Squad…">
                                </div>
                            </div>
                            <div>
                                <label class="mb-2 block text-[11px] font-black uppercase tracking-[0.22em] text-slate-500">Présentation courte</label>
                                <textarea name="wizard_simple_body" rows="3" maxlength="8000" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm" placeholder="Qui êtes-vous, cadre, ambiance…"></textarea>
                            </div>
                            <div>
                                <label class="mb-2 block text-[11px] font-black uppercase tracking-[0.22em] text-slate-500">Attentes / mot d’ordre</label>
                                <textarea name="wizard_expectations" rows="2" maxlength="8000" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm" placeholder="Disponibilité, esprit d’équipe…"></textarea>
                            </div>
                            <div class="grid gap-6 md:grid-cols-2">
                                <div class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50/80 p-4">
                                    <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-600">Logo</p>
                                    <label class="block">
                                        <span class="mb-1 block text-xs font-semibold text-slate-700">Envoyer une image</span>
                                        <input type="file" name="wizard_logo_file" accept="image/jpeg,image/png,image/webp,image/gif" class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-xl file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-xs file:font-bold file:text-white file:uppercase file:tracking-wider hover:file:bg-emerald-600">
                                    </label>
                                    <p class="text-[11px] text-slate-500">JPEG, PNG, WebP ou GIF — max 3&nbsp;Mo. Prévisualisation ci-dessous.</p>
                                    <div class="wizard-img-preview mt-2 hidden overflow-hidden rounded-xl border border-slate-200 bg-white" data-preview-for="wizard_logo_file">
                                        <img src="" alt="" class="max-h-40 w-full object-contain">
                                    </div>
                                    <div class="border-t border-slate-200 pt-3">
                                        <label class="mb-1 block text-xs font-semibold text-slate-600">Ou lien externe (URL)</label>
                                        <input type="url" name="wizard_logo_url" maxlength="500" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm" placeholder="https://…">
                                    </div>
                                </div>
                                <div class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50/80 p-4">
                                    <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-600">Bannière (page publique)</p>
                                    <label class="block">
                                        <span class="mb-1 block text-xs font-semibold text-slate-700">Envoyer une image</span>
                                        <input type="file" name="wizard_public_banner_file" accept="image/jpeg,image/png,image/webp,image/gif" class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-xl file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-xs file:font-bold file:text-white file:uppercase file:tracking-wider hover:file:bg-emerald-600">
                                    </label>
                                    <p class="text-[11px] text-slate-500">Idem — image large recommandée (paysage).</p>
                                    <div class="wizard-img-preview mt-2 hidden overflow-hidden rounded-xl border border-slate-200 bg-slate-900/5" data-preview-for="wizard_public_banner_file">
                                        <img src="" alt="" class="max-h-48 w-full object-cover">
                                    </div>
                                    <div class="border-t border-slate-200 pt-3">
                                        <label class="mb-1 block text-xs font-semibold text-slate-600">Ou lien externe (URL)</label>
                                        <input type="url" name="wizard_public_banner_url" maxlength="500" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm" placeholder="https://…">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="wizard-panel hidden" data-step="2">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-800 ring-1 ring-emerald-100">2</div>
                            <div>
                                <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Étape 2</p>
                                <h2 class="text-lg font-black tracking-tight text-slate-950">Rôles & permissions</h2>
                            </div>
                        </div>
                        <p class="mt-4 text-sm leading-6 text-slate-600">
                            Choisissez un <strong>modèle de départ</strong> pour les rôles et droits d’accès de votre communauté. Vous pourrez tout ajuster ensuite dans le back-office.
                            <strong>Rapide</strong> : profils types (Fondateur, État-major, RH, cadre, opérateur, visiteur) avec droits de base.
                            <strong>Standard</strong> : identique, avec une modération forum plus large pour le rôle « Modérateur forum ».
                        </p>
                        <div class="mt-5 space-y-3">
                            <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                                <input type="radio" name="wizard_roles_template" value="quick" class="text-emerald-600" checked>
                                <span class="text-sm font-semibold text-slate-900">Démarrage rapide (recommandé)</span>
                            </label>
                            <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                                <input type="radio" name="wizard_roles_template" value="standard" class="text-emerald-600">
                                <span class="text-sm font-semibold text-slate-900">Standard (modération forum renforcée)</span>
                            </label>
                        </div>
                        <div class="mt-8 overflow-x-auto rounded-2xl border border-slate-200">
                            <table class="min-w-full text-left text-xs">
                                <thead>
                                    <tr class="border-b border-slate-200 bg-slate-50">
                                        <th class="px-3 py-2 font-black text-slate-600">Rôle</th>
                                        <th class="px-3 py-2 font-black text-slate-600">Forum</th>
                                        <th class="px-3 py-2 font-black text-slate-600">Docs / formation</th>
                                        <th class="px-3 py-2 font-black text-slate-600">Modération org. <span class="text-emerald-600 font-bold" title="Standard uniquement">(Std)</span></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-700">
                                    <tr><td class="px-3 py-2 font-semibold">Fondateur</td><td class="px-3 py-2">Complet</td><td class="px-3 py-2">Complet</td><td class="px-3 py-2">—</td></tr>
                                    <tr><td class="px-3 py-2 font-semibold">État-major</td><td class="px-3 py-2">Complet</td><td class="px-3 py-2">Complet</td><td class="px-3 py-2">—</td></tr>
                                    <tr><td class="px-3 py-2 font-semibold">RH</td><td class="px-3 py-2">Lecture / sujets</td><td class="px-3 py-2">Lecture</td><td class="px-3 py-2">—</td></tr>
                                    <tr><td class="px-3 py-2 font-semibold">Instructeur</td><td class="px-3 py-2">Membre</td><td class="px-3 py-2">Formations</td><td class="px-3 py-2">—</td></tr>
                                    <tr><td class="px-3 py-2 font-semibold">Membre</td><td class="px-3 py-2">Standard</td><td class="px-3 py-2">—</td><td class="px-3 py-2">—</td></tr>
                                    <tr><td class="px-3 py-2 font-semibold">Invité</td><td class="px-3 py-2">Lecture</td><td class="px-3 py-2">—</td><td class="px-3 py-2">—</td></tr>
                                    <tr class="bg-emerald-50/50"><td class="px-3 py-2 font-semibold">Modérateur forum</td><td class="px-3 py-2">Modération</td><td class="px-3 py-2">—</td><td class="px-3 py-2 font-semibold text-emerald-800">+ section org. en Standard</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="mt-2 text-xs text-slate-500">Ces droits sont appliqués automatiquement à la création ; la colonne « Std » indique une différence lorsque vous choisissez le modèle Standard.</p>

                        <details class="mt-8 rounded-2xl border border-slate-200 bg-slate-50/80 p-4">
                            <summary class="cursor-pointer text-sm font-bold text-slate-800">Rôles supplémentaires (optionnel)</summary>
                            <p class="mt-3 text-xs leading-relaxed text-slate-600">Ajoutez des <strong>profils métier</strong> propres à votre unité (ex. « Opérateur ATAK », « Logistique ») et cochez les autorisations. Ils seront créés dans votre communauté uniquement et pourront être attribués aux membres depuis le back-office.</p>
                            <div id="wizard-custom-roles-container" class="mt-4 space-y-4"></div>
                            <button type="button" id="wizard-add-custom-role" class="mt-2 rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-2 text-xs font-bold uppercase tracking-wider text-slate-700 hover:border-emerald-400 hover:text-emerald-800">
                                + Ajouter un rôle
                            </button>
                        </details>

                        <template id="wizard-custom-role-tpl">
                            <div class="wizard-custom-role-row rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                <div class="grid gap-3 md:grid-cols-2">
                                    <div>
                                        <label class="mb-1 block text-[10px] font-bold uppercase text-slate-500">Nom affiché</label>
                                        <input type="text" data-role-field="name" maxlength="80" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="ex. Cellule renseignement">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-[10px] font-bold uppercase text-slate-500">Identifiant technique</label>
                                        <input type="text" data-role-field="slug" maxlength="50" class="w-full rounded-xl border border-slate-200 px-3 py-2 font-mono text-sm" placeholder="ex. renseignement" pattern="[a-z][a-z0-9_]{1,48}">
                                        <p class="mt-1 text-[10px] text-slate-500">Lettre minuscule puis lettres, chiffres ou _</p>
                                    </div>
                                </div>
                                <div class="mt-4 space-y-3">
                                    <?php foreach ($wizardPermissionGroups as $groupLabel => $items): ?>
                                    <fieldset class="rounded-xl border border-slate-100 p-3">
                                        <legend class="px-1 text-[10px] font-black uppercase tracking-wider text-slate-500"><?= htmlspecialchars($groupLabel, ENT_QUOTES, 'UTF-8') ?></legend>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            <?php foreach ($items as $item): ?>
                                            <label class="inline-flex items-center gap-2 rounded-lg bg-slate-50 px-2 py-1 text-xs text-slate-700">
                                                <input type="checkbox" class="custom-perm-cb rounded border-slate-300 text-emerald-600" data-perm-slug="<?= htmlspecialchars($item['slug'], ENT_QUOTES, 'UTF-8') ?>">
                                                <span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                            </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </fieldset>
                                    <?php endforeach; ?>
                                </div>
                                <div class="mt-3 text-right">
                                    <button type="button" class="text-xs font-bold text-red-600 hover:underline" data-remove-row>Retirer cette ligne</button>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="wizard-panel hidden" data-step="3">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-2xl bg-sky-50 text-sky-800 ring-1 ring-sky-100">3</div>
                            <div>
                                <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Étape 3</p>
                                <h2 class="text-lg font-black tracking-tight text-slate-950">Structure ORBAT</h2>
                            </div>
                        </div>
                        <p class="mt-4 text-sm leading-6 text-slate-600">
                            Représentez la <strong>chaîne de commandement</strong> ou l’organigramme : au moins une unité racine (ex. état-major, groupement), puis des sous-niveaux si besoin.
                            Types proposés : <strong>Groupe</strong>, <strong>Section</strong>, <strong>Équipe</strong>, <strong>Escouade</strong>. Utilisez les chevrons pour replier ou développer les branches.
                        </p>
                        <label class="mt-4 flex cursor-pointer items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4">
                            <input type="checkbox" name="wizard_quick_fill" value="1" id="wizard-quick-fill" class="mt-1 h-4 w-4 rounded border-slate-300 text-emerald-600">
                            <span class="text-sm text-emerald-950"><strong>Démarrage rapide ORBAT</strong> — insère un groupe racine, une section et une équipe (modifiable ci-dessous).</span>
                        </label>
                        <label class="mt-3 flex cursor-pointer items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3">
                            <input type="checkbox" name="wizard_orbat_custom_slug" value="1" id="wizard-orbat-custom-slug" class="h-4 w-4 rounded border-slate-300 text-emerald-600">
                            <span class="text-sm text-slate-800"><strong>Slugs d’unités personnalisés</strong> — affiche le segment d’URL par unité ; sinon il est calculé à partir du nom.</span>
                        </label>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button type="button" id="orbat-add-root" class="rounded-2xl bg-slate-900 px-4 py-2 text-[11px] font-black uppercase tracking-wider text-white hover:bg-emerald-600">+ Unité racine</button>
                        </div>
                        <div id="orbat-builder-root" class="mt-4 min-h-[120px]"></div>
                        <details class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4">
                            <summary class="cursor-pointer text-sm font-bold text-slate-700">Avancé : édition JSON</summary>
                            <label class="mt-3 mb-2 block text-[11px] font-black uppercase tracking-[0.22em] text-slate-500">JSON des unités</label>
                            <textarea id="wizard-units-editor" rows="8" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 font-mono text-xs text-slate-900 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"><?= htmlspecialchars($defaultWizardUnitsJson, ENT_QUOTES, 'UTF-8') ?></textarea>
                            <p class="mt-2 text-xs text-slate-500">Synchronisé avec le constructeur et le champ <code>wizard_units_json</code>.</p>
                        </details>
                    </div>

                    <div class="wizard-panel hidden" data-step="4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-2xl bg-violet-50 text-violet-800 ring-1 ring-violet-100">4</div>
                            <div>
                                <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Étape 4</p>
                                <h2 class="text-lg font-black tracking-tight text-slate-950">Référentiel de grades</h2>
                            </div>
                        </div>
                        <p class="mt-4 text-sm leading-6 text-slate-600">
                            Sélectionnez le jeu de grades affiché pour votre communauté (français ou américain). Les aperçus listent les catégories ; choisissez le grade initial du compte fondateur.
                        </p>
                        <div class="mt-5 space-y-4">
                            <div>
                                <p class="mb-2 text-[11px] font-black uppercase tracking-[0.22em] text-slate-500">Modèle</p>
                                <div class="flex flex-wrap gap-6">
                                    <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                                        <input type="radio" name="wizard_grade_system_code" value="FR_CLASSIC" class="text-emerald-600" checked data-grade-system>
                                        <span class="inline-flex items-center gap-2 text-sm font-bold text-slate-900">
                                            <span class="text-2xl leading-none" aria-hidden="true">🇫🇷</span>
                                            Français
                                        </span>
                                    </label>
                                    <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                                        <input type="radio" name="wizard_grade_system_code" value="US_CLASSIC" class="text-emerald-600" data-grade-system>
                                        <span class="inline-flex items-center gap-2 text-sm font-bold text-slate-900">
                                            <span class="text-2xl leading-none" aria-hidden="true">🇺🇸</span>
                                            United States
                                        </span>
                                    </label>
                                </div>
                            </div>
                            <div class="grid gap-6 md:grid-cols-2">
                                <div id="preview-fr" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 max-h-80 overflow-y-auto">
                                    <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Aperçu référentiel français</p>
                                    <div class="mt-3 space-y-3 text-xs text-slate-700">
                                        <?php foreach ($gradesFrGrouped as $block): ?>
                                            <?php if (empty($block['grades'])) { continue; } ?>
                                            <div>
                                                <p class="font-black text-slate-800"><?= htmlspecialchars($block['label'], ENT_QUOTES, 'UTF-8') ?></p>
                                                <ul class="mt-1 space-y-0.5 pl-2 border-l-2 border-emerald-200">
                                                    <?php foreach ($block['grades'] as $g): ?>
                                                    <li><?= htmlspecialchars((string) ($g['label_short'] ?? $g['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars((string) ($g['label_long'] ?? ''), ENT_QUOTES, 'UTF-8') ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div id="preview-us" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 max-h-80 overflow-y-auto opacity-60">
                                    <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Aperçu référentiel US</p>
                                    <div class="mt-3 space-y-3 text-xs text-slate-700">
                                        <?php foreach ($gradesUsGrouped as $block): ?>
                                            <?php if (empty($block['grades'])) { continue; } ?>
                                            <div>
                                                <p class="font-black text-slate-800"><?= htmlspecialchars($block['label'], ENT_QUOTES, 'UTF-8') ?></p>
                                                <ul class="mt-1 space-y-0.5 pl-2 border-l-2 border-slate-300">
                                                    <?php foreach ($block['grades'] as $g): ?>
                                                    <li><?= htmlspecialchars((string) ($g['label_short'] ?? $g['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars((string) ($g['label_long'] ?? ''), ENT_QUOTES, 'UTF-8') ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="mb-2 block text-[11px] font-black uppercase tracking-[0.22em] text-slate-500">Grade du fondateur</label>
                                <select name="wizard_founder_grade_id" id="founder-grade-fr" class="h-14 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-900 founder-grade-select">
                                    <?php foreach ($gradesFrGrouped as $block): ?>
                                        <?php if (empty($block['grades'])) { continue; } ?>
                                        <optgroup label="<?= htmlspecialchars($block['label'], ENT_QUOTES, 'UTF-8') ?>">
                                            <?php foreach ($block['grades'] as $g): ?>
                                            <option value="<?= (int) $g['id'] ?>"><?= htmlspecialchars((string) ($g['label_long'] ?? $g['label_short'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                                <select name="wizard_founder_grade_id_us" id="founder-grade-us" class="hidden h-14 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-900 founder-grade-select">
                                    <?php foreach ($gradesUsGrouped as $block): ?>
                                        <?php if (empty($block['grades'])) { continue; } ?>
                                        <optgroup label="<?= htmlspecialchars($block['label'], ENT_QUOTES, 'UTF-8') ?>">
                                            <?php foreach ($block['grades'] as $g): ?>
                                            <option value="<?= (int) $g['id'] ?>"><?= htmlspecialchars((string) ($g['label_long'] ?? $g['label_short'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="wizard-panel hidden" data-step="5">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-2xl bg-amber-50 text-amber-800 ring-1 ring-amber-100">5</div>
                            <div>
                                <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Étape 5</p>
                                <h2 class="text-lg font-black tracking-tight text-slate-950">Validation & accès</h2>
                            </div>
                        </div>
                        <p class="mt-4 text-sm leading-6 text-slate-600">
                            Dernière étape : visibilité de l’organigramme, formule d’hébergement, règles d’inscription et personnalisation du formulaire. Vérifiez le récapitulatif avant de créer la communauté ou de passer au paiement.
                        </p>
                        <div class="mt-5 grid gap-5">
                            <div>
                                <label class="mb-2 block text-[11px] font-black uppercase tracking-[0.22em] text-slate-500">Visibilité ORBAT</label>
                                <select name="wizard_orbat_visibility" class="h-14 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-900">
                                    <option value="public">Public</option>
                                    <option value="members" selected>Membres uniquement</option>
                                    <option value="command">Commandement</option>
                                </select>
                            </div>
                        </div>

                        <section class="mt-8 space-y-5 border-t border-slate-200 pt-8">
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-3.314 0-6 1.343-6 3s2.686 3 6 3 6-1.343 6-3-2.686-3-6-3zm0 0V6m0 8v2m-7 2h14"></path></svg>
                                </div>
                                <div>
                                    <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Offre</p>
                                    <h2 class="text-lg font-black tracking-tight text-slate-950">Formule</h2>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 gap-5 md:grid-cols-2 md:gap-6 xl:grid-cols-3">
                                <label class="group relative flex min-h-[22rem] cursor-pointer flex-col rounded-[1.75rem] border-2 border-slate-900 bg-slate-900 p-5 text-white transition hover:-translate-y-0.5 hover:shadow-xl has-[:checked]:ring-4 has-[:checked]:ring-emerald-100 sm:p-6">
                                    <input type="radio" name="plan_choice" value="free" class="sr-only" checked data-paid="0">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-[11px] font-black uppercase tracking-[0.24em] text-emerald-400">Sans engagement</p>
                                            <h3 class="mt-2 text-2xl font-black tracking-tight">Quartier libre</h3>
                                        </div>
                                    </div>
                                    <p class="mt-4 text-sm leading-6 text-slate-300">Création immédiate après validation de l’assistant. Fonctionnalités de base (forum, documents, formations selon configuration plateforme).</p>
                                </label>
                                <?php foreach ($paidPlans as $p):
                                    $slug = (string) ($p['slug'] ?? '');
                                    $m = $planMarketing($slug);
                                    $featRaw = $p['features_json'] ?? '{}';
                                    $feat = is_string($featRaw) ? json_decode($featRaw, true) : [];
                                    if (!is_array($feat)) {
                                        $feat = [];
                                    }
                                    $limitsRaw = $p['limits_json'] ?? '{}';
                                    $limits = is_string($limitsRaw) ? json_decode($limitsRaw, true) : [];
                                    if (!is_array($limits)) {
                                        $limits = [];
                                    }
                                    $hasM = $stripeConfigured && trim((string) ($p['stripe_price_id_monthly'] ?? '')) !== '';
                                    $hasY = $stripeConfigured && trim((string) ($p['stripe_price_id_yearly'] ?? '')) !== '';
                                    $planBadgeClass = $slug === 'pro' ? 'text-violet-700' : 'text-amber-600';
                                    ?>
                                <div class="relative flex min-h-[22rem] flex-col rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-[11px] font-black uppercase tracking-[0.24em] <?= $planBadgeClass ?>"><?= htmlspecialchars($m['eyebrow']) ?></p>
                                            <h3 class="mt-2 text-2xl font-black tracking-tight text-slate-950"><?= htmlspecialchars($m['title']) ?></h3>
                                        </div>
                                    </div>
                                    <?php if (!empty($m['blurb'])): ?>
                                    <p class="mt-3 text-sm leading-6 text-slate-600"><?= htmlspecialchars($m['blurb']) ?></p>
                                    <?php endif; ?>
                                    <ul class="mt-4 flex-1 space-y-1.5">
                                        <?php ob_start(); ?>
                                        <?php $renderPlanFeatures($feat, $limits, $featureLabels); ?>
                                        <?php $featHtml = ob_get_clean(); ?>
                                        <?php if ($featHtml !== ''): ?>
                                            <?= $featHtml ?>
                                        <?php else: ?>
                                            <li class="text-xs text-slate-500">Fonctionnalités décrites dans la facturation Stripe.</li>
                                        <?php endif; ?>
                                    </ul>
                                    <div class="mt-6 space-y-2 border-t border-slate-100 pt-4">
                                        <?php if ($hasM): ?>
                                        <label class="flex cursor-pointer items-center gap-3 text-sm">
                                            <input type="radio" name="plan_choice" value="<?= htmlspecialchars($slug) ?>|monthly" class="rounded-full border-slate-300 text-emerald-600" data-paid="1">
                                            <span>Mensuel (Stripe)</span>
                                        </label>
                                        <?php endif; ?>
                                        <?php if ($hasY): ?>
                                        <label class="flex cursor-pointer items-center gap-3 text-sm">
                                            <input type="radio" name="plan_choice" value="<?= htmlspecialchars($slug) ?>|yearly" class="rounded-full border-slate-300 text-emerald-600" data-paid="1">
                                            <span>Annuel (Stripe)</span>
                                        </label>
                                        <?php endif; ?>
                                        <?php if (!$hasM && !$hasY): ?>
                                        <p class="text-xs text-amber-800">Configurez les identifiants de prix Stripe pour ce plan afin d’activer l’abonnement.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </section>

                        <section class="mt-8 space-y-6 border-t border-slate-200 pt-8">
                            <div>
                                <h2 class="text-lg font-black tracking-tight text-slate-950">Paramètres d’accès</h2>
                                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Le mode définit l’expérience candidat sur <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-800">/c/&lt;slug&gt;/enlistment</code>. Vous pourrez le modifier plus tard dans le back-office.</p>
                            </div>

                            <div class="grid gap-6 lg:grid-cols-[minmax(0,22rem)_1fr] lg:items-start">
                                <div class="space-y-2">
                                    <label class="mb-2 block text-[11px] font-black uppercase tracking-[0.22em] text-slate-500">Mode d’inscription</label>
                                    <select name="registration_mode" id="registration_mode" class="h-14 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-900 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100">
                                        <option value="milsim">Formulaire MilSim complet</option>
                                        <option value="simple">Mode simple</option>
                                    </select>
                                    <p class="text-xs text-slate-500">Visible par les visiteurs sur le portail d’enrôlement public.</p>
                                </div>

                                <div class="min-w-0 space-y-4">
                                    <div id="registration-mode-detail-milsim" class="rounded-2xl border border-emerald-200/80 bg-emerald-50/50 p-5 shadow-sm">
                                        <p class="text-[11px] font-black uppercase tracking-[0.2em] text-emerald-900">Formulaire MilSim complet</p>
                                        <ul class="mt-3 list-disc space-y-2 pl-5 text-xs leading-relaxed text-slate-700">
                                            <li><strong class="text-slate-900">Dossier étendu</strong> (type « Olympus ») : identité administratif/RP, matériel, expérience, motivation, engagement, barre de progression et case de confirmation anti-IA.</li>
                                            <li><strong class="text-slate-900">Adapté</strong> aux unités qui veulent filtrer fortement et documenter chaque candidature.</li>
                                            <li><strong class="text-slate-900">Personnalisation</strong> : libellés, préambule, ROE, filigrane — soit via JSON dans cet assistant, soit via l’éditeur visuel après création.</li>
                                        </ul>
                                        <div class="mt-4 flex flex-wrap gap-2">
                                            <button type="button" class="btn-open-milsim-form-editor inline-flex items-center justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-[10px] font-black uppercase tracking-[0.18em] text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                                                Ouvrir l’édition du formulaire
                                            </button>
                                            <span class="self-center text-[10px] text-slate-500">JSON optionnel · pack MilSim</span>
                                        </div>
                                    </div>

                                    <div id="registration-mode-detail-simple" class="hidden rounded-2xl border border-slate-200 bg-slate-50 p-5 shadow-sm">
                                        <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-600">Mode simple</p>
                                        <ul class="mt-3 list-disc space-y-2 pl-5 text-xs leading-relaxed text-slate-700">
                                            <li><strong class="text-slate-900">Formulaire court</strong> : appel, disponibilité, motivation, consentement compte Athena si besoin — sans le parcours « dossier complet ».</li>
                                            <li><strong class="text-slate-900">Idéal</strong> pour onboarding rapide, communautés plus casual ou file d’attente légère.</li>
                                            <li><strong class="text-slate-900">Côté candidat</strong>, le questionnaire MilSim long n’est pas proposé ; le message d’accueil ci-dessous et la vitrine portent votre ton.</li>
                                        </ul>
                                        <div class="mt-4 flex flex-wrap gap-2">
                                            <button type="button" id="btn-open-welcome-editor" class="inline-flex items-center justify-center rounded-xl border-2 border-slate-300 bg-white px-4 py-2.5 text-[10px] font-black uppercase tracking-[0.18em] text-slate-800 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                                                Aller au message d’accueil
                                            </button>
                                            <a href="<?= htmlspecialchars($presentationPackUrl, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-xl border border-dashed border-slate-300 px-4 py-2.5 text-[10px] font-bold uppercase tracking-widest text-slate-500 hover:border-slate-400 hover:text-slate-800" title="Disponible après création de la communauté — ancre Pack MilSim">
                                                Éditeur complet (après création)
                                            </a>
                                        </div>
                                    </div>

                                    <p class="text-xs text-slate-500">
                                        <span class="font-semibold text-slate-700">Après la création :</span>
                                        éditeur visuel du pack MilSim, textes et fiche registre dans
                                        <a href="<?= htmlspecialchars($presentationUrl, ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-700 underline decoration-emerald-500/40 hover:text-emerald-900">Fiche registre &amp; contact</a>
                                        (back-office organisation, lorsque vous administrez cette communauté).
                                    </p>
                                </div>
                            </div>

                            <div class="grid gap-5 md:grid-cols-2">
                                <div class="md:col-span-2">
                                    <label for="wizard-welcome-text" class="mb-2 block text-[11px] font-black uppercase tracking-[0.22em] text-slate-500">Message d’accueil</label>
                                    <textarea id="wizard-welcome-text" name="welcome_text" rows="3" maxlength="500" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" placeholder="Texte visible sur la page de communauté publique."></textarea>
                                    <p class="mt-1 text-xs text-slate-500">Surtout utile en mode simple ; complète la vitrine <code class="text-[11px]">/c/&lt;slug&gt;</code>.</p>
                                </div>
                            </div>
                            <div class="grid gap-4 md:grid-cols-2">
                                <label class="group flex items-start gap-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                                    <input type="checkbox" name="community_locked" value="1" class="mt-1 h-4 w-4 rounded border-slate-300 text-emerald-600">
                                    <span class="block text-sm font-bold text-slate-900">Verrouiller la communauté</span>
                                </label>
                                <label class="group flex items-start gap-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                                    <input type="checkbox" name="require_ai_ack" value="1" checked class="mt-1 h-4 w-4 rounded border-slate-300 text-emerald-600">
                                    <span class="block text-sm font-bold text-slate-900">Exiger la confirmation « sans IA »</span>
                                </label>
                            </div>

                            <div class="rounded-2xl border border-emerald-200/80 bg-white p-5 shadow-sm ring-1 ring-emerald-100/50">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <p class="text-[11px] font-black uppercase tracking-[0.2em] text-emerald-900">Édition du formulaire MilSim</p>
                                        <p class="mt-1 text-sm font-semibold text-slate-900">Atelier visuel + aperçu en direct</p>
                                        <p class="mt-2 max-w-xl text-xs leading-relaxed text-slate-600">Ouvrez une fenêtre pleine page : préambule, ROE, champs (texte, liste, oui/non) et rendu dans un cadre à droite. Plus besoin de JSON pour l’essentiel.</p>
                                    </div>
                                    <button type="button" class="btn-open-milsim-form-editor inline-flex shrink-0 items-center justify-center rounded-2xl bg-slate-950 px-5 py-3 text-[10px] font-black uppercase tracking-[0.2em] text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                                        Ouvrir l’atelier
                                    </button>
                                </div>
                                <details class="mt-4 rounded-xl border border-dashed border-slate-200 bg-slate-50/80 p-3">
                                    <summary class="cursor-pointer text-xs font-bold text-slate-600">JSON expert (optionnel)</summary>
                                    <p class="mt-2 text-xs text-slate-500">Surcharge de <code class="rounded bg-white px-1 text-[11px]">enlistment_milsim</code> si vous importez une config complète.</p>
                                    <textarea name="wizard_enlistment_milsim_json" id="wizard_enlistment_milsim_json" rows="5" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 font-mono text-xs text-slate-900" placeholder='{"portal_title": "…"}'></textarea>
                                    <p class="mt-2 text-xs text-slate-500">
                                        <a href="<?= htmlspecialchars($presentationPackUrl, ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-700 underline">Fiche registre (après création)</a>
                                    </p>
                                </details>
                            </div>
                        </section>

                        <div id="paid-hint" class="mt-6 hidden rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm leading-6 text-emerald-900">
                            Vous serez redirigé vers <strong>Stripe</strong> pour valider l’abonnement. La communauté sera créée <strong>après confirmation du paiement</strong> avec la configuration de l’assistant.
                        </div>

                        <div class="mt-8 flex flex-wrap items-center gap-3">
                            <button type="submit" formaction="<?= htmlspecialchars(url('communities/create/preview'), ENT_QUOTES, 'UTF-8') ?>" formmethod="post" formtarget="_blank" class="rounded-2xl border border-slate-300 bg-white px-5 py-3 text-[11px] font-black uppercase tracking-[0.15em] text-slate-800 hover:bg-slate-50">
                                Aperçu (nouvel onglet)
                            </button>
                            <span class="text-xs text-slate-500">Enregistre un brouillon en session et ouvre la simulation.</span>
                        </div>

                        <div id="wizard-recap" class="mt-8 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                            <p class="font-bold text-slate-900">Récapitulatif</p>
                            <ul id="wizard-recap-list" class="mt-3 list-disc space-y-1 pl-5 text-slate-700">
                                <li>Complétez les étapes : le détail apparaît ici à l’étape 5.</li>
                            </ul>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-4 border-t border-slate-200 pt-6">
                        <button type="button" id="wizard-prev" class="hidden rounded-2xl border border-slate-300 bg-white px-5 py-3 text-[11px] font-black uppercase tracking-[0.18em] text-slate-800 hover:bg-slate-50">Précédent</button>
                        <div class="ml-auto flex flex-wrap gap-3">
                            <button type="button" id="wizard-next" class="rounded-2xl bg-slate-200 px-5 py-3 text-[11px] font-black uppercase tracking-[0.18em] text-slate-900 hover:bg-slate-300">Suivant</button>
                            <button type="submit" id="submit-btn" class="hidden rounded-2xl bg-slate-950 px-6 py-3 text-[11px] font-black uppercase tracking-[0.22em] text-white hover:bg-emerald-600 focus:outline-none focus:ring-4 focus:ring-emerald-100">Créer la communauté</button>
                        </div>
                    </div>
                <?php include base_path('views/community/partials/milsim_wizard_modal.php'); ?>
                </form>
            </section>

            <aside class="space-y-6">
                <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-6 py-5">
                        <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Contrôle</p>
                        <h3 class="mt-2 text-lg font-black tracking-tight text-slate-950">Assistant complet</h3>
                    </div>
                    <div class="space-y-3 px-6 py-5 text-sm text-slate-600">
                        <p>Droits d’accès, structure des unités (ORBAT), référentiel de grades et paramètres d’inscription sont pris en compte avant la création effective.</p>
                        <p class="text-xs text-slate-500">Les communautés créées avant l’assistant v2 peuvent utiliser la page de rattrapage onboarding dans le back-office.</p>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</div>
<script src="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/assets/js/community-orbat-builder.js"></script>
<script>
(function () {
    var form = document.getElementById('community-create-form');
    var customCommunitySlugCb = document.getElementById('wizard-custom-community-slug');
    var communitySlugWrap = document.getElementById('wizard-community-slug-wrap');
    var communitySlugInput = document.getElementById('wizard-community-slug-input');
    function syncCommunitySlugUi() {
        var on = customCommunitySlugCb && customCommunitySlugCb.checked;
        if (communitySlugWrap) communitySlugWrap.classList.toggle('hidden', !on);
        if (communitySlugInput) {
            communitySlugInput.required = !!on;
            if (!on) communitySlugInput.value = '';
        }
    }
    if (customCommunitySlugCb) {
        customCommunitySlugCb.addEventListener('change', syncCommunitySlugUi);
        syncCommunitySlugUi();
    }

    var regModeSel = document.getElementById('registration_mode');
    var detailMilsim = document.getElementById('registration-mode-detail-milsim');
    var detailSimple = document.getElementById('registration-mode-detail-simple');
    var milsimModal = document.getElementById('milsim-wizard-modal');
    var milsimJsonTa = document.getElementById('wizard_enlistment_milsim_json');
    var welcomeTa = document.getElementById('wizard-welcome-text');
    var btnWelcome = document.getElementById('btn-open-welcome-editor');

    function syncRegistrationModeUi() {
        if (!regModeSel || !detailMilsim || !detailSimple) return;
        var simple = regModeSel.value === 'simple';
        detailMilsim.classList.toggle('hidden', simple);
        detailSimple.classList.toggle('hidden', !simple);
    }
    if (regModeSel) {
        regModeSel.addEventListener('change', syncRegistrationModeUi);
        syncRegistrationModeUi();
    }

    function closeMilsimModal() {
        if (milsimModal) milsimModal.classList.add('hidden');
    }
    function openMilsimFormEditor() {
        if (milsimModal) {
            milsimModal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }
    }
    if (milsimModal) {
        milsimModal.querySelectorAll('[data-milsim-modal-close], [data-milsim-modal-backdrop]').forEach(function (el) {
            el.addEventListener('click', function () {
                closeMilsimModal();
                document.body.classList.remove('overflow-hidden');
            });
        });
    }
    document.querySelectorAll('.btn-open-milsim-form-editor').forEach(function (btn) {
        btn.addEventListener('click', openMilsimFormEditor);
    });
    if (btnWelcome) {
        btnWelcome.addEventListener('click', function () {
            if (welcomeTa) {
                welcomeTa.scrollIntoView({ behavior: 'smooth', block: 'center' });
                welcomeTa.focus();
            }
        });
    }

    var hint = document.getElementById('paid-hint');
    var btn = document.getElementById('submit-btn');
    var nextBtn = document.getElementById('wizard-next');
    var prevBtn = document.getElementById('wizard-prev');
    var step = 1;
    var maxStep = 5;
    var panels = form.querySelectorAll('.wizard-panel');
    var tabs = form.querySelectorAll('[data-step-tab]');
    var unitsHidden = document.getElementById('wizard-units-json');
    var unitsEditor = document.getElementById('wizard-units-editor');
    var quickFill = document.getElementById('wizard-quick-fill');
    var defaultUnits = <?= json_encode($quickUnitsArr, JSON_UNESCAPED_UNICODE) ?>;
    var orbatBuilder = typeof initOrbatBuilder === 'function'
        ? initOrbatBuilder('orbat-builder-root', 'wizard-units-json', { defaultUnits: defaultUnits })
        : null;

    var addRootBtn = document.getElementById('orbat-add-root');
    if (addRootBtn && orbatBuilder) {
        addRootBtn.addEventListener('click', function () { orbatBuilder.addRoot(); });
    }

    function syncUnitsFromStep3() {
        if (orbatBuilder) {
            orbatBuilder.sync();
        } else if (unitsEditor && unitsHidden) {
            unitsHidden.value = unitsEditor.value;
        }
    }

    function updateRecap() {
        var list = document.getElementById('wizard-recap-list');
        if (!list) return;
        var name = (form.querySelector('[name="name"]') || {}).value || '';
        var slug = (form.querySelector('[name="slug"]') || {}).value || '';
        var tz = (form.querySelector('[name="wizard_timezone"]') || {}).value || '';
        var roles = (form.querySelector('input[name="wizard_roles_template"]:checked') || {}).value || '';
        var gs = (form.querySelector('input[name="wizard_grade_system_code"]:checked') || {}).value || '';
        var fgId = (form.querySelector('select.founder-grade-select:not(.hidden)') || {}).value || '';
        var fgText = '';
        var fgSel = form.querySelector('select.founder-grade-select:not(.hidden)');
        if (fgSel && fgSel.selectedIndex >= 0) {
            fgText = fgSel.options[fgSel.selectedIndex].text;
        }
        var unitsCount = 0;
        try {
            var raw = unitsHidden ? unitsHidden.value : '[]';
            var arr = JSON.parse(raw || '[]');
            unitsCount = Array.isArray(arr) ? arr.length : 0;
        } catch (e) { unitsCount = 0; }
        var reg = (form.querySelector('[name="registration_mode"]') || {}).value || '';
        var locked = form.querySelector('[name="community_locked"]') && form.querySelector('[name="community_locked"]').checked;
        var plan = '';
        form.querySelectorAll('input[name="plan_choice"]').forEach(function (el) {
            if (el.checked) plan = el.value;
        });
        var lines = [];
        lines.push('Nom : ' + (name || '—'));
        if (slug) lines.push('Slug : ' + slug);
        if (tz) lines.push('Fuseau : ' + tz);
        lines.push('Modèle rôles : ' + (roles === 'standard' ? 'Standard (modération org.)' : 'Rapide'));
        var extraRoles = document.querySelectorAll('#wizard-custom-roles-container .wizard-custom-role-row').length;
        if (extraRoles > 0) lines.push('Rôles supplémentaires définis : ' + extraRoles);
        lines.push('Unités ORBAT : ' + unitsCount);
        lines.push('Référentiel grades : ' + (gs === 'US_CLASSIC' ? 'US' : 'FR'));
        if (fgText) lines.push('Grade fondateur : ' + fgText);
        lines.push('Inscription : ' + (reg === 'simple' ? 'Mode simple (court)' : 'MilSim complet (dossier étendu)'));
        lines.push('Communauté verrouillée : ' + (locked ? 'oui' : 'non'));
        lines.push('Formule : ' + (plan || '—'));
        list.innerHTML = lines.map(function (l) { return '<li>' + escapeHtml(l) + '</li>'; }).join('');
    }

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function showStep(n) {
        step = Math.min(maxStep, Math.max(1, n));
        panels.forEach(function (p) {
            var d = parseInt(p.getAttribute('data-step'), 10);
            p.classList.toggle('hidden', d !== step);
        });
        tabs.forEach(function (t) {
            var d = parseInt(t.getAttribute('data-step-tab'), 10);
            t.setAttribute('data-active', d === step ? '1' : '0');
        });
        prevBtn.classList.toggle('hidden', step <= 1);
        nextBtn.classList.toggle('hidden', step >= maxStep);
        btn.classList.toggle('hidden', step < maxStep);
        if (step === maxStep) {
            syncPaid();
            updateRecap();
        }
    }
    function syncPaid() {
        var paid = false;
        form.querySelectorAll('input[name="plan_choice"]').forEach(function (el) {
            if (el.checked && el.getAttribute('data-paid') === '1') paid = true;
        });
        if (hint) hint.classList.toggle('hidden', !paid);
        if (btn) btn.textContent = paid ? 'Continuer vers Stripe' : 'Créer la communauté';
    }
    form.querySelectorAll('input[name="plan_choice"]').forEach(function (el) {
        el.addEventListener('change', syncPaid);
    });

    nextBtn.addEventListener('click', function () {
        if (step === 3) {
            syncUnitsFromStep3();
            if (unitsEditor && unitsHidden) {
                unitsEditor.value = unitsHidden.value;
            }
        }
        showStep(step + 1);
    });
    prevBtn.addEventListener('click', function () { showStep(step - 1); });
    tabs.forEach(function (t) {
        t.addEventListener('click', function () {
            var n = parseInt(t.getAttribute('data-step-tab'), 10);
            if (step === 3) {
                syncUnitsFromStep3();
                if (unitsEditor && unitsHidden) {
                    unitsEditor.value = unitsHidden.value;
                }
            }
            showStep(n);
        });
    });

    if (quickFill && orbatBuilder) {
        quickFill.addEventListener('change', function () {
            if (quickFill.checked) {
                orbatBuilder.loadUnits(defaultUnits);
                if (unitsEditor) {
                    unitsEditor.value = JSON.stringify(defaultUnits, null, 2);
                }
            }
        });
    }

    if (unitsEditor && orbatBuilder) {
        unitsEditor.addEventListener('change', function () {
            try {
                var j = JSON.parse(unitsEditor.value);
                if (Array.isArray(j)) {
                    orbatBuilder.loadUnits(j);
                }
            } catch (e) { /* ignore */ }
        });
    }

    form.addEventListener('submit', function () {
        if (customCommunitySlugCb && !customCommunitySlugCb.checked && communitySlugInput) {
            communitySlugInput.value = '';
        }
        syncUnitsFromStep3();
        if (unitsEditor && unitsHidden) {
            unitsEditor.value = unitsHidden.value;
        }
        var fr = document.getElementById('founder-grade-fr');
        var us = document.getElementById('founder-grade-us');
        var sys = form.querySelector('input[name="wizard_grade_system_code"]:checked');
        if (sys && fr && us) {
            if (sys.value === 'US_CLASSIC') {
                fr.disabled = true;
                us.disabled = false;
                fr.name = '';
                us.name = 'wizard_founder_grade_id';
            } else {
                fr.disabled = false;
                us.disabled = true;
                us.name = '';
                fr.name = 'wizard_founder_grade_id';
            }
        }
    });

    var gradeRadios = form.querySelectorAll('[data-grade-system]');
    var previewFr = document.getElementById('preview-fr');
    var previewUs = document.getElementById('preview-us');
    gradeRadios.forEach(function (r) {
        r.addEventListener('change', function () {
            var us = r.value === 'US_CLASSIC';
            if (previewFr) previewFr.classList.toggle('opacity-60', us);
            if (previewUs) previewUs.classList.toggle('opacity-60', !us);
            var frSel = document.getElementById('founder-grade-fr');
            var usSel = document.getElementById('founder-grade-us');
            if (frSel && usSel) {
                frSel.classList.toggle('hidden', us);
                usSel.classList.toggle('hidden', !us);
            }
        });
    });

    var customRoleIdx = 0;
    var tplCustomRole = document.getElementById('wizard-custom-role-tpl');
    var customRolesContainer = document.getElementById('wizard-custom-roles-container');
    var btnAddCustomRole = document.getElementById('wizard-add-custom-role');
    function appendCustomRoleRow() {
        if (!tplCustomRole || !customRolesContainer) return;
        if (customRoleIdx >= 15) {
            window.alert('Maximum 15 rôles supplémentaires.');
            return;
        }
        var frag = tplCustomRole.content.cloneNode(true);
        var row = frag.querySelector('.wizard-custom-role-row');
        if (!row) return;
        var idx = customRoleIdx;
        customRoleIdx += 1;
        row.querySelectorAll('[data-role-field]').forEach(function (inp) {
            var f = inp.getAttribute('data-role-field');
            inp.name = 'wizard_custom_roles[' + idx + '][' + f + ']';
        });
        row.querySelectorAll('.custom-perm-cb').forEach(function (cb) {
            cb.name = 'wizard_custom_roles[' + idx + '][perms][]';
            cb.value = cb.getAttribute('data-perm-slug') || '';
        });
        var rm = row.querySelector('[data-remove-row]');
        if (rm) rm.addEventListener('click', function () { row.remove(); });
        customRolesContainer.appendChild(frag);
    }
    if (btnAddCustomRole) btnAddCustomRole.addEventListener('click', appendCustomRoleRow);

    form.querySelectorAll('input[type="file"][name="wizard_logo_file"], input[type="file"][name="wizard_public_banner_file"]').forEach(function (input) {
        input.addEventListener('change', function () {
            var wrap = form.querySelector('.wizard-img-preview[data-preview-for="' + input.name + '"]');
            if (!wrap || !input.files || !input.files[0]) return;
            var url = URL.createObjectURL(input.files[0]);
            var img = wrap.querySelector('img');
            if (img) img.src = url;
            wrap.classList.remove('hidden');
        });
    });

    showStep(1);
})();
</script>
