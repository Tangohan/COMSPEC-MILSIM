<?php
declare(strict_types=1);

$user = $user ?? [];
$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');

$accountNavKey = 'devices';
$accountTitle = 'Appareils ATAK';
$accountLead = 'Votre téléphone ATAK tel que le réseau le voit : identité, liaison, certificat et diagnostic. Retirez un appareil s’il n’est plus le vôtre.';
$accountUser = $user;
require base_path('views/partials/account/shell_open.php');

// Fiches partagées avec le back-office membre (Mes appareils) ; ici les actions restent sur la page compte.
$revokeUrl = url('account/security/devices/revoke');
$setupUrl = url('atak/premiere-liaison');
?>

<div class="bo-member-situation atk-dev account-atak-devices">
    <?php require base_path('views/partials/atak_device_cards.php'); ?>

    <?php /* « Sur mon téléphone » : vrais téléphones ouverts depuis le QR code d'une app de l'ATAK en jeu. */ ?>
    <article class="bo-member-situation__card atk-dev__card" id="appareils-connectes">
        <header class="atk-dev__head">
            <div>
                <p class="bo-member-situation__kicker">Appareils connectés</p>
                <h2>Téléphones ouverts depuis le jeu</h2>
            </div>
        </header>
        <?php $mobileLinks = $mobileLinks ?? []; ?>
        <?php if ($mobileLinks === []): ?>
            <p class="atk-sheet__note">Aucun téléphone connecté. Dans le jeu, le bouton « Sur mon téléphone » des apps Comptes rendus, CR fin d’op et Carte affiche un QR code à scanner avec votre vrai téléphone.</p>
        <?php else: ?>
            <div class="atk-table-wrap">
                <table class="atk-table">
                    <thead><tr><th>Ouvert depuis</th><th>Partie</th><th>Dernière activité</th><th>Fin</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($mobileLinks as $ml): ?>
                        <tr>
                            <td><strong><?= $h(\App\Services\Atak\AtakMobileLinkService::APPS[(string) $ml['app']][1] ?? (string) $ml['app']) ?></strong><small translate="no"><?= $h((string) ($ml['user_agent'] ?? '')) ?></small></td>
                            <td translate="no"><?= $h(\App\Support\AtakMissionKey::label((string) ($ml['mission_key'] ?? ''))) ?></td>
                            <td class="is-mono"><?= $h(!empty($ml['last_seen_at']) ? date('d/m H:i', (int) strtotime($ml['last_seen_at'] . ' UTC')) : '—') ?></td>
                            <td class="is-mono"><?= $h(!empty($ml['session_expires_at']) ? date('d/m H:i', (int) strtotime($ml['session_expires_at'] . ' UTC')) : '—') ?></td>
                            <td>
                                <form method="post" action="<?= $h(url('account/security/devices/mobile/revoke')) ?>">
                                    <?= \App\Core\Csrf::field() ?>
                                    <input type="hidden" name="link_id" value="<?= (int) $ml['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Déconnecter</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <form method="post" action="<?= $h(url('account/security/devices/mobile/revoke')) ?>" style="margin-top:12px">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="link_id" value="0">
                <button type="submit" class="btn btn-sm btn-outline-danger">Déconnecter tous les téléphones</button>
            </form>
        <?php endif; ?>
    </article>
</div>

<?php require base_path('views/partials/account/shell_close.php'); ?>
