<?php
declare(strict_types=1);

use App\Support\AdvancementCodes;

$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
$campaign = is_array($campaign ?? null) ? $campaign : [];
$candidacies = is_array($candidacies ?? null) ? $candidacies : [];
$commission = is_array($commission ?? null) ? $commission : null;
$commissionMembers = is_array($commissionMembers ?? null) ? $commissionMembers : [];
$billets = is_array($billets ?? null) ? $billets : [];
$members = is_array($members ?? null) ? $members : [];
$cid = (int) ($campaign['id'] ?? 0);
$status = (string) ($campaign['status'] ?? '');
$personLabel = static function (array $u): string {
    $n = trim((string) ($u['display_name'] ?? ''));
    if ($n !== '') {
        return $n;
    }
    $n = trim((string) (($u['last_name'] ?? '') . ' ' . ($u['first_name'] ?? '')));

    return $n !== '' ? $n : (string) ($u['username'] ?? ('#' . ($u['id'] ?? '')));
};
?>
<div class="bo-adv">
    <?php include __DIR__ . '/_flash.php'; ?>
    <p class="bo-adv__back"><a href="<?= $h(url('back-office/rh/avancement')) ?>">← Campagnes</a></p>

    <section class="bo-adv__intro">
        <div>
            <p class="bo-adv__kicker"><?= $h(AdvancementCodes::campaignLabel($status)) ?></p>
            <h2><?= $h((string) ($campaign['grade_label'] ?? 'Campagne')) ?> · <?= (int) ($campaign['year'] ?? 0) ?></h2>
            <p>Fenêtre <?= $h((string) ($campaign['opens_at'] ?? '')) ?> → <?= $h((string) ($campaign['closes_at'] ?? '')) ?>
                · quota <?= isset($campaign['quota_slots']) && $campaign['quota_slots'] !== null && $campaign['quota_slots'] !== '' ? (int) $campaign['quota_slots'] : 'illimité' ?></p>
        </div>
        <div class="bo-adv__toolbar">
            <?php if ($status === AdvancementCodes::CAMPAIGN_OPEN): ?>
                <form method="post" action="<?= $h(url('back-office/rh/avancement/' . $cid . '/recheck')) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <button class="ath-btn" type="submit">Revérifier l’éligibilité</button>
                </form>
                <form method="post" action="<?= $h(url('back-office/rh/avancement/' . $cid . '/commissionner')) ?>" onsubmit="return confirm('Verrouiller les candidatures et ouvrir la commission ?');">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="meeting_date" value="<?= $h(date('Y-m-d')) ?>">
                    <button class="ath-btn ath-btn--solid" type="submit">Passer en commission</button>
                </form>
            <?php endif; ?>
            <?php if ($status === AdvancementCodes::CAMPAIGN_IN_COMMISSION || $status === AdvancementCodes::CAMPAIGN_PUBLISHED): ?>
                <a class="ath-btn ath-btn--solid" href="<?= $h(url('back-office/rh/avancement/' . $cid . '/commission')) ?>">Ouvrir la commission</a>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($status === AdvancementCodes::CAMPAIGN_OPEN): ?>
        <section class="bo-adv__panel">
            <div class="bo-adv__panel-head">
                <h2>Ajouter une candidature</h2>
                <p>Le personnel peut aussi se porter volontaire depuis sa fiche. La mobilité n’est qu’informative — elle ne réserve pas le poste ORBAT.</p>
            </div>
            <form method="post" action="<?= $h(url('back-office/rh/avancement/' . $cid . '/candidatures')) ?>" class="bo-adv__inline-form">
                <?= \App\Core\Csrf::field() ?>
                <select name="personnel_id" required>
                    <option value="">Personnel</option>
                    <?php foreach ($members as $u): ?>
                        <option value="<?= (int) ($u['id'] ?? 0) ?>"><?= $h($personLabel($u)) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="requested_billet_id">
                    <option value="0">Aucun poste visé</option>
                    <?php foreach ($billets as $b): ?>
                        <option value="<?= (int) ($b['id'] ?? 0) ?>"><?= $h((string) (($b['unit_name'] ?? '') . ' · ' . ($b['title'] ?? $b['code'] ?? ''))) ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="bo-adv__check"><input type="checkbox" name="mobility_requested" value="1"> Mobilité demandée</label>
                <button class="ath-btn ath-btn--solid" type="submit">Enregistrer</button>
            </form>
        </section>
    <?php endif; ?>

    <?php if ($commissionMembers !== []): ?>
        <p class="bo-adv__hint">Commission : <?php foreach ($commissionMembers as $m): ?><?= $h((string) ($m['display_name'] ?? '')) ?> (<?= $h((string) ($m['role'] ?? '')) ?>) <?php endforeach; ?></p>
    <?php endif; ?>

    <div class="bo-adv__table-wrap">
        <table class="bo-adv__table">
            <thead>
                <tr>
                    <th>Personnel</th>
                    <th>Éligible</th>
                    <th>Motif</th>
                    <th>Classement</th>
                    <th>Mobilité</th>
                    <th>Avis</th>
                    <th>Décision</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($candidacies as $row): ?>
                    <tr>
                        <td><strong><?= $h((string) ($row['display_name'] ?? $row['username'] ?? '')) ?></strong></td>
                        <td><?= !empty($row['live_eligible']) || !empty($row['is_eligible']) ? 'Oui' : 'Non' ?></td>
                        <td><?= $h((string) ($row['live_reason'] ?? $row['eligibility_reason'] ?? '—')) ?></td>
                        <td><?= isset($row['preference_rank']) && $row['preference_rank'] !== null && $row['preference_rank'] !== '' ? (int) $row['preference_rank'] : '—' ?></td>
                        <td><?= !empty($row['mobility_requested'])
                            ? $h('Oui' . (!empty($row['requested_billet_title']) ? ' · ' . $row['requested_billet_title'] : ''))
                            : 'Non' ?></td>
                        <td><?= $h(AdvancementCodes::opinionLabel(isset($row['commission_opinion']) ? (string) $row['commission_opinion'] : null)) ?></td>
                        <td><?= $h(AdvancementCodes::decisionLabel(isset($row['decision']) ? (string) $row['decision'] : null)) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($candidacies === []): ?>
                    <tr><td colspan="7" class="bo-adv__empty">Aucune candidature pour l’instant.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
