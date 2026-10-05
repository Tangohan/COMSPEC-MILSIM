<?php
declare(strict_types=1);

/*
 * Téléphone Android COMSPEC (même visuel que la coque en jeu) avec un écran vivant :
 * barre d'état (heure, réseau, signal, batterie) et écran d'accueil ATAK de l'opérateur.
 *
 * Variables :
 *   $phone : [
 *     'callsign' => string, 'number' => string, 'battery' => ?int (0-100), 'bars' => ?int (0-4),
 *     'state' => string (OK | CRACKED | OFF | BROKEN | ABSENT | ''), 'link' => string (online | idle | offline | never),
 *     'subtitle' => string, 'size' => 'lg' | 'sm'
 *   ]
 */
$phone = is_array($phone ?? null) ? $phone : [];
$pe = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$pCallsign = trim((string) ($phone['callsign'] ?? ''));
$pNumber = trim((string) ($phone['number'] ?? ''));
$pBattery = $phone['battery'] ?? null;
$pBattery = $pBattery === null || $pBattery === '' ? null : max(0, min(100, (int) $pBattery));
$pBars = $phone['bars'] ?? null;
$pBars = $pBars === null || $pBars === '' ? null : max(0, min(4, (int) $pBars));
$pState = strtoupper(trim((string) ($phone['state'] ?? '')));
$pLink = (string) ($phone['link'] ?? 'never');
$pSubtitle = trim((string) ($phone['subtitle'] ?? ''));
$pSize = ($phone['size'] ?? 'lg') === 'sm' ? 'sm' : 'lg';
$pDark = in_array($pState, ['OFF', 'BROKEN'], true) || ($pBattery !== null && $pBattery === 0);
if ($pBars === null) {
    $pBars = match ($pLink) {
        'online' => 4,
        'idle' => 2,
        default => 0,
    };
}
$pBatteryTone = $pBattery === null ? '' : ($pBattery <= 15 ? ' is-low' : ($pBattery <= 35 ? ' is-mid' : ''));
?>
<figure class="atk-phone atk-phone--<?= $pe($pSize) ?><?= $pDark ? ' is-off' : '' ?><?= $pState === 'CRACKED' ? ' is-cracked' : '' ?>" aria-label="Téléphone ATAK<?= $pCallsign !== '' ? ' de ' . $pe($pCallsign) : '' ?>">
    <div class="atk-phone__screen">
        <?php if ($pDark): ?>
            <div class="atk-phone__off">
                <span><?= $pState === 'BROKEN' ? 'Hors d’usage' : ($pBattery === 0 ? 'Batterie vide' : 'Éteint') ?></span>
            </div>
        <?php else: ?>
            <div class="atk-phone__status">
                <span class="atk-phone__time" data-atk-phone-clock><?= $pe(date('H:i')) ?></span>
                <span class="atk-phone__carrier">COMSPEC<?= $pLink === 'online' ? ' · 4G' : '' ?></span>
                <span class="atk-phone__bars" aria-label="Signal <?= (int) $pBars ?> sur 4">
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                        <i class="<?= $i <= $pBars ? 'is-on' : '' ?>" style="height: <?= 25 * $i ?>%"></i>
                    <?php endfor; ?>
                </span>
                <?php if ($pBattery !== null): ?>
                    <span class="atk-phone__battery<?= $pBatteryTone ?>" aria-label="Batterie <?= $pBattery ?> %">
                        <i style="width: <?= $pBattery ?>%"></i>
                    </span>
                    <span class="atk-phone__pct"><?= $pBattery ?>%</span>
                <?php endif; ?>
            </div>
            <div class="atk-phone__home">
                <span class="atk-phone__app">ATAK · COMSPEC</span>
                <strong class="atk-phone__cs"><?= $pe($pCallsign !== '' ? $pCallsign : 'Opérateur') ?></strong>
                <?php if ($pNumber !== ''): ?>
                    <span class="atk-phone__num"><?= $pe($pNumber) ?></span>
                <?php endif; ?>
                <?php if ($pSubtitle !== ''): ?>
                    <span class="atk-phone__sub atk-phone__sub--<?= $pe($pLink) ?>"><?= $pe($pSubtitle) ?></span>
                <?php endif; ?>
            </div>
            <?php if ($pState === 'CRACKED'): ?>
                <svg class="atk-phone__crack" viewBox="0 0 100 60" preserveAspectRatio="none" aria-hidden="true">
                    <path d="M70 0 L64 14 L72 22 L60 31 L66 42 L58 60 M64 14 L52 18 L46 12 M60 31 L48 34 L40 44 M72 22 L86 26 L100 22 M66 42 L80 48 L92 60" fill="none" stroke="rgba(255,255,255,.75)" stroke-width=".5"/>
                </svg>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <picture>
        <source srcset="<?= $pe(asset_url('assets/img/atak/android-phone.webp')) ?>" type="image/webp">
        <img class="atk-phone__shell" src="<?= $pe(asset_url('assets/img/atak/android-phone.png')) ?>" alt="" width="1000" height="697" loading="lazy" decoding="async">
    </picture>
</figure>
