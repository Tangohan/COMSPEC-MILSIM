<?php
declare(strict_types=1);

/** Onglets de « Documents RH » : le coffre du dossier, et les documents d'identité (si le droit existe). */
$tab = (string) ($rhDocsTab ?? 'coffre');
$withIdentity = !empty($rhDocsIdentityTab);
$h = $h ?? static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
if (!$withIdentity) {
    return;
}
?>
<nav class="rh-dt" aria-label="Sections des documents RH">
    <a class="rh-dt__tab<?= $tab === 'coffre' ? ' is-active' : '' ?>" href="<?= $h(effectifs_workspace_url('documents-rh')) ?>"<?= $tab === 'coffre' ? ' aria-current="page"' : '' ?>>Pièces du dossier RH</a>
    <a class="rh-dt__tab<?= $tab === 'identite' ? ' is-active' : '' ?>" href="<?= $h(effectifs_workspace_url('documents-rh?vue=identite')) ?>"<?= $tab === 'identite' ? ' aria-current="page"' : '' ?>>Documents d’identité</a>
</nav>
