<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Services\Cron\CronJobInterface;
use App\Services\Integrations\DiscordLinkedRoles;

/**
 * Rôles liés de Discord : renvoie à Discord, une fois par jour et par compte relié, les communautés et l'ancienneté
 * du membre (un changement de communauté se voit ainsi sur Discord sans qu'il ait à relier son compte à nouveau).
 */
final class DiscordLinkedRolesSyncCronJob implements CronJobInterface
{
    public function __construct(private ?DiscordLinkedRoles $service = null)
    {
    }

    public function key(): string
    {
        return 'discord_linked_roles_sync';
    }

    public function label(): string
    {
        return 'Rôles liés Discord';
    }

    public function description(): string
    {
        return 'Met à jour sur Discord les communautés et l’ancienneté des membres qui ont relié leur compte Discord.';
    }

    public function run(): array
    {
        $count = ($this->service ?? new DiscordLinkedRoles())->syncStale(50);

        return [
            'ok' => true,
            'summary' => $count['ok'] . ' compte(s) mis à jour sur Discord · ' . $count['failed'] . ' échec(s)',
            'details' => $count,
        ];
    }
}
