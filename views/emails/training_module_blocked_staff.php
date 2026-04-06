<?php
declare(strict_types=1);
/** @var string $staffDisplayName */
/** @var string $learnerDisplayName */
/** @var string $learnerEmail */
/** @var string $tenantName */
/** @var string $courseTitle */
/** @var string $moduleTitle */
/** @var string $summaryText */
/** @var string $enrollmentsAdminUrl */
?>
<p>Bonjour <?= htmlspecialchars($staffDisplayName, ENT_QUOTES, 'UTF-8') ?>,</p>
<p>Un membre de <strong><?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?></strong> a signalé qu’il n’arrive pas à valider un module dans la formation <strong><?= htmlspecialchars($courseTitle, ENT_QUOTES, 'UTF-8') ?></strong>.</p>
<p><strong>Module concerné :</strong> <?= htmlspecialchars($moduleTitle, ENT_QUOTES, 'UTF-8') ?><br>
<strong>Membre :</strong> <?= htmlspecialchars($learnerDisplayName, ENT_QUOTES, 'UTF-8') ?><?php if ($learnerEmail !== ''): ?> · <?= htmlspecialchars($learnerEmail, ENT_QUOTES, 'UTF-8') ?><?php endif; ?></p>
<?php if (trim($summaryText) !== ''): ?>
<p style="white-space:pre-wrap;border-left:3px solid #cbd5e1;padding-left:12px;margin:16px 0;"><?= nl2br(htmlspecialchars($summaryText, ENT_QUOTES, 'UTF-8')) ?></p>
<?php endif; ?>
<p>
    <a href="<?= htmlspecialchars($enrollmentsAdminUrl, ENT_QUOTES, 'UTF-8') ?>" style="display:inline-block;padding:10px 16px;background:#0f172a;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;">Ouvrir les assignations</a>
</p>
<p style="font-size:13px;color:#64748b;">Le membre dispose d’une page de synthèse dans son espace formation ; le résumé ci-dessus reprend les éléments qu’il a confirmés.</p>
