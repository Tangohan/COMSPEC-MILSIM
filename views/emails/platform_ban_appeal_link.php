<?php

declare(strict_types=1);

/** @var string $appealUrl */
$body = '<p>Un lien a été demandé pour consulter votre décision de modération. Vous pourrez y lire le motif communiqué et, si vous le souhaitez, déposer une contestation.</p>'
    . email_html_button($appealUrl, 'Consulter la décision', 'blue')
    . email_html_url_fallback($appealUrl)
    . '<p>Ce lien est personnel et expire dans une heure. Si vous n’êtes pas à l’origine de la demande, ignorez ce message.</p>';

return [
    'html' => email_html_layout('Décision de modération', 'Modération Athena', $body, ['accent' => 'slate']),
    'text' => "Consultez votre décision de modération avec ce lien personnel, valable une heure :\n\n"
        . $appealUrl . "\n\nSi vous n’êtes pas à l’origine de la demande, ignorez ce message.\n",
];
