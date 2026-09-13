<?php

declare(strict_types=1);

$devices = is_array($devices ?? null) ? $devices : [];
$success = $success ?? null;
$error = $error ?? null;
$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');

$typeLabel = static function (string $type): string {
    return match (strtolower(trim($type))) {
        'phone' => 'Téléphone',
        'tablet' => 'Tablette',
        'radio' => 'Radio',
        'vehicle' => 'Véhicule',
        'desktop' => 'Poste',
        default => 'Appareil',
    };
};
$statusLabel = static function (string $status): string {
    return match (strtolower(trim($status))) {
        'active' => 'Autorisé',
        'pending' => 'En attente',
        'inactive' => 'Inactif',
        'lost' => 'Signalé perdu',
        'revoked' => 'Retiré',
        default => 'Inconnu',
    };
};
?>
<div class="bo-member-situation">
    <p class="bo-member-situation__lead">
        <a href="<?= $h(url('back-office/ma-situation/liaison-atak')) ?>">← Ma liaison ATAK</a>
    </p>

    <?php if ($success): ?>
        <p class="bo-member-situation__flash bo-member-situation__flash--ok"><?= $h($success) ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p class="bo-member-situation__flash bo-member-situation__flash--err"><?= $h($error) ?></p>
    <?php endif; ?>

    <section class="bo-member-situation__card">
        <h2>Vos appareils</h2>
        <p>Chaque ligne correspond à un terminal qui s’est présenté avec votre compte. Retirer un appareil l’empêche de se reconnecter jusqu’à une nouvelle association.</p>

        <?php if ($devices === []): ?>
            <p class="bo-member-situation__empty">Aucun téléphone ni tablette n’est encore associé à votre compte.</p>
            <a class="ath-btn ath-btn--solid" href="<?= $h(url('back-office/ma-situation/premiere-liaison')) ?>">Configurer ATAK</a>
        <?php else: ?>
            <ul class="bo-member-situation__list">
                <?php foreach ($devices as $device): ?>
                    <?php
                    if (!is_array($device)) {
                        continue;
                    }
                    $id = (int) ($device['id'] ?? 0);
                    $label = trim((string) ($device['terminal_label'] ?? ''));
                    if ($label === '') {
                        $label = $typeLabel((string) ($device['terminal_type'] ?? ''));
                    }
                    $callsign = trim((string) ($device['operator_callsign'] ?? $device['callsign'] ?? ''));
                    $status = strtolower(trim((string) ($device['status'] ?? '')));
                    $seen = trim((string) ($device['last_seen_at'] ?? ''));
                    $seenTs = $seen !== '' ? strtotime($seen) : false;
                    ?>
                    <li class="bo-member-situation__list-item">
                        <div>
                            <p class="bo-member-situation__kicker"><?= $h($typeLabel((string) ($device['terminal_type'] ?? ''))) ?></p>
                            <h3><?= $h($label) ?></h3>
                            <p>
                                <?= $h($statusLabel($status)) ?>
                                <?php if ($callsign !== ''): ?>
                                    · Indicatif <?= $h($callsign) ?>
                                <?php endif; ?>
                                <?php if ($seenTs !== false): ?>
                                    · Dernière activité <?= $h(date('d/m/Y à H:i', $seenTs)) ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php if ($id > 0 && $status !== 'revoked'): ?>
                            <form method="post" action="<?= $h(url('back-office/ma-situation/appareils/retirer')) ?>">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="terminal_id" value="<?= $id ?>">
                                <button type="submit" class="ath-btn" onclick="return confirm('Retirer cet appareil de votre compte ?');">Retirer l’appareil</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
