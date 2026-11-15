<?php
declare(strict_types=1);
use App\Support\SseAccessCatalog as C;

/**
 * Droits d'accès SSE : grille sections × niveaux, modèles prêts à l'emploi, aide courte.
 *
 * @var list<array<string, mixed>> $grid
 * @var int $rulesTotal
 * @var bool $ready
 * @var array{roles: list<array{id: int, label: string}>, units: list<array{id: int, label: string}>} $options
 * @var array<string, mixed> $old
 * @var string $csrfToken
 * @var string $base
 */
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$tab = 'grille';
require __DIR__ . '/_head.php';
$old = is_array($old ?? null) ? $old : [];
?>
<div class="fx sar">
    <div class="fx-th">
        <div>
            <h1>Grille détaillée des droits</h1>
            <p>Qui peut voir, lire, écrire, verser, supprimer, exporter et gérer, section par section. Tant qu’aucune règle n’existe, chaque personne garde exactement les droits qu’elle avait avant : une règle ne fait que restreindre ou étendre, pour votre communauté seulement.</p>
        </div>
        <div class="fx-ws"><div><small>Règles actives</small><b><?= (int) $rulesTotal ?></b></div></div>
    </div>

    <section class="fx-card" aria-labelledby="sar-grid-title">
        <h2 id="sar-grid-title" class="sar-h2">Grille des droits</h2>
        <p class="fx-help">Chaque case montre ce qui s’applique : « Par défaut » (le droit d’origine, sans règle), ou le nombre d’accords et de refus configurés. Ouvrez une case pour voir les règles et en ajouter.</p>
        <div class="fx-tbw">
            <table class="fx-tb sar-grid resp">
                <caption class="sr-only">Sections du portail SSE et niveaux de droit</caption>
                <thead>
                    <tr>
                        <th scope="col">Section</th>
                        <?php foreach (C::LEVELS as $lv => $def): ?>
                            <th scope="col" title="<?= $h($def['hint']) ?>"><?= $h($def['label']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($grid as $row): ?>
                    <tr>
                        <th scope="row" data-label="Section">
                            <a href="<?= $h(url($base . '/section/' . $row['key'])) ?>"><?= $h($row['label']) ?></a>
                            <small class="fx-help"><?= $h($row['hint']) ?></small>
                        </th>
                        <?php foreach (C::LEVELS as $lv => $def): $cell = $row['cells'][$lv]; ?>
                            <td data-label="<?= $h($def['label']) ?>">
                                <?php if (!$cell['applies']): ?>
                                    <span class="sar-na" title="Sans objet pour cette section">Sans objet</span>
                                <?php else: ?>
                                    <a class="sar-cell" href="<?= $h(url($base . '/section/' . $row['key'] . '?niveau=' . $lv . '#niv-' . $lv)) ?>">
                                        <?php if ($cell['allow'] === 0 && $cell['deny'] === 0): ?>
                                            <span class="sar-def">Par défaut</span>
                                        <?php else: ?>
                                            <?php if ($cell['allow'] > 0): ?><span class="fx-flg sar-allow">Accords : <?= (int) $cell['allow'] ?></span><?php endif; ?>
                                            <?php if ($cell['deny'] > 0): ?><span class="fx-flg sar-deny">Refus : <?= (int) $cell['deny'] ?></span><?php endif; ?>
                                        <?php endif; ?>
                                        <?php if ($cell['roles'] + $cell['units'] + $cell['users'] > 0): ?>
                                            <span class="sar-kinds">
                                                <?php if ($cell['roles'] > 0): ?><span>Niveaux d’accès <b><?= (int) $cell['roles'] ?></b></span><?php endif; ?>
                                                <?php if ($cell['units'] > 0): ?><span>Unités <b><?= (int) $cell['units'] ?></b></span><?php endif; ?>
                                                <?php if ($cell['users'] > 0): ?><span>Membres <b><?= (int) $cell['users'] ?></b></span><?php endif; ?>
                                            </span>
                                        <?php endif; ?>
                                        <small class="fx-help"><?= $h($cell['profile_label']) ?></small>
                                    </a>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="fx-card" aria-labelledby="sar-tpl-title">
        <h2 id="sar-tpl-title" class="sar-h2">Modèles prêts à l’emploi</h2>
        <p class="fx-help">Posez un modèle sur un niveau d’accès ou une unité d’un clic, vérifiez l’effet dans l’aperçu, puis modifiez les règles une à une depuis chaque section. Un modèle n’accorde que les droits que vous possédez vous-même.</p>
        <form method="post" action="<?= $h(url($base . '/modele')) ?>" class="sar-form">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
            <input type="hidden" name="do" value="preview">
            <fieldset class="sar-fs">
                <legend>Modèle</legend>
                <?php $first = true; foreach (C::TEMPLATES as $key => $tpl): ?>
                    <label class="sar-radio">
                        <input type="radio" name="template" value="<?= $h($key) ?>" required<?= ($old['template'] ?? ($first ? $key : '')) === $key ? ' checked' : '' ?>>
                        <span><b><?= $h($tpl['label']) ?></b><small class="fx-help"><?= $h($tpl['hint']) ?></small></span>
                    </label>
                <?php $first = false; endforeach; ?>
            </fieldset>
            <div class="fx-g3 sar-g">
                <div class="fx-f">
                    <?php $pickId = 'sar-tpl-subject'; $pickLabel = 'Bénéficiaire'; $pickKinds = []; $pickSelected = isset($old['subject_type'], $old['subject_id']) ? C::subjectRef((string) $old['subject_type'], (int) $old['subject_id']) : ''; require __DIR__ . '/_beneficiary.php'; ?>
                </div>
                <label class="fx-f"><span>À partir du (facultatif)</span><input class="fx-in" type="date" name="starts_at" value="<?= $h($old['starts_at'] ?? '') ?>"></label>
                <label class="fx-f"><span>Jusqu’au (facultatif)</span><input class="fx-in" type="date" name="ends_at" value="<?= $h($old['ends_at'] ?? '') ?>"></label>
            </div>
            <label class="fx-f"><span>Motif (obligatoire si vous refusez les autres niveaux)</span><input class="fx-in" type="text" name="reason" maxlength="<?= (int) C::REASON_MAX ?>" value="<?= $h($old['reason'] ?? '') ?>"></label>
            <label class="sar-check"><input type="checkbox" name="restrict_rest" value="1"<?= !empty($old['restrict_rest']) ? ' checked' : '' ?>> <span>Refuser aussi les autres niveaux (le refus l’emporte sur les droits d’origine de la personne)</span></label>
            <div class="fx-row"><button type="submit" class="btn pri">Voir l’aperçu de l’effet</button></div>
        </form>
    </section>

    <section class="fx-card" aria-labelledby="sar-help-title">
        <h2 id="sar-help-title" class="sar-h2">Comment les droits sont décidés</h2>
        <ol class="sar-order">
            <li><b>La porte d’entrée reste la même.</b> Une règle n’ouvre jamais le bureau SSE à quelqu’un qui n’a pas le droit d’y entrer ou qui n’a pas accepté l’engagement de confidentialité.</li>
            <li><b>Le refus explicite l’emporte.</b> Un refus (membre, niveau d’accès, unité) ferme le niveau, même si un accord ou le droit d’origine l’ouvrait. Refuser « Lire » ferme aussi écrire, verser, supprimer et exporter.</li>
            <li><b>Sinon, le droit d’origine s’applique.</b> Sans règle, personne ne gagne ni ne perd rien.</li>
            <li><b>Sinon, un accord actif ouvre le niveau,</b> dans ses dates de début et de fin.</li>
            <li><b>Sinon, c’est refusé.</b></li>
        </ol>
        <p class="fx-help">Vous ne pouvez accorder que ce que vous possédez vous-même. Un motif est obligatoire pour une dérogation individuelle, un refus ou le niveau « Gérer ». Chaque changement est inscrit dans l’historique et dans le journal d’audit SSE. Les administrateurs de la plateforme ne sont jamais soumis aux règles ; un refus ne peut pas retirer la lecture d’une section à tous les gestionnaires SSE.</p>
    </section>
</div>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
