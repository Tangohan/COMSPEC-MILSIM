<?php

declare(strict_types=1);

/**
 * Fiche membre › Disponibilité : sessions de jeu du membre sur le serveur de sa communauté.
 * Inclus par member.php dans son onglet. Variables : $memberAvailability (PlayerSessionRepository::panelForUser, ou null),
 * $availabilitySettingsUrl (lien vers le réglage du seuil, vide si le lecteur ne le gère pas).
 * Seules les sessions d'au moins le seuil de la communauté comptent ; les plus courtes restent listées pour l'historique.
 */

use App\Services\Personnel\PlayerSessionService as Sessions;

$avh = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$av = is_array($memberAvailability ?? null) ? $memberAvailability : null;
$avSettingsUrl = trim((string) ($availabilitySettingsUrl ?? ''));
$avSum = is_array($av['summary'] ?? null) ? $av['summary'] : [];
$avMin = max(1, (int) ($av['min_minutes'] ?? Sessions::DEFAULT_MIN_MINUTES));
$avGap = max(1, (int) ($av['gap_minutes'] ?? Sessions::DEFAULT_GAP_MINUTES));
$avThreshold = Sessions::thresholdLabel($avMin);
$avTz = new DateTimeZone(in_array((string) ($av['tz'] ?? ''), DateTimeZone::listIdentifiers(), true) ? (string) $av['tz'] : 'Europe/Paris');
$avWhen = static function (?int $ts) use ($avTz): string {
    return $ts !== null && $ts > 0 ? (new DateTimeImmutable('@' . $ts))->setTimezone($avTz)->format('d/m/Y H:i') : '—';
};
$avTpl = static fn (string $tpl, array $vals): string => str_replace(['%1', '%2', '%3'], array_map('strval', $vals + [0, 0, 0]), $tpl);
$avReady = !empty($av['ready']);
$avLinked = !empty($avSum['linked']);
$avHasData = !empty($avSum['has_data']);
$avReliability = (string) ($avSum['reliability'] ?? Sessions::RELIABILITY_NO_DATA);
$avReliabilityChip = [
    Sessions::RELIABILITY_GOOD => ['is-ok', 'Fiable'],
    Sessions::RELIABILITY_PARTIAL => ['is-warn', 'À vérifier'],
    Sessions::RELIABILITY_NO_DATA => ['', 'Aucune donnée'],
    Sessions::RELIABILITY_NO_UID => ['', 'Steam non relié'],
][$avReliability] ?? ['', 'Aucune donnée'];
$avReliabilityHelp = [
    Sessions::RELIABILITY_GOOD => 'Les sessions viennent des signes de vie du mod, sans anomalie.',
    Sessions::RELIABILITY_PARTIAL => 'Certaines sessions sont reconstituées après coup ou dépassent 24 h : les durées sont à prendre avec réserve.',
    Sessions::RELIABILITY_NO_DATA => 'Le compte Steam est relié, mais aucune session n’a été enregistrée sur 90 jours.',
    Sessions::RELIABILITY_NO_UID => 'Sans compte Steam relié, aucune session ne peut être attribuée à ce membre.',
][$avReliability] ?? '';
$avWindows = [7 => '7 jours', 30 => '30 jours', 90 => '90 jours'];
$avLast = is_array($avSum['last_qualifying'] ?? null) ? $avSum['last_qualifying'] : null;
$avRecent = is_array($av['recent'] ?? null) ? $av['recent'] : [];
$avExplain = $avTpl('Une session est une présence continue sur le serveur de la communauté : les reconnexions de moins de %1 minutes restent la même session. Elle compte quand elle dure %2 minutes ou plus.', [$avGap, $avMin]);
?>
<section class="eff-fm-sec eff-fm-av" id="disponibilite" aria-labelledby="disponibilite-titre">
    <header class="eff-fm-sec__head">
        <h2 id="disponibilite-titre">Disponibilité</h2>
        <p><?= $avh($avTpl('Sessions de jeu sur le serveur de la communauté : seules celles de %1 comptent.', [$avThreshold])) ?></p>
    </header>

    <?php if (!$avReady): ?>
        <div class="eff-fm-av__note" role="status">
            <p class="eff-fm-lead">Le suivi des sessions de jeu n’est pas encore actif sur ce site : la disponibilité reste calculée sur la participation et la présence aux événements.</p>
        </div>
    <?php elseif (!$avLinked): ?>
        <div class="eff-fm-av__note" role="status">
            <p class="eff-fm-lead">Ce membre n’a pas relié son compte Steam : aucune session de jeu ne peut lui être attribuée.</p>
            <p class="eff-fm-hint">Le compte Steam se relie depuis la fiche du membre, dans l’onglet Accès, ou par le membre lui-même.</p>
        </div>
    <?php else: ?>
        <div class="eff-fm-av__top">
            <p class="eff-fm-av__state <?= !empty($avSum['active']) ? 'is-on' : 'is-off' ?>">
                <?php if (!empty($avSum['ongoing_qualifying'])): ?>
                    <b>En jeu</b> <span><?= $avh($avTpl('Session en cours de %1 comptée.', [Sessions::durationLabel((int) ($avLast['duration_s'] ?? 0))])) ?></span>
                <?php elseif (!empty($avSum['active'])): ?>
                    <b>Actif sur le serveur</b> <span><?= $avh($avTpl('Au moins une session de %1 sur les %2 derniers jours.', [$avThreshold, (int) ($avSum['active_days'] ?? 30)])) ?></span>
                <?php else: ?>
                    <b>Pas de session qui compte</b> <span><?= $avh($avTpl('Aucune session de %1 sur les %2 derniers jours.', [$avThreshold, (int) ($avSum['active_days'] ?? 30)])) ?></span>
                <?php endif; ?>
            </p>
            <span class="eff-fm-av__chip <?= $avh($avReliabilityChip[0]) ?>" tabindex="0" title="<?= $avh($avReliabilityHelp) ?>"><?= $avh($avReliabilityChip[1]) ?></span>
        </div>

        <div class="eff-fm-av__tablewrap">
            <table class="eff-fm-av__table">
                <caption class="eff-fm-sr">Sessions de jeu qui comptent, par période</caption>
                <thead>
                    <tr>
                        <th scope="col">Période</th>
                        <th scope="col" title="<?= $avh($avExplain) ?>"><?= $avh($avTpl('Sessions de %1', [$avThreshold])) ?></th>
                        <th scope="col">Durée cumulée</th>
                        <th scope="col">Jours de jeu</th>
                        <th scope="col" title="Ces sessions restent dans l’historique mais ne comptent pas dans la disponibilité.">Sessions plus courtes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($avWindows as $days => $label): ?>
                        <?php $w = is_array($avSum['windows'][$days] ?? null) ? $avSum['windows'][$days] : ['sessions' => 0, 'seconds' => 0, 'days' => 0, 'short' => 0]; ?>
                        <tr>
                            <th scope="row"><?= $avh($label) ?></th>
                            <td data-label="Sessions qui comptent"><b><?= (int) $w['sessions'] ?></b></td>
                            <td data-label="Durée cumulée"><?= (int) $w['seconds'] > 0 ? $avh(Sessions::durationLabel((int) $w['seconds'])) : '—' ?></td>
                            <td data-label="Jours de jeu"><?= (int) $w['days'] ?></td>
                            <td data-label="Sessions plus courtes" class="is-muted"><?= (int) $w['short'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <dl class="eff-fm-av__facts">
            <div>
                <dt>Dernière session qui compte</dt>
                <dd>
                    <?php if ($avLast !== null): ?>
                        <?= $avh($avWhen((int) $avLast['started_at'])) ?> · <?= $avh(Sessions::durationLabel((int) $avLast['duration_s'])) ?><?= !empty($avLast['ongoing']) ? ' · en cours' : '' ?>
                    <?php else: ?>
                        Aucune sur 90 jours
                    <?php endif; ?>
                </dd>
            </div>
            <div>
                <dt>Série</dt>
                <dd><?= (int) ($avSum['streak_days'] ?? 0) > 0 ? $avh($avTpl((int) $avSum['streak_days'] > 1 ? '%1 jours de suite' : '%1 jour de suite', [(int) $avSum['streak_days']])) : 'Aucune' ?></dd>
            </div>
            <div>
                <dt>Fiabilité</dt>
                <dd><?= $avh($avReliabilityHelp) ?></dd>
            </div>
        </dl>

        <details class="eff-fm-av__drawer"<?= $avRecent !== [] ? ' open' : '' ?>>
            <summary>
                <span class="eff-fm-av__drawer-title">Sessions récentes</span>
                <span class="eff-fm-av__drawer-sub">Les dernières présences sur le serveur, celles qui comptent et les plus courtes.</span>
            </summary>
            <?php if ($avRecent === []): ?>
                <p class="eff-fm-lead">Aucune session enregistrée sur les 90 derniers jours.</p>
            <?php else: ?>
                <ol class="eff-fm-av__list">
                    <?php foreach ($avRecent as $s): ?>
                        <li class="<?= !empty($s['qualifies']) ? 'is-counted' : 'is-short' ?>">
                            <span class="eff-fm-av__when"><?= $avh($avWhen((int) $s['started_at'])) ?></span>
                            <span class="eff-fm-av__dur"><?= $avh(Sessions::durationLabel((int) $s['duration_s'])) ?></span>
                            <span class="eff-fm-av__tag">
                                <?php if (!empty($s['ongoing'])): ?>En cours<?php elseif (!empty($s['qualifies'])): ?>Comptée<?php else: ?>Trop courte<?php endif; ?><?= !empty($s['aberrant']) ? ' · plafonnée à 24 h' : '' ?><?= ($s['source'] ?? '') === 'backfill' ? ' · reconstituée' : '' ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </details>
    <?php endif; ?>

    <p class="eff-fm-hint eff-fm-av__explain"><?= $avh($avExplain) ?></p>
    <?php if ($avSettingsUrl !== ''): ?>
        <p class="eff-fm-av__cfg"><a href="<?= $avh($avSettingsUrl) ?>">Régler la durée qui compte</a></p>
    <?php endif; ?>
</section>
