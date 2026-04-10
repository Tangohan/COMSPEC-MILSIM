<?php
declare(strict_types=1);

$presetMeta = $presetMeta ?? [];
$roles = $roles ?? [];
$presetsPreviewUrl = isset($presetsPreviewUrl) ? (string) $presetsPreviewUrl : url('back-office/roles/presets/preview');

$err = \App\Core\Session::getFlash('error');
$ok = \App\Core\Session::getFlash('success');
?>
<div class="max-w-5xl mx-auto px-4 sm:px-6 py-8 lg:py-12">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between mb-8">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500 mb-2">Rôles communauté</p>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Profils de permissions</h1>
            <p class="mt-2 text-slate-600 text-sm leading-relaxed max-w-2xl">
                Choisissez un rôle et un profil, puis consultez le <strong class="font-semibold text-slate-800">récapitulatif des changements</strong> avant d’appliquer.
                Les profils ne contiennent jamais les habilitations réservées à l’administration de <strong class="font-semibold text-slate-800">l’ensemble du site</strong>.
            </p>
        </div>
        <a href="<?= url('back-office/roles') ?>" class="shrink-0 text-sm font-semibold text-slate-600 hover:text-slate-900">← Liste des rôles</a>
    </div>

    <?php if ($err): ?>
        <p class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if ($ok): ?>
        <p class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 leading-relaxed"><?= htmlspecialchars($ok, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <div class="rounded-2xl border border-amber-200 bg-amber-50/80 p-5 mb-8">
        <h2 class="text-sm font-bold text-amber-950">Toujours exclus des profils automatiques</h2>
        <p class="mt-1 text-sm text-amber-950/90">Aucun profil ci-dessous n’accorde les habilitations réservées à la maintenance de la plateforme pour toutes les communautés, ni la modération forum au niveau global.</p>
    </div>

    <?php if (empty($roles)): ?>
        <p class="text-slate-500">Aucun rôle communauté ou opérationnel. Créez d’abord des rôles (ou exécutez les migrations).</p>
    <?php else: ?>
        <form method="post" action="<?= url('back-office/roles/presets/apply') ?>" id="preset-apply-form" class="space-y-8">
            <?= \App\Core\Csrf::field() ?>

            <ol class="grid gap-4 lg:gap-6 lg:grid-cols-3 mb-2">
                <li class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-slate-900 text-sm font-black text-white mb-3">1</span>
                    <h2 class="text-sm font-bold text-slate-900">Rôle à configurer</h2>
                    <p class="text-xs text-slate-500 mt-1 mb-3">Rôles de votre communauté ou opérationnels (hors plateforme).</p>
                    <label for="role_id" class="sr-only">Rôle</label>
                    <select name="role_id" id="role_id" required class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
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
                    <p class="mt-2 text-[11px] text-slate-500">Les rôles verrouillés ne sont pas modifiables ici.</p>
                </li>
                <li class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-slate-900 text-sm font-black text-white mb-3">2</span>
                    <h2 class="text-sm font-bold text-slate-900">Profil à appliquer</h2>
                    <p class="text-xs text-slate-500 mt-1 mb-4">Chaque carte remplace <strong class="font-semibold text-slate-700">intégralement</strong> les habilitations du rôle (pas de fusion avec l’existant).</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <?php foreach ($presetMeta as $meta):
                            $pid = (string) ($meta['id'] ?? '');
                            if ($pid === '') {
                                continue;
                            }
                            $plab = (string) ($meta['label'] ?? $pid);
                            $pdesc = (string) ($meta['description'] ?? '');
                            ?>
                            <label class="flex cursor-pointer gap-3 rounded-xl border border-slate-200 bg-slate-50/50 p-4 transition hover:border-blue-400 hover:bg-blue-50/30 has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50/50 has-[:checked]:ring-2 has-[:checked]:ring-blue-200">
                                <input type="radio" name="preset_id" value="<?= htmlspecialchars($pid, ENT_QUOTES, 'UTF-8') ?>" required class="mt-1 h-4 w-4 shrink-0 text-blue-600 border-slate-300 focus:ring-blue-500">
                                <span class="min-w-0 flex-1">
                                    <span class="block font-bold text-slate-900 text-sm"><?= htmlspecialchars($plab, ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="mt-1 block text-xs text-slate-600 leading-snug"><?= htmlspecialchars($pdesc, ENT_QUOTES, 'UTF-8') ?></span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </li>
            </ol>

            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/60 p-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-indigo-900 text-sm font-black text-white mb-2">3</span>
                        <h2 class="text-sm font-bold text-slate-900">Récapitulatif avant application</h2>
                        <p class="text-xs text-slate-600 mt-1 max-w-xl">Calculez les <strong class="font-semibold text-slate-800">ajouts</strong> et <strong class="font-semibold text-slate-800">retraits</strong> par rapport à l’état actuel du rôle. Vous pourrez confirmer à l’étape suivante.</p>
                    </div>
                    <button type="button" id="btn-load-preview" class="inline-flex items-center justify-center rounded-xl bg-indigo-700 px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-indigo-800 disabled:opacity-45 disabled:pointer-events-none">
                        Afficher le récapitulatif
                    </button>
                </div>

                <div id="preview-status" class="mt-4 hidden text-sm font-medium" role="status"></div>
                <div id="preview-panel" class="mt-5 hidden space-y-5"></div>
            </div>

            <div class="rounded-2xl border border-rose-200 bg-rose-50/60 p-5">
                <p class="text-sm font-semibold text-rose-950">Rappel important</p>
                <p class="mt-1 text-sm text-rose-900/90">Après confirmation, les membres qui ont ce rôle disposent immédiatement du nouveau jeu de droits. Vérifiez la fiche du rôle après application.</p>
            </div>

            <div class="sticky bottom-3 z-10 rounded-2xl border border-slate-200/80 bg-white/95 p-3 shadow-lg backdrop-blur sm:static sm:border-0 sm:bg-transparent sm:p-0 sm:shadow-none">
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:gap-3">
                <button type="button" id="btn-open-confirm" disabled class="inline-flex w-full sm:w-auto items-center justify-center rounded-xl bg-slate-900 px-6 py-3 text-sm font-bold text-white shadow-md hover:bg-slate-800 disabled:opacity-40 disabled:pointer-events-none">
                    Continuer vers la confirmation…
                </button>
                <a href="<?= url('back-office/roles') ?>" class="inline-flex w-full sm:w-auto items-center justify-center rounded-xl border border-slate-300 bg-white px-6 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Annuler</a>
                </div>
            </div>

            <dialog id="preset-confirm-dialog" class="max-w-lg w-[calc(100%-1.5rem)] sm:w-[calc(100%-2rem)] rounded-2xl border border-slate-200 bg-white p-0 shadow-2xl backdrop:bg-slate-900/40">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h2 class="text-lg font-black text-slate-900">Confirmer l’application du profil</h2>
                    <p class="text-xs text-slate-500 mt-1">Cette action remplace toutes les habilitations du rôle sélectionné.</p>
                </div>
                <div class="px-6 py-4 text-sm text-slate-700 leading-relaxed space-y-3" id="dialog-summary-body"></div>
                <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 px-6 py-4 bg-slate-50/80 rounded-b-2xl">
                    <button type="button" id="dialog-cancel" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Retour</button>
                    <button type="submit" id="dialog-confirm-submit" class="rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-800">Confirmer et appliquer</button>
                </div>
            </dialog>
        </form>

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
            html += '<details class="group rounded-xl border border-slate-200 bg-white overflow-hidden mb-2">';
            html += '<summary class="cursor-pointer select-none px-4 py-3 text-sm font-bold text-slate-800 bg-slate-50/90 hover:bg-slate-100 flex justify-between items-center">';
            html += '<span>' + escapeHtml(label) + ' <span class="font-normal text-slate-500">(' + items.length + ')</span></span>';
            html += '<span class="text-slate-400 text-xs group-open:rotate-180 transition-transform">▼</span>';
            html += '</summary>';
            html += '<ul class="divide-y divide-slate-100 px-4 py-2 text-sm text-slate-700">';
            items.forEach(function (it) {
                html += '<li class="py-2">';
                html += '<span class="font-medium text-slate-900">' + escapeHtml(it.name) + '</span>';
                if (it.slug) {
                    html += '<details class="mt-1"><summary class="cursor-pointer text-[11px] text-slate-500 hover:text-slate-700">Référence technique</summary>';
                    html += '<code class="mt-1 block text-[11px] text-slate-600 bg-slate-100 rounded px-2 py-1 break-all">' + escapeHtml(it.slug) + '</code></details>';
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
            statusEl.className = 'mt-4 text-sm font-medium text-amber-800';
            statusEl.classList.remove('hidden');
            statusEl.textContent = 'Choisissez d’abord un rôle et un profil.';
            return;
        }
        statusEl.classList.remove('hidden');
        statusEl.className = 'mt-4 text-sm font-medium text-slate-600';
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
                    statusEl.className = 'mt-4 text-sm font-medium text-red-800';
                    statusEl.textContent = j.error || 'Impossible de charger le récapitulatif.';
                    panel.classList.add('hidden');
                    btnConfirm.disabled = true;
                    return;
                }
                lastPreview = { roleId: rid, presetId: pid, payload: j };
                statusEl.className = 'mt-4 text-sm font-medium text-emerald-800';
                statusEl.textContent = 'Récapitulatif à jour pour « ' + j.role_name + ' » et le profil « ' + j.preset_label + ' ».';

                var d = j.diff;
                var ml = j.module_labels || {};

                var stats = '<div class="grid grid-cols-2 sm:grid-cols-4 gap-3">';
                stats += '<div class="rounded-xl border border-slate-200 bg-white p-4 text-center shadow-sm"><p class="text-2xl font-black text-slate-900">' + d.current_total + '</p><p class="text-[11px] uppercase tracking-wide text-slate-500 font-semibold mt-1">Avant</p></div>';
                stats += '<div class="rounded-xl border border-emerald-200 bg-emerald-50/80 p-4 text-center shadow-sm"><p class="text-2xl font-black text-emerald-800">' + d.added_count + '</p><p class="text-[11px] uppercase tracking-wide text-emerald-800/80 font-semibold mt-1">Ajouts</p></div>';
                stats += '<div class="rounded-xl border border-rose-200 bg-rose-50/80 p-4 text-center shadow-sm"><p class="text-2xl font-black text-rose-800">' + d.removed_count + '</p><p class="text-[11px] uppercase tracking-wide text-rose-800/80 font-semibold mt-1">Retraits</p></div>';
                stats += '<div class="rounded-xl border border-slate-200 bg-white p-4 text-center shadow-sm"><p class="text-2xl font-black text-indigo-900">' + d.preset_total + '</p><p class="text-[11px] uppercase tracking-wide text-slate-500 font-semibold mt-1">Après</p></div>';
                stats += '</div>';

                if (j.preset_description) {
                    stats += '<p class="text-xs text-slate-600 mt-4 p-3 rounded-xl bg-slate-100/80 border border-slate-200/80"><span class="font-semibold text-slate-800">Contenu du profil :</span> ' + escapeHtml(j.preset_description) + '</p>';
                }

                var detail = '<div class="grid gap-6 lg:grid-cols-2">';
                detail += '<div><h3 class="text-xs font-black uppercase tracking-widest text-emerald-800 mb-2">Habilitations ajoutées</h3>' + renderListGrouped(d.added_by_module, ml) + '</div>';
                detail += '<div><h3 class="text-xs font-black uppercase tracking-widest text-rose-800 mb-2">Habilitations retirées</h3>' + renderListGrouped(d.removed_by_module, ml) + '</div>';
                detail += '</div>';

                if (d.unchanged_count > 0) {
                    detail += '<p class="text-xs text-slate-500 mt-4 text-center">' + d.unchanged_count + ' habilitation(s) déjà présente(s) et conservée(s) sans changement.</p>';
                }

                panel.innerHTML = stats + detail;
                panel.classList.remove('hidden');
                btnConfirm.disabled = false;
            })
            .catch(function () {
                btnPreview.disabled = false;
                statusEl.className = 'mt-4 text-sm font-medium text-red-800';
                statusEl.textContent = 'Erreur réseau. Réessayez.';
                btnConfirm.disabled = true;
            });
    });

    btnConfirm.addEventListener('click', function () {
        var rid = parseInt(roleEl.value, 10);
        var pid = getPresetId();
        if (!lastPreview || lastPreview.roleId !== rid || lastPreview.presetId !== pid) {
            statusEl.classList.remove('hidden');
            statusEl.className = 'mt-4 text-sm font-medium text-amber-800';
            statusEl.textContent = 'Le rôle ou le profil a changé : affichez à nouveau le récapitulatif.';
            return;
        }
        var j = lastPreview.payload;
        var d = j.diff;
        var html = '';
        html += '<p><span class="font-semibold text-slate-900">Rôle :</span> ' + escapeHtml(j.role_name) + '</p>';
        html += '<p><span class="font-semibold text-slate-900">Profil :</span> ' + escapeHtml(j.preset_label) + '</p>';
        html += '<ul class="list-disc list-inside space-y-1 text-slate-700 mt-2">';
        html += '<li><strong class="font-semibold text-emerald-800">' + d.added_count + '</strong> habilitation(s) seront <strong>ajoutées</strong></li>';
        html += '<li><strong class="font-semibold text-rose-800">' + d.removed_count + '</strong> habilitation(s) seront <strong>retirées</strong></li>';
        html += '<li><strong class="font-semibold text-slate-800">' + d.unchanged_count + '</strong> resteront inchangées</li>';
        html += '<li>Total après application : <strong class="font-semibold text-slate-900">' + d.preset_total + '</strong></li>';
        html += '</ul>';
        html += '<p class="text-xs text-slate-500 mt-3">En confirmant, vous acceptez de remplacer l’ensemble des droits actuels de ce rôle.</p>';
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
