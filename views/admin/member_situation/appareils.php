<?php

declare(strict_types=1);

use App\Repositories\AtakRealismRepository;
use App\Repositories\GamePhoneIdentityRepository;
use App\Support\AtakDevicePresenter as Dev;

$devices = is_array($devices ?? null) ? $devices : [];
$gamePhone = is_array($gamePhone ?? null) ? $gamePhone : null;
$success = $success ?? null;
$error = $error ?? null;
$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');

$statusLabel = static function (string $status): string {
    return match (strtolower(trim($status))) {
        'active' => 'Autorisé sur le réseau',
        'pending' => 'En attente de validation',
        'inactive' => 'Inactif',
        'lost' => 'Signalé perdu',
        'revoked' => 'Retiré',
        default => 'Inconnu',
    };
};
$fmtDate = static function (mixed $raw, string $format = 'd/m/Y à H:i'): string {
    $raw = trim((string) $raw);
    $ts = $raw !== '' ? strtotime($raw) : false;

    return $ts === false ? '—' : date($format, $ts);
};
$formatLabels = GamePhoneIdentityRepository::FORMATS;
$stateLabels = GamePhoneIdentityRepository::DEVICE_STATES;

// Le téléphone en jeu se rattache au premier terminal « téléphone » ; sinon il a sa propre fiche.
$phoneAttached = false;
$cards = [];
foreach ($devices as $device) {
    if (!is_array($device)) {
        continue;
    }
    $type = Dev::terminalType($device);
    $withPhone = !$phoneAttached && $gamePhone !== null && $type === 'phone';
    if ($withPhone) {
        $phoneAttached = true;
    }
    $cards[] = ['terminal' => $device, 'type' => $type, 'phone' => $withPhone ? $gamePhone : null];
}
if (!$phoneAttached && $gamePhone !== null) {
    $cards[] = ['terminal' => null, 'type' => 'phone', 'phone' => $gamePhone];
}
?>
<div class="bo-member-situation atk-dev">
    <p class="bo-member-situation__lead">
        <a href="<?= $h(url('back-office/ma-situation/liaison-atak')) ?>">← Ma liaison ATAK</a>
    </p>

    <?php if ($success): ?>
        <p class="bo-member-situation__flash bo-member-situation__flash--ok"><?= $h($success) ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p class="bo-member-situation__flash bo-member-situation__flash--err"><?= $h($error) ?></p>
    <?php endif; ?>

    <?php if ($cards === []): ?>
        <section class="bo-member-situation__card atk-dev__empty">
            <?php $phone = ['callsign' => '', 'link' => 'never', 'subtitle' => 'Pas encore associé', 'size' => 'sm']; require base_path('views/partials/atak_android_phone.php'); ?>
            <div>
                <h2>Aucun appareil associé</h2>
                <p>Votre téléphone ATAK apparaît ici dès votre première connexion depuis le jeu : numéro, IMEI, adresse MAC, batterie, certificat et qualité de liaison.</p>
                <a class="ath-btn ath-btn--solid" href="<?= $h(url('back-office/ma-situation/premiere-liaison')) ?>">Configurer ATAK</a>
            </div>
        </section>
    <?php endif; ?>

    <?php foreach ($cards as $card):
        $t = is_array($card['terminal']) ? $card['terminal'] : [];
        $p = is_array($card['phone']) ? $card['phone'] : [];
        $type = (string) $card['type'];
        $id = (int) ($t['id'] ?? 0);
        $status = strtolower(trim((string) ($t['status'] ?? '')));
        $callsign = trim((string) ($t['operator_callsign'] ?? $t['callsign'] ?? $p['callsign'] ?? ''));
        $seenRaw = (string) ($p['device_seen_at'] ?? '');
        $tSeen = (string) ($t['last_seen_at'] ?? '');
        if ($tSeen !== '' && ($seenRaw === '' || (strtotime($tSeen) ?: 0) > (strtotime($seenRaw) ?: 0))) {
            $seenRaw = $tSeen;
        }
        $link = Dev::linkState($seenRaw !== '' ? $seenRaw : null);
        $deviceState = strtoupper(trim((string) ($p['device_state'] ?? '')));
        $battery = isset($p['battery_pct']) && $p['battery_pct'] !== null && $p['battery_pct'] !== '' ? max(0, min(100, (int) $p['battery_pct'])) : null;
        $bars = isset($p['signal_bars']) && $p['signal_bars'] !== null && $p['signal_bars'] !== '' ? (int) $p['signal_bars'] : null;
        $number = trim((string) ($p['live_number'] ?? '')) ?: trim((string) ($p['phone_number'] ?? ''));
        $model = trim((string) ($p['device_model'] ?? ''));
        $title = trim((string) ($t['terminal_label'] ?? ''));
        if ($title === '' || preg_match('/^(Terminal|\d{2}-\d{4}-\d+)/', $title) === 1) {
            $title = $type === 'phone' ? ($model !== '' ? $model : Dev::DEFAULT_PHONE_MODEL) : Dev::typeLabel($type);
        }
        $liaison = $t !== [] ? AtakRealismRepository::liaisonIdentity($t) : null;
        $cert = $t !== [] ? Dev::certificateOfTerminal($t) : [];
        $life = Dev::certificateLifetime($cert);
        $fingerprint = Dev::colonHex((string) ($cert['fingerprint_sha256'] ?? ''));
        $serial = Dev::colonHex((string) ($cert['serial_number'] ?? ''), 16);
        $uid = trim((string) ($t['terminal_uid'] ?? ''));
        $compromise = strtolower(trim((string) ($t['compromise_state'] ?? 'none')));
        ?>
        <article class="bo-member-situation__card atk-dev__card">
            <header class="atk-dev__head">
                <div>
                    <p class="bo-member-situation__kicker"><?= $h(Dev::typeLabel($type)) ?></p>
                    <h2><?= $h($title) ?></h2>
                </div>
                <div class="atk-dev__chips">
                    <span class="atk-chip atk-chip--<?= $h($link['key']) ?>"><i></i><?= $h($link['label']) ?><?= $link['ago'] !== '' ? ' · ' . $h($link['ago']) : '' ?></span>
                    <?php if ($t !== []): ?>
                        <span class="atk-chip <?= $status === 'active' ? 'atk-chip--ok' : ($status === 'revoked' || $status === 'lost' ? 'atk-chip--bad' : 'atk-chip--muted') ?>"><?= $h($statusLabel($status)) ?></span>
                    <?php endif; ?>
                    <?php if ($compromise !== '' && $compromise !== 'none'): ?>
                        <span class="atk-chip atk-chip--bad">Compromis</span>
                    <?php endif; ?>
                </div>
            </header>

            <div class="atk-dev__body">
                <div class="atk-dev__visual">
                    <?php if ($type === 'phone'): ?>
                        <?php
                        $phone = [
                            'callsign' => $callsign,
                            'number' => $number,
                            'battery' => $battery,
                            'bars' => $bars,
                            'state' => $deviceState,
                            'link' => $link['key'],
                            'subtitle' => $link['label'],
                        ];
                        require base_path('views/partials/atak_android_phone.php');
                        ?>
                    <?php else: ?>
                        <div class="atk-dev__glyph" aria-hidden="true"><?= $h(strtoupper(substr(Dev::typeLabel($type), 0, 1))) ?></div>
                    <?php endif; ?>
                    <?php if ($battery !== null): ?>
                        <div class="atk-meter" aria-label="Batterie <?= $battery ?> %">
                            <span>Batterie</span>
                            <div class="atk-meter__bar<?= $battery <= 15 ? ' is-low' : ($battery <= 35 ? ' is-mid' : '') ?>"><i style="width: <?= $battery ?>%"></i></div>
                            <strong><?= $battery ?> %</strong>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="atk-dev__sheets">
                    <?php if ($p !== []): ?>
                        <section class="atk-sheet">
                            <h3>Identité de l’appareil</h3>
                            <dl>
                                <div><dt>Numéro</dt><dd class="is-mono"><?= $h($number !== '' ? $number : '—') ?></dd></div>
                                <div><dt>Plan de numérotation</dt><dd><?= $h($formatLabels[(string) ($p['phone_format'] ?? '')] ?? (string) ($p['phone_format'] ?? '—')) ?></dd></div>
                                <div><dt>IMEI</dt><dd class="is-mono"><?= $h((string) ($p['imei'] ?? '') ?: '—') ?></dd></div>
                                <div><dt>Adresse MAC Wi-Fi</dt><dd class="is-mono"><?= $h((string) ($p['mac'] ?? '') ?: '—') ?></dd></div>
                                <div><dt>Modèle</dt><dd><?= $h($model !== '' ? $model : Dev::DEFAULT_PHONE_MODEL) ?></dd></div>
                                <div><dt>État matériel</dt><dd><?= $h($stateLabels[$deviceState] ?? 'Non remonté') ?><?php if (trim((string) ($p['device_reason'] ?? '')) !== ''): ?> · <?= $h($p['device_reason']) ?><?php endif; ?></dd></div>
                                <div><dt>Réception</dt><dd><?= $bars !== null ? $h($bars . ' barre' . ($bars > 1 ? 's' : '') . ' sur 4') : '—' ?></dd></div>
                                <div><dt>Dernier rapport du téléphone</dt><dd><?= $h($fmtDate($p['device_seen_at'] ?? '')) ?></dd></div>
                            </dl>
                        </section>
                    <?php endif; ?>

                    <?php if ($t !== []): ?>
                        <section class="atk-sheet">
                            <h3>Liaison réseau</h3>
                            <dl>
                                <div><dt>Identifiant terminal</dt><dd class="is-mono"><?= $h($uid !== '' ? $uid : '—') ?></dd></div>
                                <div><dt>Indicatif</dt><dd><?= $h($callsign !== '' ? $callsign : '—') ?><?php if (trim((string) ($t['operator_military_id'] ?? '')) !== ''): ?> · <span class="is-mono"><?= $h($t['operator_military_id']) ?></span><?php endif; ?></dd></div>
                                <div><dt>Chaîne de confiance</dt><dd><?= $h($liaison['trust'] ?? '—') ?></dd></div>
                                <div><dt>Signature du serveur</dt><dd class="is-mono"><?= $h($liaison['signature'] ?? '—') ?><?php if (($liaison['host'] ?? '') !== ''): ?> <small><?= $h($liaison['host']) ?></small><?php endif; ?></dd></div>
                                <div><dt>Dernière adresse IP</dt><dd class="is-mono"><?= $h($liaison['ip'] ?? '—') ?></dd></div>
                                <div><dt>Logiciel</dt><dd><?= $h($liaison['versions'] ?? '—') ?></dd></div>
                                <div><dt>Plateforme</dt><dd><?= $h((string) ($t['platform_label'] ?? '') ?: '—') ?></dd></div>
                                <div><dt>Première connexion</dt><dd><?= $h($fmtDate($t['first_seen_at'] ?? '')) ?></dd></div>
                                <div><dt>Associé au compte</dt><dd><?= $h($fmtDate($t['linked_at'] ?? '')) ?></dd></div>
                            </dl>
                        </section>

                        <section class="atk-sheet atk-sheet--cert">
                            <h3>Certificat client <span class="atk-chip atk-chip--<?= $h($life['tone']) ?>"><?= $h($life['label']) ?></span></h3>
                            <?php if ($life['state'] === 'none'): ?>
                                <p class="atk-sheet__note">Aucun certificat n’est encore émis pour cet appareil. Il est délivré à la validation de la liaison par un responsable ATAK.</p>
                            <?php else: ?>
                                <dl>
                                    <div><dt>Sujet</dt><dd class="is-mono"><?= $h(Dev::subjectDn($cert)) ?></dd></div>
                                    <div><dt>Émetteur</dt><dd><?= $h((string) ($cert['authority_label'] ?? '') ?: 'Autorité ATAK locale') ?></dd></div>
                                    <div><dt>Référence</dt><dd class="is-mono"><?= $h((string) ($cert['certificate_ref'] ?? '') ?: '—') ?></dd></div>
                                    <?php if ($serial !== ''): ?><div><dt>Numéro de série</dt><dd class="is-mono"><?= $h($serial) ?></dd></div><?php endif; ?>
                                    <?php if ($fingerprint !== ''): ?><div class="is-wide"><dt>Empreinte SHA-256</dt><dd class="is-mono is-wrap"><?= $h($fingerprint) ?></dd></div><?php endif; ?>
                                </dl>
                                <?php if ($life['from'] !== null || $life['to'] !== null): ?>
                                    <div class="atk-validity atk-validity--<?= $h($life['tone']) ?>">
                                        <div class="atk-validity__bar"><i style="width: <?= (int) ($life['elapsed_pct'] ?? 0) ?>%"></i></div>
                                        <div class="atk-validity__dates">
                                            <span>Valide du <?= $h($life['from'] !== null ? date('d/m/Y', $life['from']) : '—') ?></span>
                                            <span>au <?= $h($life['to'] !== null ? date('d/m/Y', $life['to']) : '—') ?><?php if ($life['days_left'] !== null && $life['days_left'] >= 0 && $life['state'] !== 'revoked'): ?> · <?= (int) $life['days_left'] ?> j restants<?php endif; ?></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </section>
                    <?php else: ?>
                        <section class="atk-sheet">
                            <h3>Liaison réseau</h3>
                            <p class="atk-sheet__note">Le téléphone s’est déjà présenté depuis le jeu, mais il n’est pas encore enregistré comme terminal du réseau. Validez la liaison depuis le téléphone (app Liaison) pour recevoir un certificat.</p>
                        </section>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($id > 0 && $status !== 'revoked'): ?>
                <footer class="atk-dev__foot">
                    <p>Plus en votre possession ? Le retirer l’empêche de se reconnecter jusqu’à une nouvelle association.</p>
                    <form method="post" action="<?= $h(url('back-office/ma-situation/appareils/retirer')) ?>">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="terminal_id" value="<?= $id ?>">
                        <button type="submit" class="ath-btn" onclick="return confirm('Retirer cet appareil de votre compte ?');">Retirer l’appareil</button>
                    </form>
                </footer>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>
