<?php
declare(strict_types=1);
$success = trim((string) ($success ?? ''));
$error = trim((string) ($error ?? ''));
if ($success !== ''): ?>
    <p class="bo-adv__flash bo-adv__flash--ok"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif;
if ($error !== ''): ?>
    <p class="bo-adv__flash bo-adv__flash--err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
