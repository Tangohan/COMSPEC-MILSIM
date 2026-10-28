<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $reportLabel */
/** @var string $replyText */
/** @var string $activityUrl */
/** @var bool $auto erreur envoyée automatiquement par le mod (pas un signalement écrit par le joueur) */
/** @var string $lang fr|en, langue du profil Athena du joueur */

$e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$auto = (bool) ($auto ?? false);
$en = ($lang ?? 'fr') === 'en';

if ($en) {
    $hello = 'Hello ' . $displayName . ',';
    $intro = $auto
        ? 'During your game, your COMSPEC ATAK mod detected an error and sent it to us automatically: ' . $reportLabel . '. You did not have to report anything. Here is what the COMSPEC team says about it:'
        : 'The COMSPEC team has read the problem you reported from the game: ' . $reportLabel . '.';
    $outro = $auto
        ? 'If you notice a problem in game, use Esc > Report a problem.'
        : 'If the problem comes back, use Esc > Report a problem in game again.';
    $button = 'Open my activity';
    $heading = $auto ? 'Error detected by your mod' : 'Reply to your report';
    $activityLabel = 'My activity';
} else {
    $hello = 'Bonjour ' . $displayName . ',';
    $intro = $auto
        ? 'Pendant votre partie, votre mod COMSPEC ATAK a détecté une erreur et nous l’a envoyée automatiquement : ' . $reportLabel . '. Vous n’aviez rien à signaler. Voici ce qu’en dit l’équipe COMSPEC :'
        : 'L’équipe COMSPEC a lu le problème que vous avez signalé depuis le jeu : ' . $reportLabel . '.';
    $outro = $auto
        ? 'Si vous remarquez un problème en jeu, utilisez Échap > Signaler un problème.'
        : 'Si le problème revient, utilisez à nouveau Échap > Signaler un problème dans le jeu.';
    $button = 'Ouvrir mon activité';
    $heading = $auto ? 'Erreur détectée par votre mod' : 'Réponse à votre signalement';
    $activityLabel = 'Mon activité';
}

$body = '<p>' . $e($hello) . '</p>'
    . '<p>' . $e($intro) . '</p>'
    . '<p style="margin:16px 0;padding:12px 14px;background:#f3f5f3;border-left:3px solid #12d18e;white-space:pre-wrap;">'
    . $e((string) $replyText) . '</p>'
    . '<p style="margin:16px 0 0;">' . $e($outro) . '</p>'
    . email_html_button($activityUrl, $button, 'emerald')
    . email_html_url_fallback($activityUrl);

$html = email_html_layout(
    $heading,
    'Mod COMSPEC ATAK',
    $body,
    ['accent' => 'emerald']
);

$text = $hello . "\n\n"
    . $intro . "\n\n"
    . $replyText . "\n\n"
    . $outro . "\n\n"
    . $activityLabel . ($en ? ': ' : ' : ') . $activityUrl . "\n";

return ['html' => $html, 'text' => $text];
