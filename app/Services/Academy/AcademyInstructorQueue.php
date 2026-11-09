<?php

declare(strict_types=1);

namespace App\Services\Academy;

/**
 * File de travail de l'instructeur : tout ce qui attend une action, au même endroit (tableau de bord Academy, espace
 * instructeur, badge du menu). Aucune règle de validation ici : la file lit les états existants et chaque action reste
 * celle du serveur (validation de chapitre, réponse aux demandes d'inscription, décision sur une qualification proposée).
 *
 * Types : chapter (débriefing à valider), exam (évaluation échouée à reprendre), enrollment (demande d'inscription),
 * proposal (qualification ou brevet proposé automatiquement, à approuver), stalled (stagiaire sans activité),
 * auto (chapitre validé automatiquement par Athena ces derniers jours : suivi, le formateur peut l'annuler).
 */
final class AcademyInstructorQueue
{
    public const TYPES = ['chapter', 'proposal', 'enrollment', 'exam', 'stalled', 'auto'];
    /** Types qui demandent une décision (badge « à traiter ») ; les autres sont du suivi. */
    public const ACTIONABLE = ['chapter', 'proposal', 'enrollment'];
    public const STALLED_DAYS = 14;
    /** Une validation automatique reste dans la file (pour être annulée) pendant ce nombre de jours. */
    public const AUTO_DAYS = 14;

    /**
     * @param list<array<string, mixed>> $learners lignes AcademyRepository::learners() (déjà filtrées sur ce que l'on suit)
     * @param callable(string):bool $teaches droit de valider ce cours (AcademyRights::canTeach)
     * @param list<array<string, mixed>> $enrollments demandes TrainingEnrollmentRepository::listPendingApproval()
     * @param list<array<string, mixed>> $proposals propositions (id, user_id, definition_id, name, since)
     * @return array{items: list<array<string, mixed>>, counts: array<string, int>, total: int}
     */
    public static function build(array $learners, callable $teaches, bool $certify, array $enrollments, array $proposals, int $me, int $now): array
    {
        $items = [];
        foreach ($learners as $row) {
            $state = is_array($row['state'] ?? null) ? $row['state'] : [];
            $key = (string) ($row['course_key'] ?? '');
            $uid = (int) ($row['user_id'] ?? 0);
            if ($key !== '' && $uid > 0) {
                foreach (self::autoValidated($state, $uid, $key, $row, $teaches, $me, $now) as $auto) {
                    $items[] = $auto;
                }
            }
            // Formation terminée ou stagiaire dispensé (tableau de suivi) : plus rien à suivre.
            if ($key === '' || $uid < 1 || !empty($state['completed']) || !empty($state['exempt'])) {
                continue;
            }
            $chapter = (int) ($state['chapter'] ?? 0);
            $chapters = is_array($state['snapshot']['chapters'] ?? null) ? $state['snapshot']['chapters'] : [];
            $updated = strtotime((string) ($row['updated_at'] ?? '')) ?: (int) ($state['updated'] ?? 0);
            $base = [
                'course_key' => $key, 'course_title' => $state['snapshot']['title'] ?? '', 'learner_id' => $uid,
                'chapter' => $chapter, 'chapter_title' => $chapters[$chapter]['title'] ?? '',
            ];
            if ((int) ($state['stage'] ?? 0) === 6 && isset($chapters[$chapter])) {
                $final = $chapter + 1 >= count($chapters);
                $can = $uid === $me ? 'own' : (!$teaches($key) ? 'not_allowed' : ($final && !$certify ? 'needs_certify' : 'ok'));
                $items[] = $base + ['id' => 'chapter-' . $uid . '-' . $key, 'type' => 'chapter', 'since' => $updated, 'final' => $final, 'can' => $can, 'row' => $row,
                    'auto_check' => is_array($state['auto_check'] ?? null) && (int) ($state['auto_check']['chapter'] ?? -1) === $chapter ? $state['auto_check'] : null];
                continue;
            }
            $attempts = is_array($state['attempts'] ?? null) ? $state['attempts'] : [];
            $last = $attempts !== [] ? end($attempts) : null;
            if ((int) ($state['stage'] ?? 0) === 4 && empty($state['exam']) && is_array($last) && (int) ($last['chapter'] ?? -1) === $chapter && empty($last['passed'])) {
                $items[] = $base + ['id' => 'exam-' . $uid . '-' . $key, 'type' => 'exam', 'since' => (int) ($last['at'] ?? $updated), 'attempt' => $last, 'can' => 'ok'];
                continue;
            }
            if ($updated > 0 && $updated < $now - self::STALLED_DAYS * 86400) {
                $items[] = $base + ['id' => 'stalled-' . $uid . '-' . $key, 'type' => 'stalled', 'since' => $updated, 'can' => 'ok'];
            }
        }
        foreach ($enrollments as $e) {
            $items[] = [
                'id' => 'enrollment-' . (int) $e['id'], 'type' => 'enrollment', 'enrollment_id' => (int) $e['id'],
                'course_key' => '', 'course_title' => (string) ($e['course_title'] ?? ''), 'learner_id' => (int) ($e['user_id'] ?? 0),
                'learner_name' => trim((string) ($e['display_name'] ?? '')), 'since' => strtotime((string) ($e['assigned_at'] ?? '')) ?: 0,
                'can' => (int) ($e['user_id'] ?? 0) === $me ? 'own' : 'ok',
            ];
        }
        foreach ($proposals as $p) {
            $items[] = [
                'id' => 'proposal-' . (int) $p['id'], 'type' => 'proposal', 'award_id' => (int) $p['id'], 'definition_id' => (int) ($p['definition_id'] ?? 0),
                'course_key' => '', 'course_title' => (string) ($p['name'] ?? ''), 'learner_id' => (int) ($p['user_id'] ?? 0),
                'since' => strtotime((string) ($p['since'] ?? '')) ?: 0, 'can' => (int) ($p['user_id'] ?? 0) === $me ? 'own' : 'ok',
            ];
        }
        // Le plus ancien d'abord : c'est celui qui attend depuis le plus longtemps.
        $order = array_flip(self::TYPES);
        usort($items, static fn (array $a, array $b): int => [$order[$a['type']], $a['since']] <=> [$order[$b['type']], $b['since']]);
        $counts = array_fill_keys(self::TYPES, 0);
        foreach ($items as $item) {
            $counts[$item['type']]++;
        }

        return ['items' => $items, 'counts' => $counts, 'total' => self::actionable($items)];
    }

    /**
     * Chapitres validés par Athena (et non annulés) ces derniers jours, que l'encadrement peut encore annuler.
     *
     * @param array<string, mixed> $state
     * @param array<string, mixed> $row
     * @param callable(string):bool $teaches
     * @return list<array<string, mixed>>
     */
    private static function autoValidated(array $state, int $uid, string $key, array $row, callable $teaches, int $me, int $now): array
    {
        $out = [];
        $chapters = is_array($state['snapshot']['chapters'] ?? null) ? $state['snapshot']['chapters'] : [];
        foreach (is_array($state['validations'] ?? null) ? $state['validations'] : [] as $index => $entry) {
            if (!is_array($entry) || empty($entry['auto']) || (int) ($entry['at'] ?? 0) < $now - self::AUTO_DAYS * 86400) {
                continue;
            }
            try {
                AcademyCurriculum::revokeAutoValidation($state, (int) $index, 1, $now);
                $revocable = true;
            } catch (\DomainException) {
                $revocable = false;
            }
            $out[] = [
                'id' => 'auto-' . $uid . '-' . $key . '-' . (int) $index, 'type' => 'auto', 'course_key' => $key,
                'course_title' => $state['snapshot']['title'] ?? '', 'learner_id' => $uid, 'chapter' => (int) $index,
                'chapter_title' => $chapters[$index]['title'] ?? '', 'since' => (int) $entry['at'], 'hours' => (int) ($entry['hours'] ?? 0),
                'revocable' => $revocable, 'final' => !empty($entry['final']), 'can' => $uid === $me ? 'own' : (!$teaches($key) ? 'not_allowed' : 'ok'), 'row' => $row,
            ];
        }

        return $out;
    }

    /**
     * Nombre du badge, depuis une lecture légère (sans le contenu des cours) : débriefings que l'on peut valider,
     * demandes d'inscription et propositions à décider, hors les siens.
     *
     * @param list<array{user_id: int|string, course_key: string, stage: mixed, completed: mixed}> $reviewRows AcademyRepository::reviewRows()
     * @param callable(string):bool $teaches
     */
    public static function badge(array $reviewRows, callable $teaches, int $enrollments, int $proposals, int $me): int
    {
        $n = $enrollments + $proposals;
        foreach ($reviewRows as $row) {
            if ((int) $row['user_id'] !== $me && (int) $row['stage'] === 6 && !self::truthy($row['completed'] ?? null) && $teaches((string) $row['course_key'])) {
                $n++;
            }
        }

        return $n;
    }

    /** @param list<array<string, mixed>> $items */
    public static function actionable(array $items): int
    {
        return count(array_filter($items, static fn (array $i): bool => in_array($i['type'], self::ACTIONABLE, true) && $i['can'] !== 'own' && $i['can'] !== 'not_allowed'));
    }

    private static function truthy(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }
}
