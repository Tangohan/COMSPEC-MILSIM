<?php
declare(strict_types=1);

$presetMeta = $presetMeta ?? [];
$customPresetKits = $customPresetKits ?? [];
$allPermissions = $allPermissions ?? [];
$roles = $roles ?? [];
$presetsPreviewUrl = isset($presetsPreviewUrl) ? (string) $presetsPreviewUrl : url('back-office/roles/presets/preview');

$err = \App\Core\Session::getFlash('error');
$ok = \App\Core\Session::getFlash('success');
?>
<div class="mx-auto w-full min-w-0 max-w-6xl space-y-6 px-4 pb-10 pt-8 sm:px-6 lg:pb-12 lg:pt-10">
    <header class="relative overflow-hidden rounded-[2rem] border border-slate-200/90 bg-white p-6 shadow-sm ring-1 ring-slate-900/[0.04] md:p-8">
        <div class="pointer-events-none absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-indigo-600/85 via-sky-500/35 to-transparent" aria-hidden="true"></div>
        <div class="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
            <div class="min-w-0">
                <p class="text-[10px] font-black uppercase tracking-[0.32em] text-slate-500">Rôles communauté</p>
                <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-900 md:text-3xl">Profils de permissions</h1>
                <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-600">
                    Choisissez un rôle et un profil, puis consultez le <strong class="font-semibold text-slate-800">récapitulatif des changements</strong> avant d’appliquer.
                    Les profils ne contiennent jamais les habilitations réservées à l’administration de <strong class="font-semibold text-slate-800">l’ensemble du site</strong>.
                </p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                <a href="<?= htmlspecialchars(url('back-office/roles'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex min-h-[2.75rem] items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-800 transition hover:border-slate-300 hover:bg-slate-50">← Liste des rôles</a>
                <a href="<?= htmlspecialchars(url('back-office'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex min-h-[2.75rem] items-center justify-center rounded-xl bg-slate-900 px-4 text-sm font-bold text-white shadow-sm transition hover:bg-slate-800">Back-office</a>
            </div>
        </div>
    </header>

    <?php if ($err): ?>
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-950 shadow-sm" role="alert"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($ok): ?>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-950 shadow-sm" role="status"><?= htmlspecialchars($ok, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="rounded-2xl border border-amber-200/90 bg-amber-50/95 px-5 py-4 shadow-sm sm:px-6">
        <h2 class="text-sm font-black uppercase tracking-wide text-amber-950">Toujours exclus des profils automatiques</h2>
        <p class="mt-2 text-sm leading-relaxed text-amber-950/90">Aucun profil ci-dessous n’accorde les habilitations réservées à la maintenance de la plateforme pour toutes les communautés, ni la modération forum au niveau global.</p>
    </div>

    <?php if (empty($roles)): ?>
        <div class="rounded-2xl border border-slate-200 bg-slate-50/80 px-5 py-8 text-center text-sm text-slate-600">
            Aucun rôle communauté ou opérationnel. Créez d’abord des rôles (ou exécutez les migrations).
        </div>
    <?php else: ?>
        <form method="post" action="<?= htmlspecialchars(url('back-office/roles/presets/apply'), ENT_QUOTES, 'UTF-8') ?>" id="preset-apply-form" class="space-y-6">
            <?= \App\Core\Csrf::field() ?>

            <div class="grid gap-6 lg:grid-cols-12 lg:items-stretch">
                <div class="flex flex-col rounded-[1.75rem] border border-slate-200/90 bg-white p-5 shadow-sm ring-1 ring-slate-900/[0.03] sm:p-6 lg:col-span-4">
                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-900 text-sm font-black text-white">1</span>
                    <h2 class="mt-3 text-sm font-black uppercase tracking-wide text-slate-900">Rôle à configurer</h2>
                    <p class="mt-2 text-xs leading-relaxed text-slate-600">Rôles de votre communauté ou opérationnels (hors plateforme).</p>
                    <label for="role_id" class="sr-only">Rôle</label>
                    <select name="role_id" id="role_id" required class="<?= htmlspecialchars(bo_select_class('mt-4 w-full rounded-xl border-slate-300 bg-white text-sm font-semibold text-slate-900'), ENT_QUOTES, 'UTF-8') ?>">
                        <option value="">— Choisir un rôle —</option>
                        <?php foreach ($roles as $r):
                            $rid = (int) $r['id'];
                            $layer = (string) ($r['role_layer'] ?? '');
                            $layerFr = $layer === 'intra' ? 'Opérationnel' : 'Communauté';
                            $locked = !empty($r['is_locked']);
                            ?>
                            <option value="<?= $rid ?>" <?= $locked ? 'disabled' : '' ?>><?= htmlspecialchars($r['name'] ?? '', ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($layerFr, ENT_QUOTES, 'UTF-8') ?>)<?= $locked ? ' — verrouillé' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="mt-3 text-[11px] leading-snug text-slate-500">Les rôles verrouillés ne sont pas modifiables ici.</p>
                </div>

                <div class="flex min-w-0 flex-col rounded-[1.75rem] border border-slate-200/90 bg-white p-5 shadow-sm ring-1 ring-slate-900/[0.03] sm:p-6 lg:col-span-8">
                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-900 text-sm font-black text-white">2</span>
                    <h2 class="mt-3 text-sm font-black uppercase tracking-wide text-slate-900">Profil à appliquer</h2>
                    <p class="mt-2 text-xs leading-relaxed text-slate-600">Chaque carte remplace <strong class="font-semibold text-slate-800">intégralement</strong> les habilitations du rôle (pas de fusion avec l’existant).</p>
                    <div class="mt-5 grid min-w-0 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <?php foreach ($presetMeta as $meta):
                            $pid = (string) ($meta['id'] ?? '');
                            if ($pid === '') {
                                continue;
                            }
                            $plab = (string) ($meta['label'] ?? $pid);
                            $pdesc = (string) ($meta['description'] ?? '');
                            ?>
                            <label class="flex min-w-0 cursor-pointer gap-3 rounded-2xl border border-slate-200 bg-slate-50/60 p-4 transition hover:border-sky-400/80 hover:bg-sky-50/40 has-[:checked]:border-sky-600 has-[:checked]:bg-sky-50/70 has-[:checked]:ring-2 has-[:checked]:ring-sky-200">
                                <input type="radio" name="preset_id" value="<?= htmlspecialchars($pid, ENT_QUOTES, 'UTF-8') ?>" required class="mt-1 h-4 w-4 shrink-0 border-slate-300 text-sky-600 focus:ring-sky-500">
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-bold text-slate-900"><?= htmlspecialchars($plab, ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="mt-1 block text-xs leading-snug text-slate-600"><?= htmlspecialchars($pdesc, ENT_QUOTES, 'UTF-8') ?></span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                        <?php foreach ($customPresetKits as $kit):
                            $kid = (string) ($kit['id'] ?? '');
                            if ($kid === '') {
                                continue;
                            }
                            $kLabel = (string) ($kit['label'] ?? $kid);
                            $kDesc = (string) ($kit['description'] ?? '');
                            $kCount = is_array($kit['permission_ids'] ?? null) ? count($kit['permission_ids']) : 0;
                            ?>
                            <label class="flex min-w-0 cursor-pointer gap-3 rounded-2xl border border-violet-200 bg-violet-50/50 p-4 transition hover:border-violet-400 hover:bg-violet-50/80 has-[:checked]:border-violet-600 has-[:checked]:bg-violet-50 has-[:checked]:ring-2 has-[:checked]:ring-violet-200">
                                <input type="radio" name="preset_id" value="<?= htmlspecialchars('custom:' . $kid, ENT_QUOTES, 'UTF-8') ?>" required class="mt-1 h-4 w-4 shrink-0 border-slate-300 text-violet-600 focus:ring-violet-500">
                                <span class="min-w-0 flex-1">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-violet-900">Kit perso</span>
                                    <span class="mt-1 block text-sm font-bold text-slate-900"><?= htmlspecialchars($kLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="mt-1 block text-xs leading-snug text-slate-600"><?= htmlspecialchars($kDesc !== '' ? $kDesc : 'Kit personnalisé de permissions.', ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="mt-1.5 block text-[11px] font-semibold text-violet-800"><?= (int) $kCount ?> droits inclus</span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <section class="overflow-hidden rounded-[1.75rem] border border-dashed border-slate-300/90 bg-slate-50/80 p-5 shadow-inner sm:p-6 md:p-8" aria-labelledby="preset-preview-heading">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                    <div class="min-w-0">
                        <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-900 text-sm font-black text-white">3</span>
                        <h2 id="preset-preview-heading" class="mt-3 text-sm font-black uppercase tracking-wide text-slate-900">Récapitulatif avant application</h2>
                        <p class="mt-2 max-w-xl text-xs leading-relaxed text-slate-600">Calculez les <strong class="font-semibold text-slate-800">ajouts</strong> et <strong class="font-semibold text-slate-800">retraits</strong> par rapport à l’état actuel du rôle. Vous pourrez confirmer à l’étape suivante.</p>
                    </div>
                    <button type="button" id="btn-load-preview" class="inline-flex min-h-[2.75rem] w-full shrink-0 items-center justify-center rounded-xl bg-indigo-700 px-6 text-sm font-black text-white shadow-sm transition hover:bg-indigo-800 disabled:pointer-events-none disabled:opacity-45 lg:w-auto">
                        Afficher le récapitulatif
                    </button>
                </div>

                <div id="preview-status" class="mt-4 hidden text-sm font-semibold" role="status"></div>
                <div id="preview-panel" class="mt-5 hidden min-w-0 space-y-5"></div>
            </section>

            <div class="rounded-2xl border border-rose-200/90 bg-rose-50/90 px-5 py-4 shadow-sm sm:px-6">
                <p class="text-sm font-black uppercase tracking-wide text-rose-950">Rappel important</p>
                <p class="mt-2 text-sm leading-relaxed text-rose-900/95">Après confirmation, les membres qui ont ce rôle disposent immédiatement du nouveau jeu de droits. Vérifiez la fiche du rôle après application.</p>
            </div>

            <div class="sticky bottom-0 z-10 -mx-4 border-t border-slate-200/80 bg-white/95 px-4 py-4 backdrop-blur supports-[backdrop-filter]:bg-white/80 sm:static sm:mx-0 sm:flex sm:flex-wrap sm:items-center sm:justify-between sm:gap-3 sm:border-0 sm:bg-transparent sm:px-0 sm:py-0 sm:backdrop-blur-none">
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                    <button type="button" id="btn-open-confirm" disabled class="inline-flex min-h-[2.75rem] w-full items-center justify-center rounded-xl bg-slate-900 px-6 text-sm font-black text-white shadow-md transition hover:bg-slate-800 disabled:pointer-events-none disabled:opacity-40 sm:w-auto">
                        Continuer vers la confirmation…
                    </button>
                    <a href="<?= htmlspecialchars(url('back-office/roles'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex min-h-[2.75rem] w-full items-center justify-center rounded-xl border border-slate-300 bg-white px-6 text-sm font-bold text-slate-700 transition hover:bg-slate-50 sm:w-auto">Annuler</a>
                </div>
            </div>

            <dialog id="preset-confirm-dialog" class="max-h-[min(90dvh,42rem)] w-[calc(100%-1.5rem)] max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white p-0 shadow-2xl backdrop:bg-slate-900/50 sm:w-full">
                <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-4 sm:px-6">
                    <h2 class="text-lg font-black text-slate-900">Confirmer l’application du profil</h2>
                    <p class="mt-1 text-xs text-slate-600">Cette action remplace toutes les habilitations du rôle sélectionné.</p>
                </div>
                <div id="dialog-summary-body" class="max-h-[min(50vh,22rem)] space-y-3 overflow-y-auto px-5 py-4 text-sm leading-relaxed text-slate-700 sm:px-6"></div>
                <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 bg-slate-50/90 px-5 py-4 sm:px-6">
                    <button type="button" id="dialog-cancel" class="inline-flex min-h-[2.5rem] items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 transition hover:bg-slate-50">Retour</button>
                    <button type="submit" id="dialog-confirm-submit" class="inline-flex min-h-[2.5rem] items-center justify-center rounded-xl bg-emerald-700 px-5 text-sm font-black text-white transition hover:bg-emerald-800">Confirmer et appliquer</button>
                </div>
            </dialog>
        </form>

        <section class="overflow-hidden rounded-[1.75rem] border border-violet-200/90 bg-gradient-to-br from-violet-50/90 via-white to-white p-5 shadow-sm ring-1 ring-violet-900/[0.05] sm:p-6 md:p-8" aria-labelledby="kits-perso-heading">
            <div class="flex flex-col gap-3 border-b border-violet-200/60 pb-5 sm:flex-row sm:items-end sm:justify-between">
                <div class="min-w-0">
                    <p class="text-[10px] font-black uppercase tracking-[0.28em] text-violet-800/90">Hors application directe</p>
                    <h2 id="kits-perso-heading" class="mt-1 text-lg font-black tracking-tight text-slate-900">Kits personnalisés</h2>
                    <p class="mt-2 max-w-2xl text-sm text-slate-600">Créez des jeux de droits réutilisables (jusqu’à 24), puis sélectionnez-les comme un profil à l’étape 2.</p>
                </div>
                <p class="shrink-0 rounded-xl border border-violet-200 bg-white/80 px-3 py-2 text-center text-xs font-bold text-violet-900"><?= count($customPresetKits) ?> / 24 kits</p>
            </div>

            <form method="post" action="<?= htmlspecialchars(url('back-office/roles/presets/kits/save'), ENT_QUOTES, 'UTF-8') ?>" class="mt-6 space-y-4">
                <?= \App\Core\Csrf::field() ?>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="kit_label" class="mb-1.5 block text-[11px] font-black uppercase tracking-wide text-slate-600">Nom du kit</label>
                        <input type="text" id="kit_label" name="kit_label" maxlength="90" required class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition focus:border-violet-500 focus:ring-2 focus:ring-violet-500/15" placeholder="Ex. : Cellule OPS">
                    </div>
                    <div>
                        <label for="kit_description" class="mb-1.5 block text-[11px] font-black uppercase tracking-wide text-slate-600">Description</label>
                        <input type="text" id="kit_description" name="kit_description" maxlength="180" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition focus:border-violet-500 focus:ring-2 focus:ring-violet-500/15" placeholder="Courte phrase explicative">
                    </div>
                </div>
                <details class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <summary class="cursor-pointer text-sm font-bold text-slate-800">Sélectionner les droits <span class="font-normal text-slate-500">(<?= count($allPermissions) ?> disponibles)</span></summary>
                    <div class="mt-4 max-h-64 space-y-1.5 overflow-y-auto overscroll-contain pr-1">
                        <?php foreach ($allPermissions as $perm): ?>
                            <label class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-slate-100 bg-slate-50/80 px-3 py-2 text-xs text-slate-700 transition hover:border-violet-200 hover:bg-violet-50/30">
                                <input type="checkbox" name="kit_permission_ids[]" value="<?= (int) ($perm['id'] ?? 0) ?>" class="mt-0.5 rounded border-slate-300 text-violet-600 focus:ring-violet-500">
                                <span class="min-w-0">
                                    <span class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($perm['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="mt-0.5 block text-[11px] text-slate-500"><?= htmlspecialchars((string) ($perm['module'] ?? ''), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) ($perm['slug'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </details>
                <button type="submit" class="inline-flex min-h-[2.75rem] items-center justify-center rounded-xl bg-violet-700 px-6 text-sm font-black text-white shadow-sm transition hover:bg-violet-800">Enregistrer le kit</button>
            </form>

            <?php if (!empty($customPresetKits)): ?>
                <div class="mt-8 border-t border-violet-200/60 pt-6">
                    <p class="text-[11px] font-black uppercase tracking-wide text-violet-900">Supprimer un kit</p>
                    <ul class="mt-3 flex flex-wrap gap-2">
                        <?php foreach ($customPresetKits as $kit): ?>
                            <?php $kid = (string) ($kit['id'] ?? '');
                            if ($kid === '') {
                                continue;
                            } ?>
                            <li>
                                <form method="post" action="<?= htmlspecialchars(url('back-office/roles/presets/kits/delete'), ENT_QUOTES, 'UTF-8') ?>" class="inline" onsubmit="return confirm('Supprimer ce kit personnalisé ?');">
                                    <?= \App\Core\Csrf::field() ?>
                                    <input type="hidden" name="kit_id" value="<?= htmlspecialchars($kid, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="rounded-xl border border-rose-200 bg-white px-3 py-2 text-left text-xs font-bold text-rose-800 transition hover:bg-rose-50">Supprimer « <?= htmlspecialchars((string) ($kit['label'] ?? $kid), ENT_QUOTES, 'UTF-8') ?> »</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </section>

        <script>
(function () {
    var previewUrl = <?= json_encode($presetsPreviewUrl, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var form = document.getElementById('preset-apply-form');
    var roleEl = document.getElementById('role_id');
    var btnPreview = document.getElementById('btn-load-preview');
    var btnConfirm = document.getElementById('btn-open-confirm');
    var panel = document.getElementById('preview-panel');
    var statusEl = document.getElementById('preview-status');
    var dialog = document.getElementById('preset-confirm-dialog');
    var dialogBody = document.getElementById('dialog-summary-body');
    var dialogCancel = document.getElementById('dialog-cancel');

    var lastPreview = null;

    function getPresetId() {
        var r = form.querySelector('input[name="preset_id"]:checked');
        return r ? r.value : '';
    }

    function moduleLabel(map, key) {
        return map[key] || map['autre'] || key;
    }

    function renderListGrouped(byModule, moduleLabels) {
        var keys = Object.keys(byModule).sort();
        if (!keys.length) {
            return '<p class="text-sm text-slate-500 italic">Aucune entrée dans cette liste.</p>';
        }
        var html = '';
        keys.forEach(function (mod) {
            var label = moduleLabel(moduleLabels, mod);
            var items = byModule[mod];
            html += '<details class="group mb-2 overflow-hidden rounded-xl border border-slate-200 bg-white">';
            html += '<summary class="flex cursor-pointer select-none items-center justify-between bg-slate-50/90 px-4 py-3 text-sm font-bold text-slate-800 hover:bg-slate-100">';
            html += '<span>' + escapeHtml(label) + ' <span class="font-normal text-slate-500">(' + items.length + ')</span></span>';
            html += '<span class="text-xs text-slate-400 transition-transform group-open:rotate-180">▼</span>';
            html += '</summary>';
            html += '<ul class="divide-y divide-slate-100 px-4 py-2 text-sm text-slate-700">';
            items.forEach(function (it) {
                html += '<li class="py-2">';
                html += '<span class="font-medium text-slate-900">' + escapeHtml(it.name) + '</span>';
                if (it.slug) {
                    html += '<details class="mt-1"><summary class="cursor-pointer text-[11px] text-slate-500 hover:text-slate-700">Référence technique</summary>';
                    html += '<code class="mt-1 block break-all rounded bg-slate-100 px-2 py-1 text-[11px] text-slate-600">' + escapeHtml(it.slug) + '</code></details>';
                }
                html += '</li>';
            });
            html += '</ul></details>';
        });
        return html;
    }

    function escapeHtml(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function invalidatePreview() {
        lastPreview = null;
        panel.classList.add('hidden');
        panel.innerHTML = '';
        statusEl.classList.add('hidden');
        btnConfirm.disabled = true;
    }

    roleEl.addEventListener('change', invalidatePreview);
    form.querySelectorAll('input[name="preset_id"]').forEach(function (r) {
        r.addEventListener('change', invalidatePreview);
    });

    btnPreview.addEventListener('click', function () {
        var rid = parseInt(roleEl.value, 10);
        var pid = getPresetId();
        if (!rid || !pid) {
            statusEl.className = 'mt-4 text-sm font-semibold text-amber-800';
            statusEl.classList.remove('hidden');
            statusEl.textContent = 'Choisissez d’abord un rôle et un profil.';
            return;
        }
        statusEl.classList.remove('hidden');
        statusEl.className = 'mt-4 text-sm font-semibold text-slate-600';
        statusEl.textContent = 'Calcul du récapitulatif…';
        btnPreview.disabled = true;

        var u = previewUrl + (previewUrl.indexOf('?') >= 0 ? '&' : '?') + 'role_id=' + encodeURIComponent(rid) + '&preset_id=' + encodeURIComponent(pid);
        fetch(u, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (res) {
                return res.text().then(function (text) {
                    try {
                        return { httpOk: res.ok, j: JSON.parse(text) };
                    } catch (e) {
                        return { httpOk: false, j: { ok: false, error: 'Réponse inattendue du serveur.' } };
                    }
                });
            })
            .then(function (pack) {
                btnPreview.disabled = false;
                var j = pack.j;
                if (!j || !j.ok) {
                    statusEl.className = 'mt-4 text-sm font-semibold text-rose-800';
                    statusEl.textContent = j.error || 'Impossible de charger le récapitulatif.';
                    panel.classList.add('hidden');
                    btnConfirm.disabled = true;
                    return;
                }
                lastPreview = { roleId: rid, presetId: pid, payload: j };
                statusEl.className = 'mt-4 text-sm font-semibold text-emerald-800';
                statusEl.textContent = 'Récapitulatif à jour pour « ' + j.role_name + ' » et le profil « ' + j.preset_label + ' ».';

                var d = j.diff;
                var ml = j.module_labels || {};

                var stats = '<div class="grid grid-cols-2 gap-3 sm:grid-cols-4">';
                stats += '<div class="rounded-xl border border-slate-200 bg-white p-4 text-center shadow-sm"><p class="text-2xl font-black text-slate-900">' + d.current_total + '</p><p class="mt-1 text-[11px] font-bold uppercase tracking-wide text-slate-500">Avant</p></div>';
                stats += '<div class="rounded-xl border border-emerald-200 bg-emerald-50/90 p-4 text-center shadow-sm"><p class="text-2xl font-black text-emerald-800">' + d.added_count + '</p><p class="mt-1 text-[11px] font-bold uppercase tracking-wide text-emerald-800/80">Ajouts</p></div>';
                stats += '<div class="rounded-xl border border-rose-200 bg-rose-50/90 p-4 text-center shadow-sm"><p class="text-2xl font-black text-rose-800">' + d.removed_count + '</p><p class="mt-1 text-[11px] font-bold uppercase tracking-wide text-rose-800/80">Retraits</p></div>';
                stats += '<div class="rounded-xl border border-slate-200 bg-white p-4 text-center shadow-sm"><p class="text-2xl font-black text-indigo-900">' + d.preset_total + '</p><p class="mt-1 text-[11px] font-bold uppercase tracking-wide text-slate-500">Après</p></div>';
                stats += '</div>';

                if (j.preset_description) {
                    stats += '<p class="mt-4 rounded-xl border border-slate-200/80 bg-slate-100/60 p-3 text-xs text-slate-600"><span class="font-bold text-slate-800">Contenu du profil :</span> ' + escapeHtml(j.preset_description) + '</p>';
                }

                var detail = '<div class="grid min-w-0 gap-6 lg:grid-cols-2">';
                detail += '<div class="min-w-0"><h3 class="mb-2 text-[11px] font-black uppercase tracking-widest text-emerald-800">Habilitations ajoutées</h3>' + renderListGrouped(d.added_by_module, ml) + '</div>';
                detail += '<div class="min-w-0"><h3 class="mb-2 text-[11px] font-black uppercase tracking-widest text-rose-800">Habilitations retirées</h3>' + renderListGrouped(d.removed_by_module, ml) + '</div>';
                detail += '</div>';

                if (d.unchanged_count > 0) {
                    detail += '<p class="mt-4 text-center text-xs text-slate-500">' + d.unchanged_count + ' habilitation(s) déjà présente(s) et conservée(s) sans changement.</p>';
                }

                panel.innerHTML = stats + detail;
                panel.classList.remove('hidden');
                btnConfirm.disabled = false;
            })
            .catch(function () {
                btnPreview.disabled = false;
                statusEl.className = 'mt-4 text-sm font-semibold text-rose-800';
                statusEl.textContent = 'Erreur réseau. Réessayez.';
                btnConfirm.disabled = true;
            });
    });

    btnConfirm.addEventListener('click', function () {
        var rid = parseInt(roleEl.value, 10);
        var pid = getPresetId();
        if (!lastPreview || lastPreview.roleId !== rid || lastPreview.presetId !== pid) {
            statusEl.classList.remove('hidden');
            statusEl.className = 'mt-4 text-sm font-semibold text-amber-800';
            statusEl.textContent = 'Le rôle ou le profil a changé : affichez à nouveau le récapitulatif.';
            return;
        }
        var j = lastPreview.payload;
        var d = j.diff;
        var html = '';
        html += '<p><span class="font-bold text-slate-900">Rôle :</span> ' + escapeHtml(j.role_name) + '</p>';
        html += '<p><span class="font-bold text-slate-900">Profil :</span> ' + escapeHtml(j.preset_label) + '</p>';
        html += '<ul class="mt-2 list-inside list-disc space-y-1 text-slate-700">';
        html += '<li><strong class="text-emerald-800">' + d.added_count + '</strong> habilitation(s) seront <strong>ajoutées</strong></li>';
        html += '<li><strong class="text-rose-800">' + d.removed_count + '</strong> habilitation(s) seront <strong>retirées</strong></li>';
        html += '<li><strong class="text-slate-800">' + d.unchanged_count + '</strong> resteront inchangées</li>';
        html += '<li>Total après application : <strong class="text-slate-900">' + d.preset_total + '</strong></li>';
        html += '</ul>';
        html += '<p class="mt-3 text-xs text-slate-500">En confirmant, vous acceptez de remplacer l’ensemble des droits actuels de ce rôle.</p>';
        dialogBody.innerHTML = html;
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            window.alert('Votre navigateur ne prend pas en charge les fenêtres de confirmation intégrées. Utilisez un navigateur récent.');
        }
    });

    dialogCancel.addEventListener('click', function () {
        dialog.close();
    });
})();
        </script>
    <?php endif; ?>
</div>
