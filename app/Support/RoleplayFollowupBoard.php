<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Bureau de suivi roleplay : ce qu'il y a à faire pour chaque membre, et les compteurs des filtres.
 * Calcul sans base de données, sur les lignes préparées par RoleplayFollowupAdminController::index.
 */
final class RoleplayFollowupBoard
{
    public const FILTERS = ['a-traiter', 'retard', 'sans-tuteur', 'prets', 'non-suivis', 'tous'];

    /**
     * Prochaine action du membre : l'échéance la plus proche parmi entretien, visite, rotation et bilan.
     *
     * @param array<string, mixed> $row
     * @return array{label: string, date: string, overdue: bool}|null
     */
    public static function nextAction(array $row, string $today): ?array
    {
        $candidates = [
            'Entretien' => (string) ($row['next_interview_date'] ?? ''),
            'Visite médicale' => (string) ($row['medical_due_date'] ?? ''),
            'Rotation' => (string) ($row['service_rotation_date'] ?? ''),
            'Bilan' => (string) ($row['rp_bilan_next_due_at'] ?? ''),
        ];
        $best = null;
        foreach ($candidates as $label => $raw) {
            $date = substr(trim($raw), 0, 10);
            if ($date === '' || str_starts_with($date, '0000')) {
                continue;
            }
            if ($best === null || $date < $best['date']) {
                $best = ['label' => $label, 'date' => $date, 'overdue' => $date < $today];
            }
        }

        return $best;
    }

    /**
     * Filtres auxquels appartient une ligne (attribut data-filters de la page).
     *
     * @param array<string, mixed> $row
     * @return list<string>
     */
    public static function filtersOf(array $row, string $today): array
    {
        $tracked = !empty($row['tracked']);
        $action = self::nextAction($row, $today);
        $late = ($action !== null && $action['overdue']) || !empty($row['rp_bilan_overdue']);
        $noTutor = $tracked && (int) ($row['tutor_id'] ?? 0) < 1;
        $out = ['tous'];
        if ($late) {
            $out[] = 'retard';
        }
        if ($noTutor) {
            $out[] = 'sans-tuteur';
        }
        if ($late || $noTutor) {
            $out[] = 'a-traiter';
        }
        if (!empty($row['eligible'])) {
            $out[] = 'prets';
        }
        if (!$tracked) {
            $out[] = 'non-suivis';
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, int> filtre => nombre de membres
     */
    public static function counts(array $rows, string $today): array
    {
        $out = array_fill_keys(self::FILTERS, 0);
        foreach ($rows as $row) {
            foreach (self::filtersOf($row, $today) as $f) {
                $out[$f]++;
            }
        }

        return $out;
    }

    /**
     * Position de l'étape dans la liste de la communauté (0 : pas d'étape ou étape retirée de la liste).
     *
     * @param list<string> $stages
     */
    public static function stagePosition(string $stage, array $stages): int
    {
        $i = array_search(trim($stage), array_map(static fn ($s): string => trim((string) $s), $stages), true);

        return $i === false ? 0 : (int) $i + 1;
    }
}
