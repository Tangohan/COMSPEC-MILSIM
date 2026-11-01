<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $tenantName */
/** @var int $members */
/** @var string $stage m3|m4 */
/** @var int $deletionFrom horodatage UTC */
/** @var array<string, string> $links invitations, new_member, invite_codes, public_page */
/** @var string $lang fr|en */

use App\Support\LowMemberOrgPolicy;

$lang = LowMemberOrgPolicy::locale((string) ($lang ?? 'fr'));
$copy = LowMemberOrgPolicy::copy($lang, (string) $stage, (string) $tenantName, (int) $members, (int) $deletionFrom);
$links = is_array($links ?? null) ? $links : [];
$e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$colon = $lang === 'en' ? ': ' : ' : ';
$hello = $copy['hello'] . ($displayName !== '' ? ' ' . $displayName : '') . ',';
$cta = (string) ($links['invitations'] ?? url('back-office/invitations'));

$tipsHtml = '';
$tipsText = '';
foreach ($copy['tips'] as $tip) {
    $href = (string) ($links[$tip['key']] ?? '');
    $tipsHtml .= '<li style="margin:0 0 6px;">' . ($href !== ''
        ? '<a href="' . $e($href) . '" style="color:#000091;">' . $e($tip['label']) . '</a>'
        : $e($tip['label'])) . '</li>';
    $tipsText .= '- ' . $tip['label'] . ($href !== '' ? "\n  " . $href : '') . "\n";
}

// Versions française et anglaise déjà rédigées : translate="no" pour que la traduction automatique n'y touche pas.
$body = '<div translate="no">'
    . '<p>' . $e($hello) . '</p>'
    . '<p><strong>' . $e($copy['lead']) . '</strong></p>'
    . '<p>' . $e($copy['tips_title']) . '</p>'
    . '<ul style="margin:0 0 16px 18px;padding:0;">' . $tipsHtml . '</ul>'
    . '<p style="padding:12px 14px;border-left:4px solid #b34000;background:#fff4ed;">' . $e($copy['warning']) . '</p>'
    . email_html_button($cta, $copy['cta'], $stage === LowMemberOrgPolicy::STAGE_SECOND ? 'amber' : 'emerald')
    . '</div>'
    . email_html_url_fallback($cta);

$html = email_html_layout(
    $copy['lead'],
    $copy['title'],
    $body,
    ['accent' => $stage === LowMemberOrgPolicy::STAGE_SECOND ? 'amber' : 'emerald', 'kicker' => $copy['kicker']]
);

$text = $hello . "\n\n"
    . $copy['lead'] . "\n\n"
    . $copy['tips_title'] . "\n"
    . $tipsText . "\n"
    . $copy['warning'] . "\n\n"
    . $copy['cta'] . $colon . $cta . "\n";

return ['html' => $html, 'text' => $text];
