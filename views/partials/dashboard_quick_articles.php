<?php
declare(strict_types=1);

/**
 * Accès organisateur aux mini-articles permanents.
 *
 * @var bool $can_publish_dashboard_articles
 */

$canPublish = !empty($can_publish_dashboard_articles);
if (!$canPublish) {
    return;
}
?>
<section class="dash-hub-panel dash-hub-panel--organizer" id="dashboard-quick-articles" aria-labelledby="dash-quick-articles-title">
    <div class="dash-hub-panel__head">
        <div>
            <p class="dash-hub-panel__kicker">Organisation</p>
            <h2 id="dash-quick-articles-title" class="dash-hub-panel__title">Rédiger un article</h2>
            <p class="dash-hub-panel__lead">Créez un mini-article permanent : titre, tags, description, images et contenu HTML. Visible sur le tableau de bord et la page Articles.</p>
        </div>
    </div>
    <div class="dash-offer-card__actions">
        <a href="<?= htmlspecialchars(url('back-office/articles/create'), ENT_QUOTES, 'UTF-8') ?>" class="dash-hub-panel__cta">Nouveau mini-article</a>
        <a href="<?= htmlspecialchars(url('back-office/articles'), ENT_QUOTES, 'UTF-8') ?>" class="dash-hub-panel__ghost">Mes articles</a>
        <a href="<?= htmlspecialchars(url('articles'), ENT_QUOTES, 'UTF-8') ?>" class="dash-hub-panel__ghost">Voir côté membres</a>
        <a href="<?= htmlspecialchars(url('publier'), ENT_QUOTES, 'UTF-8') ?>" class="dash-hub-panel__ghost">Autres publications</a>
    </div>
</section>
