<?php
declare(strict_types=1);

$user = $user ?? [];
$devices = is_array($devices ?? null) ? $devices : [];
$success = $success ?? null;
$error = $error ?? null;

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

$accountNavKey = 'devices';
$accountTitle = 'Appareils liés';
$accountLead = 'Téléphones et tablettes ATAK associés à votre compte. Retirez un appareil s’il n’est plus le vôtre.';
$accountUser = $user;
require base_path('views/partials/account/shell_open.php');
?>

<section class="account-hub__panel">
    <div class="account-hub__panel-head">
        <p class="account-hub__panel-kicker">Sécurité</p>
        <h2 class="account-hub__panel-title">Vos appareils ATAK</h2>
        <p class="account-hub__panel-desc">Chaque ligne correspond à un terminal qui s’est présenté avec votre compte. Retirer un appareil l’empêche de se reconnecter jusqu’à une nouvelle association.</p>
    </div>
    <div class="account-hub__panel-body">
        <?php if ($devices === []): ?>
            <p>Aucun téléphone ni tablette n’est encore associé à votre compte.</p>
        <?php else: ?>
            <ul class="account-hub__stack" style="list-style:none;margin:0;padding:0;gap:0.85rem">
                <?php foreach ($devices as $device): ?>
                    <?php
                    $id = (int) ($device['id'] ?? 0);
                    $label = trim((string) ($device['terminal_label'] ?? ''));
                    if ($label === '') {
                        $label = $typeLabel((string) ($device['terminal_type'] ?? ''));
                    }
                    $callsign = trim((string) ($device['operator_callsign'] ?? $device['callsign'] ?? ''));
                    $status = strtolower(trim((string) ($device['status'] ?? '')));
                    $seen = trim((string) ($device['last_seen_at'] ?? ''));
                    ?>
                    <li class="account-hub__panel" style="margin:0;padding:1rem">
                        <p class="account-hub__panel-kicker"><?= htmlspecialchars($typeLabel((string) ($device['terminal_type'] ?? '')), ENT_QUOTES, 'UTF-8') ?></p>
                        <h3 class="account-hub__panel-title" style="margin-top:.2rem"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></h3>
                        <p class="account-hub__panel-desc">
                            <?= htmlspecialchars($statusLabel($status), ENT_QUOTES, 'UTF-8') ?>
                            <?php if ($callsign !== ''): ?>
                                · Indicatif <?= htmlspecialchars($callsign, ENT_QUOTES, 'UTF-8') ?>
                            <?php endif; ?>
                            <?php if ($seen !== ''): ?>
                                · Dernière activité <?= htmlspecialchars($seen, ENT_QUOTES, 'UTF-8') ?>
                            <?php endif; ?>
                        </p>
                        <?php if ($id > 0 && $status !== 'revoked'): ?>
                            <form method="post" action="<?= htmlspecialchars(url('account/security/devices/revoke'), ENT_QUOTES, 'UTF-8') ?>" style="margin-top:.75rem">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="terminal_id" value="<?= $id ?>">
                                <button type="submit" class="account-hub__btn" onclick="return confirm('Retirer cet appareil de votre compte ?');">Retirer l’appareil</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>

<?php require base_path('views/partials/account/shell_close.php'); ?>
