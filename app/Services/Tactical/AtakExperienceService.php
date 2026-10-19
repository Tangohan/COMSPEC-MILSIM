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
                'id' => 'rp_enabled',
                'label' => 'Mode roleplay du téléphone',
                'description' => 'Active les simulations du téléphone ATAK (réseau, capteurs, effets) pour toute la communauté.',
                'type' => 'tri',
                'default' => 'player',
                'group' => 'realisme',
                'surface' => 'control',
                'choices' => $tri('Toujours activer', 'Toujours désactiver'),
            ],
            [
                'id' => 'rp_network_failures',
                'label' => 'Coupures réseau simulées',
                'description' => 'Pertes de liaison et paquets perdus selon le terrain et les relais.',
                'type' => 'tri',
                'default' => 'player',
                'group' => 'realisme',
                'surface' => 'control',
                'choices' => $tri('Toujours activer', 'Toujours désactiver'),
            ],
            [
                'id' => 'rp_sensor_failures',
                'label' => 'Défauts des capteurs médicaux',
                'description' => 'Les constantes médicales peuvent manquer ou être fausses.',
                'type' => 'tri',
                'default' => 'player',
                'group' => 'realisme',
                'surface' => 'control',
                'choices' => $tri('Toujours activer', 'Toujours désactiver'),
            ],
            [
                'id' => 'rp_visual_effects',
                'label' => 'Effets visuels de dégradation',
                'description' => 'Écran brouillé ou parasité quand la liaison faiblit.',
                'type' => 'tri',
                'default' => 'player',
                'group' => 'realisme',
                'surface' => 'control',
                'choices' => $tri('Toujours activer', 'Toujours désactiver'),
            ],
            [
                'id' => 'rp_link_degrade',
                'label' => 'Liaison dégradée simulée',
                'description' => 'Latence et coupures courtes même en bonne couverture.',
                'type' => 'tri',
                'default' => 'player',
                'group' => 'realisme',
                'surface' => 'control',
                'choices' => $tri('Toujours activer', 'Toujours désactiver'),
            ],
            [
                'id' => 'rp_data_bar',
                'label' => 'Barre de données sous la carte',
                'description' => 'Vitesse, altitude, cap et grille en bas de la carte du téléphone.',
                'type' => 'tri',
                'default' => 'player',
                'group' => 'realisme',
                'surface' => 'control',
                'choices' => $tri('Toujours afficher', 'Toujours masquer'),
            ],
            [
                'id' => 'native_map_labels',
                'label' => 'Indicatifs sur la carte du téléphone',
                'description' => 'Noms des unités amies affichés à côté de leurs symboles.',
                'type' => 'tri',
                'default' => 'player',
                'group' => 'realisme',
                'surface' => 'control',
                'choices' => $tri('Toujours afficher', 'Toujours masquer'),
            ],
            [
                'id' => 'native_marker_tags',
                'label' => 'Coordonnées des repères',
                'description' => 'Encadré nom et coordonnées E / N sous les repères de la carte du téléphone.',
                'type' => 'tri',
                'default' => 'player',
                'group' => 'realisme',
                'surface' => 'control',
                'choices' => $tri('Toujours afficher', 'Toujours masquer'),
            ],
            [
                'id' => 'native_compass',
                'label' => 'Boussole sur la carte du téléphone',
                'description' => 'Rose des vents et cap du joueur en haut de la carte.',
                'type' => 'tri',
                'default' => 'player',
                'group' => 'realisme',
                'surface' => 'control',
                'choices' => $tri('Toujours afficher', 'Toujours masquer'),
            ],
            [
                'id' => 'native_vibrate',
                'label' => 'Vibration du téléphone',
                'description' => 'Vibration et son à chaque nouveau message ou alerte.',
                'type' => 'tri',
                'default' => 'player',
                'group' => 'realisme',
                'surface' => 'control',
                'choices' => $tri('Toujours activer', 'Toujours désactiver'),
            ],
            [
                'id' => 'native_jtac_autofill',
                'label' => 'JTAC : complétion automatique du 9-LINE',
                'description' => 'Par défaut, le JTAC saisit lui-même l’IP, les lignes 5 et 9 et les remarques (réalisme). Activé, le téléphone propose l’appareil, la cible laser, l’IP, la description, les menaces, le FAH et le dégagement.',
                'type' => 'list',
                'default' => 'off',
                'group' => 'realisme',
                'surface' => 'control',
                'choices' => [
                    ['value' => 'off', 'label' => 'Saisie manuelle (réalisme)'],
                    ['value' => 'on', 'label' => 'Complétion automatique'],
                ],
            ],
            ...$this->networkCatalog(),
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
     * Conditions réseau de l'ATAK natif (simulation radio : météo, surcharge, pertes de paquets…).
     * « Laisser au serveur » : le serveur décide (réglages CBA, ou menu Échap > Conditions réseau en partie) ;
     * toute autre valeur s'impose à tous les joueurs de la communauté (COMSPEC Link 2.0.89, clés net_*).
     * Les valeurs sont celles que le jeu envoie (on / off, nombres en texte) : NetConditions.Report.
     *
     * @return list<array{id: string, label: string, description: string, type: string, default: mixed, group: string, surface: string, choices: list<array{value: string, label: string}>}>
     */
    public function networkCatalog(): array
    {
        $server = ['value' => 'player', 'label' => 'Laisser au serveur (menu Échap en jeu)'];
        $onOff = fn (string $on, string $off): array => [
            $server,
            ['value' => 'on', 'label' => $on],
            ['value' => 'off', 'label' => $off],
        ];
        $nums = function (array $values, string $unit) use ($server): array {
            $out = [$server];
            foreach ($values as $v => $label) {
                $out[] = ['value' => (string) $v, 'label' => $label !== '' ? $label : $v . ' ' . $unit];
            }

            return $out;
        };
        $row = fn (string $id, string $label, string $description, array $choices): array => [
            'id' => $id,
            'label' => $label,
            'description' => $description,
            'type' => 'list',
            'default' => 'player',
            'group' => 'reseau',
            'surface' => 'control',
            'choices' => $choices,
        ];

        return [
            $row('net_sim', 'Débit réseau simulé', 'Bâtiments, relief, météo, brouilleurs, relais et dégâts réduisent le débit : les envois partent avec un délai, se perdent ou attendent le retour du réseau.', $onOff('Toujours simuler', 'Couper la simulation (débit plein)')),
            $row('net_profile', 'Profil de réalisme', 'Milsim (par défaut en jeu) : toutes les simulations à leur valeur réelle. Standard : en plus doux. Arcade : réseau parfait, BFT en direct, GPS exact, batterie infinie. Personnalisé : seuls les réglages du serveur comptent. Chaque règle ci-dessous reste modifiable avec n’importe quel profil et l’emporte sur lui.', [
                $server,
                ['value' => '3', 'label' => 'Milsim'],
                ['value' => '2', 'label' => 'Standard'],
                ['value' => '1', 'label' => 'Arcade'],
                ['value' => '0', 'label' => 'Personnalisé (réglages du serveur)'],
            ]),
            $row('net_realism', 'Réalisme de la simulation radio', 'Réaliste : bilan radio en dB (relief, Fresnel, forêt, bâtiments). Exigeant : forêt et bâtiments comptés en entier. Simplifié : ancien modèle.', [
                $server,
                ['value' => '1', 'label' => 'Réaliste'],
                ['value' => '2', 'label' => 'Exigeant'],
                ['value' => '0', 'label' => 'Simplifié (ancien modèle)'],
            ]),
            $row('net_weather', 'Météo sur le réseau', 'Pluie sur le trajet, antennes mouillées, bruit des orages et vent fort réduisent la marge radio ; orage et vent font perdre des paquets par rafales.', $onOff('Toujours compter la météo', 'Ignorer la météo')),
            $row('net_weather_k', 'Intensité de la météo', '100 % : valeurs réelles à 4,9 GHz. Au-dessus, la météo pèse davantage (scénario dégradé).', $nums([0 => '', 50 => '', 100 => '100 % (réel)', 150 => '', 200 => ''], '%')),
            $row('net_congestion', 'Surcharge du réseau', 'Les téléphones à portée d’un même relais se partagent son canal : débit en baisse, latence et collisions quand il se remplit.', $onOff('Toujours simuler la surcharge', 'Ignorer la surcharge')),
            $row('net_bg_load', 'Trafic de fond', 'Part de la capacité de chaque relais déjà occupée par d’autres utilisateurs (vidéo, synchronisations, unités hors jeu).', $nums([0 => '', 10 => '', 25 => '', 50 => '', 75 => '', 90 => ''], '%')),
            $row('net_loss', 'Pertes de paquets', 'Des envois se perdent par rafales selon la qualité du lien et repartent automatiquement.', $onOff('Toujours simuler les pertes', 'Aucune perte')),
            $row('net_loss_floor', 'Perte minimale', 'Pertes imposées partout, même avec un excellent signal (zone dégradée).', $nums([0 => '', 1 => '', 3 => '', 5 => '', 10 => '', 20 => ''], '%')),
            $row('net_latency_add', 'Latence ajoutée', 'Délai ajouté à chaque envoi, par exemple une liaison satellite vers le poste (600 ms).', $nums([0 => '', 50 => '', 150 => '', 300 => '', 600 => '600 ms (satellite)', 1200 => '1 200 ms'], 'ms')),
            $row('net_relay_only', 'Réseau exclusivement par relais', 'Hors de portée de tout relais de la mission, aucun réseau.', $onOff('Toujours exiger un relais', 'Garder la liaison directe')),
            $row('net_gateways', 'Passerelles de véhicule et PC mobile', 'L’équipage peut faire d’un véhicule un relais ATAK ou un poste de commandement à mât.', $onOff('Toujours autoriser', 'Toujours interdire')),
            $row('net_offline_hold', 'Fonctionnement hors connexion', 'Sans signal, le téléphone garde ses données et retient ce qui arrive jusqu’au retour du réseau.', $onOff('Toujours activer', 'Toujours couper')),
            $row('net_video_relays', 'Flux vidéo selon les relais', 'Débit, définition et latence des flux vidéo suivent les relais de la mission.', $onOff('Toujours activer', 'Toujours couper')),
            $row('net_pli', 'Positions BFT par points', 'Comme un vrai ATAK : chaque téléphone envoie sa position à intervalle régulier, telle que son GPS la voit. Les alliés avancent par bonds et passent en gris quand leurs points n’arrivent plus.', $onOff('Toujours par points', 'Positions exactes en direct')),
            $row('net_pli_rate', 'Intervalle des positions BFT', 'Temps entre deux points de position d’un téléphone (deux fois plus souvent s’il bouge).', $nums([2 => '', 5 => '', 10 => '10 s (Milsim)', 15 => '', 30 => '', 60 => ''], 's')),
            $row('net_gps', 'GPS réaliste', 'Précision selon le ciel visible : quelques mètres à découvert, plus en forêt, rue étroite ou vallée, beaucoup plus dans un bâtiment, aucun fix en sous-sol.', $onOff('Toujours simuler le GPS', 'GPS exact')),
            $row('net_gps_k', 'Force des erreurs GPS', '100 % : erreurs réalistes d’un téléphone. 50 % : moitié moins.', $nums([50 => '', 100 => '100 % (réel)', 150 => '', 200 => ''], '%')),
            $row('net_gps_fix', 'Attente des satellites', 'Au démarrage du téléphone, le GPS cherche les satellites de quelques secondes à environ 40 s.', $onOff('Toujours attendre les satellites', 'Position tout de suite')),
            $row('net_battery', 'Batterie réaliste', 'La batterie se vide selon l’usage et se recharge en véhicule ; à 0 % le téléphone s’éteint.', $onOff('Toujours simuler la batterie', 'Batterie infinie')),
        ];
    }

    /**
     * Conditions réseau appliquées en jeu (menu Échap de l'ATAK natif, via COMSPEC Link) : gardées pour la page
     * Contrôle serveur, sans toucher aux règles de la communauté.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function recordLiveNetwork(int $tenantId, array $body, ?string $steamUid = null): array
    {
        $choices = [];
        foreach ($this->networkCatalog() as $row) {
            $choices[$row['id']] = array_column($row['choices'], 'value');
        }
        $in = is_array($body['conditions'] ?? null) ? $body['conditions'] : [];
        $conditions = [];
        foreach ($choices as $id => $allowed) {
            if (!array_key_exists($id, $in) || !is_scalar($in[$id])) {
                continue;
            }
            $val = (string) $in[$id];
            if ($val !== 'player' && in_array($val, $allowed, true)) {
                $conditions[$id] = $val;
            }
        }
        $text = static fn (mixed $v, int $max): string => mb_substr(trim(is_scalar($v) ? (string) $v : ''), 0, $max);
        $live = [
            'conditions' => $conditions,
            'applied_by' => $text($body['applied_by'] ?? '', 64),
            'steam_uid' => $steamUid !== null ? $text($steamUid, 32) : '',
            'mission' => $text($body['mission'] ?? '', 120),
            'world' => $text($body['world'] ?? '', 64),
            'server' => $text($body['server'] ?? '', 120),
            'reset' => !empty($body['reset']),
            'at' => gmdate('c'),
        ];

        $repo = new \App\Repositories\TenantAtakConfigRepository();
        $raw = $repo->getExperienceConfigRaw($tenantId);
        $raw = is_array($raw) ? $raw : [];
        $raw['net_live'] = $live;
        $repo->saveExperienceConfig($tenantId, $raw);

        return $live;
    }

    /**
     * Reprend comme règles de la communauté les conditions appliquées en dernier en jeu.
     *
     * @return int nombre de conditions reprises
     */
    public function adoptLiveNetwork(int $tenantId): int
    {
        $settings = $this->get($tenantId)['settings'];
        $live = is_array($settings['net_live'] ?? null) ? $settings['net_live'] : [];
        $conditions = is_array($live['conditions'] ?? null) ? $live['conditions'] : [];
        if ($conditions === []) {
            return 0;
        }
        $this->put($tenantId, $conditions);

        return count($conditions);
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
            'reseau' => 'Réseau et simulation radio',
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
        $out['net_live'] = [];

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
        $lines[] = '   • Saisissez la clé d’accès et l’identifiant de communauté dans Options → Mods → COMSPEC Overwatch → Connexion.';
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
