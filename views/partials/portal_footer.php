<?php
declare(strict_types=1);

/**
 * Pied de page portail (hors shells admin / formation).
 */
$footerLoggedIn = (bool) \App\Core\Session::get('user_id');
$year = (int) date('Y');
?>
<footer class="portal-footer" data-portal-footer>
    <div class="portal-footer__inner">
        <div class="portal-footer__brand">
            <p class="portal-footer__mark" aria-hidden="true">ATHENA<span class="portal-footer__mark-dot">.</span></p>
            <p class="portal-footer__lead">
                Centralisez le recrutement, la présence, les formations et la coordination opérationnelle.
            </p>
            <div class="portal-footer__cta">
                <?php if ($footerLoggedIn): ?>
                    <a href="<?= htmlspecialchars(url('dashboard'), ENT_QUOTES, 'UTF-8') ?>" class="portal-footer__btn portal-footer__btn--solid">Tableau de bord</a>
                    <a href="<?= htmlspecialchars(url('communities'), ENT_QUOTES, 'UTF-8') ?>" class="portal-footer__btn portal-footer__btn--ghost">Communautés</a>
                <?php else: ?>
                    <a href="<?= htmlspecialchars(url('register'), ENT_QUOTES, 'UTF-8') ?>" class="portal-footer__btn portal-footer__btn--solid">Créer un compte</a>
                    <a href="<?= htmlspecialchars(url('communities'), ENT_QUOTES, 'UTF-8') ?>" class="portal-footer__btn portal-footer__btn--ghost">Explorer les communautés</a>
                <?php endif; ?>
            </div>
        </div>

        <nav class="portal-footer__nav" aria-label="Liens du pied de page">
            <details class="portal-footer__group" open>
                <summary class="portal-footer__summary">
                    <span class="portal-footer__heading">Accès rapide</span>
                    <span class="portal-footer__chevron" aria-hidden="true"></span>
                </summary>
                <ul class="portal-footer__list">
                    <li><a href="<?= htmlspecialchars(url('home'), ENT_QUOTES, 'UTF-8') ?>">Accueil</a></li>
                    <li><a href="<?= htmlspecialchars(url('documents'), ENT_QUOTES, 'UTF-8') ?>">Documents</a></li>
                    <li><a href="<?= htmlspecialchars(url('formations'), ENT_QUOTES, 'UTF-8') ?>">Formations</a></li>
                    <li><a href="<?= htmlspecialchars(url('atak'), ENT_QUOTES, 'UTF-8') ?>">ATAK &amp; cartographie</a></li>
                </ul>
            </details>

            <details class="portal-footer__group" open>
                <summary class="portal-footer__summary">
                    <span class="portal-footer__heading">Plateforme</span>
                    <span class="portal-footer__chevron" aria-hidden="true"></span>
                </summary>
                <ul class="portal-footer__list">
                    <li><a href="<?= htmlspecialchars(url('enlistment'), ENT_QUOTES, 'UTF-8') ?>">Enrôlement</a></li>
                    <li><a href="<?= htmlspecialchars(url('overwatch'), ENT_QUOTES, 'UTF-8') ?>">Overwatch</a></li>
                    <li><a href="<?= htmlspecialchars(url('tacmap'), ENT_QUOTES, 'UTF-8') ?>">Tacmap</a></li>
                    <li><a href="<?= htmlspecialchars(url('equipment'), ENT_QUOTES, 'UTF-8') ?>">Fiches matériel</a></li>
                    <li><a href="<?= htmlspecialchars(url('soutenir-atak'), ENT_QUOTES, 'UTF-8') ?>">Soutenir ATAK</a></li>
                </ul>
            </details>

            <details class="portal-footer__group" open>
                <summary class="portal-footer__summary">
                    <span class="portal-footer__heading">Légal</span>
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
        <p class="portal-footer__copy">© <?= $year ?> Athena Compsec. Tous droits réservés.</p>
        <p class="portal-footer__tag">Outils de gestion pour communautés MILSIM.</p>
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
