<?php
declare(strict_types=1);

/**
 * Panneau « Personnaliser l'accueil » : blocs affichés, ordre, largeur, nouveaux tableaux.
 * Formulaire classique (boutons monter / descendre qui rechargent) ; back-office-home.js ajoute le glisser-déposer
 * et déplace sans recharger avant l'enregistrement.
 *
 * @var array<string, mixed> $home
 * @var callable(string): string $pilH
 */

$hcItems = is_array($home['items'] ?? null) ? $home['items'] : [];
$hcCatalog = is_array($home['catalog'] ?? null) ? $home['catalog'] : [];
$hcReadable = is_array($home['readable'] ?? null) ? $home['readable'] : [];
$hcSheetTitles = [];
foreach ($hcReadable as $hcS) {
    $hcSheetTitles[(int) $hcS['id']] = $hcS;
}
// Tableaux lisibles pas encore dans la disposition : proposés masqués, à cocher.
$hcInLayout = array_column($hcItems, 'id');
foreach ($hcReadable as $hcS) {
    if (!in_array('sheet:' . $hcS['id'], $hcInLayout, true)) {
        $hcItems[] = ['id' => 'sheet:' . $hcS['id'], 'hidden' => true, 'size' => 'full'];
    }
}
$hcSizes = ['normal' => 'Étroit', 'wide' => 'Large', 'full' => 'Pleine largeur'];
$hcN = count($hcItems);
?>
<section class="pil-card home-cust" id="personnaliser" aria-labelledby="home-cust-title">
    <div class="pil-card__h">
        <div>
            <p class="pil-kicker">Accueil</p>
            <h2 class="pil-card__title" id="home-cust-title">Personnaliser le tableau de bord</h2>
        </div>
        <a class="pil-link" href="<?= $pilH(url('back-office')) ?>">Fermer</a>
    </div>
    <p class="home-cust__lead">Cochez les blocs à afficher, rangez-les (glisser-déposer ou flèches) et choisissez leur largeur. Votre disposition ne change que pour vous, dans cette communauté.</p>

    <form method="post" action="<?= $pilH(url('back-office/accueil/disposition')) ?>" data-home-form>
        <?= \App\Core\Csrf::field() ?>
        <?php /* Entrée au clavier : enregistre, au lieu de déclencher le premier bouton « monter ». */ ?>
        <button type="submit" class="ath-sr-only" tabindex="-1" aria-hidden="true">Enregistrer</button>
        <ol class="home-cust__list" data-home-list>
            <?php foreach ($hcItems as $hcI => $hcItem): ?>
            <?php
            $hcId = (string) $hcItem['id'];
            $hcSid = \App\Services\BackOffice\Home\HomeLayout::sheetId($hcId);
            if ($hcSid > 0) {
                $hcMeta = $hcSheetTitles[$hcSid] ?? null;
                if ($hcMeta === null) {
                    continue;
                }
                $hcLabel = 'Tableau : ' . $hcMeta['title'];
                $hcHint = $hcMeta['mine'] ? ($hcMeta['shared'] ? 'Votre tableau, partagé avec la communauté.' : 'Votre tableau.') : 'Partagé par un gestionnaire, en lecture seule.';
            } else {
                $hcLabel = (string) ($hcCatalog[$hcId]['label'] ?? $hcId);
                $hcHint = (string) ($hcCatalog[$hcId]['hint'] ?? '');
            }
            $hcDom = 'hc-' . preg_replace('/[^a-z0-9]/', '-', $hcId);
            ?>
            <li class="home-cust__it" data-home-item draggable="true">
                <input type="hidden" name="order[]" value="<?= $pilH($hcId) ?>">
                <span class="home-cust__grip" aria-hidden="true">⋮⋮</span>
                <label class="home-cust__check" for="<?= $pilH($hcDom) ?>">
                    <input type="checkbox" id="<?= $pilH($hcDom) ?>" name="visible[]" value="<?= $pilH($hcId) ?>"<?= empty($hcItem['hidden']) ? ' checked' : '' ?>>
                    <span><b><?= $pilH($hcLabel) ?></b><?php if ($hcHint !== ''): ?><small><?= $pilH($hcHint) ?></small><?php endif; ?></span>
                </label>
                <label class="home-cust__size">
                    <span class="ath-sr-only"><?= $pilH('Largeur de ' . $hcLabel) ?></span>
                    <select name="size[<?= $pilH($hcId) ?>]">
                        <?php foreach ($hcSizes as $hcK => $hcV): ?>
                        <option value="<?= $hcK ?>"<?= ($hcItem['size'] ?? '') === $hcK ? ' selected' : '' ?>><?= $pilH($hcV) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <span class="home-cust__moves">
                    <button type="submit" name="move" value="<?= $pilH('up|' . $hcId) ?>" class="pil-btn pil-btn--sm" data-home-move="up" aria-label="<?= $pilH('Monter « ' . $hcLabel . ' »') ?>"<?= $hcI === 0 ? ' disabled' : '' ?>>↑</button>
                    <button type="submit" name="move" value="<?= $pilH('down|' . $hcId) ?>" class="pil-btn pil-btn--sm" data-home-move="down" aria-label="<?= $pilH('Descendre « ' . $hcLabel . ' »') ?>"<?= $hcI === $hcN - 1 ? ' disabled' : '' ?>>↓</button>
                </span>
            </li>
            <?php endforeach; ?>
        </ol>
        <div class="home-cust__acts">
            <button type="submit" class="pil-btn pil-btn--accent">Enregistrer</button>
            <button type="submit" name="stay" value="1" class="pil-btn">Enregistrer et continuer</button>
            <button type="submit" name="reset" value="1" class="pil-btn" data-sheet-confirm="Revenir à la disposition par défaut de votre rôle ?">Disposition par défaut</button>
        </div>
    </form>

    <form method="post" action="<?= $pilH(url('back-office/tableaux')) ?>" class="home-cust__new">
        <?= \App\Core\Csrf::field() ?>
        <label for="home-new-sheet"><b>Nouveau tableau</b> <small>Une petite grille à remplir : en-têtes, tri, filtre, formules, import et export CSV.</small></label>
        <div class="home-cust__row">
            <input id="home-new-sheet" name="title" maxlength="80" placeholder="Ex. : Matériel à commander" required>
            <button type="submit" class="pil-btn pil-btn--accent">Créer le tableau</button>
        </div>
    </form>
</section>
