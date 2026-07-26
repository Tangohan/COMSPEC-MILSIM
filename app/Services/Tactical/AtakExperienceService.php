<?php

declare(strict_types=1);

namespace App\Services\Tactical;

/**
 * Expérience Overwatch par communauté (réalisme, mode troll, réglages recommandés).
 * Stockage JSON dans tenant_atak_config.experience_config.
 */
final class AtakExperienceService
{
    /**
     * @return list<array{id: string, label: string, description: string, type: string, default: mixed, choices?: list<array{value: string, label: string}>}>
     */
    public function catalog(): array
    {
        return [
            [
                'id' => 'realism',
                'label' => 'Mode réalisme',
                'description' => 'Immersion renforcée pour toute la communauté : moins d’aides à l’écran, pas d’alertes « confort » (immobilité, sauts de position). La liaison, la tablette et les fonctions tactiques restent actives.',
                'type' => 'bool',
                'default' => false,
            ],
            [
                'id' => 'troll',
                'label' => 'Mode troll',
                'description' => 'Ambiance décontractée : alertes de suivi exagérées (immobilité, téléportation suspecte) visibles à l’écran. À réserver aux entraînements légers ou sessions fun — incompatible avec le mode réalisme.',
                'type' => 'bool',
                'default' => false,
            ],
            [
                'id' => 'screen_notifications',
                'label' => 'Notifications à l’écran',
                'description' => 'Bandeaux et messages système en bas de la carte en jeu.',
                'type' => 'tri',
                'default' => 'player',
                'choices' => [
                    ['value' => 'player', 'label' => 'Laisser le choix à chaque opérateur'],
                    ['value' => 'on', 'label' => 'Toujours afficher'],
                    ['value' => 'off', 'label' => 'Toujours masquer'],
                ],
            ],
            [
                'id' => 'vehicle_detail',
                'label' => 'Détail véhicule sur la carte',
                'description' => 'Orientation 3D et vitesse lorsque l’opérateur est embarqué.',
                'type' => 'tri',
                'default' => 'player',
                'choices' => [
                    ['value' => 'player', 'label' => 'Laisser le choix à chaque opérateur'],
                    ['value' => 'on', 'label' => 'Toujours activer'],
                    ['value' => 'off', 'label' => 'Toujours désactiver'],
                ],
            ],
            [
                'id' => 'require_equipment',
                'label' => 'Exiger une tablette ou un GPS',
                'description' => 'La liaison et la tablette ne fonctionnent qu’avec l’équipement choisi dans l’inventaire.',
                'type' => 'tri',
                'default' => 'player',
                'choices' => [
                    ['value' => 'player', 'label' => 'Laisser le choix à chaque opérateur'],
                    ['value' => 'on', 'label' => 'Toujours exiger'],
                    ['value' => 'off', 'label' => 'Jamais exiger'],
                ],
            ],
            [
                'id' => 'show_opfor',
                'label' => 'Afficher l’adversaire sur la carte web',
                'description' => 'Positions du camp adverse visibles sur Tacmap pour les observateurs autorisés.',
                'type' => 'tri',
                'default' => 'player',
                'choices' => [
                    ['value' => 'player', 'label' => 'Laisser le choix à chaque opérateur'],
                    ['value' => 'on', 'label' => 'Toujours afficher'],
                    ['value' => 'off', 'label' => 'Toujours masquer'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        $out = [];
        foreach ($this->catalog() as $row) {
            $out[$row['id']] = $row['default'];
        }
        $out['guide_custom'] = '';

        return $out;
    }

    /**
     * @return array{settings: array<string, mixed>, updated_at: string, guide: string}
     */
    public function get(int $tenantId): array
    {
        $repo = new \App\Repositories\TenantAtakConfigRepository();
        $raw = $repo->getExperienceConfigRaw($tenantId);
        $merged = $this->defaults();
        if (is_array($raw)) {
            foreach ($merged as $key => $_) {
                if (array_key_exists($key, $raw)) {
                    $merged[$key] = $raw[$key];
                }
            }
        }
        if (!empty($merged['realism']) && !empty($merged['troll'])) {
            $merged['troll'] = false;
        }

        return [
            'settings' => $merged,
            'updated_at' => is_array($raw) ? (string) ($raw['updated_at'] ?? '') : '',
            'guide' => $this->buildGuide($merged),
        ];
    }

    /**
     * @param array<string, mixed> $incoming
     * @return array{settings: array<string, mixed>, updated_at: string, guide: string}
     */
    public function put(int $tenantId, array $incoming): array
    {
        $merged = $this->defaults();
        foreach ($this->catalog() as $row) {
            $id = $row['id'];
            if (!array_key_exists($id, $incoming)) {
                continue;
            }
            if ($row['type'] === 'bool') {
                $merged[$id] = (bool) $incoming[$id];
            } elseif ($row['type'] === 'tri') {
                $val = (string) $incoming[$id];
                $allowed = array_column($row['choices'] ?? [], 'value');
                $merged[$id] = in_array($val, $allowed, true) ? $val : 'player';
            }
        }
        if (!empty($merged['realism'])) {
            $merged['troll'] = false;
        } elseif (!empty($merged['troll'])) {
            $merged['realism'] = false;
        }
        $custom = trim((string) ($incoming['guide_custom'] ?? ''));
        $merged['guide_custom'] = $custom;
        if (!empty($merged['realism'])) {
            $merged['troll'] = false;
        } elseif (!empty($merged['troll'])) {
            $merged['realism'] = false;
        }
        $merged['updated_at'] = gmdate('c');

        $repo = new \App\Repositories\TenantAtakConfigRepository();
        $repo->saveExperienceConfig($tenantId, $merged);

        return [
            'settings' => $merged,
            'updated_at' => $merged['updated_at'],
            'guide' => $this->buildGuide($merged),
        ];
    }

    /**
     * @return list<array{id: string, label: string, description: string, type: string, value: mixed, choices?: list<array{value: string, label: string}>}>
     */
    public function catalogWithState(int $tenantId): array
    {
        $state = $this->get($tenantId);
        $settings = $state['settings'];
        $out = [];
        foreach ($this->catalog() as $row) {
            $id = $row['id'];
            $item = $row;
            $item['value'] = $settings[$id] ?? $row['default'];
            $out[] = $item;
        }

        return $out;
    }

    /**
     * Payload compact pour le mod (GET /api/atak/experience).
     *
     * @return array<string, mixed>
     */
    public function payloadForGame(int $tenantId): array
    {
        $pack = $this->get($tenantId);
        $s = $pack['settings'];

        return [
            'realism' => !empty($s['realism']),
            'troll' => !empty($s['troll']),
            'screen_notifications' => (string) ($s['screen_notifications'] ?? 'player'),
            'vehicle_detail' => (string) ($s['vehicle_detail'] ?? 'player'),
            'require_equipment' => (string) ($s['require_equipment'] ?? 'player'),
            'show_opfor' => (string) ($s['show_opfor'] ?? 'player'),
            'guide' => $pack['guide'],
            'updated_at' => $pack['updated_at'],
        ];
    }

    /**
     * @param array<string, mixed> $settings
     */
    public function buildGuide(array $settings): string
    {
        $lines = [];
        $lines[] = '=== Guide de configuration Overwatch (communauté) ===';
        $lines[] = '';
        $lines[] = '1. Lier votre compte';
        $lines[] = '   • Ouvrez la Tacmap sur le portail et utilisez « Connexion en jeu », ou';
        $lines[] = '   • Saisissez l’adresse du portail, la clé d’accès et l’identifiant de communauté dans Options → Mods → COMSPEC Overwatch → Connexion.';
        $lines[] = '';
        $lines[] = '2. Tablette en mission';
        $lines[] = '   • Touche K : ouvrir la tablette Athena.';
        $lines[] = '   • Ctrl+K : messagerie tactique.';
        $lines[] = '   • La liaison doit être « active » (pastille verte) pour synchroniser position, ordres et alertes.';
        $lines[] = '';

        if (!empty($settings['realism'])) {
            $lines[] = '3. Mode réalisme — ACTIF pour votre communauté';
            $lines[] = '   • Moins de messages « confort » à l’écran : restez concentré sur le terrain.';
            $lines[] = '   • Consultez la tablette (K) pour les alertes médicales, ordres et signalements.';
            $lines[] = '   • Les anomalies de suivi automatique (immobilité, saut de position) sont désactivées.';
        } elseif (!empty($settings['troll'])) {
            $lines[] = '3. Mode troll — ACTIF pour votre communauté';
            $lines[] = '   • Alertes de suivi visibles : immobilité prolongée, déplacements brusques.';
            $lines[] = '   • Session décontractée — ne pas utiliser pour un exercice sérieux.';
            $lines[] = '   • Vous pouvez désactiver les bandeaux à l’écran dans Options → Mods si besoin.';
        } else {
            $lines[] = '3. Réglages personnels';
            $lines[] = '   • Options → Mods → COMSPEC Overwatch : sons, notifications, équipement requis.';
            $lines[] = '   • « Mode milsim » coupe les aides d’interface si vous voulez plus d’immersion.';
        }
        $lines[] = '';

        $lines[] = '4. Consignes de votre état-major';
        foreach ($this->triStateHints($settings) as $hint) {
            $lines[] = '   • ' . $hint;
        }

        $custom = trim((string) ($settings['guide_custom'] ?? ''));
        if ($custom !== '') {
            $lines[] = '';
            $lines[] = '5. Message de votre communauté';
            foreach (preg_split('/\r\n|\r|\n/', $custom) ?: [] as $cl) {
                $cl = trim($cl);
                if ($cl !== '') {
                    $lines[] = '   ' . $cl;
                }
            }
        }

        $lines[] = '';
        $lines[] = '— Fin du guide —';

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $settings
     * @return list<string>
     */
    private function triStateHints(array $settings): array
    {
        $hints = [];
        $map = [
            'screen_notifications' => [
                'on' => 'Votre communauté demande d’afficher les notifications à l’écran.',
                'off' => 'Votre communauté demande de masquer les bandeaux à l’écran (consultez la tablette).',
            ],
            'vehicle_detail' => [
                'on' => 'Le détail véhicule (cap et vitesse) est activé pour la carte.',
                'off' => 'Le détail véhicule est désactivé pour alléger la carte.',
            ],
            'require_equipment' => [
                'on' => 'Une tablette ou un GPS en inventaire est requis pour ouvrir Overwatch.',
                'off' => 'Aucun équipement particulier n’est exigé pour la liaison.',
            ],
            'show_opfor' => [
                'on' => 'Les positions adverses sont visibles sur Tacmap pour les observateurs.',
                'off' => 'Les positions adverses sont masquées sur Tacmap.',
            ],
        ];
        foreach ($map as $key => $labels) {
            $val = (string) ($settings[$key] ?? 'player');
            if ($val !== 'player' && isset($labels[$val])) {
                $hints[] = $labels[$val];
            }
        }
        if ($hints === []) {
            $hints[] = 'Aucune contrainte supplémentaire imposée — réglages personnels libres.';
        }

        return $hints;
    }
}
