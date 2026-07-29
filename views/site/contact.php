<?php
declare(strict_types=1);
$contactInboxConfigured = !empty($contactInboxConfigured);
$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');
?>
<article class="site-page">
    <header class="site-page__hero">
        <p class="hi-kicker text-emerald-400/90"><?= htmlspecialchars(__('site.contact_kicker'), ENT_QUOTES, 'UTF-8') ?></p>
        <h1 class="hi-display hi-display-md mt-4 text-white"><?= htmlspecialchars(__('site.contact_title'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="hi-body mt-5 max-w-2xl text-white/65"><?= htmlspecialchars(__('site.contact_lead'), ENT_QUOTES, 'UTF-8') ?></p>
    </header>

    <?php if ($flashOk): ?>
        <p class="site-flash site-flash--ok" role="status"><?= htmlspecialchars((string) $flashOk, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if ($flashErr): ?>
        <p class="site-flash site-flash--err" role="alert"><?= htmlspecialchars((string) $flashErr, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <?php if (!$contactInboxConfigured): ?>
        <p class="site-flash site-flash--err"><?= htmlspecialchars(__('site.contact_inbox_missing'), ENT_QUOTES, 'UTF-8') ?></p>
    <?php else: ?>
        <form method="post" action="<?= htmlspecialchars(url('contact'), ENT_QUOTES, 'UTF-8') ?>" class="site-form" novalidate>
            <?= \App\Core\Csrf::field() ?>
            <div class="sr-only" aria-hidden="true">
                <label>Company website<input type="text" name="company_website" tabindex="-1" autocomplete="off"></label>
            </div>
            <div class="site-form__field">
                <label for="contact-name"><?= htmlspecialchars(__('site.contact_name'), ENT_QUOTES, 'UTF-8') ?></label>
                <input id="contact-name" name="full_name" type="text" required maxlength="160" autocomplete="name">
            </div>
            <div class="site-form__field">
                <label for="contact-email"><?= htmlspecialchars(__('site.contact_email'), ENT_QUOTES, 'UTF-8') ?></label>
                <input id="contact-email" name="from_email" type="email" required maxlength="255" autocomplete="email">
            </div>
            <div class="site-form__field">
                <label for="contact-subject"><?= htmlspecialchars(__('site.contact_subject'), ENT_QUOTES, 'UTF-8') ?></label>
                <input id="contact-subject" name="subject" type="text" required maxlength="200">
            </div>
            <div class="site-form__field">
                <label for="contact-message"><?= htmlspecialchars(__('site.contact_message'), ENT_QUOTES, 'UTF-8') ?></label>
                <textarea id="contact-message" name="message" rows="7" required maxlength="5000"></textarea>
            </div>
            <button type="submit" class="hi-cta hi-cta-solid"><?= htmlspecialchars(__('site.contact_submit'), ENT_QUOTES, 'UTF-8') ?></button>
            <p class="site-form__note"><?= htmlspecialchars(__('site.contact_privacy'), ENT_QUOTES, 'UTF-8') ?></p>
        </form>
    <?php endif; ?>

    <p class="mt-8">
        <a href="<?= htmlspecialchars(url('demande-donnees'), ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-semibold text-emerald-400 underline-offset-4 hover:underline">
            <?= htmlspecialchars(__('site.contact_rights_link'), ENT_QUOTES, 'UTF-8') ?>
        </a>
    </p>
</article>
