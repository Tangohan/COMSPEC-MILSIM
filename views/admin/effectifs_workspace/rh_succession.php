<?php
declare(strict_types=1);

require base_path('views/admin/effectifs_workspace/partials/rh_ui_helpers.php');

$rows = is_array($successionEntries ?? null) ? $successionEntries : [];
$counts = is_array($successionCounts ?? null) ? $successionCounts : [];
$readinessLabels = is_array($successionReadinessLabels ?? null) ? $successionReadinessLabels : [];
$defaultRoles = is_array($successionDefaultRoles ?? null) ? $successionDefaultRoles : [];
$users = is_array($orgUsers ?? null) ? $orgUsers : [];
$schemaReady = !empty($successionSchemaReady);
$canManage = !empty($canManage);
$filter = (string) ($successionReadinessFilter ?? '');
$csrf = htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8');

$readyNow = (int) ($counts['ready_now'] ?? 0);
$ready3m = (int) ($counts['ready_3m'] ?? 0);
$develop = (int) ($counts['develop'] ?? 0);
$readinessTone = [
    'ready_now' => 'ok',
    'ready_3m' => 'warn',
    'develop' => 'info',
];
?>
<section class="eff-rh-hero">
    <p class="eff-page-kicker">Dossier RH</p>
    <h1 class="eff-page-title">Succession et vivier</h1>
    <p class="eff-page-lead">
        Personnes identifiées pour les postes de responsabilité : prêtes maintenant, sous trois mois, ou à accompagner.
        Le vivier n’affecte pas le membre : il sert à préparer les relèves.
    </p>
    <div class="eff-rh-tiles" aria-label="Répartition du vivier">
        <a class="eff-rh-tile <?= $filter === 'ready_now' ? 'is-on' : '' ?> eff-rh-tile--ok" href="<?= $h(effectifs_workspace_url('vivier') . '?readiness=ready_now') ?>">
            <span class="eff-rh-tile__kicker">Maintenant</span>
            <strong class="eff-rh-tile__value"><?= $readyNow ?></strong>
            <span class="eff-rh-tile__label">prêt<?= $readyNow > 1 ? 's' : '' ?> à occuper le poste</span>
        </a>
        <a class="eff-rh-tile <?= $filter === 'ready_3m' ? 'is-on' : '' ?> eff-rh-tile--warn" href="<?= $h(effectifs_workspace_url('vivier') . '?readiness=ready_3m') ?>">
            <span class="eff-rh-tile__kicker">Horizon court</span>
            <strong class="eff-rh-tile__value"><?= $ready3m ?></strong>
            <span class="eff-rh-tile__label">prêt<?= $ready3m > 1 ? 's' : '' ?> sous 3 mois</span>
        </a>
        <a class="eff-rh-tile <?= $filter === 'develop' ? 'is-on' : '' ?> eff-rh-tile--info" href="<?= $h(effectifs_workspace_url('vivier') . '?readiness=develop') ?>">
            <span class="eff-rh-tile__kicker">Parcours</span>
            <strong class="eff-rh-tile__value"><?= $develop ?></strong>
            <span class="eff-rh-tile__label">à développer</span>
        </a>
    </div>
</section>

<?php if (!$schemaReady): ?>
    <div class="eff-catalog">
        <div class="eff-catalog__empty">
            <strong>Vivier indisponible</strong>
            <?= $h($rhUnavailable) ?>
        </div>
    </div>
<?php else: ?>
    <div class="eff-rh-pills" role="tablist" aria-label="Filtrer le vivier">
        <a href="<?= $h(effectifs_workspace_url('vivier')) ?>" class="eff-rh-pill <?= $filter === '' ? 'is-on' : '' ?>">Tous</a>
        <?php foreach ($readinessLabels as $k => $lab): ?>
            <a href="<?= $h(effectifs_workspace_url('vivier') . '?readiness=' . rawurlencode((string) $k)) ?>"
               class="eff-rh-pill <?= $filter === $k ? 'is-on' : '' ?> eff-rh-pill--<?= $h($readinessTone[$k] ?? '') ?>">
                <?= $h((string) $lab) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($canManage): ?>
    <section class="eff-rh-form" aria-labelledby="eff-vivier-add-title">
        <div class="eff-rh-form__head">
            <h2 id="eff-vivier-add-title" class="eff-rh-form__title">Ajouter au vivier</h2>
            <p class="eff-rh-form__lead">Choisissez un membre, le poste visé, puis le niveau de maturité.</p>
        </div>
        <form method="post" action="<?= $h(effectifs_workspace_url('vivier')) ?>" class="eff-rh-form__grid">
            <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
            <div class="eff-rh-field">
                <span class="eff-rh-field__label">Membre</span>
                <select name="user_id" required aria-label="Membre">
                    <option value="">Choisir un membre…</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int) ($u['id'] ?? 0) ?>"><?= $h(trim((string) ($u['display_name'] ?? '')) ?: (string) ($u['email'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="eff-rh-field">
                <span class="eff-rh-field__label">
                    Poste cible
                    <?php $rhTip('tip-vivier-poste', 'À propos du poste', 'Intitulé du poste de relève (chef d’équipe, instructeur…). Vous pouvez choisir une suggestion ou saisir un intitulé propre à votre organisation.'); ?>
                </span>
                <input type="text" name="target_role_label" list="vivier-roles" maxlength="120" required placeholder="Chef d’équipe…" aria-label="Poste cible">
                <datalist id="vivier-roles">
                    <?php foreach ($defaultRoles as $role): ?>
                        <option value="<?= $h((string) $role) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="eff-rh-field">
                <span class="eff-rh-field__label">
                    Maturité
                    <?php $rhTip('tip-vivier-ready', 'À propos de la maturité', 'Prêt maintenant : peut occuper le poste. Sous 3 mois : un accompagnement court suffit. À développer : un parcours plus long est nécessaire.'); ?>
                </span>
                <select name="readiness" aria-label="Maturité">
                    <?php foreach ($readinessLabels as $k => $lab): ?>
                        <option value="<?= $h((string) $k) ?>"><?= $h((string) $lab) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="eff-rh-field eff-rh-field--wide">
                <span class="eff-rh-field__label">Notes</span>
                <input type="text" name="notes" maxlength="500" placeholder="Points forts, freins, formations à prévoir…" aria-label="Notes">
            </div>
            <div class="eff-rh-form__actions">
                <button type="submit" class="eff-rh-btn eff-rh-btn--primary">Ajouter au vivier</button>
            </div>
        </form>
    </section>
    <?php endif; ?>

    <div class="eff-catalog">
        <div class="eff-catalog__head">
            <div class="min-w-0">
                <p class="eff-catalog__kicker">Registre</p>
                <h2 class="eff-catalog__title">Personnes identifiées</h2>
                <p class="eff-catalog__lead">Retirer une entrée n’efface pas le dossier du membre : elle quitte seulement le vivier.</p>
            </div>
        </div>
        <?php if ($rows === []): ?>
            <div class="eff-catalog__empty"><strong>Vivier vide<?= $filter !== '' ? ' pour ce filtre' : '' ?>.</strong></div>
        <?php else: ?>
            <div class="eff-sheets" role="region" aria-label="Vivier" tabindex="0">
                <table class="eff-sheets__table">
                    <thead>
                        <tr>
                            <th>Membre</th>
                            <th>Poste</th>
                            <th>Maturité</th>
                            <th>Notes</th>
                            <th>Évalué le</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $e): ?>
                        <?php
                        $eid = (int) ($e['id'] ?? 0);
                        $uid = (int) ($e['user_id'] ?? 0);
                        $name = trim((string) ($e['user_display_name'] ?? '')) ?: (string) ($e['user_email'] ?? 'Membre');
                        $cs = trim((string) ($e['user_callsign'] ?? ''));
                        $rd = (string) ($e['readiness'] ?? 'develop');
                        $tone = $readinessTone[$rd] ?? 'info';
                        ?>
                        <tr>
                            <td>
                                <strong class="eff-sheets__name"><?= $h($name) ?></strong>
                                <?php if ($cs !== ''): ?><br><span class="eff-sheets__meta"><?= $h($cs) ?></span><?php endif; ?>
                            </td>
                            <td><?= $h((string) ($e['target_role_label'] ?? '')) ?></td>
                            <td><span class="eff-rh-chip eff-rh-chip--<?= $h($tone) ?>"><?= $h((string) ($readinessLabels[$rd] ?? $rd)) ?></span></td>
                            <td><span class="eff-sheets__meta"><?= $h(trim((string) ($e['notes'] ?? '')) ?: '—') ?></span></td>
                            <td><?= $h($rhWhen((string) ($e['assessed_at'] ?? ''))) ?></td>
                            <td>
                                <div class="eff-rh-row-actions">
                                    <?php if ($uid > 0): ?>
                                        <a class="is-primary" href="<?= $h(effectifs_workspace_url('membres/' . $uid)) ?>">Fiche</a>
                                    <?php endif; ?>
                                    <?php if ($canManage && $eid > 0): ?>
                                        <form method="post" action="<?= $h(effectifs_workspace_url('vivier/' . $eid . '/retirer')) ?>" onsubmit="return confirm('Retirer cette personne du vivier ? Le dossier du membre n’est pas modifié.');">
                                            <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                                            <button type="submit" class="eff-rh-btn">Retirer</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php $rhShortcutCurrent = 'vivier'; require base_path('views/admin/effectifs_workspace/partials/rh_shortcuts.php'); ?>
