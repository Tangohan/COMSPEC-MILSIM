<?php
declare(strict_types=1);
/**
 * Vue d'ensemble de la page Équipement (à l'arrivée, views/equipment/hub.php) : quatre sections claires, chacune avec
 * son compteur, une aide courte avec un exemple, un état vide utile et un appel à l'action principal :
 * Mes tenues, Kits de la communauté, Modèles, Configurateur (avec l'avancement du catalogue de base d'Arma 3, chargé
 * tout seul au premier accès par public/assets/js/equipment-base-catalog.js).
 *
 * Variables : $counts, $kitsAllowed, $canManageKits, $wardrobes, $eqBase (VanillaCatalogLoader::status), $csrfToken, $h.
 */
$eqModels = count(array_filter($wardrobes, static fn (array $w): bool => (string) ($w['source'] ?? '') === 'template'));
$eqMine = (int) ($counts['mes-tenues'] ?? 0);
$eqKits = $kitsAllowed ? (int) ($counts['kits'] ?? 0) : null;
$eqBase = is_array($eqBase ?? null) ? $eqBase : ['available' => false];
$eqBaseNeeded = !empty($eqBase['available']) && !empty($eqBase['needed']);
$eqIcon = static fn (string $d): string => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="' . $d . '"/></svg>';
?>
<section class="eqo" aria-labelledby="eqo-title">
    <div class="eqo__head">
        <div>
            <h2 id="eqo-title">Par où commencer</h2>
            <p>Une tenue composée ici se retrouve en jeu dans l’arsenal (panneau « Athena · Tenues »).</p>
        </div>
    </div>
    <div class="eqo__grid">
        <article class="eqo-card">
            <div class="eqo-card__top">
                <span class="eqo-card__icon"><?= $eqIcon('M8 3l4 2 4-2 5 4-3 3-2-1v12H8V9L6 10 3 7z') ?></span>
                <span class="eqo-card__count"><?= $eqMine ?> <small><?= $eqMine > 1 ? 'tenues' : 'tenue' ?></small></span>
            </div>
            <h3>Mes tenues</h3>
            <?php if ($eqMine === 0): ?>
            <p class="eqo-card__empty">Aucune tenue pour l’instant. Composez la première au configurateur, ou enregistrez celle que vous portez en jeu.</p>
            <?php else: ?>
            <p>Vos tenues personnelles, prêtes à enfiler en jeu ou à partager.</p>
            <?php endif; ?>
            <p class="eqo-card__ex">Exemple : « Fusilier MX — désert », ouverte au configurateur puis enfilée à l’arsenal.</p>
            <div class="eqo-card__acts">
                <?php if ($eqMine === 0): ?>
                <a class="eqo-btn" href="<?= $h(url('equipment/configurateur')) ?>">Composer ma première tenue</a>
                <?php else: ?>
                <a class="eqo-btn" href="<?= $h(\App\Support\EquipmentSpaces::url(['espace' => 'mes-tenues']) . '#espace') ?>">Voir mes tenues</a>
                <a class="eqo-btn eqo-btn--ghost" href="<?= $h(url('equipment/configurateur')) ?>">Nouvelle tenue</a>
                <?php endif; ?>
            </div>
        </article>

        <article class="eqo-card">
            <div class="eqo-card__top">
                <span class="eqo-card__icon"><?= $eqIcon('M4 7h16v12H4zM9 7V4h6v3M4 12h16') ?></span>
                <?php if ($eqKits !== null): ?><span class="eqo-card__count"><?= $eqKits ?> <small><?= $eqKits > 1 ? 'kits' : 'kit' ?></small></span><?php else: ?><span class="eqx-pill">Pro</span><?php endif; ?>
            </div>
            <h3>Kits de la communauté</h3>
            <?php if ($eqKits === null): ?>
            <p>Kits de départ proposés à un rôle, une unité ou un membre, pris en jeu à une caisse : inclus dans les formules Pro et plus.</p>
            <?php elseif ($eqKits === 0): ?>
            <p class="eqo-card__empty"><?= $canManageKits ? 'Aucun kit encore : composez une tenue au configurateur, puis « Enregistrer › Kit de départ ».' : 'Aucun kit ne vous est proposé pour l’instant : un responsable de l’équipement en créera un.' ?></p>
            <?php else: ?>
            <p>Les kits de départ qui vous sont proposés : prenez-les en jeu à une caisse de kits.</p>
            <?php endif; ?>
            <p class="eqo-card__ex">Exemple : kit « Médecin » proposé à la qualification Secouriste.</p>
            <div class="eqo-card__acts">
                <?php if ($eqKits !== null): ?>
                <a class="eqo-btn" href="<?= $h(\App\Support\EquipmentSpaces::url(['espace' => 'kits']) . '#espace') ?>"><?= $eqKits === 0 && $canManageKits ? 'Créer un kit' : 'Voir les kits' ?></a>
                <?php endif; ?>
            </div>
        </article>

        <article class="eqo-card">
            <div class="eqo-card__top">
                <span class="eqo-card__icon"><?= $eqIcon('M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z') ?></span>
                <span class="eqo-card__count"><?= $eqModels ?> <small><?= $eqModels > 1 ? 'modèles' : 'modèle' ?></small></span>
            </div>
            <h3>Modèles</h3>
            <?php if ($eqModels === 0): ?>
            <p class="eqo-card__empty"><?= $canManageKits ? 'Aucun modèle : enregistrez une tenue « en modèle » pour donner un point de départ à tout le monde.' : 'Aucun modèle pour l’instant : partez d’une tenue vide au configurateur.' ?></p>
            <?php else: ?>
            <p>Points de départ partagés par la communauté : copiez-en un, puis adaptez-le.</p>
            <?php endif; ?>
            <p class="eqo-card__ex">Exemple : modèle « Base OTAN » à compléter avec votre arme.</p>
            <div class="eqo-card__acts">
                <a class="eqo-btn" href="<?= $h(url('equipment/configurateur') . '?recuperer=1') ?>"><?= $eqModels > 0 ? 'Partir d’un modèle' : 'Ouvrir le configurateur' ?></a>
            </div>
        </article>

        <article class="eqo-card eqo-card--main">
            <div class="eqo-card__top">
                <span class="eqo-card__icon"><?= $eqIcon('M2 12h13l2-2h5v3h-3l-1 3h-3l-1-2H8l-2 4H3l1-4H2z') ?></span>
                <?php if (!empty($eqBase['available'])): ?><span class="eqo-card__count"><?= (int) ($eqBase['total'] ?? 0) ?> <small>objets</small></span><?php endif; ?>
            </div>
            <h3>Configurateur</h3>
            <p>Montez une arme (accessoires compatibles seulement), remplissez uniforme, gilet et sac, copiez d’une tenue à l’autre.</p>
            <p class="eqo-card__ex">Exemple : MX 6,5 mm + ACO + 6 chargeurs dans le gilet.</p>
            <?php if (!empty($eqBase['available'])): ?>
            <div class="eqo-base" data-eq-base data-url="<?= $h(url('equipment/configurateur/catalogue/base')) ?>" data-csrf="<?= $h($csrfToken) ?>" data-needed="<?= $eqBaseNeeded ? '1' : '0' ?>"<?= $eqBaseNeeded ? '' : ' hidden' ?>>
                <span data-eq-base-text>Préparation du catalogue de base d’Arma 3…</span>
                <span class="eqo-base__bar" role="progressbar" aria-label="Catalogue de base" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><i data-eq-base-bar></i></span>
            </div>
            <?php endif; ?>
            <div class="eqo-card__acts">
                <a class="eqo-btn" href="<?= $h(url('equipment/configurateur')) ?>">Ouvrir le configurateur</a>
            </div>
        </article>
    </div>
</section>
