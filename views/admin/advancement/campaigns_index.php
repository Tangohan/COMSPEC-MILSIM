<?php
require __DIR__ . '/_helpers.php';
$campaigns = is_array($campaigns ?? null) ? $campaigns : [];
$labels = [
    'ouverte' => 'Ouverte',
    'cloturee' => 'Clôturée',
    'en_commission' => 'En commission',
    'publiee' => 'Publiée',
    'archivee' => 'Archivée',
];
$format = static function (string $iso): string {
    $ts = strtotime(substr($iso, 0, 10));

    return $ts !== false ? date('d/m/Y', $ts) : $iso;
};
?>
<div class="adv-page">
    <?php require __DIR__ . '/_flash.php'; ?>
    <section class="adv-panel">
        <p class="adv-kicker">Voie choix</p>
        <h2>Campagnes d’avancement <?= adv_info('Campagnes', 'Une campagne = un grade, une année, une fenêtre. L’ancienneté, elle, avance toute seule dès que le temps de grade est atteint.') ?></h2>
        <p>Ouvrez une campagne pour le grade visé, collectez les candidatures, réunissez la commission, puis publiez le tableau.</p>
        <div class="adv-actions">
            <a class="ath-btn ath-btn--solid" href="<?= adv_h(url('back-office/rh/avancement/create')) ?>">Nouvelle campagne</a>
            <a class="ath-btn" href="<?= adv_h(url('back-office/organisation/grades')) ?>">Référentiel des grades</a>
        </div>
    </section>
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
                    <td><a href="<?= adv_h(url('back-office/rh/avancement/' . (int) $campaign['id'])) ?>"><?= adv_h((string) ($campaign['grade_label'] ?? '')) ?></a></td>
                    <td><?= adv_h((string) ($campaign['filiere_label'] ?? '—')) ?></td>
                    <td><?= adv_h($format((string) ($campaign['opens_at'] ?? ''))) ?> → <?= adv_h($format((string) ($campaign['closes_at'] ?? ''))) ?></td>
                    <td><?= $campaign['quota_slots'] === null || $campaign['quota_slots'] === '' ? 'Illimité' : (int) $campaign['quota_slots'] ?></td>
                    <td><span class="adv-badge"><?= adv_h($labels[(string) ($campaign['status'] ?? '')] ?? (string) ($campaign['status'] ?? '')) ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($campaigns === []): ?>
                <tr><td colspan="6">Aucune campagne. Créez-en une pour ouvrir la voie choix.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
