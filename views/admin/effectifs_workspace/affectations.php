<?php
declare(strict_types=1);
$units = is_array($units ?? null) ? $units : [];
$membersWithoutUnit = (int) ($membersWithoutUnit ?? 0);
$canManageAssignments = (bool) ($canManageAssignments ?? false);
?>
<section class="eff-page-head">
    <p class="eff-page-kicker">Structure</p>
    <h1 class="eff-page-title">Affectations</h1>
    <p class="eff-page-lead">
        Suivez les unités de rattachement et les membres encore sans unité.
        Depuis le tableur, vous pouvez affecter une unité directement ; l’édition fine reste disponible sur le dossier membre ou la structure ORBAT.
    </p>
</section>

<div class="eff-cards" style="margin-bottom:1.25rem">
    <a class="eff-card" href="<?= htmlspecialchars(effectifs_workspace_url() . '?sans_affectation=1', ENT_QUOTES, 'UTF-8') ?>">
        <h3><?= $membersWithoutUnit ?> sans unité</h3>
        <p>Ouvrez le tableur filtré sur les membres qui n’ont pas encore d’unité de rattachement, puis utilisez « Affecter ».</p>
        <p class="eff-card-cta">Voir dans le tableur →</p>
    </a>
    <?php if ($canManageAssignments): ?>
    <a class="eff-card" href="<?= htmlspecialchars(url('back-office/organisation/structure'), ENT_QUOTES, 'UTF-8') ?>">
        <h3>Structure &amp; ORBAT</h3>
        <p>Organigramme, regroupements et équipes pour rattacher les effectifs.</p>
        <p class="eff-card-cta">Ouvrir →</p>
    </a>
    <a class="eff-card" href="<?= htmlspecialchars(url('deploiement'), ENT_QUOTES, 'UTF-8') ?>">
        <h3>Déploiement personnel</h3>
        <p>Vue terrain des affectations et mouvements.</p>
        <p class="eff-card-cta">Ouvrir →</p>
    </a>
    <?php endif; ?>
</div>

<?php if ($units === []): ?>
    <div class="eff-empty"><p>Aucune unité n’est encore définie dans la structure.</p></div>
<?php else: ?>
    <div class="eff-table-wrap">
        <table class="eff-table" style="min-width:520px">
            <thead>
                <tr>
                    <th>Unité</th>
                    <th>Type</th>
                    <th>Code</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($units as $u):
                $typeRaw = (string) ($u['type'] ?? '');
                $typeLabel = match ($typeRaw) {
                    'company', 'compagnie' => 'Compagnie',
                    'platoon', 'peloton' => 'Peloton',
                    'section' => 'Section',
                    'squad', 'groupe' => 'Groupe',
                    'hq', 'etat_major', 'état-major' => 'État-major',
                    'team', 'equipe', 'équipe' => 'Équipe',
                    'battalion', 'bataillon' => 'Bataillon',
                    default => $typeRaw !== '' ? $typeRaw : '—',
                };
                ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($u['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(trim((string) ($u['code'] ?? '')) ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
