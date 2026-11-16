<?php

declare(strict_types=1);

return [
    'title' => 'ATHENA Companion',
    'intro' => 'Le bot officiel publie vos événements et synchronise les réponses Discord. Invitez-le puis reliez votre serveur avec un code temporaire.',
    'invite' => 'Inviter ATHENA Companion',
    'generate' => 'Créer un code de liaison (10 minutes)',
    'pair_code' => 'Code valable 10 minutes : :code. Sur votre serveur Discord, utilisez /athena connect avec ce code et le salon de publication.',
    'unlink' => 'Délier le serveur',
    'unlinked' => 'Serveur délié. Le bot ne reçoit plus vos événements.',
    'connected' => 'Serveur relié : :guild · :channel',
    'not_configured' => 'Le service ATHENA Companion doit encore être configuré par l’administrateur de la plateforme.',
    'api_key' => 'Clé de connexion du service Companion',
    'api_help' => 'Utilisez la même clé aléatoire (32 caractères minimum) dans le service du bot. Le jeton Discord reste uniquement dans ce service.',
    'key_keep' => 'Laisser vide pour conserver la clé',
    'saved' => 'Configuration Companion enregistrée.',
    'invalid_key' => 'La clé doit contenir entre 32 et 128 caractères alphanumériques, tirets ou tirets bas.',
    'invalid_code' => 'Le code de liaison est invalide ou expiré.',
    'already_bound' => 'Le serveur ou la communauté est déjà relié. Déliez-le avant de changer de serveur.',
    'channel_choice' => 'ATHENA Companion — :channel',
    'client_id' => 'Identifiant de l’application du bot (Application ID)',
    'client_id_help' => 'Portail des développeurs Discord, application du bot (Athena Companion) : Informations générales, Application ID (17 à 20 chiffres). Ce n’est pas l’application des rôles liés.',
    'client_secret' => 'Secret client de l’application du bot',
    'secret_saved' => 'Secret enregistré. Laisser vide pour le conserver.',
    'secret_missing' => 'Secret non enregistré.',
    'secret_help' => 'OAuth2 › Client Secret. Il n’est jamais réaffiché.',
    'redirect_help' => 'À enregistrer dans le portail (OAuth2 › Redirections) de l’application du bot :',
    'invalid_client_id' => 'L’identifiant de l’application doit contenir de 17 à 20 chiffres.',
    'invalid_client_secret' => 'Le secret client ne ressemble pas à un secret Discord (OAuth2 › Client Secret).',
];
