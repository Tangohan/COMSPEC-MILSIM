<?php

declare(strict_types=1);

namespace App\Services\Doctrine;

/**
 * Gabarits d’ordres et de comptes rendus, à champs imposés.
 *
 * Chaque gabarit décrit une trame de rédaction : des sections, et dans chaque section des
 * champs dont l’intitulé et l’ordre sont fixés. C’est la trame qui est doctrinale, pas le
 * contenu : une unité remplit les champs comme elle l’entend.
 *
 * Portée : ces trames servent de **référence de rédaction** pour le milsim. Elles reprennent
 * la structure enseignée des formats correspondants, mais ne se substituent pas à une
 * publication officielle : une unité qui applique un règlement plus précis doit le suivre.
 * Les intitulés restent donc ajustables côté communauté.
 *
 * Origines : `us` (armée américaine), `fr` (armée française). Voir {@see DoctrineReferential}.
 */
final class OrderFormatCatalog
{
    public const KIND_ORDER = 'order';
    public const KIND_REPORT = 'report';

    /** @return array<string, string> */
    public static function kindLabels(): array
    {
        return [
            self::KIND_ORDER => 'Ordre',
            self::KIND_REPORT => 'Compte rendu / demande',
        ];
    }

    /**
     * @return list<array{
     *   key: string, origin: string, kind: string, code: string, label: string,
     *   purpose: string, issued_by: string, sections: list<array{title: string, fields: list<string>}>
     * }>
     */
    public static function all(): array
    {
        return array_merge(self::americanFormats(), self::frenchFormats());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function forReferential(string $referential): array
    {
        return DoctrineReferential::filter(self::all(), $referential);
    }

    public static function find(string $key): ?array
    {
        foreach (self::all() as $format) {
            if ($format['key'] === $key) {
                return $format;
            }
        }

        return null;
    }

    /** Nombre total de champs imposés d’un gabarit. */
    public static function fieldCount(array $format): int
    {
        $count = 0;
        foreach ($format['sections'] ?? [] as $section) {
            $count += count($section['fields'] ?? []);
        }

        return $count;
    }

    /** @return list<array<string, mixed>> */
    private static function americanFormats(): array
    {
        return [
            [
                'key' => 'us_opord',
                'origin' => DoctrineReferential::US,
                'kind' => self::KIND_ORDER,
                'code' => 'OPORD',
                'label' => 'Ordre d’opération (5 paragraphes)',
                'purpose' => 'Ordre complet préparant une opération. Trame en cinq paragraphes, dans un ordre invariable.',
                'issued_by' => 'Commandement de l’unité qui conduit l’opération',
                'sections' => [
                    ['title' => '1. Situation', 'fields' => [
                        'Zone d’intérêt et zone d’opération',
                        'Terrain et météo',
                        'Forces ennemies : composition, dispositif, intentions probables',
                        'Forces amies : mission du niveau supérieur, unités adjacentes',
                        'Renforcements et prélèvements',
                        'Considérations civiles',
                    ]],
                    ['title' => '2. Mission', 'fields' => [
                        'Qui, quoi, quand, où, dans quel but',
                    ]],
                    ['title' => '3. Exécution', 'fields' => [
                        'Intention du chef',
                        'Concept de l’opération',
                        'Articulation et phasage',
                        'Missions aux unités subordonnées',
                        'Instructions de coordination',
                    ]],
                    ['title' => '4. Soutien', 'fields' => [
                        'Ravitaillement et munitions',
                        'Soutien santé et évacuations',
                        'Maintenance et transport',
                        'Traitement des prisonniers',
                    ]],
                    ['title' => '5. Commandement et transmissions', 'fields' => [
                        'Chaîne de commandement et succession',
                        'Emplacement des postes de commandement',
                        'Réseaux, fréquences et indicatifs',
                        'Mots de passe et procédures de reconnaissance',
                    ]],
                ],
            ],
            [
                'key' => 'us_warno',
                'origin' => DoctrineReferential::US,
                'kind' => self::KIND_ORDER,
                'code' => 'WARNO',
                'label' => 'Ordre préparatoire',
                'purpose' => 'Prévient les subordonnés d’une opération à venir pour qu’ils engagent leur préparation avant l’ordre complet.',
                'issued_by' => 'Commandement, dès réception de la mission',
                'sections' => [
                    ['title' => 'Contenu', 'fields' => [
                        'Situation résumée',
                        'Mission probable ou tâche envisagée',
                        'Articulation envisagée',
                        'Délai le plus tôt de mouvement',
                        'Heure et lieu de diffusion de l’ordre complet',
                        'Tâches de préparation à engager immédiatement',
                        'Reconnaissances à lancer',
                    ]],
                ],
            ],
            [
                'key' => 'us_frago',
                'origin' => DoctrineReferential::US,
                'kind' => self::KIND_ORDER,
                'code' => 'FRAGO',
                'label' => 'Ordre fragmentaire',
                'purpose' => 'Modifie un ordre déjà diffusé en cours d’exécution. Ne reprend que ce qui change.',
                'issued_by' => 'Commandement, pendant l’exécution',
                'sections' => [
                    ['title' => 'Contenu', 'fields' => [
                        'Ordre de référence modifié',
                        'Changement de situation',
                        'Mission nouvelle ou ajustée',
                        'Modifications de l’exécution',
                        'Modifications du soutien',
                        'Modifications du commandement et des transmissions',
                        'Heure d’effet',
                    ]],
                ],
            ],
            [
                'key' => 'us_salute',
                'origin' => DoctrineReferential::US,
                'kind' => self::KIND_REPORT,
                'code' => 'SALUTE',
                'label' => 'Compte rendu d’observation',
                'purpose' => 'Rend compte d’une observation ennemie sous une forme brève et invariable.',
                'issued_by' => 'Toute unité au contact ou en observation',
                'sections' => [
                    ['title' => 'Six lignes', 'fields' => [
                        'S — Volume observé',
                        'A — Activité constatée',
                        'L — Localisation',
                        'U — Unité ou identification',
                        'T — Heure de l’observation',
                        'E — Équipement observé',
                    ]],
                ],
            ],
            [
                'key' => 'us_sitrep',
                'origin' => DoctrineReferential::US,
                'kind' => self::KIND_REPORT,
                'code' => 'SITREP',
                'label' => 'Compte rendu de situation',
                'purpose' => 'Point périodique de la situation d’une unité à son échelon supérieur.',
                'issued_by' => 'Chef d’unité, selon la périodicité fixée par l’ordre',
                'sections' => [
                    ['title' => 'Contenu', 'fields' => [
                        'Unité et heure du compte rendu',
                        'Position et dispositif',
                        'Situation ennemie',
                        'Activité propre depuis le dernier compte rendu',
                        'Situation des personnels',
                        'Situation logistique : munitions, carburant, eau, vivres',
                        'Appréciation du chef et intentions',
                    ]],
                ],
            ],
            [
                'key' => 'us_medevac_9line',
                'origin' => DoctrineReferential::US,
                'kind' => self::KIND_REPORT,
                'code' => 'MEDEVAC 9 lignes',
                'label' => 'Demande d’évacuation sanitaire',
                'purpose' => 'Demande une évacuation par aéronef. Neuf lignes numérotées, transmises dans l’ordre.',
                'issued_by' => 'Unité au contact ayant des blessés',
                'sections' => [
                    ['title' => 'Neuf lignes', 'fields' => [
                        '1 — Localisation du point de ramassage',
                        '2 — Fréquence et indicatif au point de ramassage',
                        '3 — Nombre de patients par priorité (urgent, urgent chirurgical, prioritaire, différé)',
                        '4 — Matériel spécialisé nécessaire',
                        '5 — Nombre de patients par type (couché, assis)',
                        '6 — Sécurité du point de ramassage',
                        '7 — Méthode de balisage du point de ramassage',
                        '8 — Nationalité et statut des patients',
                        '9 — Contamination NBC, ou description du terrain hors conflit',
                    ]],
                ],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function frenchFormats(): array
    {
        return [
            [
                'key' => 'fr_ordre_initial',
                'origin' => DoctrineReferential::FR,
                'kind' => self::KIND_ORDER,
                'code' => 'Ordre initial',
                'label' => 'Ordre initial d’opération',
                'purpose' => 'Ordre complet donné avant l’action, à l’issue du raisonnement tactique.',
                'issued_by' => 'Chef de l’unité qui conduit l’action',
                'sections' => [
                    ['title' => '1. Situation', 'fields' => [
                        'Terrain et conditions',
                        'Ennemi : volume, dispositif, intentions probables',
                        'Amis : mission du niveau supérieur, unités voisines',
                        'Renforcements et prélèvements',
                        'Population et environnement civil',
                    ]],
                    ['title' => '2. Mission', 'fields' => [
                        'Mission reçue, exprimée en une phrase',
                    ]],
                    ['title' => '3. Exécution', 'fields' => [
                        'Intention du chef et effet majeur recherché',
                        'Idée de manœuvre',
                        'Articulation de l’unité',
                        'Missions aux subordonnés',
                        'Coordination : horaires, limites, mesures de sûreté',
                    ]],
                    ['title' => '4. Soutien', 'fields' => [
                        'Ravitaillement et munitions',
                        'Soutien santé et évacuations',
                        'Maintenance et mouvements',
                    ]],
                    ['title' => '5. Commandement et liaisons', 'fields' => [
                        'Place du chef et succession du commandement',
                        'Emplacement du poste de commandement',
                        'Réseaux, fréquences et indicatifs',
                        'Mots de reconnaissance',
                    ]],
                ],
            ],
            [
                'key' => 'fr_ordre_conduite',
                'origin' => DoctrineReferential::FR,
                'kind' => self::KIND_ORDER,
                'code' => 'Ordre de conduite',
                'label' => 'Ordre de conduite',
                'purpose' => 'Ajuste l’action en cours d’exécution, sans reprendre l’ordre initial.',
                'issued_by' => 'Chef d’unité, pendant l’action',
                'sections' => [
                    ['title' => 'Contenu', 'fields' => [
                        'Ordre initial concerné',
                        'Évolution de la situation',
                        'Nouvelle mission ou mission ajustée',
                        'Modifications de la manœuvre',
                        'Modifications de coordination et de soutien',
                        'Heure d’effet',
                    ]],
                ],
            ],
            [
                'key' => 'fr_compte_rendu',
                'origin' => DoctrineReferential::FR,
                'kind' => self::KIND_REPORT,
                'code' => 'Compte rendu',
                'label' => 'Compte rendu au niveau supérieur',
                'purpose' => 'Informe l’échelon supérieur d’un fait ou d’une situation, immédiatement ou périodiquement.',
                'issued_by' => 'Tout chef d’unité',
                'sections' => [
                    ['title' => 'Contenu', 'fields' => [
                        'Unité émettrice et heure',
                        'Position',
                        'Fait constaté : quoi, où, quand',
                        'Situation de l’ennemi',
                        'Situation de l’unité : personnels, matériels, munitions',
                        'Action engagée',
                        'Intentions du chef et besoins exprimés',
                    ]],
                ],
            ],
            [
                'key' => 'fr_demande_evacuation',
                'origin' => DoctrineReferential::FR,
                'kind' => self::KIND_REPORT,
                'code' => 'Demande d’évacuation',
                'label' => 'Demande d’évacuation sanitaire',
                'purpose' => 'Demande la relève et l’évacuation de blessés.',
                'issued_by' => 'Unité ayant des blessés',
                'sections' => [
                    ['title' => 'Contenu', 'fields' => [
                        'Unité demandeuse et moyens de liaison',
                        'Position du point de regroupement des blessés',
                        'Nombre de blessés par degré d’urgence',
                        'Nature des atteintes',
                        'Moyens de relève disponibles sur place',
                        'Balisage et accès du point de ramassage',
                        'Sécurité de la zone',
                        'Contamination éventuelle',
                    ]],
                ],
            ],
        ];
    }
}
