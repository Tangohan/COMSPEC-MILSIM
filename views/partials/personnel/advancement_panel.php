<?php
$advancementPanel = is_array($advancementPanel ?? null) ? $advancementPanel : null;
if ($advancementPanel === null) {
    return;
}
$offer = is_array($advancementPanel['offer'] ?? null) ? $advancementPanel['offer'] : null;
$history = is_array($advancementPanel['history'] ?? null) ? $advancementPanel['history'] : [];
$current = is_array($advancementPanel['current'] ?? null) ? $advancementPanel['current'] : null;
if ($current === null && $history === [] && $offer === null) {
    return;
}
$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
$isSelf = !empty($advancementPanelIsSelf);
$via = ['initial' => 'initial', 'anciennete' => 'ancienneté', 'choix' => 'choix'];
?>
<section class="adv-panel adv-panel--fiche">
    <p class="adv-kicker">Avancement</p>
    <?php if ($current !== null): ?>
        <h2><?= $h((string) ($current['label'] ?? 'Grade')) ?></h2>
    <?php else: ?>
        <h2>Grade non renseigné sur l’historique</h2>
    <?php endif; ?>
    <?php if ($offer !== null): ?>
        <?php if (!empty($offer['is_eligible'])): ?>
            <p>Vous êtes éligible à l’avancement au grade de <?= $h((string) ($offer['grade_label'] ?? '')) ?>.</p>
            <?php if ($isSelf && empty($offer['already_volunteered'])): ?>
                <form method="post" action="<?= $h(url('back-office/ma-situation/avancement/' . (int) $offer['campaign_id'] . '/volontaire')) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <button class="ath-btn ath-btn--solid" type="submit">Me porter volontaire</button>
                </form>
            <?php elseif (!empty($offer['already_volunteered'])): ?>
                <p>Candidature déjà enregistrée.</p>
            <?php endif; ?>
        <?php else: ?>
            <p><?= $h((string) ($offer['eligibility_reason'] ?? '')) ?></p>
        <?php endif; ?>
    <?php endif; ?>
    <?php if ($history !== []): ?>
        <ol class="adv-timeline">
            <?php foreach ($history as $row): ?>
                <li>
                    <strong><?= $h((string) ($row['label'] ?? '')) ?></strong>
                    <span><?= $h((string) ($row['obtained_at'] ?? '')) ?> · <?= $h($via[(string) ($row['obtained_via'] ?? '')] ?? '') ?></span>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>
