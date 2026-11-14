<?php
declare(strict_types=1);
/**
 * Accueil de l'espace d'une unité : bandeau et chiffres, informations à prendre en compte, flux des ordres et échanges,
 * prochain rendez-vous (réponse de présence), fil de l'unité, opérations, tâches, pages, effectif, sous-unités, photos et
 * documents. Inclus par unit_space.php, dont il partage les variables ($h, $space, $look, $base, $label, $tabUrl…).
 */
use App\Services\Jnet\JnetUnitWorkspaceService as WS;

$wEvent = is_array($usw['event'] ?? null) ? $usw['event'] : null;
$wTasks = is_array($usw['tasks'] ?? null) ? $usw['tasks'] : [];
$wPages = is_array($usw['pages'] ?? null) ? $usw['pages'] : [];
$wFiles = is_array($usw['files'] ?? null) ? $usw['files'] : ['photos' => [], 'documents' => []];
$me = (int) ($usw['viewerId'] ?? 0);
$myTasks = array_values(array_filter($wTasks, static fn (array $t): bool => (int) ($t['assignee_user_id'] ?? 0) === $me && (string) $t['status'] !== 'done'));
$pendingAck = array_values(array_filter(array_merge($received, $internal), static fn (array $e): bool => !empty($e['requiresAck']) && empty($e['readByMe'])));
$latest = static fn (array $list): string => $list !== [] ? (string) $list[0]['title'] : '';
$flow = [
    ['↓', 'Reçu du commandement', count($received), $latest($received) ?: 'Rien de reçu pour l’instant.', $toRead],
    ['≡', 'Dans l’unité', count($internal), $latest($internal) ?: 'Aucun échange interne.', 0],
    ['↑', 'Remonté vers ' . ($hasParentUnit ? $parentLabel : 'l’échelon supérieur'), count($sentUp), $latest($sentUp) ?: 'Rien de remonté pour l’instant.', 0],
];
?>
    <section class="us-hero" aria-labelledby="us-title">
        <div class="us-hero__bg" style="background-image: url('<?= $h($heroPhoto) ?>')" aria-hidden="true"></div>
        <div class="us-hero__in">
            <?= $emblemHtml('ja-crest ja-crest--lg us-hero__crest') ?>
            <h1 class="ja-disp ja-disp--lg" id="us-title"><?= $h($label) ?></h1>
            <?php if ((string) $look['tagline'] !== ''): ?><p class="us-hero__tagline">« <?= $h((string) $look['tagline']) ?> »</p><?php endif; ?>
            <p class="us-hero__lead">
                <?php if ($kindLine !== ''): ?><span class="us-hero__kind"><?= $h($kindLine) ?></span><?php endif; ?>
                <span>Chef : <b><?= $h((string) ($space['leader'] ?? '') !== '' ? (string) $space['leader'] : 'non désigné') ?></b> · Adjoint : <b><?= $h((string) ($space['deputy'] ?? '') !== '' ? (string) $space['deputy'] : 'non désigné') ?></b></span>
            </p>
            <?php if (count($chain) > 1): ?>
            <nav class="us-hero__chain" aria-label="Échelons">
                <?php foreach ($chain as $c): ?>
                    <?php if ((int) $c['id'] === $unitId): ?><b><?= $h((string) $c['label']) ?></b><?php else: ?><a href="<?= $h((string) $c['href']) ?>"><?= $h((string) $c['label']) ?></a><span aria-hidden="true">›</span><?php endif; ?>
                <?php endforeach; ?>
            </nav>
            <?php endif; ?>
            <div class="us-hero__acts">
                <a class="ja-b1" href="<?= $h($tabUrl('echanges')) ?>">Ordres et échanges <svg class="ja-i" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
                <a class="ja-b2" href="<?= $h($tabUrl('effectif')) ?>">Effectif</a>
            </div>
        </div>
        <div class="ja-stats" style="--n: 4">
            <a class="ja-stat" href="<?= $h($tabUrl('effectif')) ?>"><b><?= (int) $strength['available'] ?><small> / <?= (int) $strength['total'] ?></small></b><span>disponibles</span></a>
            <a class="ja-stat" href="<?= $h($tabUrl('echanges')) ?>"><b><?= $toRead ?></b><span>à lire</span></a>
            <a class="ja-stat" href="<?= $h($tabUrl('operations')) ?>"><b><?= (int) ($spaceOpsTotal ?? count($spaceOps)) ?></b><span>opération<?= (int) ($spaceOpsTotal ?? count($spaceOps)) > 1 ? 's' : '' ?></span></a>
            <a class="ja-stat" href="<?= $h($tabUrl($subTree !== [] ? 'sous-unites' : 'accueil')) ?>"><b><?= count($subTree) ?></b><span>sous-unité<?= count($subTree) > 1 ? 's' : '' ?></span></a>
        </div>
    </section>

    <?php if ((string) $look['welcome'] !== ''): ?>
        <section class="us-welcome" aria-label="Mot du chef d’unité">
            <h2>Mot d’accueil</h2>
            <p><?= nl2br($h((string) $look['welcome'])) ?></p>
        </section>
    <?php endif; ?>

    <?php if ($pendingAck !== []): ?>
        <section class="uw-alert" aria-labelledby="uw-alert-title">
            <h2 id="uw-alert-title"><?= count($pendingAck) ?> information<?= count($pendingAck) > 1 ? 's' : '' ?> à prendre en compte</h2>
            <p>Votre prise en compte est horodatée et visible du commandement.</p>
            <ul>
                <?php foreach (array_slice($pendingAck, 0, 5) as $ex): ?>
                    <li>
                        <a href="<?= $h($tabUrl('echanges') . '#ex-' . (int) $ex['id']) ?>"><b><?= $h((string) $ex['title']) ?></b><small><?= $h((string) ($ex['from']['label'] ?? '')) ?> · <?= $h((string) $ex['when']) ?></small></a>
                        <form method="post" action="<?= $h(url('jnet/echanges/' . (int) $ex['id'] . '/lu')) ?>">
                            <?= \App\Core\Csrf::field() ?>
                            <input type="hidden" name="back" value="<?= $h($tabUrl('accueil')) ?>">
                            <button class="ja-b1 sm" type="submit">J’ai pris connaissance</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if (count($pendingAck) > 5): ?><a class="ja-sh__link" href="<?= $h($tabUrl('echanges')) ?>">Les <?= count($pendingAck) ?> informations →</a><?php endif; ?>
        </section>
    <?php endif; ?>

    <div class="us-grid">
        <div class="us-col">
            <section class="us-sec">
                <?= $section('Ordres et échanges', $tabUrl('echanges'), 'Tout voir') ?>
                <div class="ja-rows uw-flow">
                    <?php foreach ($flow as [$glyph, $name, $n, $last, $unread]): ?>
                        <a class="ja-row uw-flowrow" href="<?= $h($tabUrl('echanges')) ?>">
                            <span class="uw-gl" aria-hidden="true"><?= $glyph ?></span>
                            <span class="t"><b><?= $h($name) ?><?php if ($unread > 0): ?><span class="ja-pill" style="--p: var(--u)"><?= (int) $unread ?> à lire</span><?php endif; ?></b><small><?= $h($last) ?></small></span>
                            <span class="uw-flowrow__n ja-mono"><?= (int) $n ?></span>
                            <span class="ar" aria-hidden="true"><svg class="ja-i" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
            <section class="us-sec">
                <?= $section('Reçu du commandement', $tabUrl('echanges'), 'Tout voir') ?>
                <?php if (empty($exchangesReady)): ?>
                    <p class="us-empty">Les échanges ne sont pas encore activés.</p>
                <?php elseif ($received === []): ?>
                    <p class="us-empty">Aucun ordre ni consigne pour l’instant.</p>
                <?php else: ?>
                    <?php $exVariant = 'card'; $exPerspective = 'down'; foreach (array_slice($received, 0, 3) as $ex) { require base_path('views/jnet/_exchange.php'); } ?>
                <?php endif; ?>
            </section>
            <section class="us-sec">
                <?= $section('Fil de l’unité', $tabUrl('echanges'), 'Ouvrir le fil') ?>
                <?php if (!empty($exchangesReady) && !empty($canPost)): ?>
                    <?php require base_path('views/jnet/_exchange_composer.php'); ?>
                <?php endif; ?>
                <?php if ($internal === []): ?>
                    <p class="us-empty">Aucun échange interne. <?= !empty($canPost) ? 'Publiez le premier.' : '' ?></p>
                <?php else: ?>
                    <?php $exVariant = 'chat'; $exPerspective = ''; foreach (array_slice($internal, 0, 4) as $ex) { require base_path('views/jnet/_exchange.php'); } ?>
                <?php endif; ?>
            </section>
        </div>
        <aside class="us-col us-col--side">
            <?php if ($wEvent !== null): ?>
            <section class="us-sec">
                <?= $section('Prochain rendez-vous') ?>
                <?php $event = $wEvent; $evCanAnswer = $wCanPost; $evAction = $base . '/presence'; require base_path('views/jnet/unit/_event_card.php'); ?>
            </section>
            <?php endif; ?>
            <section class="us-sec">
                <?= $section('Opérations', $tabUrl('operations'), $spaceOps !== [] ? 'Toutes' : '') ?>
                <?php if ($spaceOps === []): ?>
                    <p class="us-empty">Aucune opération engagée.</p>
                <?php else: ?>
                    <ul class="us-ops us-ops--compact">
                        <?php foreach (array_slice($spaceOps, 0, 3) as $op): ?>
                            <li><a class="us-opcard" href="<?= $h(url('jnet/operations/' . (int) ($op['id'] ?? 0))) ?>">
                                <span class="us-opcard__state<?= in_array($op['state_key'] ?? '', ['active', 'in_progress'], true) ? ' is-live' : '' ?>"><?= $h((string) ($op['state'] ?? '')) ?></span>
                                <b><?= $h((string) ($op['title'] ?? '')) ?></b>
                            </a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
            <?php if (!empty($wShown['taches'])): ?>
            <section class="us-sec">
                <?= $section('Mes tâches', $tabUrl('taches'), 'Tableau') ?>
                <?php if ($myTasks === []): ?>
                    <p class="us-empty">Aucune tâche ne vous est confiée.</p>
                <?php else: ?>
                    <ul class="uw-mytasks">
                        <?php foreach (array_slice($myTasks, 0, 5) as $t): ?>
                            <li>
                                <?php if ($wCanPost): ?>
                                    <form method="post" action="<?= $h($base . '/taches/' . (int) $t['id']) ?>">
                                        <?= \App\Core\Csrf::field() ?>
                                        <input type="hidden" name="act" value="status">
                                        <button class="uw-tick" type="submit" name="status" value="done" aria-label="Marquer comme faite : <?= $h((string) $t['title']) ?>"><svg class="ja-i" viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg></button>
                                    </form>
                                <?php endif; ?>
                                <span><?= $h((string) $t['title']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if ($wCanPost): ?>
                    <form class="uw-add" method="post" action="<?= $h($base . '/taches') ?>">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="status" value="todo">
                        <input type="hidden" name="assignee" value="<?= $me ?>">
                        <label class="ja-sr" for="uw-home-task">Nouvelle tâche pour vous</label>
                        <input id="uw-home-task" name="title" maxlength="<?= WS::TASK_MAX ?>" required placeholder="+ Ajouter une tâche, puis Entrée" autocomplete="off">
                    </form>
                <?php endif; ?>
            </section>
            <?php endif; ?>
            <?php if (!empty($wShown['pages'])): ?>
            <section class="us-sec">
                <?= $section('Pages de l’unité', $tabUrl('pages'), 'Toutes') ?>
                <?php if ($wPages === []): ?>
                    <p class="us-empty">Aucune page.<?= $wCanPost ? ' Créez la première depuis la rubrique Pages.' : '' ?></p>
                <?php else: ?>
                    <div class="ja-rows">
                        <?php foreach (array_slice($wPages, 0, 4) as $i => $pg): ?>
                            <a class="ja-row uw-sidepage" href="<?= $h($base . '?vue=page&p=' . (int) $pg['id'] . '&direct=1') ?>">
                                <span class="n"><?= $pad2($i + 1) ?></span>
                                <span class="t"><b><?= $h((string) ($pg['title'] !== '' ? $pg['title'] : 'Sans titre')) ?></b><small>Modifiée par <?= $h((string) ($pg['updated_by_label'] ?? 'un membre')) ?></small></span>
                                <span class="ar" aria-hidden="true"><svg class="ja-i" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
            <?php endif; ?>
            <section class="us-sec">
                <?= $section('Effectif', $tabUrl('effectif'), 'Voir tous') ?>
                <?php if ($members === []): ?>
                    <p class="us-empty">Aucun membre affecté directement.</p>
                <?php else: ?>
                    <ul class="us-faces">
                        <?php foreach (array_slice($members, 0, 10) as $p): ?>
                            <li><a class="us-member__face<?= ($p['duty'] ?? '') === 'off' ? ' is-off' : '' ?>" href="<?= $h((string) ($p['href'] ?? '#')) ?>" title="<?= $h((string) ($p['name'] ?? '')) ?>"><?= $face($p) ?></a></li>
                        <?php endforeach; ?>
                        <?php if (count($members) > 10): ?><li class="us-faces__more">+<?= count($members) - 10 ?></li><?php endif; ?>
                    </ul>
                <?php endif; ?>
            </section>
            <?php if ($subTree !== []): ?>
            <section class="us-sec">
                <?= $section('Sous-unités', $tabUrl('sous-unites'), 'Organigramme') ?>
                <ul class="us-subs">
                    <?php foreach (array_slice($subTree, 0, 6) as $sub): ?>
                        <li><a href="<?= $h((string) $sub['href']) ?>"<?= (string) ($sub['accent'] ?? '') !== '' ? ' style="--sub: ' . $h((string) $sub['accent']) . '"' : '' ?>>
                            <b><?= $h((string) $sub['label']) ?></b>
                            <small><?= (int) ($sub['strength']['available'] ?? 0) ?>/<?= (int) ($sub['strength']['total'] ?? 0) ?> disponibles<?= (string) ($sub['leader'] ?? '') !== '' ? ' · ' . $h((string) $sub['leader']) : '' ?></small>
                        </a></li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endif; ?>
        </aside>
    </div>

    <?php if (!empty($wShown['photos']) && !empty($wFiles['photos'])): ?>
        <section class="us-sec us-sec--wide">
            <?= $section('Photos', $tabUrl('photos'), 'Tout voir') ?>
            <div class="uw-photos uw-photos--strip">
                <?php foreach (array_slice($wFiles['photos'], 0, 4) as $p): ?>
                    <a class="uw-photo" href="<?= $h($p['preview']) ?>" target="_blank" rel="noopener">
                        <img src="<?= $h($p['preview']) ?>" alt="<?= $h($p['title']) ?>" loading="lazy" decoding="async">
                        <span class="uw-photo__cap"><?= $h($p['title']) ?><small><?= $h($p['album']) ?></small></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
    <?php if (!empty($wShown['documents']) && !empty($wFiles['documents'])): ?>
        <section class="us-sec us-sec--wide">
            <?= $section('Documents', $tabUrl('documents'), 'Tout voir') ?>
            <div class="ja-rows uw-docs">
                <?php foreach (array_slice($wFiles['documents'], 0, 4) as $d): ?>
                    <a class="ja-row uw-doc" href="<?= $h($d['href']) ?>">
                        <span class="uw-ext"><?= $h($d['ext']) ?></span>
                        <span class="t"><b><?= $h($d['title']) ?></b><small><?= $h($d['album']) ?></small></span>
                        <span class="m uw-doc__meta"><?= $h(trim($d['date'] . ($d['size'] !== '' ? ' · ' . $d['size'] : ''), ' ·')) ?></span>
                        <span class="ar" aria-hidden="true"><svg class="ja-i" viewBox="0 0 24 24"><path d="M12 4v12M6 12l6 6 6-6M5 20h14"/></svg></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
