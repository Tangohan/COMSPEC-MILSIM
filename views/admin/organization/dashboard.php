<?php
declare(strict_types=1);

$kpis = $adminKpis ?? [];
$blockError = $adminKpiBlockError ?? null;
$tenantName = isset($tenantName) ? (string) $tenantName : '';

$byId = [];
foreach ($kpis as $k) {
    if (!empty($k['id'])) {
        $byId[(string) $k['id']] = $k;
    }
}
$primaryIds = ['members_active', 'active_30d'];
$primaryKpis = [];
foreach ($primaryIds as $pid) {
    if (isset($byId[$pid])) {
        $primaryKpis[] = $byId[$pid];
    }
}
$secondaryKpis = [];
foreach ($kpis as $k) {
    $id = (string) ($k['id'] ?? '');
    if ($id !== '' && !in_array($id, $primaryIds, true)) {
        $secondaryKpis[] = $k;
    }
}

$gate = \App\Core\Gate::getInstance();
$canDocs = $gate->allows('documents.upload') || $gate->allows('admin.access');
$canTraining = $gate->allows('training.manage') || $gate->allows('training.assign') || $gate->allows('admin.access');
$canTenantTechModules = $gate->allows('admin.system') || $gate->allows('admin.organization') || $gate->allows('admin.access');

$orgFormatDt = static function (?string $raw): string {
    if ($raw === null || $raw === '') {
        return '—';
    }
    $t = strtotime($raw);

    return $t ? date('d/m/Y H:i', $t) : htmlspecialchars($raw, ENT_QUOTES, 'UTF-8');
};

$kpiCardClass = static function (array $k): string {
    $id = (string) ($k['id'] ?? '');
    $valStr = $k['value'] ?? null;
    $n = is_numeric($valStr) ? (int) $valStr : null;
    $base = 'rounded-xl border bg-white p-5 shadow-sm transition-shadow hover:shadow-md ';
    switch ($id) {
        case 'invites_expired':
            return $base . 'border-slate-200 border-l-[3px] border-l-amber-500';
        case 'invites_pending':
            return $base . 'border-slate-200 border-l-[3px] ' . (($n !== null && $n > 0) ? 'border-l-amber-400' : 'border-l-slate-200');
        case 'profiles_incomplete':
            return $base . 'border-slate-200 border-l-[3px] ' . (($n !== null && $n > 0) ? 'border-l-amber-400' : 'border-l-slate-200');
        case 'members_no_unit':
            return $base . 'border-slate-200 border-l-[3px] ' . (($n !== null && $n > 0) ? 'border-l-violet-400' : 'border-l-slate-200');
        case 'members_no_role':
            return $base . 'border-slate-200 border-l-[3px] ' . (($n !== null && $n > 0) ? 'border-l-orange-400' : 'border-l-slate-200');
        case 'training_expiring':
            return $base . 'border-slate-200 border-l-[3px] border-l-sky-500';
        case 'moderation_open':
            if (!empty($k['error'])) {
                return $base . 'border-slate-200 border-l-[3px] border-l-slate-300';
            }

            return $base . 'border-slate-200 border-l-[3px] ' . (($n !== null && $n > 0) ? 'border-l-rose-400' : 'border-l-emerald-500/60');
        case 'members_inactive':
            return $base . 'border-slate-200 border-l-[3px] border-l-slate-300';
        case 'members_active':
        case 'active_30d':
            return $base . 'border-slate-200 border-l-[3px] border-l-blue-600';
        default:
            return $base . 'border-slate-200';
    }
};

$rows = $adminRecentActivity ?? [];
$activityError = $adminRecentActivityError ?? null;
$moreUrl = $adminRecentActivityMoreUrl ?? url('back-office/audit');

$wq = $orgWorkQueue ?? [
    'expired_invitations' => [],
    'training_expiring' => [],
    'incomplete_profiles' => [],
    'users_without_unit' => [],
    'users_without_role' => [],
    'error_invitations' => null,
    'error_training' => null,
    'error_incomplete' => null,
    'error_no_unit' => null,
    'error_no_role' => null,
];
$mod = $orgModerationRecent ?? [];
$modErr = $orgModerationError ?? null;

$nowLabel = date('d/m/Y · H:i');
$showPlatformEnv = $gate->allows('admin.system');
$envLabel = '';
if ($showPlatformEnv) {
    $appEnv = function_exists('env') ? (string) env('APP_ENV', 'local') : 'local';
    $envLabel = match ($appEnv) {
        'production' => 'Production',
        'staging' => 'Préproduction',
        default => 'Développement',
    };
}
?>
<div class="bg-slate-50 min-h-[calc(100vh-3.5rem)]">
    <div class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-10 space-y-8 lg:space-y-10">

        <header class="relative overflow-hidden rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-50 via-white to-slate-100/90 shadow-sm">
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-blue-50/80 via-transparent to-transparent pointer-events-none" aria-hidden="true"></div>
            <div class="relative px-5 sm:px-8 py-7 lg:py-8 flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6">
                <div class="min-w-0 flex-1">
                    <p class="inline-flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.2em] text-emerald-800/90 mb-3">
                        <span class="h-px w-6 bg-emerald-400" aria-hidden="true"></span>
                        Back-office communauté
                    </p>
                    <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Centre de pilotage</h1>
                    <p class="mt-2 text-sm sm:text-base text-slate-600 max-w-2xl leading-relaxed">
                        Pilotage <strong class="font-semibold text-slate-800">de votre organisation</strong> (membres, structure, recrutement, modération locale).
                        L’administration <strong class="font-semibold text-slate-800">globale du site</strong> (tous tenants, rôles système, maintenance) est sur
                        <?php if ($gate->allows('admin.system')): ?>
                            <a href="<?= url('admin') ?>" class="font-semibold text-amber-900 underline decoration-amber-300 hover:text-amber-950">/admin</a>.
                        <?php else: ?>
                            <span class="font-mono text-xs bg-slate-100 px-1 rounded">/admin</span> (réservé aux opérateurs plateforme).
                        <?php endif; ?>
                    </p>
                    <div class="mt-5 flex flex-wrap items-center gap-3">
                        <a href="<?= url('dashboard') ?>" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50 hover:border-slate-300 transition-colors">
                            <svg class="h-4 w-4 text-slate-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
                            Retour au tableau de bord
                        </a>
                        <?php if ($gate->allows('admin.system')): ?>
                        <a href="<?= url('admin') ?>" class="inline-flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50/80 px-4 py-2 text-sm font-semibold text-amber-950 shadow-sm hover:bg-amber-50 transition-colors">Admin plateforme</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="shrink-0 w-full lg:w-72 rounded-xl border border-slate-200/80 bg-white/90 backdrop-blur-sm p-4 shadow-sm">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-3">Contexte</p>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Horodatage</dt>
                            <dd class="font-medium text-slate-900 tabular-nums"><?= htmlspecialchars($nowLabel, ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                        <?php if ($showPlatformEnv && $envLabel !== ''): ?>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Environnement</dt>
                            <dd><span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700"><?= htmlspecialchars($envLabel, ENT_QUOTES, 'UTF-8') ?></span></dd>
                        </div>
                        <?php endif; ?>
                        <?php if ($tenantName !== ''): ?>
                        <div class="pt-1 border-t border-slate-100">
                            <dt class="text-slate-500 text-xs mb-0.5">Communauté</dt>
                            <dd class="font-semibold text-slate-900 truncate" title="<?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                        <?php else: ?>
                        <div class="pt-1 border-t border-slate-100">
                            <p class="text-xs text-slate-500 leading-snug">Centre de pilotage organisationnel — session active.</p>
                        </div>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
        </header>

        <section aria-labelledby="org-kpi-heading">
            <div class="flex items-end justify-between gap-4 mb-4">
                <div>
                    <h2 id="org-kpi-heading" class="text-xs font-semibold uppercase tracking-wider text-slate-500">Indicateurs stratégiques</h2>
                    <p class="mt-1 text-sm text-slate-600">Synthèse opérationnelle et signaux de charge.</p>
                </div>
            </div>

            <?php if ($blockError): ?>
                <div class="rounded-xl border border-amber-200 bg-amber-50/90 px-4 py-3 text-sm text-amber-900 shadow-sm">
                    <?= htmlspecialchars($blockError, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php elseif (!empty($kpis)): ?>
                <div class="space-y-4">
                    <?php if (!empty($primaryKpis)): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($primaryKpis as $k): ?>
                            <div class="<?= htmlspecialchars($kpiCardClass($k), ENT_QUOTES, 'UTF-8') ?> p-6">
                                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2"><?= htmlspecialchars((string) ($k['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                <?php if (!empty($k['error'])): ?>
                                    <p class="text-lg font-semibold text-rose-600"><?= htmlspecialchars((string) $k['error'], ENT_QUOTES, 'UTF-8') ?></p>
                                <?php else: ?>
                                    <p class="text-4xl font-black text-slate-900 tabular-nums tracking-tight"><?= htmlspecialchars((string) ($k['value'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                                <?php if (!empty($k['hint'])): ?>
                                    <p class="text-xs text-slate-500 mt-2"><?= htmlspecialchars((string) $k['hint'], ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($secondaryKpis)): ?>
                    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-3 lg:gap-4">
                        <?php foreach ($secondaryKpis as $k): ?>
                            <div class="<?= htmlspecialchars($kpiCardClass($k), ENT_QUOTES, 'UTF-8') ?> p-4">
                                <p class="text-[10px] font-semibold text-slate-500 uppercase tracking-wide mb-1.5 leading-tight"><?= htmlspecialchars((string) ($k['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                <?php if (!empty($k['error'])): ?>
                                    <p class="text-sm font-semibold text-rose-600"><?= htmlspecialchars((string) $k['error'], ENT_QUOTES, 'UTF-8') ?></p>
                                <?php else: ?>
                                    <p class="text-2xl font-black text-slate-900 tabular-nums"><?= htmlspecialchars((string) ($k['value'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                                <?php if (!empty($k['hint'])): ?>
                                    <p class="text-[11px] text-slate-500 mt-1"><?= htmlspecialchars((string) $k['hint'], ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php
        $orgEnlistmentCounts = $orgEnlistmentCounts ?? [];
        $orgEnlistmentRecent = $orgEnlistmentRecent ?? [];
        $orgEnlistmentErr = $orgEnlistmentError ?? null;
        $rhRows = $orgRhRecent ?? [];
        $rhErr = $orgRhRecentError ?? null;
        $ecSubmitted = (int) ($orgEnlistmentCounts['submitted'] ?? 0);
        $ecReviewed = (int) ($orgEnlistmentCounts['reviewed'] ?? 0);
        $ecRejected = (int) ($orgEnlistmentCounts['rejected'] ?? 0);
        $enlistStatusBadge = static function (string $status): array {
            return match ($status) {
                'submitted' => ['En attente', 'bg-amber-100 text-amber-950 ring-1 ring-amber-200/80'],
                'reviewed' => ['Traitée', 'bg-emerald-100 text-emerald-950 ring-1 ring-emerald-200/80'],
                'rejected' => ['Rejetée', 'bg-rose-100 text-rose-950 ring-1 ring-rose-200/80'],
                default => [$status, 'bg-slate-100 text-slate-800 ring-1 ring-slate-200/80'],
            };
        };
        ?>
        <section aria-labelledby="org-recruitment-rh-heading" class="space-y-4">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 id="org-recruitment-rh-heading" class="text-xs font-semibold uppercase tracking-wider text-slate-500">Recrutement &amp; traçabilité RH</h2>
                    <p class="mt-1 text-sm text-slate-600">États des candidatures, dernières soumissions et mouvements de comptes, rôles, groupes et invitations.</p>
                </div>
            </div>
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 lg:gap-5">
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden flex flex-col min-h-[280px]">
                    <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3 bg-gradient-to-r from-slate-50 to-white">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">Candidatures</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Répartition par état · dernières mises à jour</p>
                        </div>
                        <a href="<?= url('back-office/recruitments') ?>" class="inline-flex items-center gap-1 text-sm font-semibold text-blue-700 hover:text-blue-900">Liste complète →</a>
                    </div>
                    <?php if ($orgEnlistmentErr): ?>
                        <div class="p-5 flex-1 flex items-center">
                            <p class="text-sm text-rose-600"><?= htmlspecialchars($orgEnlistmentErr, ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    <?php else: ?>
                        <div class="px-5 pt-4 flex flex-wrap gap-2">
                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-950 ring-1 ring-amber-200/70" title="En attente de décision">
                                <span class="tabular-nums text-base font-black"><?= $ecSubmitted ?></span> en attente
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-950 ring-1 ring-emerald-200/70">
                                <span class="tabular-nums text-base font-black"><?= $ecReviewed ?></span> traitées
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-950 ring-1 ring-rose-200/70">
                                <span class="tabular-nums text-base font-black"><?= $ecRejected ?></span> rejetées
                            </span>
                        </div>
                        <?php if (empty($orgEnlistmentRecent)): ?>
                            <div class="p-8 text-center flex-1 flex flex-col justify-center">
                                <p class="text-sm font-medium text-slate-700">Aucune candidature enregistrée</p>
                                <p class="text-xs text-slate-500 mt-1">Les dossiers soumis via le formulaire de recrutement apparaîtront ici.</p>
                            </div>
                        <?php else: ?>
                            <ul class="divide-y divide-slate-100 mt-2 flex-1">
                                <?php foreach ($orgEnlistmentRecent as $erow): ?>
                                    <?php
                                    $st = (string) ($erow['status'] ?? '');
                                    [$stLabel, $stClass] = $enlistStatusBadge($st);
                                    $eid = (int) ($erow['id'] ?? 0);
                                    $name = trim((string) ($erow['first_name'] ?? '') . ' ' . (string) ($erow['last_name'] ?? ''));
                                    if ($name === '') {
                                        $name = (string) ($erow['email'] ?? '—');
                                    }
                                    ?>
                                    <li class="px-5 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 hover:bg-slate-50/80">
                                        <div class="min-w-0">
                                            <a href="<?= url('back-office/recruitments/' . $eid) ?>" class="font-semibold text-slate-900 hover:text-blue-800 hover:underline truncate block"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></a>
                                            <span class="text-xs text-slate-500 truncate block"><?= htmlspecialchars((string) ($erow['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                        <div class="flex shrink-0 items-center gap-2">
                                            <span class="inline-flex rounded-md px-2 py-0.5 text-[11px] font-bold <?= htmlspecialchars($stClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($stLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="text-[11px] text-slate-400 tabular-nums whitespace-nowrap"><?= htmlspecialchars($orgFormatDt(isset($erow['updated_at']) ? (string) $erow['updated_at'] : (isset($erow['created_at']) ? (string) $erow['created_at'] : null)), ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="rounded-2xl border border-indigo-200/70 bg-white shadow-sm overflow-hidden flex flex-col min-h-[280px]">
                    <div class="px-5 py-4 border-b border-indigo-100 flex flex-wrap items-center justify-between gap-3 bg-gradient-to-r from-indigo-50/80 to-white">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">Fil RH &amp; affectations</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Rôles, comptes, groupes, invitations — hors actions plateforme pure.</p>
                        </div>
                        <a href="<?= htmlspecialchars($moreUrl, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-1 text-sm font-semibold text-indigo-800 hover:text-indigo-950">Journal complet →</a>
                    </div>
                    <?php if ($rhErr): ?>
                        <div class="p-5 flex-1 flex items-center">
                            <p class="text-sm text-rose-600"><?= htmlspecialchars($rhErr, ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    <?php elseif (empty($rhRows)): ?>
                        <div class="p-8 text-center flex-1 flex flex-col justify-center">
                            <p class="text-sm font-medium text-slate-700">Aucun mouvement RH récent</p>
                            <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">Les changements de rôle, d’affectation à un groupe ou les invitations apparaissent ici lorsqu’ils sont journalisés.</p>
                        </div>
                    <?php else: ?>
                        <ul class="divide-y divide-slate-100">
                            <?php foreach ($rhRows as $rrow): ?>
                                <li class="px-5 py-3 hover:bg-indigo-50/40 transition-colors">
                                    <div class="flex flex-col sm:flex-row sm:items-start gap-2 sm:gap-4">
                                        <div class="shrink-0 w-36 text-xs font-medium text-slate-500 tabular-nums">
                                            <?= htmlspecialchars($orgFormatDt(isset($rrow['created_at']) ? (string) $rrow['created_at'] : null), ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <?php $rhAction = (string) ($rrow['action'] ?? ''); ?>
                                            <span class="inline-flex items-center rounded-md bg-indigo-100 px-2 py-0.5 text-[11px] font-semibold text-indigo-950 mb-1" title="<?= htmlspecialchars($rhAction, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(audit_action_label_fr($rhAction), ENT_QUOTES, 'UTF-8') ?></span>
                                            <p class="text-sm text-slate-700">
                                                <span class="text-slate-500">Acteur ·</span>
                                                <?= htmlspecialchars((string) ($rrow['actor_email'] ?? ('#' . (string) ($rrow['user_id'] ?? ''))), ENT_QUOTES, 'UTF-8') ?>
                                            </p>
                                            <?php
                                            $ov = trim((string) ($rrow['old_value'] ?? ''));
                                            $nv = trim((string) ($rrow['new_value'] ?? ''));
                                            if ($rhAction === 'role_assigned' && ($ov !== '' || $nv !== '')): ?>
                                                <p class="text-xs text-slate-600 mt-1 font-mono"><?= htmlspecialchars($ov === '' ? $nv : ($nv === '' ? $ov : $ov . ' → ' . $nv), ENT_QUOTES, 'UTF-8') ?></p>
                                            <?php elseif (($rhAction === 'group_member_added' || $rhAction === 'group_member_removed') && ($nv !== '' || $ov !== '')): ?>
                                                <p class="text-xs text-slate-600 mt-1">Unité / groupe · #<?= htmlspecialchars($nv !== '' ? $nv : $ov, ENT_QUOTES, 'UTF-8') ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section aria-labelledby="org-personnel-alerts-heading" class="space-y-4">
            <div>
                <h2 id="org-personnel-alerts-heading" class="text-xs font-semibold uppercase tracking-wider text-slate-500">Alertes effectifs</h2>
                <p class="mt-1 text-sm text-slate-600">Membres actifs à traiter : profil incomplet, affectation d’unité, ou rôle communautaire manquant (hors comptes techniques).</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 lg:gap-5">
                <?php
                $alertCard = static function (string $title, string $badgeOk, string $badgeWarn, array $rows, ?string $err, string $moreUrl, string $borderAccent): void {
                    $n = is_array($rows) ? count($rows) : 0;
                    ?>
                <div class="rounded-2xl border <?= htmlspecialchars($borderAccent, ENT_QUOTES, 'UTF-8') ?> bg-white p-5 shadow-sm flex flex-col min-h-[200px]">
                    <div class="flex items-start justify-between gap-2 mb-3">
                        <h3 class="text-sm font-bold text-slate-900"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h3>
                        <?php if ($err): ?>
                            <span class="shrink-0 text-[10px] font-semibold uppercase tracking-wide text-rose-700 bg-rose-50 px-2 py-0.5 rounded-md">Erreur</span>
                        <?php elseif ($n > 0): ?>
                            <span class="shrink-0 text-[10px] font-bold uppercase tracking-wide text-amber-800 bg-amber-100 px-2 py-0.5 rounded-md"><?= (int) $n ?> affiché(s)</span>
                        <?php else: ?>
                            <span class="shrink-0 text-[10px] font-semibold uppercase tracking-wide text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md"><?= htmlspecialchars($badgeOk, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($err): ?>
                        <p class="text-sm text-rose-600 flex-1"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php elseif (empty($rows)): ?>
                        <p class="text-sm text-slate-600 flex-1"><?= htmlspecialchars($badgeWarn, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php else: ?>
                        <ul class="text-sm space-y-2 flex-1 min-h-0">
                            <?php foreach ($rows as $row): ?>
                                <li class="border-b border-slate-100 pb-2 last:border-0 last:pb-0">
                                    <a href="<?= url('back-office/users/' . (int) ($row['id'] ?? 0)) ?>" class="font-medium text-blue-800 hover:text-blue-950 hover:underline">
                                        <?= htmlspecialchars((string) ($row['display_name'] ?? $row['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                    <span class="block text-xs text-slate-500 truncate"><?= htmlspecialchars((string) ($row['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <a href="<?= htmlspecialchars($moreUrl, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-1 mt-4 text-sm font-semibold text-slate-800 hover:text-slate-950">Voir la liste filtrée →</a>
                </div>
                <?php
                };

                $alertCard(
                    'Profils incomplets',
                    'OK',
                    'Aucun profil actif ne correspond aux critères (identité ou rôle manquant).',
                    $wq['incomplete_profiles'] ?? [],
                    $wq['error_incomplete'] ?? null,
                    url('back-office/users') . '?filter_incomplete=1',
                    'border-amber-200/80'
                );
                $alertCard(
                    'Sans unité',
                    'OK',
                    'Tous les membres actifs ont une affectation en cours (ou table d’affectations absente).',
                    $wq['users_without_unit'] ?? [],
                    $wq['error_no_unit'] ?? null,
                    url('back-office/users') . '?filter_no_unit=1',
                    'border-violet-200/80'
                );
                $alertCard(
                    'Sans rôle communautaire',
                    'OK',
                    'Chaque membre actif a un rôle assigné.',
                    $wq['users_without_role'] ?? [],
                    $wq['error_no_role'] ?? null,
                    url('back-office/users') . '?filter_no_role=1',
                    'border-orange-200/80'
                );
                ?>
            </div>
        </section>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 items-start">
            <section class="lg:col-span-7 xl:col-span-8" aria-labelledby="org-journal-heading">
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3 bg-slate-50/50">
                        <div>
                            <h2 id="org-journal-heading" class="text-base font-bold text-slate-900">Journal opérationnel</h2>
                            <p class="text-xs text-slate-500 mt-0.5">Derniers événements d’audit enregistrés pour cette organisation.</p>
                        </div>
                        <a href="<?= htmlspecialchars($moreUrl, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-700 hover:text-blue-900">
                            Voir tout
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                        </a>
                    </div>
                    <?php if ($activityError): ?>
                        <div class="p-6">
                            <p class="text-sm text-rose-600"><?= htmlspecialchars($activityError, ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    <?php elseif (empty($rows)): ?>
                        <div class="p-10 text-center">
                            <div class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400 mb-3" aria-hidden="true">
                                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                            </div>
                            <p class="text-sm font-medium text-slate-700">Aucun événement récent</p>
                            <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">Le journal se remplira au fil des actions administratives et des connexions.</p>
                        </div>
                    <?php else: ?>
                        <ul class="divide-y divide-slate-100">
                            <?php foreach ($rows as $row): ?>
                                <li class="px-5 py-3.5 flex flex-col sm:flex-row sm:items-start gap-2 sm:gap-4 hover:bg-slate-50/80 transition-colors">
                                    <div class="shrink-0 w-36 text-xs font-medium text-slate-500 tabular-nums">
                                        <?= htmlspecialchars($orgFormatDt(isset($row['created_at']) ? (string) $row['created_at'] : null), ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <?php $actionSlug = (string) ($row['action'] ?? ''); ?>
                                        <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-800 mb-1" title="<?= htmlspecialchars($actionSlug, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(audit_action_label_fr($actionSlug), ENT_QUOTES, 'UTF-8') ?></span>
                                        <p class="text-sm text-slate-700">
                                            <span class="text-slate-500">Acteur ·</span>
                                            <?= htmlspecialchars((string) ($row['actor_email'] ?? ('#' . (string) ($row['user_id'] ?? ''))), ENT_QUOTES, 'UTF-8') ?>
                                        </p>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </section>

            <aside class="lg:col-span-5 xl:col-span-4" aria-labelledby="org-pilotage-heading">
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white">
                        <h2 id="org-pilotage-heading" class="text-base font-bold text-slate-900">Actions de pilotage</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Création, supervision et modules transverses.</p>
                    </div>
                    <div class="p-5 space-y-6">
                        <div>
                            <a href="<?= url('back-office/users/create') ?>" class="flex w-full items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-3.5 text-sm font-bold text-white shadow-md hover:bg-slate-800 transition-colors">
                                <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                Nouvel utilisateur
                            </a>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-2">Création</p>
                            <div class="grid grid-cols-1 gap-2">
                                <a href="<?= url('back-office/groups/create') ?>" class="group flex items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm font-medium text-slate-800 hover:border-blue-300 hover:bg-blue-50/50 transition-colors">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-600 group-hover:bg-white group-hover:text-blue-700"><svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 1 0-5.196-3H4.5" /></svg></span>
                                    Nouveau groupe
                                </a>
                                <a href="<?= url('back-office/teams/create') ?>" class="group flex items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm font-medium text-slate-800 hover:border-blue-300 hover:bg-blue-50/50 transition-colors">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-600 group-hover:bg-white group-hover:text-blue-700"><svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg></span>
                                    Nouvelle équipe
                                </a>
                            </div>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-2">Supervision</p>
                            <div class="grid grid-cols-1 gap-2">
                                <a href="<?= url('back-office/invitations') ?>" class="group flex items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm font-medium text-slate-800 hover:border-amber-300 hover:bg-amber-50/40 transition-colors">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-600 group-hover:bg-white group-hover:text-amber-700"><svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" /></svg></span>
                                    Invitations
                                </a>
                                <a href="<?= url('back-office/moderation') ?>" class="group flex items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm font-medium text-slate-800 hover:border-rose-300 hover:bg-rose-50/40 transition-colors">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-600 group-hover:bg-white group-hover:text-rose-700"><svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0-10.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.75c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.57-.598-3.75h-.152c-3.196 0-6.1-1.25-8.25-3.286Zm0 13.036h.008v.008H12v-.008Z" /></svg></span>
                                    Modération
                                </a>
                                <a href="<?= url('back-office/events') ?>" class="group flex items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm font-medium text-slate-800 hover:border-emerald-300 hover:bg-emerald-50/40 transition-colors">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-600 group-hover:bg-white group-hover:text-emerald-700"><svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg></span>
                                    Événements
                                </a>
                            </div>
                        </div>
                        <?php if ($canDocs || $canTraining): ?>
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-2">Modules</p>
                            <div class="grid grid-cols-1 gap-2">
                                <?php if ($canDocs): ?>
                                <a href="<?= url('documents/gestion') ?>" class="group flex items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm font-medium text-slate-800 hover:border-slate-400 hover:bg-slate-50 transition-colors">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-600"><svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg></span>
                                    Documents
                                </a>
                                <?php endif; ?>
                                <?php if ($canTraining): ?>
                                <a href="<?= url('back-office/ressources/training') ?>" class="group flex items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm font-medium text-slate-800 hover:border-sky-300 hover:bg-sky-50/50 transition-colors">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-600 group-hover:bg-white group-hover:text-sky-700"><svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm6 0a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm6 0a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z" /></svg></span>
                                    Formations (LMS)
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </aside>
        </div>

        <section aria-labelledby="org-watch-heading">
            <div class="mb-4">
                <h2 id="org-watch-heading" class="text-xs font-semibold uppercase tracking-wider text-slate-500">Surveillance et signaux</h2>
                <p class="mt-1 text-sm text-slate-600">Files à traiter et actions récentes de modération.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 lg:gap-5">
                <div class="rounded-2xl border border-amber-200/80 bg-white p-5 shadow-sm flex flex-col min-h-[220px]">
                    <div class="flex items-start justify-between gap-2 mb-3">
                        <h3 class="text-sm font-bold text-slate-900">Invitations expirées</h3>
                        <?php
                        $expCount = is_array($wq['expired_invitations'] ?? null) ? count($wq['expired_invitations']) : 0;
                        $expErr = $wq['error_invitations'] ?? null;
                        ?>
                        <?php if (!$expErr && $expCount > 0): ?>
                            <span class="shrink-0 text-[10px] font-bold uppercase tracking-wide text-amber-800 bg-amber-100 px-2 py-0.5 rounded-md"><?= (int) $expCount ?> en file</span>
                        <?php elseif (!$expErr): ?>
                            <span class="shrink-0 text-[10px] font-semibold uppercase tracking-wide text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md">OK</span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($expErr)): ?>
                        <p class="text-sm text-rose-600 flex-1"><?= htmlspecialchars((string) $expErr, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php elseif (empty($wq['expired_invitations'])): ?>
                        <div class="flex-1 flex flex-col justify-center py-4">
                            <p class="text-sm text-slate-600">Aucune invitation expirée en attente de traitement.</p>
                            <p class="text-xs text-slate-400 mt-2">Les relances et renvois se gèrent depuis Invitations.</p>
                        </div>
                    <?php else: ?>
                        <ul class="text-sm space-y-2 flex-1">
                            <?php foreach ($wq['expired_invitations'] as $inv): ?>
                                <li class="flex justify-between gap-2 text-slate-700 border-b border-amber-50 pb-2 last:border-0 last:pb-0">
                                    <span class="truncate font-medium"><?= htmlspecialchars((string) ($inv['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="text-slate-500 whitespace-nowrap text-xs tabular-nums"><?= htmlspecialchars((string) ($inv['expires_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <a href="<?= url('back-office/invitations') ?>" class="inline-flex items-center gap-1 mt-4 text-sm font-semibold text-amber-800 hover:text-amber-950">Gérer les invitations →</a>
                    <?php endif; ?>
                </div>

                <div class="rounded-2xl border border-sky-200/80 bg-white p-5 shadow-sm flex flex-col min-h-[220px]">
                    <div class="flex items-start justify-between gap-2 mb-3">
                        <h3 class="text-sm font-bold text-slate-900">Formations · échéance proche</h3>
                        <?php
                        $trCount = is_array($wq['training_expiring'] ?? null) ? count($wq['training_expiring']) : 0;
                        $trErr = $wq['error_training'] ?? null;
                        ?>
                        <?php if (!$trErr && $trCount > 0): ?>
                            <span class="shrink-0 text-[10px] font-bold uppercase tracking-wide text-sky-800 bg-sky-100 px-2 py-0.5 rounded-md"><?= (int) $trCount ?> relance(s)</span>
                        <?php elseif (!$trErr): ?>
                            <span class="shrink-0 text-[10px] font-semibold uppercase tracking-wide text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md">À jour</span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($trErr)): ?>
                        <p class="text-sm text-rose-600 flex-1"><?= htmlspecialchars((string) $trErr, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php elseif (empty($wq['training_expiring'])): ?>
                        <div class="flex-1 flex flex-col justify-center py-4">
                            <p class="text-sm text-slate-600">Rien à relancer sur les 30 prochains jours.</p>
                            <p class="text-xs text-slate-400 mt-2">Les inscriptions à surveiller apparaîtront ici.</p>
                        </div>
                    <?php else: ?>
                        <ul class="text-sm space-y-2 flex-1">
                            <?php foreach ($wq['training_expiring'] as $row): ?>
                                <li class="text-slate-700 border-b border-sky-50 pb-2 last:border-0 last:pb-0">
                                    <span class="font-medium text-slate-900"><?= htmlspecialchars((string) ($row['course_title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="block text-xs text-slate-500 mt-0.5"><?= htmlspecialchars((string) ($row['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <a href="<?= url('back-office/ressources/training') ?>" class="inline-flex items-center gap-1 mt-4 text-sm font-semibold text-sky-800 hover:text-sky-950">Formations (LMS) →</a>
                    <?php endif; ?>
                </div>

                <div class="rounded-2xl border border-rose-200/70 bg-white p-5 shadow-sm flex flex-col min-h-[220px]">
                    <div class="flex items-start justify-between gap-2 mb-3">
                        <h3 class="text-sm font-bold text-slate-900">Modération récente</h3>
                        <?php if (!$modErr && !empty($mod)): ?>
                            <span class="shrink-0 text-[10px] font-bold uppercase tracking-wide text-rose-800 bg-rose-50 px-2 py-0.5 rounded-md"><?= count($mod) ?> vue(s)</span>
                        <?php elseif (!$modErr): ?>
                            <span class="shrink-0 text-[10px] font-semibold uppercase tracking-wide text-slate-500 bg-slate-100 px-2 py-0.5 rounded-md">Calme</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($modErr): ?>
                        <p class="text-sm text-rose-600 flex-1"><?= htmlspecialchars((string) $modErr, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php elseif (empty($mod)): ?>
                        <div class="flex-1 flex flex-col justify-center py-4">
                            <p class="text-sm text-slate-600">Aucune action de modération récente.</p>
                            <p class="text-xs text-slate-400 mt-2">Les sanctions et avertissements apparaissent ici.</p>
                        </div>
                    <?php else: ?>
                        <ul class="text-sm space-y-2 flex-1">
                            <?php foreach ($mod as $a): ?>
                                <li class="text-slate-700 border-b border-rose-50 pb-2 last:border-0 last:pb-0">
                                    <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                        <span class="font-mono text-[11px] font-semibold text-slate-800"><?= htmlspecialchars((string) ($a['action_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if (!empty($a['created_at'])): ?>
                                            <span class="text-[10px] text-slate-400 tabular-nums"><?= htmlspecialchars($orgFormatDt((string) $a['created_at']), ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-1">
                                        Cible · <?= htmlspecialchars((string) ($a['target_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                        <?php if (!empty($a['actor_email'])): ?>
                                            <span class="text-slate-400"> · </span><?= htmlspecialchars((string) $a['actor_email'], ENT_QUOTES, 'UTF-8') ?>
                                        <?php endif; ?>
                                    </p>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <a href="<?= url('back-office/moderation') ?>" class="inline-flex items-center gap-1 mt-4 text-sm font-semibold text-rose-800 hover:text-rose-950">Ouvrir la modération →</a>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="pb-4" aria-labelledby="org-domains-heading">
            <div class="mb-5">
                <h2 id="org-domains-heading" class="text-xs font-semibold uppercase tracking-wider text-slate-500">Domaines d’administration</h2>
                <p class="mt-1 text-sm text-slate-600">Accès structurés par famille fonctionnelle.</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="group rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:shadow-md hover:border-slate-300 transition-all">
                    <h3 class="text-sm font-bold text-slate-900">Communauté</h3>
                    <p class="text-xs text-slate-500 mt-1 mb-4 leading-relaxed">Identité, configuration et mesures d’usage.</p>
                    <ul class="space-y-1">
                        <li><a href="<?= url('back-office/alerts') ?>" class="block rounded-lg px-2 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 hover:text-blue-800 -mx-2">Alertes &amp; annonces</a></li>
                        <li><a href="<?= url('back-office/community') ?>" class="block rounded-lg px-2 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 hover:text-blue-800 -mx-2">Code communauté</a></li>
                        <li><a href="<?= url('back-office/configuration') ?>" class="block rounded-lg px-2 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 hover:text-blue-800 -mx-2">Configuration</a></li>
                        <li><a href="<?= url('back-office/analytics') ?>" class="block rounded-lg px-2 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 hover:text-blue-800 -mx-2">Analytics</a></li>
                    </ul>
                </div>
                <div class="group rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:shadow-md hover:border-slate-300 transition-all">
                    <h3 class="text-sm font-bold text-slate-900">Utilisateurs &amp; accès</h3>
                    <p class="text-xs text-slate-500 mt-1 mb-4 leading-relaxed">Comptes, droits et parcours d’entrée.</p>
                    <ul class="space-y-1">
                        <li><a href="<?= url('back-office/users') ?>" class="block rounded-lg px-2 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 hover:text-blue-800 -mx-2">Utilisateurs</a></li>
                        <li><a href="<?= url('back-office/invitations') ?>" class="block rounded-lg px-2 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 hover:text-blue-800 -mx-2">Invitations</a></li>
                        <li><a href="<?= url('back-office/roles') ?>" class="block rounded-lg px-2 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 hover:text-blue-800 -mx-2">Rôles</a></li>
                        <li><a href="<?= url('back-office/recruitments') ?>" class="block rounded-lg px-2 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 hover:text-blue-800 -mx-2">Candidatures</a></li>
                    </ul>
                </div>
                <div class="group rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:shadow-md hover:border-slate-300 transition-all">
                    <h3 class="text-sm font-bold text-slate-900">Structure</h3>
                    <p class="text-xs text-slate-500 mt-1 mb-4 leading-relaxed">Organisation interne et référentiels.</p>
                    <ul class="space-y-1">
                        <li><a href="<?= url('back-office/groups') ?>" class="block rounded-lg px-2 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 hover:text-blue-800 -mx-2">Groupes</a></li>
                        <li><a href="<?= url('back-office/teams') ?>" class="block rounded-lg px-2 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 hover:text-blue-800 -mx-2">Équipes</a></li>
                        <li><a href="<?= url('back-office/categories') ?>" class="block rounded-lg px-2 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 hover:text-blue-800 -mx-2">Catégories</a></li>
                        <li><a href="<?= url('back-office/referentiels/grades') ?>" class="block rounded-lg px-2 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 hover:text-blue-800 -mx-2">Référentiels · Grades</a></li>
                    </ul>
                </div>
                <div class="group rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:shadow-md hover:border-slate-300 transition-all">
                    <h3 class="text-sm font-bold text-slate-900">Contrôle &amp; suivi</h3>
                    <p class="text-xs text-slate-500 mt-1 mb-4 leading-relaxed">Traçabilité, conformité et vie communautaire.</p>
                    <ul class="space-y-1">
                        <li><a href="<?= url('back-office/audit') ?>" class="block rounded-lg px-2 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 hover:text-blue-800 -mx-2">Journal d’activité</a></li>
                        <li><a href="<?= url('back-office/moderation') ?>" class="block rounded-lg px-2 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 hover:text-blue-800 -mx-2">Modération &amp; sanctions</a></li>
                        <li><a href="<?= url('back-office/events') ?>" class="block rounded-lg px-2 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 hover:text-blue-800 -mx-2">Événements</a></li>
                    </ul>
                </div>
                <div class="group rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:shadow-md hover:border-slate-300 transition-all">
                    <h3 class="text-sm font-bold text-slate-900">Modules &amp; intégrations</h3>
                    <p class="text-xs text-slate-500 mt-1 mb-4 leading-relaxed">Ressources et outils <strong class="font-semibold text-slate-700">scopés à cette communauté</strong> (URL canonique <span class="font-mono text-[10px]">/back-office/ressources/…</span>).</p>
                    <ul class="space-y-1">
                        <?php if ($canDocs): ?>
                        <li><a href="<?= url('documents/gestion') ?>" class="block rounded-lg px-2 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 hover:text-blue-800 -mx-2">Documents</a></li>
                        <?php endif; ?>
                        <?php if ($canTraining): ?>
                        <li><a href="<?= url('back-office/ressources/training') ?>" class="block rounded-lg px-2 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 hover:text-blue-800 -mx-2">Formations (LMS)</a></li>
                        <li><a href="<?= url('back-office/ressources/training/studio') ?>" class="block rounded-lg px-2 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 hover:text-blue-800 -mx-2">Studio LMS</a></li>
                        <?php endif; ?>
                        <?php if ($canTenantTechModules): ?>
                        <li><a href="<?= url('back-office/ressources/modpacks') ?>" class="block rounded-lg px-2 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 hover:text-blue-800 -mx-2">Modpacks</a></li>
                        <li><a href="<?= url('back-office/ressources/forum-config') ?>" class="block rounded-lg px-2 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 hover:text-blue-800 -mx-2">Configuration forum</a></li>
                        <li><a href="<?= url('back-office/ressources/atak-config') ?>" class="block rounded-lg px-2 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 hover:text-blue-800 -mx-2">ATAK / Tacmap</a></li>
                        <?php endif; ?>
                        <?php if (!$canDocs && !$canTraining && !$canTenantTechModules): ?>
                        <li class="text-xs text-slate-400">Aucun module transverse accessible avec vos droits actuels.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </section>

    </div>
</div>
