<?php
/** @var list<array<string, mixed>> $actions */
/** @var list<array<string, mixed>> $memberUsers */
/** @var list<array<string, mixed>> $blocklistRows */
/** @var array<string, string> $moduleLabels */

$actionTypeLabel = static function (string $t): string {
    return match ($t) {
        'warn' => 'Avertissement',
        'mute' => 'Limitation',
        'suspend' => 'Suspension',
        'ban' => 'Exclusion',
        default => 'Autre',
    };
};

$forumLabel = static function (?string $json): string {
    if ($json === null || $json === '') {
        return '—';
    }
    $d = json_decode($json, true);
    if (!is_array($d)) {
        return '—';
    }
    $f = (string) ($d['forum'] ?? '');
    return match ($f) {
        'read_only' => 'Forum : lecture seule',
        'none' => 'Forum : aucun accès',
        'full_access' => 'Forum : accès complet',
        default => '—',
    };
};

$indicatorKindLabel = static function (string $t): string {
    return $t === 'ip' ? 'Adresse réseau' : 'Adresse e-mail';
};
?>
<div class="max-w-5xl mx-auto px-6 py-12">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-black text-slate-900">Modération</h1>
        <a href="<?= url('back-office') ?>" class="text-sm text-slate-600 hover:underline">Retour</a>
    </div>
    <?php $f = \App\Core\Session::getFlash('error'); $s = \App\Core\Session::getFlash('success'); $w = \App\Core\Session::getFlash('warning'); ?>
    <?php if ($f): ?><p class="text-red-600 text-sm mb-4"><?= htmlspecialchars($f) ?></p><?php endif; ?>
    <?php if ($s): ?><p class="text-emerald-700 text-sm mb-4"><?= htmlspecialchars($s) ?></p><?php endif; ?>
    <?php if ($w): ?><p class="text-amber-800 text-sm mb-4"><?= htmlspecialchars($w) ?></p><?php endif; ?>

    <form method="post" action="<?= url('back-office/moderation/apply') ?>" class="grid md:grid-cols-2 gap-4 mb-10 border border-slate-200 rounded-lg p-4">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
        <div class="md:col-span-2">
            <h2 class="text-sm font-bold text-slate-800 mb-3">Nouvelle mesure</h2>
        </div>
        <div class="md:col-span-2">
            <label class="block text-xs text-slate-500 mb-1">Membre concerné</label>
            <select name="target_user_id" required class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
                <option value="">— Choisir —</option>
                <?php foreach ($memberUsers as $u): ?>
                    <option value="<?= (int) ($u['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($u['email'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs text-slate-500 mb-1">Nature de la mesure</label>
            <select name="action_type" id="mod_action_type" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
                <option value="warn">Avertissement (trace interne)</option>
                <option value="mute">Limitation d’activité</option>
                <option value="suspend">Suspension</option>
                <option value="ban">Exclusion</option>
            </select>
        </div>
        <div id="mod_duration_wrap">
            <label class="block text-xs text-slate-500 mb-1">Durée</label>
            <div class="space-y-2">
                <label class="flex items-center gap-2 text-sm">
                    <input type="radio" name="duration_mode" value="permanent" checked class="rounded border-slate-300"> Sans date de fin
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="radio" name="duration_mode" value="temporary" class="rounded border-slate-300"> Temporaire
                </label>
                <input type="number" name="duration_days" value="7" min="1" max="3650" class="w-full border border-slate-300 rounded px-3 py-2 text-sm" title="Nombre de jours si temporaire">
            </div>
        </div>
        <div class="md:col-span-2" id="mod_scope_wrap">
            <p class="text-xs font-semibold text-slate-600 mb-2">Portée (hors simple avertissement)</p>
            <div class="grid sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Forum</label>
                    <select name="forum_access" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
                        <option value="full_access">Lecture et publication</option>
                        <option value="read_only">Lecture seule</option>
                        <option value="none">Aucun accès</option>
                    </select>
                </div>
                <div class="space-y-2">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="messages_blocked" value="1" class="rounded border-slate-300"> Messagerie interne : envoi bloqué
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="join_blocked" value="1" class="rounded border-slate-300"> Empêcher toute nouvelle inscription avec la même adresse e-mail dans cette communauté
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="account_lock" value="1" class="rounded border-slate-300"> Verrouiller complètement le compte (déconnexion)
                    </label>
                </div>
            </div>
            <div class="mt-3">
                <p class="text-xs text-slate-500 mb-1">Modules du portail concernés</p>
                <div class="grid sm:grid-cols-2 gap-2">
                    <?php foreach ($moduleLabels as $key => $label): ?>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="modules_blocked[]" value="<?= htmlspecialchars($key) ?>" class="rounded border-slate-300">
                            <?= htmlspecialchars($label) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="md:col-span-2">
            <label class="block text-xs text-slate-500 mb-1">Motif (visible dans l’historique interne)</label>
            <textarea name="reason" rows="2" class="w-full border border-slate-300 rounded px-3 py-2 text-sm"></textarea>
        </div>
        <div class="md:col-span-2">
            <button type="submit" class="px-4 py-2 bg-rose-700 text-white text-sm font-semibold rounded">Enregistrer la mesure</button>
        </div>
    </form>

    <section class="mb-10 border border-slate-200 rounded-lg p-4">
        <h2 class="text-lg font-bold text-slate-800 mb-2">Liste de restriction (cette communauté)</h2>
        <p class="text-xs text-slate-600 mb-4">Pour les connexions, inscriptions et candidatures : adresses e-mail ou origines réseau que vous refusez pour cette organisation. Les valeurs sont conservées de façon sécurisée ; seuls le type et la référence interne s’affichent ici.</p>
        <form method="post" action="<?= url('back-office/moderation/blocklist/add') ?>" class="grid md:grid-cols-2 gap-3 mb-6">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
            <div>
                <label class="block text-xs text-slate-500 mb-1">Type</label>
                <select name="indicator_kind" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
                    <option value="email">Adresse e-mail</option>
                    <option value="ip">Adresse réseau (telle qu’observée par le serveur)</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs text-slate-500 mb-1">Valeur à restreindre</label>
                <input type="text" name="restriction_target" required class="w-full border border-slate-300 rounded px-3 py-2 text-sm" placeholder="Selon le type : adresse e-mail complète, ou adresse réseau telle qu’affichée dans les journaux">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs text-slate-500 mb-1">Motif interne</label>
                <input type="text" name="block_reason" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
            </div>
            <div class="md:col-span-2 flex flex-wrap gap-4 items-center">
                <label class="flex items-center gap-2 text-sm">
                    <input type="radio" name="block_duration_mode" value="permanent" checked class="rounded border-slate-300"> Sans date de fin
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="radio" name="block_duration_mode" value="temporary" class="rounded border-slate-300"> Jusqu’au bout de
                </label>
                <input type="number" name="block_duration_days" value="30" min="1" class="w-24 border border-slate-300 rounded px-2 py-1 text-sm"> jours
            </div>
            <div class="md:col-span-2">
                <button type="submit" class="px-4 py-2 bg-slate-800 text-white text-sm font-semibold rounded">Ajouter</button>
            </div>
        </form>
        <?php if ($blocklistRows === []): ?>
            <p class="text-sm text-slate-500">Aucune entrée active.</p>
        <?php else: ?>
            <table class="w-full text-sm border border-slate-200 rounded">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="text-left p-2">Réf.</th>
                        <th class="text-left p-2">Type</th>
                        <th class="text-left p-2">Fin</th>
                        <th class="text-left p-2">Motif</th>
                        <th class="text-left p-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($blocklistRows as $b): ?>
                    <tr class="border-t border-slate-100">
                        <td class="p-2">#<?= (int) ($b['id'] ?? 0) ?></td>
                        <td class="p-2"><?= htmlspecialchars($indicatorKindLabel((string) ($b['indicator_type'] ?? ''))) ?></td>
                        <td class="p-2"><?= !empty($b['expires_at']) ? htmlspecialchars((string) $b['expires_at']) : '—' ?></td>
                        <td class="p-2"><?= htmlspecialchars(trim((string) ($b['reason'] ?? '')) !== '' ? (string) $b['reason'] : '—') ?></td>
                        <td class="p-2">
                            <form method="post" action="<?= url('back-office/moderation/blocklist/revoke') ?>" class="inline">
                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                                <input type="hidden" name="indicator_id" value="<?= (int) ($b['id'] ?? 0) ?>">
                                <button type="submit" class="text-rose-600 text-xs underline">Lever</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <h2 class="text-lg font-bold text-slate-800 mb-2">Historique récent</h2>
    <table class="w-full text-sm border border-slate-200 rounded">
        <thead class="bg-slate-50">
            <tr>
                <th class="text-left p-2">Date</th>
                <th class="text-left p-2">Cible</th>
                <th class="text-left p-2">Mesure</th>
                <th class="text-left p-2">Forum</th>
                <th class="text-left p-2">Acteur</th>
                <th class="text-left p-2"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($actions as $a): ?>
            <tr class="border-t border-slate-100">
                <td class="p-2"><?= htmlspecialchars((string) ($a['created_at'] ?? '')) ?></td>
                <td class="p-2"><?= htmlspecialchars((string) ($a['target_email'] ?? '')) ?></td>
                <td class="p-2"><?= htmlspecialchars($actionTypeLabel((string) ($a['action_type'] ?? ''))) ?></td>
                <td class="p-2"><?= htmlspecialchars($forumLabel(isset($a['restrictions_json']) ? (is_string($a['restrictions_json']) ? $a['restrictions_json'] : null) : null)) ?></td>
                <td class="p-2"><?= htmlspecialchars((string) ($a['actor_email'] ?? '')) ?></td>
                <td class="p-2">
                    <?php if (empty($a['revoked_at'])): ?>
                    <form method="post" action="<?= url('back-office/moderation/revoke') ?>" class="inline">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                        <input type="hidden" name="action_id" value="<?= (int) ($a['id'] ?? 0) ?>">
                        <button type="submit" class="text-rose-600 text-xs underline">Lever</button>
                    </form>
                    <?php else: ?>
                        <span class="text-slate-400">Révoqué</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<script>
(function () {
    var sel = document.getElementById('mod_action_type');
    var dur = document.getElementById('mod_duration_wrap');
    var scope = document.getElementById('mod_scope_wrap');
    function sync() {
        if (!sel || !dur || !scope) return;
        var w = sel.value === 'warn';
        dur.style.display = w ? 'none' : '';
        scope.style.display = w ? 'none' : '';
    }
    if (sel) sel.addEventListener('change', sync);
    sync();
})();
</script>
