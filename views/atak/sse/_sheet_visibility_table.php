<?php
declare(strict_types=1);
/**
 * Tableau « rubriques × niveaux » de la visibilité par niveau : pour chaque rubrique, le niveau à partir duquel elle est lisible en
 * clair (un seul choix par ligne, jamais sous le plancher de sécurité de la rubrique). Les cases à droite du choix sont lisibles,
 * celles à gauche partent au noir. Sans JavaScript, les choix restent valides (boutons radio) ; le script ne fait que colorer.
 *
 * @var array{rows: list<array<string, mixed>>, levels: list<array<string, string>>} $vt  SseSheetVisibility::table()
 * @var string $vtName nom du champ (« visibility » : visibility[identite]…)
 * @var string $vtId   préfixe d'identifiants (unique dans la page)
 * @var bool   $vtEdit false : lecture seule
 */
use App\Services\Sse\SseRedactionService;

$h = $h ?? static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$vtEn = ($lang ?? 'fr') === 'en' || (($ui ?? 0) === 1);
$vtEdit = $vtEdit ?? true;
$vtLevels = (array) $vt['levels'];
$svgCheck = '<svg class="svl-ico" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';
$svgBar = '<svg class="svl-ico" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="3" y="9" width="18" height="6" rx="1" fill="currentColor"/></svg>';
$svgLock = '<svg class="svl-ico" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="5" y="11" width="14" height="9" rx="1.5"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>';
?>
<div class="svl" data-svl>
    <div class="svl-scroll">
        <table class="svl-table">
            <caption class="svl-sr">Niveau de diffusion à partir duquel chaque rubrique est lisible en clair</caption>
            <thead>
                <tr>
                    <th scope="col" class="svl-head svl-head--cat">Rubrique</th>
                    <?php foreach ($vtLevels as $lv): ?>
                        <th scope="col" class="svl-head" title="<?= $h($vtEn ? $lv['label_en'] : $lv['label_fr']) ?>"><span class="svl-abbr" translate="no"><?= $h($lv['abbr']) ?></span></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ((array) $vt['rows'] as $row): ?>
                    <?php
                    $floorRank = SseRedactionService::levelRank((string) $row['floor']);
                    $rank = SseRedactionService::levelRank((string) $row['level']);
                    ?>
                    <tr data-svl-row data-default="<?= $h($row['default']) ?>">
                        <th scope="row" class="svl-cat">
                            <span class="svl-cat__name"><?= $h($vtEn ? $row['label_en'] : $row['label_fr']) ?></span>
                            <span class="svl-cat__covers"><?= $h($vtEn ? $row['covers_en'] : $row['covers_fr']) ?></span>
                        </th>
                        <?php foreach ($vtLevels as $lv): ?>
                            <?php
                            $r = SseRedactionService::levelRank($lv['code']);
                            $locked = $r < $floorRank;
                            $sel = $lv['code'] === $row['level'];
                            $cid = $vtId . '-' . $row['key'] . '-' . $lv['code'];
                            $state = $locked ? 'locked' : ($r >= $rank ? 'on' : 'off');
                            $say = ($vtEn ? $row['label_en'] : $row['label_fr']) . ' : ' . ($vtEn ? 'readable from ' : 'lisible à partir de ') . ($vtEn ? $lv['label_en'] : $lv['label_fr']);
                            ?>
                            <td class="svl-cell is-<?= $h($state) ?><?= $sel ? ' is-picked' : '' ?>" data-svl-cell data-rank="<?= (int) $r ?>">
                                <label class="svl-pick" for="<?= $h($cid) ?>" title="<?= $h($locked ? ($vtEn ? 'Below the safety floor of this item' : 'Sous le plancher de sécurité de cette rubrique') : $say) ?>">
                                    <input type="radio" id="<?= $h($cid) ?>" name="<?= $h($vtName . '[' . $row['key'] . ']') ?>" value="<?= $h($lv['code']) ?>"<?= $sel ? ' checked' : '' ?><?= $locked || !$vtEdit ? ' disabled' : '' ?> data-rank="<?= (int) $r ?>">
                                    <span class="svl-mark"><span class="svl-on"><?= $svgCheck ?></span><span class="svl-off"><?= $svgBar ?></span><span class="svl-lock"><?= $svgLock ?></span></span>
                                    <span class="svl-sr"><?= $h($locked ? ($vtEn ? 'Locked: below the safety floor' : 'Verrouillé : sous le plancher de sécurité') : $say) ?></span>
                                </label>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th scope="row" class="svl-cat"><span class="svl-cat__name">Lisibles à ce niveau</span></th>
                    <?php foreach ($vtLevels as $lv): ?>
                        <?php
                        $count = 0;
                        foreach ((array) $vt['rows'] as $row) {
                            if (SseRedactionService::levelRank($lv['code']) >= SseRedactionService::levelRank((string) $row['level'])) { ++$count; }
                        }
                        ?>
                        <td class="svl-total" data-svl-total data-rank="<?= (int) SseRedactionService::levelRank($lv['code']) ?>"><b data-svl-n><?= (int) $count ?></b>/<?= count((array) $vt['rows']) ?></td>
                    <?php endforeach; ?>
                </tr>
            </tfoot>
        </table>
    </div>
    <p class="svl-levels"><?php foreach ($vtLevels as $i => $lv): ?><span><b translate="no"><?= $h($lv['abbr']) ?></b> <?= $h($vtEn ? $lv['label_en'] : $lv['label_fr']) ?></span><?php endforeach; ?></p>
    <ul class="svl-legend" aria-label="Légende">
        <li><span class="svl-key svl-key--on"><?= $svgCheck ?></span> Lisible en clair</li>
        <li><span class="svl-key svl-key--off"><?= $svgBar ?></span> Barre noire, avec la lettre de sa catégorie</li>
        <li><span class="svl-key svl-key--lock"><?= $svgLock ?></span> Sous le plancher de sécurité : non modifiable</li>
    </ul>
    <?php if ($vtEdit): ?>
        <button type="button" class="svl-reset" data-svl-reset>Rétablir les niveaux livrés</button>
    <?php endif; ?>
</div>
