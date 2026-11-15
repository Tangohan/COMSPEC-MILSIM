<?php
/**
 * Salon « Vue d'ensemble » : l'ordre du jour (à accuser), le pouls des unités, les actions à traiter et les prochaines
 * échéances. Tout est calculé par JnetCommonSpaceService à partir des échanges, des tâches et des événements réels : un bloc
 * sans donnée dit qu'il est vide. Le détail d'une unité s'ouvre dans le tiroir (jnet_commun.js) ou, sans JavaScript, dans le
 * tableau des unités.
 *
 * @var array<string, mixed> $view
 * @var array<string, mixed> $cm
 * @var bool $canPin
 * @var string $salon
 */

use App\Services\Jnet\JnetAgenda;
use App\Services\Jnet\JnetCommonRules;
use App\Services\Jnet\JnetSpaceService;
use App\Services\Jnet\JnetUnitBoard;
use App\Support\JnetUi;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$order = is_array($view['order'] ?? null) ? $view['order'] : null;
$pulse = is_array($view['pulse'] ?? null) ? $view['pulse'] : [];
$todo = is_array($view['todo'] ?? null) ? $view['todo'] : [];
$events = is_array($view['events'] ?? null) ? $view['events'] : [];
$states = is_array($view['states'] ?? null) ? $view['states'] : JnetUnitBoard::STATES;
$rowsById = is_array($view['rowsById'] ?? null) ? $view['rowsById'] : [];
$unitPins = is_array($view['unitPins'] ?? null) ? $view['unitPins'] : [];
$view['windowDays'] = JnetUnitBoard::ORDER_WINDOW_DAYS;
$stateTone = ['late' => 'bad', 'pending' => 'warn', 'active' => 'ok', 'calm' => 'dim'];
?>
<div class="jc-body jc-ov">
    <div class="jc-ov__col">
        <?php if ($order !== null): ?>
            <?php
            $audience = (int) $order['audience'];
            $read = (int) $order['readCount'];
            $firstLine = trim((string) (preg_split('/\R/u', trim((string) $order['body']))[0] ?? ''));
            ?>
            <article class="jc-odj" aria-labelledby="jc-odj-t">
                <h3 id="jc-odj-t" class="jc-odj__t"><?= $h((string) $order['title']) ?></h3>
                <p class="jc-odj__meta">
                    <span class="jc-pill jc-pill--bad">L’ordre du jour</span>
                    <span translate="no"><?= $h((string) ($order['from']['label'] ?? '')) ?></span><span aria-hidden="true">·</span>
                    <span translate="no"><?= $h((string) $order['author']) ?></span><span aria-hidden="true">·</span>
                    <time><?= $h((string) $order['when']) ?></time>
                </p>
                <?php if ($firstLine !== ''): ?><p class="jc-odj__p"><?= $h(mb_strimwidth($firstLine, 0, 240, '…')) ?></p><?php endif; ?>
                <div class="jc-odj__ft">
                    <?php if (!empty($order['requiresAck']) && $audience > 0): ?>
                        <div class="jc-ackbar"><span class="jc-mono"><?= $read ?> / <?= $audience ?></span><span class="jc-ackbar__tr" aria-hidden="true"><i style="width:<?= (int) round($read / $audience * 100) ?>%"></i></span><span>ont accusé réception</span></div>
                    <?php endif; ?>
                    <div class="jc-odj__acts">
                        <?php if (!empty($order['requiresAck']) && empty($order['readByMe'])): ?>
                            <form method="post" action="<?= $h(url('jnet/echanges/' . (int) $order['id'] . '/lu')) ?>" data-jc-ack>
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="back" value="<?= $h(url(JnetCommonRules::path('apercu'))) ?>">
                                <button type="submit" class="jn-btn jn-btn--small"><?= JnetCommonRules::icon('check') ?><span>Accuser réception</span></button>
                            </form>
                        <?php elseif (!empty($order['requiresAck'])): ?>
                            <span class="jc-pill jc-pill--ok">Accusé de votre part</span>
                        <?php endif; ?>
                        <a class="jn-btn jn-btn--small jn-btn--ghost" href="<?= $h(url(JnetCommonRules::path('annonces')) . '#ex-' . (int) $order['id']) ?>" data-jc-salon="annonces">Lire</a>
                    </div>
                </div>
            </article>
        <?php else: ?>
            <div class="jc-empty">
                <p><strong>Aucun ordre en cours.</strong></p>
                <p>Le dernier ordre diffusé à l’organisation apparaîtra ici, avec l’avancement des accusés de réception.</p>
            </div>
        <?php endif; ?>

        <section class="jc-panel" aria-labelledby="jc-pulse-t">
            <header class="jc-panel__h">
                <h2 id="jc-pulse-t" class="jc-panel__t">Pouls des unités</h2>
                <ul class="jc-legend">
                    <?php foreach ($states as $key => $label): ?>
                        <li class="jc-legend__i jc-tone--<?= $h($stateTone[$key] ?? 'dim') ?>"><i aria-hidden="true"></i><?= $h((string) $label) ?></li>
                    <?php endforeach; ?>
                </ul>
            </header>
            <?php if ($pulse === []): ?>
                <p class="jc-muted jc-panel__empty">L’organigramme n’est pas encore renseigné : chaque unité apparaîtra ici avec son état.</p>
            <?php else: ?>
                <ul class="jc-pulse">
                    <?php foreach ($pulse as $r): ?>
                        <li>
                            <a class="jc-pt jc-tone--<?= $h($stateTone[$r['state']] ?? 'dim') ?>" href="<?= $h(url(JnetCommonRules::path('taches', ['unite' => (int) $r['id']])) . '#jc-unit-card') ?>" data-jc-unit="<?= (int) $r['id'] ?>"<?= (string) $r['accent'] !== '' ? ' style="--u:' . $h((string) $r['accent']) . '"' : '' ?>>
                                <span class="jc-pt__h">
                                    <?= JnetUi::crest(mb_strtoupper(mb_substr((string) $r['label'], 0, 2)), (string) $r['badge'], 'jc-logo') ?>
                                    <span class="jc-pt__s"><?= $h((string) ($states[$r['state']] ?? '')) ?></span>
                                </span>
                                <b class="jc-pt__n" translate="no"><?= $h((string) $r['label']) ?></b>
                                <span class="jc-pt__st">
                                    <span><?= $h(JnetCommonRules::fmt('%1 / %2 disponibles', (int) $r['strength']['available'], (int) $r['strength']['total'])) ?></span>
                                    <?php if ($r['tasksVisible'] && (int) $r['tasks']['late'] > 0): ?>
                                        <span class="jc-bad"><?= $h(JnetCommonRules::fmt('%1 en retard', (int) $r['tasks']['late'])) ?></span>
                                    <?php elseif ((string) $r['lastAt'] !== ''): ?>
                                        <span><?= $h(JnetSpaceService::relativeTime((string) $r['lastAt'])) ?></span>
                                    <?php endif; ?>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ((int) ($view['pulseTotal'] ?? 0) > count($pulse)): ?>
                    <p class="jc-card__more"><a href="<?= $h(url(JnetCommonRules::path('taches'))) ?>" data-jc-salon="taches"><?= $h(JnetCommonRules::fmt('Voir les %1 unités', (int) $view['pulseTotal'])) ?></a></p>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </div>

    <div class="jc-ov__col">
        <section class="jc-panel" aria-labelledby="jc-todo-t">
            <header class="jc-panel__h">
                <h2 id="jc-todo-t" class="jc-panel__t">À traiter</h2>
                <?php if ($todo !== []): ?><span class="jc-mono"><?= $h(JnetCommonRules::fmt(count($todo) > 1 ? '%1 actions' : '%1 action', count($todo))) ?></span><?php endif; ?>
            </header>
            <?php if ($todo === []): ?>
                <div class="jc-empty jc-empty--in"><p><strong>Rien en attente.</strong></p><p>Vous êtes à jour.</p></div>
            <?php else: ?>
                <ul class="jc-todo">
                    <?php foreach ($todo as $t): ?>
                        <li>
                            <a class="jc-td jc-tone--<?= $h((string) $t['tone']) ?>" href="<?= $h((string) $t['href']) ?>"<?= (int) $t['unit'] > 0 ? ' data-jc-unit="' . (int) $t['unit'] . '"' : ' data-jc-salon="' . $h((string) $t['salon']) . '"' ?>>
                                <span class="jc-td__ic"><?= JnetCommonRules::icon((string) $t['icon']) ?></span>
                                <span class="jc-td__t"><b><?= $h((string) $t['title']) ?></b><small><?= $h((string) $t['sub']) ?></small></span>
                                <span class="jc-td__go" aria-hidden="true"><?= JnetCommonRules::icon('caret') ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="jc-panel" aria-labelledby="jc-evmini-t">
            <header class="jc-panel__h">
                <h2 id="jc-evmini-t" class="jc-panel__t">Prochaines échéances</h2>
                <a class="jc-link" href="<?= $h(url(JnetCommonRules::path('agenda'))) ?>" data-jc-salon="agenda">Agenda</a>
            </header>
            <?php if (empty($view['agendaEnabled'])): ?>
                <p class="jc-muted jc-panel__empty">Le module des événements n’est pas disponible pour cette communauté.</p>
            <?php elseif ($events === []): ?>
                <div class="jc-empty jc-empty--in"><p><strong>Rien de prévu.</strong></p><p>Aucun événement à venir.</p></div>
            <?php else: ?>
                <ul class="jc-evl">
                    <?php foreach ($events as $e): ?>
                        <li class="jc-ev">
                            <div class="jc-dt"><span><?= $h(JnetAgenda::WEEKDAYS_SHORT[(int) $e['weekday']]) ?></span><b><?= (int) $e['dayNum'] ?></b></div>
                            <div class="jc-ev__t">
                                <b><?= $h((string) $e['title']) ?></b>
                                <small><?= $h((string) $e['time']) ?> · <span><?= $h((string) $e['type']) ?></span></small>
                            </div>
                            <?php require base_path('views/jnet/commun/_rsvp.php'); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>

    <div hidden data-jc-drawers>
        <?php foreach ($pulse as $r): ?>
            <template data-jc-unit-tpl="<?= (int) $r['id'] ?>">
                <?php $drawerAsCard = false; require base_path('views/jnet/commun/_unit_drawer.php'); ?>
            </template>
        <?php endforeach; ?>
    </div>
</div>
