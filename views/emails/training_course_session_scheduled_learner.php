<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $tenantName */
/** @var string $courseTitle */
/** @var string $courseUrl */
/** @var string $periodLine */
/** @var string|null $sessionLabel */
/** @var string|null $sessionLocation */
/** @var string $myTrainingUrl */

$name = htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8');
$tn = htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8');
$ct = htmlspecialchars((string) $courseTitle, ENT_QUOTES, 'UTF-8');
$cUrl = htmlspecialchars((string) $courseUrl, ENT_QUOTES, 'UTF-8');
$pl = htmlspecialchars((string) $periodLine, ENT_QUOTES, 'UTF-8');
$mUrl = htmlspecialchars((string) $myTrainingUrl, ENT_QUOTES, 'UTF-8');

$extra = '';
if (is_string($sessionLabel) && $sessionLabel !== '') {
    $extra .= '<p><strong>Précision :</strong> ' . htmlspecialchars($sessionLabel, ENT_QUOTES, 'UTF-8') . '</p>';
}
if (is_string($sessionLocation) && $sessionLocation !== '') {
    $extra .= '<p><strong>Lieu :</strong> ' . htmlspecialchars($sessionLocation, ENT_QUOTES, 'UTF-8') . '</p>';
}

$body = '<p>Bonjour ' . $name . ',</p>'
    . '<p>Un nouveau créneau a été planifié pour la formation <strong>' . $ct . '</strong> sur <strong>' . $tn . '</strong>.</p>';
if ($pl !== '') {
    $body .= '<p><strong>Période :</strong> ' . $pl . '</p>';
}
$body .= $extra
    . '<p>Vous êtes inscrit sur ce parcours : vous pouvez consulter le détail et la suite des leçons depuis votre espace.</p>'
    . email_html_button($courseUrl, 'Ouvrir la formation', 'emerald')
    . email_html_url_fallback($courseUrl)
    . '<p style="margin-top:20px;font-size:14px;color:#64748b;">Toutes vos formations : « Mes formations ».</p>'
    . email_html_button($myTrainingUrl, 'Mes formations', 'slate')
    . email_html_url_fallback($myTrainingUrl);

$html = email_html_layout(
    'Créneau planifié — ' . $courseTitle,
    'Formation — nouveau créneau',
    $body,
    ['accent' => 'emerald']
);

$textLines = [
    "Bonjour {$displayName},",
    '',
    "Un nouveau créneau a été planifié pour la formation « {$courseTitle} » sur « {$tenantName} ».",
];
if ($periodLine !== '') {
    $textLines[] = "Période : {$periodLine}";
}
if (is_string($sessionLabel) && $sessionLabel !== '') {
    $textLines[] = 'Précision : ' . $sessionLabel;
}
if (is_string($sessionLocation) && $sessionLocation !== '') {
    $textLines[] = 'Lieu : ' . $sessionLocation;
}
$textLines[] = '';
$textLines[] = 'Ouvrir la formation : ' . $courseUrl;
$textLines[] = 'Mes formations : ' . $myTrainingUrl;

$text = implode("\n", $textLines);

return ['html' => $html, 'text' => $text];
