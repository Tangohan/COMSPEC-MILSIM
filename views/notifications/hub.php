<?php
/** @var list<array{title: string, detail: string, href: string, unread: bool, at: string}> $activity_forum_items */
/** @var list<array{title: string, detail: string, href: string, unread: bool, at: string}> $activity_courrier_items */
/** @var list<array{title: string, detail: string, href: string, unread: bool, at: string}> $activity_message_items */
$activity_forum_items = $activity_forum_items ?? [];
$activity_courrier_items = $activity_courrier_items ?? [];
$activity_message_items = $activity_message_items ?? [];
?>
<div class="mx-auto max-w-3xl px-4 py-8 sm:px-6">
    <h1 class="text-2xl font-black uppercase italic tracking-tight text-slate-900">Mon activité</h1>
    <p class="mt-2 text-sm text-slate-600">Résumé des alertes (forum, suivi roleplay, etc.), courrier interne et messagerie pour votre espace.</p>

    <section class="mt-10 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Alertes et notifications</h2>
            <?php if ($activity_forum_items !== []): ?>
            <form method="post" action="<?= htmlspecialchars(url('activite/forum/lu'), ENT_QUOTES, 'UTF-8') ?>">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="text-xs font-bold uppercase tracking-wider text-emerald-700 hover:text-slate-900">Tout marquer comme lu</button>
            </form>
            <?php endif; ?>
        </div>
        <?php if ($activity_forum_items === []): ?>
            <p class="mt-4 text-sm text-slate-500">Aucune alerte récente.</p>
        <?php else: ?>
            <ul class="mt-4 divide-y divide-slate-100">
                <?php foreach ($activity_forum_items as $it): ?>
                <li class="py-3 first:pt-0">
                    <a href="<?= htmlspecialchars($it['href'], ENT_QUOTES, 'UTF-8') ?>" class="block rounded-lg px-1 py-1 transition hover:bg-slate-50">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-bold text-slate-900"><?= htmlspecialchars($it['title'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if (!empty($it['unread'])): ?>
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-black uppercase tracking-wider text-emerald-800">Nouveau</span>
                            <?php endif; ?>
                        </div>
                        <p class="mt-0.5 text-sm text-slate-600"><?= htmlspecialchars($it['detail'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php if ($it['at'] !== ''): ?>
                        <p class="mt-1 text-xs text-slate-400"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($it['at'])), ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Courrier interne</h2>
            <?php if ($activity_courrier_items !== []): ?>
            <form method="post" action="<?= htmlspecialchars(url('activite/courrier/lu'), ENT_QUOTES, 'UTF-8') ?>">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="text-xs font-bold uppercase tracking-wider text-emerald-700 hover:text-slate-900">Tout marquer comme lu</button>
            </form>
            <?php endif; ?>
        </div>
        <?php if ($activity_courrier_items === []): ?>
            <p class="mt-4 text-sm text-slate-500">Aucune notification récente.</p>
        <?php else: ?>
            <ul class="mt-4 divide-y divide-slate-100">
                <?php foreach ($activity_courrier_items as $it): ?>
                <li class="py-3 first:pt-0">
                    <a href="<?= htmlspecialchars($it['href'], ENT_QUOTES, 'UTF-8') ?>" class="block rounded-lg px-1 py-1 transition hover:bg-slate-50">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-bold text-slate-900"><?= htmlspecialchars($it['title'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if (!empty($it['unread'])): ?>
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-black uppercase tracking-wider text-emerald-800">Nouveau</span>
                            <?php endif; ?>
                        </div>
                        <p class="mt-0.5 text-sm text-slate-600"><?= htmlspecialchars($it['detail'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php if ($it['at'] !== ''): ?>
                        <p class="mt-1 text-xs text-slate-400"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($it['at'])), ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Messagerie interne</h2>
            <?php if ($activity_message_items !== []): ?>
            <form method="post" action="<?= htmlspecialchars(url('activite/messages/lu'), ENT_QUOTES, 'UTF-8') ?>">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="text-xs font-bold uppercase tracking-wider text-emerald-700 hover:text-slate-900">Tout marquer comme lu</button>
            </form>
            <?php endif; ?>
        </div>
        <?php if ($activity_message_items === []): ?>
            <p class="mt-4 text-sm text-slate-500">Aucune conversation récente.</p>
        <?php else: ?>
            <ul class="mt-4 divide-y divide-slate-100">
                <?php foreach ($activity_message_items as $it): ?>
                <li class="py-3 first:pt-0">
                    <a href="<?= htmlspecialchars($it['href'], ENT_QUOTES, 'UTF-8') ?>" class="block rounded-lg px-1 py-1 transition hover:bg-slate-50">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-bold text-slate-900"><?= htmlspecialchars($it['title'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if (!empty($it['unread'])): ?>
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-black uppercase tracking-wider text-emerald-800">Nouveau</span>
                            <?php endif; ?>
                        </div>
                        <p class="mt-0.5 text-sm text-slate-600"><?= htmlspecialchars($it['detail'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php if ($it['at'] !== ''): ?>
                        <p class="mt-1 text-xs text-slate-400"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($it['at'])), ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
