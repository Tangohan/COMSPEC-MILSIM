<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SseWantedCatalog as C;
use App\Support\SseWantedCodes;
use App\Support\SseWantedRealNames;
use App\Support\SseWantedServices;

/**
 * Réglages de la notice de recherche, par communauté : autorité émettrice FICTIVE, service demandeur, format de référence,
 * libellé de la bannière, types d'avis, pied de page. Sans ligne (ou sans la table) : valeurs par défaut.
 */
final class SseWantedSettingsRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /** @return array<string, mixed> */
    public function get(int $tenantId): array
    {
        try {
            $row = $this->db->fetchOne('SELECT settings_json FROM sse_wanted_settings WHERE tenant_id = :t', ['t' => $tenantId]);
        } catch (\Throwable) {
            $row = null;
        }
        $data = $row !== null ? json_decode((string) $row['settings_json'], true) : [];

        return self::normalize(is_array($data) ? $data : []);
    }

    /**
     * Enregistre des réglages déjà validés ({@see self::validate()}).
     *
     * @param array<string, mixed> $settings
     */
    public function save(int $tenantId, array $settings, ?int $userId): void
    {
        $json = (string) json_encode(self::normalize($settings), JSON_UNESCAPED_UNICODE);
        $now = gmdate('Y-m-d H:i:s');
        if ($this->db->fetchOne('SELECT tenant_id FROM sse_wanted_settings WHERE tenant_id = :t', ['t' => $tenantId]) === null) {
            $this->db->execute(
                'INSERT INTO sse_wanted_settings (tenant_id, settings_json, updated_by_user_id, updated_at) VALUES (:t, :j, :u, :now)',
                ['t' => $tenantId, 'j' => $json, 'u' => $userId, 'now' => $now]
            );

            return;
        }
        $this->db->execute(
            'UPDATE sse_wanted_settings SET settings_json = :j, updated_by_user_id = :u, updated_at = :now WHERE tenant_id = :t',
            ['t' => $tenantId, 'j' => $json, 'u' => $userId, 'now' => $now]
        );
    }

    /**
     * Réglages complets et bornés, sur la base des valeurs par défaut.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function normalize(array $data): array
    {
        $d = C::DEFAULTS;
        $bool = static fn (mixed $v, bool $def): bool => $v === null ? $def : ($v === true || $v === 1 || $v === '1' || $v === 'on' || $v === 'true');
        $line = static fn (mixed $v, int $max, string $def): string => ($s = C::line($v, $max)) !== '' ? $s : $def;
        $format = C::line($data['ref_format'] ?? '', 40);
        if (!self::validFormat($format)) {
            $format = (string) $d['ref_format'];
        }
        $disabled = [];
        foreach (is_array($data['disabled_types'] ?? null) ? $data['disabled_types'] : [] as $k) {
            if (is_string($k) && isset(C::TYPES[$k])) {
                $disabled[] = $k;
            }
        }
        // Au moins un type reste proposé (types fournis ou propres à la communauté).
        $custom = self::normalizeCustom($data['custom_types'] ?? []);
        if (count(array_diff(array_keys(C::TYPES), $disabled)) === 0 && $custom === []) {
            $disabled = [];
        }

        return [
            'authority_fr' => $line($data['authority_fr'] ?? null, 160, (string) $d['authority_fr']),
            'authority_en' => $line($data['authority_en'] ?? null, 160, (string) $d['authority_en']),
            'contact_label' => C::line($data['contact_label'] ?? ($d['contact_label']), 160),
            'contact_detail' => C::line($data['contact_detail'] ?? '', 200),
            'ref_format' => $format,
            'banner' => C::pick(C::BANNERS, $data['banner'] ?? null, (string) $d['banner']),
            'emblem' => C::pick(C::EMBLEMS, $data['emblem'] ?? null, (string) $d['emblem']),
            'pdf_lang' => in_array($data['pdf_lang'] ?? null, ['auto', 'fr', 'en'], true) ? (string) $data['pdf_lang'] : 'auto',
            'watermark' => $bool($data['watermark'] ?? null, true),
            'footer' => $bool($data['footer'] ?? null, true),
            'default_classification' => isset(\App\Support\SseUsClassification::SCALE[(string) ($data['default_classification'] ?? '')]) ? (string) $data['default_classification'] : (string) $d['default_classification'],
            'default_expiry_days' => max(0, min(3650, (int) ($data['default_expiry_days'] ?? 0))),
            'default_instructions_fr' => C::text($data['default_instructions_fr'] ?? $d['default_instructions_fr'], C::INSTRUCTIONS_MAX),
            'default_instructions_en' => C::text($data['default_instructions_en'] ?? $d['default_instructions_en'], C::INSTRUCTIONS_MAX),
            'disabled_types' => $disabled,
            'custom_types' => $custom,
            'codes' => SseWantedCodes::normalizeSettings($data['codes'] ?? []),
            'services' => SseWantedServices::normalizeSettings($data['services'] ?? []),
            'process' => self::normalizeProcess($data['process'] ?? []),
        ];
    }

    /**
     * Valide la saisie du formulaire des réglages : refuse un nom d'organisation réelle, un format sans numéro, des types
     * mal formés.
     *
     * @param array<string, mixed> $input
     * @return array{settings: array<string, mixed>, errors: list<string>}
     */
    public static function validate(array $input): array
    {
        $errors = [];
        foreach (['authority_fr', 'authority_en', 'contact_label', 'contact_detail'] as $field) {
            $hit = SseWantedRealNames::find((string) ($input[$field] ?? ''));
            if ($hit !== null) {
                $errors[] = 'L’autorité et le service demandeur doivent être fictifs : « ' . $hit . ' » est un nom d’organisation réelle.';
            }
        }
        $format = C::line($input['ref_format'] ?? '', 40);
        if ($format !== '' && !self::validFormat($format)) {
            $errors[] = 'Le format de référence doit contenir {N} (numéro) et seulement des lettres, chiffres, « - », « / », « . », « _ » et les repères {N}, {N:5}, {Y}, {y}.';
        }
        $custom = [];
        foreach (preg_split('/\R/u', C::text($input['custom_types'] ?? '', 2000)) ?: [] as $lineText) {
            $lineText = trim($lineText);
            if ($lineText === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $lineText));
            if (preg_match('/^[a-z0-9_]{2,24}$/', $parts[0]) !== 1 || ($parts[1] ?? '') === '') {
                $errors[] = 'Type propre mal écrit : « ' . mb_substr($lineText, 0, 60) . ' » (attendu : clé|libellé français|libellé anglais, clé en minuscules).';
                continue;
            }
            if (isset(C::TYPES[$parts[0]])) {
                $errors[] = 'La clé « ' . $parts[0] . ' » est déjà un type fourni : renommez-la.';
                continue;
            }
            $custom[$parts[0]] = [C::line($parts[1], 80), C::line($parts[2] ?? $parts[1], 80)];
        }
        if (count($custom) > C::MAX_CUSTOM_TYPES) {
            $errors[] = 'Huit types propres au plus.';
            $custom = array_slice($custom, 0, C::MAX_CUSTOM_TYPES, true);
        }
        foreach ($custom as $labels) {
            $hit = SseWantedRealNames::find($labels[0] . ' ' . $labels[1]);
            if ($hit !== null) {
                $errors[] = 'Un type d’avis cite une organisation réelle : « ' . $hit . ' ».';
            }
        }
        $enabled = is_array($input['enabled_types'] ?? null) ? $input['enabled_types'] : [];
        $disabled = [];
        foreach (array_keys(C::TYPES) as $key) {
            if (!in_array($key, $enabled, true)) {
                $disabled[] = $key;
            }
        }
        $settings = $input;
        $settings['custom_types'] = $custom;
        $settings['disabled_types'] = $disabled;
        $settings['watermark'] = !empty($input['watermark']);
        $settings['footer'] = !empty($input['footer']);

        return ['settings' => self::normalize($settings), 'errors' => $errors];
    }

    /** Format valide : porte {N} (ou {N:1} à {N:8}), seulement des repères connus et des caractères sûrs. */
    public static function validFormat(string $format): bool
    {
        if ($format === '' || strlen($format) > 40 || preg_match('/\{N(?::[1-8])?\}/', $format) !== 1) {
            return false;
        }
        $rest = (string) preg_replace('/\{(?:N(?::[1-8])?|Y|y)\}/', '', $format);

        return preg_match('/^[A-Za-z0-9\-\/._ ]*$/', $rest) === 1;
    }

    /**
     * Réglages du processus d'émission : circuit de validation (un analyste propose, un responsable diffuse), fuseau de la
     * communauté pour les dates, notification des abonnés.
     *
     * @return array{validation: bool, timezone: string, notify: bool}
     */
    public static function normalizeProcess(mixed $raw): array
    {
        $raw = is_array($raw) ? $raw : [];
        $tz = is_string($raw['timezone'] ?? null) ? trim($raw['timezone']) : '';
        if ($tz === '' || !in_array($tz, \DateTimeZone::listIdentifiers(), true)) {
            $tz = 'Europe/Paris';
        }

        return [
            'validation' => ($raw['validation'] ?? false) === true || ($raw['validation'] ?? '') === '1' || ($raw['validation'] ?? '') === 1,
            'timezone' => $tz,
            'notify' => !array_key_exists('notify', $raw) || $raw['notify'] === true || $raw['notify'] === '1' || $raw['notify'] === 1,
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    private static function normalizeCustom(mixed $raw): array
    {
        $out = [];
        if (!is_array($raw)) {
            return $out;
        }
        foreach ($raw as $key => $labels) {
            if (!is_string($key) || preg_match('/^[a-z0-9_]{2,24}$/', $key) !== 1 || isset(C::TYPES[$key]) || !is_array($labels)) {
                continue;
            }
            $fr = C::line($labels[0] ?? '', 80);
            if ($fr === '') {
                continue;
            }
            $out[$key] = [$fr, C::line($labels[1] ?? $fr, 80) ?: $fr];
            if (count($out) >= C::MAX_CUSTOM_TYPES) {
                break;
            }
        }

        return $out;
    }
}
