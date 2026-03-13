<?php
// mail/config.php
return [
    'from_email' => 'carburant@vlm.ttrd.fr',
    'from_name'  => 'Votre brigade connectée.',
    'smtp' => [
        'enabled'  => true,
        'host'     => 'smtp.hostinger.com',
        'port'     => 587,
        'secure'   => 'tls', // ou 'ssl' si tu utilises le port 465
        'username' => 'carburant@vlm.ttrd.fr',
        'password' => 'Tt05032001_TETARD',
    ],
    'bcc_admins' => true, // Envoyer une copie à tous les admins
];
