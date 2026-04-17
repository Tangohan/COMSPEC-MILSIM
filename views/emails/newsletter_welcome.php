<?php

declare(strict_types=1);

$unsubscribeUrl = (string) ($unsubscribeUrl ?? '#');
$brand = email_brand_name();
$brandEsc = htmlspecialchars($brand, ENT_QUOTES, 'UTF-8');

$tipsHtml = '<ul style="margin:0 0 16px;padding-left:22px;">'
    . '<li style="margin:6px 0;">nouveautés produit et informations pratiques</li>'
    . '<li style="margin:6px 0;">conseils et contenus pour vous accompagner au quotidien</li>'
    . '</ul>';

$body = '<p>Bonjour,</p>'
    . '<p>Votre inscription aux actualités <strong>' . $brandEsc . '</strong> est bien enregistrée. Merci de votre confiance.</p>'
    . email_html_callout(
        '<strong>Prochaine étape.</strong> Vous recevrez nos prochains messages à cette adresse. Chaque e-mail inclut un lien simple pour vous désinscrire si vous le souhaitez.',
        'success'
    )
    . '<p style="margin:0 0 8px;font-weight:600;color:#0f172a;">À quoi s’attendre ?</p>'
    . $tipsHtml
    . '<p style="margin-top:20px;font-size:14px;line-height:1.6;color:#64748b;">Vous changez d’avis ? '
    . '<a href="' . htmlspecialchars($unsubscribeUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#2563eb;font-weight:600;">Se désinscrire</a> en un clic — le même lien figure aussi en bas de chaque message que nous envoyons.</p>'
    . email_html_url_fallback($unsubscribeUrl);

$preheader = 'Merci — vous recevrez bientôt nos actualités';
$html = email_html_layout(
    $preheader,
    'Bienvenue parmi nos lectrices et lecteurs',
    $body,
    [
        'accent' => 'emerald',
        'footer_note' => 'Ce message confirme votre inscription. Conservez-le si vous le souhaitez comme référence.',
    ]
);

$text = "Bonjour,\n\n"
    . "Votre inscription aux actualités « {$brand} » est confirmée.\n\n"
    . "Vous recevrez bientôt nos messages à cette adresse.\n"
    . "Chaque e-mail contient un lien pour vous désinscrire en un clic.\n\n"
    . "Lien de désabonnement : {$unsubscribeUrl}\n";

return ['html' => $html, 'text' => $text];
