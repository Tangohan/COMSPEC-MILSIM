<?php
declare(strict_types=1);
$trainingAllowed = !empty($rhTrainingAllowed);
$charterReady = !empty($rhCharterReady);
$charterAccepted = !empty($rhCharterAccepted);
$seniorityLines = is_array($rhSeniorityLines ?? null) ? $rhSeniorityLines : [];
$testerCommunities = is_array($rhTesterCommunities ?? null) ? $rhTesterCommunities : [];
$rolloutRows = is_array($rhRolloutRows ?? null) ? $rhRolloutRows : [];
?>
<div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8">
    <header class="mb-10">
        <p class="text-xs font-semibold uppercase tracking-widest text-emerald-600">Personnel</p>
        <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-900">Espace RH & formations</h1>
        <p class="mt-3 text-slate-600 leading-relaxed">
            Retrouvez ici les éléments liés à votre parcours de formation, à la charte d’engagement et, le cas échéant, aux programmes de préqualification auxquels vous participez.
        </p>
    </header>

    <div class="space-y-8">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">Formations</h2>
            <?php if ($trainingAllowed): ?>
                <p class="mt-2 text-sm text-slate-600">Accédez au catalogue et à vos parcours.</p>
                <div class="mt-4 flex flex-wrap gap-3">
                    <a href="<?= htmlspecialchars(url('formations'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700">Catalogue</a>
                    <a href="<?= htmlspecialchars(url('formations/mes-formations'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50">Mes parcours</a>
                </div>
            <?php else: ?>
                <p class="mt-2 text-sm text-slate-600">Les formations ne sont pas incluses dans votre formule actuelle. En cas de question, contactez l’encadrement de votre communauté.</p>
            <?php endif; ?>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">Charte de participation aux formations</h2>
            <?php if (!$trainingAllowed): ?>
                <p class="mt-2 text-sm text-slate-600">Non applicable tant que les formations ne sont pas disponibles pour votre compte.</p>
            <?php elseif (!$charterReady): ?>
                <p class="mt-2 text-sm text-slate-600">Cette démarche sera proposée dès que votre organisation aura finalisé la configuration.</p>
            <?php else: ?>
                <p class="mt-2 text-sm text-slate-600">
                    <?= $charterAccepted
                        ? 'Votre dernière prise en compte est enregistrée. Vous pouvez relire le document à tout moment.'
                        : 'Une lecture et une confirmation sont nécessaires avant de poursuivre certains parcours.' ?>
                </p>
                <div class="mt-4">
                    <a href="<?= htmlspecialchars(url('account/charte-formations'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">
                        <?= $charterAccepted ? 'Consulter à nouveau la charte' : 'Lire et confirmer la charte' ?>
                    </a>
                </div>
            <?php endif; ?>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">Ancienneté et parcours</h2>
            <?php if ($seniorityLines === []): ?>
                <p class="mt-2 text-sm text-slate-600">Aucun indicateur d’ancienneté n’est affiché pour le moment (réglages de votre organisation ou données en cours de mise à jour).</p>
            <?php else: ?>
                <ul class="mt-4 divide-y divide-slate-100">
                    <?php foreach ($seniorityLines as $line): ?>
                        <li class="flex justify-between gap-4 py-3 text-sm">
                            <span class="font-medium text-slate-800"><?= htmlspecialchars((string) ($line['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="text-slate-600 tabular-nums"><?= htmlspecialchars((string) ($line['formatted'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <p class="mt-4 text-sm">
                <a href="<?= htmlspecialchars(url('personnel/me'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-700 underline decoration-emerald-300 underline-offset-2 hover:text-emerald-800">Ouvrir ma fiche personnelle</a>
            </p>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">Programmes de préqualification</h2>
            <?php if ($testerCommunities === []): ?>
                <p class="mt-2 text-sm text-slate-600">Vous n’êtes inscrit à aucun programme de test pour l’instant. L’équipe plateforme ou votre encadrement vous informera si une participation est ouverte.</p>
            <?php else: ?>
                <ul class="mt-4 space-y-4">
                    <?php foreach ($testerCommunities as $tc): ?>
                        <li class="rounded-xl border border-slate-100 bg-slate-50/80 p-4">
                            <p class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($tc['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                            <?php $desc = trim((string) ($tc['description'] ?? '')); ?>
                            <?php if ($desc !== ''): ?>
                                <p class="mt-2 text-sm text-slate-600 leading-relaxed"><?= $desc ?></p>
                            <?php endif; ?>
                            <?php
                            $vf = $tc['valid_from'] ?? null;
                            $vu = $tc['valid_until'] ?? null;
                            if (($vf !== null && $vf !== '') || ($vu !== null && $vu !== '')):
                            ?>
                                <p class="mt-2 text-xs text-slate-500">
                                    Période d’inclusion
                                    <?php if ($vf !== null && $vf !== ''): ?> du <?= htmlspecialchars((string) $vf, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                                    <?php if ($vu !== null && $vu !== ''): ?> au <?= htmlspecialchars((string) $vu, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <?php if ($rolloutRows !== []): ?>
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-bold text-slate-900">Fonctionnalités concernées par vos programmes</h2>
                <p class="mt-2 text-sm text-slate-600">Selon les règles définies par l’organisation, certaines évolutions peuvent vous être proposées en avant-première ou limitées.</p>
                <ul class="mt-4 space-y-4">
                    <?php foreach ($rolloutRows as $rr): ?>
                        <li class="rounded-xl border border-slate-100 p-4">
                            <p class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($rr['module_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="mt-1 text-xs font-medium text-amber-800"><?= htmlspecialchars((string) ($rr['rule_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                            <?php $md = $rr['module_description'] ?? null; ?>
                            <?php if (is_string($md) && trim($md) !== ''): ?>
                                <p class="mt-2 text-sm text-slate-600"><?= htmlspecialchars(trim($md), ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                            <?php $ev = $rr['evaluation_version'] ?? null; ?>
                            <?php if (is_string($ev) && $ev !== ''): ?>
                                <p class="mt-2 text-sm text-slate-700">
                                    Version en cours d’évaluation sur l’environnement de test : <span class="font-mono font-semibold"><?= htmlspecialchars($ev, ENT_QUOTES, 'UTF-8') ?></span>
                                </p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
    </div>
</div>
