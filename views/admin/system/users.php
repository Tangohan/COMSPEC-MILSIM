<?php
declare(strict_types=1);

$groups = is_array($platformUserGroups ?? null) ? $platformUserGroups : [];
$total = (int) ($platformUsersTotal ?? 0);
$page = max(1, (int) ($platformUsersPage ?? 1));
$pages = max(1, (int) ($platformUsersPages ?? 1));
$q = (string) ($platformUsersQ ?? '');
$statusFilter = (string) ($platformUsersStatus ?? '');
$tenantFilter = (int) ($platformUsersTenantId ?? 0);
$tenants = is_array($platformTenants ?? null) ? $platformTenants : [];
$bansCount = (int) ($platformBansCount ?? 0);
$pendingPurgeRequests = is_array($pendingPurgeRequests ?? null) ? $pendingPurgeRequests : [];
$pendingPurgeRequestsCount = (int) ($pendingPurgeRequestsCount ?? count($pendingPurgeRequests));

$currentActorId = (int) \App\Core\Session::get('user_id');
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$csrf = $h(\App\Core\Csrf::token());
$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');

$statusOptions = [
    '' => 'Tous les comptes',
    'active' => 'Actifs',
    'inactive' => 'Accès suspendu',
    'pending_verification' => 'E-mail pas encore vérifié',
    'no_org' => 'Sans communauté',
    'banned' => 'Bannis du site',
    'deleted' => 'Comptes anonymisés',
];

$hiddenReturns = static function () use ($h, $q, $statusFilter, $tenantFilter, $page): string {
    return '<input type="hidden" name="return_q" value="' . $h($q) . '">'
        . '<input type="hidden" name="return_status" value="' . $h($statusFilter) . '">'
        . '<input type="hidden" name="return_tenant_id" value="' . ($tenantFilter > 0 ? $tenantFilter : '') . '">'
        . '<input type="hidden" name="return_page" value="' . $page . '">';
};

$queryUrl = static function (array $overrides) use ($q, $statusFilter, $tenantFilter, $page): string {
    $params = [
        'q' => $overrides['q'] ?? $q,
        'status' => $overrides['status'] ?? $statusFilter,
        'tenant_id' => $overrides['tenant_id'] ?? ($tenantFilter > 0 ? (string) $tenantFilter : ''),
        'page' => (string) ($overrides['page'] ?? $page),
    ];
    $bits = [];
    foreach ($params as $key => $value) {
        $value = trim((string) $value);
        if ($value === '' || ($key === 'page' && $value === '1')) {
            continue;
        }
        $bits[] = rawurlencode($key) . '=' . rawurlencode($value);
    }
    $base = url('admin/users');

    return $bits === [] ? $base : $base . '?' . implode('&', $bits);
};

$fmtDate = static function (?string $raw): string {
    $ts = $raw !== null && $raw !== '' ? strtotime($raw) : false;

    return $ts ? date('d/m/Y', $ts) : '';
};

/** Formulaire POST discret ; la confirmation (fenêtre) est gérée par le script en bas de page. */
$actionForm = static function (string $action, array $fields, string $label, string $btnClass, array $confirm = []) use ($h, $csrf, $hiddenReturns): string {
    $attrs = '';
    foreach ($confirm as $k => $v) {
        $attrs .= ' data-confirm-' . $k . '="' . $h((string) $v) . '"';
    }
    $html = '<form method="post" action="' . $h(url($action)) . '" class="pu-inline"' . ($confirm !== [] ? ' data-pu-confirm="1"' : '') . $attrs . '>'
        . '<input type="hidden" name="_csrf_token" value="' . $csrf . '">';
    foreach ($fields as $name => $value) {
        $html .= '<input type="hidden" name="' . $h((string) $name) . '" value="' . $h((string) $value) . '">';
    }

    return $html . $hiddenReturns() . '<button type="submit" class="' . $h($btnClass) . '">' . $h($label) . '</button></form>';
};
?>
<div class="pa pu">
    <div class="pa__frame">
        <header class="pa-hero">
            <p class="pa-crumb">
                <a href="<?= $h(url('admin')) ?>">Administration du site</a>
                <span aria-hidden="true"> / </span>
                Comptes utilisateurs
            </p>
            <div class="pa-hero__row">
                <div>
                    <p class="pa-hero__kicker">Comptes &amp; accès</p>
                    <h1 class="pa-hero__title">Comptes utilisateurs</h1>
                    <p class="pa-hero__lead">
                        Une carte par personne, regroupée par adresse e-mail, avec chacune de ses communautés.
                        Les actions vont de la plus douce (suspendre l’accès à une communauté) à la plus lourde
                        (supprimer le compte) : chacune explique ce qu’elle fait avant de s’exécuter.
                    </p>
                    <div class="pa-hero__actions">
                        <button type="button" class="pa-btn pa-btn--solid" data-pu-open="pu-guide">Quelle action choisir ?</button>
                        <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/system/blocklist')) ?>">Adresses IP et e-mails bloqués</a>
                        <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/tenants')) ?>">Communautés</a>
                    </div>
                </div>
                <aside class="pa-hero__meta">
                    <p class="pa-hero__meta-kicker">Aperçu</p>
                    <dl>
                        <div class="pa-hero__meta-row">
                            <dt>Personnes trouvées</dt>
                            <dd><?= number_format($total, 0, ',', ' ') ?></dd>
                        </div>
                        <div class="pa-hero__meta-row">
                            <dt>Bannies du site</dt>
                            <dd><a class="pu-meta-link" href="<?= $h($queryUrl(['status' => 'banned', 'page' => 1])) ?>"><?= $bansCount ?></a></dd>
                        </div>
                        <div class="pa-hero__meta-row">
                            <dt>Demandes de suppression</dt>
                            <dd><?= $pendingPurgeRequestsCount ?></dd>
                        </div>
                    </dl>
                </aside>
            </div>
        </header>

        <?php if (is_string($flashOk) && trim($flashOk) !== ''): ?>
            <p class="pa-flash pa-flash--ok" role="status"><?= $h($flashOk) ?></p>
        <?php endif; ?>
        <?php if (is_string($flashErr) && trim($flashErr) !== ''): ?>
            <p class="pa-flash pa-flash--err" role="alert"><?= $h($flashErr) ?></p>
        <?php endif; ?>

        <?php if ($pendingPurgeRequests !== []): ?>
            <section class="pa-card pu-requests" aria-labelledby="purge-requests-title">
                <p class="pa-card__kicker">À traiter</p>
                <h2 id="purge-requests-title" class="pa-card__title">
                    Demandes de suppression de compte
                    <span class="pa-pill pa-pill--amber"><?= $pendingPurgeRequestsCount ?></span>
                </h2>
                <p class="pa-card__help">
                    Un gestionnaire de communauté demande à effacer la fiche d’un ancien membre de sa communauté.
                    « Accepter » efface cette fiche dans cette communauté seulement : les messages et événements restent signés
                    « Ancien membre ». Les autres communautés de la personne ne bougent pas.
                </p>
                <ul class="pu-requests__list">
                    <?php foreach ($pendingPurgeRequests as $req): ?>
                        <?php
                        $reqId = (int) ($req['id'] ?? 0);
                        $reqTenant = (string) ($req['tenant_name'] ?? '');
                        $reqTarget = (string) ($req['target_display_name'] ?? 'Compte supprimé');
                        $reqEmail = (string) ($req['target_email'] ?? '');
                        $reqBy = (string) ($req['requester_display_name'] ?? '');
                        if ($reqBy === '') {
                            $reqBy = (string) ($req['requester_email'] ?? 'Gestionnaire');
                        }
                        $reqNote = trim((string) ($req['note'] ?? ''));
                        ?>
                        <li class="pu-request">
                            <div class="pu-request__body">
                                <p class="pu-request__who"><?= $h($reqTarget) ?> <span class="pu-muted">· <?= $h($reqTenant !== '' ? $reqTenant : '—') ?></span></p>
                                <?php if ($reqEmail !== ''): ?>
                                    <p class="pu-mono"><?= $h($reqEmail) ?></p>
                                <?php endif; ?>
                                <p class="pu-muted">Demandé par <?= $h($reqBy) ?> le <?= $h($fmtDate((string) ($req['created_at'] ?? ''))) ?></p>
                                <?php if ($reqNote !== ''): ?>
                                    <p class="pu-request__note"><?= $h($reqNote) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="pu-actions">
                                <?= $actionForm('admin/users/purge-requests/approve', ['request_id' => $reqId], 'Accepter et effacer la fiche', 'pa-btn pa-btn--sm pu-btn-danger', [
                                    'title' => 'Effacer la fiche de ' . $reqTarget . ' dans « ' . $reqTenant . ' » ?',
                                    'body' => "La fiche de cette communauté est effacée définitivement.\nL’historique reste signé « Ancien membre ».\nLes autres communautés de la personne ne changent pas.",
                                    'tone' => 'danger',
                                    'ok' => 'Effacer la fiche',
                                ]) ?>
                                <?= $actionForm('admin/users/purge-requests/reject', ['request_id' => $reqId], 'Refuser', 'pa-btn pa-btn--sm pa-btn--line') ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <form method="get" action="<?= $h(url('admin/users')) ?>" class="pa-filters pu-filters">
            <div class="pu-filters__grid">
                <div class="pa-field pu-filters__q">
                    <label for="platform-users-q">Rechercher une personne</label>
                    <input id="platform-users-q" type="search" name="q" value="<?= $h($q) ?>" placeholder="E-mail, nom ou indicatif">
                </div>
                <div class="pa-field">
                    <label for="platform-users-tenant">Communauté</label>
                    <select id="platform-users-tenant" name="tenant_id">
                        <option value="">Toutes</option>
                        <?php foreach ($tenants as $t): ?>
                            <?php $tid = (int) ($t['id'] ?? 0); ?>
                            <option value="<?= $tid ?>" <?= $tid === $tenantFilter ? 'selected' : '' ?>><?= $h(community_display_name($t)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="pa-field">
                    <label for="platform-users-status">État</label>
                    <select id="platform-users-status" name="status">
                        <?php foreach ($statusOptions as $value => $label): ?>
                            <option value="<?= $h($value) ?>" <?= $statusFilter === $value ? 'selected' : '' ?>><?= $h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="pu-filters__buttons">
                    <button type="submit" class="pa-btn pa-btn--solid">Filtrer</button>
                    <?php if ($q !== '' || $statusFilter !== '' || $tenantFilter > 0): ?>
                        <a href="<?= $h(url('admin/users')) ?>" class="pa-btn pa-btn--line">Tout afficher</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($tenantFilter > 0): ?>
                <p class="pu-filters__note">Le filtre par communauté montre ses membres actuels. Une personne retirée de cette communauté reste visible sans ce filtre, avec la mention « Retiré ».</p>
            <?php endif; ?>
        </form>

        <p class="pu-count">
            <?= $total === 0 ? 'Aucune personne trouvée.' : ($total === 1 ? '1 personne' : number_format($total, 0, ',', ' ') . ' personnes') ?>
            <?php if ($pages > 1): ?>
                <span class="pu-muted">· page <?= $page ?> sur <?= $pages ?></span>
            <?php endif; ?>
        </p>

        <?php if ($groups === []): ?>
            <div class="pa-card pu-empty">
                <p>Aucun compte ne correspond à ces critères.</p>
            </div>
        <?php endif; ?>

        <div class="pu-list">
            <?php foreach ($groups as $gi => $group): ?>
                <?php
                $email = (string) ($group['email'] ?? '');
                $display = trim((string) ($group['display_name'] ?? ''));
                $callsign = trim((string) ($group['callsign'] ?? ''));
                $memberships = is_array($group['memberships'] ?? null) ? $group['memberships'] : [];
                $anchor = is_array($group['account_anchor'] ?? null) ? $group['account_anchor'] : null;
                $primary = $callsign !== '' ? $callsign : ($display !== '' ? $display : $email);
                $ban = is_array($group['ban'] ?? null) ? $group['ban'] : null;
                $noOrg = !empty($group['no_org']);
                $knownIps = is_array($group['known_ips'] ?? null) ? $group['known_ips'] : [];
                $anchorId = (int) ($anchor['id'] ?? 0);
                $anchorTid = (int) ($anchor['tenant_id'] ?? 0);
                $isSelf = $anchorId === $currentActorId;
                foreach ($memberships as $m) {
                    if ((int) ($m['id'] ?? 0) === $currentActorId) {
                        $isSelf = true;
                    }
                }
                $isAnonymized = $anchor !== null && !empty($anchor['deleted_at']);
                $distinctLive = [];
                foreach ($memberships as $m) {
                    if ((int) ($m['id'] ?? 0) > 0 && empty($m['deleted_at']) && (string) ($m['status'] ?? '') !== 'merged') {
                        $distinctLive[(int) $m['id']] = true;
                    }
                }
                $personUrl = url('admin/users/person') . '?email=' . rawurlencode($email);
                $banDialogId = 'pu-ban-' . $gi;
                $initial = mb_strtoupper(mb_substr($primary !== '' ? $primary : '?', 0, 1));
                ?>
                <article class="pu-person<?= $ban !== null ? ' is-banned' : '' ?>">
                    <header class="pu-person__head">
                        <span class="pu-avatar" aria-hidden="true"><?= $h($initial) ?></span>
                        <div class="pu-person__id">
                            <h2 class="pu-person__name">
                                <a class="pu-person__link" href="<?= $h($personUrl) ?>"><?= $h($primary) ?></a>
                                <?php if ($ban !== null): ?>
                                    <span class="pa-pill pa-pill--rose">Banni du site</span>
                                <?php endif; ?>
                                <?php if ($noOrg && !$isAnonymized): ?>
                                    <span class="pa-pill pa-pill--slate" title="Aucune communauté active : la personne a un compte seul, elle peut rejoindre ou créer une communauté.">Sans communauté</span>
                                <?php endif; ?>
                                <?php if ($isAnonymized): ?>
                                    <span class="pa-pill pa-pill--slate">Compte anonymisé</span>
                                <?php endif; ?>
                            </h2>
                            <p class="pu-person__sub">
                                <?php if ($display !== '' && strcasecmp($display, $primary) !== 0): ?>
                                    <span><?= $h($display) ?></span>
                                <?php endif; ?>
                                <span class="pu-mono"><?= $h($email) ?></span>
                            </p>
                            <p class="pu-person__links">
                                <a class="pu-link" href="<?= $h($personUrl) ?>">Dossier complet</a>
                                <?php if ($anchorId > 0 && !$isAnonymized): ?>
                                    <a class="pu-link" href="<?= $h(url('admin/users/' . $anchorId . '/edit')) ?>">Modifier le compte</a>
                                <?php endif; ?>
                                <?php if (count($distinctLive) > 1): ?>
                                    <a class="pu-link pu-link--warn" href="<?= $h($personUrl . '#fusion-comptes') ?>">Réunir ses <?= count($distinctLive) ?> comptes</a>
                                <?php endif; ?>
                            </p>
                            <?php if ($ban !== null): ?>
                                <p class="pu-ban-note">
                                    Banni le <?= $h($fmtDate((string) ($ban['created_at'] ?? ''))) ?>
                                    <?php if (trim((string) ($ban['reason'] ?? '')) !== ''): ?>
                                        · motif : <?= $h((string) $ban['reason']) ?>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="pu-person__actions">
                            <?php if ($isSelf): ?>
                                <span class="pu-muted">C’est votre compte</span>
                            <?php elseif (!$isAnonymized && $anchorId > 0): ?>
                                <?php if ($ban !== null): ?>
                                    <?= $actionForm('admin/users/lever-bannissement', ['email' => $email], 'Lever le bannissement', 'pa-btn pa-btn--sm pa-btn--line', [
                                        'title' => 'Lever le bannissement de ' . $primary . ' ?',
                                        'body' => "La personne peut de nouveau se connecter et s’inscrire.\nSes fiches retrouvent l’état qu’elles avaient avant le bannissement.\nLes blocages (e-mail, Steam, IP) créés par ce bannissement sont retirés.",
                                        'ok' => 'Lever le bannissement',
                                    ]) ?>
                                <?php else: ?>
                                    <button type="button" class="pa-btn pa-btn--sm pu-btn-danger-soft" data-pu-open="<?= $h($banDialogId) ?>">Bannir du site</button>
                                <?php endif; ?>
                                <details class="pu-more">
                                    <summary class="pa-btn pa-btn--sm pa-btn--line" aria-label="Autres actions sur le compte">Supprimer…</summary>
                                    <div class="pu-more__menu">
                                        <p class="pu-more__title">Actions sur tout le compte</p>
                                        <?= $actionForm('admin/users/delete', ['user_id' => $anchorId, 'tenant_id' => $anchorTid, 'scope' => 'site'], 'Anonymiser le compte', 'pu-menu-item', [
                                            'title' => 'Anonymiser le compte de ' . $primary . ' ?',
                                            'body' => "Le nom, l’e-mail et les données personnelles sont effacés dans toutes ses communautés.\nSes messages et participations restent, signés « Compte supprimé ».\nL’adresse e-mail redevient libre. On ne peut pas revenir en arrière.",
                                            'tone' => 'danger',
                                            'ok' => 'Anonymiser',
                                        ]) ?>
                                        <p class="pu-more__hint">Efface l’identité, garde l’historique. Pour une demande RGPD.</p>
                                        <?= $actionForm('admin/users/purge', ['user_id' => $anchorId, 'tenant_id' => $anchorTid, 'scope' => 'site', 'confirm_email' => ''], 'Supprimer définitivement', 'pu-menu-item pu-menu-item--danger', [
                                            'title' => 'Supprimer définitivement le compte de ' . $primary . ' ?',
                                            'body' => "Toutes les fiches de cette adresse sont effacées de la base, dans toutes ses communautés.\nRien ne peut être restauré.",
                                            'tone' => 'danger',
                                            'type' => $email,
                                            'ok' => 'Supprimer définitivement',
                                        ]) ?>
                                        <p class="pu-more__hint">Efface tout. À réserver aux faux comptes et comptes de test.</p>
                                    </div>
                                </details>
                            <?php endif; ?>
                        </div>
                    </header>

                    <div class="pu-rows">
                        <?php if ($memberships === []): ?>
                            <div class="pu-row pu-row--solo">
                                <div class="pu-row__main">
                                    <p class="pu-row__name">Aucune communauté</p>
                                    <p class="pu-muted">Compte seul : la personne peut rejoindre une communauté avec un code ou en créer une.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php foreach ($memberships as $m): ?>
                            <?php
                            $uid = (int) ($m['id'] ?? 0);
                            $tid = (int) ($m['tenant_id'] ?? 0);
                            $tenantName = (string) ($m['tenant_name'] ?? '');
                            $roleName = trim((string) ($m['role_name'] ?? ''));
                            $st = (string) ($m['status'] ?? '');
                            $ms = (string) ($m['membership_status'] ?? 'active');
                            $isDeleted = !empty($m['deleted_at']);
                            $isLeft = !$isDeleted && $ms !== 'active';
                            $mCallsign = trim((string) ($m['callsign'] ?? ''));
                            $fields = ['user_id' => $uid, 'tenant_id' => $tid];
                            if ($isDeleted) {
                                [$pill, $pillText] = ['pa-pill--slate', 'Compte anonymisé'];
                            } elseif ($isLeft) {
                                $leftAt = $fmtDate((string) ($m['membership_left_at'] ?? ''));
                                [$pill, $pillText] = ['pa-pill--slate', 'Retiré' . ($leftAt !== '' ? ' le ' . $leftAt : '')];
                            } elseif ($st === 'active') {
                                [$pill, $pillText] = ['pa-pill--mint', 'Membre actif'];
                            } elseif ($st === 'pending_verification') {
                                [$pill, $pillText] = ['pa-pill--amber', 'E-mail pas encore vérifié'];
                            } else {
                                [$pill, $pillText] = ['pa-pill--amber', 'Accès suspendu'];
                            }
                            ?>
                            <div class="pu-row<?= $isLeft || $isDeleted ? ' is-past' : '' ?>">
                                <div class="pu-row__main">
                                    <p class="pu-row__name"><?= $h($tenantName !== '' ? $tenantName : '—') ?></p>
                                    <p class="pu-muted">
                                        <?= $h($roleName !== '' ? $roleName : 'Sans rôle') ?>
                                        <?php if ($mCallsign !== '' && $mCallsign !== 'Compte supprimé'): ?>
                                            · <?= $h($mCallsign) ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <span class="pa-pill <?= $h($pill) ?> pu-row__state"><?= $h($pillText) ?></span>
                                <div class="pu-actions pu-row__actions">
                                    <?php if ($uid === $currentActorId): ?>
                                        <span class="pu-muted">Vous</span>
                                    <?php elseif ($isDeleted): ?>
                                        <?= $actionForm('admin/users/purge', $fields + ['scope' => 'org', 'confirm_email' => ''], 'Effacer cette fiche', 'pa-btn pa-btn--sm pa-btn--line', [
                                            'title' => 'Effacer la fiche anonymisée dans « ' . $tenantName . ' » ?',
                                            'body' => "La fiche « Compte supprimé » disparaît de l’annuaire de cette communauté.\nRien ne peut être restauré.",
                                            'tone' => 'danger',
                                            'type' => $email,
                                            'ok' => 'Effacer',
                                        ]) ?>
                                    <?php elseif ($isLeft): ?>
                                        <?php if ($ban === null): ?>
                                            <?= $actionForm('admin/users/reintegrer-communaute', $fields, 'Réintégrer', 'pa-btn pa-btn--sm pa-btn--line', [
                                                'title' => 'Réintégrer ' . $primary . ' dans « ' . $tenantName . ' » ?',
                                                'body' => "La personne redevient membre avec sa fiche, son rôle et son historique d’avant.",
                                                'ok' => 'Réintégrer',
                                            ]) ?>
                                        <?php endif; ?>
                                        <?= $actionForm('admin/users/purge', $fields + ['scope' => 'org', 'confirm_email' => ''], 'Effacer sa fiche ici', 'pa-btn pa-btn--sm pu-btn-ghost-danger', [
                                            'title' => 'Effacer la fiche de ' . $primary . ' dans « ' . $tenantName . ' » ?',
                                            'body' => "Sa fiche dans cette communauté est effacée de la base (grade, dossier, rôle…).\nSes autres communautés et son compte ne changent pas.\nRien ne peut être restauré.",
                                            'tone' => 'danger',
                                            'type' => $email,
                                            'ok' => 'Effacer la fiche',
                                        ]) ?>
                                    <?php else: ?>
                                        <a class="pa-btn pa-btn--sm pa-btn--line" href="<?= $h(url('admin/users/' . $uid . '/edit')) ?>">Modifier</a>
                                        <?php if ($ban === null): ?>
                                            <?php if ($st === 'active'): ?>
                                                <?= $actionForm('admin/users/set-status', $fields + ['status' => 'inactive'], 'Suspendre', 'pa-btn pa-btn--sm pa-btn--line', [
                                                    'title' => 'Suspendre l’accès de ' . $primary . ' à « ' . $tenantName . ' » ?',
                                                    'body' => "La personne reste membre mais ne peut plus entrer dans cette communauté.\nSes autres communautés ne changent pas. « Rétablir » annule.",
                                                    'ok' => 'Suspendre',
                                                ]) ?>
                                            <?php else: ?>
                                                <?= $actionForm('admin/users/set-status', $fields + ['status' => 'active'], 'Rétablir l’accès', 'pa-btn pa-btn--sm pa-btn--line') ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?= $actionForm('admin/users/retirer-communaute', $fields, 'Retirer', 'pa-btn pa-btn--sm pu-btn-ghost-danger', [
                                            'title' => 'Retirer ' . $primary . ' de « ' . $tenantName . ' » ?',
                                            'body' => "La personne ne fait plus partie de cette communauté et n’y a plus accès.\nRien n’est effacé : sa fiche reste ici avec la mention « Retiré », et « Réintégrer » annule.",
                                            'ok' => 'Retirer',
                                        ]) ?>
                                    <?php endif; ?>
                                    <a class="pu-icon-link" href="<?= $h(url('admin/system/member-sanctions') . ($tid > 0 ? '?tenant_id=' . $tid : '')) ?>" title="Sanctions dans cette communauté (avertissement, restriction du forum…)">Sanctions</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($ban === null && !$isSelf && !$isAnonymized && $anchorId > 0): ?>
                        <dialog id="<?= $h($banDialogId) ?>" class="pu-dialog" aria-labelledby="<?= $h($banDialogId) ?>-title">
                            <form method="post" action="<?= $h(url('admin/users/bannir')) ?>" class="pu-dialog__form">
                                <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                                <input type="hidden" name="email" value="<?= $h($email) ?>">
                                <?= $hiddenReturns() ?>
                                <p class="pu-dialog__kicker">Bannir du site</p>
                                <h3 id="<?= $h($banDialogId) ?>-title" class="pu-dialog__title"><?= $h($primary) ?></h3>
                                <ul class="pu-dialog__facts">
                                    <li>Ses sessions sont fermées et ses fiches passent « inactif » dans toutes ses communautés.</li>
                                    <li>L’adresse <strong><?= $h($email) ?></strong> ne peut plus se connecter ni créer de compte.</li>
                                    <li>Rien n’est effacé. « Lever le bannissement » remet tout comme avant.</li>
                                </ul>
                                <label class="pu-dialog__field">
                                    <span>Motif (gardé dans le journal)</span>
                                    <textarea name="reason" rows="2" required maxlength="500" placeholder="Ex. insultes répétées sur le forum, triche"></textarea>
                                </label>
                                <fieldset class="pu-dialog__field">
                                    <legend>Bloquer aussi</legend>
                                    <label class="pu-check"><input type="checkbox" name="block_steam" value="1" checked> Son Steam ID dans le mod (s’il est lié)</label>
                                    <?php foreach ($knownIps as $ipRow): ?>
                                        <label class="pu-check">
                                            <input type="checkbox" name="block_ips[]" value="<?= $h((string) $ipRow['ip']) ?>">
                                            Adresse IP <span class="pu-mono"><?= $h((string) $ipRow['ip']) ?></span>
                                            <span class="pu-muted">· dernière connexion <?= $h($fmtDate((string) $ipRow['last_seen'])) ?>, <?= (int) $ipRow['logins'] ?> fois</span>
                                        </label>
                                    <?php endforeach; ?>
                                    <?php if ($knownIps === []): ?>
                                        <p class="pu-muted">Aucune adresse IP de connexion connue sur les 6 derniers mois.</p>
                                    <?php endif; ?>
                                    <label class="pu-dialog__sub">
                                        <span>Autre adresse IP (facultatif)</span>
                                        <input type="text" name="extra_ips" placeholder="Ex. 88.163.184.156" autocomplete="off" inputmode="decimal">
                                    </label>
                                    <p class="pu-muted">Une adresse IP peut être partagée (box familiale, partage de connexion) : ne la bloquez que si vous êtes sûr.</p>
                                </fieldset>
                                <div class="pu-dialog__buttons">
                                    <button type="button" class="pa-btn pa-btn--line" data-pu-close>Annuler</button>
                                    <button type="submit" class="pa-btn pu-btn-danger">Bannir du site</button>
                                </div>
                            </form>
                        </dialog>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($pages > 1): ?>
            <nav class="pu-pager" aria-label="Pagination">
                <?php if ($page > 1): ?>
                    <a href="<?= $h($queryUrl(['page' => $page - 1])) ?>" class="pa-btn pa-btn--line">Page précédente</a>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
                <span class="pu-muted">Page <?= $page ?> sur <?= $pages ?></span>
                <?php if ($page < $pages): ?>
                    <a href="<?= $h($queryUrl(['page' => $page + 1])) ?>" class="pa-btn pa-btn--line">Page suivante</a>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>

        <section class="pa-card pu-cleanup">
            <p class="pa-card__kicker">Entretien</p>
            <h2 class="pa-card__title">Nettoyer les comptes anonymisés</h2>
            <p class="pa-card__help">
                Après une anonymisation, une fiche « Compte supprimé » reste dans l’annuaire de chaque communauté.
                Ce nettoyage les retire toutes d’un coup. Les messages, événements et participations restent,
                signés « Ancien membre ». Aucun compte actif n’est touché.
            </p>
            <?= $actionForm('admin/users/purge-anonymises', ['confirm_phrase' => ''], 'Nettoyer les fiches anonymisées', 'pa-btn pa-btn--line', [
                'title' => 'Retirer toutes les fiches « Compte supprimé » ?',
                'body' => "Les fiches anonymisées disparaissent des annuaires de toutes les communautés.\nL’historique reste, signé « Ancien membre ». Aucun compte actif n’est touché.",
                'tone' => 'danger',
                'type' => 'supprimer definitivement',
                'ok' => 'Nettoyer',
            ]) ?>
        </section>
    </div>

    <dialog id="pu-guide" class="pu-dialog pu-dialog--wide" aria-labelledby="pu-guide-title">
        <div class="pu-dialog__form">
            <p class="pu-dialog__kicker">Aide</p>
            <h3 id="pu-guide-title" class="pu-dialog__title">Quelle action choisir ?</h3>
            <div class="pu-guide">
                <section>
                    <h4>Dans une communauté</h4>
                    <dl>
                        <div><dt>Suspendre <span class="pa-pill pa-pill--mint">réversible</span></dt><dd>La personne reste membre mais ne peut plus entrer dans cette communauté. Pour une pause ou une enquête.</dd></div>
                        <div><dt>Retirer <span class="pa-pill pa-pill--mint">réversible</span></dt><dd>Elle ne fait plus partie de la communauté. Sa fiche reste visible ici avec « Retiré » et peut être réintégrée.</dd></div>
                        <div><dt>Effacer sa fiche ici <span class="pa-pill pa-pill--rose">définitif</span></dt><dd>Efface son dossier dans cette communauté (grade, rôle, dossier). Ses autres communautés et son compte restent.</dd></div>
                        <div><dt>Sanctions</dt><dd>Avertissement, restriction du forum ou des modules, dans une communauté donnée.</dd></div>
                    </dl>
                </section>
                <section>
                    <h4>Sur tout le site</h4>
                    <dl>
                        <div><dt>Bannir du site <span class="pa-pill pa-pill--mint">réversible</span></dt><dd>Bloque la connexion et l’inscription (e-mail, Steam ID, IP au choix) sans rien effacer. Pour un fauteur de troubles.</dd></div>
                        <div><dt>Anonymiser le compte <span class="pa-pill pa-pill--rose">définitif</span></dt><dd>Efface l’identité partout, garde l’historique sous « Compte supprimé ». Pour une demande de suppression des données.</dd></div>
                        <div><dt>Supprimer définitivement <span class="pa-pill pa-pill--rose">définitif</span></dt><dd>Efface tout de la base. Pour les faux comptes et comptes de test.</dd></div>
                        <div><dt>Bloquer une adresse IP</dt><dd>Dans le dossier complet (rubrique Réseau) ou la liste « Adresses IP et e-mails bloqués ».</dd></div>
                    </dl>
                </section>
                <section>
                    <h4>Les communautés</h4>
                    <p>« Sans communauté » signale un compte seul, sans communauté active : il n’y a plus de fausse communauté « pas d’organisation » à gérer.
                        Une personne retirée d’une communauté reste dans l’annuaire avec son nom et la mention « Retiré le … » : « Réintégrer » la remet en place.</p>
                </section>
            </div>
            <div class="pu-dialog__buttons">
                <button type="button" class="pa-btn pa-btn--solid" data-pu-close>J’ai compris</button>
            </div>
        </div>
    </dialog>

    <dialog id="pu-confirm" class="pu-dialog" aria-labelledby="pu-confirm-title">
        <form method="dialog" class="pu-dialog__form">
            <p class="pu-dialog__kicker" id="pu-confirm-kicker">Confirmer</p>
            <h3 id="pu-confirm-title" class="pu-dialog__title"></h3>
            <ul class="pu-dialog__facts" id="pu-confirm-body"></ul>
            <label class="pu-dialog__field" id="pu-confirm-type-wrap" hidden>
                <span>Pour confirmer, tapez <strong class="pu-mono" id="pu-confirm-type-expected"></strong></span>
                <input type="text" id="pu-confirm-type" autocomplete="off" spellcheck="false">
            </label>
            <div class="pu-dialog__buttons">
                <button type="button" class="pa-btn pa-btn--line" data-pu-close>Annuler</button>
                <button type="button" class="pa-btn pa-btn--solid" id="pu-confirm-ok">Confirmer</button>
            </div>
        </form>
    </dialog>
</div>

<script>
(function () {
    function open(id) {
        var d = document.getElementById(id);
        if (d && typeof d.showModal === 'function') { d.showModal(); }
    }
    document.addEventListener('click', function (e) {
        var opener = e.target.closest('[data-pu-open]');
        if (opener) { e.preventDefault(); open(opener.getAttribute('data-pu-open')); return; }
        var closer = e.target.closest('[data-pu-close]');
        if (closer) { e.preventDefault(); var d = closer.closest('dialog'); if (d) { d.close(); } }
    });
    document.querySelectorAll('.pu-dialog').forEach(function (d) {
        d.addEventListener('click', function (e) { if (e.target === d) { d.close(); } });
    });

    var dlg = document.getElementById('pu-confirm');
    if (!dlg || typeof dlg.showModal !== 'function') { return; }
    var title = document.getElementById('pu-confirm-title');
    var body = document.getElementById('pu-confirm-body');
    var kicker = document.getElementById('pu-confirm-kicker');
    var typeWrap = document.getElementById('pu-confirm-type-wrap');
    var typeInput = document.getElementById('pu-confirm-type');
    var typeExpected = document.getElementById('pu-confirm-type-expected');
    var okBtn = document.getElementById('pu-confirm-ok');
    var pending = null;

    function norm(v) { return String(v || '').trim().toLowerCase(); }
    function refresh() {
        if (!pending) { return; }
        var expected = pending.getAttribute('data-confirm-type');
        okBtn.disabled = !!expected && norm(typeInput.value) !== norm(expected);
    }
    typeInput.addEventListener('input', refresh);

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || form.getAttribute('data-pu-confirm') !== '1') { return; }
        if (form.getAttribute('data-pu-confirmed') === '1') { form.removeAttribute('data-pu-confirmed'); return; }
        e.preventDefault();
        pending = form;
        var danger = form.getAttribute('data-confirm-tone') === 'danger';
        kicker.textContent = danger ? 'Action définitive' : 'Confirmer';
        kicker.classList.toggle('is-danger', danger);
        title.textContent = form.getAttribute('data-confirm-title') || 'Confirmer ?';
        body.innerHTML = '';
        String(form.getAttribute('data-confirm-body') || '').split('\n').forEach(function (line) {
            if (line.trim() === '') { return; }
            var li = document.createElement('li');
            li.textContent = line;
            body.appendChild(li);
        });
        var expected = form.getAttribute('data-confirm-type');
        typeWrap.hidden = !expected;
        typeInput.value = '';
        typeExpected.textContent = expected || '';
        okBtn.textContent = form.getAttribute('data-confirm-ok') || 'Confirmer';
        okBtn.className = 'pa-btn ' + (danger ? 'pu-btn-danger' : 'pa-btn--solid');
        dlg.showModal();
        refresh();
        if (expected) { typeInput.focus(); }
    }, true);

    okBtn.addEventListener('click', function () {
        if (!pending || okBtn.disabled) { return; }
        var form = pending;
        pending = null;
        form.querySelectorAll('input[name="confirm_email"], input[name="confirm_phrase"]').forEach(function (typed) {
            typed.value = typeInput.value.trim();
        });
        dlg.close();
        form.setAttribute('data-pu-confirmed', '1');
        if (typeof form.requestSubmit === 'function') { form.requestSubmit(); } else { form.submit(); }
    });
    dlg.addEventListener('close', function () { pending = null; });
})();
</script>
