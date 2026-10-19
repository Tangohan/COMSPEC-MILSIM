<?php
/** @var array<string, mixed> $terminal */
/** @var array<string, mixed>|null $metrics */
/** @var list<array{key: string, label: string, seconds: int}> $roles */
/** @var list<array{label: string, kind: string, seconds: int, meters: int}> $pilot */
/** @var list<array<string, mixed>> $certificates */
/** @var array<string, mixed>|null $activeBlock */
/** @var list<array<string, mixed>> $blocks */
/** @var list<array<string, mixed>> $messages */
/** @var list<array<string, mixed>> $actions */
/** @var list<array<string, mixed>> $compromises */
/** @var array{tenant: bool, base: string, home: string, home_label: string, by: string} $fleetScope */

use App\Repositories\AtakAdminControlRepository as Fleet;
use App\Support\AtakAdminBlockGate;
use App\Support\PlayerMetrics;
use App\Support\UtcDateTime;

$t = $terminal;
$m = is_array($metrics ?? null) ? $metrics : null;
$roles = is_array($roles ?? null) ? $roles : [];
$pilot = is_array($pilot ?? null) ? $pilot : [];
$certificates = is_array($certificates ?? null) ? $certificates : [];
$blocks = is_array($blocks ?? null) ? $blocks : [];
$messages = is_array($messages ?? null) ? $messages : [];
$actions = is_array($actions ?? null) ? $actions : [];
$compromises = is_array($compromises ?? null) ? $compromises : [];

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
// Même fiche pour l'administration de la plateforme et la gestion de la communauté (actions limitées, voir BackOfficeAtakFleetController).
$fs = is_array($fleetScope ?? null) ? $fleetScope : ['tenant' => false, 'base' => 'admin/parc-atak', 'home' => 'admin', 'home_label' => 'Administration du site'];
$inTenant = !empty($fs['tenant']);
$base = url((string) $fs['base']);
$v = static fn (mixed $x): string => trim((string) $x) !== '' ? trim((string) $x) : '—';
$fmtDate = static function (mixed $raw): string {
    $ts = UtcDateTime::timestamp($raw);

    return $ts !== null ? date('d/m/Y H:i', $ts) : '—';
};

$id = (int) $t['id'];
$name = Fleet::playerLabel($t);
$userId = (int) ($t['user_id'] ?? 0);
$csrf = \App\Core\Csrf::token();
$hidden = '<input type="hidden" name="_csrf" value="' . $h($csrf) . '">'
    . '<input type="hidden" name="terminal_id" value="' . $id . '">'
    . '<input type="hidden" name="retour" value="fiche:' . $id . '">';

$messageState = static function (array $msg): array {
    if (!empty($msg['cancelled_at'])) {
        return ['Annulé', 'slate'];
    }
    if (!empty($msg['acked_at'])) {
        return ['Lu par le joueur', 'mint'];
    }
    if (!empty($msg['expires_at']) && UtcDateTime::isPast($msg['expires_at'])) {
        return ['Expiré', 'slate'];
    }
    if (!empty($msg['delivered_at'])) {
        return ['Affiché en jeu', 'blue'];
    }

    return ['En attente', 'amber'];
};

$levelTone = ['info' => 'blue', 'warning' => 'amber', 'critical' => 'rose'];
$compromiseState = (string) ($t['compromise_state'] ?? 'none');
$isCompromised = !in_array($compromiseState, ['', 'none'], true);
$compromiseTone = ['captured' => 'amber', 'compromised' => 'rose', 'none' => 'mint'];
$certTone = ['active' => 'mint', 'issued' => 'mint', 'revoked' => 'rose', 'expired' => 'slate'];
$certLabel = ['active' => 'Valide', 'issued' => 'Valide', 'revoked' => 'Révoqué', 'expired' => 'Expiré'];
$fmtTs = static fn (int $ts): string => $ts > 0 ? date('d/m/Y H:i', $ts) : '—';
$maxRole = max(1, ...array_map(static fn (array $r): int => $r['seconds'], $roles ?: [['seconds' => 1]]));
$class = $m !== null ? ($m['game_class'] !== '' ? $m['game_class'] : $m['slot']) : '';
?>
<div class="pa paf">
    <div class="pa__frame">
        <header class="pa-hero">
            <p class="pa-crumb">
                <a class="paf-link" href="<?= $h(url((string) $fs['home'])) ?>"><?= $h((string) $fs['home_label']) ?></a>
                <span aria-hidden="true"> / </span>
                <a class="paf-link" href="<?= $h($base) ?>"><?= $h($inTenant ? 'Parc ATAK des membres' : 'Parc ATAK des joueurs') ?></a>
                <span aria-hidden="true"> / </span>
                <?= $h($name) ?>
            </p>
            <div class="pa-hero__row">
                <div>
                    <p class="pa-hero__kicker"><?= $h($v($t['tenant_name'] ?? '')) ?></p>
                    <h1 class="pa-hero__title"><?= $h($name) ?></h1>
                    <p class="pa-hero__lead">
                        <?= $h(trim((string) ($t['display_name'] ?? '')) !== '' ? (string) $t['display_name'] . ' · ' : '') ?>ATAK <span class="paf-mono"><?= $h((string) $t['terminal_uid']) ?></span>
                    </p>
                    <div class="pa-hero__actions">
                        <?php if ($isCompromised): ?>
                            <span class="pa-pill pa-pill--<?= $h($compromiseTone[$compromiseState] ?? 'amber') ?>"><?= $h(Fleet::COMPROMISE_STATES[$compromiseState] ?? 'Compromis') ?></span>
                        <?php endif; ?>
                        <?php if ($activeBlock !== null): ?>
                            <span class="pa-pill pa-pill--rose">Bloqué<?= (int) ($activeBlock['tenant_id'] ?? 0) === 0 ? ($inTenant ? ' par la plateforme' : ' dans toutes les communautés') : '' ?></span>
                        <?php endif; ?>
                        <?php if ($userId > 0 && $inTenant): ?>
                            <a class="pa-btn pa-btn--ghost" href="<?= $h(effectifs_workspace_url('membres/' . $userId)) ?>">Fiche du membre</a>
                        <?php elseif ($userId > 0): ?>
                            <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/users/person') . '?user_id=' . $userId) ?>">Compte de la personne</a>
                        <?php endif; ?>
                    </div>
                </div>
                <aside class="pa-hero__meta">
                    <p class="pa-hero__meta-kicker">Dernier signe</p>
                    <dl>
                        <div class="pa-hero__meta-row"><dt>ATAK</dt><dd><?= $h($fmtDate($t['last_seen_at'] ?? null)) ?></dd></div>
                        <div class="pa-hero__meta-row"><dt>En jeu</dt><dd><?= $h($fmtDate($m['game_seen'] ?? null)) ?></dd></div>
                        <div class="pa-hero__meta-row"><dt>Premier signe</dt><dd><?= $h($fmtDate($t['first_seen_at'] ?? $t['created_at'] ?? null)) ?></dd></div>
                    </dl>
                </aside>
            </div>
        </header>

        <?php if ($flash = \App\Core\Session::getFlash('success')): ?>
            <p class="pa-flash pa-flash--ok" role="status"><?= $h((string) $flash) ?></p>
        <?php endif; ?>
        <?php if ($flash = \App\Core\Session::getFlash('error')): ?>
            <p class="pa-flash pa-flash--err" role="alert"><?= $h((string) $flash) ?></p>
        <?php endif; ?>

        <ul class="pa-kpi-strip paf-kpis">
            <li class="pa-kpi"><span class="pa-kpi__label">Temps de jeu</span><span class="pa-kpi__value"><?= $h($m !== null ? PlayerMetrics::duration((int) $m['play']) : '—') ?></span><span class="pa-kpi__hint"><?= $h($m !== null && $m['screen'] > 0 ? PlayerMetrics::duration((int) $m['screen']) . ' sur l’écran de l’ATAK' : 'toutes missions') ?></span></li>
            <li class="pa-kpi"><span class="pa-kpi__label">Distance totale</span><span class="pa-kpi__value"><?= $h($m !== null ? PlayerMetrics::distance((int) $m['total_m']) : '—') ?></span><span class="pa-kpi__hint"><?= (int) ($m['sessions'] ?? 0) ?> mission(s) comptée(s)</span></li>
            <li class="pa-kpi"><span class="pa-kpi__label">À pied</span><span class="pa-kpi__value"><?= $h($m !== null ? PlayerMetrics::distance((int) $m['foot_m']) : '—') ?></span><span class="pa-kpi__hint"><?= number_format((int) ($m['steps'] ?? 0), 0, ',', ' ') ?> pas</span></li>
            <li class="pa-kpi"><span class="pa-kpi__label">Véhicule · air · mer</span><span class="pa-kpi__value"><?= $h($m !== null ? PlayerMetrics::distance((int) $m['land_m']) : '—') ?></span><span class="pa-kpi__hint"><?= $h($m !== null ? PlayerMetrics::distance((int) $m['air_m'] + (int) $m['halo_m']) . ' air · ' . PlayerMetrics::distance((int) $m['sea_m']) . ' mer' : '') ?></span></li>
            <li class="pa-kpi"><span class="pa-kpi__label">Heures de vol</span><span class="pa-kpi__value"><?= $h($m !== null ? PlayerMetrics::duration((int) $m['pilot_s']) : '—') ?></span><span class="pa-kpi__hint">aux commandes</span></li>
            <li class="pa-kpi"><span class="pa-kpi__label">Comptes rendus</span><span class="pa-kpi__value"><?= (int) ($m['reports'] ?? 0) ?></span><span class="pa-kpi__hint">envoyés depuis l’ATAK</span></li>
        </ul>

        <div class="paf-grid">
            <div class="paf-col">
                <section class="pa-card">
                    <p class="pa-card__kicker">En jeu</p>
                    <h2 class="pa-card__title">Classe et rôle</h2>
                    <dl class="paf-dl">
                        <div><dt>Classe (slot Arma)</dt><dd><?= $h($v($class)) ?></dd></div>
                        <div><dt>Rôle le plus tenu</dt><dd><?= $h($v($m['role'] ?? '')) ?><?php if (($m['role_s'] ?? 0) > 0): ?> <span class="paf-muted">· <?= $h(PlayerMetrics::duration((int) $m['role_s'])) ?></span><?php endif; ?></dd></div>
                        <div><dt>Faction · camp</dt><dd><?= $h($v(trim(($m['faction'] ?? '') . (($m['side'] ?? '') !== '' ? ' · ' . $m['side'] : ''), ' ·'))) ?></dd></div>
                        <div><dt>Groupe</dt><dd><?= $h($v($m['group'] ?? '')) ?></dd></div>
                        <div><dt>Dernière mission</dt><dd><?= $h($v($m['mission'] ?? '')) ?></dd></div>
                        <div><dt>Serveur</dt><dd><?= $h($v($m['server'] ?? '')) ?></dd></div>
                    </dl>
                    <?php if ($roles !== []): ?>
                    <h3 class="paf-sub">Rôles tenus</h3>
                    <ul class="paf-bars">
                        <?php foreach ($roles as $r): ?>
                        <li>
                            <span class="paf-bars__label"><?= $h(preg_replace('/^SLOT:\s*/i', 'Slot : ', $r['label']) ?? $r['label']) ?></span>
                            <span class="paf-bars__track"><span style="width: <?= max(2, (int) round($r['seconds'] * 100 / $maxRole)) ?>%"></span></span>
                            <span class="paf-bars__value"><?= $h(PlayerMetrics::duration($r['seconds'])) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                    <?php if ($pilot !== []): ?>
                    <h3 class="paf-sub">Heures de vol par appareil</h3>
                    <ul class="paf-bars">
                        <?php $maxP = max(1, ...array_map(static fn (array $p): int => $p['seconds'], $pilot)); ?>
                        <?php foreach ($pilot as $p): ?>
                        <li>
                            <span class="paf-bars__label"><?= $h($p['label']) ?></span>
                            <span class="paf-bars__track"><span style="width: <?= max(2, (int) round($p['seconds'] * 100 / $maxP)) ?>%"></span></span>
                            <span class="paf-bars__value"><?= $h(PlayerMetrics::duration($p['seconds'])) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                    <?php if ($m === null): ?>
                    <p class="paf-hint">Aucun chiffre de jeu : l’ATAK n’est rattaché à aucun compte du site, ou le joueur n’a pas encore joué avec le mod à jour.</p>
                    <?php endif; ?>
                </section>

                <section class="pa-card">
                    <p class="pa-card__kicker">Terminal</p>
                    <h2 class="pa-card__title">Identité de l’ATAK</h2>
                    <dl class="paf-dl">
                        <div><dt>Identifiant</dt><dd class="paf-mono"><?= $h((string) $t['terminal_uid']) ?></dd></div>
                        <div><dt>Appareil</dt><dd><?= $h($v($t['platform_label'] ?? $t['terminal_type'] ?? '')) ?></dd></div>
                        <div><dt>Matricule</dt><dd><?= $h($v($t['operator_military_id'] ?? '')) ?></dd></div>
                        <div><dt>Steam</dt><dd class="paf-mono"><?= $h($v($t['steam_id'] ?? '')) ?></dd></div>
                        <div><dt>Version ATAK · Link</dt><dd><?= $h($v($t['mod_version'] ?? '')) ?> · <?= $h($v($t['extension_version'] ?? '')) ?></dd></div>
                        <?php if (!$inTenant): ?>
                        <div><dt>Adresse réseau</dt><dd class="paf-mono"><?= $h($v($t['last_client_ip'] ?? '')) ?></dd></div>
                        <?php endif; ?>
                        <div><dt>Statut de la fiche</dt><dd><?= $h($v($t['status'] ?? '')) ?><?= $isCompromised ? ' · ' . $h(mb_strtolower(Fleet::COMPROMISE_STATES[$compromiseState] ?? 'compromis')) : '' ?></dd></div>
                        <div><dt>Compte du site</dt><dd><?= $h($v($t['email'] ?? '')) ?><?= trim((string) ($t['user_status'] ?? '')) !== '' ? ' · ' . $h((string) $t['user_status']) : '' ?></dd></div>
                    </dl>
                    <?php if ($certificates !== []): ?>
                    <h3 class="paf-sub">Certificats</h3>
                    <ul class="paf-certs">
                        <?php foreach ($certificates as $c): ?>
                        <?php
                            $cs = (string) $c['status'];
                            $expired = !empty($c['expires_at']) && UtcDateTime::isPast($c['expires_at']);
                        ?>
                        <li class="paf-cert">
                            <div class="paf-cert__main">
                                <span class="paf-mono"><?= $h((string) $c['certificate_ref']) ?></span>
                                <span class="pa-pill pa-pill--<?= $h($certTone[$cs] ?? 'slate') ?>"><?= $h($certLabel[$cs] ?? $cs) ?></span>
                            </div>
                            <p class="paf-muted"><span>Émis le</span> <?= $h($fmtDate($c['issued_at'] ?? null)) ?><?php if (!empty($c['expires_at'])): ?> · <span>expire le</span> <?= $h($fmtDate($c['expires_at'])) ?><?php endif; ?><?php if ($cs === 'revoked'): ?> · <span>révoqué le</span> <?= $h($fmtDate($c['revoked_at'] ?? null)) ?><?php if ((string) ($c['revoked_reason'] ?? '') === 'keyCompromise'): ?> <span>(compromission)</span><?php endif; ?><?php endif; ?></p>
                            <?php if (in_array($cs, ['active', 'issued'], true)): ?>
                            <form method="post" action="<?= $h($base . '/certificat') ?>">
                                <?= $hidden ?>
                                <input type="hidden" name="certificat_id" value="<?= (int) $c['id'] ?>">
                                <input type="hidden" name="operation" value="revoquer">
                                <button type="submit" class="pa-btn pa-btn--sm pa-btn--line" onclick="return confirm('Révoquer ce certificat ?');">Révoquer</button>
                            </form>
                            <?php elseif ($cs === 'revoked' && !$expired): ?>
                            <form method="post" action="<?= $h($base . '/certificat') ?>">
                                <?= $hidden ?>
                                <input type="hidden" name="certificat_id" value="<?= (int) $c['id'] ?>">
                                <input type="hidden" name="operation" value="retablir">
                                <button type="submit" class="pa-btn pa-btn--sm pa-btn--line">Rétablir</button>
                            </form>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </section>
            </div>

            <div class="paf-col">
                <?php ob_start(); ?>
                <section class="pa-card paf-compromise" id="compromissions">
                    <p class="pa-card__kicker">Sécurité</p>
                    <h2 class="pa-card__title">Compromissions</h2>
                    <?php if ($isCompromised): ?>
                    <div class="paf-alert paf-alert--<?= $h($compromiseTone[$compromiseState] ?? 'amber') ?>">
                        <p><strong><?= $h(Fleet::COMPROMISE_STATES[$compromiseState] ?? 'Compromis') ?> depuis le <?= $h($fmtDate($t['compromised_at'] ?? null)) ?></strong></p>
                        <?php if (trim((string) ($t['compromise_reason'] ?? '')) !== ''): ?><p><?= $h((string) $t['compromise_reason']) ?></p><?php endif; ?>
                        <form method="post" action="<?= $h($base . '/compromission/annuler') ?>" class="paf-undo">
                            <?= $hidden ?>
                            <label class="paf-field"><span class="paf-label">Note (facultatif)</span><input type="text" name="note" maxlength="255" placeholder="Faux positif, téléphone récupéré…"></label>
                            <label class="paf-check"><input type="hidden" name="certificats" value="0"><input type="checkbox" name="certificats" value="1" checked> <span>Rétablir les certificats révoqués par la compromission</span></label>
                            <button type="submit" class="pa-btn pa-btn--sm pa-btn--mint" onclick="return confirm('Annuler la compromission de cet ATAK ?');">Annuler la compromission</button>
                        </form>
                    </div>
                    <?php else: ?>
                    <p class="paf-ok"><span class="pa-pill pa-pill--mint">Sous contrôle</span> <span class="paf-muted">Aucune compromission en cours.</span></p>
                    <?php endif; ?>

                    <?php if ($compromises === []): ?>
                    <p class="paf-hint">Aucune compromission enregistrée pour cet ATAK.</p>
                    <?php else: ?>
                    <ol class="paf-timeline">
                        <?php foreach ($compromises as $e): ?>
                        <?php
                            $es = (string) $e['state'];
                            $undone = !empty($e['undone_at']);
                            $certCount = count(array_filter(explode(',', (string) ($e['certificate_ids'] ?? ''))));
                        ?>
                        <li class="paf-timeline__item paf-timeline__item--<?= $h($undone ? 'undone' : ($compromiseTone[$es] ?? 'amber')) ?>">
                            <div class="paf-timeline__head">
                                <span class="paf-timeline__when"><?= $h($fmtTs((int) $e['ts'])) ?></span>
                                <span class="pa-pill pa-pill--<?= $h($undone ? 'slate' : ($compromiseTone[$es] ?? 'amber')) ?>"><?= $h(Fleet::COMPROMISE_STATES[$es] ?? $es) ?></span>
                                <?php if ($undone): ?><span class="pa-pill pa-pill--slate">Annulée</span><?php endif; ?>
                            </div>
                            <?php if (trim((string) ($e['reason'] ?? '')) !== ''): ?><p class="paf-timeline__what"><?= $h((string) $e['reason']) ?></p><?php endif; ?>
                            <p class="paf-muted">
                                <?= $h(Fleet::COMPROMISE_SOURCES[(string) ($e['source'] ?? '')] ?? 'En jeu') ?><?php if (trim((string) ($e['actor_label'] ?? '')) !== ''): ?> · <?= $h((string) $e['actor_label']) ?><?php endif; ?><?php if ($certCount > 0): ?> · <?= $certCount ?> certificat(s) révoqué(s)<?php endif; ?><?php if (!empty($e['legacy'])): ?> · d’après le journal d’activité<?php endif; ?>
                            </p>
                            <?php if ($undone): ?>
                            <p class="paf-muted"><span>Annulée le</span> <?= $h($fmtDate($e['undone_at'])) ?> <span>par</span> <?= $h($v($e['undone_by_label'] ?? '')) ?><?= trim((string) ($e['undo_note'] ?? '')) !== '' ? ' · ' . $h((string) $e['undo_note']) : '' ?></p>
                            <?php elseif ($es !== 'none' && (int) $e['id'] > 0): ?>
                            <form method="post" action="<?= $h($base . '/compromission/annuler') ?>">
                                <?= $hidden ?>
                                <input type="hidden" name="evenement_id" value="<?= (int) $e['id'] ?>">
                                <input type="hidden" name="certificats" value="1">
                                <button type="submit" class="pa-btn pa-btn--sm pa-btn--line" onclick="return confirm('Annuler cette compromission et rétablir ses certificats ?');">Annuler cette compromission</button>
                            </form>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ol>
                    <?php endif; ?>
                </section>
                <?php $compromiseCard = (string) ob_get_clean(); ?>
                <?php if ($isCompromised) { echo $compromiseCard; } ?>

                <section class="pa-card paf-actions-card">
                    <p class="pa-card__kicker">Actions</p>
                    <h2 class="pa-card__title"><?= $h($inTenant ? 'Gérer cet ATAK' : 'Administrer cet ATAK') ?></h2>

                    <?php if ($activeBlock !== null): ?>
                    <?php $platformBlock = (int) ($activeBlock['tenant_id'] ?? 0) === 0; ?>
                    <div class="paf-blocked">
                        <p><strong>Bloqué<?= $inTenant && $platformBlock ? ' par l’administration de la plateforme' : '' ?> depuis le <?= $h($fmtDate($activeBlock['created_at'] ?? null)) ?></strong>
                            <?= !empty($activeBlock['expires_at']) ? ' jusqu’au ' . $h($fmtDate($activeBlock['expires_at'])) : ', sans fin' ?>.</p>
                        <?php if (trim((string) ($activeBlock['reason'] ?? '')) !== ''): ?><p>Motif : <?= $h((string) $activeBlock['reason']) ?></p><?php endif; ?>
                        <p class="paf-muted">Le joueur voit : « <?= $h(AtakAdminBlockGate::playerMessage($activeBlock)) ?> »</p>
                        <?php if ($inTenant && $platformBlock): ?>
                        <p class="paf-hint">Seule l’administration de la plateforme peut lever ce blocage.</p>
                        <?php else: ?>
                        <form method="post" action="<?= $h($base . '/debloquer') ?>">
                            <?= $hidden ?>
                            <input type="hidden" name="blocage_id" value="<?= (int) $activeBlock['id'] ?>">
                            <button type="submit" class="pa-btn pa-btn--sm pa-btn--mint">Lever le blocage</button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <details class="paf-act"<?= $inTenant ? '' : ' open' ?>>
                        <summary><?= $h($inTenant ? 'Bloquer dans la communauté' : 'Bloquer') ?></summary>
                        <form method="post" action="<?= $h($base . '/bloquer') ?>" class="paf-act__body">
                            <?= $hidden ?>
                            <p class="paf-hint">L’API refuse la connexion Athena du jeu, la reprise de session, l’enregistrement du téléphone et tous les envois du jeu<?= $inTenant ? ' dans votre communauté' : '' ?>. Les sessions ouvertes sont fermées.</p>
                            <div class="paf-row">
                                <?php if (!$inTenant): ?>
                                <label class="paf-field"><span class="paf-label">Portée</span>
                                    <select name="portee"><option value="communaute">Sa communauté</option><option value="toutes">Toutes les communautés</option></select>
                                </label>
                                <?php endif; ?>
                                <label class="paf-field"><span class="paf-label">Durée</span>
                                    <select name="duree_jours"><option value="1">1 jour</option><option value="7">7 jours</option><option value="30">30 jours</option><option value="0" selected>Sans fin</option></select>
                                </label>
                            </div>
                            <label class="paf-field"><span class="paf-label">Motif (interne)</span><input type="text" name="motif" maxlength="500"></label>
                            <label class="paf-field"><span class="paf-label">Message montré au joueur</span><input type="text" name="message_joueur" maxlength="500" placeholder="<?= $h($inTenant ? 'Votre ATAK a été bloqué par la gestion de votre communauté…' : 'Votre ATAK a été bloqué par l’administration ATHENA…') ?>"></label>
                            <button type="submit" class="pa-btn pa-btn--sm paf-btn--danger" onclick="return confirm('Bloquer cet ATAK ?');">Bloquer</button>
                        </form>
                    </details>
                    <?php endif; ?>

                    <details class="paf-act">
                        <summary>Imposer un message en jeu</summary>
                        <form method="post" action="<?= $h($base . '/message') ?>" class="paf-act__body">
                            <?= $hidden ?>
                            <label class="paf-field"><span class="paf-label">Titre</span><input type="text" name="titre" maxlength="120" required></label>
                            <label class="paf-field"><span class="paf-label">Texte</span><textarea name="texte" rows="4" maxlength="2000" required></textarea></label>
                            <div class="paf-row">
                                <label class="paf-field"><span class="paf-label">Niveau</span>
                                    <select name="niveau"><?php foreach (Fleet::LEVELS as $lv => $ll): ?><option value="<?= $h($lv) ?>"><?= $h($ll) ?></option><?php endforeach; ?></select>
                                </label>
                                <label class="paf-field"><span class="paf-label">Valable</span>
                                    <select name="validite_heures"><option value="24">24 h</option><option value="72" selected>3 jours</option><option value="168">7 jours</option><option value="720">30 jours</option><option value="0">Jusqu’à lecture</option></select>
                                </label>
                            </div>
                            <label class="paf-check"><input type="hidden" name="validation" value="0"><input type="checkbox" name="validation" value="1" checked> <span>Le joueur doit valider pour fermer le message</span></label>
                            <button type="submit" class="pa-btn pa-btn--sm pa-btn--mint">Enregistrer le message</button>
                            <p class="paf-hint">Le message s’affiche sur l’ATAK du joueur à sa prochaine présence en jeu, une fois le mod à jour (prochaine version de l’ATAK).</p>
                        </form>
                    </details>

                    <?php if (!$isCompromised): ?>
                    <details class="paf-act" data-act="compromise">
                        <summary>Déclarer une compromission</summary>
                        <form method="post" action="<?= $h($base . '/compromission') ?>" class="paf-act__body">
                            <?= $hidden ?>
                            <p class="paf-hint">Comme une compromission validée sur le terrain : « Compromis » révoque aussi ses certificats valides. Vous pourrez l’annuler depuis l’historique.</p>
                            <label class="paf-field"><span class="paf-label">État</span>
                                <select name="etat"><option value="captured">Capturé (téléphone aux mains de l’ennemi)</option><option value="compromised">Compromis (certificats révoqués)</option></select>
                            </label>
                            <label class="paf-field"><span class="paf-label">Motif</span><input type="text" name="motif" maxlength="255"></label>
                            <button type="submit" class="pa-btn pa-btn--sm paf-btn--danger" onclick="return confirm('Déclarer cet ATAK compromis ?');">Déclarer</button>
                        </form>
                    </details>
                    <?php endif; ?>

                    <details class="paf-act" data-act="note">
                        <summary>Ajouter une note interne</summary>
                        <form method="post" action="<?= $h($base . '/note') ?>" class="paf-act__body">
                            <?= $hidden ?>
                            <label class="paf-field"><span class="paf-label"><?= $h($inTenant ? 'Note (visible des gestionnaires de la communauté et de l’administration)' : 'Note (visible de l’administration seulement)') ?></span><textarea name="note" rows="3" maxlength="1000" required></textarea></label>
                            <button type="submit" class="pa-btn pa-btn--sm pa-btn--line">Ajouter au journal</button>
                        </form>
                    </details>

                    <details class="paf-act">
                        <summary><?= $h($inTenant ? 'Déconnecter' : 'Déconnecter ou retirer') ?></summary>
                        <div class="paf-act__body">
                            <form method="post" action="<?= $h($base . '/deconnecter') ?>">
                                <?= $hidden ?>
                                <p class="paf-hint">Ferme les sessions de jeu ouvertes. Le jeu se reconnecte seul s’il n’est pas bloqué.</p>
                                <button type="submit" class="pa-btn pa-btn--sm pa-btn--line">Déconnecter</button>
                            </form>
                            <?php if ($inTenant): ?>
                            <p class="paf-hint">Le retrait définitif d’un ATAK et le blocage dans toutes les communautés restent réservés à l’administration de la plateforme.</p>
                            <?php else: ?>
                            <form method="post" action="<?= $h($base . '/retirer') ?>" class="paf-retire">
                                <?= $hidden ?>
                                <p class="paf-hint">Révoque les certificats et l’appareil de confiance, ferme les sessions et supprime la fiche. Le joueur devra refaire la liaison ; bloquez-le aussi pour l’en empêcher.</p>
                                <label class="paf-field"><span class="paf-label">Motif</span><input type="text" name="motif" maxlength="255"></label>
                                <button type="submit" class="pa-btn pa-btn--sm paf-btn--danger" onclick="return confirm('Retirer définitivement cet ATAK ?');">Retirer l’ATAK</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </details>
                </section>

                <?php if (!$isCompromised) { echo $compromiseCard; } ?>

                <section class="pa-card">
                    <p class="pa-card__kicker">Messages imposés</p>
                    <h2 class="pa-card__title">Ce que le joueur a reçu</h2>
                    <?php if ($messages === []): ?>
                    <p class="paf-hint">Aucun message imposé à ce joueur.</p>
                    <?php else: ?>
                    <ol class="paf-msgs">
                        <?php foreach ($messages as $msg): ?>
                        <?php [$stLabel, $stTone] = $messageState($msg); ?>
                        <li class="paf-msg">
                            <div class="paf-msg__head">
                                <strong><?= $h((string) $msg['title']) ?></strong>
                                <span class="pa-pill pa-pill--<?= $h($levelTone[(string) $msg['level']] ?? 'blue') ?>"><?= $h(Fleet::LEVELS[(string) $msg['level']] ?? (string) $msg['level']) ?></span>
                                <span class="pa-pill pa-pill--<?= $h($stTone) ?>"><?= $h($stLabel) ?></span>
                                <?php if ((string) ($msg['kind'] ?? 'message') !== 'message'): ?><span class="pa-pill pa-pill--slate">Avis automatique</span><?php endif; ?>
                            </div>
                            <p class="paf-msg__body"><?= nl2br($h((string) $msg['body'])) ?></p>
                            <p class="paf-muted">Le <?= $h($fmtDate($msg['created_at'] ?? null)) ?> par <?= $h($v($msg['actor_callsign'] ?? $msg['actor_name'] ?? '')) ?><?= !empty($msg['acked_at']) ? ' · lu le ' . $h($fmtDate($msg['acked_at'])) : '' ?></p>
                            <?php if ($stLabel === 'En attente' || $stLabel === 'Affiché en jeu'): ?>
                            <form method="post" action="<?= $h($base . '/message/annuler') ?>">
                                <?= $hidden ?>
                                <input type="hidden" name="message_id" value="<?= (int) $msg['id'] ?>">
                                <button type="submit" class="pa-btn pa-btn--sm pa-btn--line">Annuler ce message</button>
                            </form>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ol>
                    <?php endif; ?>
                </section>

                <?php if ($blocks !== [] || $actions !== []): ?>
                <section class="pa-card">
                    <p class="pa-card__kicker">Journal</p>
                    <h2 class="pa-card__title"><?= $h($inTenant ? 'Actions sur cet ATAK' : 'Actions de l’administration') ?></h2>
                    <ol class="paf-log">
                        <?php foreach ($actions as $a): ?>
                        <li>
                            <span class="paf-log__when"><?= $h($fmtDate($a['created_at'] ?? null)) ?></span>
                            <span class="paf-log__what"><strong><?= $h(Fleet::ACTIONS[(string) $a['action']] ?? (string) $a['action']) ?></strong></span>
                            <span class="paf-log__who">par <?= $h((string) ($a['actor_label'] ?? '—')) ?><?php if (trim((string) ($a['detail'] ?? '')) !== ''): ?> · <?= $h((string) $a['detail']) ?><?php endif; ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ol>
                    <?php if ($blocks !== []): ?>
                    <h3 class="paf-sub">Blocages</h3>
                    <ul class="paf-list">
                        <?php foreach ($blocks as $b): ?>
                        <li>
                            Du <?= $h($fmtDate($b['created_at'] ?? null)) ?>
                            <?= !empty($b['lifted_at']) ? 'au ' . $h($fmtDate($b['lifted_at'])) . ' (levé)' : (!empty($b['expires_at']) ? 'au ' . $h($fmtDate($b['expires_at'])) : '(sans fin)') ?>
                            · <?= (int) ($b['tenant_id'] ?? 0) === 0 ? ($inTenant ? 'plateforme, toutes les communautés' : 'toutes les communautés') : 'sa communauté' ?>
                            <?php if (trim((string) ($b['reason'] ?? '')) !== ''): ?> · <?= $h((string) $b['reason']) ?><?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </section>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
