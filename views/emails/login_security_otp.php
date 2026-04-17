<?php
declare(strict_types=1);
/** @var string $displayName */
/** @var string $tenantName */
/** @var string $code */
/** @var int $ttlMinutes */
?>
<p>Bonjour <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>,</p>
<p>Une tentative de connexion nécessitant une validation renforcée a été détectée pour votre compte de sécurité sur <strong><?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?></strong>.</p>
<p style="font-size:1.5rem;font-weight:bold;letter-spacing:0.2em;"><?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></p>
<p>Ce code OTP est valable environ <?= (int) $ttlMinutes ?> minute(s). Si vous n’êtes pas à l’origine de cette demande, ignorez ce message et changez votre mot de passe.</p>
