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
        'valeur' => 'Valeur',
        'title' => 'Titre',
        'plan_slug' => 'Formule d’accès',
        'subscription_status' => 'Statut d’abonnement',
        'end_founder_trial' => 'Fin anticipée de la période fondateur',
        'slug' => 'Identifiant court',
        'sort_order' => 'Ordre d’affichage',
        'features_json' => 'Fonctionnalités incluses',
        'limits_json' => 'Limites associées',
        'stripe_price_id_monthly' => 'Tarif mensuel (paiement)',
        'stripe_price_id_yearly' => 'Tarif annuel (paiement)',
        'platform_directory' => 'Depuis l’annuaire plateforme',
        'source_action' => 'Événement d’origine',
        'source_audit_id' => 'Référence journal d’origine',
        'restored' => 'Valeurs restaurées',
        'previous_state' => 'État juste avant restauration',
        'result' => 'Résultat',
        'note' => 'Message',
        'account.lock' => 'Verrouillage du compte',
        'account.lock_login' => 'Blocage de la connexion',
        'forum.post' => 'Publication sur le forum',
        'forum.reply' => 'Réponses sur le forum',
        'join_blocked' => 'Blocage des nouvelles inscriptions (même e-mail)',
        'restrictions.account.lock' => 'Verrouillage du compte',
        'restrictions.forum.post' => 'Publication sur le forum',
        'restrictions.forum.reply' => 'Réponses sur le forum',
        'restrictions.join_blocked' => 'Blocage des nouvelles inscriptions (même e-mail)',
    ];

    /** @var array<string, string> */
    private const ENTITY_TYPE_LABELS = [
        'user' => 'Compte',
        'auth' => 'Connexion',
        'tenant' => 'Communauté',
        'document' => 'Document',
        'role' => 'Rôle',
        'group' => 'Groupe',
        'invitation' => 'Invitation',
        'course' => 'Formation',
        'enrollment' => 'Inscription',
        'module' => 'Fonctionnalité',
        'access_rule' => 'Règle d’accès',
    ];

    /** @var array<string, string> */
    private const STATUS_VALUE_LABELS = [
        'active' => 'Compte actif',
        'inactive' => 'Compte inactif',
        'pending_verification' => 'En attente de vérification de l’e-mail',
        'suspended' => 'Compte suspendu',
        'banned' => 'Compte banni',
        'draft' => 'Brouillon',
        'published' => 'Publié',
        'archived' => 'Archivé',
    ];

    /**
     * Résumé court pour une ligne de liste (libellés métier, pas de JSON).
     */
    public static function listSummary(?string $oldValue, ?string $newValue): string
    {
        $rows = self::diffRows($oldValue, $newValue);
        if ($rows === []) {
            return '—';
        }

        $parts = [];
        $shown = 0;
        foreach ($rows as $row) {
            if ($shown >= 2) {
                break;
            }
            $before = trim((string) ($row['before'] ?? ''));
            $after = trim((string) ($row['after'] ?? ''));
            if ($before === '—' && $after === '—') {
                continue;
            }
            $label = (string) ($row['label'] ?? 'Champ');
            if ($before === '—' || $before === '') {
                $parts[] = $label . ' : ' . $after;
            } elseif ($after === '—' || $after === '') {
                $parts[] = $label . ' : ' . $before . ' → (vide)';
            } else {
                $parts[] = $label . ' : ' . $before . ' → ' . $after;
            }
            $shown++;
        }

        if ($parts === []) {
            $c = count($rows);
            if ($c === 1) {
                return '1 élément enregistré';
            }

            return $c . ' éléments enregistrés';
        }

        $extra = count($rows) - $shown;
        $text = implode(' · ', $parts);
        if ($extra > 0) {
            $text .= ' · +' . $extra . ' autre' . ($extra > 1 ? 's' : '');
        }

        return $text;
    }

    /**
     * Nom / indicatif prioritaire pour l’acteur (comme les mesures d’usage).
     *
     * @param array<string, mixed> $row
     */
    public static function actorPrimaryLabel(array $row): string
    {
        $dn = trim((string) ($row['actor_display_name'] ?? ''));
        if ($dn !== '') {
            return $dn;
        }
        $cs = trim((string) ($row['actor_callsign'] ?? ''));
        if ($cs !== '') {
            return $cs;
        }
        $email = trim((string) ($row['actor_email'] ?? ''));
        if ($email !== '') {
            return $email;
        }
        $uid = (int) ($row['user_id'] ?? 0);

        return $uid > 0 ? 'Compte n°' . $uid : 'Système';
    }

    /**
     * Sous-ligne acteur (e-mail si le libellé principal n’est pas déjà l’e-mail).
     *
     * @param array<string, mixed> $row
     */
    public static function actorSecondaryLabel(array $row): string
    {
        $email = trim((string) ($row['actor_email'] ?? ''));
        if ($email === '') {
            return '';
        }
        $primary = self::actorPrimaryLabel($row);
        if (strcasecmp($primary, $email) === 0) {
            $cs = trim((string) ($row['actor_callsign'] ?? ''));
            $dn = trim((string) ($row['actor_display_name'] ?? ''));
            if ($cs !== '' && strcasecmp($primary, $cs) !== 0) {
                return $cs;
            }
            if ($dn !== '' && strcasecmp($primary, $dn) !== 0) {
                return $dn;
            }

            return '';
        }

        return $email;
    }

    /**
     * Cible humaine : type + nom résolu, sans « Authentification · n°5 » brut.
     *
     * @param array<string, mixed> $row
     * @return array{primary: string, secondary: string}
     */
    public static function entityTargetLabels(array $row): array
    {
        $type = trim((string) ($row['entity_type'] ?? ''));
        $id = (int) ($row['entity_id'] ?? 0);
        $typeLabel = self::entityTypeLabel($type);

        if ($type === '' && $id < 1) {
            return ['primary' => '—', 'secondary' => ''];
        }

        $resolved = self::resolveEntityName($row, $type);
        if ($resolved !== '') {
            $primary = $resolved;
            $secondary = $typeLabel;
            if ($id > 0 && !in_array($type, ['auth'], true)) {
                $secondary .= ' · réf. ' . $id;
            }

            return ['primary' => $primary, 'secondary' => $secondary];
        }

        // Connexion : l’id pointe vers le compte — ne pas afficher « Connexion · n°… »
        if ($type === 'auth') {
            if ($id > 0) {
                return [
                    'primary' => 'Compte n°' . $id,
                    'secondary' => 'Session / authentification',
                ];
            }

            return ['primary' => 'Session / authentification', 'secondary' => ''];
        }

        if ($typeLabel !== '—' && $id > 0) {
            return [
                'primary' => $typeLabel,
                'secondary' => 'Référence ' . $id,
            ];
        }

        if ($typeLabel !== '—') {
            return ['primary' => $typeLabel, 'secondary' => ''];
        }

        return ['primary' => 'Élément n°' . $id, 'secondary' => ''];
    }

    public static function entityTypeLabel(?string $type): string
    {
        $type = trim((string) $type);
        if ($type === '') {
            return '—';
        }
        if (isset(self::ENTITY_TYPE_LABELS[$type])) {
            return self::ENTITY_TYPE_LABELS[$type];
        }

        return ucfirst(str_replace(['_', '-'], ' ', $type));
    }

    /**
     * Indice navigateur court (sans jargon technique).
     */
    public static function browserHint(?string $userAgent): string
    {
        $ua = trim((string) $userAgent);
        if ($ua === '') {
            return '';
        }
        if (stripos($ua, 'Edg/') !== false || stripos($ua, 'Edge/') !== false) {
            return 'Microsoft Edge';
        }
        if (stripos($ua, 'Chrome/') !== false && stripos($ua, 'Chromium') === false) {
            return 'Google Chrome';
        }
        if (stripos($ua, 'Firefox/') !== false) {
            return 'Mozilla Firefox';
        }
        if (stripos($ua, 'Safari/') !== false && stripos($ua, 'Chrome/') === false) {
            return 'Safari';
        }
        if (stripos($ua, 'Opera/') !== false || stripos($ua, 'OPR/') !== false) {
            return 'Opera';
        }

        return 'Navigateur';
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
        $o = self::flattenAssociative($o);
        $n = self::flattenAssociative($n);
        $keys = array_unique(array_merge(array_keys($o), array_keys($n)));
        sort($keys, SORT_STRING);
        $rows = [];
        foreach ($keys as $k) {
            $rows[] = [
                'key' => $k,
                'label' => self::fieldLabel($k),
                'before' => self::scalarToDisplay($o[$k] ?? null, $k),
                'after' => self::scalarToDisplay($n[$k] ?? null, $k),
            ];
        }

        return $rows;
    }

    public static function fieldLabel(string $key): string
    {
        if (isset(self::FIELD_LABELS[$key])) {
            return self::FIELD_LABELS[$key];
        }
        $human = str_replace(['_', '-'], ' ', $key);
        $human = str_replace('.', ' · ', $human);
        if (function_exists('mb_convert_case')) {
            return mb_convert_case($human, MB_CASE_TITLE, 'UTF-8');
        }

        return ucwords($human);
    }

    /**
     * Affichage public d’une valeur scalaire / booléenne (sans dump JSON primaire).
     */
    public static function displayScalar(mixed $v, string $key = ''): string
    {
        return self::scalarToDisplay($v, $key);
    }

    /**
     * Aplatit les objets imbriqués en clés pointées (ex. restrictions.account.lock).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function flattenAssociative(array $data, string $prefix = ''): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            $key = $prefix === '' ? (string) $k : $prefix . '.' . (string) $k;
            if (is_array($v) && $v !== [] && self::isAssociativeArray($v)) {
                foreach (self::flattenAssociative($v, $key) as $fk => $fv) {
                    $out[$fk] = $fv;
                }
                continue;
            }
            $out[$key] = $v;
        }

        return $out;
    }

    /**
     * @param array<mixed, mixed> $arr
     */
    private static function isAssociativeArray(array $arr): bool
    {
        $i = 0;
        foreach ($arr as $k => $_) {
            if ($k !== $i) {
                return true;
            }
            $i++;
        }

        return false;
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
     * @param array<string, mixed> $row
     */
    private static function resolveEntityName(array $row, string $type): string
    {
        if (in_array($type, ['user', 'auth'], true)) {
            $dn = trim((string) ($row['entity_user_display_name'] ?? ''));
            if ($dn !== '') {
                return $dn;
            }
            $cs = trim((string) ($row['entity_user_callsign'] ?? ''));
            if ($cs !== '') {
                return $cs;
            }
            $em = trim((string) ($row['entity_user_email'] ?? ''));
            if ($em !== '') {
                return $em;
            }
        }
        if ($type === 'document') {
            $t = trim((string) ($row['entity_document_title'] ?? ''));
            if ($t !== '') {
                return $t;
            }
        }
        if ($type === 'role') {
            $n = trim((string) ($row['entity_role_name'] ?? ''));
            if ($n !== '') {
                return $n;
            }
        }
        if ($type === 'tenant') {
            $n = trim((string) ($row['entity_tenant_name'] ?? ''));
            if ($n !== '') {
                return $n;
            }
        }

        // Fallback : extraire un nom depuis le snapshot
        foreach ([$row['new_value'] ?? null, $row['old_value'] ?? null] as $raw) {
            $obj = self::decodeObject(is_string($raw) ? $raw : null);
            if (!is_array($obj)) {
                continue;
            }
            foreach (['display_name', 'callsign', 'name', 'title', 'role_name', 'email'] as $k) {
                $v = trim((string) ($obj[$k] ?? ''));
                if ($v !== '') {
                    return $v;
                }
            }
            if (isset($obj['valeur']) && is_scalar($obj['valeur'])) {
                $v = trim((string) $obj['valeur']);
                if ($v !== '' && filter_var($v, FILTER_VALIDATE_EMAIL)) {
                    return $v;
                }
            }
        }

        return '';
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

    private static function scalarToDisplay(mixed $v, string $key = ''): string
    {
        if ($v === null) {
            return '—';
        }
        if (is_bool($v)) {
            return $v ? 'Oui' : 'Non';
        }
        if (is_scalar($v)) {
            $s = (string) $v;
            if ($s === '') {
                return '—';
            }
            if ($key === 'status' || $key === 'statut' || str_ends_with($key, '.status')) {
                return self::STATUS_VALUE_LABELS[$s] ?? $s;
            }
            if ($key === 'subscription_status') {
                return match ($s) {
                    'none' => 'Sans abonnement payant',
                    'active' => 'Abonnement actif',
                    'trialing' => 'Période d’essai',
                    'past_due' => 'Paiement en retard',
                    'canceled' => 'Résilié',
                    'unpaid' => 'Impayé',
                    default => $s,
                };
            }
            if ($key === 'result') {
                return match ($s) {
                    'restored' => 'Restauré',
                    'alert_sent' => 'Alerte envoyée',
                    default => $s,
                };
            }
            if (($key === 'features_json' || $key === 'limits_json') && (str_starts_with($s, '{') || str_starts_with($s, '['))) {
                return 'Contenu structuré (voir le détail technique)';
            }

            return $s;
        }
        if (is_array($v)) {
            if ($v === []) {
                return '—';
            }
            $flat = self::isAssociativeArray($v) ? self::flattenAssociative($v) : $v;
            $parts = [];
            $n = 0;
            foreach ($flat as $fk => $fv) {
                if ($n >= 4) {
                    $parts[] = '…';
                    break;
                }
                $label = is_string($fk) ? self::fieldLabel($fk) : 'Élément';
                $parts[] = $label . ' : ' . self::scalarToDisplay($fv, is_string($fk) ? $fk : $key);
                $n++;
            }

            return implode(' · ', $parts);
        }

        return '—';
    }
}
