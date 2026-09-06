<?php
/**
 * Sélecteur de vue du dossier : fiche (tous les membres) ou commandement.
 * Visible uniquement si le visiteur a le droit de consulter la vue commandement.
 *
 * @var string $personnelFileBaseUrl
 * @var bool $canAccessRhView
 * @var bool $personnelFileIsRhFull
 */
if (empty($canAccessRhView)) {
    return;
}
$switcherIsCommand = !empty($personnelFileIsRhFull);
$ficheUrl = (string) ($personnelFileBaseUrl ?? '');
$commandUrl = $ficheUrl . '?view=rh';
$switcherTone = $switcherIsCommand ? 'command' : 'fiche';
?>
<nav class="personnel-file-switcher personnel-file-switcher--<?= htmlspecialchars($switcherTone, ENT_QUOTES, 'UTF-8') ?>" aria-label="Vue du dossier">
    <a
        href="<?= htmlspecialchars($ficheUrl, ENT_QUOTES, 'UTF-8') ?>"
        class="personnel-file-switcher__item<?= $switcherIsCommand ? '' : ' is-current' ?>"
        <?= $switcherIsCommand ? '' : ' aria-current="page"' ?>
    >Fiche</a>
    <a
        href="<?= htmlspecialchars($commandUrl, ENT_QUOTES, 'UTF-8') ?>"
        class="personnel-file-switcher__item<?= $switcherIsCommand ? ' is-current' : '' ?>"
        <?= $switcherIsCommand ? ' aria-current="page"' : '' ?>
    >Commandement</a>
</nav>
