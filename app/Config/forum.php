<?php

declare(strict_types=1);

return [
    'name' => 'Salle de brief',
    'subtitle' => 'COMSPEC · Athena',
    'tagline' => 'Ici, les ordres et les retours d\'opération circulent. Briefs, comptes-rendus et annonces au cœur de la communauté.',
    'context' => 'Centre des transmissions · Communauté',

    'labels' => [
        'topics' => 'Briefs',
        'categories' => 'Canaux',
        'last_activity' => 'Dernier signal',
        'new_topic' => 'Émettre un brief',
        'agora_title' => 'Agora Athena',
        'agora_subtitle' => 'Publier dans l\'Agora',
        'recent_archives' => 'Archives récentes',
        'moderation_panel' => 'Terminal de Contrôle',
        'official_announcements' => 'Communiqués officiels',
        'channels_active' => 'zones actives',
        'search_placeholder' => 'Recherche forum (titre + contenu)',
        'reply' => 'Répondre',
        'subscribe' => 'Suivre',
        'unsubscribe' => 'Ne plus suivre',
        'locked' => 'Verrouillé',
        'pinned' => 'Épinglé',
        'archived' => 'Archivé',
        'replies' => 'réponses',
        'views' => 'lectures',
        'by' => 'Par',
    ],

    'category_colors' => ['orange', 'indigo', 'violet', 'rose', 'emerald', 'slate'],

    'forum_max_post_length' => 10000,
    'enabled' => true,

    /** Hôtes considérés comme internes (en plus du host APP_URL), sans protocole. */
    'internal_link_hosts' => [],

    /** Délai (secondes) avant activation du bouton « Continuer » sur la page de sortie. */
    'leave_countdown_seconds' => 5,
];
