<?php
declare(strict_types=1);

use App\Support\AdvancementCodes;

$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
$campaigns = is_array($campaigns ?? null) ? $campaigns : [];
?>
<div class="bo-adv">
    <?php include __DIR__ . '/_flash.php'; ?>
    <div class="bo-adv__toolbar">
        <a class="ath-btn ath-btn--solid" href="<?= $h(url('back-office/rh/avancement/create')) ?>">Nouvelle campagne</a>
        <a class="ath-btn" href="<?= $h(url('back-office/organisation/grades')) ?>">Échelle de grades</a>
    </div>
    <div class="bo-adv__table-wrap">
        <table class="bo-adv__table">
            <thead>
                <tr>
                    <th>Année</th>
                    <th>Grade visé</th>
                    <th>Filière</th>
                    <th>Fenêtre</th>
                    <th>Quota</th>
                    <th>Candidatures</th>
                    <th>Statut</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($campaigns as $c): ?>
                    <tr>
                        <td><?= (int) ($c['year'] ?? 0) ?></td>
                        <td><strong><?= $h((string) ($c['grade_label'] ?? '')) ?></strong></td>
                        <td><?= $h((string) ($c['filiere_label'] ?? '—')) ?></td>
                        <td><?= $h((string) ($c['opens_at'] ?? '')) ?> → <?= $h((string) ($c['closes_at'] ?? '')) ?></td>
                        <td><?= isset($c['quota_slots']) && $c['quota_slots'] !== null && $c['quota_slots'] !== '' ? (int) $c['quota_slots'] : '—' ?></td>
                        <td><?= (int) ($c['candidacies_count'] ?? 0) ?> <span class="bo-adv__muted">(<?= (int) ($c['eligible_count'] ?? 0) ?> éligibles)</span></td>
                        <td><?= $h(AdvancementCodes::campaignLabel((string) ($c['status'] ?? ''))) ?></td>
                        <td class="bo-adv__actions">
                            <a href="<?= $h(url('back-office/rh/avancement/' . (int) ($c['id'] ?? 0))) ?>">Candidatures</a>
                            <?php if ((string) ($c['status'] ?? '') === AdvancementCodes::CAMPAIGN_IN_COMMISSION || (string) ($c['status'] ?? '') === AdvancementCodes::CAMPAIGN_PUBLISHED): ?>
                                <a href="<?= $h(url('back-office/rh/avancement/' . (int) ($c['id'] ?? 0) . '/commission')) ?>">Commission</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($campaigns === []): ?>
                    <tr><td colspan="8" class="bo-adv__empty">Aucune campagne. Ouvrez une vague annuelle par grade et filière.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
