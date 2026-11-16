<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/**
 * Bureau SSE › Analyse › Détections Cerbère : les règles de Cerbère-Lecture (lexique livré et réglages de la communauté).
 * Bande d'en-tête (titre, chiffres), puis trois onglets : Règles (par thème, urgence et type de fiche : état, poids, termes,
 * exclusions, déclenchements sur 30 jours, taux de rejet, réglage en fenêtre), Testez un texte, Historique.
 * Styles : cerbere-detections.css (.cdx-*) ; script : cerbere-detections.js (onglets, fenêtres, test en direct) ; aucun texte.
 *
 * @var array<string, string> $tabs
 * @var array{rules: list<array<string, mixed>>, ready: bool, sample: int, version: string, thresholds: array{visible: int, review: int}} $overview
 * @var list<array<string, mixed>> $history
 * @var array<string, string> $falseFriends
 * @var array<string, mixed>|null $cdxTest
 * @var bool $cdxManage
 */
use App\Services\Sse\Detection\SseDetectionLexicon as L;
use App\Support\SseFieldNoteCatalog;

$rules = $overview['rules'];
$ready = (bool) $overview['ready'];
$canEdit = !empty($cdxManage) && $ready;
$csrf = '<input type="hidden" name="_csrf_token" value="' . $h(\App\Core\Csrf::token()) . '">';
$json = static fn (array $v): string => $h(json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
$active = count(array_filter($rules, static fn (array $r): bool => $r['enabled']));
$customized = count(array_filter($rules, static fn (array $r): bool => $r['customized']));
$flagged = count(array_filter($rules, static fn (array $r): bool => $r['flagged']));
$groups = [
    'theme' => ['title' => 'Thèmes', 'desc' => 'Proposent un thème à ajouter à la fiche ; plusieurs règles peuvent viser le même thème.'],
    'urgency' => ['title' => 'Urgence', 'desc' => 'Signaux d’urgence propres : « Critique » demande un signal immédiat et un deuxième signal distinct.'],
    'kind' => ['title' => 'Type de fiche', 'desc' => 'Proposent un autre type de fiche quand au moins deux termes distincts le décrivent.'],
];
$close = '<button type="button" class="cdx-dialog__close" data-cdx-close aria-label="Fermer la fenêtre"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg></button>';
$firstTab = is_array($cdxTest ?? null) ? 'tester' : 'regles';
$diff = static function (array $e): string {
    if ($e['action'] === 'cerbere.lecture.reset') {
        return 'Lexique livré rétabli (' . count($e['before']) . ' réglage' . (count($e['before']) > 1 ? 's' : '') . ' retiré' . (count($e['before']) > 1 ? 's' : '') . ')';
    }
    $b = $e['before'];
    $a = $e['after'];
    $out = [];
    if (array_key_exists('enabled', $a) && (bool) ($b['enabled'] ?? true) !== (bool) $a['enabled']) {
        $out[] = $a['enabled'] ? 'activée' : 'désactivée';
    }
    if (array_key_exists('weight', $a) && $a['weight'] !== null && (int) ($b['weight'] ?? 0) !== (int) $a['weight']) {
        $out[] = 'poids ' . (int) ($b['weight'] ?? 0) . ' → ' . (int) $a['weight'];
    }
    foreach (['terms' => 'termes', 'exclusions' => 'exclusions', 'requires' => 'contexte requis'] as $k => $label) {
        if (is_array($a[$k] ?? null)) {
            $before = $k === 'terms' ? array_keys((array) ($b[$k] ?? [])) : (array) ($b[$k] ?? []);
            $after = $k === 'terms' ? array_keys($a[$k]) : $a[$k];
            $plus = array_diff($after, $before);
            $minus = array_diff($before, $after);
            if ($plus !== [] || $minus !== []) {
                $out[] = $label . ($plus !== [] ? ' + ' . implode(', ', $plus) : '') . ($minus !== [] ? ' − ' . implode(', ', $minus) : '');
            }
        }
    }

    return 'Règle ' . $e['rule'] . ' : ' . ($out !== [] ? implode(' ; ', $out) : 'réglage enregistré sans changement visible');
};
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/cerbere-detections.css')) . '">';
$sseExtraScripts = ($sseExtraScripts ?? '') . '<script defer src="' . $h(asset_url('assets/js/cerbere-detections.js')) . '"></script>';
?>
<div class="cdx-page">
    <header class="cdx-strip">
        <span class="cdx-strip__index" aria-hidden="true">C.L</span>
        <div>
            <h1>Détections Cerbère</h1>
            <p>Les règles de Cerbère-Lecture, qui lisent le texte des remontées pour proposer un type, des thèmes et une urgence : mots entiers, exclusions, négations, rien n’est appliqué sans un clic.</p>
        </div>
        <dl class="cdx-figs">
            <div><dt>règles actives</dt><dd><?= $active ?> / <?= count($rules) ?></dd></div>
            <div><dt>réglées par la communauté</dt><dd><?= $customized ?></dd></div>
            <div><dt>remontées lues (30 jours)</dt><dd><?= (int) $overview['sample'] ?></dd></div>
            <div><dt>règles trop rejetées</dt><dd><?= $flagged ?></dd></div>
            <div><dt>lexique livré</dt><dd class="cdx-mono" translate="no"><?= $h($overview['version']) ?></dd></div>
        </dl>
    </header>

    <?php if (!$ready): ?>
        <p class="cdx-notice"><strong>Lexique livré, sans réglage</strong>L’étape de base de données « sse_detection_rules » n’est pas encore passée : les détections marchent avec le lexique livré, mais les règles ne se règlent pas et les retours ne sont pas gardés.</p>
    <?php elseif (empty($cdxManage)): ?>
        <p class="cdx-notice"><strong>Lecture seule</strong>Régler une règle demande le droit « gérer » des fiches de renseignement. Vous pouvez tester un texte.</p>
    <?php endif; ?>

    <div class="cdx-tabs" role="tablist" aria-label="Rubriques des détections">
        <?php foreach ($tabs as $key => $label): ?>
            <button type="button" role="tab" id="cdx-tab-<?= $h($key) ?>" aria-controls="cdx-panel-<?= $h($key) ?>" aria-selected="<?= $key === $firstTab ? 'true' : 'false' ?>"<?= $key === $firstTab ? '' : ' tabindex="-1"' ?> data-cdx-tab="<?= $h($key) ?>"><?= $h($label) ?></button>
        <?php endforeach; ?>
    </div>

    <section class="cdx-panel" role="tabpanel" id="cdx-panel-regles" aria-labelledby="cdx-tab-regles" data-cdx-panel="regles"<?= $firstTab === 'regles' ? '' : ' hidden' ?>>
        <div class="cdx-panel__head">
            <div>
                <h2>Règles</h2>
                <p class="cdx-desc">Chaque règle : son identifiant, son poids, ses termes et exclusions, ses déclenchements sur 30 jours et la part de « Mauvaise détection » parmi les décisions.</p>
            </div>
            <?php if ($canEdit): ?>
                <button type="button" class="btn btn--ghost" data-cdx-open="cdx-dlg-reset" aria-haspopup="dialog">Rétablir le lexique livré</button>
            <?php endif; ?>
        </div>
        <?php foreach ($groups as $target => $g): $list = array_values(array_filter($rules, static fn (array $r): bool => $r['target'] === $target)); ?>
            <section class="cdx-group" aria-labelledby="cdx-g-<?= $h($target) ?>">
                <h3 id="cdx-g-<?= $h($target) ?>"><?= $h($g['title']) ?> <span class="cdx-count"><?= count($list) ?></span></h3>
                <p class="cdx-desc"><?= $h($g['desc']) ?></p>
                <ul class="cdx-rules">
                    <?php foreach ($list as $r): $s = $r['stats']; $terms = array_keys($r['terms']); ?>
                        <li class="cdx-rule-row<?= $r['enabled'] ? '' : ' cdx-rule-row--off' ?><?= $r['flagged'] ? ' cdx-rule-row--flag' : '' ?>" id="regle-<?= $h($r['id']) ?>">
                            <div>
                                <h4><span class="cdx-mono" translate="no"><?= $h($r['id']) ?></span> · <?= $h($r['label']) ?> → <?= $h($r['value_label']) ?></h4>
                                <p>
                                    <span class="cdx-state cdx-state--<?= $r['enabled'] ? 'on' : 'off' ?>"><?= $r['enabled'] ? 'Active' : 'Inactive' ?></span>
                                    <?php if ($r['customized']): ?><span class="cdx-state cdx-state--off">Réglée</span><?php endif; ?>
                                    <?php if ($r['flagged']): ?><span class="cdx-state cdx-state--flag">Trop rejetée</span><?php endif; ?>
                                    <?= $h($r['explain']) ?>
                                </p>
                                <ul class="cdx-chips" aria-label="Termes">
                                    <?php foreach (array_slice($terms, 0, 10) as $t): ?><li><?= $h($t) ?></li><?php endforeach; ?>
                                    <?php if (count($terms) > 10): ?><li>+<?= count($terms) - 10 ?></li><?php endif; ?>
                                </ul>
                                <?php if ($r['exclusions'] !== []): ?><p class="cdx-dim">Exclusions : <?= $h(implode(', ', $r['exclusions'])) ?></p><?php endif; ?>
                                <?php if ($r['requires'] !== []): ?><p class="cdx-dim">Contexte requis : <?= $h(implode(', ', $r['requires'])) ?></p><?php endif; ?>
                                <p class="cdx-stats">
                                    <span>Poids <strong><?= (int) $r['weight'] ?></strong></span>
                                    <span><strong><?= (int) $r['fired'] ?></strong> déclenchement<?= $r['fired'] > 1 ? 's' : '' ?> sur 30 jours</span>
                                    <span>appliquée <strong><?= (int) $s['applied'] ?></strong></span>
                                    <span>ignorée <strong><?= (int) $s['ignored'] ?></strong></span>
                                    <span>mauvaise détection <strong><?= (int) $s['rejected'] ?></strong></span>
                                    <span>taux de rejet <strong><?= $r['reject_rate'] === null ? '—' : (int) $r['reject_rate'] . ' %' ?></strong></span>
                                </p>
                                <?php if ($s['reasons'] !== []): ?><p class="cdx-dim">Derniers motifs : <?= $h(implode(' · ', $s['reasons'])) ?></p><?php endif; ?>
                            </div>
                            <?php if ($canEdit): ?>
                                <div class="cdx-rule-row__acts">
                                    <button type="button" class="btn btn--ghost" data-cdx-open="cdx-dlg-rule" aria-haspopup="dialog" aria-label="Régler la règle <?= $h($r['id']) ?>"
                                        data-cdx-fill="<?= $json(['rule_id' => $r['id'], 'title' => $r['id'] . ' · ' . $r['label'], 'enabled' => $r['enabled'], 'weight' => $r['weight'], 'terms' => L::termsToText($r['terms']), 'exclusions' => implode("\n", $r['exclusions']), 'requires' => implode("\n", $r['requires']), 'reason' => '']) ?>">Régler</button>
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
        <section class="cdx-group" aria-labelledby="cdx-g-friends">
            <h3 id="cdx-g-friends">Faux amis vérifiés</h3>
            <p class="cdx-desc">Mots qui contiennent un terme sans en être un : la lecture par mots entiers les écarte, et une racine déclarée (« mot* ») ne les prend jamais.</p>
            <ul class="cdx-chips">
                <?php foreach ($falseFriends as $word => $why): ?><li title="<?= $h($why) ?>"><?= $h($word) ?></li><?php endforeach; ?>
            </ul>
        </section>
    </section>

    <section class="cdx-panel" role="tabpanel" id="cdx-panel-tester" aria-labelledby="cdx-tab-tester" data-cdx-panel="tester"<?= $firstTab === 'tester' ? '' : ' hidden' ?>>
        <div class="cdx-panel__head">
            <div>
                <h2>Testez un texte</h2>
                <p class="cdx-desc">Collez un texte : vous voyez ce que Cerbère-Lecture proposerait et ce qu’il écarterait. Rien n’est enregistré.</p>
            </div>
        </div>
        <div class="cdx-test">
            <form method="post" action="<?= $h(url(\App\Controllers\Web\SseDetectionController::PATH . '/tester')) ?>" class="cdx-test__form" data-cdx-live="cdx-test-out">
                <?= $csrf ?>
                <label class="cdx-field" for="cdx-test-text">Texte à lire
                    <textarea id="cdx-test-text" name="text" rows="6" maxlength="20000" required><?= $h(is_array($cdxTest ?? null) ? $cdxTest['text'] : '') ?></textarea>
                </label>
                <div class="cdx-test__row">
                    <label class="cdx-field" for="cdx-test-kind">Type de fiche (facultatif)
                        <select id="cdx-test-kind" name="kind">
                            <option value="">Aucun</option>
                            <?php foreach (SseFieldNoteCatalog::KINDS as $code => $k): ?><option value="<?= $h($code) ?>"<?= is_array($cdxTest ?? null) && $cdxTest['kind'] === $code ? ' selected' : '' ?>><?= $h($code . ' · ' . $k['label']) ?></option><?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit" class="btn">Tester</button>
                </div>
            </form>
            <div id="cdx-test-out" aria-live="polite">
                <?php if (is_array($cdxTest ?? null)): require __DIR__ . '/_cerbere_detection_result.php'; endif; ?>
            </div>
        </div>
    </section>

    <section class="cdx-panel" role="tabpanel" id="cdx-panel-historique" aria-labelledby="cdx-tab-historique" data-cdx-panel="historique" hidden>
        <div class="cdx-panel__head">
            <div>
                <h2>Historique</h2>
                <p class="cdx-desc">Les changements de règles de la communauté, du plus récent au plus ancien, avec leur auteur.</p>
            </div>
        </div>
        <?php if ($history === []): ?>
            <p class="cdx-result__none">Aucun changement : le lexique livré s’applique tel quel.</p>
        <?php else: ?>
            <ol class="cdx-history">
                <?php foreach ($history as $e): ?>
                    <li><strong><?= $h($diff($e)) ?></strong><br><span class="cdx-dim"><?= $h($e['at']) ?> · <span translate="no"><?= $h($e['actor']) ?></span><?= $e['reason'] !== '' ? ' · ' . $h($e['reason']) : '' ?></span></li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>

    <?php if ($canEdit): ?>
        <dialog class="cdx-dialog" id="cdx-dlg-rule" aria-labelledby="cdx-dlg-rule-title">
            <form method="post" action="<?= $h(url(\App\Controllers\Web\SseDetectionController::PATH . '/regle')) ?>" class="cdx-dialog__card">
                <?= $csrf ?><input type="hidden" name="rule_id" value="">
                <header class="cdx-dialog__head">
                    <div>
                        <h2 id="cdx-dlg-rule-title">Régler la règle <span data-cdx-out="title" translate="no"></span></h2>
                        <p class="cdx-desc">Le changement s’applique tout de suite aux propositions de toute la communauté et reste dans l’historique.</p>
                    </div>
                    <?= $close ?>
                </header>
                <div class="cdx-dialog__body">
                    <div class="cdx-grid2">
                        <label class="cdx-switch"><input type="checkbox" name="enabled" value="1"> Règle active</label>
                        <label class="cdx-field" for="cdx-rule-weight">Poids (0 à 100)
                            <input id="cdx-rule-weight" type="number" name="weight" min="0" max="100" step="1" required>
                        </label>
                    </div>
                    <label class="cdx-field" for="cdx-rule-terms">Termes
                        <small>Un par ligne, comparé mot entier sans accents. « mot(s|es) » : suffixes ; « racine* » : début de mot ; « terme = 80 » : poids propre.</small>
                        <textarea id="cdx-rule-terms" name="terms" rows="6" required></textarea>
                    </label>
                    <label class="cdx-field" for="cdx-rule-excl">Exclusions
                        <small>Une expression par ligne : un terme qui en fait partie est écarté (« barrage routier »).</small>
                        <textarea id="cdx-rule-excl" name="exclusions" rows="3"></textarea>
                    </label>
                    <label class="cdx-field" for="cdx-rule-req">Contexte requis
                        <small>Un mot par ligne : au moins un doit aussi être dans le texte. Vide : aucun contexte exigé.</small>
                        <textarea id="cdx-rule-req" name="requires" rows="2"></textarea>
                    </label>
                    <label class="cdx-field" for="cdx-rule-why">Motif (facultatif)
                        <input id="cdx-rule-why" type="text" name="reason" maxlength="300" placeholder="Visible dans l’historique">
                    </label>
                </div>
                <footer class="cdx-dialog__foot">
                    <button type="button" class="btn btn--ghost" data-cdx-close>Annuler</button>
                    <button type="submit" class="btn">Enregistrer</button>
                </footer>
            </form>
        </dialog>

        <dialog class="cdx-dialog cdx-dialog--small" id="cdx-dlg-reset" aria-labelledby="cdx-dlg-reset-title">
            <form method="post" action="<?= $h(url(\App\Controllers\Web\SseDetectionController::PATH . '/retablir')) ?>" class="cdx-dialog__card">
                <?= $csrf ?>
                <header class="cdx-dialog__head">
                    <div>
                        <h2 id="cdx-dlg-reset-title">Rétablir le lexique livré</h2>
                        <p class="cdx-desc">Tous les réglages de la communauté sont retirés ; l’historique et les retours d’expérience restent.</p>
                    </div>
                    <?= $close ?>
                </header>
                <div class="cdx-dialog__body">
                    <label class="cdx-check"><input type="checkbox" name="confirm" value="1" required> Je retire les <?= $customized ?> réglage<?= $customized > 1 ? 's' : '' ?> de la communauté</label>
                </div>
                <footer class="cdx-dialog__foot">
                    <button type="button" class="btn btn--ghost" data-cdx-close>Annuler</button>
                    <button type="submit" class="btn">Rétablir</button>
                </footer>
            </form>
        </dialog>
    <?php endif; ?>
</div>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
