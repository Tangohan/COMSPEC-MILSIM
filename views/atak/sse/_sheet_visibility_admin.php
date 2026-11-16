<?php
declare(strict_types=1);
/**
 * « Gestion des fiches » › Modèles : visibilité par niveau des fiches PDF. Une ligne par modèle de fiche (et une pour tous les
 * modèles de la communauté) ; le bouton-icône ouvre la fenêtre « Visibilité par niveau » (tableau rubriques × niveaux). Le droit de
 * gestion des fiches est requis pour enregistrer ; un plancher de sécurité fixé dans le code empêche de rendre une rubrique lisible
 * sous un certain niveau. Un réglage de modèle prime sur celui de la communauté, une surcharge à l'émission prime sur les deux.
 *
 * @var list<array<string, mixed>> $sheetVis lignes : scope, label, table, custom (nombre de rubriques réglées), inherited
 * @var bool $canManageRef
 * @var bool $canStoreVis table des réglages présente
 * @var string $csrf
 * @var string $base adresse de la page (sans adresse absolue)
 */
$h = $h ?? static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$visCan = $canManageRef && ($canStoreVis ?? false);
$visIco = static fn (string $d): string => '<svg class="fnr-i" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $d . '</svg>';
$eyeSlash = $visIco('<path d="M3 12s3.6-6.5 9-6.5S21 12 21 12s-3.6 6.5-9 6.5S3 12 3 12z"/><circle cx="12" cy="12" r="2.6"/><path d="M4 20L20 4"/>');
?>
<div class="fng-panel__head" style="margin-top:28px">
    <div>
        <h2 class="fng-h2">Visibilité par niveau des fiches PDF</h2>
        <p class="fng-desc">Pour chaque rubrique d’un document (identité, lieu, biométrie, source, horodatage), le niveau de diffusion à partir duquel elle est lisible en clair. Un modèle peut avoir son réglage ; l’émetteur peut l’ajuster pour un document précis.</p>
    </div>
</div>
<?php if (!($canStoreVis ?? false)): ?>
    <p class="fng-help">Les réglages seront disponibles dès que l’étape « étiquettes partagées du bureau » aura été passée : en attendant, les niveaux livrés s’appliquent.</p>
<?php endif; ?>
<div class="fng-tablewrap">
    <table class="fng-table">
        <thead>
            <tr><th scope="col"><span>Modèle</span></th><th scope="col"><span>Réglage</span></th><?php if ($visCan): ?><th scope="col"><span class="sr-only">Actions</span></th><?php endif; ?></tr>
        </thead>
        <tbody>
            <?php foreach ((array) $sheetVis as $row): ?>
                <tr>
                    <td data-label="Modèle"><span class="fng-label"><?= $h($row['label']) ?></span><span class="fng-sub"><?= $h($row['sub']) ?></span></td>
                    <td class="fng-wide" data-label="Réglage">
                        <?php if ((int) $row['custom'] > 0): ?>
                            <span class="fng-pills"><?php foreach ((array) $row['custom_rows'] as $c): ?><span class="fng-pill"><?= $h($c) ?></span><?php endforeach; ?></span>
                        <?php else: ?>
                            <span class="fng-sub"><?= $h($row['scope'] === '*' ? 'Niveaux livrés' : 'Comme le réglage de la communauté') ?></span>
                        <?php endif; ?>
                    </td>
                    <?php if ($visCan): ?>
                        <td class="fng-rowacts-cell"><div class="fng-rowacts">
                            <button type="button" class="btn btn--ghost fng-icon" data-fnr-open="svl-dlg-<?= $h($row['dom']) ?>" aria-haspopup="dialog" title="Visibilité par niveau : <?= $h($row['label']) ?>" aria-label="Visibilité par niveau : <?= $h($row['label']) ?>"><?= $eyeSlash ?><span class="fng-icon__text">Visibilité</span></button>
                        </div></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php if ($visCan): ?>
    <?php foreach ((array) $sheetVis as $row): ?>
        <dialog class="svl-admin fnr-dialog fnr-dialog--wide" id="svl-dlg-<?= $h($row['dom']) ?>" aria-labelledby="svl-dlg-<?= $h($row['dom']) ?>-t">
            <form method="post" action="<?= $h(url($base . '/visibilite')) ?>" class="fnr-dialog__card">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <input type="hidden" name="scope" value="<?= $h($row['scope']) ?>">
                <header class="fnr-dialog__head">
                    <div>
                        <h2 id="svl-dlg-<?= $h($row['dom']) ?>-t">Visibilité par niveau : <?= $h($row['label']) ?></h2>
                        <p class="fnr-desc"><?= $h($row['scope'] === '*' ? 'Réglage par défaut de la communauté, repris par tous les modèles qui n’ont pas le leur.' : 'Réglage de ce modèle ; il prime sur celui de la communauté. « Rétablir » ramène au réglage de la communauté.') ?></p>
                    </div>
                    <button type="button" class="fnr-dialog__close" data-fnr-close aria-label="Fermer la fenêtre"><?= $visIco('<path d="M6 6l12 12M18 6L6 18"/>') ?></button>
                </header>
                <div class="fnr-dialog__body">
                    <?php $vt = $row['table']; $vtName = 'visibility'; $vtId = 'svl-' . $row['dom']; $vtEdit = true; require __DIR__ . '/_sheet_visibility_table.php'; ?>
                </div>
                <footer class="fnr-dialog__foot">
                    <button type="button" class="btn btn--ghost" data-fnr-close>Annuler</button>
                    <button type="submit" class="btn">Enregistrer</button>
                </footer>
            </form>
        </dialog>
    <?php endforeach; ?>
<?php endif; ?>
