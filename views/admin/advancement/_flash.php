<?php
$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
if (!empty($success)): ?>
    <p class="adv-flash adv-flash--ok"><?= $h($success) ?></p>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <p class="adv-flash adv-flash--bad"><?= $h($error) ?></p>
<?php endif; ?>
