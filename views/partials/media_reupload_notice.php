<?php
declare(strict_types=1);

/**
 * Bandeau tableau de bord : demander de recharger les images perdues au déploiement.
 * Masquable (localStorage). Complète les annonces injectées via OpsDashboardNotices.
 */
if (!empty($skipMediaReuploadNotice)) {
    return;
}
$userId = (int) (\App\Core\Session::get('user_id') ?? 0);
if ($userId <= 0) {
    return;
}

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$accountUrl = url('account');
$canManageOrg = function_exists('can') && (can('admin.organization') || can('admin.access'));
$orgUrl = $canManageOrg ? url('back-office/organisation/parametres') : null;
$noticeId = 'media-reupload-notice';
$storageKey = 'athena_ops_media_reupload_v1';
?>
<section
    id="<?= $h($noticeId) ?>"
    class="media-reupload-notice"
    role="status"
    aria-live="polite"
    data-storage-key="<?= $h($storageKey) ?>"
>
    <div class="media-reupload-notice__inner">
        <div class="media-reupload-notice__mark" aria-hidden="true">IMG</div>
        <div class="media-reupload-notice__body">
            <p class="media-reupload-notice__kicker">Information</p>
            <h2 class="media-reupload-notice__title">Merci de recharger vos images</h2>
            <p class="media-reupload-notice__text">
                Lors d’une mise à jour récente, certains visuels déposés sur le site
                (photo de profil, bannière, logos de communauté, illustrations) ont pu disparaître.
                Déposez-les à nouveau pour les retrouver partout sur le portail.
            </p>
            <div class="media-reupload-notice__actions">
                <a class="media-reupload-notice__btn" href="<?= $h($accountUrl) ?>">Mettre à jour mon profil</a>
                <?php if ($orgUrl !== null): ?>
                    <a class="media-reupload-notice__btn media-reupload-notice__btn--ghost" href="<?= $h($orgUrl) ?>">
                        Logos de la communauté
                    </a>
                <?php endif; ?>
                <button type="button" class="media-reupload-notice__dismiss" data-dismiss-notice>
                    J’ai compris
                </button>
            </div>
        </div>
    </div>
</section>
<style>
.media-reupload-notice {
    border-bottom: 1px solid rgba(180, 83, 9, 0.28);
    background:
        linear-gradient(135deg, rgba(251, 191, 36, 0.16), transparent 42%),
        #fffbeb;
    color: #78350f;
}
.media-reupload-notice__inner {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    gap: 0.9rem 1.1rem;
    align-items: start;
    max-width: 72rem;
    margin: 0 auto;
    padding: 1rem 1.5rem 1.15rem;
}
.media-reupload-notice__mark {
    display: grid;
    place-items: center;
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 0.65rem;
    border: 1px solid rgba(180, 83, 9, 0.35);
    background: rgba(255, 255, 255, 0.7);
    color: #b45309;
    font: 800 0.68rem/1 Inter, system-ui, sans-serif;
    letter-spacing: 0.08em;
}
.media-reupload-notice__kicker {
    margin: 0 0 0.2rem;
    font: 800 0.62rem/1 Inter, system-ui, sans-serif;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: #b45309;
}
.media-reupload-notice__title {
    margin: 0 0 0.35rem;
    font: 800 1.05rem/1.25 Inter, system-ui, sans-serif;
    color: #78350f;
}
.media-reupload-notice__text {
    margin: 0;
    max-width: 46rem;
    font: 500 0.88rem/1.5 Inter, system-ui, sans-serif;
    color: rgba(120, 53, 15, 0.92);
}
.media-reupload-notice__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.85rem;
}
.media-reupload-notice__btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 2.15rem;
    padding: 0.45rem 0.9rem;
    border-radius: 0.55rem;
    background: #b45309;
    color: #fffbeb;
    font: 800 0.68rem/1 Inter, system-ui, sans-serif;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    text-decoration: none;
}
.media-reupload-notice__btn:hover { background: #92400e; }
.media-reupload-notice__btn--ghost {
    background: transparent;
    border: 1px solid rgba(180, 83, 9, 0.45);
    color: #92400e;
}
.media-reupload-notice__btn--ghost:hover { background: rgba(180, 83, 9, 0.08); }
.media-reupload-notice__dismiss {
    min-height: 2.15rem;
    padding: 0.45rem 0.75rem;
    border: 0;
    background: transparent;
    color: rgba(120, 53, 15, 0.75);
    font: 700 0.72rem/1 Inter, system-ui, sans-serif;
    text-decoration: underline;
    text-underline-offset: 0.18em;
    cursor: pointer;
}
.media-reupload-notice__dismiss:hover { color: #78350f; }
@media (max-width: 640px) {
    .media-reupload-notice__inner {
        grid-template-columns: 1fr;
        padding: 1rem 1.15rem 1.1rem;
    }
}
</style>
<script>
(function () {
    var root = document.getElementById(<?= json_encode($noticeId) ?>);
    if (!root) return;
    var key = root.getAttribute('data-storage-key') || '';
    try {
        if (key && localStorage.getItem(key) === '1') {
            root.remove();
            return;
        }
    } catch (e) {}
    root.hidden = false;
    var btn = root.querySelector('[data-dismiss-notice]');
    if (!btn) return;
    btn.addEventListener('click', function () {
        try { if (key) localStorage.setItem(key, '1'); } catch (e) {}
        root.remove();
    });
})();
</script>
