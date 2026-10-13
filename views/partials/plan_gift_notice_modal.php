<?php
declare(strict_types=1);

// Fenêtre « formule offerte » montrée une fois aux gestionnaires de la communauté, dans la langue de leur profil.
// Écrite en français et en anglais (App\Support\PlanGift::copy) : translate="no" pour éviter une double traduction.

use App\Support\PlanGift;

$planGiftUserId = (int) (\App\Core\Session::get('user_id') ?? 0);
if ($planGiftUserId < 1) {
    return;
}
$planGiftCheckKey = 'plan_gift_notice_checked_at';
$planGiftLastCheck = (int) (\App\Core\Session::get($planGiftCheckKey) ?? 0);
if ($planGiftLastCheck > time() - 600) {
    return;
}
$planGiftNotice = null;
try {
    $planGiftNotice = (new \App\Repositories\PlanGiftRepository())->pendingNoticeForUser($planGiftUserId);
} catch (\Throwable) {
    // Table absente (base pas encore mise à jour) : rien à montrer.
}
if ($planGiftNotice === null) {
    \App\Core\Session::set($planGiftCheckKey, time());

    return;
}

$planGiftLang = '';
try {
    $planGiftProfile = (new \App\Repositories\UserProfileRepository())->getByUserId($planGiftUserId);
    $planGiftLang = trim((string) ($planGiftProfile['language'] ?? ''));
} catch (\Throwable) {
}
$planGiftLang = PlanGift::locale($planGiftLang !== '' ? $planGiftLang : (function_exists('locale') ? locale() : 'fr'));
$planGiftCopy = PlanGift::copy(
    $planGiftLang,
    (string) ($planGiftNotice['tenant_name'] ?? ''),
    (string) (($planGiftNotice['plan_name'] ?? '') !== '' ? $planGiftNotice['plan_name'] : $planGiftNotice['plan_slug']),
    isset($planGiftNotice['ends_at']) ? (string) $planGiftNotice['ends_at'] : null
);
$planGiftMessage = PlanGift::message(
    (string) ($planGiftNotice['message_fr'] ?? ''),
    (string) ($planGiftNotice['message_en'] ?? ''),
    $planGiftLang
);
$planGiftAction = url('compte/formule-offerte/' . (int) $planGiftNotice['notice_id'] . '/vu');
$hg = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<div class="plan-gift-overlay" id="plan-gift-notice" role="dialog" aria-modal="true" aria-labelledby="plan-gift-title" translate="no" lang="<?= $hg($planGiftLang) ?>">
    <form class="plan-gift-card" method="post" action="<?= $hg($planGiftAction) ?>">
        <?= \App\Core\Csrf::field() ?>
        <p class="plan-gift-kicker"><span aria-hidden="true">🎁</span> <?= $hg($planGiftCopy['kicker']) ?></p>
        <h2 class="plan-gift-title" id="plan-gift-title"><?= $hg($planGiftCopy['title']) ?></h2>
        <p class="plan-gift-lead"><?= $hg($planGiftCopy['lead']) ?></p>
        <p><?= $hg($planGiftCopy['duration']) ?></p>
        <p><?= $hg($planGiftCopy['immediate']) ?></p>
        <?php if ($planGiftMessage !== ''): ?>
            <p class="plan-gift-label"><?= $hg($planGiftCopy['message_label']) ?></p>
            <blockquote class="plan-gift-message"><?= nl2br($hg($planGiftMessage)) ?></blockquote>
        <?php endif; ?>
        <div class="plan-gift-actions">
            <button type="submit" name="go" value="back-office" class="plan-gift-btn plan-gift-btn--solid"><?= $hg($planGiftCopy['cta']) ?></button>
            <button type="submit" name="go" value="" class="plan-gift-btn"><?= $hg($planGiftCopy['close']) ?></button>
        </div>
    </form>
</div>
<style>
.plan-gift-overlay{position:fixed;inset:0;z-index:9990;display:flex;align-items:center;justify-content:center;padding:16px;background:rgba(15,23,42,.6)}
.plan-gift-card{width:100%;max-width:480px;max-height:calc(100vh - 32px);overflow:auto;background:#fff;color:#0f172a;border-radius:16px;padding:24px;box-shadow:0 24px 60px rgba(0,0,0,.35);font-size:15px;line-height:1.55}
.plan-gift-card p{margin:0 0 10px}
.plan-gift-kicker{font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#047857}
.plan-gift-title{margin:0 0 12px;font-size:22px;font-weight:800;line-height:1.25}
.plan-gift-lead{font-weight:700}
.plan-gift-label{margin-top:14px!important;font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:#64748b}
.plan-gift-message{margin:0 0 12px;padding:10px 12px;border-left:3px solid #0f172a;background:#f1f5f9;border-radius:0 8px 8px 0}
.plan-gift-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:18px}
.plan-gift-btn{flex:1 1 auto;min-height:44px;padding:10px 16px;border-radius:10px;border:1px solid #cbd5e1;background:#fff;color:#0f172a;font-weight:700;cursor:pointer}
.plan-gift-btn--solid{background:#047857;border-color:#047857;color:#fff}
</style>
