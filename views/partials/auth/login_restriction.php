<?php
declare(strict_types=1);

$restrictionEscape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$hasDecision = isset($loginRestriction['id']);
?>
<dialog class="au-restriction" open data-login-restriction aria-labelledby="login-restriction-title" aria-describedby="login-restriction-message">
    <header class="au-restriction__heading">
        <span class="au-restriction__symbol" aria-hidden="true">!</span>
        <div>
            <p class="au-restriction__kicker"><?= $t('auth.restriction_heading') ?></p>
            <h2 id="login-restriction-title" tabindex="-1"><?= $t($hasDecision ? 'auth.restriction_ban_title' : 'auth.restriction_title') ?></h2>
        </div>
        <a class="au-restriction__close" href="<?= $restrictionEscape(url('login')) ?>" aria-label="<?= $t('auth.restriction_close') ?>" data-restriction-dismiss>×</a>
    </header>
    <div class="au-restriction__body">
        <div class="au-restriction__notice">
            <?php if ($hasDecision): ?><h3 class="au-restriction__label"><?= $t('auth.restriction_reason') ?></h3><?php endif; ?>
            <p id="login-restriction-message"><?= $hasDecision ? $restrictionEscape($loginRestriction['message']) : $t('auth.flash_access_restricted') ?></p>
        </div>
        <?php if ($hasDecision): ?>
            <dl class="au-restriction__facts">
                <div><dt><?= $t('auth.restriction_reference') ?></dt><dd>#<?= (int) $loginRestriction['id'] ?></dd></div>
                <div><dt><?= $t('auth.restriction_end') ?></dt><dd><?= $restrictionEscape($loginRestriction['end']) ?></dd></div>
                <?php if ($loginRestriction['rule'] !== ''): ?>
                    <div class="au-restriction__rule"><dt><?= $t('auth.restriction_rule') ?></dt><dd><?= $restrictionEscape($loginRestriction['rule']) ?></dd></div>
                <?php endif; ?>
            </dl>
        <?php endif; ?>
        <?php if (!empty($loginRestriction['appeal_closed'])): ?>
            <section class="au-restriction__appeal" aria-labelledby="login-restriction-appeal">
                <h3 id="login-restriction-appeal"><?= $t('moderation.appeal_closed_title') ?></h3>
                <p><?= $t('moderation.appeal_closed_message') ?></p>
                <a class="au-link" href="<?= $restrictionEscape(url('contester-sanction')) ?>"><?= $t('moderation.view_decision_or_appeal') ?></a>
            </section>
        <?php else: ?>
        <section class="au-restriction__appeal" aria-labelledby="login-restriction-appeal">
            <h3 id="login-restriction-appeal"><?= $t($hasDecision ? 'auth.restriction_appeal' : 'auth.restriction_review') ?></h3>
            <p><?= $t($hasDecision ? 'auth.restriction_appeal_help' : 'auth.restriction_review_help') ?></p>
            <?php if ($hasDecision): ?>
                <form method="post" action="<?= $restrictionEscape(url('contester-sanction/lien')) ?>" data-busy-form>
                    <?= \App\Core\Csrf::field() ?>
                    <label class="rg-label" for="restriction-email"><?= $t('auth.email') ?></label>
                    <div class="au-restriction__request">
                        <input class="rg-input" type="email" name="email" id="restriction-email" maxlength="255" autocomplete="email" required>
                        <button type="submit" class="ds-btn ds-btn--primary au-btn au-restriction__primary" data-busy-label="<?= $t('moderation.sending') ?>"><?= $t('auth.restriction_send_link') ?></button>
                    </div>
                </form>
            <?php else: ?>
                <a class="ds-btn ds-btn--primary au-btn au-restriction__primary" href="<?= $restrictionEscape(url('contact')) ?>"><?= $t('auth.restriction_contact') ?></a>
                <a class="au-link" href="<?= $restrictionEscape(url('contester-sanction')) ?>"><?= $t('auth.restriction_appeal') ?></a>
            <?php endif; ?>
        </section>
        <?php endif; ?>
        <footer class="au-restriction__actions">
            <a class="au-link" href="<?= $restrictionEscape(url('legal/site') . '#cgu') ?>"><?= $t('auth.restriction_terms') ?></a>
            <a class="au-link au-restriction__back" href="<?= $restrictionEscape(url('login')) ?>" data-restriction-dismiss><?= $t('auth.restriction_back') ?></a>
        </footer>
    </div>
</dialog>
