<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Textes d’aide métier pour les rubriques de la fiche dossier SSE.
 * Contenu destiné à l’UI — ton bureau, sans jargon technique.
 *
 * @phpstan-type SectionHelp array{title: string, principe: string, utilite: string, donnee: string}
 */
final class SseCaseSectionHelp
{
    /**
     * @return SectionHelp|null
     */
    public static function for(string $key): ?array
    {
        $all = self::all();

        return $all[$key] ?? null;
    }

    /**
     * @return array<string, SectionHelp>
     */
    public static function all(): array
    {
        return [
            '01.00' => [
                'title' => 'Où en est ce dossier',
                'principe' => 'Cette rubrique dresse l’état d’avancement du dossier : ce qui est déjà engagé et ce qui manque encore pour le rendre exploitable.',
                'utilite' => 'Elle oriente l’analyste ou le chef de bureau vers les prochaines actions utiles, sans ouvrir chaque section au hasard.',
                'donnee' => 'Les étapes et compteurs servent de tableau de bord opérationnel ; ils ne remplacent ni la synthèse ni les appréciations.',
            ],
            '01.01' => [
                'title' => 'Chemise du dossier',
                'principe' => 'La chemise est la pièce de garde : identification, classification et éléments de transmission du dossier.',
                'utilite' => 'Elle garantit qu’une transmission emporte toujours le même cadre de lecture (référence, classification, périodicité).',
                'donnee' => 'Ces informations cadrent la diffusion et la traçabilité ; elles ne détaillent pas encore les faits ni les hypothèses.',
            ],
            '01.02' => [
                'title' => 'Synthèse du dossier',
                'principe' => 'Texte court rédigé pour qui ouvre le dossier : situation, enjeu et lecture d’ensemble.',
                'utilite' => 'Elle accélère la prise de connaissance avant d’entrer dans les pièces, notes ou analyses détaillées.',
                'donnee' => 'La synthèse est une vue narrative de travail ; elle doit rester cohérente avec les éléments structurés du dossier.',
            ],
            '01.03' => [
                'title' => 'Identités rattachées',
                'principe' => 'Liste des personnes liées au dossier. Sans identité rattachée, le dossier ne désigne encore personne.',
                'utilite' => 'Elle permet de suivre qui est concerné, d’ouvrir les fiches identité et de préparer les croisements.',
                'donnee' => 'Ces rattachements alimentent la complétude du dossier, les rapprochements et les documents de diffusion.',
            ],
            '01.04' => [
                'title' => 'Pièces versées',
                'principe' => 'Registre des éléments matériels ou numériques versés (photographies, saisies, captures).',
                'utilite' => 'Elle conserve la preuve visible et partageable sans diluer le fil analytique de la synthèse.',
                'donnee' => 'Chaque pièce documente un fait ou un contexte ; elle peut étayer une appréciation ou un compte rendu.',
            ],
            '01.05' => [
                'title' => 'Sites exploités',
                'principe' => 'Sites rattachés à l’affaire : lieux fouillés, surveillés ou mentionnés dans le scénario d’enquête.',
                'utilite' => 'Elle relie le dossier au terrain et au suivi de fouille (pièces, saisies, avancement).',
                'donnee' => 'Ces liens permettent de naviguer vers les sites et de croiser lieux, personnes et pièces.',
            ],
            '01.06' => [
                'title' => 'Notes classifiées',
                'principe' => 'Observations et remarques qui n’ont pas leur place dans la synthèse, avec un niveau de lecture propre.',
                'utilite' => 'Elle permet de consigner des éléments sensibles ou provisoires sans polluer le texte de garde.',
                'donnee' => 'Les notes restent consultables selon la classification choisie ; elles enrichissent le mémoire du dossier.',
            ],
            '01.07' => [
                'title' => 'Carte tactique',
                'principe' => 'Vue cartographique propre au dossier : pings, cadrage mémorisé et captures versées aux pièces.',
                'utilite' => 'Elle fixe une représentation spatiale partagée pour le bureau et, le cas échéant, la projection terrain.',
                'donnee' => 'Les points et la vue enregistrée documentent le contexte géographique sans remplacer un compte rendu rédigé.',
            ],
            '01.08' => [
                'title' => 'Synthèse exécutive',
                'principe' => 'Résumé structuré produit à partir des éléments déjà consignés dans le dossier.',
                'utilite' => 'Elle offre une lecture rapide pour un décideur ou une relève, sans rouvrir chaque rubrique.',
                'donnee' => 'Le texte reflète l’état courant des données structurées ; il évolue quand le dossier s’enrichit.',
            ],
            '01.09' => [
                'title' => 'Mentions proposées',
                'principe' => 'Suggestions contextuelles générées selon l’état du dossier (lacunes, alertes, formulations utiles).',
                'utilite' => 'Elle rappelle ce qu’il serait pertinent de traiter ou de formuler, sans imposer une décision.',
                'donnee' => 'Ce sont des propositions d’aide à la rédaction et au suivi ; l’analyste reste maître de ce qu’il consigne.',
            ],
            '01.10' => [
                'title' => 'Appréciation analytique',
                'principe' => 'Chaîne de raisonnement formalisée : fait, source, recoupement, appréciation, confiance et hypothèse.',
                'utilite' => 'Elle sépare ce qui est établi de ce qui est interprété, et rend le jugement traçable et rejouable.',
                'donnee' => 'Chaque appréciation documente une lecture argumentée ; elle alimente la synthèse, les décisions et la diffusion.',
            ],
            '01.11' => [
                'title' => 'Lacunes et besoins',
                'principe' => 'Ce qui reste à déterminer : informations manquantes, besoins de confirmation, critères de clôture.',
                'utilite' => 'Elle transforme l’incertitude en plan d’action (priorité, responsable, échéance).',
                'donnee' => 'Ces entrées orientent la collecte et la validation ; elles ne valent pas conclusion tant qu’elles ne sont pas satisfaites.',
            ],
            '01.12' => [
                'title' => 'Registre des décisions',
                'principe' => 'Journal des arbitrages analytiques : ce qui était jugé avant, ce qui l’est après, et pourquoi.',
                'utilite' => 'Il conserve l’historique des conclusions sans écraser les versions antérieures.',
                'donnee' => 'Chaque ligne sert de preuve de continuité du raisonnement pour une relève ou une revue de dossier.',
            ],
            '01.13' => [
                'title' => 'Relations entre dossiers',
                'principe' => 'Liens déclarés avec d’autres dossiers (parent, dérivé, connexe, source, doublon), avec conservation des références.',
                'utilite' => 'Elle évite de perdre le fil après fusion, scission ou rattachement à une affaire voisine.',
                'donnee' => 'Ces relations structurent la navigation et la mémoire administrative entre dossiers liés.',
            ],
            '01.14' => [
                'title' => 'Moteur — propositions',
                'principe' => 'Propositions automatiques de rapprochements ou de signaux à partir des éléments déjà présents.',
                'utilite' => 'Elle accélère la découverte de liens possibles ; le moteur propose, il ne tranche jamais.',
                'donnee' => 'Chaque proposition attend une validation humaine ; seules les validations enrichissent le graphe et le registre.',
            ],
        ];
    }
}
