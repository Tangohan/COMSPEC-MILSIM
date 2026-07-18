<?php
declare(strict_types=1);

/** @var list<array<string, mixed>> $tenantAlerts */
$rows = $tenantAlerts ?? [];

$kindPresentation = static function (string $raw): array {
    return match ($raw) {
        'info' => [
            'label' => 'Information',
            'class' => 'bg-slate-100 text-slate-800 ring-slate-300',
        ],
        'novelty' => [
            'label' => 'Nouveauté',
            'class' => 'bg-emerald-50 text-emerald-900 ring-emerald-200',
        ],
        'discount' => [
            'label' => 'Promo / remise',
            'class' => 'bg-amber-50 text-amber-950 ring-amber-200',
        ],
        'urgent' => [
            'label' => 'Urgent',
            'class' => 'bg-rose-50 text-rose-900 ring-rose-200',
        ],
        'notice' => [
            'label' => 'Consigne',
            'class' => 'bg-teal-50 text-teal-900 ring-teal-200',
        ],
        'event' => [
            'label' => 'Événement',
            'class' => 'bg-emerald-50 text-emerald-800 ring-emerald-300',
        ],
        'maintenance' => [
            'label' => 'Maintenance',
            'class' => 'bg-slate-100 text-slate-700 ring-slate-200',
        ],
        default => [
            'label' => 'Annonce',
            'class' => 'bg-slate-50 text-slate-700 ring-slate-200',
        ],
    };
};

$formatDt = static function (?string $mysql): string {
    if ($mysql === null || trim($mysql) === '') {
        return '';
    }
    $t = strtotime($mysql);

    return $t ? date('d/m/Y H:i', $t) : '';
};

$now = time();
$activeCount = 0;
$inactiveCount = 0;
$visibleNow = 0;
foreach ($rows as $r) {
    $isActive = !empty($r['is_active']);
    if ($isActive) {
        $activeCount++;
    } else {
        $inactiveCount++;
    }
    if (!$isActive) {
        continue;
    }
    $starts = !empty($r['starts_at']) ? strtotime((string) $r['starts_at']) : false;
    $ends = !empty($r['ends_at']) ? strtotime((string) $r['ends_at']) : false;
    if ($starts !== false && $starts > $now) {
        continue;
    }
    if ($ends !== false && $ends < $now) {
        continue;
    }
    $visibleNow++;
}
$totalCount = count($rows);
?>
<style>
.ta-sheet { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.8125rem; }
.ta-sheet thead th {
    position: sticky; top: 0; z-index: 1;
    background: #020617; color: #f8fafc;
    font-size: 0.65rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase;
    text-align: left; padding: 0.7rem 0.85rem; white-space: nowrap;
    border-bottom: 2px solid #059669;
}
.ta-sheet thead th:first-child { box-shadow: inset 3px 0 0 #059669; }
.ta-sheet thead th.num { text-align: right; }
.ta-sheet tbody td {
    padding: 0.75rem 0.85rem; vertical-align: middle;
    border-bottom: 1px solid #e2e8f0; border-right: 1px solid #f1f5f9;
    background: #fff; color: #0f172a;
}
.ta-sheet tbody td:last-child { border-right: none; }
.ta-sheet tbody tr:nth-child(even) td { background: #f8fafc; }
.ta-sheet tbody tr:hover td { background: #ecfdf5; }
.ta-sheet tbody tr:last-child td { border-bottom: none; }
.ta-sheet .num { text-align: right; font-variant-numeric: tabular-nums; }
</style>
<div class="min-h-0 flex-1 bg-slate-50">
    <div class="w-full px-4 sm:px-5 lg:px-6 py-4 sm:py-5 space-y-5">

        <header class="relative overflow-hidden rounded-xl border border-emerald-200/90 bg-gradient-to-br from-emerald-50/90 via-white to-slate-50 shadow-sm">
            <div class="absolute inset-y-0 left-0 w-1 bg-emerald-600" aria-hidden="true"></div>
            <div class="relative px-4 sm:px-6 py-5 flex flex-col lg:flex-row lg:items-start lg:justify-between gap-5">
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-black uppercase tracking-[0.22em] text-emerald-800/90">Back-office · Communauté</p>
                    <h1 class="mt-1.5 text-2xl lg:text-3xl font-black tracking-tight text-slate-900">Annonces &amp; alertes</h1>
                    <p class="mt-2 text-sm text-slate-600 max-w-2xl leading-relaxed">
                        Publiez des bandeaux visibles par les membres connectés de votre unité : information, nouveauté, promo ou message urgent.
                    </p>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <a href="<?= url('back-office/alerts/create') ?>" class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Nouvelle annonce</a>
                        <a href="<?= url('back-office') ?>" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Retour back-office</a>
                    </div>
                </div>
                <div class="shrink-0 w-full lg:w-72 rounded-xl border border-emerald-200/80 bg-white p-4 shadow-sm">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-emerald-700/70 mb-3">Aperçu</p>
                    <dl class="grid grid-cols-2 gap-3">
                        <div>
                            <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Total</dt>
                            <dd class="mt-0.5 text-2xl font-black tabular-nums text-slate-900"><?= $totalCount ?></dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">Visibles</dt>
                            <dd class="mt-0.5 text-2xl font-black tabular-nums text-emerald-800"><?= $visibleNow ?></dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Actives</dt>
                            <dd class="mt-0.5 text-lg font-black tabular-nums text-slate-800"><?= $activeCount ?></dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Inactives</dt>
                            <dd class="mt-0.5 text-lg font-black tabular-nums text-slate-800"><?= $inactiveCount ?></dd>
                        </div>
                    </dl>
                </div>
            </div>
        </header>

        <?php $s = \App\Core\Session::getFlash('success'); $e = \App\Core\Session::getFlash('error'); ?>
        <?php if ($s): ?>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800" role="status"><?= htmlspecialchars($s) ?></div>
        <?php endif; ?>
        <?php if ($e): ?>
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800" role="alert"><?= htmlspecialchars($e) ?></div>
        <?php endif; ?>

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="ta-list-heading">
            <div class="flex flex-col gap-2 border-b border-slate-100 border-l-[3px] border-l-emerald-600 bg-slate-50/80 px-4 sm:px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 id="ta-list-heading" class="text-sm font-black uppercase tracking-[0.12em] text-slate-800">Annonces enregistrées</h2>
                    <p class="mt-0.5 text-xs text-slate-500"><?= $totalCount ?> annonce<?= $totalCount > 1 ? 's' : '' ?> · ordre d’affichage croissant</p>
                </div>
                <a href="<?= url('back-office/alerts/create') ?>" class="inline-flex items-center rounded-lg border border-emerald-200 bg-white px-3 py-1.5 text-[11px] font-bold uppercase tracking-wide text-emerald-800 shadow-sm hover:bg-emerald-50">Ajouter</a>
            </div>

            <?php if ($rows === []): ?>
                <div class="px-6 py-14 text-center">
                    <p class="text-sm font-semibold text-slate-700">Aucune annonce pour le moment.</p>
                    <p class="mt-1.5 text-sm text-slate-500 max-w-md mx-auto">Créez un bandeau pour informer vos membres (nouveauté, offre, consigne importante).</p>
                    <a href="<?= url('back-office/alerts/create') ?>" class="mt-5 inline-flex rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-700">Créer une annonce</a>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="ta-sheet min-w-[56rem]">
                        <thead>
                            <tr>
                                <th style="width:2.25rem">#</th>
                                <th>Titre</th>
                                <th>Type</th>
                                <th>Emplacement</th>
                                <th>État</th>
                                <th>Période</th>
                                <th class="num">Ordre</th>
                                <th class="num">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $idx => $r):
                                $id = (int) ($r['id'] ?? 0);
                                $active = !empty($r['is_active']);
                                $kind = $kindPresentation((string) ($r['kind'] ?? ''));
                                $body = trim((string) ($r['body'] ?? ''));
                                $starts = $formatDt(isset($r['starts_at']) ? (string) $r['starts_at'] : null);
                                $ends = $formatDt(isset($r['ends_at']) ? (string) $r['ends_at'] : null);
                                $period = 'Toujours';
                                if ($starts !== '' || $ends !== '') {
                                    $period = ($starts !== '' ? $starts : '…') . ' → ' . ($ends !== '' ? $ends : '…');
                                }
                                $startsTs = !empty($r['starts_at']) ? strtotime((string) $r['starts_at']) : false;
                                $endsTs = !empty($r['ends_at']) ? strtotime((string) $r['ends_at']) : false;
                                $isVisibleNow = $active
                                    && ($startsTs === false || $startsTs <= $now)
                                    && ($endsTs === false || $endsTs >= $now);
                                ?>
                                <tr>
                                    <td class="num text-slate-400"><?= (int) ($idx + 1) ?></td>
                                    <td>
                                        <span class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($r['title'] ?? '')) ?></span>
                                        <?php if ($body !== ''): ?>
                                            <span class="mt-0.5 block text-xs text-slate-500 line-clamp-1"><?= htmlspecialchars(mb_strlen($body) > 90 ? mb_substr($body, 0, 90) . '…' : $body) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="inline-flex rounded-md px-2 py-0.5 text-[10px] font-black uppercase tracking-wide ring-1 ring-inset <?= htmlspecialchars($kind['class']) ?>">
                                            <?= htmlspecialchars($kind['label']) ?>
                                        </span>
                                    </td>
                                    <td class="text-xs text-slate-600">
                                        <?= htmlspecialchars(\App\Support\AlertDisplayStyle::label(isset($r['display_style']) ? (string) $r['display_style'] : 'classic'), ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td>
                                        <?php if ($isVisibleNow): ?>
                                            <span class="inline-flex rounded-md bg-emerald-50 px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-emerald-900 ring-1 ring-inset ring-emerald-200">Visible</span>
                                        <?php elseif ($active): ?>
                                            <span class="inline-flex rounded-md bg-amber-50 px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-amber-950 ring-1 ring-inset ring-amber-200">Programmée</span>
                                        <?php else: ?>
                                            <span class="inline-flex rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-slate-600 ring-1 ring-inset ring-slate-200">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-slate-600 whitespace-nowrap text-xs"><?= htmlspecialchars($period) ?></td>
                                    <td class="num text-slate-600"><?= (int) ($r['sort_order'] ?? 0) ?></td>
                                    <td class="num">
                                        <div class="inline-flex items-center gap-1.5">
                                            <a href="<?= url('back-office/alerts/' . $id . '/edit') ?>" class="inline-flex rounded-md border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-slate-700 hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-900">Modifier</a>
                                            <form method="post" action="<?= url('back-office/alerts/' . $id . '/delete') ?>" class="inline" onsubmit="return confirm('Supprimer cette annonce ? Les membres ne la verront plus.');">
                                                <?= \App\Core\Csrf::field() ?>
                                                <button type="submit" class="inline-flex rounded-md border border-rose-200 bg-white px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-rose-800 hover:bg-rose-50">Supprimer</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
