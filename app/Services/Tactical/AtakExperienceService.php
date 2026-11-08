<?php

declare(strict_types=1);

namespace App\Services\Tactical;

/**
 * Expérience Overwatch par communauté (réalisme, mode troll, réglages recommandés).
 * Stockage JSON dans tenant_atak_config.experience_config.
 */
final class AtakExperienceService
{
    /** Message de la communauté à la suite du guide : le jeu lit 4 000 caractères au plus de l'ensemble (fn_tenantAction "guide"). */
    public const GUIDE_CUSTOM_MAX = 2000;

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
            $row('net_gps_loss', 'Pertes de fix GPS', 'Part des satellites masqués (toit, forêt, immeubles, relief) qui compte vraiment. 100 % : plus de fix dans un bâtiment ou en sous-sol, comme un vrai téléphone. 0 % : le fix tient partout, seule la précision baisse.', $nums([0 => '0 % (fix partout)', 50 => '', 100 => '100 % (réel)', 150 => '', 200 => ''], '%')),
            $row('net_gps_rel', 'Fiabilité du GPS', 'Décrochages du fix de quelques secondes et sauts de position de 15 à 60 m (reflets du signal). Bonne : environ une fois toutes les 20 min ; Médiocre : toutes les 2 min.', [
                $server,
                ['value' => '0', 'label' => 'Parfaite'],
                ['value' => '1', 'label' => 'Bonne (réel)'],
                ['value' => '2', 'label' => 'Moyenne'],
                ['value' => '3', 'label' => 'Médiocre'],
            ]),
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
     * Apps du téléphone ATAK natif que la communauté peut imposer ou interdire (App Store du téléphone).
     * Miroir de COMSPEC_ATAK_Apps (config.cpp) sans les apps système (Réglages, App Store, Alertes), Admin et Poste TOC.
     *
     * @return list<array{id: string, name: string, description: string, section: string}>
     */
    public function phoneApps(): array
    {
        return [
            ['id' => 'Map', 'name' => 'Carte', 'description' => 'Carte tactique : unités amies, marqueurs, mesures et dessins partagés', 'section' => 'Navigation'],
            ['id' => 'Gps', 'name' => 'GPS', 'description' => 'Position, grille, altitude et précision du GPS', 'section' => 'Navigation'],
            ['id' => 'Waypoints', 'name' => 'Itinéraire', 'description' => 'Points de passage et itinéraire guidé', 'section' => 'Navigation'],
            ['id' => 'Route', 'name' => 'Routes', 'description' => 'Routes nommées : type de tracé, étapes typées, calculs, guidage et partage', 'section' => 'Navigation'],
            ['id' => 'Relief', 'name' => 'Relief', 'description' => 'Relief, pentes et lignes de vue du terrain', 'section' => 'Navigation'],
            ['id' => 'Weather', 'name' => 'Météo', 'description' => 'Vent, visibilité, températures et prévisions', 'section' => 'Navigation'],
            ['id' => 'Distance', 'name' => 'Distances', 'description' => 'Distances parcourues à pied et en véhicule', 'section' => 'Navigation'],
            ['id' => 'Garmin', 'name' => 'Garmin', 'description' => 'Montre Garmin : pas, cardio, stress, sommeil', 'section' => 'Navigation'],
            ['id' => 'Chat', 'name' => 'Messages', 'description' => 'Canaux de l\'unité et SMS entre joueurs', 'section' => 'Communication'],
            ['id' => 'Sms', 'name' => 'SMS', 'description' => 'SMS entre joueurs, de téléphone à téléphone', 'section' => 'Communication'],
            ['id' => 'Group', 'name' => 'Groupe', 'description' => 'Composition du groupe, rôles et indicatifs', 'section' => 'Communication'],
            ['id' => 'Bft', 'name' => 'BFT', 'description' => 'Suivi des forces amies (Blue Force Tracking)', 'section' => 'Communication'],
            ['id' => 'InterTeam', 'name' => 'Inter-team', 'description' => 'Liaison entre équipes et fire teams', 'section' => 'Communication'],
            ['id' => 'WaveRelay', 'name' => 'Wave Relay', 'description' => 'Réseau maillé radio : relais et qualité de liaison', 'section' => 'Communication'],
            ['id' => 'Numbers', 'name' => 'Numéros', 'description' => 'Annuaire des numéros de téléphone', 'section' => 'Communication'],
            ['id' => 'Discord', 'name' => 'Discord', 'description' => 'Messages du Discord de la communauté', 'section' => 'Communication'],
            ['id' => 'Pilot', 'name' => 'Mode pilote', 'description' => 'Grande carte, appareils alliés, FIRES en un geste', 'section' => 'Appui et feux'],
            ['id' => 'FiresQuick', 'name' => 'FIRES court', 'description' => 'Cible sur la carte, munition, envoi aux pilotes et JTAC', 'section' => 'Appui et feux'],
            ['id' => 'Fires', 'name' => 'CAS', 'description' => 'Demande d\'appui aérien rapproché (9-LINE)', 'section' => 'Appui et feux'],
            ['id' => 'Jtac', 'name' => 'JTAC', 'description' => 'Guidage des appuis, cibles et cartes IP', 'section' => 'Appui et feux'],
            ['id' => 'Drone', 'name' => 'Drone', 'description' => 'Pilotage et caméra des drones', 'section' => 'Appui et feux'],
            ['id' => 'Medical', 'name' => 'Médical', 'description' => 'Fiches médicales, triage et évacuation', 'section' => 'Spécialités'],
            ['id' => 'Sniper', 'name' => 'Sniper', 'description' => 'Calculs de tir longue distance', 'section' => 'Spécialités'],
            ['id' => 'Breach', 'name' => 'Brèche', 'description' => 'Préparation des brèches et entrées', 'section' => 'Spécialités'],
            ['id' => 'Explo', 'name' => 'Explosifs', 'description' => 'Charges explosives et minuteries', 'section' => 'Spécialités'],
            ['id' => 'Ew', 'name' => 'GE', 'description' => 'Guerre électronique : détection et brouillage', 'section' => 'Spécialités'],
            ['id' => 'DroneDetect', 'name' => 'Anti-drone', 'description' => 'Détection des drones à proximité', 'section' => 'Spécialités'],
            ['id' => 'LinkAlly', 'name' => 'Liaison allié', 'description' => 'Liaison avec une unité alliée', 'section' => 'Spécialités'],
            ['id' => 'Intel', 'name' => 'FRS / FRM', 'description' => 'Fiches de renseignement FRS / FRM', 'section' => 'Renseignement'],
            ['id' => 'Reco', 'name' => 'Reco', 'description' => 'Comptes rendus de reconnaissance', 'section' => 'Renseignement'],
            ['id' => 'Salute', 'name' => 'SALUTE', 'description' => 'Rapport d\'observation SALUTE', 'section' => 'Renseignement'],
            ['id' => 'Photos', 'name' => 'Photo', 'description' => 'Appareil photo du téléphone', 'section' => 'Renseignement'],
            ['id' => 'Gallery', 'name' => 'Galerie', 'description' => 'Photos prises et reçues', 'section' => 'Renseignement'],
            ['id' => 'Livecam', 'name' => 'Vidéo live', 'description' => 'Vidéo en direct partagée', 'section' => 'Renseignement'],
            ['id' => 'Tracking', 'name' => 'Marquer tracking', 'description' => 'Marquer et suivre une cible à l\'image', 'section' => 'Renseignement'],
            ['id' => 'Sse', 'name' => 'SSE', 'description' => 'Exploitation de site (SSE) : indices et saisies', 'section' => 'Renseignement'],
            ['id' => 'DataLink', 'name' => 'Extraction', 'description' => 'Extraction de données au câble DL-7', 'section' => 'Renseignement'],
            ['id' => 'Osint', 'name' => 'OSINT', 'description' => 'Renseignement en sources ouvertes', 'section' => 'Renseignement'],
            ['id' => 'Wanted', 'name' => 'Recherchés', 'description' => 'Personnes recherchées (HVT) de la communauté', 'section' => 'Renseignement'],
            ['id' => 'Tasks', 'name' => 'Tâches', 'description' => 'Tâches et ordres reçus', 'section' => 'Mission'],
            ['id' => 'Reports', 'name' => 'Comptes rendus', 'description' => 'Comptes rendus de fin de mission', 'section' => 'Mission'],
            ['id' => 'Briefing', 'name' => 'Briefing', 'description' => 'Diapositives et consignes de la mission', 'section' => 'Mission'],
            ['id' => 'Plan', 'name' => 'Plan', 'description' => 'Plan de manœuvre et phases', 'section' => 'Mission'],
            ['id' => 'Debrief', 'name' => 'CR fin d\'op', 'description' => 'Compte rendu de fin d\'opération à remplir ou à lire', 'section' => 'Mission'],
            ['id' => 'Tenant', 'name' => 'Communauté', 'description' => 'Consignes, ordres, tips et kit obligatoire de votre communauté', 'section' => 'Mission'],
            ['id' => 'Academy', 'name' => 'Academy', 'description' => 'Cours COMSPEC Academy : lire ses leçons et les présenter sur les écrans', 'section' => 'Mission'],
            ['id' => 'Zones', 'name' => 'Zones', 'description' => 'Battleplan : zones découpées, nommées et numérotées, itinéraires MSR / ASR, situation par zone', 'section' => 'Mission'],
            ['id' => 'Logistics', 'name' => 'Logistique', 'description' => 'Demandes de ravitaillement et logistique', 'section' => 'Mission'],
            ['id' => 'Aar', 'name' => 'Rejeu', 'description' => 'Rejeu de la mission (AAR)', 'section' => 'Mission'],
            ['id' => 'Athena', 'name' => 'Athena', 'description' => 'Liaison au site Athena de la communauté', 'section' => 'Système'],
            ['id' => 'Network', 'name' => 'Réseau', 'description' => 'État du réseau et de la liaison', 'section' => 'Système'],
            ['id' => 'Bluetooth', 'name' => 'Bluetooth', 'description' => 'Jumelage et partage avec les téléphones proches', 'section' => 'Système'],
            ['id' => 'Resynch', 'name' => 'Synchro', 'description' => 'Resynchronisation avec Athena', 'section' => 'Système'],
            ['id' => 'Status', 'name' => 'Statut', 'description' => 'État du téléphone et du joueur', 'section' => 'Système'],
            ['id' => 'Profile', 'name' => 'Profil', 'description' => 'Profil de l\'opérateur', 'section' => 'Système'],
            ['id' => 'Accessories', 'name' => 'Accessoires', 'description' => 'Antennes, batteries et accessoires montés', 'section' => 'Système'],
            ['id' => 'ScreenTime', 'name' => 'Temps d\'écran', 'description' => 'Temps passé sur le téléphone et en jeu', 'section' => 'Système'],
            ['id' => 'Debug', 'name' => 'Debug', 'description' => 'Diagnostic technique du téléphone', 'section' => 'Système'],
            ['id' => 'Credits', 'name' => 'Crédits', 'description' => 'Auteurs et remerciements', 'section' => 'Système'],
            ['id' => 'Music', 'name' => 'Musique', 'description' => 'Lecteur de musique', 'section' => 'Civil'],
            ['id' => 'Calc', 'name' => 'Calculette', 'description' => 'Calculatrice : opérations courantes', 'section' => 'Civil'],
            ['id' => 'Notes', 'name' => 'Bloc-notes', 'description' => 'Notes personnelles enregistrées sur ce poste', 'section' => 'Civil'],
            ['id' => 'Food', 'name' => 'UberEats', 'description' => 'Rations livrées par drone', 'section' => 'Civil'],
            ['id' => 'Dating', 'name' => 'Tinder', 'description' => 'Rencontres entre joueurs', 'section' => 'Civil'],
        ];
    }

    /**
     * Règles de la communauté pour l'App Store du téléphone : id d'app => required | forbidden (absent = au choix du joueur).
     *
     * @param mixed $raw
     * @return array<string, string>
     */
    public function sanitizeAppPolicy(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $known = array_column($this->phoneApps(), 'id');
        $out = [];
        foreach ($known as $id) {
            $rule = $raw[$id] ?? null;
            if ($rule === 'required' || $rule === 'forbidden') {
                $out[$id] = $rule;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $rules id d'app => required | forbidden | player
     * @return array<string, string>
     */
    public function putAppPolicy(int $tenantId, array $rules): array
    {
        $settings = $this->get($tenantId)['settings'];
        $settings['apps_policy'] = $this->sanitizeAppPolicy($rules);
        $settings['updated_at'] = gmdate('c');
        (new \App\Repositories\TenantAtakConfigRepository())->saveExperienceConfig($tenantId, $settings);

        return $settings['apps_policy'];
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
        // App Communauté du téléphone (AtakCommunityBrief) : écrits par Consignes et kit ATAK, lus par GET /api/atak/community-brief.
        $out['community_notes'] = '';
        $out['community_kit'] = '';
        $out['server_control_reviewed'] = false;
        $out['net_live'] = [];
        $out['apps_policy'] = [];

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
        $merged['apps_policy'] = $this->sanitizeAppPolicy($merged['apps_policy']);

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
        $merged = $this->mergeSettings($this->get($tenantId)['settings'], $incoming);
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
     * Comme put(), mais dit si l'écriture a réussi : un écran qui annonce « enregistré » s'appuie sur cette réponse.
     *
     * @param array<string, mixed> $incoming
     */
    public function putChecked(int $tenantId, array $incoming): bool
    {
        $merged = $this->mergeSettings($this->get($tenantId)['settings'], $incoming);
        $merged['updated_at'] = gmdate('c');

        return (new \App\Repositories\TenantAtakConfigRepository())->saveExperienceConfig($tenantId, $merged);
    }

    /**
     * Réglages enregistrés + réglages reçus : seules les clés reçues changent, une valeur hors liste reprend le défaut.
     *
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $incoming
     * @return array<string, mixed>
     */
    public function mergeSettings(array $existing, array $incoming): array
    {
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
            $merged['guide_custom'] = mb_substr(trim((string) $incoming['guide_custom']), 0, self::GUIDE_CUSTOM_MAX);
        }
        foreach (['community_notes', 'community_kit'] as $key) {
            if (array_key_exists($key, $incoming)) {
                $merged[$key] = mb_substr(trim((string) $incoming[$key]), 0, \App\Support\AtakCommunityBrief::MAX_SOURCE);
            }
        }
        if (!empty($incoming['server_control_reviewed'])) {
            $merged['server_control_reviewed'] = true;
        }

        return $merged;
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
        // Fréquence d'envoi des positions (Configuration ATAK > Connexion en jeu). Préfixe net_ : la DLL relaie
        // toutes les clés net_* sans nouvelle version ; le pack l'applique à comspec_overwatch_position_interval.
        try {
            $interval = (int) ((new \App\Services\Game\GameOverwatchExperienceService())->get($tenantId)['update_interval'] ?? 0);
        } catch (\Throwable) {
            $interval = 0;
        }
        $out['net_position_interval'] = $interval >= 2 ? (string) $interval : 'player';
        // App Store du téléphone (Contrôle serveur > Apps du téléphone) : noms de classe séparés par des virgules.
        // Préfixe net_ : la DLL COMSPEC Link relaie toutes les clés net_* sans nouvelle version (fn_appInstall les lit).
        $policy = $this->sanitizeAppPolicy($s['apps_policy'] ?? []);
        $required = array_keys(array_filter($policy, static fn (string $r): bool => $r === 'required'));
        $forbidden = array_keys(array_filter($policy, static fn (string $r): bool => $r === 'forbidden'));
        $out['net_apps_required'] = $required === [] ? 'player' : implode(',', $required);
        $out['net_apps_forbidden'] = $forbidden === [] ? 'player' : implode(',', $forbidden);

        return $out;
    }

    /**
     * Guide du joueur : app Communauté du téléphone (onglet Consignes) et journal de mission, à jour pour le mod 4.0.0.
     * Chaque phrase correspond au mod (COMSPEC_ATAK_Native et COMSPEC Overwatch connect) : touches de
     * XEH_postInitClient.sqf, liaison de fn_athenaLink et fn_pageAthena, réglages de fn_applyTenantExperience et fn_hasDevice.
     * Le jeu lit ce texte tel quel : pas de flèche (sa police ne les a pas), et l'ensemble doit tenir avec
     * les autres réglages dans la réponse de la DLL (MaxOutputBytes), sinon la ligne du guide disparaît en entier.
     *
     * @param array<string, mixed> $settings
     */
    public function buildGuide(array $settings): string
    {
        $lines = [];
        $lines[] = '=== Guide du téléphone ATAK (communauté) ===';
        $lines[] = '';
        $lines[] = '1. Relier le jeu à votre compte Athena';
        $lines[] = '   • Ouvrez l’app Athena du téléphone, onglet CODE, et obtenez votre code.';
        $lines[] = '   • Si l’app vous le demande, répondez « OUI, MON UNITÉ UTILISE ATHENA ».';
        $lines[] = '   • Sur athena-comspec.eu/lier, connecté à votre compte, saisissez ce code.';
        $lines[] = '   • À faire une seule fois : les parties suivantes se connectent seules.';
        $lines[] = '   • Onglets E-MAIL et STEAM : autres moyens, si votre communauté les autorise.';
        $lines[] = '';
        $lines[] = '2. Utiliser le téléphone (touches par défaut)';
        $lines[] = '   • Ctrl+U : sortir ou ranger le téléphone en miniature, vous continuez à jouer.';
        $lines[] = '   • Alt+U : le prendre en main avec la souris, ou le reposer.';
        $lines[] = '   • Maj+U : utiliser la miniature à la souris, sans l’agrandir.';
        $lines[] = '   • Ctrl+Page haut et Ctrl+Page bas : zoomer et dézoomer la carte.';
        $lines[] = '   • Changer une touche : app Réglages, ou Options > Contrôles > Configurer les addons > COMSPEC ATAK.';
        $lines[] = '   • App Communauté : consignes, ordres, tips et kit obligatoire.';
        $lines[] = '';

        if (!empty($settings['realism'])) {
            $lines[] = '3. Mode réalisme : ACTIF pour votre communauté';
            $lines[] = '   • Plus de bandeaux d’alerte Athena ni de messages d’aide à l’écran.';
            $lines[] = '   • Plus d’alertes de suivi automatique (immobilité, déplacement brusque).';
            $lines[] = '   • La liaison et l’envoi de position continuent ; alertes médicales et ordres arrivent dans le téléphone.';
            $lines[] = '   • Le réseau simulé et les dégâts du téléphone ont leurs propres réglages : ce mode ne les change pas.';
        } elseif (!empty($settings['troll'])) {
            $lines[] = '3. Mode troll : ACTIF pour votre communauté';
            $lines[] = '   • Alertes de suivi plus sensibles : immobile après 2 min, déplacement brusque dès 150 m (sinon 3 min et 250 m).';
            $lines[] = '   • À réserver aux sessions décontractées.';
        } else {
            $lines[] = '3. Vos réglages';
            $lines[] = '   • Réglages d’addons (CBA), catégorie COMSPEC, rubrique Overwatch : « Bandeaux d’alerte à l’écran » et « Mode milsim : moins d’aides à l’écran ».';
        }
        $lines[] = '';

        $lines[] = '4. Règles de votre communauté';
        foreach ($this->triStateHints($settings) as $hint) {
            $lines[] = '   • ' . $hint;
        }

        $custom = trim(self::gameText((string) ($settings['guide_custom'] ?? '')));
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

        return implode("\n", $lines);
    }

    /** Flèches saisies par la communauté : la police du jeu les affiche en glyphes barrés. */
    private static function gameText(string $text): string
    {
        return strtr($text, ['→' => '>', '⇒' => '>', '➜' => '>', '➔' => '>', '➡' => '>', '←' => '<', '⇐' => '<', '⬅' => '<']);
    }

    /**
     * @param array<string, mixed> $settings
     * @return list<string>
     */
    private function triStateHints(array $settings): array
    {
        $hints = [];
        // Le réalisme et le mode roleplay masquent les bandeaux quoi qu'il arrive (fn_shouldShowScreenNotification).
        $bannersHidden = !empty($settings['realism']) || (string) ($settings['rp_enabled'] ?? 'player') === 'on';
        $map = [
            'screen_notifications' => [
                'on' => $bannersHidden ? null : 'Les bandeaux d’alerte Athena sont activés à l’écran (en bas à gauche).',
                'off' => 'Les bandeaux d’alerte Athena sont masqués : les alertes restent dans le téléphone.',
            ],
            'require_equipment' => [
                'on' => 'Téléphone obligatoire sur vous (équipé ou dans l’inventaire). Équipage d’aéronef : tablette de bord si le serveur l’autorise.',
                'off' => 'Aucun objet requis : l’ATAK s’utilise sans téléphone dans l’équipement.',
                'player' => 'Téléphone obligatoire ou non : c’est le serveur de jeu qui décide.',
            ],
            'vehicle_detail' => [
                'on' => 'En véhicule, l’orientation et la vitesse sont envoyées à la carte Athena.',
                'off' => 'En véhicule, l’orientation et la vitesse ne sont pas envoyées à la carte Athena.',
            ],
            'show_opfor' => [
                'on' => 'Les observateurs sur Athena voient les positions du camp ennemi.',
                'off' => 'Les observateurs sur Athena ne voient pas les positions du camp ennemi.',
            ],
            'show_independent' => [
                'on' => 'Les observateurs sur Athena voient les positions des indépendants.',
                'off' => 'Les observateurs sur Athena ne voient pas les positions des indépendants.',
            ],
            'show_civilian' => [
                'on' => 'Les observateurs sur Athena voient les positions des civils.',
                'off' => 'Les observateurs sur Athena ne voient pas les positions des civils.',
            ],
        ];
        foreach ($map as $key => $labels) {
            $hint = $labels[(string) ($settings[$key] ?? 'player')] ?? null;
            if ($hint !== null) {
                $hints[] = $hint;
            }
        }

        return $hints;
    }
}
