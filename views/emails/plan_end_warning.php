<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $tenantName */
/** @var string $planName */
/** @var string $kind gift|trial|paid */
/** @var string $endsAt */
/** @var bool $atakLost */
/** @var int $graceDays */
/** @var string $plansUrl */
/** @var string $lang fr|en */

use App\Support\PlanAccessWindow;
use App\Support\PlanGift;

$lang = PlanGift::locale((string) ($lang ?? 'fr'));
$copy = PlanAccessWindow::copy($lang, (string) $tenantName, (string) $planName, (string) $kind, (string) $endsAt, !empty($atakLost), (int) ($graceDays ?? 0));
$e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$colon = $lang === 'en' ? ': ' : ' : ';
$hello = $copy['hello'] . ($displayName !== '' ? ' ' . $displayName : '') . ',';

// Versions française et anglaise déjà rédigées : translate="no" pour que la traduction automatique n'y touche pas.
$body = '<div translate="no">'
    . '<p>' . $e($hello) . '</p>'
    . '<p><strong>' . $e($copy['lead']) . '</strong></p>'
    . '<p>' . $e($copy['lost']) . '</p>';
if ($copy['grace'] !== '') {
    $body .= '<p>' . $e($copy['grace']) . '</p>';
}
$body .= email_html_button($plansUrl, $copy['cta'], 'emerald')
    . '</div>'
    . email_html_url_fallback($plansUrl);

$html = email_html_layout(
    $copy['lead'],
    $copy['title'],
    $body,
    ['accent' => 'emerald', 'kicker' => $copy['kicker']]
);

$text = $hello . "\n\n"
    . $copy['lead'] . "\n"
    . $copy['lost'] . "\n"
    . ($copy['grace'] !== '' ? $copy['grace'] . "\n" : '')
    . "\n" . $copy['cta'] . $colon . $plansUrl . "\n";

return ['html' => $html, 'text' => $text];
