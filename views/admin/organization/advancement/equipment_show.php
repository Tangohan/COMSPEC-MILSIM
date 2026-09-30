<?php
declare(strict_types=1);

use App\Support\AdvancementCodes;

$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
$definition = is_array($definition ?? null) ? $definition : [];
$assignments = is_array($assignments ?? null) ? $assignments : [];
$did = (int) ($definition['id'] ?? 0);
?>
<div class="bo-adv">
    <?php include __DIR__ . '/_flash.php'; ?>
    <p class="bo-adv__back"><a href="<?= $h(url('back-office/referentiels/dotation')) ?>">← Dotation</a></p>
    <p class="bo-adv__hint"><?= $h((string) ($definition['category'] ?? '')) ?> · <?= $h((string) ($definition['description'] ?? '')) ?></p>

    <div class="bo-adv__table-wrap">
        <table class="bo-adv__table">
            <thead>
                <tr>
                    <th>Personnel</th>
                    <th>N° de série</th>
                    <th>Statut</th>
                    <th>Attribué le</th>
                    <th>Notes</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assignments as $a): ?>
                    <tr>
                        <td><?= $h((string) ($a['display_name'] ?? '')) ?></td>
                        <td class="bo-adv__mono"><?= $h((string) ($a['serial_number'] ?? '—')) ?></td>
                        <td><?= $h(AdvancementCodes::equipmentLabel((string) ($a['status'] ?? ''))) ?></td>
                        <td><?= $h((string) ($a['assigned_at'] ?? '')) ?></td>
                        <td><?= $h((string) ($a['notes'] ?? '')) ?></td>
                        <td>
                            <form method="post" action="<?= $h(url('back-office/referentiels/dotation/attributions/' . (int) ($a['id'] ?? 0) . '/statut')) ?>" class="bo-adv__inline-form">
                                <?= \App\Core\Csrf::field() ?>
                                <select name="status">
                                    <?php foreach ([AdvancementCodes::EQUIP_ISSUED, AdvancementCodes::EQUIP_REPAIR, AdvancementCodes::EQUIP_LOST, AdvancementCodes::EQUIP_RETURNED] as $st): ?>
                                        <option value="<?= $h($st) ?>" <?= (string) ($a['status'] ?? '') === $st ? 'selected' : '' ?>><?= $h(AdvancementCodes::equipmentLabel($st)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="notes" placeholder="Motif">
                                <button class="ath-btn" type="submit">Mettre à jour</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($assignments === []): ?>
                    <tr><td colspan="6" class="bo-adv__empty">Aucune attribution pour cet article.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
