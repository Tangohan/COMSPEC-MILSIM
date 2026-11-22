<?php

declare(strict_types=1);

/** @var string $appealUrl */
/** @var string|null $appealLanguage */
$language = ($appealLanguage ?? 'fr') === 'en' ? 'en' : 'fr';
$translate = static fn (string $key): string => __($key, [], $language);
$escape = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
$intro = $translate(!empty($appealClosed) ? 'moderation.mail_intro_closed' : 'moderation.mail_intro');
$body = '<p>' . $escape($intro) . '</p>'
    . email_html_button($appealUrl, $translate('moderation.mail_action'), 'blue')
    . email_html_url_fallback($appealUrl)
    . '<p>' . $escape($translate('moderation.mail_expiry')) . '</p>';

return [
    'html' => email_html_layout(
        $translate('moderation.decision_title'),
        $translate('auth.restriction_heading'),
        $body,
        ['accent' => 'slate', 'lang' => $language]
    ),
    'text' => $intro . "\n\n" . $appealUrl . "\n\n" . $translate('moderation.mail_expiry') . "\n",
];
