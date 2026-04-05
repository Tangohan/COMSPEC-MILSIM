<?php

declare(strict_types=1);

/** @var string $level */
/** @var string $title */
/** @var string $body */

$lv = htmlspecialchars($level, ENT_QUOTES, 'UTF-8');
$html = '<p><strong>[' . $lv . '] ' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</strong></p>'
    . '<p>' . nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')) . '</p>';

$text = "[{$level}] {$title}\n\n{$body}\n";

return ['html' => $html, 'text' => $text];
