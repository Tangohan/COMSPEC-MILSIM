<?php
declare(strict_types=1);

/**
 * Bloc « Bloc personnalisé » de l'accueil : le contenu libre de la communauté (mêmes blocs que la vitrine publique).
 * Lecture pour toute l'équipe ; le bouton « Modifier » n'est proposé qu'aux gestionnaires.
 *
 * @var array<string, mixed> $home
 * @var callable(string): string $pilH
 */
$hcuBlocks = is_array($home['custom']['blocks'] ?? null) ? $home['custom']['blocks'] : [];
$hcuSheets = is_array($home['custom']['sheets'] ?? null) ? $home['custom']['sheets'] : [];
$hcuCanEdit = !empty($home['custom']['can_edit']);
// Fermeture : les variables du gabarit de bloc ($b, $p, $h…) ne débordent pas sur le tableau de bord.
$hcuRender = static function (array $pbBlock, array $pbSheets): void {
    $pbEditing = false;
    require base_path('views/community/partials/page_block.php');
};
?>
<section class="bo-blocks" aria-label="Bloc personnalisé">
    <?php if ($hcuCanEdit): ?>
    <div class="bo-blocks__bar">
        <a class="pil-btn pil-btn--sm" href="<?= $pilH(url('back-office/accueil/bloc')) ?>"><?= $hcuBlocks === [] ? 'Créer le contenu' : 'Modifier le contenu' ?></a>
    </div>
    <?php endif; ?>
    <?php if ($hcuBlocks === []): ?>
    <div class="pil-card">
        <p class="pil-empty">Ce bloc est vide. Les gestionnaires peuvent y composer un message d’accueil, une consigne, une vidéo ou un tableau pour toute l’équipe.</p>
    </div>
    <?php else: ?>
    <div class="cl-body bo-blocks__stack">
        <?php foreach ($hcuBlocks as $hcuBlock): ?>
            <?php $hcuRender($hcuBlock, $hcuSheets); ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
