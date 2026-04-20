<?php
/** @var list<array{title: string, detail: string, href: string, unread: bool, at: string}> $activity_forum_items */
/** @var list<array{title: string, detail: string, href: string, unread: bool, at: string}> $activity_courrier_items */
/** @var list<array{title: string, detail: string, href: string, unread: bool, at: string}> $activity_message_items */
/** @var array{forum_unread: int, courrier_unread: int, tenant_messages_unread: int, total?: int} $activity_unread_counts */
$activity_forum_items = $activity_forum_items ?? [];
$activity_courrier_items = $activity_courrier_items ?? [];
$activity_message_items = $activity_message_items ?? [];
$activity_forum_available = (bool) ($activity_forum_available ?? true);
$activity_courrier_available = (bool) ($activity_courrier_available ?? true);
$activity_unread_counts = $activity_unread_counts ?? [
    'forum_unread' => 0,
    'courrier_unread' => 0,
    'tenant_messages_unread' => 0,
];
$uForum = (int) ($activity_unread_counts['forum_unread'] ?? 0);
$uCourrier = (int) ($activity_unread_counts['courrier_unread'] ?? 0);
$uMsgs = (int) ($activity_unread_counts['tenant_messages_unread'] ?? 0);
$formatActivityAt = static function (string $at): ?string {
    if ($at === '') {
        return null;
    }
    $t = strtotime($at);

    return $t !== false ? date('d/m/Y H:i', $t) : null;
};
?>
<div class="mx-auto max-w-3xl px-4 py-8 sm:px-6">
    <h1 class="text-2xl font-black uppercase italic tracking-tight text-slate-900">Mon activité</h1>
    <p class="mt-2 text-sm text-slate-600">Résumé des alertes (forum, suivi roleplay, etc.), courrier interne et messagerie pour votre espace.</p>

    <div class="mt-6 flex flex-wrap gap-2 text-xs font-semibold text-slate-700">
        <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-1 shadow-sm">
            Forum&nbsp;: <?= $uForum > 99 ? '99+' : (string) $uForum ?>
        </span>
        <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-1 shadow-sm">
            Courrier&nbsp;: <?= $uCourrier > 99 ? '99+' : (string) $uCourrier ?>
        </span>
        <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-1 shadow-sm">
            Messagerie&nbsp;: <?= $uMsgs > 99 ? '99+' : (string) $uMsgs ?>
        </span>
    </div>

    <section class="mt-10 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Alertes et notifications</h2>
            <?php if ($activity_forum_available && $activity_forum_items !== []): ?>
            <form method="post" action="<?= htmlspecialchars(url('activite/forum/lu'), ENT_QUOTES, 'UTF-8') ?>">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="text-xs font-bold uppercase tracking-wider text-emerald-700 hover:text-slate-900">Tout marquer comme lu</button>
            </form>
            <?php endif; ?>
        </div>
        <?php if (!$activity_forum_available): ?>
            <p class="mt-4 text-sm text-slate-600">Le forum n’est pas accessible pour le moment ; les alertes liées au forum ne s’affichent pas ici.</p>
        <?php elseif ($activity_forum_items === []): ?>
            <p class="mt-4 text-sm text-slate-500">Aucune alerte récente.</p>
            <p class="mt-3 text-sm"><a href="<?= htmlspecialchars(url('forum'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-700 hover:text-slate-900">Ouvrir le forum</a></p>
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
                        <?php
                        $atLabel = $formatActivityAt((string) ($it['at'] ?? ''));
                        if ($atLabel !== null):
                        ?>
                        <p class="mt-1 text-xs text-slate-400"><?= htmlspecialchars($atLabel, ENT_QUOTES, 'UTF-8') ?></p>
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
            <?php if ($activity_courrier_available && $activity_courrier_items !== []): ?>
            <form method="post" action="<?= htmlspecialchars(url('activite/courrier/lu'), ENT_QUOTES, 'UTF-8') ?>">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="text-xs font-bold uppercase tracking-wider text-emerald-700 hover:text-slate-900">Tout marquer comme lu</button>
            </form>
            <?php endif; ?>
        </div>
        <?php if (!$activity_courrier_available): ?>
            <p class="mt-4 text-sm text-slate-600">Vous n’avez pas accès au courrier interne dans votre espace, ou celui-ci vous est temporairement restreint. Les notifications correspondantes ne s’affichent pas ici.</p>
        <?php elseif ($activity_courrier_items === []): ?>
            <p class="mt-4 text-sm text-slate-500">Aucune notification récente.</p>
            <p class="mt-3 text-sm"><a href="<?= htmlspecialchars(url('courrier'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-700 hover:text-slate-900">Ouvrir le bureau courrier</a></p>
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
                        <?php
                        $atLabel = $formatActivityAt((string) ($it['at'] ?? ''));
                        if ($atLabel !== null):
                        ?>
                        <p class="mt-1 text-xs text-slate-400"><?= htmlspecialchars($atLabel, ENT_QUOTES, 'UTF-8') ?></p>
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
            <p class="mt-3 text-sm"><a href="<?= htmlspecialchars(url('messages'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-700 hover:text-slate-900">Ouvrir la messagerie</a></p>
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
                        <?php
                        $atLabel = $formatActivityAt((string) ($it['at'] ?? ''));
                        if ($atLabel !== null):
                        ?>
                        <p class="mt-1 text-xs text-slate-400"><?= htmlspecialchars($atLabel, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
