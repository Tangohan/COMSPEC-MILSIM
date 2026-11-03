<?php

declare(strict_types=1);

namespace App\Support;

/**
 * COMSPEC IDENTITY — valeurs fermées du registre (identités, papiers, statuts, contrôles) et leurs libellés.
 *
 * Les clés sont celles du contrat avec le mod SSE (pack d'identité, vérification d'un papier, contrôles) :
 * ne pas les renommer sans changer le mod. Les libellés sont ceux affichés sur le portail SSE.
 */
final class SseIdentityCatalog
{
    /** Nature d'une identité (vérité de jeu : jamais renvoyée lors d'une vérification). */
    public const KINDS = [
        'civil' => 'Identité civile',
        'alias' => 'Alias',
        'cover' => 'Identité de couverture',
        'forged' => 'Identité falsifiée',
        'generated' => 'Identité générée',
    ];

    public const DOC_TYPES = [
        'id_card' => 'Carte d’identité',
        'passport' => 'Passeport',
        'driving_license' => 'Permis de conduire',
    ];

    public const SEXES = [
        'M' => 'Masculin',
        'F' => 'Féminin',
        'X' => 'Non précisé',
    ];

    /** Origine : d'où vient le papier. */
    public const ORIGINS = [
        'official' => 'Délivré officiellement',
        'cover' => 'Couverture préparée',
        'forged' => 'Faux document',
        'generated' => 'Généré pour la partie',
    ];

    /** Intégrité : état matériel du support. */
    public const INTEGRITIES = [
        'intact' => 'Intact',
        'damaged' => 'Abîmé',
        'altered' => 'Modifié',
        'counterfeit' => 'Contrefait',
    ];

    /** Validité administrative. */
    public const VALIDITIES = [
        'valid' => 'En cours de validité',
        'expired' => 'Expiré',
        'suspended' => 'Suspendu',
        'revoked' => 'Annulé',
    ];

    /** Signalement porté sur le papier. */
    public const REPORTS = [
        'none' => 'Aucun signalement',
        'lost' => 'Déclaré perdu',
        'stolen' => 'Déclaré volé',
        'wanted' => 'Titulaire recherché',
        'watch' => 'Sous surveillance',
    ];

    /** Attribution : qui détient l'exemplaire. */
    public const ATTRIBUTIONS = [
        'holder' => 'Porté par son titulaire',
        'other' => 'Porté par un tiers',
        'seized' => 'Saisi',
        'unassigned' => 'Non attribué',
    ];

    /** Puce électronique (contrôle N2). */
    public const CHIPS = [
        'ok' => 'Puce lisible',
        'absent' => 'Sans puce',
        'defect' => 'Puce illisible',
        'mismatch' => 'Puce incohérente',
    ];

    public const FORGERY_KINDS = [
        'counterfeit' => 'Contrefaçon complète',
        'photo_swap' => 'Photo substituée',
        'data_alteration' => 'Données modifiées',
        'stolen_blank' => 'Vierge volé',
    ];

    /** Cycle de vie : seul un papier « émis » part dans le pack du mod. */
    public const WORKFLOWS = [
        'draft' => 'Brouillon',
        'validated' => 'Validé',
        'issued' => 'Émis',
        'revoked' => 'Révoqué',
    ];

    /** Transitions autorisées : état actuel → états suivants possibles. */
    public const TRANSITIONS = [
        'draft' => ['validated'],
        'validated' => ['issued', 'draft'],
        'issued' => ['revoked'],
        'revoked' => [],
    ];

    /** Verdict d'une vérification (contrat lookup) et d'un contrôle. */
    public const VERDICTS = [
        'ras' => 'RAS',
        'signale' => 'Signalé',
        'recherche' => 'Recherché',
        'hvt' => 'Cible prioritaire',
        'inconnu' => 'Inconnu des fichiers',
        'revoque' => 'Papier annulé',
        'vole' => 'Papier volé ou perdu',
        'expire' => 'Papier expiré',
    ];

    /** Niveaux de contrôle. */
    public const LEVELS = [
        'N0' => 'N0 — examen visuel',
        'N1' => 'N1 — lecture de la bande MRZ',
        'N2' => 'N2 — lecture de la puce',
        'N3' => 'N3 — interrogation des fichiers',
        'N4' => 'N4 — comparaison du visage (biométrie)',
    ];

    /** Niveau d'une liste de surveillance (pack watch[].level, lookup level). */
    public const WATCH_LEVELS = [
        'surveillance' => 'Surveillance',
        'prioritaire' => 'Personne prioritaire',
        'hvt' => 'Cible prioritaire',
    ];

    public const FLAGS = [
        'holder_mismatch' => 'Le nom présenté ne correspond pas au titulaire',
        'dob_mismatch' => 'La date de naissance présentée ne correspond pas',
        'expired' => 'Date d’expiration dépassée',
        'revoked' => 'Papier annulé',
        'stolen' => 'Déclaré volé',
        'lost' => 'Déclaré perdu',
        'watchlist' => 'Nom sur une liste de surveillance',
        'person_priority' => 'Fiche personne prioritaire',
        'unknown_document' => 'Numéro absent des fichiers',
        'suspended' => 'Papier suspendu',
    ];

    /** Date civile et âge révolu ; les dates absentes ou invalides ne produisent pas d’âge. */
    public static function birthDateLabel(?string $value, ?\DateTimeImmutable $today = null): string
    {
        $value = trim((string) $value);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            return $value !== '' ? $value : '—';
        }
        $today ??= new \DateTimeImmutable('today', new \DateTimeZone('Europe/Paris'));
        return $date->format('d/m/Y') . ($date <= $today ? ' · ' . $date->diff($today)->y . ' ans' : '');
    }

    public static function label(array $map, ?string $key, string $fallback = '—'): string
    {
        $key = (string) $key;

        return $key !== '' && isset($map[$key]) ? (string) $map[$key] : $fallback;
    }

    /** Valeur fermée : la clé si elle est connue, sinon la valeur par défaut. */
    public static function pick(array $map, mixed $value, string $default): string
    {
        $v = is_string($value) ? trim($value) : (is_scalar($value) ? (string) $value : '');

        return isset($map[$v]) ? $v : $default;
    }

    /** Référence lisible d'un papier (DOC-000012). */
    public static function documentRef(int $id): string
    {
        return 'DOC-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    public static function identityRef(int $id): string
    {
        return 'IDT-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    public static function checkRef(int $id): string
    {
        return 'CTRL-' . $id;
    }
}
