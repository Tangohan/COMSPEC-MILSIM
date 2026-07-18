<?php
declare(strict_types=1);

$rows = is_array($deploymentRows ?? null) ? $deploymentRows : [];
$canManage = !empty($deploymentCanManage);
$q = (string) ($deploymentSearch ?? '');
$campaignFilter = (string) ($deploymentCampaignFilter ?? '');
$eventFilter = (int) ($deploymentEventFilter ?? 0);
$campaignTags = is_array($deploymentCampaignTags ?? null) ? $deploymentCampaignTags : [];
$events = is_array($deploymentEvents ?? null) ? $deploymentEvents : [];
$csrf = htmlspecialchars((string) ($deploymentCsrf ?? ''), ENT_QUOTES, 'UTF-8');

$statusLabelFr = static function (string $status): string {
    return match ($status) {
        'checkup_validated' => 'Check-up validé',
        'deployed' => 'Déployé — check-up',
        default => 'Non déployé',
    };
};

/**
 * @param array<string, mixed> $r
 * @return list<array{key:string,label:string,href:string,cta:string}>
 */
$buildGaps = static function (array $r): array {
    $uid = (int) ($r['user_id'] ?? 0);
    $editBase = url('personnel/' . $uid . '/edit');
    $gaps = [];

    if (((int) ($r['deployable'] ?? 1)) !== 1) {
        $gaps[] = [
            'key' => 'deployable',
            'label' => 'Marqué non déployable',
            'href' => $editBase . '#deployable',
            'cta' => 'Activer « Déployable »',
        ];
    }
    if (trim((string) ($r['primary_role'] ?? '')) === '') {
        $gaps[] = [
            'key' => 'role',
            'label' => 'Rôle principal manquant',
            'href' => $editBase . '#primary_role',
            'cta' => 'Renseigner le rôle',
        ];
    }
    if ((int) ($r['primary_unit_id'] ?? 0) < 1) {
        $gaps[] = [
            'key' => 'unit',
            'label' => 'Unité principale manquante',
            'href' => $editBase . '#primary_unit_id',
            'cta' => 'Affecter l’unité',
        ];
    }
    if (trim((string) (($r['matricule_internal'] ?? '') ?: ($r['matricule'] ?? ''))) === '') {
        $gaps[] = [
            'key' => 'matricule',
            'label' => 'Matricule manquant',
            'href' => $editBase . '#edit-habilitation',
            'cta' => 'Générer / saisir le matricule',
        ];
    }
    if (trim((string) (($r['profile_blood_type'] ?? '') ?: ($r['blood_type'] ?? ''))) === '') {
        $gaps[] = [
            'key' => 'blood',
            'label' => 'Groupe sanguin manquant',
            'href' => $editBase . '#blood_type',
            'cta' => 'Renseigner le groupe sanguin',
        ];
    }

    return $gaps;
};

$totalRows = 0;
$blockedRows = 0;
$readyRows = 0;
$deployedRows = 0;
$validatedRows = 0;
$enriched = [];

foreach ($rows as $r) {
    $status = (string) ($r['deployment_status'] ?? 'non_deploye');
    $gaps = $buildGaps($r);
    $bucket = 'ready';
    if ($status === 'checkup_validated') {
        $bucket = 'validated';
        $validatedRows++;
    } elseif ($status === 'deployed') {
        $bucket = 'deployed';
        $deployedRows++;
    } elseif ($gaps !== []) {
        $bucket = 'blocked';
        $blockedRows++;
    } else {
        $readyRows++;
    }
    $totalRows++;
    $r['_gaps'] = $gaps;
    $r['_bucket'] = $bucket;
    $enriched[] = $r;
}
?>
<style>
.dep-sheet { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.8125rem; }
.dep-sheet thead th {
    position: sticky; top: 0; z-index: 2;
    background: #0f172a; color: #e2e8f0;
    border-bottom: 1px solid #1e293b;
    padding: 0.7rem 0.75rem;
    text-align: left;
    font-size: 0.625rem;
    font-weight: 900;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    white-space: nowrap;
}
.dep-sheet thead th.num { text-align: right; }
.dep-sheet tbody td {
    padding: 0.65rem 0.75rem;
    border-bottom: 1px solid #e2e8f0;
    border-right: 1px solid #f1f5f9;
    vertical-align: top;
    color: #0f172a;
    background: #fff;
}
.dep-sheet tbody td:last-child { border-right: none; }
.dep-sheet tbody tr:nth-child(even) > td { background: #f8fafc; }
.dep-sheet tbody tr:hover > td { background: #eff6ff; }
.dep-sheet tbody tr.is-blocked:hover > td { background: #fff7ed; }
.dep-sheet tbody tr.dep-detail > td { background: #f8fafc !important; border-bottom: 1px solid #cbd5e1; padding: 0; }
.dep-sheet .num { text-align: right; font-variant-numeric: tabular-nums; }
.dep-sheet-wrap {
    max-height: min(72vh, 46rem);
    overflow: auto;
    border: 1px solid #cbd5e1;
    border-radius: 0 0 0.75rem 0.75rem;
    background: #fff;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
}
.dep-sheet-toolbar {
    display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem;
    padding: 0.85rem 1rem;
    border: 1px solid #cbd5e1; border-bottom: none;
    border-radius: 0.75rem 0.75rem 0 0;
    background: linear-gradient(180deg, #f8fafc, #fff);
}
.dep-pill {
    display: inline-flex; align-items: center; gap: 0.35rem;
    border-radius: 999px; padding: 0.2rem 0.55rem;
    font-size: 0.65rem; font-weight: 800; letter-spacing: 0.04em; text-transform: uppercase;
}
.dep-act {
    display: inline-flex; align-items: center; gap: 0.25rem;
    border-radius: 0.5rem; border: 1px solid #cbd5e1; background: #fff;
    padding: 0.35rem 0.55rem; font-size: 0.7rem; font-weight: 700; color: #0f172a;
    text-decoration: none; white-space: nowrap;
}
.dep-act:hover { border-color: #059669; background: #ecfdf5; color: #065f46; }
.dep-act--fix { border-color: #fcd34d; background: #fffbeb; color: #92400e; }
.dep-act--fix:hover { border-color: #f59e0b; background: #fef3c7; }
.dep-act--go { border-color: #059669; background: #059669; color: #fff; }
.dep-act--go:hover { background: #047857; color: #fff; }
.dep-act--ghost { color: #475569; }
[x-cloak] { display: none !important; }
</style>

<section
    class="mx-auto w-full max-w-[1600px] space-y-5 px-1 sm:px-0"
    x-data="{ filter: 'all', openId: null }"
>
    <header class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="min-w-0">
                <p class="text-[11px] font-black uppercase tracking-[0.22em] text-emerald-700">Opérations RH</p>
                <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900">Déploiement du personnel</h1>
                <p class="mt-1.5 max-w-3xl text-sm text-slate-600 leading-relaxed">
                    Tableur opérationnel : corrigez les dossiers incomplets via les actions de la ligne, déployez les profils prêts, puis validez le check-up.
                </p>
            </div>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-5 w-full lg:w-auto">
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Total</p>
                    <p class="text-xl font-black tabular-nums text-slate-900"><?= $totalRows ?></p>
                </div>
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5">
                    <p class="text-[10px] font-black uppercase tracking-wider text-amber-800">À corriger</p>
                    <p class="text-xl font-black tabular-nums text-amber-950"><?= $blockedRows ?></p>
                </div>
                <div class="rounded-xl border border-sky-200 bg-sky-50 px-3 py-2.5">
                    <p class="text-[10px] font-black uppercase tracking-wider text-sky-800">Prêts</p>
                    <p class="text-xl font-black tabular-nums text-sky-950"><?= $readyRows ?></p>
                </div>
                <div class="rounded-xl border border-indigo-200 bg-indigo-50 px-3 py-2.5">
                    <p class="text-[10px] font-black uppercase tracking-wider text-indigo-800">Déployés</p>
                    <p class="text-xl font-black tabular-nums text-indigo-950"><?= $deployedRows ?></p>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2.5 col-span-2 sm:col-span-1">
                    <p class="text-[10px] font-black uppercase tracking-wider text-emerald-800">Validés</p>
                    <p class="text-xl font-black tabular-nums text-emerald-950"><?= $validatedRows ?></p>
                </div>
            </div>
        </div>
    </header>

    <?php if ($canManage): ?>
    <form method="get" action="<?= htmlspecialchars(url('deploiement'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 md:grid-cols-4">
            <label class="block md:col-span-1">
                <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Recherche</span>
                <input type="text" id="q" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Nom, callsign, e-mail" />
            </label>
            <label class="block">
                <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Campagne</span>
                <input list="campagnes-list" type="text" name="campagne" value="<?= htmlspecialchars($campaignFilter, ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Ex. OP SABRE 2026" />
                <datalist id="campagnes-list">
                    <?php foreach ($campaignTags as $ct): ?>
                        <?php $tag = trim((string) ($ct['campaign_tag'] ?? '')); if ($tag === '') { continue; } ?>
                        <option value="<?= htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </label>
            <label class="block">
                <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Événement</span>
                <select name="event_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="0">Tous</option>
                    <?php foreach ($events as $ev): ?>
                        <?php $eid = (int) ($ev['id'] ?? 0); if ($eid < 1) { continue; } ?>
                        <option value="<?= $eid ?>" <?= $eventFilter === $eid ? 'selected' : '' ?>><?= htmlspecialchars((string) ($ev['title'] ?? ('#' . $eid)), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="flex items-end">
                <button type="submit" class="w-full rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-800">Appliquer les filtres</button>
            </div>
        </div>
    </form>
    <?php endif; ?>

    <div class="dep-sheet-panel">
        <div class="dep-sheet-toolbar">
            <div>
                <h2 class="text-sm font-black uppercase tracking-[0.12em] text-slate-800">Effectifs</h2>
                <p class="mt-0.5 text-xs text-slate-500">Cliquez une action manquante pour ouvrir directement le champ à corriger.</p>
            </div>
            <div class="flex flex-wrap items-center gap-1.5">
                <?php
                $filters = [
                    'all' => 'Tout (' . $totalRows . ')',
                    'blocked' => 'À corriger (' . $blockedRows . ')',
                    'ready' => 'Prêts (' . $readyRows . ')',
                    'deployed' => 'Déployés (' . $deployedRows . ')',
                    'validated' => 'Validés (' . $validatedRows . ')',
                ];
                foreach ($filters as $fkey => $flabel):
                ?>
                <button
                    type="button"
                    @click="filter = '<?= htmlspecialchars($fkey, ENT_QUOTES, 'UTF-8') ?>'"
                    :class="filter === '<?= htmlspecialchars($fkey, ENT_QUOTES, 'UTF-8') ?>' ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-600 hover:bg-slate-50 border-slate-200'"
                    class="rounded-lg border px-2.5 py-1.5 text-[11px] font-bold uppercase tracking-wide shadow-sm"
                ><?= htmlspecialchars($flabel, ENT_QUOTES, 'UTF-8') ?></button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="dep-sheet-wrap">
            <table class="dep-sheet min-w-[72rem]">
                <thead>
                    <tr>
                        <th style="width:2.25rem">#</th>
                        <th>Personnel</th>
                        <th>Unité</th>
                        <th>Rôle</th>
                        <th>Matricule</th>
                        <th>Groupe sanguin</th>
                        <th>Statut</th>
                        <th>Campagne / événement</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($enriched === []): ?>
                    <tr>
                        <td colspan="9" class="!bg-white px-4 py-14 text-center text-sm text-slate-500">Aucun personnel à afficher avec ces filtres.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($enriched as $i => $r): ?>
                        <?php
                        $uid = (int) ($r['user_id'] ?? 0);
                        $status = (string) ($r['deployment_status'] ?? 'non_deploye');
                        $isValidated = $status === 'checkup_validated';
                        $isDeployed = $status === 'deployed' || $isValidated;
                        $gaps = $r['_gaps'] ?? [];
                        $bucket = (string) ($r['_bucket'] ?? 'ready');
                        $currentCampaign = trim((string) ($r['campaign_tag'] ?? ''));
                        $currentEventId = (int) ($r['event_id'] ?? 0);
                        $matriculeShow = trim((string) (($r['matricule_internal'] ?? '') ?: ($r['matricule'] ?? '')));
                        $bloodShow = trim((string) (($r['profile_blood_type'] ?? '') ?: ($r['blood_type'] ?? '')));
                        $unitShow = trim((string) ($r['unit_name'] ?? ''));
                        $roleShow = trim((string) ($r['primary_role'] ?? ''));
                        $ficheUrl = url('personnel/' . $uid);
                        $editUrl = url('personnel/' . $uid . '/edit');
                        $canBeAssigned = $canManage && !$isDeployed && $gaps === [];
                        $anomalies = is_array($r['anomalies'] ?? null) ? $r['anomalies'] : [];
                        ?>
                        <tr
                            class="<?= $bucket === 'blocked' ? 'is-blocked' : '' ?>"
                            x-show="filter === 'all' || filter === '<?= htmlspecialchars($bucket, ENT_QUOTES, 'UTF-8') ?>'"
                            data-dep-row="<?= $uid ?>"
                        >
                            <td class="num text-slate-400"><?= (int) ($i + 1) ?></td>
                            <td>
                                <a href="<?= htmlspecialchars($ficheUrl, ENT_QUOTES, 'UTF-8') ?>" class="font-bold text-slate-900 hover:text-emerald-800 hover:underline"><?= htmlspecialchars((string) ($r['display_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></a>
                                <p class="mt-0.5 text-[11px] text-slate-500"><?= htmlspecialchars((string) ($r['callsign'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                            </td>
                            <td>
                                <?php if ($unitShow !== ''): ?>
                                    <span class="font-medium"><?= htmlspecialchars($unitShow, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php else: ?>
                                    <span class="dep-pill bg-amber-100 text-amber-900">Manquant</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($roleShow !== ''): ?>
                                    <span class="font-medium"><?= htmlspecialchars($roleShow, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php else: ?>
                                    <span class="dep-pill bg-amber-100 text-amber-900">Manquant</span>
                                <?php endif; ?>
                            </td>
                            <td class="font-mono text-xs"><?= $matriculeShow !== '' ? htmlspecialchars($matriculeShow, ENT_QUOTES, 'UTF-8') : '<span class="dep-pill bg-amber-100 text-amber-900">Manquant</span>' ?></td>
                            <td><?= $bloodShow !== '' ? htmlspecialchars($bloodShow, ENT_QUOTES, 'UTF-8') : '<span class="dep-pill bg-amber-100 text-amber-900">Manquant</span>' ?></td>
                            <td>
                                <?php if ($isValidated): ?>
                                    <span class="dep-pill bg-emerald-100 text-emerald-900">Check-up validé</span>
                                <?php elseif ($status === 'deployed'): ?>
                                    <span class="dep-pill bg-indigo-100 text-indigo-900">Déployé</span>
                                <?php elseif ($gaps !== []): ?>
                                    <span class="dep-pill bg-amber-100 text-amber-950"><?= count($gaps) ?> à corriger</span>
                                <?php else: ?>
                                    <span class="dep-pill bg-sky-100 text-sky-900">Prêt</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($currentCampaign !== '' || (string) ($r['event_title'] ?? '') !== ''): ?>
                                    <?php if ($currentCampaign !== ''): ?>
                                        <p class="font-semibold text-indigo-900"><?= htmlspecialchars($currentCampaign, ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                    <?php if ((string) ($r['event_title'] ?? '') !== ''): ?>
                                        <p class="text-[11px] text-slate-600"><?= htmlspecialchars((string) $r['event_title'], ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php if (!empty($r['event_rsvp_status'])): ?>
                                            <?php
                                            $rsvpRaw = strtolower(trim((string) $r['event_rsvp_status']));
                                            $rsvpFr = match ($rsvpRaw) {
                                                'yes', 'oui' => 'Présent',
                                                'no', 'non' => 'Absent',
                                                'maybe', 'peut-être', 'peut-etre' => 'Incertain',
                                                default => 'En attente',
                                            };
                                            ?>
                                            <p class="text-[10px] uppercase tracking-wide text-slate-400">Présence : <?= htmlspecialchars($rsvpFr, ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-slate-400">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="flex flex-col items-stretch gap-1.5 min-w-[11rem]">
                                    <?php if ($gaps !== []): ?>
                                        <?php foreach ($gaps as $gap): ?>
                                            <a class="dep-act dep-act--fix" href="<?= htmlspecialchars((string) $gap['href'], ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars((string) $gap['cta'], ENT_QUOTES, 'UTF-8') ?> →
                                            </a>
                                        <?php endforeach; ?>
                                    <?php elseif ($canBeAssigned): ?>
                                        <button type="button" class="dep-act dep-act--go" @click="openId = openId === <?= $uid ?> ? null : <?= $uid ?>">Déployer →</button>
                                    <?php elseif ($isDeployed): ?>
                                        <button type="button" class="dep-act dep-act--go" @click="openId = openId === <?= $uid ?> ? null : <?= $uid ?>">
                                            <?= $isValidated ? 'Voir / mettre à jour' : 'Ouvrir le check-up' ?> →
                                        </button>
                                    <?php endif; ?>
                                    <a class="dep-act dep-act--ghost" href="<?= htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8') ?>">Dossier complet</a>
                                    <a class="dep-act dep-act--ghost" href="<?= htmlspecialchars($ficheUrl, ENT_QUOTES, 'UTF-8') ?>">Fiche</a>
                                </div>
                            </td>
                        </tr>
                        <tr
                            class="dep-detail"
                            x-show="(filter === 'all' || filter === '<?= htmlspecialchars($bucket, ENT_QUOTES, 'UTF-8') ?>') && openId === <?= $uid ?>"
                            x-cloak
                        >
                            <td colspan="9">
                                <div class="border-t border-slate-200 bg-slate-50/90 p-4 sm:p-5 space-y-4">
                                    <?php if ($canBeAssigned): ?>
                                        <form method="post" action="<?= htmlspecialchars(url('deploiement/' . $uid . '/assigner'), ENT_QUOTES, 'UTF-8') ?>" class="grid gap-3 rounded-xl border border-emerald-200 bg-white p-4 sm:grid-cols-[1fr_1fr_auto]">
                                            <input type="hidden" name="_csrf" value="<?= $csrf ?>" />
                                            <label class="block text-xs font-bold text-slate-600">Campagne (facultatif)
                                                <input list="campagnes-list" name="campaign_tag" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Ex. OP SABRE 2026" />
                                            </label>
                                            <label class="block text-xs font-bold text-slate-600">Événement lié
                                                <select name="event_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                                    <option value="0">Aucun</option>
                                                    <?php foreach ($events as $ev): ?>
                                                        <?php $eid = (int) ($ev['id'] ?? 0); if ($eid < 1) { continue; } ?>
                                                        <option value="<?= $eid ?>"><?= htmlspecialchars((string) ($ev['title'] ?? ('#' . $eid)), ENT_QUOTES, 'UTF-8') ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>
                                            <div class="flex items-end">
                                                <button type="submit" class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-xs font-black uppercase tracking-wide text-white hover:bg-emerald-700">Confirmer le déploiement</button>
                                            </div>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($isDeployed): ?>
                                        <form method="post" action="<?= htmlspecialchars(url('deploiement/' . $uid . '/checkup'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-4 rounded-xl border border-slate-200 bg-white p-4">
                                            <input type="hidden" name="_csrf" value="<?= $csrf ?>" />
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <h3 class="text-xs font-black uppercase tracking-[0.16em] text-slate-700">Check-up — <?= htmlspecialchars((string) ($r['display_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h3>
                                                <span class="text-[11px] text-slate-500"><?= htmlspecialchars($statusLabelFr($status), ENT_QUOTES, 'UTF-8') ?></span>
                                            </div>
                                            <div class="grid gap-3 sm:grid-cols-2">
                                                <label class="text-xs font-bold text-slate-600">Campagne
                                                    <input list="campagnes-list" type="text" name="campaign_tag" value="<?= htmlspecialchars($currentCampaign, ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                                                </label>
                                                <label class="text-xs font-bold text-slate-600">Événement lié
                                                    <select name="event_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                                        <option value="0">Aucun</option>
                                                        <?php foreach ($events as $ev): ?>
                                                            <?php $eid = (int) ($ev['id'] ?? 0); if ($eid < 1) { continue; } ?>
                                                            <option value="<?= $eid ?>" <?= $currentEventId === $eid ? 'selected' : '' ?>><?= htmlspecialchars((string) ($ev['title'] ?? ('#' . $eid)), ENT_QUOTES, 'UTF-8') ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </label>
                                            </div>
                                            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                                <?php
                                                $checks = [
                                                    'mods_up_to_date' => 'Mods à jour',
                                                    'role_qualified_authorized' => 'Rôle qualifié et autorisé',
                                                    'recycling_alpha_bravo_up_to_date' => 'Recyclage ALPHA et Bravo à jour',
                                                    'vmp_up_to_date' => 'VMP à jour',
                                                    'last_interview_done' => 'Dernier entretien effectué',
                                                ];
                                                foreach ($checks as $k => $label):
                                                    $checked = (int) ($r[$k] ?? 0) === 1;
                                                ?>
                                                    <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                                        <input type="hidden" name="<?= $k ?>" value="0" />
                                                        <input type="checkbox" name="<?= $k ?>" value="1" <?= $checked ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300 text-emerald-600" />
                                                        <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                                <label class="text-xs font-bold text-slate-600">Poids (kg)
                                                    <input type="number" step="0.1" min="0" max="350" name="weight_kg" value="<?= htmlspecialchars((string) ($r['weight_kg'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                                                </label>
                                                <label class="text-xs font-bold text-slate-600">Groupe sanguin
                                                    <input type="text" name="blood_type" value="<?= htmlspecialchars($bloodShow, ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="O+, A-, …" />
                                                </label>
                                                <label class="text-xs font-bold text-slate-600">Matricule
                                                    <input type="text" name="matricule" value="<?= htmlspecialchars($matriculeShow, ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                                                </label>
                                                <label class="text-xs font-bold text-slate-600">Affectation
                                                    <input type="text" name="assignment_label" value="<?= htmlspecialchars((string) (($r['assignment_label'] ?? '') ?: ($r['unit_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                                                </label>
                                            </div>
                                            <label class="block text-xs font-bold text-slate-600">Notes check-up
                                                <textarea name="checkup_notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Restrictions, précisions…"><?= htmlspecialchars((string) ($r['checkup_notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                                            </label>
                                            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-800">Enregistrer / valider le check-up</button>
                                        </form>

                                        <form method="post" action="<?= htmlspecialchars(url('deploiement/' . $uid . '/anomalie'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-xl border border-rose-200 bg-rose-50/80 p-4">
                                            <input type="hidden" name="_csrf" value="<?= $csrf ?>" />
                                            <label class="block text-xs font-bold uppercase tracking-[0.12em] text-rose-900">Signaler une anomalie
                                                <textarea name="anomaly_message" rows="2" class="mt-2 w-full rounded-lg border border-rose-300 bg-white px-3 py-2 text-sm" placeholder="Ex. VMP expiré, matricule incorrect…"></textarea>
                                            </label>
                                            <button type="submit" class="mt-2 rounded-lg bg-rose-700 px-3 py-2 text-xs font-bold uppercase tracking-wide text-white hover:bg-rose-800">Envoyer</button>
                                        </form>

                                        <?php if ($anomalies !== []): ?>
                                            <div class="rounded-xl border border-amber-200 bg-amber-50/70 p-4">
                                                <p class="text-xs font-black uppercase tracking-[0.12em] text-amber-900">Anomalies récentes</p>
                                                <ul class="mt-2 space-y-2 text-sm text-amber-950">
                                                    <?php foreach ($anomalies as $a): ?>
                                                        <li class="rounded-lg border border-amber-200 bg-white px-3 py-2">
                                                            <p><?= htmlspecialchars((string) ($a['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                                            <p class="mt-1 text-[11px] text-amber-700">Par <?= htmlspecialchars((string) ($a['reported_by_name'] ?? 'inconnu'), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) ($a['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
