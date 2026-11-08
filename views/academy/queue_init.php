<?php
/**
 * File « à traiter » de l'encadrement (App\Services\Academy\AcademyInstructorQueue) : libellés et liens partagés par le
 * bloc du tableau de bord et la file de l'espace instructeur. Chaque action renvoie au formulaire existant côté serveur.
 *
 * @var array $queue
 */
$queue = is_array($queue ?? null) ? $queue : ['items' => [], 'counts' => [], 'total' => 0, 'sessions' => []];
$qMembers = $queueMembers ?? ($instructor['members'] ?? []);
$qName = static function (array $item) use ($qMembers, $t): string {
    $id = (int) ($item['learner_id'] ?? 0);
    return $qMembers[$id] ?? (($item['learner_name'] ?? '') !== '' ? (string) $item['learner_name'] : sprintf($t('ins_member_number'), $id));
};
$qTitle = static function (mixed $value) use ($local, $t): string {
    $text = is_array($value) ? $local($value) : (string) $value;
    return $text !== '' ? $text : $t('untitled_course');
};
$qAge = static function (int $since) use ($t): string {
    if ($since <= 0) return '—';
    $days = intdiv(max(0, time() - $since), 86400);
    return $days === 0 ? $t('q_age_today') : sprintf($t($days === 1 ? 'q_age_day' : 'q_age_days'), $days);
};
$qDate = static fn (int $since): string => $since > 0 ? (new DateTimeImmutable('@' . $since))->setTimezone(new DateTimeZone('Europe/Paris'))->format('d/m/Y H:i') : '';
/* Ce que l'élément attend, en une ligne. */
$qDetail = static function (array $item) use ($t, $local): string {
    $chapter = $t('chapter') . ' ' . ((int) ($item['chapter'] ?? 0) + 1) . (is_array($item['chapter_title'] ?? null) && $local($item['chapter_title']) !== '' ? ' · ' . $local($item['chapter_title']) : '');
    return match ($item['type']) {
        'chapter' => $chapter . (!empty($item['final']) ? ' · ' . $t('ins_task_final') : '') . (!empty($item['auto_check']) ? ' · ' . $t('auto_q_flag') : ''),
        'exam' => $chapter . ' · ' . (!empty($item['attempt']['expired']) ? $t('ins_exam_expired') : sprintf($t('ins_exam_score'), (int) ($item['attempt']['score'] ?? 0))),
        'stalled' => $chapter,
        'auto' => $chapter . ' · ' . sprintf($t('auto_q_detail'), (int) ($item['hours'] ?? 0)),
        'enrollment' => $t('q_detail_enrollment'),
        'proposal' => $t('q_detail_proposal'),
        default => '',
    };
};
/* Écran exact de l'action, hors validation rapide. */
$qUrl = static function (array $item) use ($link): string {
    return match ($item['type']) {
        'enrollment' => training_lms_admin_url('enrollments'),
        'proposal' => url('back-office/referentiels/qualifications/' . (int) $item['definition_id']) . '#a-valider',
        'exam', 'stalled' => $link('instructor') . '#eleve-' . (int) $item['learner_id'] . '-' . $item['course_key'],
        default => $link('instructor') . '?type=chapter#' . $item['id'],
    };
};
/* Action groupée possible : URL et champs du formulaire existant (accepter / refuser). */
$qBulk = static function (array $item, string $return) use ($link): ?array {
    if (($item['can'] ?? '') !== 'ok') return null;
    return match ($item['type']) {
        'chapter' => ['approve' => ['url' => $link('action/validate'), 'fields' => ['course' => $item['course_key'], 'learner' => (int) $item['learner_id'], 'expected_chapter' => (int) $item['chapter'], 'return' => $return]], 'refuse' => null],
        'enrollment' => ['approve' => ['url' => url('formation/enrollments/' . (int) $item['enrollment_id'] . '/approve'), 'fields' => ['return_to' => 'academy']], 'refuse' => ['url' => url('formation/enrollments/' . (int) $item['enrollment_id'] . '/decline'), 'fields' => ['return_to' => 'academy']]],
        'proposal' => ['approve' => ['url' => url('back-office/referentiels/qualifications/propositions/' . (int) $item['award_id']), 'fields' => ['decision' => 'approve', 'definition_id' => (int) $item['definition_id'], 'return_to' => 'academy']], 'refuse' => ['url' => url('back-office/referentiels/qualifications/propositions/' . (int) $item['award_id']), 'fields' => ['decision' => 'refuse', 'definition_id' => (int) $item['definition_id'], 'return_to' => 'academy']]],
        default => null,
    };
};
$qHidden = static function (array $fields) use ($h): string {
    $out = \App\Core\Csrf::field();
    foreach ($fields as $name => $value) {
        $out .= '<input type="hidden" name="' . $h($name) . '" value="' . $h($value) . '">';
    }
    return $out;
};
