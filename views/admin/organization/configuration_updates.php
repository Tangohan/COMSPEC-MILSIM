<?php
declare(strict_types=1);

/** @var array $tenant */
/** @var array<string, mixed> $summary */
/** @var bool $canManage */

$summary = is_array($summary ?? null) ? $summary : [];
$counts = is_array($summary['counts'] ?? null) ? $summary['counts'] : [];
$actionable = is_array($summary['actionable'] ?? null) ? $summary['actionable'] : [];
$completed = is_array($summary['completed'] ?? null) ? $summary['completed'] : [];
$dismissed = is_array($summary['dismissed'] ?? null) ? $summary['dismissed'] : [];
$canManage = !empty($canManage);

$fmtDate = static function (?string $raw): string {
    if ($raw === null || $raw === '') {
        return '';
    }
    $t = strtotime($raw);

    return $t ? date('d/m/Y', $t) : '';
};

$renderRow = static function (array $item, bool $canManage, string $mode) use ($fmtDate): void {
    $code = htmlspecialchars((string) ($item['code'] ?? ''), ENT_QUOTES, 'UTF-8');
    $title = htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8');
    $desc = htmlspecialchars((string) ($item['description'] ?? ''), ENT_QUOTES, 'UTF-8');
    $level = htmlspecialchars((string) ($item['level_label'] ?? ''), ENT_QUOTES, 'UTF-8');
    $status = htmlspecialchars((string) ($item['status_label'] ?? ''), ENT_QUOTES, 'UTF-8');
    $url = htmlspecialchars((string) ($item['configure_url'] ?? '#'), ENT_QUOTES, 'UTF-8');
    $mins = $item['estimate_minutes'] ?? null;
    $completedAt = $fmtDate($item['completed_at'] ?? null);
    $isNew = !empty($item['is_new']);
    ?>
    <article class="border-b border-slate-200 py-4 last:border-0">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-900"><?= $title ?></h3>
                    <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-700"><?= $level ?></span>
                    <?php if ($isNew): ?>
                        <span class="rounded bg-sky-50 px-1.5 py-0.5 text-[11px] font-medium text-sky-800">Récent</span>
                    <?php endif; ?>
                </div>
                <p class="mt-1 text-sm text-slate-600"><?= $desc ?></p>
                <p class="mt-1 text-xs text-slate-500">
                    État : <?= $status ?>
                    <?php if ($mins): ?> · Temps estimé : <?= (int) $mins ?> min<?php endif; ?>
                    <?php if ($completedAt !== ''): ?> · Configuré le <?= htmlspecialchars($completedAt, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <?php if ($mode === 'actionable' && $canManage): ?>
                    <form method="post" action="<?= htmlspecialchars(url('back-office/mise-a-niveau/demarrer'), ENT_QUOTES, 'UTF-8') ?>">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="code" value="<?= $code ?>">
                        <button type="submit" class="rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">Configurer</button>
                    </form>
                    <?php if (!empty($item['dismissible'])): ?>
                    <form method="post" action="<?= htmlspecialchars(url('back-office/mise-a-niveau/ignorer'), ENT_QUOTES, 'UTF-8') ?>">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="code" value="<?= $code ?>">
                        <input type="hidden" name="remind_days" value="30">
                        <button type="submit" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">Plus tard</button>
                    </form>
                    <?php endif; ?>
                <?php elseif ($mode === 'completed' && $canManage): ?>
                    <a href="<?= $url ?>" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">Modifier</a>
                <?php elseif ($mode === 'dismissed' && $canManage): ?>
                    <form method="post" action="<?= htmlspecialchars(url('back-office/mise-a-niveau/rouvrir'), ENT_QUOTES, 'UTF-8') ?>">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="code" value="<?= $code ?>">
                        <button type="submit" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">Reprendre</button>
                    </form>
                <?php else: ?>
                    <a href="<?= $url ?>" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">Voir</a>
                <?php endif; ?>
            </div>
        </div>
    </article>
    <?php
};
?>
<div class="mx-auto max-w-3xl px-4 py-8 sm:px-6">
    <header class="mb-6">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Administration</p>
        <h1 class="mt-1 text-2xl font-semibold text-slate-900">Mise à niveau de l’organisation</h1>
        <p class="mt-2 text-sm text-slate-600">
            Complétez les options apparues après les évolutions de la plateforme. Rien n’est obligatoire sauf mention contraire — vous pouvez y revenir à tout moment.
        </p>
        <p class="mt-3 text-sm text-slate-700">
            <strong><?= (int) ($counts['actionable'] ?? 0) ?></strong> à compléter
            · <?= (int) ($counts['recommended'] ?? 0) ?> recommandée(s)
            · <?= (int) ($counts['required'] ?? 0) ?> obligatoire(s)
            · <?= (int) ($counts['completed'] ?? 0) ?> terminée(s)
        </p>
    </header>

    <section class="mb-8">
        <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-800">À compléter — <?= count($actionable) ?></h2>
        <div class="rounded-xl border border-slate-200 bg-white px-4">
            <?php if ($actionable === []): ?>
                <p class="py-6 text-sm text-slate-600">Aucune configuration en attente. Votre organisation est à jour.</p>
            <?php else: ?>
                <?php foreach ($actionable as $item): ?>
                    <?php $renderRow($item, $canManage, 'actionable'); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($completed !== []): ?>
    <section class="mb-8">
        <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-800">Terminé — <?= count($completed) ?></h2>
        <div class="rounded-xl border border-slate-200 bg-white px-4">
            <?php foreach ($completed as $item): ?>
                <?php $renderRow($item, $canManage, 'completed'); ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($dismissed !== []): ?>
    <section class="mb-8">
        <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-800">Ignoré — <?= count($dismissed) ?></h2>
        <div class="rounded-xl border border-slate-200 bg-white px-4">
            <?php foreach ($dismissed as $item): ?>
                <?php $renderRow($item, $canManage, 'dismissed'); ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <p class="text-sm text-slate-500">
        <a href="<?= htmlspecialchars(url('back-office'), ENT_QUOTES, 'UTF-8') ?>" class="underline hover:text-slate-800">Retour au tableau de bord</a>
        ·
        <a href="<?= htmlspecialchars(url('back-office/organisation/parametres'), ENT_QUOTES, 'UTF-8') ?>" class="underline hover:text-slate-800">Paramètres de la communauté</a>
    </p>
</div>
