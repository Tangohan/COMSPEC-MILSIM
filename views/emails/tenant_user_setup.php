<?php

declare(strict_types=1);

/** @var string $setupUrl */
/** @var int $hoursValid */
/** @var string $tenantName */
/** @var string $inviteSource admin_created|recruitment_accepted */

$tn = htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8');
$source = isset($inviteSource) ? (string) $inviteSource : 'admin_created';
$isRecruitment = $source === 'recruitment_accepted';

if ($isRecruitment) {
    $body = '<p>Bonjour,</p>'
        . '<p>Votre <strong>candidature</strong> pour la communauté <strong>' . $tn . '</strong> a été <strong>acceptée</strong>.</p>'
        . '<p>Un <strong>premier accès</strong> à l’espace membre a été ouvert pour votre adresse e-mail. Ce n’est pas la « fin » d’un ancien compte : il s’agit de <strong>choisir un mot de passe pour ce nouvel accès</strong> (comme pour un premier compte sur cette communauté).</p>'
        . '<p>Cliquez sur le bouton pour <strong>définir ce mot de passe</strong>. Le lien est valable <strong>' . (int) $hoursValid . ' heure(s)</strong>.</p>'
        . email_html_button($setupUrl, 'Choisir mon mot de passe', 'blue')
        . email_html_url_fallback($setupUrl)
        . '<p style="margin-top:24px;font-size:14px;color:#64748b;">Si vous n’attendiez pas ce message, vous pouvez l’ignorer.</p>';

    $html = email_html_layout(
        'Premier accès — lien ' . (int) $hoursValid . ' h — ' . $tenantName,
        'Premier accès à la communauté',
        $body,
        ['accent' => 'blue']
    );

    $text = "Bonjour,\n\nVotre candidature pour la communauté « {$tenantName} » a été acceptée.\n\n"
        . "Un premier accès a été ouvert pour votre adresse : définissez votre mot de passe (lien valide {$hoursValid} h) :\n\n"
        . $setupUrl . "\n\nSi vous n’attendiez pas ce message, ignorez-le.\n";
} else {
    $body = '<p>Bonjour,</p>'
        . '<p>Un administrateur de la communauté <strong>' . $tn . '</strong> a créé un compte pour votre adresse e-mail sur <strong>' . htmlspecialchars(email_brand_name(), ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
        . '<p>Cliquez sur le bouton pour <strong>définir votre mot de passe</strong> et activer l’accès. Ce lien est valable <strong>' . (int) $hoursValid . ' heure(s)</strong>.</p>'
        . email_html_button($setupUrl, 'Définir mon mot de passe', 'blue')
        . email_html_url_fallback($setupUrl)
        . '<p style="margin-top:24px;font-size:14px;color:#64748b;">Si vous n’attendiez pas ce message, vous pouvez l’ignorer.</p>';

    $html = email_html_layout(
        'Activez votre compte — lien ' . (int) $hoursValid . ' h',
        'Votre compte est prêt',
        $body,
        ['accent' => 'blue']
    );

    $text = 'Bonjour,' . "\n\nUn administrateur de la communauté « {$tenantName} » a créé un compte pour votre adresse e-mail sur " . email_brand_name() . ".\n\n"
        . "Définissez votre mot de passe pour activer le compte (lien valide {$hoursValid} h) :\n\n"
        . $setupUrl . "\n\nSi vous n’attendiez pas ce message, ignorez-le.\n";
}

return ['html' => $html, 'text' => $text];
