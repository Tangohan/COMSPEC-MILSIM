<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $problemLabel */
/** @var string $reasonText */
/** @var list<string> $steps */
/** @var string $versions */
/** @var string $helpUrl */

$e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$steps = is_array($steps ?? null) ? array_values($steps) : [];

$list = '';
foreach ($steps as $step) {
    $list .= '<li style="margin:0 0 6px;">' . $e((string) $step) . '</li>';
}

$body = '<p>Bonjour ' . $e((string) $displayName) . ',</p>'
    . '<p>Pendant votre dernière partie, Athena a refusé les envois de votre jeu (' . $e((string) $problemLabel) . ') : '
    . 'votre position, votre fiche et vos photos n’arrivent plus au poste.</p>'
    . '<p style="margin:16px 0;padding:12px 14px;background:#f3f5f3;border-left:3px solid #e0a100;">'
    . $e((string) $reasonText) . '</p>'
    . '<p style="margin:16px 0 6px;"><strong>Pour corriger :</strong></p>'
    . '<ol style="margin:0 0 16px 18px;padding:0;">' . $list . '</ol>'
    . '<p style="margin:0 0 16px;color:#5b6470;font-size:13px;">Versions vues : ' . $e((string) $versions) . '</p>'
    . email_html_button($helpUrl, 'Guide de connexion du jeu', 'emerald')
    . email_html_url_fallback($helpUrl)
    . '<p style="margin:16px 0 0;color:#5b6470;font-size:13px;">Message automatique, envoyé au plus une fois par jour. '
    . 'Si le problème continue, utilisez Échap &gt; Signaler un problème dans le jeu.</p>';

$html = email_html_layout(
    'Athena a refusé la connexion de votre jeu',
    'Mod COMSPEC ATAK',
    $body,
    ['accent' => 'emerald']
);

$text = "Bonjour {$displayName},\n\n"
    . "Pendant votre dernière partie, Athena a refusé les envois de votre jeu ({$problemLabel}) : "
    . "votre position, votre fiche et vos photos n’arrivent plus au poste.\n\n"
    . $reasonText . "\n\n"
    . "Pour corriger :\n";
foreach ($steps as $i => $step) {
    $text .= ($i + 1) . '. ' . $step . "\n";
}
$text .= "\nVersions vues : {$versions}\n"
    . "Guide de connexion du jeu : {$helpUrl}\n\n"
    . "Message automatique, envoyé au plus une fois par jour. Si le problème continue, utilisez Échap > Signaler un problème dans le jeu.\n";

return ['html' => $html, 'text' => $text];
