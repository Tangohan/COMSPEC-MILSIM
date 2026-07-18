<?php
declare(strict_types=1);
/** @var list<array<string, mixed>> $tenantsList */
/** @var int $selectedTenantId */
/** @var array<string, mixed>|null $selectedTenant */
/** @var list<array<string, mixed>> $memberUsers */
/** @var list<array<string, mixed>> $actions */
/** @var array<string, string> $moduleLabels */
/** @var string $blocklistUrl */

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

/** Libellé lisible pour le select « Membre concerné » : indicatif / identité civile, e-mail en secondaire. */
$memberOptionLabel = static function (array $u): string {
    $email = trim((string) ($u['email'] ?? ''));
    $first = trim((string) ($u['first_name'] ?? ''));
    $last = trim((string) ($u['last_name'] ?? ''));
    $legalName = trim($first . ' ' . $last);
    $display = trim((string) ($u['display_name'] ?? ''));
    $callsign = trim((string) ($u['callsign'] ?? ''));
    $nickname = trim((string) ($u['nickname'] ?? ''));
    $pseudo = $callsign !== '' ? $callsign : $nickname;
    $civil = $legalName !== '' ? $legalName : $display;

    if ($pseudo !== '' && $civil !== '' && strcasecmp($pseudo, $civil) !== 0) {
        $primary = $pseudo . ' — ' . $civil;
    } elseif ($pseudo !== '') {
        $primary = $pseudo;
    } elseif ($civil !== '') {
        $primary = $civil;
    } else {
        return $email !== '' ? $email : ('Membre #' . (int) ($u['id'] ?? 0));
    }

    if ($email !== '' && strcasecmp($primary, $email) !== 0) {
        return $primary . ' — ' . $email;
    }

    return $primary;
};
?>
<div class="max-w-5xl mx-auto px-4 sm:px-6 py-10">
    <header class="mb-8">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1">Administration plateforme</p>
        <h1 class="text-2xl font-black text-slate-900">Sanctions membres (niveau site)</h1>
        <p class="mt-2 text-sm text-slate-600 leading-relaxed">
            Mesures d’accès au compte, au forum, à la messagerie et aux domaines du portail, pour un membre d’une communauté donnée.
            Les <strong class="font-semibold text-slate-800">limitations « organisation »</strong> (formations, documents sans toucher au compte) restent du ressort des personnes habilitées dans chaque communauté.
        </p>
        <div class="mt-4 flex flex-wrap gap-3 text-sm">
            <a href="<?= htmlspecialchars($blocklistUrl, ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-rose-800 hover:underline">Liste de restriction e-mail et réseau (toute la plateforme)</a>
            <a href="<?= url('admin') ?>" class="text-slate-600 hover:underline">Retour au centre opérateur site</a>
        </div>
    </header>

    <?php $f = \App\Core\Session::getFlash('error'); $s = \App\Core\Session::getFlash('success'); ?>
    <?php if ($f): ?><p class="text-red-600 text-sm mb-4"><?= htmlspecialchars($f) ?></p><?php endif; ?>
    <?php if ($s): ?><p class="text-emerald-700 text-sm mb-4"><?= htmlspecialchars($s) ?></p><?php endif; ?>

    <section class="border border-slate-200 rounded-xl p-5 bg-white shadow-sm mb-8">
        <h2 class="text-sm font-bold text-slate-800 mb-3">Choisir une communauté</h2>
        <form method="get" action="<?= url('admin/system/member-sanctions') ?>" class="flex flex-wrap items-end gap-3">
            <div class="min-w-[240px] flex-1">
                <label class="block text-xs text-slate-500 mb-1">Communauté</label>
                <select name="tenant_id" class="w-full border border-slate-300 rounded px-3 py-2 text-sm" onchange="this.form.submit()">
                    <option value="">— Sélectionner —</option>
                    <?php foreach ($tenantsList as $t): ?>
                        <option value="<?= (int) ($t['id'] ?? 0) ?>" <?= (int) ($t['id'] ?? 0) === $selectedTenantId ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) ($t['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded">Afficher</button>
        </form>
    </section>

    <?php if ($selectedTenantId > 0 && $selectedTenant !== null): ?>
    <form method="post" action="<?= url('admin/system/member-sanctions/apply') ?>" class="grid md:grid-cols-2 gap-4 mb-10 border border-slate-200 rounded-lg p-4 bg-white shadow-sm">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
        <input type="hidden" name="tenant_id" value="<?= (int) $selectedTenantId ?>">
        <div class="md:col-span-2">
            <h2 class="text-sm font-bold text-slate-800 mb-3">Nouvelle mesure sur <?= htmlspecialchars((string) ($selectedTenant['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
        </div>
        <div class="md:col-span-2">
            <label class="block text-xs text-slate-500 mb-1">Membre concerné</label>
            <select name="target_user_id" required class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
                <option value="">— Choisir —</option>
                <?php foreach ($memberUsers as $u): ?>
                    <option value="<?= (int) ($u['id'] ?? 0) ?>"><?= htmlspecialchars($memberOptionLabel($u), ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs text-slate-500 mb-1">Nature de la mesure</label>
            <select name="action_type" id="sys_mod_action_type" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
                <option value="warn">Avertissement (trace sur le dossier)</option>
                <option value="mute">Limitation d’activité</option>
                <option value="suspend">Suspension</option>
                <option value="ban">Exclusion</option>
            </select>
        </div>
        <div id="sys_mod_duration_wrap">
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
        <div class="md:col-span-2" id="sys_mod_scope_wrap">
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
                <p class="text-xs text-slate-500 mb-1">Domaines du portail concernés</p>
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
            <label class="block text-xs text-slate-500 mb-1">Motif (historique interne)</label>
            <textarea name="reason" rows="2" class="w-full border border-slate-300 rounded px-3 py-2 text-sm"></textarea>
        </div>
        <div class="md:col-span-2">
            <button type="submit" class="px-4 py-2 bg-rose-700 text-white text-sm font-semibold rounded">Enregistrer la mesure</button>
        </div>
    </form>

    <h2 class="text-lg font-bold text-slate-800 mb-2">Historique récent (sanctions site, cette communauté)</h2>
    <table class="w-full text-sm border border-slate-200 rounded bg-white shadow-sm">
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
                    <?php if (empty($a['revoked_at']) && in_array((string) ($a['action_type'] ?? ''), ['mute', 'suspend', 'ban'], true)): ?>
                    <form method="post" action="<?= url('admin/system/member-sanctions/revoke') ?>" class="inline">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                        <input type="hidden" name="tenant_id" value="<?= (int) $selectedTenantId ?>">
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
    <?php elseif ($selectedTenantId > 0): ?>
        <p class="text-sm text-rose-600">Communauté introuvable.</p>
    <?php else: ?>
        <p class="text-sm text-slate-500">Sélectionnez une communauté pour appliquer ou consulter les sanctions de niveau site.</p>
    <?php endif; ?>
</div>
<script>
(function () {
    var sel = document.getElementById('sys_mod_action_type');
    var dur = document.getElementById('sys_mod_duration_wrap');
    var scope = document.getElementById('sys_mod_scope_wrap');
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
