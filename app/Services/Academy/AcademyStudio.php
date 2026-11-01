<?php

declare(strict_types=1);

namespace App\Services\Academy;

/**
 * Brouillons du Studio : le formulaire peut être incomplet, la publication reste soumise à
 * AcademyCurriculum::validate(). Les champs vides des sections facultatives sont retirés pour que
 * le parcours retombe sur la question de référence du chapitre, comme les cursus historiques.
 */
final class AcademyStudio
{
    public const COVERS = ['night', 'fog', 'hero'];
    public const MAX_CHAPTERS = 20;

    /** @return array{fr:string,en:string} */
    private static function text(mixed $value): array
    {
        $out = [];
        foreach (['fr', 'en'] as $lang) {
            $raw = is_array($value) && is_string($value[$lang] ?? null) ? $value[$lang] : '';
            $out[$lang] = mb_substr(trim(str_replace("\r\n", "\n", $raw)), 0, 20000);
        }
        return $out;
    }

    private static function filled(array $text): bool
    {
        return $text['fr'] !== '' || $text['en'] !== '';
    }

    private static function complete(array $text): bool
    {
        return $text['fr'] !== '' && $text['en'] !== '';
    }

    public static function blankChapter(?array $title = null): array
    {
        $empty = ['fr' => '', 'en' => ''];
        return ['title' => $title ?? $empty, 'theory' => $empty, 'demonstration' => $empty, 'question' => $empty,
            'options' => [$empty, $empty, $empty], 'answer' => 0, 'feedback' => $empty, 'resources' => []];
    }

    public static function blankCourse(): array
    {
        $empty = ['fr' => '', 'en' => ''];
        return ['title' => $empty, 'summary' => $empty, 'cover' => 'night', 'chapters' => [self::blankChapter()]];
    }

    /** Question à trois choix : le formulaire envoie toujours trois options et l'index de la bonne. */
    private static function question(mixed $form): array
    {
        $form = is_array($form) ? $form : [];
        $options = [];
        for ($i = 0; $i < 3; $i++) {
            $options[] = self::text($form['options'][$i] ?? null);
        }
        $answer = (int) ($form['answer'] ?? 0);
        return ['question' => self::text($form['question'] ?? null), 'options' => $options,
            'answer' => $answer >= 0 && $answer <= 2 ? $answer : 0, 'feedback' => self::text($form['feedback'] ?? null)];
    }

    private static function questionFilled(array $question): bool
    {
        foreach (array_merge([$question['question'], $question['feedback']], $question['options']) as $text) {
            if (self::filled($text)) {
                return true;
            }
        }
        return false;
    }

    private static function questionComplete(array $question): bool
    {
        foreach (array_merge([$question['question'], $question['feedback']], $question['options']) as $text) {
            if (!self::complete($text)) {
                return false;
            }
        }
        return true;
    }

    /** @return list<array{fr:string,en:string}> */
    private static function criteria(mixed $list): array
    {
        $out = [];
        foreach (is_array($list) ? array_slice($list, 0, 20) : [] as $item) {
            $text = self::text($item);
            if (self::filled($text)) {
                $out[] = $text;
            }
        }
        return $out;
    }

    /**
     * @param array $form   champ « course » du formulaire Studio
     * @param array $before brouillon enregistré : seules ses diapositives importées peuvent être conservées
     */
    public static function fromForm(array $form, array $before): array
    {
        $course = [
            'title' => self::text($form['title'] ?? null),
            'summary' => self::text($form['summary'] ?? null),
            'cover' => in_array($form['cover'] ?? null, self::COVERS, true) ? $form['cover'] : 'night',
        ];
        $license = self::text($form['license'] ?? null);
        if (self::filled($license)) {
            $course['license'] = $license;
        }
        $version = trim((string) (is_scalar($form['version'] ?? null) ? $form['version'] : ''));
        if ($version !== '' && preg_match('/\A[a-zA-Z0-9._-]{1,32}\z/', $version)) {
            $course['version'] = $version;
        }
        $minutes = (int) (is_scalar($form['duration_minutes'] ?? null) ? $form['duration_minutes'] : 0);
        if ($minutes >= 5 && $minutes <= 1440) {
            $course['duration_minutes'] = $minutes;
        }
        foreach (['replaces'] as $kept) {
            if (isset($before[$kept]) && is_string($before[$kept])) {
                $course[$kept] = $before[$kept];
            }
        }
        $imported = [];
        foreach ($before['chapters'] ?? [] as $chapter) {
            foreach ($chapter['resources'] ?? [] as $resource) {
                if (isset($resource['file'])) {
                    $imported[$resource['file']] = $resource;
                }
            }
        }
        $course['chapters'] = [];
        $chapters = is_array($form['chapters'] ?? null) ? array_values($form['chapters']) : [];
        foreach (array_slice($chapters, 0, self::MAX_CHAPTERS) as $raw) {
            $raw = is_array($raw) ? $raw : [];
            $chapter = ['title' => self::text($raw['title'] ?? null), 'theory' => self::text($raw['theory'] ?? null),
                'demonstration' => self::text($raw['demonstration'] ?? null)] + self::question($raw);
            foreach (['guided', 'simulation'] as $stage) {
                $section = is_array($raw[$stage] ?? null) ? $raw[$stage] : [];
                $case = self::question($section) + ['brief' => self::text($section['brief'] ?? null)];
                if ($stage === 'guided') {
                    $case['hint'] = self::text($section['hint'] ?? null);
                    if (!self::filled($case['hint'])) {
                        unset($case['hint']);
                    }
                }
                if (self::questionFilled($case) || self::filled($case['brief']) || isset($case['hint'])) {
                    $chapter[$stage] = $case;
                }
            }
            $assessment = is_array($raw['assessment'] ?? null) ? $raw['assessment'] : [];
            $questions = [];
            foreach (is_array($assessment['questions'] ?? null) ? array_slice(array_values($assessment['questions']), 0, 50) : [] as $question) {
                $question = self::question($question);
                if (self::questionFilled($question)) {
                    $questions[] = $question;
                }
            }
            $instructions = self::text($assessment['instructions'] ?? null);
            if ($questions !== [] || self::filled($instructions)) {
                $examMinutes = max(1, min(120, (int) ($assessment['minutes'] ?? 5)));
                $pass = max(1, min(100, (int) ($assessment['pass_percent'] ?? 80)));
                $chapter['assessment'] = ['instructions' => $instructions, 'seconds' => $examMinutes * 60, 'pass_percent' => $pass, 'questions' => $questions];
            }
            foreach (['debrief' => 'prompt', 'validation' => 'brief'] as $stage => $field) {
                $section = is_array($raw[$stage] ?? null) ? $raw[$stage] : [];
                $text = self::text($section[$field] ?? null);
                $criteria = self::criteria($section['criteria'] ?? null);
                if (self::filled($text) || $criteria !== []) {
                    $chapter[$stage] = [$field => $text, 'criteria' => $criteria];
                }
            }
            $chapter['resources'] = [];
            foreach (is_array($raw['resources'] ?? null) ? array_slice(array_values($raw['resources']), 0, 20) : [] as $resource) {
                $resource = is_array($resource) ? $resource : [];
                $file = is_string($resource['file'] ?? null) ? $resource['file'] : '';
                if ($file !== '') {
                    // Les diapositives importées ne sont jamais décrites par le navigateur : on reprend la version serveur.
                    if (isset($imported[$file])) {
                        $title = self::text($resource['title'] ?? null);
                        $chapter['resources'][] = array_replace($imported[$file], self::complete($title) ? ['title' => $title] : []);
                    }
                    continue;
                }
                $entry = ['type' => in_array($resource['type'] ?? '', ['document', 'audio', 'diagram', 'slides'], true) ? $resource['type'] : 'document',
                    'title' => self::text($resource['title'] ?? null)];
                $body = self::text($resource['body'] ?? null);
                // Fiche intégrée (texte) ou lien externe : jamais les deux.
                $entry += self::filled($body) ? ['body' => $body] : ['url' => self::text($resource['url'] ?? null)];
                if (self::filled($entry['title']) || self::filled($body) || self::filled($entry['url'] ?? ['fr' => '', 'en' => ''])) {
                    $chapter['resources'][] = $entry;
                }
            }
            $course['chapters'][] = $chapter;
        }
        if ($course['chapters'] === []) {
            $course['chapters'][] = self::blankChapter();
        }
        return $course;
    }

    /** Applique une action de structure (ajout, retrait, déplacement) choisie dans le Studio. */
    public static function applyOperation(array $course, string $operation): array
    {
        $parts = explode(':', $operation);
        $index = (int) ($parts[1] ?? -1);
        $count = count($course['chapters']);
        switch ($parts[0]) {
            case 'add_chapter':
                if ($count < self::MAX_CHAPTERS) {
                    $course['chapters'][] = self::blankChapter();
                }
                break;
            case 'remove_chapter':
                if ($count > 1 && isset($course['chapters'][$index])) {
                    array_splice($course['chapters'], $index, 1);
                }
                break;
            case 'up':
            case 'down':
                $target = $parts[0] === 'up' ? $index - 1 : $index + 1;
                if (isset($course['chapters'][$index], $course['chapters'][$target])) {
                    [$course['chapters'][$index], $course['chapters'][$target]] = [$course['chapters'][$target], $course['chapters'][$index]];
                }
                break;
            case 'remove_question':
                $question = (int) ($parts[2] ?? -1);
                if (isset($course['chapters'][$index]['assessment']['questions'][$question])) {
                    array_splice($course['chapters'][$index]['assessment']['questions'], $question, 1);
                }
                break;
            case 'remove_resource':
                $resource = (int) ($parts[2] ?? -1);
                if (isset($course['chapters'][$index]['resources'][$resource])) {
                    array_splice($course['chapters'][$index]['resources'], $resource, 1);
                }
                break;
            case 'remove_criterion':
                $stage = $parts[2] ?? '';
                $criterion = (int) ($parts[3] ?? -1);
                if (in_array($stage, ['debrief', 'validation'], true) && isset($course['chapters'][$index][$stage]['criteria'][$criterion])) {
                    array_splice($course['chapters'][$index][$stage]['criteria'], $criterion, 1);
                }
                break;
        }
        return $course;
    }

    /**
     * Part des textes français déjà traduits en anglais (les deux langues sont exigées à la publication).
     *
     * @return array{0:int,1:int} [textes français remplis, dont traduits]
     */
    public static function translation(array $course): array
    {
        $total = $done = 0;
        $walk = static function (mixed $node) use (&$walk, &$total, &$done): void {
            if (!is_array($node)) {
                return;
            }
            if (count($node) === 2 && is_string($node['fr'] ?? null) && is_string($node['en'] ?? null)) {
                if (trim($node['fr']) !== '') {
                    $total++;
                    $done += (int) (trim($node['en']) !== '');
                }
                return;
            }
            foreach ($node as $child) {
                $walk($child);
            }
        };
        $walk($course);
        return [$total, $done];
    }

    /** Diapositives encore référencées : les autres fichiers du cours peuvent être supprimés. */
    public static function files(array $course): array
    {
        $files = [];
        foreach ($course['chapters'] ?? [] as $chapter) {
            foreach ($chapter['resources'] ?? [] as $resource) {
                if (isset($resource['file'])) {
                    $files[] = $resource['file'];
                }
            }
        }
        return $files;
    }

    /**
     * Points à compléter avant publication, rattachés à l'étape qui les corrige.
     *
     * @return list<array{chapter:?int, stage:int, issue:string}>
     */
    public static function issues(array $course): array
    {
        $issues = [];
        if (!self::complete($course['title'] ?? ['fr' => '', 'en' => ''])) {
            $issues[] = ['chapter' => null, 'stage' => 0, 'issue' => 'issue_course_title'];
        }
        if (!self::complete($course['summary'] ?? ['fr' => '', 'en' => ''])) {
            $issues[] = ['chapter' => null, 'stage' => 0, 'issue' => 'issue_course_summary'];
        }
        if (isset($course['license']) && !self::complete($course['license'])) {
            $issues[] = ['chapter' => null, 'stage' => 0, 'issue' => 'issue_course_license'];
        }
        foreach ($course['chapters'] ?? [] as $index => $chapter) {
            $add = static function (int $stage, string $issue) use (&$issues, $index): void {
                $issues[] = ['chapter' => $index, 'stage' => $stage, 'issue' => $issue];
            };
            if (!self::complete($chapter['title'])) {
                $add(0, 'issue_chapter_title');
            }
            if (!self::complete($chapter['theory'])) {
                $add(0, 'issue_theory');
            }
            foreach ($chapter['resources'] ?? [] as $resource) {
                if (!self::complete($resource['title']) || (!isset($resource['file']) && !isset($resource['body'])
                    && (!self::complete($resource['url'] ?? ['fr' => '', 'en' => '']) || !self::httpsUrls($resource['url'])))) {
                    $add(0, 'issue_resource');
                    break;
                }
            }
            if (!self::complete($chapter['demonstration'])) {
                $add(1, 'issue_demonstration');
            }
            if (!self::questionComplete($chapter)) {
                $add(2, 'issue_reference_question');
            }
            if (isset($chapter['guided']) && (!self::questionComplete($chapter['guided']) || !self::complete($chapter['guided']['brief'])
                || (isset($chapter['guided']['hint']) && !self::complete($chapter['guided']['hint'])))) {
                $add(2, 'issue_guided');
            }
            if (isset($chapter['simulation']) && (!self::questionComplete($chapter['simulation']) || !self::complete($chapter['simulation']['brief']))) {
                $add(3, 'issue_simulation');
            }
            if (isset($chapter['assessment'])) {
                $assessment = $chapter['assessment'];
                $ok = self::complete($assessment['instructions']) && $assessment['questions'] !== [];
                foreach ($assessment['questions'] as $question) {
                    $ok = $ok && self::questionComplete($question);
                }
                if (!$ok) {
                    $add(4, 'issue_assessment');
                }
            }
            foreach (['debrief' => [5, 'prompt'], 'validation' => [6, 'brief']] as $stage => [$number, $field]) {
                if (!isset($chapter[$stage])) {
                    continue;
                }
                $ok = self::complete($chapter[$stage][$field]) && $chapter[$stage]['criteria'] !== [];
                foreach ($chapter[$stage]['criteria'] as $criterion) {
                    $ok = $ok && self::complete($criterion);
                }
                if (!$ok) {
                    $add($number, 'issue_' . $stage);
                }
            }
        }
        return $issues;
    }

    private static function httpsUrls(array $urls): bool
    {
        foreach (['fr', 'en'] as $lang) {
            if (!filter_var($urls[$lang], FILTER_VALIDATE_URL) || parse_url($urls[$lang], PHP_URL_SCHEME) !== 'https') {
                return false;
            }
        }
        return true;
    }
}
