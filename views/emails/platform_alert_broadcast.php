<?php

declare(strict_types=1);

/** @var string $title */
/** @var string $body */
/** @var string|null $ctaLabel */
/** @var string|null $ctaUrl */

$titleSafe = htmlspecialchars((string) ($title ?? 'Annonce Athena'), ENT_QUOTES, 'UTF-8');
$bodyRaw = trim((string) ($body ?? ''));
$bodyHtml = $bodyRaw !== ''
    ? '<div style="padding:16px 18px;background-color:#f8fafc;border-radius:10px;border:1px solid #e2e8f0;font-size:15px;line-height:1.65;color:#334155;">'
        . nl2br(htmlspecialchars($bodyRaw, ENT_QUOTES, 'UTF-8'))
        . '</div>'
    : '';

$ctaLabel = trim((string) ($ctaLabel ?? ''));
$ctaUrl = trim((string) ($ctaUrl ?? ''));
$ctaBlock = '';
if ($ctaLabel !== '' && $ctaUrl !== '') {
    $ctaBlock = '<p style="margin:20px 0 0;">'
        . '<a href="' . htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;padding:12px 18px;border-radius:10px;background:#059669;color:#fff;font-weight:700;text-decoration:none;">'
        . htmlspecialchars($ctaLabel, ENT_QUOTES, 'UTF-8')
        . '</a></p>';
}

$html = email_html_layout(
    $titleSafe,
    (string) ($title ?? 'Annonce Athena'),
    $bodyHtml . $ctaBlock,
    [
        'accent' => 'emerald',
        'footer_note' => 'Vous recevez ce message car vous avez un compte actif sur le portail Athena. Connectez-vous pour voir les annonces en bandeau.',
    ]
);

$text = (string) ($title ?? 'Annonce Athena') . "\n\n" . $bodyRaw;
if ($ctaLabel !== '' && $ctaUrl !== '') {
    $text .= "\n\n" . $ctaLabel . ' : ' . $ctaUrl;
}
$text .= "\n\n— " . email_brand_name();

return ['html' => $html, 'text' => $text];
