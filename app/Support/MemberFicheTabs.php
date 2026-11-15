<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Onglets de la fiche membre du back-office (Effectifs › Membres › fiche).
 *
 * Les onglets regroupent les cartes existantes : aucune donnée ni aucun droit n'est créé ici. Chaque carte garde son
 * ancre d'origine (`#grade`, `#sanctions`…) : l'ancre ouvre l'onglet qui la contient. Sans JavaScript, tous les
 * onglets s'affichent à la suite ; `?onglet=` désigne l'onglet mis en avant côté serveur.
 */
final class MemberFicheTabs
{
    public const DEFAULT = 'identite';

    /**
     * Ordre d'affichage. `anchors` : ancres des cartes que l'onglet contient (hors cartes insérées par un partiel
     * propre, qui gardent leur ancre : `papiers`, `invitations`). `needs` : l'onglet n'existe que si ce droit de
     * lecture est accordé au lecteur.
     *
     * @var array<string, array{label: string, anchors: list<string>, needs?: string}>
     */
    private const TABS = [
        'identite' => ['label' => 'Identité', 'anchors' => ['identite', 'situation', 'modifier-dossier', 'langues', 'invitations']],
        'papiers' => ['label' => 'Identité & papiers', 'anchors' => ['papiers'], 'needs' => 'papers'],
        'poste' => ['label' => 'Affectation et poste', 'anchors' => ['poste', 'unite', 'equipe', 'role-unite', 'fonctions']],
        'carriere' => ['label' => 'Carrière', 'anchors' => ['carriere', 'grade', 'anciennete', 'elevation']],
        'qualifications' => ['label' => 'Qualifications et distinctions', 'anchors' => ['qualifications-distinctions', 'qualifications', 'distinctions', 'decerner']],
        'acces' => ['label' => 'Accès', 'anchors' => ['acces-niveau', 'acces', 'acces-sse']],
        'statut' => ['label' => 'Position et statut', 'anchors' => ['statut-position', 'statut', 'depart']],
        'suivi-rh' => ['label' => 'Suivi RH', 'anchors' => ['suivi-rh', 'presence', 'absences', 'sanctions', 'documents-rh', 'mobilite', 'fiabilisation']],
        'disponibilite' => ['label' => 'Disponibilité', 'anchors' => ['disponibilite']],
        'historique' => ['label' => 'Historique', 'anchors' => ['historique', 'historique-rh', 'chronologie']],
    ];

    /** @return list<string> */
    public static function slugs(): array
    {
        return array_keys(self::TABS);
    }

    public static function label(string $slug): string
    {
        return self::TABS[$slug]['label'] ?? '';
    }

    /** @return list<string> ancres des cartes de l'onglet */
    public static function anchors(string $slug): array
    {
        return self::TABS[$slug]['anchors'] ?? [];
    }

    /**
     * Onglet qui contient une ancre de la fiche (ancien lien `#grade`, `#statut`…), ou null si l'ancre est inconnue.
     */
    public static function tabForAnchor(string $anchor): ?string
    {
        $anchor = ltrim(trim($anchor), '#');
        foreach (self::TABS as $slug => $tab) {
            if (in_array($anchor, $tab['anchors'], true)) {
                return $slug;
            }
        }

        return null;
    }

    /**
     * Onglets que ce lecteur peut ouvrir, dans l'ordre d'affichage.
     *
     * @param array<string, bool> $rights droits de lecture du lecteur, par nom (`papers` : papiers d'identité)
     * @return list<string>
     */
    public static function visible(array $rights): array
    {
        $out = [];
        foreach (self::TABS as $slug => $tab) {
            $needs = $tab['needs'] ?? null;
            if ($needs === null || !empty($rights[$needs])) {
                $out[] = $slug;
            }
        }

        return $out;
    }

    /**
     * Onglet à mettre en avant : celui demandé s'il est visible pour ce lecteur, sinon le premier visible.
     *
     * @param list<string> $visible
     */
    public static function resolve(?string $requested, array $visible): string
    {
        $requested = trim((string) $requested);
        if ($requested !== '' && in_array($requested, $visible, true)) {
            return $requested;
        }

        return $visible[0] ?? self::DEFAULT;
    }
}
