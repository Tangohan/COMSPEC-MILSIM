<?php

declare(strict_types=1);

namespace App\Services\ContactMail;

/** Assemble les services de la boîte de contact d'après le .env (un seul endroit pour contrôleur, tâche planifiée, formulaire). */
final class ContactMailFactory
{
    public static function config(): ContactMailConfig
    {
        return ContactMailConfig::fromEnv();
    }

    public static function repository(): ContactMailRepository
    {
        return new ContactMailRepository();
    }

    public static function ingest(?ContactMailConfig $config = null, ?ContactMailRepository $repo = null): ContactMailIngest
    {
        return new ContactMailIngest($repo ?? self::repository(), new AttachmentStorage(), $config ?? self::config());
    }

    public static function sync(): ContactMailSyncService
    {
        $config = self::config();
        $repo = self::repository();

        return new ContactMailSyncService($config, $repo, self::ingest($config, $repo));
    }

    public static function sender(): ContactMailSender
    {
        $config = self::config();
        $repo = self::repository();

        return new ContactMailSender($config, $repo, self::ingest($config, $repo));
    }
}
