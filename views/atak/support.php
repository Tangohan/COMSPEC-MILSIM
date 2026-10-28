<?php
declare(strict_types=1);

/** @var list<array{eur: int, name: string, line: string, url: string}> $tiers */
/** @var int $defaultEur */
/** @var int $minEur */
/** @var int $maxEur */
/** @var string $paypalBase compte PayPal du site (Admin > Paiements) */
/** @var bool $paypalAmountable le lien accepte un montant pré-rempli (paypal.me) */
/** @var bool $alreadyDonor */
/** @var bool $isLoggedIn */
$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$asset = static fn (string $file): string => $e(asset_url('assets/atak-native/' . $file));
$default = $tiers[0] ?? null;
foreach ($tiers as $tier) {
    if ($tier['eur'] === $defaultEur) {
        $default = $tier;
    }
}
$defaultUrl = $default['url'] ?? $paypalBase;
$defaultEurShown = $default['eur'] ?? $defaultEur;
$tierIcons = [
    // Gobelet, serveur, téléphone, fusée : un pictogramme par palier.
    '<path d="M5 8h11v5a5 5 0 0 1-5 5h-1a5 5 0 0 1-5-5V8Z"/><path d="M16 9h1.5a2.5 2.5 0 0 1 0 5H16"/><path d="M8 3c0 1 1 1 1 2s-1 1-1 2M12 3c0 1 1 1 1 2s-1 1-1 2"/>',
    '<rect x="4" y="4" width="16" height="6" rx="1.5"/><rect x="4" y="14" width="16" height="6" rx="1.5"/><path d="M8 7h.01M8 17h.01M12 7h5M12 17h5"/>',
    '<rect x="6" y="2.5" width="12" height="19" rx="2.5"/><path d="M10 18.5h4"/><path d="M9 8l3-2 3 2v4l-3 2-3-2Z"/>',
    '<path d="M12 15c-2-2-3-5-2-9 2.5 1 4.5 3 5 6l-3 3Z"/><path d="M9.5 12.5 6 13l-1 3 4-1M14.5 9.5 15 6l3-1-1 4"/><path d="M8 18c-1 1-1 3-1 3s2 0 3-1"/>',
];
?>
<div class="don" data-don>
    <script>document.documentElement.classList.add('don-js');</script>

    <!-- Ouverture -->
    <header class="don-hero">
        <div class="don-hero__grid" aria-hidden="true"></div>
        <div class="don-hero__glow" aria-hidden="true"></div>
        <div class="don-wrap don-hero__inner">
            <div class="don-hero__copy">
                <p class="don-kicker don-rise"><span class="don-live" aria-hidden="true"></span>Soutenir COMSPEC · 100 % communautaire</p>
                <h1 class="don-title don-rise" style="--d:1">Gardez le réseau <span class="don-title__accent">en ligne.</span></h1>
                <p class="don-lead don-rise" style="--d:2">COMSPEC ATAK et Athena sont gratuits pour tous les joueurs, sans publicité ni offre payante côté joueur. Ce sont vos dons qui paient les serveurs, la liaison en temps réel et les prochaines apps du téléphone.</p>
                <div class="don-hero__cta don-rise" style="--d:3">
                    <a href="#don" class="don-btn don-btn--solid">
                        <svg class="don-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10Z"/></svg>
                        Faire un don
                    </a>
                    <a href="#finance" class="don-btn don-btn--ghost">Ce que ça finance</a>
                </div>
                <?php if (!empty($alreadyDonor)): ?>
                <p class="don-thanks-chip don-rise" style="--d:4">Vous avez déjà le badge Donateur ATAK. Merci pour votre soutien.</p>
                <?php endif; ?>
            </div>
            <figure class="don-hero__phone don-rise" style="--d:2" aria-hidden="true">
                <span class="don-ring don-ring--1"></span>
                <span class="don-ring don-ring--2"></span>
                <span class="don-ring don-ring--3"></span>
                <div class="don-device">
                    <div class="don-device__screen">
                        <img src="<?= $asset('v2/screen-map.webp') ?>" alt="" class="is-1" decoding="async">
                        <img src="<?= $asset('v2/screen-apps.webp') ?>" alt="" class="is-2" decoding="async">
                        <img src="<?= $asset('v2/screen-tools.webp') ?>" alt="" class="is-3" decoding="async">
                        <span class="don-device__sweep"></span>
                    </div>
                    <img class="don-device__frame" src="<?= $asset('v2/frame-s7.webp') ?>" alt="" width="1200" height="836" decoding="async" fetchpriority="high">
                </div>
                <span class="don-chip don-chip--a"><span class="don-chip__dot"></span>Liaison Athena · en ligne</span>
                <span class="don-chip don-chip--b">+30 apps dans la poche</span>
                <span class="don-chip don-chip--c">
                    <svg class="don-ico" viewBox="0 0 24 24"><path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10Z"/></svg>
                    Merci, opérateur
                </span>
            </figure>
        </div>
    </header>

    <!-- Choix du don -->
    <section id="don" class="don-wrap don-section don-section--console" aria-labelledby="don-pick-title">
        <div class="don-console don-reveal" data-don-console data-base="<?= $e($paypalBase) ?>" data-amountable="<?= !empty($paypalAmountable) ? '1' : '0' ?>" data-min="<?= (int) $minEur ?>" data-max="<?= (int) $maxEur ?>">
            <div class="don-console__pick">
                <p class="don-kicker">Votre don</p>
                <h2 id="don-pick-title" class="don-h2">Choisissez votre niveau d’engagement</h2>
                <div class="don-tiers" role="radiogroup" aria-label="Montant du don">
                    <?php foreach ($tiers as $i => $tier): $on = $tier['eur'] === $defaultEurShown; ?>
                    <button type="button" class="don-tier<?= $on ? ' is-on' : '' ?>" role="radio" aria-checked="<?= $on ? 'true' : 'false' ?>" data-don-tier data-eur="<?= (int) $tier['eur'] ?>" data-url="<?= $e($tier['url']) ?>" style="--d:<?= (int) $i ?>">
                        <span class="don-tier__icon"><svg class="don-ico" viewBox="0 0 24 24" aria-hidden="true"><?= $tierIcons[$i % count($tierIcons)] ?></svg></span>
                        <span class="don-tier__amount"><?= (int) $tier['eur'] ?>&nbsp;€</span>
                        <span class="don-tier__name"><?= $e($tier['name']) ?></span>
                        <span class="don-tier__line"><?= $e($tier['line']) ?></span>
                        <?php if ($tier['eur'] === $defaultEur): ?><span class="don-tier__badge">Recommandé</span><?php endif; ?>
                    </button>
                    <?php endforeach; ?>
                </div>
                <?php if (!empty($paypalAmountable)): ?>
                <label class="don-custom" data-don-custom-wrap>
                    <span class="don-custom__label">Autre montant</span>
                    <span class="don-custom__field">
                        <input type="number" inputmode="numeric" min="<?= (int) $minEur ?>" max="<?= (int) $maxEur ?>" step="1" placeholder="<?= (int) $minEur ?> – <?= (int) $maxEur ?>" aria-label="Montant libre en euros" data-don-custom>
                        <span aria-hidden="true">€</span>
                    </span>
                </label>
                <?php endif; ?>
            </div>

            <aside class="don-console__pay" aria-label="Paiement">
                <p class="don-pay__label">Vous donnez</p>
                <p class="don-pay__amount"><span data-don-amount><?= (int) $defaultEurShown ?></span><span class="don-pay__cur">€</span></p>
                <p class="don-pay__once">une fois, sans abonnement</p>
                <a href="<?= $e($defaultUrl) ?>" class="don-paypal" target="_blank" rel="noopener noreferrer" data-don-go>
                    <span class="don-paypal__shine" aria-hidden="true"></span>
                    <svg class="don-paypal__logo" viewBox="0 0 24 24" aria-hidden="true"><path d="M7.5 21H4.3a.5.5 0 0 1-.5-.6L6.6 3.4A.6.6 0 0 1 7.2 3h6c3.7 0 5.6 1.9 5 5-.7 3.6-3.1 5.2-6.5 5.2H9.6l-1.1 7a.7.7 0 0 1-.7.6"/><path d="M19.5 8.6c.9.8 1.2 2 .9 3.6-.6 3.3-2.8 4.8-5.9 4.8h-1.4l-.8 4.4"/></svg>
                    <span>Donner avec PayPal</span>
                    <span aria-hidden="true">↗</span>
                </a>
                <ul class="don-pay__facts">
                    <li><svg class="don-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4 10-10"/></svg>Carte bancaire ou compte PayPal</li>
                    <li><svg class="don-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4 10-10"/></svg>Aucun compte Athena nécessaire</li>
                    <li><svg class="don-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4 10-10"/></svg>Paiement chez PayPal : Athena ne voit pas vos coordonnées bancaires</li>
                </ul>
                <div class="don-qr">
                    <img src="<?= $e(url('soutenir-atak/paypal-qr.png')) ?>" width="96" height="96" alt="QR code du compte PayPal de COMSPEC" loading="lazy">
                    <p><strong>Sur téléphone ?</strong> Scannez le code pour ouvrir PayPal directement.</p>
                </div>
            </aside>
        </div>
    </section>

    <!-- Ce que ça finance -->
    <section id="finance" class="don-wrap don-section" aria-labelledby="don-fund-title">
        <div class="don-head don-reveal">
            <p class="don-kicker">Où va votre don</p>
            <h2 id="don-fund-title" class="don-h2">Chaque euro reste sur le terrain</h2>
            <p class="don-sub">Pas d’actionnaire, pas de publicité : le projet est porté par des joueurs, pour les unités MILSIM.</p>
        </div>
        <div class="don-cards">
            <article class="don-card don-reveal">
                <span class="don-card__icon"><svg class="don-ico" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="6" rx="1.5"/><rect x="4" y="14" width="16" height="6" rx="1.5"/><path d="M8 7h.01M8 17h.01"/></svg></span>
                <h3 class="don-card__title">Serveurs et liaison en direct</h3>
                <p class="don-card__body">L’hébergement d’Athena, la liaison entre le téléphone en jeu et le poste de commandement, les photos et les comptes rendus qui remontent pendant vos opérations.</p>
                <span class="don-card__bar" aria-hidden="true"><span></span></span>
            </article>
            <article class="don-card don-reveal" style="--d:1">
                <span class="don-card__icon"><svg class="don-ico" viewBox="0 0 24 24" aria-hidden="true"><rect x="6" y="2.5" width="12" height="19" rx="2.5"/><path d="M10 18.5h4"/></svg></span>
                <h3 class="don-card__title">Le téléphone COMSPEC ATAK</h3>
                <p class="don-card__body">De nouvelles apps, de nouveaux terminaux et les corrections du mod sur le Steam Workshop. Carte BFT, messagerie, appui feu, médical, drones : tout reste gratuit.</p>
                <span class="don-card__bar" aria-hidden="true"><span></span></span>
            </article>
            <article class="don-card don-reveal" style="--d:2">
                <span class="don-card__icon"><svg class="don-ico" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6M15 14.5c3 0 6 1.8 6 5.5"/></svg></span>
                <h3 class="don-card__title">Les outils des communautés</h3>
                <p class="don-card__body">Enrôlement, effectifs, ORBAT, formations et briefings pour les unités qui s’organisent sur Athena, avec un socle gratuit pour tout le monde.</p>
                <span class="don-card__bar" aria-hidden="true"><span></span></span>
            </article>
        </div>

        <dl class="don-stats don-reveal">
            <div><dt><span data-don-count="30">30</span>+</dt><dd>apps dans le téléphone</dd></div>
            <div><dt><span data-don-count="8">8</span></dt><dd>terminaux au choix</dd></div>
            <div><dt>24/7</dt><dd>liaison avec Athena</dd></div>
            <div><dt>0&nbsp;€</dt><dd>pour les joueurs, toujours</dd></div>
        </dl>
    </section>

    <!-- Badge -->
    <section class="don-wrap don-section" aria-labelledby="don-badge-title">
        <div class="don-badge don-reveal">
            <div class="don-badge__medal" aria-hidden="true">
                <span class="don-badge__halo"></span>
                <svg viewBox="0 0 64 64"><path d="M32 6 52 14v16c0 13-8.6 22.8-20 28C20.6 52.8 12 43 12 30V14Z"/><path d="M32 42s-10-6.2-10-14a5.6 5.6 0 0 1 10-3.6A5.6 5.6 0 0 1 42 28c0 7.8-10 14-10 14Z"/></svg>
            </div>
            <div>
                <p class="don-kicker">Merci en retour</p>
                <h2 id="don-badge-title" class="don-h2">Le badge Donateur ATAK</h2>
                <p class="don-sub">Dès que votre don est validé, le badge « Donateur Athena » apparaît sur votre fiche dans chacune de vos communautés, et votre nom porte une marque de couleur partout sur le site : <b class="don-tier-word don-tier-word--soutien">Soutien</b> dès le premier euro, <b class="don-tier-word don-tier-word--allie">Allié</b> à partir de 20 € au total, <b class="don-tier-word don-tier-word--pilier">Pilier</b> à partir de 100 €. Un don par PayPal ? Envoyez-nous la référence de la transaction par la page Contact pour qu’on le rattache à votre compte.</p>
                <a href="<?= $e(url('contact')) ?>" class="don-link">Rattacher un don PayPal →</a>
            </div>
        </div>
    </section>

    <!-- Mur des donateurs -->
    <?php
    $board = is_array($donorBoard ?? null) ? $donorBoard : [];
    $tierNames = ['pilier' => 'Piliers', 'allie' => 'Alliés', 'soutien' => 'Soutiens'];
    $byTier = [];
    foreach ($board as $row) { $byTier[$row['tier']][] = $row; }
    ?>
    <section id="donateurs" class="don-wrap don-section" aria-labelledby="don-wall-title">
        <div class="don-head don-reveal">
            <p class="don-kicker">Merci</p>
            <h2 id="don-wall-title" class="don-h2">Ils font tourner Athena</h2>
            <p class="don-sub"><?= count($board) > 0 ? count($board) . ' donateur' . (count($board) > 1 ? 's' : '') . ' et ' . (int) ($supporterCount ?? 0) . ' communauté' . ((int) ($supporterCount ?? 0) > 1 ? 's' : '') . ' soutiennent le projet.' : 'Le mur est encore vide : votre nom peut être le premier.' ?> Les montants ne sont jamais affichés.</p>
        </div>
        <?php if ($board !== []): ?>
        <div class="don-wall don-reveal">
            <?php foreach ($tierNames as $tier => $label): if (empty($byTier[$tier])) { continue; } ?>
            <div class="don-wall__tier don-wall__tier--<?= $e($tier) ?>">
                <h3 class="don-wall__title"><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M12 20.5s-7.5-4.6-7.5-10.2A4.3 4.3 0 0 1 12 7.6a4.3 4.3 0 0 1 7.5 2.7c0 5.6-7.5 10.2-7.5 10.2Z" fill="currentColor"/></svg><?= $e($label) ?> <span><?= count($byTier[$tier]) ?></span></h3>
                <ul class="don-wall__list">
                    <?php foreach ($byTier[$tier] as $row): $since = !empty($row['since']) ? strtotime((string) $row['since'] . ' UTC') : false; ?>
                    <li class="don-wall__item">
                        <span class="don-wall__avatar" aria-hidden="true"><?= $e(mb_strtoupper(mb_substr((string) $row['name'], 0, 1))) ?></span>
                        <span class="don-wall__who"><b><?= $e($row['name']) ?></b><small><?= $row['community'] !== '' ? $e($row['community']) : 'Donateur indépendant' ?><?= $since ? ' · depuis ' . $e(date('m/Y', $since)) : '' ?></small></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($myDonorTier)): ?>
        <form method="post" action="<?= $e(url('soutenir-atak/mur')) ?>" class="don-wall__me don-reveal">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="visible" value="<?= !empty($myDonorHidden) ? '1' : '0' ?>">
            <p>Merci ! Vous êtes <b class="don-tier-word don-tier-word--<?= $e($myDonorTier) ?>"><?= $e(['soutien' => 'Soutien', 'allie' => 'Allié', 'pilier' => 'Pilier'][$myDonorTier] ?? '') ?></b>. <?= !empty($myDonorHidden) ? 'Votre nom est masqué sur ce mur.' : 'Votre nom public apparaît sur ce mur.' ?></p>
            <button type="submit" class="don-btn don-btn--ghost"><?= !empty($myDonorHidden) ? 'Apparaître sur le mur' : 'Masquer mon nom' ?></button>
        </form>
        <?php endif; ?>
    </section>

    <!-- Questions -->
    <section class="don-wrap don-section" aria-labelledby="don-faq-title">
        <div class="don-head don-reveal">
            <p class="don-kicker">Questions</p>
            <h2 id="don-faq-title" class="don-h2">Avant de donner</h2>
        </div>
        <div class="don-faq don-reveal">
            <details>
                <summary>Est-ce un abonnement ?</summary>
                <p>Non. Un don est versé une seule fois, au montant que vous choisissez. Rien n’est prélevé ensuite.</p>
            </details>
            <details>
                <summary>Dois-je avoir un compte Athena ?</summary>
                <p>Non. Le don se fait directement chez PayPal, avec une carte bancaire ou un compte PayPal.</p>
            </details>
            <details>
                <summary>Mes coordonnées bancaires sont-elles en sécurité ?</summary>
                <p>Le paiement est traité par PayPal. Athena ne reçoit ni vos coordonnées bancaires ni votre adresse.</p>
            </details>
            <details>
                <summary>Je veux payer la formule de ma communauté.</summary>
                <p>Ce n’est pas un don : la formule se règle depuis l’espace de gestion de votre communauté, qui vous donne une référence de paiement. Le même compte PayPal reçoit les deux.</p>
            </details>
        </div>
    </section>

    <!-- Appel final -->
    <section class="don-wrap don-section don-section--last">
        <div class="don-final don-reveal">
            <div class="don-final__radar" aria-hidden="true"><span></span></div>
            <p class="don-kicker">Dernier mot</p>
            <h2 class="don-final__title">Chaque don garde une escouade connectée.</h2>
            <div class="don-hero__cta">
                <a href="#don" class="don-btn don-btn--solid">Choisir un montant</a>
                <a href="<?= $e(url('atak-natif')) ?>" class="don-btn don-btn--ghost">Découvrir COMSPEC ATAK</a>
            </div>
        </div>
    </section>
</div>
<script>
(function () {
  var root = document.querySelector('[data-don]');
  if (!root) { return; }
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Apparition au défilement.
  var items = root.querySelectorAll('.don-reveal');
  if ('IntersectionObserver' in window && !reduce) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); }
      });
    }, { threshold: 0.18 });
    items.forEach(function (el) { io.observe(el); });
  } else {
    items.forEach(function (el) { el.classList.add('is-in'); });
  }

  // Compteurs.
  root.querySelectorAll('[data-don-count]').forEach(function (el) {
    var target = parseInt(el.getAttribute('data-don-count'), 10) || 0;
    if (reduce || !('IntersectionObserver' in window)) { return; }
    el.textContent = '0';
    var seen = new IntersectionObserver(function (entries) {
      if (!entries[0].isIntersecting) { return; }
      seen.disconnect();
      var t0 = null;
      function step(ts) {
        if (t0 === null) { t0 = ts; }
        var k = Math.min(1, (ts - t0) / 1200);
        el.textContent = String(Math.round(target * (1 - Math.pow(1 - k, 3))));
        if (k < 1) { requestAnimationFrame(step); }
      }
      requestAnimationFrame(step);
    }, { threshold: 0.6 });
    seen.observe(el);
  });

  // Choix du montant : met à jour le montant affiché et le lien PayPal.
  var box = root.querySelector('[data-don-console]');
  if (!box) { return; }
  var base = (box.getAttribute('data-base') || '').replace(/\/+$/, '');
  var amountable = box.getAttribute('data-amountable') === '1';
  var min = parseInt(box.getAttribute('data-min'), 10) || 1;
  var max = parseInt(box.getAttribute('data-max'), 10) || 500;
  var tiers = box.querySelectorAll('[data-don-tier]');
  var go = box.querySelector('[data-don-go]');
  var shown = box.querySelector('[data-don-amount]');
  var custom = box.querySelector('[data-don-custom]');
  var customWrap = box.querySelector('[data-don-custom-wrap]');

  function show(eur, url) {
    if (shown && shown.textContent !== String(eur)) {
      shown.textContent = String(eur);
      shown.classList.remove('is-pop');
      void shown.offsetWidth;
      shown.classList.add('is-pop');
    }
    if (go && url) { go.setAttribute('href', url); }
  }
  function select(btn) {
    tiers.forEach(function (b) {
      var on = b === btn;
      b.classList.toggle('is-on', on);
      b.setAttribute('aria-checked', on ? 'true' : 'false');
    });
    if (customWrap) { customWrap.classList.toggle('is-on', !btn); }
  }
  tiers.forEach(function (btn) {
    btn.addEventListener('click', function () {
      select(btn);
      if (custom) { custom.value = ''; }
      show(btn.getAttribute('data-eur'), btn.getAttribute('data-url'));
    });
  });
  if (custom && amountable) {
    custom.addEventListener('input', function () {
      var v = Math.round(parseFloat(String(custom.value).replace(',', '.')));
      if (!isFinite(v) || v < min) { return; }
      if (v > max) { v = max; }
      select(null);
      show(v, base + '/' + v + '.00EUR');
    });
  }
})();
</script>
