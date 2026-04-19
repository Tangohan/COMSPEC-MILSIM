<?php
declare(strict_types=1);
/** @var string $displayName */
/** @var string $tenantName */
/** @var string $code */
/** @var int $ttlMinutes */
$isMailboxSelfTest = !empty($isMailboxSelfTest);
?>
<p>Bonjour <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>,</p>
<?php if ($isMailboxSelfTest): ?>
<p>Vous avez demandé un <strong>envoi de test</strong> depuis la page des préférences du compte pour <strong><?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?></strong>. Ce message ne correspond pas à une connexion en cours.</p>
<?php else: ?>
<p>Une tentative de connexion nécessitant une validation renforcée a été détectée pour votre compte sur <strong><?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?></strong>.</p>
<?php endif; ?>
<p style="font-size:1.5rem;font-weight:bold;letter-spacing:0.2em;"><?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></p>
<?php if ($isMailboxSelfTest): ?>
<p>Ce code est valable environ <?= (int) $ttlMinutes ?> minute(s) et sert uniquement à confirmer que vous recevez bien nos messages. Vous pouvez l’ignorer une fois la vérification faite.</p>
<?php else: ?>
<p>Ce code est valable environ <?= (int) $ttlMinutes ?> minute(s). Si vous n’êtes pas à l’origine de cette demande, ignorez ce message et changez votre mot de passe.</p>
<?php endif; ?>
