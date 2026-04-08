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
$canInv = $gate->allows('admin.organization') || $gate->allows('admin.access') || $gate->allows('invitations.send');
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
    $envLabel = app_environment_label_fr($appEnv);
}
?>
<div class="min-h-0 flex-1">
    <div class="mx-auto max-w-[1400px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8 space-y-8 lg:space-y-10">

        <header class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white px-5 py-5 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <div class="min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-700/90">Synthèse</p>
                <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900">Centre de pilotage</h1>
                <p class="mt-1 text-sm text-slate-600">
                    Membres, structure, recrutement et modération pour
                    <?php if ($tenantName !== ''): ?>
                        <span class="font-semibold text-slate-800"><?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?></span>.
                    <?php else: ?>
                        votre communauté.
                    <?php endif; ?>
                    <?php if ($gate->allows('admin.system')): ?>
                        <span class="text-slate-500"> — outils globaux du site dans </span>
                        <a href="<?= url('admin') ?>" class="font-semibold text-amber-900 underline decoration-amber-200 hover:text-amber-950">l’administration plateforme</a>.
                    <?php endif; ?>
                </p>
            </div>
            <dl class="flex shrink-0 flex-wrap gap-4 text-sm sm:flex-col sm:gap-2 sm:text-right">
                <div class="flex items-baseline justify-between gap-6 sm:justify-end">
                    <dt class="text-slate-500">Mis à jour</dt>
                    <dd class="font-semibold tabular-nums text-slate-900"><?= htmlspecialchars($nowLabel, ENT_QUOTES, 'UTF-8') ?></dd>
                </div>
                <?php if ($showPlatformEnv && $envLabel !== ''): ?>
                <div class="flex items-baseline justify-between gap-6 sm:justify-end">
                    <dt class="text-slate-500">Environnement</dt>
                    <dd><span class="inline-flex rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700"><?= htmlspecialchars($envLabel, ENT_QUOTES, 'UTF-8') ?></span></dd>
                </div>
                <?php endif; ?>
            </dl>
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

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="org-actions-rapides-heading">
            <h2 id="org-actions-rapides-heading" class="text-sm font-bold text-slate-900">Actions rapides</h2>
            <p class="mt-0.5 text-xs text-slate-500">Le détail des rubriques se trouve dans le menu latéral.</p>
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="<?= url('back-office/users/create') ?>" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-slate-800">Nouvel utilisateur</a>
                <a href="<?= url('back-office/groups/create') ?>" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 hover:border-blue-300 hover:bg-blue-50/50">Nouveau groupe</a>
                <a href="<?= url('back-office/teams/create') ?>" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 hover:border-blue-300 hover:bg-blue-50/50">Nouvelle équipe</a>
                <?php if ($canInv): ?>
                <a href="<?= url('back-office/invitations') ?>" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 hover:border-amber-300 hover:bg-amber-50/40">Invitations</a>
                <?php endif; ?>
                <a href="<?= url('back-office/moderation') ?>" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 hover:border-rose-200 hover:bg-rose-50/40">Modération</a>
                <a href="<?= url('back-office/centre-operations') ?>" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 hover:border-emerald-300 hover:bg-emerald-50/40">Centre des opérations</a>
                <a href="<?= url('back-office/integrations') ?>" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 hover:border-violet-200 hover:bg-violet-50/40">Intégrations</a>
                <?php if (\App\Core\Gate::getInstance()->allows('admin.compliance.export')): ?>
                <a href="<?= url('back-office/conformite/export-dossier') ?>" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 hover:border-emerald-200 hover:bg-emerald-50/40">Export conformité</a>
                <?php endif; ?>
            </div>
        </section>

        <?php
        $orgTrainingFeed = $orgTrainingFeed ?? [];
        $orgTrainingFeedErr = $orgTrainingFeedError ?? null;
        $trainingFeedBadge = static function (string $cat): array {
            return match ($cat) {
                'training_enrollment_pending' => ['Inscription', 'bg-violet-100 text-violet-950 ring-violet-200/80'],
                'training_course_completed' => ['Réussite', 'bg-emerald-100 text-emerald-950 ring-emerald-200/80'],
                'training_module_blocked' => ['Accompagnement', 'bg-amber-100 text-amber-950 ring-amber-200/80'],
                default => ['Formation', 'bg-slate-100 text-slate-800 ring-slate-200/80'],
            };
        };
        ?>
        <?php if ($canTraining): ?>
        <section aria-labelledby="org-training-feed-heading" class="space-y-4">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 id="org-training-feed-heading" class="text-xs font-semibold uppercase tracking-wider text-slate-500">Formations — alertes récentes</h2>
                    <p class="mt-1 text-sm text-slate-600">Inscriptions à valider, parcours terminés et demandes d’aide sur un module (issues du portail apprenant).</p>
                </div>
                <a href="<?= htmlspecialchars(training_lms_admin_url('enrollments'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-1 text-sm font-semibold text-emerald-800 hover:text-emerald-950">Assignations →</a>
            </div>
            <div class="rounded-2xl border border-emerald-200/70 bg-white shadow-sm overflow-hidden">
                <?php if ($orgTrainingFeedErr): ?>
                    <div class="p-5 text-sm text-rose-600"><?= htmlspecialchars($orgTrainingFeedErr, ENT_QUOTES, 'UTF-8') ?></div>
                <?php elseif ($orgTrainingFeed === []): ?>
                    <div class="p-8 text-center text-sm text-slate-600">Aucune alerte récente liée aux formations.</div>
                <?php else: ?>
                    <ul class="divide-y divide-slate-100">
                        <?php foreach ($orgTrainingFeed as $frow): ?>
                            <?php
                            $cat = (string) ($frow['category'] ?? '');
                            [$catLab, $catClass] = $trainingFeedBadge($cat);
                            $fLink = trim((string) ($frow['link_url'] ?? ''));
                            ?>
                            <li class="px-5 py-4 hover:bg-emerald-50/30 transition-colors">
                                <div class="flex flex-wrap items-start gap-3">
                                    <span class="inline-flex rounded-md px-2 py-0.5 text-[11px] font-bold ring-1 <?= htmlspecialchars($catClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($catLab, ENT_QUOTES, 'UTF-8') ?></span>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($frow['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php $fb = trim((string) ($frow['body'] ?? '')); ?>
                                        <?php if ($fb !== ''): ?>
                                        <p class="text-sm text-slate-600 mt-1"><?= htmlspecialchars($fb, ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php endif; ?>
                                        <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                                            <span class="tabular-nums"><?= htmlspecialchars($orgFormatDt(isset($frow['created_at']) ? (string) $frow['created_at'] : null), ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php if ($fLink !== ''): ?>
                                            <a href="<?= htmlspecialchars($fLink, ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-800 hover:underline">Ouvrir</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

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

        <div class="grid grid-cols-1 gap-6 lg:gap-8 items-start">
            <section class="w-full" aria-labelledby="org-journal-heading">
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

    </div>
</div>
