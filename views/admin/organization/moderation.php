<?php
/** @var list<array<string, mixed>> $actions */
/** @var list<array<string, mixed>> $memberUsers */
/** @var array<string, string> $moduleLabels */

$actionTypeLabel = static function (string $t): string {
    return match ($t) {
        'warn' => 'Avertissement',
        'mute' => 'Restriction d’activité',
        'suspend' => 'Suspension',
        'ban' => 'Exclusion',
        default => 'Autre',
    };
};

$modulesSummary = static function (?string $json): string {
    if ($json === null || $json === '') {
        return '—';
    }
    $d = json_decode($json, true);
    if (!is_array($d)) {
        return '—';
    }
    $mods = $d['modules_blocked'] ?? [];
    if (!is_array($mods) || $mods === []) {
        return '—';
    }
    $labels = \App\Services\Moderation\ModerationRestrictionsCatalog::moduleLabels();
    $parts = [];
    foreach ($mods as $k) {
        $k = (string) $k;
        $parts[] = $labels[$k] ?? $k;
    }

    return implode(', ', $parts);
};
?>
<div class="max-w-5xl mx-auto px-6 py-12">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-black text-slate-900">Restrictions membres</h1>
        <a href="<?= url('back-office') ?>" class="text-sm text-slate-600 hover:underline">Retour</a>
    </div>
    <div class="rounded-lg border border-amber-200 bg-amber-50/80 px-4 py-3 text-sm text-amber-950 mb-6">
        <p class="font-semibold">Niveau organisation</p>
        <p class="mt-1 text-amber-900/90">Ici vous limitez l’accès du membre à certains <strong>domaines du portail</strong> de votre communauté (formations, documents, candidatures, etc.). Les mesures sur le compte, le forum, la messagerie ou les listes de blocage à l’échelle du site sont gérées par l’administration de la plateforme.</p>
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
        <div class="md:col-span-2">
            <label class="block text-xs text-slate-500 mb-1">Type</label>
            <select name="action_type" id="mod_action_type" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
                <option value="warn">Avertissement (conservé sur le dossier)</option>
                <option value="restriction">Restriction d’accès à des domaines du portail</option>
            </select>
        </div>
        <div id="mod_duration_wrap" class="md:col-span-2" style="display:none">
            <label class="block text-xs text-slate-500 mb-1">Durée de la restriction</label>
            <div class="space-y-2">
                <label class="flex items-center gap-2 text-sm">
                    <input type="radio" name="duration_mode" value="permanent" checked class="rounded border-slate-300"> Sans date de fin
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="radio" name="duration_mode" value="temporary" class="rounded border-slate-300"> Jusqu’au bout de
                </label>
                <input type="number" name="duration_days" value="7" min="1" max="3650" class="w-full max-w-xs border border-slate-300 rounded px-3 py-2 text-sm" title="Nombre de jours si temporaire">
                <span class="text-xs text-slate-500">jours</span>
            </div>
        </div>
        <div class="md:col-span-2" id="mod_scope_wrap" style="display:none">
            <p class="text-xs font-semibold text-slate-600 mb-2">Domaines concernés</p>
            <p class="text-xs text-slate-500 mb-3">Cochez les parties du portail auxquelles le membre ne doit plus accéder dans votre communauté.</p>
            <div class="grid sm:grid-cols-2 gap-2">
                <?php foreach ($moduleLabels as $key => $label): ?>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="modules_blocked[]" value="<?= htmlspecialchars($key) ?>" class="rounded border-slate-300">
                        <?= htmlspecialchars($label) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="md:col-span-2">
            <label class="block text-xs text-slate-500 mb-1">Motif (visible pour les personnes habilitées sur la fiche)</label>
            <textarea name="reason" rows="2" class="w-full border border-slate-300 rounded px-3 py-2 text-sm"></textarea>
        </div>
        <div class="md:col-span-2">
            <button type="submit" class="px-4 py-2 bg-rose-700 text-white text-sm font-semibold rounded">Enregistrer</button>
        </div>
    </form>

    <h2 class="text-lg font-bold text-slate-800 mb-2">Historique récent (organisation)</h2>
    <table class="w-full text-sm border border-slate-200 rounded">
        <thead class="bg-slate-50">
            <tr>
                <th class="text-left p-2">Date</th>
                <th class="text-left p-2">Membre</th>
                <th class="text-left p-2">Mesure</th>
                <th class="text-left p-2">Domaines</th>
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
                <td class="p-2"><?= htmlspecialchars($modulesSummary(isset($a['restrictions_json']) ? (is_string($a['restrictions_json']) ? $a['restrictions_json'] : null) : null)) ?></td>
                <td class="p-2"><?= htmlspecialchars((string) ($a['actor_email'] ?? '')) ?></td>
                <td class="p-2">
                    <?php if (empty($a['revoked_at']) && in_array((string) ($a['action_type'] ?? ''), ['mute', 'suspend', 'ban'], true)): ?>
                    <form method="post" action="<?= url('back-office/moderation/revoke') ?>" class="inline">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                        <input type="hidden" name="action_id" value="<?= (int) ($a['id'] ?? 0) ?>">
                        <button type="submit" class="text-rose-600 text-xs underline">Lever</button>
                    </form>
                    <?php elseif (!empty($a['revoked_at'])): ?>
                        <span class="text-slate-400">Levée</span>
                    <?php else: ?>
                        <span class="text-slate-400">—</span>
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
