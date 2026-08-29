<?php
declare(strict_types=1);

/** @var list<array{key: string, label: string, description: string, latest: ?array<string, mixed>}> $jobs */
/** @var list<array<string, mixed>> $recentRuns */
/** @var bool $tablesReady */
/** @var bool $secretConfigured */
/** @var bool $schedulerActive */
/** @var string $cronHttpUrl */
/** @var string $cliCommand */
/** @var string $crontabLine */
/** @var string $installCommand */

$statusLabel = static function (?string $status): string {
    return match ($status) {
        'ok' => 'Réussi',
        'error' => 'Échec',
        'running' => 'En cours',
        default => 'Jamais exécuté',
    };
};

$statusClass = static function (?string $status): string {
    return match ($status) {
        'ok' => 'text-emerald-800 bg-emerald-50 border-emerald-100',
        'error' => 'text-rose-800 bg-rose-50 border-rose-100',
        'running' => 'text-amber-900 bg-amber-50 border-amber-100',
        default => 'text-slate-600 bg-slate-50 border-slate-100',
    };
};

$triggerLabel = static function (?string $src): string {
    return match ($src) {
        'cli' => 'Ligne de commande',
        'http' => 'Appel distant',
        'admin' => 'Administration',
        'watchdog' => 'Relance automatique',
        default => $src !== null && $src !== '' ? $src : '—',
    };
};
?>
<div class="max-w-5xl mx-auto px-4 sm:px-6 py-10 space-y-10">
    <header>
        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1">Administration plateforme</p>
        <h1 class="text-2xl font-black text-slate-900">Tâches automatiques</h1>
        <p class="mt-2 text-sm text-slate-600 leading-relaxed max-w-3xl">
            Travaux récurrents du site : escalade des rapports tactiques, expiration des formations,
            fermeture de contenus en quarantaine, rappels de bilan recrutement. Ils partent toutes les
            cinq minutes dès que le passage automatique est en place, ou dès qu’un opérateur ouvre le portail.
        </p>
        <a href="<?= url('admin') ?>" class="inline-block mt-4 text-sm text-slate-600 hover:underline">Retour au centre opérateur site</a>
    </header>

    <?php $f = \App\Core\Session::getFlash('error'); $s = \App\Core\Session::getFlash('success'); ?>
    <?php if ($f): ?><p class="text-red-600 text-sm"><?= htmlspecialchars((string) $f, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <?php if ($s): ?><p class="text-emerald-700 text-sm"><?= htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

    <?php if (!$tablesReady): ?>
        <section class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-950">
            Les tables de suivi ne sont pas encore créées. Lancez les migrations, puis revenez sur cette page.
        </section>
    <?php endif; ?>

    <?php if (!empty($schedulerActive)): ?>
        <section class="rounded-xl border border-emerald-200 bg-emerald-50 p-5 text-sm text-emerald-950">
            Le passage automatique tourne : les tâches ne dépendent plus d’un clic sur cette page.
        </section>
    <?php else: ?>
        <section class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-950 leading-relaxed">
            Aucun passage automatique récent. Tant que le planificateur du serveur n’est pas installé,
            les tâches ne partent que si quelqu’un ouvre le site (relance de secours) ou clique ci-dessous.
        </section>
    <?php endif; ?>

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
        <h2 class="text-sm font-bold text-slate-800">Planification</h2>
        <p class="text-sm text-slate-600 leading-relaxed">
            Sur le serveur, installez le passage toutes les cinq minutes avec
            <code class="text-xs font-mono">install-system-cron.sh</code> (recommandé). À défaut,
            une relance de secours part après une visite du portail. L’appel distant reste optionnel.
        </p>
        <div class="rounded-lg border border-slate-100 bg-slate-50 px-4 py-3">
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Installation sur le serveur</p>
            <code class="text-sm text-slate-900 break-all"><?= htmlspecialchars($installCommand, ENT_QUOTES, 'UTF-8') ?></code>
        </div>
        <div class="rounded-lg border border-slate-100 bg-slate-50 px-4 py-3">
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Ligne à coller (si l’installation n’est pas possible)</p>
            <code class="text-sm text-slate-900 break-all"><?= htmlspecialchars($crontabLine, ENT_QUOTES, 'UTF-8') ?></code>
        </div>
        <div class="rounded-lg border border-slate-100 bg-slate-50 px-4 py-3">
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Commande (essai manuel)</p>
            <code class="text-sm text-slate-900 break-all"><?= htmlspecialchars($cliCommand, ENT_QUOTES, 'UTF-8') ?></code>
        </div>
        <div class="rounded-lg border border-slate-100 bg-slate-50 px-4 py-3 space-y-2">
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Appel distant (optionnel)</p>
            <?php if ($secretConfigured): ?>
                <p class="text-sm text-emerald-800">Clé secrète configurée. Adresse de base :</p>
                <code class="text-sm text-slate-900 break-all"><?= htmlspecialchars($cronHttpUrl, ENT_QUOTES, 'UTF-8') ?>?key=…</code>
            <?php else: ?>
                <p class="text-sm text-amber-900">
                    Aucune clé secrète n’est définie. Pour activer l’appel distant, renseignez
                    <span class="font-mono text-xs">CRON_SECRET</span> dans la configuration du serveur
                    (valeur longue et aléatoire).
                </p>
            <?php endif; ?>
        </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-sm font-bold text-slate-800">Tâches disponibles</h2>
            <form method="post" action="<?= htmlspecialchars(url('admin/system/cron/run'), ENT_QUOTES, 'UTF-8') ?>">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="inline-flex items-center rounded-lg bg-slate-900 px-3 py-2 text-xs font-bold text-white hover:bg-slate-800">
                    Tout exécuter maintenant
                </button>
            </form>
        </div>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($jobs as $job): ?>
                <?php
                $latest = is_array($job['latest'] ?? null) ? $job['latest'] : null;
                $st = $latest !== null ? (string) ($latest['status'] ?? '') : null;
                ?>
                <li class="py-4 first:pt-0 last:pb-0 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-slate-900"><?= htmlspecialchars($job['label'], ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="mt-1 text-xs text-slate-500 leading-relaxed"><?= htmlspecialchars($job['description'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php if ($latest !== null): ?>
                            <p class="mt-2 text-xs text-slate-600">
                                Dernière fois :
                                <?= htmlspecialchars((string) ($latest['finished_at'] ?? $latest['started_at'] ?? '—'), ENT_QUOTES, 'UTF-8') ?>
                                · <?= htmlspecialchars($triggerLabel(isset($latest['trigger_source']) ? (string) $latest['trigger_source'] : null), ENT_QUOTES, 'UTF-8') ?>
                                <?php if (!empty($latest['summary'])): ?>
                                    — <?= htmlspecialchars((string) $latest['summary'], ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        <span class="inline-flex items-center rounded-md border px-2.5 py-1 text-[11px] font-semibold <?= $statusClass($st) ?>">
                            <?= htmlspecialchars($statusLabel($st), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <form method="post" action="<?= htmlspecialchars(url('admin/system/cron/run'), ENT_QUOTES, 'UTF-8') ?>">
                            <?= \App\Core\Csrf::field() ?>
                            <input type="hidden" name="job_key" value="<?= htmlspecialchars($job['key'], ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 hover:bg-slate-50">
                                Exécuter
                            </button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-3">
        <h2 class="text-sm font-bold text-slate-800">Historique récent</h2>
        <?php if ($recentRuns === []): ?>
            <p class="text-sm text-slate-500">Aucune exécution enregistrée pour le moment.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 text-[11px] uppercase tracking-wider text-slate-500">
                            <th class="py-2 pr-4 font-semibold">Quand</th>
                            <th class="py-2 pr-4 font-semibold">Tâche</th>
                            <th class="py-2 pr-4 font-semibold">Statut</th>
                            <th class="py-2 pr-4 font-semibold">Déclencheur</th>
                            <th class="py-2 font-semibold">Résultat</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach ($recentRuns as $run): ?>
                            <?php
                            $jobKey = (string) ($run['job_key'] ?? '');
                            $jobLabel = $jobKey;
                            foreach ($jobs as $j) {
                                if ($j['key'] === $jobKey) {
                                    $jobLabel = $j['label'];
                                    break;
                                }
                            }
                            $st = (string) ($run['status'] ?? '');
                            ?>
                            <tr>
                                <td class="py-2.5 pr-4 text-slate-600 whitespace-nowrap"><?= htmlspecialchars((string) ($run['finished_at'] ?? $run['started_at'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="py-2.5 pr-4 font-medium text-slate-900"><?= htmlspecialchars($jobLabel, ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="py-2.5 pr-4">
                                    <span class="inline-flex rounded-md border px-2 py-0.5 text-[11px] font-semibold <?= $statusClass($st) ?>">
                                        <?= htmlspecialchars($statusLabel($st), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="py-2.5 pr-4 text-slate-600"><?= htmlspecialchars($triggerLabel(isset($run['trigger_source']) ? (string) $run['trigger_source'] : null), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="py-2.5 text-slate-600 max-w-xs truncate" title="<?= htmlspecialchars((string) ($run['summary'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars((string) ($run['summary'] ?? '—'), ENT_QUOTES, 'UTF-8') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
