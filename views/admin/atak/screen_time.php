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
$totalPlay = array_sum(array_column($rows, 'play'));
$totalScreen = array_sum(array_column($rows, 'screen'));
$totalRoles = array_sum(array_column($roles, 'seconds'));
$maxRole = $roles !== [] ? max(array_column($roles, 'seconds')) : 1;
?>
<div class="aksq">
    <div class="aksq__frame">
        <header class="aksq__hero">
            <p class="aksq__kicker">ATAK · Temps d’écran et rôles</p>
            <h1 class="aksq__title">Temps d’écran et temps par rôle</h1>
            <p class="aksq__lead">Temps passé téléphone ATAK allumé (en main ou porté en miniature), apps les plus utilisées, et temps de jeu par rôle tenu : poste d’équipage (pilote, copilote, conducteur), sinon rôle d’équipe de feu, sinon spécialité ou slot. Le temps de jeu et les rôles sont comptés par le serveur Arma : ils remontent même si le téléphone d’un joueur n’a plus de batterie ni de réseau.</p>
            <div class="aksq__kpis">
                <div class="aksq__kpi"><span>Membres suivis</span><strong><?= count($rows) ?></strong></div>
                <div class="aksq__kpi"><span>Temps de jeu</span><strong><?= $h($dur($totalPlay)) ?></strong></div>
                <div class="aksq__kpi"><span>Temps d’écran</span><strong><?= $h($dur($totalScreen)) ?></strong></div>
                <div class="aksq__kpi"><span>Temps de jeu par rôle</span><strong><?= $h($dur($totalRoles)) ?></strong></div>
            </div>
            <nav class="aksq__periods" aria-label="Période">
                <?php foreach ($periods as $n => $label): ?>
                    <a href="<?= $h(url('back-office/atak/temps-ecran?jours=' . $n)) ?>" class="aksq__period<?= (int) $n === $days ? ' is-active' : '' ?>"<?= (int) $n === $days ? ' aria-current="page"' : '' ?>><?= $h($label) ?></a>
                <?php endforeach; ?>
                <a href="<?= $h(url('back-office/atak/heures' . ($days !== 30 ? '?jours=' . $days : ''))) ?>" class="aksq__link">Heures de jeu (tableur)</a>
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

            <?php
            $roleOptions = [];
            $appOptions = [];
            foreach ($rows as $u) {
                foreach ($u['roles'] as $r) {
                    $roleOptions[(string) $r['label']] = true;
                }
                foreach ($u['apps'] as $a) {
                    $appOptions[(string) $a['label']] = true;
                }
            }
            ksort($roleOptions, SORT_NATURAL | SORT_FLAG_CASE);
            ksort($appOptions, SORT_NATURAL | SORT_FLAG_CASE);
            ?>
            <section class="aksq__session" data-aksq-screen>
                <h2 class="aksq__session-title">Par membre</h2>
                <div class="aksq__filters" role="search">
                    <label class="aksq__filter aksq__filter--grow">
                        <span>Rechercher</span>
                        <input type="search" data-aksq-q placeholder="Indicatif ou nom" autocomplete="off">
                    </label>
                    <label class="aksq__filter">
                        <span>Rôle tenu</span>
                        <select data-aksq-role>
                            <option value="">Tous</option>
                            <?php foreach (array_keys($roleOptions) as $opt): ?>
                                <option value="<?= $h($opt) ?>"><?= $h($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="aksq__filter">
                        <span>App utilisée</span>
                        <select data-aksq-app>
                            <option value="">Toutes</option>
                            <?php foreach (array_keys($appOptions) as $opt): ?>
                                <option value="<?= $h($opt) ?>"><?= $h($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="aksq__switch">
                        <input type="checkbox" data-aksq-durations>
                        <span>Durée des apps</span>
                    </label>
                    <button type="button" class="aksq__link" data-aksq-expand-all>Tout déplier</button>
                    <span class="aksq__muted" data-aksq-count aria-live="polite"><?= count($rows) ?> membre(s)</span>
                </div>
                <div class="aksq__table-wrap">
                    <table class="aksq__table aksq__table--fold">
                        <thead>
                            <tr>
                                <th scope="col">Membre</th>
                                <th scope="col">Temps de jeu</th>
                                <th scope="col">Écran</th>
                                <th scope="col">Apps les plus utilisées</th>
                                <th scope="col">Rôle principal</th>
                                <th scope="col">Dernier jour</th>
                            </tr>
                        </thead>
                        <?php foreach ($rows as $i => $u): ?>
                            <?php
                            $name = $u['callsign'] !== '' ? $u['callsign'] : ($u['display_name'] !== '' ? $u['display_name'] : 'Membre #' . $u['user_id']);
                            $detailId = 'aksq-member-' . (int) $u['user_id'];
                            $roleLabels = array_map(static fn (array $r): string => (string) $r['label'], $u['roles']);
                            $appLabels = array_map(static fn (array $a): string => (string) $a['label'], $u['apps']);
                            ?>
                            <tbody class="aksq__member"
                                data-aksq-name="<?= $h(mb_strtolower($u['callsign'] . ' ' . $u['display_name'])) ?>"
                                data-aksq-roles="<?= $h(json_encode($roleLabels, JSON_UNESCAPED_UNICODE)) ?>"
                                data-aksq-apps="<?= $h(json_encode($appLabels, JSON_UNESCAPED_UNICODE)) ?>">
                                <tr class="aksq__member-row">
                                    <th scope="row">
                                        <button type="button" class="aksq__fold" aria-expanded="false" aria-controls="<?= $h($detailId) ?>">
                                            <span class="aksq__fold-chevron" aria-hidden="true"></span>
                                            <span>
                                                <strong><?= $h($name) ?></strong>
                                                <?php if ($u['callsign'] !== '' && $u['display_name'] !== ''): ?><span class="aksq__muted"><?= $h($u['display_name']) ?></span><?php endif; ?>
                                            </span>
                                        </button>
                                    </th>
                                    <td><?= $h($dur((int) ($u['play'] ?? 0))) ?></td>
                                    <td><?= $h($dur((int) $u['screen'])) ?></td>
                                    <td>
                                        <?php foreach (array_slice($u['apps'], 0, 3) as $a): ?>
                                            <span class="aksq__chip"><?= $h($a['label']) ?> <em class="aksq__app-dur"><?= $h($dur($a['seconds'])) ?></em></span>
                                        <?php endforeach; ?>
                                        <?php if (count($u['apps']) > 3): ?><span class="aksq__muted">+<?= count($u['apps']) - 3 ?></span><?php endif; ?>
                                        <?php if ($u['apps'] === []): ?><span class="aksq__muted">—</span><?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($u['roles'] !== []): ?>
                                            <span class="aksq__chip aksq__chip--role"><?= $h($u['roles'][0]['label']) ?> <em><?= $h($dur($u['roles'][0]['seconds'])) ?></em></span>
                                        <?php else: ?><span class="aksq__muted">—</span><?php endif; ?>
                                    </td>
                                    <td class="aksq__muted"><?= $h($u['last_day'] !== '' ? date('d/m/Y', (int) strtotime($u['last_day'])) : '') ?></td>
                                </tr>
                                <tr class="aksq__member-detail" id="<?= $h($detailId) ?>" hidden>
                                    <td colspan="6" class="aksq__detail-cell">
                                        <div class="aksq__detail">
                                            <div>
                                                <p class="aksq__detail-title">Téléphone</p>
                                                <p>Écran allumé <?= $h($dur((int) $u['screen'])) ?> : en main <?= $h($dur((int) $u['hand'])) ?>, porté en miniature <?= $h($dur((int) $u['carry'])) ?>.</p>
                                            </div>
                                            <div>
                                                <p class="aksq__detail-title">Apps utilisées (<?= count($u['apps']) ?>)</p>
                                                <?php foreach ($u['apps'] as $a): ?>
                                                    <span class="aksq__chip"><?= $h($a['label']) ?> <em class="aksq__app-dur"><?= $h($dur($a['seconds'])) ?></em></span>
                                                <?php endforeach; ?>
                                                <?php if ($u['apps'] === []): ?><span class="aksq__muted">Aucune app relevée.</span><?php endif; ?>
                                            </div>
                                            <div>
                                                <p class="aksq__detail-title">Temps par rôle</p>
                                                <?php foreach ($u['roles'] as $r): ?>
                                                    <span class="aksq__chip aksq__chip--role"><?= $h($r['label']) ?> <em><?= $h($dur($r['seconds'])) ?></em></span>
                                                <?php endforeach; ?>
                                                <?php if ($u['roles'] === []): ?><span class="aksq__muted">Aucun rôle relevé.</span><?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        <?php endforeach; ?>
                    </table>
                </div>
                <p class="aksq__empty-filter aksq__muted" data-aksq-none hidden>Aucun membre ne correspond à ces filtres.</p>
            </section>
        <?php endif; ?>
    </div>
</div>
<script defer src="<?= $h(asset_url('assets/js/back-office-atak-screen-time.js')) ?>"></script>
