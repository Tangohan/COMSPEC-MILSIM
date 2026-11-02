<?php

declare(strict_types=1);

namespace App\Services\Academy\BuiltIn;

use App\Services\Academy\BuiltIn\AcademyCourseKit as K;

/**
 * Formation intégrée « ATHENA — utiliser et administrer la plateforme » : prise en main du portail, back-office
 * (organisation, effectifs, doctrine, Academy, opérations, droits d’accès), circuit de recrutement et liaison du mod
 * ATAK. Contenu FR/EN complet, choisi selon la langue de l’interface (repli FR : views/academy/init.php).
 *
 * Chaque écran cité l’est avec son chemin de menu et son adresse entre accents graves (`/…`) :
 * tests/Unit/AcademyAthenaCourseTest.php vérifie que chaque adresse existe dans routes/*.php.
 * Après toute modification : augmenter VERSION et, si des stagiaires sont inscrits, ajouter une étape de
 * rafraîchissement des inscrits non commencés (modèle : database/steps/3540_academy_jtac_rich_text_refresh.php).
 */
final class AthenaPlatformCourse
{
    public const KEY = 'athena-utiliser-administrer-plateforme';
    public const VERSION = '2.0.0';

    /** @return array<string, mixed> */
    public static function course(): array
    {
        $course = K::course(
            self::KEY,
            self::VERSION,
            K::text('ATHENA — Utiliser et administrer la plateforme', 'ATHENA — Using and running the platform'),
            K::text(
                'La formation de référence pour tous les membres et l’encadrement : prise en main du portail (tableau de bord, profil, situation, langue, notifications, documents), puis le back-office expliqué module par module — organisation, effectifs, doctrine, Academy, opérations, droits d’accès et simulateur —, le circuit complet du recrutement, de la page publique à l’intégration dans l’unité, et la liaison du mod ATAK. Chapitres courts, chemins de menu et adresses exacts, quiz commentés à chaque chapitre.',
                'The reference course for every member and for staff: getting started with the portal (dashboard, profile, status, language, notifications, documents), then the back office explained module by module — organisation, personnel, doctrine, Academy, operations, access rights and the simulator — the full recruitment pipeline, from the public page to joining a unit, and linking the ATAK mod. Short chapters, exact menu paths and addresses, and an explained quiz in every chapter.'
            ),
            240,
            [
                AthenaCourseChaptersPortal::gettingStarted(),
                AthenaCourseChaptersBackOffice::aside(),
                AthenaCourseChaptersBackOffice::organisation(),
                AthenaCourseChaptersBackOffice::personnelChain(),
                AthenaCourseChaptersBackOffice::qualifications(),
                AthenaCourseChaptersBackOffice::doctrine(),
                AthenaCourseChaptersBackOffice::academy(),
                AthenaCourseChaptersBackOffice::operations(),
                AthenaCourseChaptersBackOffice::rights(),
                AthenaCourseChaptersRecruitment::pipeline(),
                AthenaCourseChaptersRecruitment::integration(),
                AthenaCourseChaptersPortal::atak(),
            ],
            [
                'code' => 'ACAD-ATHENA',
                'license_code' => 'ATHENA',
                'name' => K::text('Utilisateur et administrateur ATHENA (Athena Academy)', 'ATHENA user and administrator (Athena Academy)'),
                'short_name' => 'ATHENA',
                'description' => K::text(
                    'Le titulaire utilise le portail ATHENA en autonomie et sait administrer sa communauté dans le back-office : structure des unités et ORBAT, affectations et fonctions, qualifications, doctrine, Academy, opérations, droits d’accès vérifiés au simulateur, et circuit complet du recrutement. Délivrée par la formation Academy « ATHENA — Utiliser et administrer la plateforme » après validation instructeur de chaque chapitre.',
                    'The holder uses the ATHENA portal independently and can run their community in the back office: unit structure and ORBAT, assignments and positions, qualifications, doctrine, Academy, operations, access rights checked in the simulator, and the full recruitment pipeline. Awarded by the Academy course “ATHENA — Using and running the platform” after instructor validation of every chapter.'
                ),
                'reference' => K::text('Formation interne à la plateforme ATHENA (version ' . self::VERSION . ')', 'Internal ATHENA platform training (version ' . self::VERSION . ')'),
                'validity_months' => null,
            ]
        );
        $course['license'] = K::text(
            'Qualification Athena Academy · formation à l’utilisation de la plateforme ATHENA',
            'Athena Academy qualification · ATHENA platform training'
        );

        return $course;
    }

    /** Questions et choix de réponse sont du texte simple : la syntaxe de mise en forme y est retirée. */
    private static function plain(string $text): string
    {
        return str_replace(['**', '`'], '', $text);
    }

    /**
     * Option de réponse.
     *
     * @return array{fr: string, en: string}
     */
    public static function o(string $fr, string $en): array
    {
        return K::text(self::plain($fr), self::plain($en));
    }

    /**
     * Chapitre de la formation ATHENA : sept étapes du format Academy, avec la présentation de validation propre à
     * une formation à la plateforme (pas de mention « formation de jeu inspirée d’un standard OTAN »).
     *
     * @param array{0: string, 1: string} $title
     * @param array{0: string, 1: string} $theory
     * @param array{0: string, 1: string} $demo
     * @param array<string, mixed> $guided
     * @param array<string, mixed> $simulation
     * @param list<array<string, mixed>> $questions
     * @param array{0: string, 1: string} $debrief
     * @param list<array{0: string, 1: string}> $debriefCriteria
     * @param list<array{0: string, 1: string}> $validationCriteria
     * @param array{0: string, 1: string, 2: string, 3: string} $sheet titre FR, titre EN, texte FR, texte EN
     * @return array<string, mixed>
     */
    public static function chapter(array $title, array $theory, array $demo, array $guided, array $simulation, array $questions, array $debrief, array $debriefCriteria, array $validationCriteria, array $sheet): array
    {
        $pairs = static fn (array $list): array => array_map(static fn (array $p): array => K::text($p[0], $p[1]), $list);
        $chapter = K::chapter(
            K::text($title[0], $title[1]),
            K::text($theory[0], $theory[1]),
            K::text($demo[0], $demo[1]),
            $guided,
            $simulation,
            $questions,
            K::text($debrief[0], $debrief[1]),
            $pairs($debriefCriteria),
            $pairs($validationCriteria),
            [K::sheet(K::text($sheet[0], $sheet[1]), K::text($sheet[2], $sheet[3]))]
        );
        $chapter['validation']['brief'] = K::text(
            'La validation confirme que vous savez **faire** ce que décrit ce chapitre dans ATHENA, pas seulement le reconnaître dans un quiz. Un instructeur habilité, distinct du stagiaire, examine vos tentatives et votre débriefing selon les critères ci-dessous ; il peut vous demander de refaire la manipulation devant lui (partage d’écran ou session) et laisse une justification écrite. Si un critère n’est pas démontré, il organise une reprise avant de valider.',
            'Validation confirms that you can **do** what this chapter describes in ATHENA, not just recognise it in a quiz. An authorised instructor other than the learner reviews your attempts and debrief against the criteria below; they may ask you to repeat the task in front of them (screen share or session) and record a written justification. If a criterion has not been demonstrated, they arrange further practice before validating.'
        );

        return $chapter;
    }

    /**
     * Question à trois choix ; l’explication justifie la bonne réponse (affichée après réponse).
     *
     * @param list<array{fr: string, en: string}> $options
     * @return array<string, mixed>
     */
    public static function q(string $fr, string $en, array $options, int $answer, string $whyFr, string $whyEn): array
    {
        return K::question(self::plain($fr), self::plain($en), $options, $answer, $whyFr, $whyEn);
    }

    /**
     * Cas guidé (avec indice) ou cas autonome (sans indice).
     *
     * @param array<string, mixed> $question
     * @param array{0: string, 1: string} $brief
     * @param array{0: string, 1: string}|null $hint
     * @return array<string, mixed>
     */
    public static function stage(array $question, array $brief, ?array $hint = null): array
    {
        return K::stage($question, K::text($brief[0], $brief[1]), $hint !== null ? K::text($hint[0], $hint[1]) : null);
    }
}
