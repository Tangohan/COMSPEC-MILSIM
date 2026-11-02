<?php

declare(strict_types=1);

namespace App\Services\Academy\BuiltIn;

/**
 * Briques communes des cours intégrés de l’Academy (même format que le cours JTAC : chapitres de sept étapes,
 * questions à trois choix, évaluation chronométrée, débriefing et validation instructeur). Chaque cours intégré
 * déclare la qualification Athena Academy qu’il délivre (clé « qualification ») : à la validation du dernier
 * chapitre, la qualification est créée si besoin dans le référentiel de la communauté, puis attribuée
 * (App\Services\Academy\AcademyBuiltInQualifications).
 */
final class AcademyCourseKit
{
    /** @return array{fr: string, en: string} */
    public static function text(string $fr, string $en): array
    {
        return ['fr' => $fr, 'en' => $en];
    }

    /**
     * Question à trois choix ; la réponse et l’explication restent dans la copie du cours côté serveur.
     *
     * @param list<array{fr: string, en: string}> $options
     * @return array<string, mixed>
     */
    public static function question(string $fr, string $en, array $options, int $answer, string $feedbackFr, string $feedbackEn): array
    {
        return [
            'question' => self::text($fr, $en),
            'options' => $options,
            'answer' => $answer,
            'feedback' => self::text($feedbackFr, $feedbackEn),
        ];
    }

    /**
     * Cas guidé (avec indice) ou simulation autonome (sans indice) : une question précédée d’un énoncé.
     *
     * @param array<string, mixed> $question self::question(...)
     * @param array{fr: string, en: string} $brief
     * @param array{fr: string, en: string}|null $hint seulement pour le cas guidé
     * @return array<string, mixed>
     */
    public static function stage(array $question, array $brief, ?array $hint = null): array
    {
        return $question + ['brief' => $brief] + ($hint !== null ? ['hint' => $hint] : []);
    }

    /**
     * Les sept étapes d’un chapitre. Le cas guidé renseigne aussi la question historique du chapitre, pour que
     * l’éditeur standard puisse encore l’afficher.
     *
     * @param array{fr: string, en: string} $title
     * @param array{fr: string, en: string} $theory
     * @param array{fr: string, en: string} $demonstration
     * @param array<string, mixed> $guided self::stage(question, brief, hint)
     * @param array<string, mixed> $simulation self::stage(question, brief)
     * @param list<array<string, mixed>> $questions évaluation (au moins cinq)
     * @param array{fr: string, en: string} $debriefPrompt
     * @param list<array{fr: string, en: string}> $debriefCriteria
     * @param list<array{fr: string, en: string}> $validationCriteria
     * @param list<array<string, mixed>> $resources fiches du cours (self::sheet)
     * @return array<string, mixed>
     */
    public static function chapter(array $title, array $theory, array $demonstration, array $guided, array $simulation, array $questions, array $debriefPrompt, array $debriefCriteria, array $validationCriteria, array $resources): array
    {
        $count = count($questions);
        $needed = (int) ceil($count * 0.8);

        return [
            'title' => $title,
            'theory' => $theory,
            'demonstration' => $demonstration,
            'question' => $guided['question'],
            'options' => $guided['options'],
            'answer' => $guided['answer'],
            'feedback' => $guided['feedback'],
            'guided' => $guided,
            'simulation' => $simulation,
            'assessment' => [
                'instructions' => self::text(
                    "Répondez aux {$count} questions sans aide. Vous disposez de dix minutes ; au moins {$needed} bonnes réponses (80 %) sont nécessaires. La note est calculée par le site et chaque tentative est conservée. Un dépassement du temps compte comme une tentative non validée. Après réussite, rédigez votre débriefing ; le chapitre est ensuite validé par un instructeur distinct du stagiaire.",
                    "Answer the {$count} questions without assistance. You have ten minutes; at least {$needed} correct answers (80%) are required. The site calculates the score and keeps every attempt. Running out of time counts as an unsuccessful attempt. After passing, write your debrief; an instructor other than the learner then validates the chapter."
                ),
                'seconds' => 600,
                'pass_percent' => 80,
                'questions' => $questions,
            ],
            'debrief' => [
                'prompt' => $debriefPrompt,
                'criteria' => $debriefCriteria,
            ],
            'validation' => [
                'brief' => self::text(
                    'La validation confirme uniquement les acquis de ce chapitre de formation de jeu (Arma 3). Un instructeur habilité, distinct du stagiaire, examine les tentatives et le débriefing selon les critères ci-dessous ; il peut aussi faire répéter le compte rendu ou la phrase radio en session. Il laisse une justification écrite. Une réussite au questionnaire ne remplace pas cette revue. Si un critère n’est pas démontré, l’instructeur organise une reprise avant de valider. La qualification Athena Academy délivrée à la fin du cours est une qualification de jeu, inspirée d’un standard OTAN public, sans valeur de qualification réelle.',
                    'Validation confirms learning within this in-game training chapter (Arma 3) only. An authorised instructor other than the learner reviews the attempts and debrief against the criteria below; they may also have the learner repeat the report or radio call during a session. They record a written justification. Passing the quiz does not replace this review. If a criterion has not been demonstrated, the instructor arranges further practice before validating. The Athena Academy qualification awarded at the end of the course is an in-game qualification, inspired by a public NATO standard, with no real-world qualification value.'
                ),
                'criteria' => $validationCriteria,
            ],
            'resources' => $resources,
        ];
    }

    /**
     * Fiche mémo (document intégré, texte brut bilingue).
     *
     * @param array{fr: string, en: string} $title
     * @param array{fr: string, en: string} $body
     * @return array<string, mixed>
     */
    public static function sheet(array $title, array $body): array
    {
        return ['title' => $title, 'type' => 'document', 'body' => $body];
    }

    /**
     * Cours intégré complet.
     *
     * @param array{fr: string, en: string} $title
     * @param array{fr: string, en: string} $summary
     * @param list<array<string, mixed>> $chapters
     * @param array{code: string, license_code: string, name: array{fr: string, en: string}, short_name: string, description: array{fr: string, en: string}, reference: array{fr: string, en: string}, validity_months: int|null} $qualification
     * @return array<string, mixed>
     */
    public static function course(string $key, string $version, array $title, array $summary, int $durationMinutes, array $chapters, array $qualification): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'summary' => $summary,
            'license' => self::text(
                'Qualification Athena Academy · formation de jeu (Arma 3) inspirée d’un standard OTAN public · sans valeur de qualification réelle',
                'Athena Academy qualification · in-game training (Arma 3) inspired by a public NATO standard · no real-world qualification value'
            ),
            'version' => $version,
            'duration_minutes' => $durationMinutes,
            'published' => true,
            'built_in' => true,
            'license_code' => $qualification['license_code'],
            'qualification' => $qualification,
            'chapters' => $chapters,
        ];
    }
}
