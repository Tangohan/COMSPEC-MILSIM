<?php
/** @var bool $canTba */
/** @var string|null $preferredPortal */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$canTba = !empty($canTba);
$pref = (string) ($preferredPortal ?? '');
$prefLabel = match ($pref) {
    'tba' => 'Tableau de bord administratif',
    'jnet' => 'Accueil Athena',
    default => 'Aucun (choix à chaque connexion)',
};
?>
<section class="jnet-panel">
    <div class="jnet-panel__head">
        <h2>Système & bascule d’espace</h2>
    </div>
    <div class="jnet-panel__body jnet-stack">
        <p class="jnet-lead">Préférence mémorisée : <strong><?= $h($prefLabel) ?></strong>.</p>

        <form method="post" action="<?= $h(url('jnet/systeme/bascule')) ?>" class="jnet-stack">
            <?= \App\Core\Csrf::field() ?>
            <?php if ($canTba): ?>
                <button class="jnet-btn jnet-btn--accent" type="submit" name="action" value="tba">
                    Ouvrir le tableau de bord administratif
                </button>
            <?php endif; ?>
            <button class="jnet-btn" type="submit" name="action" value="chooser">
                Revenir à l’écran de choix
            </button>
            <button class="jnet-btn" type="submit" name="action" value="clear_preference">
                Effacer le choix mémorisé
            </button>
        </form>

        <p class="jnet-meta">JNET Extranet — environnement de situation pour le jeu de rôle et la coordination. Les contenus sectoriels d’exemple seront progressivement reliés aux données de votre communauté.</p>
    </div>
</section>
