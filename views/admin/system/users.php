<?php
declare(strict_types=1);

$groups = is_array($platformUserGroups ?? null) ? $platformUserGroups : [];
// Repli si une ancienne clé plate est encore fournie.
if ($groups === [] && is_array($platformUsers ?? null) && $platformUsers !== []) {
    foreach ($platformUsers as $u) {
        $key = strtolower(trim((string) ($u['email'] ?? '')));
        if ($key === '') {
            continue;
        }
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'email' => (string) ($u['email'] ?? ''),
                'display_name' => (string) ($u['display_name'] ?? ''),
                'callsign' => (string) ($u['callsign'] ?? ''),
                'memberships' => [],
            ];
        }
        $groups[$key]['memberships'][] = $u;
    }
    $groups = array_values($groups);
}

$total = (int) ($platformUsersTotal ?? 0);
$page = max(1, (int) ($platformUsersPage ?? 1));
$pages = max(1, (int) ($platformUsersPages ?? 1));
$q = (string) ($platformUsersQ ?? '');
$statusFilter = (string) ($platformUsersStatus ?? '');
$tenantFilter = (int) ($platformUsersTenantId ?? 0);
$tenants = is_array($platformTenants ?? null) ? $platformTenants : [];

$statusLabel = static function (string $status): string {
    return match ($status) {
        'active' => 'Actif',
        'inactive' => 'Désactivé',
        'pending_verification' => 'E-mail à vérifier',
        default => 'Inconnu',
    };
};

$statusBadgeClass = static function (string $status): string {
    return match ($status) {
        'active' => 'bg-emerald-50 text-emerald-950',
        'inactive' => 'bg-rose-50 text-rose-950',
        'pending_verification' => 'bg-amber-50 text-amber-950',
        default => 'bg-slate-100 text-slate-700',
    };
};

$currentActorId = (int) \App\Core\Session::get('user_id');
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$csrf = $h(\App\Core\Csrf::token());

$returnCtx = [
    'q' => $q,
    'status' => $statusFilter,
    'tenant_id' => $tenantFilter > 0 ? (string) $tenantFilter : '',
    'page' => (string) $page,
];

$hiddenReturns = static function () use ($h, $returnCtx): string {
    return '<input type="hidden" name="return_q" value="' . $h((string) $returnCtx['q']) . '">'
        . '<input type="hidden" name="return_status" value="' . $h((string) $returnCtx['status']) . '">'
        . '<input type="hidden" name="return_tenant_id" value="' . $h((string) $returnCtx['tenant_id']) . '">'
        . '<input type="hidden" name="return_page" value="' . $h((string) $returnCtx['page']) . '">';
};

$queryUrl = static function (array $overrides) use ($q, $statusFilter, $tenantFilter, $page): string {
    $params = [
        'q' => $overrides['q'] ?? $q,
        'status' => $overrides['status'] ?? $statusFilter,
        'tenant_id' => $overrides['tenant_id'] ?? ($tenantFilter > 0 ? (string) $tenantFilter : ''),
        'page' => (string) ($overrides['page'] ?? $page),
    ];
    $bits = [];
    foreach ($params as $key => $value) {
        $value = trim((string) $value);
        if ($value === '' || ($key === 'page' && $value === '1')) {
            continue;
        }
        $bits[] = rawurlencode($key) . '=' . rawurlencode($value);
    }
    $base = url('admin/users');

    return $bits === [] ? $base : $base . '?' . implode('&', $bits);
};
?>
<div class="min-h-0 flex-1 bg-slate-50">
    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 space-y-8">
        <nav class="text-sm text-slate-500">
            <a href="<?= $h(url('admin')) ?>" class="font-semibold text-emerald-800 hover:text-emerald-950">Administration plateforme</a>
            <span class="mx-2" aria-hidden="true">/</span>
            <span class="text-slate-800">Comptes utilisateurs</span>
        </nav>

        <?php $ok = \App\Core\Session::getFlash('success'); ?>
        <?php if ($ok): ?>
            <p class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900"><?= $h((string) $ok) ?></p>
        <?php endif; ?>
        <?php $err = \App\Core\Session::getFlash('error'); ?>
        <?php if ($err): ?>
            <p class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-900"><?= $h((string) $err) ?></p>
        <?php endif; ?>

        <header>
            <h1 class="text-2xl font-black text-slate-900">Comptes utilisateurs (toutes communautés)</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-600 leading-relaxed">
                Une personne = une ligne (regroupée par e-mail), avec toutes ses appartenances.
                Actions possibles&nbsp;: désactiver / réactiver une communauté, retirer d’une organisation,
                ou supprimer sur tout le site (anonymisation ou suppression définitive).
                Les comptes sans appartenance active à une vraie communauté restent visibles
                (badge « Orphelin » sur le dossier complet).
                Ouvrez le <strong>dossier complet</strong> pour voir chaque communauté en détail.
            </p>
        </header>

        <div class="flex flex-wrap gap-3 text-sm">
            <a href="<?= $h(url('admin/system/member-sanctions')) ?>" class="font-semibold text-rose-800 hover:underline">Sanctions à l’échelle du site</a>
            <a href="<?= $h(url('admin/site-roles')) ?>" class="text-slate-600 hover:underline">Affectations rôles site</a>
            <a href="<?= $h(url('admin/tenants')) ?>" class="text-slate-600 hover:underline">Annuaire des communautés</a>
        </div>

        <form method="get" action="<?= $h(url('admin/users')) ?>" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="grid gap-3 md:grid-cols-4">
                <div class="md:col-span-2">
                    <label for="platform-users-q" class="block text-xs font-medium text-slate-500 mb-1">Rechercher</label>
                    <input id="platform-users-q" type="search" name="q" value="<?= $h($q) ?>"
                           placeholder="Adresse e-mail, nom affiché ou indicatif"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="platform-users-tenant" class="block text-xs font-medium text-slate-500 mb-1">Communauté</label>
                    <select id="platform-users-tenant" name="tenant_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Toutes</option>
                        <?php foreach ($tenants as $t): ?>
                            <?php $tid = (int) ($t['id'] ?? 0); ?>
                            <option value="<?= $tid ?>" <?= $tid === $tenantFilter ? 'selected' : '' ?>>
                                <?= $h((string) ($t['name'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="platform-users-status" class="block text-xs font-medium text-slate-500 mb-1">État du compte</label>
                    <select id="platform-users-status" name="status" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Tous</option>
                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Compte actif</option>
                        <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Compte désactivé</option>
                        <option value="pending_verification" <?= $statusFilter === 'pending_verification' ? 'selected' : '' ?>>En attente de vérification de l’e-mail</option>
                        <option value="deleted" <?= $statusFilter === 'deleted' ? 'selected' : '' ?>>Comptes supprimés</option>
                    </select>
                </div>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <button type="submit" class="inline-flex rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filtrer</button>
                <a href="<?= $h(url('admin/users')) ?>" class="inline-flex rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Réinitialiser</a>
            </div>
        </form>

        <p class="text-sm text-slate-600">
            <?= $total === 0 ? 'Aucune personne trouvée.' : ($total === 1 ? '1 personne trouvée.' : number_format($total, 0, ',', ' ') . ' personnes trouvées.') ?>
            <?php if ($pages > 1): ?>
                <span class="text-slate-400">— page <?= $page ?> / <?= $pages ?></span>
            <?php endif; ?>
        </p>

        <div class="space-y-4">
            <?php if ($groups === []): ?>
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-10 text-center text-slate-500 shadow-sm">
                    Aucun compte ne correspond à ces critères.
                </div>
            <?php else: ?>
                <?php foreach ($groups as $group): ?>
                    <?php
                    $email = (string) ($group['email'] ?? '');
                    $display = trim((string) ($group['display_name'] ?? ''));
                    $callsign = trim((string) ($group['callsign'] ?? ''));
                    $memberships = is_array($group['memberships'] ?? null) ? $group['memberships'] : [];
                    $primary = $callsign !== '' ? $callsign : ($display !== '' ? $display : $email);
                    $isSelf = false;
                    foreach ($memberships as $m) {
                        if ((int) ($m['id'] ?? 0) === $currentActorId) {
                            $isSelf = true;
                            break;
                        }
                    }
                    $alive = [];
                    foreach ($memberships as $m) {
                        if (empty($m['deleted_at'])) {
                            $alive[] = $m;
                        }
                    }
                    $siteAnchor = $alive[0] ?? ($memberships[0] ?? null);
                    $siteUid = (int) ($siteAnchor['id'] ?? 0);
                    $siteTid = (int) ($siteAnchor['tenant_id'] ?? 0);
                    $emailJs = htmlspecialchars(json_encode($email, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '""', ENT_QUOTES, 'UTF-8');
                    ?>
                    <article class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-4 py-3 bg-slate-50">
                            <div>
                                <h2 class="text-base font-bold text-slate-900">
                                    <a href="<?= $h(url('admin/users/person') . '?email=' . rawurlencode($email)) ?>" class="hover:text-emerald-800 hover:underline">
                                        <?= $h($primary) ?>
                                    </a>
                                </h2>
                                <?php if ($display !== '' && strcasecmp($display, $primary) !== 0): ?>
                                    <p class="text-xs text-slate-600"><?= $h($display) ?></p>
                                <?php endif; ?>
                                <?php if ($email !== ''): ?>
                                    <p class="text-xs text-slate-500 font-mono"><?= $h($email) ?></p>
                                <?php endif; ?>
                                <p class="mt-1 text-xs text-slate-500">
                                    <?= count($memberships) ?> communauté<?= count($memberships) > 1 ? 's' : '' ?>
                                    · <a href="<?= $h(url('admin/users/person') . '?email=' . rawurlencode($email)) ?>" class="font-semibold text-emerald-800 hover:underline">Dossier complet</a>
                                </p>
                            </div>
                            <?php if (!$isSelf && $siteUid > 0 && $siteTid > 0 && $alive !== []): ?>
                                <div class="flex flex-wrap gap-2">
                                    <form method="post" action="<?= $h(url('admin/users/delete')) ?>"
                                          onsubmit="return confirm('Anonymiser <?= $h(addslashes($primary)) ?> sur TOUT LE SITE ?\n\nToutes les communautés de cette adresse seront retirées (fiches « Compte supprimé »).');">
                                        <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="user_id" value="<?= $siteUid ?>">
                                        <input type="hidden" name="tenant_id" value="<?= $siteTid ?>">
                                        <input type="hidden" name="scope" value="site">
                                        <?= $hiddenReturns() ?>
                                        <button type="submit" class="rounded-lg border border-rose-300 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-950 hover:bg-rose-100">
                                            Anonymiser (site)
                                        </button>
                                    </form>
                                    <form method="post" action="<?= $h(url('admin/users/purge')) ?>"
                                          onsubmit="return athPurgeConfirm(this, <?= $emailJs ?>, 'site');">
                                        <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="user_id" value="<?= $siteUid ?>">
                                        <input type="hidden" name="tenant_id" value="<?= $siteTid ?>">
                                        <input type="hidden" name="scope" value="site">
                                        <input type="hidden" name="confirm_email" value="">
                                        <?= $hiddenReturns() ?>
                                        <button type="submit" class="rounded-lg border border-rose-700 bg-rose-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-800">
                                            Supprimer définitivement (site)
                                        </button>
                                    </form>
                                </div>
                            <?php elseif ($isSelf): ?>
                                <p class="text-xs text-slate-400">C’est votre propre compte.</p>
                            <?php endif; ?>
                        </div>

                        <div class="divide-y divide-slate-100">
                            <?php foreach ($memberships as $m): ?>
                                <?php
                                $uid = (int) ($m['id'] ?? 0);
                                $tid = (int) ($m['tenant_id'] ?? 0);
                                $tenantName = (string) ($m['tenant_name'] ?? '');
                                $roleName = trim((string) ($m['role_name'] ?? ''));
                                $st = (string) ($m['status'] ?? '');
                                $isDeleted = !empty($m['deleted_at']);
                                $mCallsign = trim((string) ($m['callsign'] ?? ''));
                                $sanctionsUrl = url('admin/system/member-sanctions') . ($tid > 0 ? '?tenant_id=' . $tid : '');
                                ?>
                                <div class="grid gap-3 px-4 py-3 md:grid-cols-[1.2fr_1fr_auto] md:items-center">
                                    <div>
                                        <p class="font-semibold text-slate-900"><?= $h($tenantName !== '' ? $tenantName : '—') ?></p>
                                        <p class="text-xs text-slate-500">
                                            <?= $h($roleName !== '' ? $roleName : 'Sans rôle') ?>
                                            <?php if ($mCallsign !== ''): ?>
                                                · <?= $h($mCallsign) ?>
                                            <?php endif; ?>
                                            · #<?= $uid ?>
                                        </p>
                                    </div>
                                    <div>
                                        <?php if ($isDeleted): ?>
                                            <span class="inline-flex rounded-md px-2 py-1 text-xs font-semibold bg-slate-200 text-slate-700">Compte supprimé</span>
                                        <?php else: ?>
                                            <span class="inline-flex rounded-md px-2 py-1 text-xs font-semibold <?= $h($statusBadgeClass($st)) ?>">
                                                <?= $h($statusLabel($st)) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex flex-wrap gap-2 justify-start md:justify-end">
                                        <?php if ($uid === $currentActorId): ?>
                                            <span class="text-xs text-slate-400 self-center">Vous</span>
                                        <?php elseif ($isDeleted): ?>
                                            <form method="post" action="<?= $h(url('admin/users/purge')) ?>"
                                                  onsubmit="return athPurgeConfirm(this, <?= $emailJs ?>, 'org');">
                                                <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                                                <input type="hidden" name="user_id" value="<?= $uid ?>">
                                                <input type="hidden" name="tenant_id" value="<?= $tid ?>">
                                                <input type="hidden" name="scope" value="org">
                                                <input type="hidden" name="confirm_email" value="">
                                                <?= $hiddenReturns() ?>
                                                <button type="submit" class="rounded-lg border border-rose-700 bg-rose-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-800">
                                                    Purger cette communauté
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <?php if ($st === 'active'): ?>
                                                <form method="post" action="<?= $h(url('admin/users/set-status')) ?>"
                                                      onsubmit="return confirm('Désactiver l’accès dans <?= $h(addslashes($tenantName)) ?> ?');">
                                                    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                                                    <input type="hidden" name="user_id" value="<?= $uid ?>">
                                                    <input type="hidden" name="tenant_id" value="<?= $tid ?>">
                                                    <input type="hidden" name="status" value="inactive">
                                                    <?= $hiddenReturns() ?>
                                                    <button type="submit" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-950 hover:bg-amber-100">Désactiver</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="post" action="<?= $h(url('admin/users/set-status')) ?>">
                                                    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                                                    <input type="hidden" name="user_id" value="<?= $uid ?>">
                                                    <input type="hidden" name="tenant_id" value="<?= $tid ?>">
                                                    <input type="hidden" name="status" value="active">
                                                    <?= $hiddenReturns() ?>
                                                    <button type="submit" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-950 hover:bg-emerald-100">Réactiver</button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="post" action="<?= $h(url('admin/users/delete')) ?>"
                                                  onsubmit="return confirm('Retirer <?= $h(addslashes($primary)) ?> de « <?= $h(addslashes($tenantName)) ?> » uniquement ?\n\nLes autres communautés restent intactes.');">
                                                <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                                                <input type="hidden" name="user_id" value="<?= $uid ?>">
                                                <input type="hidden" name="tenant_id" value="<?= $tid ?>">
                                                <input type="hidden" name="scope" value="org">
                                                <?= $hiddenReturns() ?>
                                                <button type="submit" class="rounded-lg border border-rose-300 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-950 hover:bg-rose-100">
                                                    Retirer de l’orga
                                                </button>
                                            </form>
                                            <form method="post" action="<?= $h(url('admin/users/purge')) ?>"
                                                  onsubmit="return athPurgeConfirm(this, <?= $emailJs ?>, 'org');">
                                                <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                                                <input type="hidden" name="user_id" value="<?= $uid ?>">
                                                <input type="hidden" name="tenant_id" value="<?= $tid ?>">
                                                <input type="hidden" name="scope" value="org">
                                                <input type="hidden" name="confirm_email" value="">
                                                <?= $hiddenReturns() ?>
                                                <button type="submit" class="rounded-lg border border-rose-700 bg-white px-3 py-1.5 text-xs font-semibold text-rose-800 hover:bg-rose-50">
                                                    Purger orga
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <a href="<?= $h($sanctionsUrl) ?>" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 hover:bg-slate-50">Sanctions</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($pages > 1): ?>
            <nav class="flex flex-wrap items-center justify-between gap-3 text-sm" aria-label="Pagination">
                <?php if ($page > 1): ?>
                    <a href="<?= $h($queryUrl(['page' => $page - 1])) ?>" class="rounded-lg border border-slate-300 bg-white px-3 py-2 font-semibold text-slate-800 hover:bg-slate-50">Page précédente</a>
                <?php else: ?>
                    <span class="text-slate-400">Page précédente</span>
                <?php endif; ?>
                <span class="text-slate-600">Page <?= $page ?> sur <?= $pages ?></span>
                <?php if ($page < $pages): ?>
                    <a href="<?= $h($queryUrl(['page' => $page + 1])) ?>" class="rounded-lg border border-slate-300 bg-white px-3 py-2 font-semibold text-slate-800 hover:bg-slate-50">Page suivante</a>
                <?php else: ?>
                    <span class="text-slate-400">Page suivante</span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>

        <section class="rounded-2xl border border-rose-200 bg-rose-50 p-5">
            <h2 class="text-base font-semibold text-rose-950">Purge des fiches anonymisées</h2>
            <p class="mt-2 max-w-3xl text-sm text-rose-900">
                Les comptes anonymisés restent en base sous « Compte supprimé ». Cette action les
                efface tous définitivement.
            </p>
            <form method="post" action="<?= $h(url('admin/users/purge-anonymises')) ?>"
                  class="mt-4 flex flex-wrap items-end gap-3">
                <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                <?= $hiddenReturns() ?>
                <label class="flex-1 min-w-[16rem]">
                    <span class="block text-xs font-semibold uppercase tracking-wide text-rose-900">Tapez « supprimer definitivement » pour confirmer</span>
                    <input type="text" name="confirm_phrase" autocomplete="off" required
                           class="mt-1 w-full rounded-lg border border-rose-300 bg-white px-3 py-2 text-sm text-slate-900">
                </label>
                <button type="submit" class="rounded-lg border border-rose-700 bg-rose-700 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-800">
                    Purger les fiches anonymisées
                </button>
            </form>
        </section>
    </div>
</div>

<script>
function athPurgeConfirm(form, expectedEmail, scope) {
    var scopeLabel = scope === 'org'
        ? 'cette communauté uniquement'
        : 'TOUT LE SITE (toutes les communautés de cette adresse)';
    var saisi = window.prompt(
        'Suppression DÉFINITIVE — ' + scopeLabel + '.\n\n'
        + 'Compte : ' + expectedEmail + '\n\n'
        + 'Aucune restauration n’est possible.\n\n'
        + 'Retapez l’adresse exacte pour confirmer :'
    );
    if (saisi === null) {
        return false;
    }
    if (saisi.trim().toLowerCase() !== String(expectedEmail).trim().toLowerCase()) {
        window.alert('Adresse incorrecte : le compte n’a pas été touché.');
        return false;
    }
    form.querySelector('input[name="confirm_email"]').value = saisi.trim();
    return true;
}
</script>
