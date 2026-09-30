<?php
$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
$campaigns = is_array($campaigns ?? null) ? $campaigns : [];
$labels = [
    'ouverte' => 'Ouverte',
    'cloturee' => 'Clôturée',
    'en_commission' => 'En commission',
    'publiee' => 'Publiée',
    'archivee' => 'Archivée',
];
?>
<div class="adv-page">
    <?php require __DIR__ . '/_flash.php'; ?>
    <div class="adv-actions">
        <a class="ath-btn ath-btn--solid" href="<?= $h(url('back-office/rh/avancement/create')) ?>">Nouvelle campagne</a>
        <a class="ath-btn" href="<?= $h(url('back-office/organisation/grades')) ?>">Référentiel des grades</a>
    </div>
    <div class="adv-table-wrap">
        <table class="adv-table">
            <thead>
                <tr>
                    <th>Année</th>
                    <th>Grade</th>
                    <th>Filière</th>
                    <th>Fenêtre</th>
                    <th>Quota</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($campaigns as $campaign): ?>
                <tr>
                    <td><?= (int) ($campaign['year'] ?? 0) ?></td>
                    <td><a href="<?= $h(url('back-office/rh/avancement/' . (int) $campaign['id'])) ?>"><?= $h((string) ($campaign['grade_label'] ?? '')) ?></a></td>
                    <td><?= $h((string) ($campaign['filiere_label'] ?? '—')) ?></td>
                    <td><?= $h((string) ($campaign['opens_at'] ?? '')) ?> → <?= $h((string) ($campaign['closes_at'] ?? '')) ?></td>
                    <td><?= $campaign['quota_slots'] === null || $campaign['quota_slots'] === '' ? '—' : (int) $campaign['quota_slots'] ?></td>
                    <td><?= $h($labels[(string) ($campaign['status'] ?? '')] ?? (string) ($campaign['status'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($campaigns === []): ?>
                <tr><td colspan="6">Aucune campagne.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
