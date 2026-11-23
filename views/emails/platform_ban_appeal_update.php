<?php

declare(strict_types=1);

/** @var string $updateKind received|lift|uphold */
/** @var string $appealUrl */
/** @var string|null $reference */
/** @var string|null $appealLanguage */
$language = ($appealLanguage ?? 'fr') === 'en' ? 'en' : 'fr';
$kind = in_array($updateKind ?? '', ['lift', 'uphold', 'reply', 'info', 'closed'], true) ? $updateKind : 'received';
$reference = trim((string) ($reference ?? ''));
$translate = static fn (string $key): string => __($key, [], $language);
$escape = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
$intro = $translate('moderation.mail_update_intro_' . $kind);
$referenceLine = $reference !== '' ? $translate('moderation.case_reference') . ' ' . $reference : '';
$body = ($referenceLine !== '' ? '<p><strong>' . $escape($referenceLine) . '</strong></p>' : '') . '<p>' . $escape($intro) . '</p>'
    . email_html_button($appealUrl, $translate('moderation.mail_update_action'), 'blue')
    . email_html_url_fallback($appealUrl)
    . '<p>' . $escape($translate('moderation.mail_update_hint')) . '</p>';

return [
    'html' => email_html_layout(
        $translate('moderation.decision_title'),
        $translate('auth.restriction_heading'),
        $body,
        ['accent' => 'slate', 'lang' => $language]
    ),
    'text' => ($referenceLine !== '' ? $referenceLine . "\n\n" : '') . $intro . "\n\n" . $appealUrl . "\n\n" . $translate('moderation.mail_update_hint') . "\n",
];
