<?php
declare(strict_types=1);
// Rôles de contributeur (/contribuer) : App\Controllers\Web\ContributorController. Textes : lang/*/site.php (contrib_*).
use App\Controllers\Web\ContributorController;
use App\Core\Csrf;
use App\Core\Session;

$t = static fn (string $key, array $r = []): string => htmlspecialchars(__('site.contrib_' . $key, $r), ENT_QUOTES, 'UTF-8');
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$user = is_array($contribUser ?? null) ? $contribUser : null;
$ready = (bool) ($contribReady ?? false);
$mine = is_array($contribMine ?? null) ? $contribMine : [];
$credits = is_array($contribCredits ?? null) ? $contribCredits : [];
$want = (string) ($contribWant ?? '');
$prefLang = (string) ($contribLang ?? '');
$publicName = (string) ($contribPublicName ?? '');
$callsign = (string) ($contribCallsign ?? '');
$roles = [
    'translator' => '<path d="M4 5h9M8.5 3v2M6 5c0 4 3 7 6.5 8M11 5c-.6 3.4-3.2 6.6-7 8"/><path d="M13 20l4-9 4 9M14.5 17h5"/>',
    'tester' => '<path d="M9 3h6M10 3v6l-5.5 9.5A1.7 1.7 0 0 0 6 21h12a1.7 1.7 0 0 0 1.5-2.5L14 9V3"/><path d="M7.5 15h9"/>',
    'content' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M10 9.5v5l4.5-2.5z"/>',
    'developer' => '<path d="M8 8l-4 4 4 4M16 8l4 4-4 4M13.5 5l-3 14"/>',
    'support' => '<path d="M4 13a8 8 0 0 1 16 0v4a2 2 0 0 1-2 2h-1v-6h3M4 13v4a2 2 0 0 0 2 2h1v-6H4"/>',
];
$statusLabel = static fn (string $s): string => htmlspecialchars(__('site.contrib_status_' . $s), ENT_QUOTES, 'UTF-8');
$flashOk = Session::getFlash('success');
$flashErr = Session::getFlash('error');
?>
<article class="ctb">
    <header class="ctb-hero">
        <p class="ctb-kicker"><?= $t('kicker') ?></p>
        <h1 class="ctb-title"><?= $t('title') ?></h1>
        <p class="ctb-lead"><?= $t('lead') ?></p>
        <div class="ctb-actions">
            <a class="ctb-btn ctb-btn--primary" href="#demande"><?= $t('cta_apply') ?></a>
            <a class="ctb-btn" href="<?= $h(url('traduire-site')) ?>"><?= $t('cta_site') ?></a>
            <a class="ctb-btn" href="<?= $h(url('traduire-atak')) ?>"><?= $t('cta_mod') ?></a>
        </div>
    </header>

    <?php if (is_string($flashOk) && $flashOk !== ''): ?><p class="ctb-msg is-ok" role="status"><?= $h($flashOk) ?></p><?php endif; ?>
    <?php if (is_string($flashErr) && $flashErr !== ''): ?><p class="ctb-msg is-bad" role="alert"><?= $h($flashErr) ?></p><?php endif; ?>

    <section class="ctb-section" aria-labelledby="ctb-roles-h">
        <h2 id="ctb-roles-h" class="ctb-h2"><?= $t('roles_title') ?></h2>
        <p class="ctb-sub"><?= $t('roles_lead') ?></p>
        <ul class="ctb-roles">
            <?php foreach ($roles as $role => $icon): ?>
            <li class="ctb-role" id="role-<?= $role ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true" class="ctb-role__ico"><?= $icon ?></svg>
                <h3 class="ctb-role__name"><?= $t('role_' . $role) ?></h3>
                <p class="ctb-role__desc"><?= $t('role_' . $role . '_desc') ?></p>
                <p class="ctb-role__discord"><?= $t('discord_role') ?> <strong translate="no"><?= $h(ContributorController::DISCORD_ROLES[$role]) ?></strong></p>
                <?php if (isset($mine[$role])): ?>
                <p class="ctb-pill ctb-pill--<?= $h($mine[$role]['status']) ?>"><?= $statusLabel((string) $mine[$role]['status']) ?></p>
                <?php else: ?>
                <a class="ctb-role__go" href="<?= $h(url('contribuer?role=' . $role)) ?>#demande"><?= $t('role_apply') ?></a>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <?php if ($user !== null && $mine !== []): ?>
    <section class="ctb-section" id="mes-roles" aria-labelledby="ctb-mine-h">
        <h2 id="ctb-mine-h" class="ctb-h2"><?= $t('mine_title') ?></h2>
        <ul class="ctb-mine">
            <?php foreach ($mine as $role => $row): ?>
            <li>
                <span class="ctb-mine__role"><?= $t('role_' . $role) ?><?php if ((string) $row['languages'] !== ''): ?> <span class="ctb-mine__langs" translate="no"><?= $h(strtoupper(str_replace(',', ' · ', (string) $row['languages']))) ?></span><?php endif; ?></span>
                <span class="ctb-pill ctb-pill--<?= $h($row['status']) ?>"><?= $statusLabel((string) $row['status']) ?></span>
                <?php if ((string) $row['admin_note'] !== '' && in_array((string) $row['status'], ['rejected', 'revoked'], true)): ?>
                <span class="ctb-mine__note" translate="no"><?= $h($row['admin_note']) ?></span>
                <?php endif; ?>
                <?php if ((string) $row['status'] === 'active' && $role === 'translator'): ?>
                <a class="ctb-link" href="<?= $h(url('traduire-site')) ?>"><?= $t('mine_open_space') ?></a>
                <?php endif; ?>
                <?php if ((string) $row['status'] === 'pending'): ?>
                <form method="post" action="<?= $h(url('contribuer/retirer')) ?>" class="ctb-inline">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="role" value="<?= $h($role) ?>">
                    <button type="submit" class="ctb-link"><?= $t('mine_withdraw') ?></button>
                </form>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <section class="ctb-section ctb-apply" id="demande" aria-labelledby="ctb-apply-h">
        <h2 id="ctb-apply-h" class="ctb-h2"><?= $t('apply_title') ?></h2>
        <?php if (!$ready): ?>
        <p class="ctb-sub"><?= $t('not_ready') ?></p>
        <?php elseif ($user === null): ?>
        <p class="ctb-sub"><?= $t('apply_login') ?></p>
        <div class="ctb-actions">
            <a class="ctb-btn ctb-btn--primary" href="<?= $h(url('login')) ?>"><?= $t('login') ?></a>
            <a class="ctb-btn" href="<?= $h(url('register')) ?>"><?= $t('register') ?></a>
        </div>
        <?php else: ?>
        <p class="ctb-sub"><?= $t('apply_lead') ?></p>
        <form method="post" action="<?= $h(url('contribuer/demande')) ?>" class="ctb-form" id="ctb-form">
            <?= Csrf::field() ?>
            <label class="ctb-field"><span><?= $t('field_role') ?></span>
                <select name="role" id="ctb-role" required>
                    <?php foreach (array_keys($roles) as $role): ?>
                    <option value="<?= $role ?>" <?= $want === $role ? 'selected' : '' ?>><?= $t('role_' . $role) ?></option>
                    <?php endforeach; ?>
                </select></label>
            <label class="ctb-field" id="ctb-langs-field"><span><?= $t('field_languages') ?></span>
                <input type="text" name="languages" maxlength="60" autocomplete="off" spellcheck="false" placeholder="es, de" value="<?= $h($prefLang) ?>">
                <small><?= $t('field_languages_help') ?></small></label>
            <label class="ctb-field"><span><?= $t('field_discord') ?></span>
                <input type="text" name="discord" maxlength="64" autocomplete="off" spellcheck="false">
                <small><?= $t('field_discord_help') ?></small></label>
            <label class="ctb-field ctb-field--wide"><span><?= $t('field_motivation') ?></span>
                <textarea name="motivation" rows="4" maxlength="1500"></textarea>
                <small><?= $t('field_motivation_help') ?></small></label>
            <label class="ctb-field"><span><?= $t('field_public_name') ?></span>
                <input type="text" name="public_name" maxlength="40" autocomplete="nickname" spellcheck="false" value="<?= $h($publicName) ?>" placeholder="<?= $h($callsign) ?>">
                <small><?= $t('field_public_name_help') ?></small></label>
            <label class="ctb-check"><input type="checkbox" name="public_credit" value="1" checked> <?= $t('field_credit') ?></label>
            <div class="ctb-actions"><button type="submit" class="ctb-btn ctb-btn--primary"><?= $t('apply_submit') ?></button></div>
        </form>
        <script>
        (function () {
            var role = document.getElementById('ctb-role');
            var langs = document.getElementById('ctb-langs-field');
            if (!role || !langs) { return; }
            var sync = function () { langs.hidden = role.value !== 'translator'; };
            role.addEventListener('change', sync);
            sync();
        })();
        </script>
        <?php endif; ?>
    </section>

    <?php if ($credits !== []): ?>
    <section class="ctb-section" aria-labelledby="ctb-credits-h">
        <h2 id="ctb-credits-h" class="ctb-h2"><?= $t('credits_title') ?></h2>
        <ul class="ctb-credits">
            <?php foreach ($credits as $c): ?>
            <li><strong translate="no"><?= $h($c['name']) ?></strong>
                <span><?= implode(' · ', array_map(static fn (string $r): string => htmlspecialchars(__('site.contrib_role_' . $r), ENT_QUOTES, 'UTF-8'), $c['roles'])) ?><?php if ($c['languages'] !== ''): ?> <span translate="no">(<?= $h(strtoupper(str_replace(',', ', ', $c['languages']))) ?>)</span><?php endif; ?></span></li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>
</article>
