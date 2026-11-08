<?php
declare(strict_types=1);

use App\Services\Watch\LoadLevel;
use App\Services\Watch\WatchRules;
use App\Services\Watch\WatchSettings;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$state = is_array($loadState ?? null) ? $loadState : null;
$cfg = is_array($loadSettings ?? null) ? $loadSettings : WatchSettings::defaults();
$sensor = is_array($state['sensor'] ?? null) ? $state['sensor'] : [];
$measured = (int) ($loadMeasuredLevel ?? 0);
$effective = (int) ($loadEffectiveLevel ?? 0);
$age = $loadStateAge ?? null;
$stale = $state === null || ($age !== null && $age > LoadLevel::MAX_AGE_SECONDS);
$reliable = !$stale && !empty($sensor['reliable']);
$shedOn = (int) $cfg[WatchSettings::SHED_ENABLED] === 1;
$killed = !empty($state['kill_switch']);
$minutes = is_array($loadMinutes ?? null) ? $loadMinutes : [];
$hours = is_array($loadHours ?? null) ? $loadHours : [];
$routes = is_array($loadRoutes ?? null) ? $loadRoutes : [];
$tenants = is_array($loadTenants ?? null) ? $loadTenants : [];
$alerts = is_array($loadAlerts ?? null) ? $loadAlerts : [];
$never = is_array($loadNever ?? null) ? $loadNever : [];
$levelLabels = [0 => 'Normal', 1 => 'Charge élevée · palier 1', 2 => 'Charge forte · palier 2', 3 => 'Saturation · palier 3'];
$tierText = [0 => 'Personne n’est ralenti.', 1 => 'Communautés Gratuit.', 2 => 'Communautés Gratuit et Standard.', 3 => 'Communautés Gratuit, Standard et Pro (Pro+ jamais).'];
$fmtTime = static function (int $ts): string {
    return $ts > 0 ? date('d/m H:i', $ts) : '—';
};
$fmtMs = static fn (?int $ms): string => $ms === null ? '—' : ($ms > 3000 ? 'plus de 3 s' : ($ms >= 1000 ? number_format($ms / 1000, 1, ',', '') . ' s' : $ms . ' ms'));
$maxMinute = 1;
foreach ($minutes as $m) {
    $maxMinute = max($maxMinute, (int) $m['requests']);
}
$maxHour = 1;
foreach ($hours as $x) {
    $maxHour = max($maxHour, (int) $x['requests']);
}
$ok = \App\Core\Session::getFlash('success');
$err = \App\Core\Session::getFlash('error');
$lastMinute = $minutes !== [] ? $minutes[count($minutes) - 1] : null;
$kpiTone = static fn (bool $warn): string => $warn ? ' pa-kpi--warn' : '';
?>
<div class="pa">
    <div class="pa__frame">
        <header class="pa-hero">
            <p class="pa-crumb">
                <a href="<?= $h(url('admin')) ?>">Administration du site</a>
                <span aria-hidden="true"> / </span>
                Exploitation
                <span aria-hidden="true"> / </span>
                Charge et surcharge
            </p>
            <h1 class="pa-hero__title">Charge et surcharge</h1>
            <p class="pa-hero__lead">
                Ce que l’application mesure d’elle-même : requêtes par route et par communauté, erreurs, durée, tendance. Sous saturation
                avérée, et seulement si vous l’activez, les communautés Gratuit puis Standard sont ralenties avant les formules payantes,
                sur les seules routes non critiques.
            </p>
            <div class="pa-hero__actions">
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/system/alertes-mod')) ?>">Activités suspectes du mod</a>
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/system/cron')) ?>">Tâches automatiques</a>
            </div>
        </header>

        <div class="pa-panel">
            <?php if ($ok): ?><p class="pa-flash pa-flash--ok"><?= $h($ok) ?></p><?php endif; ?>
            <?php if ($err): ?><p class="pa-flash pa-flash--err"><?= $h($err) ?></p><?php endif; ?>

            <?php if ($stale): ?>
                <p class="pa-flash pa-flash--warn">
                    Capteur de charge indisponible : aucune mesure récente<?= $age !== null ? ' (dernière il y a ' . (int) $age . ' s)' : '' ?>.
                    Dans ce cas, personne n’est ralenti. La mesure reprend avec le prochain passage de la tâche « Surveillance » ou la prochaine visite.
                </p>
            <?php elseif (!$reliable): ?>
                <p class="pa-flash pa-flash--warn">
                    Mesure non fiable<?= ($sensor['reason'] ?? '') === 'too_few_requests' ? ' : trop peu de requêtes dans la dernière minute pour juger la durée et les erreurs' : '' ?>.
                    Tant qu’aucun signal fiable n’existe, le palier reste à zéro et personne n’est ralenti.
                </p>
            <?php endif; ?>
            <?php if ($killed): ?>
                <p class="pa-flash pa-flash--warn">Le ralentissement est coupé par la variable d’environnement LOAD_SHED=off du serveur.</p>
            <?php endif; ?>

            <ul class="pa-kpi-strip" style="margin-bottom:1.25rem;">
                <li class="pa-kpi<?= $kpiTone($measured > 0) ?>">
                    <span class="pa-kpi__label">État mesuré</span>
                    <span class="pa-kpi__value"><?= $h($levelLabels[$measured] ?? 'Normal') ?></span>
                    <span class="pa-kpi__hint"><?= $reliable ? 'Fenêtre de ' . (int) ($sensor['window_s'] ?? 60) . ' s' : 'Mesure indisponible' ?></span>
                </li>
                <li class="pa-kpi<?= $kpiTone($effective > 0) ?>">
                    <span class="pa-kpi__label">Ralentissement</span>
                    <span class="pa-kpi__value"><?= $shedOn ? ($effective > 0 ? 'En cours' : 'Prêt') : 'Désactivé' ?></span>
                    <span class="pa-kpi__hint"><?= $h($shedOn && $effective > 0 ? $tierText[$effective] : ($shedOn ? 'Rien à ralentir pour l’instant' : 'Réglage ci-dessous')) ?></span>
                </li>
                <li class="pa-kpi">
                    <span class="pa-kpi__label">Requêtes (60 s)</span>
                    <span class="pa-kpi__value"><?= (int) ($sensor['requests'] ?? 0) ?></span>
                    <span class="pa-kpi__hint">Dynamiques, hors administration</span>
                </li>
                <li class="pa-kpi<?= $kpiTone(isset($sensor['error_pct']) && (float) $sensor['error_pct'] >= (float) $cfg[WatchSettings::ALERT_ERROR_PCT]) ?>">
                    <span class="pa-kpi__label">Erreurs 5xx</span>
                    <span class="pa-kpi__value"><?= isset($sensor['error_pct']) ? $h(number_format((float) $sensor['error_pct'], 1, ',', '')) . ' %' : '—' ?></span>
                    <span class="pa-kpi__hint">Seuil d’alerte <?= $h((string) $cfg[WatchSettings::ALERT_ERROR_PCT]) ?> %</span>
                </li>
                <li class="pa-kpi<?= $kpiTone(isset($sensor['p95_ms']) && (int) $sensor['p95_ms'] >= (int) $cfg[WatchSettings::ALERT_P95_MS]) ?>">
                    <span class="pa-kpi__label">Durée p95</span>
                    <span class="pa-kpi__value"><?= isset($sensor['p95_ms']) ? $h((string) (int) $sensor['p95_ms']) . ' ms' : '—' ?></span>
                    <span class="pa-kpi__hint">Seuil d’alerte <?= $h((string) $cfg[WatchSettings::ALERT_P95_MS]) ?> ms</span>
                </li>
                <li class="pa-kpi<?= $kpiTone(isset($sensor['load_per_core']) && (float) $sensor['load_per_core'] >= (float) $cfg[WatchSettings::ALERT_LOAD_PER_CORE]) ?>">
                    <span class="pa-kpi__label">Charge CPU par cœur</span>
                    <span class="pa-kpi__value"><?= isset($sensor['load_per_core']) ? $h(number_format((float) $sensor['load_per_core'], 2, ',', '')) : '—' ?></span>
                    <span class="pa-kpi__hint"><?= isset($sensor['load_per_core']) ? 'Seuil ' . $h((string) $cfg[WatchSettings::ALERT_LOAD_PER_CORE]) . ' · ' . (int) ($loadCores ?? 0) . ' cœur(s)' : 'Non lisible sur ce serveur' ?></span>
                </li>
            </ul>

            <div class="pa-card">
                <p class="pa-card__kicker">Ce qui est mesuré, et ce qui ne l’est pas</p>
                <h2 class="pa-card__title">Lecture honnête de ces chiffres</h2>
                <p class="pa-card__help">
                    <strong>Mesuré par l’application :</strong> chaque requête qui arrive jusqu’à PHP (route, communauté, code de réponse, temps passé dans PHP),
                    et la charge du processeur du serveur lorsqu’elle est lisible.
                </p>
                <p class="pa-card__help">
                    <strong>Non mesuré :</strong> l’état réel du réseau ; ce que nginx, le pare-feu ou l’hébergeur refusent avant PHP ; l’attente dans la file de
                    PHP-FPM avant l’exécution ; un trafic malveillant arrêté en amont. Un site saturé peut donc sembler calme ici. Voir
                    <code>docs/technical/protection-charge-vps.md</code> pour les limites nginx, l’état de PHP-FPM et la conduite à tenir en cas d’attaque réseau.
                </p>
            </div>

            <h3 class="pa-label" style="margin:1.5rem 0 0.5rem;">Tendance : requêtes par minute, 60 dernières minutes</h3>
            <?php if ($minutes === []): ?>
                <p class="pa-card__help">Aucune mesure enregistrée sur la période (la tâche « Surveillance » écrit une fois par minute close).</p>
            <?php else: ?>
                <div role="img" aria-label="Requêtes par minute sur la dernière heure" style="display:flex;align-items:flex-end;gap:2px;height:96px;margin-bottom:0.5rem;">
                    <?php foreach ($minutes as $m): ?>
                        <?php $pct = max(2, (int) round(((int) $m['requests']) * 100 / $maxMinute)); $errShare = (int) $m['requests'] > 0 ? (int) $m['errors'] * 100 / (int) $m['requests'] : 0; ?>
                        <span title="<?= $h($fmtTime((int) $m['minute']) . ' · ' . (int) $m['requests'] . ' requêtes · ' . (int) $m['errors'] . ' erreurs · ' . (int) $m['shed'] . ' ralenties') ?>"
                              style="flex:1;min-width:2px;height:<?= $pct ?>%;border-radius:2px 2px 0 0;background:<?= $errShare >= (float) $cfg[WatchSettings::ALERT_ERROR_PCT] ? 'var(--pa-tone-rose-fg)' : ((int) $m['shed'] > 0 ? 'var(--pa-tone-amber-fg, #b7791f)' : 'var(--pa-mint-bright)') ?>;"></span>
                    <?php endforeach; ?>
                </div>
                <p class="pa-hint">Vert : normal. Ambre : des requêtes ont été ralenties. Rouge : taux d’erreurs au-dessus du seuil. Survolez une barre pour le détail.</p>
            <?php endif; ?>

            <?php if ($hours !== []): ?>
                <h3 class="pa-label" style="margin:1.5rem 0 0.5rem;">Tendance : requêtes par heure</h3>
                <div class="pa-table-wrap">
                    <table class="pa-table">
                        <thead><tr><th>Heure</th><th class="pa-num">Requêtes</th><th class="pa-num">Erreurs 5xx</th><th class="pa-num">Ralenties</th><th style="width:45%;"></th></tr></thead>
                        <tbody>
                            <?php foreach (array_reverse($hours) as $x): ?>
                                <tr>
                                    <td><?= $h(date('d/m H\h', (int) $x['hour'])) ?></td>
                                    <td class="pa-num"><?= (int) $x['requests'] ?></td>
                                    <td class="pa-num"><?= (int) $x['errors'] ?></td>
                                    <td class="pa-num"><?= (int) $x['shed'] ?></td>
                                    <td><span style="display:block;height:8px;border-radius:4px;background:var(--pa-line);overflow:hidden;"><span style="display:block;height:100%;width:<?= (int) round(((int) $x['requests']) * 100 / $maxHour) ?>%;background:var(--pa-mint-bright);"></span></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <h3 class="pa-label" style="margin:1.5rem 0 0.5rem;">Routes les plus chargées (15 dernières minutes)</h3>
            <?php if ($routes === []): ?>
                <p class="pa-card__help">Aucune requête enregistrée sur la période.</p>
            <?php else: ?>
                <div class="pa-table-wrap">
                    <table class="pa-table">
                        <thead><tr><th>Route</th><th class="pa-num">Requêtes</th><th class="pa-num">15 min avant</th><th class="pa-num">Erreurs 5xx</th><th class="pa-num">Taux</th><th class="pa-num">Ralenties</th><th class="pa-num">Moyenne</th><th class="pa-num">p95 (classe)</th></tr></thead>
                        <tbody>
                            <?php foreach ($routes as $r): ?>
                                <?php $rate = (int) $r['requests'] > 0 ? (int) $r['errors'] * 100 / (int) $r['requests'] : 0; ?>
                                <tr>
                                    <td><span class="pa-name"><?= $h($r['route']) ?></span></td>
                                    <td class="pa-num"><?= (int) $r['requests'] ?></td>
                                    <td class="pa-num"><?= (int) $r['previous'] ?><?= (int) $r['previous'] > 0 ? ' (' . ((int) $r['requests'] >= (int) $r['previous'] ? '+' : '−') . (int) round(abs((int) $r['requests'] - (int) $r['previous']) * 100 / (int) $r['previous']) . ' %)' : '' ?></td>
                                    <td class="pa-num"><?= (int) $r['errors'] ?></td>
                                    <td class="pa-num"><?= $h(number_format($rate, 1, ',', '')) ?> %</td>
                                    <td class="pa-num"><?= (int) $r['shed'] ?></td>
                                    <td class="pa-num"><?= (int) $r['avg_ms'] ?> ms</td>
                                    <td class="pa-num"><?= $h($fmtMs($r['p95_ms'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <h3 class="pa-label" style="margin:1.5rem 0 0.5rem;">Communautés les plus actives (dernière heure)</h3>
            <?php if ($tenants === []): ?>
                <p class="pa-card__help">Aucune requête rattachée à une communauté sur la période.</p>
            <?php else: ?>
                <div class="pa-table-wrap">
                    <table class="pa-table">
                        <thead><tr><th>Communauté</th><th>Formule effective</th><th class="pa-num">Requêtes</th><th class="pa-num">Erreurs 5xx</th><th class="pa-num">Ralenties</th><th class="pa-num">Durée moyenne</th><th>Route la plus appelée</th></tr></thead>
                        <tbody>
                            <?php foreach ($tenants as $t): ?>
                                <tr>
                                    <td><span class="pa-name"><?= $h($t['name']) ?></span></td>
                                    <td><span class="pa-pill <?= in_array($t['plan_slug'], ['pro', 'pro_plus'], true) ? 'pa-pill--mint' : ($t['plan_slug'] === 'standard' ? 'pa-pill--blue' : 'pa-pill--slate') ?>"><?= $h($t['plan_label']) ?></span></td>
                                    <td class="pa-num"><?= (int) $t['requests'] ?></td>
                                    <td class="pa-num"><?= (int) $t['errors'] ?></td>
                                    <td class="pa-num"><?= (int) $t['shed'] ?></td>
                                    <td class="pa-num"><?= (int) $t['avg_ms'] ?> ms</td>
                                    <td><span class="pa-sub"><?= $h($t['top_route']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <form class="pa-card" method="post" action="<?= $h(url('admin/system/surcharge/reglages')) ?>" style="margin-top:1.5rem;">
                <?= \App\Core\Csrf::field() ?>
                <p class="pa-card__kicker">Réglages</p>
                <h2 class="pa-card__title">Seuils d’alerte et priorité sous charge</h2>
                <p class="pa-card__help">
                    Chaque seuil est comparé à une mesure de la dernière minute. Le palier 1 commence quand l’une des mesures atteint son seuil, le palier 2 à une fois et demie
                    le seuil, le palier 3 à deux fois et demie. Monter demande deux mesures de suite au-dessus, redescendre trois mesures de suite en dessous.
                </p>
                <div class="pa-field">
                    <label for="load_alert_p95_ms">Durée p95 d’une requête (ms)</label>
                    <input id="load_alert_p95_ms" type="number" name="load_alert_p95_ms" min="500" max="60000" step="100" value="<?= $h((string) $cfg[WatchSettings::ALERT_P95_MS]) ?>">
                </div>
                <div class="pa-field">
                    <label for="load_alert_error_pct">Part de réponses 5xx (%)</label>
                    <input id="load_alert_error_pct" type="number" name="load_alert_error_pct" min="1" max="90" step="1" value="<?= $h((string) $cfg[WatchSettings::ALERT_ERROR_PCT]) ?>">
                </div>
                <div class="pa-field">
                    <label for="load_alert_load_per_core">Charge du processeur par cœur</label>
                    <input id="load_alert_load_per_core" type="number" name="load_alert_load_per_core" min="0.5" max="50" step="0.1" value="<?= $h((string) $cfg[WatchSettings::ALERT_LOAD_PER_CORE]) ?>">
                </div>
                <div class="pa-field">
                    <label><input type="checkbox" name="load_shed_enabled" value="1" <?= $shedOn ? 'checked' : '' ?>> Ralentir les formules les moins élevées sous saturation avérée</label>
                    <p class="pa-hint">
                        Désactivé par défaut. Palier 1 : communautés Gratuit ; palier 2 : Gratuit et Standard ; palier 3 : Gratuit, Standard et Pro. Pro+ n’est jamais ralenti.
                        Réponse 503 avec « Retry-After » sur les seules routes non critiques. Sans mesure fiable, personne n’est ralenti.
                        <?php if ($loadPlanIndexAge !== null): ?>Formules effectives relues il y a <?= (int) $loadPlanIndexAge ?> s (cadeaux compris).<?php else: ?>Index des formules pas encore construit.<?php endif; ?>
                    </p>
                </div>
                <div class="pa-field">
                    <label for="load_shed_percent">Part des requêtes ralenties pour une formule visée (%)</label>
                    <p class="pa-hint">Moins de 100 : une partie des requêtes passe encore, le ralentissement est progressif.</p>
                    <input id="load_shed_percent" type="number" name="load_shed_percent" min="10" max="100" step="5" value="<?= $h((string) $cfg[WatchSettings::SHED_PERCENT]) ?>">
                </div>
                <div class="pa-actions" style="margin-top:1rem;">
                    <button type="submit" class="pa-btn pa-btn--ink">Enregistrer</button>
                </div>
            </form>

            <div class="pa-card">
                <p class="pa-card__kicker">Garanties</p>
                <h2 class="pa-card__title">Jamais ralenti, quelle que soit la charge</h2>
                <p class="pa-card__help">
                    Connexion et session, paiements et webhooks, alertes de commandement et sécurité des joueurs (ordres, alertes plein écran, santé, appui, SALUTE, tchat),
                    administration de la plateforme, signalement d’erreurs du mod et sondes de vie, interface opérationnelle (carte ATAK, téléphone) et ressources de page.
                    Les écritures et les appels d’API d’un navigateur ne sont pas ralentis non plus : seuls les envois du mod et les pages ouvertes en lecture le sont.
                </p>
                <details>
                    <summary>Liste exacte des chemins protégés (<?= count($never) ?>)</summary>
                    <p class="pa-sub" style="word-break:break-word;"><?= $h(implode('  ', $never)) ?></p>
                </details>
            </div>

            <?php if ($alerts !== []): ?>
                <h3 class="pa-label" style="margin:1.5rem 0 0.5rem;">Dernières alertes de charge</h3>
                <div class="pa-table-wrap">
                    <table class="pa-table">
                        <thead><tr><th>Début</th><th>Dernière mesure</th><th class="pa-num">Mesures</th><th>Valeurs</th></tr></thead>
                        <tbody>
                            <?php foreach ($alerts as $a): ?>
                                <tr>
                                    <td><?= $h($fmtTime((int) (strtotime((string) $a['first_seen_at'] . ' UTC') ?: 0))) ?></td>
                                    <td><?= $h($fmtTime((int) (strtotime((string) $a['last_seen_at'] . ' UTC') ?: 0))) ?></td>
                                    <td class="pa-num"><?= (int) $a['occurrences'] ?></td>
                                    <td><span class="pa-sub"><?= $h(implode(' · ', array_map(static fn (array $l): string => $l[0] . ' ' . $l[1], WatchRules::measuredLines((string) $a['measured_json'])))) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
