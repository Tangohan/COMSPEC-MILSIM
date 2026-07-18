<?php

declare(strict_types=1);

$unsubscribeUrl = (string) ($unsubscribeUrl ?? '#');
$brand = email_brand_name();
$brandEsc = htmlspecialchars($brand, ENT_QUOTES, 'UTF-8');

$tipsHtml = '<ul style="margin:0 0 16px;padding-left:22px;">'
    . '<li style="margin:6px 0;">évolutions du portail et nouveaux modules tactiques</li>'
    . '<li style="margin:6px 0;">guides d’installation Arma&nbsp;3 et conseils pour les communautés MILSIM</li>'
    . '<li style="margin:6px 0;">rappels utiles avant les mises à jour importantes</li>'
    . '</ul>';

$body = '<p>Bonjour,</p>'
    . '<p>Votre inscription aux nouveautés <strong>' . $brandEsc . '</strong> est confirmée. Merci de votre confiance.</p>'
    . email_html_callout(
        '<strong>Prochaine étape.</strong> Les prochains messages arriveront à cette adresse. Chaque envoi contient un lien pour vous désabonner en un clic.',
        'success'
    )
    . '<p style="margin:0 0 8px;font-weight:600;color:#0f172a;">Ce que vous recevrez</p>'
    . $tipsHtml
    . '<p style="margin-top:20px;font-size:14px;line-height:1.6;color:#64748b;">Vous changez d’avis&nbsp;? '
    . '<a href="' . htmlspecialchars($unsubscribeUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#2563eb;font-weight:600;">Se désabonner</a> dès maintenant — le même lien figure aussi en bas de chaque message.</p>'
    . email_html_url_fallback($unsubscribeUrl);

$preheader = 'Inscription confirmée — nouveautés Athena et guides MILSIM';
$html = email_html_layout(
    $preheader,
    'Bienvenue dans les communications Athena',
    $body,
    [
        'accent' => 'emerald',
        'footer_note' => 'Ce message confirme votre inscription aux communications Athena.',
    ]
);

$text = "Bonjour,\n\n"
    . "Votre inscription aux nouveautés « {$brand} » est confirmée.\n\n"
    . "Vous recevrez notamment :\n"
    . "- les évolutions du portail et les nouveaux modules tactiques ;\n"
    . "- des guides d’installation Arma 3 et des conseils pour les communautés MILSIM ;\n"
    . "- des rappels utiles avant les mises à jour importantes.\n\n"
    . "Chaque e-mail contient un lien pour vous désabonner en un clic.\n\n"
    . "Lien de désabonnement : {$unsubscribeUrl}\n";

return ['html' => $html, 'text' => $text];
