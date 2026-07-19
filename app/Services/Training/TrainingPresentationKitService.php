<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Repositories\TenantRepository;

/**
 * Kits de présentation LMS réutilisables (apparence + médias), stockés dans tenants.settings.
 */
final class TrainingPresentationKitService
{
    private const SETTINGS_KEY = 'lms_presentation_kits';

    private const MAX_KITS = 24;

    public function __construct(
        private TenantRepository $tenantRepository,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listKits(int $tenantId): array
    {
        $settings = $this->tenantRepository->getSettings($tenantId);
        $raw = $settings[self::SETTINGS_KEY] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = trim((string) ($row['id'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            if ($id === '' || $name === '') {
                continue;
            }
            $out[] = $row;
        }

        usort($out, static function (array $a, array $b): int {
            $ta = (string) ($a['saved_at'] ?? '');
            $tb = (string) ($b['saved_at'] ?? '');

            return strcmp($tb, $ta);
        });

        return $out;
    }

    /**
     * @param array{
     *   theme_enable?: bool,
     *   accent?: string,
     *   font_key?: string,
     *   radius_key?: string,
     *   variant?: string,
     *   opening_loader_image?: string|null,
     *   opening_loader_title?: string|null,
     *   opening_loader_body?: string|null,
     *   thumbnail_path?: string|null,
     *   banner_path?: string|null,
     *   instruction_audio_url?: string|null,
     *   instruction_audio_instructor_optional?: bool,
     *   instruction_audio_notes?: string|null
     * } $payload
     * @return array<string, mixed>
     */
    public function saveKit(int $tenantId, string $name, array $payload): array
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Indiquez un nom pour ce kit de présentation.');
        }
        if (mb_strlen($name) > 80) {
            $name = mb_substr($name, 0, 80);
        }

        $kits = $this->listKits($tenantId);
        if (count($kits) >= self::MAX_KITS) {
            throw new \InvalidArgumentException('Vous avez atteint le nombre maximum de kits enregistrés (' . self::MAX_KITS . '). Supprimez-en un avant d’en créer un autre.');
        }

        $kit = [
            'id' => bin2hex(random_bytes(8)),
            'name' => $name,
            'saved_at' => gmdate('c'),
            'payload' => $this->normalizePayload($payload),
        ];
        $kits[] = $kit;
        $this->persist($tenantId, $kits);

        return $kit;
    }

    public function findKit(int $tenantId, string $kitId): ?array
    {
        $kitId = trim($kitId);
        if ($kitId === '') {
            return null;
        }
        foreach ($this->listKits($tenantId) as $kit) {
            if ((string) ($kit['id'] ?? '') === $kitId) {
                return $kit;
            }
        }

        return null;
    }

    public function deleteKit(int $tenantId, string $kitId): bool
    {
        $kitId = trim($kitId);
        if ($kitId === '') {
            return false;
        }
        $kits = $this->listKits($tenantId);
        $filtered = array_values(array_filter(
            $kits,
            static fn (array $k): bool => (string) ($k['id'] ?? '') !== $kitId
        ));
        if (count($filtered) === count($kits)) {
            return false;
        }
        $this->persist($tenantId, $filtered);

        return true;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function normalizePayload(array $payload): array
    {
        $accent = trim((string) ($payload['accent'] ?? '#10b981'));
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $accent)) {
            $accent = '#10b981';
        }

        $optionalStr = static function (mixed $v, int $max): ?string {
            $t = trim((string) ($v ?? ''));
            if ($t === '') {
                return null;
            }

            return mb_substr($t, 0, $max);
        };

        return [
            'theme_enable' => !empty($payload['theme_enable']),
            'accent' => $accent,
            'font_key' => mb_substr(trim((string) ($payload['font_key'] ?? 'inter')), 0, 40),
            'radius_key' => mb_substr(trim((string) ($payload['radius_key'] ?? 'generous')), 0, 40),
            'variant' => mb_substr(trim((string) ($payload['variant'] ?? 'default')), 0, 40),
            'opening_loader_image' => $optionalStr($payload['opening_loader_image'] ?? null, 255),
            'opening_loader_title' => $optionalStr($payload['opening_loader_title'] ?? null, 120),
            'opening_loader_body' => $optionalStr($payload['opening_loader_body'] ?? null, 320),
            'thumbnail_path' => $optionalStr($payload['thumbnail_path'] ?? null, 255),
            'banner_path' => $optionalStr($payload['banner_path'] ?? null, 255),
            'instruction_audio_url' => $optionalStr($payload['instruction_audio_url'] ?? null, 512),
            'instruction_audio_instructor_optional' => array_key_exists('instruction_audio_instructor_optional', $payload)
                ? !empty($payload['instruction_audio_instructor_optional'])
                : true,
            'instruction_audio_notes' => $optionalStr($payload['instruction_audio_notes'] ?? null, 500),
        ];
    }

    /**
     * @param list<array<string, mixed>> $kits
     */
    private function persist(int $tenantId, array $kits): void
    {
        $this->tenantRepository->mergeSettings($tenantId, [
            self::SETTINGS_KEY => array_values($kits),
        ]);
    }
}
