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
?>
<div class="acx acx--dashboard">
<header class="acx-heading acx-heading--row">
    <div>
        <p class="eyebrow">ATHENA ACADEMY · <span>Tableau de bord</span></p>
        <h1><?php if ($name !== ''): ?><span>Bonjour</span> <span translate="no"><?= $h($name) ?></span><?php else: ?><span>Votre tableau de bord</span><?php endif ?></h1>
        <p>Vos formations, vos qualifications et ce qu’il vous reste à faire.</p>
    </div>
    <a class="button" href="<?= $h($link('catalogue')) ?>"><span>Explorer le catalogue</span> →</a>
</header>

<?php if ($newcomer): ?>
<section class="acx-panel acx-start" aria-labelledby="acx-start-title">
    <h2 id="acx-start-title">Commencez par…</h2>
    <ol class="acx-start__steps">
        <li><b>Choisir une première formation.</b> <span>Chaque formation se suit à votre rythme, étape par étape.</span>
            <?php if ($available !== []): ?><div class="acx-grid acx-grid--tight"><?php foreach (array_slice($available, 0, 3) as $it) { require __DIR__ . '/offer_card.php'; } ?></div>
            <?php else: ?><p><a class="text-link" href="<?= $h($oxUrl(['onglet' => 'formations'])) ?>"><span>Voir les formations</span> →</a></p><?php endif ?>
        </li>
        <?php if (($totals['qualifications'] ?? 0) > 0): ?><li><b>Découvrir les qualifications de votre communauté.</b> <span>Certaines s’obtiennent automatiquement en jouant, d’autres avec un instructeur.</span> <a class="text-link" href="<?= $h($oxUrl(['onglet' => 'qualifications'])) ?>"><span>Voir les qualifications</span> (<?= (int) $totals['qualifications'] ?>) →</a></li><?php endif ?>
        <li><b>Viser une fonction ou une compétence.</b> <span>Le générateur de parcours propose les étapes dans l’ordre.</span> <a class="text-link" href="<?= $h(url('academy/formations/competences') . '#generateur') ?>"><span>Générer un parcours</span> →</a></li>
    </ol>
</section>
<?php else: ?>
<section class="acx-where" aria-labelledby="acx-where-title">
    <h2 id="acx-where-title" class="acx-h2">Où j’en suis</h2>
    <div class="acx-where__grid">
        <article class="acx-panel">
            <h3><span>Formations en cours</span> <b class="acx-count"><?= count($coursesActive) ?></b></h3>
            <?php if ($coursesActive === []): ?><p class="acx-muted">Aucune formation en cours.</p><a class="text-link" href="<?= $h($oxUrl(['onglet' => 'formations', 'etat' => 'disponible'])) ?>"><span>Choisir une formation</span> →</a>
            <?php else: ?><ul class="acx-mini"><?php foreach (array_slice($coursesActive, 0, 3) as $c): ?>
                <li><a href="<?= $h($todoUrl(['item' => $c])) ?>" translate="no"><?= $h($c['title']) ?></a>
                    <?= $oxBar($c['pct'] ?? 0, 'Progression') ?>
                    <small><b><?= (int) ($c['pct'] ?? 0) ?> %</b><?php if ($c['next'] !== null): ?> · <span>Prochaine étape :</span> <span>chapitre</span> <?= (int) $c['next']['chapter'] ?> · <span><?= $h($t($c['next']['stage'])) ?></span><?php elseif ($c['state'] === 'pending'): ?> · <span>En attente de validation</span><?php endif ?></small></li>
            <?php endforeach ?></ul><?php endif ?>
        </article>
        <article class="acx-panel">
            <h3><span>Acquis</span> <b class="acx-count"><?= count($held) ?></b></h3>
            <?php if ($held === []): ?><p class="acx-muted">Pas encore de qualification ni de brevet.</p>
            <?php else: ?><p class="acx-big"><b><?= count(array_filter($held, static fn (array $i): bool => $i['kind'] === 'qualification')) ?></b> <span>qualifications et brevets</span><?php $lic = count(array_filter($held, static fn (array $i): bool => !empty($i['licence_issued']))); if ($lic > 0): ?> · <b><?= $lic ?></b> <span><?= $lic === 1 ? 'licence' : 'licences' ?></span><?php endif ?></p>
                <?php if ($expiring !== []): ?><p class="acx-missing"><?= $oxSymbol('alert') ?><span><b><?= count($expiring) ?></b> <span>à renouveler bientôt :</span><?php foreach (array_slice($expiring, 0, 3) as $e): ?> <a href="<?= $h($oxFiche($e['id'])) ?>" translate="no"><?= $h($e['title']) ?></a><?php endforeach ?></span></p><?php else: ?><p class="acx-muted">Rien n’expire dans les prochaines semaines.</p><?php endif ?>
            <?php endif ?>
            <a class="text-link" href="#acx-mine"><span>Voir mes brevets et qualifications</span> ↓</a>
        </article>
        <article class="acx-panel acx-panel--goal">
            <h3>Prochain objectif</h3>
            <?php if ($goal === null): ?><p class="acx-muted">Tout est à jour. Parcourez le catalogue pour viser plus haut.</p>
            <?php else: ?>
                <p class="acx-kicker"><span><?= $h($goal['kind'] === 'course' ? 'Formation à commencer' : 'Qualification la plus proche') ?></span></p>
                <p class="acx-goal__title"><a href="<?= $h($oxFiche($goal['id'])) ?>" translate="no"><?= $h($goal['title']) ?></a></p>
                <?php if ($goal['pct'] !== null && $goal['kind'] !== 'course'): ?><?= $oxBar((int) $goal['pct'], 'Progression') ?><small><b><?= (int) $goal['pct'] ?> %</b></small><?php endif ?>
                <?php $it = $goal; require __DIR__ . '/offer_actions.php'; ?>
            <?php endif ?>
        </article>
    </div>
</section>
<?php endif ?>

<?php if (!$newcomer || $todo !== []): ?>
<section class="acx-section" aria-labelledby="acx-todo-title">
    <h2 id="acx-todo-title" class="acx-h2">À faire maintenant</h2>
    <?php if ($todo === []): ?>
        <p class="acx-empty-line">Rien d’urgent. <a class="text-link" href="<?= $h($link('catalogue')) ?>"><span>Choisissez votre prochaine formation dans le catalogue</span> →</a></p>
    <?php else: ?>
    <ul class="acx-todo">
    <?php foreach (array_slice($todo, 0, 8) as $row): $it = $row['item']; ?>
        <li class="acx-todo__row acx-todo__row--<?= $h($row['kind']) ?>">
            <span class="acx-badge acx-badge--sm"><?= $oxSymbol($row['icon']) ?></span>
            <div class="acx-todo__text">
                <?php if ($row['kind'] === 'resume'): ?><b>Reprendre la leçon</b><?php elseif ($row['kind'] === 'exam'): ?><b>Passer l’évaluation</b><?php elseif ($row['kind'] === 'wait'): ?><b>En attente d’un instructeur</b><?php elseif ($row['kind'] === 'renew'): ?><b><?= $it['state'] === 'expired' ? 'Qualification expirée' : 'À renouveler' ?></b><?php else: ?><b>Conditions remplies</b><?php endif ?>
                <span translate="no"><?= $h($it['title']) ?></span>
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

<section class="acx-section" id="acx-mine" aria-labelledby="acx-mine-title">
    <header class="acx-section__head">
        <h2 id="acx-mine-title" class="acx-h2">Mes brevets et qualifications <b class="acx-count"><?= count($held) ?></b></h2>
        <a class="text-link" href="<?= $h(url('back-office/ma-situation/qualifications')) ?>"><span>Mon dossier de qualifications</span> ↗</a>
    </header>
    <?php if ($held === []): ?>
        <p class="acx-empty-line">Vos brevets et qualifications apparaîtront ici dès qu’ils vous seront délivrés. <a class="text-link" href="<?= $h($oxUrl(['onglet' => ($totals['brevets'] ?? 0) > 0 ? 'brevets' : 'qualifications'])) ?>"><span>Voir ce que vous pouvez obtenir</span> →</a></p>
    <?php else: ?>
    <div class="acx-held">
    <?php foreach ($held as $it): $aw = is_array($it['award'] ?? null) ? $it['award'] : null; ?>
        <article class="acx-held__card is-<?= $h($it['state']) ?>">
            <?= $oxBadge($it) ?>
            <div class="acx-held__body">
                <h3><a href="<?= $h($oxFiche($it['id'])) ?>" translate="no"><?= $h($it['title']) ?></a></h3>
                <?php if (($it['award_issuer'] ?? $it['issuer'] ?? '') !== ''): ?><p class="acx-issuer" translate="no"><?= $h($it['award_issuer'] ?? $it['issuer']) ?></p><?php endif ?>
                <dl class="acx-meta">
                    <?php if ($aw !== null && $oxDate($aw['obtained_at'] ?? '') !== ''): ?><div><dt>Obtenue le</dt><dd><?= $h($oxDate($aw['obtained_at'])) ?></dd></div><?php endif ?>
                    <?php if ($it['kind'] === 'qualification'): ?><div><dt>Validité</dt><dd><?= $aw !== null && $oxDate($aw['expires_at'] ?? '') !== '' ? '<span>jusqu’au</span> ' . $h($oxDate($aw['expires_at'])) : '<span>Sans limite</span>' ?></dd></div><?php endif ?>
                    <?php if (!empty($it['licence_issued'])): ?><div><dt>Validité</dt><dd><span>Licence de simulation</span></dd></div><?php endif ?>
                </dl>
                <?= $oxStatePill($it) ?>
            </div>
            <?php $actionsInDetail = true; require __DIR__ . '/offer_actions.php'; $actionsInDetail = false; ?>
        </article>
    <?php endforeach ?>
    </div>
    <?php endif ?>
</section>

<?php if ($autoProgress !== []): ?>
<section class="acx-section" aria-labelledby="acx-auto-title">
    <h2 id="acx-auto-title" class="acx-h2">Ma progression vers…</h2>
    <p class="acx-muted">Ces qualifications se débloquent toutes seules quand vous atteignez les seuils (heures de jeu, de vol, missions…).</p>
    <div class="acx-auto">
    <?php foreach (array_slice($autoProgress, 0, 6) as $it): ?>
        <article class="acx-panel acx-auto__item">
            <header class="acx-auto__head"><a href="<?= $h($oxFiche($it['id'])) ?>" translate="no"><?= $h($it['title']) ?></a><b><?= (int) $it['pct'] ?> %</b></header>
            <?= $oxBar((int) $it['pct'], 'Progression') ?>
            <?php $howFull = true; require __DIR__ . '/offer_how.php'; $howFull = false; ?>
            <?php if (($it['missing'] ?? []) !== []): ?><p class="acx-missing"><?= $oxSymbol('alert') ?><span><span>Il vous manque :</span><?php foreach ($it['missing'] as $missingLine): ?> <span class="acx-missing__item"><?= $h($missingLine) ?></span><?php endforeach ?></span></p><?php endif ?>
        </article>
    <?php endforeach ?>
    </div>
</section>
<?php endif ?>

<section class="acx-section" aria-labelledby="acx-explore-title">
    <h2 id="acx-explore-title" class="acx-h2">Explorer le catalogue</h2>
    <ul class="acx-shortcuts">
    <?php foreach (array_slice(\App\Services\Academy\AcademyOffer::TABS, 1) as $tabKey): if (($totals[$tabKey] ?? 0) === 0 && $tabKey !== 'formations') continue; ?>
        <li><a href="<?= $h($oxUrl(['onglet' => $tabKey])) ?>"><span class="acx-shortcuts__label"><?= $h($oxTabs[$tabKey]) ?></span><b><?= (int) ($totals[$tabKey] ?? 0) ?></b><small><?= $h($oxTabHelp[$tabKey]) ?></small></a></li>
    <?php endforeach ?>
    </ul>
</section>
<?php require __DIR__ . '/lms_tools.php'; ?>
</div>
