<?php

declare(strict_types=1);

$r = $maintenanceRule;
$row = $r ?? [];
$isEdit = $r !== null;
$action = $formAction ?? url('admin/maintenance');
$dt = static function (?string $v): string {
    if ($v === null || $v === '') {
        return '';
    }

    return htmlspecialchars(str_replace(' ', 'T', substr($v, 0, 16)), ENT_QUOTES, 'UTF-8');
};

$scopeRaw = trim((string) ($row['scope'] ?? 'global'));
$scopeType = 'custom';
$scopeRoute = '';
$scopeModule = '';
$scopeCustom = '';
if ($scopeRaw === '' || $scopeRaw === 'global') {
    $scopeType = 'global';
} elseif (str_starts_with($scopeRaw, 'route:')) {
    $scopeType = 'route';
    $scopeRoute = substr($scopeRaw, strlen('route:'));
} elseif (str_starts_with($scopeRaw, 'module:')) {
    $scopeType = 'module';
    $scopeModule = substr($scopeRaw, strlen('module:'));
} else {
    $scopeCustom = $scopeRaw;
}

$presets = [
    'standard' => [
        'label' => 'Mise à jour planifiée',
        'title' => 'Nous revenons bientôt',
        'message' => "Athena est momentanément fermé le temps d’une mise à jour. Nous simplifions la gestion des communautés : un accès plus clair par membre, des rôles mieux alignés, des dossiers plus simples à suivre.\n\nVos données restent en sécurité. Vous n’avez rien à faire de votre côté. Pendant cette période, le site et les outils associés restent inaccessibles.\n\nMerci de votre patience, et de la confiance que vous accordez à Athena.",
    ],
    'security' => [
        'label' => 'Sécurisation',
        'title' => 'Sécurisation en cours',
        'message' => "Nous renforçons la sécurité de la plateforme. L’accès est temporairement suspendu.\n\nVos informations restent protégées. Merci de patienter jusqu’à la réouverture.",
    ],
    'infra' => [
        'label' => 'Réseau et hébergement',
        'title' => 'Intervention réseau',
        'message' => "Une intervention sur le réseau et l’hébergement est en cours. Le portail peut rester inaccessible le temps des contrôles.\n\nNous rouvrons dès que tout est rétabli.",
    ],
    'hotfix' => [
        'label' => 'Correctif urgent',
        'title' => 'Correctif en cours',
        'message' => "Un correctif urgent est en cours. Athena sera de nouveau disponible dès qu’il sera terminé.\n\nMerci de votre patience.",
    ],
];
$currentPreset = (string) ($row['message_preset'] ?? 'standard');
if (!isset($presets[$currentPreset])) {
    $currentPreset = 'standard';
}
$currentVariant = (string) ($row['ui_variant'] ?? 'military');
$currentAnimation = ((int) ($row['ui_animation'] ?? 1)) === 1;
?>
<div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:py-10">
    <div class="pa-head mb-7 flex items-end justify-between gap-4">
        <div>
            <a href="<?= url('admin/maintenance') ?>" class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-700 hover:text-emerald-900">
                <span aria-hidden="true">←</span> Retour
            </a>
            <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-900"><?= $isEdit ? 'Refonte maintenance — édition' : 'Créer une maintenance globale' ?></h1>
            <p class="mt-2 text-sm text-slate-600">Contrôlez les accès autorisés, le message membre, les presets et l’UI animée de la page maintenance.</p>
        </div>
    </div>

    <form method="post" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>" class="space-y-7" id="maintenance-form">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="scope" id="maintenance-scope-hidden" value="<?= htmlspecialchars($scopeRaw === '' ? 'global' : $scopeRaw, ENT_QUOTES, 'UTF-8') ?>">

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-black text-slate-900">1) Portée</h2>
            <p class="mt-2 text-sm text-slate-600">Une intervention globale ferme tout le site : accueil public, authentification, dossiers, administration, Arma 3, ATAK et API. Seuls les points d’entrée techniques indispensables restent ouverts.</p>
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                <?php foreach (['global' => 'Tout le portail', 'route' => 'Préfixe URL', 'module' => 'Module', 'custom' => 'Personnalisé'] as $mode => $label): ?>
                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50/60">
                        <input type="radio" name="scope_mode" value="<?= $mode ?>" class="text-emerald-600" <?= $scopeType === $mode ? 'checked' : '' ?>>
                        <span class="font-semibold text-slate-800"><?= $label ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="mt-4 grid gap-3 md:grid-cols-3">
                <input type="text" id="scope-route-input" class="rounded-lg border border-slate-200 px-3 py-2 font-mono text-sm" value="<?= htmlspecialchars($scopeRoute, ENT_QUOTES, 'UTF-8') ?>" placeholder="/forum">
                <input type="text" id="scope-module-input" class="rounded-lg border border-slate-200 px-3 py-2 font-mono text-sm" value="<?= htmlspecialchars($scopeModule, ENT_QUOTES, 'UTF-8') ?>" placeholder="forum">
                <input type="text" id="scope-custom-input" class="rounded-lg border border-slate-200 px-3 py-2 font-mono text-sm" value="<?= htmlspecialchars($scopeCustom, ENT_QUOTES, 'UTF-8') ?>" placeholder="scope custom">
            </div>
            <p class="mt-3 rounded-lg bg-slate-50 px-3 py-2 font-mono text-xs text-slate-700">scope: <span id="scope-preview"><?= htmlspecialchars($scopeRaw === '' ? 'global' : $scopeRaw, ENT_QUOTES, 'UTF-8') ?></span></p>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-black text-slate-900">2) Message préfait + contenu</h2>
            <div class="mt-4 flex flex-wrap gap-2" id="preset-buttons">
                <?php foreach ($presets as $key => $preset): ?>
                    <button type="button" data-preset-key="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" data-title="<?= htmlspecialchars($preset['title'], ENT_QUOTES, 'UTF-8') ?>" data-message="<?= htmlspecialchars($preset['message'], ENT_QUOTES, 'UTF-8') ?>" class="rounded-full border px-3 py-1.5 text-xs font-semibold <?= $currentPreset === $key ? 'border-emerald-600 bg-emerald-50 text-emerald-900' : 'border-slate-300 text-slate-700' ?>">
                        <?= htmlspecialchars($preset['label'], ENT_QUOTES, 'UTF-8') ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <input type="hidden" name="message_preset" id="message_preset" value="<?= htmlspecialchars($currentPreset, ENT_QUOTES, 'UTF-8') ?>">

            <div class="mt-5 grid gap-4">
                <label class="text-sm font-bold text-slate-700">Titre
                    <input type="text" name="title" id="maint-title" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5" value="<?= htmlspecialchars((string) ($row['title'] ?? 'Nous revenons bientôt'), ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <label class="text-sm font-bold text-slate-700">Message
                    <textarea name="message" id="maint-message" rows="6" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5"><?= htmlspecialchars((string) ($row['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                    <span class="mt-1.5 block text-xs font-medium text-slate-500">Markdown pris en charge : titres, listes, gras, liens, séparateur <code>---</code>, libellés FR / EN.</span>
                </label>
            </div>

            <div class="mt-6">
                <p class="text-sm font-bold text-slate-700">Apparence de la page</p>
                <p class="mt-1 text-xs text-slate-500">Choisissez un style : l’aperçu à droite montre la page telle que les membres la verront, avec votre titre et votre message.</p>
                <div class="mt-3 mt-themes-layout">
                    <div class="mt-themes-grid" role="radiogroup" aria-label="Apparence de la page de maintenance">
                        <?php foreach (\App\Support\MaintenanceThemes::THEMES as $themeKey => $theme): ?>
                            <label class="mt-theme">
                                <input type="radio" name="ui_variant" value="<?= htmlspecialchars($themeKey, ENT_QUOTES, 'UTF-8') ?>" class="sr-only" data-maint-preview <?= $currentVariant === $themeKey ? 'checked' : '' ?>>
                                <span class="mt-theme__thumb mt-theme__thumb--<?= htmlspecialchars($themeKey, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true">
                                    <span class="mt-theme__line mt-theme__line--title"></span>
                                    <span class="mt-theme__line"></span>
                                    <span class="mt-theme__line mt-theme__line--short"></span>
                                    <span class="mt-theme__btn"></span>
                                </span>
                                <span class="mt-theme__name"><?= htmlspecialchars($theme['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="mt-theme__hint"><?= htmlspecialchars($theme['hint'], ENT_QUOTES, 'UTF-8') ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div>
                        <div class="mt-preview" data-maint-preview-frame-wrap>
                            <iframe title="Aperçu de la page de maintenance" class="mt-preview__frame" data-maint-preview-frame loading="lazy" src="<?= htmlspecialchars(url('admin/maintenance/apercu') . '?' . http_build_query(['variant' => $currentVariant, 'title' => (string) ($row['title'] ?? ''), 'message' => (string) ($row['message'] ?? ''), 'animation' => $currentAnimation ? '1' : '0']), ENT_QUOTES, 'UTF-8') ?>"></iframe>
                        </div>
                        <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                            <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-800">
                                <input type="checkbox" name="ui_animation" value="1" <?= $currentAnimation ? 'checked' : '' ?> class="rounded border-slate-300 text-emerald-600" data-maint-preview> Animations (apparition du texte, radar, voyants)
                            </label>
                            <a href="#" class="text-sm font-semibold text-emerald-700 hover:text-emerald-900" target="_blank" rel="noopener" data-maint-preview-open>Ouvrir l’aperçu en grand ↗</a>
                        </div>
                    </div>
                </div>
                <label class="mt-5 block max-w-sm text-sm font-bold text-slate-700">Code maintenance <span class="font-medium text-slate-500">(interne, jamais affiché aux membres)</span>
                    <input type="text" name="maintenance_code" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 font-mono text-sm" value="<?= htmlspecialchars((string) ($row['maintenance_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </label>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-black text-slate-900">3) Qui est autorisé pendant la maintenance</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <label class="text-sm font-bold text-slate-700">IPs autorisées
                    <input type="text" name="allowed_ips" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 font-mono text-sm" value="<?= htmlspecialchars((string) ($row['allowed_ips'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="127.0.0.1, 203.0.113.10">
                </label>
                <label class="text-sm font-bold text-slate-700">Rôles autorisés
                    <input type="text" name="allowed_roles" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 font-mono text-sm" value="<?= htmlspecialchars((string) ($row['allowed_roles'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="moderateur, admin">
                </label>
                <label class="text-sm font-bold text-slate-700 md:col-span-2">IDs utilisateurs autorisés (nouveau)
                    <input type="text" name="allowed_user_ids" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 font-mono text-sm" value="<?= htmlspecialchars((string) ($row['allowed_user_ids'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="12, 45, 87">
                </label>
                <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-800">
                    <input type="checkbox" name="allow_admin_bypass" value="1" class="rounded border-slate-300 text-emerald-600" <?= ((int) ($row['allow_admin_bypass'] ?? ($r === null ? 1 : 0))) === 1 ? 'checked' : '' ?>> Laisser les administrateurs plateforme accéder au site pendant la maintenance
                </label>
                <p class="text-xs text-slate-500 md:col-span-2">Maintenance globale : toutes les autres sessions sont fermées (site et comptes connectés). Les gestionnaires de communauté sont déconnectés comme les membres ; seuls les administrateurs plateforme, les IP, rôles et comptes autorisés ci-dessus restent connectés. Le jeu (app Athena du téléphone, SSE) affiche « Athena en maintenance ».</p>
                <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-800">
                    <input type="checkbox" name="is_enabled" value="1" class="rounded border-slate-300 text-emerald-600" <?= ($r !== null && !empty($r['is_enabled'])) ? 'checked' : '' ?>> Règle active
                </label>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-black text-slate-900">4) Information par mail aux membres (optionnel)</h2>
            <p class="mt-1 text-sm text-slate-600">Préparez le message opérationnel ; l’envoi est piloté par vos procédures d’exploitation/worker.</p>
            <div class="mt-4 space-y-4">
                <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-800">
                    <input type="checkbox" name="notify_members_by_email" value="1" class="rounded border-slate-300 text-emerald-600" <?= ((int) ($row['notify_members_by_email'] ?? 0)) === 1 ? 'checked' : '' ?>> Préparer une diffusion e-mail membres
                </label>
                <label class="text-sm font-bold text-slate-700">Sujet mail
                    <input type="text" name="notify_email_subject" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5" value="<?= htmlspecialchars((string) ($row['notify_email_subject'] ?? 'Information maintenance plateforme'), ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <label class="text-sm font-bold text-slate-700">Message mail
                    <textarea name="notify_email_message" rows="4" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5"><?= htmlspecialchars((string) ($row['notify_email_message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </label>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-black text-slate-900">5) Fenêtre & HTTP</h2>
            <p class="mt-2 text-sm text-slate-600">Tant qu’une <strong class="font-semibold text-slate-800">date de début</strong> est renseignée, le blocage public (y compris la page de connexion) ne commence qu’à partir de ce moment. Laissez début et fin vides pour appliquer la maintenance dès que la règle est activée, sans limite de fin.</p>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <label class="text-sm font-bold text-slate-700">Début
                    <input type="datetime-local" name="starts_at" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" value="<?= $dt($isEdit ? ($r['starts_at'] ?? null) : null) ?>">
                </label>
                <label class="text-sm font-bold text-slate-700">Fin
                    <input type="datetime-local" name="ends_at" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" value="<?= $dt($isEdit ? ($r['ends_at'] ?? null) : null) ?>">
                </label>
                <label class="text-sm font-bold text-slate-700">Code HTTP
                    <input type="number" name="http_status" min="100" max="599" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" value="<?= (int) ($row['http_status'] ?? 503) ?>">
                </label>
                <label class="text-sm font-bold text-slate-700">Priorité
                    <input type="number" name="priority" min="0" max="999999" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" value="<?= (int) ($row['priority'] ?? 100) ?>">
                </label>
            </div>
        </section>

        <div class="flex justify-end gap-3">
            <a href="<?= url('admin/maintenance') ?>" class="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-800">Annuler</a>
            <button type="submit" class="rounded-xl bg-slate-900 px-6 py-3 text-sm font-bold text-white hover:bg-emerald-700"><?= $isEdit ? 'Mettre à jour' : 'Créer la règle' ?></button>
        </div>
    </form>
</div>

<style>
.mt-theme { position: relative; display: grid; gap: 0.3rem; padding: 0.65rem; border: 1px solid #e2e8f0; border-radius: 0.9rem; background: #fff; cursor: pointer; transition: border-color .15s ease, box-shadow .15s ease; }
.mt-theme:hover { border-color: #94a3b8; }
.mt-theme:has(input:checked) { border-color: #059669; box-shadow: 0 0 0 3px rgba(16,185,129,.25); }
.mt-theme:has(input:focus-visible) { outline: 2px solid #059669; outline-offset: 2px; }
.mt-themes-layout { display: grid; gap: 1.25rem; }
.mt-themes-grid { display: grid; gap: 0.6rem; grid-template-columns: repeat(2, minmax(0, 1fr)); }
@media (min-width: 1100px) { .mt-themes-layout { grid-template-columns: minmax(0, 1fr) minmax(0, 1.15fr); align-items: start; } }
@media (max-width: 520px) { .mt-themes-grid { grid-template-columns: 1fr; } }
.mt-theme__thumb { position: relative; display: flex; flex-direction: column; justify-content: flex-end; gap: 0.25rem; height: 5.5rem; padding: 0.6rem; border-radius: 0.6rem; overflow: hidden; }
.mt-theme__line { display: block; width: 70%; height: 0.28rem; border-radius: 99px; background: rgba(255,255,255,.55); }
.mt-theme__line--title { width: 55%; height: 0.5rem; background: #fff; }
.mt-theme__line--short { width: 40%; }
.mt-theme__btn { display: block; width: 2.2rem; height: 0.65rem; margin-top: 0.2rem; border-radius: 0.15rem; background: #fff; }
.mt-theme__thumb--military { background: linear-gradient(180deg, rgba(5,5,5,.35), rgba(5,5,5,.92)), url('<?= htmlspecialchars(asset_url('assets/images/fog-team.jpg'), ENT_QUOTES, 'UTF-8') ?>') center / cover; }
.mt-theme__thumb--minimal { align-items: center; justify-content: center; background: #f3f5f4; }
.mt-theme__thumb--minimal::before { content: ""; position: absolute; inset: 0.75rem 1.4rem; border-radius: 0.4rem; background: #fff; box-shadow: 0 6px 16px -8px rgba(15,23,42,.35); }
.mt-theme__thumb--minimal > * { position: relative; background: #cbd5e1; }
.mt-theme__thumb--minimal .mt-theme__line--title, .mt-theme__thumb--minimal .mt-theme__btn { background: #0c1116; }
.mt-theme__thumb--neon { background: radial-gradient(circle at 75% 35%, rgba(52,211,153,.35), transparent 45%), repeating-linear-gradient(0deg, rgba(52,211,153,.12) 0 1px, transparent 1px 12px), repeating-linear-gradient(90deg, rgba(52,211,153,.12) 0 1px, transparent 1px 12px), #020806; }
.mt-theme__thumb--neon .mt-theme__line--title { background: #a7f3d0; box-shadow: 0 0 8px rgba(52,211,153,.8); }
.mt-theme__thumb--neon .mt-theme__line { background: rgba(167,243,208,.55); }
.mt-theme__thumb--neon .mt-theme__btn { background: transparent; border: 1px solid #34d399; }
.mt-theme__thumb--status { justify-content: center; background: #0b1014; }
.mt-theme__thumb--status::before { content: ""; position: absolute; inset: 0.6rem 1rem; border: 1px solid rgba(255,255,255,.14); border-radius: 0.4rem; background: repeating-linear-gradient(180deg, transparent 0 0.85rem, rgba(255,255,255,.08) 0.85rem calc(0.85rem + 1px)); }
.mt-theme__thumb--status::after { content: ""; position: absolute; right: 1.5rem; top: 1.05rem; width: 0.4rem; height: 0.4rem; border-radius: 50%; background: #fbbf24; box-shadow: 0 0.85rem 0 #60a5fa, 0 1.7rem 0 #34d399; }
.mt-theme__thumb--status > * { position: relative; margin-left: 0.6rem; }
.mt-theme__name { font-size: 0.875rem; font-weight: 800; color: #0f172a; }
.mt-theme__hint { font-size: 0.75rem; line-height: 1.35; color: #64748b; }
.mt-preview { position: relative; aspect-ratio: 16 / 10; border: 1px solid #e2e8f0; border-radius: 0.9rem; overflow: hidden; background: #0b1014; }
.mt-preview__frame { position: absolute; top: 0; left: 0; width: 200%; height: 200%; border: 0; transform: scale(0.5); transform-origin: 0 0; pointer-events: none; }
</style>
<script>
(function () {
    var form = document.getElementById('maintenance-form');
    if (!form) return;

    var hidden = document.getElementById('maintenance-scope-hidden');
    var preview = document.getElementById('scope-preview');
    var routeIn = document.getElementById('scope-route-input');
    var moduleIn = document.getElementById('scope-module-input');
    var customIn = document.getElementById('scope-custom-input');

    function selectedMode() {
        var el = form.querySelector('input[name="scope_mode"]:checked');
        return el ? el.value : 'global';
    }

    function normalizePath(p) {
        p = (p || '').trim();
        if (p === '') return '';
        if (p.charAt(0) !== '/') p = '/' + p;
        return p.replace(/\/+/, '/');
    }

    function buildScope() {
        var mode = selectedMode();
        if (mode === 'global') return 'global';
        if (mode === 'route') {
            var p = normalizePath(routeIn ? routeIn.value : '');
            return p === '' ? 'global' : ('route:' + p);
        }
        if (mode === 'module') {
            var m = (moduleIn && moduleIn.value ? moduleIn.value : '').trim().replace(/^\/+/, '').replace(/\s+/g, '');
            return m === '' ? 'global' : ('module:' + m);
        }
        var c = (customIn && customIn.value ? customIn.value : '').trim();
        return c === '' ? 'global' : c;
    }

    function syncScope() {
        var v = buildScope();
        if (hidden) hidden.value = v;
        if (preview) preview.textContent = v;
    }

    form.querySelectorAll('input[name="scope_mode"]').forEach(function (r) { r.addEventListener('change', syncScope); });
    [routeIn, moduleIn, customIn].forEach(function (el) { if (el) el.addEventListener('input', syncScope); });
    form.addEventListener('submit', syncScope);

    var presetInput = document.getElementById('message_preset');
    var titleInput = document.getElementById('maint-title');
    var messageInput = document.getElementById('maint-message');
    document.querySelectorAll('[data-preset-key]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var key = btn.getAttribute('data-preset-key') || 'standard';
            if (presetInput) presetInput.value = key;
            if (titleInput) titleInput.value = btn.getAttribute('data-title') || '';
            if (messageInput) messageInput.value = btn.getAttribute('data-message') || '';
            document.querySelectorAll('[data-preset-key]').forEach(function (other) {
                other.classList.remove('border-emerald-600', 'bg-emerald-50', 'text-emerald-900');
                other.classList.add('border-slate-300', 'text-slate-700');
            });
            btn.classList.remove('border-slate-300', 'text-slate-700');
            btn.classList.add('border-emerald-600', 'bg-emerald-50', 'text-emerald-900');
        });
    });

    syncScope();

    // Aperçu en direct : l’iframe recharge la page publique avec les valeurs en cours de saisie.
    var frame = form.querySelector('[data-maint-preview-frame]');
    var openLink = form.querySelector('[data-maint-preview-open]');
    var previewBase = <?= json_encode(url('admin/maintenance/apercu'), JSON_UNESCAPED_SLASHES) ?>;
    var endsInput = form.querySelector('input[name="ends_at"]');
    var timer = null;
    function previewUrl() {
        var variant = form.querySelector('input[name="ui_variant"]:checked');
        var anim = form.querySelector('input[name="ui_animation"]');
        var q = new URLSearchParams();
        q.set('variant', variant ? variant.value : 'military');
        q.set('title', titleInput ? titleInput.value : '');
        q.set('message', messageInput ? messageInput.value.slice(0, 1800) : '');
        q.set('animation', anim && anim.checked ? '1' : '0');
        if (endsInput && endsInput.value) q.set('ends_at', endsInput.value.replace('T', ' '));
        return previewBase + '?' + q.toString();
    }
    function refreshPreview() {
        var u = previewUrl();
        if (frame) frame.src = u;
        if (openLink) openLink.href = u;
    }
    function schedule() {
        clearTimeout(timer);
        timer = setTimeout(refreshPreview, 450);
    }
    form.querySelectorAll('[data-maint-preview]').forEach(function (el) { el.addEventListener('change', refreshPreview); });
    [titleInput, messageInput, endsInput].forEach(function (el) { if (el) el.addEventListener('input', schedule); });
    document.querySelectorAll('[data-preset-key]').forEach(function (btn) { btn.addEventListener('click', schedule); });
    if (openLink) openLink.href = previewUrl();
})();
</script>
