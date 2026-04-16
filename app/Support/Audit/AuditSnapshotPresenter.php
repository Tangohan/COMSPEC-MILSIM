<?php

declare(strict_types=1);

namespace App\Support\Audit;

/**
 * Affichage métier des snapshots JSON du journal d’audit (pas de dump brut dans les vues).
 */
final class AuditSnapshotPresenter
{
    /** @var array<string, string> */
    private const FIELD_LABELS = [
        'email' => 'Adresse e-mail',
        'status' => 'État du compte',
        'grade_id' => 'Grade',
        'nationality_code' => 'Nationalité',
        'preferred_grade_format' => 'Format des grades',
        'professional_category_code' => 'Catégorie professionnelle',
        'display_name' => 'Nom affiché',
        'callsign' => 'Indicatif',
        'profile_slug' => 'Profil public',
        'connexion_mot_de_passe' => 'Mot de passe',
        'brief_member_access' => 'Accès au brief pour les membres',
        'brief_member_closed_message' => 'Message lorsque le brief est fermé',
        'code' => 'Référence technique',
        'name' => 'Nom',
        'description' => 'Description',
        'is_active' => 'Actif',
        'is_public' => 'Visible',
        'module_id' => 'Fonctionnalité',
        'module_version_id' => 'Version publiée',
        'version' => 'Numéro de version',
        'channel' => 'Canal',
        'rule_type' => 'Type de règle',
        'community_id' => 'Communauté de test',
        'environment_channel_id' => 'Canal d’environnement',
        'applies_to_version_id' => 'Version concernée',
        'priority' => 'Priorité',
        'rule_id' => 'Règle',
        'valid_from' => 'Valide à partir du',
        'valid_until' => 'Valide jusqu’au',
        'user_id' => 'Compte',
        'contexte' => 'Contexte',
        'indicator_id' => 'Entrée de liste',
        'indicator_type' => 'Type d’indicateur',
        'scope' => 'Périmètre',
        'tenant_id' => 'Communauté',
        'role_name' => 'Rôle',
        'role_id' => 'Référence rôle',
        'sanction_kind' => 'Type de mesure',
        'type' => 'Type de mesure',
        'target_user_id' => 'Compte concerné',
        'sanction_scope' => 'Niveau (organisation ou site)',
        'restrictions' => 'Restrictions associées',
        'statut' => 'État après action',
        'expires_at' => 'Fin prévue',
    ];

    /**
     * Résumé court pour une ligne de liste (sans JSON).
     */
    public static function listSummary(?string $oldValue, ?string $newValue): string
    {
        $o = self::decodeObject($oldValue);
        $n = self::decodeObject($newValue);
        if ($o === null && $n === null) {
            return '—';
        }
        if (is_array($o) && is_array($n)) {
            $keys = array_keys(array_merge($o, $n));
            $c = count($keys);
            if ($c === 0) {
                return '—';
            }
            if ($c === 1) {
                return '1 champ modifié';
            }

            return $c . ' champs modifiés';
        }
        if ($n !== null && $n !== []) {
            return 'Données associées';
        }
        if ($o !== null && $o !== []) {
            return 'Valeur précédente enregistrée';
        }

        return '—';
    }

    /**
     * Lignes pour tableau avant / après (clé => [libellé, avant, après]).
     *
     * @return list<array{key: string, label: string, before: string, after: string}>
     */
    public static function diffRows(?string $oldValue, ?string $newValue): array
    {
        $o = self::decodeObject($oldValue);
        $n = self::decodeObject($newValue);
        if (!is_array($o)) {
            $o = [];
        }
        if (!is_array($n)) {
            $n = [];
        }
        $keys = array_unique(array_merge(array_keys($o), array_keys($n)));
        sort($keys, SORT_STRING);
        $rows = [];
        foreach ($keys as $k) {
            $rows[] = [
                'key' => $k,
                'label' => self::fieldLabel($k),
                'before' => self::scalarToDisplay($o[$k] ?? null),
                'after' => self::scalarToDisplay($n[$k] ?? null),
            ];
        }

        return $rows;
    }

    public static function fieldLabel(string $key): string
    {
        return self::FIELD_LABELS[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }

    /**
     * Masque partiellement une adresse IP pour l’affichage (IPv4 : dernier octet).
     */
    public static function maskIpForDisplay(?string $ip): string
    {
        if ($ip === null || trim($ip) === '') {
            return '—';
        }
        $ip = trim($ip);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $p = explode('.', $ip);
            if (count($p) === 4) {
                return $p[0] . '.' . $p[1] . '.' . $p[2] . '.·';
            }
        }

        return strlen($ip) > 24 ? substr($ip, 0, 21) . '…' : $ip;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function decodeObject(?string $json): ?array
    {
        if ($json === null || trim($json) === '') {
            return null;
        }
        try {
            $v = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return ['valeur' => $json];
        }

        return is_array($v) ? $v : null;
    }

    private static function scalarToDisplay(mixed $v): string
    {
        if ($v === null) {
            return '—';
        }
        if (is_bool($v)) {
            return $v ? 'Oui' : 'Non';
        }
        if (is_scalar($v)) {
            $s = (string) $v;

            return $s === '' ? '—' : $s;
        }
        $enc = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $enc !== false ? $enc : '—';
    }
}
