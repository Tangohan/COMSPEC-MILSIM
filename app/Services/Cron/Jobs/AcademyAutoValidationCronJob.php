<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Services\Academy\AcademyAutoValidation;
use App\Services\Cron\CronJobInterface;

/**
 * Academy : vérification automatique par Athena des chapitres restés sans réponse d'un formateur (72 h par défaut,
 * réglable et désactivable par communauté dans Academy > Droits). Passage horaire, plafonné.
 */
final class AcademyAutoValidationCronJob implements CronJobInterface
{
    public function __construct(private ?AcademyAutoValidation $service = null)
    {
    }

    public function key(): string
    {
        return 'academy_auto_validation';
    }

    public function label(): string
    {
        return 'Academy : vérification automatique des chapitres en attente';
    }

    public function description(): string
    {
        return 'Pour les communautés qui l’ont activée, vérifie les chapitres sans réponse du formateur depuis le délai réglé (72 h par défaut) : valide si les critères objectifs sont remplis, sinon relance le formateur et signale le dossier à traiter.';
    }

    public function run(): array
    {
        $r = ($this->service ??= new AcademyAutoValidation())->run();

        return [
            'ok' => true,
            'summary' => sprintf(
                '%d chapitre(s) examiné(s) dans %d communauté(s) : %d validé(s) automatiquement, %d à traiter par un formateur, %d ignoré(s)%s',
                $r['checked'],
                $r['tenants'],
                $r['validated'],
                $r['unmet'],
                $r['skipped'],
                $r['capped'] ? ' (plafond atteint, suite au prochain passage)' : ''
            ),
            'details' => $r,
        ];
    }
}
