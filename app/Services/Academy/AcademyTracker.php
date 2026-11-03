<?php

declare(strict_types=1);

namespace App\Services\Academy;

/**
 * Tableau de suivi des formations (Academy › Tableau de suivi) : matrice stagiaires × formations (vue « cursus ») ou
 * stagiaires × chapitres d'une formation (vue « formation »), sur le modèle du tableau de suivi CQB de l'aperçu public.
 *
 * Aucune donnée propre : les états viennent des inscriptions (academy_learners, lues par AcademyRepository::trackerRows
 * sans le contenu des cours) et des qualifications ou brevets délivrés par les cours (référentiel). Les actions restent
 * celles du serveur (inscrire, valider le chapitre en attente comme la file « À traiter », dispenser, relancer).
 */
final class AcademyTracker
{
    /** États d'une cellule, dans l'ordre de la légende. */
    public const STATES = ['none', 'enrolled', 'progress', 'review', 'failed', 'validated', 'expired', 'exempt'];
    /** États d'un chapitre (vue « formation »). */
    public const STEPS = ['none', 'todo', 'current', 'review', 'failed', 'done', 'exempt'];
    /** Sans activité depuis ce nombre de jours : en retard (même seuil que la file « À traiter »). */
    public const LATE_DAYS = AcademyInstructorQueue::STALLED_DAYS;
    /** Qualification qui expire dans ce délai : à renouveler. */
    public const RENEW_DAYS = 30;
    /** Une relance par stagiaire et par cours au plus toutes les 24 h. */
    public const REMIND_GAP = 86400;
    public const PER_PAGE = 40;
    /** Colonnes par défaut de la vue « cursus » : les formations les plus suivies. */
    public const MAX_COLUMNS = 12;

    /**
     * Ligne lue en base (JSON_EXTRACT) → valeurs typées.
     *
     * @param array<string, mixed> $raw
     * @return array{user_id:int, course_key:string, chapter:int, stage:int, completed:bool, exempt:bool, failed:bool, exam:bool, updated:int, reminded:int, debriefs:array<int|string,mixed>}
     */
    public static function normalizeRow(array $raw): array
    {
        $updated = (int) self::scalar($raw['updated'] ?? null);
        if ($updated < 1) {
            $updated = strtotime((string) ($raw['updated_at'] ?? '')) ?: 0;
        }
        $debriefs = $raw['debriefs'] ?? null;
        if (is_string($debriefs) && $debriefs !== '') {
            $debriefs = json_decode($debriefs, true);
        }

        return [
            'user_id' => (int) ($raw['user_id'] ?? 0),
            'course_key' => (string) ($raw['course_key'] ?? ''),
            'chapter' => max(0, (int) self::scalar($raw['chapter'] ?? null)),
            'stage' => max(0, (int) self::scalar($raw['stage'] ?? null)),
            'completed' => self::truthy($raw['completed'] ?? null),
            'exempt' => (int) self::scalar($raw['exempt_at'] ?? null) > 0,
            'failed' => self::scalar($raw['feedback'] ?? null) === 'failed',
            'exam' => (int) self::scalar($raw['exam_started'] ?? null) > 0,
            'updated' => $updated,
            'reminded' => (int) self::scalar($raw['reminded'] ?? null),
            'debriefs' => is_array($debriefs) ? $debriefs : [],
        ];
    }

    /**
     * Qualifications et brevets délivrés par les cours : pour chaque membre et chaque cours, la meilleure attribution
     * obtenue (permanente, sinon l'échéance la plus lointaine).
     *
     * @param list<array{id:int, courses:list<string>}> $credentials AcademyStudioCredentials::list()
     * @param list<array<string, mixed>> $awards QualificationAwardRepository::forDefinitions()
     * @return array<int, array<string, array{held:bool, expires:?int}>>
     */
    public static function awardsByCourse(array $credentials, array $awards): array
    {
        $courses = [];
        foreach ($credentials as $credential) {
            foreach ($credential['courses'] ?? [] as $key) {
                $courses[(int) $credential['id']][] = (string) $key;
            }
        }
        $out = [];
        foreach ($awards as $award) {
            if (\App\Support\QualificationAdminStatus::normalize((string) ($award['status'] ?? '')) !== \App\Support\QualificationAdminStatus::OBTAINED) {
                continue;
            }
            $expires = trim((string) ($award['expires_at'] ?? '')) !== '' ? (strtotime((string) $award['expires_at']) ?: null) : null;
            foreach ($courses[(int) ($award['definition_id'] ?? 0)] ?? [] as $key) {
                $uid = (int) $award['user_id'];
                $current = $out[$uid][$key] ?? null;
                if ($current === null || ($current['expires'] !== null && ($expires === null || $expires > $current['expires']))) {
                    $out[$uid][$key] = ['held' => true, 'expires' => $expires];
                }
            }
        }

        return $out;
    }

    /**
     * État d'un stagiaire sur une formation.
     *
     * @param array<string, mixed>|null $row ligne normalisée, null = pas inscrit
     * @param array{held:bool, expires:?int}|null $award qualification ou brevet délivré par ce cours
     * @return array{state:string, pct:int, chapter:int, stage:int, late:bool, since:int, reminded:int, expires:?int, final:bool}
     */
    public static function cell(?array $row, int $chapters, ?array $award, int $now): array
    {
        $chapters = max(1, $chapters);
        $held = $award !== null && !empty($award['held']);
        $expires = $held ? ($award['expires'] ?? null) : null;
        $base = ['pct' => 0, 'chapter' => 0, 'stage' => 0, 'late' => false, 'since' => 0, 'reminded' => 0, 'expires' => $expires, 'final' => false];
        $renew = $expires !== null && $expires < $now + self::RENEW_DAYS * 86400;
        if ($row === null) {
            return ['state' => $held ? ($renew ? 'expired' : 'validated') : 'none'] + $base;
        }
        $chapter = min((int) $row['chapter'], $chapters - 1);
        $stage = (int) $row['stage'];
        $base = ['chapter' => $chapter, 'stage' => $stage, 'since' => (int) $row['updated'], 'reminded' => (int) $row['reminded'], 'final' => $chapter + 1 >= $chapters] + $base;
        if (!empty($row['exempt'])) {
            return ['state' => 'exempt', 'pct' => 100] + $base;
        }
        if (!empty($row['completed']) || $held) {
            return ['state' => $renew ? 'expired' : 'validated', 'pct' => 100] + $base;
        }
        $base['pct'] = (int) floor(($chapter * 7 + min($stage, 6)) / ($chapters * 7) * 100);
        $base['late'] = $base['since'] > 0 && $base['since'] < $now - self::LATE_DAYS * 86400;
        $state = match (true) {
            $stage === 6 => 'review',
            $stage === 4 && !empty($row['failed']) && empty($row['exam']) => 'failed',
            $chapter === 0 && $stage === 0 => 'enrolled',
            default => 'progress',
        };

        return ['state' => $state] + $base;
    }

    /**
     * Chapitres d'une formation pour un stagiaire (vue « formation »).
     *
     * @param array<string, mixed> $cell résultat de cell()
     * @return list<string> un état de STEPS par chapitre
     */
    public static function steps(array $cell, int $chapters): array
    {
        $out = [];
        for ($i = 0; $i < max(1, $chapters); $i++) {
            $out[] = match (true) {
                $cell['state'] === 'none' => 'none',
                $cell['state'] === 'exempt' => 'exempt',
                in_array($cell['state'], ['validated', 'expired'], true), $i < $cell['chapter'] => 'done',
                $i > $cell['chapter'] => 'todo',
                $cell['state'] === 'review' => 'review',
                $cell['state'] === 'failed' => 'failed',
                $cell['stage'] > 0 => 'current',
                default => 'todo',
            };
        }

        return $out;
    }

    /**
     * Garde les membres qui correspondent aux filtres (unité, fonction, grade, cohorte, recherche).
     *
     * @param list<array<string, mixed>> $members id, name, grade, units, jobs, groups
     * @param array{unit?:int, job?:int, grade?:string, group?:int, q?:string} $filters
     * @return list<array<string, mixed>>
     */
    public static function filterMembers(array $members, array $filters): array
    {
        $unit = (int) ($filters['unit'] ?? 0);
        $job = (int) ($filters['job'] ?? 0);
        $group = (int) ($filters['group'] ?? 0);
        $grade = trim((string) ($filters['grade'] ?? ''));
        $q = mb_strtolower(trim((string) ($filters['q'] ?? '')));

        return array_values(array_filter($members, static fn (array $m): bool =>
            ($unit < 1 || in_array($unit, $m['units'] ?? [], true))
            && ($job < 1 || in_array($job, $m['jobs'] ?? [], true))
            && ($group < 1 || in_array($group, $m['groups'] ?? [], true))
            && ($grade === '' || (string) ($m['grade'] ?? '') === $grade)
            && ($q === '' || str_contains(mb_strtolower((string) ($m['name'] ?? '')), $q))));
    }

    /**
     * Matrice complète : cellules de chaque membre filtré, indicateurs sur l'ensemble, page demandée.
     *
     * @param list<array<string, mixed>> $members membres déjà filtrés
     * @param list<array{key:string, chapters:int}> $columns formations en colonnes
     * @param array<int, array<string, array<string, mixed>>> $rows [user][cours] lignes normalisées
     * @param array<int, array<string, array{held:bool, expires:?int}>> $awards [user][cours]
     * @param string $state ne garder que les lignes ayant au moins une cellule dans cet état ('' = tout, 'late' = en retard)
     * @return array{rows: list<array<string, mixed>>, total:int, page:int, pages:int, kpis: array<string, int>}
     */
    public static function matrix(array $members, array $columns, array $rows, array $awards, int $now, int $page = 1, string $state = '', int $perPage = self::PER_PAGE): array
    {
        $all = [];
        $kpis = array_fill_keys(self::STATES, 0) + ['late' => 0, 'cells' => 0, 'tracked' => 0, 'complete_pct' => 0];
        foreach ($members as $member) {
            $id = (int) $member['id'];
            $cells = [];
            foreach ($columns as $column) {
                $key = (string) $column['key'];
                $cells[$key] = self::cell($rows[$id][$key] ?? null, (int) $column['chapters'], $awards[$id][$key] ?? null, $now);
            }
            if ($state !== '' && !self::rowHas($cells, $state)) {
                continue;
            }
            foreach ($cells as $cell) {
                $kpis[$cell['state']]++;
                $kpis['cells']++;
                $kpis['late'] += (int) $cell['late'];
            }
            $all[] = ['member' => $member, 'cells' => $cells];
        }
        $kpis['tracked'] = $kpis['cells'] - $kpis['none'];
        $kpis['complete_pct'] = $kpis['tracked'] > 0 ? (int) round(($kpis['validated'] + $kpis['expired'] + $kpis['exempt']) * 100 / $kpis['tracked']) : 0;
        $perPage = max(1, $perPage);
        $pages = max(1, (int) ceil(count($all) / $perPage));
        $page = min(max(1, $page), $pages);

        return ['rows' => array_slice($all, ($page - 1) * $perPage, $perPage), 'total' => count($all), 'page' => $page, 'pages' => $pages, 'kpis' => $kpis];
    }

    /** @param array<string, array<string, mixed>> $cells */
    private static function rowHas(array $cells, string $state): bool
    {
        foreach ($cells as $cell) {
            if ($state === 'late' ? $cell['late'] : $cell['state'] === $state) {
                return true;
            }
        }

        return false;
    }

    /**
     * Actions possibles sur une cellule pour la personne connectée (le serveur revérifie chaque droit).
     *
     * @param array<string, mixed> $cell
     * @param array{assign:bool, teach:bool, certify:bool, me:int} $can
     * @return list<string> enroll | remind | validate | exempt | unexempt
     */
    public static function actions(array $cell, int $learnerId, array $can): array
    {
        $own = $learnerId === (int) $can['me'];
        $state = (string) $cell['state'];
        $out = [];
        if ($state === 'none' && $can['assign']) {
            $out[] = 'enroll';
        }
        if (in_array($state, ['enrolled', 'progress', 'failed'], true) && !$own && ($can['teach'] || $can['assign'])) {
            $out[] = 'remind';
        }
        if ($state === 'review' && !$own && $can['teach'] && (!$cell['final'] || $can['certify'])) {
            $out[] = 'validate';
        }
        if (!$own && $can['teach'] && $can['certify']) {
            if (in_array($state, ['none', 'enrolled', 'progress', 'failed', 'review'], true)) {
                $out[] = 'exempt';
            } elseif ($state === 'exempt') {
                $out[] = 'unexempt';
            }
        }

        return $out;
    }

    /** Relance possible : pas déjà relancé dans les dernières 24 h. */
    public static function canRemindNow(array $cell, int $now): bool
    {
        return in_array($cell['state'], ['enrolled', 'progress', 'failed'], true) && (int) $cell['reminded'] < $now - self::REMIND_GAP;
    }

    /**
     * Export CSV (séparateur « ; », BOM UTF-8 pour Excel).
     *
     * @param list<string> $headers
     * @param list<list<string>> $lines
     */
    public static function csv(array $headers, array $lines): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        foreach (array_merge([$headers], $lines) as $line) {
            fputcsv($out, array_map(static fn (mixed $v): string => self::csvSafe((string) $v), $line), ';', '"', '');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /** Pas de formule exécutée à l'ouverture dans un tableur. */
    private static function csvSafe(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $value : $value;
    }

    private static function scalar(mixed $value): string
    {
        if ($value === null || is_array($value)) {
            return '';
        }

        return trim((string) $value, "\" \t\n");
    }

    private static function truthy(mixed $value): bool
    {
        return in_array(self::scalar($value), ['1', 'true'], true) || $value === true;
    }
}
