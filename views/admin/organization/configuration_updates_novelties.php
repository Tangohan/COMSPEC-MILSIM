<?php
declare(strict_types=1);

/** @var array $tenant */
/** @var array<string, mixed> $summary */
/** @var bool $canManage */
/** @var array<string, mixed>|null $productionStatus */

$summary = is_array($summary ?? null) ? $summary : [];
$counts = is_array($summary['counts'] ?? null) ? $summary['counts'] : [];
$actionable = is_array($summary['actionable'] ?? null) ? $summary['actionable'] : [];
$canManage = !empty($canManage);
$tenantName = htmlspecialchars((string) ($tenant['name'] ?? 'votre organisation'), ENT_QUOTES, 'UTF-8');
$prod = is_array($productionStatus ?? null) ? $productionStatus : [];
$prodVersion = htmlspecialchars((string) ($prod['platform_version'] ?? ''), ENT_QUOTES, 'UTF-8');
$prodWhen = htmlspecialchars((string) ($prod['deployed_at_label'] ?? 'Non renseignée'), ENT_QUOTES, 'UTF-8');
$prodOverwatch = htmlspecialchars((string) ($prod['overwatch_version'] ?? ''), ENT_QUOTES, 'UTF-8');
$prodAthena = htmlspecialchars((string) ($prod['athena_version'] ?? ''), ENT_QUOTES, 'UTF-8');
$prodExt = htmlspecialchars((string) ($prod['extension_version'] ?? ''), ENT_QUOTES, 'UTF-8');
$prodSha = htmlspecialchars((string) ($prod['git_sha'] ?? ''), ENT_QUOTES, 'UTF-8');
$prodBulletin = htmlspecialchars((string) ($prod['bulletin_title'] ?? ''), ENT_QUOTES, 'UTF-8');
$prodBulletinUrl = htmlspecialchars((string) ($prod['bulletin_url'] ?? ''), ENT_QUOTES, 'UTF-8');
$prodCodename = htmlspecialchars((string) ($prod['codename'] ?? ''), ENT_QUOTES, 'UTF-8');
$prodCodenameLabel = htmlspecialchars((string) ($prod['codename_label'] ?? ''), ENT_QUOTES, 'UTF-8');
?>
<div class="mx-auto max-w-2xl px-4 py-10 sm:px-6">
    <?php if ($prod !== []): ?>
    <aside class="mb-8 overflow-hidden rounded-2xl border border-emerald-200/90 bg-gradient-to-br from-emerald-50 via-white to-slate-50 shadow-sm" aria-label="État de la production Athena">
        <div class="h-1 w-full bg-gradient-to-r from-emerald-600 via-teal-500 to-slate-300" aria-hidden="true"></div>
        <div class="px-5 py-5 sm:px-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-emerald-800/80">Production en ligne</p>
                    <p class="mt-1.5 text-lg font-semibold tracking-tight text-slate-900">
                        Portail Athena
                        <?php if ($prodVersion !== ''): ?>
                            <span class="ml-1.5 inline-flex items-center rounded-md bg-emerald-900 px-2 py-0.5 text-xs font-bold tabular-nums text-emerald-50">v<?= $prodVersion ?></span>
                        <?php endif; ?>
                        <?php if ($prodCodenameLabel !== ''): ?>
                            <span class="ml-1.5 inline-flex items-center rounded-md border border-emerald-300 bg-emerald-50 px-2 py-0.5 text-xs font-bold tracking-wide text-emerald-900"><?= $prodCodenameLabel ?></span>
                        <?php endif; ?>
                    </p>
                    <p class="mt-1 text-sm text-slate-600">
                        Dernière mise en production&nbsp;: <strong class="font-semibold text-slate-800"><?= $prodWhen ?></strong>
                        <?php if ($prodSha !== ''): ?>
                            <span class="text-slate-400"> · livraison <?= $prodSha ?></span>
                        <?php endif; ?>
                    </p>
                </div>
                <?php if ($prodBulletin !== '' && $prodBulletinUrl !== ''): ?>
                <a href="<?= $prodBulletinUrl ?>" class="shrink-0 rounded-lg border border-emerald-200 bg-white/80 px-3 py-1.5 text-xs font-semibold text-emerald-900 hover:bg-emerald-50">
                    Voir le bulletin
                </a>
                <?php endif; ?>
            </div>

            <dl class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-slate-200/90 bg-white/90 px-3.5 py-3">
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Overwatch</dt>
                    <dd class="mt-1 text-base font-semibold tabular-nums text-slate-900"><?= $prodOverwatch !== '' ? $prodOverwatch : '—' ?></dd>
                    <dd class="mt-0.5 text-[11px] text-slate-500">Pack jeu</dd>
                </div>
                <div class="rounded-xl border border-slate-200/90 bg-white/90 px-3.5 py-3">
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Athena (téléphone)</dt>
                    <dd class="mt-1 text-base font-semibold tabular-nums text-slate-900"><?= $prodAthena !== '' ? $prodAthena : '—' ?></dd>
                    <dd class="mt-0.5 text-[11px] text-slate-500">Module ATAK</dd>
                </div>
                <div class="rounded-xl border border-slate-200/90 bg-white/90 px-3.5 py-3">
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Extension</dt>
                    <dd class="mt-1 text-base font-semibold tabular-nums text-slate-900"><?= $prodExt !== '' ? $prodExt : '—' ?></dd>
                    <dd class="mt-0.5 text-[11px] text-slate-500">Liaison Arma</dd>
                </div>
            </dl>

            <?php if ($prodBulletin !== ''): ?>
            <p class="mt-4 text-xs leading-relaxed text-slate-600">
                Dernier bulletin mis en avant&nbsp;: <span class="font-medium text-slate-800"><?= $prodBulletin ?></span>
            </p>
            <?php endif; ?>
        </div>
    </aside>
    <?php endif; ?>

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
