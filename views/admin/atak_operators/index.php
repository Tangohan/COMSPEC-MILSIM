<?php
declare(strict_types=1);

/** @var list<array<string,mixed>> $atakOperators */
/** @var array{total:int,linked:int,delayed:int,offline:int,shown:int} $atakOperatorsStats */
/** @var list<array{id:int,label:string}> $atakOperatorsMaps */
/** @var int $atakOperatorsMapId */
/** @var string $atakOperatorsFilter */
/** @var string $atakOperatorsQuery */
/** @var int $atakOperatorsRefreshSeconds */

$rows = is_array($atakOperators ?? null) ? $atakOperators : [];
$stats = is_array($atakOperatorsStats ?? null) ? $atakOperatorsStats : [];
$maps = is_array($atakOperatorsMaps ?? null) ? $atakOperatorsMaps : [];
$mapId = (int) ($atakOperatorsMapId ?? 1);
$filter = (string) ($atakOperatorsFilter ?? 'liaison');
$q = (string) ($atakOperatorsQuery ?? '');
$refreshSeconds = max(15, (int) ($atakOperatorsRefreshSeconds ?? 30));

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$statusMeta = static function (string $status): array {
    return match ($status) {
        'linked' => ['label' => 'En liaison', 'class' => 'bg-emerald-100 text-emerald-900 border-emerald-200'],
        'delayed' => ['label' => 'Signal faible', 'class' => 'bg-amber-100 text-amber-950 border-amber-200'],
        default => ['label' => 'Hors ligne', 'class' => 'bg-slate-100 text-slate-700 border-slate-200'],
    };
};

$baseQuery = static function (array $overrides = []) use ($mapId, $filter, $q): string {
    $params = array_merge([
        'carte' => $mapId,
        'statut' => $filter,
        'q' => $q,
    ], $overrides);
    if (($params['q'] ?? '') === '') {
        unset($params['q']);
    }
    return http_build_query($params);
};

$pageUrl = url('back-office/atak/operateurs');
$exportUrl = url('back-office/atak/operateurs/export?' . $baseQuery());
$refreshUrl = url('back-office/atak/operateurs?' . $baseQuery());
?>
<div class="max-w-[1400px] mx-auto px-4 sm:px-6 py-8 space-y-5"
     data-atak-operators-refresh="<?= (int) $refreshSeconds ?>"
     data-atak-operators-url="<?= $h($refreshUrl) ?>">
    <header class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">ATAK · Sessions</p>
                <h1 class="mt-2 text-2xl font-black text-slate-900">Sessions & connexions</h1>
                <p class="mt-2 text-sm text-slate-600 max-w-3xl">
                    Opérateurs actuellement connectés via leur terminal de situation.
                    Une présence disparaît automatiquement après environ trois minutes sans mise à jour.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="<?= $h($refreshUrl) ?>" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Actualiser</a>
                <a href="<?= $h($exportUrl) ?>" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Exporter CSV</a>
                <a href="<?= $h(url('atak')) ?>" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Ouvrir la carte</a>
                <a href="<?= $h(url('back-office/atak/fire-teams')) ?>" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Équipes de feu</a>
            </div>
        </div>
        <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl bg-emerald-50 p-3">
                <p class="text-xs uppercase text-emerald-700">En liaison</p>
                <p class="text-2xl font-black text-emerald-900"><?= (int) ($stats['linked'] ?? 0) ?></p>
            </div>
            <div class="rounded-xl bg-amber-50 p-3">
                <p class="text-xs uppercase text-amber-800">Signal faible</p>
                <p class="text-2xl font-black text-amber-950"><?= (int) ($stats['delayed'] ?? 0) ?></p>
            </div>
            <div class="rounded-xl bg-slate-50 p-3">
                <p class="text-xs uppercase text-slate-500">Hors ligne</p>
                <p class="text-2xl font-black text-slate-900"><?= (int) ($stats['offline'] ?? 0) ?></p>
            </div>
            <div class="rounded-xl bg-slate-50 p-3">
                <p class="text-xs uppercase text-slate-500">Lignes affichées</p>
                <p class="text-2xl font-black text-slate-900"><?= (int) ($stats['shown'] ?? count($rows)) ?></p>
            </div>
        </div>
    </header>

    <form method="get" action="<?= $h($pageUrl) ?>" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
            <div class="flex-1 min-w-[12rem]">
                <label for="atak-op-q" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Recherche</label>
                <input id="atak-op-q" type="search" name="q" value="<?= $h($q) ?>"
                       placeholder="Indicatif, ID militaire, rôle, compte…"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
            </div>
            <div class="w-full lg:w-48">
                <label for="atak-op-statut" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Statut</label>
                <select id="atak-op-statut" name="statut"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                    <option value="liaison" <?= $filter === 'liaison' ? 'selected' : '' ?>>En liaison seulement</option>
                    <option value="tous" <?= $filter === 'tous' ? 'selected' : '' ?>>Tous les opérateurs</option>
                    <option value="hors_ligne" <?= $filter === 'hors_ligne' ? 'selected' : '' ?>>Hors ligne seulement</option>
                </select>
            </div>
            <?php if (count($maps) > 1): ?>
                <div class="w-full lg:w-56">
                    <label for="atak-op-carte" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Carte</label>
                    <select id="atak-op-carte" name="carte"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                        <?php foreach ($maps as $m): ?>
                            <option value="<?= (int) $m['id'] ?>" <?= (int) $m['id'] === $mapId ? 'selected' : '' ?>>
                                <?= $h($m['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php else: ?>
                <input type="hidden" name="carte" value="<?= (int) $mapId ?>">
            <?php endif; ?>
            <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                Filtrer
            </button>
        </div>
        <p class="mt-3 text-xs text-slate-500">
            Actualisation automatique toutes les <?= (int) $refreshSeconds ?> secondes ·
            <span id="atak-op-refresh-hint">prochaine dans <?= (int) $refreshSeconds ?> s</span>
        </p>
    </form>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <?php if ($rows === []): ?>
            <div class="p-8 text-center text-sm text-slate-500">
                <?php if ($filter === 'liaison'): ?>
                    Aucun opérateur en liaison pour le moment.
                    Vérifiez qu’au moins un terminal envoie sa position, puis actualisez.
                <?php else: ?>
                    Aucun opérateur ne correspond à ces filtres.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-[13px] leading-tight">
                    <thead class="bg-slate-100 border-b border-slate-200 text-[11px] uppercase tracking-wide text-slate-500 sticky top-0">
                        <tr>
                            <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap">Indicatif</th>
                            <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap">ID militaire</th>
                            <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap">Rôle</th>
                            <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap">Statut liaison</th>
                            <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap">Grille</th>
                            <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap">Cap</th>
                            <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap">Unité / groupe</th>
                            <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap">Équipe de feu</th>
                            <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap">Compte lié</th>
                            <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap">Dernière MAJ</th>
                            <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap">Carte</th>
                            <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap">Fiche</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($rows as $row):
                            $st = $statusMeta((string) ($row['status'] ?? ''));
                            $linkedUrl = trim((string) ($row['linked_url'] ?? ''));
                            $linkedName = trim((string) ($row['linked_display_name'] ?? ''));
                            $ftId = (int) ($row['fire_team_id'] ?? 0);
                            $ftLabel = trim((string) ($row['fire_team_label'] ?? ''));
                            $callSign = trim((string) ($row['call_sign'] ?? ''));
                            $ficheUrl = $callSign !== ''
                                ? url('back-office/atak/fiche-operateur?indicatif=' . rawurlencode($callSign))
                                : '';
                            ?>
                            <tr class="hover:bg-slate-50/90">
                                <td class="px-3 py-2 font-semibold text-slate-900 whitespace-nowrap">
                                    <?php if ($ficheUrl !== ''): ?>
                                        <a class="underline decoration-slate-300 hover:decoration-slate-700" href="<?= $h($ficheUrl) ?>"><?= $h($callSign) ?></a>
                                    <?php else: ?>
                                        <?= $h($callSign) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-2 text-slate-700 font-mono text-[12px] whitespace-nowrap"><?= $h($row['military_id'] ?? '') !== '' ? $h($row['military_id']) : '—' ?></td>
                                <td class="px-3 py-2 text-slate-700 whitespace-nowrap"><?= $h($row['role_label'] ?? '') !== '' ? $h($row['role_label']) : '—' ?></td>
                                <td class="px-3 py-2 whitespace-nowrap">
                                    <span class="inline-flex items-center rounded-md border px-2 py-0.5 text-[11px] font-semibold <?= $h($st['class']) ?>">
                                        <?= $h($st['label']) ?>
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-slate-700 font-mono text-[12px] whitespace-nowrap"><?= $h($row['grid_ref'] ?? '') !== '' ? $h($row['grid_ref']) : '—' ?></td>
                                <td class="px-3 py-2 text-slate-700 whitespace-nowrap"><?= $h($row['heading_label'] ?? '') !== '' ? $h($row['heading_label']) : '—' ?></td>
                                <td class="px-3 py-2 text-slate-700"><?= $h($row['unit_group_label'] ?? '') !== '' ? $h($row['unit_group_label']) : '—' ?></td>
                                <td class="px-3 py-2 text-slate-700">
                                    <?php if ($ftId > 0 && $ftLabel !== ''): ?>
                                        <a class="font-semibold text-slate-900 underline decoration-slate-300 hover:decoration-slate-700" href="<?= $h(url('back-office/atak/fire-teams/' . $ftId)) ?>"><?= $h($ftLabel) ?></a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-2 text-slate-700">
                                    <?php if ($linkedUrl !== '' && $linkedName !== ''): ?>
                                        <a class="font-semibold text-slate-900 underline decoration-slate-300 hover:decoration-slate-700" href="<?= $h($linkedUrl) ?>"><?= $h($linkedName) ?></a>
                                    <?php elseif ($linkedName !== ''): ?>
                                        <?= $h($linkedName) ?>
                                    <?php else: ?>
                                        <span class="text-slate-400">Non lié</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-2 text-slate-600 whitespace-nowrap" title="<?= $h($row['updated_at'] ?? '') ?>"><?= $h($row['updated_at_label'] ?? '—') ?></td>
                                <td class="px-3 py-2 whitespace-nowrap">
                                    <a class="font-semibold text-slate-900 underline decoration-slate-300 hover:decoration-slate-700" href="<?= $h($row['map_url'] ?? url('atak')) ?>">Voir</a>
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap">
                                    <?php if ($ficheUrl !== ''): ?>
                                        <a class="font-semibold text-slate-900 underline decoration-slate-300 hover:decoration-slate-700" href="<?= $h($ficheUrl) ?>">Ouvrir</a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
<script>
(function () {
    var root = document.querySelector('[data-atak-operators-refresh]');
    if (!root) return;
    var seconds = parseInt(root.getAttribute('data-atak-operators-refresh') || '30', 10);
    if (!seconds || seconds < 10) seconds = 30;
    var url = root.getAttribute('data-atak-operators-url') || window.location.href;
    var remaining = seconds;
    var hint = document.getElementById('atak-op-refresh-hint');
    var timer = window.setInterval(function () {
        remaining -= 1;
        if (hint) {
            hint.textContent = remaining > 0
                ? ('prochaine dans ' + remaining + ' s')
                : 'actualisation…';
        }
        if (remaining <= 0) {
            window.clearInterval(timer);
            window.location.href = url;
        }
    }, 1000);
})();
</script>
