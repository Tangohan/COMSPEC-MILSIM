<?php
declare(strict_types=1);

/**
 * Fiche d’une session Arma (back-office/roleplay/sessions/{id}) : état et actions (démarrer, clôturer, annuler,
 * rouvrir), participants et temps retenu, ajout manuel, réglages de la session.
 *
 * @var array<string, mixed> $gameSession
 * @var list<array<string, mixed>> $sessionMembers
 * @var array<string, mixed> $sessionSettings
 * @var list<array<string, mixed>> $sessionEvents
 * @var array<string, mixed>|null $sessionLinkedEvent
 * @var list<array{id: int, label: string}> $sessionRoster
 * @var int $sessionLengthSeconds
 */

use App\Support\RoleplaySessionBoard as Board;

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$s = is_array($gameSession ?? null) ? $gameSession : [];
$members = is_array($sessionMembers ?? null) ? $sessionMembers : [];
$cfg = is_array($sessionSettings ?? null) ? $sessionSettings : [];
$cats = is_array($cfg['hour_categories'] ?? null) ? $cfg['hour_categories'] : [];
$events = is_array($sessionEvents ?? null) ? $sessionEvents : [];
$linkedEvent = is_array($sessionLinkedEvent ?? null) ? $sessionLinkedEvent : null;
$roster = is_array($sessionRoster ?? null) ? $sessionRoster : [];
$length = (int) ($sessionLengthSeconds ?? 0);
$id = (int) ($s['id'] ?? 0);
$base = url('back-office/roleplay/sessions');
$phase = Board::phase($s);
$kind = (string) ($s['session_kind'] ?? 'libre');
$cancelled = ($s['status'] ?? '') === 'cancelled';
$name = trim((string) ($s['mission_name'] ?? '')) ?: 'Session';

$kinds = [
    'officielle' => ['Opération officielle', 'Compte pour le suivi et le taux de présence.'],
    'entrainement' => ['Entraînement', 'Compte comme heures de formation.'],
    'libre' => ['Session libre', 'Le temps est noté, mais ne compte pas pour le suivi.'],
];
if (!in_array((string) ($s['hour_category'] ?? ''), $cats, true) && trim((string) ($s['hour_category'] ?? '')) !== '') {
    $cats[] = (string) $s['hour_category'];
}

$counted = 0;
$validatedTotal = 0;
foreach ($members as $m) {
    if (!empty($m['attendance_valid']) && empty($m['rh_excluded'])) {
        $counted++;
        $validatedTotal += (int) ($m['validated_seconds'] ?? 0);
    }
}

if ($cancelled) {
    $pill = '<span class="rps-pill rps-pill--bad">Annulée</span>';
    $stateText = 'Cette session a été annulée : rien n’y est compté.';
} elseif ($phase === Board::PAST) {
    $pill = '<span class="rps-pill">Terminée</span>';
    $stateText = 'Session clôturée : les temps sont figés. Vous pouvez encore retenir ou écarter une présence.';
} elseif ($phase === Board::LIVE) {
    $pill = Board::isStale($s) ? '<span class="rps-pill rps-pill--warn">À clôturer</span>' : '<span class="rps-pill rps-pill--live">En cours</span>';
    $stateText = 'Session démarrée : le temps de jeu remonté par le mod ATAK est rattaché à cette session. Clôturez-la en fin de soirée.';
} else {
    $pill = Board::isOverdue($s) ? '<span class="rps-pill rps-pill--warn">À démarrer</span>' : '<span class="rps-pill rps-pill--plan">Prévue</span>';
    $stateText = 'Session prévue : rien n’est encore compté. Démarrez-la quand la partie commence.';
}

$presence = static function (array $m): array {
    $checked = trim((string) ($m['checked_in_at'] ?? '')) !== '';
    $seen = trim((string) ($m['joined_at'] ?? '')) !== '' || (int) ($m['raw_seconds'] ?? 0) > 0;
    if (($m['attendance_source'] ?? '') === 'staff') {
        return ['Ajouté par un responsable', 'rps-pill--soft'];
    }
    if ($checked && $seen) {
        return ['Pointé et vu en jeu', 'rps-pill--ok'];
    }
    if ($seen) {
        return ['Vu en jeu, sans pointage', 'rps-pill--plan'];
    }
    if ($checked) {
        return ['Pointé, pas vu en jeu', 'rps-pill--warn'];
    }

    return ['Absent', 'rps-pill--soft'];
};
$countLabel = static function (array $m) use ($kind): array {
    if (!empty($m['rh_excluded'])) {
        return ['Écarté par un responsable', 'rps-pill--bad'];
    }
    if (!empty($m['attendance_valid'])) {
        if ($kind === 'libre') {
            return ['Présent (session libre)', 'rps-pill--soft'];
        }

        return [trim((string) ($m['staff_validated_at'] ?? '')) !== '' ? 'Retenu par un responsable' : 'Compte pour le suivi', 'rps-pill--ok'];
    }

    return ['Temps insuffisant', 'rps-pill--warn'];
};
$stateButton = static function (string $state, string $label, string $class = 'ath-btn', string $confirm = '') use ($h, $base, $id): string {
    return '<form method="post" action="' . $h($base . '/' . $id . '/etat') . '"' . ($confirm !== '' ? ' onsubmit="return confirm(this.dataset.confirm)" data-confirm="' . $h($confirm) . '"' : '') . '>'
        . \App\Core\Csrf::field() . '<input type="hidden" name="state" value="' . $h($state) . '">'
        . '<button type="submit" class="' . $h($class) . '">' . $h($label) . '</button></form>';
};
?>
<div class="rps">
    <a class="rps-back" href="<?= $h($base) ?>">← Toutes les sessions</a>

    <section class="rps-head ath-rise">
        <div class="rps-head__body">
            <h2><span class="sr-only"><?= $h($name) ?></span><?= $pill ?></h2>
            <p><?= $h($stateText) ?></p>
            <p>
                <?= $h(($kinds[$kind] ?? $kinds['libre'])[0]) ?> · <?= $h((string) ($s['hour_category'] ?? '')) ?>
                <?php if (trim((string) ($s['server_name'] ?? '')) !== ''): ?> · Serveur : <?= $h((string) $s['server_name']) ?><?php endif; ?>
                <?php if ($linkedEvent !== null): ?> · Événement : <?= $h((string) ($linkedEvent['title'] ?? '')) ?><?php endif; ?>
            </p>
        </div>
        <div class="rps-head__acts">
            <?php if ($phase === Board::UPCOMING): ?>
            <?= $stateButton('start', 'Démarrer maintenant', 'ath-btn ath-btn--solid') ?>
            <?= $stateButton('cancel', 'Annuler la session', 'ath-btn', 'Annuler cette session ? Rien n’y sera compté.') ?>
            <?php elseif ($phase === Board::LIVE): ?>
            <?= $stateButton('close', 'Clôturer la session', 'ath-btn ath-btn--solid', 'Clôturer la session ? Les temps seront figés.') ?>
            <?= $stateButton('cancel', 'Annuler la session', 'ath-btn', 'Annuler cette session ? Rien n’y sera compté.') ?>
            <?php else: ?>
            <?= $stateButton('reopen', 'Rouvrir la session', 'ath-btn', 'Rouvrir la session ? Le temps de jeu y sera de nouveau rattaché.') ?>
            <?php endif; ?>
            <a class="ath-btn" href="#modifier">Modifier</a>
        </div>
    </section>

    <div class="rps-facts">
        <div class="rps-fact">
            <span>Début</span>
            <?php if (trim((string) ($s['started_at'] ?? '')) !== ''): ?>
            <b><?= $h(Board::time((string) $s['started_at'])) ?></b><small><?= $h(Board::when((string) $s['started_at'])) ?></small>
            <?php elseif (trim((string) ($s['planned_starts_at'] ?? '')) !== ''): ?>
            <b><?= $h(Board::time((string) $s['planned_starts_at'])) ?></b><small>Prévu le <?= $h(Board::when((string) $s['planned_starts_at'])) ?></small>
            <?php else: ?>
            <b>—</b><small>Pas encore démarrée</small>
            <?php endif; ?>
        </div>
        <div class="rps-fact">
            <span>Fin</span>
            <?php if (trim((string) ($s['ended_at'] ?? '')) !== ''): ?>
            <b><?= $h(Board::time((string) $s['ended_at'])) ?></b><small>Durée : <?= $h(Board::duration($length)) ?></small>
            <?php elseif (trim((string) ($s['planned_ends_at'] ?? '')) !== ''): ?>
            <b><?= $h(Board::time((string) $s['planned_ends_at'])) ?></b><small>Fin prévue</small>
            <?php else: ?>
            <b>—</b><small><?= $phase === Board::LIVE ? 'En cours' : 'Non fixée' ?></small>
            <?php endif; ?>
        </div>
        <div class="rps-fact">
            <span>Participants</span>
            <b><?= count($members) ?></b><small>Comptés pour le suivi : <?= $counted ?></small>
        </div>
        <div class="rps-fact">
            <span>Temps retenu</span>
            <b><?= $h(Board::duration($validatedTotal)) ?></b><small>Toutes présences comptées</small>
        </div>
    </div>

    <section class="rps-panel" id="participants" data-ath-quiet-scope="session-participants-<?= (int) $id ?>">
        <div class="rps-panel__head">
            <h2 class="rps-panel__title">Participants</h2>
            <span class="rps-panel__hint">Une présence compte si le joueur a joué au moins <?= (int) ($s['min_minutes'] ?? 45) ?> min ou <?= (int) ($s['min_percent'] ?? 50) ?> % de la session. Vous pouvez retenir ou écarter une présence à la main.</span>
        </div>
        <?php if ($members === []): ?>
        <div class="rps-panel__body">
            <div class="rps-empty">
                <strong>Aucun participant pour l’instant</strong>
                <?php if ($phase === Board::LIVE): ?>
                <p>Les joueurs apparaissent ici dès que le mod ATAK, relié à leur compte, remonte leur temps de jeu (toutes les 5 minutes environ). Un joueur sans mod ? Ajoutez-le à la main ci-dessous.</p>
                <?php elseif ($phase === Board::UPCOMING): ?>
                <p>Démarrez la session au début de la soirée : les joueurs connectés avec le mod ATAK s’ajouteront automatiquement.</p>
                <?php else: ?>
                <p>Personne n’a été enregistré pendant cette session. Ajoutez les présents à la main si besoin.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <table class="rps-table">
            <thead>
                <tr>
                    <th>Membre</th>
                    <th>Présence</th>
                    <th>Temps en jeu</th>
                    <th>Temps retenu</th>
                    <th>Pour le suivi</th>
                    <th><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $m):
                    $who = trim((string) ($m['display_name'] ?? '')) ?: trim((string) ($m['callsign'] ?? ''));
                    [$pLabel, $pTone] = $presence($m);
                    [$cLabel, $cTone] = $countLabel($m);
                    $isCounted = !empty($m['attendance_valid']) && empty($m['rh_excluded']);
                ?>
                <tr>
                    <td class="rps-who-cell"><div class="rps-who">
                        <?php if ($who !== ''): ?>
                        <strong><?= $h($who) ?></strong>
                        <?php if (trim((string) ($m['callsign'] ?? '')) !== '' && trim((string) ($m['callsign'] ?? '')) !== $who): ?><small><?= $h((string) $m['callsign']) ?></small><?php endif; ?>
                        <?php else: ?>
                        <strong>Joueur non relié</strong><small>Son compte Steam n’est lié à aucun membre</small>
                        <?php endif; ?>
                    </div></td>
                    <td data-label="Présence"><span class="rps-pill <?= $h($pTone) ?>"><?= $h($pLabel) ?></span></td>
                    <td data-label="Temps en jeu" class="rps-num"><?= $h(Board::duration((int) ($m['raw_seconds'] ?? 0))) ?></td>
                    <td data-label="Temps retenu" class="rps-num"><?= $h(Board::duration(!empty($m['rh_excluded']) ? 0 : (int) ($m['validated_seconds'] ?? 0))) ?></td>
                    <td data-label="Pour le suivi"><span class="rps-pill <?= $h($cTone) ?>"><?= $h($cLabel) ?></span></td>
                    <td class="rps-acts">
                        <?php if ((int) ($m['user_id'] ?? 0) > 0): ?>
                        <form method="post" data-ath-quiet action="<?= $h($base . '/' . $id . '/membres/' . (int) $m['id']) ?>">
                            <?= \App\Core\Csrf::field() ?>
                            <?php if (!$isCounted): ?>
                            <button type="submit" name="member_action" value="validate" class="ath-btn">Retenir</button>
                            <?php endif; ?>
                            <?php if (empty($m['rh_excluded'])): ?>
                            <button type="submit" name="member_action" value="exclude" class="ath-btn">Écarter</button>
                            <?php endif; ?>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <?php if (!$cancelled): ?>
        <form method="post" data-ath-quiet action="<?= $h($base . '/' . $id . '/membres/0') ?>">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="member_action" value="add">
            <div class="rps-panel__foot rps-form rps-form--3" style="align-items:end;">
                <label class="rps-field">
                    <span>Ajouter un participant</span>
                    <select name="user_id" required>
                        <option value="">Choisir un membre…</option>
                        <?php foreach ($roster as $r): ?>
                        <option value="<?= (int) $r['id'] ?>"><?= $h((string) $r['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="rps-field">
                    <span>Temps joué (minutes)</span>
                    <input type="number" name="minutes" min="0" max="1440" value="<?= $length > 0 ? (int) round($length / 60) : 0 ?>">
                </label>
                <div><button type="submit" class="ath-btn ath-btn--solid">Ajouter</button></div>
            </div>
        </form>
        <?php endif; ?>
    </section>

    <details class="rps-panel" id="modifier">
        <summary class="rps-panel__head">
            <h2 class="rps-panel__title">Modifier la session</h2>
            <span class="rps-panel__hint">Nom, type, horaires, événement lié et seuils propres à cette session.</span>
        </summary>
        <form method="post" action="<?= $h($base . '/' . $id) ?>">
            <?= \App\Core\Csrf::field() ?>
            <div class="rps-panel__body rps-form">
                <label class="rps-field">
                    <span>Nom de la mission</span>
                    <input type="text" name="mission_name" maxlength="191" value="<?= $h((string) ($s['mission_name'] ?? '')) ?>" required>
                </label>
                <label class="rps-field">
                    <span>Catégorie d’heures</span>
                    <select name="hour_category">
                        <?php foreach ($cats as $cat): ?>
                        <option value="<?= $h((string) $cat) ?>"<?= (string) ($s['hour_category'] ?? '') === (string) $cat ? ' selected' : '' ?>><?= $h((string) $cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <fieldset class="rps-field rps-field--wide">
                    <legend>Type de session</legend>
                    <div class="rps-choices">
                        <?php foreach ($kinds as $k => [$label, $help]): ?>
                        <label class="rps-choice">
                            <input type="radio" name="session_kind" value="<?= $h($k) ?>"<?= $kind === $k ? ' checked' : '' ?>>
                            <span><b><?= $h($label) ?></b><small><?= $h($help) ?></small></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <label class="rps-field">
                    <span>Début prévu</span>
                    <input type="datetime-local" name="planned_starts_at" value="<?= $h(Board::inputValue((string) ($s['planned_starts_at'] ?? ''))) ?>">
                </label>
                <label class="rps-field">
                    <span>Fin prévue</span>
                    <input type="datetime-local" name="planned_ends_at" value="<?= $h(Board::inputValue((string) ($s['planned_ends_at'] ?? ''))) ?>">
                </label>
                <label class="rps-field">
                    <span>Événement du calendrier</span>
                    <select name="community_event_id">
                        <option value="0">Aucun</option>
                        <?php foreach ($events as $e): ?>
                        <option value="<?= (int) ($e['id'] ?? 0) ?>"<?= (int) ($s['community_event_id'] ?? 0) === (int) ($e['id'] ?? 0) ? ' selected' : '' ?>><?= $h((string) ($e['title'] ?? '')) ?> — <?= $h(Board::when((string) ($e['starts_at'] ?? ''))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="rps-field">
                    <span>Serveur</span>
                    <input type="text" name="server_name" maxlength="191" value="<?= $h((string) ($s['server_name'] ?? '')) ?>">
                </label>
                <label class="rps-field">
                    <span>Temps minimum en jeu (minutes)</span>
                    <input type="number" min="0" max="240" name="min_minutes" value="<?= (int) ($s['min_minutes'] ?? 45) ?>">
                </label>
                <label class="rps-field">
                    <span>… ou part de la session (%)</span>
                    <input type="number" min="0" max="100" name="min_percent" value="<?= (int) ($s['min_percent'] ?? 50) ?>">
                </label>
                <label class="rps-field">
                    <span>Tolérance de retard (minutes)</span>
                    <input type="number" min="0" max="120" name="late_tolerance_minutes" value="<?= (int) ($s['late_tolerance_minutes'] ?? 15) ?>">
                </label>
                <label class="rps-check">
                    <input type="checkbox" name="attendance_enabled" value="1"<?= !empty($s['attendance_enabled']) ? ' checked' : '' ?>>
                    <span>Demander aux joueurs de pointer leur présence</span>
                </label>
            </div>
            <div class="rps-panel__foot">
                <button type="submit" class="ath-btn ath-btn--solid">Enregistrer</button>
                <span class="rps-panel__hint">Les temps retenus des participants sont recalculés avec les nouveaux réglages.</span>
            </div>
        </form>
    </details>
</div>
<script>
(function () {
    function openTarget() {
        var el = location.hash ? document.getElementById(location.hash.slice(1)) : null;
        if (el && el.tagName === 'DETAILS') { el.open = true; }
    }
    window.addEventListener('hashchange', openTarget);
    openTarget();
})();
</script>
