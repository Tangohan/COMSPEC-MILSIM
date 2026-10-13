<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $tenantName */
/** @var string $planName */
/** @var string|null $endsAt */
/** @var string $adminMessage */
/** @var string $manageUrl */
/** @var string $lang fr|en */

use App\Support\PlanGift;

$copy = PlanGift::copy((string) ($lang ?? 'fr'), (string) $tenantName, (string) $planName, $endsAt ?? null);
$e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$message = trim((string) ($adminMessage ?? ''));
$colon = PlanGift::locale((string) ($lang ?? 'fr')) === 'en' ? ': ' : ' : ';
$hello = $copy['hello'] . ($displayName !== '' ? ' ' . $displayName : '') . ',';

// Version anglaise déjà rédigée : translate="no" pour que la traduction automatique des e-mails n'y touche pas.
$body = '<div translate="no">'
    . '<p>' . $e($hello) . '</p>'
    . '<p><strong>' . $e($copy['lead']) . '</strong></p>'
    . '<p>' . $e($copy['duration']) . '</p>'
    . '<p>' . $e($copy['immediate']) . '</p>';
if ($message !== '') {
    $body .= '<p style="margin:18px 0 6px;font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:#667078;">' . $e($copy['message_label']) . '</p>'
        . '<blockquote style="margin:0;padding:12px 14px;border-left:3px solid #161616;background:#f6f6f6;">' . nl2br($e($message)) . '</blockquote>';
}
$body .= email_html_button($manageUrl, $copy['cta'], 'emerald')
    . '</div>'
    . email_html_url_fallback($manageUrl);

$html = email_html_layout(
    $copy['lead'],
    $copy['title'],
    $body,
    ['accent' => 'emerald', 'kicker' => $copy['kicker']]
);

$text = $hello . "\n\n"
    . $copy['lead'] . "\n"
    . $copy['duration'] . "\n"
    . $copy['immediate'] . "\n";
if ($message !== '') {
    $text .= "\n" . $copy['message_label'] . rtrim($colon) . "\n" . $message . "\n";
}
$text .= "\n" . $copy['cta'] . $colon . $manageUrl . "\n";

return ['html' => $html, 'text' => $text];
