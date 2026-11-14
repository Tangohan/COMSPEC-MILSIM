<?php

declare(strict_types=1);

namespace App\Support\JobCatalog;

/**
 * Suggestions du référentiel pour un emploi de la communauté (JobCatalogMatcher), au format affiché : nom, pays, unité,
 * abrégé, code, et ce que l'import apporte en plus (qualifications, postes types, sources). Servi à la création d'un
 * emploi (JobCatalogController::detect) et pour la note orange des emplois déjà créés (EffectifsWorkspaceController).
 */
final class JobCatalogHints
{
    /**
     * @param array{name?: string, abbreviation?: string, code?: string, unit?: string, country?: string} $job
     * @return list<array<string, mixed>>
     */
    public static function for(array $job, string $lang, int $limit = 3, int $min = JobCatalogMatcher::SUGGEST): array
    {
        $out = [];
        foreach (JobCatalogMatcher::match($job, $limit, $min) as $hit) {
            $entry = JobCatalog::find($hit['id'], $lang);
            if ($entry === null) {
                continue;
            }
            $out[] = [
                'id' => $entry['id'],
                'score' => $hit['score'],
                'reasons' => $hit['reasons'],
                'name' => $entry['name'],
                'official_name' => $entry['official_name'] !== $entry['name'] ? $entry['official_name'] : '',
                'country' => $entry['country'],
                'country_label' => $entry['country_label'],
                'unit' => $entry['unit'] !== '' ? $entry['unit'] : $entry['formation'],
                'abbreviation' => $entry['abbreviation'],
                'code' => $entry['code'],
                'counts' => [
                    'qualifications' => count($entry['qualifications']),
                    'billets' => count($entry['billets']),
                    'sources' => count($entry['sources']),
                ],
            ];
        }

        return $out;
    }

    /**
     * Ce que l'import apporte, en clair : « 4 qualifications, 2 postes types, 3 sources ».
     *
     * @param array{qualifications: int, billets: int, sources: int} $counts
     */
    public static function gains(array $counts): string
    {
        $parts = [];
        foreach (['qualifications' => ['qualification', 'qualifications'], 'billets' => ['poste type', 'postes types'], 'sources' => ['source', 'sources']] as $key => [$one, $many]) {
            $n = (int) ($counts[$key] ?? 0);
            if ($n > 0) {
                $parts[] = $n . ' ' . ($n > 1 ? $many : $one);
            }
        }

        return implode(', ', $parts);
    }

    /**
     * Emploi de la communauté déjà rempli depuis cette entrée (même nom et description qui reprend celle du référentiel) :
     * pas de note, l'import a déjà eu lieu.
     */
    public static function alreadyImported(string $jobName, string $jobDescription, string $entryId, string $lang): bool
    {
        $entry = JobCatalog::find($entryId, $lang);
        if ($entry === null || trim($jobDescription) === '') {
            return false;
        }
        $names = array_map(static fn (string $n): string => JobCatalog::normalize($n), array_values($entry['names']));
        if (!in_array(JobCatalog::normalize($jobName), $names, true)) {
            return false;
        }
        $start = mb_substr(JobCatalog::normalize((string) $entry['description']), 0, 40);

        return $start !== '' && str_contains(JobCatalog::normalize($jobDescription), trim($start));
    }
}
