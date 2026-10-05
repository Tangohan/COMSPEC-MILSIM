<?php
declare(strict_types=1);

use App\Services\Intel\IntelIntakeService;
use App\Services\Intel\IntelRedaction;
use App\Services\Sse\SseRedactionService;
use App\Support\SseFieldNoteCatalog;

/** @var array<string, mixed> $intake */
/** @var string $intakeViewerLevel */
/** @var int $intakeViewerId */
/** @var array<string, string> $intakeTypes */
/** @var list<array{id: int, label: string}> $intakeMembers */
/** @var array<string, string> $intakeLevels */
/** @var string $csrfToken */

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$it = $intake;
$row = $it['row'];
$src = (string) $it['source'];
$level = (string) $intakeViewerLevel;
$me = (int) $intakeViewerId;
$canRedact = $level === 'tres_restreint';
$base = url('back-office/remontees/' . $src . '/' . (int) $it['id']);
$csrf = '<input type="hidden" name="_csrf_token" value="' . $h($csrfToken) . '">';
$redactions = $it['redactions'];

$ago = static function (string $ts): string {
    $t = strtotime($ts);
    if ($t === false) {
        return '';
    }
    $d = time() - $t;

    return match (true) {
        $d < 90 => 'à l’instant',
        $d < 3600 => 'il y a ' . (int) round($d / 60) . ' min',
        $d < 86400 => 'il y a ' . (int) round($d / 3600) . ' h',
        $d < 86400 * 30 => 'il y a ' . (int) round($d / 86400) . ' j',
        default => 'le ' . date('d/m/Y à H:i', $t),
    };
};
$when = static fn (string $ts): string => ($t = strtotime($ts)) !== false ? date('d/m/Y H:i', $t) : '';
// Texte d'un champ : passages caviardés surlignés si je peux les lire, barrés sinon.
$text = static function (string $field) use ($row, $redactions, $level, $me, $h): string {
    $value = (string) ($row[$field] ?? '');
    if (trim($value) === '') {
        return '';
    }
    $mine = array_values(array_filter($redactions, static fn (array $r): bool => $r['field'] === $field));
    $out = '';
    foreach (IntelRedaction::split($value, $mine) as [$chunk, $r]) {
        if ($r === null) {
            $out .= $h($chunk);
        } elseif (IntelRedaction::readable($r, $level, $me)) {
            $out .= '<mark class="rmt-red" title="Caviardé : en clair à partir de « ' . $h(SseRedactionService::levelLabel((string) $r['clear_level'])) . ' »">' . $h($chunk) . '</mark>';
        } else {
            $out .= '<span class="rmt-bar" title="Passage caviardé">' . $h(SseRedactionService::bar($chunk)) . '</span>';
        }
    }

    return nl2br($out, false);
};
$stateIcon = static fn (string $s): string => match ($s) {
    'a_traiter' => '<svg viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="6" fill="none" stroke="currentColor" stroke-width="1.6"/><circle cx="8" cy="8" r="1.6" fill="currentColor"/></svg>',
    'en_cours' => '<svg viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="6" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M8 2a6 6 0 0 1 0 12z" fill="currentColor"/></svg>',
    'exploitee' => '<svg viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="6.8" fill="currentColor"/><path d="M5 8.2l2 2 4-4.2" fill="none" stroke="#fff" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    'non_exploitable' => '<svg viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="6" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M4.2 11.8l7.6-7.6" stroke="currentColor" stroke-width="1.6"/></svg>',
    default => '<svg viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="6.8" fill="currentColor"/><path d="M5.5 5.5l5 5m0-5l-5 5" stroke="#fff" stroke-width="1.6" stroke-linecap="round"/></svg>',
};
$initials = static fn (string $s): string => mb_strtoupper(mb_substr(trim($s) !== '' ? trim($s) : '?', 0, 2));
$eventLine = static function (array $e) use ($h): string {
    $m = $e['meta'];

    return match ($e['kind']) {
        'state' => 'a passé l’état de <strong>' . $h(IntelIntakeService::STATES[$m['from'] ?? ''] ?? '?') . '</strong> à <strong>' . $h(IntelIntakeService::STATES[$m['to'] ?? ''] ?? '?') . '</strong>',
        'assign' => !empty($m['to']) ? 'a attribué la remontée à <strong>' . $h($m['to']) . '</strong>' : 'a retiré l’attribution',
        'type' => 'a changé le type de <strong>' . $h($m['from'] ?? '') . '</strong> en <strong>' . $h($m['to'] ?? '') . '</strong>',
        'edit' => 'a modifié : ' . $h(implode(', ', (array) ($m['fields'] ?? []))),
        'redact' => 'a caviardé un passage (' . $h($m['field'] ?? '') . ', ' . (int) ($m['length'] ?? 0) . ' caractères), en clair à partir de « ' . $h($m['level'] ?? '') . ' »' . (!empty($m['people']) ? ' et pour ' . (int) $m['people'] . ' membre(s) nommé(s)' : ''),
        default => $h(IntelIntakeService::EVENT_LABELS[$e['kind']] ?? $e['kind']),
    };
};
$fieldsShown = $src === 'cr'
    ? ['summary' => 'Résumé', 'details' => 'Détails', 'remarks' => 'Remarques', 'location_description' => 'Lieu']
    : ['body' => 'Texte', 'place_label' => 'Lieu'];
$titleHtml = $src === 'fiche' && trim((string) ($row['title'] ?? '')) !== '' ? $text('title') : $h($it['type_label']);
$facts = $src === 'cr'
    ? array_filter([
        'Priorité' => (string) ($row['priority'] ?? ''),
        'Diffusion' => (string) ($row['classification'] ?? ''),
        'Carroyage' => (string) ($row['grid_reference'] ?? ''),
        'DTG' => (string) ($row['dtg'] ?? ''),
        'Unité' => (string) ($row['submitter_unit'] ?? ''),
        'Événement' => !empty($row['event_timestamp']) ? $when((string) $row['event_timestamp']) : '',
    ])
    : array_filter([
        'Urgence' => SseFieldNoteCatalog::urgencyLabel((string) ($row['urgency'] ?? '')),
        'Diffusion' => SseRedactionService::levelLabel((string) ($row['classification'] ?? 'interne')),
        'Observée' => $when((string) ($row['observed_at'] ?? '')),
        'Carroyage' => (string) ($row['grid_reference'] ?? ''),
        'Source' => SseFieldNoteCatalog::sourceLabel((string) ($row['intel_source'] ?? '')),
        'Fiabilité' => trim((string) ($row['source_reliability'] ?? '') . (string) ($row['info_credibility'] ?? '')),
        'Origine' => SseFieldNoteCatalog::originLabel((string) ($row['origin'] ?? '')),
        'Unité' => (string) ($row['author_unit'] ?? ''),
    ]);
$isOpen = in_array($it['state'], IntelIntakeService::OPEN_STATES, true);
?>
<div class="rmt">
    <div class="rmt__frame">
        <nav class="rmt__crumbs" aria-label="Fil d’Ariane"><a href="<?= $h(url('back-office/remontees')) ?>">Remontées</a> <span aria-hidden="true">/</span> <?= $h($it['ref']) ?></nav>

        <header class="rmt__pr-head">
            <h1 class="rmt__pr-title"><?= $titleHtml ?> <span class="rmt__pr-ref"><?= $h($it['ref']) ?></span></h1>
            <div class="rmt__pr-sub">
                <span class="rmt__pill rmt__pill--<?= $h($it['state']) ?>"><?= $stateIcon((string) $it['state']) ?><?= $h($it['state_label']) ?></span>
                <?php if (!empty($it['deleted'])): ?><span class="rmt__pill rmt__pill--deleted">Supprimée</span><?php endif; ?>
                <span><strong><?= $h($it['author'] !== '' ? $it['author'] : 'Terrain') ?></strong> a remonté <?= $src === 'cr' ? 'ce compte rendu' : 'cette fiche' ?> <?= $h($ago((string) $it['at'])) ?> · <?= $h($it['source_label']) ?> <span class="rmt__label rmt__label--<?= $h($src) ?>"><?= $h($it['type']) ?></span> <?= $h($it['type_label']) ?></span>
            </div>
        </header>

        <div class="rmt__pr">
            <div class="rmt__thread" id="fil">
                <article class="rmt__card">
                    <header class="rmt__card-head">
                        <span class="rmt__avatar"><?= $h($initials((string) $it['author'])) ?></span>
                        <strong><?= $h($it['author'] !== '' ? $it['author'] : 'Terrain') ?></strong>
                        <span class="rmt__muted">a remonté <?= $h($ago((string) $it['at'])) ?></span>
                        <span class="rmt__card-tag">Auteur</span>
                    </header>
                    <div class="rmt__card-body">
                        <?php if ($facts !== []): ?>
                            <dl class="rmt__facts">
                                <?php foreach ($facts as $k => $v): ?><div><dt><?= $h($k) ?></dt><dd><?= $h($v) ?></dd></div><?php endforeach; ?>
                            </dl>
                        <?php endif; ?>
                        <?php foreach ($fieldsShown as $field => $label): $html = $text($field); if ($html === '') { continue; } ?>
                            <section class="rmt__field">
                                <h3><?= $h($label) ?></h3>
                                <p><?= $html ?></p>
                            </section>
                        <?php endforeach; ?>
                        <?php if ($it['structured'] !== []): ?>
                            <section class="rmt__field">
                                <h3>Champs du compte rendu</h3>
                                <dl class="rmt__facts rmt__facts--wide">
                                    <?php foreach ($it['structured'] as $k => $v): if (is_array($v)) { $v = implode(', ', array_map(static fn ($x): string => is_scalar($x) ? (string) $x : json_encode($x, JSON_UNESCAPED_UNICODE), $v)); } ?>
                                        <div><dt><?= $h(\App\Support\AtakIcemanReportCatalog::fieldLabelFr((string) $it['type'], (string) $k)) ?></dt><dd><?= $h((string) $v) ?></dd></div>
                                    <?php endforeach; ?>
                                </dl>
                            </section>
                        <?php endif; ?>
                        <?php if ($it['attachments'] !== []): ?>
                            <section class="rmt__field">
                                <h3>Pièces jointes</h3>
                                <ul class="rmt__atts">
                                    <?php foreach ($it['attachments'] as $a): ?>
                                        <li class="rmt__att<?= $a['blurred'] ? ' is-blurred' : '' ?>">
                                            <?php if ($a['is_image'] && $a['url']): ?>
                                                <a href="<?= $h($a['url']) ?>" target="_blank" rel="noopener" class="rmt__att-img"><img src="<?= $h($a['url']) ?>" alt="<?= $h($a['caption'] !== '' ? $a['caption'] : 'Pièce jointe') ?>" loading="lazy"></a>
                                            <?php elseif ($a['url']): ?>
                                                <a href="<?= $h($a['url']) ?>" target="_blank" rel="noopener" class="rmt__att-file"><?= $h($a['original_name'] !== '' ? $a['original_name'] : 'Document') ?></a>
                                            <?php endif; ?>
                                            <div class="rmt__att-meta">
                                                <span><?= $h($a['kind_label']) ?><?= $a['caption'] !== '' ? ' · ' . $h($a['caption']) : '' ?></span>
                                                <form method="post" action="<?= $h($base . '/blur') ?>">
                                                    <?= $csrf ?>
                                                    <input type="hidden" name="attachment_id" value="<?= (int) $a['id'] ?>">
                                                    <input type="hidden" name="blurred" value="<?= $a['blurred'] ? '0' : '1' ?>">
                                                    <button type="submit" class="rmt__link-btn"><?= $a['blurred'] ? 'Retirer le flou' : 'Flouter' ?></button>
                                                </form>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </section>
                        <?php endif; ?>
                    </div>
                </article>

                <?php foreach ($it['events'] as $e): ?>
                    <?php if ($e['kind'] === 'comment'): ?>
                        <article class="rmt__card" id="c<?= (int) $e['id'] ?>">
                            <header class="rmt__card-head">
                                <span class="rmt__avatar"><?= $h($initials($e['actor'])) ?></span>
                                <strong><?= $h($e['actor']) ?></strong>
                                <span class="rmt__muted">a commenté <a href="#c<?= (int) $e['id'] ?>" title="<?= $h($when($e['at'])) ?>"><?= $h($ago($e['at'])) ?></a></span>
                            </header>
                            <div class="rmt__card-body"><p><?= nl2br($h($e['body']), false) ?></p></div>
                        </article>
                    <?php else: ?>
                        <div class="rmt__event rmt__event--<?= $h($e['kind']) ?>">
                            <span class="rmt__event-dot" aria-hidden="true"></span>
                            <p><strong><?= $h($e['actor']) ?></strong> <?= $eventLine($e) ?> <span class="rmt__muted" title="<?= $h($when($e['at'])) ?>"><?= $h($ago($e['at'])) ?></span><?php if ($e['body'] !== ''): ?><br><span class="rmt__event-note"><?= $h($e['body']) ?></span><?php endif; ?></p>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>

                <form class="rmt__card rmt__composer" method="post" action="<?= $h($base . '/comment') ?>" id="fil-fin">
                    <?= $csrf ?>
                    <label for="rmt-comment" class="rmt__composer-title">Ajouter un commentaire</label>
                    <textarea id="rmt-comment" name="body" rows="4" placeholder="Analyse, recoupement, consigne d’exploitation…" required></textarea>
                    <div class="rmt__composer-actions">
                        <button type="submit" class="rmt__btn rmt__btn--primary">Commenter</button>
                    </div>
                </form>

                <section class="rmt__card rmt__decide" aria-label="Décision d’exploitation">
                    <header class="rmt__card-head"><strong>Décision</strong><span class="rmt__muted">État actuel : <?= $h($it['state_label']) ?></span></header>
                    <form method="post" action="<?= $h($base . '/state') ?>" class="rmt__card-body">
                        <?= $csrf ?>
                        <label for="rmt-note" class="rmt__sr">Motif (facultatif)</label>
                        <input id="rmt-note" type="text" name="note" maxlength="400" placeholder="Motif (facultatif), visible dans le fil">
                        <div class="rmt__decide-btns">
                            <?php if ($isOpen && $it['state'] !== 'en_cours'): ?><button name="state" value="en_cours" class="rmt__btn">Prendre en exploitation</button><?php endif; ?>
                            <?php if ($it['state'] !== 'exploitee'): ?><button name="state" value="exploitee" class="rmt__btn rmt__btn--done">Marquer exploitée</button><?php endif; ?>
                            <?php if ($it['state'] !== 'non_exploitable'): ?><button name="state" value="non_exploitable" class="rmt__btn">Non exploitable</button><?php endif; ?>
                            <?php if ($it['state'] !== 'close'): ?><button name="state" value="close" class="rmt__btn rmt__btn--close">Clore</button><?php endif; ?>
                            <?php if (!$isOpen): ?><button name="state" value="a_traiter" class="rmt__btn">Rouvrir</button><?php endif; ?>
                        </div>
                    </form>
                </section>

                <details class="rmt__card rmt__fold">
                    <summary>Modifier les données</summary>
                    <form method="post" action="<?= $h($base . '/edit') ?>" class="rmt__card-body rmt__form">
                        <?= $csrf ?>
                        <?php foreach (IntelIntakeService::EDITABLE[$src] as $col => $label):
                            $val = (string) ($row[$col] ?? '');
                            // Un champ qui porte un passage que je ne lis pas en clair ne m'est pas modifiable (ni lisible ici).
                            $locked = array_filter($redactions, static fn (array $r): bool => $r['field'] === $col && !IntelRedaction::readable($r, $level, $me)) !== [];
                        ?>
                            <label><span><?= $h($label) ?></span>
                                <?php if ($locked): ?>
                                    <em class="rmt__muted">Champ caviardé au-dessus de votre habilitation : modification réservée.</em>
                                <?php elseif ($col === 'urgency'): ?>
                                    <select name="urgency"><?php foreach (SseFieldNoteCatalog::URGENCIES as $code => $u): ?><option value="<?= $h($code) ?>"<?= $val === $code ? ' selected' : '' ?>><?= $h(is_array($u) ? ($u['label'] ?? $code) : $u) ?></option><?php endforeach; ?></select>
                                <?php elseif ($col === 'classification'): ?>
                                    <select name="classification"><?php foreach ($intakeLevels as $code => $l): ?><option value="<?= $h($code) ?>"<?= $val === $code ? ' selected' : '' ?>><?= $h($l) ?></option><?php endforeach; ?></select>
                                <?php elseif ($col === 'priority'): ?>
                                    <select name="priority"><?php foreach (\App\Controllers\Admin\AdminAtakReportRoutingController::PRIORITIES as $code => $l): ?><option value="<?= $h($code) ?>"<?= $val === $code ? ' selected' : '' ?>><?= $h($l) ?></option><?php endforeach; ?></select>
                                <?php elseif (in_array($col, ['body', 'details', 'remarks', 'summary', 'location_description'], true)): ?>
                                    <textarea name="<?= $h($col) ?>" rows="<?= in_array($col, ['body', 'details'], true) ? 7 : 3 ?>"><?= $h($val) ?></textarea>
                                <?php else: ?>
                                    <input type="text" name="<?= $h($col) ?>" value="<?= $h($val) ?>">
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                        <p class="rmt__hint">L’ancienne valeur reste dans l’historique du fil. Les passages caviardés restent caviardés tant que la phrase est inchangée.</p>
                        <button type="submit" class="rmt__btn rmt__btn--primary">Enregistrer</button>
                    </form>
                </details>

                <?php if ($canRedact): ?>
                <details class="rmt__card rmt__fold" id="caviarder">
                    <summary>Caviarder un passage</summary>
                    <form method="post" action="<?= $h($base . '/redact') ?>" class="rmt__card-body rmt__form">
                        <?= $csrf ?>
                        <label><span>Champ</span>
                            <select name="field"><?php foreach (IntelRedaction::FIELDS[$src] as $k => $l): ?><option value="<?= $h($k) ?>"><?= $h($l) ?></option><?php endforeach; ?></select>
                        </label>
                        <label><span>Passage à caviarder (copié tel qu’il est écrit)</span>
                            <textarea name="phrase" rows="2" required placeholder="ex. : le chef du réseau loge chez Karimi"></textarea>
                        </label>
                        <label><span>Lisible en clair à partir de</span>
                            <select name="level"><?php foreach ($intakeLevels as $code => $l): ?><option value="<?= $h($code) ?>"<?= $code === 'confidentiel' ? ' selected' : '' ?>><?= $h($l) ?></option><?php endforeach; ?></select>
                        </label>
                        <label><span>Et en clair pour ces membres (facultatif)</span>
                            <select name="allowed[]" multiple size="5"><?php foreach ($intakeMembers as $m): ?><option value="<?= (int) $m['id'] ?>"><?= $h($m['label']) ?></option><?php endforeach; ?></select>
                        </label>
                        <label><span>Motif</span><input type="text" name="reason" maxlength="255" placeholder="Protection de la source, opération en cours…"></label>
                        <p class="rmt__hint">Tous les autres lecteurs, sur le portail SSE et sur le téléphone en jeu, voient une barre ███ à la place du passage.</p>
                        <button type="submit" class="rmt__btn rmt__btn--primary">Caviarder</button>
                    </form>
                </details>
                <?php endif; ?>
            </div>

            <aside class="rmt__aside" aria-label="Propriétés">
                <section class="rmt__prop">
                    <h2>Attribuée à</h2>
                    <?php if ($it['assignee'] !== null): ?>
                        <p class="rmt__person"><span class="rmt__avatar"><?= $h($initials($it['assignee']['label'])) ?></span><?= $h($it['assignee']['label']) ?></p>
                    <?php else: ?>
                        <p class="rmt__muted">Personne.</p>
                    <?php endif; ?>
                    <form method="post" action="<?= $h($base . '/assign') ?>" class="rmt__inline">
                        <?= $csrf ?>
                        <label class="rmt__sr" for="rmt-assign">Attribuer à</label>
                        <select id="rmt-assign" name="user_id">
                            <option value="0">Personne</option>
                            <option value="<?= $me ?>">Moi</option>
                            <?php foreach ($intakeMembers as $m): ?><option value="<?= (int) $m['id'] ?>"<?= ($it['assignee']['id'] ?? 0) === $m['id'] ? ' selected' : '' ?>><?= $h($m['label']) ?></option><?php endforeach; ?>
                        </select>
                        <button type="submit" class="rmt__btn rmt__btn--small">OK</button>
                    </form>
                </section>

                <section class="rmt__prop">
                    <h2>Type de <?= $src === 'cr' ? 'compte rendu' : 'fiche' ?></h2>
                    <form method="post" action="<?= $h($base . '/type') ?>" class="rmt__inline">
                        <?= $csrf ?>
                        <label class="rmt__sr" for="rmt-type">Type</label>
                        <select id="rmt-type" name="type">
                            <?php foreach ($intakeTypes as $code => $label): ?><option value="<?= $h($code) ?>"<?= $it['type'] === $code ? ' selected' : '' ?>><?= $h($code . ' · ' . $label) ?></option><?php endforeach; ?>
                        </select>
                        <button type="submit" class="rmt__btn rmt__btn--small">OK</button>
                    </form>
                </section>

                <section class="rmt__prop">
                    <h2>Exploitation</h2>
                    <p><span class="rmt__pill rmt__pill--<?= $h($it['state']) ?>"><?= $stateIcon((string) $it['state']) ?><?= $h($it['state_label']) ?></span></p>
                </section>

                <section class="rmt__prop">
                    <h2>Caviardages <span class="rmt__count"><?= count($redactions) ?></span></h2>
                    <?php if ($redactions === []): ?>
                        <p class="rmt__muted">Aucun passage caviardé.</p>
                    <?php else: ?>
                        <ul class="rmt__reds">
                            <?php foreach ($redactions as $r): $readable = IntelRedaction::readable($r, $level, $me); ?>
                                <li>
                                    <p class="rmt__red-phrase"><?= $readable ? '« ' . $h(mb_strimwidth((string) $r['phrase'], 0, 90, '…')) . ' »' : '<span class="rmt-bar">' . $h(SseRedactionService::bar((string) $r['phrase'])) . '</span>' ?></p>
                                    <p class="rmt__muted"><?= $h(IntelRedaction::FIELDS[$src][$r['field']] ?? $r['field']) ?> · en clair dès « <?= $h(SseRedactionService::levelLabel((string) $r['clear_level'])) ?> »<?= $r['allowed'] !== [] ? ' + ' . count($r['allowed']) . ' membre(s)' : '' ?><?= !empty($r['reason']) ? ' · ' . $h($r['reason']) : '' ?></p>
                                    <?php if ($canRedact): ?>
                                        <form method="post" action="<?= $h($base . '/unredact') ?>">
                                            <?= $csrf ?>
                                            <input type="hidden" name="redaction_id" value="<?= (int) $r['id'] ?>">
                                            <button type="submit" class="rmt__link-btn">Lever</button>
                                        </form>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <?php if (!$canRedact): ?><p class="rmt__hint">Caviarder est réservé aux habilités « Diffusion très restreinte ».</p><?php endif; ?>
                </section>

                <section class="rmt__prop">
                    <h2>Lectures <span class="rmt__count"><?= count($it['reads']) ?></span></h2>
                    <?php if ($it['reads'] === []): ?>
                        <p class="rmt__muted">Personne d’autre ne l’a encore ouverte.</p>
                    <?php else: ?>
                        <ul class="rmt__reads">
                            <?php foreach ($it['reads'] as $rd): ?>
                                <li title="Première lecture <?= $h($when($rd['first'])) ?>"><span class="rmt__avatar rmt__avatar--sm"><?= $h($initials($rd['label'])) ?></span><?= $h($rd['label']) ?> <span class="rmt__muted"><?= $h(['bo' => 'back-office', 'web' => 'portail SSE', 'jeu' => 'en jeu'][$rd['channel']] ?? $rd['channel']) ?> · <?= $h($ago($rd['last'])) ?><?= $rd['count'] > 1 ? ' · ' . $rd['count'] . '×' : '' ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

                <section class="rmt__prop rmt__prop--danger">
                    <h2>Zone sensible</h2>
                    <?php if (empty($it['deleted'])): ?>
                        <form method="post" action="<?= $h($base . '/delete') ?>" onsubmit="return confirm('Supprimer cette remontée ? Elle restera restaurable depuis l’onglet Supprimées.');">
                            <?= $csrf ?>
                            <label class="rmt__sr" for="rmt-del">Motif</label>
                            <input id="rmt-del" type="text" name="reason" maxlength="255" placeholder="Motif de la suppression">
                            <button type="submit" class="rmt__btn rmt__btn--danger">Supprimer la remontée</button>
                        </form>
                    <?php else: ?>
                        <form method="post" action="<?= $h($base . '/restore') ?>">
                            <?= $csrf ?>
                            <button type="submit" class="rmt__btn">Restaurer</button>
                        </form>
                    <?php endif; ?>
                </section>
            </aside>
        </div>
    </div>
</div>
