<?php

declare(strict_types=1);

/** @var string $level */
/** @var string $title */
/** @var string $body */

$lv = htmlspecialchars($level, ENT_QUOTES, 'UTF-8');
$bodyHtml = '<p style="margin:0 0 12px;"><span style="display:inline-block;padding:4px 10px;border-radius:6px;background-color:#fef2f2;color:#991b1b;font-size:12px;font-weight:700;letter-spacing:0.04em;">' . $lv . '</span></p>'
    . '<div style="padding:16px 18px;background-color:#fffbeb;border-radius:10px;border:1px solid #fde68a;font-size:15px;line-height:1.65;color:#334155;">'
    . nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'))
    . '</div>';

$html = email_html_layout(
    $title . ' — ' . $level,
    $title,
    $bodyHtml,
    ['accent' => 'rose', 'footer_note' => 'En cas de doute, changez votre mot de passe et contactez un administrateur.']
);

$text = "[{$level}] {$title}\n\n{$body}\n";

return ['html' => $html, 'text' => $text];
