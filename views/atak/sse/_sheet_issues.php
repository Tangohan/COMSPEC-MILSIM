<?php
declare(strict_types=1);
/**
 * Liste « Documents émis » d'une fiche : référence, modèle, langue, niveau de diffusion (pastille de couleur), passages masqués
 * (pastille et fenêtre d'explication : catégories, niveau requis, marche à suivre), date, émetteur, re-téléchargement, texte
 * réellement imprimé et réémission à un niveau supérieur (fenêtre : niveau, aperçu des catégories qui deviennent lisibles, motif).
 * Un document classé au-dessus de l'habilitation du lecteur reste listé, sans téléchargement ni texte.
 *
 * Jamais la valeur masquée : seulement des catégories, des niveaux et des nombres.
 *
 * @var list<array<string, mixed>> $issues  (ou $sheetMenu['issues'])
 * @var string $sheetLevel habilitation du lecteur
 */
use App\Core\Csrf;
use App\Services\Sse\SseRedactionService;
use App\Services\Sse\SseSheetAccess;
use App\Services\Sse\SseSheetIssueView;
use App\Support\SseRedactionExplain as X;
use App\Support\SseSheetCatalog as D;
use App\Support\SseUsClassification;

$h = $h ?? static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$issuesList = is_array($issues ?? null) ? $issues : (array) ($sheetMenu['issues'] ?? []);
$ilang = ($lang ?? $sheetMenu['lang'] ?? 'fr') === 'en' ? 'en' : 'fr';
$ilevel = (string) ($sheetLevel ?? $sheetMenu['level'] ?? 'interne');
try {
    $iaccess = isset($rights) && $rights instanceof SseSheetAccess ? $rights : SseSheetAccess::fromSession();
    $issuesList = SseSheetIssueView::rows($issuesList, $iaccess);
} catch (\Throwable) {
    $iaccess = null;
}
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-sheets.css')) . '">';
$sseExtraScripts = ($sseExtraScripts ?? '') . '<script src="' . $h(asset_url('assets/js/sse-sheet-redaction.js')) . '" defer></script>';
$ilabels = static function (string $model) use ($ilang): array {
    $out = [];
    foreach (D::texts($model) as [$key, , $labels]) {
        $out[$key] = $labels[$ilang === 'en' ? 1 : 0];
    }

    return $out;
};
$idt = static function (string $v): string {
    $ts = strtotime($v . ' UTC');

    return $ts === false ? '—' : gmdate('d/m/Y H:i', $ts);
};
$lvlStyle = static fn (array $i): string => '--lv-bg:rgb(' . implode(',', array_map('intval', (array) ($i['rgb'] ?? [80, 40, 120]))) . ');--lv-ink:' . ($i['ink'] ?? '#ffffff');
?>
<section class="panel shx-issues"<?= empty($issuesEmbedded) ? ' id="documents-emis"' : '' ?> style="margin-top:12px">
    <div class="panel-header"><div class="panel-title">Documents émis</div><div class="panel-meta"><?= count($issuesList) ?> document(s)</div></div>
    <div class="panel-body">
        <p class="shx-issues__intro">Chaque document garde le texte réellement imprimé. Les passages masqués dépendent du niveau de diffusion choisi à l’émission.</p>
        <?php if ($issuesList === []): ?>
            <p class="muted">Aucun document n’a encore été émis pour cette fiche. L’émission d’un PDF ne modifie jamais la fiche : elle est seulement notée ici et dans l’historique.</p>
        <?php else: ?>
            <div class="table-wrap"><table>
                <thead><tr><th>Référence</th><th>Modèle</th><th>Diffusion</th><th>Passages masqués</th><th>Émis le</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($issuesList as $i): ?>
                    <?php
                    $open = array_key_exists('open', $i) ? (bool) $i['open'] : SseRedactionService::levelRank((string) $i['classification']) <= SseRedactionService::levelRank($ilevel);
                    $x = is_array($i['x'] ?? null) ? $i['x'] : ['total' => 0, 'by' => []];
                    $total = (int) $x['total'];
                    $lvl = (string) ($i['level'] ?? SseUsClassification::normalize((string) $i['classification']));
                    $lvlLabel = SseUsClassification::label($lvl, $ilang);
                    $abbr = SseUsClassification::abbr($lvl);
                    $dlgExplain = 'shx-explain-' . (int) $i['id'];
                    $dlgReissue = 'shx-reissue-' . (int) $i['id'];
                    ?>
                    <tr>
                        <td class="shx-c-nowrap" data-label="Référence" translate="no">
                            <?= $h($i['ref']) ?>
                            <?php if (($i['reissue_of'] ?? '') !== ''): ?><span class="shx-link muted">Réémission de <span translate="no"><?= $h($i['reissue_of']) ?></span></span><?php endif; ?>
                            <?php if (($i['replaced_by'] ?? '') !== ''): ?><span class="shx-link muted">Réémis sous <span translate="no"><?= $h($i['replaced_by']) ?></span></span><?php endif; ?>
                        </td>
                        <td data-label="Modèle"><?= $h(D::label((string) $i['model'], $ilang)) ?> <span class="muted">· <?= $h(strtoupper((string) $i['lang'])) ?></span></td>
                        <td data-label="Diffusion"><span class="shx-lvl" style="<?= $h($lvlStyle($i)) ?>" title="<?= $h($lvlLabel) ?>"><?= $h($lvlLabel) ?></span></td>
                        <td class="shx-c-nowrap" data-label="Passages masqués">
                            <?php if (!$open): ?>
                                <span class="muted">—</span>
                            <?php elseif ($total < 1): ?>
                                <span class="muted">Aucun passage masqué</span>
                            <?php else: ?>
                                <button type="button" class="shx-mask" data-shx-open="<?= $h($dlgExplain) ?>" aria-haspopup="dialog"
                                        title="Ce document est diffusé au niveau <?= $h($abbr) ?>. Le caviardage dépend du niveau de diffusion choisi à l’émission, pas de la classification de la personne. Touchez pour le détail.">
                                    <?= $total ?> passage(s) masqué(s)
                                </button>
                            <?php endif; ?>
                        </td>
                        <td class="shx-c-nowrap" data-label="Émis le"><?= $h($idt((string) $i['issued_at'])) ?><span class="shx-link muted" translate="no"><?= $h($i['issued_by_label'] !== '' ? $i['issued_by_label'] : '—') ?></span></td>
                        <td class="shx-actions-cell">
                            <?php if ($open): ?>
                                <a class="btn shx__btn shx__btn--small" href="<?= $h(url('atak/sse/identites/emissions/' . (int) $i['id'] . '/pdf')) ?>">Télécharger</a>
                                <?php if ($total > 0 && !empty($i['can_reissue'])): ?>
                                    <button type="button" class="btn shx__btn shx__btn--small" data-shx-open="<?= $h($dlgReissue) ?>" aria-haspopup="dialog">Réémettre à un niveau supérieur</button>
                                <?php elseif ($total > 0 && ($i['needed_abbr'] ?? '') !== ''): ?>
                                    <span class="shx-need">Niveau requis : <?= $h($i['needed_abbr']) ?> — demander à un responsable habilité</span>
                                <?php endif; ?>
                                <?php if ($i['texts'] !== []): ?>
                                    <details class="shx-printed">
                                        <summary>Texte imprimé</summary>
                                        <?php foreach ($i['texts'] as $k => $text): ?>
                                            <?php if (trim((string) $text) === '') { continue; } ?>
                                            <p class="shx-printed__k"><?= $h($ilabels((string) $i['model'])[$k] ?? $k) ?></p>
                                            <p class="shx-printed__t" translate="no"><?= nl2br($h(\App\Support\SseSheetText::plain((string) $text))) ?></p>
                                        <?php endforeach; ?>
                                    </details>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="muted" title="Ce document est classé au-dessus de votre habilitation.">Classé au-dessus de votre habilitation</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>

            <?php foreach ($issuesList as $i): ?>
                <?php
                $x = is_array($i['x'] ?? null) ? $i['x'] : ['total' => 0, 'by' => []];
                if (empty($i['open']) || (int) $x['total'] < 1) { continue; }
                $lvl = (string) $i['level'];
                $abbr = SseUsClassification::abbr($lvl);
                $letters = X::letters(array_map('strval', array_keys($x['by'])));
                $dlgExplain = 'shx-explain-' . (int) $i['id'];
                $dlgReissue = 'shx-reissue-' . (int) $i['id'];
                $above = (array) ($i['levels_above'] ?? []);
                $needed = (string) ($i['needed'] ?? '');
                $pick = in_array($needed, $above, true) ? $needed : (string) end($above);
                ?>
                <dialog class="shx-dialog" id="<?= $h($dlgExplain) ?>" aria-labelledby="<?= $h($dlgExplain) ?>-t">
                    <div class="shx-dialog__box">
                        <h3 id="<?= $h($dlgExplain) ?>-t" class="shx-dialog__title">Pourquoi ces passages sont masqués</h3>
                        <p class="shx-dialog__sub"><span translate="no"><?= $h($i['ref']) ?></span> · <?= (int) $x['total'] ?> passage(s) masqué(s)</p>
                        <p>Ce document est diffusé au niveau <strong><?= $h($abbr) ?></strong>. Une donnée est masquée quand sa catégorie exige un niveau supérieur à celui de la diffusion.</p>
                        <ul class="shx-cats">
                            <?php foreach ($x['by'] as $cat => $n): ?>
                                <?php $cat = (string) $cat; ?>
                                <li>
                                    <span class="shx-cats__letter" aria-hidden="true"><?= $h($letters[$cat] ?? '?') ?></span>
                                    <span class="shx-cats__body">
                                        <strong><?= $h(X::label($cat, $ilang)) ?></strong>
                                        <?php if ($cat !== ''): ?><span class="muted"><?= $h(X::help($cat, $ilang)) ?></span><?php endif; ?>
                                    </span>
                                    <span class="shx-cats__need"><?php if ($cat !== ''): ?>Requiert le niveau <strong><?= $h(X::need($cat)) ?></strong><?php else: ?>Niveau non conservé<?php endif; ?> · <?= (int) $n ?> passage(s)</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <p>Le caviardage dépend du niveau de diffusion choisi à l’émission, pas de la classification de la personne. Dans le PDF, chaque barre noire porte la lettre de sa catégorie.</p>
                        <p class="muted">La valeur masquée n’est jamais conservée dans cette explication.</p>
                        <div class="shx-dialog__foot">
                            <?php if (!empty($i['can_reissue'])): ?>
                                <button type="button" class="btn shx__btn shx__btn--primary" data-shx-swap="<?= $h($dlgReissue) ?>">Réémettre à un niveau supérieur</button>
                            <?php elseif (($i['needed_abbr'] ?? '') !== ''): ?>
                                <span class="shx-need">Niveau requis : <?= $h($i['needed_abbr']) ?> — demander à un responsable habilité</span>
                            <?php endif; ?>
                            <button type="button" class="btn shx__btn" data-shx-close>Fermer</button>
                        </div>
                    </div>
                </dialog>
                <?php if (!empty($i['can_reissue'])): ?>
                    <dialog class="shx-dialog" id="<?= $h($dlgReissue) ?>" aria-labelledby="<?= $h($dlgReissue) ?>-t">
                        <form class="shx-dialog__box" method="post" action="<?= $h(url('atak/sse/identites/emissions/' . (int) $i['id'] . '/reemettre')) ?>" data-shx-reissue>
                            <?= Csrf::field() ?>
                            <h3 id="<?= $h($dlgReissue) ?>-t" class="shx-dialog__title">Réémettre à un niveau supérieur</h3>
                            <p class="shx-dialog__sub">Un nouveau document est émis, avec une nouvelle référence liée à <span translate="no"><?= $h($i['ref']) ?></span>. L’ancien document est conservé tel quel.</p>
                            <label for="<?= $h($dlgReissue) ?>-lvl">Nouveau niveau de diffusion</label>
                            <select id="<?= $h($dlgReissue) ?>-lvl" name="classification" data-shx-level>
                                <?php foreach ($above as $l): ?>
                                    <option value="<?= $h($l) ?>" data-rank="<?= (int) SseRedactionService::levelRank((string) $l) ?>" <?= $l === $pick ? 'selected' : '' ?>><?= $h(SseUsClassification::label((string) $l, $ilang)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="shx-dialog__hint">Limité à votre habilitation.</p>
                            <p class="shx-dialog__k">Ce qui deviendra lisible</p>
                            <ul class="shx-cats shx-cats--preview" data-shx-preview>
                                <?php foreach ($x['by'] as $cat => $n): ?>
                                    <?php $cat = (string) $cat; $rank = $cat !== '' ? SseRedactionService::levelRank(X::needCode($cat)) : 99; ?>
                                    <li data-rank="<?= (int) $rank ?>">
                                        <span class="shx-cats__letter" aria-hidden="true"><?= $h($letters[$cat] ?? '?') ?></span>
                                        <span class="shx-cats__body"><strong><?= $h(X::label($cat, $ilang)) ?></strong> <span class="muted"><?= (int) $n ?> passage(s)</span></span>
                                        <span class="shx-cats__state"><span class="shx-on">Lisible</span><span class="shx-off"><?php if ($cat !== ''): ?>Reste masqué (requiert <?= $h(X::need($cat)) ?>)<?php else: ?>Reste masqué<?php endif; ?></span></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <label for="<?= $h($dlgReissue) ?>-why">Motif de la réémission (obligatoire)</label>
                            <textarea id="<?= $h($dlgReissue) ?>-why" name="reason" rows="3" required minlength="5" maxlength="300"></textarea>
                            <p class="shx-dialog__hint">Les textes saisis à la main sont repris ; ceux qui contenaient une barre reviennent à la trame du modèle. Le motif est inscrit au journal d’audit.</p>
                            <div class="shx-dialog__foot">
                                <button type="submit" class="btn shx__btn shx__btn--primary">Réémettre</button>
                                <button type="button" class="btn shx__btn" data-shx-close>Annuler</button>
                            </div>
                        </form>
                    </dialog>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>
