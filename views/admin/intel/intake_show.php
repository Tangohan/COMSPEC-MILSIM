<?php
declare(strict_types=1);

use App\Services\Intel\IntelIntakePresenter as P;
use App\Services\Intel\IntelIntakeService;
use App\Services\Intel\IntelRedaction;
use App\Services\Sse\SseRedactionService;
use App\Support\SseFieldNoteCatalog;

/**
 * Back-office « Remontées » — traitement d'une remontée (compte rendu ou fiche de renseignement).
 * En-tête (référence, pastilles, actions), cycle de prise en compte, puis deux colonnes : le contenu et le fil d'activité
 * à gauche, des onglets à droite (Traitement, Émetteur, Liens, Historique, Zone sensible). Tous les formulaires
 * s'ouvrent dans des fenêtres. Styles : public/assets/css/remontees.css ; script : public/assets/js/remontees.js (aucun texte).
 *
 * @var array<string, mixed> $intakeDesk préparé par IntelIntakeDesk::view
 * @var array<string, string> $intakeTypes
 * @var list<array{id: int, label: string}> $intakeMembers
 * @var array<string, string> $intakeLevels
 * @var string $csrfToken
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$d = $intakeDesk;
$it = $d['item'];
$row = $it['row'];
$src = (string) $it['source'];
$rights = $d['rights'];
$level = (string) $d['viewer']['level'];
$me = (int) $d['viewer']['id'];
$base = url('back-office/remontees/' . $src . '/' . (int) $it['id']);
$csrf = '<input type="hidden" name="_csrf_token" value="' . $h($csrfToken) . '">';
$redactions = $it['redactions'];
$state = (string) $it['state'];
$deleted = !empty($it['deleted']);
$open = !empty($d['open']);
$canTreat = $rights['treat'] && !$deleted;
$author = (string) ($it['author'] !== '' ? $it['author'] : 'Terrain');
$thing = $src === 'cr' ? 'ce compte rendu' : 'cette fiche';
$stateInfo = is_array($it['state_info'] ?? null) ? $it['state_info'] : [];
$sender = $it['sender'];
$senderKey = isset($intakeSenderKey) && is_string($intakeSenderKey) && $intakeSenderKey !== '' && $intakeSenderKey !== 'inconnu' ? $intakeSenderKey : null;
$itemUrl = static fn (string $s, int $i): string => url('back-office/remontees/' . $s . '/' . $i);
$text = static fn (string $field): string => P::textHtml($row, $field, $redactions, $level, $me);
$initials = static fn (string $s): string => mb_strtoupper(mb_substr(trim($s) !== '' ? trim($s) : '?', 0, 2));
$ico = static fn (string $d): string => '<svg class="rmt-i" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="' . $d . '"/></svg>';
$icons = [
    'take' => 'M5 12.5l4.5 4.5L19 7.5',
    'work' => 'M12 6v6l4 2M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z',
    'done' => 'M4 12.5l5 5L20 6.5',
    'forward' => 'M4 12h15M13 6l6 6-6 6',
    'dup' => 'M9 9h11v11H9zM5 15V4h11',
    'convert' => 'M7 7h11l-3-3M17 17H6l3 3M18 7v4M6 17v-4',
    'link' => 'M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1',
    'more' => 'M5 12h.01M12 12h.01M19 12h.01',
    'close' => 'M6 6l12 12M18 6L6 18',
    'copy' => 'M9 9h11v11H9zM5 15V4h11',
    'bot' => 'M5 9h14v10H5zM12 9V5M9 14h.01M15 14h.01M2 13v3M22 13v3',
    'eye' => 'M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12zM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z',
    'spark' => 'M12 3l2 6 6 2-6 2-2 6-2-6-6-2 6-2z',
];
$badge = static fn (array $b): string => '<li class="rmt-badge rmt-badge--' . $h($b['tone']) . '"' . ($b['title'] !== '' ? ' title="' . $h($b['title']) . '"' : '') . '><span class="rmt-badge__dot" aria-hidden="true"></span>' . $h($b['label']) . '</li>';
$chips = static function (string $kind, string $target) use ($d, $h): string {
    $out = '';
    foreach ($d['reasons'][$kind] ?? [] as $reason) {
        $out .= '<button type="button" class="rmt-chip" data-rmt-fill="' . $h($target) . '" data-value="' . $h($reason) . '">' . $h($reason) . '</button>';
    }

    return $out !== '' ? '<div class="rmt-chips" role="group" aria-label="Motifs proposés">' . $out . '</div>' : '';
};
$dialogHead = static fn (string $id, string $title, string $desc): string => '<header class="rmt-dialog__head"><div><h2 id="' . $h($id) . '-title">' . $h($title) . '</h2><p class="rmt-desc">' . $h($desc) . '</p></div>'
    . '<button type="button" class="rmt-iconbtn" data-rmt-close aria-label="Fermer la fenêtre" title="Fermer">' . $ico($icons['close']) . '</button></header>';
$cerbere = '<span class="rmt-cerbere">' . $ico($icons['spark']) . 'Proposé par Cerbère</span>';

// Position et contenu.
$grid = trim((string) ($row['grid_reference'] ?? ''));
$hasXy = isset($row['pos_x'], $row['pos_y']) && ((float) $row['pos_x'] !== 0.0 || (float) $row['pos_y'] !== 0.0);
$xy = $hasXy ? 'X ' . (int) round((float) $row['pos_x']) . ' / Y ' . (int) round((float) $row['pos_y']) : '';
$latlng = !empty($row['lat']) && !empty($row['lng']) ? round((float) $row['lat'], 5) . ', ' . round((float) $row['lng'], 5) : '';
$placeField = $src === 'cr' ? 'location_description' : 'place_label';
$titleField = $src === 'cr' ? 'summary' : 'title';
$bodyFields = $src === 'cr' ? ['details' => 'Détails', 'remarks' => 'Remarques', 'action_taken' => 'Suite donnée'] : ['body' => 'Texte', 'triage_note' => 'Note de tri'];
$observed = P::when((string) ($src === 'cr' ? ($row['event_timestamp'] ?? '') : ($row['observed_at'] ?? '')));
$facts = array_filter([
    'Observée le' => $observed,
    'Urgence' => (string) $d['urgency']['label'],
    'Diffusion' => (string) $d['classification'],
    'Recueil' => (string) $d['intel_source'],
    'Fiabilité' => (string) $d['reliability'],
    'Origine' => (string) $d['origin'],
    'Unité' => (string) ($sender['unit'] ?? ''),
    'Mission' => (string) ($row['mission_key'] ?? ''),
    'Groupe date-heure' => (string) ($row['dtg'] ?? ''),
]);
$tabs = ['traitement' => 'Traitement', 'emetteur' => 'Émetteur', 'liens' => 'Liens', 'historique' => 'Historique', 'sensible' => 'Zone sensible'];
$channels = P::CHANNELS;
$delay = $d['delay'];
?>
<div class="rmt rmt-show" data-rmt data-rmt-copied="Copié dans le presse-papiers." data-rmt-copy-failed="La copie a échoué : sélectionnez le texte et copiez-le à la main." data-rmt-empty="Aucun résultat.">
    <nav class="rmt-crumbs" aria-label="Fil d’Ariane"><a href="<?= $h(url('back-office/remontees')) ?>">Remontées</a> <span aria-hidden="true">/</span> <span><?= $h($it['ref']) ?></span></nav>

    <header class="rmt-head">
        <div class="rmt-head__main">
            <h1 class="rmt-title"><span class="rmt-title__ref"><?= $h($it['ref']) ?></span> <span class="rmt-title__kind"><?= $h($it['type_label']) ?></span></h1>
            <p class="rmt-desc"><strong><?= $h($author) ?></strong> a remonté <?= $thing ?> le <?= $h(P::when((string) $it['at'])) ?><?php if (!str_starts_with(P::ago((string) $it['at']), 'le ')): ?> (<?= $h(P::ago((string) $it['at'])) ?>)<?php endif; ?>.</p>
            <ul class="rmt-badges" aria-label="Pastilles de la remontée">
                <?php foreach ($d['badges'] as $b): ?><?= $badge($b) ?><?php endforeach; ?>
            </ul>
        </div>
        <div class="rmt-actions">
            <?php if ($canTreat && $state === 'a_traiter'): ?>
                <form method="post" action="<?= $h($base . '/take') ?>"><?= $csrf ?><button type="submit" class="rmt-btn rmt-btn--primary" title="Elle vous est attribuée et l’heure est notée."><?= $ico($icons['take']) ?>Prendre en compte</button></form>
            <?php endif; ?>
            <?php if ($canTreat && $state === 'prise_en_compte'): ?>
                <form method="post" action="<?= $h($base . '/state') ?>"><?= $csrf ?><input type="hidden" name="state" value="en_cours"><button type="submit" class="rmt-btn rmt-btn--primary"><?= $ico($icons['work']) ?>Mettre en exploitation</button></form>
            <?php endif; ?>
            <?php if ($canTreat && $state !== 'exploitee'): ?>
                <form method="post" action="<?= $h($base . '/state') ?>"><?= $csrf ?><input type="hidden" name="state" value="exploitee"><button type="submit" class="rmt-btn<?= in_array($state, ['en_cours', 'transmise'], true) ? ' rmt-btn--primary' : '' ?>"><?= $ico($icons['done']) ?>Marquer exploitée</button></form>
            <?php endif; ?>
            <?php if ($canTreat): ?>
                <button type="button" class="rmt-btn" data-rmt-open="rmt-dlg-transmettre" aria-haspopup="dialog"><?= $ico($icons['forward']) ?>Transmettre</button>
                <button type="button" class="rmt-btn" data-rmt-open="rmt-dlg-doublon" aria-haspopup="dialog"><?= $ico($icons['dup']) ?>Doublon</button>
            <?php endif; ?>
            <?php if ($rights['convert'] && !$deleted): ?>
                <button type="button" class="rmt-btn" data-rmt-open="rmt-dlg-convertir" aria-haspopup="dialog"><?= $ico($icons['convert']) ?>Convertir</button>
            <?php endif; ?>
            <?php if ($rights['link'] && !$deleted && $src === 'fiche'): ?>
                <button type="button" class="rmt-btn" data-rmt-open="rmt-dlg-relier" aria-haspopup="dialog"><?= $ico($icons['link']) ?>Relier</button>
            <?php endif; ?>
            <details class="rmt-more" data-rmt-more>
                <summary class="rmt-btn rmt-btn--icon" aria-label="Autres actions" title="Autres actions"><?= $ico($icons['more']) ?><span class="rmt-more__label">Autres actions</span></summary>
                <div class="rmt-more__menu">
                    <?php if ($canTreat && $open): ?>
                        <button type="button" data-rmt-open="rmt-dlg-clore" aria-haspopup="dialog">Classer sans suite</button>
                    <?php endif; ?>
                    <?php if ($canTreat && !$open): ?>
                        <form method="post" action="<?= $h($base . '/state') ?>"><?= $csrf ?><input type="hidden" name="state" value="a_traiter"><button type="submit">Rouvrir la remontée</button></form>
                    <?php endif; ?>
                    <?php if ($rights['discord']): ?>
                        <button type="button" data-rmt-open="rmt-dlg-discord" aria-haspopup="dialog">Notifier sur Discord</button>
                    <?php endif; ?>
                    <button type="button" data-rmt-copy="<?= $h($it['ref']) ?>">Copier la référence</button>
                    <button type="button" data-rmt-copy="<?= $h($base) ?>">Copier le lien</button>
                    <?php if (!empty($intakeSsePortal) && !$deleted): ?>
                        <a href="<?= $h(url($src === 'fiche' ? 'atak/sse/fiches/' . (int) $it['id'] : 'atak/sse/remontees/cr/' . (int) $it['id'])) ?>">Ouvrir dans le bureau SSE</a>
                    <?php endif; ?>
                    <?php if ($rights['manage'] && !$deleted): ?>
                        <button type="button" data-rmt-open="rmt-dlg-modifier" aria-haspopup="dialog">Modifier les données</button>
                    <?php endif; ?>
                    <?php if ($rights['redact'] && !$deleted): ?>
                        <button type="button" data-rmt-open="rmt-dlg-caviarder" aria-haspopup="dialog">Caviarder un passage</button>
                    <?php endif; ?>
                    <?php if ($rights['manage'] && !$deleted): ?>
                        <button type="button" class="is-danger" data-rmt-open="rmt-dlg-supprimer" aria-haspopup="dialog">Supprimer la remontée</button>
                    <?php endif; ?>
                </div>
            </details>
        </div>
    </header>

    <section class="rmt-cycle<?= $delay['late'] ? ' is-late' : '' ?>" aria-label="Cycle de prise en compte">
        <ol class="rmt-steps">
            <?php foreach ($d['cycle'] as $n => $step): ?>
                <li class="rmt-step is-<?= $h($step['status']) ?>"<?= $step['status'] === 'current' ? ' aria-current="step"' : '' ?>>
                    <span class="rmt-step__mark" aria-hidden="true"><?= $step['status'] === 'done' ? $ico($icons['take']) : $n + 1 ?></span>
                    <span class="rmt-step__txt"><strong><?= $h($step['label']) ?></strong><?php if ($step['at'] !== '' && $step['status'] !== 'todo'): ?><span><?= $h(P::when($step['at'])) ?></span><?php endif; ?></span>
                </li>
            <?php endforeach; ?>
        </ol>
        <p class="rmt-cycle__delay"><strong><?= $h($delay['label']) ?></strong> · <?= $h($delay['target_label']) ?> pour une urgence « <?= $h($d['urgency']['label']) ?> »<?php if ($it['ack_by'] !== '' && $delay['acked']): ?> · par <?= $h($it['ack_by']) ?><?php endif; ?><?php if ($stateInfo !== [] && !empty($stateInfo['forwarded_to'])): ?> · transmise à <?= $h((string) $stateInfo['forwarded_to']) ?><?php endif; ?><?php if (!empty($stateInfo['duplicate_of']['ref'])): ?> · doublon de <a href="<?= $h($itemUrl((string) $stateInfo['duplicate_of']['source'], (int) $stateInfo['duplicate_of']['id'])) ?>"><?= $h((string) $stateInfo['duplicate_of']['ref']) ?></a><?php endif; ?></p>
    </section>

    <div class="rmt-layout">
        <div class="rmt-main">
            <section class="rmt-panel" aria-labelledby="rmt-contenu">
                <h2 id="rmt-contenu" class="rmt-h2">Contenu</h2>
                <p class="rmt-desc">Ce que l’émetteur a écrit, tel que votre habilitation vous le laisse lire.</p>

                <?php if (($titleHtml = $text($titleField)) !== ''): ?>
                    <p class="rmt-doc__title"><?= $titleHtml ?></p>
                <?php endif; ?>
                <?php foreach ($bodyFields as $field => $label): $html = $text($field); if ($html === '') { continue; } ?>
                    <div class="rmt-doc__block">
                        <h3 class="rmt-h3"><?= $h($label) ?></h3>
                        <p class="rmt-doc__text"><?= $html ?></p>
                    </div>
                <?php endforeach; ?>

                <?php if ($d['themes'] !== []): ?>
                    <div class="rmt-doc__block">
                        <h3 class="rmt-h3">Thèmes</h3>
                        <ul class="rmt-themes">
                            <?php foreach ($d['themes'] as $theme): ?>
                                <li class="rmt-theme rmt-theme--<?= $h($theme['tone']) ?>" title="<?= $h(SseFieldNoteCatalog::THEMES[$theme['code']]['hint'] ?? '') ?>"><?= $h($theme['label']) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="rmt-doc__block">
                    <h3 class="rmt-h3">Lieu et position</h3>
                    <dl class="rmt-rows">
                        <div><dt>Lieu</dt><dd><?= ($place = $text($placeField)) !== '' ? $place : '<span class="rmt-dim">Non précisé</span>' ?></dd></div>
                        <div><dt>Carroyage</dt><dd>
                            <?php if ($grid !== ''): ?>
                                <span class="rmt-mono"><?= $h($grid) ?></span>
                                <button type="button" class="rmt-btn rmt-btn--mini" data-rmt-copy="<?= $h($grid) ?>"><?= $ico($icons['copy']) ?>Copier</button>
                            <?php else: ?><span class="rmt-dim">Aucun</span><?php endif; ?>
                        </dd></div>
                        <?php if ($xy !== ''): ?>
                            <div><dt>Coordonnées carte</dt><dd><span class="rmt-mono"><?= $h($xy) ?></span> <button type="button" class="rmt-btn rmt-btn--mini" data-rmt-copy="<?= $h($xy) ?>"><?= $ico($icons['copy']) ?>Copier</button></dd></div>
                        <?php endif; ?>
                        <?php if ($latlng !== ''): ?>
                            <div><dt>Coordonnées GPS</dt><dd><span class="rmt-mono"><?= $h($latlng) ?></span></dd></div>
                        <?php endif; ?>
                    </dl>
                </div>

                <div class="rmt-doc__block">
                    <h3 class="rmt-h3">Repères</h3>
                    <dl class="rmt-rows rmt-rows--two">
                        <?php foreach ($facts as $k => $v): ?><div><dt><?= $h($k) ?></dt><dd><?= $h($v) ?></dd></div><?php endforeach; ?>
                    </dl>
                </div>

                <?php if ($it['structured'] !== []): ?>
                    <div class="rmt-doc__block">
                        <h3 class="rmt-h3">Champs du compte rendu</h3>
                        <dl class="rmt-rows rmt-rows--two">
                            <?php foreach ($it['structured'] as $k => $v): if (is_array($v)) { $v = implode(', ', array_map(static fn ($x): string => is_scalar($x) ? (string) $x : 'liste', $v)); } ?>
                                <div><dt><?= $h(\App\Support\AtakIcemanReportCatalog::fieldLabelFr((string) $it['type'], (string) $k)) ?></dt><dd translate="no"><?= $h((string) $v) ?></dd></div>
                            <?php endforeach; ?>
                        </dl>
                    </div>
                <?php endif; ?>

                <?php if ($it['attachments'] !== []): ?>
                    <div class="rmt-doc__block">
                        <h3 class="rmt-h3">Pièces jointes</h3>
                        <ul class="rmt-atts">
                            <?php foreach ($it['attachments'] as $a): $cap = $a['caption'] !== '' ? $a['caption'] : ($a['original_name'] !== '' ? $a['original_name'] : 'Pièce jointe'); ?>
                                <li class="rmt-att<?= $a['blurred'] ? ' is-blurred' : '' ?>">
                                    <?php if ($a['is_image'] && $a['url']): ?>
                                        <a href="<?= $h($a['url']) ?>" target="_blank" rel="noopener" class="rmt-att__img" data-recon-viewer="rmt-att" data-title="<?= $h($cap) ?>" data-source="<?= $h($a['kind_label']) ?>" data-blurred="<?= $a['blurred'] ? '1' : '0' ?>"><img src="<?= $h($a['url']) ?>" alt="<?= $h($cap) ?>" loading="lazy"></a>
                                    <?php elseif ($a['url']): ?>
                                        <a href="<?= $h($a['url']) ?>" target="_blank" rel="noopener" class="rmt-att__img rmt-att__img--doc">Ouvrir le document</a>
                                    <?php else: ?>
                                        <span class="rmt-att__img rmt-att__img--doc">Fichier indisponible</span>
                                    <?php endif; ?>
                                    <div class="rmt-att__meta">
                                        <span><strong><?= $h($a['kind_label']) ?></strong><?= $a['caption'] !== '' ? ' · ' . $h($a['caption']) : '' ?><?= !empty($a['taken_at']) ? ' · ' . $h(P::when((string) $a['taken_at'])) : '' ?></span>
                                        <?php if (empty($a['readonly']) && $rights['manage'] && !$deleted): ?>
                                            <form method="post" action="<?= $h($base . '/blur') ?>">
                                                <?= $csrf ?>
                                                <input type="hidden" name="attachment_id" value="<?= (int) $a['id'] ?>">
                                                <input type="hidden" name="blurred" value="<?= $a['blurred'] ? '0' : '1' ?>">
                                                <button type="submit" class="rmt-btn rmt-btn--mini"><?= $a['blurred'] ? 'Retirer le flou' : 'Flouter' ?></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </section>

            <section class="rmt-panel" aria-labelledby="rmt-fil" id="fil">
                <h2 id="rmt-fil" class="rmt-h2">Activité</h2>
                <p class="rmt-desc">États, commentaires, transmissions et lectures, du plus ancien au plus récent.</p>
                <ol class="rmt-timeline">
                    <li class="rmt-tl rmt-tl--recu">
                        <span class="rmt-tl__dot" aria-hidden="true"></span>
                        <div class="rmt-tl__body"><p><strong><?= $h($author) ?></strong> a remonté <?= $thing ?> <span class="rmt-dim" title="<?= $h(P::when((string) $it['at'])) ?>"><?= $h(P::ago((string) $it['at'])) ?></span></p></div>
                    </li>
                    <?php foreach ($d['timeline'] as $entry): ?>
                        <?php if ($entry['type'] === 'comment'): $e = $entry['event']; ?>
                            <li class="rmt-tl rmt-tl--comment" id="c<?= (int) $e['id'] ?>">
                                <span class="rmt-avatar" aria-hidden="true"><?= $h($initials((string) $e['actor'])) ?></span>
                                <div class="rmt-tl__body">
                                    <p><strong><?= $h($e['actor']) ?></strong> a commenté <span class="rmt-dim" title="<?= $h(P::when((string) $e['at'])) ?>"><?= $h(P::ago((string) $e['at'])) ?></span></p>
                                    <blockquote class="rmt-quote" translate="no"><?= nl2br($h($e['body']), false) ?></blockquote>
                                </div>
                            </li>
                        <?php elseif ($entry['type'] === 'read'): $r = $entry['read']; ?>
                            <li class="rmt-tl rmt-tl--read">
                                <span class="rmt-tl__dot" aria-hidden="true"></span>
                                <div class="rmt-tl__body"><p><strong><?= $h($r['label']) ?></strong> <span>a lu la remontée</span> <span>(<?= $h($channels[$r['channel']] ?? $r['channel']) ?><?= $r['count'] > 1 ? ', ' . (int) $r['count'] . ' fois' : '' ?>)</span> <span class="rmt-dim" title="<?= $h(P::when((string) $r['first'])) ?>"><?= $h(P::ago((string) $r['first'])) ?></span></p></div>
                            </li>
                        <?php else: $e = $entry['event']; ?>
                            <li class="rmt-tl rmt-tl--<?= $h($e['kind']) ?>">
                                <span class="rmt-tl__dot" aria-hidden="true"></span>
                                <div class="rmt-tl__body">
                                    <p><strong><?= $h($e['actor']) ?></strong> <?= P::eventLine($e, $itemUrl) ?> <span class="rmt-dim" title="<?= $h(P::when((string) $e['at'])) ?>"><?= $h(P::ago((string) $e['at'])) ?></span></p>
                                    <?php if ($e['body'] !== ''): ?><blockquote class="rmt-quote" translate="no"><?= $h($e['body']) ?></blockquote><?php endif; ?>
                                </div>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ol>
                <?php if ($canTreat): ?>
                    <form class="rmt-composer" method="post" action="<?= $h($base . '/comment') ?>" id="fil-fin">
                        <?= $csrf ?>
                        <label for="rmt-comment" class="rmt-label">Ajouter un commentaire</label>
                        <textarea id="rmt-comment" name="body" rows="3" maxlength="8000" placeholder="Analyse, recoupement, consigne d’exploitation…" required></textarea>
                        <div class="rmt-composer__foot"><button type="submit" class="rmt-btn rmt-btn--primary">Commenter</button></div>
                    </form>
                <?php endif; ?>
            </section>
        </div>

        <aside class="rmt-side" aria-label="Traitement de la remontée">
            <div class="rmt-tabs" role="tablist" aria-label="Rubriques de la remontée">
                <?php foreach ($tabs as $key => $label): ?>
                    <button type="button" role="tab" id="rmt-tab-<?= $h($key) ?>" aria-controls="rmt-panel-<?= $h($key) ?>" aria-selected="<?= $key === 'traitement' ? 'true' : 'false' ?>"<?= $key === 'traitement' ? '' : ' tabindex="-1"' ?> data-rmt-tab="<?= $h($key) ?>"><?= $h($label) ?></button>
                <?php endforeach; ?>
            </div>

            <section class="rmt-tabpanel" role="tabpanel" id="rmt-panel-traitement" aria-labelledby="rmt-tab-traitement" data-rmt-panel="traitement">
                <h2 class="rmt-h2">Traitement</h2>
                <p class="rmt-desc"><?= $h(IntelIntakeService::STATE_HINTS[$state] ?? '') ?></p>

                <div class="rmt-block">
                    <h3 class="rmt-h3">Traitant</h3>
                    <?php if ($it['assignee'] !== null): ?>
                        <p class="rmt-person"><span class="rmt-avatar" aria-hidden="true"><?= $h($initials($it['assignee']['label'])) ?></span><?= $h($it['assignee']['label']) ?></p>
                    <?php else: ?>
                        <p class="rmt-dim">Personne n’en répond pour l’instant.</p>
                    <?php endif; ?>
                    <?php if ($canTreat): ?>
                        <form method="post" action="<?= $h($base . '/assign') ?>" class="rmt-inline">
                            <?= $csrf ?>
                            <label class="rmt-sr" for="rmt-assign">Attribuer à</label>
                            <select id="rmt-assign" name="user_id">
                                <option value="0">Personne</option>
                                <option value="<?= $me ?>">Moi</option>
                                <?php foreach ($intakeMembers as $m): ?><option value="<?= (int) $m['id'] ?>"<?= ($it['assignee']['id'] ?? 0) === $m['id'] ? ' selected' : '' ?>><?= $h($m['label']) ?></option><?php endforeach; ?>
                            </select>
                            <button type="submit" class="rmt-btn"><?= $it['assignee'] !== null ? 'Réattribuer' : 'Attribuer' ?></button>
                        </form>
                        <?php if ($d['assignee_proposal'] !== null): $ap = $d['assignee_proposal']; ?>
                            <form method="post" action="<?= $h($base . '/assign') ?>" class="rmt-proposal">
                                <?= $csrf ?>
                                <input type="hidden" name="user_id" value="<?= (int) $ap['user_id'] ?>">
                                <p><?= $cerbere ?> <strong><?= $h($ap['label']) ?></strong></p>
                                <p class="rmt-dim"><?= $h($ap['reason']) ?></p>
                                <button type="submit" class="rmt-btn rmt-btn--mini">Attribuer à <?= $h($ap['label']) ?></button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="rmt-block">
                    <h3 class="rmt-h3">Type de <?= $src === 'cr' ? 'compte rendu' : 'fiche' ?></h3>
                    <?php if ($canTreat): ?>
                        <form method="post" action="<?= $h($base . '/type') ?>" class="rmt-inline">
                            <?= $csrf ?>
                            <label class="rmt-sr" for="rmt-type">Type</label>
                            <select id="rmt-type" name="type">
                                <?php foreach ($intakeTypes as $code => $label): ?><option value="<?= $h($code) ?>"<?= $it['type'] === $code ? ' selected' : '' ?>><?= $h($code . ' · ' . $label) ?></option><?php endforeach; ?>
                            </select>
                            <button type="submit" class="rmt-btn">Changer</button>
                        </form>
                    <?php else: ?>
                        <p><?= $h($it['type'] . ' · ' . $it['type_label']) ?></p>
                    <?php endif; ?>
                </div>

                <?php if ($d['proposals'] !== []): ?>
                    <div class="rmt-block">
                        <h3 class="rmt-h3">Propositions</h3>
                        <ul class="rmt-proposals">
                            <?php foreach ($d['proposals'] as $p): ?>
                                <li>
                                    <form method="post" action="<?= $h($base . '/' . $p['action']) ?>" class="rmt-proposal">
                                        <?= $csrf ?>
                                        <?php foreach ($p['fields'] as $name => $value): ?><input type="hidden" name="<?= $h($name) ?>" value="<?= $h($value) ?>"><?php endforeach; ?>
                                        <p><?= $cerbere ?> <?= $h($p['label']) ?> : <strong><?= $h($p['value_label']) ?></strong></p>
                                        <p class="rmt-dim"><?= $h($p['reason']) ?></p>
                                        <button type="submit" class="rmt-btn rmt-btn--mini">Appliquer</button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($rights['discord']): ?>
                    <div class="rmt-block">
                        <h3 class="rmt-h3">Discord</h3>
                        <?php if ($d['discord']['ready']): ?>
                            <p>Les notifications partent dans <strong translate="no"><?= $h($d['discord']['channel']) ?></strong>.</p>
                        <?php else: ?>
                            <p class="rmt-dim"><?= $h($d['discord']['reason']) ?></p>
                        <?php endif; ?>
                        <?php if ($d['discord']['journal'] !== []): ?>
                            <ul class="rmt-list">
                                <?php foreach ($d['discord']['journal'] as $j): ?>
                                    <li><?= $h($j['label']) ?> <span class="rmt-dim"><?= $j['sent_at'] !== '' ? 'envoyée le ' . $h(P::when($j['sent_at'])) : 'en attente du bot' ?></span></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <button type="button" class="rmt-btn" data-rmt-open="rmt-dlg-discord" aria-haspopup="dialog"><?= $ico($icons['bot']) ?>Notifier sur Discord</button>
                    </div>
                <?php endif; ?>
            </section>

            <section class="rmt-tabpanel" role="tabpanel" id="rmt-panel-emetteur" aria-labelledby="rmt-tab-emetteur" data-rmt-panel="emetteur" hidden>
                <h2 class="rmt-h2">Émetteur</h2>
                <p class="rmt-desc">Qui a envoyé la remontée, et depuis quel compte.</p>
                <p class="rmt-person"><span class="rmt-avatar" aria-hidden="true"><?= $h($initials((string) ($sender['callsign'] ?: $author))) ?></span><?= $h($sender['callsign'] !== '' ? $sender['callsign'] : 'Terrain') ?></p>
                <dl class="rmt-rows">
                    <?php if ($sender['unit'] !== ''): ?><div><dt>Unité</dt><dd><?= $h($sender['unit']) ?></dd></div><?php endif; ?>
                    <div><dt>Compte</dt><dd>
                        <?php if (is_array($sender['account'])): $accName = $sender['account']['name'] !== '' ? $sender['account']['name'] : $sender['account']['label']; ?>
                            <?php if (!empty($intakeCanSeeMembers)): ?><a href="<?= $h(url('back-office/users/' . (int) $sender['account']['id'])) ?>"><?= $h($accName) ?></a><?php else: ?><?= $h($accName) ?><?php endif; ?>
                        <?php elseif ($sender['user_id'] !== null): ?>Compte n° <?= (int) $sender['user_id'] ?>
                        <?php else: ?><span class="rmt-dim">Non rattaché</span><?php endif; ?>
                    </dd></div>
                    <div><dt>Steam</dt><dd>
                        <?php if ($sender['steam'] === ''): ?>
                            <span class="rmt-dim">Inconnu</span>
                        <?php else: ?>
                            <span class="rmt-mono" data-rmt-steam-out><?= $h($d['steam_masked']) ?></span>
                            <?php if ($rights['steam']): ?>
                                <form method="post" action="<?= $h($base . '/steam') ?>" class="rmt-steam" data-rmt-steam>
                                    <?= $csrf ?>
                                    <button type="submit" class="rmt-btn rmt-btn--mini" data-rmt-steam-show><?= $ico($icons['eye']) ?>Afficher</button>
                                    <button type="button" class="rmt-btn rmt-btn--mini" data-rmt-steam-copy hidden><?= $ico($icons['copy']) ?>Copier</button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </dd></div>
                </dl>
                <?php if ($sender['steam'] !== ''): ?>
                    <p class="rmt-dim"><?= $rights['steam'] ? 'L’affichage et la copie de l’identifiant sont notés dans le fil.' : 'L’identifiant complet est réservé aux administrateurs de la communauté.' ?></p>
                <?php endif; ?>
                <?php if ($senderKey !== null): ?><a class="rmt-btn rmt-btn--wide" href="<?= $h(url('back-office/remontees/joueurs/' . rawurlencode($senderKey))) ?>">Tout ce que ce joueur a remonté</a><?php endif; ?>
            </section>

            <section class="rmt-tabpanel" role="tabpanel" id="rmt-panel-liens" aria-labelledby="rmt-tab-liens" data-rmt-panel="liens" hidden>
                <h2 class="rmt-h2">Liens</h2>
                <p class="rmt-desc">Dossier, conversions et éléments du bureau SSE rattachés à cette remontée.</p>
                <?php $done = array_values(array_filter($d['conversions'], static fn (array $c): bool => $c['existing'] !== null)); ?>
                <?php if ($d['case'] === null && $done === [] && $d['links']['items'] === [] && empty($stateInfo['duplicate_of'])): ?>
                    <div class="rmt-empty"><strong>Aucun lien</strong><p>Cette remontée n’est versée à aucun dossier et n’a été ni convertie ni reliée.</p></div>
                <?php endif; ?>
                <ul class="rmt-links">
                    <?php if ($d['case'] !== null): ?>
                        <li><a href="<?= $h(url('atak/sse/dossiers/' . (int) $d['case']['id'])) ?>"><strong class="rmt-mono"><?= $h($d['case']['ref']) ?></strong> <span translate="no"><?= $h($d['case']['title']) ?></span></a><span class="rmt-dim">Dossier dans lequel elle est versée</span></li>
                    <?php endif; ?>
                    <?php if (!empty($stateInfo['duplicate_of']['ref'])): ?>
                        <li><a href="<?= $h($itemUrl((string) $stateInfo['duplicate_of']['source'], (int) $stateInfo['duplicate_of']['id'])) ?>"><strong class="rmt-mono"><?= $h((string) $stateInfo['duplicate_of']['ref']) ?></strong></a><span class="rmt-dim">Remontée d’origine de ce doublon</span></li>
                    <?php endif; ?>
                    <?php foreach ($done as $c): ?>
                        <li><a href="<?= $h(url($c['existing']['url'])) ?>"><strong class="rmt-mono"><?= $h($c['existing']['ref']) ?></strong></a><span class="rmt-dim"><?= $h($c['label']) ?> issu de cette remontée</span></li>
                    <?php endforeach; ?>
                    <?php foreach ($d['links']['items'] as $l): ?>
                        <li>
                            <?php if ($l['href'] !== ''): ?><a href="<?= $h(url(ltrim($l['href'], '/'))) ?>"><strong class="rmt-mono"><?= $h($l['ref']) ?></strong> <span translate="no"><?= $h($l['title']) ?></span></a><?php else: ?><span><?= $h($l['title']) ?></span><?php endif; ?>
                            <span class="rmt-dim"><?= $h($l['type_label']) ?> · <?= $h($l['relation']) ?><?= $l['auto'] ? ' · posé par Cerbère' : ($l['actor'] !== '' ? ' · par ' . $h($l['actor']) : '') ?></span>
                            <?php if ($l['reason'] !== ''): ?><span class="rmt-dim" translate="no"><?= $h($l['reason']) ?></span><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="rmt-btnrow">
                    <?php if ($rights['convert'] && !$deleted): ?><button type="button" class="rmt-btn" data-rmt-open="rmt-dlg-convertir" aria-haspopup="dialog"><?= $ico($icons['convert']) ?>Convertir</button><?php endif; ?>
                    <?php if ($rights['link'] && !$deleted && $src === 'fiche'): ?><button type="button" class="rmt-btn" data-rmt-open="rmt-dlg-relier" aria-haspopup="dialog"><?= $ico($icons['link']) ?>Relier</button><?php endif; ?>
                </div>
                <?php if ($src === 'cr'): ?><p class="rmt-dim">Un compte rendu se relie par « Doublon », par « Transmettre » à un dossier, ou en le convertissant en fiche.</p><?php endif; ?>
            </section>

            <section class="rmt-tabpanel" role="tabpanel" id="rmt-panel-historique" aria-labelledby="rmt-tab-historique" data-rmt-panel="historique" hidden>
                <h2 class="rmt-h2">Historique</h2>
                <p class="rmt-desc">Dates, états successifs, passages caviardés et lectures.</p>
                <div class="rmt-block">
                    <h3 class="rmt-h3">Dates</h3>
                    <dl class="rmt-rows">
                        <?php foreach ($it['timestamps'] ?? [] as $label => $ts): ?><div><dt><?= $h($label) ?></dt><dd><?= $h(P::when((string) $ts)) ?></dd></div><?php endforeach; ?>
                        <?php if (!empty($it['triaged_by'])): ?><div><dt>Prise en compte par</dt><dd><?= $h($it['triaged_by']['label']) ?></dd></div><?php endif; ?>
                    </dl>
                </div>
                <div class="rmt-block">
                    <h3 class="rmt-h3">États <span class="rmt-count"><?= count($it['state_history']) ?></span></h3>
                    <?php if ($it['state_history'] === []): ?>
                        <p class="rmt-dim">Aucun changement d’état.</p>
                    <?php else: ?>
                        <ol class="rmt-list">
                            <?php foreach (array_reverse($it['state_history']) as $e): $to = (string) ($e['meta']['to'] ?? ''); ?>
                                <li><strong><?= $h(IntelIntakeService::STATES[$to] ?? $to) ?></strong> <span class="rmt-dim"><?= $h($e['actor']) ?> · <?= $h(P::when((string) $e['at'])) ?></span><?php if ($e['body'] !== ''): ?><span class="rmt-dim" translate="no"><?= $h($e['body']) ?></span><?php endif; ?></li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </div>
                <div class="rmt-block">
                    <h3 class="rmt-h3">Caviardages <span class="rmt-count"><?= count($redactions) ?></span></h3>
                    <?php if ($redactions === []): ?>
                        <p class="rmt-dim">Aucun passage caviardé.</p>
                    <?php else: ?>
                        <ul class="rmt-list">
                            <?php foreach ($redactions as $r): $readable = IntelRedaction::readable($r, $level, $me); ?>
                                <li>
                                    <span translate="no"><?= $readable ? '« ' . $h(mb_strimwidth((string) $r['phrase'], 0, 90, '…')) . ' »' : '<span class="rmt-bar">' . $h(SseRedactionService::bar((string) $r['phrase'])) . '</span>' ?></span>
                                    <span class="rmt-dim"><?= $h(IntelRedaction::FIELDS[$src][$r['field']] ?? $r['field']) ?> · en clair dès « <?= $h(SseRedactionService::levelLabel((string) $r['clear_level'])) ?> »<?= $r['allowed'] !== [] ? ' · ' . count($r['allowed']) . ' membre(s) nommé(s)' : '' ?></span>
                                    <?php if ($rights['redact'] && !$deleted): ?>
                                        <form method="post" action="<?= $h($base . '/unredact') ?>"><?= $csrf ?><input type="hidden" name="redaction_id" value="<?= (int) $r['id'] ?>"><button type="submit" class="rmt-btn rmt-btn--mini">Lever le caviardage</button></form>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <div class="rmt-block">
                    <h3 class="rmt-h3">Lectures <span class="rmt-count"><?= count($it['reads']) ?></span></h3>
                    <?php if ($it['reads'] === []): ?>
                        <p class="rmt-dim">Personne d’autre ne l’a encore ouverte.</p>
                    <?php else: ?>
                        <ul class="rmt-list">
                            <?php foreach ($it['reads'] as $rd): ?>
                                <li><strong><?= $h($rd['label']) ?></strong> <span class="rmt-dim"><?= $h($channels[$rd['channel']] ?? $rd['channel']) ?> · <?= $h(P::ago((string) $rd['last'])) ?> · <?= (int) $rd['count'] ?> lecture<?= $rd['count'] > 1 ? 's' : '' ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </section>

            <section class="rmt-tabpanel" role="tabpanel" id="rmt-panel-sensible" aria-labelledby="rmt-tab-sensible" data-rmt-panel="sensible" hidden>
                <h2 class="rmt-h2">Zone sensible</h2>
                <p class="rmt-desc">Corriger, caviarder ou supprimer : chaque geste reste dans le fil.</p>
                <?php if ($deleted): ?>
                    <p>Cette remontée est supprimée : elle n’apparaît plus au bureau SSE ni sur les téléphones.</p>
                    <?php if ($rights['manage']): ?>
                        <form method="post" action="<?= $h($base . '/restore') ?>"><?= $csrf ?><button type="submit" class="rmt-btn rmt-btn--wide">Restaurer la remontée</button></form>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="rmt-btncol">
                        <?php if ($rights['manage']): ?><button type="button" class="rmt-btn rmt-btn--wide" data-rmt-open="rmt-dlg-modifier" aria-haspopup="dialog">Modifier les données</button><?php endif; ?>
                        <?php if ($rights['redact']): ?>
                            <button type="button" class="rmt-btn rmt-btn--wide" data-rmt-open="rmt-dlg-caviarder" aria-haspopup="dialog">Caviarder un passage</button>
                        <?php else: ?>
                            <p class="rmt-dim">Caviarder est réservé aux habilités « Très secret (TS) ».</p>
                        <?php endif; ?>
                        <?php if ($rights['manage']): ?><button type="button" class="rmt-btn rmt-btn--wide rmt-btn--danger" data-rmt-open="rmt-dlg-supprimer" aria-haspopup="dialog">Supprimer la remontée</button><?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>
        </aside>
    </div>

    <?php if ($canTreat): ?>
        <dialog class="rmt-dialog" id="rmt-dlg-transmettre" aria-labelledby="rmt-dlg-transmettre-title">
            <form method="post" action="<?= $h($base . '/state') ?>" class="rmt-dialog__card">
                <?= $csrf ?>
                <input type="hidden" name="state" value="transmise">
                <?= $dialogHead('rmt-dlg-transmettre', 'Transmettre', 'Choisissez l’échelon, la cellule ou le dossier SSE qui reçoit la remontée.') ?>
                <div class="rmt-dialog__body">
                    <div class="rmt-picker" data-rmt-picker data-rmt-picker-name="target" data-rmt-picker-url="<?= $h($base . '/suggestions?genre=transmettre') ?>">
                        <label class="rmt-label" for="rmt-fwd-q">Destinataire</label>
                        <input id="rmt-fwd-q" type="search" autocomplete="off" placeholder="Rechercher un échelon ou un dossier" data-rmt-picker-input autofocus>
                        <div class="rmt-picker__list" role="radiogroup" aria-label="Destinataires" data-rmt-picker-list>
                            <?php foreach ($d['forward_targets'] as $t): ?>
                                <label class="rmt-opt"><input type="radio" name="target" value="<?= $h($t['value']) ?>" required<?= ($stateInfo['forwarded_to'] ?? '') === $t['value'] ? ' checked' : '' ?>><span translate="no"><?= $h($t['label']) ?></span><small><?= $h($t['kind']) ?></small></label>
                            <?php endforeach; ?>
                        </div>
                        <p class="rmt-dim" data-rmt-picker-empty hidden>Aucun échelon ni dossier ne correspond.</p>
                    </div>
                    <label class="rmt-label" for="rmt-fwd-note">Motif (facultatif)</label>
                    <input id="rmt-fwd-note" type="text" name="note" maxlength="400" placeholder="Visible dans le fil">
                    <?= $chips('transmise', '#rmt-fwd-note') ?>
                    <p class="rmt-dim">Un dossier SSE choisi ici est relié à la remontée.</p>
                </div>
                <footer class="rmt-dialog__foot"><button type="button" class="rmt-btn" data-rmt-close>Annuler</button><button type="submit" class="rmt-btn rmt-btn--primary">Transmettre</button></footer>
            </form>
        </dialog>

        <dialog class="rmt-dialog" id="rmt-dlg-doublon" aria-labelledby="rmt-dlg-doublon-title">
            <form method="post" action="<?= $h($base . '/state') ?>" class="rmt-dialog__card">
                <?= $csrf ?>
                <input type="hidden" name="state" value="doublon">
                <?= $dialogHead('rmt-dlg-doublon', 'Marquer comme doublon', 'Indiquez la remontée d’origine : celle-ci sera close et notée dans son fil.') ?>
                <div class="rmt-dialog__body">
                    <div class="rmt-picker" data-rmt-picker data-rmt-picker-name="target" data-rmt-picker-url="<?= $h($base . '/suggestions?genre=doublon') ?>" data-rmt-picker-remote>
                        <label class="rmt-label" for="rmt-dup-q">Remontée d’origine</label>
                        <input id="rmt-dup-q" type="search" autocomplete="off" placeholder="Rechercher par référence, texte ou indicatif" data-rmt-picker-input autofocus>
                        <?php if ($d['duplicates'] !== []): ?><p class="rmt-picker__hint"><?= $cerbere ?> remontées proches de celle-ci</p><?php endif; ?>
                        <div class="rmt-picker__list" role="radiogroup" aria-label="Remontées d’origine possibles" data-rmt-picker-list>
                            <?php foreach ($d['duplicates'] as $dup): ?>
                                <label class="rmt-opt"><input type="radio" name="target" value="<?= $h($dup['source'] . '/' . $dup['id']) ?>" required><span><strong class="rmt-mono"><?= $h($dup['ref']) ?></strong> · <?= $h(P::when($dup['at'])) ?></span><small><?= $h($dup['reason']) ?></small></label>
                            <?php endforeach; ?>
                        </div>
                        <p class="rmt-dim" data-rmt-picker-empty<?= $d['duplicates'] !== [] ? ' hidden' : '' ?>>Aucune remontée proche : cherchez-la par sa référence.</p>
                    </div>
                    <label class="rmt-label" for="rmt-dup-note">Motif (facultatif)</label>
                    <input id="rmt-dup-note" type="text" name="note" maxlength="400" placeholder="Visible dans le fil">
                    <?= $chips('doublon', '#rmt-dup-note') ?>
                </div>
                <footer class="rmt-dialog__foot"><button type="button" class="rmt-btn" data-rmt-close>Annuler</button><button type="submit" class="rmt-btn rmt-btn--primary">Marquer doublon</button></footer>
            </form>
        </dialog>

        <dialog class="rmt-dialog" id="rmt-dlg-clore" aria-labelledby="rmt-dlg-clore-title">
            <form method="post" action="<?= $h($base . '/state') ?>" class="rmt-dialog__card">
                <?= $csrf ?>
                <?= $dialogHead('rmt-dlg-clore', 'Classer sans suite', 'La remontée sort de la file ; elle reste consultable et peut être rouverte.') ?>
                <div class="rmt-dialog__body">
                    <div class="rmt-picker__list" role="radiogroup" aria-label="Issue">
                        <label class="rmt-opt"><input type="radio" name="state" value="close" required checked><span>Close</span><small><?= $h(IntelIntakeService::STATE_HINTS['close']) ?></small></label>
                        <label class="rmt-opt"><input type="radio" name="state" value="non_exploitable" required><span>Non exploitable</span><small><?= $h(IntelIntakeService::STATE_HINTS['non_exploitable']) ?></small></label>
                    </div>
                    <label class="rmt-label" for="rmt-clo-note">Motif (facultatif)</label>
                    <input id="rmt-clo-note" type="text" name="note" maxlength="400" placeholder="Visible dans le fil">
                    <?= $chips('close', '#rmt-clo-note') ?><?= $chips('non_exploitable', '#rmt-clo-note') ?>
                </div>
                <footer class="rmt-dialog__foot"><button type="button" class="rmt-btn" data-rmt-close>Annuler</button><button type="submit" class="rmt-btn rmt-btn--primary">Classer</button></footer>
            </form>
        </dialog>
    <?php endif; ?>

    <?php if ($rights['convert'] && !$deleted): ?>
        <dialog class="rmt-dialog" id="rmt-dlg-convertir" aria-labelledby="rmt-dlg-convertir-title">
            <form method="post" action="<?= $h($base . '/convert') ?>" class="rmt-dialog__card">
                <?= $csrf ?>
                <?= $dialogHead('rmt-dlg-convertir', 'Convertir', 'Crée un élément du bureau SSE à partir de cette remontée, qui reste intacte.') ?>
                <div class="rmt-dialog__body">
                    <div class="rmt-picker__list" role="radiogroup" aria-label="Convertir en">
                        <?php $first = true; foreach ($d['conversions'] as $c): ?>
                            <?php if ($c['existing'] !== null): ?>
                                <p class="rmt-opt rmt-opt--done"><span><?= $h($c['label']) ?> : déjà fait</span><small><a href="<?= $h(url($c['existing']['url'])) ?>"><?= $h($c['existing']['ref']) ?></a></small></p>
                            <?php else: ?>
                                <label class="rmt-opt"><input type="radio" name="kind" value="<?= $h($c['kind']) ?>" required<?= $first ? ' checked' : '' ?>><span><?= $h($c['label']) ?></span><small><?= $h($c['hint']) ?></small></label>
                            <?php $first = false; endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <p class="rmt-dim">L’élément créé garde un lien « issu de » vers cette remontée ; un passage caviardé pour vous y reste barré.</p>
                </div>
                <footer class="rmt-dialog__foot"><button type="button" class="rmt-btn" data-rmt-close>Annuler</button><button type="submit" class="rmt-btn rmt-btn--primary"<?= $first ? ' disabled' : '' ?>>Convertir</button></footer>
            </form>
        </dialog>
    <?php endif; ?>

    <?php if ($rights['link'] && !$deleted && $src === 'fiche'): ?>
        <dialog class="rmt-dialog" id="rmt-dlg-relier" aria-labelledby="rmt-dlg-relier-title">
            <form method="post" action="<?= $h($base . '/link') ?>" class="rmt-dialog__card">
                <?= $csrf ?>
                <?= $dialogHead('rmt-dlg-relier', 'Relier', 'Reliez la fiche à une personne, un dossier, une autre fiche ou un site du bureau SSE.') ?>
                <div class="rmt-dialog__body">
                    <?php if (!$d['links']['can']): ?>
                        <p class="rmt-notice"><?= $d['links']['ready'] ? 'Droit manquant : écrire dans les fiches de renseignement du bureau SSE.' : 'Les liens manuels arrivent avec la mise à jour du site.' ?></p>
                    <?php endif; ?>
                    <div class="rmt-picker" data-rmt-picker data-rmt-picker-name="to" data-rmt-picker-url="<?= $h($base . '/suggestions?genre=lien') ?>" data-rmt-picker-remote>
                        <label class="rmt-label" for="rmt-link-q">Élément à relier</label>
                        <input id="rmt-link-q" type="search" autocomplete="off" placeholder="Référence ou nom, deux caractères au moins" data-rmt-picker-input autofocus>
                        <div class="rmt-picker__list" role="radiogroup" aria-label="Éléments trouvés" data-rmt-picker-list></div>
                        <p class="rmt-dim" data-rmt-picker-empty>Tapez une référence ou un nom pour chercher.</p>
                    </div>
                    <label class="rmt-label" for="rmt-link-rel">Nature du lien</label>
                    <select id="rmt-link-rel" name="relation">
                        <?php foreach (\App\Support\SseLinkCatalog::MANUAL_RELATIONS as $rel): ?><option value="<?= $h($rel) ?>"><?= $h(\App\Support\SseLinkCatalog::relationLabel($rel, true)) ?></option><?php endforeach; ?>
                    </select>
                    <label class="rmt-label" for="rmt-link-why">Motif</label>
                    <input id="rmt-link-why" type="text" name="reason" minlength="5" maxlength="255" required placeholder="Pourquoi ces deux éléments sont liés">
                </div>
                <footer class="rmt-dialog__foot"><button type="button" class="rmt-btn" data-rmt-close>Annuler</button><button type="submit" class="rmt-btn rmt-btn--primary"<?= $d['links']['can'] ? '' : ' disabled' ?>>Relier</button></footer>
            </form>
        </dialog>
    <?php endif; ?>

    <?php if ($rights['discord']): $pv = $d['discord']['preview']; ?>
        <dialog class="rmt-dialog" id="rmt-dlg-discord" aria-labelledby="rmt-dlg-discord-title">
            <form method="post" action="<?= $h($base . '/discord') ?>" class="rmt-dialog__card">
                <?= $csrf ?>
                <?= $dialogHead('rmt-dlg-discord', 'Notifier sur Discord', 'Voici exactement ce que le bot publiera : jamais le texte de la remontée.') ?>
                <div class="rmt-dialog__body">
                    <div class="rmt-preview" aria-label="Aperçu du message">
                        <p class="rmt-preview__title">Remontée à regarder</p>
                        <p translate="no"><?= $h($pv['ref']) ?> · <?= $h($pv['type']) ?></p>
                        <p>Urgence : <?= $h(mb_strtolower($pv['urgency_label'])) ?></p>
                        <?php if ($pv['emitter'] !== ''): ?><p>Émetteur : <span translate="no"><?= $h($pv['emitter']) ?></span></p><?php endif; ?>
                        <?php if ($pv['title'] !== ''): ?><p translate="no"><?= $h($pv['title']) ?></p><?php endif; ?>
                        <p class="rmt-dim">Lien vers cette fiche du back-office</p>
                    </div>
                    <?php if ($d['discord']['ready']): ?>
                        <p>Salon : <strong translate="no"><?= $h($d['discord']['channel']) ?></strong>. Le bot l’envoie dans les cinq minutes.</p>
                    <?php else: ?>
                        <p class="rmt-notice"><?= $h($d['discord']['reason']) ?></p>
                    <?php endif; ?>
                    <?php if ($pv['emitter'] === ''): ?><p class="rmt-dim">L’émetteur et le titre ne sortent pas du site : la diffusion de cette remontée est au-dessus du niveau le plus bas.</p><?php elseif ($pv['title'] === ''): ?><p class="rmt-dim">Le titre ne sort pas du site : la remontée est caviardée ou n’en a pas.</p><?php endif; ?>
                </div>
                <footer class="rmt-dialog__foot"><button type="button" class="rmt-btn" data-rmt-close>Annuler</button><button type="submit" class="rmt-btn rmt-btn--primary"<?= $d['discord']['ready'] ? '' : ' disabled' ?>>Envoyer la notification</button></footer>
            </form>
        </dialog>
    <?php endif; ?>

    <?php if ($rights['manage'] && !$deleted): ?>
        <dialog class="rmt-dialog rmt-dialog--wide" id="rmt-dlg-modifier" aria-labelledby="rmt-dlg-modifier-title">
            <form method="post" action="<?= $h($base . '/edit') ?>" class="rmt-dialog__card">
                <?= $csrf ?>
                <?= $dialogHead('rmt-dlg-modifier', 'Modifier les données', 'L’ancienne valeur de chaque champ reste dans le fil.') ?>
                <div class="rmt-dialog__body">
                    <?php foreach (IntelIntakeService::EDITABLE[$src] as $col => $label):
                        $val = (string) ($row[$col] ?? '');
                        // Un champ qui porte un passage que je ne lis pas en clair ne m'est pas modifiable (ni lisible ici).
                        $locked = array_filter($redactions, static fn (array $r): bool => $r['field'] === $col && !IntelRedaction::readable($r, $level, $me)) !== [];
                        $fid = 'rmt-edit-' . $col;
                    ?>
                        <label class="rmt-label" for="<?= $h($fid) ?>"><?= $h($label) ?></label>
                        <?php if ($locked): ?>
                            <p class="rmt-dim" id="<?= $h($fid) ?>">Champ caviardé au-dessus de votre habilitation : modification réservée.</p>
                        <?php elseif ($col === 'urgency'): ?>
                            <select id="<?= $h($fid) ?>" name="urgency"><?php foreach (SseFieldNoteCatalog::URGENCIES as $code => $u): ?><option value="<?= $h($code) ?>"<?= SseFieldNoteCatalog::normalizeUrgency($val) === $code ? ' selected' : '' ?>><?= $h($u['label']) ?></option><?php endforeach; ?></select>
                        <?php elseif ($col === 'classification'): ?>
                            <select id="<?= $h($fid) ?>" name="classification"><?php foreach ($intakeLevels as $code => $l): ?><option value="<?= $h($code) ?>"<?= $val === $code ? ' selected' : '' ?>><?= $h($l) ?></option><?php endforeach; ?></select>
                        <?php elseif ($col === 'priority'): ?>
                            <select id="<?= $h($fid) ?>" name="priority"><?php foreach (\App\Controllers\Admin\AdminAtakReportRoutingController::PRIORITIES as $code => $l): ?><option value="<?= $h($code) ?>"<?= strtoupper($val) === $code ? ' selected' : '' ?>><?= $h($l) ?></option><?php endforeach; ?></select>
                        <?php elseif (in_array($col, ['body', 'details', 'remarks', 'summary', 'location_description'], true)): ?>
                            <textarea id="<?= $h($fid) ?>" name="<?= $h($col) ?>" rows="<?= in_array($col, ['body', 'details'], true) ? 7 : 3 ?>"><?= $h($val) ?></textarea>
                        <?php else: ?>
                            <input id="<?= $h($fid) ?>" type="text" name="<?= $h($col) ?>" value="<?= $h($val) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <p class="rmt-dim">Les passages caviardés restent caviardés tant que la phrase est inchangée.</p>
                </div>
                <footer class="rmt-dialog__foot"><button type="button" class="rmt-btn" data-rmt-close>Annuler</button><button type="submit" class="rmt-btn rmt-btn--primary">Enregistrer</button></footer>
            </form>
        </dialog>

        <dialog class="rmt-dialog" id="rmt-dlg-supprimer" aria-labelledby="rmt-dlg-supprimer-title">
            <form method="post" action="<?= $h($base . '/delete') ?>" class="rmt-dialog__card">
                <?= $csrf ?>
                <?= $dialogHead('rmt-dlg-supprimer', 'Supprimer la remontée', 'Elle disparaît du bureau SSE et des téléphones ; elle reste restaurable ici.') ?>
                <div class="rmt-dialog__body">
                    <label class="rmt-label" for="rmt-del-why">Motif de la suppression</label>
                    <input id="rmt-del-why" type="text" name="reason" maxlength="255" required placeholder="Pourquoi cette remontée est retirée">
                    <?= $chips('delete', '#rmt-del-why') ?>
                    <label class="rmt-label" for="rmt-del-confirm">Pour confirmer, recopiez la référence <strong class="rmt-mono" translate="no"><?= $h($it['ref']) ?></strong></label>
                    <input id="rmt-del-confirm" type="text" name="confirm" required autocomplete="off" pattern="<?= $h(preg_quote((string) $it['ref'], '/')) ?>" data-rmt-confirm="<?= $h($it['ref']) ?>">
                </div>
                <footer class="rmt-dialog__foot"><button type="button" class="rmt-btn" data-rmt-close autofocus>Annuler</button><button type="submit" class="rmt-btn rmt-btn--danger" data-rmt-confirm-submit>Supprimer la remontée</button></footer>
            </form>
        </dialog>
    <?php endif; ?>

    <?php if ($rights['redact'] && !$deleted): ?>
        <dialog class="rmt-dialog" id="rmt-dlg-caviarder" aria-labelledby="rmt-dlg-caviarder-title">
            <form method="post" action="<?= $h($base . '/redact') ?>" class="rmt-dialog__card">
                <?= $csrf ?>
                <?= $dialogHead('rmt-dlg-caviarder', 'Caviarder un passage', 'Les autres lecteurs voient une barre à la place du passage, partout.') ?>
                <div class="rmt-dialog__body">
                    <label class="rmt-label" for="rmt-red-field">Champ</label>
                    <select id="rmt-red-field" name="field"><?php foreach (IntelRedaction::FIELDS[$src] as $k => $l): ?><option value="<?= $h($k) ?>"<?= in_array($k, ['body', 'details'], true) ? ' selected' : '' ?>><?= $h($l) ?></option><?php endforeach; ?></select>
                    <label class="rmt-label" for="rmt-red-phrase">Passage à caviarder, copié tel qu’il est écrit</label>
                    <textarea id="rmt-red-phrase" name="phrase" rows="2" required placeholder="ex. : le chef du réseau loge chez Karimi"></textarea>
                    <label class="rmt-label" for="rmt-red-level">Lisible en clair à partir de</label>
                    <select id="rmt-red-level" name="level"><?php foreach ($intakeLevels as $code => $l): ?><option value="<?= $h($code) ?>"<?= $code === 'confidentiel' ? ' selected' : '' ?>><?= $h($l) ?></option><?php endforeach; ?></select>
                    <label class="rmt-label" for="rmt-red-allowed">Et en clair pour ces membres (facultatif)</label>
                    <select id="rmt-red-allowed" name="allowed[]" multiple size="5"><?php foreach ($intakeMembers as $m): ?><option value="<?= (int) $m['id'] ?>"><?= $h($m['label']) ?></option><?php endforeach; ?></select>
                    <label class="rmt-label" for="rmt-red-why">Motif</label>
                    <input id="rmt-red-why" type="text" name="reason" maxlength="255" placeholder="Protection de la source, opération en cours…">
                </div>
                <footer class="rmt-dialog__foot"><button type="button" class="rmt-btn" data-rmt-close>Annuler</button><button type="submit" class="rmt-btn rmt-btn--primary">Caviarder</button></footer>
            </form>
        </dialog>
    <?php endif; ?>
</div>
<script src="<?= $h(asset_url('assets/js/remontees.js')) ?>" defer></script>
<script src="<?= $h(asset_url('assets/js/recon-viewer.js')) ?>" defer></script>
