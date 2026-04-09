<?php

declare(strict_types=1);

$profile = (string) ($operationsProfile ?? 'commandement');
$profiles = $operationsProfiles ?? ['commandement', 'rh', 'moderation', 'formation'];
$moderationOpen = (int) ($operationsModerationOpen ?? 0);
$pendingRecruitments = $operationsPendingRecruitments ?? [];
$pendingRecruitmentsError = $operationsPendingRecruitmentsError ?? null;
$eventsJ1 = $operationsEventsJ1 ?? [];
$eventsJ7 = $operationsEventsJ7 ?? [];
$eventsError = $operationsEventsError ?? null;
$activeAlerts = $operationsActiveAlerts ?? [];
$alertsError = $operationsAlertsError ?? null;
$anomalies = $operationsOnboardingAnomalies ?? [];
$workQueue = $operationsWorkQueue ?? [];

$profileLabels = [
    'commandement' => 'Commandement',
    'rh' => 'RH',
    'moderation' => 'Modération',
    'formation' => 'Formation',
];

$formatDate = static function (?string $raw): string {
    if ($raw === null || trim($raw) === '') {
        return '—';
    }
    $ts = strtotime($raw);

    return $ts ? date('d/m/Y H:i', $ts) : (string) $raw;
};
?>

<div class="mx-auto max-w-[1400px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8 space-y-6">
    <header class="rounded-2xl border border-slate-200 bg-white px-6 py-5 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-[11px] uppercase tracking-[0.2em] font-bold text-emerald-700">Control Tower</p>
                <h1 class="text-2xl font-black text-slate-900 mt-1">Centre des opérations</h1>
                <p class="text-sm text-slate-600 mt-2">Vue unifiée des incidents, recrutements, événements, alertes et anomalies d’onboarding.</p>
            </div>
            <a href="<?= url('back-office') ?>" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Retour tableau de bord</a>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            <?php foreach ($profiles as $p): ?>
                <?php $active = $profile === $p; ?>
                <a href="<?= htmlspecialchars(url('back-office/centre-operations') . '?profile=' . urlencode((string) $p), ENT_QUOTES, 'UTF-8') ?>"
                   class="rounded-lg px-3 py-2 text-xs font-bold uppercase tracking-wide <?= $active ? 'bg-emerald-700 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' ?>">
                    <?= htmlspecialchars($profileLabels[(string) $p] ?? (string) $p, ENT_QUOTES, 'UTF-8') ?>
                </a>
            <?php endforeach; ?>
        </div>
    </header>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <article class="rounded-xl border border-rose-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-rose-700">Signalements forum</p>
            <p class="mt-2 text-3xl font-black text-slate-900"><?= $moderationOpen ?></p>
            <p class="mt-1 text-[11px] text-slate-500">Dossiers signalés en attente dans cette communauté.</p>
            <a class="mt-3 inline-flex text-sm font-semibold text-rose-700 hover:underline" href="<?= url('back-office/forum-moderation') ?>">Ouvrir la console forum →</a>
            <?php if (\App\Core\Gate::getInstance()->allows('admin.members.moderate')): ?>
                <a class="mt-2 block text-xs font-semibold text-slate-600 hover:underline" href="<?= url('back-office/moderation') ?>">Restrictions membres (organisation)</a>
            <?php endif; ?>
        </article>

        <article class="rounded-xl border border-blue-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Candidatures en attente</p>
            <p class="mt-2 text-3xl font-black text-slate-900"><?= count($pendingRecruitments) ?></p>
            <a class="mt-3 inline-flex text-sm font-semibold text-blue-700 hover:underline" href="<?= url('back-office/recruitments') ?>">Instruire →</a>
        </article>

        <article class="rounded-xl border border-amber-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Événements J+1</p>
            <p class="mt-2 text-3xl font-black text-slate-900"><?= count($eventsJ1) ?></p>
            <a class="mt-3 inline-flex text-sm font-semibold text-amber-700 hover:underline" href="<?= url('back-office/events') ?>">Préparer →</a>
        </article>

        <article class="rounded-xl border border-violet-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-violet-700">Événements J+7</p>
            <p class="mt-2 text-3xl font-black text-slate-900"><?= count($eventsJ7) ?></p>
            <a class="mt-3 inline-flex text-sm font-semibold text-violet-700 hover:underline" href="<?= url('back-office/events') ?>">Planifier →</a>
        </article>

        <article class="rounded-xl border border-emerald-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Alertes locales actives</p>
            <p class="mt-2 text-3xl font-black text-slate-900"><?= count($activeAlerts) ?></p>
            <a class="mt-3 inline-flex text-sm font-semibold text-emerald-700 hover:underline" href="<?= url('back-office/alerts') ?>">Escalader →</a>
        </article>
    </section>

    <section class="grid gap-6 xl:grid-cols-3">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-bold text-slate-900">Candidatures à traiter</h2>
            <?php if ($pendingRecruitmentsError): ?>
                <p class="mt-3 text-sm text-rose-600"><?= htmlspecialchars((string) $pendingRecruitmentsError, ENT_QUOTES, 'UTF-8') ?></p>
            <?php elseif ($pendingRecruitments === []): ?>
                <p class="mt-3 text-sm text-slate-500">Aucune candidature en attente.</p>
            <?php else: ?>
                <ul class="mt-3 space-y-2 text-sm">
                    <?php foreach ($pendingRecruitments as $row): ?>
                        <li class="rounded-lg border border-slate-100 px-3 py-2">
                            <a class="font-semibold text-blue-700 hover:underline" href="<?= url('back-office/recruitments/' . (int) ($row['id'] ?? 0)) ?>">
                                <?= htmlspecialchars((string) ($row['display_name'] ?? $row['email'] ?? 'Dossier'), ENT_QUOTES, 'UTF-8') ?>
                            </a>
                            <p class="text-xs text-slate-500">Soumis le <?= htmlspecialchars($formatDate((string) ($row['created_at'] ?? '')), ENT_QUOTES, 'UTF-8') ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-bold text-slate-900">Événements imminents</h2>
            <?php if ($eventsError): ?>
                <p class="mt-3 text-sm text-rose-600"><?= htmlspecialchars((string) $eventsError, ENT_QUOTES, 'UTF-8') ?></p>
            <?php elseif ($eventsJ7 === []): ?>
                <p class="mt-3 text-sm text-slate-500">Aucun événement sur les 7 prochains jours.</p>
            <?php else: ?>
                <ul class="mt-3 space-y-2 text-sm">
                    <?php foreach (array_slice($eventsJ7, 0, 6) as $event): ?>
                        <li class="rounded-lg border border-slate-100 px-3 py-2">
                            <a class="font-semibold text-violet-700 hover:underline" href="<?= url('back-office/events/' . (int) ($event['id'] ?? 0)) ?>"><?= htmlspecialchars((string) ($event['title'] ?? 'Événement'), ENT_QUOTES, 'UTF-8') ?></a>
                            <p class="text-xs text-slate-500"><?= htmlspecialchars($formatDate((string) ($event['starts_at'] ?? '')), ENT_QUOTES, 'UTF-8') ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-bold text-slate-900">Anomalies onboarding / configuration</h2>
            <ul class="mt-3 space-y-2 text-sm text-slate-700">
                <li class="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2"><span>Profils incomplets</span><strong><?= (int) ($anomalies['profils_incomplets'] ?? 0) ?></strong></li>
                <li class="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2"><span>Membres sans unité</span><strong><?= (int) ($anomalies['membres_sans_unite'] ?? 0) ?></strong></li>
                <li class="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2"><span>Membres sans rôle</span><strong><?= (int) ($anomalies['membres_sans_role'] ?? 0) ?></strong></li>
                <li class="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2"><span>Invitations expirées</span><strong><?= (int) ($anomalies['invitations_expirees'] ?? 0) ?></strong></li>
            </ul>
            <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold">
                <a href="<?= url('back-office/users') . '?filter_incomplete=1' ?>" class="rounded-md bg-slate-100 px-2.5 py-1.5 text-slate-700 hover:bg-slate-200">Assigner</a>
                <a href="<?= url('back-office/users') . '?filter_no_role=1' ?>" class="rounded-md bg-slate-100 px-2.5 py-1.5 text-slate-700 hover:bg-slate-200">Traiter</a>
                <a href="<?= url('back-office/invitations') ?>" class="rounded-md bg-slate-100 px-2.5 py-1.5 text-slate-700 hover:bg-slate-200">Relancer</a>
            </div>
        </article>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-bold text-slate-900">Alertes locales</h2>
        <?php if ($alertsError): ?>
            <p class="mt-3 text-sm text-rose-600"><?= htmlspecialchars((string) $alertsError, ENT_QUOTES, 'UTF-8') ?></p>
        <?php elseif ($activeAlerts === []): ?>
            <p class="mt-3 text-sm text-slate-500">Aucune alerte locale active.</p>
        <?php else: ?>
            <ul class="mt-3 space-y-2">
                <?php foreach (array_slice($activeAlerts, 0, 6) as $alert): ?>
                    <li class="rounded-lg border border-slate-100 px-3 py-2">
                        <p class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($alert['title'] ?? 'Alerte'), ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="text-xs text-slate-500">Actif depuis <?= htmlspecialchars($formatDate((string) ($alert['start_at'] ?? $alert['created_at'] ?? '')), ENT_QUOTES, 'UTF-8') ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
