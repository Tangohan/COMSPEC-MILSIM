<?php
require __DIR__ . '/_helpers.php';

$campaigns = is_array($campaigns ?? null) ? $campaigns : [];
$format = static function (string $iso): string {
    $ts = strtotime(substr($iso, 0, 10));

    return $ts !== false ? date('d/m/Y', $ts) : $iso;
};
$tone = static function (string $status): string {
    return match ($status) {
        'ouverte', \App\Support\AdvancementCodes::CAMPAIGN_OPEN, 'publiee', \App\Support\AdvancementCodes::CAMPAIGN_PUBLISHED => 'ok',
        'en_commission', \App\Support\AdvancementCodes::CAMPAIGN_IN_COMMISSION => 'wait',
        default => 'quiet',
    };
};
$count = count($campaigns);
?>
<div class="adv-page">
    <?php require __DIR__ . '/_flash.php'; ?>
    <section class="adv-hero">
        <div>
            <p class="adv-kicker">Au choix</p>
            <p class="adv-lead">
                <?php if ($count < 1): ?>
                    Aucune campagne pour l’instant.
                <?php else: ?>
                    <?= $count ?> campagne<?= $count > 1 ? 's' : '' ?>.
                <?php endif; ?>
                Chaque campagne vise un grade, pour une année et une période de candidatures. L’ancienneté, elle, avance dès que le temps de grade est atteint.
            </p>
        </div>
        <div class="adv-actions">
            <a class="ath-btn ath-btn--solid" href="<?= adv_h(url('back-office/rh/avancement/create')) ?>">Nouvelle campagne</a>
            <a class="ath-btn" href="<?= adv_h(url('back-office/referentiels/grades')) ?>">Référentiel des grades</a>
            <a class="ath-btn" href="<?= adv_h(url('back-office/organisation/grades')) ?>">Règles d’avancement</a>
        </div>
    </section>

    <?php if ($campaigns === []): ?>
        <div class="adv-empty" role="status">
            <p class="adv-empty__title">Aucune campagne ouverte</p>
            <p>Créez une campagne pour recueillir les candidatures à un grade, réunir la commission, puis publier le tableau.</p>
        </div>
    <?php else: ?>
        <ul class="adv-campaigns">
            <?php foreach ($campaigns as $campaign): ?>
                <?php
                $status = (string) ($campaign['status'] ?? '');
                $filiere = trim((string) ($campaign['filiere_label'] ?? ''));
                $quota = $campaign['quota_slots'] ?? null;
                $places = ($quota === null || $quota === '') ? 'Places non limitées' : ((int) $quota . ' place' . ((int) $quota > 1 ? 's' : ''));
                ?>
                <li>
                    <a class="adv-campaign" href="<?= adv_h(url('back-office/rh/avancement/' . (int) ($campaign['id'] ?? 0))) ?>">
                        <span class="adv-campaign__year"><?= (int) ($campaign['year'] ?? 0) ?></span>
                        <span class="adv-campaign__body">
                            <strong><?= adv_h((string) ($campaign['grade_label'] ?? 'Grade')) ?></strong>
                            <span><?= adv_h($filiere !== '' ? $filiere : 'Filière du grade') ?> · du <?= adv_h($format((string) ($campaign['opens_at'] ?? ''))) ?> au <?= adv_h($format((string) ($campaign['closes_at'] ?? ''))) ?> · <?= adv_h($places) ?></span>
                        </span>
                        <span class="adv-badge adv-badge--<?= adv_h($tone($status)) ?>"><?= adv_h(\App\Support\AdvancementCodes::campaignLabel($status)) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
