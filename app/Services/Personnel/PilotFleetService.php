<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\GameItemIconRepository;
use App\Repositories\PilotFleetRepository;
use App\Support\PilotFleet;
use App\Support\PilotSyllabusCatalog;
use RuntimeException;
use Throwable;

/**
 * Flotte du cursus pilote : appareils réellement pilotés dans la communauté (relevés par le mod), regroupés par
 * appareil et par famille, avec leurs heures, pilotes et dernier vol ; appareils reconnus jamais pilotés ; appareils
 * et familles créés par la communauté.
 */
final class PilotFleetService
{
    public function __construct(
        private PilotFleetRepository $repo,
        private ?QualificationBadgeStorageService $storage = null,
        private ?GameItemIconRepository $icons = null,
    ) {
    }

    public function schemaReady(): bool
    {
        return $this->repo->schemaReady();
    }

    public function fleet(int $tenantId): PilotFleet
    {
        return $this->repo->fleet($tenantId);
    }

    /**
     * Appareils proposés au cursus, les plus pilotés d'abord : pilotés (heures, pilotes, dernier vol, classes vues),
     * puis appareils de la communauté et appareils reconnus jamais pilotés.
     *
     * @return list<array{key: string, label: string, group: string, track: string, classes: list<array{class: string, label: string, hours: float}>, hours: float, pilots: int, last_flown_at: string, image: string, flown: bool, custom: bool, archived: bool, configured: bool}>
     */
    public function aircraft(int $tenantId): array
    {
        $fleet = $this->repo->fleet($tenantId);

        return self::buildAircraft($fleet, $this->repo->flownClasses($tenantId), $this->repo->pilotsByClass($tenantId), $this->imageUrls($tenantId, $fleet));
    }

    /**
     * Regroupe les classes pilotées par appareil (pur, testable seul).
     *
     * @param list<array{vehicle_class: string, vehicle_label: string, vehicle_kind: string, seconds: int, pilots: int, last_flown_at: string}> $flown
     * @param array<string, list<int>> $pilotsByClass
     * @param array<string, string> $images clé d'appareil ou classe en minuscules => URL d'image
     * @return list<array<string, mixed>>
     */
    public static function buildAircraft(PilotFleet $fleet, array $flown, array $pilotsByClass = [], array $images = []): array
    {
        $acc = [];
        foreach ($flown as $f) {
            $class = (string) $f['vehicle_class'];
            $r = $fleet->resolve($class, (string) $f['vehicle_label'], (string) $f['vehicle_kind']);
            $key = $r['key'];
            $acc[$key] ??= [
                'key' => $key,
                'label' => $r['label'],
                'group' => $r['group'],
                'track' => $r['track'],
                'classes' => [],
                'seconds' => 0,
                'pilot_ids' => [],
                'pilots_fallback' => 0,
                'last_flown_at' => '',
                'flown' => true,
            ];
            $acc[$key]['classes'][] = ['class' => $class, 'label' => (string) $f['vehicle_label'], 'hours' => round((int) $f['seconds'] / 3600, 1)];
            $acc[$key]['seconds'] += (int) $f['seconds'];
            foreach ($pilotsByClass[strtolower($class)] ?? [] as $uid) {
                $acc[$key]['pilot_ids'][$uid] = true;
            }
            $acc[$key]['pilots_fallback'] = max($acc[$key]['pilots_fallback'], (int) $f['pilots']);
            if (strcmp((string) $f['last_flown_at'], $acc[$key]['last_flown_at']) > 0) {
                $acc[$key]['last_flown_at'] = (string) $f['last_flown_at'];
            }
        }
        // Appareils de la communauté et appareils reconnus jamais pilotés : ajoutables au cursus quand même.
        foreach ($fleet->aircraftRows() as $key => $row) {
            if (!empty($row['is_custom']) && !isset($acc[$key])) {
                $acc[$key] = ['key' => (string) $key, 'label' => (string) $row['name'], 'group' => (string) ($row['group_key'] ?: 'other'), 'track' => $fleet->trackOf((string) $key), 'classes' => [], 'seconds' => 0, 'pilot_ids' => [], 'pilots_fallback' => 0, 'last_flown_at' => '', 'flown' => false];
                foreach ($fleet->classesOf((string) $key) as $c) {
                    $acc[$key]['classes'][] = ['class' => $c, 'label' => '', 'hours' => 0.0];
                }
            }
        }
        foreach ([PilotSyllabusCatalog::TRACK_HELI, PilotSyllabusCatalog::TRACK_PLANE] as $track) {
            foreach (PilotSyllabusCatalog::knownFamilies($track) as $key => $label) {
                if (isset($acc[$key])) {
                    continue;
                }
                $row = $fleet->aircraftRow($key);
                $group = (string) ($row['group_key'] ?? '');
                $acc[$key] = ['key' => $key, 'label' => trim((string) ($row['name'] ?? '')) ?: $label, 'group' => $group !== '' ? $group : (PilotFleet::builtinGroup($key) ?? 'other'), 'track' => $fleet->trackOf($key, $key), 'classes' => [], 'seconds' => 0, 'pilot_ids' => [], 'pilots_fallback' => 0, 'last_flown_at' => '', 'flown' => false];
            }
        }
        $rows = $fleet->aircraftRows();
        $out = [];
        foreach ($acc as $key => $a) {
            $row = $rows[$key] ?? null;
            $image = $images[$key] ?? '';
            foreach ($a['classes'] as $c) {
                $image = $image !== '' ? $image : ($images[strtolower($c['class'])] ?? '');
            }
            usort($a['classes'], static fn (array $x, array $y): int => $y['hours'] <=> $x['hours']);
            $out[] = [
                'key' => (string) $key,
                'label' => (string) $a['label'],
                'group' => (string) $a['group'],
                'track' => (string) $a['track'],
                'classes' => $a['classes'],
                'hours' => round($a['seconds'] / 3600, 1),
                'pilots' => $a['pilot_ids'] !== [] ? count($a['pilot_ids']) : $a['pilots_fallback'],
                'last_flown_at' => (string) $a['last_flown_at'],
                'image' => $image,
                'flown' => (bool) $a['flown'],
                'custom' => !empty($row['is_custom']),
                'archived' => $row !== null && !empty($row['archived_at']),
                'configured' => $row !== null,
            ];
        }
        usort($out, static fn (array $x, array $y): int => [$y['flown'], $y['hours'], $x['label']] <=> [$x['flown'], $x['hours'], $y['label']]);

        return $out;
    }

    /** @return array<string, string> */
    private function imageUrls(int $tenantId, PilotFleet $fleet): array
    {
        $out = [];
        foreach ($fleet->aircraftRows() as $key => $row) {
            if (trim((string) ($row['image_path'] ?? '')) !== '' && $this->storage !== null) {
                $out[(string) $key] = $this->storage->publicUrl((string) $row['image_path']);
            }
        }
        // Icônes envoyées par COMSPEC Link (lues dans les PBO), quand elles existent pour ces classes.
        if ($this->icons !== null) {
            try {
                $classes = array_map(static fn (array $f): string => (string) $f['vehicle_class'], $this->repo->flownClasses($tenantId));
                foreach ($this->icons->iconUrls($tenantId, $classes) as $class => $url) {
                    $out[strtolower((string) $class)] = $url;
                }
            } catch (Throwable) {
            }
        }

        return $out;
    }

    /**
     * Crée ou règle un appareil : nom, filière, famille, classes Arma rattachées, image, archivage. Retourne sa clé.
     *
     * @param array<string, mixed> $in
     * @param array<string, mixed>|null $file image envoyée ($_FILES)
     */
    public function saveAircraft(int $tenantId, array $in, ?array $file, ?int $actorId): string
    {
        if (!$this->repo->schemaReady()) {
            throw new RuntimeException('Les appareils de la communauté arrivent avec la prochaine mise à jour du site (migration à lancer).');
        }
        $fleet = $this->repo->fleet($tenantId);
        $name = mb_substr(trim((string) ($in['name'] ?? '')), 0, 120);
        if ($name === '') {
            throw new RuntimeException('Donnez un nom à l’appareil.');
        }
        $key = strtolower(trim((string) ($in['aircraft_key'] ?? '')));
        $isNew = $key === '';
        if ($isNew) {
            $base = PilotFleet::customKey($name);
            $key = $base;
            for ($i = 2; $this->repo->findAircraft($tenantId, $key) !== null || isset(PilotSyllabusCatalog::knownFamilies(PilotSyllabusCatalog::TRACK_HELI)[$key]) || isset(PilotSyllabusCatalog::knownFamilies(PilotSyllabusCatalog::TRACK_PLANE)[$key]); $i++) {
                $key = substr($base, 0, 76) . '_' . $i;
            }
        } elseif (!PilotFleet::validKey($key)) {
            throw new RuntimeException('Appareil introuvable.');
        }
        $existing = $this->repo->findAircraft($tenantId, $key);
        $custom = $isNew || !empty($existing['is_custom']);
        $track = (string) ($in['track'] ?? '');
        $track = in_array($track, [PilotSyllabusCatalog::TRACK_HELI, PilotSyllabusCatalog::TRACK_PLANE, PilotFleet::TRACK_OTHER], true) ? $track : null;
        if ($custom && $track === null) {
            $track = PilotSyllabusCatalog::TRACK_HELI;
        }
        $group = (string) ($in['group_key'] ?? '');
        $group = isset($fleet->groups(true)[$group]) ? $group : null;

        $classes = [];
        if (!str_starts_with($key, 'class:')) {
            foreach (preg_split('/[\s,;]+/', (string) ($in['classes'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $c) {
                if (preg_match('/^[A-Za-z0-9_]{1,80}$/', $c) !== 1) {
                    throw new RuntimeException('Classe Arma invalide : « ' . mb_substr($c, 0, 40) . ' » (lettres, chiffres et _ seulement).');
                }
                $classes[] = $c;
            }
            if (count($classes) > 30) {
                throw new RuntimeException('Trente classes Arma au plus par appareil.');
            }
        }
        $image = null;
        if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE && $this->storage !== null) {
            $image = $this->storage->storeUpload($tenantId, 0, $file, 'aircraft');
        }
        $id = $this->repo->saveAircraft($tenantId, $key, [
            'name' => $name,
            'track' => $track,
            'group_key' => $group,
            'image_path' => $image,
            'is_custom' => $custom ? 1 : 0,
            'archived' => !empty($in['archived']),
        ], $actorId);
        if (!empty($in['remove_image']) && $image === null) {
            $this->repo->removeImage($tenantId, $id);
        }
        if (!str_starts_with($key, 'class:') && ($custom || array_key_exists('classes', $in))) {
            $this->repo->setClasses($tenantId, $id, $classes);
        }

        return $key;
    }

    /**
     * Crée, renomme, range ou archive une famille. Retourne sa clé.
     *
     * @param array<string, mixed> $in
     */
    public function saveGroup(int $tenantId, array $in, ?int $actorId): string
    {
        if (!$this->repo->schemaReady()) {
            throw new RuntimeException('Les familles de la communauté arrivent avec la prochaine mise à jour du site (migration à lancer).');
        }
        $name = mb_substr(trim((string) ($in['name'] ?? '')), 0, 80);
        if ($name === '') {
            throw new RuntimeException('Donnez un nom à la famille.');
        }
        $groups = $this->repo->fleet($tenantId)->groups(true);
        $key = trim((string) ($in['group_key'] ?? ''));
        if ($key === '') {
            $base = PilotFleet::groupKey($name);
            $key = $base;
            for ($i = 2; isset($groups[$key]); $i++) {
                $key = substr($base, 0, 36) . '_' . $i;
            }
        } elseif (!isset($groups[$key])) {
            throw new RuntimeException('Famille introuvable.');
        }
        $sort = max(-999, min(9999, (int) ($in['sort_order'] ?? (count($groups) * 10))));
        $this->repo->saveGroup($tenantId, $key, $name, $sort, !empty($in['archived']), $actorId);

        return $key;
    }
}
