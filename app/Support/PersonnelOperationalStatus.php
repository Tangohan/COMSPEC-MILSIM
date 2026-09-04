<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Présentation lisible et calculée de la situation opérationnelle d'un membre.
 *
 * Le pourcentage est volontairement secondaire : il sert à expliquer le calcul,
 * pas à demander au RH de saisir une note subjective.
 */
final class PersonnelOperationalStatus
{
    /**
     * @param array<string, bool> $checks
     * @return array{score:int,label:string,tone:string,summary:string,checks:list<array{label:string,ok:bool}>}
     */
    public static function assess(array $checks, bool $deployable, bool $accountActive): array
    {
        $labels = [
            'unit' => 'Une unité est affectée',
            'role' => 'Un poste ou une fonction est renseigné',
            'clearance' => 'Une habilitation documentaire est définie',
            'qualification' => 'Une qualification ou formation est enregistrée',
            'available' => 'Aucune absence en cours n’est signalée',
        ];

        $details = [];
        foreach ($labels as $key => $label) {
            $details[] = ['label' => $label, 'ok' => !empty($checks[$key])];
        }
        $score = $details !== []
            ? (int) round(100 * count(array_filter($details, static fn (array $row): bool => $row['ok'])) / count($details))
            : 0;

        if (!$accountActive || !$deployable || empty($checks['available'])) {
            return [
                'score' => $score,
                'label' => 'Non disponible',
                'tone' => 'rose',
                'summary' => !$accountActive
                    ? 'Le compte n’est pas actif.'
                    : (!$deployable ? 'Le dossier indique que ce membre ne peut pas être déployé.' : 'Une absence est actuellement déclarée.'),
                'checks' => $details,
            ];
        }

        if ($score === 100) {
            return ['score' => $score, 'label' => 'Prêt', 'tone' => 'emerald', 'summary' => 'Tous les éléments attendus sont présents.', 'checks' => $details];
        }

        return [
            'score' => $score,
            'label' => 'À compléter',
            'tone' => 'amber',
            'summary' => 'Le membre reste disponible, mais des informations utiles manquent dans son dossier.',
            'checks' => $details,
        ];
    }
}
