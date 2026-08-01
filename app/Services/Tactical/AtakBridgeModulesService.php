<?php

declare(strict_types=1);

namespace App\Services\Tactical;

/**
 * Modules pont ATAK Enhanced / cTab → Athena (activables par communauté).
 * Stockage fichier — pas de migration BDD.
 */
final class AtakBridgeModulesService
{
    /**
     * Catalogue figé (ordre d’affichage admin / jeu).
     *
     * @return list<array{id: string, label: string, description: string}>
     */
    public function catalog(): array
    {
        return [
            [
                'id' => 'weather',
                'label' => 'Météo mission',
                'description' => 'Remonte l’état météo du théâtre vers le bandeau de la carte tactique.',
            ],
            [
                'id' => 'drone',
                'label' => 'Contacts ISR / drones',
                'description' => 'Partage les contacts repérés via Drone Ops sur la carte et la tablette.',
            ],
            [
                'id' => 'video_feeds',
                'label' => 'Caméras casque et drone',
                'description' => 'Annonce les caméras casque / drones disponibles et remonte les aperçus photo vers le panneau Cams.',
            ],
            [
                'id' => 'ctab_markers',
                'label' => 'Marqueurs ATAK / cTab',
                'description' => 'Synchronise les repères posés sur ATAK Enhanced et la tablette cTab vers la carte web.',
            ],
            [
                'id' => 'route',
                'label' => 'Itinéraires',
                'description' => 'Affiche l’itinéraire actif (Route) comme tracé sur la carte.',
            ],
            [
                'id' => 'jump',
                'label' => 'Plans de saut HAHO / HALO',
                'description' => 'Partage le plan de saut (point de largage, zone, trajet) vers la carte.',
            ],
            [
                'id' => 'wave_relay',
                'label' => 'Wave Relay / MPU-5',
                'description' => 'Affiche l’état radio Wave Relay (talkgroup, passerelle) sur les fiches opérateurs.',
            ],
            [
                'id' => 'iceman_alerts',
                'label' => 'Alertes ATAK Enhanced',
                'description' => 'Remonte les alertes tactiques envoyées depuis ATAK Enhanced vers Athena.',
            ],
            [
                'id' => 'iceman_bda',
                'label' => 'Bilans des dégâts (BDA)',
                'description' => 'Remonte les bilans BDA ATAK Enhanced vers Athena et l’inbox cTab.',
            ],
            [
                'id' => 'iceman_photo',
                'label' => 'Photos terrain',
                'description' => 'Envoie les captures Photo Library / BCE vers le portail.',
            ],
            [
                'id' => 'sse_person',
                'label' => 'Renseignement interpersonnel (SSE)',
                'description' => 'Enregistrement de personnes (identité, photo du visage, armement) vers le poste de commandement.',
            ],
            [
                'id' => 'iceman_group',
                'label' => 'Messages de groupe',
                'description' => 'Remonte les messages de groupe ATAK Enhanced vers la messagerie Athena.',
            ],
            [
                'id' => 'comspec_mirror',
                'label' => 'Miroir Athena → cTab',
                'description' => 'Diffuse les alertes / BDA envoyés depuis Athena vers les appareils cTab en jeu.',
            ],
            [
                'id' => 'report_routing',
                'label' => 'Routage des rapports tactiques',
                'description' => 'Applique les règles de distribution aux rapports, sans masquer les données existantes.',
            ],
        ];
    }

    /**
     * @return array{modules: array<string, bool>, updated_at: string}
     */
    public function get(int $tenantId): array
    {
        $defaults = $this->defaultEnabledMap();
        $path = $this->path($tenantId);
        if (!is_file($path)) {
            return [
                'modules' => $defaults,
                'updated_at' => '',
            ];
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return [
                'modules' => $defaults,
                'updated_at' => '',
            ];
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return [
                'modules' => $defaults,
                'updated_at' => '',
            ];
        }
        $stored = is_array($data['modules'] ?? null) ? $data['modules'] : [];
        $merged = $defaults;
        foreach ($merged as $id => $_) {
            if (array_key_exists($id, $stored)) {
                $merged[$id] = (bool) $stored[$id];
            }
        }

        return [
            'modules' => $merged,
            'updated_at' => (string) ($data['updated_at'] ?? ''),
        ];
    }

    /**
     * @param array<string, bool> $modules
     * @return array{modules: array<string, bool>, updated_at: string}
     */
    public function put(int $tenantId, array $modules): array
    {
        $merged = $this->defaultEnabledMap();
        foreach ($merged as $id => $_) {
            if (array_key_exists($id, $modules)) {
                $merged[$id] = (bool) $modules[$id];
            }
        }
        $payload = [
            'modules' => $merged,
            'updated_at' => gmdate('c'),
        ];
        $dir = dirname($this->path($tenantId));
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents(
            $this->path($tenantId),
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );

        return $payload;
    }

    /**
     * @return list<array{id: string, label: string, description: string, enabled: bool}>
     */
    public function catalogWithState(int $tenantId): array
    {
        $state = $this->get($tenantId);
        $out = [];
        foreach ($this->catalog() as $row) {
            $id = $row['id'];
            $out[] = [
                'id' => $id,
                'label' => $row['label'],
                'description' => $row['description'],
                'enabled' => (bool) ($state['modules'][$id] ?? true),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, bool>
     */
    private function defaultEnabledMap(): array
    {
        $map = [];
        foreach ($this->catalog() as $row) {
            $map[$row['id']] = true;
        }

        return $map;
    }

    private function path(int $tenantId): string
    {
        return base_path('storage/cache/atak-modules/t' . $tenantId . '.json');
    }
}
