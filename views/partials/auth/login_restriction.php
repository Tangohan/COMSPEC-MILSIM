<?php
declare(strict_types=1);

$restrictionEscape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$hasDecision = isset($loginRestriction['id']);
$restrictionLanguage = $loginRestriction['locale'] ?? locale();
$restrictionText = static fn (string $key): string => $restrictionEscape(__($key, [], $restrictionLanguage));
?>
<dialog lang="<?= $restrictionEscape($restrictionLanguage) ?>" class="au-restriction" open data-login-restriction aria-labelledby="login-restriction-title" aria-describedby="login-restriction-message">
    <header class="au-restriction__heading">
        <span class="au-restriction__symbol" aria-hidden="true">!</span>
        <h2 id="login-restriction-title" tabindex="-1"><?= $restrictionText($hasDecision ? 'auth.restriction_ban_title' : 'auth.restriction_title') ?></h2>
        <a class="au-restriction__close" href="<?= $restrictionEscape(url('login')) ?>" aria-label="<?= $restrictionText('auth.restriction_close') ?>" data-restriction-dismiss>×</a>
    </header>
    <div class="au-restriction__body">
        <div class="au-restriction__notice">
            <?php if ($hasDecision): ?><h3 class="au-restriction__label"><?= $restrictionText('auth.restriction_reason') ?></h3><?php endif; ?>
            <p id="login-restriction-message"><?= $hasDecision ? $restrictionEscape($loginRestriction['message']) : $restrictionText('auth.flash_access_restricted') ?></p>
        </div>
        <?php if ($hasDecision): ?>
            <dl class="au-restriction__facts">
                <div><dt><?= $restrictionText('auth.restriction_reference') ?></dt><dd>#<?= (int) $loginRestriction['id'] ?></dd></div>
                <div><dt><?= $restrictionText('auth.restriction_scope') ?></dt><dd><?= $restrictionText('auth.restriction_scope_platform') ?></dd></div>
                <?php if ((string) ($loginRestriction['start'] ?? '') !== ''): ?>
                    <div><dt><?= $restrictionText('auth.restriction_start') ?></dt><dd><?= $restrictionEscape((string) $loginRestriction['start']) ?></dd></div>
                <?php endif; ?>
                <?php if (!empty($loginRestriction['permanent'])): ?>
                    <div><dt><?= $restrictionText('auth.restriction_duration') ?></dt><dd><?= $restrictionText('moderation.permanent') ?></dd></div>
                <?php else: ?>
                    <div><dt><?= $restrictionText('auth.restriction_end') ?></dt><dd><?= $restrictionEscape($loginRestriction['end']) ?></dd></div>
                <?php endif; ?>
                <?php if ($loginRestriction['rule'] !== ''): ?>
                    <div class="au-restriction__rule">
                        <dt><?= $restrictionText('auth.restriction_rule') ?></dt>
                        <dd>
                            <?php if (!empty($loginRestriction['rule_url'])): ?>
                                <a class="au-link" href="<?= $restrictionEscape($loginRestriction['rule_url']) ?>" target="_blank" rel="noopener"><?= $restrictionEscape($loginRestriction['rule']) ?></a>
                            <?php else: ?>
                                <?= $restrictionEscape($loginRestriction['rule']) ?>
                            <?php endif; ?>
                        </dd>
                    </div>
                <?php endif; ?>
            </dl>
            <p class="au-restriction__effects"><?= $restrictionText('auth.restriction_effects') ?></p>
        <?php endif; ?>
        <?php $restrictionProgress = $hasDecision && is_array($loginRestriction['appeal'] ?? null) ? $loginRestriction['appeal'] : null; ?>
        <?php if (!empty($loginRestriction['appeal_closed']) && $restrictionProgress === null): ?>
            <section class="au-restriction__appeal" aria-labelledby="login-restriction-appeal">
                <h3 id="login-restriction-appeal"><?= $restrictionText('moderation.appeal_closed_title') ?></h3>
                <p><?= $restrictionText('moderation.appeal_closed_message') ?></p>
                <a class="au-link" href="<?= $restrictionEscape(url('contester-sanction')) ?>"><?= $restrictionText('moderation.view_decision_or_appeal') ?></a>
            </section>
        <?php elseif ($restrictionProgress !== null): ?>
        <section class="au-restriction__appeal" aria-labelledby="login-restriction-appeal">
            <h3 id="login-restriction-appeal"><?= $restrictionText('auth.restriction_followup') ?></h3>
            <p class="au-restriction__status" role="status"><?= $restrictionEscape(__('moderation.appeal_status_' . (string) $restrictionProgress['state'], ['date' => (string) $restrictionProgress['received']], $restrictionLanguage)) ?></p>
            <p><?= $restrictionText('auth.restriction_followup_how') ?></p>
            <form method="post" action="<?= $restrictionEscape(url('contester-sanction/lien')) ?>" data-busy-form>
                <?= \App\Core\Csrf::field() ?>
                <label class="rg-label" for="restriction-email"><?= $restrictionText('auth.email') ?></label>
                <div class="au-restriction__request">
                    <input class="rg-input" type="email" name="email" id="restriction-email" maxlength="255" autocomplete="email" required>
                    <button type="submit" class="ds-btn ds-btn--primary au-btn au-restriction__primary" data-busy-label="<?= $restrictionText('moderation.sending') ?>"><?= $restrictionText('auth.restriction_followup_button') ?></button>
                </div>
            </form>
        </section>
        <?php else: ?>
        <section class="au-restriction__appeal" aria-labelledby="login-restriction-appeal">
            <h3 id="login-restriction-appeal"><?= $restrictionText($hasDecision ? 'auth.restriction_appeal' : 'auth.restriction_review') ?></h3>
            <p><?= $restrictionText($hasDecision ? 'auth.restriction_appeal_how' : 'auth.restriction_review_help') ?></p>
            <?php if ($hasDecision): ?>
                <form method="post" action="<?= $restrictionEscape(url('contester-sanction/lien')) ?>" data-busy-form>
                    <?= \App\Core\Csrf::field() ?>
                    <label class="rg-label" for="restriction-email"><?= $restrictionText('auth.email') ?></label>
                    <div class="au-restriction__request">
                        <input class="rg-input" type="email" name="email" id="restriction-email" maxlength="255" autocomplete="email" required>
                        <button type="submit" class="ds-btn ds-btn--primary au-btn au-restriction__primary" data-busy-label="<?= $restrictionText('moderation.sending') ?>"><?= $restrictionText('auth.restriction_appeal_button') ?></button>
                    </div>
                </form>
            <?php else: ?>
                <a class="ds-btn ds-btn--primary au-btn au-restriction__primary" href="<?= $restrictionEscape(url('contact')) ?>"><?= $restrictionText('auth.restriction_contact') ?></a>
                <a class="au-link" href="<?= $restrictionEscape(url('contester-sanction')) ?>"><?= $restrictionText('auth.restriction_appeal') ?></a>
            <?php endif; ?>
        </section>
        <?php endif; ?>
        <footer class="au-restriction__actions">
            <a class="au-link" href="<?= $restrictionEscape(url('legal/site') . '#cgu') ?>"><?= $restrictionText('auth.restriction_terms') ?></a>
            <?php if ($hasDecision): ?>
                <a class="au-link" href="<?= $restrictionEscape(url('contact')) ?>"><?= $restrictionText('auth.restriction_contact') ?></a>
            <?php endif; ?>
            <a class="au-link au-restriction__back" href="<?= $restrictionEscape(url('login')) ?>" data-restriction-dismiss><?= $restrictionText('auth.restriction_back') ?></a>
        </footer>
    </div>
</dialog>
