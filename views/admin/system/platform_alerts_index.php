<?php
declare(strict_types=1);

/** @var list<array<string, mixed>> $platformAlertRows */
$rows = $platformAlertRows ?? [];
$stats = $platformAlertStats ?? ['published' => 0, 'disabled' => 0, 'visible_now' => 0];
$canManagePlatform = $canManagePlatformAlerts ?? \App\Core\Gate::getInstance()->allows('admin.system');
$readOnlySupport = $isPlatformSupportReadOnly ?? false;

$s = \App\Core\Session::getFlash('success');
$e = \App\Core\Session::getFlash('error');
?>
<div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:py-12">
    <div class="mb-8">
        <a href="<?= url('admin') ?>" class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-700 hover:text-emerald-900">
            <span aria-hidden="true">←</span> Retour au tableau de bord plateforme
        </a>
        <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
            <div class="max-w-2xl">
                <h1 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Alertes plateforme</h1>
                <p class="mt-3 text-sm leading-relaxed text-slate-600">
                    Messages et bandeaux affichés sur le portail pour les profils concernés (selon la période et l’audience).
                    Les membres connectés voient aussi les annonces dans la cloche du bandeau supérieur lorsque le message leur est destiné.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <?php if ($canManagePlatform): ?>
                    <a href="<?= url('admin/system/alerts/create') ?>" class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-600">
                        Nouvelle alerte
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($readOnlySupport): ?>
        <div class="mb-6 rounded-2xl border border-sky-200 bg-sky-50/90 p-4 text-sm text-sky-950 shadow-sm">
            <p class="font-bold text-sky-900">Consultation seule</p>
            <p class="mt-1 leading-relaxed text-sky-900/90">
                Votre rôle permet de consulter les annonces prévues sur le site. La création et la modification restent réservées aux administrateurs plateforme.
            </p>
        </div>
    <?php endif; ?>

    <?php if ($s): ?>
        <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900" role="status">
            <?= htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>
    <?php if ($e): ?>
        <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-900" role="alert">
            <?= htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if ($rows !== []): ?>
        <div class="mb-8 grid gap-3 sm:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-black uppercase tracking-wider text-slate-500">Publication activée</p>
                <p class="mt-1 text-2xl font-black text-slate-900"><?= (int) ($stats['published'] ?? 0) ?></p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-black uppercase tracking-wider text-slate-500">Publication désactivée</p>
                <p class="mt-1 text-2xl font-black text-slate-900"><?= (int) ($stats['disabled'] ?? 0) ?></p>
            </div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4 shadow-sm">
                <p class="text-[11px] font-black uppercase tracking-wider text-emerald-800">Diffusées sur le portail maintenant</p>
                <p class="mt-1 text-2xl font-black text-emerald-950"><?= (int) ($stats['visible_now'] ?? 0) ?></p>
                <p class="mt-1 text-xs text-emerald-900/80">Compte les annonces actives dont la période le permet.</p>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($rows === []): ?>
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center shadow-sm">
            <p class="text-slate-700">
                <?= $canManagePlatform
                    ? 'Aucune alerte pour l’instant. Créez une annonce pour informer les visiteurs ou les membres (nouveauté, offre, message important).'
                    : 'Aucune alerte configurée pour le moment.' ?>
            </p>
            <?php if ($canManagePlatform): ?>
                <a href="<?= url('admin/system/alerts/create') ?>" class="mt-5 inline-flex items-center rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-600">
                    Créer une alerte
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <ul class="space-y-4">
            <?php foreach ($rows as $r): ?>
                <?php
                $id = (int) ($r['id'] ?? 0);
                $kindLabel = (string) ($r['_kind_label'] ?? '');
                $badge = $r['_badge'] ?? ['class' => 'bg-slate-100 text-slate-800', 'ring' => 'ring-1'];
                $badgeClass = is_array($badge) ? (string) ($badge['class'] ?? '') : '';
                $badgeRing = is_array($badge) ? (string) ($badge['ring'] ?? 'ring-1') : 'ring-1';
                $active = ! empty($r['is_active']);
                $visibleNow = ! empty($r['_visible_now']);
                $audience = $r['_audience'] ?? [];
                $audience = is_array($audience) ? $audience : [];
                $bodyPrev = (string) ($r['_body_preview'] ?? '');
                $ctaLabel = trim((string) ($r['cta_label'] ?? ''));
                $ctaUrl = trim((string) ($r['cta_url'] ?? ''));
                $availTone = match ((string) ($r['_availability_key'] ?? 'inactive')) {
                    'live' => 'bg-emerald-100 text-emerald-950 ring-emerald-200/80',
                    'scheduled' => 'bg-sky-100 text-sky-950 ring-sky-200/80',
                    'ended' => 'bg-slate-200 text-slate-800 ring-slate-300/80',
                    default => 'bg-amber-100 text-amber-950 ring-amber-200/80',
                };
                ?>
                <li class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-4 p-5">
                        <div class="min-w-0 flex-1 space-y-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-black uppercase tracking-wide <?= htmlspecialchars($badgeClass . ' ' . $badgeRing, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($kindLabel, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <?php if ($visibleNow): ?>
                                    <span class="inline-flex items-center rounded-full bg-emerald-600 px-2.5 py-0.5 text-[11px] font-black uppercase tracking-wide text-white">
                                        Diffusée sur le portail maintenant
                                    </span>
                                <?php endif; ?>
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold ring-1 <?= $active ? 'bg-white text-slate-800 ring-slate-200' : 'bg-slate-100 text-slate-600 ring-slate-200' ?>">
                                    <?= $active ? 'Publication activée' : 'Publication désactivée' ?>
                                </span>
                                <?php if (! $visibleNow): ?>
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold ring-1 <?= htmlspecialchars($availTone, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars((string) ($r['_availability'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <h2 class="text-lg font-black text-slate-900"><?= htmlspecialchars((string) ($r['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
                            <p class="text-xs text-slate-600">
                                <?= htmlspecialchars((string) ($r['_schedule'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                <span class="text-slate-400"> · </span>
                                Ordre d’affichage <?= (int) ($r['sort_order'] ?? 0) ?>
                            </p>
                            <?php if ($bodyPrev !== ''): ?>
                                <p class="text-sm leading-relaxed text-slate-700"><?= htmlspecialchars($bodyPrev, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                            <?php if ($ctaLabel !== '' || $ctaUrl !== ''): ?>
                                <p class="text-sm text-slate-600">
                                    <?php if ($ctaLabel !== ''): ?>
                                        <span class="font-semibold text-slate-800">Lien :</span>
                                        <?= htmlspecialchars($ctaLabel, ENT_QUOTES, 'UTF-8') ?>
                                    <?php endif; ?>
                                    <?php if ($ctaUrl !== ''): ?>
                                        <?php if ($ctaLabel !== ''): ?><span class="text-slate-400"> — </span><?php endif; ?>
                                        <span class="break-all text-emerald-800"><?= htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($audience !== []): ?>
                                <div class="flex flex-wrap gap-1.5 pt-1">
                                    <?php foreach ($audience as $chip): ?>
                                        <span class="inline-flex rounded-md border border-slate-200 bg-slate-50 px-2 py-0.5 text-[11px] font-medium text-slate-700">
                                            <?= htmlspecialchars((string) $chip, ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($canManagePlatform): ?>
                            <div class="flex shrink-0 flex-col gap-2 sm:items-end">
                                <a href="<?= url('admin/system/alerts/' . $id . '/edit') ?>" class="text-sm font-bold text-blue-700 hover:underline">Modifier</a>
                                <form method="post" action="<?= url('admin/system/alerts/' . $id . '/delete') ?>" class="inline" onsubmit="return confirm('Supprimer cette alerte ?');">
                                    <?= \App\Core\Csrf::field() ?>
                                    <button type="submit" class="text-sm font-bold text-rose-600 hover:underline">Supprimer</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
