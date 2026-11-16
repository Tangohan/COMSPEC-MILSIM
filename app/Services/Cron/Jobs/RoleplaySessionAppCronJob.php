<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Services\Cron\CronJobInterface;
use App\Services\Personnel\RoleplaySessionCodeService;
use App\Services\Personnel\RoleplaySessionNotifier;

/**
 * Sessions de jeu, app Session du téléphone : rappels 15 et 5 minutes avant le début prévu (une seule fois par horaire)
 * et expiration des codes de session dont l'heure est passée. Sans les tables de l'étape 4910 : rien à faire.
 */
final class RoleplaySessionAppCronJob implements CronJobInterface
{
    public function __construct(
        private ?RoleplaySessionCodeService $codes = null,
        private ?RoleplaySessionNotifier $notifier = null,
    ) {}

    public function key(): string
    {
        return 'roleplay_session_app';
    }

    public function label(): string
    {
        return 'Sessions de jeu : rappels en jeu et codes';
    }

    public function description(): string
    {
        return 'Envoie aux téléphones les rappels 15 et 5 minutes avant une session prévue et fait expirer les codes de session terminés.';
    }

    public function run(): array
    {
        $codes = $this->codes ?? new RoleplaySessionCodeService();
        if (!$codes->ready()) {
            return ['ok' => true, 'summary' => 'Tables de l’app Session absentes : rien à faire.'];
        }
        $notifier = $this->notifier ?? new RoleplaySessionNotifier($codes->repository());
        $reminders = $notifier->tickReminders();
        $expired = $codes->expireDue();

        return [
            'ok' => true,
            'summary' => $reminders . ' rappel(s) envoyé(s), ' . $expired . ' code(s) expiré(s).',
            'details' => ['reminders' => $reminders, 'expired_codes' => $expired],
        ];
    }
}
