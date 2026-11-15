<?php
declare(strict_types=1);

use App\Support\SseAccessCatalog as C;

/**
 * Droits d'accès SSE : « Qui peut quoi », une page. Lignes : sections ; colonnes : voir, écrire, gérer ; cellules : puces des
 * bénéficiaires (positions, fonctions, qualifications, identités d'abord ; niveaux d'accès et unités ensuite, moins visibles).
 * Un seul champ pour ajouter un bénéficiaire, des modèles « en situation », l'effet en une phrase avant d'enregistrer.
 * Aucun surtitre : le titre de la page est le premier texte.
 *
 * @var list<array<string, mixed>> $board
 * @var list<string> $sections sections que la personne voit
 * @var int $rulesTotal
 * @var bool $ready
 * @var list<array{ref: string, label: string, group: string, kind: string}> $picker
 * @var array<string, mixed> $old
 * @var string $focus
 * @var string $csrfToken
 * @var string $base
 */
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$tab = 'quoi';
require __DIR__ . '/_head.php';
$old = is_array($old ?? null) ? $old : [];
$oldSections = is_array($old['sections'] ?? null) ? array_map('strval', $old['sections']) : [];
$oldAccess = (string) ($old['access'] ?? 'voir');
$oldEffect = (string) ($old['effect'] ?? 'allow');
$typeClass = ['position' => 'is-position', 'job' => 'is-position', 'qualif' => 'is-qualif', 'user' => 'is-user'];
?>
<div class="fx sar">
    <div class="fx-th">
        <div>
            <h1>Qui peut quoi</h1>
            <p>Chaque ligne est une section du bureau SSE ; chaque colonne, ce que l’on peut y faire. Les puces disent qui reçoit le droit en plus de ce qu’il avait déjà : une position, une qualification, une personne. Sans puce, personne ne gagne ni ne perd rien.</p>
        </div>
        <div class="fx-ws"><div><small>Règles actives</small><b><?= (int) $rulesTotal ?></b></div></div>
    </div>

    <section class="fx-card" id="ajouter" aria-labelledby="sar-add-title">
        <h2 id="sar-add-title" class="sar-h2">Ajouter un bénéficiaire</h2>
        <p class="fx-help">Cherchez une position de service (chef, adjoint, instructeur…), une qualification ou une personne, dites ce qu’elle peut faire et où. Vous voyez l’effet avant d’enregistrer.</p>
        <form method="post" action="<?= $h(url($base . '/simple')) ?>" class="sar-form" data-sar-simple>
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
            <input type="hidden" name="do" value="preview">
            <div class="fx-g2 sar-g">
                <div class="fx-f">
                    <?php
                    $pickId = 'sar-add-subject';
                    $pickLabel = 'Bénéficiaire';
                    $pickKinds = [];
                    $pickPeople = true;
                    $pickSelected = isset($old['subject_type'], $old['subject_id']) ? C::subjectRef((string) $old['subject_type'], (int) $old['subject_id']) : '';
                    require __DIR__ . '/_beneficiary.php';
                    ?>
                </div>
                <div class="sar-stack">
                    <fieldset class="sar-fs">
                        <legend>Elle peut</legend>
                        <div class="sar-levels">
                            <?php foreach (C::SIMPLE_ACCESS as $key => $def): ?>
                                <label class="sar-check"><input type="radio" name="access" value="<?= $h($key) ?>"<?= $oldAccess === $key ? ' checked' : '' ?> required> <span><?= $h($def['label']) ?></span></label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                    <fieldset class="sar-fs">
                        <legend>Effet</legend>
                        <div class="sar-levels">
                            <label class="sar-check"><input type="radio" name="effect" value="allow"<?= $oldEffect !== 'deny' ? ' checked' : '' ?>> <span>Accorder</span></label>
                            <label class="sar-check"><input type="radio" name="effect" value="deny"<?= $oldEffect === 'deny' ? ' checked' : '' ?>> <span>Refuser (l’emporte sur tout)</span></label>
                        </div>
                    </fieldset>
                </div>
            </div>
            <fieldset class="sar-fs">
                <legend>Sur quelles sections</legend>
                <div class="sar-levels">
                    <label class="sar-check"><input type="checkbox" name="sections[]" value="*"<?= in_array('*', $oldSections, true) ? ' checked' : '' ?>> <span><b>Toutes les sections</b></span></label>
                    <?php foreach ($sections as $key): ?>
                        <label class="sar-check"><input type="checkbox" name="sections[]" value="<?= $h($key) ?>"<?= in_array($key, $oldSections, true) ? ' checked' : '' ?>> <span><?= $h(C::sectionLabel($key)) ?></span></label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <div class="fx-g3 sar-g">
                <label class="fx-f"><span>À partir du (facultatif)</span><input class="fx-in" type="date" name="starts_at" value="<?= $h($old['starts_at'] ?? '') ?>"></label>
                <label class="fx-f"><span>Jusqu’au (facultatif)</span><input class="fx-in" type="date" name="ends_at" value="<?= $h($old['ends_at'] ?? '') ?>"></label>
                <label class="fx-f"><span>Motif (obligatoire pour une personne, un refus ou « Gérer »)</span><input class="fx-in" type="text" name="reason" maxlength="<?= (int) C::REASON_MAX ?>" value="<?= $h($old['reason'] ?? '') ?>"></label>
            </div>
            <div class="fx-row"><button type="submit" class="btn pri"<?= !empty($ready) ? '' : ' disabled' ?>>Voir l’effet avant d’enregistrer</button></div>
        </form>
    </section>

    <section class="fx-card" aria-labelledby="sar-board-title">
        <h2 id="sar-board-title" class="sar-h2">Les droits ajoutés, section par section</h2>
        <p class="fx-help">Les positions, qualifications et personnes passent en premier ; les unités et niveaux d’accès, plus discrets, suivent. Une puce rouge est un refus. « Retirer » enlève la règle, pas la personne.</p>
        <div class="fx-tbw">
            <table class="fx-tb sar-board resp">
                <caption class="sr-only">Qui peut voir, écrire et gérer chaque section du bureau SSE</caption>
                <thead>
                    <tr>
                        <th scope="col">Section</th>
                        <?php foreach (C::SIMPLE_ACCESS as $def): ?><th scope="col"><?= $h($def['label']) ?></th><?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($board as $row): ?>
                    <tr<?= $focus === $row['key'] ? ' class="is-focus"' : '' ?>>
                        <th scope="row" data-label="Section"><a href="<?= $h(url($base . '/section/' . $row['key'])) ?>"><?= $h($row['label']) ?></a></th>
                        <?php foreach (C::SIMPLE_ACCESS as $access => $def): ?>
                            <td data-label="<?= $h($def['label']) ?>">
                                <?php if (empty($row['applies'][$access])): ?>
                                    <span class="sar-na">Sans objet</span>
                                <?php else: ?>
                                    <ul class="sar-chips">
                                        <?php foreach ($row['columns'][$access] as $chip): ?>
                                            <li class="sar-bchip <?= $h($typeClass[$chip['type']] ?? 'is-other') ?><?= $chip['effect'] === 'deny' ? ' is-deny' : '' ?><?= $chip['simple'] ? '' : ' is-muted' ?><?= $chip['state'] === 'scheduled' ? ' is-later' : '' ?>">
                                                <span class="sar-bchip-t">
                                                    <b><?= $h($chip['label']) ?></b>
                                                    <small><?= $chip['effect'] === 'deny' ? 'Refus · ' : '' ?><?= $h($chip['type_label']) ?><?= $chip['state'] === 'scheduled' ? ' · à venir' : '' ?><?= $chip['ends_at'] !== null ? ' · jusqu’au ' . $h(substr((string) $chip['ends_at'], 0, 10)) : '' ?></small>
                                                </span>
                                                <form method="post" action="<?= $h(url($base . '/regle/' . (int) $chip['rule_id'] . '/retirer')) ?>" class="sar-bchip-x">
                                                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                                    <input type="hidden" name="back" value="<?= $h($base) ?>">
                                                    <button type="submit" class="sar-x" aria-label="<?= $h('Retirer ' . $chip['label'] . ' : ' . $def['label'] . ' · ' . $row['label']) ?>" title="Retirer">×</button>
                                                </form>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php if ($row['columns'][$access] === []): ?><span class="sar-def">Droit d’origine seulement</span><?php endif; ?>
                                    <a class="sar-plus" href="<?= $h(url($base . '?section=' . $row['key'] . '&acces=' . $access . '#ajouter')) ?>">Ajouter</a>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="fx-help">Besoin d’un niveau plus fin (verser, supprimer, exporter) ? Ouvrez la <a href="<?= $h(url($base . '/grille')) ?>">grille détaillée</a>.</p>
    </section>

    <section class="fx-card" aria-labelledby="sar-recipes-title">
        <h2 id="sar-recipes-title" class="sar-h2">Modèles prêts à l’emploi</h2>
        <p class="fx-help">Un modèle règle plusieurs sections d’un coup. Vous choisissez les positions et la qualification de votre communauté ; vous ne donnez que ce que vous possédez vous-même.</p>
        <?php foreach (C::RECIPES as $rkey => $recipe): ?>
            <form method="post" action="<?= $h(url($base . '/situation')) ?>" class="sar-form sar-recipe" data-sar-recipe>
                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                <input type="hidden" name="do" value="preview">
                <input type="hidden" name="recipe" value="<?= $h($rkey) ?>">
                <h3 class="sar-h3"><?= $h($recipe['label']) ?></h3>
                <p class="fx-help"><?= $h($recipe['hint']) ?></p>
                <div class="fx-g2 sar-g">
                    <?php foreach ($recipe['steps'] as $i => $step): ?>
                        <div class="fx-f">
                            <?php
                            $pickId = 'sar-r-' . $rkey . '-' . $i;
                            $pickLabel = $step['label'] . ' · ' . C::SIMPLE_ACCESS[$step['access']]['label'];
                            $pickName = 'ref[' . $i . ']';
                            $pickKinds = $step['kinds'];
                            $pickPeople = false;
                            $pickSelected = '';
                            require __DIR__ . '/_beneficiary.php';
                            ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php $pickName = 'subject_ref'; ?>
                <div class="fx-row"><button type="submit" class="btn"<?= !empty($ready) ? '' : ' disabled' ?>>Voir l’effet avant d’enregistrer</button></div>
            </form>
        <?php endforeach; ?>
    </section>

    <section class="fx-card" aria-labelledby="sar-help-title">
        <h2 id="sar-help-title" class="sar-h2">Ce qui ne change pas</h2>
        <ol class="sar-order">
            <li><b>La porte d’entrée reste la même.</b> Une règle n’ouvre jamais le bureau SSE à quelqu’un qui n’a pas le droit d’y entrer ou qui n’a pas accepté l’engagement de confidentialité.</li>
            <li><b>Le refus l’emporte.</b> Un refus ferme le droit, même si une position, une qualification ou le droit d’origine l’ouvrait.</li>
            <li><b>Une qualification expirée ou suspendue ne donne rien.</b> Le droit suit la qualification : il se ferme dès qu’elle n’est plus valide.</li>
            <li><b>Vous ne donnez que ce que vous avez.</b> Chaque changement est inscrit dans l’historique et dans le journal d’audit SSE.</li>
        </ol>
    </section>
</div>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
