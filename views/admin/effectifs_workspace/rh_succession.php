<?php
declare(strict_types=1);

$rows = is_array($successionEntries ?? null) ? $successionEntries : [];
$counts = is_array($successionCounts ?? null) ? $successionCounts : [];
$readinessLabels = is_array($successionReadinessLabels ?? null) ? $successionReadinessLabels : [];
$defaultRoles = is_array($successionDefaultRoles ?? null) ? $successionDefaultRoles : [];
$users = is_array($orgUsers ?? null) ? $orgUsers : [];
$schemaReady = !empty($successionSchemaReady);
$canManage = !empty($canManage);
$filter = (string) ($successionReadinessFilter ?? '');
$csrf = htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8');
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<div class="eff-catalog">
    <div class="eff-catalog__head">
        <div class="min-w-0">
            <p class="eff-catalog__kicker">Dossier individuel</p>
            <h1 class="eff-catalog__title">Succession et vivier</h1>
            <p class="eff-catalog__lead">
                Personnes prêtes maintenant, sous 3 mois, ou à développer pour les postes de chef d’équipe,
                chef de groupe, instructeur, etc.
            </p>
        </div>
        <div class="eff-catalog__tools" style="gap:.5rem">
            <span class="eff-catalog__btn"><?= (int) ($counts['ready_now'] ?? 0) ?> prêts</span>
            <span class="eff-catalog__btn"><?= (int) ($counts['ready_3m'] ?? 0) ?> sous 3 mois</span>
            <span class="eff-catalog__btn"><?= (int) ($counts['develop'] ?? 0) ?> à développer</span>
        </div>
    </div>

    <?php if (!$schemaReady): ?>
        <div class="eff-catalog__empty"><strong>Schéma non migrée.</strong> Relancez les migrations pour activer le vivier.</div>
    <?php else: ?>
        <div class="eff-catalog__tools" style="margin-bottom:1rem">
            <a href="<?= $h(effectifs_workspace_url('vivier')) ?>" class="eff-catalog__btn <?= $filter === '' ? 'eff-catalog__btn--primary' : '' ?>">Tous</a>
            <?php foreach ($readinessLabels as $k => $lab): ?>
                <a href="<?= $h(effectifs_workspace_url('vivier') . '?readiness=' . rawurlencode((string) $k)) ?>"
                   class="eff-catalog__btn <?= $filter === $k ? 'eff-catalog__btn--primary' : '' ?>"><?= $h((string) $lab) ?></a>
            <?php endforeach; ?>
        </div>

        <?php if ($canManage): ?>
        <form method="post" action="<?= $h(effectifs_workspace_url('vivier')) ?>" class="eff-catalog__tools" style="flex-wrap:wrap;gap:.75rem;margin-bottom:1.25rem;align-items:end">
            <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
            <label>
                <span class="eff-section-label">Membre</span>
                <select name="user_id" required>
                    <option value="">Choisir…</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int) ($u['id'] ?? 0) ?>"><?= $h(trim((string) ($u['display_name'] ?? '')) ?: (string) ($u['email'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span class="eff-section-label">Poste cible</span>
                <input type="text" name="target_role_label" list="vivier-roles" maxlength="120" required placeholder="Chef d’équipe…">
                <datalist id="vivier-roles">
                    <?php foreach ($defaultRoles as $role): ?>
                        <option value="<?= $h((string) $role) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </label>
            <label>
                <span class="eff-section-label">Maturité</span>
                <select name="readiness">
                    <?php foreach ($readinessLabels as $k => $lab): ?>
                        <option value="<?= $h((string) $k) ?>"><?= $h((string) $lab) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label style="flex:1;min-width:12rem">
                <span class="eff-section-label">Notes</span>
                <input type="text" name="notes" maxlength="500" placeholder="Points forts, freins…">
            </label>
            <button type="submit" class="eff-catalog__btn eff-catalog__btn--primary">Ajouter au vivier</button>
        </form>
        <?php endif; ?>

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
                            <th>Évalué</th>
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
                        ?>
                        <tr>
                            <td>
                                <strong class="eff-sheets__name"><?= $h($name) ?></strong>
                                <?php if ($cs !== ''): ?><br><span class="eff-sheets__meta"><?= $h($cs) ?></span><?php endif; ?>
                            </td>
                            <td><?= $h((string) ($e['target_role_label'] ?? '')) ?></td>
                            <td><?= $h((string) ($readinessLabels[$rd] ?? $rd)) ?></td>
                            <td><span class="eff-sheets__meta"><?= $h(trim((string) ($e['notes'] ?? '')) ?: '—') ?></span></td>
                            <td><?= $h((string) ($e['assessed_at'] ?? '')) ?></td>
                            <td>
                                <?php if ($uid > 0): ?>
                                    <a class="is-primary" href="<?= $h(effectifs_workspace_url('membres/' . $uid)) ?>">Fiche</a>
                                <?php endif; ?>
                                <?php if ($canManage && $eid > 0): ?>
                                    <form method="post" action="<?= $h(effectifs_workspace_url('vivier/' . $eid . '/retirer')) ?>" style="display:inline" onsubmit="return confirm('Retirer du vivier ?');">
                                        <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                                        <button type="submit" class="eff-catalog__btn">Retirer</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
