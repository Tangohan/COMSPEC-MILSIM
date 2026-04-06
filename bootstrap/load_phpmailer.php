<?php

declare(strict_types=1);

/**
 * Garantit la présence de PHPMailer si vendor/autoload.php n’a pas enregistré la classe
 * (déploiement incomplet, cache d’autoload, etc.).
 * Les sources attendues : vendor/phpmailer/phpmailer/src (Composer) ou PHPMailer/src (copie manuelle).
 */
if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class, false)) {
    return;
}

$root = dirname(__DIR__);
$dirs = [
    $root . '/vendor/phpmailer/phpmailer/src',
    $root . '/PHPMailer/src',
];

foreach ($dirs as $dir) {
    $main = $dir . '/PHPMailer.php';
    if (!is_file($main)) {
        continue;
    }
    require_once $dir . '/Exception.php';
    require_once $dir . '/SMTP.php';
    require_once $main;

    return;
}
