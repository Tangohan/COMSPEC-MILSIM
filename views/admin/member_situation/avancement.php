<?php
require dirname(__DIR__) . '/advancement/_helpers.php';
$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
$panel = is_array($panel ?? null) ? $panel : [];
$current = is_array($panel['current'] ?? null) ? $panel['current'] : null;
$history = is_array($panel['history'] ?? null) ? $panel['history'] : [];
$offer = is_array($panel['offer'] ?? null) ? $panel['offer'] : null;
$via = [
    'initial' => 'Grade initial',
    'anciennete' => 'Ancienneté',
    'choix' => 'Choix',
    'exception' => 'Passage exceptionnel',
];
$format = static function (string $iso) use ($h): string {
    $ts = strtotime(substr($iso, 0, 10));

    return $ts !== false ? $h(date('d/m/Y', $ts)) : $h($iso);
};
?>
<div class="bo-member-situation adv-page">
    <?php if (!empty($success)): ?><p class="adv-flash adv-flash--ok"><?= $h($success) ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="adv-flash adv-flash--bad"><?= $h($error) ?></p><?php endif; ?>

    <section class="adv-panel">
        <p class="adv-kicker">Grade actuel <?= adv_info('Grade actuel', 'Ligne d’historique ouverte : elle n’est jamais réécrite. Un nouveau grade ajoute une ligne et clôture la précédente.') ?></p>
        <?php if ($current === null): ?>
            <h2>Aucun grade enregistré</h2>
            <p>Le grade détenu apparaît ici dès qu’une ligne d’historique est ouverte.</p>
        <?php else: ?>
            <h2><?= $h((string) ($current['label'] ?? '')) ?></h2>
            <p>Depuis le <?= $format((string) ($current['obtained_at'] ?? '')) ?> · <?= $h($via[(string) ($current['obtained_via'] ?? '')] ?? (string) ($current['obtained_via'] ?? '')) ?></p>
        <?php endif; ?>
    </section>

    <?php if ($offer !== null): ?>
        <section class="adv-offer">
            <?php if (!empty($offer['is_eligible'])): ?>
                <h2>Vous êtes éligible à l’avancement au grade de <?= $h((string) ($offer['grade_label'] ?? '')) ?></h2>
            <?php else: ?>
                <h2>Campagne ouverte pour <?= $h((string) ($offer['grade_label'] ?? 'le grade suivant')) ?></h2>
                <p><?= $h((string) ($offer['eligibility_reason'] ?? 'Les conditions ne sont pas réunies.')) ?></p>
            <?php endif; ?>
            <?php if (!empty($offer['already_volunteered'])): ?>
                <p>Votre candidature est déjà enregistrée.</p>
            <?php elseif (!empty($offer['is_eligible'])): ?>
                <form method="post" action="<?= $h(url('back-office/ma-situation/avancement/' . (int) $offer['campaign_id'] . '/volontaire')) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <button class="ath-btn ath-btn--solid" type="submit">Me porter volontaire</button>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="adv-panel">
        <h2>Historique <?= adv_info('Historique', 'Chaque promotion (initial, ancienneté, choix ou exception) reste une ligne. On corrige en ajoutant, pas en écrasant.') ?></h2>
        <?php if ($history === []): ?>
            <p>Aucune ligne pour l’instant.</p>
        <?php else: ?>
            <ol class="adv-timeline">
                <?php foreach ($history as $row): ?>
                    <li>
                        <strong><?= $h((string) ($row['label'] ?? '')) ?></strong>
                        <span><?= $format((string) ($row['obtained_at'] ?? '')) ?><?php if (!empty($row['ends_at'])): ?> — <?= $format((string) $row['ends_at']) ?><?php else: ?> — en cours<?php endif; ?></span>
                        <span><?= $h($via[(string) ($row['obtained_via'] ?? '')] ?? '') ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>
</div>
