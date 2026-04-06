<?php

declare(strict_types=1);

/** @var string $tenantName */
/** @var string|null $reviewerComment */
/** @var string $dashboardUrl */
/** @var string $accountScenario existing|new_password_pending */

$tn = htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8');
$scenario = isset($accountScenario) ? (string) $accountScenario : 'existing';
$isExisting = $scenario === 'existing';

$comment = $reviewerComment !== null && trim($reviewerComment) !== ''
    ? nl2br(htmlspecialchars(trim($reviewerComment), ENT_QUOTES, 'UTF-8'))
    : null;

if ($isExisting) {
    $intro = '<p>Votre candidature pour <strong>' . $tn . '</strong> a été <strong>acceptée</strong>.</p>'
        . email_html_callout(
            'Vous aviez <strong>déjà un compte</strong> pour cette communauté : connectez-vous avec la <strong>même adresse e-mail</strong> et votre <strong>mot de passe habituel</strong>. Aucun nouveau mot de passe n’est demandé par ce message.',
            'success'
        );
    $afterCta = '<p style="margin-top:12px;font-size:14px;color:#64748b;">Si vous ne vous souvenez plus du mot de passe, utilisez « Mot de passe oublié » sur la page de connexion.</p>';
} else {
    $intro = '<p>Votre candidature pour <strong>' . $tn . '</strong> a été <strong>acceptée</strong>.</p>'
        . email_html_callout(
            'Un <strong>autre e-mail</strong> vous a été envoyé (objet du type « Premier accès ») : il contient le lien pour <strong>choisir le mot de passe</strong> du compte qui vient d’être <strong>créé pour vous</strong> sur cette communauté. Ce n’est pas un compte que vous utilisiez déjà ailleurs : c’est la <strong>première connexion</strong> à cet espace.',
            'info'
        );
    $afterCta = '';
}

$commentBlock = $comment !== null
    ? '<p style="margin:16px 0 8px;font-size:13px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;">Message du recrutement</p>'
        . '<div style="padding:14px 16px;background-color:#f0fdf4;border-radius:10px;border:1px solid #bbf7d0;font-size:15px;line-height:1.55;color:#14532d;">' . $comment . '</div>'
    : '';

$preheader = $isExisting
    ? 'Candidature acceptée — connectez-vous avec votre compte — ' . $tenantName
    : 'Candidature acceptée — voir l’e-mail « Premier accès » — ' . $tenantName;

$body = '<p>Bonjour,</p>'
    . $intro
    . $commentBlock
    . '<p style="margin-top:20px;">Accéder à l’espace membre :</p>'
    . email_html_button($dashboardUrl, 'Ouvrir le portail', 'emerald')
    . email_html_url_fallback($dashboardUrl)
    . $afterCta;

$html = email_html_layout(
    $preheader,
    'Candidature acceptée',
    $body,
    ['accent' => 'emerald']
);

$text = "Bonjour,\n\nVotre candidature pour « {$tenantName} » a été acceptée.\n\n";
if ($isExisting) {
    $text .= "Vous aviez déjà un compte pour cette communauté : connectez-vous avec votre adresse e-mail et votre mot de passe habituels.\n\n";
} else {
    $text .= "Un autre message contient le lien pour définir le mot de passe du nouveau compte créé pour vous sur cette communauté.\n\n";
}
if ($reviewerComment !== null && trim($reviewerComment) !== '') {
    $text .= "Message du recrutement :\n" . trim($reviewerComment) . "\n\n";
}
$text .= "Portail : {$dashboardUrl}\n";

return ['html' => $html, 'text' => $text];
