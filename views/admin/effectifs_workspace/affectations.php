<?php
declare(strict_types=1);

$units = is_array($units ?? null) ? $units : [];
$membersWithoutUnit = (int) ($membersWithoutUnit ?? 0);
$canManageAssignments = (bool) ($canManageAssignments ?? false);
$unitCount = count($units);

$typeLabel = static function (string $typeRaw): string {
    return match ($typeRaw) {
        'company', 'compagnie' => 'Compagnie',
        'platoon', 'peloton' => 'Peloton',
        'section' => 'Section',
        'squad', 'groupe' => 'Groupe',
        'hq', 'etat_major', 'état-major' => 'État-major',
        'team', 'equipe', 'équipe' => 'Équipe',
        'battalion', 'bataillon' => 'Bataillon',
        default => $typeRaw !== '' ? $typeRaw : '—',
    };
};
?>
<section class="eff-rh-hero">
    <?php if ($canManageAssignments): ?>
    <div class="eff-pg__actions">
        <a class="eff-catalog__btn" href="<?= htmlspecialchars(url('deploiement'), ENT_QUOTES, 'UTF-8') ?>">Déploiement</a>
        <a class="eff-catalog__btn" href="<?= htmlspecialchars(effectifs_workspace_url('chaine'), ENT_QUOTES, 'UTF-8') ?>">Chaîne de commandement</a>
        <a class="eff-catalog__btn eff-catalog__btn--primary" href="<?= htmlspecialchars(url('back-office/organisation/structure'), ENT_QUOTES, 'UTF-8') ?>">Structure &amp; ORBAT</a>
    </div>
    <?php endif; ?>
    <p class="eff-page-kicker">Structure</p>
    <h2 class="eff-page-title">Affectations</h2>
    <p class="eff-page-lead">
        Suivez les unités de rattachement et les membres encore sans unité.
        Depuis le tableur des effectifs, vous pouvez affecter une unité directement.
    </p>
    <div class="eff-rh-tiles" aria-label="Aperçu des affectations">
        <article class="eff-rh-tile <?= $unitCount > 0 ? 'eff-rh-tile--ok' : 'eff-rh-tile--warn' ?>">
            <span class="eff-rh-tile__kicker">Unités</span>
            <strong class="eff-rh-tile__value"><?= $unitCount ?></strong>
            <span class="eff-rh-tile__label">unité<?= $unitCount > 1 ? 's' : '' ?> de rattachement</span>
        </article>
        <a class="eff-rh-tile <?= $membersWithoutUnit > 0 ? 'eff-rh-tile--warn' : 'eff-rh-tile--ok' ?>" href="<?= htmlspecialchars(effectifs_workspace_url() . '?sans_affectation=1', ENT_QUOTES, 'UTF-8') ?>">
            <span class="eff-rh-tile__kicker">Sans unité</span>
            <strong class="eff-rh-tile__value"><?= $membersWithoutUnit ?></strong>
            <span class="eff-rh-tile__label">membre<?= $membersWithoutUnit > 1 ? 's' : '' ?> à rattacher</span>
        </a>
    </div>
</section>

<div class="eff-catalog">
    <div class="eff-catalog__head">
        <div class="min-w-0">
            <p class="eff-catalog__kicker">Organigramme</p>
            <h3 class="eff-catalog__title">Unités</h3>
            <p class="eff-catalog__lead">Toutes les unités où un membre peut être affecté.</p>
        </div>
        <?php if ($membersWithoutUnit > 0): ?>
        <div class="eff-catalog__tools">
            <a class="eff-catalog__btn eff-catalog__btn--primary" href="<?= htmlspecialchars(effectifs_workspace_url() . '?sans_affectation=1', ENT_QUOTES, 'UTF-8') ?>">Traiter les sans unité (<?= $membersWithoutUnit ?>)</a>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($units === []): ?>
        <div class="eff-catalog__empty">
            <strong>Aucune unité définie</strong>
            La structure ne contient pas encore d’unité de rattachement.
            <?php if ($canManageAssignments): ?>
                <a href="<?= htmlspecialchars(url('back-office/organisation/structure'), ENT_QUOTES, 'UTF-8') ?>">Ouvrir la structure</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="eff-sheets" role="region" aria-label="Tableur des affectations" tabindex="0">
            <table class="eff-sheets__table" id="eff-affectations-table">
                <thead>
                    <tr>
                        <th>Unité</th>
                        <th>Type</th>
                        <th>Code</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($units as $u):
                    $name = trim((string) ($u['name'] ?? ''));
                    $code = trim((string) ($u['code'] ?? ''));
                    $label = $typeLabel((string) ($u['type'] ?? ''));
                    ?>
                    <tr>
                        <td>
                            <strong class="eff-sheets__name"><?= htmlspecialchars($name !== '' ? $name : '—', ENT_QUOTES, 'UTF-8') ?></strong>
                        </td>
                        <td>
                            <?php if ($label !== '—'): ?>
                                <span class="eff-sheets__badge eff-sheets__badge--muted"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php else: ?>
                                <span class="eff-sheets__path-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="eff-sheets__meta"><?= htmlspecialchars($code !== '' ? $code : '—', ENT_QUOTES, 'UTF-8') ?></span>
                        </td>
                        <td>
                            <div class="eff-sheets__actions">
                                <a href="<?= htmlspecialchars(effectifs_workspace_url(), ENT_QUOTES, 'UTF-8') ?>">Voir effectifs</a>
                                <?php if ($canManageAssignments): ?>
                                    <a href="<?= htmlspecialchars(url('back-office/organisation/structure'), ENT_QUOTES, 'UTF-8') ?>">Structure</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="eff-card-foot">
            <span><strong><?= $unitCount ?></strong> unité<?= $unitCount > 1 ? 's' : '' ?> · <strong><?= $membersWithoutUnit ?></strong> membre<?= $membersWithoutUnit > 1 ? 's' : '' ?> sans unité</span>
        </p>
    <?php endif; ?>
</div>
