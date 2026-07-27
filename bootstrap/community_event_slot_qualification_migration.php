<?php

declare(strict_types=1);

/**
 * Prérequis de qualification sur un poste d'opération.
 *
 * Jusqu'ici, `community_event_slots` ne portait qu'une capacité : le système ne savait pas
 * si un membre était en droit de tenir le poste. Le contrôle reposait entièrement sur la
 * vigilance de l'organisateur.
 *
 * Deux colonnes :
 *   - required_training_course_id : formation dont la qualification est exigée (le lien passe
 *     par personnel_qualifications.training_course_id, alimenté depuis les certificats) ;
 *   - qualification_enforcement   : « advisory » (avertir, laisser passer) ou « strict »
 *     (refuser l'inscription). Le défaut est volontairement « advisory » afin qu'activer
 *     un prérequis sur une communauté existante ne bloque personne du jour au lendemain.
 *
 * Idempotent : rejouable sans effet de bord.
 *
 * @return callable(PDO): void
 */
return static function (PDO $pdo): void {
    require_once __DIR__ . '/schema_ensure_column.php';

    if (!schema_table_exists($pdo, 'community_event_slots')) {
        echo "  [ATTENTION] community_event_slots absente — skip prérequis de qualification\n";

        return;
    }

    schema_ensure_column(
        $pdo,
        'community_event_slots',
        'required_training_course_id',
        '`required_training_course_id` int(10) UNSIGNED DEFAULT NULL AFTER `unit_id`'
    );
    schema_ensure_column(
        $pdo,
        'community_event_slots',
        'qualification_enforcement',
        "`qualification_enforcement` varchar(16) NOT NULL DEFAULT 'advisory' AFTER `required_training_course_id`"
    );
};
