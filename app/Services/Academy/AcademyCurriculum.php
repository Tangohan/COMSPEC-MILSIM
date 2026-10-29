<?php

declare(strict_types=1);

namespace App\Services\Academy;

final class AcademyCurriculum
{
    public const STAGES = ['theory', 'demonstration', 'guided', 'simulation', 'assessment', 'debrief', 'validation'];
    public const EXAM_SECONDS = 300;

    public static function blank(): array
    {
        $text = ['fr'=>'', 'en'=>''];
        return ['title'=>$text, 'summary'=>$text, 'chapters'=>[
            ['title'=>$text, 'theory'=>$text, 'demonstration'=>$text, 'question'=>$text,
             'options'=>[$text,$text,$text], 'answer'=>0, 'feedback'=>$text, 'resources'=>[]],
        ]];
    }

    public static function demo(): array
    {
        $topics = [
            [
                'title' => ['fr' => 'Observer et distinguer les faits', 'en' => 'Observe and distinguish facts'],
                'theory' => ['fr' => 'Dans une scène fictive, séparez ce qui est observé de ce qui est supposé. Une observation décrit un élément visible. Une interprétation propose une explication qui reste à vérifier. Signalez explicitement toute incertitude.', 'en' => 'In a fictional scene, separate observations from assumptions. An observation describes something visible. An interpretation suggests an explanation that still needs checking. State uncertainty explicitly.'],
                'demonstration' => ['fr' => '« Un véhicule bleu est immobile » est une observation. « Le véhicule est en panne » est une hypothèse. La seconde phrase nécessite une vérification.', 'en' => '“A blue vehicle is stationary” is an observation. “The vehicle has broken down” is an assumption. The second statement needs verification.'],
                'question' => ['fr' => 'Quelle phrase décrit uniquement un fait observable ?', 'en' => 'Which sentence describes only an observable fact?'],
                'options' => [['fr' => 'Une lumière clignote sur le panneau.', 'en' => 'A light is flashing on the panel.'], ['fr' => 'Le panneau est probablement défectueux.', 'en' => 'The panel is probably faulty.'], ['fr' => 'Quelqu’un a saboté le panneau.', 'en' => 'Someone sabotaged the panel.']],
                'answer' => 0,
                'feedback' => ['fr' => 'Le clignotement est visible ; sa cause reste inconnue.', 'en' => 'The flashing is visible; its cause remains unknown.'],
            ],
            [
                'title' => ['fr' => 'Communiquer sans ambiguïté', 'en' => 'Communicate unambiguously'],
                'theory' => ['fr' => 'Un message pédagogique utile distingue le contexte, le fait observé et ce qui reste à confirmer. Lorsqu’une information manque, demandez une clarification plutôt que de la deviner. La reformulation aide à vérifier la compréhension.', 'en' => 'A useful learning message distinguishes context, observed facts and what still needs confirming. When information is missing, ask for clarification instead of guessing. Restating a message helps check understanding.'],
                'demonstration' => ['fr' => 'Message fictif : « Le dossier de l’exercice est incomplet : la date est absente. Pouvez-vous la préciser ? » La demande identifie le problème et l’information attendue.', 'en' => 'Fictional message: “The exercise record is incomplete: the date is missing. Could you clarify it?” The request identifies the issue and the information needed.'],
                'question' => ['fr' => 'Une consigne est ambiguë. Quelle réponse favorise la compréhension ?', 'en' => 'An instruction is ambiguous. Which response helps understanding?'],
                'options' => [['fr' => 'Deviner et poursuivre.', 'en' => 'Guess and continue.'], ['fr' => 'Demander une clarification précise.', 'en' => 'Ask for specific clarification.'], ['fr' => 'Ignorer la consigne.', 'en' => 'Ignore the instruction.']],
                'answer' => 1,
                'feedback' => ['fr' => 'Une question précise réduit l’ambiguïté avant de poursuivre.', 'en' => 'A specific question reduces ambiguity before continuing.'],
            ],
            [
                'title' => ['fr' => 'Analyser et débriefer', 'en' => 'Analyse and debrief'],
                'theory' => ['fr' => 'Un débriefing compare l’objectif, l’action réalisée et le résultat observé. Identifiez un point réussi et une amélioration concrète. La validation porte sur une compétence démontrée, pas sur le seul temps passé dans le cours.', 'en' => 'A debrief compares the objective, action taken and observed result. Identify one success and one concrete improvement. Validation concerns demonstrated competence, rather than time spent in the course.'],
                'demonstration' => ['fr' => 'Objectif : distinguer faits et hypothèses. Résultat : deux hypothèses ont été présentées comme des faits. Amélioration : marquer les hypothèses avant de remettre l’exercice.', 'en' => 'Objective: distinguish facts from assumptions. Result: two assumptions were presented as facts. Improvement: label assumptions before submitting the exercise.'],
                'question' => ['fr' => 'Quel élément rend un débriefing utile ?', 'en' => 'What makes a debrief useful?'],
                'options' => [['fr' => 'Le temps de connexion uniquement.', 'en' => 'Connection time alone.'], ['fr' => 'Une note sans explication.', 'en' => 'A score without explanation.'], ['fr' => 'Un constat et une amélioration concrète.', 'en' => 'An observation and a concrete improvement.']],
                'answer' => 2,
                'feedback' => ['fr' => 'Le constat doit permettre de choisir une prochaine action mesurable.', 'en' => 'The observation should help choose a measurable next action.'],
            ],
        ];
        return ['key' => 'fondamentaux', 'demo' => true, 'title' => ['fr' => 'Fondamentaux de l’apprentissage en simulation', 'en' => 'Foundations of simulation learning'], 'summary' => ['fr' => 'Cursus de démonstration : observation, communication et débriefing dans des situations fictives.', 'en' => 'Demonstration curriculum: observation, communication and debriefing in fictional situations.'], 'chapters' => $topics];
    }

    /** Validate bilingual instructor content; HTML is never executed. */
    public static function validate(array $course): array
    {
        foreach (['title', 'summary'] as $field) {
            self::bilingual($course[$field] ?? null);
        }
        if (!is_array($course['chapters'] ?? null) || !array_is_list($course['chapters']) || count($course['chapters']) < 1 || count($course['chapters']) > 20) {
            throw new \InvalidArgumentException('invalid_content');
        }
        foreach ($course['chapters'] as $chapter) {
            foreach (['title', 'theory', 'demonstration', 'question', 'feedback'] as $field) {
                self::bilingual($chapter[$field] ?? null);
            }
            if (!is_array($chapter['options'] ?? null) || !array_is_list($chapter['options']) || count($chapter['options']) !== 3 || !is_int($chapter['answer'] ?? null) || $chapter['answer'] < 0 || $chapter['answer'] > 2) {
                throw new \InvalidArgumentException('invalid_content');
            }
            foreach ($chapter['options'] as $option) {
                self::bilingual($option);
            }
            if (isset($chapter['resources']) && (!is_array($chapter['resources']) || !array_is_list($chapter['resources']) || count($chapter['resources']) > 20)) {
                throw new \InvalidArgumentException('invalid_content');
            }
            foreach ($chapter['resources'] ?? [] as $resource) {
                if (!is_array($resource)) {
                    throw new \InvalidArgumentException('invalid_content');
                }
                self::bilingual($resource['title'] ?? null);
                if (!in_array($resource['type'] ?? '', ['document', 'audio', 'diagram', 'slides'], true)) {
                    throw new \InvalidArgumentException('invalid_content');
                }
                foreach (['fr', 'en'] as $language) {
                    $link = $resource['url'][$language] ?? '';
                    if (!is_string($link) || !filter_var($link, FILTER_VALIDATE_URL) || parse_url($link, PHP_URL_SCHEME) !== 'https' || parse_url($link, PHP_URL_USER) !== null) {
                        throw new \InvalidArgumentException('invalid_content');
                    }
                }
            }
        }
        return $course;
    }

    private static function bilingual(mixed $value): void
    {
        foreach (['fr', 'en'] as $lang) {
            if (!is_array($value) || !is_string($value[$lang] ?? null) || trim($value[$lang]) === '' || strlen($value[$lang]) > 20000) {
                throw new \InvalidArgumentException('invalid_content');
            }
        }
    }

    /** Sequential progression and server-side assessment. Never accepts a client score. */
    public static function transition(array $state, array $course, string $action, array $input, int $now): array
    {
        $chapterIndex = (int) ($state['chapter'] ?? 0);
        $stage = (int) ($state['stage'] ?? 0);
        if (!empty($state['completed']) || !isset($course['chapters'][$chapterIndex])) {
            throw new \DomainException('locked');
        }
        if (!is_scalar($input['expected_stage'] ?? null) || !is_scalar($input['expected_chapter'] ?? null) || (string) $input['expected_stage'] !== (string) $stage || (string) $input['expected_chapter'] !== (string) $chapterIndex) {
            throw new \DomainException('locked');
        }
        unset($state['feedback']);
        $chapter = $course['chapters'][$chapterIndex];
        if ($action === 'advance' && in_array($stage, [0, 1], true)) {
            $state['stage'] = $stage + 1;
        } elseif ($action === 'practice' && in_array($stage, [2, 3], true)) {
            $answer = $input['answer'] ?? '';
            $correct = is_scalar($answer) && (string) $answer === (string) $chapter['answer'];
            $state['practice'][] = ['chapter' => $chapterIndex, 'stage' => $stage, 'correct' => $correct, 'at' => $now];
            if ($correct) {
                $state['stage'] = $stage + 1;
            }
            $state['feedback'] = $correct ? 'correct' : 'retry';
        } elseif ($action === 'exam_start' && $stage === 4 && empty($state['exam'])) {
            $state['exam'] = ['started' => $now, 'deadline' => $now + self::EXAM_SECONDS, 'token' => bin2hex(random_bytes(16))];
        } elseif ($action === 'exam_submit' && $stage === 4 && !empty($state['exam'])) {
            if (!is_string($input['token'] ?? null) || !hash_equals($state['exam']['token'], $input['token'])) {
                throw new \DomainException('locked');
            }
            $passed = $now < $state['exam']['deadline'] && is_scalar($input['answer'] ?? null) && (string) $input['answer'] === (string) $chapter['answer'];
            $state['attempts'][] = ['chapter' => $chapterIndex, 'score' => $passed ? 100 : 0, 'expired' => $now >= $state['exam']['deadline'], 'at' => $now];
            unset($state['exam']);
            $state['feedback'] = $passed ? 'passed' : 'failed';
            if ($passed) {
                $state['stage'] = 5;
            }
        } elseif ($action === 'debrief' && $stage === 5) {
            $reflection = $input['reflection'] ?? '';
            if (!is_string($reflection) || mb_strlen(trim($reflection)) < 20 || mb_strlen($reflection) > 5000) {
                throw new \DomainException('reflection_required');
            }
            $state['debriefs'][$chapterIndex] = trim($reflection);
            $state['stage'] = 6;
        } else {
            throw new \DomainException('locked');
        }
        $state['updated'] = $now;
        return $state;
    }

    public static function validateChapter(array $state, array $course, int $instructorId, string $comment, int $now): array
    {
        if (($state['stage'] ?? 0) !== 6 || !empty($state['completed']) || mb_strlen(trim($comment)) < 10 || mb_strlen($comment) > 5000) {
            throw new \DomainException('locked');
        }
        unset($state['feedback']);
        $index = $state['chapter'];
        $state['validations'][$index] = ['by' => $instructorId, 'comment' => trim($comment), 'at' => $now];
        if ($index + 1 === count($course['chapters'])) {
            $state['completed'] = true;
        } else {
            $state['chapter']++;
            $state['stage'] = 0;
        }
        $state['updated'] = $now;
        return $state;
    }
}
