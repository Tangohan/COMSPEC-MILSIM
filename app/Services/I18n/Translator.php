<?php

declare(strict_types=1);

namespace App\Services\I18n;

/**
 * Charge des catalogues PHP sous lang/{locale}/{group}.php et résout des clés « group.key ».
 */
final class Translator
{
    private string $locale = 'fr';

    /** @var array<string, array<string, mixed>> */
    private array $loaded = [];

    public function setLocale(string $locale): void
    {
        $this->locale = LocaleService::normalize($locale);
        $this->loaded = [];
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * @param array<string, scalar|null> $replace
     */
    public function get(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale = $locale !== null ? LocaleService::normalize($locale) : $this->locale;
        $dot = strpos($key, '.');
        if ($dot === false) {
            $group = 'common';
            $item = $key;
        } else {
            $group = substr($key, 0, $dot);
            $item = substr($key, $dot + 1);
        }

        $value = $this->lookup($locale, $group, $item);
        if ($value === null && $locale !== 'fr') {
            $value = $this->lookup('fr', $group, $item);
        }
        if ($value === null) {
            $value = $key;
        }

        if ($replace === []) {
            return $value;
        }

        $search = [];
        $with = [];
        foreach ($replace as $k => $v) {
            $search[] = ':' . $k;
            $with[] = (string) ($v ?? '');
        }

        return str_replace($search, $with, $value);
    }

    private function lookup(string $locale, string $group, string $item): ?string
    {
        $catalog = $this->loadGroup($locale, $group);
        if ($catalog === []) {
            return null;
        }

        $parts = explode('.', $item);
        $cursor = $catalog;
        foreach ($parts as $part) {
            if (!is_array($cursor) || !array_key_exists($part, $cursor)) {
                return null;
            }
            $cursor = $cursor[$part];
        }

        return is_string($cursor) ? $cursor : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadGroup(string $locale, string $group): array
    {
        $cacheKey = $locale . '/' . $group;
        if (isset($this->loaded[$cacheKey])) {
            return $this->loaded[$cacheKey];
        }

        $group = preg_replace('/[^a-z0-9_\-]/i', '', $group) ?? '';
        if ($group === '') {
            return $this->loaded[$cacheKey] = [];
        }

        $path = base_path('lang/' . $locale . '/' . $group . '.php');
        if (!is_file($path)) {
            return $this->loaded[$cacheKey] = [];
        }

        /** @var mixed $data */
        $data = require $path;

        return $this->loaded[$cacheKey] = is_array($data) ? $data : [];
    }
}
