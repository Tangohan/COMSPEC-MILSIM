<?php

declare(strict_types=1);

namespace App\Support;

/**
 * SSE identités : catalogues de l'avis de recherche (jeu de rôle, aucune donnée réelle) : types d'avis, niveaux de menace,
 * état armé, statuts, types de détection système et d'événements de la chronologie, réglages par défaut de la notice.
 *
 * Chaque libellé existe en français et en anglais. Une communauté peut renommer ou retirer les types d'avis et en ajouter
 * (voir {@see self::types()}) ; les autres catalogues sont fixes.
 */
final class SseWantedCatalog
{
    /** Types d'avis fournis (clé => [français, anglais]). */
    public const TYPES = [
        'arrest' => ['Recherche en vue d’arrestation', 'Wanted for arrest'],
        'hvt' => ['Cible de haute valeur (HVT)', 'High-value target (HVT)'],
        'capture_alive' => ['À capturer vivant', 'Capture alive'],
        'capture_or_kill' => ['À capturer ou neutraliser', 'Capture or neutralize'],
        'poi' => ['Personne d’intérêt (PoI)', 'Person of interest (PoI)'],
        'info' => ['Recherche d’information', 'Information sought'],
        'surveillance' => ['Surveillance', 'Surveillance'],
        'escaped' => ['Détenu en fuite', 'Escaped detainee'],
    ];

    /** Niveaux de menace, du plus faible au plus fort (clé => [français, anglais]). */
    public const THREATS = [
        'low' => ['Faible', 'Low'],
        'medium' => ['Moyen', 'Medium'],
        'high' => ['Élevé', 'High'],
        'critical' => ['Critique', 'Critical'],
    ];

    public const ARMED = [
        'yes' => ['Armé et dangereux', 'Armed and dangerous'],
        'no' => ['Non armé', 'Not armed'],
        'unknown' => ['Inconnu', 'Unknown'],
    ];

    public const STATUSES = [
        'active' => ['Actif', 'Active'],
        'lifted' => ['Levé', 'Lifted'],
        'expired' => ['Expiré', 'Expired'],
        'captured' => ['Capturé', 'Captured'],
    ];

    /** Statuts qui ferment un avis (levée avec motif obligatoire). */
    public const CLOSING = ['lifted', 'captured'];

    public const DETECTION_TYPES = [
        'id_check' => ['Contrôle d’identité', 'Identity check'],
        'seek' => ['Scan biométrique SEEK II', 'SEEK II biometric scan'],
        'file_query' => ['Consultation des fichiers', 'File query'],
        'photo' => ['Photo ajoutée à l’identité', 'Photo added to the identity'],
        'link' => ['Rapprochement (adresse, plaque, personne liée)', 'Link (address, plate, linked person)'],
        'phone' => ['Téléphone rattaché', 'Phone attached'],
        'cross_match' => ['Recoupement d’un papier', 'Document cross-match'],
    ];

    public const DETECTION_STATUSES = [
        'new' => ['Nouvelle', 'New'],
        'acknowledged' => ['Prise en compte', 'Acknowledged'],
        'dismissed' => ['Écartée', 'Dismissed'],
    ];

    public const DETECTION_SOURCES = [
        'game' => ['Jeu', 'Game'],
        'web' => ['Site', 'Site'],
        'system' => ['Système', 'System'],
    ];

    /** Événements écrits dans la chronologie (clé => [français, anglais, ton]). */
    public const EVENTS = [
        'identity.created' => ['Identité créée', 'Identity created', 'info'],
        'identity.updated' => ['Fiche modifiée', 'Record edited', 'info'],
        'identity.photo' => ['Photo ou signature modifiée', 'Photo or signature changed', 'info'],
        'document.created' => ['Papier préparé', 'Document prepared', 'info'],
        'check.recorded' => ['Contrôle d’identité', 'Identity check', 'info'],
        'query.recorded' => ['Consultation des fichiers', 'File query', 'muted'],
        'file.photo' => ['Photo versée au dossier', 'Photo added to the file', 'info'],
        'file.mention' => ['Mention aux fichiers', 'File mention', 'warn'],
        'notice.placed' => ['Avis de recherche placé', 'Wanted notice placed', 'bad'],
        'notice.lifted' => ['Avis de recherche levé', 'Wanted notice lifted', 'ok'],
        'notice.captured' => ['Personne capturée', 'Subject captured', 'ok'],
        'notice.submitted' => ['Avis proposé à la validation', 'Notice submitted for validation', 'warn'],
        'notice.validated' => ['Avis validé et diffusé', 'Notice validated and released', 'bad'],
        'notice.rejected' => ['Avis refusé à la validation', 'Notice rejected at validation', 'muted'],
        'notice.renewed' => ['Avis renouvelé', 'Notice renewed', 'info'],
        'notice.extended' => ['Avis prolongé', 'Notice extended', 'info'],
        'notice.expired' => ['Avis de recherche expiré', 'Wanted notice expired', 'muted'],
        'notice.pdf' => ['Notice PDF générée', 'PDF notice generated', 'muted'],
        'notice.attachment_added' => ['Image jointe à l’avis', 'Image attached to the notice', 'info'],
        'notice.attachment_removed' => ['Image retirée de l’avis', 'Image removed from the notice', 'muted'],
        'document.issued' => ['Document PDF émis', 'PDF document issued', 'muted'],
        'detection.raised' => ['Détection système', 'System detection', 'bad'],
        'detection.handled' => ['Détection traitée', 'Detection handled', 'muted'],
        'audit' => ['Action enregistrée au journal', 'Action logged in the audit trail', 'muted'],
    ];

    /** Familles de filtre de la chronologie (clé => [français, anglais, types d'événements]). */
    public const EVENT_FILTERS = [
        'record' => ['Fiche', 'Record', ['identity.created', 'identity.updated', 'identity.photo', 'file.photo', 'audit']],
        'papers' => ['Papiers', 'Documents', ['document.created', 'document.issued']],
        'checks' => ['Contrôles et consultations', 'Checks and queries', ['check.recorded', 'query.recorded']],
        'notices' => ['Avis de recherche', 'Wanted notices', ['notice.placed', 'notice.submitted', 'notice.validated', 'notice.rejected', 'notice.renewed', 'notice.extended', 'notice.lifted', 'notice.captured', 'notice.expired', 'notice.pdf', 'notice.attachment_added', 'notice.attachment_removed', 'file.mention']],
        'detections' => ['Détections', 'Detections', ['detection.raised', 'detection.handled']],
    ];

    /** Pictogrammes de l'en-tête de la notice. */
    public const EMBLEMS = ['neutral' => ['Pictogramme neutre', 'Neutral pictogram'], 'community' => ['Logo de la communauté', 'Community logo']];

    /** Libellés de la bannière (français, anglais), au choix de la communauté. */
    public const BANNERS = [
        'red' => ['NOTICE ROUGE', 'RED NOTICE'],
        'wanted' => ['AVIS DE RECHERCHE', 'WANTED NOTICE'],
    ];

    /** Réglages par défaut de la notice (rien de réel : autorité et service demandeur fictifs). */
    public const DEFAULTS = [
        'authority_fr' => 'Agence interservices du renseignement',
        'authority_en' => 'Interservice Intelligence Agency',
        'contact_label' => 'Cellule de coordination des recherches',
        'contact_detail' => '',
        'ref_format' => 'A-{N}/{Y}',
        'banner' => 'red',
        'emblem' => 'neutral',
        'pdf_lang' => 'auto',
        'default_classification' => 'encadrement',
        'default_expiry_days' => 0,
        'default_instructions_fr' => 'Signaler toute information au service demandeur. Ne pas intervenir sans ordre de la chaîne de commandement.',
        'default_instructions_en' => 'Report any information to the requesting service. Do not intervene without orders from the chain of command.',
        'disabled_types' => [],
        'custom_types' => [],
    ];

    public const MAX_CUSTOM_TYPES = 8;

    public const REASON_MAX = 2000;

    public const INSTRUCTIONS_MAX = 1000;

    /**
     * Types proposés à la communauté : types fournis moins ceux qu'elle a retirés, plus les siens.
     *
     * @param array<string, mixed> $config réglages normalisés ({@see \App\Repositories\SseWantedSettingsRepository::normalize()})
     * @return array<string, array{0: string, 1: string}>
     */
    public static function types(array $config = []): array
    {
        $out = [];
        $disabled = is_array($config['disabled_types'] ?? null) ? $config['disabled_types'] : [];
        foreach (self::TYPES as $key => $labels) {
            if (!in_array($key, $disabled, true)) {
                $out[$key] = $labels;
            }
        }
        $custom = is_array($config['custom_types'] ?? null) ? $config['custom_types'] : [];
        foreach ($custom as $key => $labels) {
            if (is_string($key) && is_array($labels) && !isset($out[$key]) && !isset(self::TYPES[$key])) {
                $out[$key] = [(string) ($labels[0] ?? $key), (string) ($labels[1] ?? ($labels[0] ?? $key))];
            }
        }

        return $out;
    }

    /**
     * Libellé d'un type, y compris d'un type retiré depuis (les avis déjà posés restent lisibles).
     *
     * @param array<string, mixed> $config
     */
    public static function typeLabel(string $key, array $config = [], string $lang = 'fr'): string
    {
        $all = self::types($config);
        $labels = $all[$key] ?? self::TYPES[$key] ?? null;
        if ($labels === null) {
            $custom = is_array($config['custom_types'] ?? null) ? $config['custom_types'] : [];
            $labels = is_array($custom[$key] ?? null) ? $custom[$key] : null;
        }

        return $labels === null ? $key : self::pickLang($labels, $lang);
    }

    /** @param array<string, array{0: string, 1: string}|array{0: string, 1: string, 2?: mixed}> $map */
    public static function label(array $map, string $key, string $lang = 'fr', string $fallback = '—'): string
    {
        return isset($map[$key]) ? self::pickLang($map[$key], $lang) : $fallback;
    }

    /** @param array<int, mixed> $labels */
    private static function pickLang(array $labels, string $lang): string
    {
        return (string) ($lang === 'en' ? ($labels[1] ?? $labels[0] ?? '') : ($labels[0] ?? ''));
    }

    /** Rang d'un niveau de menace (0 à 3), pour trier et comparer. */
    public static function threatRank(string $level): int
    {
        $i = array_search($level, array_keys(self::THREATS), true);

        return is_int($i) ? $i : 1;
    }

    /**
     * Valeur d'une liste fermée, ou la valeur par défaut.
     *
     * @param array<string, mixed> $map
     */
    public static function pick(array $map, mixed $value, string $default): string
    {
        return is_string($value) && isset($map[$value]) ? $value : $default;
    }

    /** Langue de la notice : « fr » ou « en ». */
    public static function lang(mixed $value): string
    {
        return $value === 'en' ? 'en' : 'fr';
    }

    /** Date « Y-m-d H:i:s » ou « Y-m-d » ou « Y-m-d\TH:i » valide, sinon null. */
    public static function dateTime(mixed $value): ?string
    {
        $s = trim(is_scalar($value) ? (string) $value : '');
        if ($s === '') {
            return null;
        }
        $s = str_replace('T', ' ', $s);
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'] as $format) {
            $d = \DateTimeImmutable::createFromFormat('!' . $format, $s);
            if ($d !== false && $d->format($format) === $s) {
                $year = (int) $d->format('Y');

                return $year >= 1970 && $year <= 2100 ? $d->format('Y-m-d H:i:s') : null;
            }
        }

        return null;
    }

    /** Texte d'une ligne : sans retour à la ligne ni caractère de contrôle, borné. */
    public static function line(mixed $value, int $max): string
    {
        $s = is_scalar($value) ? (string) $value : '';
        $s = (string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $s);

        return mb_substr(trim((string) preg_replace('/\s+/u', ' ', $s)), 0, $max);
    }

    /** Texte libre : retours à la ligne gardés, sans caractère de contrôle, borné. */
    public static function text(mixed $value, int $max): string
    {
        $s = is_scalar($value) ? (string) $value : '';
        $s = str_replace("\r\n", "\n", $s);
        $s = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $s);

        return mb_substr(trim($s), 0, $max);
    }
}
