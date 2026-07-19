<?php
declare(strict_types=1);

$rows = is_array($platformUsers ?? null) ? $platformUsers : [];
$total = (int) ($platformUsersTotal ?? 0);
$page = max(1, (int) ($platformUsersPage ?? 1));
$pages = max(1, (int) ($platformUsersPages ?? 1));
$q = (string) ($platformUsersQ ?? '');
$statusFilter = (string) ($platformUsersStatus ?? '');
$tenantFilter = (int) ($platformUsersTenantId ?? 0);
$tenants = is_array($platformTenants ?? null) ? $platformTenants : [];

$statusLabel = static function (string $status): string {
    return match ($status) {
        'active' => 'Compte actif',
        'inactive' => 'Compte désactivé',
        'pending_verification' => 'En attente de vérification de l’e-mail',
        default => 'Statut inconnu',
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
            <a href="<?= htmlspecialchars(url('admin'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-800 hover:text-emerald-950">Administration plateforme</a>
            <span class="mx-2" aria-hidden="true">/</span>
            <span class="text-slate-800">Comptes utilisateurs</span>
        </nav>

        <?php $ok = \App\Core\Session::getFlash('success'); ?>
        <?php if ($ok): ?>
            <p class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900"><?= htmlspecialchars((string) $ok, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <?php $err = \App\Core\Session::getFlash('error'); ?>
        <?php if ($err): ?>
            <p class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-900"><?= htmlspecialchars((string) $err, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <header>
            <h1 class="text-2xl font-black text-slate-900">Comptes utilisateurs (toutes communautés)</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-600 leading-relaxed">
                Recherchez un compte sur l’ensemble du site, filtrez par communauté ou par état d’accès, puis activez ou désactivez la connexion.
                Les dossiers RH détaillés restent dans le back-office de chaque communauté&nbsp;; les mesures d’accès avancées se gèrent via les sanctions site.
            </p>
        </header>

        <div class="flex flex-wrap gap-3 text-sm">
            <a href="<?= htmlspecialchars(url('admin/system/member-sanctions'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-rose-800 hover:underline">Sanctions à l’échelle du site</a>
            <a href="<?= htmlspecialchars(url('admin/site-roles'), ENT_QUOTES, 'UTF-8') ?>" class="text-slate-600 hover:underline">Affectations rôles site</a>
            <a href="<?= htmlspecialchars(url('admin/tenants'), ENT_QUOTES, 'UTF-8') ?>" class="text-slate-600 hover:underline">Annuaire des communautés</a>
        </div>

        <form method="get" action="<?= htmlspecialchars(url('admin/users'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="grid gap-3 md:grid-cols-4">
                <div class="md:col-span-2">
                    <label for="platform-users-q" class="block text-xs font-medium text-slate-500 mb-1">Rechercher</label>
                    <input id="platform-users-q" type="search" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"
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
                                <?= htmlspecialchars((string) ($t['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
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
                    </select>
                </div>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <button type="submit" class="inline-flex rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filtrer</button>
                <a href="<?= htmlspecialchars(url('admin/users'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Réinitialiser</a>
            </div>
        </form>

        <p class="text-sm text-slate-600">
            <?= $total === 0 ? 'Aucun compte trouvé.' : ($total === 1 ? '1 compte trouvé.' : number_format($total, 0, ',', ' ') . ' comptes trouvés.') ?>
            <?php if ($pages > 1): ?>
                <span class="text-slate-400">— page <?= $page ?> / <?= $pages ?></span>
            <?php endif; ?>
        </p>

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Personne</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Communauté</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Rôle communautaire</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">État</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if ($rows === []): ?>
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-slate-500">Aucun compte ne correspond à ces critères.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $u): ?>
                            <?php
                            $uid = (int) ($u['id'] ?? 0);
                            $tid = (int) ($u['tenant_id'] ?? 0);
                            $email = (string) ($u['email'] ?? '');
                            $display = trim((string) ($u['display_name'] ?? ''));
                            $callsign = trim((string) ($u['callsign'] ?? ''));
                            $tenantName = (string) ($u['tenant_name'] ?? '');
                            $roleName = trim((string) ($u['role_name'] ?? ''));
                            $st = (string) ($u['status'] ?? '');
                            $primary = $callsign !== '' ? $callsign : ($display !== '' ? $display : $email);
                            $sanctionsUrl = url('admin/system/member-sanctions') . ($tid > 0 ? '?tenant_id=' . $tid : '');
                            ?>
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-slate-900"><?= htmlspecialchars($primary, ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php if ($display !== '' && strcasecmp($display, $primary) !== 0): ?>
                                        <p class="text-xs text-slate-600"><?= htmlspecialchars($display, ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                    <?php if ($email !== ''): ?>
                                        <p class="text-xs text-slate-500"><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-slate-800"><?= htmlspecialchars($tenantName !== '' ? $tenantName : '—', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-4 py-3 text-slate-700"><?= htmlspecialchars($roleName !== '' ? $roleName : '—', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="inline-flex rounded-md px-2 py-1 text-xs font-semibold <?= htmlspecialchars($statusBadgeClass($st), ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($statusLabel($st), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-col gap-2 min-w-[10rem]">
                                        <?php if ($st === 'active'): ?>
                                            <form method="post" action="<?= htmlspecialchars(url('admin/users/set-status'), ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('Désactiver ce compte ? La personne ne pourra plus se connecter.');">
                                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="user_id" value="<?= $uid ?>">
                                                <input type="hidden" name="tenant_id" value="<?= $tid ?>">
                                                <input type="hidden" name="status" value="inactive">
                                                <input type="hidden" name="return_q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="return_status" value="<?= htmlspecialchars($statusFilter, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="return_tenant_id" value="<?= $tenantFilter > 0 ? $tenantFilter : '' ?>">
                                                <input type="hidden" name="return_page" value="<?= $page ?>">
                                                <button type="submit" class="w-full rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-950 hover:bg-rose-100">Désactiver</button>
                                            </form>
                                        <?php elseif ($st === 'inactive' || $st === 'pending_verification'): ?>
                                            <form method="post" action="<?= htmlspecialchars(url('admin/users/set-status'), ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="user_id" value="<?= $uid ?>">
                                                <input type="hidden" name="tenant_id" value="<?= $tid ?>">
                                                <input type="hidden" name="status" value="active">
                                                <input type="hidden" name="return_q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="return_status" value="<?= htmlspecialchars($statusFilter, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="return_tenant_id" value="<?= $tenantFilter > 0 ? $tenantFilter : '' ?>">
                                                <input type="hidden" name="return_page" value="<?= $page ?>">
                                                <button type="submit" class="w-full rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-950 hover:bg-emerald-100">Réactiver</button>
                                            </form>
                                        <?php endif; ?>
                                        <a href="<?= htmlspecialchars($sanctionsUrl, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex justify-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 hover:bg-slate-50">Sanctions</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pages > 1): ?>
            <nav class="flex flex-wrap items-center justify-between gap-3 text-sm" aria-label="Pagination">
                <?php if ($page > 1): ?>
                    <a href="<?= htmlspecialchars($queryUrl(['page' => $page - 1]), ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg border border-slate-300 bg-white px-3 py-2 font-semibold text-slate-800 hover:bg-slate-50">Page précédente</a>
                <?php else: ?>
                    <span class="text-slate-400">Page précédente</span>
                <?php endif; ?>
                <span class="text-slate-600">Page <?= $page ?> sur <?= $pages ?></span>
                <?php if ($page < $pages): ?>
                    <a href="<?= htmlspecialchars($queryUrl(['page' => $page + 1]), ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg border border-slate-300 bg-white px-3 py-2 font-semibold text-slate-800 hover:bg-slate-50">Page suivante</a>
                <?php else: ?>
                    <span class="text-slate-400">Page suivante</span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </div>
</div>
