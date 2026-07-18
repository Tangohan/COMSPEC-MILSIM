<?php
declare(strict_types=1);
$preamblePostUrl = url(training_studio_path() . '/preamble-ack');
$pilotageUrl = training_lms_admin_url();
?>
<section class="ts-preamble" aria-labelledby="ts-preamble-title">
    <div class="ts-preamble__ambient" aria-hidden="true"></div>

    <header class="ts-preamble__hero">
        <p class="ts-preamble__kicker">Atelier de conception</p>
        <h1 id="ts-preamble-title" class="ts-preamble__title">Studio de formations</h1>
        <p class="ts-preamble__lede">
            Créez et structurez les parcours de votre communauté&nbsp;: modules, leçons et ressources, avant publication dans le catalogue des apprenants.
        </p>
        <form method="post" action="<?= htmlspecialchars($preamblePostUrl, ENT_QUOTES, 'UTF-8') ?>" class="ts-preamble__actions">
            <?= \App\Core\Csrf::field() ?>
            <button type="submit" class="ts-preamble__cta">
                Entrer dans le studio
            </button>
            <a href="<?= htmlspecialchars($pilotageUrl, ENT_QUOTES, 'UTF-8') ?>" class="ts-preamble__secondary">
                Retour au pilotage des formations
            </a>
        </form>
    </header>

    <div class="ts-preamble__reminders" role="list">
        <article class="ts-preamble__card" role="listitem">
            <p class="ts-preamble__card-label">Session</p>
            <h2 class="ts-preamble__card-title">Poste partagé</h2>
            <p class="ts-preamble__card-text">Déconnectez-vous après utilisation sur un ordinateur commun à plusieurs personnes.</p>
        </article>
        <article class="ts-preamble__card" role="listitem">
            <p class="ts-preamble__card-label">Visibilité</p>
            <h2 class="ts-preamble__card-title">Brouillons côté staff</h2>
            <p class="ts-preamble__card-text">Les versions en préparation restent visibles aux formateurs et responsables. Vérifiez avant de publier.</p>
        </article>
        <article class="ts-preamble__card" role="listitem">
            <p class="ts-preamble__card-label">Coordination</p>
            <h2 class="ts-preamble__card-title">Changements sensibles</h2>
            <p class="ts-preamble__card-text">En cas de doute sur une modification importante, alignez-vous avec le commandement ou le pôle formation.</p>
        </article>
    </div>
</section>
