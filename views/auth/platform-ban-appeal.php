<?php
declare(strict_types=1);

use App\Core\Csrf;

$e = static fn (string|int|null $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$t = static fn (string $key, array $replace = []): string => $e(__($key, $replace));
$authTitle = __($notice === null ? 'moderation.appeal_title' : 'moderation.decision_title');
$athena_header_current = 'login';
?>
<!DOCTYPE html>
<html lang="<?= $e(html_lang()) ?>">
<head>
    <?php require base_path('views/partials/auth/head.php'); ?>
    <link rel="stylesheet" href="<?= $e(asset_url('assets/css/auth-appeal.css')) ?>">
</head>
<body class="ds-page ds-page--auth athena-appeal">
<a class="ds-skip" href="#contenu"><?= $t('common.skip_to_content') ?></a>
<?php require base_path('views/partials/athena_header_guest.php'); ?>
<main class="appeal-shell" id="contenu">
    <header class="appeal-hero">
        <div>
            <p class="ds-kicker"><?= $t('auth.restriction_heading') ?></p>
            <h1><?= $e($authTitle) ?></h1>
            <p><?= $t($notice === null ? 'moderation.appeal_intro' : 'moderation.decision_intro') ?></p>
        </div>
        <?php if ($notice !== null): ?>
            <span class="appeal-state"><?= $t($notice['active'] ? 'moderation.restriction_active' : 'moderation.restriction_ended') ?></span>
        <?php endif; ?>
    </header>

    <div class="ds-alert-stack">
        <?php if ($error): ?>
            <?php $flash_variant = 'error'; $flash_message = $error; $flash_margin_class = ''; require base_path('views/partials/flash_message.php'); ?>
        <?php endif; ?>
        <?php if ($success): ?>
            <?php $flash_variant = 'success'; $flash_message = $success; $flash_margin_class = ''; require base_path('views/partials/flash_message.php'); ?>
        <?php endif; ?>
    </div>

    <div class="appeal-grid">
        <?php if ($notice === null): ?>
            <section class="appeal-card" aria-labelledby="appeal-request">
                <h2 id="appeal-request"><?= $t('moderation.request_link') ?></h2>
                <form class="ds-form rg-form" method="post" action="<?= $e(url('contester-sanction/lien')) ?>" data-busy-form>
                    <?= Csrf::field() ?>
                    <div class="rg-field">
                        <label class="rg-label" for="ban-appeal-email"><?= $t('moderation.account_email') ?></label>
                        <input class="rg-input" id="ban-appeal-email" type="email" name="email" maxlength="255" autocomplete="email" required aria-describedby="appeal-delivery">
                        <p class="rg-hint" id="appeal-delivery"><?= $t('moderation.delivery_hint') ?></p>
                    </div>
                    <button type="submit" class="ds-btn ds-btn--primary au-btn" data-busy-label="<?= $t('moderation.sending') ?>"><?= $t('auth.restriction_send_link') ?></button>
                </form>
            </section>
            <aside class="appeal-card appeal-card--guide" aria-labelledby="appeal-steps">
                <h2 id="appeal-steps"><?= $t('moderation.appeal_steps') ?></h2>
                <ol class="appeal-steps">
                    <li><strong><?= $t('moderation.open_link') ?></strong><span><?= $t('moderation.open_link_hint') ?></span></li>
                    <li><strong><?= $t('moderation.describe_facts') ?></strong><span><?= $t('moderation.describe_facts_hint') ?></span></li>
                    <li><strong><?= $t('moderation.read_reply') ?></strong><span><?= $t('moderation.read_reply_hint') ?></span></li>
                </ol>
            </aside>
        <?php else: ?>
            <section class="appeal-card" aria-labelledby="ban-notice-title">
                <h2 id="ban-notice-title"><?= $t('moderation.decision_reference', ['id' => $notice['id']]) ?></h2>
                <p class="appeal-message"><?= $e($notice['message']) ?></p>
                <dl class="appeal-facts">
                    <?php if ($notice['active']): ?>
                        <div><dt><?= $t('moderation.restriction_end') ?></dt><dd><?= $e($notice['end']) ?></dd></div>
                    <?php endif; ?>
                    <?php if ($notice['rule'] !== ''): ?>
                        <div><dt><?= $t('auth.restriction_rule') ?></dt><dd><?= $e($notice['rule']) ?></dd></div>
                    <?php endif; ?>
                </dl>
            </section>
            <?php if ($appeal === null): ?>
                <section class="appeal-card" aria-labelledby="ban-appeal-title">
                    <h2 id="ban-appeal-title"><?= $t('moderation.request_review') ?></h2>
                    <form class="ds-form rg-form" method="post" action="<?= $e(url('contester-sanction/deposer')) ?>" data-busy-form>
                        <?= Csrf::field() ?>
                        <div class="rg-field">
                            <label class="rg-label" for="ban-appeal-body"><?= $t('moderation.statement') ?></label>
                            <textarea class="rg-input" id="ban-appeal-body" name="statement" rows="7" required minlength="10" maxlength="4000" aria-describedby="ban-appeal-help"></textarea>
                            <p class="rg-hint" id="ban-appeal-help"><?= $t('moderation.statement_hint') ?></p>
                        </div>
                        <button type="submit" class="ds-btn ds-btn--primary au-btn" data-busy-label="<?= $t('moderation.sending') ?>"><?= $t('moderation.submit_appeal') ?></button>
                    </form>
                </section>
            <?php else: ?>
                <section class="appeal-card" aria-labelledby="ban-followup-title">
                    <h2 id="ban-followup-title"><?= $t('moderation.follow_up') ?></h2>
                    <p class="appeal-review"><?= $t(match ($appeal['decision']) {
                        'lift' => 'moderation.appeal_accepted',
                        'uphold' => 'moderation.decision_upheld',
                        default => 'moderation.review_pending',
                    }) ?></p>
                    <?php if ($appeal['reply'] !== null): ?>
                        <h3><?= $t('moderation.reply') ?></h3>
                        <p class="appeal-message"><?= $e($appeal['reply']) ?></p>
                    <?php elseif ($appeal['decision'] === null): ?>
                        <p><?= $t('moderation.reply_pending') ?></p>
                    <?php endif; ?>
                    <details class="appeal-statement">
                        <summary><?= $t('moderation.read_statement') ?></summary>
                        <p class="appeal-message"><?= $e($appeal['statement']) ?></p>
                    </details>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <footer class="appeal-footer" aria-label="<?= $t('moderation.useful_links') ?>">
        <a class="au-link" href="<?= $e(url('login')) ?>"><?= $t('auth.back_to_login') ?></a>
        <a class="au-link" href="<?= $e(url('legal/site') . '#cgu') ?>"><?= $t('auth.restriction_terms') ?></a>
        <a class="au-link" href="<?= $e(url('contact')) ?>"><?= $t('auth.restriction_contact') ?></a>
    </footer>
</main>
<script defer src="<?= $e(asset_url('assets/js/athena-header.js')) ?>"></script>
<script defer src="<?= $e(asset_url('assets/js/auth_forms.js')) ?>"></script>
</body>
</html>
