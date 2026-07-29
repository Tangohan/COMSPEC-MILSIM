<?php
declare(strict_types=1);

/** @var array $tenant */
/** @var array<string, mixed> $summary */
/** @var bool $canManage */

$summary = is_array($summary ?? null) ? $summary : [];
$counts = is_array($summary['counts'] ?? null) ? $summary['counts'] : [];
$actionable = is_array($summary['actionable'] ?? null) ? $summary['actionable'] : [];
$canManage = !empty($canManage);
$tenantName = htmlspecialchars((string) ($tenant['name'] ?? 'votre organisation'), ENT_QUOTES, 'UTF-8');
?>
<div class="mx-auto max-w-2xl px-4 py-10 sm:px-6">
    <header class="mb-6">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Mise à jour</p>
        <h1 class="mt-1 text-2xl font-semibold text-slate-900">Nouvelles possibilités pour votre organisation</h1>
        <p class="mt-2 text-sm text-slate-600">
            <?= $tenantName ?> a été créée avant l’ajout de plusieurs fonctionnalités. Vous pouvez compléter sa configuration maintenant, ou plus tard depuis Administration → Mise à niveau.
        </p>
        <p class="mt-3 text-sm text-slate-700">
            <?= (int) ($counts['actionable'] ?? 0) ?> configuration(s) disponible(s)
            · <?= (int) ($counts['recommended'] ?? 0) ?> recommandée(s)
            · <?= (int) ($counts['required'] ?? 0) ?> obligatoire(s)
        </p>
    </header>

    <div class="rounded-xl border border-slate-200 bg-white px-4 mb-6">
        <?php if ($actionable === []): ?>
            <p class="py-6 text-sm text-slate-600">Rien à configurer pour le moment.</p>
        <?php else: ?>
            <?php foreach ($actionable as $item): ?>
                <?php
                $code = htmlspecialchars((string) ($item['code'] ?? ''), ENT_QUOTES, 'UTF-8');
                $title = htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8');
                $desc = htmlspecialchars((string) ($item['description'] ?? ''), ENT_QUOTES, 'UTF-8');
                $level = htmlspecialchars((string) ($item['level_label'] ?? ''), ENT_QUOTES, 'UTF-8');
                $mins = $item['estimate_minutes'] ?? null;
                ?>
                <article class="border-b border-slate-200 py-4 last:border-0">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-900"><?= $title ?></h2>
                                <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-700"><?= $level ?></span>
                            </div>
                            <p class="mt-1 text-sm text-slate-600"><?= $desc ?></p>
                            <?php if ($mins): ?>
                                <p class="mt-1 text-xs text-slate-500">Temps estimé : <?= (int) $mins ?> min</p>
                            <?php endif; ?>
                        </div>
                        <?php if ($canManage): ?>
                        <form method="post" action="<?= htmlspecialchars(url('back-office/mise-a-niveau/demarrer'), ENT_QUOTES, 'UTF-8') ?>">
                            <?= \App\Core\Csrf::field() ?>
                            <input type="hidden" name="code" value="<?= $code ?>">
                            <button type="submit" class="rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">Configurer</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="flex flex-wrap gap-3">
        <a href="<?= htmlspecialchars(url('back-office/mise-a-niveau'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Configurer les nouveautés</a>
        <form method="post" action="<?= htmlspecialchars(url('back-office/nouveautes-organisation/continuer'), ENT_QUOTES, 'UTF-8') ?>">
            <?= \App\Core\Csrf::field() ?>
            <button type="submit" class="rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Accéder au tableau de bord</button>
        </form>
    </div>
</div>
