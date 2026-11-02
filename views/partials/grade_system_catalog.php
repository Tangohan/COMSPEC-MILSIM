<?php
declare(strict_types=1);
/**
 * Fenêtre « Systèmes de grades » : tous les systèmes du registre OfficialGradeSystems, par pays (drapeau, nom natif)
 * puis par armée, recherche plein texte sur tous les grades (code, abrégé, intitulé natif, translittération, équivalent,
 * code OTAN), liste ordonnée des grades du système choisi et comparaison avec le système actuel (même niveau OTAN).
 * Mêmes composants que le catalogue d’emplois (job-catalog.css : .jobcat, drapeaux .jobcat__flag) ; comportement dans
 * assets/js/grade-system-catalog.js.
 *
 * Paramètres : $gscCountries (App\Support\GradeSystemCatalog::countries), $gscCurrent (code du système actuel, vide si
 * aucun), $gscMode « referentiel » (bouton « Utiliser pour la communauté » : formulaire POST $gscAction, confirmation)
 * ou « wizard » (étape Grades de la création : choisit le bouton radio wizard_grade_system_code), $gscCustom (systèmes
 * sur mesure de la communauté : [label, url]).
 */
$gscCountries = is_array($gscCountries ?? null) ? $gscCountries : [];
$gscCurrent = strtoupper(trim((string) ($gscCurrent ?? '')));
$gscMode = ($gscMode ?? 'referentiel') === 'wizard' ? 'wizard' : 'referentiel';
$gscAction = (string) ($gscAction ?? '');
$gscCustom = is_array($gscCustom ?? null) ? $gscCustom : [];
$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$categoryLabels = ['OFFICIER' => 'Officier', 'SOUS_OFFICIER' => 'Sous-officier', 'MDR' => 'Militaire du rang', 'CIVIL' => 'Civil', 'HORS_GRADE' => 'Hors grade', 'AUTRE' => '—'];
$currentSystem = null;
foreach ($gscCountries as $c) {
    foreach ($c['systems'] as $s) {
        if ($s['code'] === $gscCurrent) {
            $currentSystem = $s;
        }
    }
}
// Grades du système actuel par niveau OTAN (comparaison côte à côte).
$currentByLevel = [];
foreach (($currentSystem['grades'] ?? []) as $g) {
    if ($g['level'] !== null) {
        $currentByLevel[(int) $g['level']][] = $g['short'];
    }
}
$systemCount = 0;
$gradeCount = 0;
foreach ($gscCountries as $c) {
    $systemCount += count($c['systems']);
    foreach ($c['systems'] as $s) {
        $gradeCount += count($s['grades']);
    }
}
?>
<dialog class="jobcat grd-cat" id="grade-catalog-dialog" aria-labelledby="grade-catalog-title" data-grade-catalog data-mode="<?= $e($gscMode) ?>" data-current="<?= $e($gscCurrent) ?>">
<div class="jobcat__frame">
    <header class="jobcat__head">
        <div>
            <p class="jobcat__kicker">Catalogue des systèmes de grades</p>
            <h2 id="grade-catalog-title">Systèmes de grades</h2>
            <p class="jobcat__lead">Parcourez les grades officiels par pays et par armée, comparez-les au système actuel grâce aux codes OTAN, puis choisissez celui de la communauté.</p>
        </div>
        <div class="jobcat__head-actions">
            <button type="button" class="jobcat__close" data-grade-catalog-close aria-label="Fermer le catalogue">×</button>
        </div>
    </header>
    <div class="jobcat__toolbar" role="search">
        <label class="jobcat__search"><span class="eff-sr-only">Rechercher un grade dans tous les systèmes</span><input type="search" data-grade-catalog-search placeholder="Code, abrégé, intitulé, translittération, équivalent, code OTAN (OF-4, Hauptmann, Капитан, 上尉…)" autocomplete="off" aria-controls="grade-catalog-nav"></label>
    </div>
    <div class="jobcat__status">
        <p class="jobcat__count" data-grade-catalog-count role="status" aria-live="polite"><?= (int) $systemCount ?> systèmes · <?= (int) $gradeCount ?> grades</p>
        <span hidden data-grade-catalog-label="found">grades trouvés</span><span hidden data-grade-catalog-label="none">Aucun grade ne correspond à la recherche.</span>
    </div>
    <div class="jobcat__body">
        <nav class="jobcat__results grd-cat__nav" id="grade-catalog-nav" aria-label="Pays et systèmes de grades">
            <?php foreach ($gscCountries as $c): ?>
                <ul class="jobcat__group" data-grade-catalog-country="<?= $e($c['country']) ?>">
                    <li><p class="jobcat__group-title"><span class="jobcat__flag" data-flag="<?= $e($c['country']) ?>" aria-hidden="true"><?= $e($c['country']) ?></span><span translate="no"><?= $e($c['native']) ?></span></p></li>
                    <?php foreach ($c['systems'] as $s): ?>
                        <li><button type="button" class="jobcat__result<?= $s['code'] === $gscCurrent ? ' is-current' : '' ?>" data-grade-catalog-system="<?= $e($s['code']) ?>" aria-controls="grade-catalog-panel-<?= $e(strtolower($s['code'])) ?>">
                            <strong><?= $e(\App\Support\GradeSystemCatalog::BRANCHES[$s['branch']] ?? '') ?><?php if ($s['native'] !== ''): ?> <span class="grd-cat__native" translate="no"<?= $s['lang'] !== '' ? ' lang="' . $e($s['lang']) . '"' : '' ?>><?= $e($s['native']) ?></span><?php endif; ?></strong>
                            <small><span data-grade-catalog-total><?= count($s['grades']) ?> grades</span><span data-grade-catalog-hits hidden></span><?php if ($s['code'] === $gscCurrent): ?> · <span class="grd-cat__mark">Système actuel</span><?php elseif (!$s['installed']): ?> · <span class="grd-cat__mark is-off">Pas encore installé</span><?php endif; ?></small>
                        </button></li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
            <?php if ($gscCustom !== []): ?>
                <ul class="jobcat__group">
                    <li><p class="jobcat__group-title"><span class="jobcat__flag is-community" aria-hidden="true">★</span>Sur mesure</p></li>
                    <?php foreach ($gscCustom as [$customLabel, $customUrl]): ?>
                        <li><a class="jobcat__result" href="<?= $e($customUrl) ?>"><strong translate="no"><?= $e($customLabel) ?></strong><small>Système créé par la communauté</small></a></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </nav>
        <?php foreach ($gscCountries as $c): ?>
            <?php foreach ($c['systems'] as $s): ?>
                <?php
                $isCurrent = $s['code'] === $gscCurrent;
                $matched = 0;
                foreach ($s['grades'] as $g) {
                    if ($g['level'] !== null && isset($currentByLevel[(int) $g['level']])) {
                        $matched++;
                    }
                }
                $langAttr = $s['lang'] !== '' ? ' lang="' . $e($s['lang']) . '"' : '';
                ?>
                <section class="jobcat__detail grd-cat__panel" id="grade-catalog-panel-<?= $e(strtolower($s['code'])) ?>" data-grade-catalog-panel="<?= $e($s['code']) ?>" tabindex="-1" hidden>
                    <button type="button" class="jobcat__back" data-grade-catalog-back>← Retour aux systèmes</button>
                    <div class="jobcat__detail-head">
                        <div>
                            <p class="jobcat__origin"><span class="jobcat__flag" data-flag="<?= $e($s['country']) ?>" aria-hidden="true"><?= $e($s['country']) ?></span><span translate="no"><?= $e($c['native']) ?></span><span data-jc="branch_label"><?= $e(\App\Support\GradeSystemCatalog::BRANCHES[$s['branch']] ?? '') ?></span></p>
                            <h3 translate="no"<?= $langAttr ?>><?= $e($s['title']) ?></h3>
                            <p class="jobcat__official"><?= $e($s['text']) ?></p>
                        </div>
                    </div>
                    <p class="jobcat__chips"><span class="jobcat__chip is-abbr"><?= count($s['grades']) ?> grades</span><?php if ($isCurrent): ?><span class="jobcat__chip">Système actuel</span><?php endif; ?><?php if (!$s['installed']): ?><span class="jobcat__chip is-type">Pas encore installé</span><?php endif; ?></p>
                    <div class="jobcat__actions">
                        <?php if ($isCurrent): ?>
                            <small>Ce système est celui de la communauté.</small>
                        <?php elseif (!$s['installed']): ?>
                            <small>Ces grades ne sont pas encore installés sur le site : un administrateur doit lancer les migrations.</small>
                        <?php elseif ($gscMode === 'wizard'): ?>
                            <button type="button" class="eff-btn eff-btn--primary cc-btn cc-btn--primary" data-grade-catalog-pick="<?= $e($s['code']) ?>">Choisir ce système</button>
                        <?php else: ?>
                            <form method="post" action="<?= $e($gscAction) ?>" data-grade-catalog-use-form>
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="grade_system_code" value="<?= $e($s['code']) ?>">
                                <button type="button" class="eff-btn eff-btn--primary grd-ref__switch" data-grade-catalog-use>Utiliser pour la communauté</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($currentSystem !== null && !$isCurrent): ?>
                            <label class="jobcat__check grd-cat__compare"><input type="checkbox" data-grade-catalog-compare> Comparer avec le système actuel <span translate="no">(<?= $e($currentSystem['title']) ?>)</span></label>
                        <?php endif; ?>
                    </div>
                    <table class="grd-cat__table" data-grade-catalog-table>
                        <thead><tr><th scope="col">Abrégé</th><th scope="col">Intitulé et équivalent</th><th scope="col">Code OTAN</th><th scope="col">Catégorie</th><?php if ($currentSystem !== null && !$isCurrent): ?><th scope="col" data-grade-catalog-compare-col hidden>Système actuel</th><?php endif; ?></tr></thead>
                        <tbody>
                        <?php foreach ($s['grades'] as $g): ?>
                            <?php $search = mb_strtolower(implode(' ', [$g['code'], $g['short'], $g['long'], $g['roman'], $g['english'], $g['nato']]), 'UTF-8'); ?>
                            <tr data-grade-catalog-row data-search="<?= $e($search) ?>">
                                <td translate="no"<?= $langAttr ?>><b><?= $e($g['short']) ?></b></td>
                                <td translate="no"><span<?= $langAttr ?>><?= $e($g['long']) ?></span><?php if ($g['roman'] !== ''): ?><small lang="<?= $e($s['lang']) ?>-Latn"><?= $e($g['roman']) ?></small><?php endif; ?><?php if ($g['english'] !== ''): ?><small lang="en"><?= $e($g['english']) ?></small><?php endif; ?></td>
                                <td><code translate="no"><?= $e($g['nato'] !== '' ? $g['nato'] : '—') ?></code></td>
                                <td><?= $e($categoryLabels[$g['category']] ?? $categoryLabels['AUTRE']) ?></td>
                                <?php if ($currentSystem !== null && !$isCurrent): ?><td data-grade-catalog-compare-col hidden translate="no"><?= $e($g['level'] !== null && isset($currentByLevel[(int) $g['level']]) ? implode(' / ', $currentByLevel[(int) $g['level']]) : '—') ?></td><?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if ($gscMode === 'referentiel' && $s['installed'] && !$isCurrent): ?>
                    <div hidden data-grade-catalog-summary>
                        <p class="jobcat-confirm__lead" translate="no"><b><?= $e($s['title']) ?></b></p>
                        <dl class="jobcat-confirm__fields">
                            <div><dt>Grades du système</dt><dd><?= count($s['grades']) ?></dd></div>
                            <?php if ($currentSystem !== null): ?><div><dt>Équivalent OTAN dans le système actuel</dt><dd><?= (int) $matched ?> / <?= count($s['grades']) ?></dd></div><?php endif; ?>
                        </dl>
                    </div>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        <?php endforeach; ?>
        <p class="jobcat__placeholder" data-grade-catalog-placeholder>Choisissez un système dans la liste pour voir ses grades.</p>
    </div>
    <footer class="jobcat__foot"><p>Intitulés officiels dans la langue du pays, translittération et équivalent anglais usuel ; codes OTAN du STANAG 2116 (correspondance par fonction pour les armées hors OTAN).</p></footer>
</div>
<?php if ($gscMode === 'referentiel'): ?>
<dialog class="jobcat-confirm" data-grade-catalog-confirm aria-labelledby="grade-catalog-confirm-title">
    <form method="dialog" class="jobcat-confirm__frame">
        <h2 id="grade-catalog-confirm-title">Changer le système de grades de la communauté ?</h2>
        <div data-grade-catalog-confirm-summary></div>
        <ul class="jobcat-confirm__notes">
            <li>Les membres gardent les grades déjà attribués : rien n’est supprimé ni modifié.</li>
            <li>Les fiches membres, l’ORBAT et l’avancement proposent ensuite les grades du nouveau système ; un grade se retrouve dans l’autre système par son code OTAN (même niveau).</li>
            <li>Vous pouvez revenir au système précédent à tout moment depuis ce référentiel.</li>
        </ul>
        <div class="jobcat-confirm__actions"><button type="submit" value="use" class="eff-btn eff-btn--primary">Utiliser pour la communauté</button><button type="submit" value="cancel" class="eff-btn">Annuler</button></div>
    </form>
</dialog>
<?php endif; ?>
</dialog>
