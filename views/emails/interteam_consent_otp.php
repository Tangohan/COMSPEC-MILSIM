<?php
declare(strict_types=1);
/** @var string $displayName */
/** @var string $tenantName */
/** @var string $code */
/** @var int $ttlMinutes */
/** @var string $missionTitle */
/** @var string $sharingSummary */
$sharingSummary = isset($sharingSummary) ? trim((string) $sharingSummary) : '';
?>
<p>Bonjour <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>,</p>
<p>Vous avez demandé à confirmer votre <strong>autorisation de partage</strong> pour la coopération <strong><?= htmlspecialchars($missionTitle, ENT_QUOTES, 'UTF-8') ?></strong> (communauté <?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?>).</p>
<?php if ($sharingSummary !== ''): ?>
<p><strong>Portée couverte par cette confirmation</strong> : <?= htmlspecialchars($sharingSummary, ENT_QUOTES, 'UTF-8') ?>.</p>
<?php endif; ?>
<p style="font-size:1.5rem;font-weight:bold;letter-spacing:0.2em;"><?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></p>
<p>Ce code est valable environ <?= (int) $ttlMinutes ?> minute(s), uniquement pour cette demande. Si vous n’êtes pas à l’origine de ce message, ignorez-le.</p>
