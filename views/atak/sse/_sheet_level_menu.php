<?php
declare(strict_types=1);
/**
 * Bouton « Télécharger » d'un document émis : ouvre un petit menu (fenêtre) pour choisir le niveau de diffusion du PDF, du plus bas
 * jusqu'à l'habilitation du lecteur, avec le récapitulatif de ce qui sera masqué à ce niveau avant de télécharger. Sans JavaScript, le
 * lien télécharge le PDF au niveau d'émission. Un niveau plus haut que l'émission n'est offert que si le document contient déjà les
 * données ; sinon il renvoie à la réémission. Jamais une valeur masquée : des catégories, des niveaux et des nombres.
 *
 * @var array<string, mixed> $lmRow    ligne de SseSheetIssueView::rows (id, ref, level, level_menu)
 * @var string               $lmClass  classes du bouton
 * @var string               $lmLabel  texte du bouton
 */
use App\Support\SseRedactionExplain as X;
use App\Support\SseUsClassification;

$h = $h ?? static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$lmLang = (($sheetMenu['lang'] ?? $lang ?? 'fr') === 'en') ? 'en' : 'fr';
$lmOptions = array_values((array) ($lmRow['level_menu'] ?? []));
$lmId = (int) ($lmRow['id'] ?? 0);
$lmPdf = url('atak/sse/identites/emissions/' . $lmId . '/pdf');
$lmLabel = $lmLabel ?? 'Télécharger';
$lmClass = $lmClass ?? 'btn shx__btn shx__btn--small';
if (count($lmOptions) < 2) {
    echo '<a class="' . $h($lmClass) . '" href="' . $h($lmPdf) . '">' . $h($lmLabel) . '</a>';

    return;
}
$lmDlg = 'shx-dl-' . $lmId;
$lmOrigin = (string) ($lmRow['level'] ?? '');
$lmLetters = X::letters(array_keys(array_reduce($lmOptions, static fn (array $c, array $o): array => $c + $o['by'], [])));
?>
<a class="<?= $h($lmClass) ?>" href="<?= $h($lmPdf) ?>" data-shx-open="<?= $h($lmDlg) ?>" aria-haspopup="dialog" title="Choisir le niveau de diffusion du PDF">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="vertical-align:-3px;margin-right:6px"><path d="M12 4v11M7 11l5 5 5-5M5 20h14"/></svg><?= $h($lmLabel) ?>
</a>
<dialog class="shx-dialog" id="<?= $h($lmDlg) ?>" aria-labelledby="<?= $h($lmDlg) ?>-t">
    <form class="shx-dialog__box" method="get" action="<?= $h($lmPdf) ?>" data-shx-dl>
        <h3 id="<?= $h($lmDlg) ?>-t" class="shx-dialog__title">Télécharger à un niveau de diffusion</h3>
        <p class="shx-dialog__sub"><span translate="no"><?= $h($lmRow['ref'] ?? '') ?></span> · émis au niveau <strong translate="no"><?= $h(SseUsClassification::abbr($lmOrigin)) ?></strong>. Le PDF est une copie : aucun nouveau document n’est émis et l’original reste intact.</p>
        <label for="<?= $h($lmDlg) ?>-lvl">Niveau de diffusion du PDF</label>
        <select id="<?= $h($lmDlg) ?>-lvl" name="niveau" data-shx-dl-level>
            <?php foreach ($lmOptions as $o): ?>
                <?php
                $blocked = $o['state'] === 'reissue';
                $text = SseUsClassification::label((string) $o['level'], $lmLang) . ($o['origin'] ? ' — niveau d’émission' : '') . ($blocked ? ' — réémission requise' : '');
                ?>
                <option value="<?= $h($o['level']) ?>" data-state="<?= $h($o['state']) ?>"<?= $o['origin'] ? ' selected' : '' ?><?= $blocked ? ' disabled' : '' ?>><?= $h($text) ?></option>
            <?php endforeach; ?>
        </select>
        <p class="shx-dialog__hint">Limité à votre habilitation.</p>
        <p class="shx-dialog__k">Ce qui sera masqué à ce niveau</p>
        <?php foreach ($lmOptions as $o): ?>
            <div class="shx-dl__lvl" data-level="<?= $h($o['level']) ?>"<?= $o['origin'] ? '' : ' hidden' ?>>
                <?php if ($o['state'] === 'reissue'): ?>
                    <p class="shx-dl__sum shx-dl__sum--warn">Ce niveau rendrait lisibles des passages que ce document ne contient pas. Réémettez-le à un niveau supérieur<?= ($o['need'] ?? '') !== '' ? ' (niveau requis : ' . $h(SseUsClassification::abbr((string) $o['need'])) . ')' : '' ?>.</p>
                <?php elseif ((int) $o['total'] < 1): ?>
                    <p class="shx-dl__sum shx-dl__sum--ok"><strong>Rien ne sera masqué</strong> à ce niveau.</p>
                <?php else: ?>
                    <p class="shx-dl__sum"><strong><?= (int) $o['total'] ?> passage(s) masqué(s)</strong> à ce niveau.</p>
                    <ul class="shx-cats">
                        <?php foreach ($o['by'] as $cat => $n): ?>
                            <?php $cat = (string) $cat; ?>
                            <li>
                                <span class="shx-cats__letter" aria-hidden="true"><?= $h($lmLetters[$cat] ?? '?') ?></span>
                                <span class="shx-cats__body"><strong><?= $h(X::label($cat, $lmLang)) ?></strong> <span class="muted"><?= (int) $n ?> passage(s)</span></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <p class="shx-dialog__hint">Chaque téléchargement à un niveau est inscrit au journal d’audit.</p>
        <div class="shx-dialog__foot">
            <button type="submit" class="btn shx__btn shx__btn--primary" data-shx-dl-go>Télécharger le PDF</button>
            <button type="button" class="btn shx__btn" data-shx-close>Annuler</button>
        </div>
    </form>
</dialog>
