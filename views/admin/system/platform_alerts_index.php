<?php
declare(strict_types=1);

/** @var list<array<string, mixed>> $platformAlertRows */
$rows = $platformAlertRows ?? [];
$stats = $platformAlertStats ?? ['published' => 0, 'disabled' => 0, 'visible_now' => 0];
$canManagePlatform = $canManagePlatformAlerts ?? \App\Core\Gate::getInstance()->allows('admin.system');
$readOnlySupport = $isPlatformSupportReadOnly ?? false;

$kindPresentation = static function (string $raw): array {
    return match ($raw) {
        'info' => ['label' => 'Information', 'class' => 'bg-slate-100 text-slate-800 ring-slate-300'],
        'novelty' => ['label' => 'Nouveauté', 'class' => 'bg-emerald-50 text-emerald-900 ring-emerald-200'],
        'discount' => ['label' => 'Promo / remise', 'class' => 'bg-amber-50 text-amber-950 ring-amber-200'],
        'urgent' => ['label' => 'Urgent', 'class' => 'bg-rose-50 text-rose-900 ring-rose-200'],
        default => ['label' => 'Annonce', 'class' => 'bg-slate-50 text-slate-700 ring-slate-200'],
    };
};

$formatDt = static function (?string $mysql): string {
    if ($mysql === null || trim($mysql) === '') {
        return '';
    }
    $t = strtotime($mysql);

    return $t ? date('d/m/Y H:i', $t) : '';
};

$totalCount = count($rows);
$activeCount = (int) ($stats['published'] ?? 0);
$inactiveCount = (int) ($stats['disabled'] ?? 0);
$visibleNow = (int) ($stats['visible_now'] ?? 0);
?>
<style>
.pa-sheet { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.8125rem; }
.pa-sheet thead th {
    position: sticky; top: 0; z-index: 1;
    background: #020617; color: #f8fafc;
    font-size: 0.65rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase;
    text-align: left; padding: 0.7rem 0.85rem; white-space: nowrap;
    border-bottom: 2px solid #059669;
}
.pa-sheet thead th:first-child { box-shadow: inset 3px 0 0 #059669; }
.pa-sheet thead th.num { text-align: right; }
.pa-sheet tbody td {
    padding: 0.75rem 0.85rem; vertical-align: middle;
    border-bottom: 1px solid #e2e8f0; border-right: 1px solid #f1f5f9;
    background: #fff; color: #0f172a;
}
.pa-sheet tbody td:last-child { border-right: none; }
.pa-sheet tbody tr:nth-child(even) td { background: #f8fafc; }
.pa-sheet tbody tr:hover td { background: #ecfdf5; }
.pa-sheet tbody tr:last-child td { border-bottom: none; }
.pa-sheet .num { text-align: right; font-variant-numeric: tabular-nums; }
</style>
<div class="min-h-0 flex-1 bg-slate-50">
    <div class="w-full px-4 sm:px-5 lg:px-6 py-4 sm:py-5 space-y-5">

        <header class="relative overflow-hidden rounded-xl border border-emerald-200/90 bg-gradient-to-br from-emerald-50/90 via-white to-slate-50 shadow-sm">
            <div class="absolute inset-y-0 left-0 w-1 bg-emerald-600" aria-hidden="true"></div>
            <div class="relative px-4 sm:px-6 py-5 flex flex-col lg:flex-row lg:items-start lg:justify-between gap-5">
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-black uppercase tracking-[0.22em] text-emerald-800/90">Administration · Plateforme</p>
                    <h1 class="mt-1.5 text-2xl lg:text-3xl font-black tracking-tight text-slate-900">Annonces &amp; alertes</h1>
                    <p class="mt-2 text-sm text-slate-600 max-w-2xl leading-relaxed">
                        Publiez des bandeaux sur le portail pour les profils concernés. Vous pouvez empêcher le masquage et diffuser l’annonce par e-mail aux comptes actifs.
                    </p>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <?php if ($canManagePlatform): ?>
                        <a href="<?= url('admin/system/alerts/create') ?>" class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Nouvelle annonce</a>
                        <?php endif; ?>
                        <a href="<?= url('admin') ?>" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Retour tableau de bord</a>
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

        <?php if ($readOnlySupport): ?>
        <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-950">
            <p class="font-bold text-sky-900">Consultation seule</p>
            <p class="mt-1 text-sky-900/90">Votre rôle permet de consulter les annonces. La création et la modification restent réservées aux administrateurs plateforme.</p>
        </div>
        <?php endif; ?>

        <?php $s = \App\Core\Session::getFlash('success'); $e = \App\Core\Session::getFlash('error'); ?>
        <?php if ($s): ?>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800" role="status"><?= htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($e): ?>
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800" role="alert"><?= htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="pa-list-heading">
            <div class="flex flex-col gap-2 border-b border-slate-100 border-l-[3px] border-l-emerald-600 bg-slate-50/80 px-4 sm:px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 id="pa-list-heading" class="text-sm font-black uppercase tracking-[0.12em] text-slate-800">Annonces enregistrées</h2>
                    <p class="mt-0.5 text-xs text-slate-500"><?= $totalCount ?> annonce<?= $totalCount > 1 ? 's' : '' ?> · ordre d’affichage croissant</p>
                </div>
                <?php if ($canManagePlatform): ?>
                <a href="<?= url('admin/system/alerts/create') ?>" class="inline-flex items-center rounded-lg border border-emerald-200 bg-white px-3 py-1.5 text-[11px] font-bold uppercase tracking-wide text-emerald-800 shadow-sm hover:bg-emerald-50">Ajouter</a>
                <?php endif; ?>
            </div>

            <div class="border-b border-slate-100 bg-white px-4 sm:px-5 py-3">
                <p class="text-[11px] font-black uppercase tracking-[0.14em] text-slate-500 mb-2">Emplacements disponibles</p>
                <div class="flex flex-wrap gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-[10px] font-bold text-slate-700"><span class="h-2 w-2 rounded-full bg-slate-500"></span>Bandeau classique</span>
                    <span class="inline-flex items-center gap-1.5 rounded-md border border-indigo-200 bg-indigo-50 px-2 py-1 text-[10px] font-bold text-indigo-800"><span class="h-2 w-2 rounded-full bg-indigo-500"></span>Barre Info</span>
                    <span class="inline-flex items-center gap-1.5 rounded-md border border-emerald-200 bg-emerald-50 px-2 py-1 text-[10px] font-bold text-emerald-800"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>Barre Succès</span>
                    <span class="inline-flex items-center gap-1.5 rounded-md border border-amber-200 bg-amber-50 px-2 py-1 text-[10px] font-bold text-amber-900"><span class="h-2 w-2 rounded-full bg-amber-500"></span>Barre Attention</span>
                    <span class="inline-flex items-center gap-1.5 rounded-md border border-rose-200 bg-rose-50 px-2 py-1 text-[10px] font-bold text-rose-800"><span class="h-2 w-2 rounded-full bg-rose-500"></span>Barre Critique</span>
                    <span class="inline-flex items-center gap-1.5 rounded-md border border-red-300 bg-red-50 px-2 py-1 text-[10px] font-bold text-red-900"><span class="h-2 w-2 rounded-full bg-red-700"></span>Attention</span>
                    <span class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-2 py-1 text-[10px] font-bold text-slate-700"><span class="h-2 w-2 rounded-full bg-slate-700"></span>Pop-up</span>
                </div>
            </div>

            <?php if ($rows === []): ?>
                <div class="px-6 py-14 text-center">
                    <p class="text-sm font-semibold text-slate-700">Aucune annonce pour le moment.</p>
                    <p class="mt-1.5 text-sm text-slate-500 max-w-md mx-auto">Créez un bandeau pour informer les visiteurs ou les membres (nouveauté, offre, message important).</p>
                    <?php if ($canManagePlatform): ?>
                    <a href="<?= url('admin/system/alerts/create') ?>" class="mt-5 inline-flex rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-700">Créer une annonce</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="pa-sheet min-w-[64rem]">
                        <thead>
                            <tr>
                                <th style="width:2.25rem">#</th>
                                <th>Titre</th>
                                <th>Type</th>
                                <th>Emplacement</th>
                                <th>État</th>
                                <th>Masquage</th>
                                <th>Période</th>
                                <th class="num">Ordre</th>
                                <th class="num">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $idx => $r):
                                $id = (int) ($r['id'] ?? 0);
                                $active = !empty($r['is_active']);
                                $dismissible = !isset($r['dismissible']) || (int) $r['dismissible'] === 1;
                                $kind = $kindPresentation((string) ($r['kind'] ?? ''));
                                $body = trim((string) ($r['body'] ?? ''));
                                $starts = $formatDt(isset($r['starts_at']) ? (string) $r['starts_at'] : null);
                                $ends = $formatDt(isset($r['ends_at']) ? (string) $r['ends_at'] : null);
                                $period = 'Toujours';
                                if ($starts !== '' || $ends !== '') {
                                    $period = ($starts !== '' ? $starts : '…') . ' → ' . ($ends !== '' ? $ends : '…');
                                }
                                $isVisibleNow = !empty($r['_visible_now']);
                                $emailSentAt = !empty($r['email_last_sent_at']) ? $formatDt((string) $r['email_last_sent_at']) : '';
                                $emailCount = (int) ($r['email_last_sent_count'] ?? 0);
                                ?>
                                <tr>
                                    <td class="num text-slate-400"><?= (int) ($idx + 1) ?></td>
                                    <td>
                                        <span class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($r['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if ($body !== ''): ?>
                                            <span class="mt-0.5 block text-xs text-slate-500 line-clamp-1"><?= htmlspecialchars(mb_strlen($body) > 90 ? mb_substr($body, 0, 90) . '…' : $body, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                        <?php if ($emailSentAt !== ''): ?>
                                            <span class="mt-1 block text-[10px] text-slate-400">Dernier envoi e-mail : <?= htmlspecialchars($emailSentAt, ENT_QUOTES, 'UTF-8') ?><?= $emailCount > 0 ? ' · ' . $emailCount . ' adresse(s)' : '' ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="inline-flex rounded-md px-2 py-0.5 text-[10px] font-black uppercase tracking-wide ring-1 ring-inset <?= htmlspecialchars($kind['class'], ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($kind['label'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap gap-1">
                                        <?php
                                        $dsList = \App\Support\AlertDisplayStyle::parsePlatformList(
                                            isset($r['display_style']) ? (string) $r['display_style'] : null
                                        );
                                        if ($dsList === []) {
                                            $dsList = ['classic'];
                                        }
                                        foreach ($dsList as $ds):
                                            $dsShort = match ($ds) {
                                                'mini_info' => ['Barre Info', 'bg-indigo-50 text-indigo-900 ring-indigo-200'],
                                                'mini_success' => ['Barre Succès', 'bg-emerald-50 text-emerald-900 ring-emerald-200'],
                                                'mini_warning' => ['Barre Attention', 'bg-amber-50 text-amber-950 ring-amber-200'],
                                                'mini_danger' => ['Barre Critique', 'bg-rose-50 text-rose-900 ring-rose-200'],
                                                'breaking' => ['Attention', 'bg-red-50 text-red-900 ring-red-200'],
                                                'popup' => ['Pop-up', 'bg-slate-100 text-slate-800 ring-slate-300'],
                                                default => ['Classique', 'bg-slate-100 text-slate-800 ring-slate-200'],
                                            };
                                        ?>
                                            <span class="inline-flex rounded-md px-2 py-0.5 text-[10px] font-black uppercase tracking-wide ring-1 ring-inset <?= htmlspecialchars($dsShort[1], ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($dsShort[0], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        <?php endforeach; ?>
                                        </div>
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
                                    <td>
                                        <?php if ($dismissible): ?>
                                            <span class="inline-flex rounded-md bg-slate-50 px-2 py-0.5 text-[10px] font-bold text-slate-600 ring-1 ring-inset ring-slate-200">Autorisé</span>
                                        <?php else: ?>
                                            <span class="inline-flex rounded-md bg-rose-50 px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-rose-900 ring-1 ring-inset ring-rose-200">Interdit</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-slate-600 whitespace-nowrap text-xs"><?= htmlspecialchars($period, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="num text-slate-600"><?= (int) ($r['sort_order'] ?? 0) ?></td>
                                    <td class="num">
                                        <?php if ($canManagePlatform): ?>
                                        <div class="inline-flex flex-wrap items-center justify-end gap-1.5">
                                            <a href="<?= url('admin/system/alerts/' . $id . '/edit') ?>" class="inline-flex rounded-md border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-slate-700 hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-900">Modifier</a>
                                            <form method="post" action="<?= url('admin/system/alerts/' . $id . '/send-email') ?>" class="inline" onsubmit="return confirm('Envoyer cette annonce par e-mail à tous les comptes actifs du portail ?');">
                                                <?= \App\Core\Csrf::field() ?>
                                                <button type="submit" class="inline-flex rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-emerald-900 hover:bg-emerald-100">E-mail</button>
                                            </form>
                                            <form method="post" action="<?= url('admin/system/alerts/' . $id . '/delete') ?>" class="inline" onsubmit="return confirm('Supprimer cette annonce ? Elle disparaîtra du portail.');">
                                                <?= \App\Core\Csrf::field() ?>
                                                <button type="submit" class="inline-flex rounded-md border border-rose-200 bg-white px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-rose-800 hover:bg-rose-50">Supprimer</button>
                                            </form>
                                        </div>
                                        <?php else: ?>
                                        <span class="text-xs text-slate-400">—</span>
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
</div>
