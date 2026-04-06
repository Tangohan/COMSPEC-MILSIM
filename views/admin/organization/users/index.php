<?php
$users = $users ?? [];
$roles = $roles ?? [];
$completenessByUser = $completenessByUser ?? [];
$filters = $filters ?? [];
$usersTotal = $usersTotal ?? null;
$usersPage = $usersPage ?? 1;
$usersTotalPages = $usersTotalPages ?? 1;
$personnelCompletenessByUser = $personnelCompletenessByUser ?? [];

$usersQuery = static function (int $page) use ($filters): string {
    $q = [
        'search' => $filters['search'] ?? null,
        'status' => !empty($filters['status']) ? $filters['status'] : null,
        'role_id' => !empty($filters['role_id']) ? (int) $filters['role_id'] : null,
        'filter_incomplete' => !empty($filters['filter_incomplete']) ? '1' : null,
        'filter_no_unit' => !empty($filters['filter_no_unit']) ? '1' : null,
        'filter_no_role' => !empty($filters['filter_no_role']) ? '1' : null,
        'show_technical' => !empty($filters['show_technical']) ? '1' : null,
        'page' => $page > 1 ? $page : null,
    ];
    $q = array_filter($q, static fn ($v) => $v !== null && $v !== '');

    return url('back-office/users') . ($q ? '?' . http_build_query($q) : '');
};

$userInitials = static function (string $displayName, string $email): string {
    $displayName = trim($displayName);
    if ($displayName !== '') {
        $parts = preg_split('/\s+/u', $displayName, -1, PREG_SPLIT_NO_EMPTY);
        if ($parts !== false && count($parts) >= 2) {
            $a = mb_substr($parts[0], 0, 1, 'UTF-8');
            $b = mb_substr($parts[1], 0, 1, 'UTF-8');

            return mb_strtoupper($a . $b, 'UTF-8');
        }

        return mb_strtoupper(mb_substr($displayName, 0, 2, 'UTF-8'), 'UTF-8');
    }
    $local = preg_replace('/@.*$/', '', $email);

    return mb_strtoupper(mb_substr($local, 0, 2, 'UTF-8'), 'UTF-8');
};
?>
<main class="min-h-[80vh] bg-slate-50">
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="space-y-6">

            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-4 border-b border-slate-200 px-6 py-6 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="mb-2 text-[11px] font-bold uppercase tracking-[0.28em] text-slate-500">Administration organisationnelle</p>
                        <div class="flex flex-wrap items-center gap-3">
                            <h1 class="text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">Utilisateurs</h1>
                            <?php if ($usersTotal !== null): ?>
                            <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                <?= (int) $usersTotal ?> utilisateur(s)
                            </span>
                            <?php endif; ?>
                        </div>
                        <p class="mt-2 text-sm text-slate-600">
                            Membres de la communauté active — comptes, rôles, statuts et complétude (compte + fiche personnelle). Les administrateurs site voient le même périmètre selon l’organisation sélectionnée.
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center gap-3">
                        <a href="<?= url('back-office/users/create') ?>"
                           class="inline-flex items-center justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800">
                            Nouvel utilisateur
                        </a>
                    </div>
                </div>

                <div class="border-b border-slate-200 bg-slate-50/80 px-6 py-5">
                    <form method="get"
                          action="<?= url('back-office/users') ?>"
                          class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-[minmax(280px,1.4fr)_180px_220px_auto_auto]">

                        <div class="relative xl:col-span-1">
                            <label for="search" class="mb-1.5 block text-[11px] font-bold uppercase tracking-[0.22em] text-slate-500">
                                Recherche
                            </label>
                            <input
                                id="search"
                                type="text"
                                name="search"
                                value="<?= htmlspecialchars($filters['search'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="Email, nom, indicatif"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-slate-400 focus:ring-2 focus:ring-slate-200"
                            >
                        </div>

                        <div>
                            <label for="status" class="mb-1.5 block text-[11px] font-bold uppercase tracking-[0.22em] text-slate-500">
                                Statut
                            </label>
                            <select
                                id="status"
                                name="status"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-900 outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-200"
                            >
                                <option value="">Tous les statuts</option>
                                <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Actif</option>
                                <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>En attente</option>
                                <option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactif</option>
                            </select>
                        </div>

                        <div>
                            <label for="role_id" class="mb-1.5 block text-[11px] font-bold uppercase tracking-[0.22em] text-slate-500">
                                Rôle
                            </label>
                            <select
                                id="role_id"
                                name="role_id"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-900 outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-200"
                            >
                                <option value="">Tous les rôles</option>
                                <?php foreach ($roles as $r): ?>
                                <option value="<?= (int) $r['id'] ?>" <?= (int) ($filters['role_id'] ?? 0) === (int) $r['id'] ? 'selected' : '' ?>><?= htmlspecialchars($r['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="flex flex-col gap-2 md:col-span-2 xl:col-span-2">
                            <label class="flex min-h-11 w-full items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 transition hover:border-slate-300">
                                <input
                                    type="checkbox"
                                    name="filter_incomplete"
                                    value="1"
                                    class="h-4 w-4 shrink-0 rounded border-slate-300 text-slate-900 focus:ring-slate-300"
                                    <?= !empty($filters['filter_incomplete']) ? 'checked' : '' ?>
                                >
                                <span class="font-medium">Profils à corriger</span>
                            </label>
                            <label class="flex min-h-11 w-full items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 transition hover:border-slate-300">
                                <input
                                    type="checkbox"
                                    name="filter_no_unit"
                                    value="1"
                                    class="h-4 w-4 shrink-0 rounded border-slate-300 text-slate-900 focus:ring-slate-300"
                                    <?= !empty($filters['filter_no_unit']) ? 'checked' : '' ?>
                                >
                                <span class="font-medium">Sans affectation d’unité</span>
                            </label>
                            <label class="flex min-h-11 w-full items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 transition hover:border-slate-300">
                                <input
                                    type="checkbox"
                                    name="filter_no_role"
                                    value="1"
                                    class="h-4 w-4 shrink-0 rounded border-slate-300 text-slate-900 focus:ring-slate-300"
                                    <?= !empty($filters['filter_no_role']) ? 'checked' : '' ?>
                                >
                                <span class="font-medium">Sans rôle communautaire</span>
                            </label>
                        </div>

                        <div class="flex flex-wrap items-end gap-2 md:col-span-2 xl:col-span-1">
                            <button
                                type="submit"
                                class="inline-flex h-11 items-center justify-center rounded-xl bg-slate-800 px-4 text-sm font-semibold text-white transition hover:bg-slate-700">
                                Filtrer
                            </button>
                            <a
                                href="<?= url('back-office/users') ?>"
                                class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
                                Réinitialiser
                            </a>
                        </div>

                        <div class="md:col-span-2 xl:col-span-full">
                            <label class="inline-flex cursor-pointer items-center gap-3 rounded-xl border border-dashed border-slate-200 bg-white px-4 py-3 text-sm text-slate-600 transition hover:border-slate-300">
                                <input
                                    type="checkbox"
                                    name="show_technical"
                                    value="1"
                                    class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-300"
                                    <?= !empty($filters['show_technical']) ? 'checked' : '' ?>
                                    onchange="this.form.submit()"
                                >
                                <span>Afficher les comptes techniques (modération automatique)</span>
                            </label>
                        </div>
                    </form>
                </div>

                <?php if ($usersTotal !== null): ?>
                <div class="flex flex-col gap-3 border-b border-slate-200 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm font-medium text-slate-600">
                        <?= (int) $usersTotal ?> utilisateur(s) — page <span class="font-bold text-slate-900"><?= (int) $usersPage ?></span> / <span class="font-bold text-slate-900"><?= (int) $usersTotalPages ?></span>
                    </p>

                    <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 font-semibold text-emerald-700">Actifs</span>
                        <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 font-semibold text-amber-700">En attente</span>
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 font-semibold text-slate-700">Inactifs</span>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (empty($users)): ?>
                <div class="px-6 py-16 text-center text-slate-500">
                    <p class="text-sm font-medium">Aucun utilisateur ne correspond aux critères.</p>
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-[11px] font-bold uppercase tracking-[0.22em] text-slate-500">Utilisateur</th>
                                <th class="px-6 py-4 text-left text-[11px] font-bold uppercase tracking-[0.22em] text-slate-500">Rôle</th>
                                <th class="px-6 py-4 text-left text-[11px] font-bold uppercase tracking-[0.22em] text-slate-500">Statut</th>
                                <th class="px-6 py-4 text-left text-[11px] font-bold uppercase tracking-[0.22em] text-slate-500">Complétude</th>
                                <th class="px-6 py-4 text-right text-[11px] font-bold uppercase tracking-[0.22em] text-slate-500">Actions</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100 bg-white">
                            <?php foreach ($users as $u):
                                $uid = (int) $u['id'];
                                $comp = $completenessByUser[$uid] ?? ['score' => 0, 'sections_critiques' => [], 'missing' => []];
                                $pComp = $personnelCompletenessByUser[$uid] ?? null;
                                $isTech = !empty($u['is_service_account']);
                                $display = trim((string) ($u['display_name'] ?? ''));
                                $email = (string) ($u['email'] ?? '');
                                $initials = $userInitials($display, $email);
                                $acct = (int) ($comp['score'] ?? 0);
                                $pers = $pComp !== null ? (int) ($pComp['score'] ?? 0) : 100;
                                $overall = $isTech ? null : min($acct, $pers);
                                $hasCrit = !empty($comp['sections_critiques']) || ($pComp !== null && !empty($pComp['sections_critiques']));
                                $hintParts = [];
                                foreach ($comp['missing'] ?? [] as $m) {
                                    $hintParts[] = (string) ($m['label'] ?? '');
                                }
                                if ($pComp !== null && !empty($pComp['missing_labels'])) {
                                    foreach (array_slice($pComp['missing_labels'], 0, 4) as $lbl) {
                                        $hintParts[] = (string) $lbl;
                                    }
                                }
                                $hintParts = array_values(array_filter(array_unique($hintParts)));
                                $hintShort = implode(', ', array_slice($hintParts, 0, 3));
                                if (count($hintParts) > 3) {
                                    $hintShort .= '…';
                                }
                                $barTone = 'amber';
                                $labelCompl = 'À corriger';
                                if ($isTech) {
                                    $barTone = 'slate';
                                } elseif ($overall !== null && $overall >= 100) {
                                    $barTone = 'emerald';
                                    $labelCompl = 'Complet';
                                } elseif ($hasCrit) {
                                    $barTone = 'rose';
                                    $labelCompl = 'Incomplet';
                                }
                                $avatarBg = $isTech ? 'bg-slate-100 text-slate-700' : (($u['status'] ?? '') === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-700');
                            ?>
                            <tr class="transition hover:bg-slate-50/80">
                                <td class="px-6 py-5 align-top">
                                    <div class="flex items-start gap-4">
                                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl text-sm font-black <?= $avatarBg ?>">
                                            <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-bold text-slate-900">
                                                <?= htmlspecialchars($display !== '' ? $display : '—') ?>
                                                <?php if ($isTech): ?>
                                                <span class="ml-1 align-middle text-[10px] font-bold uppercase text-slate-500">Technique</span>
                                                <?php endif; ?>
                                            </p>
                                            <p class="mt-1 truncate text-sm text-slate-500">
                                                <?= htmlspecialchars($email) ?>
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-6 py-5 align-top">
                                    <?php $rn = trim((string) ($u['roles_display'] ?? $u['role_name'] ?? '')); ?>
                                    <?php if ($rn === ''): ?>
                                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-500">
                                        Aucun rôle
                                    </span>
                                    <?php else: ?>
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-200">
                                        <?= htmlspecialchars($rn) ?>
                                    </span>
                                    <?php endif; ?>
                                </td>

                                <td class="px-6 py-5 align-top">
                                    <?php $st = (string) ($u['status'] ?? ''); ?>
                                    <?php if ($st === 'active'): ?>
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-200">Actif</span>
                                    <?php elseif ($st === 'inactive'): ?>
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 ring-1 ring-inset ring-slate-200">Inactif</span>
                                    <?php else: ?>
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-200"><?= htmlspecialchars($st !== '' ? $st : '—') ?></span>
                                    <?php endif; ?>
                                </td>

                                <td class="px-6 py-5 align-top">
                                    <?php if ($isTech): ?>
                                    <div class="max-w-[200px]">
                                        <p class="text-sm text-slate-500">Compte système — pas de fiche personnage.</p>
                                    </div>
                                    <?php else: ?>
                                    <div class="max-w-[200px]">
                                        <div class="mb-2 flex items-center justify-between gap-3">
                                            <span class="text-sm font-semibold <?= $barTone === 'emerald' ? 'text-emerald-700' : ($barTone === 'rose' ? 'text-rose-700' : 'text-amber-700') ?>"><?= (int) $overall ?>%</span>
                                            <span class="text-xs font-medium text-slate-500"><?= htmlspecialchars($labelCompl) ?></span>
                                        </div>
                                        <div class="h-2.5 overflow-hidden rounded-full <?= $barTone === 'emerald' ? 'bg-emerald-100' : ($barTone === 'rose' ? 'bg-rose-100' : 'bg-amber-100') ?>">
                                            <div class="h-full rounded-full <?= $barTone === 'emerald' ? 'bg-emerald-500' : ($barTone === 'rose' ? 'bg-rose-500' : 'bg-amber-500') ?>" style="width: <?= min(100, max(0, (int) $overall)) ?>%"></div>
                                        </div>
                                        <p class="mt-2 text-xs text-slate-500" title="<?= htmlspecialchars($hintShort, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= $hintShort !== '' ? htmlspecialchars($hintShort) : 'Compte ' . $acct . '% · Fiche ' . $pers . '%' ?>
                                        </p>
                                    </div>
                                    <?php endif; ?>
                                </td>

                                <td class="px-6 py-5 align-top">
                                    <div class="flex flex-wrap items-center justify-end gap-2">
                                        <a href="<?= url('back-office/users/' . $uid) ?>"
                                           class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 hover:text-slate-900">
                                            Voir
                                        </a>
                                        <a href="<?= url('back-office/users/' . $uid . '/edit') ?>"
                                           class="inline-flex items-center rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white transition hover:bg-slate-800">
                                            Compte
                                        </a>
                                        <?php if (!$isTech): ?>
                                        <a href="<?= url('personnel/' . $uid . '/edit') ?>"
                                           class="inline-flex items-center rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-800 transition hover:bg-blue-100">
                                            Fiche perso.
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($usersTotal !== null && (int) $usersTotalPages > 1): ?>
                <div class="flex flex-wrap items-center justify-between gap-4 border-t border-slate-200 px-6 py-4">
                    <?php if ($usersPage > 1): ?>
                    <a class="text-sm font-medium text-slate-600 hover:text-slate-900 hover:underline" href="<?= htmlspecialchars($usersQuery($usersPage - 1), ENT_QUOTES, 'UTF-8') ?>">← Précédent</a>
                    <?php else: ?><span></span><?php endif; ?>
                    <?php if ($usersPage < $usersTotalPages): ?>
                    <a class="text-sm font-medium text-slate-600 hover:text-slate-900 hover:underline" href="<?= htmlspecialchars($usersQuery($usersPage + 1), ENT_QUOTES, 'UTF-8') ?>">Suivant →</a>
                    <?php else: ?><span></span><?php endif; ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>

                <div class="flex flex-col gap-4 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                    <a href="<?= url('back-office') ?>"
                       class="text-sm font-medium text-slate-600 underline-offset-4 transition hover:text-slate-950 hover:underline">
                        Retour administration organisationnelle
                    </a>

                    <div class="text-xs uppercase tracking-[0.22em] text-slate-400">
                        Back-office utilisateurs
                    </div>
                </div>
            </section>
        </div>
    </div>
</main>
