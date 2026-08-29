<?php
declare(strict_types=1);

/** @var list<array{key: string, label: string, description: string, interval_minutes?: int, latest: ?array<string, mixed>}> $jobs */
/** @var list<array<string, mixed>> $recentRuns */
/** @var bool $tablesReady */
/** @var bool $secretConfigured */
/** @var bool $schedulerActive */
/** @var string $cronHttpUrl */
/** @var string $cliCommand */
/** @var string $crontabLine */
/** @var string $installCommand */
/** @var array<string, mixed> $vpsStatus */

$vpsStatus = is_array($vpsStatus ?? null) ? $vpsStatus : [];
$vpsSupported = !empty($vpsStatus['supported']);
$vpsInstalled = !empty($vpsStatus['installed']);
$vpsReason = trim((string) ($vpsStatus['reason'] ?? ''));
$vpsLine = trim((string) ($vpsStatus['line'] ?? ''));
$vpsPreview = trim((string) ($vpsStatus['crontab_preview'] ?? ''));

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

$intervalLabel = static function (int $minutes): string {
    if ($minutes < 60) {
        return $minutes . ' min';
    }
    if ($minutes % 1440 === 0) {
        $d = intdiv($minutes, 1440);

        return $d === 1 ? '1 jour' : $d . ' jours';
    }
    if ($minutes % 60 === 0) {
        $h = intdiv($minutes, 60);

        return $h === 1 ? '1 h' : $h . ' h';
    }

    return $minutes . ' min';
};
?>
<div class="max-w-5xl mx-auto px-4 sm:px-6 py-10 space-y-10">
    <header>
        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1">Administration plateforme</p>
        <h1 class="text-2xl font-black text-slate-900">Tâches automatiques</h1>
        <p class="mt-2 text-sm text-slate-600 leading-relaxed max-w-3xl">
            Travaux récurrents du site : ancienneté, escalade des rapports tactiques, expiration des formations,
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
        <h2 class="text-sm font-bold text-slate-800">Installation sur le VPS</h2>
        <p class="text-sm text-slate-600 leading-relaxed">
            Installe la ligne crontab de l’utilisateur PHP (passage toutes les 5 minutes).
            Réservé aux administrateurs système. Aucune commande libre n’est acceptée : uniquement le script Athena.
        </p>

        <?php if ($vpsInstalled): ?>
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-950">
                <p class="font-bold">Crontab Athena détectée</p>
                <?php if ($vpsLine !== ''): ?>
                <code class="mt-2 block text-xs break-all"><?= htmlspecialchars($vpsLine, ENT_QUOTES, 'UTF-8') ?></code>
                <?php endif; ?>
            </div>
        <?php elseif ($vpsSupported): ?>
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                Aucune ligne Athena dans la crontab de cet utilisateur PHP. Vous pouvez l’installer ci-dessous.
            </div>
        <?php else: ?>
            <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                Installation depuis l’interface indisponible<?= $vpsReason !== '' ? ' : ' . htmlspecialchars($vpsReason, ENT_QUOTES, 'UTF-8') : '.' ?>
                Utilisez la commande SSH indiquée plus bas.
            </div>
        <?php endif; ?>

        <?php if ($vpsPreview !== '' && $vpsPreview !== '(aucune ligne Athena)'): ?>
        <div class="rounded-lg border border-slate-100 bg-slate-50 px-4 py-3">
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Aperçu crontab Athena</p>
            <pre class="text-xs text-slate-800 whitespace-pre-wrap break-all m-0"><?= htmlspecialchars($vpsPreview, ENT_QUOTES, 'UTF-8') ?></pre>
        </div>
        <?php endif; ?>

        <div class="flex flex-wrap gap-3">
            <?php if ($vpsSupported && !$vpsInstalled): ?>
            <form method="post" action="<?= htmlspecialchars(url('admin/system/cron/install-vps'), ENT_QUOTES, 'UTF-8') ?>">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="inline-flex items-center rounded-lg bg-emerald-800 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700">
                    Installer le cron sur le VPS
                </button>
            </form>
            <?php endif; ?>
            <?php if ($vpsSupported && $vpsInstalled): ?>
            <form method="post" action="<?= htmlspecialchars(url('admin/system/cron/uninstall-vps'), ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('Retirer la ligne Athena de la crontab de cet utilisateur ?');">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-800 hover:bg-slate-50">
                    Retirer le cron VPS
                </button>
            </form>
            <?php endif; ?>
        </div>

        <div class="rounded-lg border border-slate-100 bg-slate-50 px-4 py-3">
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Installation manuelle (SSH)</p>
            <p class="text-xs text-slate-500 mb-1">À la racine du site : <code class="font-mono">bash scripts/install-system-cron.sh</code></p>
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
                $st = $latest['status'] ?? null;
                $intervalMin = (int) ($job['interval_minutes'] ?? 60);
                ?>
                <li class="py-4 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-slate-900"><?= htmlspecialchars((string) $job['label'], ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="mt-1 text-xs text-slate-500 font-mono"><?= htmlspecialchars((string) $job['key'], ENT_QUOTES, 'UTF-8') ?> · toutes les <?= htmlspecialchars($intervalLabel($intervalMin), ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="mt-1 text-sm text-slate-600 leading-relaxed"><?= htmlspecialchars((string) $job['description'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php if ($latest): ?>
                            <p class="mt-2 text-xs text-slate-500">
                                Dernier passage :
                                <?= htmlspecialchars((string) ($latest['finished_at'] ?? $latest['started_at'] ?? '—'), ENT_QUOTES, 'UTF-8') ?>
                                · <?= htmlspecialchars($triggerLabel($latest['trigger_source'] ?? null), ENT_QUOTES, 'UTF-8') ?>
                                <?php if (!empty($latest['summary'])): ?>
                                    — <?= htmlspecialchars((string) $latest['summary'], ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-bold <?= $statusClass(is_string($st) ? $st : null) ?>">
                            <?= htmlspecialchars($statusLabel(is_string($st) ? $st : null), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <form method="post" action="<?= htmlspecialchars(url('admin/system/cron/run'), ENT_QUOTES, 'UTF-8') ?>">
                            <?= \App\Core\Csrf::field() ?>
                            <input type="hidden" name="job_key" value="<?= htmlspecialchars((string) $job['key'], ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-bold text-slate-800 hover:bg-slate-50">
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
                <table class="min-w-full text-left text-xs">
                    <thead class="text-[10px] uppercase tracking-wider text-slate-500 border-b border-slate-100">
                        <tr>
                            <th class="py-2 pr-3 font-bold">Tâche</th>
                            <th class="py-2 pr-3 font-bold">Statut</th>
                            <th class="py-2 pr-3 font-bold">Source</th>
                            <th class="py-2 pr-3 font-bold">Fin</th>
                            <th class="py-2 font-bold">Résumé</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach ($recentRuns as $run): ?>
                            <tr>
                                <td class="py-2 pr-3 font-mono text-slate-700"><?= htmlspecialchars((string) ($run['job_key'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="py-2 pr-3"><?= htmlspecialchars($statusLabel(isset($run['status']) ? (string) $run['status'] : null), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="py-2 pr-3"><?= htmlspecialchars($triggerLabel($run['trigger_source'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="py-2 pr-3 whitespace-nowrap"><?= htmlspecialchars((string) ($run['finished_at'] ?? $run['started_at'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="py-2 text-slate-600 max-w-md truncate" title="<?= htmlspecialchars((string) ($run['summary'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($run['summary'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
