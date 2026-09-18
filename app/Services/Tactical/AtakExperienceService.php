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
     * @return list<array{id: string, label: string, description: string, type: string, default: mixed, group: string, surface: string, choices?: list<array{value: string, label: string}>}>
     */
    public function catalog(): array
    {
        $tri = fn (string $on, string $off): array => [
            ['value' => 'player', 'label' => 'Laisser le choix à chaque opérateur'],
            ['value' => 'on', 'label' => $on],
            ['value' => 'off', 'label' => $off],
        ];

        return [
            [
                'id' => 'realism',
                'label' => 'Mode réalisme',
                'description' => 'Immersion renforcée pour toute la communauté : moins d’aides à l’écran, pas d’alertes « confort » (immobilité, sauts de position). La liaison, la tablette et les fonctions tactiques restent actives.',
                'type' => 'bool',
                'default' => false,
                'group' => 'ambiance',
                'surface' => 'experience',
            ],
            [
                'id' => 'troll',
                'label' => 'Mode troll',
                'description' => 'Ambiance décontractée : alertes de suivi exagérées (immobilité, téléportation suspecte) visibles à l’écran. À réserver aux entraînements légers ou sessions fun — incompatible avec le mode réalisme.',
                'type' => 'bool',
                'default' => false,
                'group' => 'ambiance',
                'surface' => 'experience',
            ],
            [
                'id' => 'screen_notifications',
                'label' => 'Notifications à l’écran',
                'description' => 'Bandeaux d’information en bas de la carte en jeu. N’écrit pas dans le chat du jeu.',
                'type' => 'tri',
                'default' => 'player',
                'group' => 'ambiance',
                'surface' => 'experience',
                'choices' => $tri('Toujours afficher', 'Toujours masquer'),
            ],
            [
                'id' => 'vehicle_detail',
                'label' => 'Détail véhicule sur la carte',
                'description' => 'Orientation 3D et vitesse lorsque l’opérateur est embarqué.',
                'type' => 'tri',
                'default' => 'player',
                'group' => 'liaison',
                'surface' => 'experience',
                'choices' => $tri('Toujours activer', 'Toujours désactiver'),
            ],
            [
                'id' => 'require_equipment',
                'label' => 'Exiger une tablette ou un GPS',
                'description' => 'La liaison et la tablette ne fonctionnent qu’avec l’équipement choisi dans l’inventaire.',
                'type' => 'tri',
                'default' => 'player',
                'group' => 'liaison',
                'surface' => 'experience',
                'choices' => $tri('Toujours exiger', 'Jamais exiger'),
            ],
            [
                'id' => 'show_opfor',
                'label' => 'Afficher l’adversaire sur la carte web',
                'description' => 'Positions du camp adverse visibles sur Tacmap pour les observateurs autorisés.',
                'type' => 'tri',
                'default' => 'player',
                'group' => 'carte',
                'surface' => 'experience',
                'choices' => $tri('Toujours afficher', 'Toujours masquer'),
            ],
            [
                'id' => 'show_independent',
                'label' => 'Afficher les indépendants sur la carte web',
                'description' => 'Positions du camp indépendant visibles sur Tacmap pour les observateurs autorisés.',
                'type' => 'tri',
                'default' => 'player',
                'group' => 'carte',
                'surface' => 'control',
                'choices' => $tri('Toujours afficher', 'Toujours masquer'),
            ],
            [
                'id' => 'show_civilian',
                'label' => 'Afficher les civils sur la carte web',
                'description' => 'Positions civiles visibles sur Tacmap pour les observateurs autorisés.',
                'type' => 'tri',
                'default' => 'player',
                'group' => 'carte',
                'surface' => 'control',
                'choices' => $tri('Toujours afficher', 'Toujours masquer'),
            ],
            [
                'id' => 'sync_map_markers',
                'label' => 'Repères de carte vers le poste',
                'description' => 'Les repères posés en jeu sont transmis au poste de commandement.',
                'type' => 'tri',
                'default' => 'player',
                'group' => 'carte',
                'surface' => 'control',
                'choices' => $tri('Toujours transmettre', 'Ne jamais transmettre'),
            ],
            [
                'id' => 'atak_realism',
                'label' => 'Dommages au téléphone ATAK',
                'description' => 'Les blessures au torse peuvent éteindre, casser l’écran ou détruire le téléphone. Réparable selon le niveau choisi.',
                'type' => 'list',
                'default' => 'player',
                'group' => 'realisme',
                'surface' => 'control',
                'choices' => [
                    ['value' => 'player', 'label' => 'Laisser le choix à la mission'],
                    ['value' => 'off', 'label' => 'Aucun dégât'],
                    ['value' => '1', 'label' => 'Peut s’éteindre (réparable)'],
                    ['value' => '2', 'label' => 'L’écran peut être détruit'],
                    ['value' => '3', 'label' => 'Le téléphone peut être détruit'],
                ],
            ],
            [
                'id' => 'radio_proximity',
                'label' => 'Écoute radio à proximité',
                'description' => 'Le poste entend les échanges radio autour des opérateurs.',
                'type' => 'tri',
                'default' => 'player',
                'group' => 'fonctions',
                'surface' => 'control',
                'choices' => $tri('Toujours activer', 'Toujours désactiver'),
            ],
            [
                'id' => 'ace_menus',
                'label' => 'Menus Overwatch dans ACE',
                'description' => 'Les actions Overwatch étendues apparaissent dans le menu d’interaction ACE.',
                'type' => 'tri',
                'default' => 'player',
                'group' => 'fonctions',
                'surface' => 'control',
                'choices' => $tri('Toujours afficher', 'Toujours masquer'),
            ],
            [
                'id' => 'order_compose',
                'label' => 'Émission d’ordres depuis le terrain',
                'description' => 'Les chefs d’unité peuvent rédiger un ordre ou un FRAGO sans passer par la carte du poste.',
                'type' => 'tri',
                'default' => 'player',
                'group' => 'fonctions',
                'surface' => 'control',
                'choices' => $tri('Toujours autoriser', 'Toujours interdire'),
            ],
            [
                'id' => 'sse_require_item',
                'label' => 'Terminal SEEK requis',
                'description' => 'Ouvrir une fiche de renseignement exige un terminal de recueil (SEEK, BII-10 ou téléphone ATAK).',
                'type' => 'tri',
                'default' => 'player',
                'group' => 'fonctions',
                'surface' => 'control',
                'choices' => $tri('Toujours exiger', 'Jamais exiger'),
            ],
            [
                'id' => 'playtime',
                'label' => 'Temps de jeu vers le poste',
                'description' => 'Le temps passé en mission est transmis au portail.',
                'type' => 'tri',
                'default' => 'player',
                'group' => 'fonctions',
                'surface' => 'control',
                'choices' => $tri('Toujours transmettre', 'Ne jamais transmettre'),
            ],
            [
                'id' => 'athena_feed',
                'label' => 'Aperçus caméra automatiques',
                'description' => 'Des photos casque ou drone partent périodiquement vers le poste.',
                'type' => 'tri',
                'default' => 'player',
                'group' => 'fonctions',
                'surface' => 'control',
                'choices' => $tri('Toujours activer', 'Toujours désactiver'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function groupLabels(): array
    {
        return [
            'ambiance' => 'Ambiance de mission',
            'liaison' => 'Liaison et équipement',
            'carte' => 'Carte du poste',
            'realisme' => 'Réalisme du téléphone',
            'fonctions' => 'Fonctions de mission',
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
        $out['server_control_reviewed'] = false;

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
        $existing = $this->get($tenantId)['settings'];
        $merged = $this->defaults();
        foreach ($merged as $key => $_) {
            if (array_key_exists($key, $existing)) {
                $merged[$key] = $existing[$key];
            }
        }
        foreach ($this->catalog() as $row) {
            $id = $row['id'];
            if (!array_key_exists($id, $incoming)) {
                continue;
            }
            if ($row['type'] === 'bool') {
                $merged[$id] = (bool) $incoming[$id];
            } elseif ($row['type'] === 'tri' || $row['type'] === 'list') {
                $val = (string) $incoming[$id];
                $allowed = array_column($row['choices'] ?? [], 'value');
                $merged[$id] = in_array($val, $allowed, true) ? $val : (string) $row['default'];
            }
        }
        if (!empty($merged['realism'])) {
            $merged['troll'] = false;
        } elseif (!empty($merged['troll'])) {
            $merged['realism'] = false;
        }
        if (array_key_exists('guide_custom', $incoming)) {
            $merged['guide_custom'] = trim((string) $incoming['guide_custom']);
        }
        if (!empty($incoming['server_control_reviewed'])) {
            $merged['server_control_reviewed'] = true;
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
     * @return list<array{id: string, label: string, description: string, type: string, value: mixed, group: string, surface: string, choices?: list<array{value: string, label: string}>}>
     */
    public function catalogWithState(int $tenantId, ?string $surface = null): array
    {
        $state = $this->get($tenantId);
        $settings = $state['settings'];
        $out = [];
        foreach ($this->catalog() as $row) {
            if ($surface !== null && ($row['surface'] ?? 'experience') !== $surface) {
                continue;
            }
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

        $out = [
            'realism' => !empty($s['realism']),
            'troll' => !empty($s['troll']),
            'guide' => $pack['guide'],
            'updated_at' => $pack['updated_at'],
        ];
        foreach ($this->catalog() as $row) {
            $id = $row['id'];
            if ($row['type'] === 'bool') {
                $out[$id] = !empty($s[$id]);
            } else {
                $out[$id] = (string) ($s[$id] ?? $row['default']);
            }
        }

        return $out;
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
            'show_independent' => [
                'on' => 'Les positions indépendantes sont visibles sur Tacmap.',
                'off' => 'Les positions indépendantes sont masquées sur Tacmap.',
            ],
            'show_civilian' => [
                'on' => 'Les positions civiles sont visibles sur Tacmap.',
                'off' => 'Les positions civiles sont masquées sur Tacmap.',
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
