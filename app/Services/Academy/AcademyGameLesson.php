<?php

declare(strict_types=1);

namespace App\Services\Academy;

/**
 * Lecteur Academy en jeu (app Academy du téléphone ATAK, écrans posables) : leçon découpée en pages de texte court,
 * au format ligne de COMSPEC Link (champs séparés par tabulation, « \n » littéral pour un retour à la ligne).
 * Aucune réponse de question ni contenu d'évaluation ne sort d'ici : les exercices restent sur le site.
 */
final class AcademyGameLesson
{
    public const PAGE_CHARS = 1100;
    /** Sortie de COMSPEC Link limitée à 8000 octets : une réponse en reste loin, la suite part dans une autre. */
    public const CHUNK_BYTES = 7000;

    public static function text(mixed $value, string $lang): string
    {
        if (!is_array($value)) {
            return is_string($value) ? $value : '';
        }
        $t = $value[$lang] ?? $value['fr'] ?? '';

        return is_string($t) ? $t : '';
    }

    public static function field(string $value, int $max = 4000): string
    {
        $value = str_replace(["\r\n", "\r", "\t"], ["\n", "\n", ' '], trim($value));
        if (mb_strlen($value) > $max) {
            $value = mb_substr($value, 0, $max - 1) . '…';
        }

        return str_replace("\n", '\\n', $value);
    }

    /**
     * Pages d'une leçon (chapitre) : [genre, étape du parcours, titre, texte].
     * Étape 0 = cours (théorie), 1 = démonstration : ce sont les seules que le jeu peut marquer comme vues.
     *
     * @return list<array{0:string,1:int,2:string,3:string}>
     */
    public static function pages(array $course, int $chapterIndex, string $lang): array
    {
        $en = $lang === 'en';
        $chapters = $course['chapters'] ?? [];
        $chapter = $chapters[$chapterIndex] ?? null;
        if (!is_array($chapter)) {
            return [];
        }
        $title = self::text($chapter['title'] ?? null, $lang);
        $intro = sprintf($en ? 'Lesson %d of %d' : 'Leçon %d sur %d', $chapterIndex + 1, count($chapters));
        $summary = $chapterIndex === 0 ? self::text($course['summary'] ?? null, $lang) : '';
        $pages = [['intro', 0, $title, trim($intro . ($summary !== '' ? "\n\n" . $summary : ''))]];
        foreach (preg_split('/\n\s*\n/u', str_replace("\r\n", "\n", self::text($chapter['theory'] ?? null, $lang))) ?: [] as $block) {
            $lines = explode("\n", trim($block));
            if (trim($block) === '') {
                continue;
            }
            // Bloc « TITRE\nparagraphe » : la première ligne courte devient le titre de la page.
            $head = $title;
            if (count($lines) > 1 && mb_strlen(trim($lines[0])) <= 90) {
                $head = trim(array_shift($lines));
            }
            self::push($pages, 'theory', 0, $head, implode("\n", $lines), $en);
        }
        self::push($pages, 'demo', 1, $en ? 'Demonstration' : 'Démonstration', self::text($chapter['demonstration'] ?? null, $lang), $en);
        foreach ([2 => $en ? 'Guided case' : 'Cas guidé', 3 => $en ? 'Independent case' : 'Cas sans aide'] as $stage => $label) {
            $q = AcademyCurriculum::questionForStage($chapter, $stage);
            if ($stage === 3 && !isset($chapter['simulation'])) {
                continue;
            }
            $body = trim(self::text($q['brief'] ?? null, $lang) . "\n\n" . self::text($q['question'] ?? null, $lang));
            foreach (array_values($q['options'] ?? []) as $i => $option) {
                $body .= "\n" . chr(65 + $i) . '. ' . self::text($option, $lang);
            }
            // Réponse et explication jamais envoyées : l'exercice se fait sur le site.
            self::push($pages, 'case', $stage, $label, $body, $en);
        }
        if (isset($chapter['debrief']) && is_array($chapter['debrief'])) {
            $body = self::text($chapter['debrief']['prompt'] ?? null, $lang);
            foreach ($chapter['debrief']['criteria'] ?? [] as $criterion) {
                $body .= "\n• " . self::text($criterion, $lang);
            }
            self::push($pages, 'debrief', 5, $en ? 'Debrief' : 'Débriefing', $body, $en);
        }
        $pages[] = ['end', 6, $en ? 'Continue on Academy' : 'Suite sur Academy', $en
            ? 'Exercises, the assessment, your debrief and the instructor validation are done on the Academy website.'
            : 'Les exercices, l’évaluation, votre débriefing et la validation par l’instructeur se font sur le site Academy.'];

        return $pages;
    }

    /** @param list<array{0:string,1:int,2:string,3:string}> $pages */
    private static function push(array &$pages, string $kind, int $stage, string $title, string $body, bool $en): void
    {
        $body = trim($body);
        if ($body === '') {
            return;
        }
        $parts = [];
        $cur = '';
        foreach (preg_split('/(?<=[.!?…:])\s+/u', $body) ?: [$body] as $sentence) {
            if ($cur !== '' && mb_strlen($cur) + mb_strlen($sentence) + 1 > self::PAGE_CHARS) {
                $parts[] = $cur;
                $cur = '';
            }
            $cur = $cur === '' ? $sentence : $cur . ' ' . $sentence;
        }
        $parts[] = $cur;
        foreach ($parts as $i => $part) {
            $pages[] = [$kind, $stage, $i === 0 ? $title : $title . ($en ? ' (continued)' : ' (suite)'), mb_substr($part, 0, self::PAGE_CHARS + 200)];
        }
    }

    /** Leçon visible : instructeur, toutes ; élève, celles déjà atteintes (toutes une fois le cours terminé). */
    public static function chapterAllowed(?array $state, int $chapter, bool $staff): bool
    {
        if ($staff) {
            return true;
        }
        if ($state === null) {
            return false;
        }

        return !empty($state['completed']) || $chapter <= (int) ($state['chapter'] ?? 0);
    }

    /**
     * Étape lue jusqu'au bout dans le jeu : cours (0) puis démonstration (1) de la leçon en cours. Tout autre cas
     * (déjà passée, autre leçon, exercice) ne change rien : null.
     */
    public static function seen(array $state, int $chapter, int $stage, int $now): ?array
    {
        if (!in_array($stage, [0, 1], true) || !empty($state['completed'])
            || (int) ($state['chapter'] ?? -1) !== $chapter || (int) ($state['stage'] ?? -1) !== $stage
            || !is_array($state['snapshot'] ?? null)) {
            return null;
        }

        return AcademyCurriculum::transition($state, $state['snapshot'], 'advance', ['expected_stage' => $stage, 'expected_chapter' => $chapter], $now);
    }

    /**
     * Réponse « courses » : en-tête, cours suivis (C), puis pour l'instructeur tous les cours de la communauté (S).
     *
     * @param array<string, array<string, mixed>> $states
     * @param list<array<string, mixed>> $courses
     */
    public static function coursesBody(array $states, array $courses, bool $staff, string $lang): string
    {
        $out = "H\t" . (int) $staff . "\t" . $lang . "\n";
        foreach ($states as $key => $state) {
            if ($key === '__notes' || !is_array($state['snapshot'] ?? null)) {
                continue;
            }
            $snap = $state['snapshot'];
            $ch = (int) ($state['chapter'] ?? 0);
            $line = implode("\t", ['C', self::field((string) $key, 64), self::field(self::text($snap['title'] ?? null, $lang), 90), $ch,
                count($snap['chapters'] ?? []), (int) ($state['stage'] ?? 0), (int) !empty($state['completed']),
                self::field(self::text($snap['chapters'][$ch]['title'] ?? null, $lang), 90)]) . "\n";
            if (strlen($out) + strlen($line) > self::CHUNK_BYTES) {
                break;
            }
            $out .= $line;
        }
        if ($staff) {
            foreach ($courses as $course) {
                $line = implode("\t", ['S', self::field((string) ($course['key'] ?? ''), 64), self::field(self::text($course['title'] ?? null, $lang), 90),
                    count($course['chapters'] ?? []), (int) ($course['published'] ?? true)]) . "\n";
                if (strlen($out) + strlen($line) > self::CHUNK_BYTES) {
                    break;
                }
                $out .= $line;
            }
        }

        return $out;
    }

    /** Réponse « lesson » à partir de la page $from ; « next » = première page à redemander (0 : fin). */
    public static function lessonBody(array $course, string $key, int $chapter, string $lang, int $from, int $stage): string
    {
        $pages = self::pages($course, $chapter, $lang);
        $from = max(0, min($from, count($pages)));
        $lines = '';
        $next = 0;
        for ($i = $from; $i < count($pages); $i++) {
            [$kind, $st, $title, $body] = $pages[$i];
            $line = implode("\t", ['P', $i, $kind, $st, self::field($title, 160), self::field($body, self::PAGE_CHARS + 200)]) . "\n";
            if ($lines !== '' && strlen($lines) + strlen($line) > self::CHUNK_BYTES - 600) {
                $next = $i;
                break;
            }
            $lines .= $line;
        }
        $res = '';
        if ($from === 0) {
            foreach ($course['chapters'][$chapter]['resources'] ?? [] as $r) {
                $url = is_array($r) ? self::text($r['url'] ?? null, $lang) : '';
                // Diapositives Google Slides : ouvertes par l'app Briefing du téléphone (même chargement que ses présentations).
                // Deux au plus : la réponse garde sa place pour les pages (600 octets réservés à l'en-tête).
                if (($r['type'] ?? '') === 'slides' && str_starts_with($url, 'https://docs.google.com/presentation/') && strlen($url) <= 200 && substr_count($res, "\n") < 2) {
                    $res .= implode("\t", ['R', self::field(self::text($r['title'] ?? null, $lang), 60), self::field($url, 200)]) . "\n";
                }
            }
        }
        $chapters = $course['chapters'] ?? [];
        $head = implode("\t", ['L', self::field($key, 64), $chapter, self::field(self::text($course['title'] ?? null, $lang), 90),
            self::field(self::text($chapters[$chapter]['title'] ?? null, $lang), 90), count($chapters), count($pages), $stage, $from, $next]) . "\n";

        return $head . $res . $lines;
    }
}
