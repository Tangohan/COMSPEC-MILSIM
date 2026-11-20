<?php
declare(strict_types=1);

$restrictionEscape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$hasDecision = isset($loginRestriction['id']);
?>
<dialog class="au-restriction" open data-login-restriction aria-labelledby="login-restriction-title" aria-describedby="login-restriction-message">
    <header class="au-restriction__heading">
        <p class="ds-kicker"><?= $t('auth.restriction_heading') ?></p>
        <h2 id="login-restriction-title" tabindex="-1"><?= $t($hasDecision ? 'auth.restriction_ban_title' : 'auth.restriction_title') ?></h2>
    </header>
    <div class="au-restriction__body">
        <p id="login-restriction-message"><?= $hasDecision ? $restrictionEscape($loginRestriction['message']) : $t('auth.flash_access_restricted') ?></p>
        <?php if ($hasDecision): ?>
            <dl class="au-restriction__facts">
                <div><dt><?= $t('auth.restriction_reference') ?></dt><dd>#<?= (int) $loginRestriction['id'] ?></dd></div>
                <div><dt><?= $t('auth.restriction_end') ?></dt><dd><?= $restrictionEscape($loginRestriction['end']) ?></dd></div>
                <?php if ($loginRestriction['rule'] !== ''): ?>
                    <div><dt><?= $t('auth.restriction_rule') ?></dt><dd><?= $restrictionEscape($loginRestriction['rule']) ?></dd></div>
                <?php endif; ?>
            </dl>
        <?php endif; ?>
        <section class="au-restriction__appeal" aria-labelledby="login-restriction-appeal">
            <h3 id="login-restriction-appeal"><?= $t($hasDecision ? 'auth.restriction_appeal' : 'auth.restriction_review') ?></h3>
            <p><?= $t($hasDecision ? 'auth.restriction_appeal_help' : 'auth.restriction_review_help') ?></p>
            <?php if ($hasDecision): ?>
                <form method="post" action="<?= $restrictionEscape(url('contester-sanction/lien')) ?>" data-busy-form>
                    <?= \App\Core\Csrf::field() ?>
                    <label class="rg-label" for="restriction-email"><?= $t('auth.email') ?></label>
                    <div class="au-restriction__request">
                        <input class="rg-input" type="email" name="email" id="restriction-email" maxlength="255" autocomplete="email" required>
                        <button type="submit" class="ds-btn ds-btn--primary au-btn"><?= $t('auth.restriction_send_link') ?></button>
                    </div>
                </form>
            <?php else: ?>
                <a class="ds-btn ds-btn--primary au-btn" href="<?= $restrictionEscape(url('contact')) ?>"><?= $t('auth.restriction_contact') ?></a>
                <a class="au-link" href="<?= $restrictionEscape(url('contester-sanction')) ?>"><?= $t('auth.restriction_appeal') ?></a>
            <?php endif; ?>
        </section>
        <footer class="au-restriction__actions">
            <a class="au-link" href="<?= $restrictionEscape(url('legal/site') . '#cgu') ?>"><?= $t('auth.restriction_terms') ?></a>
            <a class="ds-btn ds-btn--secondary au-btn" href="<?= $restrictionEscape(url('login')) ?>" data-restriction-dismiss><?= $t('auth.restriction_back') ?></a>
        </footer>
    </div>
</dialog>
