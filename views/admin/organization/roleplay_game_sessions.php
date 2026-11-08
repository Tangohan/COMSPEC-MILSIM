<?php
declare(strict_types=1);

/**
 * Sessions Arma (back-office/roleplay/sessions) — charte ATHENA, en-tête rendu par la coque back-office.
 *
 * Une session = une soirée de jeu. Créée ici (ou depuis un événement du calendrier), démarrée au début de la
 * soirée : le temps de jeu remonté par le mod ATAK y est rattaché, puis compte pour le suivi RH à la clôture.
 *
 * @var bool $sessionSchemaReady
 * @var array{upcoming: list<array<string, mixed>>, live: list<array<string, mixed>>, past: list<array<string, mixed>>} $sessionGroups
 * @var array<int, array{total: int, checked_in: int, counted: int, validated_seconds: int}> $sessionStats
 * @var array<string, mixed> $sessionSettings
 * @var string $sessionTab
 * @var array<string, string> $sessionTabs
 * @var list<array<string, mixed>> $sessionSuggestedEvents
 * @var array<int, array<string, mixed>> $sessionEventsById
 * @var list<array<string, mixed>> $sessionEvents
 */

use App\Support\RoleplaySessionBoard as Board;

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$ready = !empty($sessionSchemaReady);
$groups = is_array($sessionGroups ?? null) ? $sessionGroups : ['upcoming' => [], 'live' => [], 'past' => []];
$stats = is_array($sessionStats ?? null) ? $sessionStats : [];
$cfg = is_array($sessionSettings ?? null) ? $sessionSettings : [];
$cats = is_array($cfg['hour_categories'] ?? null) ? $cfg['hour_categories'] : [];
$tab = (string) ($sessionTab ?? 'a-venir');
$tabs = is_array($sessionTabs ?? null) ? $sessionTabs : ['a-venir' => 'upcoming', 'en-cours' => 'live', 'passees' => 'past'];
$suggested = is_array($sessionSuggestedEvents ?? null) ? $sessionSuggestedEvents : [];
$eventsById = is_array($sessionEventsById ?? null) ? $sessionEventsById : [];
$events = is_array($sessionEvents ?? null) ? $sessionEvents : [];
$base = url('back-office/roleplay/sessions');
$syncOn = !empty($cfg['sync_enabled']);

$kinds = [
    'officielle' => ['Opération officielle', 'Compte pour le suivi et le taux de présence.'],
    'entrainement' => ['Entraînement', 'Compte comme heures de formation.'],
    'libre' => ['Session libre', 'Le temps est noté, mais ne compte pas pour le suivi.'],
];
$tabLabels = ['a-venir' => 'À venir', 'en-cours' => 'En cours', 'passees' => 'Passées'];
$tabHints = [
    'a-venir' => 'Sessions prévues, pas encore démarrées. Démarrez-les au début de la soirée.',
    'en-cours' => 'Sessions démarrées : le temps de jeu remonté par le mod y est rattaché. Clôturez-les en fin de soirée.',
    'passees' => 'Sessions clôturées ou annulées. Les temps retenus comptent dans le suivi des membres.',
];
$current = $groups[$tabs[$tab] ?? 'upcoming'] ?? [];
$total = count($groups['upcoming'] ?? []) + count($groups['live'] ?? []) + count($groups['past'] ?? []);

$statusPill = static function (array $s): string {
    $phase = Board::phase($s);
    if (($s['status'] ?? '') === 'cancelled') {
        return '<span class="rps-pill rps-pill--bad">Annulée</span>';
    }
    if ($phase === Board::PAST) {
        return '<span class="rps-pill">Terminée</span>';
    }
    if ($phase === Board::LIVE) {
        return Board::isStale($s) ? '<span class="rps-pill rps-pill--warn">À clôturer</span>' : '<span class="rps-pill rps-pill--live">En cours</span>';
    }

    return Board::isOverdue($s) ? '<span class="rps-pill rps-pill--warn">À démarrer</span>' : '<span class="rps-pill rps-pill--plan">Prévue</span>';
};
$stateButton = static function (int $id, string $state, string $label, string $class = 'ath-btn') use ($h, $base): string {
    return '<form method="post" action="' . $h($base . '/' . $id . '/etat') . '">' . \App\Core\Csrf::field()
        . '<input type="hidden" name="state" value="' . $h($state) . '"><input type="hidden" name="back" value="liste">'
        . '<button type="submit" class="' . $h($class) . '">' . $h($label) . '</button></form>';
};
?>
<div class="rps">
    <section class="rps-intro ath-rise" aria-labelledby="rps-intro-title">
        <div class="rps-intro__text">
            <p id="rps-intro-title"><strong>Une session, c’est une soirée de jeu.</strong> Vous la créez ici (ou depuis un événement du calendrier), vous la démarrez quand la partie commence : le temps de chaque joueur remonté par le mod ATAK s’y ajoute tout seul, puis compte dans son suivi RH.</p>
            <details class="rps-guide"<?= $total === 0 ? ' open' : '' ?>>
                <summary>Comment ça marche, en 3 étapes</summary>
                <ol class="rps-steps">
                    <li><strong>Préparer</strong><span>Créez la session : nom de la mission, type (opération, entraînement ou libre) et heure prévue. Un événement du calendrier peut servir de point de départ.</span></li>
                    <li><strong>Démarrer et jouer</strong><span>Au début de la soirée, cliquez sur « Démarrer ». Les joueurs dont le mod ATAK est relié à leur compte sont ajoutés automatiquement avec leur temps de jeu.</span></li>
                    <li><strong>Clôturer et vérifier</strong><span>En fin de soirée, cliquez sur « Clôturer ». Ouvrez la fiche pour retenir ou écarter une présence, ou ajouter un joueur que le mod n’a pas vu.</span></li>
                </ol>
            </details>
        </div>
        <div class="rps-intro__acts">
            <a class="ath-btn ath-btn--solid" href="#nouvelle">Nouvelle session</a>
            <a class="ath-btn" href="#regles">Règles de comptage</a>
            <a class="ath-btn" href="<?= $h(url('back-office/events')) ?>">Agenda</a>
        </div>
    </section>

    <?php if (!$ready): ?>
    <div class="ath-note">
        <div class="ath-note__title">Module en préparation</div>
        <div class="ath-note__text">Les sessions Arma seront disponibles après la prochaine mise à jour de la plateforme.</div>
    </div>
    <?php else: ?>

    <?php if (!$syncOn): ?>
    <div class="ath-note">
        <div class="ath-note__title">Le temps de jeu n’est pas rattaché aux sessions</div>
        <div class="ath-note__text">La réception depuis le jeu est coupée dans les règles de comptage : seules les présences ajoutées à la main seront comptées.</div>
    </div>
    <?php endif; ?>

    <section aria-label="Sessions">
        <nav class="rps-tabs" aria-label="Sessions par période">
            <?php foreach ($tabs as $slug => $key): ?>
            <a class="rps-tab<?= $key === 'live' && ($groups['live'] ?? []) !== [] ? ' rps-tab--live' : '' ?>" href="<?= $h($base . '?vue=' . $slug) ?>"<?= $slug === $tab ? ' aria-current="page"' : '' ?>>
                <?= $h($tabLabels[$slug] ?? $slug) ?> <span class="rps-tab__count"><?= count($groups[$key] ?? []) ?></span>
            </a>
            <?php endforeach; ?>
        </nav>
        <p class="rps-tabhint"><?= $h($tabHints[$tab] ?? '') ?></p>
    </section>

    <?php if ($current === []): ?>
    <div class="rps-empty">
        <?php if ($total === 0): ?>
        <strong>Aucune session pour l’instant</strong>
        <p>Créez votre première session ci-dessous, ou préparez-la à partir d’un événement du calendrier. Le temps de jeu ne remonte que pendant qu’une session est démarrée.</p>
        <div class="rps-empty__acts">
            <a class="ath-btn ath-btn--solid" href="#nouvelle">Créer une session</a>
            <a class="ath-btn" href="<?= $h(url('back-office/events')) ?>">Ouvrir le calendrier des événements</a>
        </div>
        <?php elseif ($tab === 'en-cours'): ?>
        <strong>Aucune session en cours</strong>
        <p>Rien n’est compté en ce moment. Démarrez une session prévue (onglet « À venir ») ou créez-en une qui démarre tout de suite.</p>
        <div class="rps-empty__acts">
            <a class="ath-btn ath-btn--solid" href="#nouvelle">Créer une session</a>
            <a class="ath-btn" href="<?= $h($base . '?vue=a-venir') ?>">Voir les sessions à venir</a>
        </div>
        <?php elseif ($tab === 'a-venir'): ?>
        <strong>Aucune session prévue</strong>
        <p>Préparez la prochaine soirée : créez une session avec son heure prévue, ou partez d’un événement du calendrier.</p>
        <div class="rps-empty__acts">
            <a class="ath-btn ath-btn--solid" href="#nouvelle">Prévoir une session</a>
            <a class="ath-btn" href="<?= $h(url('back-office/events')) ?>">Calendrier des événements</a>
        </div>
        <?php else: ?>
        <strong>Aucune session passée</strong>
        <p>Les sessions apparaissent ici une fois clôturées. Leur temps retenu compte alors dans le suivi des membres.</p>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <ul class="rps-list">
        <?php foreach ($current as $s):
            $id = (int) ($s['id'] ?? 0);
            $phase = Board::phase($s);
            $ref = Board::referenceDate($s);
            [$day, $mon] = Board::dayBadge($ref);
            $st = $stats[$id] ?? ['total' => 0, 'checked_in' => 0, 'counted' => 0, 'validated_seconds' => 0];
            $kind = $kinds[(string) ($s['session_kind'] ?? 'libre')] ?? $kinds['libre'];
            $event = $eventsById[(int) ($s['community_event_id'] ?? 0)] ?? null;
            $warn = Board::isStale($s) || Board::isOverdue($s);
            $cardClass = 'rps-card' . ($warn ? ' rps-card--warn' : ($phase === Board::LIVE ? ' rps-card--live' : ($phase === Board::PAST ? ' rps-card--muted' : '')));
            $href = $base . '/' . $id;
            $whenText = '';
            $whenPrefix = '';
            if ($phase === Board::UPCOMING) {
                $planned = trim((string) ($s['planned_starts_at'] ?? ''));
                $whenPrefix = 'planned';
                $whenText = $planned !== '' ? Board::when($planned) : '';
            } elseif ($phase === Board::LIVE) {
                $whenPrefix = 'started';
                $whenText = Board::when((string) $s['started_at']);
            } else {
                $whenText = Board::when($ref);
                $end = Board::time((string) ($s['ended_at'] ?? ''));
                if ($end !== '' && $whenText !== '') {
                    $whenText .= ' → ' . $end;
                }
            }
        ?>
        <li class="<?= $h($cardClass) ?>">
            <div class="rps-date" aria-hidden="true"><b><?= $h($day) ?></b><span><?= $h($mon) ?></span></div>
            <div class="rps-card__body">
                <h3 class="rps-card__title"><a href="<?= $h($href) ?>"><?= $h(trim((string) ($s['mission_name'] ?? '')) ?: 'Session') ?></a> <?= $statusPill($s) ?></h3>
                <div class="rps-card__meta">
                    <?php if ($whenText === ''): ?>
                    <span>Heure non fixée</span>
                    <?php elseif ($whenPrefix === 'planned'): ?>
                    <span>Prévue le <?= $h($whenText) ?></span>
                    <?php elseif ($whenPrefix === 'started'): ?>
                    <span>Démarrée le <?= $h($whenText) ?></span>
                    <?php else: ?>
                    <span><?= $h($whenText) ?></span>
                    <?php endif; ?>
                    <span><?= $h($kind[0]) ?> · <?= $h((string) ($s['hour_category'] ?? '')) ?></span>
                    <?php if (trim((string) ($s['server_name'] ?? '')) !== ''): ?><span>Serveur : <?= $h((string) $s['server_name']) ?></span><?php endif; ?>
                    <?php if ($event !== null): ?><span>Événement : <?= $h((string) ($event['title'] ?? '')) ?></span><?php endif; ?>
                </div>
                <div class="rps-card__meta">
                    <?php if ($phase === Board::UPCOMING): ?>
                    <span><?= !empty($s['attendance_enabled']) ? 'Pointage demandé aux joueurs' : 'Sans pointage' ?></span>
                    <?php elseif ((int) $st['total'] === 0): ?>
                    <span><?= $phase === Board::LIVE ? 'Aucun participant pour l’instant' : 'Aucun participant enregistré' ?></span>
                    <?php else: ?>
                    <span>Participants : <strong><?= (int) $st['total'] ?></strong></span>
                    <span>Comptés pour le suivi : <strong><?= (int) $st['counted'] ?></strong></span>
                    <span>Temps retenu : <?= $h(Board::duration((int) $st['validated_seconds'])) ?></span>
                    <?php endif; ?>
                </div>
                <?php if (Board::isStale($s)): ?>
                <div class="rps-card__note">Ouverte depuis plus de <?= Board::STALE_HOURS ?> h : clôturez-la pour figer les temps.</div>
                <?php elseif (Board::isOverdue($s)): ?>
                <div class="rps-card__note">L’heure prévue est passée : démarrez-la pour que le temps de jeu soit compté.</div>
                <?php endif; ?>
            </div>
            <div class="rps-card__acts">
                <a class="ath-btn" href="<?= $h($href) ?>">Ouvrir la fiche</a>
                <?php if ($phase === Board::UPCOMING): ?>
                <?= $stateButton($id, 'start', 'Démarrer', 'ath-btn ath-btn--solid') ?>
                <?php elseif ($phase === Board::LIVE): ?>
                <?= $stateButton($id, 'close', 'Clôturer', 'ath-btn ath-btn--solid') ?>
                <?php endif; ?>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <?php if ($suggested !== [] && $tab !== 'passees'): ?>
    <section class="rps-panel">
        <div class="rps-panel__head">
            <h2 class="rps-panel__title">Événements du calendrier sans session</h2>
            <span class="rps-panel__hint">Préparez la session en un clic : le nom et l’horaire sont repris de l’événement.</span>
        </div>
        <ul class="rps-events">
            <?php foreach (array_slice($suggested, 0, 8) as $e): ?>
            <li class="rps-event">
                <div class="rps-event__body">
                    <strong><?= $h((string) ($e['title'] ?? '')) ?></strong>
                    <span><?= $h(Board::when((string) ($e['starts_at'] ?? ''))) ?></span>
                </div>
                <form method="post" action="<?= $h($base . '/nouvelle') ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="community_event_id" value="<?= (int) ($e['id'] ?? 0) ?>">
                    <input type="hidden" name="session_kind" value="officielle">
                    <input type="hidden" name="when" value="later">
                    <button type="submit" class="ath-btn">Préparer la session</button>
                </form>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <details class="rps-panel" id="nouvelle"<?= $total === 0 ? ' open' : '' ?>>
        <summary class="rps-panel__head">
            <h2 class="rps-panel__title">Nouvelle session</h2>
            <span class="rps-panel__hint">Démarrez-la tout de suite, ou prévoyez-la pour plus tard.</span>
        </summary>
        <form method="post" action="<?= $h($base . '/nouvelle') ?>">
            <?= \App\Core\Csrf::field() ?>
            <div class="rps-panel__body rps-form">
                <label class="rps-field">
                    <span>Nom de la mission</span>
                    <input type="text" name="mission_name" maxlength="191" required placeholder="Ex. Opération Dague, entraînement tir">
                </label>
                <label class="rps-field">
                    <span>Catégorie d’heures</span>
                    <select name="hour_category">
                        <?php foreach ($cats as $cat): ?>
                        <option value="<?= $h((string) $cat) ?>"><?= $h((string) $cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small>La colonne d’heures où le temps est rangé (utilisée par le parcours RH).</small>
                </label>
                <fieldset class="rps-field rps-field--wide">
                    <legend>Type de session</legend>
                    <div class="rps-choices">
                        <?php foreach ($kinds as $k => [$label, $help]): ?>
                        <label class="rps-choice">
                            <input type="radio" name="session_kind" value="<?= $h($k) ?>"<?= $k === 'officielle' ? ' checked' : '' ?>>
                            <span><b><?= $h($label) ?></b><small><?= $h($help) ?></small></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <fieldset class="rps-field rps-field--wide">
                    <legend>Quand ?</legend>
                    <div class="rps-choices rps-choices--2">
                        <label class="rps-choice">
                            <input type="radio" name="when" value="now" checked>
                            <span><b>Maintenant</b><small>La session démarre tout de suite : le temps de jeu est compté dès à présent.</small></span>
                        </label>
                        <label class="rps-choice">
                            <input type="radio" name="when" value="later">
                            <span><b>Plus tard</b><small>La session apparaît dans « À venir ». Vous la démarrerez au début de la soirée.</small></span>
                        </label>
                    </div>
                </fieldset>
                <div class="rps-when-later">
                    <label class="rps-field">
                        <span>Début prévu</span>
                        <input type="datetime-local" name="planned_starts_at">
                    </label>
                    <label class="rps-field">
                        <span>Fin prévue (facultatif)</span>
                        <input type="datetime-local" name="planned_ends_at">
                        <small>Sert à calculer la part de la session passée en jeu.</small>
                    </label>
                </div>
                <label class="rps-field">
                    <span>Événement du calendrier (facultatif)</span>
                    <select name="community_event_id">
                        <option value="0">Aucun</option>
                        <?php foreach ($events as $e): ?>
                        <option value="<?= (int) ($e['id'] ?? 0) ?>"><?= $h((string) ($e['title'] ?? '')) ?> — <?= $h(Board::when((string) ($e['starts_at'] ?? ''))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="rps-field">
                    <span>Serveur (facultatif)</span>
                    <input type="text" name="server_name" maxlength="191" placeholder="Ex. Serveur principal">
                </label>
                <label class="rps-check rps-field--wide">
                    <input type="checkbox" name="attendance_enabled" value="1">
                    <span>Demander aux joueurs de pointer leur présence (toujours demandé pour une opération officielle).</span>
                </label>
            </div>
            <div class="rps-panel__foot">
                <button type="submit" class="ath-btn ath-btn--solid">Créer la session</button>
            </div>
        </form>
    </details>
    <?php endif; ?>

    <details class="rps-panel" id="regles">
        <summary class="rps-panel__head">
            <h2 class="rps-panel__title">Règles de comptage</h2>
            <span class="rps-panel__hint">Quand une présence compte-t-elle pour le suivi ? Ces règles s’appliquent aux nouvelles sessions.</span>
        </summary>
        <form method="post" action="<?= $h($base . '/reglages') ?>">
            <?= \App\Core\Csrf::field() ?>
            <div class="rps-panel__body rps-form rps-form--3">
                <label class="rps-field">
                    <span>Temps minimum en jeu (minutes)</span>
                    <input type="number" min="0" max="240" name="rp_session_min_minutes" value="<?= (int) ($cfg['min_minutes'] ?? 45) ?>">
                    <small>Une présence compte si le joueur a joué au moins ce temps…</small>
                </label>
                <label class="rps-field">
                    <span>… ou part de la session (%)</span>
                    <input type="number" min="0" max="100" name="rp_session_min_percent" value="<?= (int) ($cfg['min_percent'] ?? 50) ?>">
                    <small>… ou au moins ce pourcentage de la durée de la session.</small>
                </label>
                <label class="rps-field">
                    <span>Tolérance de retard (minutes)</span>
                    <input type="number" min="0" max="120" name="rp_session_late_tolerance" value="<?= (int) ($cfg['late_tolerance_minutes'] ?? 15) ?>">
                    <small>Retard accepté à l’arrivée, indiqué sur chaque session.</small>
                </label>
                <label class="rps-field">
                    <span>Coupure tolérée (minutes)</span>
                    <input type="number" min="5" max="60" name="rp_session_heartbeat_timeout" value="<?= (int) ($cfg['heartbeat_timeout_minutes'] ?? 15) ?>">
                    <small>Au-delà, une déconnexion interrompt le décompte jusqu’au retour du joueur.</small>
                </label>
                <label class="rps-field rps-field--wide">
                    <span>Catégories d’heures (une par ligne)</span>
                    <textarea name="rp_session_hour_categories" rows="4"><?= $h(implode("\n", array_map('strval', $cats))) ?></textarea>
                </label>
                <label class="rps-check rps-field--wide">
                    <input type="checkbox" name="rp_session_sync_enabled" value="1"<?= $syncOn ? ' checked' : '' ?>>
                    <span>Rattacher automatiquement le temps de jeu remonté par le mod ATAK à la session en cours.</span>
                </label>
            </div>
            <div class="rps-panel__foot">
                <button type="submit" class="ath-btn ath-btn--solid">Enregistrer les règles</button>
                <span class="rps-panel__hint">Voir aussi : <a href="<?= $h(url('back-office/roleplay-followup?onglet=phases')) ?>">Parcours RH</a> (heures demandées pour passer une étape).</span>
            </div>
        </form>
    </details>
</div>
<script>
(function () {
    // Les liens « Nouvelle session » / « Règles de comptage » ouvrent le panneau replié qu'ils visent.
    function openTarget() {
        var el = location.hash ? document.getElementById(location.hash.slice(1)) : null;
        if (el && el.tagName === 'DETAILS') { el.open = true; }
    }
    window.addEventListener('hashchange', openTarget);
    openTarget();
})();
</script>
