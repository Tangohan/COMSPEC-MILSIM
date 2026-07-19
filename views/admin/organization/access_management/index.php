<?php
declare(strict_types=1);

$roles = is_array($roles ?? null) ? $roles : [];
$permissions = is_array($permissions ?? null) ? $permissions : [];
$rules = is_array($rules ?? null) ? $rules : [];
$logs = is_array($logs ?? null) ? $logs : [];
$users = is_array($users ?? null) ? $users : [];
$activeTab = (string) ($activeTab ?? 'roles');
if (!in_array($activeTab, ['roles', 'rules', 'matrix', 'simulation'], true)) {
    $activeTab = 'roles';
}

$successFlash = \App\Core\Session::getFlash('success');
$errorFlash = \App\Core\Session::getFlash('error');

$rolesCount = count($roles);
$rulesCount = count($rules);
$permsCount = count($permissions);
$activeRulesCount = 0;
foreach ($rules as $r) {
    if (!empty($r['is_active'])) {
        $activeRulesCount++;
    }
}

$effectLabel = static function (string $raw): string {
    return match (strtoupper($raw)) {
        'ALLOW' => 'Autorisé',
        'DENY' => 'Refusé',
        default => $raw !== '' ? $raw : '—',
    };
};

$effectBadgeClass = static function (string $raw): string {
    return match (strtoupper($raw)) {
        'ALLOW' => 'bo-access__badge--ok',
        'DENY' => 'bo-access__badge--deny',
        default => 'bo-access__badge--neutral',
    };
};

$targetTypeLabel = static function (string $raw): string {
    return match (strtoupper($raw)) {
        'ROLE' => 'Rôle',
        'USER' => 'Membre',
        default => 'Cible',
    };
};

$conditionTypeLabel = static function (string $raw): string {
    return match (strtoupper($raw)) {
        'DAYS_SINCE_CREATION' => 'Ancienneté du compte',
        'MODULE_VALIDATED' => 'Parcours validé',
        'UNIT' => 'Appartenance à une unité',
        'MANUAL_APPROVAL' => 'Validation manuelle',
        'STATUS' => 'Statut du compte',
        default => 'Condition',
    };
};

$actionLabel = static function (string $raw): string {
    return match (strtoupper($raw)) {
        'READ' => 'Consulter',
        'CREATE' => 'Créer',
        'UPDATE' => 'Modifier',
        'DELETE' => 'Supprimer',
        'EXPORT' => 'Exporter',
        'WRITE' => 'Modifier la politique',
        default => $raw !== '' ? $raw : '—',
    };
};

$resourceLabel = static function (string $raw): string {
    return match (strtolower($raw)) {
        'documents' => 'Documents',
        'courrier' => 'Courrier',
        'training' => 'Formations',
        'admin' => 'Administration',
        'role.created' => 'Création de rôle',
        'rule.created' => 'Création de règle',
        '*' => 'Tous les espaces',
        default => $raw !== '' ? $raw : '—',
    };
};

$reasonLabel = static function (string $raw): string {
    return match ($raw) {
        'platform_admin_bypass' => 'Accès administrateur plateforme',
        'rbac_denied' => 'Droit de base non accordé',
        'rbac_only_no_abac_rule' => 'Autorisé par le rôle (aucune règle particulière)',
        'abac_deny_rule' => 'Refusé par une règle particulière',
        'abac_allow_rule' => 'Autorisé par une règle particulière',
        'abac_deny_by_default' => 'Refusé par défaut (aucune règle d’autorisation)',
        'invalid_user_context' => 'Membre introuvable ou hors communauté',
        'policy_change' => 'Modification de la politique d’accès',
        default => $raw !== '' ? $raw : '—',
    };
};

$userStatusLabel = static function (string $raw): string {
    return match ($raw) {
        'active' => 'Compte actif',
        'inactive' => 'Compte inactif',
        'pending_verification' => 'En attente de vérification de l’e-mail',
        default => $raw !== '' ? $raw : '—',
    };
};

$userDisplay = static function (array $u): string {
    $dn = trim((string) ($u['display_name'] ?? ''));
    $cs = trim((string) ($u['callsign'] ?? ''));
    $em = trim((string) ($u['email'] ?? ''));
    if ($dn !== '') {
        return $dn;
    }
    if ($cs !== '') {
        return $cs;
    }
    if ($em !== '') {
        return $em;
    }

    return 'Membre #' . (int) ($u['id'] ?? 0);
};

$permissionsByCategory = [];
foreach ($permissions as $p) {
    $cat = trim((string) ($p['category'] ?? ''));
    if ($cat === '') {
        $cat = 'Divers';
    }
    $permissionsByCategory[$cat][] = $p;
}
ksort($permissionsByCategory, SORT_NATURAL | SORT_FLAG_CASE);

$categoryLabel = static function (string $cat): string {
    $map = [
        'admin' => 'Administration',
        'documents' => 'Documents',
        'forum' => 'Forum',
        'training' => 'Formations',
        'recruitment' => 'Recrutement',
        'personnel' => 'Effectifs',
        'courrier' => 'Courrier',
        'organization' => 'Organisation',
        'media' => 'Médias',
        'events' => 'Événements',
    ];
    $key = strtolower($cat);

    return $map[$key] ?? (ucfirst(str_replace(['_', '-'], ' ', $cat)));
};

$matrixResources = [
    'documents' => 'Documents',
    'courrier' => 'Courrier',
    'training' => 'Formations',
    'admin' => 'Administration',
];

$tabs = [
    'roles' => 'Rôles & droits',
    'rules' => 'Règles particulières',
    'matrix' => 'Vue d’ensemble',
    'simulation' => 'Tester un accès',
];

$tabLead = match ($activeTab) {
    'rules' => 'Définissez des exceptions conditionnelles (qui peut faire quoi, sous quelles conditions).',
    'matrix' => 'Repère rapide des espaces et des actions — à croiser avec vos rôles et règles.',
    'simulation' => 'Vérifiez ce qu’un membre peut faire avant de publier une règle.',
    default => 'Créez des rôles pour votre communauté et consultez le catalogue des droits disponibles.',
};
?>
<link href="<?= htmlspecialchars(asset_url('assets/css/back-office-access.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">

<div class="bo-access" x-data="{
    targetType: 'ROLE',
    conditionType: 'STATUS',
    simState: 'idle',
    simTitle: '',
    simDetail: ''
}">
    <header class="bo-access__hero">
        <div class="bo-access__hero-inner">
            <div>
                <p class="bo-access__eyebrow">Communauté · Habilitations</p>
                <h1 class="bo-access__title">Gestion des accès</h1>
                <p class="bo-access__lead">
                    Pilotez les rôles, les droits et les exceptions pour votre communauté.
                    Les profils prêts à l’emploi restent disponibles depuis les rôles communauté.
                </p>
            </div>
            <div class="bo-access__hero-actions">
                <a href="<?= htmlspecialchars(url('back-office/roles'), ENT_QUOTES, 'UTF-8') ?>" class="bo-access__btn bo-access__btn--ghost">Rôles communauté</a>
                <a href="<?= htmlspecialchars(url('back-office/roles/presets'), ENT_QUOTES, 'UTF-8') ?>" class="bo-access__btn bo-access__btn--ghost">Profils prêts</a>
                <a href="<?= htmlspecialchars(url('back-office'), ENT_QUOTES, 'UTF-8') ?>" class="bo-access__btn bo-access__btn--solid">Centre de pilotage</a>
            </div>
        </div>
    </header>

    <div class="bo-access__deck">
        <?php if ($successFlash): ?>
            <div class="bo-access__flash bo-access__flash--ok" role="status"><?= htmlspecialchars((string) $successFlash, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($errorFlash): ?>
            <div class="bo-access__flash bo-access__flash--err" role="alert"><?= htmlspecialchars((string) $errorFlash, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="bo-access__kpi-grid" aria-label="Synthèse des accès">
            <div class="bo-access__kpi">
                <p class="bo-access__kpi-label">Rôles</p>
                <p class="bo-access__kpi-value"><?= $rolesCount ?></p>
                <p class="bo-access__kpi-meta">Définis pour cette communauté</p>
            </div>
            <div class="bo-access__kpi">
                <p class="bo-access__kpi-label">Droits catalogue</p>
                <p class="bo-access__kpi-value"><?= $permsCount ?></p>
                <p class="bo-access__kpi-meta">Habilitations disponibles</p>
            </div>
            <div class="bo-access__kpi">
                <p class="bo-access__kpi-label">Règles actives</p>
                <p class="bo-access__kpi-value"><?= $activeRulesCount ?></p>
                <p class="bo-access__kpi-meta"><?= $rulesCount ?> règle<?= $rulesCount > 1 ? 's' : '' ?> au total</p>
            </div>
            <div class="bo-access__kpi">
                <p class="bo-access__kpi-label">Journal récent</p>
                <p class="bo-access__kpi-value"><?= count($logs) ?></p>
                <p class="bo-access__kpi-meta">Dernières décisions enregistrées</p>
            </div>
        </div>

        <div class="bo-access__toolbar">
            <div>
                <h2><?= htmlspecialchars($tabs[$activeTab], ENT_QUOTES, 'UTF-8') ?></h2>
                <p><?= htmlspecialchars($tabLead, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <nav class="bo-access__tabs" aria-label="Sections de la gestion des accès">
                <?php foreach ($tabs as $key => $label): ?>
                    <a
                        href="<?= htmlspecialchars(url('back-office/access-management?tab=' . $key), ENT_QUOTES, 'UTF-8') ?>"
                        class="bo-access__tab<?= $activeTab === $key ? ' is-active' : '' ?>"
                        <?= $activeTab === $key ? 'aria-current="page"' : '' ?>
                    ><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
                <?php endforeach; ?>
            </nav>
        </div>

        <?php if ($activeTab === 'roles'): ?>
            <div class="bo-access__grid bo-access__grid--2">
                <section class="bo-access__panel" aria-labelledby="access-role-form-title">
                    <div class="bo-access__panel-head">
                        <h2 id="access-role-form-title">Nouveau rôle</h2>
                        <p>Ajoutez un rôle pour organiser les responsabilités. L’affectation fine des droits se fait ensuite depuis les rôles communauté ou les profils prêts.</p>
                    </div>
                    <div class="bo-access__panel-body">
                        <form method="post" action="<?= htmlspecialchars(url('back-office/access-management/roles/save'), ENT_QUOTES, 'UTF-8') ?>">
                            <?= \App\Core\Csrf::field() ?>
                            <div class="bo-access__field">
                                <label class="bo-access__label" for="role-name">Nom du rôle</label>
                                <input id="role-name" name="name" class="bo-access__input" placeholder="Ex. Chef de section" required maxlength="120" autocomplete="off">
                            </div>
                            <div class="bo-access__field">
                                <label class="bo-access__label" for="role-level">Niveau de priorité</label>
                                <input id="role-level" name="level" type="number" class="bo-access__input" value="10" min="0" max="9999" step="1">
                                <p class="bo-access__hint">Plus le chiffre est élevé, plus le rôle est prioritaire dans les comparaisons hiérarchiques.</p>
                            </div>
                            <div class="bo-access__form-actions">
                                <button type="submit" class="bo-access__btn bo-access__btn--primary">Enregistrer le rôle</button>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="bo-access__panel" aria-labelledby="access-roles-list-title">
                    <div class="bo-access__panel-head">
                        <h2 id="access-roles-list-title">Rôles de la communauté</h2>
                        <p><?= $rolesCount ?> rôle<?= $rolesCount > 1 ? 's' : '' ?> listé<?= $rolesCount > 1 ? 's' : '' ?>.</p>
                    </div>
                    <div class="bo-access__panel-body">
                        <?php if ($roles === []): ?>
                            <div class="bo-access__empty">
                                <div class="bo-access__empty-icon" aria-hidden="true">∅</div>
                                <p>Aucun rôle pour le moment</p>
                                <span>Créez un premier rôle à gauche, ou appliquez un profil prêt depuis les rôles communauté.</span>
                            </div>
                        <?php else: ?>
                            <ul class="bo-access__list">
                                <?php foreach ($roles as $role): ?>
                                    <li class="bo-access__list-item">
                                        <strong><?= htmlspecialchars((string) ($role['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                                        <div class="bo-access__list-meta">
                                            <span class="bo-access__badge bo-access__badge--neutral">Priorité <?= (int) ($role['level'] ?? 0) ?></span>
                                            <?php if (!empty($role['is_system'])): ?>
                                                <span class="bo-access__badge bo-access__badge--info">Rôle système</span>
                                            <?php else: ?>
                                                <span class="bo-access__badge bo-access__badge--ok">Personnalisé</span>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <section class="bo-access__panel" aria-labelledby="access-perms-title">
                <div class="bo-access__panel-head">
                    <h2 id="access-perms-title">Catalogue des droits</h2>
                    <p>Droits disponibles pour cette communauté. Pour les attribuer à un rôle, utilisez la page Rôles communauté ou les profils prêts.</p>
                </div>
                <div class="bo-access__panel-body">
                    <?php if ($permissions === []): ?>
                        <div class="bo-access__empty">
                            <div class="bo-access__empty-icon" aria-hidden="true">∅</div>
                            <p>Aucun droit catalogue</p>
                            <span>Le catalogue se remplit après la configuration initiale ou les migrations de permissions.</span>
                        </div>
                    <?php else: ?>
                        <div class="bo-access__perm-groups">
                            <?php foreach ($permissionsByCategory as $cat => $items): ?>
                                <div class="bo-access__perm-group">
                                    <h3><?= htmlspecialchars($categoryLabel((string) $cat), ENT_QUOTES, 'UTF-8') ?></h3>
                                    <div class="bo-access__perm-chips">
                                        <?php foreach ($items as $p): ?>
                                            <?php
                                            $label = trim((string) ($p['label'] ?? ''));
                                            if ($label === '') {
                                                $label = 'Droit #' . (int) ($p['id'] ?? 0);
                                            }
                                            ?>
                                            <span class="bo-access__chip" title="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($activeTab === 'rules'): ?>
            <div class="bo-access__grid bo-access__grid--2">
                <section class="bo-access__panel" aria-labelledby="access-rule-form-title">
                    <div class="bo-access__panel-head">
                        <h2 id="access-rule-form-title">Créer une règle</h2>
                        <p>Si la condition est remplie pour la cible choisie, alors l’action sur l’espace indiqué est autorisée ou refusée.</p>
                    </div>
                    <div class="bo-access__panel-body">
                        <form method="post" action="<?= htmlspecialchars(url('back-office/access-management/rules/save'), ENT_QUOTES, 'UTF-8') ?>">
                            <?= \App\Core\Csrf::field() ?>

                            <div class="bo-access__field">
                                <label class="bo-access__label" for="rule-name">Nom de la règle</label>
                                <input id="rule-name" name="name" class="bo-access__input" placeholder="Ex. Accès documents après validation" required maxlength="160">
                            </div>
                            <div class="bo-access__field">
                                <label class="bo-access__label" for="rule-description">Description (optionnel)</label>
                                <textarea id="rule-description" name="description" class="bo-access__textarea" placeholder="Précisez l’intention métier pour vos collègues administrateurs."></textarea>
                            </div>

                            <div class="bo-access__row bo-access__row--2">
                                <div class="bo-access__field">
                                    <label class="bo-access__label" for="rule-target-type">S’applique à</label>
                                    <select id="rule-target-type" name="target_type" class="bo-access__select" x-model="targetType">
                                        <option value="ROLE">Un rôle</option>
                                        <option value="USER">Un membre</option>
                                    </select>
                                </div>
                                <div class="bo-access__field">
                                    <label class="bo-access__label" for="rule-target-id">Cible</label>
                                    <select
                                        id="rule-target-id"
                                        name="target_id"
                                        class="bo-access__select"
                                        required
                                        @change="const opt = $event.target.selectedOptions[0]; if (opt && opt.dataset.kind) { targetType = opt.dataset.kind }"
                                    >
                                        <option value="">— Choisir —</option>
                                        <?php if ($roles !== []): ?>
                                            <optgroup label="Rôles">
                                                <?php foreach ($roles as $role): ?>
                                                    <option value="<?= (int) ($role['id'] ?? 0) ?>" data-kind="ROLE">
                                                        <?= htmlspecialchars((string) ($role['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endif; ?>
                                        <?php if ($users !== []): ?>
                                            <optgroup label="Membres">
                                                <?php foreach ($users as $u): ?>
                                                    <option value="<?= (int) ($u['id'] ?? 0) ?>" data-kind="USER">
                                                        <?= htmlspecialchars($userDisplay($u), ENT_QUOTES, 'UTF-8') ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endif; ?>
                                    </select>
                                    <p class="bo-access__hint">Le type « S’applique à » se met à jour automatiquement selon la cible choisie.</p>
                                </div>
                            </div>

                            <div class="bo-access__row bo-access__row--2">
                                <div class="bo-access__field">
                                    <label class="bo-access__label" for="rule-condition">Condition</label>
                                    <select id="rule-condition" name="condition_type" class="bo-access__select" x-model="conditionType">
                                        <option value="STATUS">Statut du compte</option>
                                        <option value="DAYS_SINCE_CREATION">Ancienneté du compte</option>
                                        <option value="MODULE_VALIDATED">Parcours validé</option>
                                        <option value="UNIT">Appartenance à une unité</option>
                                        <option value="MANUAL_APPROVAL">Validation manuelle</option>
                                    </select>
                                </div>
                                <div class="bo-access__field">
                                    <label class="bo-access__label" for="rule-effect">Effet</label>
                                    <select id="rule-effect" name="effect" class="bo-access__select">
                                        <option value="ALLOW">Autoriser</option>
                                        <option value="DENY">Refuser</option>
                                    </select>
                                </div>
                            </div>

                            <div class="bo-access__condition-box">
                                <h3>Détail de la condition</h3>

                                <div x-show="conditionType === 'STATUS'" x-cloak>
                                    <p class="bo-access__hint" style="margin-bottom:0.65rem">Cochez les statuts de compte pour lesquels la règle s’applique.</p>
                                    <div class="bo-access__check-grid">
                                        <label class="bo-access__check"><input type="checkbox" name="statuses[]" value="active" checked :disabled="conditionType !== 'STATUS'"> Compte actif</label>
                                        <label class="bo-access__check"><input type="checkbox" name="statuses[]" value="pending_verification" :disabled="conditionType !== 'STATUS'"> En attente de vérification de l’e-mail</label>
                                        <label class="bo-access__check"><input type="checkbox" name="statuses[]" value="inactive" :disabled="conditionType !== 'STATUS'"> Compte inactif</label>
                                    </div>
                                </div>

                                <div class="bo-access__field" x-show="conditionType === 'DAYS_SINCE_CREATION'" x-cloak>
                                    <label class="bo-access__label" for="rule-days">Jours minimum depuis la création du compte</label>
                                    <input id="rule-days" name="days" type="number" class="bo-access__input" value="0" min="0" max="3650" step="1">
                                </div>

                                <div class="bo-access__field" x-show="conditionType === 'MODULE_VALIDATED'" x-cloak>
                                    <label class="bo-access__label" for="rule-module">Parcours concerné</label>
                                    <input id="rule-module" name="module_id" type="number" class="bo-access__input" value="0" min="0" step="1" placeholder="Numéro du parcours">
                                    <p class="bo-access__hint">Indiquez le numéro du parcours de formation à valider (visible dans l’espace formation).</p>
                                </div>

                                <div class="bo-access__field" x-show="conditionType === 'UNIT'" x-cloak>
                                    <label class="bo-access__label" for="rule-unit">Unité concernée</label>
                                    <input id="rule-unit" name="unit_id" type="number" class="bo-access__input" value="0" min="0" step="1" placeholder="Numéro de l’unité">
                                    <p class="bo-access__hint">Numéro de l’unité dans l’organigramme de la communauté.</p>
                                </div>

                                <div x-show="conditionType === 'MANUAL_APPROVAL'" x-cloak>
                                    <p class="bo-access__hint" style="margin:0">La règle s’applique lorsque l’accès a été approuvé manuellement pour le membre.</p>
                                    <input type="hidden" name="approval_field" value="access_manually_approved">
                                </div>
                            </div>

                            <div class="bo-access__row bo-access__row--2">
                                <div class="bo-access__field">
                                    <label class="bo-access__label" for="rule-scope">Espace concerné</label>
                                    <select id="rule-scope" name="scope_identifier" class="bo-access__select">
                                        <option value="*">Tous les espaces</option>
                                        <option value="documents">Documents</option>
                                        <option value="courrier">Courrier</option>
                                        <option value="training">Formations</option>
                                        <option value="admin">Administration</option>
                                    </select>
                                    <input type="hidden" name="scope_type" value="MODULE">
                                </div>
                                <div class="bo-access__field">
                                    <label class="bo-access__label" for="rule-action">Action</label>
                                    <select id="rule-action" name="action" class="bo-access__select">
                                        <option value="READ">Consulter</option>
                                        <option value="CREATE">Créer</option>
                                        <option value="UPDATE">Modifier</option>
                                        <option value="DELETE">Supprimer</option>
                                        <option value="EXPORT">Exporter</option>
                                    </select>
                                </div>
                            </div>

                            <div class="bo-access__row bo-access__row--2">
                                <div class="bo-access__field">
                                    <label class="bo-access__label" for="rule-priority">Priorité</label>
                                    <input id="rule-priority" name="priority" type="number" class="bo-access__input" value="100" min="0" max="9999" step="1">
                                    <p class="bo-access__hint">Plus le chiffre est élevé, plus la règle l’emporte en cas de conflit.</p>
                                </div>
                                <div class="bo-access__field" style="justify-content:flex-end;padding-top:1.4rem">
                                    <label class="bo-access__check">
                                        <input type="checkbox" name="is_active" value="1" checked>
                                        Règle active immédiatement
                                    </label>
                                </div>
                            </div>

                            <div class="bo-access__form-actions">
                                <button type="submit" class="bo-access__btn bo-access__btn--primary">Créer la règle</button>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="bo-access__panel" aria-labelledby="access-rules-list-title">
                    <div class="bo-access__panel-head">
                        <h2 id="access-rules-list-title">Règles enregistrées</h2>
                        <p><?= $activeRulesCount ?> active<?= $activeRulesCount > 1 ? 's' : '' ?> sur <?= $rulesCount ?>.</p>
                    </div>
                    <div class="bo-access__panel-body">
                        <?php if ($rules === []): ?>
                            <div class="bo-access__empty">
                                <div class="bo-access__empty-icon" aria-hidden="true">∅</div>
                                <p>Aucune règle particulière</p>
                                <span>Sans règle, les droits de base des rôles s’appliquent. Créez une exception à gauche si besoin.</span>
                            </div>
                        <?php else: ?>
                            <ul class="bo-access__list">
                                <?php foreach ($rules as $r): ?>
                                    <?php
                                    $desc = trim((string) ($r['description'] ?? ''));
                                    $isActive = !empty($r['is_active']);
                                    $eff = (string) ($r['effect'] ?? '');
                                    ?>
                                    <li class="bo-access__list-item">
                                        <strong><?= htmlspecialchars((string) ($r['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                                        <?php if ($desc !== ''): ?>
                                            <p class="bo-access__hint" style="margin-top:0.35rem"><?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php endif; ?>
                                        <div class="bo-access__list-meta">
                                            <span class="bo-access__badge <?= htmlspecialchars($effectBadgeClass($eff), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($effectLabel($eff), ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="bo-access__badge bo-access__badge--neutral"><?= htmlspecialchars($targetTypeLabel((string) ($r['target_type'] ?? '')), ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="bo-access__badge bo-access__badge--info"><?= htmlspecialchars($conditionTypeLabel((string) ($r['condition_type'] ?? '')), ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="bo-access__badge bo-access__badge--neutral">Priorité <?= (int) ($r['priority'] ?? 0) ?></span>
                                            <?php if ($isActive): ?>
                                                <span class="bo-access__badge bo-access__badge--ok">Active</span>
                                            <?php else: ?>
                                                <span class="bo-access__badge bo-access__badge--warn">Inactive</span>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        <?php endif; ?>

        <?php if ($activeTab === 'matrix'): ?>
            <section class="bo-access__panel" aria-labelledby="access-matrix-title">
                <div class="bo-access__panel-head">
                    <h2 id="access-matrix-title">Vue d’ensemble des espaces</h2>
                    <p>
                        Repère pédagogique des actions courantes. La décision réelle dépend des rôles, des droits attribués et des règles particulières actives —
                        utilisez l’onglet « Tester un accès » pour un cas concret.
                    </p>
                </div>
                <div class="bo-access__panel-body">
                    <div class="bo-access__table-wrap">
                        <table class="bo-access__table">
                            <thead>
                                <tr>
                                    <th>Espace</th>
                                    <th>Consulter</th>
                                    <th>Créer</th>
                                    <th>Modifier</th>
                                    <th>Supprimer</th>
                                    <th>Exporter</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($matrixResources as $key => $label): ?>
                                    <tr>
                                        <td data-label="Espace"><strong><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></strong></td>
                                        <td data-label="Consulter"><span class="bo-access__badge bo-access__badge--ok">Selon rôle</span></td>
                                        <td data-label="Créer"><span class="bo-access__badge bo-access__badge--warn">Selon règle</span></td>
                                        <td data-label="Modifier"><span class="bo-access__badge bo-access__badge--warn">Selon règle</span></td>
                                        <td data-label="Supprimer"><span class="bo-access__badge bo-access__badge--deny">Souvent restreint</span></td>
                                        <td data-label="Exporter"><span class="bo-access__badge bo-access__badge--neutral">Cas par cas</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="bo-access__hint" style="margin-top:1rem">
                        Légende : « Selon rôle » = droit de base du rôle ; « Selon règle » = peut dépendre d’une règle particulière ;
                        « Souvent restreint » = rarement accordé hors responsabilités d’état-major.
                    </p>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($activeTab === 'simulation'): ?>
            <section class="bo-access__panel" aria-labelledby="access-sim-title">
                <div class="bo-access__panel-head">
                    <h2 id="access-sim-title">Tester un accès</h2>
                    <p>Choisissez un membre, un espace et une action pour voir si l’accès serait autorisé ou refusé — et pourquoi.</p>
                </div>
                <div class="bo-access__panel-body">
                    <?php if ($users === []): ?>
                        <div class="bo-access__empty">
                            <div class="bo-access__empty-icon" aria-hidden="true">∅</div>
                            <p>Aucun membre à tester</p>
                            <span>Invitez ou activez des membres dans votre communauté pour pouvoir simuler un accès.</span>
                        </div>
                    <?php else: ?>
                        <form id="bo-access-sim-form" class="bo-access__row bo-access__row--2" @submit.prevent="
                            const fd = new FormData($event.target);
                            simState = 'loading';
                            simTitle = 'Analyse en cours…';
                            simDetail = 'Vérification des rôles et des règles particulières.';
                            fetch('<?= htmlspecialchars(url('back-office/access-management/simulate'), ENT_QUOTES, 'UTF-8') ?>?' + new URLSearchParams(fd).toString(), { credentials: 'same-origin' })
                              .then(r => r.json())
                              .then(data => {
                                if (!data || !data.ok) {
                                  simState = 'deny';
                                  simTitle = 'Impossible de tester';
                                  simDetail = (data && data.error) ? data.error : 'Une erreur est survenue.';
                                  return;
                                }
                                const d = data.decision || {};
                                const allowed = !!d.allowed;
                                const reasonMap = {
                                  platform_admin_bypass: 'Accès administrateur plateforme',
                                  rbac_denied: 'Droit de base non accordé pour ce rôle',
                                  rbac_only_no_abac_rule: 'Autorisé par le rôle (aucune règle particulière applicable)',
                                  abac_deny_rule: 'Refusé par une règle particulière',
                                  abac_allow_rule: 'Autorisé par une règle particulière',
                                  abac_deny_by_default: 'Refusé par défaut : aucune règle d’autorisation ne s’applique',
                                  invalid_user_context: 'Membre introuvable ou hors communauté'
                                };
                                simState = allowed ? 'allow' : 'deny';
                                simTitle = allowed ? 'Accès autorisé' : 'Accès refusé';
                                simDetail = reasonMap[d.reason] || (d.reason || 'Décision calculée.');
                              })
                              .catch(() => {
                                simState = 'deny';
                                simTitle = 'Impossible de tester';
                                simDetail = 'La vérification n’a pas pu aboutir. Réessayez dans un instant.';
                              });
                        ">
                            <div class="bo-access__field">
                                <label class="bo-access__label" for="sim-user">Membre</label>
                                <select id="sim-user" name="user_id" class="bo-access__select" required>
                                    <?php foreach ($users as $u): ?>
                                        <?php $st = (string) ($u['status'] ?? ''); ?>
                                        <option value="<?= (int) ($u['id'] ?? 0) ?>">
                                            <?= htmlspecialchars($userDisplay($u), ENT_QUOTES, 'UTF-8') ?>
                                            <?php if ($st !== ''): ?> — <?= htmlspecialchars($userStatusLabel($st), ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="bo-access__field">
                                <label class="bo-access__label" for="sim-resource">Espace</label>
                                <select id="sim-resource" name="resource" class="bo-access__select">
                                    <option value="documents">Documents</option>
                                    <option value="courrier">Courrier</option>
                                    <option value="training">Formations</option>
                                    <option value="admin">Administration</option>
                                </select>
                            </div>
                            <div class="bo-access__field">
                                <label class="bo-access__label" for="sim-action">Action</label>
                                <select id="sim-action" name="action" class="bo-access__select">
                                    <option value="READ">Consulter</option>
                                    <option value="CREATE">Créer</option>
                                    <option value="UPDATE">Modifier</option>
                                    <option value="DELETE">Supprimer</option>
                                    <option value="EXPORT">Exporter</option>
                                </select>
                            </div>
                            <div class="bo-access__field" style="justify-content:flex-end;padding-top:1.35rem">
                                <button type="submit" class="bo-access__btn bo-access__btn--dark">Lancer le test</button>
                            </div>
                        </form>

                        <div
                            class="bo-access__sim-result"
                            :class="{ 'is-allow': simState === 'allow', 'is-deny': simState === 'deny' }"
                            role="status"
                            aria-live="polite"
                        >
                            <h3 x-text="simState === 'idle' ? 'En attente d’un test' : simTitle">En attente d’un test</h3>
                            <p x-text="simState === 'idle' ? 'Sélectionnez un membre, un espace et une action, puis lancez le test.' : simDetail">
                                Sélectionnez un membre, un espace et une action, puis lancez le test.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="bo-access__panel" aria-labelledby="access-log-title">
            <div class="bo-access__panel-head">
                <h2 id="access-log-title">Journal des décisions</h2>
                <p>Les 50 dernières décisions et modifications de politique pour cette communauté.</p>
            </div>
            <div class="bo-access__panel-body">
                <?php if ($logs === []): ?>
                    <div class="bo-access__empty">
                        <div class="bo-access__empty-icon" aria-hidden="true">∅</div>
                        <p>Aucune entrée pour le moment</p>
                        <span>Les tests d’accès et les changements de rôles ou de règles apparaîtront ici.</span>
                    </div>
                <?php else: ?>
                    <ul class="bo-access__log-list">
                        <?php foreach ($logs as $log): ?>
                            <?php
                            $dec = (string) ($log['decision'] ?? '');
                            $created = trim((string) ($log['created_at'] ?? ''));
                            $createdFmt = $created !== '' ? date('d/m/Y H:i', strtotime($created) ?: time()) : '';
                            ?>
                            <li class="bo-access__log-item">
                                <span class="bo-access__badge <?= htmlspecialchars($effectBadgeClass($dec), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($effectLabel($dec), ENT_QUOTES, 'UTF-8') ?></span>
                                <span><?= htmlspecialchars($resourceLabel((string) ($log['resource'] ?? '')), ENT_QUOTES, 'UTF-8') ?></span>
                                <span>·</span>
                                <span><?= htmlspecialchars($actionLabel((string) ($log['action'] ?? '')), ENT_QUOTES, 'UTF-8') ?></span>
                                <span>·</span>
                                <span><?= htmlspecialchars($reasonLabel((string) ($log['reason'] ?? '')), ENT_QUOTES, 'UTF-8') ?></span>
                                <?php if ($createdFmt !== ''): ?>
                                    <time datetime="<?= htmlspecialchars($created, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($createdFmt, ENT_QUOTES, 'UTF-8') ?></time>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
