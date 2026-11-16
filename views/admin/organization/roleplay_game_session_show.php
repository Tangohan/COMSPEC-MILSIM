<?php
declare(strict_types=1);

/**
 * Fiche d’une session Arma (back-office/roleplay/sessions/{id}) : bande d’en-tête (état, code de session en grand, chiffre
 * clé, actions), puis quatre sections : Informations, Code de session, Notifications en jeu, Membres et présence.
 * Formulaires en fenêtres (.bo-dialog, back-office-bands.js) ; #modifier ouvre la fenêtre de modification.
 *
 * @var array<string, mixed> $gameSession
 * @var list<array<string, mixed>> $sessionMembers
 * @var array<string, mixed> $sessionSettings
 * @var list<array<string, mixed>> $sessionEvents
 * @var array<string, mixed>|null $sessionLinkedEvent
 * @var list<array{id: int, label: string}> $sessionRoster
 * @var int $sessionLengthSeconds
 * @var array{ready: bool, state: string, code: ?string, formatted: ?string, expires_at: ?int, created_at: ?string, can_issue: bool} $sessionCode
 * @var string|null $sessionCodeQr
 * @var array{notify_enabled: bool, remind_15: bool, remind_5: bool, briefing: string, stored: bool}|null $sessionApp
 * @var list<array<string, mixed>> $sessionJournal
 * @var bool $sessionCanTestNotify
 */

use App\Core\Csrf;
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
$code = is_array($sessionCode ?? null) ? $sessionCode : ['ready' => false, 'state' => 'none', 'code' => null, 'formatted' => null, 'expires_at' => null, 'created_at' => null, 'can_issue' => false];
$qr = is_string($sessionCodeQr ?? null) && str_starts_with((string) $sessionCodeQr, 'data:image/') ? (string) $sessionCodeQr : null;
$app = is_array($sessionApp ?? null) ? $sessionApp : null;
$journal = is_array($sessionJournal ?? null) ? $sessionJournal : [];
$canTest = !empty($sessionCanTestNotify);
$id = (int) ($s['id'] ?? 0);
$base = url('back-office/roleplay/sessions');
$action = static fn (string $path): string => $base . '/' . $id . ($path !== '' ? '/' . $path : '');
$phase = Board::phase($s);
$kind = (string) ($s['session_kind'] ?? 'libre');
$cancelled = ($s['status'] ?? '') === 'cancelled';
$name = trim((string) ($s['mission_name'] ?? '')) ?: 'Session';
$codeActive = $code['state'] === 'active' && $code['formatted'] !== null;

$kinds = [
    'officielle' => ['Opération officielle', 'Compte pour le suivi et le taux de présence.'],
    'entrainement' => ['Entraînement', 'Compte comme heures de formation.'],
    'libre' => ['Session libre', 'Le temps est noté, mais ne compte pas pour le suivi.'],
];
if (!in_array((string) ($s['hour_category'] ?? ''), $cats, true) && trim((string) ($s['hour_category'] ?? '')) !== '') {
    $cats[] = (string) $s['hour_category'];
}

$counted = 0;
$checked = 0;
$validatedTotal = 0;
foreach ($members as $m) {
    if (trim((string) ($m['checked_in_at'] ?? '')) !== '') {
        $checked++;
    }
    if (!empty($m['attendance_valid']) && empty($m['rh_excluded'])) {
        $counted++;
        $validatedTotal += (int) ($m['validated_seconds'] ?? 0);
    }
}

if ($cancelled) {
    [$stateLabel, $accent] = ['Annulée', 'rose'];
    $stateText = 'Cette session a été annulée : rien n’y est compté.';
} elseif ($phase === Board::PAST) {
    [$stateLabel, $accent] = ['Terminée', 'violet'];
    $stateText = 'Session clôturée : les temps sont figés. Vous pouvez encore retenir ou écarter une présence.';
} elseif ($phase === Board::LIVE) {
    [$stateLabel, $accent] = Board::isStale($s) ? ['À clôturer', 'amber'] : ['En cours', 'emerald'];
    $stateText = 'Enregistrement en cours : le temps des joueurs pointés et celui remonté par le mod ATAK comptent pour cette session. Terminez-le en fin de soirée.';
} else {
    [$stateLabel, $accent] = Board::isOverdue($s) ? ['À démarrer', 'amber'] : ['Prévue', 'sky'];
    $stateText = 'Session prévue : rien n’est encore compté. Pointez les joueurs connectés, puis démarrez l’enregistrement quand la partie commence.';
}

$presence = static function (array $m): array {
    $checkedIn = trim((string) ($m['checked_in_at'] ?? '')) !== '';
    $seen = trim((string) ($m['joined_at'] ?? '')) !== '' || (int) ($m['raw_seconds'] ?? 0) > 0;
    if (($m['attendance_source'] ?? '') === 'staff') {
        return ['Ajouté par un responsable', 'rps-pill--soft'];
    }
    if ($checkedIn && $seen) {
        return ['Pointé et vu en jeu', 'rps-pill--ok'];
    }
    if ($seen) {
        return ['Vu en jeu, sans pointage', 'rps-pill--plan'];
    }
    if ($checkedIn) {
        return ['Pointé, pas vu en jeu', 'rps-pill--warn'];
    }
    if (($m['attendance_source'] ?? '') === 'code') {
        return ['Inscrit avec le code', 'rps-pill--plan'];
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
$stateButton = static function (string $state, string $label, bool $solid = false, string $confirm = '') use ($h, $action): string {
    return '<form method="post" action="' . $h($action('etat')) . '"' . ($confirm !== '' ? ' onsubmit="return confirm(this.dataset.confirm)" data-confirm="' . $h($confirm) . '"' : '') . '>'
        . Csrf::field() . '<input type="hidden" name="state" value="' . $h($state) . '">'
        . '<button type="submit" class="bo-band__btn' . ($solid ? ' bo-band__btn--solid' : '') . '">' . $h($label) . '</button></form>';
};
$dialogHead = static function (string $idTitle, string $title, string $sub) use ($h): string {
    return '<header class="bo-dialog__head"><div class="bo-dialog__titles"><h2 class="bo-dialog__title" id="' . $h($idTitle) . '">' . $h($title) . '</h2>'
        . '<p class="bo-dialog__sub">' . $h($sub) . '</p></div>'
        . '<button type="button" class="bo-dialog__close" data-bo-dialog-close aria-label="Fermer"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button></header>';
};
$fmt = static function (?string $raw): string {
    $t = $raw !== null && $raw !== '' ? strtotime($raw) : false;

    return $t === false ? '—' : date('d/m/Y à H:i', $t);
};
$journalLabels = [
    'code_issued' => 'Code émis',
    'code_regenerated' => 'Code régénéré',
    'code_revoked' => 'Code révoqué',
    'code_expired' => 'Code expiré',
    'join' => 'Joueur rattaché avec le code',
    'join_refused' => 'Code refusé',
    'checkin' => 'Présence pointée depuis le téléphone',
    'leave' => 'Départ annoncé depuis le téléphone',
    'notify_opened' => 'Notification : session prévue',
    'notify_reminder_15' => 'Rappel 15 min envoyé',
    'notify_reminder_5' => 'Rappel 5 min envoyé',
    'notify_started' => 'Notification : début',
    'notify_resumed' => 'Notification : reprise',
    'notify_changed' => 'Notification : horaire modifié',
    'notify_checkin_call' => 'Appel à pointer envoyé',
    'notify_ended' => 'Notification : fin',
    'notify_cancelled' => 'Notification : annulation',
    'notify_test' => 'Notification de test',
    'notify_failed' => 'Notification non envoyée',
];
$refusals = ['code_unknown' => 'code inconnu', 'code_expired' => 'code expiré', 'session_closed' => 'session terminée'];
$live = is_array($sessionLive ?? null) ? $sessionLive : ['players' => [], 'connected' => 0, 'server_time' => time()];
$players = is_array($live['players'] ?? null) ? $live['players'] : [];
$diagnostics = is_array($sessionDiagnostics ?? null) ? $sessionDiagnostics : [];
$connectedNotChecked = count(array_filter($players, static fn (array $p): bool => !empty($p['connected']) && !empty($p['linked_account']) && empty($p['checked_in'])));
$ago = static fn (int $s): string => \App\Services\Personnel\RoleplaySessionLiveRoster::ago($s);
$linkLabels = ['linked' => 'Liaison ATAK active', 'delayed' => 'Liaison ATAK lente', 'server_only' => 'Vu sur le serveur', 'offline' => 'Liaison coupée'];
$remindText = $app === null ? '—' : (($app['remind_15'] && $app['remind_5']) ? '15 min et 5 min avant' : ($app['remind_15'] ? '15 min avant' : ($app['remind_5'] ? '5 min avant' : 'Aucun rappel')));
?>
<div class="rps rpsx bo-page">
    <header class="bo-band bo-band--hero bo-band--<?= $h($accent) ?> rpsx-hero" aria-labelledby="ath-page-title">
        <div class="bo-band__media bo-band__media--fallback" aria-hidden="true"></div>
        <div class="bo-band__glow" aria-hidden="true"></div>
        <div class="bo-band__veil" aria-hidden="true"></div>
        <div class="bo-band__body">
            <div class="bo-band__who">
                <a class="rpsx-back" href="<?= $h($base) ?>">← Toutes les sessions</a>
                <h1 class="bo-band__title" id="ath-page-title"><?= $h($name) ?></h1>
                <p class="bo-band__line"><?= $h($stateText) ?></p>
                <p class="bo-band__meta"><span><?= $h($stateLabel) ?></span> <?= $h(($kinds[$kind] ?? $kinds['libre'])[0]) ?> · <?= $h((string) ($s['hour_category'] ?? '')) ?></p>
                <div class="bo-band__actions">
                    <?php if ($phase === Board::UPCOMING): ?>
                    <button type="button" class="bo-band__btn bo-band__btn--solid" data-bo-dialog-open="rpsx-rec-start">Démarrer l’enregistrement</button>
                    <?= $stateButton('cancel', 'Annuler la session', false, 'Annuler cette session ? Rien n’y sera compté.') ?>
                    <?php elseif ($phase === Board::LIVE): ?>
                    <button type="button" class="bo-band__btn bo-band__btn--solid" data-bo-dialog-open="rpsx-rec-stop">Terminer l’enregistrement</button>
                    <?= $stateButton('cancel', 'Annuler la session', false, 'Annuler cette session ? Rien n’y sera compté.') ?>
                    <?php else: ?>
                    <?= $stateButton('reopen', 'Rouvrir la session', false, 'Rouvrir la session ? Le temps de jeu y sera de nouveau rattaché.') ?>
                    <?php endif; ?>
                    <button type="button" class="bo-band__btn" data-bo-dialog-open="modifier-dialog">Modifier</button>
                </div>
            </div>
            <div class="bo-band__side rpsx-hero__side">
                <?php if ($codeActive): ?>
                <a class="bo-figure is-hot rpsx-hero__code" href="#code"><strong class="rpsx-code"><?= $h((string) $code['formatted']) ?></strong><span>Code de session à annoncer</span></a>
                <?php else: ?>
                <a class="bo-figure rpsx-hero__code" href="#code"><strong class="rpsx-code rpsx-code--off">—</strong><span><?= $phase === Board::PAST ? 'Session terminée : plus de code' : 'Aucun code actif' ?></span></a>
                <?php endif; ?>
                <div class="bo-figure"><strong><?= $checked ?><small>/<?= count($members) ?></small></strong><span>Pointés sur les participants</span></div>
            </div>
            <div class="bo-band__foot">
                <dl class="bo-facts">
                    <div><dt>Début</dt><dd>
                        <?php if (trim((string) ($s['started_at'] ?? '')) !== ''): ?><?= $h(Board::when((string) $s['started_at'])) ?>
                        <?php elseif (trim((string) ($s['planned_starts_at'] ?? '')) !== ''): ?>Prévu le <?= $h(Board::when((string) $s['planned_starts_at'])) ?>
                        <?php else: ?>Pas encore démarrée<?php endif; ?>
                    </dd></div>
                    <div><dt>Fin</dt><dd>
                        <?php if (trim((string) ($s['ended_at'] ?? '')) !== ''): ?><?= $h(Board::time((string) $s['ended_at'])) ?> · <?= $h(Board::duration($length)) ?>
                        <?php elseif (trim((string) ($s['planned_ends_at'] ?? '')) !== ''): ?>Prévue à <?= $h(Board::time((string) $s['planned_ends_at'])) ?>
                        <?php else: ?><?= $phase === Board::LIVE ? 'En cours' : 'Non fixée' ?><?php endif; ?>
                    </dd></div>
                    <div><dt>Comptés pour le suivi</dt><dd><?= $counted ?> sur <?= count($members) ?></dd></div>
                    <div><dt>Temps retenu</dt><dd><?= $h(Board::duration($validatedTotal)) ?></dd></div>
                </dl>
            </div>
        </div>
    </header>

    <!-- 01 Informations -->
    <section class="bo-section" id="informations" aria-labelledby="rpsx-info-title">
        <div class="bo-section__head">
            <span class="bo-section__index" aria-hidden="true">/ 01</span>
            <h2 class="bo-section__title" id="rpsx-info-title">Informations</h2>
            <div class="bo-section__aside"><button type="button" class="ath-btn rpsx-btn" data-bo-dialog-open="modifier-dialog">Modifier la session</button></div>
            <p class="bo-section__desc">Type, horaires, événement lié et seuils qui décident si une présence compte.</p>
        </div>
        <dl class="rpsx-info">
            <div><dt>Type</dt><dd><?= $h(($kinds[$kind] ?? $kinds['libre'])[0]) ?></dd></div>
            <div><dt>Catégorie d’heures</dt><dd><?= $h((string) ($s['hour_category'] ?? '') ?: '—') ?></dd></div>
            <div><dt>Début prévu</dt><dd><?= trim((string) ($s['planned_starts_at'] ?? '')) !== '' ? $h(Board::when((string) $s['planned_starts_at'])) : 'Non fixé' ?></dd></div>
            <div><dt>Fin prévue</dt><dd><?= trim((string) ($s['planned_ends_at'] ?? '')) !== '' ? $h(Board::when((string) $s['planned_ends_at'])) : 'Non fixée' ?></dd></div>
            <div><dt>Événement du calendrier</dt><dd><?= $linkedEvent !== null ? $h((string) ($linkedEvent['title'] ?? '')) : 'Aucun' ?></dd></div>
            <div><dt>Serveur</dt><dd><?= trim((string) ($s['server_name'] ?? '')) !== '' ? $h((string) $s['server_name']) : '—' ?></dd></div>
            <div><dt>Présence retenue à partir de</dt><dd><?= (int) ($s['min_minutes'] ?? 45) ?> min ou <?= (int) ($s['min_percent'] ?? 50) ?> % de la session</dd></div>
            <div><dt>Pointage des joueurs</dt><dd><?= !empty($s['attendance_enabled']) ? 'Demandé' : 'Facultatif' ?></dd></div>
        </dl>
    </section>

    <!-- 02 Code de session -->
    <section class="bo-section" id="code" aria-labelledby="rpsx-code-title">
        <div class="bo-section__head">
            <span class="bo-section__index" aria-hidden="true">/ 02</span>
            <h2 class="bo-section__title" id="rpsx-code-title">Code de session</h2>
            <p class="bo-section__desc">Annoncez-le en vocal ou à l’écran : les joueurs le saisissent dans l’app Session de leur téléphone pour rejoindre la session.</p>
        </div>
        <?php if (!$code['ready']): ?>
        <div class="bo-band bo-band--empty bo-band--sky">
            <div class="bo-band__media bo-band__media--fallback" aria-hidden="true"></div>
            <div class="bo-band__veil" aria-hidden="true"></div>
            <div class="bo-band__body"><div class="bo-band__who">
                <p class="bo-band__title">Bientôt disponible</p>
                <p class="bo-band__line">Le code de session sera disponible après la prochaine mise à jour de la plateforme.</p>
            </div></div>
        </div>
        <?php else: ?>
        <div class="bo-band bo-band--compact bo-band--<?= $codeActive ? 'sky' : 'violet' ?> rpsx-codeband">
            <div class="bo-band__media bo-band__media--fallback" aria-hidden="true"></div>
            <div class="bo-band__glow" aria-hidden="true"></div>
            <div class="bo-band__veil" aria-hidden="true"></div>
            <div class="bo-band__body">
                <div class="bo-band__who">
                    <?php if ($codeActive): ?>
                    <p class="bo-band__title rpsx-code rpsx-code--band" id="rpsx-code-value" data-rpsx-code="<?= $h((string) $code['formatted']) ?>"><?= $h((string) $code['formatted']) ?></p>
                    <p class="bo-band__line">Actif jusqu’au <?= $h(date('d/m/Y à H:i', (int) $code['expires_at'])) ?>, puis il expire tout seul. Il ne sert qu’aux membres de votre communauté.</p>
                    <?php elseif ($code['state'] === 'revoked'): ?>
                    <p class="bo-band__title">Code révoqué</p>
                    <p class="bo-band__line">Plus personne ne peut rejoindre la session avec l’ancien code. Émettez-en un nouveau pour l’annoncer.</p>
                    <?php elseif (!$code['can_issue']): ?>
                    <p class="bo-band__title">Plus de code</p>
                    <p class="bo-band__line">La session est terminée : son code ne permet plus de la rejoindre.</p>
                    <?php else: ?>
                    <p class="bo-band__title">Aucun code actif</p>
                    <p class="bo-band__line">Émettez un code pour que les joueurs puissent rejoindre la session depuis leur téléphone.</p>
                    <?php endif; ?>
                </div>
                <div class="bo-band__side">
                    <div class="bo-band__actions">
                        <?php if ($codeActive): ?>
                        <button type="button" class="bo-band__btn bo-band__btn--solid" data-rpsx-copy="<?= $h((string) $code['formatted']) ?>">Copier le code</button>
                        <button type="button" class="bo-band__btn" data-bo-dialog-open="rpsx-big">Afficher en grand</button>
                        <?php endif; ?>
                        <?php if ($code['can_issue']): ?>
                        <button type="button" class="bo-band__btn" data-bo-dialog-open="rpsx-regen"><?= $codeActive ? 'Régénérer' : 'Émettre un code' ?></button>
                        <?php endif; ?>
                        <?php if ($codeActive): ?>
                        <button type="button" class="bo-band__btn bo-band__btn--warn" data-bo-dialog-open="rpsx-revoke">Révoquer</button>
                        <?php endif; ?>
                    </div>
                    <p class="bo-band__note rpsx-copied" role="status" aria-live="polite"></p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </section>

    <!-- 03 Notifications en jeu -->
    <section class="bo-section" id="notifications" aria-labelledby="rpsx-notif-title">
        <div class="bo-section__head">
            <span class="bo-section__index" aria-hidden="true">/ 03</span>
            <h2 class="bo-section__title" id="rpsx-notif-title">Notifications en jeu</h2>
            <?php if ($app !== null): ?>
            <div class="bo-section__aside"><button type="button" class="ath-btn rpsx-btn" data-bo-dialog-open="rpsx-notify">Régler</button></div>
            <?php endif; ?>
            <p class="bo-section__desc">Les téléphones de la communauté reçoivent l’annonce, les rappels, le début et la fin de la session, dans leur boîte de notifications.</p>
        </div>
        <?php if ($app === null): ?>
        <p class="rpsx-empty">Les notifications en jeu seront disponibles après la prochaine mise à jour de la plateforme.</p>
        <?php else: ?>
        <dl class="rpsx-info">
            <div><dt>Envoi aux téléphones</dt><dd><?= $app['notify_enabled'] ? 'Activé' : 'Coupé pour cette session' ?></dd></div>
            <div><dt>Rappels avant le début</dt><dd><?= $h($remindText) ?></dd></div>
            <div class="rpsx-info__wide"><dt>Consignes affichées dans l’app</dt><dd><?= $app['briefing'] !== '' ? $h($app['briefing']) : 'Aucune consigne' ?></dd></div>
        </dl>
        <div class="rpsx-actions">
            <form method="post" action="<?= $h($action('notifications/test')) ?>">
                <?= Csrf::field() ?>
                <button type="submit" class="ath-btn rpsx-btn"<?= $canTest ? '' : ' disabled aria-describedby="rpsx-test-help"' ?>>Envoyer une notification de test</button>
            </form>
            <?php if ($phase === Board::LIVE): ?>
            <form method="post" action="<?= $h($action('notifications/pointage')) ?>" onsubmit="return confirm(this.dataset.confirm)" data-confirm="Envoyer l’appel à pointer à tous les téléphones de la communauté ?">
                <?= Csrf::field() ?>
                <button type="submit" class="ath-btn ath-btn--solid rpsx-btn"<?= $app['notify_enabled'] ? '' : ' disabled' ?>>Appeler à pointer</button>
            </form>
            <?php endif; ?>
            <?php if (!$canTest): ?>
            <p class="rpsx-help" id="rpsx-test-help">La notification de test arrive sur votre propre téléphone : liez d’abord votre compte Steam à Athena.</p>
            <?php endif; ?>
        </div>
        <?php if ($journal !== []): ?>
        <details class="rpsx-journal">
            <summary>Journal de la session (<?= count($journal) ?> dernières entrées)</summary>
            <ul>
                <?php foreach ($journal as $row):
                    $ev = (string) ($row['event'] ?? '');
                    $who = trim((string) ($row['callsign'] ?? '')) ?: trim((string) ($row['display_name'] ?? ''));
                    $detail = (string) ($row['detail'] ?? '');
                    if ($ev === 'join_refused') {
                        $detail = $refusals[$detail] ?? '';
                    } elseif (str_starts_with($ev, 'notify_')) {
                        $detail = '';
                    }
                ?>
                <li>
                    <time datetime="<?= $h((string) ($row['created_at'] ?? '')) ?>"><?= $h($fmt((string) ($row['created_at'] ?? ''))) ?></time>
                    <span><?= $h($journalLabels[$ev] ?? $ev) ?><?= $detail !== '' ? ' · ' . $h($detail) : '' ?><?= $who !== '' ? ' · ' . $h($who) : '' ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </details>
        <?php endif; ?>
        <?php endif; ?>
    </section>

    <!-- 04 Membres et présence -->
    <section class="bo-section" id="participants" aria-labelledby="rpsx-members-title" data-ath-quiet-scope="session-participants-<?= (int) $id ?>">
        <div class="bo-section__head">
            <span class="bo-section__index" aria-hidden="true">/ 04</span>
            <h2 class="bo-section__title" id="rpsx-members-title">Membres et présence</h2>
            <div class="bo-section__aside">
                <span class="bo-chip">Participants <b><?= count($members) ?></b></span>
                <span class="bo-chip">Pointés <b><?= $checked ?></b></span>
                <?php if (!$cancelled): ?>
                <button type="button" class="ath-btn rpsx-btn" data-bo-dialog-open="rpsx-add">Ajouter un participant</button>
                <?php endif; ?>
            </div>
            <p class="bo-section__desc">Une présence compte à partir de <?= (int) ($s['min_minutes'] ?? 45) ?> min de jeu ou <?= (int) ($s['min_percent'] ?? 50) ?> % de la session ; vous pouvez la retenir ou l’écarter à la main.</p>
        </div>

        <div class="rpsx-live" id="connectes" data-rpsx-live="<?= $h($action('connectes')) ?>" data-rpsx-can-point="<?= $phase === Board::PAST ? '0' : '1' ?>">
            <div class="rpsx-live__head">
                <h3 class="rpsx-live__title">Joueurs ATAK connectés</h3>
                <span class="bo-chip"><b data-rpsx-live-count><?= (int) ($live['connected'] ?? 0) ?></b> connectés</span>
                <span class="rpsx-live__stamp" data-rpsx-live-stamp role="status" aria-live="polite">En direct, mis à jour toutes les 15 s</span>
                <button type="button" class="ath-btn rpsx-btn rpsx-live__help" data-bo-dialog-open="rpsx-why">Pourquoi je ne le vois pas ?</button>
            </div>
            <form method="post" action="<?= $h($action('pointage')) ?>" class="rpsx-live__form">
                <?= Csrf::field() ?>
                <ul class="rpsx-live__list" data-rpsx-live-list>
                    <?php foreach ($players as $p):
                        $uid = (int) ($p['user_id'] ?? 0);
                        $sub = trim(implode(' · ', array_filter([(string) ($p['callsign'] ?? ''), (string) ($p['unit'] ?? '')])));
                        $where = trim(implode(' · ', array_filter([(string) ($p['server'] ?? ''), (string) ($p['mission'] ?? '')])));
                    ?>
                    <li class="rpsx-player <?= !empty($p['connected']) ? 'is-on' : 'is-idle' ?>" data-age="<?= (int) ($p['age_s'] ?? 0) ?>">
                        <span class="rpsx-player__pick">
                            <?php if ($uid > 0 && empty($p['checked_in']) && $phase !== Board::PAST): ?>
                            <input type="checkbox" name="user_ids[]" value="<?= $uid ?>" id="rpsx-pick-<?= $h((string) $p['key']) ?>" aria-label="Sélectionner <?= $h((string) $p['label']) ?>">
                            <?php endif; ?>
                        </span>
                        <span class="rpsx-player__who"><strong><?= $h((string) $p['label']) ?></strong><?php if ($sub !== ''): ?><small><?= $h($sub) ?></small><?php endif; ?></span>
                        <span class="rpsx-player__where"><?= $where !== '' ? $h($where) : 'Serveur inconnu' ?><?php if (($p['server'] ?? '') !== '' && empty($p['server_known'])): ?> <em>(non déclaré)</em><?php endif; ?></span>
                        <span class="rpsx-player__seen"><span class="rpsx-dot" aria-hidden="true"></span><?= !empty($p['connected']) ? 'Connecté' : 'Inactif' ?> · <span data-rpsx-ago>il y a <?= $h($ago((int) ($p['age_s'] ?? 0))) ?></span><small><?= $h($linkLabels[(string) ($p['link'] ?? '')] ?? '') ?></small></span>
                        <span class="rpsx-player__act">
                            <?php if (!empty($p['checked_in'])): ?>
                            <span class="rps-pill rps-pill--ok">Pointé</span>
                            <?php if ($phase !== Board::PAST): ?><button type="submit" class="ath-btn rpsx-btn" name="row" value="remove:<?= $uid ?>">Retirer</button><?php endif; ?>
                            <?php elseif ($uid < 1): ?>
                            <span class="rps-pill rps-pill--warn">Compte non lié</span>
                            <?php elseif ($phase !== Board::PAST): ?>
                            <button type="submit" class="ath-btn rpsx-btn" name="row" value="point:<?= $uid ?>">Pointer</button>
                            <?php endif; ?>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <p class="rpsx-empty" data-rpsx-live-empty<?= $players !== [] ? ' hidden' : '' ?>>Aucun joueur ATAK vu depuis 30 minutes. Les joueurs apparaissent dès que leur téléphone envoie un signe de vie.</p>
                <?php if ($phase !== Board::PAST): ?>
                <div class="rpsx-actions">
                    <button type="submit" class="ath-btn rpsx-btn" name="mode" value="point">Pointer les sélectionnés</button>
                    <button type="submit" class="ath-btn ath-btn--solid rpsx-btn" name="mode" value="all">Pointer tous les connectés</button>
                </div>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($members === []): ?>
        <div class="rps-empty rpsx-empty">
            <strong>Aucun participant pour l’instant</strong>
            <?php if ($phase === Board::LIVE): ?>
            <p>Les joueurs apparaissent ici dès qu’ils saisissent le code de session ou que le mod ATAK, relié à leur compte, remonte leur temps de jeu. Un joueur sans mod ? Ajoutez-le à la main.</p>
            <?php elseif ($phase === Board::UPCOMING): ?>
            <p>Les joueurs qui saisissent le code s’inscrivent dès maintenant ; les autres s’ajoutent quand le mod remonte leur temps de jeu, une fois la session démarrée.</p>
            <?php else: ?>
            <p>Personne n’a été enregistré pendant cette session. Ajoutez les présents à la main si besoin.</p>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="rpsx-table-wrap">
        <table class="rps-table">
            <thead>
                <tr>
                    <th scope="col">Membre</th>
                    <th scope="col">Présence</th>
                    <th scope="col">Temps en jeu</th>
                    <th scope="col">Temps retenu</th>
                    <th scope="col">Pour le suivi</th>
                    <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $m):
                    $whoId = (int) ($m['user_id'] ?? 0);
                    // Joueur relié à un membre : règle unique ; sinon « Joueur non relié » plus bas.
                    $who = $whoId > 0
                        ? member_label(['id' => $whoId, 'display_name' => (string) ($m['display_name'] ?? ''), 'callsign' => (string) ($m['callsign'] ?? '')], 'list')
                        : (trim((string) ($m['display_name'] ?? '')) ?: trim((string) ($m['callsign'] ?? '')));
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
                        <form method="post" data-ath-quiet action="<?= $h($action('membres/' . (int) $m['id'])) ?>">
                            <?= Csrf::field() ?>
                            <?php if (!$isCounted): ?>
                            <button type="submit" name="member_action" value="validate" class="ath-btn rpsx-btn">Retenir</button>
                            <?php endif; ?>
                            <?php if (empty($m['rh_excluded'])): ?>
                            <button type="submit" name="member_action" value="exclude" class="ath-btn rpsx-btn">Écarter</button>
                            <?php endif; ?>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </section>

    <!-- Fenêtres -->
    <dialog class="bo-dialog rpsx-dialog--wide" id="modifier-dialog" aria-labelledby="modifier-dialog-title">
        <form class="bo-dialog__form" method="post" action="<?= $h($action('')) ?>">
            <?= Csrf::field() ?>
            <?= $dialogHead('modifier-dialog-title', 'Modifier la session', 'Nom, type, horaires, événement lié et seuils propres à cette session.') ?>
            <div class="bo-dialog__body rps-form">
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
                <label class="rps-check rps-field--wide">
                    <input type="checkbox" name="attendance_enabled" value="1"<?= !empty($s['attendance_enabled']) ? ' checked' : '' ?>>
                    <span>Demander aux joueurs de pointer leur présence</span>
                </label>
                <p class="bo-dialog__help rps-field--wide">Les temps retenus des participants sont recalculés avec les nouveaux réglages. Un horaire modifié est annoncé aux téléphones.</p>
            </div>
            <footer class="bo-dialog__foot">
                <button type="button" class="bo-dialog__btn" data-bo-dialog-close>Annuler</button>
                <button type="submit" class="bo-dialog__btn bo-dialog__btn--primary">Enregistrer</button>
            </footer>
        </form>
    </dialog>

    <?php if ($codeActive): ?>
    <dialog class="bo-dialog rpsx-dialog--big" id="rpsx-big" aria-labelledby="rpsx-big-title">
        <div class="bo-dialog__form">
            <?= $dialogHead('rpsx-big-title', $name, 'Code de session : saisissez-le dans l’app Session du téléphone.') ?>
            <div class="bo-dialog__body rpsx-big">
                <p class="rpsx-big__code" aria-label="<?= $h(implode(' ', str_split(str_replace('-', '', (string) $code['formatted'])))) ?>"><?= $h((string) $code['formatted']) ?></p>
                <?php if ($qr !== null): ?>
                <img class="rpsx-big__qr" src="<?= $h($qr) ?>" width="200" height="200" alt="QR code du code de session <?= $h((string) $code['formatted']) ?>">
                <?php endif; ?>
                <p class="bo-dialog__help">Actif jusqu’au <?= $h(date('d/m/Y à H:i', (int) $code['expires_at'])) ?>.</p>
            </div>
            <footer class="bo-dialog__foot">
                <button type="button" class="bo-dialog__btn bo-dialog__btn--primary" data-bo-dialog-close>Fermer</button>
            </footer>
        </div>
    </dialog>
    <dialog class="bo-dialog" id="rpsx-revoke" aria-labelledby="rpsx-revoke-title">
        <form class="bo-dialog__form" method="post" action="<?= $h($action('code/revoquer')) ?>">
            <?= Csrf::field() ?>
            <?= $dialogHead('rpsx-revoke-title', 'Révoquer le code ?', 'Le code ' . (string) $code['formatted'] . ' ne permettra plus de rejoindre la session.') ?>
            <div class="bo-dialog__body">
                <p class="bo-dialog__help">Les joueurs déjà rattachés restent dans la session. L’action est inscrite au journal de la session.</p>
            </div>
            <footer class="bo-dialog__foot">
                <button type="button" class="bo-dialog__btn" data-bo-dialog-close>Garder le code</button>
                <button type="submit" class="bo-dialog__btn bo-dialog__btn--danger">Révoquer</button>
            </footer>
        </form>
    </dialog>
    <?php endif; ?>
    <?php if ($code['ready'] && $code['can_issue']): ?>
    <dialog class="bo-dialog" id="rpsx-regen" aria-labelledby="rpsx-regen-title">
        <form class="bo-dialog__form" method="post" action="<?= $h($action('code/regenerer')) ?>">
            <?= Csrf::field() ?>
            <?= $dialogHead('rpsx-regen-title', $codeActive ? 'Régénérer le code ?' : 'Émettre un code ?', $codeActive ? 'Un nouveau code remplace ' . (string) $code['formatted'] . ', qui ne fonctionnera plus.' : 'Un nouveau code est émis pour cette session.') ?>
            <div class="bo-dialog__body">
                <p class="bo-dialog__help">Annoncez le nouveau code aux joueurs. Ceux qui ont déjà rejoint la session n’ont rien à refaire. L’action est inscrite au journal.</p>
            </div>
            <footer class="bo-dialog__foot">
                <button type="button" class="bo-dialog__btn" data-bo-dialog-close>Annuler</button>
                <button type="submit" class="bo-dialog__btn bo-dialog__btn--primary"><?= $codeActive ? 'Régénérer' : 'Émettre' ?></button>
            </footer>
        </form>
    </dialog>
    <?php endif; ?>

    <?php if ($app !== null): ?>
    <dialog class="bo-dialog" id="rpsx-notify" aria-labelledby="rpsx-notify-title">
        <form class="bo-dialog__form" method="post" action="<?= $h($action('notifications')) ?>">
            <?= Csrf::field() ?>
            <?= $dialogHead('rpsx-notify-title', 'Notifications en jeu', 'Ce que reçoivent les téléphones de la communauté pour cette session.') ?>
            <div class="bo-dialog__body">
                <label class="rps-check rpsx-check"><input type="checkbox" name="notify_enabled" value="1"<?= $app['notify_enabled'] ? ' checked' : '' ?>><span>Envoyer les notifications de la session aux téléphones (annonce, début, fin, horaire modifié)</span></label>
                <label class="rps-check rpsx-check"><input type="checkbox" name="remind_15" value="1"<?= $app['remind_15'] ? ' checked' : '' ?>><span>Rappel 15 minutes avant le début prévu</span></label>
                <label class="rps-check rpsx-check"><input type="checkbox" name="remind_5" value="1"<?= $app['remind_5'] ? ' checked' : '' ?>><span>Rappel 5 minutes avant le début prévu</span></label>
                <label class="bo-dialog__label" for="rpsx-briefing">Consignes affichées dans l’app Session</label>
                <textarea id="rpsx-briefing" name="briefing" maxlength="400" rows="4" aria-describedby="rpsx-briefing-help"><?= $h($app['briefing']) ?></textarea>
                <p class="bo-dialog__help" id="rpsx-briefing-help">400 caractères au plus. Par exemple : tenue, fréquence radio, point de rendez-vous.</p>
            </div>
            <footer class="bo-dialog__foot">
                <button type="button" class="bo-dialog__btn" data-bo-dialog-close>Annuler</button>
                <button type="submit" class="bo-dialog__btn bo-dialog__btn--primary">Enregistrer</button>
            </footer>
        </form>
    </dialog>
    <?php endif; ?>

    <?php if ($phase === Board::UPCOMING): ?>
    <dialog class="bo-dialog" id="rpsx-rec-start" aria-labelledby="rpsx-rec-start-title">
        <form class="bo-dialog__form" method="post" action="<?= $h($action('enregistrement')) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="start">
            <?= $dialogHead('rpsx-rec-start-title', 'Démarrer l’enregistrement ?', 'La session démarre : le temps de jeu des joueurs pointés commence à compter.') ?>
            <div class="bo-dialog__body">
                <dl class="rpsx-recap">
                    <div><dt>Joueurs pointés</dt><dd><?= $checked ?></dd></div>
                    <div><dt>Connectés pas encore pointés</dt><dd><?= $connectedNotChecked ?></dd></div>
                    <div><dt>Notification aux téléphones</dt><dd><?= $app !== null && $app['notify_enabled'] ? 'Oui, « la session a commencé »' : 'Non' ?></dd></div>
                </dl>
                <p class="bo-dialog__help">Vous pourrez encore pointer des joueurs pendant la session : leur temps court dès leur pointage.</p>
            </div>
            <footer class="bo-dialog__foot">
                <button type="button" class="bo-dialog__btn" data-bo-dialog-close>Annuler</button>
                <button type="submit" class="bo-dialog__btn bo-dialog__btn--primary">Démarrer</button>
            </footer>
        </form>
    </dialog>
    <?php elseif ($phase === Board::LIVE): ?>
    <dialog class="bo-dialog" id="rpsx-rec-stop" aria-labelledby="rpsx-rec-stop-title">
        <form class="bo-dialog__form" method="post" action="<?= $h($action('enregistrement')) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="stop">
            <?= $dialogHead('rpsx-rec-stop-title', 'Terminer l’enregistrement ?', 'La session est clôturée : les temps sont figés et comptent pour le suivi.') ?>
            <div class="bo-dialog__body">
                <dl class="rpsx-recap">
                    <div><dt>Participants</dt><dd><?= count($members) ?></dd></div>
                    <div><dt>Joueurs pointés</dt><dd><?= $checked ?></dd></div>
                    <div><dt>Notification aux téléphones</dt><dd><?= $app !== null && $app['notify_enabled'] ? 'Oui, « la session est terminée »' : 'Non' ?></dd></div>
                </dl>
                <p class="bo-dialog__help">Vous pourrez rouvrir la session si besoin.</p>
            </div>
            <footer class="bo-dialog__foot">
                <button type="button" class="bo-dialog__btn" data-bo-dialog-close>Continuer</button>
                <button type="submit" class="bo-dialog__btn bo-dialog__btn--primary">Terminer</button>
            </footer>
        </form>
    </dialog>
    <?php endif; ?>

    <dialog class="bo-dialog rpsx-dialog--wide" id="rpsx-why" aria-labelledby="rpsx-why-title">
        <div class="bo-dialog__form">
            <?= $dialogHead('rpsx-why-title', 'Pourquoi je ne le vois pas ?', 'Un joueur en jeu absent de la liste : les causes les plus fréquentes, puis ce que le site a reçu ces deux dernières heures.') ?>
            <div class="bo-dialog__body">
                <ul class="rpsx-why">
                    <li><strong>Compte non lié</strong><span>Son compte Steam n’est relié à aucun membre : il doit lier Steam à Athena (Mon compte) ou relier le jeu avec le code de liaison.</span></li>
                    <li><strong>Serveur non reconnu</strong><span>Il joue sur un serveur que la communauté n’a pas déclaré (Gestion › Serveurs de jeu) : il apparaît, mais « non déclaré ».</span></li>
                    <li><strong>Mod trop ancien</strong><span>Sans le mod COMSPEC ATAK à jour, le téléphone n’envoie ni sa liaison ni sa présence : demandez-lui de mettre le mod à jour.</span></li>
                    <li><strong>Pas de signe de vie</strong><span>Téléphone éteint ou liaison coupée : il redevient « connecté » dans les 3 minutes après avoir rouvert le canal en jeu (le serveur, lui, est signalé toutes les 10 minutes).</span></li>
                </ul>
                <h3 class="rpsx-why__title">Ce que le site a reçu</h3>
                <?php if ($diagnostics === []): ?>
                <p class="bo-dialog__help">Aucune anomalie dans les signes de vie des deux dernières heures.</p>
                <?php else: ?>
                <ul class="rpsx-why rpsx-why--found">
                    <?php foreach ($diagnostics as $d): ?>
                    <li><strong><?= $h((string) $d['label']) ?></strong><span><?= $h((string) $d['detail']) ?></span></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
            <footer class="bo-dialog__foot">
                <button type="button" class="bo-dialog__btn bo-dialog__btn--primary" data-bo-dialog-close>Fermer</button>
            </footer>
        </div>
    </dialog>

    <?php if (!$cancelled): ?>
    <dialog class="bo-dialog" id="rpsx-add" aria-labelledby="rpsx-add-title">
        <form class="bo-dialog__form" method="post" data-ath-quiet action="<?= $h($action('membres/0')) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="member_action" value="add">
            <?= $dialogHead('rpsx-add-title', 'Ajouter un participant', 'Un membre présent que ni le code ni le mod n’ont rattaché.') ?>
            <div class="bo-dialog__body">
                <label class="bo-dialog__label" for="rpsx-add-user">Membre</label>
                <select id="rpsx-add-user" class="rpsx-select" name="user_id" required>
                    <option value="">Choisir un membre…</option>
                    <?php foreach ($roster as $r): ?>
                    <option value="<?= (int) $r['id'] ?>"><?= $h((string) $r['label']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="bo-dialog__label" for="rpsx-add-minutes">Temps joué (minutes)</label>
                <input id="rpsx-add-minutes" class="rpsx-select" type="number" name="minutes" min="0" max="1440" value="<?= $length > 0 ? (int) round($length / 60) : 0 ?>">
            </div>
            <footer class="bo-dialog__foot">
                <button type="button" class="bo-dialog__btn" data-bo-dialog-close>Annuler</button>
                <button type="submit" class="bo-dialog__btn bo-dialog__btn--primary">Ajouter</button>
            </footer>
        </form>
    </dialog>
    <?php endif; ?>
</div>
<script defer src="<?= $h(asset_url('assets/js/back-office-bands.js')) ?>"></script>
<script>
(function () {
    'use strict';
    // Ancien lien « …#modifier » : ouvre la fenêtre de modification.
    function openTarget() {
        if (location.hash !== '#modifier') { return; }
        var dlg = document.getElementById('modifier-dialog');
        if (dlg && !dlg.open && typeof dlg.showModal === 'function') { dlg.showModal(); }
    }
    window.addEventListener('hashchange', openTarget);
    window.addEventListener('load', openTarget);
    // Focus gardé dans la fenêtre ouverte (Tab et Maj+Tab bouclent).
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Tab') { return; }
        var dlg = document.querySelector('.rpsx dialog[open]');
        if (!dlg) { return; }
        var items = Array.prototype.filter.call(dlg.querySelectorAll('a[href], button:not([disabled]), input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled])'), function (el) { return el.offsetParent !== null; });
        if (!items.length) { return; }
        var first = items[0];
        var last = items[items.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });
    // Joueurs ATAK connectés : relus toutes les 15 s (réponse no-store), « il y a » avancé chaque seconde.
    var live = document.querySelector('[data-rpsx-live]');
    if (live) {
        var list = live.querySelector('[data-rpsx-live-list]');
        var canPoint = live.getAttribute('data-rpsx-can-point') === '1';
        var loadedAt = Date.now();
        var links = { linked: 'Liaison ATAK active', delayed: 'Liaison ATAK lente', server_only: 'Vu sur le serveur', offline: 'Liaison coupée' };
        var ago = function (s) {
            if (s < 60) { return s + ' s'; }
            if (s < 3600) { return Math.floor(s / 60) + ' min'; }
            var m = Math.floor((s % 3600) / 60);
            return Math.floor(s / 3600) + ' h ' + (m < 10 ? '0' : '') + m;
        };
        var el = function (tag, cls, text) { var n = document.createElement(tag); if (cls) { n.className = cls; } if (text !== undefined) { n.textContent = text; } return n; };
        var button = function (label, value) { var b = el('button', 'ath-btn rpsx-btn', label); b.type = 'submit'; b.name = 'row'; b.value = value; return b; };
        var render = function (data) {
            var picked = {};
            Array.prototype.forEach.call(list.querySelectorAll('input[type="checkbox"]:checked'), function (c) { picked[c.value] = true; });
            list.textContent = '';
            (data.players || []).forEach(function (p) {
                var li = el('li', 'rpsx-player ' + (p.connected ? 'is-on' : 'is-idle'));
                li.setAttribute('data-age', String(p.age_s || 0));
                var pick = el('span', 'rpsx-player__pick');
                if (p.user_id && !p.checked_in && canPoint) {
                    var c = document.createElement('input');
                    c.type = 'checkbox'; c.name = 'user_ids[]'; c.value = String(p.user_id); c.checked = !!picked[String(p.user_id)];
                    c.setAttribute('aria-label', 'Sélectionner ' + p.label);
                    pick.appendChild(c);
                }
                var who = el('span', 'rpsx-player__who');
                who.appendChild(el('strong', '', p.label));
                var sub = [p.callsign, p.unit].filter(Boolean).join(' · ');
                if (sub) { who.appendChild(el('small', '', sub)); }
                var where = el('span', 'rpsx-player__where', [p.server, p.mission].filter(Boolean).join(' · ') || 'Serveur inconnu');
                if (p.server && !p.server_known) { where.appendChild(document.createTextNode(' ')); where.appendChild(el('em', '', '(non déclaré)')); }
                var seen = el('span', 'rpsx-player__seen');
                seen.appendChild(el('span', 'rpsx-dot'));
                seen.appendChild(document.createTextNode((p.connected ? 'Connecté' : 'Inactif') + ' · '));
                var a = el('span', '', 'il y a ' + ago(p.age_s || 0)); a.setAttribute('data-rpsx-ago', '');
                seen.appendChild(a);
                seen.appendChild(el('small', '', links[p.link] || ''));
                var act = el('span', 'rpsx-player__act');
                if (p.checked_in) {
                    act.appendChild(el('span', 'rps-pill rps-pill--ok', 'Pointé'));
                    if (canPoint) { act.appendChild(button('Retirer', 'remove:' + p.user_id)); }
                } else if (!p.user_id) {
                    act.appendChild(el('span', 'rps-pill rps-pill--warn', 'Compte non lié'));
                } else if (canPoint) {
                    act.appendChild(button('Pointer', 'point:' + p.user_id));
                }
                [pick, who, where, seen, act].forEach(function (n) { li.appendChild(n); });
                list.appendChild(li);
            });
            var count = live.querySelector('[data-rpsx-live-count]');
            if (count) { count.textContent = String(data.connected || 0); }
            var empty = live.querySelector('[data-rpsx-live-empty]');
            if (empty) { empty.hidden = (data.players || []).length > 0; }
            var stamp = live.querySelector('[data-rpsx-live-stamp]');
            if (stamp) { stamp.textContent = 'Mis à jour à ' + new Date().toLocaleTimeString(); }
            loadedAt = Date.now();
        };
        var poll = function () {
            if (document.hidden) { return; }
            fetch(live.getAttribute('data-rpsx-live'), { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) { if (data && data.ok) { render(data); } })
                .catch(function () {});
        };
        setInterval(poll, 15000);
        setInterval(function () {
            var extra = Math.floor((Date.now() - loadedAt) / 1000);
            Array.prototype.forEach.call(list.querySelectorAll('.rpsx-player'), function (li) {
                var t = li.querySelector('[data-rpsx-ago]');
                if (t) { t.textContent = 'il y a ' + ago(parseInt(li.getAttribute('data-age') || '0', 10) + extra); }
            });
        }, 1000);
    }
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-rpsx-copy]');
        if (!btn) { return; }
        var value = btn.getAttribute('data-rpsx-copy');
        var note = document.querySelector('.rpsx-copied');
        var done = function () { if (note) { note.textContent = 'Code copié : ' + value; } };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(value).then(done, function () { if (note) { note.textContent = 'Copie impossible : notez le code ' + value; } });
        } else {
            done();
        }
    });
})();
</script>
