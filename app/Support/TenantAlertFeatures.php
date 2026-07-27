<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Options de comportement des annonces communauté (masquage, Discord, priorité).
 */
final class TenantAlertFeatures
{
    public const DISMISSIBLE = 'dismissible';
    public const NOTIFY_DISCORD = 'notify_discord';
    public const HIGHLIGHT = 'highlight';

    /**
     * @return array<string, array{label: string, hint: string, default: bool}>
     */
    public static function definitions(): array
    {
        return [
            self::DISMISSIBLE => [
                'label' => 'Masquage autorisé',
                'hint' => 'Les membres peuvent fermer l’annonce après lecture.',
                'default' => true,
            ],
            self::NOTIFY_DISCORD => [
                'label' => 'Diffusion sur Discord',
                'hint' => 'Publier aussi sur le canal Discord de la communauté, si un webhook est configuré.',
                'default' => false,
            ],
            self::HIGHLIGHT => [
                'label' => 'Mise en avant',
                'hint' => 'Affiche l’annonce en tête de liste, avant les autres messages du même emplacement.',
                'default' => false,
            ],
        ];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::definitions());
    }

    /**
     * @param array<string, mixed>|null $stored
     * @return array<string, bool>
     */
    public static function resolve(?array $stored): array
    {
        $defs = self::definitions();
        $out = [];
        foreach ($defs as $key => $meta) {
            if (is_array($stored) && array_key_exists($key, $stored)) {
                $out[$key] = filter_var($stored[$key], FILTER_VALIDATE_BOOLEAN);
            } else {
                $out[$key] = (bool) $meta['default'];
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed>|null $stored
     */
    public static function isEnabled(?array $stored, string $key): bool
    {
        $resolved = self::resolve($stored);

        return $resolved[$key] ?? (self::definitions()[$key]['default'] ?? false);
    }

    /**
     * @param array<string, mixed> $input checkbox values from request (key => '1'|'0'|bool)
     * @return array<string, bool>
     */
    public static function fromRequest(array $input): array
    {
        $out = [];
        foreach (self::definitions() as $key => $meta) {
            if (array_key_exists($key, $input)) {
                $raw = $input[$key];
                $out[$key] = $raw === '1' || $raw === 'on' || $raw === true;
            } else {
                $out[$key] = (bool) $meta['default'];
            }
        }

        return $out;
    }

    /** @param array<string, bool> $features */
    public static function encodeJson(array $features): string
    {
        $payload = [];
        foreach (self::keys() as $key) {
            $payload[$key] = !empty($features[$key]);
        }
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

        return is_string($json) ? $json : '{}';
    }

    /**
     * @return array<string, bool>
     */
    public static function decodeJson(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return self::resolve(null);
        }
        if (is_array($raw)) {
            return self::resolve($raw);
        }
        if (is_string($raw)) {
            $d = json_decode($raw, true);

            return is_array($d) ? self::resolve($d) : self::resolve(null);
        }

        return self::resolve(null);
    }
}
