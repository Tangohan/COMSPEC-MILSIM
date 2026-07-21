<?php
declare(strict_types=1);
$preamblePostUrl = url(training_studio_path() . '/preamble-ack');
$pilotageUrl = training_lms_admin_url();
$preambleCourseCount = (int) ($trainingStudioCourseCount ?? 0);
?>
<div class="ts-preamble-stage">
    <section class="ts-preamble" aria-labelledby="ts-preamble-title">
        <header class="ts-preamble__hero">
            <div class="ts-preamble__hero-glow" aria-hidden="true"></div>
            <div class="ts-preamble__hero-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none"><path d="M4 19.5V6a2 2 0 0 1 2-2h8.5L20 7.5V18a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M14 4v3a1 1 0 0 0 1 1h3" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M8 12.5h8M8 15.5h5.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            </div>
            <p class="ts-preamble__kicker">Atelier de conception</p>
            <h1 id="ts-preamble-title" class="ts-preamble__title">Studio de formations</h1>
            <p class="ts-preamble__lede">
                Créez et structurez les parcours de votre communauté&nbsp;: modules, leçons et ressources, avant publication dans le catalogue des apprenants.
            </p>
            <?php if ($preambleCourseCount > 0): ?>
            <p class="ts-preamble__count"><?= $preambleCourseCount ?> parcours déjà en cours de préparation</p>
            <?php endif; ?>
            <form method="post" action="<?= htmlspecialchars($preamblePostUrl, ENT_QUOTES, 'UTF-8') ?>" class="ts-preamble__actions">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="ts-preamble__cta">
                    Entrer dans le studio
                    <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 10h12M11 5l5 5-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
                <a href="<?= htmlspecialchars($pilotageUrl, ENT_QUOTES, 'UTF-8') ?>" class="ts-preamble__secondary">
                    Retour au pilotage des formations
                </a>
            </form>
        </header>

        <div class="ts-preamble__reminders" role="list">
            <article class="ts-preamble__card" role="listitem">
                <div class="ts-preamble__card-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M12 21s-7-4.35-7-10a5 5 0 0 1 9-3 5 5 0 0 1 9 3c0 5.65-7 10-7 10a1 1 0 0 1-4 0Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
                </div>
                <p class="ts-preamble__card-label">Session</p>
                <h2 class="ts-preamble__card-title">Poste partagé</h2>
                <p class="ts-preamble__card-text">Déconnectez-vous après utilisation sur un ordinateur commun à plusieurs personnes.</p>
            </article>
            <article class="ts-preamble__card" role="listitem">
                <div class="ts-preamble__card-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M2 12s3.75-7 10-7 10 7 10 7-3.75 7-10 7-10-7-10-7Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6"/></svg>
                </div>
                <p class="ts-preamble__card-label">Visibilité</p>
                <h2 class="ts-preamble__card-title">Brouillons côté staff</h2>
                <p class="ts-preamble__card-text">Les versions en préparation restent visibles aux formateurs et responsables. Vérifiez avant de publier.</p>
            </article>
            <article class="ts-preamble__card" role="listitem">
                <div class="ts-preamble__card-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M12 9v4M12 17h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M10.29 3.86 1.82 18a1.5 1.5 0 0 0 1.3 2.25h17.76a1.5 1.5 0 0 0 1.3-2.25L13.71 3.86a1.5 1.5 0 0 0-2.42 0Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
                </div>
                <p class="ts-preamble__card-label">Coordination</p>
                <h2 class="ts-preamble__card-title">Changements sensibles</h2>
                <p class="ts-preamble__card-text">En cas de doute sur une modification importante, alignez-vous avec le commandement ou le pôle formation.</p>
            </article>
        </div>
    </section>
</div>
