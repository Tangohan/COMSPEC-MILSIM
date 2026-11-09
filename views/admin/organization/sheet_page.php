<?php
declare(strict_types=1);

/**
 * Un tableau de l'accueil en pleine page (/back-office/tableaux/{id}).
 *
 * @var array<string, mixed> $sheetView
 */
$sheetCompact = false;
?>
<div class="pil bsh-page" data-pilotage>
    <p class="bsh-page__back"><a class="pil-link" href="<?= htmlspecialchars(url('back-office') . '#tableau-' . (int) $sheetView['id'], ENT_QUOTES, 'UTF-8') ?>">← Retour au tableau de bord</a></p>
    <section class="pil-card">
        <?php require base_path('views/admin/organization/_sheet.php'); ?>
    </section>
</div>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/back-office-sheet.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
