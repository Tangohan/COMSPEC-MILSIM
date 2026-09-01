<?php
declare(strict_types=1);
/**
 * Aperçu « fenêtre détachée dans le téléphone » (QR hub / modal Écran 2).
 * Le QR à scanner est hors du visuel téléphone : grand, fond blanc, lisible à l’appareil photo.
 *
 * @var string $atakPhoneBezelUrl
 * @var string $qrImgId
 * @var string $qrImgAlt
 * @var string $phoneModifier  '' | 'atak-qr-phone--modal'
 * @var string $defaultModule c2|chat|sitac|orders|explosives
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$qrImgId = (string) ($qrImgId ?? 'atak-qr-image');
$qrImgAlt = (string) ($qrImgAlt ?? 'QR code d\'accès ATAK mobile');
$phoneModifier = trim((string) ($phoneModifier ?? ''));
$defaultModule = (string) ($defaultModule ?? 'c2');
$modLabels = [
    'c2' => 'C2 OVERVIEW',
    'chat' => 'TCHAT C2',
    'sitac' => 'SITAC',
    'orders' => 'ORDRES',
    'explosives' => 'EXPLOSIFS',
];
$defaultTitle = $modLabels[$defaultModule] ?? 'C2 OVERVIEW';
?>
<div class="atak-qr-scan-pack">
<figure class="atak-qr-scan-pack__code">
  <img id="<?= $h($qrImgId) ?>" alt="<?= $h($qrImgAlt) ?>" width="280" height="280" />
</figure>
<div class="atak-qr-phone<?= $phoneModifier !== '' ? ' ' . $h($phoneModifier) : '' ?>" data-atak-qr-phone data-module="<?= $h($defaultModule) ?>">
  <div class="atak-qr-phone__screen">
    <div class="atak-qr-phone__mobile">
      <header class="atak-qr-phone__topbar">
        <div class="atak-qr-phone__brand">
          <span class="atak-qr-phone__kicker">COMSPEC</span>
          <strong class="atak-qr-phone__title" data-atak-qr-phone-mode><?= $h($defaultTitle) ?></strong>
        </div>
        <span class="atak-qr-phone__live"><i></i>LIVE</span>
      </header>

      <div class="atak-qr-phone__stage">
        <div class="atak-qr-phone__skin<?= $defaultModule === 'c2' ? ' is-active' : '' ?>" data-skin="c2" aria-hidden="<?= $defaultModule === 'c2' ? 'false' : 'true' ?>">
          <div class="atak-qr-phone__metrics">
            <span><b>12</b><small>Unités</small></span>
            <span><b>04</b><small>Msgs</small></span>
            <span class="warn"><b>02</b><small>Alertes</small></span>
          </div>
          <div class="atak-qr-phone__mini-map" aria-hidden="true">
            <i class="u u1"></i><i class="u u2"></i><i class="u u3"></i>
          </div>
        </div>
        <div class="atak-qr-phone__skin<?= $defaultModule === 'sitac' ? ' is-active' : '' ?>" data-skin="sitac" aria-hidden="<?= $defaultModule === 'sitac' ? 'false' : 'true' ?>">
          <div class="atak-qr-phone__full-map" aria-hidden="true">
            <i class="u u1"></i><i class="u u2"></i><i class="u hostile"></i>
            <span class="atak-qr-phone__grid">GRID · BFT</span>
          </div>
        </div>
        <div class="atak-qr-phone__skin<?= $defaultModule === 'chat' ? ' is-active' : '' ?>" data-skin="chat" aria-hidden="<?= $defaultModule === 'chat' ? 'false' : 'true' ?>">
          <div class="atak-qr-phone__bubbles">
            <div class="b"><em>OW</em><p>Confirmez CP RED.</p></div>
            <div class="b me"><em>A1</em><p>Reçu. Progression nord.</p></div>
          </div>
        </div>
        <div class="atak-qr-phone__skin<?= $defaultModule === 'orders' ? ' is-active' : '' ?>" data-skin="orders" aria-hidden="<?= $defaultModule === 'orders' ? 'false' : 'true' ?>">
          <div class="atak-qr-phone__order-card">
            <span>FRAGO</span>
            <strong>Tenir axe nord</strong>
            <small>Priorité IMPORTANT</small>
          </div>
        </div>
        <div class="atak-qr-phone__skin<?= $defaultModule === 'explosives' ? ' is-active' : '' ?>" data-skin="explosives" aria-hidden="<?= $defaultModule === 'explosives' ? 'false' : 'true' ?>">
          <div class="atak-qr-phone__charge">
            <span>CHARGE</span>
            <strong>00:04:12</strong>
            <small>Minuterie active</small>
          </div>
        </div>
      </div>

      <nav class="atak-qr-phone__nav" aria-hidden="true">
        <span class="<?= $defaultModule === 'c2' ? 'is-on' : '' ?>" data-nav-mod="c2">C2</span>
        <span class="<?= $defaultModule === 'sitac' ? 'is-on' : '' ?>" data-nav-mod="sitac">SITAC</span>
        <span class="<?= $defaultModule === 'chat' ? 'is-on' : '' ?>" data-nav-mod="chat">TCHAT</span>
        <span class="<?= $defaultModule === 'orders' ? 'is-on' : '' ?>" data-nav-mod="orders">ORDRES</span>
      </nav>
      <span class="sr-only" data-atak-qr-phone-label><?= $h($defaultTitle) ?></span>
    </div>
  </div>
  <img
    class="atak-qr-phone__bezel"
    src="<?= $h($atakPhoneBezelUrl) ?>"
    alt=""
    width="512"
    height="512"
    decoding="async"
    draggable="false"
  />
</div>
</div>
