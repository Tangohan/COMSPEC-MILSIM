<?php
declare(strict_types=1);
/**
 * Une page écrite ensemble : lecture (cases à cocher, sondages, réactions, commentaires) ou édition (formulaire de
 * blocs, qui marche sans JavaScript : chaque geste de l'éditeur est un envoi de formulaire, jamais une fenêtre).
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
if ($page === null): ?>
    <header class="us-page">
        <h1 class="ja-disp ja-disp--md">Page introuvable.</h1>
        <p>Cette page n’existe plus ou n’appartient pas à cet espace.</p>
        <a class="us-btn" href="<?= $h($listUrl) ?>">Retour aux pages</a>
    </header>
    <?php return; ?>
<?php endif;

$pid = (int) $page['id'];
$blocks = is_array($page['blocksList'] ?? null) ? $page['blocksList'] : [];
$votes = is_array($page['votes'] ?? null) ? $page['votes'] : [];
$reactions = is_array($page['reactions'] ?? null) ? $page['reactions'] : ['counts' => [], 'mine' => []];
$comments = is_array($page['comments'] ?? null) ? $page['comments'] : [];
$event = is_array($page['event'] ?? null) ? $page['event'] : null;
$edit = !empty($page['edit']);
$self = $base . '?vue=page&p=' . $pid . '&direct=1';
$title = (string) $page['title'];
$canDelete = $canPost && ($chief || (int) $page['created_by'] === $me);
$action = static fn (string $suffix): string => $h($base . '/pages/' . $pid . $suffix);
?>
<article class="uw-page" id="page">
    <div class="uw-page__meta">
        <a href="<?= $h($listUrl) ?>">← Pages</a>
        <span aria-hidden="true">·</span>
        <span>Modifiée par <?= $h((string) ($page['updated_by_label'] ?? 'un membre')) ?> · <?= $h(JnetSpaceService::relativeTime((string) $page['updated_at'])) ?></span>
        <span class="uw-page__sp"></span>
        <?php if ($canPost && !$edit): ?><a class="uw-link" href="<?= $h($self . '&edit=1#page-title') ?>">Modifier</a><?php endif; ?>
        <?php if (!$edit && !empty($usw['shareTargets'])): ?><a class="uw-link" href="<?= $h($base . '?vue=partager&kind=page&item=' . $pid . '&titre=' . rawurlencode(mb_substr($title !== '' ? $title : 'Page sans titre', 0, 120)) . '&direct=1') ?>" data-uw-share data-kind="page" data-item="<?= $pid ?>" data-title="<?= $h($title !== '' ? $title : 'Page sans titre') ?>" data-back="<?= $h($self) ?>">Partager</a><?php endif; ?>
    </div>

<?php if ($edit): ?>
    <?php $blocks = $blocks !== [] ? $blocks : [WS::newBlock('p')]; ?>
<?php
/** Un bloc de l'éditeur ; $i vaut l'indice, ou « __I__ » dans les gabarits que le script clone (Entrée, palette « / »). */
$renderBlock = static function (int|string $i, array $b, int $total) use ($h, $event): void {
?>
                <?php $t = (string) $b['t']; $name = 'blocks[' . $i . ']'; $num = is_int($i) ? $i + 1 : ''; ?>
                <fieldset class="uw-block uw-block--<?= $h($t) ?>" data-uw-block>
                    <legend class="ja-sr">Bloc <?= $num ?> : <?= $h(WS::BLOCK_TYPES[$t][0]) ?></legend>
                    <input type="hidden" name="<?= $name ?>[id]" value="<?= $h((string) $b['id']) ?>">
                    <input type="hidden" name="<?= $name ?>[t]" value="<?= $h($t) ?>">
                    <div class="uw-block__body">
                        <span class="uw-block__type"><?= $h(WS::BLOCK_TYPES[$t][0]) ?></span>
                        <?php if ($t === 'h'): ?>
                            <input class="uw-block__h" name="<?= $name ?>[text]" value="<?= $h((string) $b['text']) ?>" maxlength="<?= WS::TEXT_MAX ?>" placeholder="Titre" aria-label="Titre du bloc <?= $num ?>">
                        <?php elseif ($t === 'div'): ?>
                            <hr>
                        <?php elseif ($t === 'count'): ?>
                            <p class="uw-note">Jours restants avant le prochain rendez-vous de la communauté<?= $event !== null ? ' : ' . $h((string) $event['title']) : ' (aucun programmé)' ?>.</p>
                        <?php elseif ($t === 'poll'): ?>
                            <input class="uw-block__h" name="<?= $name ?>[text]" value="<?= $h((string) $b['text']) ?>" maxlength="200" placeholder="Question" aria-label="Question du sondage">
                            <label class="uw-block__label" for="opts-<?= $i ?>">Choix, un par ligne (2 à <?= WS::POLL_OPTIONS_MAX ?>)</label>
                            <textarea id="opts-<?= $i ?>" name="<?= $name ?>[opts]" rows="3" placeholder="Jeudi 21:00&#10;Dimanche 21:00"><?= $h(implode("\n", array_filter((array) $b['opts'], static fn (string $o): bool => $o !== ''))) ?></textarea>
                        <?php else: ?>
                            <?php if ($t === 'todo'): ?>
                                <label class="uw-block__check"><input type="checkbox" name="<?= $name ?>[done]" value="1"<?= !empty($b['done']) ? ' checked' : '' ?>> Fait</label>
                            <?php endif; ?>
                            <textarea name="<?= $name ?>[text]" rows="<?= $t === 'p' ? 3 : 2 ?>" maxlength="<?= WS::TEXT_MAX ?>" placeholder="<?= $t === 'todo' ? 'À faire' : ($t === 'call' ? 'Point d’attention' : 'Écrivez ici') ?>" aria-label="Contenu du bloc <?= $num ?>"><?= $h((string) $b['text']) ?></textarea>
                        <?php endif; ?>
                    </div>
                    <div class="uw-block__tools">
                        <span class="uw-grip uw-js-only" draggable="true" hidden aria-hidden="true" title="Glisser pour déplacer le bloc">⋮⋮</span>
                        <button type="submit" name="act" value="up:<?= $i ?>" aria-label="Monter le bloc <?= $num ?>"<?= $i === 0 ? ' disabled' : '' ?>>↑</button>
                        <button type="submit" name="act" value="down:<?= $i ?>" aria-label="Descendre le bloc <?= $num ?>"<?= $i === $total - 1 ? ' disabled' : '' ?>>↓</button>
                        <button type="submit" name="act" value="del:<?= $i ?>" aria-label="Supprimer le bloc <?= $num ?>">×</button>
                    </div>
                </fieldset>
<?php
};
?>
    <form method="post" action="<?= $action('') ?>" class="uw-editor">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="version" value="<?= $h((string) $page['updated_at']) ?>">
        <button type="submit" name="act" value="save" class="ja-sr" tabindex="-1">Enregistrer</button>
        <label class="ja-sr" for="page-title">Titre de la page</label>
        <input id="page-title" class="uw-page__title" name="title" value="<?= $h($title) ?>" maxlength="<?= WS::TITLE_MAX ?>" placeholder="Sans titre" autocomplete="off">
        <div id="blocks" class="uw-blocks" data-uw-blocks>
            <?php foreach ($blocks as $i => $b): ?>
                <?php $renderBlock($i, $b, count($blocks)); ?>
            <?php endforeach; ?>
        </div>
        <div class="uw-addblock">
            <p>Ajouter un bloc</p>
            <div class="ja-chips">
                <?php foreach (WS::BLOCK_TYPES as $type => [$name, $hint]): ?>
                    <button class="ja-chip" type="submit" name="act" value="add:<?= $h($type) ?>" title="<?= $h($hint) ?>">+ <?= $h($name) ?></button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php foreach (WS::BLOCK_TYPES as $type => [$typeName, $typeHint]): ?>
            <template data-uw-block-tpl="<?= $h($type) ?>" data-label="<?= $h($typeName) ?>" data-hint="<?= $h($typeHint) ?>"><?php $renderBlock('__I__', WS::newBlock($type), 0); ?></template>
        <?php endforeach; ?>
        <div class="uw-form-actions">
            <button class="ja-b1 sm" type="submit" name="act" value="save">Enregistrer la page</button>
            <a class="ja-b2 sm" href="<?= $h($self) ?>">Annuler</a>
        </div>
    </form>
<?php else: ?>
    <h1 class="uw-page__title"><?= $h($title !== '' ? $title : 'Sans titre') ?></h1>
    <div class="uw-read">
        <?php foreach ($blocks as $b): ?>
            <?php $t = (string) $b['t']; $bid = (string) $b['id']; ?>
            <div class="uw-rb uw-rb--<?= $h($t) ?>" id="b-<?= $h($bid) ?>">
                <?php if ($t === 'h'): ?>
                    <h2><?= $h((string) $b['text']) ?></h2>
                <?php elseif ($t === 'p'): ?>
                    <?php if ((string) $b['text'] !== ''): ?><p><?= nl2br($h((string) $b['text'])) ?></p><?php endif; ?>
                <?php elseif ($t === 'div'): ?>
                    <hr>
                <?php elseif ($t === 'call'): ?>
                    <div class="uw-callout"><span aria-hidden="true">!</span><p><?= nl2br($h((string) $b['text'])) ?></p></div>
                <?php elseif ($t === 'count'): ?>
                    <div class="uw-count">
                        <?php if ($event !== null && (string) $event['countdown'] !== ''): ?>
                            <b><?= $h((string) $event['countdown']) ?></b><span><?= $h((string) $event['title']) ?> · <?= $h((string) $event['when']) ?></span>
                        <?php else: ?>
                            <span>Aucun rendez-vous programmé pour l’instant.</span>
                        <?php endif; ?>
                    </div>
                <?php elseif ($t === 'todo'): ?>
                    <?php if ($canPost): ?>
                        <form class="uw-todo" method="post" action="<?= $action('/case') ?>">
                            <?= \App\Core\Csrf::field() ?>
                            <input type="hidden" name="block" value="<?= $h($bid) ?>">
                            <button type="submit" class="uw-tick<?= !empty($b['done']) ? ' is-on' : '' ?>" role="checkbox" aria-checked="<?= !empty($b['done']) ? 'true' : 'false' ?>" aria-label="<?= !empty($b['done']) ? 'Marquer comme à faire' : 'Marquer comme fait' ?> : <?= $h((string) $b['text']) ?>"><svg class="ja-i" viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg></button>
                            <span class="<?= !empty($b['done']) ? 'is-done' : '' ?>"><?= $h((string) $b['text']) ?></span>
                        </form>
                    <?php else: ?>
                        <p class="uw-todo"><span class="uw-tick<?= !empty($b['done']) ? ' is-on' : '' ?>" aria-hidden="true"><svg class="ja-i" viewBox="0 0 24 24"><path d="m5 12 5 5 9-10"/></svg></span><span class="<?= !empty($b['done']) ? 'is-done' : '' ?>"><?= $h((string) $b['text']) ?></span></p>
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
                    <div class="uw-poll">
                        <p class="uw-poll__q"><?= $h((string) $b['text'] !== '' ? (string) $b['text'] : 'Sondage') ?></p>
                        <?php foreach ($opts as $k => $o): ?>
                            <?php $n = count($tally[$k] ?? []); $mineVote = in_array($me, $tally[$k] ?? [], true); ?>
                            <?php if ($canPost): ?>
                                <form method="post" action="<?= $action('/vote') ?>">
                                    <?= \App\Core\Csrf::field() ?>
                                    <input type="hidden" name="block" value="<?= $h($bid) ?>">
                                    <button class="uw-po<?= $mineVote ? ' is-on' : '' ?>" type="submit" name="option" value="<?= $k ?>" aria-pressed="<?= $mineVote ? 'true' : 'false' ?>"><i style="width: <?= $total > 0 ? round($n / $total * 100, 1) : 0 ?>%"></i><span><?= $h($o) ?></span><span class="ja-mono"><?= $n ?></span></button>
                                </form>
                            <?php else: ?>
                                <div class="uw-po<?= $mineVote ? ' is-on' : '' ?>"><i style="width: <?= $total > 0 ? round($n / $total * 100, 1) : 0 ?>%"></i><span><?= $h($o) ?></span><span class="ja-mono"><?= $n ?></span></div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <small><?= $total ?> vote<?= $total > 1 ? 's' : '' ?> · un choix par membre<?= $canPost ? ', modifiable' : '' ?></small>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="uw-rx" id="reactions">
        <?php foreach (WS::REACTIONS as $k => $name): ?>
            <?php $on = in_array($k, (array) $reactions['mine'], true); $n = (int) ($reactions['counts'][$k] ?? 0); ?>
            <?php if ($canPost): ?>
                <form method="post" action="<?= $action('/reaction') ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <button class="ja-chip<?= $on ? ' is-on' : '' ?>" type="submit" name="kind" value="<?= $h($k) ?>" aria-pressed="<?= $on ? 'true' : 'false' ?>"><?= $h($name) ?> <small class="ja-mono"><?= $n ?></small></button>
                </form>
            <?php else: ?>
                <span class="ja-chip"><?= $h($name) ?> <small class="ja-mono"><?= $n ?></small></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <section class="uw-comments" id="comments" aria-label="Commentaires">
        <?php foreach ($comments as $c): ?>
            <div class="uw-comment">
                <span class="uw-comment__av" aria-hidden="true"><?= $h(mb_strtoupper(mb_substr((string) $c['label'], 0, 2))) ?></span>
                <div><b><?= $h((string) $c['label']) ?></b><small><?= $h(JnetSpaceService::relativeTime((string) $c['created_at'])) ?></small><p><?= nl2br($h((string) $c['body'])) ?></p></div>
            </div>
        <?php endforeach; ?>
        <?php if ($canPost): ?>
            <form class="uw-inline" method="post" action="<?= $action('/commentaire') ?>">
                <?= \App\Core\Csrf::field() ?>
                <label class="ja-sr" for="uw-comment">Commentaire</label>
                <input id="uw-comment" name="body" maxlength="<?= WS::COMMENT_MAX ?>" required placeholder="Commenter, puis Entrée" autocomplete="off">
                <button class="ja-b2 sm" type="submit">Commenter</button>
            </form>
        <?php elseif ($comments === []): ?>
            <p class="uw-note">Aucun commentaire.</p>
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
