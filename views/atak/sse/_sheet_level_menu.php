<?php
declare(strict_types=1);
/**
 * Bouton « Télécharger » d'un document émis : ouvre un petit menu (fenêtre) pour choisir le niveau de diffusion du PDF, du plus bas
 * jusqu'à l'habilitation du lecteur, avec le récapitulatif de ce qui sera masqué à ce niveau avant de télécharger. Sans JavaScript, le
 * lien télécharge le PDF au niveau d'émission. Un niveau plus haut que l'émission n'est offert que si le document contient déjà les
 * données ; sinon, avec l'habilitation voulue, le choix de ce niveau ouvre dans la même fenêtre le panneau « Réémettre à ce niveau »
 * (niveau, aperçu de ce qui deviendra lisible, motif) qui poste vers la réémission existante puis télécharge la nouvelle référence ;
 * sans l'habilitation, la mention du niveau requis. Jamais une valeur masquée : des catégories, des niveaux et des nombres.
 *
 * @var array<string, mixed> $lmRow    ligne de SseSheetIssueView::rows (id, ref, level, level_menu, can_reissue, levels_above)
 * @var string               $lmClass  classes du bouton
 * @var string               $lmLabel  texte du bouton
 */
use App\Core\Csrf;
use App\Services\Sse\SseRedactionService;
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
$lmCanRei = !empty($lmRow['can_reissue']);
$lmAbove = array_map('strval', (array) ($lmRow['levels_above'] ?? []));
$lmReq = is_array($lmRow['req'] ?? null) ? $lmRow['req'] : null;
// Niveaux qui demandent une réémission ET que l'opérateur peut réémettre (mêmes conditions que la route de réémission).
$lmReiLevels = [];
foreach ($lmOptions as $o) {
    if ($o['state'] === 'reissue' && $lmCanRei && in_array((string) $o['level'], $lmAbove, true)) {
        $lmReiLevels[(string) $o['level']] = $o;
    }
}
$lmLetters = X::letters(array_keys(array_reduce($lmOptions, static fn (array $c, array $o): array => $c + $o['by'], [])));
?>
<a class="<?= $h($lmClass) ?>" href="<?= $h($lmPdf) ?>" data-shx-open="<?= $h($lmDlg) ?>" aria-haspopup="dialog" title="Choisir le niveau de diffusion du PDF">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="vertical-align:-3px;margin-right:6px"><path d="M12 4v11M7 11l5 5 5-5M5 20h14"/></svg><?= $h($lmLabel) ?>
</a>
<dialog class="shx-dialog shx-dialog--dl" id="<?= $h($lmDlg) ?>" aria-labelledby="<?= $h($lmDlg) ?>-t">
    <form class="shx-dialog__box" method="get" action="<?= $h($lmPdf) ?>" data-shx-dl>
        <h3 id="<?= $h($lmDlg) ?>-t" class="shx-dialog__title">Télécharger à un niveau de diffusion</h3>
        <p class="shx-dialog__sub"><span translate="no"><?= $h($lmRow['ref'] ?? '') ?></span> · émis au niveau <strong translate="no"><?= $h(SseUsClassification::abbr($lmOrigin)) ?></strong>. Le PDF est une copie : aucun nouveau document n’est émis et l’original reste intact.</p>
        <label for="<?= $h($lmDlg) ?>-lvl">Niveau de diffusion du PDF</label>
        <select id="<?= $h($lmDlg) ?>-lvl" name="niveau" data-shx-dl-level>
            <?php foreach ($lmOptions as $o): ?>
                <?php
                $canRei = isset($lmReiLevels[(string) $o['level']]);
                $blocked = $o['state'] === 'reissue' && !$canRei;
                $text = SseUsClassification::label((string) $o['level'], $lmLang) . ($o['origin'] ? ' — niveau d’émission' : '') . ($o['state'] === 'reissue' ? ' — réémission requise' : '');
                ?>
                <option value="<?= $h($o['level']) ?>" data-state="<?= $h($o['state']) ?>"<?= $canRei ? ' data-reissue="1"' : '' ?><?= $o['origin'] ? ' selected' : '' ?><?= $blocked ? ' disabled' : '' ?>><?= $h($text) ?></option>
            <?php endforeach; ?>
        </select>
        <p class="shx-dialog__hint">Limité à votre habilitation.</p>
        <p class="shx-dialog__k">Ce qui sera masqué à ce niveau</p>
        <?php foreach ($lmOptions as $o): ?>
            <div class="shx-dl__lvl" data-level="<?= $h($o['level']) ?>"<?= $o['origin'] ? '' : ' hidden' ?>>
                <?php if ($o['state'] === 'reissue' && isset($lmReiLevels[(string) $o['level']])): ?>
                    <p class="shx-dl__sum shx-dl__sum--warn">Ce niveau rendrait lisibles des passages que ce document ne contient pas : il faut le réémettre à ce niveau. Un nouveau document est émis, l’ancien est conservé.</p>
                    <button type="button" class="btn shx__btn shx__btn--primary" data-shx-rei-open>Réémettre à ce niveau</button>
                <?php elseif ($o['state'] === 'reissue'): ?>
                    <?php $needAbbr = ($o['need'] ?? '') !== '' ? SseUsClassification::abbr((string) $o['need']) : (string) ($lmRow['needed_abbr'] ?? ''); ?>
                    <p class="shx-dl__sum shx-dl__sum--warn">Ce niveau rendrait lisibles des passages que ce document ne contient pas : il doit être réémis à un niveau supérieur.</p>
                    <?php if ($needAbbr !== ''): ?><span class="shx-need">Niveau requis : <?= $h($needAbbr) ?> — demander à un responsable habilité</span><?php endif; ?>
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
    <?php if ($lmReiLevels !== []): ?>
        <form class="shx-dialog__box shx-rei" method="post" action="<?= $h(url('atak/sse/identites/emissions/' . $lmId . '/reemettre')) ?>" aria-labelledby="<?= $h($lmDlg) ?>-rt" data-shx-rei hidden>
            <?= Csrf::field() ?>
            <input type="hidden" name="classification" value="" data-shx-rei-level>
            <h3 id="<?= $h($lmDlg) ?>-rt" class="shx-dialog__title" tabindex="-1">Réémettre à ce niveau</h3>
            <p class="shx-dialog__sub"><span translate="no"><?= $h($lmRow['ref'] ?? '') ?></span> · niveau <strong translate="no" data-shx-rei-name></strong>. Un nouveau document est émis, avec une nouvelle référence liée à celle-ci ; l’ancien reste tel quel. Le PDF du nouveau document est ensuite téléchargé.</p>
            <p class="shx-dialog__k">Ce qui deviendra lisible</p>
            <?php foreach ($lmReiLevels as $lvl => $o): ?>
                <ul class="shx-cats shx-cats--preview shx-rei__lvl" data-level="<?= $h($lvl) ?>" data-name="<?= $h(SseUsClassification::label((string) $lvl, $lmLang)) ?>" hidden>
                    <?php foreach ($o['by'] as $cat => $n): ?>
                        <?php $cat = (string) $cat; $readable = $cat !== '' && SseRedactionService::levelRank(X::needCode($cat, $lmReq)) <= SseRedactionService::levelRank((string) $lvl); ?>
                        <li class="<?= $readable ? 'is-on' : 'is-off' ?>">
                            <span class="shx-cats__letter" aria-hidden="true"><?= $h($lmLetters[$cat] ?? '?') ?></span>
                            <span class="shx-cats__body"><strong><?= $h(X::label($cat, $lmLang)) ?></strong> <span class="muted"><?= (int) $n ?> passage(s)</span></span>
                            <span class="shx-cats__state"><span class="shx-on">Deviendra lisible</span><span class="shx-off"><?php if ($cat !== ''): ?>Reste masqué (requiert <?= $h(X::need($cat)) ?>)<?php else: ?>Reste masqué<?php endif; ?></span></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
            <label for="<?= $h($lmDlg) ?>-why">Motif de la réémission (obligatoire)</label>
            <textarea id="<?= $h($lmDlg) ?>-why" name="reason" rows="3" required minlength="5" maxlength="300" aria-describedby="<?= $h($lmDlg) ?>-msg"></textarea>
            <p class="shx-dialog__hint">Les textes saisis à la main sont repris ; ceux qui contenaient une barre reviennent à la trame du modèle. Le motif est inscrit au journal d’audit.</p>
            <p class="shx-rei__msg" id="<?= $h($lmDlg) ?>-msg" role="status" aria-live="polite" data-shx-rei-msg></p>
            <span hidden data-shx-t="busy">Réémission en cours…</span>
            <span hidden data-shx-t="done">Réémis sous la référence</span>
            <span hidden data-shx-t="download">Le PDF du nouveau document se télécharge.</span>
            <span hidden data-shx-t="net">La réponse n’a pas pu être lue. Rechargez la page pour vérifier la liste des documents émis avant de recommencer.</span>
            <span hidden data-shx-t="close">Fermer</span>
            <div class="shx-dialog__foot">
                <button type="submit" class="btn shx__btn shx__btn--primary" data-shx-rei-go>Réémettre et télécharger</button>
                <button type="button" class="btn shx__btn" data-shx-rei-cancel>Annuler</button>
            </div>
        </form>
    <?php endif; ?>
</dialog>
