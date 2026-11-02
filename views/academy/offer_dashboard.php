<?php
/**
 * Tableau de bord Academy du membre : où il en est, quoi faire maintenant, ce qu'il détient, ce qui avance tout seul.
 * Données : App\Services\Academy\AcademyOffer (même source que le catalogue).
 */
require __DIR__ . '/offer_init.php';
$totals = $offer['totals'] ?? [];
$byState = static fn (array $states, ?string $kind = null): array => array_values(array_filter($offerItems, static fn (array $i): bool => in_array($i['state'], $states, true) && ($kind === null || $i['kind'] === $kind)));
$coursesActive = $byState(['progress', 'pending'], 'course');
$held = array_values(array_filter($offerItems, static fn (array $i): bool => in_array($i['state'], ['held', 'expiring', 'renew', 'expired'], true) && ($i['kind'] === 'qualification' || !empty($i['licence_issued']))));
$expiring = array_values(array_filter($held, static fn (array $i): bool => in_array($i['state'], ['expiring', 'renew', 'expired'], true)));
$autoProgress = array_values(array_filter($offerItems, static fn (array $i): bool => $i['kind'] === 'qualification' && $i['pct'] !== null && in_array($i['state'], ['progress', 'eligible', 'pending', 'missing'], true) && ($i['pct'] > 0 || $i['state'] === 'eligible')));
usort($autoProgress, static fn (array $a, array $b): int => $b['pct'] <=> $a['pct']);
$available = $byState(['available'], 'course');
usort($available, static fn (array $a, array $b): int => [$a['source'] === 'athena' ? 1 : 0, $a['title']] <=> [$b['source'] === 'athena' ? 1 : 0, $b['title']]);
// Prochain objectif : la qualification la plus proche, sinon une formation à commencer.
$goal = $autoProgress[0] ?? ($available[0] ?? null);

// À faire maintenant.
$todo = [];
foreach ($coursesActive as $c) {
    if ($c['state'] === 'pending') {
        $todo[] = ['icon' => 'clock', 'kind' => 'wait', 'item' => $c];
    } elseif (($c['next']['stage'] ?? '') === 'assessment') {
        $todo[] = ['icon' => 'qualification', 'kind' => 'exam', 'item' => $c];
    } else {
        $todo[] = ['icon' => 'course', 'kind' => 'resume', 'item' => $c];
    }
}
foreach ($expiring as $q) {
    $todo[] = ['icon' => 'alert', 'kind' => 'renew', 'item' => $q];
}
foreach ($byState(['eligible'], 'qualification') as $q) {
    $todo[] = ['icon' => 'check', 'kind' => 'eligible', 'item' => $q];
}
$newcomer = $coursesActive === [] && $held === [] && $autoProgress === [] && $byState(['held'], 'course') === [];
$name = trim((string) ($account['display_name'] ?? ''));
$todoUrl = static function (array $row) use ($link, $oxFiche): string {
    $it = $row['item'];
    if ($it['kind'] === 'course') {
        return isset($it['course_key']) ? $link('classroom') . '?course=' . rawurlencode((string) $it['course_key']) : (string) ($it['url'] ?? $oxFiche($it['id']));
    }
    return $oxFiche((string) $it['id']);
};
// Couverture d'une formation : celle choisie dans le Studio, sinon une des couvertures Academy (stable pour une formation donnée).
$coverByKey = [];
foreach ($courses ?? [] as $c) {
    if (is_array($c) && isset($c['key'])) {
        $coverByKey[(string) $c['key']] = (string) ($c['cover'] ?? '');
    }
}
foreach ($states ?? [] as $k => $st) {
    if (is_array($st) && isset($st['snapshot']['cover'])) {
        $coverByKey[(string) $k] = (string) $st['snapshot']['cover'];
    }
}
$tileCover = static function (array $it) use ($coverByKey): string {
    $covers = \App\Services\Academy\AcademyStudio::COVERS;
    $cover = $coverByKey[(string) ($it['course_key'] ?? '')] ?? '';
    if (!in_array($cover, $covers, true)) {
        $cover = $covers[crc32((string) $it['id']) % count($covers)];
    }
    return asset_url('assets/images/academy/cover-' . $cover . '.webp');
};
$eligibleCount = count($byState(['eligible'], 'qualification'));
$shortcutIcons = ['formations' => 'course', 'brevets' => 'brevet', 'qualifications' => 'qualification', 'parcours' => 'pathway', 'competences' => 'competency', 'emetteurs' => 'issuer'];
?>
<div class="acx acx--dashboard">
<header class="acd-hero" style="--img:url('<?= $h(asset_url('assets/images/academy/cover-hero.webp')) ?>')">
    <div class="acd-hero__in">
        <div class="acd-hero__text">
            <p class="acd-hero__kk"><i aria-hidden="true"></i><span>ATHENA ACADEMY · <span>Tableau de bord</span></span></p>
            <h1><?php if ($name !== ''): ?><span>Bonjour</span> <span translate="no"><?= $h($name) ?></span><?php else: ?><span>Votre tableau de bord</span><?php endif ?></h1>
            <p class="acd-hero__lead">Vos formations, vos qualifications et ce qu’il vous reste à faire.</p>
        </div>
        <a class="button acd-hero__cta" href="<?= $h($link('catalogue')) ?>"><span>Explorer le catalogue</span><?= $oxSymbol('arrow') ?></a>
    </div>
    <?php if (!$newcomer): ?>
    <dl class="acd-hero__stats">
        <div><dt>Formations en cours</dt><dd><?= count($coursesActive) ?></dd></div>
        <div><dt>Acquis</dt><dd class="is-ok"><?= count($held) ?></dd></div>
        <div><dt>À renouveler</dt><dd<?= $expiring !== [] ? ' class="is-warn"' : '' ?>><?= count($expiring) ?></dd></div>
        <div><dt>Conditions remplies</dt><dd><?= $eligibleCount ?></dd></div>
    </dl>
    <?php endif ?>
</header>

<?php if ($newcomer): ?>
<section class="acd-section acd-start" aria-labelledby="acx-start-title">
    <h2 id="acx-start-title" class="acd-h2">Commencez par…</h2>
    <ol class="acd-steps">
        <li class="acd-step">
            <span class="acd-step__icon"><?= $oxSymbol('course') ?></span>
            <b>Choisir une première formation.</b>
            <span class="acd-step__text">Chaque formation se suit à votre rythme, étape par étape.</span>
            <a class="button small" href="<?= $h($oxUrl(['onglet' => 'formations'])) ?>"><span>Voir les formations</span></a>
        </li>
        <?php if (($totals['qualifications'] ?? 0) > 0): ?>
        <li class="acd-step">
            <span class="acd-step__icon"><?= $oxSymbol('qualification') ?></span>
            <b>Découvrir les qualifications de votre communauté.</b>
            <span class="acd-step__text">Certaines s’obtiennent automatiquement en jouant, d’autres avec un instructeur.</span>
            <a class="button small outline" href="<?= $h($oxUrl(['onglet' => 'qualifications'])) ?>"><span>Voir les qualifications</span> (<?= (int) $totals['qualifications'] ?>)</a>
        </li>
        <?php endif ?>
        <li class="acd-step">
            <span class="acd-step__icon"><?= $oxSymbol('pathway') ?></span>
            <b>Viser une fonction ou une compétence.</b>
            <span class="acd-step__text">Le générateur de parcours propose les étapes dans l’ordre.</span>
            <a class="button small outline" href="<?= $h(url('academy/formations/competences') . '#generateur') ?>"><span>Générer un parcours</span></a>
        </li>
    </ol>
    <?php if ($available !== []): ?>
    <h3 class="acd-h3">Formations pour bien démarrer</h3>
    <div class="acd-tiles"><?php foreach (array_slice($available, 0, 3) as $it) { require __DIR__ . '/offer_course_tile.php'; } ?></div>
    <?php endif ?>
</section>
<?php else: ?>
<section class="acd-section" aria-labelledby="acx-where-title">
    <h2 id="acx-where-title" class="acd-h2">Où j’en suis</h2>
    <div class="acd-where">
        <div class="acd-where__courses">
            <?php if ($coursesActive === []): ?>
                <div class="acd-empty">
                    <span class="acd-empty__art"><?= $oxSymbol('course') ?></span>
                    <p><b>Aucune formation en cours.</b></p>
                    <a class="button small" href="<?= $h($oxUrl(['onglet' => 'formations', 'etat' => 'disponible'])) ?>"><span>Choisir une formation</span></a>
                </div>
            <?php else: ?>
                <div class="acd-tiles acd-tiles--where"><?php foreach (array_slice($coursesActive, 0, 2) as $it) { require __DIR__ . '/offer_course_tile.php'; } ?></div>
            <?php endif ?>
        </div>
        <article class="acd-goal">
            <h3>Prochain objectif</h3>
            <?php if ($goal === null): ?><p class="acd-goal__empty">Tout est à jour. Parcourez le catalogue pour viser plus haut.</p>
            <?php else: ?>
                <p class="acd-goal__kind"><?= $oxBadge($goal) ?><span><?= $h($goal['kind'] === 'course' ? 'Formation à commencer' : 'Qualification la plus proche') ?></span></p>
                <p class="acd-goal__title"><a href="<?= $h($oxFiche($goal['id'])) ?>" translate="no"><?= $h($goal['title']) ?></a></p>
                <?php if ($goal['pct'] !== null && $goal['kind'] !== 'course'): ?><p class="acd-goal__pct"><b><?= (int) $goal['pct'] ?></b> %</p><div class="acd-seg"><?= $oxBar((int) $goal['pct'], 'Progression') ?></div><?php endif ?>
                <?php $it = $goal; require __DIR__ . '/offer_actions.php'; ?>
            <?php endif ?>
        </article>
    </div>
</section>
<?php endif ?>

<?php if (!$newcomer || $todo !== []): ?>
<section class="acd-section" aria-labelledby="acx-todo-title">
    <h2 id="acx-todo-title" class="acd-h2">À faire maintenant <?php if ($todo !== []): ?><b class="acx-count"><?= count($todo) ?></b><?php endif ?></h2>
    <?php if ($todo === []): ?>
        <div class="acd-empty acd-empty--row">
            <span class="acd-empty__art"><?= $oxSymbol('check') ?></span>
            <p><b>Rien d’urgent.</b> <a class="text-link" href="<?= $h($link('catalogue')) ?>"><span>Choisissez votre prochaine formation dans le catalogue</span></a></p>
        </div>
    <?php else: ?>
    <ul class="acd-todo">
    <?php foreach (array_slice($todo, 0, 8) as $row): $it = $row['item']; ?>
        <li class="acd-todo__row acd-todo__row--<?= $h($row['kind']) ?>">
            <span class="acd-todo__icon"><?= $oxSymbol($row['icon']) ?></span>
            <div class="acd-todo__text">
                <?php if ($row['kind'] === 'resume'): ?><b>Reprendre la leçon</b><?php elseif ($row['kind'] === 'exam'): ?><b>Passer l’évaluation</b><?php elseif ($row['kind'] === 'wait'): ?><b>En attente d’un instructeur</b><?php elseif ($row['kind'] === 'renew'): ?><b><?= $it['state'] === 'expired' ? 'Qualification expirée' : 'À renouveler' ?></b><?php else: ?><b>Conditions remplies</b><?php endif ?>
                <span class="acd-todo__title" translate="no"><?= $h($it['title']) ?></span>
                <small>
                <?php if ($row['kind'] === 'resume' || $row['kind'] === 'exam'): ?><?php if ($it['next'] !== null): ?><span>chapitre</span> <?= (int) $it['next']['chapter'] ?><?php if ($it['next']['chapter_title'] !== ''): ?> · <span translate="no"><?= $h($it['next']['chapter_title']) ?></span><?php endif ?> · <span><?= $h($t($it['next']['stage'])) ?></span><?php else: ?><?= (int) ($it['pct'] ?? 0) ?> %<?php endif ?>
                <?php elseif ($row['kind'] === 'wait'): ?><span>Votre débriefing est envoyé : un instructeur doit valider le chapitre.</span>
                <?php elseif ($row['kind'] === 'renew'): ?><?php if ($oxDate($it['award']['expires_at'] ?? '') !== ''): ?><span>Échéance :</span> <?= $h($oxDate($it['award']['expires_at'])) ?> · <?php endif ?><span>Contactez un instructeur ou remplissez à nouveau les conditions.</span>
                <?php else: ?><span>Elle sera délivrée au prochain passage automatique, ou proposée à un responsable.</span><?php endif ?>
                </small>
            </div>
            <a class="button small<?= $row['kind'] === 'wait' || $row['kind'] === 'eligible' ? ' outline' : '' ?>" href="<?= $h($todoUrl($row)) ?>"><?php if ($row['kind'] === 'resume'): ?><span>Reprendre</span><?php elseif ($row['kind'] === 'exam'): ?><span>Passer l’évaluation</span><?php else: ?><span>Voir</span><?php endif ?></a>
        </li>
    <?php endforeach ?>
    </ul>
    <?php endif ?>
</section>
<?php endif ?>

<section class="acd-section" id="acx-mine" aria-labelledby="acx-mine-title">
    <header class="acd-section__head">
        <h2 id="acx-mine-title" class="acd-h2">Mes brevets et qualifications <b class="acx-count"><?= count($held) ?></b></h2>
        <a class="text-link" href="<?= $h(url('back-office/ma-situation/qualifications')) ?>"><span>Mon dossier de qualifications</span> ↗</a>
    </header>
    <?php if ($held === []): ?>
        <div class="acd-empty acd-empty--row">
            <span class="acd-empty__art acd-empty__art--medal"><?= $oxSymbol('brevet') ?></span>
            <p>Vos brevets et qualifications apparaîtront ici dès qu’ils vous seront délivrés. <a class="text-link" href="<?= $h($oxUrl(['onglet' => ($totals['brevets'] ?? 0) > 0 ? 'brevets' : 'qualifications'])) ?>"><span>Voir ce que vous pouvez obtenir</span></a></p>
        </div>
    <?php else: ?>
    <div class="acd-medals">
    <?php foreach ($held as $it): $aw = is_array($it['award'] ?? null) ? $it['award'] : null; ?>
        <article class="acd-medal is-<?= $h($it['state']) ?>">
            <div class="acd-medal__insignia"><?= $oxBadge($it) ?></div>
            <div class="acd-medal__body">
                <p class="acd-medal__kind"><?= $it['kind'] === 'course' ? '<span>Licence de simulation</span>' : ($it['symbol'] === 'brevet' ? '<span>Brevet</span>' : '<span>Qualification</span>') ?></p>
                <h3><a href="<?= $h($oxFiche($it['id'])) ?>" translate="no"><?= $h($it['title']) ?></a></h3>
                <?php if (($it['award_issuer'] ?? $it['issuer'] ?? '') !== ''): ?><p class="acd-medal__issuer" translate="no"><?= $h($it['award_issuer'] ?? $it['issuer']) ?></p><?php endif ?>
            </div>
            <dl class="acd-medal__meta">
                <?php if ($aw !== null && $oxDate($aw['obtained_at'] ?? '') !== ''): ?><div><dt>Obtenue le</dt><dd><?= $h($oxDate($aw['obtained_at'])) ?></dd></div><?php endif ?>
                <?php if ($it['kind'] === 'qualification'): ?><div><dt>Validité</dt><dd><?= $aw !== null && $oxDate($aw['expires_at'] ?? '') !== '' ? '<span>jusqu’au</span> ' . $h($oxDate($aw['expires_at'])) : '<span>Sans limite</span>' ?></dd></div><?php endif ?>
            </dl>
            <div class="acd-medal__foot">
                <?= $oxStatePill($it) ?>
                <?php $actionsInDetail = true; require __DIR__ . '/offer_actions.php'; $actionsInDetail = false; ?>
            </div>
        </article>
    <?php endforeach ?>
    </div>
    <?php endif ?>
</section>

<?php if ($autoProgress !== []): ?>
<section class="acd-section" aria-labelledby="acx-auto-title">
    <header class="acd-section__head acd-section__head--stack">
        <h2 id="acx-auto-title" class="acd-h2">Ma progression vers…</h2>
        <p>Ces qualifications se débloquent toutes seules quand vous atteignez les seuils (heures de jeu, de vol, missions…).</p>
    </header>
    <div class="acd-auto">
    <?php foreach (array_slice($autoProgress, 0, 6) as $it): ?>
        <article class="acd-auto__item">
            <header class="acd-auto__head">
                <?= $oxBadge($it) ?>
                <a href="<?= $h($oxFiche($it['id'])) ?>" translate="no"><?= $h($it['title']) ?></a>
                <b><?= (int) $it['pct'] ?> %</b>
            </header>
            <div class="acd-seg"><?= $oxBar((int) $it['pct'], 'Progression') ?></div>
            <?php $howFull = true; require __DIR__ . '/offer_how.php'; $howFull = false; ?>
            <?php if (($it['missing'] ?? []) !== []): ?><p class="acx-missing"><?= $oxSymbol('alert') ?><span><span>Il vous manque :</span><?php foreach ($it['missing'] as $missingLine): ?> <span class="acx-missing__item"><?= $h($missingLine) ?></span><?php endforeach ?></span></p><?php endif ?>
        </article>
    <?php endforeach ?>
    </div>
</section>
<?php endif ?>

<section class="acd-section" aria-labelledby="acx-explore-title">
    <h2 id="acx-explore-title" class="acd-h2">Explorer le catalogue</h2>
    <ul class="acd-shortcuts">
    <?php foreach (array_slice(\App\Services\Academy\AcademyOffer::TABS, 1) as $tabKey): if (($totals[$tabKey] ?? 0) === 0 && $tabKey !== 'formations') continue; ?>
        <li><a href="<?= $h($oxUrl(['onglet' => $tabKey])) ?>"><span class="acd-shortcuts__icon"><?= $oxSymbol($shortcutIcons[$tabKey] ?? 'course') ?></span><span class="acd-shortcuts__label"><?= $h($oxTabs[$tabKey]) ?></span><b><?= (int) ($totals[$tabKey] ?? 0) ?></b><small><?= $h($oxTabHelp[$tabKey]) ?></small></a></li>
    <?php endforeach ?>
    </ul>
</section>
<?php require __DIR__ . '/lms_tools.php'; ?>
</div>
