<?php
declare(strict_types=1);

$msgxErr = \App\Core\Session::getFlash('error');
$msgxOk = \App\Core\Session::getFlash('success');
?>
<?php if ($msgxErr || $msgxOk): ?>
<div class="msgx-flashes">
    <?php if ($msgxErr): ?>
        <div class="msgx-flash msgx-flash--error" role="alert"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="10" r="7.5" stroke="currentColor" stroke-width="1.6"/><path d="M10 6v4.5M10 13.5v.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg><span><?= htmlspecialchars((string) $msgxErr, ENT_QUOTES, 'UTF-8') ?></span><button type="button" data-msg-dismiss aria-label="Fermer">×</button></div>
    <?php endif; ?>
    <?php if ($msgxOk): ?>
        <div class="msgx-flash msgx-flash--success" role="status"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="10" r="7.5" stroke="currentColor" stroke-width="1.6"/><path d="m6.8 10.2 2.2 2.2 4.2-4.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg><span><?= htmlspecialchars((string) $msgxOk, ENT_QUOTES, 'UTF-8') ?></span><button type="button" data-msg-dismiss aria-label="Fermer">×</button></div>
    <?php endif; ?>
</div>
<?php endif; ?>
