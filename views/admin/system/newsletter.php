<?php
declare(strict_types=1);

$schemaReady = !empty($newsletterSchemaReady);
$counts = is_array($newsletterCounts ?? null) ? $newsletterCounts : ['pending' => 0, 'subscribed' => 0, 'unsubscribed' => 0, 'total' => 0];
$rows = is_array($newsletterRows ?? null) ? $newsletterRows : [];
$total = (int) ($newsletterTotal ?? 0);
$statut = (string) ($newsletterStatut ?? 'all');
$q = (string) ($newsletterQuery ?? '');
$page = (int) ($newsletterPage ?? 1);
$totalPages = (int) ($newsletterTotalPages ?? 1);

$statutLabels = [
    'all' => 'Tous les contacts',
    'pending' => 'En attente de confirmation',
    'subscribed' => 'Inscriptions confirmées',
    'unsubscribed' => 'Désinscriptions',
];

$rowStatutLabel = static function (string $s): string {
    return match ($s) {
        'pending' => 'En attente de confirmation',
        'subscribed' => 'Confirmée',
        'unsubscribed' => 'Désinscrit·e',
        default => '—',
    };
};

$rowStatutClass = static function (string $s): string {
    return match ($s) {
        'pending' => 'bg-amber-50 text-amber-900 ring-amber-200',
        'subscribed' => 'bg-emerald-50 text-emerald-900 ring-emerald-200',
        'unsubscribed' => 'bg-slate-100 text-slate-700 ring-slate-200',
        default => 'bg-slate-50 text-slate-600 ring-slate-200',
    };
};

$formatDt = static function (?string $v): string {
    if ($v === null || $v === '') {
        return '—';
    }
    $t = strtotime($v);

    return $t ? date('d/m/Y à H:i', $t) : '—';
};

$truncate = static function (?string $v, int $max = 56): string {
    $s = trim((string) $v);
    if ($s === '') {
        return '—';
    }
    if (mb_strlen($s) <= $max) {
        return $s;
    }

    return mb_substr($s, 0, $max - 1) . '…';
};

$baseListUrl = url('admin/newsletter');
$queryBase = static function (string $statutKey, string $search, int $pg) use ($baseListUrl): string {
    $qs = http_build_query(array_filter([
        'statut' => $statutKey === 'all' ? null : $statutKey,
        'q' => $search !== '' ? $search : null,
        'page' => $pg > 1 ? $pg : null,
    ], static fn ($v) => $v !== null && $v !== ''));

    return $qs !== '' ? $baseListUrl . '?' . $qs : $baseListUrl;
};
?>
<div class="max-w-6xl mx-auto px-6 py-10">
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Lettre d’information du site</h1>
            <p class="text-sm text-slate-600 mt-2 max-w-2xl leading-relaxed">
                Liste des personnes qui se sont inscrites depuis la page d’accueil publique. Les adresses sont des données personnelles :
                limitez la consultation aux besoins légitimes (support, conformité).
            </p>
        </div>
        <a href="<?= htmlspecialchars(url('admin'), ENT_QUOTES, 'UTF-8') ?>" class="shrink-0 text-sm font-semibold text-slate-500 hover:text-slate-800">Tableau de bord</a>
    </div>

    <?php if (!$schemaReady): ?>
        <div class="rounded-2xl border border-rose-200 bg-rose-50/90 px-5 py-4 text-sm text-rose-900">
            <p class="font-semibold">Module non disponible</p>
            <p class="mt-1 text-rose-800/90">La table des inscriptions n’est pas encore créée sur cette base. Appliquez la migration correspondante puis rechargez cette page.</p>
        </div>
    <?php else: ?>
        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-8" aria-label="Synthèse des inscriptions">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Total enregistré</p>
                <p class="mt-1 text-2xl font-black tabular-nums text-slate-900"><?= (int) ($counts['total'] ?? 0) ?></p>
            </div>
            <div class="rounded-2xl border border-amber-200/80 bg-amber-50/50 p-4 shadow-sm">
                <p class="text-[10px] font-bold uppercase tracking-wider text-amber-900/80">En attente de confirmation</p>
                <p class="mt-1 text-2xl font-black tabular-nums text-amber-950"><?= (int) ($counts['pending'] ?? 0) ?></p>
            </div>
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50/50 p-4 shadow-sm">
                <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-900/80">Confirmées</p>
                <p class="mt-1 text-2xl font-black tabular-nums text-emerald-950"><?= (int) ($counts['subscribed'] ?? 0) ?></p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4 shadow-sm">
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-600">Désinscriptions</p>
                <p class="mt-1 text-2xl font-black tabular-nums text-slate-900"><?= (int) ($counts['unsubscribed'] ?? 0) ?></p>
            </div>
        </section>

        <form method="get" action="<?= htmlspecialchars($baseListUrl, ENT_QUOTES, 'UTF-8') ?>" class="mb-6 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:flex-wrap sm:items-end">
            <div class="min-w-0 flex-1 sm:max-w-xs">
                <label for="nl-statut" class="block text-xs font-semibold text-slate-600 mb-1">État</label>
                <select id="nl-statut" name="statut" class="bo-select w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-800">
                    <?php foreach ($statutLabels as $key => $lab): ?>
                        <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" <?= $statut === $key ? ' selected' : '' ?>><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="min-w-0 flex-1 sm:max-w-md">
                <label for="nl-q" class="block text-xs font-semibold text-slate-600 mb-1">Recherche par adresse e-mail</label>
                <input id="nl-q" type="search" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" maxlength="120" placeholder="ex. nom du domaine…" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 placeholder:text-slate-400" autocomplete="off" />
            </div>
            <div class="flex gap-2">
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">Filtrer</button>
                <?php if ($statut !== 'all' || $q !== ''): ?>
                    <a href="<?= htmlspecialchars($baseListUrl, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Réinitialiser</a>
                <?php endif; ?>
            </div>
        </form>

        <div class="mb-3 flex flex-wrap items-center justify-between gap-2 text-sm text-slate-600">
            <p><?= $total === 0 ? 'Aucun résultat.' : ($total === 1 ? '1 contact affiché.' : htmlspecialchars((string) $total, ENT_QUOTES, 'UTF-8') . ' contacts affichés.'); ?></p>
            <?php if ($totalPages > 1): ?>
                <nav class="flex flex-wrap items-center gap-1" aria-label="Pagination">
                    <?php if ($page > 1): ?>
                        <a class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50" href="<?= htmlspecialchars($queryBase($statut, $q, $page - 1), ENT_QUOTES, 'UTF-8') ?>">Précédent</a>
                    <?php endif; ?>
                    <span class="px-2 text-xs text-slate-500">Page <?= (int) $page ?> / <?= (int) $totalPages ?></span>
                    <?php if ($page < $totalPages): ?>
                        <a class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50" href="<?= htmlspecialchars($queryBase($statut, $q, $page + 1), ENT_QUOTES, 'UTF-8') ?>">Suivant</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50/90 text-xs font-bold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Adresse e-mail</th>
                        <th class="px-4 py-3">État</th>
                        <th class="px-4 py-3 hidden lg:table-cell">Confirmée le</th>
                        <th class="px-4 py-3 hidden md:table-cell">Dernière activité</th>
                        <th class="px-4 py-3 hidden xl:table-cell">Enregistré le</th>
                        <th class="px-4 py-3 hidden 2xl:table-cell">Origine</th>
                        <th class="px-4 py-3 hidden 2xl:table-cell">Réseau (indicatif)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if ($rows === []): ?>
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-slate-500">Aucune ligne pour ces critères.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $st = (string) ($row['status'] ?? '');
                            $email = (string) ($row['email'] ?? '');
                            ?>
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-4 py-3 font-medium text-slate-900 break-all"><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset <?= htmlspecialchars($rowStatutClass($st), ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($rowStatutLabel($st), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-600 whitespace-nowrap hidden lg:table-cell"><?= htmlspecialchars($formatDt(isset($row['subscribed_at']) ? (string) $row['subscribed_at'] : null), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-4 py-3 text-slate-600 whitespace-nowrap hidden md:table-cell"><?= htmlspecialchars($formatDt(isset($row['last_event_at']) ? (string) $row['last_event_at'] : null), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-4 py-3 text-slate-600 whitespace-nowrap hidden xl:table-cell"><?= htmlspecialchars($formatDt(isset($row['created_at']) ? (string) $row['created_at'] : null), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-4 py-3 text-slate-500 text-xs hidden 2xl:table-cell"><?= htmlspecialchars((string) ($row['source'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-4 py-3 text-slate-500 font-mono text-xs hidden 2xl:table-cell"><?= htmlspecialchars($truncate((string) ($row['ip_address'] ?? ''), 40), ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                            <tr class="md:hidden border-b border-slate-100 bg-slate-50/40">
                                <td colspan="7" class="px-4 py-2 text-xs text-slate-500">
                                    Confirmée : <?= htmlspecialchars($formatDt(isset($row['subscribed_at']) ? (string) $row['subscribed_at'] : null), ENT_QUOTES, 'UTF-8') ?>
                                    · Dernière activité : <?= htmlspecialchars($formatDt(isset($row['last_event_at']) ? (string) $row['last_event_at'] : null), ENT_QUOTES, 'UTF-8') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <p class="mt-4 text-xs text-slate-500">La colonne « navigateur » n’est pas affichée ici ; en cas d’abus, croisez les journaux serveur et l’audit applicatif.</p>
    <?php endif; ?>
</div>
