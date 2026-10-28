<?php
declare(strict_types=1);

/** @var bool $sqReady */
/** @var int $sqNow */
/** @var list<array<string, mixed>> $sqSnapshots */
/** @var array<string, mixed> $sqStatus */
/** @var list<array<string, int>> $sqHistory */
/** @var int|null $sqServer429 */
/** @var array<string, mixed>|null $sqDetail */
/** @var bool $sqDetailMissing */

use App\Repositories\AtakSendQueueRepository;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$ready = !empty($sqReady);
$now = (int) ($sqNow ?? time());
$rows = is_array($sqSnapshots ?? null) ? $sqSnapshots : [];
$status = is_array($sqStatus ?? null) ? $sqStatus : AtakSendQueueRepository::computeStatus([], $now);
$history = is_array($sqHistory ?? null) ? $sqHistory : [];
$server429 = isset($sqServer429) && is_int($sqServer429) ? $sqServer429 : null;
$detail = is_array($sqDetail ?? null) ? $sqDetail : null;
$base = 'back-office/atak/files-envoi';

$ago = static function (int $ts) use ($now): string {
    if ($ts <= 0) {
        return '—';
    }
    $d = max(0, $now - $ts);
    if ($d < 60) {
        return 'il y a ' . $d . ' s';
    }
    if ($d < 3600) {
        return 'il y a ' . intdiv($d, 60) . ' min';
    }
    if ($d < 86400) {
        return 'il y a ' . intdiv($d, 3600) . ' h ' . str_pad((string) intdiv($d % 3600, 60), 2, '0', STR_PAD_LEFT);
    }

    return 'le ' . date('d/m/Y à H:i', $ts);
};
$dur = static function (int $sec): string {
    if ($sec <= 0) {
        return '—';
    }
    if ($sec < 60) {
        return $sec . ' s';
    }
    if ($sec < 3600) {
        return intdiv($sec, 60) . ' min ' . str_pad((string) ($sec % 60), 2, '0', STR_PAD_LEFT);
    }

    return intdiv($sec, 3600) . ' h ' . str_pad((string) intdiv($sec % 3600, 60), 2, '0', STR_PAD_LEFT);
};
$ms = static fn (int $v): string => $v > 0 ? number_format($v, 0, ',', ' ') . ' ms' : '—';
$bytes = static function (int $b): string {
    if ($b <= 0) {
        return '';
    }

    return $b < 1024 ? $b . ' o' : number_format($b / 1024, 1, ',', ' ') . ' Ko';
};
$overloadLabels = [
    'ok' => ['Normal', 'ok'],
    'slow' => ['Lent', 'slow'],
    'rate_limited' => ['Refusé (trop de requêtes)', 'overloaded'],
    'offline' => ['Hors ligne', 'offline'],
];
$stateLabels = [
    'queued' => 'En attente',
    'sending' => 'Envoi en cours',
    'retry' => 'Nouvel essai',
    'failed' => 'Échec',
    'done' => 'Envoyé',
];
$kindLabels = ['client' => 'Joueur', 'server' => 'Serveur'];
$bucketLevel = static function (array $b): string {
    if ((int) $b['clients'] === 0) {
        return 'empty';
    }
    if ((int) $b['offline'] > 0 && (int) $b['offline'] * 2 >= (int) $b['clients']) {
        return 'offline';
    }
    if ((int) $b['overloaded'] > 0 || (int) $b['http_429'] > 0) {
        return 'overloaded';
    }
    if ((int) $b['slow'] > 0 || (int) $b['offline'] > 0 || (int) $b['retry'] > 0 || (int) $b['http_5xx'] + (int) $b['timeouts'] > 0) {
        return 'slow';
    }

    return 'ok';
};
$bucketWord = ['empty' => 'aucun bilan', 'ok' => 'normal', 'slow' => 'ralenti', 'overloaded' => 'surchargé', 'offline' => 'hors ligne'];
$level = (string) ($status['level'] ?? 'none');
$levelClass = ['normal' => 'ok', 'slow' => 'slow', 'overloaded' => 'overloaded', 'offline' => 'offline'][$level] ?? 'none';
?>
<div class="aksq sqf">
    <div class="aksq__frame">
        <header class="aksq__hero">
            <p class="aksq__kicker">ATAK · Files d’envoi</p>
            <h1 class="aksq__title">Files d’envoi Athena</h1>
            <p class="aksq__lead">Chaque joueur et chaque serveur Arma garde ses envois vers Athena (rapports, positions, messages…) dans une file : ils partent dans l’ordre, sont réessayés en cas d’erreur et attendent quand Athena est surchargée. COMSPEC Link envoie un bilan de sa file toutes les 30 secondes environ, quand quelque chose a changé.</p>

            <div class="sqf__status sqf__status--<?= $h($levelClass) ?>" role="status">
                <span class="sqf__badge sqf__badge--<?= $h($levelClass) ?>"><?= $h($status['label'] ?? 'Aucune donnée') ?></span>
                <p class="sqf__status-text"><?= $h($status['explanation'] ?? '') ?></p>
            </div>

            <div class="aksq__kpis">
                <div class="aksq__kpi"><span>Clients actifs (5 min)</span><strong><?= (int) ($status['clients'] ?? 0) ?></strong></div>
                <div class="aksq__kpi"><span>Refusés / lents / hors ligne</span><strong><?= (int) ($status['rate_limited'] ?? 0) ?> / <?= (int) ($status['slow'] ?? 0) ?> / <?= (int) ($status['offline'] ?? 0) ?></strong></div>
                <div class="aksq__kpi"><span>En attente · à réessayer · en échec</span><strong><?= (int) ($status['queued'] ?? 0) ?> · <?= (int) ($status['retry'] ?? 0) ?> · <?= (int) ($status['failed'] ?? 0) ?></strong></div>
                <div class="aksq__kpi"><span>Temps de réponse moyen / p95</span><strong><?= $h($ms((int) ($status['avg_latency_ms'] ?? 0))) ?> / <?= $h($ms((int) ($status['p95_latency_ms'] ?? 0))) ?></strong></div>
                <div class="aksq__kpi"><span>Refus 429 · erreurs 5xx · délais dépassés</span><strong><?= (int) ($status['http_429'] ?? 0) ?> · <?= (int) ($status['http_5xx'] ?? 0) ?> · <?= (int) ($status['timeouts'] ?? 0) ?></strong></div>
                <?php if ($server429 !== null): ?>
                    <div class="aksq__kpi" title="D’après le journal Liaison : envois du jeu refusés par Athena (trop de requêtes).">
                        <span>Refus donnés par Athena (15 min)</span><strong><?= $server429 ?></strong>
                    </div>
                <?php endif; ?>
            </div>
            <nav class="aksq__periods" aria-label="Liens">
                <a href="<?= $h(url($base)) ?>" class="aksq__period">Actualiser</a>
                <a href="<?= $h(url('back-office/atak/operateurs')) ?>" class="aksq__link">Sessions &amp; connexions</a>
            </nav>
        </header>

        <?php if (!$ready): ?>
            <p class="aksq__notice">Les tables des files d’envoi ne sont pas encore créées : un administrateur plateforme doit lancer <strong>run-migrations.php</strong>.</p>
        <?php else: ?>

            <?php if ($detail !== null): ?>
                <?php
                $dLabel = $overloadLabels[$detail['overload']] ?? $overloadLabels['ok'];
                $dName = $detail['call_sign'] !== '' ? $detail['call_sign'] : ($detail['client_kind'] === 'server' ? 'Serveur' : $detail['steam_uid']);
                ?>
                <section class="aksq__session sqf__detail" id="detail">
                    <h2 class="aksq__session-title">
                        <?= $h($dName) ?>
                        <small><?= $h($kindLabels[$detail['client_kind']] ?? $detail['client_kind']) ?><?= $detail['steam_uid'] !== AtakSendQueueRepository::SERVER_KEY ? ' · ' . $h($detail['steam_uid']) : '' ?> · bilan <?= $h($ago((int) $detail['received_ts'])) ?></small>
                    </h2>
                    <p class="sqf__detail-meta">
                        <span class="sqf__badge sqf__badge--<?= $h($dLabel[1]) ?>"><?= $h($dLabel[0]) ?></span>
                        <?php if ((int) $detail['rate_limited_for_s'] > 0): ?><span>Attend encore <?= $h($dur((int) $detail['rate_limited_for_s'])) ?> avant de renvoyer (Athena a demandé de patienter).</span><?php endif; ?>
                        <?php if ((int) $detail['backoff_for_s'] > 0): ?><span>Prochain essai dans <?= $h($dur((int) $detail['backoff_for_s'])) ?>.</span><?php endif; ?>
                        <span>COMSPEC Link <?= $h($detail['extension_version'] !== '' ? $detail['extension_version'] : '?') ?><?= $detail['mod_version'] !== '' ? ' · mod ' . $h($detail['mod_version']) : '' ?></span>
                        <a href="<?= $h(url($base)) ?>" class="aksq__link">Fermer</a>
                    </p>
                    <?php if ($detail['items'] === []): ?>
                        <div class="aksq__empty"><p>Aucun envoi suivi dans le dernier bilan.</p></div>
                    <?php else: ?>
                        <ol class="sqf__items">
                            <?php foreach ($detail['items'] as $it): ?>
                                <li class="sqf__item sqf__item--<?= $h($it['state']) ?>">
                                    <div class="sqf__item-head">
                                        <span class="sqf__state sqf__state--<?= $h($it['state']) ?>"><?= $h($stateLabels[$it['state']] ?? $it['state']) ?></span>
                                        <strong><?= $h($it['label'] !== '' ? $it['label'] : $it['kind']) ?></strong>
                                        <?php if ($it['label'] !== '' && $it['kind'] !== ''): ?><code class="aksq__muted"><?= $h($it['kind']) ?></code><?php endif; ?>
                                        <span class="aksq__muted">
                                            <?= (int) $it['attempts'] ?> essai(s)<?= $bytes((int) $it['bytes']) !== '' ? ' · ' . $h($bytes((int) $it['bytes'])) : '' ?>
                                            <?php if ((int) $it['created_at'] > 0): ?> · créé à <?= $h(date('H:i:s', (int) $it['created_at'])) ?><?php endif; ?>
                                            <?php if ((int) $it['last_http'] > 0): ?> · dernière réponse HTTP <?= (int) $it['last_http'] ?><?php endif; ?>
                                        </span>
                                    </div>
                                    <?php if ($it['last_error'] !== '' && in_array($it['state'], ['retry', 'failed'], true)): ?>
                                        <p class="sqf__error">Dernière erreur : <?= $h($it['last_error']) ?></p>
                                    <?php endif; ?>
                                    <?php if ($it['steps'] !== []): ?>
                                        <ol class="sqf__steps" aria-label="Étapes">
                                            <?php $t0 = (int) $it['steps'][0][1]; ?>
                                            <?php foreach ($it['steps'] as [$sState, $sAt, $sNote]): ?>
                                                <li class="sqf__step sqf__step--<?= $h($sState) ?>">
                                                    <span class="sqf__step-name"><?= $h($stateLabels[$sState] ?? $sState) ?></span>
                                                    <?php if ($sNote !== ''): ?><span class="sqf__step-note">(<?= $h($sNote) ?>)</span><?php endif; ?>
                                                    <?php if ((int) $sAt > 0): ?>
                                                        <time datetime="<?= $h(date('c', (int) $sAt)) ?>"><?= $h(date('H:i:s', (int) $sAt)) ?><?= $t0 > 0 && (int) $sAt > $t0 ? ' · +' . $h($dur((int) $sAt - $t0)) : '' ?></time>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ol>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </section>
            <?php elseif (!empty($sqDetailMissing)): ?>
                <p class="aksq__notice">Aucun bilan pour ce client. <a href="<?= $h(url($base)) ?>">Retour à la liste</a></p>
            <?php endif; ?>

            <section class="aksq__session">
                <h2 class="aksq__session-title">Les 6 dernières heures <small>par quart d’heure, pire état relevé</small></h2>
                <div class="sqf__history">
                    <div class="sqf__strip" role="list">
                        <?php foreach ($history as $b): ?>
                            <?php
                            $lv = $bucketLevel($b);
                            $tip = date('H:i', (int) $b['start']) . ' — ' . $bucketWord[$lv];
                            if ((int) $b['clients'] > 0) {
                                $tip .= sprintf(
                                    ' · %d client(s) · en attente %d · à réessayer %d · en échec %d · %s · 429 : %d · 5xx : %d · délais : %d',
                                    (int) $b['clients'], (int) $b['queued'], (int) $b['retry'], (int) $b['failed'],
                                    $ms((int) $b['avg_latency_ms']), (int) $b['http_429'], (int) $b['http_5xx'], (int) $b['timeouts']
                                );
                            }
                            ?>
                            <span class="sqf__cell sqf__cell--<?= $h($lv) ?>" role="listitem" title="<?= $h($tip) ?>"><span class="sqf__sr"><?= $h($tip) ?></span></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="sqf__axis" aria-hidden="true">
                        <?php foreach ($history as $i => $b): ?>
                            <span><?= $i % 4 === 0 ? $h(date('H:i', (int) $b['start'])) : '' ?></span>
                        <?php endforeach; ?>
                    </div>
                    <p class="sqf__legend">
                        <span><i class="sqf__cell sqf__cell--ok"></i> Normal</span>
                        <span><i class="sqf__cell sqf__cell--slow"></i> Ralenti</span>
                        <span><i class="sqf__cell sqf__cell--overloaded"></i> Surchargé</span>
                        <span><i class="sqf__cell sqf__cell--offline"></i> Hors ligne</span>
                        <span><i class="sqf__cell sqf__cell--empty"></i> Aucun bilan</span>
                    </p>
                </div>
            </section>

            <section class="aksq__session">
                <h2 class="aksq__session-title">Joueurs et serveurs <small><?= count($rows) ?> client(s) · grisé : pas de bilan depuis plus de 2 min</small></h2>
                <?php if ($rows === []): ?>
                    <div class="aksq__empty">
                        <p class="aksq__empty-title">Aucun bilan reçu pour le moment.</p>
                        <p>Les bilans arrivent dès qu’un joueur ou un serveur utilise une version de COMSPEC Link qui suit sa file d’envoi.</p>
                    </div>
                <?php else: ?>
                    <div class="aksq__table-wrap">
                        <table class="aksq__table sqf__table">
                            <thead>
                                <tr>
                                    <th scope="col">Indicatif</th>
                                    <th scope="col">Type</th>
                                    <th scope="col">État</th>
                                    <th scope="col">En attente · essai · échec</th>
                                    <th scope="col">Plus ancien en attente</th>
                                    <th scope="col">Temps de réponse</th>
                                    <th scope="col">Dernier bilan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $r): ?>
                                    <?php
                                    $stale = $now - (int) $r['received_ts'] > AtakSendQueueRepository::STALE_SECONDS;
                                    $lab = $overloadLabels[$r['overload']] ?? $overloadLabels['ok'];
                                    $href = url($base . '?' . http_build_query(['uid' => $r['steam_uid'], 'type' => $r['client_kind']])) . '#detail';
                                    $isCurrent = $detail !== null && $detail['steam_uid'] === $r['steam_uid'] && $detail['client_kind'] === $r['client_kind'];
                                    ?>
                                    <tr class="<?= $stale ? 'is-stale' : '' ?><?= $isCurrent ? ' is-current' : '' ?>">
                                        <th scope="row">
                                            <a href="<?= $h($href) ?>" class="sqf__row-link"><strong><?= $h($r['call_sign'] !== '' ? $r['call_sign'] : ($r['client_kind'] === 'server' ? 'Serveur' : 'Sans indicatif')) ?></strong></a>
                                            <?php if ($r['steam_uid'] !== AtakSendQueueRepository::SERVER_KEY): ?><span class="aksq__muted"><?= $h($r['steam_uid']) ?></span><?php endif; ?>
                                        </th>
                                        <td><?= $h($kindLabels[$r['client_kind']] ?? $r['client_kind']) ?></td>
                                        <td><span class="sqf__badge sqf__badge--<?= $h($stale ? 'none' : $lab[1]) ?>"><?= $h($lab[0]) ?></span></td>
                                        <td class="sqf__num"><?= (int) $r['queued'] ?> · <?= (int) $r['retry'] ?> · <span class="<?= (int) $r['failed'] > 0 ? 'sqf__bad' : '' ?>"><?= (int) $r['failed'] ?></span></td>
                                        <td class="sqf__num"><?= $h($dur((int) $r['oldest_queued_s'])) ?></td>
                                        <td class="sqf__num"><?= $h($ms((int) $r['avg_latency_ms'])) ?><?php if ((int) $r['p95_latency_ms'] > 0): ?> <span class="aksq__muted">p95 <?= $h($ms((int) $r['p95_latency_ms'])) ?></span><?php endif; ?></td>
                                        <td class="aksq__muted"><?= $h($ago((int) $r['received_ts'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</div>
