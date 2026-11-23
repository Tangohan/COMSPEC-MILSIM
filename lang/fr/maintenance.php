<?php

declare(strict_types=1);

return [
    'page_title' => 'Maintenance',
    'retry' => 'Réessayer',
    'hint' => 'Aucune action n’est nécessaire de votre part.',
    'admin_login' => 'Connexion administrateur',
    'preview' => 'Aperçu : rien n’est en ligne, le site reste ouvert.',
    'logged_out' => 'Vous avez été déconnecté pour la durée de la maintenance. Vous pourrez vous reconnecter à la réouverture.',
    'reopening' => 'Réouverture estimée : :date.',
    'date_format' => 'd/m/Y à H:i',
    'pause_background' => 'Mettre le fond en pause',
    'resume_background' => 'Reprendre le défilement',
    'status' => [
        'label' => 'État des services',
        'site' => 'Site Athena',
        'maintenance' => 'En maintenance',
        'tools' => 'Services associés',
        'waiting' => 'En attente de réouverture',
        'data' => 'Vos données',
        'protected' => 'Protégées',
        'reopening' => 'Réouverture estimée',
        'unknown' => 'Dès la fin de l’intervention',
    ],
    'presets' => [
        'standard' => [
            'label' => 'Message standard',
            'title' => 'Nous revenons bientôt',
            'message' => "Une maintenance est en cours sur Athena. L’accès au service est temporairement suspendu.\n\nVos données restent en sécurité. Vous pourrez retrouver vos espaces habituels dès la réouverture.\n\nMerci de votre patience et de votre compréhension.",
        ],
        'short' => [
            'label' => 'Version courte',
            'title' => 'Une pause pour maintenance',
            'message' => "Athena est temporairement indisponible pour maintenance.\n\nL’accès sera rétabli dès la fin de l’intervention. Merci de votre patience.",
        ],
        'friendly' => [
            'label' => 'Ton chaleureux',
            'title' => 'À très bientôt sur Athena',
            'message' => "Nous prenons un moment pour entretenir Athena et préparer la suite. Le service est temporairement indisponible.\n\nVos données restent en sécurité et aucune démarche n’est nécessaire. Vous pourrez reprendre vos activités à la réouverture.\n\nMerci pour votre patience et votre confiance.",
        ],
        'security' => [
            'label' => 'Sécurité',
            'title' => 'Maintenance de sécurité',
            'message' => "Une maintenance de sécurité est en cours sur Athena. L’accès au service est suspendu pendant l’intervention.\n\nAucune action n’est nécessaire de votre part. L’accès sera rétabli une fois les vérifications terminées.\n\nMerci de votre patience et de votre compréhension.",
        ],
        'infra' => [
            'label' => 'Infrastructure',
            'title' => 'Intervention en cours',
            'message' => "Une intervention technique est en cours sur l’infrastructure d’Athena. Le service est temporairement indisponible.\n\nNous rétablirons l’accès dès la fin de l’intervention et des vérifications nécessaires.\n\nMerci de votre patience.",
        ],
        'hotfix' => [
            'label' => 'Correctif',
            'title' => 'Nous préparons le retour du service',
            'message' => "Une intervention corrective est en cours sur Athena. L’accès au service est temporairement suspendu.\n\nNous travaillons à son rétablissement. Vous pourrez vous reconnecter dès la réouverture, sans démarche particulière.\n\nMerci de votre compréhension.",
        ],
    ],
];
