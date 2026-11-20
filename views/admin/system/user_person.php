<?php
declare(strict_types=1);

$email = (string) ($personEmail ?? '');
$displayName = trim((string) ($personDisplayName ?? ''));
$callsign = trim((string) ($personCallsign ?? ''));
$steamId = trim((string) ($personSteamId ?? ''));
$athenaId = trim((string) ($personAthenaId ?? ''));
$civil = is_array($personCivil ?? null) ? $personCivil : [];
$hasLiveOrg = !empty($personHasLiveOrg);
$memberships = is_array($personMemberships ?? null) ? $personMemberships : [];
$mergePreview = is_array($personMergePreview ?? null) ? $personMergePreview : [];
$mergeable = !empty($mergePreview['mergeable']);
$mergeRows = is_array($mergePreview['rows'] ?? null) ? $mergePreview['rows'] : [];
$suggestedSurvivorId = (int) ($mergePreview['suggested_survivor_id'] ?? 0);
$identityFills = is_array($mergePreview['identity_fills'] ?? null) ? $mergePreview['identity_fills'] : [];
$identityTableFills = is_array($mergePreview['identity_table_fills'] ?? null) ? $mergePreview['identity_table_fills'] : [];
$steamCollisions = is_array($mergePreview['steam_collisions'] ?? null) ? $mergePreview['steam_collisions'] : [];
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$csrf = $h(\App\Core\Csrf::token());
$selectedSurvivorId = (int) ($_GET['survivor_id'] ?? $suggestedSurvivorId);
$ban = is_array($personBan ?? null) ? $personBan : null;
$banHistory = is_array($personBanHistory ?? null) ? $personBanHistory : [];
$knownIps = is_array($personKnownIps ?? null) ? $personKnownIps : [];
$isPlatformAdmin = !empty($personIsPlatformAdmin);
$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');
$fmtDate = static function (string $value): string {
    $timestamp = \App\Support\UtcDateTime::timestamp($value);

    return $timestamp === null ? '—' : (new \DateTimeImmutable('@' . $timestamp))
        ->setTimezone(new \DateTimeZone('Europe/Paris'))->format('d/m/Y à H:i');
};
$fmtParis = $fmtDate;
$empty = static function (?string $v) use ($h): string {
    $t = trim((string) $v);
    return $t !== '' ? $h($t) : '<span class="pu-muted">—</span>';
};
$statusLabel = static function (string $status): string {
    return match ($status) {
        'active' => 'Compte actif',
        'inactive' => 'Compte inactif',
        'pending_verification' => 'En attente de vérification de l’e-mail',
        default => $status !== '' ? $status : 'Inconnu',
    };
};
$primary = $callsign !== '' ? $callsign : ($displayName !== '' ? $displayName : $email);
$allMemberships = $personAllMemberships ?? [];
$firstSeen = [];
$lastSeen = [];
$verified = [];
$liveOrganizations = [];
$departed = [];
foreach ($allMemberships as $row) {
    if (!empty($row['deleted_at']) || ($row['status'] ?? '') === 'merged') {
        continue;
    }
    $firstSeen[] = (string) ($row['account_created_at'] ?? '');
    $lastSeen[] = (string) ($row['last_login_at'] ?? '');
    $verified[] = (string) ($row['email_verified_at'] ?? '');
    $membershipStatus = $row['membership_status'] ?? '';
    if (!\App\Support\PortalAccessChoice::membershipIsPlaceholder($row)) {
        if ($membershipStatus === 'active') {
            $liveOrganizations[(int) $row['tenant_id']] = true;
        } elseif ($membershipStatus === 'left') {
            $departed[] = $row;
        }
    }
}
$firstSeen = array_filter($firstSeen);
$lastSeen = array_filter($lastSeen);
$verified = array_filter($verified);
$appeal = $personAppeal ?? null;
$activity = $personActivity ?? null;
$canManage = empty($personIsSelf) && !$isPlatformAdmin;
$membershipAction = static function (string $path, array $fields, string $label, string $title, string $message) use ($h, $email, $csrf): void {
    ?>
    <form method="post" action="<?= $h(url($path)) ?>" data-pu-confirm="1" data-confirm-title="<?= $h($title) ?>" data-confirm-body="<?= $h($message) ?>" data-confirm-ok="<?= $h($label) ?>">
        <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="return_person" value="<?= $h($email) ?>">
        <?php foreach ($fields as $key => $value): ?><input type="hidden" name="<?= $h($key) ?>" value="<?= $h((string) $value) ?>"><?php endforeach; ?>
        <button type="submit" class="pa-btn pa-btn--line pa-btn--sm" hidden data-person-enhanced><?= $h($label) ?></button>
    </form>
    <?php
};
?>
<div class="pa pu person" data-person>
    <div class="pa__frame">
        <header class="pa-hero">
            <div class="pa-hero__row">
                <div>
                    <p class="pa-hero__kicker">Dossier personne</p>
                    <h1 class="pa-hero__title"><?= $h($primary) ?></h1>
                    <p class="pa-hero__lead"><?= $h($email) ?><?= $displayName !== '' && strcasecmp($displayName, $primary) !== 0 ? ' · ' . $h($displayName) : '' ?></p>
                    <div class="pa-hero__actions">
                        <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/users')) ?>">Annuaire</a>
                        <button class="pa-btn pa-btn--ghost" type="button" data-pu-open="person-picker-dialog" aria-haspopup="dialog" hidden data-person-enhanced>Rechercher un compte ou une organisation</button>
                        <button class="pa-btn pa-btn--ghost" type="button" data-person-copy="<?= $h($email) ?>" hidden>Copier l’e-mail</button>
                        <?php if ($steamId !== ''): ?><a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/parc-atak') . '?q=' . rawurlencode($steamId)) ?>">Terminaux ATAK</a><?php endif; ?>
                        <?php if ($ban !== null): ?>
                            <a class="pa-btn pa-btn--solid" href="#bannissement">Examiner le bannissement</a>
                        <?php elseif ($canManage): ?>
                            <button class="pa-btn pa-btn--danger" type="button" data-pu-open="person-ban-dialog" aria-haspopup="dialog" hidden data-person-enhanced>Bannir du site</button>
                            <noscript><a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/sanctions')) ?>">Ouvrir les sanctions</a></noscript>
                        <?php endif; ?>
                    </div>
                    <span class="pa-hero__lead" role="status" data-person-copy-status></span>
                </div>
                <div class="person-badges">
                    <?php if ($ban !== null): ?><span class="pa-pill pa-pill--rose">Banni du site</span><?php endif; ?>
                    <span class="pa-pill <?= $hasLiveOrg ? 'pa-pill--mint' : 'pa-pill--slate' ?>"><?= $hasLiveOrg ? 'Accès actif en communauté' : 'Aucun accès actif en communauté' ?></span>
                    <?php if ($isPlatformAdmin): ?><a class="pa-pill pa-pill--mint" href="<?= $h(url('admin/system/administrateurs-site')) ?>">Administrateur du site</a><?php endif; ?>
                </div>
            </div>
        </header>
        <nav class="pa-tabs" aria-label="Sections du dossier">
            <a class="pa-tab" href="#identite">Vue d’ensemble</a><a class="pa-tab" href="#communautes">Communautés</a>
            <a class="pa-tab" href="#activite">Activité</a><a class="pa-tab" href="#bannissement">Sanctions</a><a class="pa-tab" href="#reseau">Réseau</a>
            <?php if ($mergeable): ?><a class="pa-tab" href="#fusion-comptes">Fusion</a><?php endif; ?>
        </nav>
        <?php if (is_string($flashOk) && trim($flashOk) !== ''): ?><p class="pa-flash pa-flash--ok" role="status"><?= $h($flashOk) ?></p><?php endif; ?>
        <?php if (is_string($flashErr) && trim($flashErr) !== ''): ?><p class="pa-flash pa-flash--err" role="alert"><?= $h($flashErr) ?></p><?php endif; ?>

        <dl class="person-metrics">
            <div class="pa-kpi"><dt class="pa-kpi__label">Dernière connexion</dt><dd><?= $lastSeen === [] ? 'Aucune date connue' : $h($fmtDate(max($lastSeen))) ?></dd></div>
            <div class="pa-kpi"><dt class="pa-kpi__label">Premier compte créé</dt><dd><?= $firstSeen === [] ? 'Date inconnue' : $h($fmtDate(min($firstSeen))) ?></dd></div>
            <div class="pa-kpi"><dt class="pa-kpi__label">Rattachements actifs</dt><dd><?= count($liveOrganizations) ?></dd></div>
            <div class="pa-kpi"><dt class="pa-kpi__label">Comptes associés</dt><dd><?= (int) ($personLiveFiches ?? 0) ?></dd></div>
        </dl>

        <div class="person-columns">
            <section id="identite" class="pa-card" aria-labelledby="person-identity">
                <h2 class="pa-card__title" id="person-identity">Identité et accès</h2>
                <dl class="person-facts">
                    <div><dt>Identifiant Athena</dt><dd><?= $empty($athenaId) ?></dd></div>
                    <div><dt>Steam ID</dt><dd><?= $empty($steamId) ?></dd></div>
                    <div><dt>Nom civil</dt><dd><?= $empty(trim(($civil['first_name'] ?? '') . ' ' . ($civil['last_name'] ?? ''))) ?></dd></div>
                    <div><dt>Téléphone</dt><dd><?= $empty($civil['phone'] ?? null) ?></dd></div>
                    <div><dt>Vérification de l’e-mail</dt><dd><?= $verified === [] ? 'Aucune vérification enregistrée' : $h($fmtDate(min($verified))) ?></dd></div>
                </dl>
                <?php if ($steamId !== '' && in_array($personSteamState ?? '', ['reported', 'pending'], true)): ?>
                    <p><a class="pu-link" href="<?= $h(url('admin/steam-doublons') . '?steam=' . rawurlencode($steamId)) ?>">Examiner le doublon Steam</a></p>
                <?php endif; ?>
            </section>
            <section id="activite" class="pa-card" aria-labelledby="person-activity">
                <div class="pa-card__head"><h2 class="pa-card__title" id="person-activity">Actions récentes des comptes</h2>
                    <a class="pa-btn pa-btn--line pa-btn--sm" href="<?= $h(url('admin/audit') . '?actor_email=' . rawurlencode($email)) ?>">Journal filtré</a>
                </div>
                <?php if ($activity === null || $activity === []): ?>
                    <p class="pa-card__help"><?= $activity === null ? 'Journal momentanément indisponible.' : 'Aucune action journalisée pour ces comptes.' ?></p>
                <?php else: ?>
                    <ol class="person-activity">
                    <?php foreach ($activity as $event): ?>
                        <li><a class="pu-link" href="<?= $h(url('admin/audit/' . (int) $event['id'])) ?>"><?= $h(audit_action_label_fr((string) $event['action'])) ?></a>
                        <span class="pu-muted"><?= $h($fmtDate((string) $event['created_at'])) ?> · Compte n° <?= (int) $event['user_id'] ?></span></li>
                    <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>
        </div>

        <section id="communautes" class="person-section" aria-labelledby="person-communities">
            <h2 class="person-heading" id="person-communities">Communautés et fiches</h2>
            <?php if ($memberships === [] && $departed === []): ?><p class="pa-card">Aucune fiche de communauté à afficher.</p><?php endif; ?>
            <?php foreach ($memberships as $pack): ?>
                <?php
                $m = is_array($pack['user'] ?? null) ? $pack['user'] : [];
                $pp = is_array($pack['personnel_profile'] ?? null) ? $pack['personnel_profile'] : [];
                $ex = is_array($pack['personnel_extras'] ?? null) ? $pack['personnel_extras'] : [];
                $grade = is_array($pack['grade'] ?? null) ? $pack['grade'] : null;
                $assign = is_array($pack['primary_assignment'] ?? null) ? $pack['primary_assignment'] : null;
                $uid = (int) ($m['id'] ?? 0);
                $tid = (int) ($m['tenant_id'] ?? 0);
                $tenantName = (string) ($m['tenant_name'] ?? '');
                $st = (string) ($m['status'] ?? '');
                $accountStatus = (string) ($m['account_status'] ?? $st);
                $isDeleted = !empty($m['deleted_at']);
                $isDefault = \App\Support\PortalAccessChoice::membershipIsPlaceholder($m);
                $matricule = trim((string) ($pp['matricule_internal'] ?? '')) ?: trim((string) ($ex['service_number'] ?? ''));
                $character = trim((string) ($pp['character_name'] ?? ''));
                $gradeLabel = '';
                if ($grade) {
                    $gradeLabel = trim((string) ($grade['short_name'] ?? $grade['name'] ?? $grade['label_long'] ?? ''));
                }
                $unitName = trim((string) ($assign['unit_name'] ?? ''));
                $roleNames = is_array($pack['role_names'] ?? null) ? $pack['role_names'] : [];
                ?>
                <article class="pa-card">
                    <div class="pa-card__head">
                        <div>
                            <h3 class="pa-card__title"><?= $isDefault ? 'Compte sans communauté' : 'Dossier — ' . $h($tenantName !== '' ? $tenantName : 'Communauté') ?></h3>
                            <p class="pa-card__help">Compte n° <?= $uid ?></p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <?php if ($isDeleted): ?>
                                <?= \App\Support\ConfidentialDisplay::badge(\App\Support\ConfidentialDisplay::ACCOUNT_DELETED) ?>
                            <?php else: ?>
                                <span class="pa-pill pa-pill--slate"><?= $h($statusLabel($st)) ?></span>
                            <?php endif; ?>
                            <?php if ($isDefault): ?>
                                <span class="pa-pill pa-pill--amber">Sans communauté</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="person-facts">
                        <div>
                            <p class="person-label">Nom affiché</p>
                            <p><?= $empty($m['display_name'] ?? null) ?></p>
                        </div>
                        <div>
                            <p class="person-label">Indicatif</p>
                            <p><?= $empty($m['callsign'] ?? null) ?></p>
                        </div>
                        <div>
                            <p class="person-label">Rôle</p>
                            <p><?= $roleNames !== [] ? $h(implode(', ', $roleNames)) : $empty($m['role_name'] ?? null) ?></p>
                        </div>
                        <div>
                            <p class="person-label">Grade</p>
                            <p><?= $empty($gradeLabel) ?></p>
                        </div>
                        <div>
                            <p class="person-label">Matricule dossier</p>
                            <p class="pu-mono"><?= $empty($matricule) ?></p>
                        </div>
                        <div>
                            <p class="person-label">Affectation</p>
                            <p><?= $empty($unitName) ?></p>
                        </div>
                        <div>
                            <p class="person-label">Personnage</p>
                            <p><?= $empty($character) ?></p>
                        </div>
                        <div>
                            <p class="person-label">Indicatif Athena de la communauté</p>
                            <p class="pu-mono"><?= $empty($m['athena_identifier'] ?? null) ?></p>
                        </div>
                        <div>
                            <p class="person-label">Créé / MAJ</p>
                            <p class="pu-muted"><?= $h($fmtDate((string) ($m['created_at'] ?? ''))) ?> · <?= $h($fmtDate((string) ($m['updated_at'] ?? ''))) ?></p>
                        </div>
                        <?php if (!$isDeleted && $uid > 0): ?>
                        <div class="person-row-actions">
                            <a href="<?= $h(url('admin/users/' . $uid . '/edit')) ?>" class="pa-btn pa-btn--solid pa-btn--sm">Modifier le compte</a>
                            <?php if (!$isDefault): ?>
                            <a href="<?= $h(url('personnel/' . $uid)) ?>" class="pa-btn pa-btn--line pa-btn--sm">Fiche personnel</a>
                            <a href="<?= $h(url('back-office/users/' . $uid)) ?>" class="pa-btn pa-btn--line pa-btn--sm">Fiche communauté</a>
                            <?php endif; ?>
                            <?php if ($canManage && $ban === null && in_array($accountStatus, ['active', 'inactive'], true)): ?>
                                <?php $enable = $accountStatus === 'inactive'; $actionLabel = $enable ? 'Réactiver ce compte' : 'Désactiver ce compte';
                                $membershipAction('admin/users/set-status', ['user_id' => $uid, 'tenant_id' => $tid, 'status' => $enable ? 'active' : 'inactive'], $actionLabel,
                                    $actionLabel . ' ?', 'Le statut de connexion de ce compte change pour toutes les communautés qui l’utilisent.'); ?>
                            <?php endif; ?>
                            <?php if ($canManage && !$isDefault && !empty($personSupportsMemberships) && ($m['membership_status'] ?? '') === 'active'): ?>
                                <?php $membershipAction('admin/users/retirer-communaute', ['user_id' => $uid, 'tenant_id' => $tid], 'Retirer de la communauté',
                                    'Retirer de « ' . $tenantName . ' » ?', 'Le rattachement est retiré et les traces de jeu de cette communauté sont effacées. La réintégration est possible depuis ce dossier.'); ?>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
            <?php if ($departed !== []): ?>
                <section class="pa-card">
                    <h3 class="pa-card__title">Rattachements retirés</h3>
                    <ul class="person-list">
                        <?php foreach ($departed as $row): ?>
                            <li><span><?= $h((string) $row['tenant_name']) ?> · Compte n° <?= (int) $row['id'] ?></span>
                            <?php if ($canManage && $ban === null && !empty($personSupportsMemberships)): ?>
                                <?php $membershipAction('admin/users/reintegrer-communaute', ['user_id' => (int) $row['id'], 'tenant_id' => (int) $row['tenant_id']],
                                    'Réintégrer', 'Réintégrer dans « ' . (string) $row['tenant_name'] . ' » ?', 'Le rattachement et la fiche sont réactivés.'); ?>
                            <?php endif; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>
        </section>

        <section id="bannissement" class="pa-card person-section">
            <h2 class="pa-card__title">Bannissement du site</h2>
            <?php if ($ban !== null): ?>
                <?php $banEndsRaw = trim((string) ($ban['ends_at'] ?? '')); ?>
                <p class="person-alert">
                    Bannie le <?= $h($fmtDate((string) ($ban['created_at'] ?? ''))) ?>,
                    <?= $banEndsRaw !== '' ? 'jusqu’au ' . $h($fmtParis($banEndsRaw)) . ' (heure de Paris)' : 'sans date de fin' ?>.
                    Motif : <strong><?= $h((string) ($ban['reason'] ?? '')) ?></strong>
                </p>
                <p class="pa-card__help">
                    Ses fiches sont inactives, son adresse e-mail est refusée à la connexion et à l’inscription.
                    Lever le bannissement remet chaque fiche dans l’état d’avant et retire les blocages créés à ce moment.
                    <?= $banEndsRaw !== '' ? 'Sans action de votre part, la sanction se termine toute seule à la date indiquée.' : '' ?>
                </p>
                <form method="post" action="<?= $h(url('admin/users/lever-bannissement')) ?>" class="space-y-2"
                      onsubmit="return confirm('Lever le bannissement de cette personne ?');">
                    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="email" value="<?= $h($email) ?>">
                    <input type="hidden" name="return_person" value="<?= $h($email) ?>">
                    <label class="pu-dialog__field">
                        <span>Note de levée (facultatif, gardée dans l’historique)</span>
                        <input type="text" name="note" maxlength="500" placeholder="Ex. excuses reçues, erreur de modération">
                    </label>
                    <button type="submit" class="pa-btn pa-btn--solid">Lever le bannissement</button>
                </form>
            <?php else: ?>
                <p class="pa-card__help">Aucun bannissement du site en cours.</p>
            <?php endif; ?>
            <div class="person-row-actions">
                <?php if ($ban === null && $canManage): ?><button class="pa-btn pa-btn--danger pa-btn--sm" type="button" data-pu-open="person-ban-dialog" aria-haspopup="dialog" hidden data-person-enhanced>Bannir du site</button><?php endif; ?>
                <a class="pa-btn pa-btn--line pa-btn--sm" href="#historique-sanctions">Historique des sanctions (<?= count($banHistory) ?>)</a>
                <?php if ($appeal !== null): ?>
                    <?php $appealId = (int) $appeal['ban_id']; ?>
                    <a class="pa-btn pa-btn--solid pa-btn--sm" href="<?= $h(url('admin/sanctions') . '?contestation=' . $appealId . ($appeal['reviewed'] ? '&recours=traites' : '') . '#contestation-' . $appealId) ?>"><?= $appeal['reviewed'] ? 'Contestation traitée' : 'Examiner la contestation' ?></a>
                <?php endif; ?>
                <a class="pa-btn pa-btn--line pa-btn--sm" href="<?= $h(url('admin/sanctions')) ?>">Console des sanctions</a>
            </div>
        </section>

        <?php if ($ban === null && $canManage): ?>
            <dialog class="pu-dialog pu-dialog--wide" id="person-ban-dialog" aria-labelledby="person-ban-title">
                <form method="post" action="<?= $h(url('admin/users/bannir')) ?>" class="pu-dialog__form">
                    <h2 class="pu-dialog__title" id="person-ban-title">Bannir du site</h2>
                    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="email" value="<?= $h($email) ?>">
                    <input type="hidden" name="return_person" value="<?= $h($email) ?>">
                    <?php
                    $banForm = [
                        'id' => 'pu-ban-person',
                        'label' => $displayName !== '' ? $displayName : ($callsign !== '' ? $callsign : $email),
                        'email' => $email,
                        'fiches' => (int) ($personLiveFiches ?? 0),
                        'prior' => count($banHistory),
                        'history_url' => '#historique-sanctions',
                        'supports_end' => !empty($personBanSupportsEnd),
                        'supports_notice' => !empty($personBanSupportsNotice),
                        'steam_id' => $steamId,
                        'known_ips' => $knownIps,
                        'shared' => false,
                    ];
                    require base_path('views/admin/system/partials/platform_ban_form.php');
                    ?>
                    <div class="pu-dialog__buttons">
                        <button class="pa-btn pa-btn--line" type="button" data-pu-close>Annuler</button>
                        <button type="submit" class="pa-btn pa-btn--danger">Bannir du site</button>
                    </div>
                </form>
            </dialog>
        <?php endif; ?>

        <section id="historique-sanctions" class="pa-card person-section">
            <h2 class="pa-card__title">Historique des sanctions du site</h2>
            <?php if ($banHistory === []): ?>
                <p class="pa-card__help">Aucune sanction du site n’a jamais été prise contre cette personne.</p>
            <?php else: ?>
                <p class="pa-card__help">Du plus récent au plus ancien : qui a décidé, quand, pour quel motif, et comment la sanction s’est terminée.</p>
                <ol class="pu-history">
                    <?php foreach ($banHistory as $item): ?>
                        <?php
                        $itemEnds = trim((string) ($item['ends_at'] ?? ''));
                        $itemLifted = trim((string) ($item['lifted_at'] ?? ''));
                        $itemLiftedBy = trim((string) ($item['lifted_by_user_id'] ?? ''));
                        $itemNow = gmdate('Y-m-d H:i:s');
                        $itemCurrent = $itemLifted === '' && ($itemEnds === '' || $itemEnds > $itemNow);
                        $who = static function (?string $name, ?string $call, string $fallback): string {
                            $name = trim((string) $name);
                            $call = trim((string) $call);

                            return $call !== '' ? $call : ($name !== '' ? $name : $fallback);
                        };
                        ?>
                        <li class="pu-history__item<?= $itemCurrent ? ' is-current' : '' ?>">
                            <div class="pu-history__head">
                                <span><?= $h($fmtDate((string) ($item['created_at'] ?? ''))) ?></span>
                                <?php if ($itemCurrent): ?>
                                    <span class="pa-pill pa-pill--rose">En cours</span>
                                <?php elseif ($itemLifted !== '' && $itemLiftedBy === ''): ?>
                                    <span class="pa-pill pa-pill--slate">Terminée (date de fin atteinte)</span>
                                <?php elseif ($itemLifted !== ''): ?>
                                    <span class="pa-pill pa-pill--mint">Levée</span>
                                <?php else: ?>
                                    <span class="pa-pill pa-pill--slate">Terminée (date de fin atteinte)</span>
                                <?php endif; ?>
                                <span class="pu-muted"><?= $itemEnds !== '' ? 'Temporaire, jusqu’au ' . $h($fmtParis($itemEnds)) : 'Sans date de fin' ?></span>
                            </div>
                            <p>Motif : <strong><?= $h((string) ($item['reason'] ?? '')) ?></strong></p>
                            <p class="pu-muted">
                                Décidée par <?= $h($who($item['banned_by_name'] ?? null, $item['banned_by_callsign'] ?? null, 'un administrateur')) ?>.
                                <?php if ($itemLifted !== '' && $itemLiftedBy !== ''): ?>
                                    Levée le <?= $h($fmtDate($itemLifted)) ?> par <?= $h($who($item['lifted_by_name'] ?? null, $item['lifted_by_callsign'] ?? null, 'un administrateur')) ?>.
                                <?php elseif ($itemLifted !== ''): ?>
                                    Terminée le <?= $h($fmtDate($itemLifted)) ?>.
                                <?php endif; ?>
                                <?php if (trim((string) ($item['lift_note'] ?? '')) !== '' && $itemLiftedBy !== ''): ?>
                                    Note : <?= $h((string) $item['lift_note']) ?>
                                <?php endif; ?>
                            </p>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>

        <section id="reseau" class="pa-card person-section">
            <h2 class="pa-card__title">Adresses réseau (IP) utilisées</h2>
            <p class="pa-card__help">
                Connexions réussies des 6 derniers mois. Bloquer une adresse refuse la connexion et l’inscription depuis
                cette adresse pour tout le monde : attention aux connexions partagées (box familiale, université, VPN).
                Pour débloquer : <a href="<?= $h(url('admin/system/blocklist')) ?>" class="pu-link">liste des adresses et e-mails bloqués</a>.
            </p>
            <?php if ($knownIps === []): ?>
                <p class="pa-card__help">Aucune adresse réseau disponible.</p>
            <?php else: ?>
                <ul class="person-list">
                    <?php foreach ($knownIps as $row): ?>
                        <li>
                            <div>
                                <span class="pu-mono"><?= $h((string) $row['ip']) ?></span>
                                <span class="pu-muted">· <?= (int) $row['logins'] ?> connexion(s), dernière le <?= $h($fmtDate((string) $row['last_seen'])) ?></span>
                            </div>
                            <?php if (!empty($row['blocked'])): ?>
                                <span class="pa-pill pa-pill--rose">Bloquée</span>
                            <?php else: ?>
                                <form method="post" action="<?= $h(url('admin/users/bloquer-ip')) ?>" class="flex flex-wrap items-center gap-2"
                                      onsubmit="return confirm('Bloquer cette adresse réseau sur tout le site ?');">
                                    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="email" value="<?= $h($email) ?>">
                                    <input type="hidden" name="ip" value="<?= $h((string) $row['ip']) ?>">
                                    <input type="text" name="reason" maxlength="200" aria-label="Motif du blocage" placeholder="Motif (facultatif)" class="person-input">
                                    <button type="submit" class="pa-btn pa-btn--danger pa-btn--sm">Bloquer cette IP</button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <?php if ($mergeable): ?>
            <section id="fusion-comptes" class="pa-card person-section person-merge">
                <div class="pa-card__head">
                    <h2 class="pa-card__title">Fusionner les comptes</h2>
                    <p class="pa-card__help">
                        Plusieurs fiches <code class="font-mono text-xs">users</code> partagent cette adresse.
                        La fusion regroupe identité plateforme, profils et références sous un compte survivant.
                        Les champs déjà renseignés ne sont pas écrasés ; les zones vides sont complétées.
                    </p>
                </div>
                <form method="post" action="<?= $h(url('admin/users/merge')) ?>" class="space-y-4"
                      onsubmit="return confirm('Confirmer la fusion définitive de ces comptes ?\n\nLes fiches absorbées seront marquées « fusionnées » et leurs données rattachées au survivant.');">
                    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="email" value="<?= $h($email) ?>">

                    <fieldset class="space-y-2">
                        <legend class="person-label">Compte survivant</legend>
                        <?php foreach ($mergeRows as $row): ?>
                            <?php
                            $rid = (int) ($row['id'] ?? 0);
                            $rtid = (int) ($row['tenant_id'] ?? 0);
                            $rlabel = trim((string) ($row['display_name'] ?? ''));
                            if ($rlabel === '') {
                                $rlabel = trim((string) ($row['callsign'] ?? ''));
                            }
                            if ($rlabel === '') {
                                $rlabel = 'Compte #' . $rid;
                            }
                            $checked = $rid === $selectedSurvivorId || ($selectedSurvivorId < 1 && $rid === $suggestedSurvivorId);
                            ?>
                            <label class="person-merge-option">
                                <input type="radio" name="survivor_user_id" value="<?= $rid ?>" class="mt-1" <?= $checked ? 'checked' : '' ?>
                                       onchange="window.location.href='<?= $h(url('admin/users/person') . '?email=' . rawurlencode($email) . '&survivor_id=') ?>' + this.value + '#fusion-comptes';">
                                <span class="text-sm">
                                    <span><?= $h($rlabel) ?></span>
                                    <span class="pu-mono block">#<?= $rid ?> · tenant #<?= $rtid ?> · <?= $h((string) ($row['status'] ?? '')) ?></span>
                                    <?php if ($rid === $suggestedSurvivorId): ?>
                                        <span class="pa-pill pa-pill--mint">Recommandé</span>
                                    <?php endif; ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>

                    <?php if ($identityFills !== [] || $identityTableFills !== []): ?>
                        <div class="pa-flash pa-flash--ok">
                            <p class="font-semibold">Données à compléter sur le survivant</p>
                            <ul class="mt-1 list-disc pl-5 text-xs">
                                <?php foreach ($identityFills as $key => $value): ?>
                                    <li>Identité plateforme — <?= $h((string) $key) ?></li>
                                <?php endforeach; ?>
                                <?php foreach ($identityTableFills as $table => $fills): ?>
                                    <?php if (is_array($fills)): ?>
                                        <?php foreach (array_keys($fills) as $field): ?>
                                            <li><?= $h((string) $table) ?> — <?= $h((string) $field) ?></li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($steamCollisions !== []): ?>
                        <div class="pa-flash pa-flash--err">
                            <p class="font-semibold"><?= count($steamCollisions) ?> conflit(s) Steam détecté(s)</p>
                            <p class="mt-1 text-xs">Le Steam du survivant sera conservé ; les autres identifiants seront journalisés.</p>
                        </div>
                    <?php endif; ?>

                    <div class="grid gap-2 sm:grid-cols-[1fr_auto] sm:items-end">
                        <div>
                            <label for="confirm_email" class="block text-xs font-bold uppercase tracking-wider text-amber-950">
                                Confirmer l’adresse e-mail
                            </label>
                            <input type="email" name="confirm_email" id="confirm_email" required autocomplete="off"
                                   class="person-input"
                                   placeholder="<?= $h($email) ?>">
                        </div>
                        <button type="submit" class="pa-btn pa-btn--danger">
                            Fusionner les comptes
                        </button>
                    </div>
                </form>
            </section>
        <?php endif; ?>

    </div>
    <dialog class="pu-dialog pu-dialog--wide" id="person-picker-dialog" aria-labelledby="person-picker-title">
        <div class="pu-dialog__form">
            <h2 class="pu-dialog__title" id="person-picker-title">Ouvrir un autre dossier</h2>
            <?php
            $pickerType = 'account';
            $pickerId = 'person-picker-account';
            $pickerDestination = url('admin/users/person');
            require base_path('views/admin/system/partials/platform_entity_picker.php');
            $pickerType = 'organization';
            $pickerId = 'person-picker-org';
            $pickerDestination = url('admin/tenants');
            require base_path('views/admin/system/partials/platform_entity_picker.php');
            ?>
            <button type="button" class="pa-btn pa-btn--line" data-pu-close>Fermer</button>
        </div>
    </dialog>
    <?php require base_path('views/admin/system/partials/platform_user_confirm.php'); ?>
</div>
<script src="<?= $h(asset_url('assets/js/platform-users-sheet.js')) ?>" defer></script>
<script src="<?= $h(asset_url('assets/js/platform-ban-notice.js')) ?>" defer></script>
<script src="<?= $h(asset_url('assets/js/platform-person.js')) ?>" defer></script>
<script src="<?= $h(asset_url('assets/js/platform-entity-picker.js')) ?>" defer></script>
