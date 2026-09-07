<?php
declare(strict_types=1);

/**
 * Pied de page portail (hors shells admin / formation).
 */
$footerLoggedIn = (bool) \App\Core\Session::get('user_id');
$year = (int) date('Y');
$n = static function (string $fr): string {
    return htmlspecialchars(function_exists('i18n_phrase') ? i18n_phrase('nav', $fr) : $fr, ENT_QUOTES, 'UTF-8');
};
?>
<footer class="portal-footer" data-portal-footer>
    <div class="portal-footer__inner">
        <div class="portal-footer__brand">
            <p class="portal-footer__mark" aria-hidden="true">ATHENA<span class="portal-footer__mark-dot">.</span></p>
            <p class="portal-footer__lead">
                <?= $n('Centralisez le recrutement, la présence, les formations et la coordination opérationnelle.') ?>
            </p>
            <div class="portal-footer__cta">
                <?php if ($footerLoggedIn): ?>
                    <a href="<?= htmlspecialchars(url('dashboard'), ENT_QUOTES, 'UTF-8') ?>" class="portal-footer__btn portal-footer__btn--solid"><?= $n('Tableau de bord') ?></a>
                    <a href="<?= htmlspecialchars(url('communities'), ENT_QUOTES, 'UTF-8') ?>" class="portal-footer__btn portal-footer__btn--ghost"><?= $n('Communautés') ?></a>
                <?php else: ?>
                    <a href="<?= htmlspecialchars(url('register'), ENT_QUOTES, 'UTF-8') ?>" class="portal-footer__btn portal-footer__btn--solid"><?= $n('Créer un compte') ?></a>
                    <a href="<?= htmlspecialchars(url('communities'), ENT_QUOTES, 'UTF-8') ?>" class="portal-footer__btn portal-footer__btn--ghost"><?= $n('Explorer les communautés') ?></a>
                <?php endif; ?>
            </div>
        </div>

        <nav class="portal-footer__nav" aria-label="<?= $n('Liens du pied de page') ?>">
            <details class="portal-footer__group" open>
                <summary class="portal-footer__summary">
                    <span class="portal-footer__heading"><?= $n('Accès rapide') ?></span>
                    <span class="portal-footer__chevron" aria-hidden="true"></span>
                </summary>
                <ul class="portal-footer__list">
                    <li><a href="<?= htmlspecialchars(url('home'), ENT_QUOTES, 'UTF-8') ?>"><?= $n('Accueil') ?></a></li>
                    <li><a href="<?= htmlspecialchars(url('documentation'), ENT_QUOTES, 'UTF-8') ?>"><?= $n('Wiki') ?></a></li>
                    <li><a href="<?= htmlspecialchars(url('documents'), ENT_QUOTES, 'UTF-8') ?>"><?= $n('Documents') ?></a></li>
                    <li><a href="<?= htmlspecialchars(url('formations'), ENT_QUOTES, 'UTF-8') ?>"><?= $n('Formations') ?></a></li>
                    <li><a href="<?= htmlspecialchars(url('atak'), ENT_QUOTES, 'UTF-8') ?>"><?= $n('ATAK & cartographie') ?></a></li>
                </ul>
            </details>

            <details class="portal-footer__group" open>
                <summary class="portal-footer__summary">
                    <span class="portal-footer__heading"><?= $n('Plateforme') ?></span>
                    <span class="portal-footer__chevron" aria-hidden="true"></span>
                </summary>
                <ul class="portal-footer__list">
                    <li><a href="<?= htmlspecialchars(url('enlistment'), ENT_QUOTES, 'UTF-8') ?>"><?= $n('Enrôlement') ?></a></li>
                    <li><a href="<?= htmlspecialchars(url('overwatch'), ENT_QUOTES, 'UTF-8') ?>"><?= $n('Overwatch') ?></a></li>
                    <li><a href="<?= htmlspecialchars(url('tacmap'), ENT_QUOTES, 'UTF-8') ?>"><?= $n('Tacmap') ?></a></li>
                    <li><a href="<?= htmlspecialchars(url('equipment'), ENT_QUOTES, 'UTF-8') ?>"><?= $n('Fiches matériel') ?></a></li>
                    <li><a href="<?= htmlspecialchars(url('soutenir-atak'), ENT_QUOTES, 'UTF-8') ?>"><?= $n('Soutenir ATAK') ?></a></li>
                    <li><a href="<?= htmlspecialchars(url(ltrim(\App\Services\DemoNda\DemoNdaGateService::FEEDBACK_PATH, '/')), ENT_QUOTES, 'UTF-8') ?>"><?= $n('Donner votre avis') ?></a></li>
                    <?php if ($footerLoggedIn): ?>
                    <li><a href="?avis=1" data-platform-review-open="review"><?= $n('Avis sur Athena') ?></a></li>
                    <li><a href="?traduction=1" data-platform-review-open="translate"><?= $n('Aider à traduire') ?></a></li>
                    <?php endif; ?>
                </ul>
            </details>

            <details class="portal-footer__group" open>
                <summary class="portal-footer__summary">
                    <span class="portal-footer__heading"><?= $n('Légal') ?></span>
                    <span class="portal-footer__chevron" aria-hidden="true"></span>
                </summary>
                <div class="portal-footer__list portal-footer__list--legal">
                    <?php
                    $legal_link_class = 'portal-footer__legal-link';
                    require base_path('views/partials/legal_site_links.php');
                    ?>
                </div>
            </details>
        </nav>
    </div>

    <div class="portal-footer__bar">
        <p class="portal-footer__copy">© <?= $year ?> Athena Comspec. <?= $n('Tous droits réservés.') ?></p>
        <p class="portal-footer__tag"><?= $n('Outils de gestion pour communautés MILSIM.') ?></p>
    </div>
</footer>
<script>
(function () {
  var root = document.querySelector('[data-portal-footer]');
  if (!root) return;

  function isMobile() {
    return window.matchMedia('(max-width: 767px)').matches;
  }

  root.querySelectorAll('.portal-footer__group').forEach(function (group) {
    var summary = group.querySelector('summary');

    group.addEventListener('toggle', function () {
      if (!isMobile() || !group.open) return;
      root.querySelectorAll('.portal-footer__group').forEach(function (other) {
        if (other !== group && other.open) other.open = false;
      });
    });

    if (summary) {
      summary.addEventListener('click', function (e) {
        if (!isMobile()) {
          e.preventDefault();
        }
      });
    }
  });

  function applyViewportDefaults() {
    var mobile = isMobile();
    root.classList.toggle('is-mobile', mobile);
    root.querySelectorAll('.portal-footer__group').forEach(function (group, i) {
      group.open = mobile ? i === 0 : true;
    });
  }

  var mq = window.matchMedia('(max-width: 767px)');
  if (typeof mq.addEventListener === 'function') {
    mq.addEventListener('change', applyViewportDefaults);
  } else if (typeof mq.addListener === 'function') {
    mq.addListener(applyViewportDefaults);
  }
  applyViewportDefaults();
})();
</script>
