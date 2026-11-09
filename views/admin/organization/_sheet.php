<?php
declare(strict_types=1);

/**
 * Un tableau de l'accueil (bloc ou pleine page).
 *
 * @var array{id: int, title: string, data: array{cols: list<array{name: string}>, rows: list<list<string>>}, computed: list<list<string>>, shared: bool, version: int, editable: bool, can_share: bool} $sheetView
 * @var bool $sheetCompact bloc de l'accueil (true) ou page du tableau (false)
 *
 * Sans JavaScript : les cases forment un formulaire « Enregistrer le tableau ». Avec back-office-sheet.js : chaque case
 * s'enregistre à la sortie, sans rechargement ; tri et filtre se font à l'affichage.
 */

$shH = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$sh = $sheetView;
$shCompact = !empty($sheetCompact);
$shId = (int) $sh['id'];
$shCols = $sh['data']['cols'];
$shRows = $sh['data']['rows'];
$shEdit = !empty($sh['editable']);
$shReturn = $shCompact ? 'accueil' : 'page';
$shBase = 'back-office/tableaux/' . $shId;
$shStruct = 'bsh-struct-' . $shId;
$shLetter = static fn (int $c): string => \App\Services\BackOffice\Home\SheetData::letter($c);
?>
<div class="bsh<?= $shCompact ? ' bsh--compact' : '' ?>" id="tableau-<?= $shId ?>" data-sheet data-id="<?= $shId ?>" data-version="<?= (int) $sh['version'] ?>"
     data-editable="<?= $shEdit ? '1' : '0' ?>" data-cell-url="<?= $shH(url($shBase . '/cellule')) ?>" data-structure-url="<?= $shH(url($shBase . '/structure')) ?>">
    <div class="bsh__bar">
        <div class="bsh__title">
            <?php if ($shCompact): ?>
            <a href="<?= $shH(url($shBase)) ?>"><?= $shH((string) $sh['title']) ?></a>
            <?php else: ?>
            <b><?= $shH((string) $sh['title']) ?></b>
            <?php endif; ?>
            <?php if (!empty($sh['shared'])): ?><span class="pil-tag pil-tone--primary">Partagé</span><?php endif; ?>
            <?php if (!$shEdit): ?><span class="pil-tag">Lecture seule</span><?php endif; ?>
        </div>
        <label class="bsh__filter">
            <span class="ath-sr-only">Filtrer les lignes</span>
            <input type="search" placeholder="Filtrer…" data-sheet-filter autocomplete="off">
        </label>
        <div class="bsh__tools">
            <?php if ($shEdit): ?>
            <button type="submit" form="<?= $shStruct ?>" name="op" value="add_row" class="pil-btn pil-btn--sm">+ Ligne</button>
            <button type="submit" form="<?= $shStruct ?>" name="op" value="add_col" class="pil-btn pil-btn--sm"<?= count($shCols) >= \App\Services\BackOffice\Home\SheetData::MAX_COLS ? ' disabled' : '' ?>>+ Colonne</button>
            <?php endif; ?>
            <a class="pil-btn pil-btn--sm" href="<?= $shH(url($shBase . '/export.csv')) ?>">Exporter CSV</a>
            <?php if ($shCompact): ?>
            <a class="pil-btn pil-btn--sm" href="<?= $shH(url($shBase)) ?>">Ouvrir</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($shEdit): ?>
    <form id="<?= $shStruct ?>" method="post" action="<?= $shH(url($shBase . '/structure')) ?>" hidden>
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="version" value="<?= (int) $sh['version'] ?>">
        <input type="hidden" name="return" value="<?= $shReturn ?>">
        <input type="hidden" name="compact" value="<?= $shCompact ? '1' : '0' ?>">
    </form>
    <?php endif; ?>

    <form method="post" action="<?= $shH(url($shBase)) ?>" class="bsh__form" data-sheet-form>
        <?php if ($shEdit): ?>
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="version" value="<?= (int) $sh['version'] ?>">
        <?php endif; ?>
        <div class="bsh__scroll" tabindex="0" role="region" aria-label="<?= $shH('Tableau ' . $sh['title']) ?>">
            <table class="bsh__t" data-sheet-table>
                <thead>
                    <tr>
                        <th scope="col" class="bsh__corner"><span class="ath-sr-only">Ligne</span></th>
                        <?php foreach ($shCols as $c => $col): ?>
                        <th scope="col" data-col="<?= $c ?>">
                            <div class="bsh__head">
                                <span class="bsh__letter" aria-hidden="true"><?= $shLetter($c) ?></span>
                                <?php if ($shEdit): ?>
                                <input class="bsh__hname" name="cols[<?= $c ?>]" value="<?= $shH($col['name']) ?>" maxlength="40" aria-label="<?= $shH('En-tête de la colonne ' . $shLetter($c)) ?>" data-sheet-head="<?= $c ?>">
                                <?php else: ?>
                                <span class="bsh__hname"><?= $shH($col['name']) ?></span>
                                <?php endif; ?>
                                <button type="button" class="bsh__ico" data-sheet-sort="<?= $c ?>" aria-label="<?= $shH('Trier par ' . ($col['name'] !== '' ? $col['name'] : 'colonne ' . $shLetter($c))) ?>" title="Trier" hidden>↕</button>
                                <?php if ($shEdit && count($shCols) > 1): ?>
                                <button type="submit" form="<?= $shStruct ?>" name="op" value="del_col:<?= $c ?>" class="bsh__ico bsh__ico--rm" data-sheet-confirm="Supprimer cette colonne et son contenu ?" aria-label="<?= $shH('Supprimer la colonne ' . $shLetter($c)) ?>" title="Supprimer la colonne">×</button>
                                <?php endif; ?>
                            </div>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shRows as $r => $row): ?>
                    <tr data-row="<?= $r ?>">
                        <th scope="row" class="bsh__num">
                            <span><?= $r + 1 ?></span>
                            <?php if ($shEdit): ?>
                            <button type="submit" form="<?= $shStruct ?>" name="op" value="del_row:<?= $r ?>" class="bsh__ico bsh__ico--rm" data-sheet-confirm="Supprimer cette ligne ?" aria-label="<?= $shH('Supprimer la ligne ' . ($r + 1)) ?>" title="Supprimer la ligne">×</button>
                            <?php endif; ?>
                        </th>
                        <?php foreach ($row as $c => $raw): ?>
                        <?php $shShown = (string) ($sh['computed'][$r][$c] ?? $raw); $shFormula = str_starts_with($raw, '='); ?>
                        <td<?= $shFormula ? ' class="is-formula"' : '' ?> data-sort="<?= $shH($shShown) ?>">
                            <?php if ($shEdit): ?>
                            <input name="cells[<?= $r ?>][<?= $c ?>]" value="<?= $shH($raw) ?>" maxlength="200" data-r="<?= $r ?>" data-c="<?= $c ?>" data-raw="<?= $shH($raw) ?>" data-shown="<?= $shH($shShown) ?>"
                                   aria-label="<?= $shH('Case ' . $shLetter($c) . ($r + 1)) ?>">
                            <?php else: ?>
                            <?= $shH($shShown) ?>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($shRows === []): ?>
            <p class="pil-empty">Le tableau est vide. <?= $shEdit ? 'Ajoutez une ligne pour commencer.' : '' ?></p>
            <?php endif; ?>
        </div>
        <p class="bsh__status" data-sheet-status role="status" aria-live="polite"></p>
        <?php if ($shEdit): ?>
        <div class="bsh__nojs" data-sheet-nojs>
            <button type="submit" class="pil-btn pil-btn--accent">Enregistrer le tableau</button>
        </div>
        <?php endif; ?>
    </form>

    <?php if ($shEdit): ?>
    <details class="bsh__more">
        <summary>Formules, import, partage</summary>
        <p class="bsh__help">Formules : <code>=SOMME(B1:B10)</code>, <code>=MOYENNE(B1:B10)</code>, <code>=NB(B1:B10)</code> (aussi SUM, AVERAGE, COUNT). La ligne 1 est la première ligne sous les en-têtes. <?= \App\Services\BackOffice\Home\SheetData::MAX_COLS ?> colonnes et <?= \App\Services\BackOffice\Home\SheetData::MAX_ROWS ?> lignes au plus.</p>
        <div class="bsh__forms">
            <form method="post" action="<?= $shH(url($shBase . '/reglages')) ?>" class="bsh__mini">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="return" value="<?= $shReturn ?>">
                <label>Titre <input name="title" value="<?= $shH((string) $sh['title']) ?>" maxlength="80" required></label>
                <?php if (!empty($sh['can_share'])): ?>
                <label class="bsh__check"><input type="checkbox" name="shared" value="1"<?= !empty($sh['shared']) ? ' checked' : '' ?>> Partager en lecture avec la communauté</label>
                <?php endif; ?>
                <button type="submit" class="pil-btn pil-btn--sm">Enregistrer</button>
            </form>
            <form method="post" action="<?= $shH(url($shBase . '/import')) ?>" enctype="multipart/form-data" class="bsh__mini">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="return" value="<?= $shReturn ?>">
                <label>Importer un CSV (remplace le contenu) <input type="file" name="csv" accept=".csv,text/csv" required></label>
                <button type="submit" class="pil-btn pil-btn--sm" data-sheet-confirm="Remplacer tout le contenu du tableau par ce fichier ?">Importer</button>
            </form>
            <form method="post" action="<?= $shH(url($shBase . '/supprimer')) ?>" class="bsh__mini">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="pil-btn pil-btn--sm bsh__danger" data-sheet-confirm="Supprimer définitivement ce tableau ?">Supprimer le tableau</button>
            </form>
        </div>
    </details>
    <?php endif; ?>
</div>
