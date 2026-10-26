<?php

declare(strict_types=1);

namespace App\Services\Tactical;

/**
 * Données remontées du jeu vers Athena, choisies par chaque communauté (Configuration ATAK > Données remontées).
 * Lu par COMSPEC Link (GET /api/atak/data-flows, toutes les 30 s) : un flux coupé n'est plus calculé en jeu ni mis
 * en file par la DLL, et le lot de télémétrie l'ignore côté site. Les comptes rendus (rapports, photos, MEDEVAC,
 * messages, 9-line) passent toujours. La communauté peut changer d'avis à tout moment.
 * Stockage fichier, comme les modules pont : pas de migration.
 */
final class AtakDataFlowsService
{
    public const FLOWS = ['positions', 'vehicles', 'combat', 'phone_mirror', 'context'];

    /** Préréglages proposés : tout, comptes rendus seulement ; « custom » quand les cases ne suivent aucun des deux. */
    public const PRESETS = [
        'full' => ['positions' => true, 'vehicles' => true, 'combat' => true, 'phone_mirror' => true, 'context' => true],
        'reports' => ['positions' => false, 'vehicles' => false, 'combat' => false, 'phone_mirror' => false, 'context' => false],
    ];

    /**
     * @return list<array{id: string, label: string, description: string}>
     */
    public function catalog(): array
    {
        return [
            [
                'id' => 'positions',
                'label' => 'Positions des joueurs en direct',
                'description' => 'Suivi en direct des opérateurs sur la carte tactique web. Coupé : un simple signe de vie par minute, pour savoir qui est en jeu.',
            ],
            [
                'id' => 'vehicles',
                'label' => 'Véhicules, drones et aéronefs',
                'description' => 'Position et état des véhicules occupés, des drones pilotés et des aéronefs, avec les manifestes de vol.',
            ],
            [
                'id' => 'combat',
                'label' => 'Pertes pour la relecture',
                'description' => 'Morts et blessés signalés par les téléphones, pour la couche des pertes de la relecture de mission.',
            ],
            [
                'id' => 'phone_mirror',
                'label' => 'Notifications du téléphone',
                'description' => 'Copie des notifications du téléphone ATAK sur la page « Mon téléphone ATAK » du joueur.',
            ],
            [
                'id' => 'context',
                'label' => 'Météo, SIGINT et logistique',
                'description' => 'Météo du théâtre, interceptions SIGINT et état logistique des véhicules.',
            ],
        ];
    }

    /**
     * @return array{flows: array<string, bool>, preset: string, chosen: bool, updated_at: string}
     */
    public function get(int $tenantId): array
    {
        $flows = self::PRESETS['full'];
        $chosen = false;
        $updatedAt = '';
        $path = $this->path($tenantId);
        $raw = is_file($path) ? @file_get_contents($path) : false;
        $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if (is_array($data)) {
            $stored = is_array($data['flows'] ?? null) ? $data['flows'] : [];
            foreach (self::FLOWS as $id) {
                if (array_key_exists($id, $stored)) {
                    $flows[$id] = (bool) $stored[$id];
                }
            }
            $chosen = !empty($data['chosen']);
            $updatedAt = (string) ($data['updated_at'] ?? '');
        }

        return [
            'flows' => $flows,
            'preset' => self::presetOf($flows),
            'chosen' => $chosen,
            'updated_at' => $updatedAt,
        ];
    }

    /**
     * @param array<string, bool> $flows
     * @return array{flows: array<string, bool>, preset: string, chosen: bool, updated_at: string}
     */
    public function put(int $tenantId, array $flows): array
    {
        $merged = self::PRESETS['full'];
        foreach (self::FLOWS as $id) {
            if (array_key_exists($id, $flows)) {
                $merged[$id] = (bool) $flows[$id];
            }
        }
        $payload = [
            'flows' => $merged,
            'chosen' => true,
            'updated_at' => gmdate('c'),
        ];
        $dir = dirname($this->path($tenantId));
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($this->path($tenantId), json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);

        return $payload + ['preset' => self::presetOf($merged)];
    }

    /** Le flux est-il envoyé ? Identifiant inconnu : oui (rien n'est coupé par défaut). */
    public function allows(int $tenantId, string $flow): bool
    {
        return (bool) ($this->get($tenantId)['flows'][$flow] ?? true);
    }

    /**
     * Flux d'un type d'événement du lot de télémétrie (null : toujours accepté, comptes rendus compris).
     * Les positions ne sont pas coupées ici : la DLL n'en envoie plus qu'une par minute (présence).
     */
    public static function flowForBatchType(string $type): ?string
    {
        return match ($type) {
            'veh', 'vehicle', 'vehicles', 'flight', 'flight_manifest' => 'vehicles',
            'combat', 'fire' => 'combat',
            'weather', 'wx', 'sigint', 'logstat', 'logistics' => 'context',
            default => null,
        };
    }

    /** @param array<string, bool> $flows */
    public static function presetOf(array $flows): string
    {
        foreach (self::PRESETS as $name => $preset) {
            $same = true;
            foreach (self::FLOWS as $id) {
                if ((bool) ($flows[$id] ?? true) !== $preset[$id]) {
                    $same = false;
                    break;
                }
            }
            if ($same) {
                return $name;
            }
        }

        return 'custom';
    }

    private function path(int $tenantId): string
    {
        return base_path('storage/cache/atak-data-flows/t' . $tenantId . '.json');
    }
}
