<?php

declare(strict_types=1);

return [
    'page_title' => 'Maintenance',
    'retry' => 'Try again',
    'hint' => 'No action is needed on your part.',
    'admin_login' => 'Administrator sign-in',
    'preview' => 'Preview only: nothing is published and the site remains open.',
    'logged_out' => 'You have been signed out for the duration of the maintenance. You can sign in again when the service is back online.',
    'reopening' => 'Estimated return: :date.',
    'date_format' => 'd M Y, H:i',
    'pause_background' => 'Pause background slideshow',
    'resume_background' => 'Resume background slideshow',
    'status' => [
        'label' => 'Service status',
        'site' => 'Athena website',
        'maintenance' => 'Under maintenance',
        'tools' => 'Related services',
        'waiting' => 'Awaiting service restoration',
        'data' => 'Your data',
        'protected' => 'Protected',
        'reopening' => 'Estimated return',
        'unknown' => 'Once maintenance is complete',
    ],
    'presets' => [
        'standard' => [
            'label' => 'Standard message',
            'title' => 'We’ll be back soon',
            'message' => "Athena is undergoing maintenance. Access to the service is temporarily paused.\n\nYour data remains safe. Your usual spaces will be available again when the service returns.\n\nThank you for your patience and understanding.",
        ],
        'short' => [
            'label' => 'Short version',
            'title' => 'A pause for maintenance',
            'message' => "Athena is temporarily unavailable for maintenance.\n\nAccess will be restored once maintenance is complete. Thank you for your patience.",
        ],
        'friendly' => [
            'label' => 'Friendly tone',
            'title' => 'See you soon on Athena',
            'message' => "We’re taking a moment to look after Athena and prepare for what’s next. The service is temporarily unavailable.\n\nYour data remains safe and there’s nothing you need to do. You can pick up where you left off when the service returns.\n\nThank you for your patience and trust.",
        ],
        'security' => [
            'label' => 'Security',
            'title' => 'Security maintenance',
            'message' => "Athena is undergoing security maintenance. Access to the service is paused while the work takes place.\n\nNo action is needed on your part. Access will be restored once the checks are complete.\n\nThank you for your patience and understanding.",
        ],
        'infra' => [
            'label' => 'Infrastructure',
            'title' => 'Maintenance in progress',
            'message' => "Work is underway on Athena’s infrastructure. The service is temporarily unavailable.\n\nWe’ll restore access once the work and the necessary checks are complete.\n\nThank you for your patience.",
        ],
        'hotfix' => [
            'label' => 'Service fix',
            'title' => 'We’re working to bring the service back',
            'message' => "We’re applying a fix to Athena. Access to the service is temporarily paused.\n\nWe’re working to restore the service. You can sign in again when it returns, with no extra steps required.\n\nThank you for your understanding.",
        ],
    ],
];
