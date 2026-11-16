<?php
declare(strict_types=1);

use App\Services\Sse\Detection\SseDetectionEngine;
use App\Services\Sse\Detection\SseDetectionService;

/**
 * Propositions de Cerbère-Lecture sur une remontée (type, thèmes, urgence), communes au back-office « Remontées » et au
 * bureau SSE. Incluse deux fois : $cdxPart = 'list' (les cartes, dans la colonne) puis 'dialogs' (fenêtres « Pourquoi ? » et
 * « Mauvaise détection », hors des panneaux masqués). Styles : cerbere-detections.css (.cdx-*) ; script :
 * cerbere-detections.js (fenêtres : focus gardé dans la fenêtre, Échap, retour au bouton ; aucun texte dans le script).
 *
 * @var list<array<string, mixed>> $cdxProposals préparées par SseDetectionService::proposals
 * @var string $cdxPart
 * @var string $cdxBase adresse des actions de la remontée (…/{source}/{id})
 * @var string $cdxCsrf champ caché du jeton
 * @var bool $cdxCanEdit l'urgence peut s'appliquer d'ici (droit « modifier les données »)
 * @var string|null $cdxManageUrl page « Détections Cerbère », si la session peut la lire
 * @var string $cdxBadge pastille « Proposé par Cerbère »
 * @var string $cdxBtn classes d'un bouton
 */
$cdh = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$cdxProposals = is_array($cdxProposals ?? null) ? $cdxProposals : [];
$cdxBtn = (string) ($cdxBtn ?? 'btn btn--ghost');
$cdxBadge = (string) ($cdxBadge ?? '<span class="cdx-badge">Proposé par Cerbère</span>');
$cdxManageUrl = $cdxManageUrl ?? null;
$cdxQuote = static function (array $e) use ($cdh): string {
    return '<q class="cdx-q">' . $cdh($e['before']) . '<mark>' . $cdh($e['match']) . '</mark>' . $cdh($e['after']) . '</q>';
};
$cdxBand = static fn (int $c): string => $c >= 75 ? 'high' : ($c >= 55 ? 'mid' : 'low');
$cdxClose = '<button type="button" class="cdx-dialog__close" data-cdx-close aria-label="Fermer la fenêtre"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg></button>';
$cdxHidden = static function (array $fields) use ($cdh): string {
    $out = '';
    foreach ($fields as $name => $value) {
        $out .= '<input type="hidden" name="' . $cdh($name) . '" value="' . $cdh($value) . '">';
    }

    return $out;
};

$cdxCard = static function (array $p) use ($cdh, $cdxQuote, $cdxBand, $cdxHidden, $cdxBtn, $cdxBadge, $cdxBase, $cdxCsrf, $cdxCanEdit): string {
    $dom = (string) $p['dom'];
    $conf = (int) $p['confidence'];
    $rules = array_column($p['rules'], 'id');
    $shown = array_slice($p['evidence'], 0, 3);
    $more = count($p['evidence']) - count($shown);
    $applicable = $p['action'] !== 'edit' || $cdxCanEdit;
    ob_start(); ?>
    <article class="cdx-card cdx-card--<?= $cdh($p['tier']) ?>" id="<?= $cdh($dom) ?>" aria-labelledby="<?= $cdh($dom) ?>-title">
        <div class="cdx-card__head">
            <p class="cdx-card__what" id="<?= $cdh($dom) ?>-title"><?= $cdxBadge ?> <span><?= $cdh($p['label']) ?> : <strong><?= $cdh($p['value_label']) ?></strong></span></p>
            <span class="cdx-conf cdx-conf--<?= $cdxBand($conf) ?>" title="Confiance de la détection"><?= $conf ?> %</span>
        </div>
        <p class="cdx-card__line">
            <span class="cdx-k">Détecté :</span>
            <?php foreach ($shown as $i => $e): ?><?= $i > 0 ? ' ' : '' ?><?= $cdxQuote($e) ?><?php if ($e['context'] !== 'normal'): ?> <span class="cdx-ctx">(<?= $cdh(SseDetectionEngine::contextLabel($e['context'])) ?>)</span><?php endif; ?><?php endforeach; ?>
            <?php if ($more > 0): ?> <span class="cdx-dim">et <?= $more ?> autre<?= $more > 1 ? 's' : '' ?></span><?php endif; ?>
        </p>
        <p class="cdx-card__meta"><?= count($rules) > 1 ? 'Règles' : 'Règle' ?> <span class="cdx-mono" translate="no"><?= $cdh(implode(', ', $rules)) ?></span> · confiance <?= $conf ?> %<?= $p['tier'] === 'review' ? ' · à vérifier' : '' ?></p>
        <div class="cdx-card__acts">
            <?php if ($applicable): ?>
                <form method="post" action="<?= $cdh($cdxBase . '/' . $p['action']) ?>" class="cdx-form">
                    <?= $cdxCsrf ?><?= $cdxHidden($p['fields']) ?>
                    <?php if (!empty($p['critical'])): ?>
                        <label class="cdx-check"><input type="checkbox" name="cerbere_checked" value="1" required> J’ai vérifié le texte</label>
                    <?php endif; ?>
                    <button type="submit" class="<?= $cdh($cdxBtn) ?>">Appliquer</button>
                </form>
            <?php endif; ?>
            <form method="post" action="<?= $cdh($cdxBase . '/cerbere') ?>" class="cdx-form">
                <?= $cdxCsrf ?><?= $cdxHidden($p['feedback'] + ['decision' => 'ignore']) ?>
                <button type="submit" class="<?= $cdh($cdxBtn) ?>">Ignorer</button>
            </form>
            <button type="button" class="<?= $cdh($cdxBtn) ?>" data-cdx-open="<?= $cdh($dom) ?>-bad" aria-haspopup="dialog">Mauvaise détection</button>
            <button type="button" class="<?= $cdh($cdxBtn) ?> cdx-why" data-cdx-open="<?= $cdh($dom) ?>-why" aria-haspopup="dialog">Pourquoi ?</button>
        </div>
        <?php if (!$applicable): ?><p class="cdx-dim">L’urgence s’applique depuis le back-office « Remontées ».</p><?php endif; ?>
    </article>
    <?php

    return (string) ob_get_clean();
};
?>
<?php if (($cdxPart ?? 'list') === 'list' && $cdxProposals !== []): ?>
    <?php
    $cdxSure = array_values(array_filter($cdxProposals, static fn (array $p): bool => $p['tier'] === 'visible'));
    $cdxReview = array_values(array_filter($cdxProposals, static fn (array $p): bool => $p['tier'] !== 'visible'));
    ?>
    <div class="cdx" data-cdx>
        <?php foreach ($cdxSure as $p): ?><?= $cdxCard($p) ?><?php endforeach; ?>
        <?php if ($cdxReview !== []): ?>
            <details class="cdx-review">
                <summary>À vérifier <span class="cdx-count"><?= count($cdxReview) ?></span> <span class="cdx-dim">confiance faible : à lire avant d’appliquer</span></summary>
                <?php foreach ($cdxReview as $p): ?><?= $cdxCard($p) ?><?php endforeach; ?>
            </details>
        <?php endif; ?>
    </div>
<?php elseif (($cdxPart ?? 'list') === 'dialogs'): ?>
    <?php foreach ($cdxProposals as $p): $dom = (string) $p['dom']; $friends = \App\Services\Sse\Detection\SseDetectionLexicon::shipped()['false_friends']; ?>
        <dialog class="cdx-dialog" id="<?= $cdh($dom) ?>-why" aria-labelledby="<?= $cdh($dom) ?>-why-title">
            <div class="cdx-dialog__card">
                <header class="cdx-dialog__head">
                    <div>
                        <h2 id="<?= $cdh($dom) ?>-why-title">Pourquoi cette proposition ?</h2>
                        <p class="cdx-desc"><?= $cdh($p['label']) ?> : <?= $cdh($p['value_label']) ?> · confiance <?= (int) $p['confidence'] ?> % · lexique <span translate="no"><?= $cdh($p['version']) ?></span></p>
                    </div>
                    <?= $cdxClose ?>
                </header>
                <div class="cdx-dialog__body">
                    <?php foreach ($p['rules'] as $r): ?>
                        <section class="cdx-rule" aria-labelledby="<?= $cdh($dom . '-' . $r['id']) ?>">
                            <h3 id="<?= $cdh($dom . '-' . $r['id']) ?>"><span class="cdx-mono" translate="no"><?= $cdh($r['id']) ?></span> · <?= $cdh($r['label']) ?></h3>
                            <p class="cdx-desc"><?= $cdh($r['explain']) ?> Poids <?= (int) $r['weight'] ?>.</p>
                            <h4>Termes trouvés</h4>
                            <ul class="cdx-hits">
                                <?php foreach ($r['hits'] as $hit): ?>
                                    <li><?= $cdxQuote($hit['snippet']) ?><span class="cdx-dim">terme « <?= $cdh($hit['term']) ?> » · <?= $cdh(SseDetectionEngine::contextLabel($hit['context'])) ?><?= $hit['cue'] !== null ? ' (« ' . $cdh($hit['cue']) . ' »)' : '' ?><?= $hit['segment'] === 'title' ? ' · dans le titre' : '' ?></span></li>
                                <?php endforeach; ?>
                            </ul>
                            <h4>Exclusions vérifiées</h4>
                            <?php if ($r['excluded'] !== []): ?>
                                <ul class="cdx-hits">
                                    <?php foreach ($r['excluded'] as $x): ?><li><?= $cdxQuote($x['snippet']) ?><span class="cdx-dim">écarté : fait partie de « <?= $cdh($x['by']) ?> »</span></li><?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <p class="cdx-desc"><?= $r['exclusions'] !== [] ? 'Exclusions de la règle : ' . $cdh(implode(', ', $r['exclusions'])) . '.' : 'Cette règle n’a pas d’exclusion.' ?>
                                Les mots sont comparés entiers : <?= $cdh(implode(', ', array_slice(array_keys($friends), 0, 6))) ?>… ne déclenchent jamais un terme qu’ils contiennent.<?php if ($r['false_friends'] !== []): ?> Faux amis écartés ici : <?= $cdh(implode(', ', $r['false_friends'])) ?>.<?php endif; ?></p>
                            <?php if ($r['requires'] !== []): ?>
                                <p class="cdx-desc">Contexte requis (un de ces mots aussi dans le texte) : <?= $cdh(implode(', ', $r['requires'])) ?>.</p>
                            <?php endif; ?>
                        </section>
                    <?php endforeach; ?>
                    <section class="cdx-rule" aria-labelledby="<?= $cdh($dom) ?>-calc">
                        <h3 id="<?= $cdh($dom) ?>-calc">Calcul de la confiance</h3>
                        <ul class="cdx-signals">
                            <?php foreach ($p['signals'] as $s): ?><li><span><?= $cdh($s['label']) ?></span><strong><?= (int) $s['points'] > 0 ? '+' . (int) $s['points'] : '—' ?></strong></li><?php endforeach; ?>
                        </ul>
                        <p class="cdx-desc">Montrée à partir de 55 %, repliée « à vérifier » entre 30 et 54 %. Rien n’est appliqué sans votre clic<?= !empty($p['critical']) ? ' ; une urgence Critique demande en plus de cocher « J’ai vérifié le texte »' : '' ?>.</p>
                    </section>
                    <section class="cdx-rule" aria-labelledby="<?= $cdh($dom) ?>-adjust">
                        <h3 id="<?= $cdh($dom) ?>-adjust">Désactiver ou ajuster</h3>
                        <p class="cdx-desc">Dans « Détections Cerbère », la règle peut être désactivée, recevoir une exclusion ou un poids plus bas, pour toute la communauté. « Mauvaise détection » compte dans son taux de rejet.</p>
                        <?php if ($cdxManageUrl !== null): ?>
                            <a class="<?= $cdh($cdxBtn) ?>" href="<?= $cdh($cdxManageUrl . '#regle-' . (string) $p['rules'][0]['id']) ?>">Ouvrir la règle <?= $cdh((string) $p['rules'][0]['id']) ?></a>
                        <?php endif; ?>
                    </section>
                </div>
                <footer class="cdx-dialog__foot"><button type="button" class="<?= $cdh($cdxBtn) ?>" data-cdx-close>Fermer</button></footer>
            </div>
        </dialog>

        <dialog class="cdx-dialog cdx-dialog--small" id="<?= $cdh($dom) ?>-bad" aria-labelledby="<?= $cdh($dom) ?>-bad-title">
            <form method="post" action="<?= $cdh($cdxBase . '/cerbere') ?>" class="cdx-dialog__card">
                <?= $cdxCsrf ?><?= $cdxHidden($p['feedback'] + ['decision' => 'reject']) ?>
                <header class="cdx-dialog__head">
                    <div>
                        <h2 id="<?= $cdh($dom) ?>-bad-title">Mauvaise détection</h2>
                        <p class="cdx-desc">La proposition disparaît de cette remontée ; le motif compte dans le taux de rejet de la règle.</p>
                    </div>
                    <?= $cdxClose ?>
                </header>
                <div class="cdx-dialog__body">
                    <label class="cdx-field" for="<?= $cdh($dom) ?>-reason">Motif
                        <input id="<?= $cdh($dom) ?>-reason" type="text" name="reason" minlength="3" maxlength="200" required list="<?= $cdh($dom) ?>-reasons" autocomplete="off">
                    </label>
                    <datalist id="<?= $cdh($dom) ?>-reasons"><?php foreach (SseDetectionService::FEEDBACK_REASONS as $why): ?><option value="<?= $cdh($why) ?>"></option><?php endforeach; ?></datalist>
                </div>
                <footer class="cdx-dialog__foot">
                    <button type="button" class="<?= $cdh($cdxBtn) ?>" data-cdx-close>Annuler</button>
                    <button type="submit" class="<?= $cdh($cdxBtn) ?> cdx-primary">Signaler</button>
                </footer>
            </form>
        </dialog>
    <?php endforeach; ?>
<?php endif; ?>
