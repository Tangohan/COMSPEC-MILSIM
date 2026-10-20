<?php
declare(strict_types=1);

/**
 * Nouveau compte rendu (/back-office/atak/comptes-rendus/nouveau[?mission=ID]).
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$base = url('back-office/atak/comptes-rendus');

$report = is_array($aarReport ?? null) ? $aarReport : [];
$missions = is_array($aarMissions ?? null) ? $aarMissions : [];
$templates = is_array($aarTemplates ?? null) ? $aarTemplates : [];
$isEdit = false;
?>
<form method="post" action="<?= $h($base) ?>" class="bo-aar bo-aar-form" id="bo-aar-form">
  <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken ?? '') ?>">
  <?php require base_path('views/admin/aar_reports/partials/form_fields.php'); ?>
  <div class="bo-aar-formbar">
    <span class="bo-aar-muted">Le compte rendu est créé « En attente » : il passe ensuite en relecture puis en validation depuis sa fiche.</span>
    <a class="ath-btn" href="<?= $h($base) ?>">Annuler</a>
    <button type="submit" class="ath-btn ath-btn--solid">Déposer le compte rendu</button>
  </div>
</form>
<script src="<?= $h(asset_url('assets/js/back-office-aar.js')) ?>" defer></script>
