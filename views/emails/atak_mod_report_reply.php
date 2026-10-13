<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $reportLabel */
/** @var string $replyText */
/** @var string $activityUrl */

$e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$body = '<p>Bonjour ' . $e((string) $displayName) . ',</p>'
    . '<p>L’équipe COMSPEC a lu le problème que vous avez signalé depuis le jeu : ' . $e((string) $reportLabel) . '.</p>'
    . '<p style="margin:16px 0;padding:12px 14px;background:#f3f5f3;border-left:3px solid #12d18e;white-space:pre-wrap;">'
    . $e((string) $replyText) . '</p>'
    . '<p style="margin:16px 0 0;">Si le problème revient, utilisez à nouveau Échap &gt; Signaler un problème dans le jeu.</p>'
    . email_html_button($activityUrl, 'Ouvrir mon activité', 'emerald')
    . email_html_url_fallback($activityUrl);

$html = email_html_layout(
    'Réponse à votre signalement',
    'Mod COMSPEC ATAK',
    $body,
    ['accent' => 'emerald']
);

$text = "Bonjour {$displayName},\n\n"
    . "L’équipe COMSPEC a lu le problème que vous avez signalé depuis le jeu : {$reportLabel}.\n\n"
    . $replyText . "\n\n"
    . "Si le problème revient, utilisez à nouveau Échap > Signaler un problème dans le jeu.\n\n"
    . "Mon activité : {$activityUrl}\n";

return ['html' => $html, 'text' => $text];
