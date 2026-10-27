<?php

declare(strict_types=1);

/** @var bool $activated */
/** @var string $state activated|rejected|cancelled|refunded|ended|reminder|renewal|extended (absent : déduit de $activated) */
/** @var string $reference */
/** @var string $description */
/** @var string $amount */
/** @var string $endsAt */
/** @var string $adminNote */
/** @var string $pageUrl */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$state = isset($state) && is_string($state) && $state !== '' ? $state : ($activated ? 'activated' : 'rejected');
[$title, $lead, $accent] = match ($state) {
    'activated' => ['Paiement confirmé', 'Merci ! Votre paiement a bien été reçu et votre achat est actif.', 'emerald'],
    'cancelled' => ['Demande de paiement annulée', 'L’équipe Athena a annulé cette demande de paiement. Si vous avez déjà payé, répondez à ce message avec votre reçu PayPal.', 'slate'],
    'refunded' => ['Paiement remboursé', 'Votre paiement vous a été remboursé sur PayPal. Le remboursement peut mettre quelques jours à apparaître sur votre compte.', 'amber'],
    'ended' => ['Formule arrêtée', 'L’équipe Athena a arrêté la formule payée de votre communauté : elle revient à la formule gratuite.', 'rose'],
    'reminder' => ['Votre paiement n’est pas finalisé', 'Vous avez commencé un paiement sur Athena sans le terminer. Il suffit d’ouvrir la demande, de payer avec PayPal en indiquant la référence, puis de coller l’identifiant de transaction.', 'amber'],
    'renewal' => ['Renouvellement de votre formule', 'Votre formule arrive à son terme. Une demande de renouvellement est prête : ouvrez-la pour payer la période suivante avec PayPal.', 'emerald'],
    'extended' => ['Formule prolongée', 'L’équipe Athena a prolongé la formule de votre communauté, sans paiement supplémentaire.', 'emerald'],
    default => ['Paiement non confirmé', 'Nous n’avons pas pu rapprocher votre paiement de cette demande. Répondez à ce message ou contactez l’équipe Athena avec votre reçu PayPal.', 'rose'],
};
$activated = $state === 'activated';
$showEnd = in_array($state, ['activated', 'extended', 'renewal'], true) && $endsAt !== '';
$endLabel = $state === 'renewal' ? 'Formule actuelle jusqu’au' : 'Active jusqu’au';
$cta = in_array($state, ['reminder', 'renewal'], true) ? 'Payer maintenant' : 'Voir la demande de paiement';

$body = '<p>' . $escape($lead) . '</p>'
    . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="font-size:15px;margin-top:8px;">'
    . '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#64748b;width:36%;">Référence</td><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;font-weight:600;">' . $escape($reference) . '</td></tr>'
    . '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#64748b;">Achat</td><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;">' . $escape($description) . '</td></tr>'
    . '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#64748b;">Montant</td><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;">' . $escape($amount) . '</td></tr>'
    . ($showEnd ? '<tr><td style="padding:8px 0;color:#64748b;">' . $escape($endLabel) . '</td><td style="padding:8px 0;font-weight:600;">' . $escape($endsAt) . '</td></tr>' : '')
    . '</table>'
    . ($adminNote !== '' ? '<p style="margin-top:16px;"><strong>Message de l’équipe :</strong> ' . $escape($adminNote) . '</p>' : '')
    . '<p style="margin-top:20px;"><a href="' . $escape($pageUrl) . '" style="color:#047857;font-weight:700;">' . $escape($cta) . '</a></p>';

$html = email_html_layout($title . ' — ' . $reference, $title, $body, ['accent' => $accent]);
$text = $title . "\n\n" . $lead . "\n\n"
    . 'Référence : ' . $reference . "\n"
    . 'Achat : ' . $description . "\n"
    . 'Montant : ' . $amount . "\n"
    . ($showEnd ? $endLabel . ' : ' . $endsAt . "\n" : '')
    . ($adminNote !== '' ? "\nMessage de l’équipe : " . $adminNote . "\n" : '')
    . "\n" . $pageUrl . "\n";

return ['html' => $html, 'text' => $text];
