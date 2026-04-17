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
        'label' => 'Maintenance standard',
        'title' => 'Maintenance globale en cours',
        'message' => "Nos équipes déploient une mise à jour stratégique.\nLe service revient très bientôt.",
    ],
    'security' => [
        'label' => 'Incident sécurité',
        'title' => 'Maintenance sécurité en cours',
        'message' => "Une opération de sécurisation est en cours.\nL’accès public est temporairement suspendu.",
    ],
    'infra' => [
        'label' => 'Infra / Réseau',
        'title' => 'Intervention infrastructure',
        'message' => "Maintenance réseau et infrastructure en cours.\nDes coupures temporaires sont attendues.",
    ],
    'hotfix' => [
        'label' => 'Hotfix urgent',
        'title' => 'Déploiement correctif urgent',
        'message' => "Un correctif critique est en cours de déploiement.\nMerci pour votre patience.",
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
    <div class="mb-7 flex items-end justify-between gap-4">
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
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                <?php foreach (['global' => 'Tout le site', 'route' => 'Préfixe URL', 'module' => 'Module', 'custom' => 'Personnalisé'] as $mode => $label): ?>
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
                    <input type="text" name="title" id="maint-title" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5" value="<?= htmlspecialchars((string) ($row['title'] ?? 'Maintenance globale en cours'), ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <label class="text-sm font-bold text-slate-700">Message
                    <textarea name="message" id="maint-message" rows="4" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5"><?= htmlspecialchars((string) ($row['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </label>
            </div>

            <div class="mt-4 grid gap-4 md:grid-cols-3">
                <label class="text-sm font-bold text-slate-700">UI maintenance
                    <select name="ui_variant" id="ui_variant" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5">
                        <option value="military" <?= $currentVariant === 'military' ? 'selected' : '' ?>>Military command</option>
                        <option value="minimal" <?= $currentVariant === 'minimal' ? 'selected' : '' ?>>Minimal clean</option>
                        <option value="neon" <?= $currentVariant === 'neon' ? 'selected' : '' ?>>Neon pulse</option>
                        <option value="status" <?= $currentVariant === 'status' ? 'selected' : '' ?>>Status board</option>
                    </select>
                </label>
                <label class="text-sm font-bold text-slate-700">Code maintenance
                    <input type="text" name="maintenance_code" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 font-mono text-sm" value="<?= htmlspecialchars((string) ($row['maintenance_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <label class="mt-7 inline-flex items-center gap-2 text-sm font-semibold text-slate-800">
                    <input type="checkbox" name="ui_animation" value="1" <?= $currentAnimation ? 'checked' : '' ?> class="rounded border-slate-300 text-emerald-600"> Activer les animations
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
                    <input type="checkbox" name="allow_admin_bypass" value="1" class="rounded border-slate-300 text-emerald-600" <?= ((int) ($row['allow_admin_bypass'] ?? 1)) === 1 ? 'checked' : '' ?>> Autoriser le bypass super-admin
                </label>
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
})();
</script>
