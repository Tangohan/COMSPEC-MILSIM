<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $problemLabel */
/** @var string $reasonText */
/** @var list<string> $steps */
/** @var string $versions */
/** @var string $helpUrl */
/** @var string $lang fr|en, langue du profil Athena du joueur */

$e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$steps = is_array($steps ?? null) ? array_values($steps) : [];
$en = ($lang ?? 'fr') === 'en';

$t = $en
    ? [
        'hello' => 'Hello ' . $displayName . ',',
        'intro' => 'During your last game, your COMSPEC ATAK mod detected that Athena refused what your game was sending (' . $problemLabel . '): '
            . 'your position, your operator card and your photos no longer reach the command post.',
        'fix' => 'To fix it:',
        'versions' => 'Versions seen: ',
        'button' => 'Game connection guide',
        'footer' => 'Automatic message, sent at most once a day. If the problem continues, use Esc > Report a problem in game.',
        'heading' => 'Athena refused your game connection',
    ]
    : [
        'hello' => 'Bonjour ' . $displayName . ',',
        'intro' => 'Pendant votre dernière partie, votre mod COMSPEC ATAK a détecté qu’Athena refusait les envois de votre jeu (' . $problemLabel . ') : '
            . 'votre position, votre fiche et vos photos n’arrivent plus au poste.',
        'fix' => 'Pour corriger :',
        'versions' => 'Versions vues : ',
        'button' => 'Guide de connexion du jeu',
        'footer' => 'Message automatique, envoyé au plus une fois par jour. Si le problème continue, utilisez Échap > Signaler un problème dans le jeu.',
        'heading' => 'Athena a refusé la connexion de votre jeu',
    ];

$list = '';
foreach ($steps as $step) {
    $list .= '<li style="margin:0 0 6px;">' . $e((string) $step) . '</li>';
}

$body = '<p>' . $e($t['hello']) . '</p>'
    . '<p>' . $e($t['intro']) . '</p>'
    . '<p style="margin:16px 0;padding:12px 14px;background:#f3f5f3;border-left:3px solid #e0a100;">'
    . $e((string) $reasonText) . '</p>'
    . '<p style="margin:16px 0 6px;"><strong>' . $e($t['fix']) . '</strong></p>'
    . '<ol style="margin:0 0 16px 18px;padding:0;">' . $list . '</ol>'
    . '<p style="margin:0 0 16px;color:#5b6470;font-size:13px;">' . $e($t['versions'] . $versions) . '</p>'
    . email_html_button($helpUrl, $t['button'], 'emerald')
    . email_html_url_fallback($helpUrl)
    . '<p style="margin:16px 0 0;color:#5b6470;font-size:13px;">' . $e($t['footer']) . '</p>';

$html = email_html_layout(
    $t['heading'],
    'Mod COMSPEC ATAK',
    $body,
    ['accent' => 'emerald']
);

$text = $t['hello'] . "\n\n"
    . $t['intro'] . "\n\n"
    . $reasonText . "\n\n"
    . $t['fix'] . "\n";
foreach ($steps as $i => $step) {
    $text .= ($i + 1) . '. ' . $step . "\n";
}
$text .= "\n" . $t['versions'] . $versions . "\n"
    . $t['button'] . ($en ? ': ' : ' : ') . $helpUrl . "\n\n"
    . $t['footer'] . "\n";

return ['html' => $html, 'text' => $text];
