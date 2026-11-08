<?php

declare(strict_types=1);

namespace App\Services\GameEquipment;

use App\Support\ArsenalLoadoutItems;

/**
 * Assistant de création de tenue : part d'un profil (déduit d'un emploi de la communauté), propose des objets pris
 * uniquement dans ce que la communauté a réellement remonté du jeu (catalogue d'objets), emplacement par emplacement,
 * signale ce qui manque et produit un loadout au format getUnitLoadout d'Arma 3 (RoleKitLoadout::build), donc accepté
 * tel quel par l'export ACE et par le jeu.
 *
 * Règles simples et lisibles, aucun modèle d'IA :
 *  - un profil dit quels emplacements comptent (obligatoire, conseillé, facultatif) et quels mots du nom d'un objet
 *    le désignent (fusil de précision pour un tireur d'élite, par exemple) ;
 *  - les candidats d'un emplacement sont les objets de la bonne famille déjà vus en jeu, classés par mot-clé puis par
 *    nombre de joueurs qui les portent ;
 *  - chargeur, optique et accessoires viennent de ce que les joueurs portent réellement avec la même arme
 *    (ObservedLoadouts) : sans relevé, l'assistant le dit au lieu d'inventer ;
 *  - un emplacement obligatoire ou conseillé sans candidat est listé dans « ce qui manque ».
 *
 * Pur : reçoit le catalogue et les relevés, ne touche pas la base.
 */
final class OutfitAssistant
{
    public const CUSTOM = 'custom';

    /** Emplacement => [libellé, famille du catalogue, groupe d'affichage]. */
    public const SLOTS = [
        'primary' => ['Arme principale', 'primary', 'weapons'],
        'optic' => ['Optique', 'optic', 'weapons'],
        'secondary' => ['Lanceur', 'secondary', 'weapons'],
        'handgun' => ['Arme de poing', 'handgun', 'weapons'],
        'uniform' => ['Tenue', 'uniform', 'protection'],
        'vest' => ['Gilet', 'vest', 'protection'],
        'headgear' => ['Casque', 'headgear', 'protection'],
        'goggles' => ['Lunettes', 'goggles', 'protection'],
        'backpack' => ['Sac', 'backpack', 'carry'],
        'radio' => ['Radio', 'radio', 'gear'],
        'nvg' => ['Vision nocturne', 'nvg', 'gear'],
        'binocular' => ['Jumelles', 'binocular', 'gear'],
        'map' => ['Carte', 'item', 'gear'],
        'gps' => ['GPS ou tablette', 'item', 'gear'],
        'compass' => ['Boussole', 'item', 'gear'],
        'watch' => ['Montre', 'item', 'gear'],
    ];

    public const GROUPS = [
        'weapons' => 'Armes',
        'protection' => 'Protection',
        'carry' => 'Transport',
        'gear' => 'Équipement porté',
    ];

    /** Objets « divers » reconnus par leur nom (le catalogue les range tous dans la famille « Objet »). */
    private const ITEM_PATTERNS = [
        'map' => '/itemmap/',
        'gps' => '/itemgps|androidtablet|microdagr|itemandroid|tablet/',
        'compass' => '/itemcompass/',
        'watch' => '/itemwatch/',
    ];

    private const DEFAULT_NEEDS = [
        'primary' => 'required',
        'uniform' => 'required',
        'vest' => 'recommended',
        'headgear' => 'recommended',
        'map' => 'recommended',
        'compass' => 'recommended',
        'watch' => 'recommended',
    ];

    /**
     * Profils : libellé, phrase d'aide, emplacements qui comptent (need), mots-clés préférés (prefer), emplacements
     * où l'absence de mot-clé reconnu est signalée (strict), nombre de chargeurs de rechange, matériel médical.
     */
    public const PROFILES = [
        'rifleman' => [
            'label' => 'Fusilier',
            'hint' => 'Tenue de base d’un combattant : arme, protection, chargeurs et pansements.',
            'need' => [],
            'prefer' => [],
            'strict' => [],
            'mags' => 7,
            'medical' => 'basic',
        ],
        'team_leader' => [
            'label' => 'Chef d’équipe',
            'hint' => 'Fusilier qui conduit un groupe : radio, jumelles, carte et GPS.',
            'need' => ['radio' => 'recommended', 'binocular' => 'recommended', 'map' => 'recommended', 'gps' => 'recommended', 'compass' => 'recommended', 'watch' => 'recommended'],
            'prefer' => [],
            'strict' => [],
            'mags' => 6,
            'medical' => 'basic',
        ],
        'medic' => [
            'label' => 'Médecin',
            'hint' => 'Arme légère, grand sac et beaucoup de matériel médical.',
            'need' => ['backpack' => 'required'],
            'prefer' => ['backpack' => '/medic|medical|ambul|doctor|corpsman|surgeon|carryall|kitbag|bergen/'],
            'strict' => [],
            'mags' => 5,
            'medical' => 'full',
        ],
        'marksman' => [
            'label' => 'Tireur d’élite',
            'hint' => 'Fusil de précision avec une optique de portée, jumelles pour observer.',
            'need' => ['optic' => 'required', 'binocular' => 'recommended', 'goggles' => 'optional', 'handgun' => 'recommended'],
            'prefer' => [
                'primary' => '/srifle|sniper|marksman|dmr|ebr|m14|m24|m107|lrr|gm6|mar10|mk14|mk18|cmr|rahim|svd|vss|sv98|m110|tac50|as50|jng|spotter/',
                'optic' => '/lrps|sos|dms|nightstalker|ard|ago|scope|mil|mrco|long/',
                'binocular' => '/rangefinder|laserdesignator|range/',
            ],
            'strict' => ['primary'],
            'mags' => 8,
            'medical' => 'basic',
        ],
        'mg' => [
            'label' => 'Mitrailleur',
            'hint' => 'Arme d’appui avec ses boîtes de munitions, gilet et sac pour les porter.',
            'need' => ['backpack' => 'recommended', 'handgun' => 'optional'],
            'prefer' => ['primary' => '/lmg|mmg|m249|mk200|rpk|pkm|pkp|negev|zafir|spmg|m60|mk46|mk48|saw|minimi|mg42|mg3|mg4|_mg|mg_|machinegun/'],
            'strict' => ['primary'],
            'mags' => 3,
            'medical' => 'basic',
        ],
        'at' => [
            'label' => 'Antichar',
            'hint' => 'Lanceur et roquettes de rechange dans le sac, arme légère pour le reste.',
            'need' => ['secondary' => 'required', 'backpack' => 'required'],
            'prefer' => [],
            'strict' => [],
            'mags' => 5,
            'launcher_spare' => 2,
            'medical' => 'basic',
        ],
        'radio' => [
            'label' => 'Opérateur radio / JTAC',
            'hint' => 'Radio et sac de transmission, jumelles ou désignateur, carte et GPS.',
            'need' => ['radio' => 'required', 'backpack' => 'recommended', 'binocular' => 'recommended', 'map' => 'recommended', 'gps' => 'recommended', 'compass' => 'recommended', 'watch' => 'recommended'],
            'prefer' => ['backpack' => '/radio|rt1523|prc|tfar|acre|comms|signal/', 'binocular' => '/laserdesignator|rangefinder|designator/'],
            'strict' => [],
            'mags' => 6,
            'medical' => 'basic',
        ],
        'engineer' => [
            'label' => 'Génie / déminage',
            'hint' => 'Sac pour la trousse à outils et le matériel de déminage.',
            'need' => ['backpack' => 'required'],
            'prefer' => ['backpack' => '/engineer|carryall|bergen|kitbag|assault|huron/'],
            'strict' => [],
            'mags' => 6,
            'medical' => 'basic',
            'specialist' => true,
        ],
        'pilot' => [
            'label' => 'Pilote / équipage',
            'hint' => 'Combinaison et casque d’équipage, arme de poing, radio.',
            'need' => ['primary' => 'optional', 'handgun' => 'recommended', 'radio' => 'recommended', 'headgear' => 'required', 'vest' => 'optional'],
            'prefer' => [
                'uniform' => '/pilot|crew|heli|coverall|tanker/',
                'headgear' => '/pilot|crew|heli|tanker/',
                'primary' => '/smg|pdw|sd|carbine|compact/',
            ],
            'strict' => [],
            'mags' => 4,
            'medical' => 'basic',
        ],
        self::CUSTOM => [
            'label' => 'Libre (de zéro)',
            'hint' => 'Aucune règle : tous les emplacements sont facultatifs, classés par ce que les joueurs portent le plus.',
            'need' => ['primary' => 'optional', 'uniform' => 'optional'],
            'prefer' => [],
            'strict' => [],
            'mags' => 6,
            'medical' => 'none',
            'all_slots' => true,
        ],
    ];

    /** Mots du nom d'un emploi => profil (premier trouvé, dans cet ordre). */
    private const ROLE_WORDS = [
        'medic' => '/medic|medec|medecin|infirm|secouris|corpsman|\bcls\b|sante|health|paramed|ambul|doctor|urgentiste/',
        'marksman' => '/tireur|sniper|marksman|\bdmr\b|designated|spotter|precision|observateur/',
        'at' => '/antichar|anti[ -]?char|\bat\b|lance[ -]?roquette|rocket|missile|javelin|\brpg\b|\bmat\b/',
        'mg' => '/mitrailleu|machine ?gun|\bmg\b|\bsaw\b|automatic rifle|fusil[ -]?mitrailleur|support gunner|\bfm\b/',
        'radio' => '/radio|transmission|signal|\bjtac\b|\bfac\b|\brto\b|telecom|comms/',
        'engineer' => '/genie|genist|sapeur|engineer|\beod\b|demin|\bieo\b|pionnier|demolition|breacher|sapper/',
        'pilot' => '/pilot|equipage|crew|aviat|helico|chopper|tankiste|conducteur|driver|mecanicien/',
        'team_leader' => '/chef|leader|commandant|commander|\bsl\b|\btl\b|sergent|officier|lieutenant|capitaine|\bnco\b|adjudant/',
    ];

    /**
     * Matériel médical : clé, libellé, motif du nom, nombre pour le profil « basic » et « full », rangement préféré.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: int, 4: int, 5: string}>
     */
    private const MEDICAL = [
        ['bandage', 'Pansements', '/bandage|fielddressing|packingbandage|elasticbandage|quikclot/', 4, 15, 'small'],
        ['tourniquet', 'Garrots', '/tourniquet/', 1, 4, 'small'],
        ['morphine', 'Morphine', '/morphine/', 0, 6, 'small'],
        ['epinephrine', 'Adrénaline', '/epinephrine|adrenaline/', 0, 6, 'small'],
        ['iv', 'Perfusions', '/bloodiv|plasma|salineiv|saline|ivbag/', 0, 4, 'bulky'],
        ['splint', 'Attelles', '/splint/', 0, 2, 'small'],
        ['kit', 'Trousse de soins', '/personalaidkit|surgicalkit|firstaidkit|medikit|\bpak\b/', 1, 1, 'small'],
    ];

    /** Matériel spécialisé du génie : clé, libellé, motif, nombre. */
    private const SPECIALIST = [
        ['toolkit', 'Trousse à outils', '/toolkit/', 1],
        ['detector', 'Détecteur de mines', '/minedetector/', 1],
        ['defusal', 'Kit de désamorçage', '/defusalkit|defuse/', 1],
        ['clacker', 'Télécommande d’explosifs', '/clacker|firingdevice|detonator/', 1],
    ];

    /** @return array<string, string> clé => libellé */
    public static function profileOptions(): array
    {
        $out = [];
        foreach (self::PROFILES as $key => $def) {
            $out[$key] = $def['label'];
        }

        return $out;
    }

    public static function normalizeProfile(mixed $key): string
    {
        $key = is_string($key) ? trim($key) : '';

        return isset(self::PROFILES[$key]) ? $key : 'rifleman';
    }

    /**
     * Profil proposé pour un emploi, d'après les mots de son nom, de son abrégé et de sa catégorie. « Fusilier » à
     * défaut : l'utilisateur peut toujours en choisir un autre.
     */
    public static function profileForRole(string $roleName, string $abbreviation = '', string $category = ''): string
    {
        $hay = self::fold($roleName . ' ' . $abbreviation . ' ' . $category);
        foreach (self::ROLE_WORDS as $profile => $pattern) {
            if (preg_match($pattern, $hay) === 1) {
                return $profile;
            }
        }

        return 'rifleman';
    }

    /**
     * @param list<array{class: string, name: string, kind: string, mod: string, max_ammo: int|null, players: int}> $catalogue
     * @param array<string, array<string, mixed>> $setups ObservedLoadouts::weaponSetups()
     * @param array{slot?: array<string, mixed>, extra?: array<string, mixed>} $choices choix de l'utilisateur ; vide = proposition par défaut
     * @return array{
     *   profile: string, profile_label: string, hint: string, has_data: bool,
     *   slots: array<string, array<string, mixed>>, groups: array<string, list<string>>,
     *   extras: list<array<string, mixed>>, accessories: list<array<string, string>>,
     *   missing: list<array{slot: string, label: string, need: string, message: string}>,
     *   warnings: list<string>, kit: array<string, mixed>, payload: string, mods: list<string>,
     *   item_count: int, ace_ok: bool, names: array<string, array{name: string, players: int}>
     * }
     */
    public function propose(string $profile, array $catalogue, array $setups = [], array $choices = []): array
    {
        $profile = self::normalizeProfile($profile);
        $def = self::PROFILES[$profile];
        $index = [];
        $byKind = [];
        foreach ($catalogue as $item) {
            $class = (string) ($item['class'] ?? '');
            if ($class === '') {
                continue;
            }
            $index[strtolower($class)] = $item;
            $byKind[(string) ($item['kind'] ?? 'item')][] = $item;
        }
        $picked = is_array($choices['slot'] ?? null) ? $choices['slot'] : [];
        $hasChoices = $choices !== [] && is_array($choices['slot'] ?? null);

        $slots = [];
        $missing = [];
        $groups = [];
        foreach (self::SLOTS as $slot => [$label, $kind, $group]) {
            $need = $this->need($def, $slot);
            if ($need === 'hidden') {
                continue;
            }
            $pattern = $def['prefer'][$slot] ?? (self::ITEM_PATTERNS[$slot] ?? null);
            $strictItem = $kind === 'item';
            $strictPref = !$strictItem && $pattern !== null && in_array($slot, $def['strict'], true);
            $pool = $byKind[$kind] ?? [];
            if ($strictItem) {
                $pool = array_values(array_filter($pool, fn (array $it): bool => $this->matches($it, (string) $pattern)));
            }
            $observed = '';
            if ($slot === 'optic') {
                $observed = $this->observedFor($setups, $this->chosenClass($slots, 'primary'), 'optic', $index);
            }
            $ranked = $this->rank($pool, $pattern, $observed);
            $matched = array_values(array_filter($ranked, static fn (array $it): bool => $it['_pref']));

            // Choix par défaut : emplacements obligatoires ou conseillés (et l'optique habituelle de l'arme choisie).
            // Un mot-clé exigé (fusil de précision, arme d'appui) qu'aucun objet ne porte laisse l'emplacement vide.
            $default = '';
            $wantsDefault = $need === 'required' || $need === 'recommended' || $observed !== '';
            if ($wantsDefault && $ranked !== [] && !($strictPref && $matched === [])) {
                $default = (string) $ranked[0]['class'];
            }
            $chosen = $default;
            if ($hasChoices && array_key_exists($slot, $picked)) {
                $want = is_string($picked[$slot]) ? trim($picked[$slot]) : '';
                $chosen = $want === '' ? '' : $this->classIn($pool, $want);
            }

            $problem = '';
            if ($need === 'required' || $need === 'recommended') {
                if ($pool === []) {
                    $problem = 'Aucun objet de ce type n’a encore été remonté du jeu par votre communauté.';
                } elseif ($chosen === '') {
                    $problem = ($strictPref && $matched === [])
                        ? 'Aucun objet reconnu pour ce rôle parmi ceux remontés du jeu : choisissez-en un à la main ou faites porter le bon matériel en jeu.'
                        : 'Rien de choisi pour cet emplacement.';
                }
            }
            if ($problem !== '') {
                $missing[] = ['slot' => $slot, 'label' => $label, 'need' => $need, 'message' => $problem];
            }
            $slots[$slot] = [
                'label' => $label,
                'kind' => $kind,
                'group' => $group,
                'need' => $need,
                'chosen' => $chosen,
                'candidates' => array_map(static fn (array $it): array => [
                    'class' => (string) $it['class'],
                    'name' => (string) $it['name'],
                    'players' => (int) ($it['players'] ?? 0),
                    'mod' => (string) ($it['mod'] ?? ''),
                    'preferred' => (bool) $it['_pref'],
                ], array_slice($ranked, 0, 60)),
                'problem' => $problem,
                'observed' => $observed,
            ];
            $groups[$group][] = $slot;
        }

        $primary = $this->chosenClass($slots, 'primary');
        $launcher = $this->chosenClass($slots, 'secondary');
        $handgun = $this->chosenClass($slots, 'handgun');
        $setup = $primary !== '' ? ($setups[strtolower($primary)] ?? null) : null;

        $weapons = [];
        $accessories = [];
        $warnings = [];
        foreach (['primary' => $primary, 'secondary' => $launcher, 'handgun' => $handgun] as $slot => $class) {
            $row = ['class' => $class, 'optic' => '', 'muzzle' => '', 'pointer' => '', 'bipod' => '', 'magazine' => ''];
            if ($class !== '') {
                $seen = $setups[strtolower($class)] ?? null;
                if ($seen !== null) {
                    $row['magazine'] = $this->keepKnown(ObservedLoadouts::top($seen['magazine']), $index);
                    if ($slot === 'primary') {
                        foreach (['muzzle', 'pointer', 'bipod'] as $part) {
                            $row[$part] = $this->keepKnown(ObservedLoadouts::top($seen[$part]), $index);
                            if ($row[$part] !== '') {
                                $accessories[] = ['part' => $part, 'class' => $row[$part], 'name' => $this->nameOf($row[$part], $index)];
                            }
                        }
                    }
                }
                if ($row['magazine'] === '') {
                    $warnings[] = 'Chargeur de « ' . $this->nameOf($class, $index) . ' » inconnu : personne n’a encore porté cette arme en jeu avec son chargeur. Portez-la une fois en jeu pour que l’assistant le retienne.';
                }
            }
            $weapons[$slot] = $row;
        }
        $weapons['primary']['optic'] = $this->chosenClass($slots, 'optic');
        if ($primary === '') {
            $weapons['primary']['optic'] = '';
        }

        // Rangement : où va chaque ligne de contenu selon les conteneurs choisis.
        $has = [
            'uniform' => $this->chosenClass($slots, 'uniform') !== '',
            'vest' => $this->chosenClass($slots, 'vest') !== '',
            'backpack' => $this->chosenClass($slots, 'backpack') !== '',
        ];
        $extras = [];
        $extraPicked = is_array($choices['extra'] ?? null) ? $choices['extra'] : [];
        $addExtra = function (string $key, string $label, string $class, int $count, string $place, bool $defaultOn) use (&$extras, $extraPicked, $hasChoices, $index, $has): void {
            if ($class === '' || $count < 1) {
                return;
            }
            $on = $hasChoices ? !empty($extraPicked[$key]) : $defaultOn;
            $container = self::container($place, $has);
            $extras[] = [
                'key' => $key,
                'label' => $label,
                'class' => $class,
                'name' => $this->nameOf($class, $index),
                'count' => $count,
                'container' => $container,
                'on' => $on && $container !== '',
            ];
        };
        $addExtra('mag_primary', 'Chargeurs de rechange (arme principale)', $weapons['primary']['magazine'], (int) $def['mags'], 'mags', true);
        $addExtra('mag_handgun', 'Chargeurs de rechange (arme de poing)', $weapons['handgun']['magazine'], 2, 'small', true);
        $addExtra('mag_launcher', 'Roquettes de rechange', $weapons['secondary']['magazine'], (int) ($def['launcher_spare'] ?? 1), 'bulky', true);
        $med = (string) ($def['medical'] ?? 'none');
        if ($med !== 'none') {
            $medPool = array_merge($byKind['medical'] ?? [], $byKind['item'] ?? []);
            foreach (self::MEDICAL as [$key, $label, $pattern, $basic, $full, $place]) {
                $count = $med === 'full' ? $full : $basic;
                $best = $this->rank(array_values(array_filter($medPool, fn (array $it): bool => $this->matches($it, $pattern))), null, '');
                if ($best === []) {
                    if ($count > 0 && $med === 'full' && in_array($key, ['bandage', 'morphine', 'epinephrine', 'iv'], true)) {
                        $missing[] = ['slot' => 'med_' . $key, 'label' => $label, 'need' => 'recommended', 'message' => 'Aucun objet « ' . mb_strtolower($label) . ' » remonté du jeu : ce matériel ne peut pas être proposé.'];
                    } elseif ($count > 0 && $key === 'bandage') {
                        $missing[] = ['slot' => 'med_' . $key, 'label' => $label, 'need' => 'optional', 'message' => 'Aucun pansement remonté du jeu.'];
                    }
                    continue;
                }
                $addExtra('med_' . $key, $label, (string) $best[0]['class'], $count, $place, true);
            }
        }
        if (!empty($def['specialist'])) {
            $specPool = $byKind['item'] ?? [];
            foreach (self::SPECIALIST as [$key, $label, $pattern, $count]) {
                $best = $this->rank(array_values(array_filter($specPool, fn (array $it): bool => $this->matches($it, $pattern))), null, '');
                if ($best === []) {
                    $missing[] = ['slot' => 'spec_' . $key, 'label' => $label, 'need' => 'optional', 'message' => 'Aucun objet « ' . mb_strtolower($label) . ' » remonté du jeu.'];
                    continue;
                }
                $addExtra('spec_' . $key, $label, (string) $best[0]['class'], $count, 'bulky', true);
            }
        }
        if (array_filter($extras, static fn (array $e): bool => $e['container'] === '') !== []) {
            $warnings[] = 'Sans tenue, gilet ni sac, le contenu (chargeurs, matériel médical) ne peut pas être rangé : choisissez au moins un conteneur.';
        }

        $cargo = ['uniform' => [], 'vest' => [], 'backpack' => []];
        foreach ($extras as $e) {
            if ($e['on']) {
                $cargo[$e['container']][] = ['class' => $e['class'], 'count' => $e['count']];
            }
        }
        $simple = [];
        foreach (RoleKitLoadout::SIMPLE as $key => $_family) {
            $simple[$key] = $this->chosenClass($slots, $key);
        }
        $kit = RoleKitLoadout::normalize(['weapons' => $weapons, 'simple' => $simple, 'cargo' => $cargo]);
        $payload = RoleKitLoadout::isEmpty($kit) ? '' : RoleKitLoadout::build($kit, $index);
        $classes = RoleKitLoadout::classes($kit);
        $names = [];
        foreach ($classes as $class) {
            $names[strtolower($class)] = ['name' => $this->nameOf($class, $index), 'players' => (int) ($index[strtolower($class)]['players'] ?? 0)];
        }

        return [
            'profile' => $profile,
            'profile_label' => (string) $def['label'],
            'hint' => (string) $def['hint'],
            'has_data' => $catalogue !== [],
            'slots' => $slots,
            'groups' => $groups,
            'extras' => $extras,
            'accessories' => $accessories,
            'missing' => $missing,
            'warnings' => $warnings,
            'kit' => $kit,
            'payload' => $payload,
            'mods' => RoleKitLoadout::requiredMods($classes, $index),
            'item_count' => count($classes),
            'ace_ok' => $payload !== '' && ArsenalLoadoutItems::aceExport($payload) !== null,
            'names' => $names,
        ];
    }

    /** @param array<string, mixed> $def */
    private function need(array $def, string $slot): string
    {
        if (!empty($def['all_slots'])) {
            return $def['need'][$slot] ?? 'optional';
        }
        if (isset($def['need'][$slot])) {
            return (string) $def['need'][$slot];
        }
        if ($slot === 'secondary') {
            return 'hidden';
        }

        return self::DEFAULT_NEEDS[$slot] ?? 'optional';
    }

    /**
     * @param list<array<string, mixed>> $pool
     * @return list<array<string, mixed>> objets avec _pref (mot-clé reconnu), les préférés d'abord puis les plus portés
     */
    private function rank(array $pool, ?string $pattern, string $observedClass): array
    {
        $rows = [];
        foreach ($pool as $it) {
            $isObserved = $observedClass !== '' && strcasecmp((string) $it['class'], $observedClass) === 0;
            $pref = ($pattern !== null && $this->matches($it, $pattern)) || $isObserved;
            $rows[] = $it + [
                '_pref' => $pref,
                '_score' => ($pattern !== null && $this->matches($it, $pattern) ? 1000000 : 0) + ($isObserved ? 5000000 : 0) + (int) ($it['players'] ?? 0),
            ];
        }
        usort($rows, static fn (array $a, array $b): int => ($b['_score'] <=> $a['_score']) ?: strcasecmp((string) $a['name'] . (string) $a['class'], (string) $b['name'] . (string) $b['class']));

        return $rows;
    }

    /** @param array<string, mixed> $item */
    private function matches(array $item, string $pattern): bool
    {
        $text = strtolower((string) ($item['class'] ?? '') . ' ' . self::fold((string) ($item['name'] ?? '')));

        return $pattern !== '' && preg_match($pattern, $text) === 1;
    }

    /** @param list<array<string, mixed>> $pool */
    private function classIn(array $pool, string $class): string
    {
        foreach ($pool as $it) {
            if (strcasecmp((string) $it['class'], $class) === 0) {
                return (string) $it['class'];
            }
        }

        return '';
    }

    /** @param array<string, array<string, mixed>> $slots */
    private function chosenClass(array $slots, string $slot): string
    {
        return (string) ($slots[$slot]['chosen'] ?? '');
    }

    /**
     * Valeur la plus portée avec cette arme, si l'objet est connu du catalogue de la communauté.
     *
     * @param array<string, array<string, mixed>> $setups
     * @param array<string, array<string, mixed>> $index
     */
    private function observedFor(array $setups, string $weapon, string $part, array $index): string
    {
        if ($weapon === '' || !isset($setups[strtolower($weapon)])) {
            return '';
        }

        return $this->keepKnown(ObservedLoadouts::top($setups[strtolower($weapon)][$part] ?? []), $index);
    }

    /** @param array<string, array<string, mixed>> $index */
    private function keepKnown(string $class, array $index): string
    {
        return $class !== '' && isset($index[strtolower($class)]) ? (string) $index[strtolower($class)]['class'] : '';
    }

    /** @param array<string, array<string, mixed>> $index */
    private function nameOf(string $class, array $index): string
    {
        $name = trim((string) ($index[strtolower($class)]['name'] ?? ''));

        return $name !== '' ? $name : $class;
    }

    /**
     * Conteneur retenu pour un rangement : « mags » (gilet d'abord), « small » (tenue d'abord), « bulky » (sac d'abord).
     *
     * @param array{uniform: bool, vest: bool, backpack: bool} $has
     */
    private static function container(string $place, array $has): string
    {
        $order = match ($place) {
            'bulky' => ['backpack', 'vest', 'uniform'],
            'small' => ['uniform', 'vest', 'backpack'],
            default => ['vest', 'backpack', 'uniform'],
        };
        foreach ($order as $c) {
            if ($has[$c]) {
                return $c;
            }
        }

        return '';
    }

    /** Minuscules sans accents, pour comparer les noms d'emplois et d'objets. */
    private static function fold(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');

        return strtr($text, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'œ' => 'oe', '’' => ' ', '\'' => ' ',
        ]);
    }
}
