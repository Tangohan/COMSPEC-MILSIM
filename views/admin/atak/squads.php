<?php
declare(strict_types=1);

/** @var bool $squadsReady */
/** @var list<array{mission_key: string, last: string, squads: list<array<string, mixed>>}> $squadSessions */
/** @var int $squadDays */

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$sessions = is_array($squadSessions ?? null) ? $squadSessions : [];
$days = (int) ($squadDays ?? 14);
$ready = !empty($squadsReady);

$ago = static function (string $ts): string {
    try {
        $diff = time() - (new DateTimeImmutable($ts))->getTimestamp();
    } catch (Throwable) {
        return '';
    }
    return match (true) {
        $diff < 90 => 'à l’instant',
        $diff < 3600 => 'il y a ' . (int) round($diff / 60) . ' min',
        $diff < 86400 => 'il y a ' . (int) round($diff / 3600) . ' h',
        default => 'le ' . date('d/m à H:i', time() - $diff),
    };
};
// « Mission@Carte#AAAAMMJJHHMM » → nom lisible.
$missionLabel = static function (string $key): string {
    [$main] = explode('#', $key, 2);
    [$mission, $world] = array_pad(explode('@', $main, 2), 2, '');
    $mission = str_replace('_', ' ', $mission);

    return $world !== '' ? $mission . ' · ' . $world : $mission;
};
$sideLabel = static fn (string $s): string => match (strtoupper($s)) {
    'WEST' => 'BLUFOR', 'EAST' => 'OPFOR', 'GUER' => 'Indépendants', 'CIV' => 'Civils', default => $s,
};
$teamCount = 0;
$squadCount = 0;
foreach ($sessions as $s) {
    $squadCount += count($s['squads']);
    foreach ($s['squads'] as $sq) {
        $teamCount += count(array_filter($sq['teams'] ?? [], static fn (array $t): bool => empty($t['dissolved_at'])));
    }
}
?>
<div class="aksq">
    <div class="aksq__frame">
        <header class="aksq__hero">
            <p class="aksq__kicker">ATAK · Escouades en jeu</p>
            <h1 class="aksq__title">Escouades et équipes de feu</h1>
            <p class="aksq__lead">Ce que les téléphones ATAK envoient pendant la partie : chaque escouade (groupe Arma), ses équipes de feu avec leur couleur, leur icône, leur description, et le rôle de chacun. Mis à jour à chaque changement en jeu.</p>
            <div class="aksq__kpis">
                <div class="aksq__kpi"><span>Sessions</span><strong><?= count($sessions) ?></strong></div>
                <div class="aksq__kpi"><span>Escouades</span><strong><?= $squadCount ?></strong></div>
                <div class="aksq__kpi"><span>Équipes actives</span><strong><?= $teamCount ?></strong></div>
            </div>
            <nav class="aksq__periods" aria-label="Période">
                <?php foreach ([1 => '24 h', 7 => '7 jours', 14 => '14 jours', 30 => '30 jours'] as $n => $label): ?>
                    <a href="<?= $h(url('back-office/atak/escouades?jours=' . $n)) ?>" class="aksq__period<?= $n === $days ? ' is-active' : '' ?>"<?= $n === $days ? ' aria-current="page"' : '' ?>><?= $h($label) ?></a>
                <?php endforeach; ?>
                <a href="<?= $h(url('back-office/atak/fire-teams')) ?>" class="aksq__link">Équipes de feu du site</a>
                <a href="<?= $h(url('back-office/atak/temps-ecran')) ?>" class="aksq__link">Temps d’écran et rôles</a>
            </nav>
        </header>

        <?php if (!$ready): ?>
            <p class="aksq__notice">Les tables des escouades ne sont pas encore créées : un administrateur plateforme doit lancer <strong>run-migrations.php</strong>.</p>
        <?php elseif ($sessions === []): ?>
            <div class="aksq__empty">
                <p class="aksq__empty-title">Aucune escouade reçue sur cette période.</p>
                <p>Les escouades arrivent quand un joueur crée ou rejoint une équipe de feu dans l’app Groupe du téléphone ATAK (COMSPEC Link 2.0.63 ou plus récent).</p>
            </div>
        <?php endif; ?>

        <?php foreach ($sessions as $session): ?>
            <section class="aksq__session">
                <h2 class="aksq__session-title"><?= $h($missionLabel($session['mission_key'])) ?> <small><?= $h($ago($session['last'])) ?></small></h2>
                <div class="aksq__grid">
                    <?php foreach ($session['squads'] as $sq):
                        $teams = $sq['teams'] ?? [];
                        $active = array_values(array_filter($teams, static fn (array $t): bool => empty($t['dissolved_at'])));
                        $gone = count($teams) - count($active);
                        $free = is_array($sq['unassigned'] ?? null) ? $sq['unassigned'] : [];
                    ?>
                        <article class="aksq__squad">
                            <header class="aksq__squad-head">
                                <div>
                                    <h3><?= $h($sq['name']) ?></h3>
                                    <p><?= $h(trim(($sq['squad_type_label'] ?: $sq['squad_type'] ?: 'Escouade') . ' · ' . $sideLabel((string) ($sq['side'] ?? '')), ' ·')) ?></p>
                                </div>
                                <div class="aksq__squad-meta">
                                    <span><?= (int) $sq['member_count'] ?> membre(s)</span>
                                    <?php if (!empty($sq['leader_callsign'])): ?><span>Chef <?= $h($sq['leader_callsign']) ?></span><?php endif; ?>
                                    <?php if (!empty($sq['locked'])): ?><span class="aksq__tag">Fermée</span><?php endif; ?>
                                </div>
                            </header>
                            <?php if ($active === []): ?>
                                <p class="aksq__muted">Pas d’équipe de feu.</p>
                            <?php endif; ?>
                            <?php foreach ($active as $t): $color = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $t['color']) ? (string) $t['color'] : '#64748B'; ?>
                                <div class="aksq__team" style="--team: <?= $h($color) ?>">
                                    <div class="aksq__team-head">
                                        <span class="aksq__swatch" aria-hidden="true"></span>
                                        <strong><?= $h($t['label']) ?></strong>
                                        <?php if (!empty($t['icon'])): ?><span class="aksq__tag"><?= $h(strtolower((string) $t['icon'])) ?></span><?php endif; ?>
                                        <span class="aksq__muted"><?= count($t['members']) ?> membre(s)</span>
                                    </div>
                                    <?php if (!empty($t['notes'])): ?><p class="aksq__desc"><?= $h($t['notes']) ?></p><?php endif; ?>
                                    <?php if ($t['members'] !== []): ?>
                                        <ul class="aksq__members">
                                            <?php foreach ($t['members'] as $m): ?>
                                                <li>
                                                    <span class="aksq__role<?= ($m['role'] ?? '') === 'leader' ? ' is-lead' : '' ?>"><?= $h($m['role_label'] ?: (($m['role'] ?? '') === 'leader' ? 'Chef d’équipe' : 'Membre')) ?></span>
                                                    <span><?= $h($m['callsign'] ?: ($m['display_name'] ?? '')) ?></span>
                                                    <?php if (!empty($m['user_id'])): ?><span class="aksq__muted">fiche liée</span><?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <?php if ($free !== []): ?>
                                <p class="aksq__free"><span>Sans équipe</span> <?= $h(implode(' · ', array_map(static fn (array $m): string => (string) (($m['role_label'] ?? '') !== '' ? $m['role_label'] . ' ' : '') . ($m['callsign'] ?? ''), $free))) ?></p>
                            <?php endif; ?>
                            <footer class="aksq__squad-foot">Reçue <?= $h($ago((string) $sq['synced_at'])) ?><?= $gone > 0 ? ' · ' . $gone . ' équipe(s) dissoute(s)' : '' ?></footer>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
</div>
