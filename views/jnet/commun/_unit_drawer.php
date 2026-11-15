<?php
/**
 * Détail d'une unité (tiroir à droite, ou fiche en page sans JavaScript) : état, chiffres clés, commandement, sous-unités,
 * ordres et tâches les plus urgents, actions. Mêmes données que la ligne du tableau des unités : aucune lecture de plus.
 *
 * @var array<string, mixed> $r ligne du tableau des unités (JnetUnitBoard::build)
 * @var array<int, array<string, mixed>> $rowsById toutes les lignes, par identifiant (sous-unités)
 * @var array<int, int> $unitPins identifiant de l'épingle de chaque unité (0 = non épinglée)
 * @var array<string, mixed> $states libellés des états
 * @var array<string, mixed> $view
 * @var bool $canPin
 * @var string $salon
 * @var bool $drawerAsCard vrai pour la fiche en page (sans bouton de fermeture du tiroir)
 */

use App\Services\Jnet\JnetCommonRules;
use App\Services\Jnet\JnetSpaceService;
use App\Support\JnetUi;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$id = (int) $r['id'];
$o = $r['orders'];
$t = $r['tasks'];
$states = is_array($states ?? null) ? $states : [];
$asCard = !empty($drawerAsCard);
$unitStyle = (string) $r['accent'] !== '' ? ' style="--u:' . $h((string) $r['accent']) . '"' : '';
$path = array_values(array_filter((array) $r['chain'], static fn (string $l): bool => $l !== ''));
$subs = [];
foreach ((array) $r['childIds'] as $cid) {
    if (isset($rowsById[(int) $cid])) {
        $subs[] = $rowsById[(int) $cid];
    }
}
$none = '<span class="jc-nil">—</span>';
$pinId = (int) (($unitPins ?? [])[$id] ?? 0);
?>
<div class="jc-dw__h"<?= $unitStyle ?>>
    <?= JnetUi::crest(mb_strtoupper(mb_substr((string) $r['label'], 0, 2)), (string) $r['badge'], 'jc-logo jc-logo--lg') ?>
    <div class="jc-dw__id">
        <h3 class="jc-dw__t" translate="no"><?= $h((string) $r['label']) ?></h3>
        <p class="jc-dw__path">
            <?php foreach ($path as $i => $label): ?><?= $i > 0 ? '<span aria-hidden="true"> › </span>' : '' ?><span translate="no"><?= $h($label) ?></span><?php endforeach; ?>
            <?php if ((string) $r['echelonLabel'] !== ''): ?><?= $path !== [] ? '<span aria-hidden="true"> · </span>' : '' ?><span><?= $h((string) $r['echelonLabel']) ?></span><?php endif; ?>
        </p>
        <span class="jc-state jc-state--<?= $h((string) $r['state']) ?>"><?= $h((string) ($states[$r['state']] ?? '')) ?></span>
    </div>
    <?php if (!$asCard): ?>
        <button type="button" class="jc-dw__x" data-jc-close aria-label="Fermer"><?= JnetCommonRules::icon('close') ?></button>
    <?php else: ?>
        <a class="jc-dw__x" href="<?= $h(url(JnetCommonRules::path('taches'))) ?>" data-jc-salon="taches" aria-label="Fermer"><?= JnetCommonRules::icon('close') ?></a>
    <?php endif; ?>
</div>
<div class="jc-dw__b"<?= $unitStyle ?>>
    <dl class="jc-dg">
        <div><dt>À accuser</dt><dd class="<?= (int) $o['pending'] > 0 ? 'is-bad' : '' ?>"><?= (int) $o['pending'] ?></dd></div>
        <div><dt>Tâches ouvertes</dt><dd><?= $r['tasksVisible'] ? (int) $t['todo'] + (int) $t['doing'] : '—' ?></dd></div>
        <div><dt>Effectif disponible</dt><dd><?= (int) $r['strength']['available'] ?><small> / <?= (int) $r['strength']['total'] ?></small></dd></div>
    </dl>

    <section class="jc-dl" aria-label="Commandement">
        <h4 class="jc-dl__t">Commandement</h4>
        <dl>
            <div><dt>Chef</dt><dd><?= (string) $r['leader'] !== '' ? '<span translate="no">' . $h((string) $r['leader']) . '</span>' : '<span class="jc-nil">Non désigné</span>' ?></dd></div>
            <div><dt>Rattachement</dt><dd><?= (string) $r['parentLabel'] !== '' ? '<span translate="no">' . $h((string) $r['parentLabel']) . '</span>' : $none ?></dd></div>
            <div><dt>Dernier échange</dt><dd><?= (string) $r['lastAt'] !== '' ? '<time title="' . $h((string) $r['lastAt']) . '">' . $h(JnetSpaceService::relativeTime((string) $r['lastAt'])) . '</time>' : $none ?></dd></div>
            <div><dt>Ordres en cours</dt><dd><?= (int) $o['recent'] ?></dd></div>
            <div><dt>Tâches en retard</dt><dd class="<?= $r['tasksVisible'] && (int) $t['late'] > 0 ? 'is-bad' : '' ?>"><?= $r['tasksVisible'] ? (int) $t['late'] : '—' ?></dd></div>
        </dl>
    </section>

    <?php if ($subs !== []): ?>
        <section class="jc-dl" aria-label="Sous-unités">
            <h4 class="jc-dl__t"><?= $h(JnetCommonRules::fmt(count($subs) > 1 ? 'Sous-unités · %1' : 'Sous-unité · %1', count($subs))) ?></h4>
            <ul class="jc-subs">
                <?php foreach ($subs as $s): ?>
                    <li>
                        <a class="jc-subs__i" href="<?= $h(url(JnetCommonRules::path('taches', ['unite' => (int) $s['id']])) . '#jc-unit-card') ?>" data-jc-unit="<?= (int) $s['id'] ?>"<?= (string) $s['accent'] !== '' ? ' style="--u:' . $h((string) $s['accent']) . '"' : '' ?>>
                            <?= JnetUi::crest(mb_strtoupper(mb_substr((string) $s['label'], 0, 2)), (string) $s['badge'], 'jc-logo') ?>
                            <span translate="no"><?= $h((string) $s['label']) ?></span>
                            <span class="jc-state jc-state--<?= $h((string) $s['state']) ?>"><?= $h((string) ($states[$s['state']] ?? '')) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <section class="jc-dl" aria-label="Ordres les plus urgents">
        <h4 class="jc-dl__t">Ordres les plus urgents</h4>
        <?php if ($o['top'] === []): ?>
            <p class="jc-muted"><?= $h(JnetCommonRules::fmt('Aucun ordre reçu ces %1 derniers jours.', (int) ($view['windowDays'] ?? 30))) ?></p>
        <?php else: ?>
            <ul class="jc-mini">
                <?php foreach ($o['top'] as $ord): ?>
                    <li>
                        <?php $orderHref = !empty($r['openable']) ? url('jnet/u/' . $id . '?vue=echanges&direct=1') . '#ex-' . (int) $ord['id'] : ''; ?>
                        <?php if ($orderHref !== ''): ?><a href="<?= $h($orderHref) ?>"><?= $h((string) $ord['title']) ?></a><?php else: ?><span><?= $h((string) $ord['title']) ?></span><?php endif; ?>
                        <em>
                            <?php if ($ord['ack'] && (int) $ord['missing'] > 0): ?><span class="jc-warn"><?= (int) $ord['missing'] ?> accusé(s) manquant(s)</span>
                            <?php elseif ($ord['ack']): ?><span>Accusé complet</span>
                            <?php else: ?><span>Sans accusé demandé</span><?php endif; ?>
                            <span class="jc-sep" aria-hidden="true">·</span> <time><?= $h(JnetSpaceService::relativeTime((string) $ord['at'])) ?></time>
                        </em>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="jc-dl" aria-label="Tâches les plus urgentes">
        <h4 class="jc-dl__t">Tâches les plus urgentes</h4>
        <?php if (!$r['tasksVisible']): ?>
            <p class="jc-muted">Les tâches de cette unité ne sont pas accessibles depuis votre accès.</p>
        <?php elseif ($t['top'] === []): ?>
            <p class="jc-muted">Aucune tâche ouverte.</p>
        <?php else: ?>
            <ul class="jc-mini">
                <?php foreach ($t['top'] as $task): ?>
                    <li>
                        <span><?= $h((string) $task['title']) ?></span>
                        <em>
                            <?php if ($task['late']): ?><span class="jc-bad">En retard</span><?php elseif ($task['status'] === 'doing'): ?><span>En cours</span><?php else: ?><span>À faire</span><?php endif; ?>
                            <?php if (trim((string) $task['who']) !== ''): ?><span class="jc-sep" aria-hidden="true">·</span> <span translate="no"><?= $h((string) $task['who']) ?></span><?php endif; ?>
                            <span class="jc-sep" aria-hidden="true">·</span> <span>Dernier changement</span> <time><?= $h(JnetSpaceService::relativeTime((string) $task['at'])) ?></time>
                        </em>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
<div class="jc-dw__f">
    <?php if ((int) $r['leaderId'] > 0): ?>
        <a class="jn-btn jn-btn--small" href="<?= $h(url('jnet/courrier/nouveau?membre=' . (int) $r['leaderId'])) ?>"><?= JnetCommonRules::icon('mail') ?><span>Écrire au chef</span></a>
    <?php endif; ?>
    <?php if (!empty($r['openable'])): ?>
        <a class="jn-btn jn-btn--small" href="<?= $h(url('jnet/u/' . $id)) ?>"><span>Ouvrir l’espace de l’unité</span><?= JnetCommonRules::icon('arrow') ?></a>
        <?php if ($r['tasksVisible']): ?><a class="jc-link" href="<?= $h(url('jnet/u/' . $id . '?vue=taches&direct=1')) ?>">Toutes ses tâches</a><?php endif; ?>
    <?php endif; ?>
    <?php $pinType = 'unite'; $pinTarget = (string) $id; require base_path('views/jnet/commun/_pin.php'); ?>
</div>
