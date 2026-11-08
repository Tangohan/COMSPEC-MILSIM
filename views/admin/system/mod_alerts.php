<?php
declare(strict_types=1);

use App\Services\Watch\WatchRules;
use App\Services\Watch\WatchSettings;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$rows = is_array($modAlertRows ?? null) ? $modAlertRows : [];
$status = (string) ($modAlertStatus ?? 'open');
$statuses = is_array($modAlertStatuses ?? null) ? $modAlertStatuses : [];
$counts = is_array($modAlertCounts ?? null) ? $modAlertCounts : [];
$cfg = is_array($modAlertSettings ?? null) ? $modAlertSettings : WatchSettings::defaults();
$rules = is_array($modAlertRules ?? null) ? $modAlertRules : [];
$fmt = static function (mixed $raw): string {
    $t = strtotime((string) $raw . ' UTC');

    return $t ? date('d/m H:i', $t) : (string) $raw;
};
$statusPill = ['open' => 'pa-pill--amber', 'ack' => 'pa-pill--blue', 'dismissed' => 'pa-pill--slate'];
$statusText = ['open' => 'À examiner', 'ack' => 'Prise en compte', 'dismissed' => 'Ignorée'];
$ok = \App\Core\Session::getFlash('success');
$err = \App\Core\Session::getFlash('error');
$number = static function (string $key, string $label, string $hint, string $step = '1') use ($cfg): string {
    return
    '<div class="pa-field"><label for="' . $key . '">' . $label . '</label>'
    . ($hint !== '' ? '<p class="pa-hint">' . $hint . '</p>' : '')
    . '<input id="' . $key . '" type="number" name="' . $key . '" min="' . WatchSettings::bounds($key)[1] . '" max="' . WatchSettings::bounds($key)[2] . '" step="' . $step . '" value="' . htmlspecialchars((string) $cfg[$key], ENT_QUOTES, 'UTF-8') . '"></div>';
};
?>
<div class="pa">
    <div class="pa__frame">
        <header class="pa-hero">
            <p class="pa-crumb">
                <a href="<?= $h(url('admin')) ?>">Administration du site</a>
                <span aria-hidden="true"> / </span>
                Mod Overwatch
                <span aria-hidden="true"> / </span>
                Activités suspectes
            </p>
            <h1 class="pa-hero__title">Activités suspectes du mod</h1>
            <p class="pa-hero__lead">
                Des règles simples comparent les envois du mod à l’usage normal. Une alerte dit ce qui a été constaté, avec les valeurs mesurées : elle ne prouve rien.
                Aucun joueur n’est banni, bloqué ou sanctionné automatiquement ; c’est à vous de décider, ici, au vu des valeurs.
            </p>
            <div class="pa-hero__actions">
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/parc-atak')) ?>">Parc ATAK des joueurs</a>
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/system/blocklist')) ?>">Liste de restriction</a>
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/system/surcharge')) ?>">Charge et surcharge</a>
            </div>
        </header>

        <div class="pa-panel">
            <?php if ($ok): ?><p class="pa-flash pa-flash--ok"><?= $h($ok) ?></p><?php endif; ?>
            <?php if ($err): ?><p class="pa-flash pa-flash--err"><?= $h($err) ?></p><?php endif; ?>

            <ul class="pa-kpi-strip" style="margin-bottom:1.25rem;">
                <li class="pa-kpi<?= (int) ($counts['open'] ?? 0) > 0 ? ' pa-kpi--warn' : '' ?>">
                    <span class="pa-kpi__label">À examiner</span>
                    <span class="pa-kpi__value"><?= (int) ($counts['open'] ?? 0) ?></span>
                    <span class="pa-kpi__hint">Doublons regroupés</span>
                </li>
                <li class="pa-kpi">
                    <span class="pa-kpi__label">Prises en compte</span>
                    <span class="pa-kpi__value"><?= (int) ($counts['ack'] ?? 0) ?></span>
                    <span class="pa-kpi__hint">Gardées 30 jours</span>
                </li>
                <li class="pa-kpi">
                    <span class="pa-kpi__label">Ignorées</span>
                    <span class="pa-kpi__value"><?= (int) ($counts['dismissed'] ?? 0) ?></span>
                    <span class="pa-kpi__hint">Silence 24 h pour le même cas</span>
                </li>
            </ul>

            <nav class="pa-actions" aria-label="Filtrer par état" style="margin-bottom:1rem;">
                <?php foreach ($statuses as $key => $label): ?>
                    <a class="pa-btn <?= $status === $key ? 'pa-btn--ink' : 'pa-btn--line' ?>" href="<?= $h(url('admin/system/alertes-mod') . '?statut=' . $key) ?>"><?= $h($label) ?></a>
                <?php endforeach; ?>
            </nav>

            <?php if ($rows === []): ?>
                <p class="pa-card__help">Aucune alerte dans cet état.</p>
            <?php else: ?>
                <div class="pa-table-wrap">
                    <table class="pa-table">
                        <thead><tr><th>Quand</th><th>Règle</th><th>Communauté</th><th>Joueur</th><th>Valeurs mesurées</th><th class="pa-num">Fois</th><th>État</th><th>Décision</th></tr></thead>
                        <tbody>
                            <?php foreach ($rows as $a): ?>
                                <?php $player = trim((string) ($a['user_callsign'] ?? '')) !== '' ? (string) $a['user_callsign'] : (string) ($a['user_name'] ?? ''); ?>
                                <tr>
                                    <td>
                                        <span class="pa-name"><?= $h($fmt($a['last_seen_at'])) ?></span>
                                        <span class="pa-sub">depuis <?= $h($fmt($a['first_seen_at'])) ?></span>
                                    </td>
                                    <td>
                                        <span class="pa-name"><?= $h($a['rule_label']) ?></span>
                                        <details><summary class="pa-sub">Ce que dit la règle</summary><span class="pa-sub"><?= $h($a['rule_explain']) ?></span></details>
                                    </td>
                                    <td><?= $a['tenant_name'] !== null ? $h($a['tenant_name']) : '—' ?></td>
                                    <td>
                                        <?php if ($player !== ''): ?><span class="pa-name"><?= $h($player) ?></span><?php endif; ?>
                                        <span class="pa-sub"><?= $a['steam_uid'] !== null ? 'Steam ' . $h($a['steam_uid']) : 'Joueur non identifié' ?></span>
                                    </td>
                                    <td>
                                        <?php foreach ($a['lines'] as [$name, $value]): ?>
                                            <span class="pa-sub" style="display:block;"><?= $h($name) ?> : <?= $h($value) ?></span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td class="pa-num"><?= (int) $a['occurrences'] ?></td>
                                    <td><span class="pa-pill <?= $statusPill[$a['status']] ?? 'pa-pill--slate' ?>"><?= $h($statusText[$a['status']] ?? $a['status']) ?></span></td>
                                    <td>
                                        <form method="post" action="<?= $h(url('admin/system/alertes-mod/' . (int) $a['id'] . '/traiter')) ?>" style="display:flex;gap:0.35rem;flex-wrap:wrap;">
                                            <?= \App\Core\Csrf::field() ?>
                                            <input type="hidden" name="retour" value="<?= $h($status) ?>">
                                            <?php if ($a['status'] !== 'ack'): ?><button class="pa-btn pa-btn--line" type="submit" name="action" value="ack">Prendre en compte</button><?php endif; ?>
                                            <?php if ($a['status'] !== 'dismissed'): ?><button class="pa-btn pa-btn--line" type="submit" name="action" value="dismiss">Ignorer</button><?php endif; ?>
                                            <?php if ($a['status'] !== 'open'): ?><button class="pa-btn pa-btn--line" type="submit" name="action" value="reopen">Rouvrir</button><?php endif; ?>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <form class="pa-card" method="post" action="<?= $h(url('admin/system/alertes-mod/reglages')) ?>" style="margin-top:1.5rem;">
                <?= \App\Core\Csrf::field() ?>
                <p class="pa-card__kicker">Seuils</p>
                <h2 class="pa-card__title">Réglage des détecteurs</h2>
                <p class="pa-card__help">
                    Les mesures sont écrites par lots, sans ralentir le jeu. Les valeurs de départ sont volontairement modestes : un seuil trop bas inonde de faux positifs
                    (téléportation d’une mission, réapparition, carte très grande). Aucune adresse réseau ni e-mail n’est gardé dans les alertes ; elles sont conservées 30 jours.
                </p>
                <div class="pa-field">
                    <label><input type="checkbox" name="abuse_detect_enabled" value="1" <?= (int) $cfg[WatchSettings::DETECT_ENABLED] === 1 ? 'checked' : '' ?>> Détecter les envois anormaux du mod</label>
                </div>
                <?= $number(WatchSettings::SPEED_MPS, 'Vitesse jugée impossible (m/s)', 'Distance divisée par le temps entre deux positions du même joueur, sur 300 m au moins.') ?>
                <?= $number(WatchSettings::SPEED_HITS, 'Sauts répétés avant alerte', 'Nombre de déplacements impossibles en 10 minutes.') ?>
                <?= $number(WatchSettings::POS_LIMIT_M, 'Étendue maximale d’une carte (m)', 'Au-delà, une position est comptée « hors carte » (3 en 10 minutes pour une alerte).', '1000') ?>
                <?= $number(WatchSettings::VOLUME_PER_MIN, 'Envois par minute et par joueur', 'Alerte si le seuil est dépassé pendant 2 minutes sur les 5 dernières.', '10') ?>
                <?= $number(WatchSettings::CRED_STEAMS, 'Steam distincts pour un même jeton', 'Nombre d’identifiants Steam différents présentés avec un même jeton en 10 minutes.') ?>
                <?= $number(WatchSettings::REFUSALS_PER_MIN, 'Écritures refusées par minute', 'Refus des contrôles du serveur (identité, débit, coordonnées…) en une minute.') ?>
                <?= $number(WatchSettings::VERSION_CHANGES, 'Versions différentes pour un même joueur', 'Nombre de versions du mod annoncées par un même joueur en 10 minutes.') ?>
                <div class="pa-actions" style="margin-top:1rem;">
                    <button type="submit" class="pa-btn pa-btn--ink">Enregistrer</button>
                </div>
            </form>

            <div class="pa-card">
                <p class="pa-card__kicker">Règles</p>
                <h2 class="pa-card__title">Ce que chaque règle constate</h2>
                <?php foreach ($rules as $rule): ?>
                    <p class="pa-card__help"><strong><?= $h(WatchRules::label($rule)) ?>.</strong> <?= $h(WatchRules::explain($rule)) ?></p>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
