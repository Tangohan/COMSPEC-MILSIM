<?php
declare(strict_types=1);

use App\Support\OrganizationRoleLabels;

/**
 * Zone 1 — Composer une invitation (formulaire).
 *
 * @var list<array<string, mixed>> $rolesOrganization
 * @var list<array<string, mixed>> $inviteUnits
 * @var list<array{id: int, label: string, name: string}> $inviteJobRoleOptions
 * @var bool $canAdd
 * @var string $organizationRoleLabelMode
 * @var array{pending: int, accepted: int, revoked: int, expired: int, total: int} $inviteStatusCounts
 */
$rolesOrganization = $rolesOrganization ?? [];
$inviteUnits = $inviteUnits ?? [];
$inviteJobRoleOptions = $inviteJobRoleOptions ?? [];
$organizationRoleLabelMode = $organizationRoleLabelMode ?? OrganizationRoleLabels::MODE_FR;
$inviteStatusCounts = $inviteStatusCounts ?? [
    'pending' => 0,
    'accepted' => 0,
    'revoked' => 0,
    'expired' => 0,
    'total' => 0,
];

$rolesByLayer = ['community' => [], 'intra' => [], 'other' => []];
$rolePickerItems = [];
foreach ($rolesOrganization as $r) {
    $ly = (string) ($r['role_layer'] ?? 'community');
    if ($ly !== 'community' && $ly !== 'intra') {
        $ly = 'other';
    }
    $rolesByLayer[$ly][] = $r;
    $rid = (int) ($r['id'] ?? 0);
    if ($rid < 1) {
        continue;
    }
    $disp = OrganizationRoleLabels::displayName($r, $organizationRoleLabelMode);
    $rolePickerItems[] = [
        'id' => $rid,
        'layer' => $ly,
        'label' => $disp !== '' ? $disp : 'Rôle sans intitulé',
        'hint' => trim((string) ($r['description'] ?? '')),
    ];
}

$layerFilterOptions = [];
foreach (['community', 'intra', 'other'] as $ly) {
    if ($rolesByLayer[$ly] === []) {
        continue;
    }
    $layerFilterOptions[] = [
        'value' => $ly,
        'label' => OrganizationRoleLabels::layerGroupLabel($ly, $organizationRoleLabelMode),
        'count' => count($rolesByLayer[$ly]),
    ];
}

$sentUrl = url('back-office/invitations/envoyees');
$rolePickerJson = json_encode($rolePickerItems, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP);
if ($rolePickerJson === false) {
    $rolePickerJson = '[]';
}
?>
<link href="<?= htmlspecialchars(asset_url('assets/css/invitations-sheet.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
<div class="inv-compose">
    <header class="inv-compose__hero">
        <div class="inv-compose__hero-inner">
            <div>
                <p class="inv-compose__eyebrow">Membres · Invitations</p>
                <h1 class="inv-compose__title">Nouvelle invitation</h1>
                <p class="inv-compose__lead">
                    Envoyez un lien d’accès par e-mail. Choisissez un rôle principal, puis — si besoin — préparez l’arrivée dans l’organigramme.
                </p>
            </div>
            <div class="inv-compose__hero-actions">
                <a href="<?= htmlspecialchars($sentUrl, ENT_QUOTES, 'UTF-8') ?>" class="inv-compose__btn inv-compose__btn--solid">
                    Invitations envoyées
                    <span class="inv-compose__btn-count"><?= (int) ($inviteStatusCounts['total'] ?? 0) ?></span>
                </a>
                <?php if ((int) ($inviteStatusCounts['pending'] ?? 0) > 0): ?>
                    <p class="inv-compose__pending-hint">
                        <?= (int) $inviteStatusCounts['pending'] ?> en attente de réponse
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="inv-compose__deck">
        <dl class="inv-compose__kpi-grid">
            <div class="inv-compose__kpi">
                <dt class="inv-compose__kpi-label inv-compose__kpi-label--amber">En attente</dt>
                <dd class="inv-compose__kpi-value"><?= (int) $inviteStatusCounts['pending'] ?></dd>
            </div>
            <div class="inv-compose__kpi">
                <dt class="inv-compose__kpi-label inv-compose__kpi-label--ok">Rattachées</dt>
                <dd class="inv-compose__kpi-value"><?= (int) $inviteStatusCounts['accepted'] ?></dd>
            </div>
            <div class="inv-compose__kpi">
                <dt class="inv-compose__kpi-label">Annulées</dt>
                <dd class="inv-compose__kpi-value"><?= (int) $inviteStatusCounts['revoked'] ?></dd>
            </div>
            <div class="inv-compose__kpi">
                <dt class="inv-compose__kpi-label">Expirées</dt>
                <dd class="inv-compose__kpi-value"><?= (int) $inviteStatusCounts['expired'] ?></dd>
            </div>
        </dl>

        <?php $f = \App\Core\Session::getFlash('error'); $s = \App\Core\Session::getFlash('success'); ?>
        <?php if ($f): ?>
            <div class="inv-compose__flash inv-compose__flash--err" role="alert"><?= htmlspecialchars($f) ?></div>
        <?php endif; ?>
        <?php if ($s): ?>
            <div class="inv-compose__flash inv-compose__flash--ok" role="status">
                <?= htmlspecialchars($s) ?>
                <a href="<?= htmlspecialchars($sentUrl, ENT_QUOTES, 'UTF-8') ?>" class="inv-compose__flash-link">Voir le tableur →</a>
            </div>
        <?php endif; ?>

        <?php if (!$canAdd): ?>
            <div class="inv-compose__flash inv-compose__flash--warn">
                Votre formule actuelle limite le nombre de membres. Passez à une offre supérieure pour envoyer de nouvelles invitations.
            </div>
        <?php endif; ?>

        <?php if ($canAdd && empty($rolesOrganization)): ?>
            <div class="inv-compose__flash inv-compose__flash--warn">
                Aucun rôle n’est encore disponible pour votre communauté. Configurez d’abord les rôles dans le back-office, ou contactez une personne administratrice si le problème persiste.
            </div>
        <?php endif; ?>

        <?php if ($canAdd && !empty($rolesOrganization)): ?>
        <section id="nouvelle-invitation" class="inv-compose__panel" aria-labelledby="invite-new-heading"
            x-data="inviteRolePicker(<?= htmlspecialchars($rolePickerJson, ENT_QUOTES, 'UTF-8') ?>)">
            <div class="inv-compose__panel-head">
                <h2 id="invite-new-heading">Composer l’invitation</h2>
                <p>Indiquez l’adresse e-mail de connexion et le rôle principal accordé dans l’unité.</p>
            </div>

            <form method="post" action="<?= url('back-office/invitations') ?>" class="inv-compose__form">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">

                <div class="inv-compose__field">
                    <label for="invite-email" class="inv-compose__label">Adresse e-mail</label>
                    <input id="invite-email" type="email" name="email" required autocomplete="email"
                        class="inv-compose__input"
                        placeholder="prenom.nom@exemple.fr">
                    <p class="inv-compose__help">Celle que la personne utilisera pour se connecter au portail.</p>
                </div>

                <div class="inv-compose__field">
                    <div class="inv-compose__label-row">
                        <label for="invite-role" class="inv-compose__label">Rôle principal</label>
                        <span class="inv-compose__meta" x-text="visibleCount + ' proposé' + (visibleCount > 1 ? 's' : '')"></span>
                    </div>
                    <p class="inv-compose__help inv-compose__help--tight">
                        Un seul rôle est attribué à l’invitation. Les habilitations réservées à l’équipe plateforme ne sont pas proposées.
                    </p>

                    <?php if (count($layerFilterOptions) > 1): ?>
                        <div class="inv-compose__layer-tabs" role="group" aria-label="Filtrer par famille de rôle">
                            <button type="button"
                                class="inv-compose__layer-tab"
                                :class="layer === '' && 'is-active'"
                                @click="setLayer('')">
                                Tous
                                <span><?= count($rolePickerItems) ?></span>
                            </button>
                            <?php foreach ($layerFilterOptions as $lof): ?>
                                <button type="button"
                                    class="inv-compose__layer-tab"
                                    :class="layer === '<?= htmlspecialchars($lof['value'], ENT_QUOTES, 'UTF-8') ?>' && 'is-active'"
                                    @click="setLayer('<?= htmlspecialchars($lof['value'], ENT_QUOTES, 'UTF-8') ?>')">
                                    <?= htmlspecialchars($lof['label']) ?>
                                    <span><?= (int) $lof['count'] ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (count($rolePickerItems) > 6): ?>
                        <label class="inv-compose__search">
                            <span class="sr-only">Rechercher un rôle</span>
                            <input type="search"
                                x-model="q"
                                @input="onFilter()"
                                placeholder="Rechercher un rôle…"
                                autocomplete="off">
                        </label>
                    <?php endif; ?>

                    <select id="invite-role" name="role_id" required class="inv-compose__select" x-ref="roleSelect">
                        <option value="">Choisir un rôle…</option>
                        <?php foreach (['community', 'intra', 'other'] as $ly): ?>
                            <?php if (empty($rolesByLayer[$ly])) {
                                continue;
                            } ?>
                            <optgroup
                                label="<?= htmlspecialchars(OrganizationRoleLabels::layerGroupLabel($ly, $organizationRoleLabelMode), ENT_QUOTES, 'UTF-8') ?>"
                                data-layer="<?= htmlspecialchars($ly, ENT_QUOTES, 'UTF-8') ?>">
                                <?php foreach ($rolesByLayer[$ly] as $r):
                                    $rid = (int) ($r['id'] ?? 0);
                                    if ($rid < 1) {
                                        continue;
                                    }
                                    $disp = OrganizationRoleLabels::displayName($r, $organizationRoleLabelMode);
                                    $rdesc = trim((string) ($r['description'] ?? ''));
                                    $optionLabel = $disp !== '' ? $disp : 'Rôle sans intitulé';
                                    ?>
                                    <option
                                        value="<?= $rid ?>"
                                        data-layer="<?= htmlspecialchars($ly, ENT_QUOTES, 'UTF-8') ?>"
                                        data-search="<?= htmlspecialchars(mb_strtolower($optionLabel . ' ' . $rdesc, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>"
                                        title="<?= htmlspecialchars($rdesc !== '' ? $rdesc : $optionLabel, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($optionLabel) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <p class="inv-compose__role-hint" x-show="selectedHint" x-cloak x-text="selectedHint"></p>
                    <p class="inv-compose__empty-filter" x-show="visibleCount === 0" x-cloak>
                        Aucun rôle ne correspond à ce filtre. Élargissez la recherche ou changez de famille.
                    </p>
                </div>

                <details class="inv-compose__advanced">
                    <summary class="inv-compose__advanced-summary">
                        <span class="inv-compose__advanced-title">Préparer l’arrivée</span>
                        <span class="inv-compose__advanced-badge">Facultatif</span>
                        <span class="inv-compose__advanced-chevron" aria-hidden="true"></span>
                    </summary>
                    <div class="inv-compose__advanced-body">
                        <p class="inv-compose__help">
                            Appliqué automatiquement lorsque la personne aura accepté : affectation dans l’organigramme et fonction sur la fiche personnel.
                        </p>
                        <div class="inv-compose__grid">
                            <div class="inv-compose__field">
                                <label for="invite-unit" class="inv-compose__label">Unité dans l’organigramme</label>
                                <select id="invite-unit" name="unit_id" class="inv-compose__select">
                                    <option value="0">Aucune pour l’instant</option>
                                    <?php foreach ($inviteUnits as $u): ?>
                                        <option value="<?= (int) ($u['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($u['name'] ?? '')) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="inv-compose__field">
                                <label for="invite-assignment" class="inv-compose__label">Libellé d’affectation</label>
                                <input id="invite-assignment" type="text" name="assignment_label" maxlength="120"
                                    placeholder="Ex. membre d’équipe, opérateur…"
                                    class="inv-compose__input"
                                    value="Membre">
                            </div>
                        </div>
                        <?php if (!empty($inviteJobRoleOptions)): ?>
                            <div class="inv-compose__field">
                                <label for="invite-job-role" class="inv-compose__label">Fonction sur la fiche personnel</label>
                                <select id="invite-job-role" name="personnel_job_role_id" class="inv-compose__select">
                                    <option value="0">Aucune pour l’instant</option>
                                    <?php foreach ($inviteJobRoleOptions as $jo): ?>
                                        <option value="<?= (int) ($jo['id'] ?? 0) ?>"><?= htmlspecialchars($jo['label'] ?? $jo['name'] ?? '') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php else: ?>
                            <p class="inv-compose__help">
                                Aucune fonction métier n’est encore définie. Vous pourrez en ajouter depuis le menu dédié, puis les associer aux prochaines invitations.
                            </p>
                        <?php endif; ?>
                    </div>
                </details>

                <div class="inv-compose__actions">
                    <button type="submit" class="inv-compose__btn inv-compose__btn--primary">
                        <svg class="inv-compose__btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        Envoyer l’invitation
                    </button>
                    <a href="<?= htmlspecialchars($sentUrl, ENT_QUOTES, 'UTF-8') ?>" class="inv-compose__link">Voir les invitations envoyées</a>
                    <span class="inv-compose__footnote">Le lien reste valable 7 jours.</span>
                </div>
            </form>
        </section>
        <?php endif; ?>
    </div>
</div>
<script>
function inviteRolePicker(items) {
    const catalog = Array.isArray(items) ? items : [];
    const hints = {};
    catalog.forEach(function (item) {
        if (item && item.id) {
            hints[String(item.id)] = item.hint || '';
        }
    });
    return {
        q: '',
        layer: '',
        visibleCount: catalog.length,
        selectedHint: '',
        init() {
            const select = this.$refs.roleSelect;
            if (!select) {
                return;
            }
            const syncHint = () => {
                const val = select.value || '';
                this.selectedHint = hints[val] || '';
            };
            select.addEventListener('change', syncHint);
            this.onFilter();
            syncHint();
        },
        setLayer(value) {
            this.layer = value;
            this.onFilter();
        },
        onFilter() {
            const select = this.$refs.roleSelect;
            if (!select) {
                return;
            }
            const needle = (this.q || '').toLowerCase().trim();
            const layer = this.layer || '';
            let visible = 0;
            const selected = select.value;
            let selectedStillVisible = false;

            Array.from(select.querySelectorAll('optgroup')).forEach(function (group) {
                const groupLayer = group.getAttribute('data-layer') || '';
                let groupVisible = 0;
                Array.from(group.querySelectorAll('option')).forEach(function (opt) {
                    const optLayer = opt.getAttribute('data-layer') || groupLayer;
                    const hay = opt.getAttribute('data-search') || (opt.textContent || '').toLowerCase();
                    const layerOk = !layer || optLayer === layer;
                    const textOk = !needle || hay.indexOf(needle) !== -1;
                    const show = layerOk && textOk;
                    opt.hidden = !show;
                    opt.disabled = !show;
                    if (show) {
                        groupVisible += 1;
                        if (opt.value === selected) {
                            selectedStillVisible = true;
                        }
                    }
                });
                group.hidden = groupVisible === 0;
                group.disabled = groupVisible === 0;
                visible += groupVisible;
            });

            this.visibleCount = visible;
            if (selected && !selectedStillVisible) {
                select.value = '';
                this.selectedHint = '';
            }
        }
    };
}
</script>
