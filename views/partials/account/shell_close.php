<?php
declare(strict_types=1);
?>
        </div>
    </div>
</div>
<script>
(function () {
    try {
        var hash = (window.location.hash || '').replace(/^#/, '');
        if (hash !== 'notifications-email') {
            return;
        }
        var links = document.querySelectorAll('.account-hub__nav-link');
        links.forEach(function (a) {
            var href = a.getAttribute('href') || '';
            var isNotif = href.indexOf('#notifications-email') !== -1;
            a.classList.toggle('is-active', isNotif);
            if (isNotif) {
                a.setAttribute('aria-current', 'page');
            } else {
                a.removeAttribute('aria-current');
            }
        });
    } catch (e) {}
})();
</script>
