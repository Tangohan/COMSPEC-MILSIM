<?php

declare(strict_types=1);

/** @var string $reference */
/** @var string $description */
/** @var string $amount */
/** @var string $transactionRef */
/** @var string $payerNote */
/** @var string $payerEmail */
/** @var string $tenantName */
/** @var string $adminUrl */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$rows = [
    'Référence Athena' => $reference,
    'Achat' => $description,
    'Montant attendu' => $amount,
    'Transaction PayPal' => $transactionRef,
    'Communauté' => $tenantName,
    'Adresse e-mail' => $payerEmail !== '' ? $payerEmail : '—',
];
if ($payerNote !== '') {
    $rows['Message'] = $payerNote;
}
$table = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="font-size:15px;margin-top:8px;">';
foreach ($rows as $label => $value) {
    $table .= '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#64748b;width:36%;">' . $escape($label) . '</td>'
        . '<td style="padding:8px 0;border-bottom:1px solid #e2e8f0;font-weight:600;color:#0f172a;">' . $escape((string) $value) . '</td></tr>';
}
$table .= '</table>';

$body = '<p>Un paiement PayPal vient d’être déclaré. Vérifiez sa réception sur votre compte PayPal (montant et référence dans la note), puis activez la formule.</p>'
    . $table
    . '<p style="margin-top:20px;"><a href="' . $escape($adminUrl) . '" style="display:inline-block;background:#047857;color:#ffffff;padding:12px 20px;font-weight:700;text-decoration:none;">Ouvrir Admin &gt; Paiements</a></p>';

$html = email_html_layout('Paiement à vérifier — ' . $reference, 'Paiement PayPal à vérifier', $body, ['accent' => 'amber']);
$text = "Paiement PayPal à vérifier\n\n";
foreach ($rows as $label => $value) {
    $text .= $label . ' : ' . $value . "\n";
}
$text .= "\nActiver la formule : " . $adminUrl . "\n";

return ['html' => $html, 'text' => $text];
