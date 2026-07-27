<?php
/** @var list<array{title: string, detail: string, href: string, unread: bool, at: string}> $activity_forum_items */
/** @var list<array{title: string, detail: string, href: string, unread: bool, at: string}> $activity_courrier_items */
/** @var list<array{title: string, detail: string, href: string, unread: bool, at: string}> $activity_message_items */
/** @var array{forum_unread: int, courrier_unread: int, tenant_messages_unread: int, total?: int} $activity_unread_counts */
$activity_forum_items = $activity_forum_items ?? [];
$activity_courrier_items = $activity_courrier_items ?? [];
$activity_message_items = $activity_message_items ?? [];
$activity_announce_items = $activity_announce_items ?? [];
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
$totalUnread = $uForum + $uCourrier + $uMsgs;
$formatActivityAt = static function (string $at): ?string {
    if ($at === '') {
        return null;
    }
    $t = strtotime($at);

    return $t !== false ? date('d/m/Y H:i', $t) : null;
};
$fmtCount = static function (int $n): string {
    return $n > 99 ? '99+' : (string) $n;
};
$renderItems = static function (array $items) use ($formatActivityAt): void {
    echo '<ul class="act-hub__list">';
    foreach ($items as $it) {
        $atLabel = $formatActivityAt((string) ($it['at'] ?? ''));
        echo '<li class="act-hub__item">';
        echo '<a class="act-hub__row" href="' . htmlspecialchars((string) ($it['href'] ?? '#'), ENT_QUOTES, 'UTF-8') . '">';
        echo '<div class="act-hub__row-top">';
        echo '<span class="act-hub__row-title">' . htmlspecialchars((string) ($it['title'] ?? ''), ENT_QUOTES, 'UTF-8') . '</span>';
        if (!empty($it['unread'])) {
            echo '<span class="act-hub__badge">Nouveau</span>';
        }
        echo '</div>';
        echo '<p class="act-hub__row-detail">' . htmlspecialchars((string) ($it['detail'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p>';
        if ($atLabel !== null) {
            echo '<p class="act-hub__row-time">' . htmlspecialchars($atLabel, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        echo '</a></li>';
    }
    echo '</ul>';
};
?>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@600;700&display=swap" rel="stylesheet">
<link href="<?= htmlspecialchars(asset_url('assets/css/activity_hub.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">

<div class="act-hub">
    <div class="act-hub__frame">
        <header class="act-hub__hero">
            <div class="act-hub__hero-inner">
                <p class="act-hub__brand">Athena</p>
                <h1 class="act-hub__title">Votre activité</h1>
                <p class="act-hub__lead">
                    Tout ce qui demande votre attention — <strong>alertes</strong>, <strong>courrier</strong> et <strong>messagerie</strong> —
                    au même endroit, pour ne rien laisser passer dans votre communauté.
                    <?php if ($totalUnread > 0): ?>
                    <strong><?= (int) $totalUnread ?> élément<?= $totalUnread > 1 ? 's' : '' ?> à traiter</strong> en ce moment.
                    <?php else: ?>
                    <strong>Rien en attente</strong> pour l’instant.
                    <?php endif; ?>
                </p>
            </div>
        </header>

        <div class="act-hub__metrics" aria-label="Résumé des éléments non lus">
            <div class="act-hub__metric">
                <span class="act-hub__metric-label">Alertes</span>
                <span class="act-hub__metric-value<?= $uForum > 0 ? ' act-hub__metric-value--hot' : '' ?>"><?= htmlspecialchars($fmtCount($uForum), ENT_QUOTES, 'UTF-8') ?></span>
                <span class="act-hub__metric-hint"><?= $activity_forum_available ? 'Forum, suivis et signalements' : 'Canal temporairement indisponible' ?></span>
            </div>
            <div class="act-hub__metric">
                <span class="act-hub__metric-label">Courrier</span>
                <span class="act-hub__metric-value<?= $uCourrier > 0 ? ' act-hub__metric-value--hot' : '' ?>"><?= htmlspecialchars($fmtCount($uCourrier), ENT_QUOTES, 'UTF-8') ?></span>
                <span class="act-hub__metric-hint"><?= $activity_courrier_available ? 'Documents et notes internes' : 'Accès non disponible pour votre profil' ?></span>
            </div>
            <div class="act-hub__metric">
                <span class="act-hub__metric-label">Messagerie</span>
                <span class="act-hub__metric-value<?= $uMsgs > 0 ? ' act-hub__metric-value--hot' : '' ?>"><?= htmlspecialchars($fmtCount($uMsgs), ENT_QUOTES, 'UTF-8') ?></span>
                <span class="act-hub__metric-hint">Échanges avec l’encadrement</span>
            </div>
        </div>

        <div class="act-hub__grid">
            <?php if ($activity_announce_items !== []): ?>
            <section class="act-hub__panel act-hub__panel--full" aria-labelledby="act-hub-announce-title">
                <div class="act-hub__panel-head">
                    <div>
                        <p class="act-hub__panel-kicker">Communauté</p>
                        <h2 id="act-hub-announce-title" class="act-hub__panel-title">Annonces</h2>
                        <p class="act-hub__panel-desc">Messages publiés par votre communauté et affichés dans ce fil d’activité.</p>
                    </div>
                </div>
                <div class="act-hub__panel-body">
                    <?php $renderItems($activity_announce_items); ?>
                </div>
            </section>
            <?php endif; ?>
            <section class="act-hub__panel" aria-labelledby="act-hub-forum-title">
                <div class="act-hub__panel-head">
                    <div>
                        <p class="act-hub__panel-kicker">Canal 01</p>
                        <h2 id="act-hub-forum-title" class="act-hub__panel-title">Alertes</h2>
                        <p class="act-hub__panel-desc">Réponses, citations, suivis roleplay et autres notifications liées à votre espace.</p>
                    </div>
                    <?php if ($activity_forum_available && $activity_forum_items !== []): ?>
                    <form method="post" action="<?= htmlspecialchars(url('activite/forum/lu'), ENT_QUOTES, 'UTF-8') ?>">
                        <?= \App\Core\Csrf::field() ?>
                        <button type="submit" class="act-hub__mark-read">Tout marquer comme lu</button>
                    </form>
                    <?php endif; ?>
                </div>
                <div class="act-hub__panel-body">
                    <?php if (!$activity_forum_available): ?>
                        <p class="act-hub__restricted">Le forum n’est pas accessible pour le moment ; les alertes liées au forum ne s’affichent pas ici.</p>
                    <?php elseif ($activity_forum_items === []): ?>
                        <div class="act-hub__empty">
                            <p>Aucune alerte récente. Votre fil est à jour.</p>
                            <a class="act-hub__empty-link" href="<?= htmlspecialchars(url('forum'), ENT_QUOTES, 'UTF-8') ?>">Ouvrir le forum</a>
                        </div>
                    <?php else: ?>
                        <?php $renderItems($activity_forum_items); ?>
                    <?php endif; ?>
                </div>
            </section>

            <section class="act-hub__panel" aria-labelledby="act-hub-courrier-title">
                <div class="act-hub__panel-head">
                    <div>
                        <p class="act-hub__panel-kicker">Canal 02</p>
                        <h2 id="act-hub-courrier-title" class="act-hub__panel-title">Courrier</h2>
                        <p class="act-hub__panel-desc">Pièces et notes transmises via le courrier interne de la communauté.</p>
                    </div>
                    <?php if ($activity_courrier_available && $activity_courrier_items !== []): ?>
                    <form method="post" action="<?= htmlspecialchars(url('activite/courrier/lu'), ENT_QUOTES, 'UTF-8') ?>">
                        <?= \App\Core\Csrf::field() ?>
                        <button type="submit" class="act-hub__mark-read">Tout marquer comme lu</button>
                    </form>
                    <?php endif; ?>
                </div>
                <div class="act-hub__panel-body">
                    <?php if (!$activity_courrier_available): ?>
                        <p class="act-hub__restricted">Vous n’avez pas accès au courrier interne dans votre espace, ou celui-ci vous est temporairement restreint. Les notifications correspondantes ne s’affichent pas ici.</p>
                    <?php elseif ($activity_courrier_items === []): ?>
                        <div class="act-hub__empty">
                            <p>Aucune notification récente de courrier.</p>
                            <a class="act-hub__empty-link" href="<?= htmlspecialchars(url('courrier'), ENT_QUOTES, 'UTF-8') ?>">Ouvrir le bureau courrier</a>
                        </div>
                    <?php else: ?>
                        <?php $renderItems($activity_courrier_items); ?>
                    <?php endif; ?>
                </div>
            </section>

            <section class="act-hub__panel" aria-labelledby="act-hub-messages-title">
                <div class="act-hub__panel-head">
                    <div>
                        <p class="act-hub__panel-kicker">Canal 03</p>
                        <h2 id="act-hub-messages-title" class="act-hub__panel-title">Messagerie</h2>
                        <p class="act-hub__panel-desc">Conversations avec l’encadrement et les rôles habilités de votre communauté.</p>
                    </div>
                    <?php if ($activity_message_items !== []): ?>
                    <form method="post" action="<?= htmlspecialchars(url('activite/messages/lu'), ENT_QUOTES, 'UTF-8') ?>">
                        <?= \App\Core\Csrf::field() ?>
                        <button type="submit" class="act-hub__mark-read">Tout marquer comme lu</button>
                    </form>
                    <?php endif; ?>
                </div>
                <div class="act-hub__panel-body">
                    <?php if ($activity_message_items === []): ?>
                        <div class="act-hub__empty">
                            <p>Aucune conversation récente pour le moment.</p>
                            <a class="act-hub__empty-link" href="<?= htmlspecialchars(url('messages'), ENT_QUOTES, 'UTF-8') ?>">Ouvrir la messagerie</a>
                        </div>
                    <?php else: ?>
                        <?php $renderItems($activity_message_items); ?>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>
</div>
