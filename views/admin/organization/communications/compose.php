<?php
declare(strict_types=1);
$kinds = $kinds ?? [];
$templates = $templates ?? [];
$templates_js = $templates_js ?? [];
$groups = $groups ?? [];
$units_flat = $units_flat ?? [];
$org_roles = $org_roles ?? [];
$members = $members ?? [];
$gate = \App\Core\Gate::getInstance();
$canTpl = $gate->allows('comms.email_templates.manage');
$canHist = $gate->allows('comms.notifications.history.view');
$canGrp = $gate->allows('comms.email.send.orbat') || $gate->allows('comms.email.send.mission') || $gate->allows('comms.email.send.activity') || $gate->allows('comms.email.send.custom') || $gate->allows('comms.email.broadcast');
?>
<div class="max-w-4xl mx-auto px-6 py-10">
    <div class="flex flex-wrap gap-2 text-sm mb-6">
        <span class="font-black text-slate-900">E-mails membres</span>
        <span class="text-slate-400">·</span>
        <span class="text-slate-700 font-semibold">Rédaction</span>
        <?php if ($canTpl): ?>
            <span class="text-slate-400">|</span>
            <a href="<?= url('back-office/communications/templates') ?>" class="text-blue-700 hover:underline font-medium">Modèles</a>
        <?php endif; ?>
        <?php if ($canGrp): ?>
            <span class="text-slate-400">|</span>
            <a href="<?= url('back-office/communications/groups') ?>" class="text-blue-700 hover:underline font-medium">Groupes de destinataires</a>
        <?php endif; ?>
        <?php if ($canHist): ?>
            <span class="text-slate-400">|</span>
            <a href="<?= url('back-office/communications/history') ?>" class="text-blue-700 hover:underline font-medium">Historique</a>
        <?php endif; ?>
    </div>

    <h1 class="text-2xl font-black text-slate-900 mb-2">Envoyer un e-mail aux membres</h1>
    <p class="text-sm text-slate-600 mb-6">Rédigez un message personnalisé avec des champs dynamiques (prénom, unité, etc.). Les destinataires sont calculés à partir d’un groupe enregistré ou des critères ci-dessous.</p>

    <?php if (\App\Core\Session::get('success')): ?><p class="mb-4 text-sm text-emerald-700 font-medium"><?= htmlspecialchars((string) \App\Core\Session::get('success')) ?></p><?php \App\Core\Session::forget('success'); endif; ?>
    <?php if (\App\Core\Session::get('error')): ?><p class="mb-4 text-sm text-rose-700 font-medium"><?= htmlspecialchars((string) \App\Core\Session::get('error')) ?></p><?php \App\Core\Session::forget('error'); endif; ?>

    <?php if (empty($kinds)): ?>
        <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Aucune habilitation d’envoi n’est associée à votre compte. Demandez à un gestionnaire de vous attribuer les droits adaptés dans les rôles communautaires.</p>
    <?php else: ?>
    <form method="post" action="<?= url('back-office/communications/send') ?>" class="space-y-8">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="template_id" id="template_id" value="0">

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-500">Type de message</h2>
            <div>
                <label for="kind" class="block text-sm font-semibold text-slate-800 mb-1">Famille</label>
                <select name="kind" id="kind" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <?php foreach ($kinds as $kv): ?>
                        <option value="<?= htmlspecialchars($kv['kind']) ?>"><?= htmlspecialchars($kv['label']) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="mt-1 text-xs text-slate-500">Détermine qui peut envoyer ce message et la préférence de réception côté membres.</p>
            </div>
            <?php if ($templates !== []): ?>
            <div>
                <label for="template_pick" class="block text-sm font-semibold text-slate-800 mb-1">Partir d’un modèle (optionnel)</label>
                <select id="template_pick" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">—</option>
                    <?php foreach ($templates as $t): ?>
                        <option value="<?= (int) ($t['id'] ?? 0) ?>"><?= htmlspecialchars(($t['name'] ?? '') . ' (' . ($t['kind'] ?? '') . ')') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-500">Contenu</h2>
            <div>
                <label for="subject" class="block text-sm font-semibold text-slate-800 mb-1">Objet</label>
                <input type="text" name="subject" id="subject" required maxlength="500" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Ex. Point d’information — {{unit.name}}">
            </div>
            <div>
                <label for="body_html" class="block text-sm font-semibold text-slate-800 mb-1">Message (HTML simple)</label>
                <textarea name="body_html" id="body_html" rows="12" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-mono"></textarea>
                <p class="mt-1 text-xs text-slate-500">Vous pouvez utiliser des champs dynamiques comme <code class="text-xs bg-slate-100 px-1 rounded">{{user.first_name}}</code>, <code class="text-xs bg-slate-100 px-1 rounded">{{user.full_name}}</code>, <code class="text-xs bg-slate-100 px-1 rounded">{{unit.name}}</code> (voir aussi le Bureau Courrier pour la liste complète).</p>
            </div>
            <div>
                <label for="body_text" class="block text-sm font-semibold text-slate-800 mb-1">Version texte brut (optionnel)</label>
                <textarea name="body_text" id="body_text" rows="4" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-500">Destinataires</h2>
            <?php if ($groups !== []): ?>
            <div>
                <label for="group_id" class="block text-sm font-semibold text-slate-800 mb-1">Groupe enregistré</label>
                <select name="group_id" id="group_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="0">— Choisir manuellement ci-dessous —</option>
                    <?php foreach ($groups as $g): ?>
                        <option value="<?= (int) ($g['id'] ?? 0) ?>"><?= htmlspecialchars($g['name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="mt-1 text-xs text-slate-500">Si vous sélectionnez un groupe, les critères manuels sont ignorés.</p>
            </div>
            <?php endif; ?>

            <div class="flex items-start gap-3 rounded-lg border border-slate-100 bg-slate-50/80 px-3 py-2">
                <input type="checkbox" name="all_members" id="all_members" value="1" class="mt-1 h-4 w-4 rounded border-slate-300 text-slate-900">
                <div>
                    <label for="all_members" class="text-sm font-semibold text-slate-900">Tous les membres actifs avec une adresse valide</label>
                    <p class="text-xs text-slate-600">Hors comptes techniques. Respecte tout de même les préférences de chacun.</p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-800 mb-1">Unités (structure)</label>
                <select name="unit_ids[]" multiple size="6" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <?php foreach ($units_flat as $u): ?>
                        <option value="<?= (int) ($u['id'] ?? 0) ?>"><?= htmlspecialchars($u['name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="mt-2 flex items-start gap-3">
                    <input type="checkbox" name="include_descendants" id="include_descendants" value="1" checked class="mt-1 h-4 w-4 rounded border-slate-300">
                    <label for="include_descendants" class="text-sm text-slate-700">Inclure les sous-unités des unités sélectionnées</label>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-800 mb-1">Rôles communautaires</label>
                <select name="role_slugs[]" multiple size="5" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <?php foreach ($org_roles as $r): ?>
                        <option value="<?= htmlspecialchars((string) ($r['slug'] ?? '')) ?>"><?= htmlspecialchars((string) ($r['name'] ?? $r['slug'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-800 mb-1">Membres supplémentaires</label>
                <select name="extra_user_ids[]" multiple size="5" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <?php foreach ($members as $m): ?>
                        <option value="<?= (int) ($m['id'] ?? 0) ?>"><?= htmlspecialchars(trim(($m['display_name'] ?? '') ?: (($m['email'] ?? '') ?: '#' . ($m['id'] ?? '')))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="rounded-xl border border-indigo-100 bg-indigo-50/50 p-5 space-y-3">
            <h2 class="text-sm font-black uppercase tracking-wider text-indigo-900">Aperçu</h2>
            <p class="text-xs text-indigo-900/80">Choisissez un membre pour substituer les champs dynamiques et visualiser le rendu.</p>
            <div class="flex flex-wrap gap-2 items-end">
                <div class="flex-1 min-w-[200px]">
                    <label for="sample_user_id" class="block text-xs font-semibold text-indigo-950 mb-1">Membre d’exemple</label>
                    <select id="sample_user_id" class="w-full rounded-lg border border-indigo-200 bg-white px-3 py-2 text-sm">
                        <option value="">—</option>
                        <?php foreach ($members as $m): ?>
                            <option value="<?= (int) ($m['id'] ?? 0) ?>"><?= htmlspecialchars(trim(($m['display_name'] ?? '') ?: ($m['email'] ?? ''))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="button" id="btn-preview" class="rounded-lg bg-indigo-700 px-4 py-2 text-sm font-bold text-white hover:bg-indigo-800">Générer l’aperçu</button>
            </div>
            <div id="preview-out" class="hidden rounded-lg border border-indigo-200 bg-white p-4 text-sm text-slate-800"></div>
        </div>

        <div class="flex flex-wrap gap-4">
            <button type="submit" class="rounded-xl bg-slate-900 px-6 py-3 text-sm font-bold text-white shadow hover:bg-slate-800">Envoyer</button>
            <a href="<?= url('back-office') ?>" class="inline-flex items-center text-sm font-semibold text-slate-600 underline">Retour administration</a>
        </div>
    </form>

    <script>
    (function () {
        var templates = <?= json_encode($templates_js, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        var pick = document.getElementById('template_pick');
        var tid = document.getElementById('template_id');
        var kindEl = document.getElementById('kind');
        var subj = document.getElementById('subject');
        var body = document.getElementById('body_html');
        if (pick && tid && templates && templates.length) {
            pick.addEventListener('change', function () {
                var id = parseInt(pick.value || '0', 10);
                tid.value = id > 0 ? String(id) : '0';
                var found = templates.find(function (x) { return x.id === id; });
                if (!found) return;
                if (kindEl && found.kind) {
                    var opts = kindEl.querySelectorAll('option');
                    for (var i = 0; i < opts.length; i++) {
                        if (opts[i].value === found.kind) {
                            kindEl.selectedIndex = i;
                            break;
                        }
                    }
                }
                if (subj) subj.value = found.subject || '';
                if (body) body.value = found.body_html || '';
            });
        }
        var btn = document.getElementById('btn-preview');
        var sample = document.getElementById('sample_user_id');
        var out = document.getElementById('preview-out');
        if (btn && sample && out) {
            btn.addEventListener('click', function () {
                var uid = parseInt(sample.value || '0', 10);
                if (!uid) {
                    alert('Choisissez un membre d’exemple.');
                    return;
                }
                var fd = new FormData();
                fd.append('_csrf_token', document.querySelector('input[name="_csrf_token"]') ? document.querySelector('input[name="_csrf_token"]').value : '');
                fd.append('sample_user_id', String(uid));
                fd.append('subject', subj ? subj.value : '');
                fd.append('body_html', body ? body.value : '');
                fd.append('body_text', document.getElementById('body_text') ? document.getElementById('body_text').value : '');
                fetch('<?= url('back-office/communications/preview') ?>', { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (j) {
                        if (!j || !j.ok) {
                            alert(j && j.error ? j.error : 'Aperçu indisponible.');
                            return;
                        }
                        out.classList.remove('hidden');
                        out.innerHTML = '<p class="text-xs font-bold text-slate-500 mb-2">Objet : ' + (j.subject || '').replace(/</g, '&lt;') + '</p><div class="prose prose-sm max-w-none border-t border-slate-100 pt-2">' + (j.html || '') + '</div>';
                    })
                    .catch(function () { alert('Erreur réseau.'); });
            });
        }
    })();
    </script>
    <?php endif; ?>
</div>
