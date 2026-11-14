<?php
declare(strict_types=1);
/**
 * Une page écrite ensemble, comme un document : lecture (cases à cocher, sondages, réactions, commentaires) ou édition.
 *
 * L'édition est un formulaire complet qui marche sans JavaScript (champs de texte, boutons « monter », « descendre »,
 * « supprimer » et « ajouter » : chaque geste est un envoi). Avec JavaScript, le même formulaire devient un éditeur de
 * document : le texte se modifie en place, sans cadre ni libellé ; Entrée, Retour arrière et « / » font le reste.
 *
 * @var array<string, mixed> $usw
 * @var string $base
 */
use App\Services\Jnet\JnetSpaceService;
use App\Services\Jnet\JnetUnitWorkspaceService as WS;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$page = is_array($usw['page'] ?? null) ? $usw['page'] : null;
$canPost = !empty($usw['canPost']);
$chief = !empty($usw['canModerate']);
$me = (int) ($usw['viewerId'] ?? 0);
$listUrl = $base . '?vue=pages&direct=1';

/** Tracés des icônes (trait de 1,8 px, grille de 24) : une chaîne par icône, tracés séparés par « | ». */
$icons = [
    'p' => 'M4 6h16|M4 12h16|M4 18h10',
    'h' => 'M6 5v14|M18 5v14|M6 12h12',
    'todo' => 'M5 4h14a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z|m8.5 12 2.5 2.5 4.5-5',
    'call' => 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18z|M12 11v5|M12 7.6v.01',
    'poll' => 'M5 20V11|M12 20V4|M19 20v-6',
    'count' => 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18z|M12 7v5l3 2',
    'div' => 'M4 12h16',
    'plus' => 'M12 5v14|M5 12h14',
    'grip' => 'M9 6v.01|M15 6v.01|M9 12v.01|M15 12v.01|M9 18v.01|M15 18v.01',
    'x' => 'M6 6l12 12|M18 6 6 18',
    'back' => 'M19 12H5|m11 18-6-6 6-6',
    'up' => 'm6 15 6-6 6 6',
    'down' => 'm6 9 6 6 6-6',
    'check' => 'm5 12 5 5 9-10',
    'vu' => 'M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z|M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z',
    'recu' => 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18z|m8.5 12 2.5 2.5 4.5-5',
    'question' => 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18z|M9.6 9.4a2.5 2.5 0 1 1 3.6 2.2c-.8.4-1.2 1-1.2 1.8|M12 17v.01',
    'send' => 'M5 12h14|m13 6 6 6-6 6',
    'edit' => 'M4 20h4L19 9l-4-4L4 16v4z|m13.5 6.5 4 4',
    'share' => 'M7 12v7a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-7|M12 15V4|m8 8 4-4 4 4',
];
$svg = static fn (string $key, string $class = 'ja-i'): string => '<svg class="' . $class . '" viewBox="0 0 24 24" aria-hidden="true">'
    . implode('', array_map(static fn (string $d): string => '<path d="' . htmlspecialchars($d, ENT_QUOTES, 'UTF-8') . '"/>', explode('|', $icons[$key] ?? ''))) . '</svg>';

if ($page === null): ?>
    <header class="us-page">
        <h1 class="ja-disp ja-disp--md">Page introuvable.</h1>
        <p>Cette page n’existe plus ou n’appartient pas à cet espace.</p>
        <a class="ja-b2 sm" href="<?= $h($listUrl) ?>">Retour aux pages</a>
    </header>
    <?php return; ?>
<?php endif;

$pid = (int) $page['id'];
$blocks = is_array($page['blocksList'] ?? null) ? $page['blocksList'] : [];
$votes = is_array($page['votes'] ?? null) ? $page['votes'] : [];
$voterLabels = is_array($page['voterLabels'] ?? null) ? $page['voterLabels'] : [];
$reactions = is_array($page['reactions'] ?? null) ? $page['reactions'] : ['counts' => [], 'mine' => []];
$comments = is_array($page['comments'] ?? null) ? $page['comments'] : [];
$event = is_array($page['event'] ?? null) ? $page['event'] : null;
$edit = !empty($page['edit']);
$self = $base . '?vue=page&p=' . $pid . '&direct=1';
$title = (string) $page['title'];
$canDelete = $canPost && ($chief || (int) $page['created_by'] === $me);
$action = static fn (string $suffix): string => $h($base . '/pages/' . $pid . $suffix);
$shareUrl = $base . '?vue=partager&kind=page&item=' . $pid . '&titre=' . rawurlencode(mb_substr($title !== '' ? $title : 'Page sans titre', 0, 120)) . '&direct=1';
$byLine = 'Modifiée par ' . (string) ($page['updated_by_label'] ?? 'un membre') . ' · ' . JnetSpaceService::relativeTime((string) $page['updated_at']);
?>
<article class="uw-page<?= $edit ? ' is-edit' : '' ?>" id="page">
    <nav class="uw-crumb" aria-label="Fil d’Ariane">
        <a class="uw-crumb__back" href="<?= $h($listUrl) ?>"><?= $svg('back') ?>Pages</a>
        <span class="uw-crumb__dot" aria-hidden="true">·</span>
        <span class="uw-crumb__by"><?= $h($byLine) ?></span>
        <span class="uw-page__sp"></span>
        <?php if (!$edit && !empty($usw['shareTargets'])): ?><a class="uw-crumb__act" href="<?= $h($shareUrl) ?>" data-uw-share data-kind="page" data-item="<?= $pid ?>" data-title="<?= $h($title !== '' ? $title : 'Page sans titre') ?>" data-back="<?= $h($self) ?>"><?= $svg('share') ?>Partager</a><?php endif; ?>
        <?php if ($canPost && !$edit): ?><a class="ja-b2 sm uw-crumb__edit" href="<?= $h($self . '&edit=1#page-title') ?>"><?= $svg('edit') ?>Modifier</a><?php endif; ?>
    </nav>

<?php if ($edit): ?>
    <?php $blocks = $blocks !== [] ? $blocks : [WS::newBlock('p')]; ?>
<?php
/** Un bloc de l'éditeur ; $i vaut l'indice, ou « __I__ » dans les gabarits que le script clone (Entrée, palette « / »). */
$renderBlock = static function (int|string $i, array $b, int $total) use ($h, $svg, $event): void {
    $t = (string) $b['t'];
    $name = 'blocks[' . $i . ']';
    $typeName = WS::BLOCK_TYPES[$t][0];
    $field = static fn (string $cls, string $ph, string $label, string $text, int $max, bool $line = false): string => '<textarea class="uw-ed ' . $cls . '" name="' . $name . '[text]" rows="1" maxlength="' . $max . '"' . ($line ? ' data-line' : '') . ' data-ph="' . $h($ph) . '" placeholder="' . $h($ph) . '" aria-label="' . $h($label) . '">' . $h($text) . '</textarea>';
?>
                <div class="uw-bk uw-bk--<?= $h($t) ?>" data-uw-block data-t="<?= $h($t) ?>" role="group" aria-label="<?= $h($typeName) ?>">
                    <input type="hidden" name="<?= $name ?>[id]" value="<?= $h((string) $b['id']) ?>">
                    <input type="hidden" name="<?= $name ?>[t]" value="<?= $h($t) ?>">
                    <div class="uw-bh">
                        <button class="uw-bh__b uw-js-only" type="button" hidden data-uw-add title="Insérer un bloc dessous" aria-label="Insérer un bloc dessous"><?= $svg('plus') ?></button>
                        <span class="uw-bh__b uw-grip uw-js-only" draggable="true" hidden role="button" tabindex="0" aria-haspopup="menu" data-uw-grip title="Glisser pour déplacer, cliquer pour le menu" aria-label="Déplacer le bloc"><?= $svg('grip') ?></span>
                        <span class="uw-nojs">
                            <button class="uw-bh__b" type="submit" name="act" value="up:<?= $i ?>" aria-label="Monter le bloc"<?= $i === 0 ? ' disabled' : '' ?>><?= $svg('up') ?></button>
                            <button class="uw-bh__b" type="submit" name="act" value="down:<?= $i ?>" aria-label="Descendre le bloc"<?= $i === $total - 1 ? ' disabled' : '' ?>><?= $svg('down') ?></button>
                        </span>
                    </div>
                    <div class="uw-bk__body">
                        <?php if ($t === 'h'): ?>
                            <?= $field('uw-ed--h', 'Titre', 'Titre', (string) $b['text'], WS::TEXT_MAX, true) ?>
                        <?php elseif ($t === 'div'): ?>
                            <hr>
                        <?php elseif ($t === 'count'): ?>
                            <div class="uw-count">
                                <?php if ($event !== null && (string) $event['countdown'] !== ''): ?>
                                    <b><?= $h((string) $event['countdown']) ?></b><span><?= $h((string) $event['title']) ?> · <?= $h((string) $event['when']) ?></span>
                                <?php else: ?>
                                    <b>J-?</b><span>Aucun rendez-vous programmé : le compte à rebours s’affichera dès qu’un événement l’est.</span>
                                <?php endif; ?>
                            </div>
                        <?php elseif ($t === 'poll'): ?>
                            <?php
                            $opts = array_values(array_filter((array) $b['opts'], static fn (string $o): bool => $o !== ''));
                            $rows = min(WS::POLL_OPTIONS_MAX, max(3, count($opts) + 1));
                            ?>
                            <div class="uw-poll">
                                <?= $field('uw-ed--q', 'Question du sondage', 'Question du sondage', (string) $b['text'], 200, true) ?>
                                <div class="uw-poll__opts" data-uw-opts>
                                    <?php for ($k = 0; $k < $rows; $k++): ?>
                                        <div class="uw-opt"><span class="uw-opt__dot" aria-hidden="true"></span><input type="text" name="<?= $name ?>[opts][]" value="<?= $h($opts[$k] ?? '') ?>" maxlength="<?= WS::POLL_OPTION_MAX ?>" placeholder="Choix <?= $k + 1 ?>" aria-label="Choix <?= $k + 1 ?>" autocomplete="off"></div>
                                    <?php endfor; ?>
                                </div>
                                <button class="uw-opt-add uw-js-only" type="button" hidden data-uw-opt-add><?= $svg('plus') ?>Ajouter un choix</button>
                                <small>2 à <?= WS::POLL_OPTIONS_MAX ?> choix. Un vote par membre, modifiable.</small>
                            </div>
                        <?php elseif ($t === 'call'): ?>
                            <div class="uw-callout"><span class="uw-callout__i"><?= $svg('call') ?></span><?= $field('', 'Point d’attention', 'Encadré', (string) $b['text'], WS::TEXT_MAX) ?></div>
                        <?php elseif ($t === 'todo'): ?>
                            <label class="uw-tk"><input type="checkbox" name="<?= $name ?>[done]" value="1"<?= !empty($b['done']) ? ' checked' : '' ?> aria-label="Fait"><span class="uw-tick"><?= $svg('check') ?></span></label>
                            <?= $field('', 'À faire', 'Tâche', (string) $b['text'], WS::TEXT_MAX) ?>
                        <?php else: ?>
                            <?= $field('', 'Tapez / pour insérer un bloc', 'Texte', (string) $b['text'], WS::TEXT_MAX) ?>
                        <?php endif; ?>
                    </div>
                    <button class="uw-del" type="submit" name="act" value="del:<?= $i ?>" title="Supprimer le bloc" aria-label="Supprimer le bloc"><?= $svg('x') ?></button>
                </div>
<?php
};
?>
    <form method="post" action="<?= $action('') ?>" class="uw-editor" data-uw-editor data-uw-read="<?= $h($self) ?>">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="version" value="<?= $h((string) $page['updated_at']) ?>">
        <button type="submit" name="act" value="save" class="ja-sr" tabindex="-1">Enregistrer</button>
        <textarea id="page-title" class="uw-title uw-ed uw-ed--t" name="title" rows="1" maxlength="<?= WS::TITLE_MAX ?>" data-line data-ph="Sans titre" placeholder="Sans titre" aria-label="Titre de la page" autocomplete="off"><?= $h($title) ?></textarea>
        <div class="uw-rule" aria-hidden="true"></div>
        <div id="blocks" class="uw-body" data-uw-blocks>
            <?php foreach ($blocks as $i => $b): ?>
                <?php $renderBlock($i, $b, count($blocks)); ?>
            <?php endforeach; ?>
        </div>
        <button class="uw-insert uw-js-only" type="button" hidden data-uw-add-end><?= $svg('plus') ?>Insérer un outil<span>ou tapez / dans une ligne vide</span></button>
        <div class="uw-addrow uw-nojs">
            <span>Ajouter</span>
            <?php foreach (WS::BLOCK_TYPES as $type => [$name, $hint]): ?>
                <button class="uw-addrow__b" type="submit" name="act" value="add:<?= $h($type) ?>" title="<?= $h($hint) ?>"><?= $svg($type) ?><?= $h($name) ?></button>
            <?php endforeach; ?>
        </div>
        <?php foreach (WS::BLOCK_TYPES as $type => [$typeName, $typeHint]): ?>
            <template data-uw-block-tpl="<?= $h($type) ?>" data-label="<?= $h($typeName) ?>" data-hint="<?= $h($typeHint) ?>" data-icon="<?= $h($icons[$type] ?? '') ?>"><?php $renderBlock('__I__', WS::newBlock($type), 0); ?></template>
        <?php endforeach; ?>
        <div class="uw-savebar" data-uw-bar>
            <span class="uw-status uw-js-only" hidden data-uw-status role="status" aria-live="polite"><i aria-hidden="true"></i><span data-uw-status-t>Enregistré</span><button type="button" class="uw-status__b" data-uw-retry hidden>Réessayer</button><a class="uw-status__b" data-uw-reload hidden href="<?= $h($self . '&edit=1#page-title') ?>">Recharger</a></span>
            <span class="uw-savebar__sp"></span>
            <a class="ja-b2 sm" href="<?= $h($self) ?>" data-uw-cancel>Annuler</a>
            <button class="ja-b1 sm" type="submit" name="act" value="save" title="Ctrl+S">Enregistrer</button>
        </div>
    </form>
<?php else: ?>
    <?php
    $hasContent = false;
    foreach ($blocks as $b) {
        if (trim((string) $b['text']) !== '' || in_array((string) $b['t'], ['div', 'count', 'poll'], true)) {
            $hasContent = true;
        }
    }
    ?>
    <h1 class="uw-title"><?= $h($title !== '' ? $title : 'Sans titre') ?></h1>
    <div class="uw-rule" aria-hidden="true"></div>
    <?php if (!$hasContent): ?>
        <div class="uw-empty">
            <p>Cette page est vide pour l’instant.</p>
            <?php if ($canPost): ?><a class="ja-b1 sm" href="<?= $h($self . '&edit=1#page-title') ?>">Commencer à écrire</a><?php endif; ?>
        </div>
    <?php endif; ?>
    <div class="uw-body uw-read">
        <?php foreach ($blocks as $b): ?>
            <?php $t = (string) $b['t']; $bid = (string) $b['id']; ?>
            <?php if ($t === 'p' && (string) $b['text'] === ''): ?><?php continue; ?><?php endif; ?>
            <div class="uw-rb uw-rb--<?= $h($t) ?>" id="b-<?= $h($bid) ?>">
                <?php if ($t === 'h'): ?>
                    <h2><?= $h((string) $b['text']) ?></h2>
                <?php elseif ($t === 'p'): ?>
                    <p><?= nl2br($h((string) $b['text'])) ?></p>
                <?php elseif ($t === 'div'): ?>
                    <hr>
                <?php elseif ($t === 'call'): ?>
                    <div class="uw-callout"><span class="uw-callout__i"><?= $svg('call') ?></span><p><?= nl2br($h((string) $b['text'])) ?></p></div>
                <?php elseif ($t === 'count'): ?>
                    <div class="uw-count">
                        <?php if ($event !== null && (string) $event['countdown'] !== ''): ?>
                            <b><?= $h((string) $event['countdown']) ?></b><span><?= $h((string) $event['title']) ?> · <?= $h((string) $event['when']) ?></span>
                        <?php else: ?>
                            <span>Aucun rendez-vous programmé pour l’instant.</span>
                        <?php endif; ?>
                    </div>
                <?php elseif ($t === 'todo'): ?>
                    <?php $done = !empty($b['done']); ?>
                    <?php if ($canPost): ?>
                        <form class="uw-todo" method="post" action="<?= $action('/case') ?>" data-uw-act="todo">
                            <?= \App\Core\Csrf::field() ?>
                            <input type="hidden" name="block" value="<?= $h($bid) ?>">
                            <button type="submit" class="uw-tick<?= $done ? ' is-on' : '' ?>" role="checkbox" aria-checked="<?= $done ? 'true' : 'false' ?>" aria-label="<?= $done ? 'Marquer comme à faire' : 'Marquer comme fait' ?> : <?= $h((string) $b['text']) ?>"><?= $svg('check') ?></button>
                            <span class="uw-todo__t<?= $done ? ' is-done' : '' ?>"><?= $h((string) $b['text']) ?></span>
                        </form>
                    <?php else: ?>
                        <p class="uw-todo"><span class="uw-tick<?= $done ? ' is-on' : '' ?>" aria-hidden="true"><?= $svg('check') ?></span><span class="uw-todo__t<?= $done ? ' is-done' : '' ?>"><?= $h((string) $b['text']) ?></span></p>
                    <?php endif; ?>
                <?php elseif ($t === 'poll'): ?>
                    <?php
                    $opts = array_values(array_filter((array) $b['opts'], static fn (string $o): bool => $o !== ''));
                    $tally = $votes[$bid] ?? [];
                    $total = 0;
                    foreach ($opts as $k => $_) {
                        $total += count($tally[$k] ?? []);
                    }
                    ?>
                    <div class="uw-poll" data-uw-poll>
                        <p class="uw-poll__q"><?= $h((string) $b['text'] !== '' ? (string) $b['text'] : 'Sondage') ?></p>
                        <?php foreach ($opts as $k => $o): ?>
                            <?php
                            $n = count($tally[$k] ?? []);
                            $mineVote = in_array($me, $tally[$k] ?? [], true);
                            $pct = $total > 0 ? round($n / $total * 100, 1) : 0;
                            $who = array_values(array_filter(array_map(static fn (mixed $uid): string => (string) ($voterLabels[(int) $uid] ?? ''), (array) ($tally[$k] ?? []))));
                            $whoTitle = $who !== [] ? implode(', ', array_slice($who, 0, 10)) . (count($who) > 10 ? '…' : '') : '';
                            ?>
                            <?php if ($canPost): ?>
                                <form method="post" action="<?= $action('/vote') ?>" data-uw-act="vote">
                                    <?= \App\Core\Csrf::field() ?>
                                    <input type="hidden" name="block" value="<?= $h($bid) ?>">
                                    <button class="uw-po<?= $mineVote ? ' is-on' : '' ?>" type="submit" name="option" value="<?= $k ?>" aria-pressed="<?= $mineVote ? 'true' : 'false' ?>" data-n="<?= $n ?>"<?= $whoTitle !== '' ? ' title="' . $h($whoTitle) . '"' : '' ?>><i style="--w: <?= $pct ?>%"></i><span class="uw-po__l"><?= $h($o) ?></span><span class="uw-po__n"><?= $n ?></span></button>
                                </form>
                            <?php else: ?>
                                <div class="uw-po<?= $mineVote ? ' is-on' : '' ?>" data-n="<?= $n ?>"<?= $whoTitle !== '' ? ' title="' . $h($whoTitle) . '"' : '' ?>><i style="--w: <?= $pct ?>%"></i><span class="uw-po__l"><?= $h($o) ?></span><span class="uw-po__n"><?= $n ?></span></div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <small data-uw-poll-total><?= $total ?> vote<?= $total > 1 ? 's' : '' ?> · un choix par membre<?= $canPost ? ', modifiable' : '' ?></small>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="uw-rx" id="reactions" role="group" aria-label="Réactions">
        <?php foreach (WS::REACTIONS as $k => $name): ?>
            <?php $on = in_array($k, (array) $reactions['mine'], true); $n = (int) ($reactions['counts'][$k] ?? 0); ?>
            <?php if ($canPost): ?>
                <form method="post" action="<?= $action('/reaction') ?>" data-uw-act="react">
                    <?= \App\Core\Csrf::field() ?>
                    <button class="uw-chip<?= $on ? ' is-on' : '' ?>" type="submit" name="kind" value="<?= $h($k) ?>" aria-pressed="<?= $on ? 'true' : 'false' ?>" data-n="<?= $n ?>"><?= $svg($k) ?><?= $h($name) ?><small><?= $n ?></small></button>
                </form>
            <?php else: ?>
                <span class="uw-chip<?= $on ? ' is-on' : '' ?>"><?= $svg($k) ?><?= $h($name) ?><small><?= $n ?></small></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <section class="uw-comments" id="comments" aria-label="Commentaires">
        <?php foreach ($comments as $c): ?>
            <div class="uw-comment">
                <span class="uw-comment__av" aria-hidden="true"><?= $h(mb_strtoupper(mb_substr((string) $c['label'], 0, 2))) ?></span>
                <div class="uw-comment__b"><p class="uw-comment__h"><b><?= $h((string) $c['label']) ?></b><small><?= $h(JnetSpaceService::relativeTime((string) $c['created_at'])) ?></small></p><p><?= nl2br($h((string) $c['body'])) ?></p></div>
            </div>
        <?php endforeach; ?>
        <?php if ($comments === []): ?>
            <p class="uw-note"><?= $canPost ? 'Aucun commentaire. Lancez la discussion.' : 'Aucun commentaire.' ?></p>
        <?php endif; ?>
        <?php if ($canPost): ?>
            <form class="uw-compose" method="post" action="<?= $action('/commentaire') ?>">
                <?= \App\Core\Csrf::field() ?>
                <label class="ja-sr" for="uw-comment">Commentaire</label>
                <input id="uw-comment" name="body" maxlength="<?= WS::COMMENT_MAX ?>" required placeholder="Commenter, puis Entrée" autocomplete="off">
                <button class="uw-compose__b" type="submit" aria-label="Publier le commentaire" title="Publier"><?= $svg('send') ?></button>
            </form>
        <?php endif; ?>
    </section>

    <?php if ($canDelete): ?>
        <details class="uw-danger">
            <summary>Supprimer la page</summary>
            <form method="post" action="<?= $action('/supprimer') ?>">
                <?= \App\Core\Csrf::field() ?>
                <p>La page « <?= $h($title !== '' ? $title : 'Sans titre') ?> » et ses commentaires ne seront plus visibles de l’unité.</p>
                <button class="ja-b2 sm uw-bad" type="submit">Supprimer la page</button>
            </form>
        </details>
    <?php endif; ?>
<?php endif; ?>
</article>
