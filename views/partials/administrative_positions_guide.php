<?php
/**
 * Explication des positions administratives et de « dans l’exercice de ses fonctions ».
 * Utilisé par le guide du portail, la fiche effectifs et la fiche membre du back-office.
 *
 * @var string|null $apgCurrent  slug de la position du membre affiché (mise en avant), ou null
 * @var bool        $apgShowLink lien vers le chapitre du guide
 */
use App\Support\AdministrativePositionCatalog;

$apgCurrent = isset($apgCurrent) && is_string($apgCurrent) ? $apgCurrent : null;
$apgShowLink = !empty($apgShowLink);
$apgH = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
if (empty($GLOBALS['__apg_css_loaded'])):
    $GLOBALS['__apg_css_loaded'] = true; ?>
<link rel="stylesheet" href="<?= $apgH(asset_url('assets/css/administrative-positions.css')) ?>">
<?php endif; ?>
<div class="apg">
    <div class="apg__table-wrap">
        <table class="apg__table">
            <thead>
                <tr>
                    <th scope="col">Position</th>
                    <th scope="col">Ce que cela veut dire</th>
                    <th scope="col">Engagé en mission</th>
                    <th scope="col">Tient le service</th>
                    <th scope="col">Effectif opérationnel</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (AdministrativePositionCatalog::all() as $apgRow): ?>
                <tr class="apg__row<?= $apgRow['slug'] === $apgCurrent ? ' apg__row--current' : '' ?>">
                    <th scope="row" data-label="Position">
                        <span class="apg__pill apg__pill--<?= $apgH($apgRow['tone']) ?>"><?= $apgH($apgRow['label']) ?></span>
                        <?php if ($apgRow['slug'] === $apgCurrent): ?><span class="apg__current">Position actuelle</span><?php endif; ?>
                    </th>
                    <td data-label="Ce que cela veut dire">
                        <p class="apg__summary"><?= $apgH($apgRow['summary']) ?></p>
                        <p class="apg__detail"><?= $apgH($apgRow['detail']) ?></p>
                    </td>
                    <td data-label="Engagé en mission"><?= $apgH($apgRow['mission']) ?></td>
                    <td data-label="Tient le service"><?= $apgH($apgRow['service']) ?></td>
                    <td data-label="Effectif opérationnel"><?= $apgH($apgRow['strength']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <h4 class="apg__subhead">Dans l’exercice de ses fonctions</h4>
    <p class="apg__lead">Cette notion regroupe deux situations : en mission et en service. En dehors de ces deux situations, le membre n’est pas en fonction.</p>
    <div class="apg__situations">
        <?php foreach (AdministrativePositionCatalog::dutySituations() as $apgSit): ?>
        <div class="apg__situation">
            <p class="apg__situation-title"><?= $apgH($apgSit['label']) ?></p>
            <p class="apg__summary"><?= $apgH($apgSit['summary']) ?></p>
            <p class="apg__detail"><?= $apgH($apgSit['examples']) ?></p>
        </div>
        <?php endforeach; ?>
    </div>
    <p class="apg__note">La position est attribuée automatiquement à l’arrivée (En formation) puis à la fin de l’accueil (En service actif). Un responsable des effectifs peut la changer depuis la fiche du membre. Elle ne modifie ni le grade, ni les fonctions, ni les niveaux d’accès.</p>
    <?php if ($apgShowLink): ?>
    <p class="apg__note"><a href="<?= $apgH(url('documentation') . '#positions-administratives') ?>">Lire le guide des positions administratives</a></p>
    <?php endif; ?>
</div>
