<?php
declare(strict_types=1);
use App\Support\AdvancementCodes;
$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
$assignments = is_array($assignments ?? null) ? $assignments : [];

$byStatus = [AdvancementCodes::EQUIP_ISSUED => 0, AdvancementCodes::EQUIP_REPAIR => 0, AdvancementCodes::EQUIP_LOST => 0, AdvancementCodes::EQUIP_RETURNED => 0];
foreach ($assignments as $row) {
    $st = (string) ($row['status'] ?? '');
    if (isset($byStatus[$st])) {
        $byStatus[$st]++;
    }
}
$inHand = $byStatus[AdvancementCodes::EQUIP_ISSUED] + $byStatus[AdvancementCodes::EQUIP_REPAIR];
$tone = static fn (string $status): string => match ($status) {
    AdvancementCodes::EQUIP_ISSUED => 'ok',
    AdvancementCodes::EQUIP_REPAIR => 'warn',
    AdvancementCodes::EQUIP_LOST => 'bad',
    default => 'muted',
};
$date = static function (mixed $raw): string {
    $ts = strtotime((string) $raw);

    return $ts !== false ? date('d/m/Y', $ts) : '—';
};
$iconKit = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5"/><path d="M12 13v8"/></svg>';

$summary = [
    'kicker' => 'Dossier individuel · Dotation',
    'focus' => [
        'label' => 'Matériel en main',
        'value' => $inHand > 0 ? $inHand . ' article' . ($inHand > 1 ? 's' : '') : 'Aucun article',
        'meta' => 'Attribué à votre nom par l’encadrement, avec numéro de série.',
        'badge_html' => $iconKit,
        'empty' => $inHand === 0,
    ],
    'links' => [
        ['label' => 'Mon coffre', 'href' => url('back-office/ma-situation/coffre')],
        ['label' => 'Dossier de carrière', 'href' => url('back-office/ma-situation/carriere')],
    ],
    'stats' => [
        ['label' => 'Articles', 'value' => count($assignments), 'note' => 'Toutes attributions confondues.'],
        ['label' => 'En dotation', 'value' => $byStatus[AdvancementCodes::EQUIP_ISSUED], 'note' => 'En votre possession.'],
        ['label' => 'En réparation', 'value' => $byStatus[AdvancementCodes::EQUIP_REPAIR], 'note' => 'Retour prévu après réparation.'],
        ['label' => 'Restitués ou perdus', 'value' => $byStatus[AdvancementCodes::EQUIP_RETURNED] + $byStatus[AdvancementCodes::EQUIP_LOST], 'note' => 'Conservés dans l’historique.'],
    ],
];
?>
<div class="msd">
    <?php require __DIR__ . '/_summary.php'; ?>

    <section class="msd-card" aria-labelledby="msd-dotation-title">
        <header class="msd-card__head">
            <div>
                <p class="msd-card__kicker">Inventaire</p>
                <h2 class="msd-card__title" id="msd-dotation-title">Mon matériel</h2>
            </div>
            <?php if ($assignments !== []): ?>
                <span class="msd-card__count"><?= count($assignments) ?> article<?= count($assignments) > 1 ? 's' : '' ?></span>
            <?php endif; ?>
        </header>
        <?php if ($assignments === []): ?>
            <div class="msd-empty">
                <span class="msd-empty__icon"><?= $iconKit ?></span>
                <strong>Aucune dotation pour l’instant</strong>
                <p>Les articles attribués nominativement par l’encadrement apparaîtront ici avec leur numéro de série.</p>
            </div>
        <?php else: ?>
            <div class="msd-items">
                <?php foreach ($assignments as $row): ?>
                    <?php $status = (string) ($row['status'] ?? ''); ?>
                    <article class="msd-item<?= in_array($status, [AdvancementCodes::EQUIP_RETURNED, AdvancementCodes::EQUIP_LOST], true) ? ' is-muted' : '' ?>">
                        <div class="msd-item__top">
                            <span class="msd-item__icon"><?= $iconKit ?></span>
                            <span class="msd-pill msd-pill--<?= $h($tone($status)) ?>"><?= $h(AdvancementCodes::equipmentLabel($status)) ?></span>
                        </div>
                        <div>
                            <p class="msd-item__kicker"><?= $h((string) ($row['category'] ?? '') !== '' ? $row['category'] : 'Matériel') ?></p>
                            <h3 class="msd-item__title"><?= $h((string) ($row['definition_name'] ?? '')) ?></h3>
                        </div>
                        <dl class="msd-item__facts">
                            <div><dt>N° de série</dt><dd class="msd-mono"><?= $h((string) ($row['serial_number'] ?? '') !== '' ? $row['serial_number'] : '—') ?></dd></div>
                            <div><dt>Attribué le</dt><dd><?= $h($date($row['assigned_at'] ?? '')) ?></dd></div>
                        </dl>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
