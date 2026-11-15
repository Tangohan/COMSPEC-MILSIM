<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Vocabulaire du chemin de liaison SSE : les types d'éléments qu'on peut lire dans le portail et les relations qui les
 * relient. Un lien se lit « A <relation> B » ; parcouru dans l'autre sens, la relation prend sa forme inverse.
 */
final class SseLinkCatalog
{
    /** Parcours borné : profondeur, nombre de nœuds, liens directs par nœud. */
    public const MAX_DEPTH = 6;

    public const MAX_NODES = 200;

    public const MAX_DIRECT = 60;

    /** Plafond de lignes lues par requête de voisinage. */
    public const QUERY_LIMIT = 400;

    public const MANUAL_REASON_MIN = 5;

    public const MANUAL_REASON_MAX = 255;

    /**
     * Types : libellé, icône (symbole du widget), section de droits par défaut, préfixe de référence.
     *
     * @var array<string, array{label: string, icon: string, section: string}>
     */
    public const TYPES = [
        'person' => ['label' => 'Personne', 'icon' => 'user', 'section' => 'fichiers'],
        'identity' => ['label' => 'Identité du registre', 'icon' => 'id', 'section' => 'papiers'],
        'paper' => ['label' => 'Papier', 'icon' => 'doc', 'section' => 'papiers'],
        'check' => ['label' => 'Contrôle', 'icon' => 'check', 'section' => 'papiers'],
        'seek' => ['label' => 'Fiche SEEK II', 'icon' => 'finger', 'section' => 'fichiers'],
        'notice' => ['label' => 'Avis de recherche', 'icon' => 'alert', 'section' => 'fichiers'],
        'doc_issue' => ['label' => 'Document émis', 'icon' => 'pdf', 'section' => 'fichiers'],
        'photo' => ['label' => 'Photo', 'icon' => 'photo', 'section' => 'photos'],
        'attribute' => ['label' => 'Élément du fichier', 'icon' => 'tag', 'section' => 'fichiers'],
        'mention' => ['label' => 'Mention', 'icon' => 'flag', 'section' => 'fichiers'],
        'case' => ['label' => 'Dossier', 'icon' => 'folder', 'section' => 'dossiers'],
        'interest' => ['label' => 'Dossier d’intérêt', 'icon' => 'eye', 'section' => 'dossiers'],
        'document' => ['label' => 'Document de rédaction', 'icon' => 'doc', 'section' => 'dossiers'],
        'note' => ['label' => 'Fiche de renseignement', 'icon' => 'note', 'section' => 'terrain'],
        'site' => ['label' => 'Site', 'icon' => 'pin', 'section' => 'dossiers'],
        'transmission' => ['label' => 'Transmission', 'icon' => 'signal', 'section' => 'dossiers'],
        'campaign' => ['label' => 'Campagne', 'icon' => 'flag', 'section' => 'outils'],
        'jnet' => ['label' => 'Compte rendu JNET', 'icon' => 'jnet', 'section' => ''],
    ];

    /**
     * Relations : [forme directe, forme inverse].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const RELATIONS = [
        'created_from' => ['créé depuis', 'a donné lieu à'],
        'attached_to' => ['rattaché à', 'regroupe'],
        'identified_as' => ['rattaché à', 'regroupe'],
        'checked' => ['a contrôlé', 'contrôlé lors de'],
        'held_by' => ['détenu par', 'détient'],
        'seek_of' => ['relevé SEEK II de', 'a pour relevé SEEK II'],
        'notice_on' => ['avis posé sur', 'sous avis'],
        'issued_for' => ['émis pour', 'a donné lieu à'],
        'photo_of' => ['cliché de', 'a pour cliché'],
        'mention_on' => ['mention posée sur', 'porte la mention'],
        'filed_in' => ['classé dans', 'contient'],
        'cited_in' => ['cité dans', 'cite'],
        'detected_on' => ['détecté sur', 'détecté par'],
        'cross_checked' => ['rapproché de', 'rapproché de'],
        'related' => ['lié à', 'lié à'],
    ];

    /** Relations qu'une personne peut choisir en posant un lien à la main. */
    public const MANUAL_RELATIONS = ['related', 'attached_to', 'created_from', 'cited_in', 'filed_in', 'cross_checked'];

    public static function hasType(string $type): bool
    {
        return isset(self::TYPES[$type]);
    }

    public static function typeLabel(string $type): string
    {
        return self::TYPES[$type]['label'] ?? $type;
    }

    public static function relationLabel(string $relation, bool $forward = true): string
    {
        $pair = self::RELATIONS[$relation] ?? self::RELATIONS['related'];

        return $forward ? $pair[0] : $pair[1];
    }

    /** « person:12 » : une référence d'élément, la même dans les adresses du widget, le texte copié et JNET. */
    public static function ref(string $type, string|int $id): string
    {
        return $type . ':' . $id;
    }

    /**
     * @return array{type: string, id: string}|null
     */
    public static function parseRef(string $ref): ?array
    {
        $parts = explode(':', trim($ref), 2);
        if (count($parts) !== 2 || !isset(self::TYPES[$parts[0]]) || $parts[0] === 'jnet') {
            return null;
        }
        if (preg_match('/^[1-9]\d{0,9}$/', $parts[1]) !== 1) {
            return null;
        }

        return ['type' => $parts[0], 'id' => $parts[1]];
    }
}
