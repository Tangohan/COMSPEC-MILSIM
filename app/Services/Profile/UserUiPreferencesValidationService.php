<?php

declare(strict_types=1);

namespace App\Services\Profile;

/**
 * Valide dashboard_layout_json et favorite_modules_json (schema_version obligatoire pour le layout).
 */
class UserUiPreferencesValidationService
{
    private const ALLOWED_THEMES = ['system', 'light', 'dark', 'tenant'];
    private const ALLOWED_DENSITY = ['compact', 'comfortable'];

    /**
     * @return array{ok: bool, errors: list<string>, normalized: array<string, mixed>}
     */
    public function validatePatch(array $input): array
    {
        $errors = [];
        $normalized = [];

        if (array_key_exists('theme', $input)) {
            $t = (string) $input['theme'];
            if (!in_array($t, self::ALLOWED_THEMES, true)) {
                $errors[] = 'theme invalide (system|light|dark|tenant).';
            } else {
                $normalized['theme'] = $t;
            }
        }
        if (array_key_exists('density', $input)) {
            $d = (string) $input['density'];
            if (!in_array($d, self::ALLOWED_DENSITY, true)) {
                $errors[] = 'density invalide (compact|comfortable).';
            } else {
                $normalized['density'] = $d;
            }
        }
        if (array_key_exists('sidebar_collapsed', $input)) {
            $normalized['sidebar_collapsed'] = (bool) $input['sidebar_collapsed'];
        }
        if (array_key_exists('dashboard_layout_json', $input)) {
            $layout = $input['dashboard_layout_json'];
            if ($layout === null) {
                $normalized['dashboard_layout_json'] = null;
            } else {
                $dec = $this->normalizeLayout($layout);
                if ($dec === null) {
                    $errors[] = 'dashboard_layout_json : objet attendu avec schema_version >= 1.';
                } else {
                    $normalized['dashboard_layout_json'] = $dec;
                }
            }
        }
        if (array_key_exists('favorite_modules_json', $input)) {
            $fav = $input['favorite_modules_json'];
            if ($fav === null) {
                $normalized['favorite_modules_json'] = null;
            } else {
                $list = $this->normalizeFavoriteModules($fav);
                if ($list === null) {
                    $errors[] = 'favorite_modules_json : liste de chaînes attendue.';
                } else {
                    $normalized['favorite_modules_json'] = $list;
                }
            }
        }

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'normalized' => $normalized,
        ];
    }

    /** @return array<string, mixed>|null */
    private function normalizeLayout(mixed $layout): ?array
    {
        if (is_string($layout)) {
            $layout = json_decode($layout, true);
        }
        if (!is_array($layout)) {
            return null;
        }
        $ver = $layout['schema_version'] ?? null;
        if (!is_int($ver) && !is_numeric($ver)) {
            return null;
        }
        if ((int) $ver < 1) {
            return null;
        }
        $out = [
            'schema_version' => (int) $ver,
        ];
        if (isset($layout['widgets']) && is_array($layout['widgets'])) {
            $out['widgets'] = $layout['widgets'];
        }

        return $out;
    }

    /** @return list<string>|null */
    private function normalizeFavoriteModules(mixed $fav): ?array
    {
        if (is_string($fav)) {
            $fav = json_decode($fav, true);
        }
        if (!is_array($fav)) {
            return null;
        }
        $out = [];
        foreach ($fav as $item) {
            if (is_string($item) && $item !== '') {
                $out[] = $item;
            }
        }

        return $out;
    }
}
