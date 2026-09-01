<?php
declare(strict_types=1);

/**
 * Accès organisateur au compositeur d’annonces existant (pas un second CMS).
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
            <p class="dash-hub-panel__lead">Publiez une courte annonce visible sur le tableau de bord des membres. Vous ouvrez le même formulaire que depuis les annonces de la communauté.</p>
        </div>
    </div>
    <div class="dash-offer-card__actions">
        <a href="<?= htmlspecialchars(url('back-office/alerts/create'), ENT_QUOTES, 'UTF-8') ?>" class="dash-hub-panel__cta">Rédiger une annonce</a>
        <a href="<?= htmlspecialchars(url('publier'), ENT_QUOTES, 'UTF-8') ?>" class="dash-hub-panel__ghost">Autres publications</a>
        <a href="<?= htmlspecialchars(url('back-office/alerts'), ENT_QUOTES, 'UTF-8') ?>" class="dash-hub-panel__ghost">Liste des annonces</a>
    </div>
</section>
