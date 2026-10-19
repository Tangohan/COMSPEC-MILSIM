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

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
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
$maxRole = max(1, ...array_map(static fn (array $r): int => $r['seconds'], $roles ?: [['seconds' => 1]]));
$class = $m !== null ? ($m['game_class'] !== '' ? $m['game_class'] : $m['slot']) : '';
?>
<div class="pa paf">
    <div class="pa__frame">
        <header class="pa-hero">
            <p class="pa-crumb">
                <a class="paf-link" href="<?= $h(url('admin')) ?>">Administration du site</a>
                <span aria-hidden="true"> / </span>
                <a class="paf-link" href="<?= $h(url('admin/parc-atak')) ?>">Parc ATAK des joueurs</a>
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
                        <?php if ($activeBlock !== null): ?>
                            <span class="pa-pill pa-pill--rose">Bloqué<?= (int) ($activeBlock['tenant_id'] ?? 0) === 0 ? ' dans toutes les communautés' : '' ?></span>
                        <?php endif; ?>
                        <?php if ($userId > 0): ?>
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
                        <div><dt>Adresse réseau</dt><dd class="paf-mono"><?= $h($v($t['last_client_ip'] ?? '')) ?></dd></div>
                        <div><dt>Statut de la fiche</dt><dd><?= $h($v($t['status'] ?? '')) ?><?= !in_array((string) ($t['compromise_state'] ?? 'none'), ['', 'none'], true) ? ' · compromis' : '' ?></dd></div>
                        <div><dt>Compte du site</dt><dd><?= $h($v($t['email'] ?? '')) ?><?= trim((string) ($t['user_status'] ?? '')) !== '' ? ' · ' . $h((string) $t['user_status']) : '' ?></dd></div>
                    </dl>
                    <?php if ($certificates !== []): ?>
                    <h3 class="paf-sub">Certificats</h3>
                    <ul class="paf-list">
                        <?php foreach ($certificates as $c): ?>
                        <li><span class="paf-mono"><?= $h((string) $c['certificate_ref']) ?></span> · <?= $h((string) $c['status']) ?> · émis le <?= $h($fmtDate($c['issued_at'] ?? null)) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </section>
            </div>

            <div class="paf-col">
                <section class="pa-card paf-actions-card">
                    <p class="pa-card__kicker">Actions</p>
                    <h2 class="pa-card__title">Administrer cet ATAK</h2>

                    <?php if ($activeBlock !== null): ?>
                    <div class="paf-blocked">
                        <p><strong>Bloqué depuis le <?= $h($fmtDate($activeBlock['created_at'] ?? null)) ?></strong>
                            <?= !empty($activeBlock['expires_at']) ? ' jusqu’au ' . $h($fmtDate($activeBlock['expires_at'])) : ', sans fin' ?>.</p>
                        <?php if (trim((string) ($activeBlock['reason'] ?? '')) !== ''): ?><p>Motif : <?= $h((string) $activeBlock['reason']) ?></p><?php endif; ?>
                        <p class="paf-muted">Le joueur voit : « <?= $h(AtakAdminBlockGate::playerMessage($activeBlock)) ?> »</p>
                        <form method="post" action="<?= $h(url('admin/parc-atak/debloquer')) ?>">
                            <?= $hidden ?>
                            <input type="hidden" name="blocage_id" value="<?= (int) $activeBlock['id'] ?>">
                            <button type="submit" class="pa-btn pa-btn--sm pa-btn--mint">Lever le blocage</button>
                        </form>
                    </div>
                    <?php else: ?>
                    <details class="paf-act" open>
                        <summary>Bloquer</summary>
                        <form method="post" action="<?= $h(url('admin/parc-atak/bloquer')) ?>" class="paf-act__body">
                            <?= $hidden ?>
                            <p class="paf-hint">L’API refuse la connexion Athena du jeu, la reprise de session, l’enregistrement du téléphone et tous les envois du jeu. Les sessions ouvertes sont fermées.</p>
                            <div class="paf-row">
                                <label class="paf-field"><span class="paf-label">Portée</span>
                                    <select name="portee"><option value="communaute">Sa communauté</option><option value="toutes">Toutes les communautés</option></select>
                                </label>
                                <label class="paf-field"><span class="paf-label">Durée</span>
                                    <select name="duree_jours"><option value="1">1 jour</option><option value="7">7 jours</option><option value="30">30 jours</option><option value="0" selected>Sans fin</option></select>
                                </label>
                            </div>
                            <label class="paf-field"><span class="paf-label">Motif (interne)</span><input type="text" name="motif" maxlength="500"></label>
                            <label class="paf-field"><span class="paf-label">Message montré au joueur</span><input type="text" name="message_joueur" maxlength="500" placeholder="Votre ATAK a été bloqué par l’administration ATHENA…"></label>
                            <button type="submit" class="pa-btn pa-btn--sm paf-btn--danger" onclick="return confirm('Bloquer cet ATAK ?');">Bloquer</button>
                        </form>
                    </details>
                    <?php endif; ?>

                    <details class="paf-act">
                        <summary>Imposer un message en jeu</summary>
                        <form method="post" action="<?= $h(url('admin/parc-atak/message')) ?>" class="paf-act__body">
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

                    <details class="paf-act">
                        <summary>Déconnecter ou retirer</summary>
                        <div class="paf-act__body">
                            <form method="post" action="<?= $h(url('admin/parc-atak/deconnecter')) ?>">
                                <?= $hidden ?>
                                <p class="paf-hint">Ferme les sessions de jeu ouvertes. Le jeu se reconnecte seul s’il n’est pas bloqué.</p>
                                <button type="submit" class="pa-btn pa-btn--sm pa-btn--line">Déconnecter</button>
                            </form>
                            <form method="post" action="<?= $h(url('admin/parc-atak/retirer')) ?>" class="paf-retire">
                                <?= $hidden ?>
                                <p class="paf-hint">Révoque les certificats et l’appareil de confiance, ferme les sessions et supprime la fiche. Le joueur devra refaire la liaison ; bloquez-le aussi pour l’en empêcher.</p>
                                <label class="paf-field"><span class="paf-label">Motif</span><input type="text" name="motif" maxlength="255"></label>
                                <button type="submit" class="pa-btn pa-btn--sm paf-btn--danger" onclick="return confirm('Retirer définitivement cet ATAK ?');">Retirer l’ATAK</button>
                            </form>
                        </div>
                    </details>
                </section>

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
                            </div>
                            <p class="paf-msg__body"><?= nl2br($h((string) $msg['body'])) ?></p>
                            <p class="paf-muted">Le <?= $h($fmtDate($msg['created_at'] ?? null)) ?> par <?= $h($v($msg['actor_callsign'] ?? $msg['actor_name'] ?? '')) ?><?= !empty($msg['acked_at']) ? ' · lu le ' . $h($fmtDate($msg['acked_at'])) : '' ?></p>
                            <?php if ($stLabel === 'En attente' || $stLabel === 'Affiché en jeu'): ?>
                            <form method="post" action="<?= $h(url('admin/parc-atak/message/annuler')) ?>">
                                <?= $hidden ?>
                                <input type="hidden" name="message_id" value="<?= (int) $msg['id'] ?>">
                                <button type="submit" class="pa-btn pa-btn--sm pa-btn--line">Annuler</button>
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
                    <h2 class="pa-card__title">Actions de l’administration</h2>
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
                            · <?= (int) ($b['tenant_id'] ?? 0) === 0 ? 'toutes les communautés' : 'sa communauté' ?>
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
