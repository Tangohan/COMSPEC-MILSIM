<?php

declare(strict_types=1);

use App\Repositories\AtakRealismRepository;

$terminals = is_array($terminals ?? null) ? $terminals : [];
$success = $success ?? null;
$error = $error ?? null;
$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');

$statusLabel = static function (string $status, bool $recent): string {
    $status = strtolower(trim($status));
    if ($status === 'revoked') {
        return 'Retiré';
    }
    if ($status === 'lost') {
        return 'Signalé perdu';
    }
    if ($status === 'pending') {
        return 'En attente';
    }
    if ($status === 'active' && $recent) {
        return 'En service';
    }
    if ($status === 'active') {
        return 'Autorisé';
    }

    return 'Inconnu';
};

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
?>
<div class="bo-member-situation">
    <?php if ($success): ?>
        <p class="bo-member-situation__flash bo-member-situation__flash--ok"><?= $h($success) ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p class="bo-member-situation__flash bo-member-situation__flash--err"><?= $h($error) ?></p>
    <?php endif; ?>

    <div class="bo-member-situation__actions">
        <a class="ath-btn ath-btn--solid" href="<?= $h(url('back-office/ma-situation/appareils')) ?>">Gérer les appareils</a>
        <a class="ath-btn" href="<?= $h(url('back-office/ma-situation/premiere-liaison')) ?>">Configurer ATAK</a>
        <a class="ath-btn" href="<?= $h(url('atak')) ?>">Ouvrir la carte</a>
    </div>

    <?php if ($terminals === []): ?>
        <section class="bo-member-situation__card">
            <h2>Aucun terminal associé</h2>
            <p>Aucun téléphone ATAK n’est encore lié à votre compte. Commencez par Configurer ATAK, puis associez l’appareil depuis le jeu ou le téléphone.</p>
        </section>
    <?php else: ?>
        <div class="bo-member-situation__grid">
            <?php foreach ($terminals as $terminal): ?>
                <?php
                if (!is_array($terminal)) {
                    continue;
                }
                $lastRaw = (string) ($terminal['last_seen_at'] ?? $terminal['updated_at'] ?? '');
                $lastTs = strtotime($lastRaw);
                $recent = $lastTs !== false && (time() - $lastTs) < 7 * 86400;
                $statusKey = strtolower(trim((string) ($terminal['status'] ?? '')));
                $deviceName = trim((string) ($terminal['terminal_label'] ?? ''));
                if ($deviceName === '' || preg_match('/^\d{2}-\d{4}-\d+$/', $deviceName) === 1) {
                    $deviceName = $typeLabel((string) ($terminal['terminal_type'] ?? '')) . ' ATAK';
                }
                $identity = AtakRealismRepository::liaisonIdentity($terminal);
                $certStatus = trim((string) ($terminal['certificate_status'] ?? ''));
                $certRef = trim((string) ($terminal['certificate_ref'] ?? ''));
                $certExpires = trim((string) ($terminal['certificate_expires_at'] ?? ''));
                $certAuth = trim((string) ($identity['authority'] ?? ''));
                $trust = trim((string) ($identity['trust'] ?? ''));
                ?>
                <article class="bo-member-situation__card">
                    <div class="bo-member-situation__card-head">
                        <h2><?= $h($deviceName) ?></h2>
                        <span class="bo-member-situation__badge<?= $recent && $statusKey === 'active' ? ' is-ok' : '' ?>">
                            <?= $h($statusLabel($statusKey, $recent)) ?>
                        </span>
                    </div>
                    <dl class="bo-member-situation__dl">
                        <div>
                            <dt>Type</dt>
                            <dd><?= $h($typeLabel((string) ($terminal['terminal_type'] ?? ''))) ?></dd>
                        </div>
                        <div>
                            <dt>Dernière liaison</dt>
                            <dd><?= $lastTs !== false ? $h(date('d/m/Y à H:i', $lastTs)) : 'Non enregistrée' ?></dd>
                        </div>
                        <div>
                            <dt>Chaîne de confiance</dt>
                            <dd><?= $h($trust !== '' ? $trust : 'Non établie') ?></dd>
                        </div>
                        <div>
                            <dt>Autorité du certificat</dt>
                            <dd><?= $h($certAuth !== '' && $certAuth !== '—' ? $certAuth : 'Non renseignée') ?></dd>
                        </div>
                        <?php if ($certRef !== ''): ?>
                        <div>
                            <dt>Référence certificat</dt>
                            <dd><?= $h($certRef) ?></dd>
                        </div>
                        <?php endif; ?>
                        <?php if ($certStatus !== ''): ?>
                        <div>
                            <dt>État du certificat</dt>
                            <dd><?= $h($certStatus) ?></dd>
                        </div>
                        <?php endif; ?>
                        <?php if ($certExpires !== ''): ?>
                        <div>
                            <dt>Fin de validité</dt>
                            <dd><?php
                                $expTs = strtotime($certExpires);
                                echo $expTs !== false ? $h(date('d/m/Y', $expTs)) : $h($certExpires);
                            ?></dd>
                        </div>
                        <?php endif; ?>
                    </dl>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
