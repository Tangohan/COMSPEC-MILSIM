<?php
declare(strict_types=1);

/** @var bool $screenReady */
/** @var list<array<string, mixed>> $screenRows */
/** @var list<array{key: string, label: string, seconds: int, members: int}> $screenRoleTotals */
/** @var int $screenDays */
/** @var array<int, string> $screenPeriods */

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$rows = is_array($screenRows ?? null) ? $screenRows : [];
$roles = is_array($screenRoleTotals ?? null) ? $screenRoleTotals : [];
$days = (int) ($screenDays ?? 30);
$periods = is_array($screenPeriods ?? null) ? $screenPeriods : [];
$ready = !empty($screenReady);

$dur = static function (int $sec): string {
    if ($sec < 60) {
        return $sec . ' s';
    }
    $m = intdiv($sec, 60);
    if ($m < 60) {
        return $m . ' min';
    }

    return intdiv($m, 60) . ' h ' . str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT);
};
$totalScreen = array_sum(array_column($rows, 'screen'));
$totalRoles = array_sum(array_column($roles, 'seconds'));
$maxRole = $roles !== [] ? max(array_column($roles, 'seconds')) : 1;
?>
<div class="aksq">
    <div class="aksq__frame">
        <header class="aksq__hero">
            <p class="aksq__kicker">ATAK · Temps d’écran et rôles</p>
            <h1 class="aksq__title">Temps d’écran et temps par rôle</h1>
            <p class="aksq__lead">Temps passé téléphone ATAK allumé (en main ou porté en miniature), apps les plus utilisées, et temps de jeu par rôle tenu : poste d’équipage (pilote, copilote, conducteur), sinon rôle d’équipe de feu, sinon spécialité ou slot.</p>
            <div class="aksq__kpis">
                <div class="aksq__kpi"><span>Membres suivis</span><strong><?= count($rows) ?></strong></div>
                <div class="aksq__kpi"><span>Temps d’écran</span><strong><?= $h($dur($totalScreen)) ?></strong></div>
                <div class="aksq__kpi"><span>Temps de jeu par rôle</span><strong><?= $h($dur($totalRoles)) ?></strong></div>
            </div>
            <nav class="aksq__periods" aria-label="Période">
                <?php foreach ($periods as $n => $label): ?>
                    <a href="<?= $h(url('back-office/atak/temps-ecran?jours=' . $n)) ?>" class="aksq__period<?= (int) $n === $days ? ' is-active' : '' ?>"<?= (int) $n === $days ? ' aria-current="page"' : '' ?>><?= $h($label) ?></a>
                <?php endforeach; ?>
                <a href="<?= $h(url('back-office/atak/escouades')) ?>" class="aksq__link">Escouades en jeu</a>
            </nav>
        </header>

        <?php if (!$ready): ?>
            <p class="aksq__notice">La table du temps d’écran n’est pas encore créée : un administrateur plateforme doit lancer <strong>run-migrations.php</strong>.</p>
        <?php elseif ($rows === []): ?>
            <div class="aksq__empty">
                <p class="aksq__empty-title">Rien sur cette période.</p>
                <p>Les téléphones ATAK envoient leur temps toutes les 5 minutes et en fin de mission (COMSPEC Link 2.0.63 ou plus récent, réglage serveur « Temps d’écran et temps par rôle » activé).</p>
            </div>
        <?php else: ?>
            <section class="aksq__session">
                <h2 class="aksq__session-title">Temps par rôle, toute la communauté</h2>
                <div class="aksq__bars">
                    <?php foreach ($roles as $r): ?>
                        <div class="aksq__bar">
                            <span class="aksq__bar-label"><?= $h($r['label']) ?></span>
                            <span class="aksq__bar-track"><span style="width: <?= max(2, (int) round(100 * $r['seconds'] / max(1, $maxRole))) ?>%"></span></span>
                            <span class="aksq__bar-value"><?= $h($dur($r['seconds'])) ?> · <?= (int) $r['members'] ?> membre(s)</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="aksq__session">
                <h2 class="aksq__session-title">Par membre</h2>
                <div class="aksq__table-wrap">
                    <table class="aksq__table">
                        <thead>
                            <tr>
                                <th scope="col">Membre</th>
                                <th scope="col">Écran</th>
                                <th scope="col">En main / porté</th>
                                <th scope="col">Apps les plus utilisées</th>
                                <th scope="col">Temps par rôle</th>
                                <th scope="col">Dernier jour</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $u): ?>
                                <tr>
                                    <th scope="row">
                                        <strong><?= $h($u['callsign'] !== '' ? $u['callsign'] : ($u['display_name'] !== '' ? $u['display_name'] : 'Membre #' . $u['user_id'])) ?></strong>
                                        <?php if ($u['callsign'] !== '' && $u['display_name'] !== ''): ?><span class="aksq__muted"><?= $h($u['display_name']) ?></span><?php endif; ?>
                                    </th>
                                    <td><?= $h($dur((int) $u['screen'])) ?></td>
                                    <td class="aksq__muted"><?= $h($dur((int) $u['hand'])) ?> / <?= $h($dur((int) $u['carry'])) ?></td>
                                    <td>
                                        <?php foreach (array_slice($u['apps'], 0, 4) as $a): ?>
                                            <span class="aksq__chip"><?= $h($a['label']) ?> <em><?= $h($dur($a['seconds'])) ?></em></span>
                                        <?php endforeach; ?>
                                        <?php if ($u['apps'] === []): ?><span class="aksq__muted">—</span><?php endif; ?>
                                    </td>
                                    <td>
                                        <?php foreach (array_slice($u['roles'], 0, 5) as $r): ?>
                                            <span class="aksq__chip aksq__chip--role"><?= $h($r['label']) ?> <em><?= $h($dur($r['seconds'])) ?></em></span>
                                        <?php endforeach; ?>
                                        <?php if ($u['roles'] === []): ?><span class="aksq__muted">—</span><?php endif; ?>
                                    </td>
                                    <td class="aksq__muted"><?= $h($u['last_day'] !== '' ? date('d/m/Y', (int) strtotime($u['last_day'])) : '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    </div>
</div>
