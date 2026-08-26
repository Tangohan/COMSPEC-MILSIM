<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Journal public style Bohemia (SPOTREP / TECHREP / UPDATE).
 * Textes destinés aux opérateurs et au commandement — pas de jargon d’implémentation.
 */
final class DevDispatchCatalog
{
    public const KIND_SPOTREP = 'spotrep';
    public const KIND_TECHREP = 'techrep';
    public const KIND_UPDATE = 'update';

    /**
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        $out = [];
        foreach (self::raw() as $row) {
            $out[] = self::resolve($row);
        }
        usort($out, static function (array $a, array $b): int {
            $d = strcmp((string) $b['date'], (string) $a['date']);
            if ($d !== 0) {
                return $d;
            }

            return ((int) $b['number']) <=> ((int) $a['number']);
        });

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $kind, string $number): ?array
    {
        $kind = strtolower(trim($kind));
        $number = self::padNumber($number);
        if (!in_array($kind, [self::KIND_SPOTREP, self::KIND_TECHREP, self::KIND_UPDATE], true)) {
            return null;
        }
        foreach (self::all() as $row) {
            if ($row['kind'] === $kind && $row['number_pad'] === $number) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function featured(): ?array
    {
        foreach (self::all() as $row) {
            if (!empty($row['featured'])) {
                return $row;
            }
        }
        foreach (self::all() as $row) {
            if ($row['kind'] === self::KIND_SPOTREP) {
                return $row;
            }
        }

        return self::all()[0] ?? null;
    }

    public static function href(string $kind, int|string $number): string
    {
        return url('nouveautes/' . $kind . '/' . self::padNumber((string) $number));
    }

    public static function padNumber(string $number): string
    {
        $n = preg_replace('/\D+/', '', $number) ?? '';
        if ($n === '') {
            return '00000';
        }

        return str_pad(substr($n, 0, 5), 5, '0', STR_PAD_LEFT);
    }

    /**
     * Tous les textes publics concaténés (contrôle anti-jargon).
     */
    public static function publicCorpus(): string
    {
        $bits = [];
        foreach (self::all() as $row) {
            $bits[] = (string) ($row['title'] ?? '');
            $bits[] = (string) ($row['activity'] ?? '');
            $bits[] = (string) ($row['from'] ?? '');
            $bits[] = (string) ($row['to'] ?? '');
            $bits[] = (string) ($row['category'] ?? '');
            $bits[] = (string) ($row['size'] ?? '');
            $bits[] = (string) ($row['search'] ?? '');
            foreach ($row['notes'] ?? [] as $note) {
                $bits[] = (string) $note;
            }
            foreach ($row['sections'] ?? [] as $section) {
                $bits[] = (string) ($section['title'] ?? '');
                foreach ($section['items'] ?? [] as $item) {
                    $bits[] = (string) ($item['text'] ?? '');
                }
            }
        }

        return strtolower(implode("\n", $bits));
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    private static function resolve(array $raw): array
    {
        $kind = (string) $raw['kind'];
        $number = (int) $raw['number'];
        $pad = self::padNumber((string) $number);
        $label = match ($kind) {
            self::KIND_TECHREP => 'TECHREP',
            self::KIND_UPDATE => 'UPDATE',
            default => 'SPOTREP',
        };
        $title = (string) ($raw['title'] ?? ($label . ' #' . $pad));
        $activity = (string) ($raw['activity'] ?? '');
        $searchBits = [$title, $activity, (string) ($raw['to'] ?? '')];
        foreach ($raw['notes'] ?? [] as $note) {
            $searchBits[] = (string) $note;
        }
        foreach ($raw['sections'] ?? [] as $section) {
            $searchBits[] = (string) ($section['title'] ?? '');
            foreach ($section['items'] ?? [] as $item) {
                $searchBits[] = (string) ($item['text'] ?? '');
            }
        }

        $companionKind = (string) ($raw['companion_kind'] ?? '');
        $companionNumber = (int) ($raw['companion_number'] ?? 0);

        return [
            'id' => $kind . '-' . $pad,
            'kind' => $kind,
            'kind_label' => $label,
            'number' => $number,
            'number_pad' => $pad,
            'heading' => $label . ' #' . $pad,
            'date' => (string) $raw['date'],
            'date_label' => self::formatDate((string) $raw['date']),
            'reported_on' => self::formatReported((string) $raw['date']),
            'reporter' => (string) ($raw['reporter'] ?? 'Athena Operations'),
            'from' => (string) ($raw['from'] ?? 'État-major COMSPEC'),
            'to' => (string) ($raw['to'] ?? 'Communautés Athena'),
            'category' => (string) ($raw['category'] ?? 'Opérations'),
            'activity' => $activity,
            'size' => (string) ($raw['size'] ?? ''),
            'title' => $title,
            'notes' => array_values($raw['notes'] ?? []),
            'sections' => array_values($raw['sections'] ?? []),
            'companion_kind' => $companionKind,
            'companion_number' => $companionNumber,
            'companion_href' => ($companionKind !== '' && $companionNumber > 0)
                ? self::href($companionKind, $companionNumber)
                : '',
            'companion_label' => $companionKind === self::KIND_TECHREP
                ? ('TECHREP #' . self::padNumber((string) $companionNumber))
                : ($companionKind === self::KIND_SPOTREP
                    ? ('SPOTREP #' . self::padNumber((string) $companionNumber))
                    : ''),
            'featured' => !empty($raw['featured']),
            'href' => self::href($kind, $number),
            'filter_groups' => array_values($raw['groups'] ?? ['atak']),
            'year' => (int) substr((string) $raw['date'], 0, 4),
            'search' => strtolower(implode(' ', $searchBits)),
        ];
    }

    private static function formatDate(string $iso): string
    {
        $ts = strtotime($iso);

        return $ts ? date('d/m/Y', $ts) : $iso;
    }

    private static function formatReported(string $iso): string
    {
        $months = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
        ];
        $ts = strtotime($iso);
        if (!$ts) {
            return $iso;
        }
        $m = (int) date('n', $ts);

        return 'on ' . ($months[$m] ?? date('F', $ts)) . ' ' . date('j, Y', $ts);
    }

    /**
     * @param list<string> $added
     * @param list<string> $tweaked
     * @param list<string> $fixed
     * @param list<string> $changed
     * @return list<array{title: string, items: list<array{verb: string, text: string}>}>
     */
    private static function changelog(string $title, array $added = [], array $tweaked = [], array $fixed = [], array $changed = []): array
    {
        $items = [];
        foreach ($added as $text) {
            $items[] = ['verb' => 'Added', 'text' => $text];
        }
        foreach ($tweaked as $text) {
            $items[] = ['verb' => 'Tweaked', 'text' => $text];
        }
        foreach ($fixed as $text) {
            $items[] = ['verb' => 'Fixed', 'text' => $text];
        }
        foreach ($changed as $text) {
            $items[] = ['verb' => 'Changed', 'text' => $text];
        }

        return [['title' => $title, 'items' => $items]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function raw(): array
    {
        $pr = static function (
            int $n,
            string $date,
            string $title,
            string $activity,
            array $added = [],
            array $tweaked = [],
            array $fixed = [],
            array $groups = ['atak'],
            array $notes = [],
            string $size = 'Vague 2026.08c'
        ): array {
            return [
                'kind' => self::KIND_UPDATE,
                'number' => $n,
                'date' => $date,
                'from' => 'État-major COMSPEC',
                'to' => 'Communautés Athena, opérateurs, commandement',
                'category' => 'Mise à jour',
                'activity' => $activity,
                'size' => $size,
                'title' => $title,
                'groups' => $groups,
                'notes' => $notes !== [] ? $notes : [
                    'Mise à jour publiée avec la vague d’août 2026. Relancer Arma complètement après un nouveau pack jeu.',
                ],
                'sections' => self::changelog('CHANGELOG', $added, $tweaked, $fixed),
            ];
        };

        return array_merge([
            [
                'kind' => self::KIND_SPOTREP,
                'number' => 2,
                'date' => '2026-08-24',
                'from' => 'État-major COMSPEC',
                'to' => 'Communautés Athena, opérateurs ATAK, cellule S1, Zeus',
                'category' => 'Carte et doctrine',
                'activity' => 'Relief lisible, carte des rôles, parc de terminaux, comptes rendus',
                'size' => 'Portail 1.5.38',
                'title' => 'Le relief se dessine, les rôles se lisent',
                'featured' => true,
                'companion_kind' => self::KIND_TECHREP,
                'companion_number' => 2,
                'groups' => ['atak', 'command', 'personnel'],
                'notes' => [
                    'Le relevé de relief autour de l’équipe n’est plus un calque vide : l’ombrage et les courbes n’apparaissent que là où le sol a été relevé, et s’étendent au fil des déplacements.',
                    'La carte des rôles n’est plus un nuage de points : elle montre qui relève de qui.',
                    'Pas besoin d’un nouveau pack jeu pour le relief déjà relevé ni pour la carte des rôles.',
                    'Le TOC peut tracer un itinéraire ou une visée : le profil du sol et le masque du relief s’affichent là où le relevé existe.',
                ],
                'sections' => [
                    [
                        'title' => 'CARTE ATAK',
                        'items' => [
                            ['verb' => 'Added', 'text' => 'Ombrage du relief (lumière du nord-ouest) superposé à la carte satellitaire'],
                            ['verb' => 'Added', 'text' => 'Courbes de niveau 10 m et 50 m, affichables séparément'],
                            ['verb' => 'Added', 'text' => 'Couche des pentes pour les véhicules (praticable à critique)'],
                            ['verb' => 'Added', 'text' => 'Altitude du sol au survol (SOL 387 m) et couverture du relevé en pourcentage'],
                            ['verb' => 'Tweaked', 'text' => 'La carte se charge plus vite : elle n’attend plus tout le relief d’un coup'],
                            ['verb' => 'Fixed', 'text' => 'Un relevé de 4 km autour de l’équipe suffit pour voir le relief, sans attendre toute la carte'],
                            ['verb' => 'Added', 'text' => 'Analyse d’itinéraire : profil du sol, distance, montée et descente'],
                            ['verb' => 'Added', 'text' => 'Visée JTAC : observateur vers cible, verdict dégagé ou masqué par le relief'],
                        ],
                    ],
                    [
                        'title' => 'POSTE DE COMMANDEMENT',
                        'items' => [
                            ['verb' => 'Added', 'text' => 'Carte des rôles en organigramme (cartes, niveaux, flèches selon la nature du lien)'],
                            ['verb' => 'Added', 'text' => 'Comptes rendus d’après-action : modèles sur mesure (question courte, liste, cases, texte libre)'],
                            ['verb' => 'Fixed', 'text' => 'Parc de terminaux : plus de décalage de deux heures sur la dernière activité'],
                            ['verb' => 'Fixed', 'text' => 'Terminaux en double (même opérateur, deux versions) regroupés en une fiche'],
                            ['verb' => 'Fixed', 'text' => 'Édition des rapports d’après-action standard : les champs restent visibles'],
                        ],
                    ],
                ],
            ],
            [
                'kind' => self::KIND_TECHREP,
                'number' => 2,
                'date' => '2026-08-24',
                'from' => 'Commissaire outils',
                'to' => 'Intégrateurs Athena, responsables de pack',
                'category' => 'Outils',
                'activity' => 'Relief de théâtre, carte des rôles, parc de terminaux',
                'size' => 'Portail 1.5.38',
                'title' => 'Outils — relief, organigramme, parc',
                'companion_kind' => self::KIND_SPOTREP,
                'companion_number' => 2,
                'groups' => ['atak', 'platform', 'personnel'],
                'notes' => [
                    'Le pack jeu Overwatch déjà en circulation (relevé autour de l’équipe) reste valable. Pas besoin d’un nouveau pack pour voir l’ombrage.',
                    'Les communautés historiques conservent leurs relevés : dès qu’un îlot de sol existe, le calque s’affiche.',
                ],
                'sections' => [
                    [
                        'title' => 'PORTAIL',
                        'items' => [
                            ['verb' => 'Added', 'text' => 'Le serveur dessine l’ombrage et les courbes une fois, puis la carte les affiche'],
                            ['verb' => 'Added', 'text' => 'Les relevés successifs se fusionnent : plusieurs équipes couvrent progressivement le théâtre'],
                            ['verb' => 'Added', 'text' => 'Organigramme des rôles à partir des relations de commandement et de tutorat'],
                            ['verb' => 'Added', 'text' => 'Journal public des bulletins (SPOTREP, TECHREP, une fiche par mise à jour)'],
                            ['verb' => 'Fixed', 'text' => 'Heure de dernière activité des terminaux alignée sur l’heure de Paris'],
                            ['verb' => 'Fixed', 'text' => 'Fiches jumelles du même appareil fusionnées dans le parc'],
                        ],
                    ],
                    [
                        'title' => 'SESSION ARMA',
                        'items' => [
                            ['verb' => 'Tweaked', 'text' => 'Le relevé autour de l’équipe (quatre kilomètres, pas de cinquante mètres) reste le mode par défaut'],
                            ['verb' => 'Added', 'text' => 'Chaque position emporte l’altitude du sol sous l’opérateur, distincte de sa propre hauteur'],
                        ],
                    ],
                ],
            ],
            [
                'kind' => self::KIND_SPOTREP,
                'number' => 1,
                'date' => '2026-08-24',
                'from' => 'État-major COMSPEC',
                'to' => 'Communautés Athena, opérateurs ATAK, Zeus',
                'category' => 'Opérations',
                'activity' => 'Vague 2026.08c — poste de situation, planification, Overwatch 1.4.63',
                'size' => 'Portail 1.5.30 · Overwatch 1.4.63 · liaison 2.0.12 · Athena ATAK 1.0.45',
                'title' => 'Poste de situation, planification et pack 1.4.63',
                'companion_kind' => self::KIND_TECHREP,
                'companion_number' => 1,
                'groups' => ['atak', 'command', 'intel'],
                'notes' => [
                    'Période couverte : 17–24 août 2026.',
                    'Le pack jeu à charger est UptoDate / COMSPEC Overwatch. Une relance complète d’Arma (pas seulement la mission) est nécessaire après mise à jour.',
                    'En cas de souci : signalement depuis Échap → gestion du mod, ou le canal habituel de la communauté.',
                ],
                'sections' => [
                    [
                        'title' => 'PORTAIL ATHENA',
                        'items' => [
                            ['verb' => 'Added', 'text' => 'Poste de situation — vue des dossiers SSE avec au moins une identité, et pose / arrêt d’une localisation de téléphone depuis le back-office'],
                            ['verb' => 'Added', 'text' => 'Planification de mission — organisation de combat, affectations, documents d’ordre, paquet exportable, lecture sur la carte ATAK'],
                            ['verb' => 'Added', 'text' => 'Fiches de renseignement simplifiées (plein écran, thèmes, pièces, suivi bureau)'],
                            ['verb' => 'Added', 'text' => 'Rapports de théâtre (prise de contact, opérateur à terre, BDA, FRAGO, SALUTE)'],
                            ['verb' => 'Added', 'text' => 'Déclenchement d’une charge posée depuis le poste de commandement (double confirmation, sans toucher à la minuterie)'],
                            ['verb' => 'Added', 'text' => 'Modèles de comptes rendus d’après-action (champs libres métier)'],
                            ['verb' => 'Tweaked', 'text' => 'Parc de terminaux — retrait d’appareils et de sessions web, colonne d’actions toujours visible'],
                            ['verb' => 'Tweaked', 'text' => 'Relecture de mission — joueurs, IA alliées, téléphones et balises GPS dans la timeline et le PDF'],
                            ['verb' => 'Tweaked', 'text' => 'Barre d’outils et bloc-notes ATAK web plus aérés'],
                            ['verb' => 'Fixed', 'text' => 'Connexion téléphone / terminal Android et photos de reconnaissance'],
                            ['verb' => 'Fixed', 'text' => 'Export PDF des dossiers (lisibilité, marges, pièces)'],
                            ['verb' => 'Fixed', 'text' => 'Colonne de relief de théâtre sur certaines bases'],
                            ['verb' => 'Fixed', 'text' => 'Journal produit public et écran de connexion en deux volets'],
                        ],
                    ],
                    [
                        'title' => 'ATAK WEB',
                        'items' => [
                            ['verb' => 'Added', 'text' => 'Panneau des fiches de renseignement dans la vue carte'],
                            ['verb' => 'Added', 'text' => 'Alerte de proximité des téléphones sous localisation'],
                            ['verb' => 'Added', 'text' => 'Traces colorées, perte de liaison et prévision de déplacement plus lisibles'],
                            ['verb' => 'Added', 'text' => 'Journal d’erreurs remonté depuis le jeu (lisible, pas une page technique)'],
                            ['verb' => 'Tweaked', 'text' => 'Overlays de liaison (signal faible, perte, écran cassé) alignés sur le roleplay'],
                            ['verb' => 'Fixed', 'text' => 'Coupures temporaires du poste de commandement'],
                            ['verb' => 'Fixed', 'text' => 'Équipes de feu bloquées (alerte tactique non reconnue)'],
                        ],
                    ],
                    [
                        'title' => 'OVERWATCH (JEU)',
                        'items' => [
                            ['verb' => 'Added', 'text' => 'Relevé du relief autour de l’équipe (sol de la carte Arma), plus toute la carte d’un coup'],
                            ['verb' => 'Added', 'text' => 'Localisation de téléphone reçue depuis le poste de situation'],
                            ['verb' => 'Added', 'text' => 'Ordres de déplacement pour IA alliée depuis ATAK'],
                            ['verb' => 'Added', 'text' => 'Panneau Zeus — éditer SSE / ATAK / Overwatch sur la sélection'],
                            ['verb' => 'Added', 'text' => 'Couper le suivi téléphone / GPS depuis ACE'],
                            ['verb' => 'Tweaked', 'text' => 'Fenêtre unique au menu principal (conditions + accès anticipé), plus de défilé en mission'],
                            ['verb' => 'Tweaked', 'text' => 'Photos ATAK : un cliché, plus de second qui gelait le jeu'],
                            ['verb' => 'Tweaked', 'text' => 'Moins de bips répétés'],
                            ['verb' => 'Fixed', 'text' => 'Doublon d’ouverture de note Athena'],
                            ['verb' => 'Fixed', 'text' => 'Suivi GPS, IA alliée et pastilles trop grandes sur la carte web'],
                            ['verb' => 'Fixed', 'text' => 'Overlay « sans couverture » qui recouvrait tout l’écran Zeus'],
                        ],
                    ],
                    [
                        'title' => 'SSE',
                        'items' => [
                            ['verb' => 'Added', 'text' => 'Atelier de modèles de mission Arma'],
                            ['verb' => 'Added', 'text' => 'Exploitation Zeus en direct et file à exploiter'],
                            ['verb' => 'Tweaked', 'text' => 'Fiches personnes, biométrie simulée et exploitation numérique plus stables à la transmission'],
                            ['verb' => 'Fixed', 'text' => 'Libellés et identifiants affichés de façon illisible sur certaines fiches'],
                            ['verb' => 'Fixed', 'text' => 'Photos visage introuvables selon le dossier d’images du profil'],
                        ],
                    ],
                ],
            ],
            [
                'kind' => self::KIND_TECHREP,
                'number' => 1,
                'date' => '2026-08-24',
                'from' => 'Commissaire outils',
                'to' => 'Intégrateurs Athena, responsables de pack',
                'category' => 'Outils',
                'activity' => 'Semaine 17–24 août 2026 — portail, Overwatch, liaison',
                'size' => 'Portail 1.5.30 · Overwatch 1.4.63 · Athena ATAK 1.0.45 · liaison 2.0.12',
                'title' => 'Outils — pack 1.4.63 et poste de situation',
                'companion_kind' => self::KIND_SPOTREP,
                'companion_number' => 1,
                'groups' => ['platform', 'atak'],
                'notes' => [
                    'Reconstruire le pack avec l’atelier habituel. Ne pas copier un stub de liaison à la place du binaire de production.',
                    'Le calque de relief de la carte web est le sol de la carte Arma, pas la hauteur de l’opérateur.',
                    'Les communautés déjà en place n’ont rien à reconfigurer de force.',
                ],
                'sections' => [
                    [
                        'title' => 'ATHENA',
                        'items' => [
                            ['verb' => 'Added', 'text' => 'Poste de situation et localisation de téléphone depuis le back-office'],
                            ['verb' => 'Added', 'text' => 'Planification de mission, overlay carte, lecture en session'],
                            ['verb' => 'Added', 'text' => 'Fiches de renseignement, rapports de théâtre, déclenchement de charge au poste'],
                            ['verb' => 'Added', 'text' => 'Relecture élargie (joueurs, IA alliées, téléphones, balises)'],
                            ['verb' => 'Added', 'text' => 'Alerte de proximité des téléphones sur la carte web'],
                            ['verb' => 'Tweaked', 'text' => 'Retrait d’un terminal sans casser les certificats restants'],
                            ['verb' => 'Fixed', 'text' => 'Relief illisible sur certaines bases historiques'],
                            ['verb' => 'Fixed', 'text' => 'Équipes de feu bloquées'],
                            ['verb' => 'Fixed', 'text' => 'Rafales de refus quand la carte interroge trop souvent le poste'],
                            ['verb' => 'Changed', 'text' => 'Journal produit public (cette page)'],
                        ],
                    ],
                    [
                        'title' => 'OVERWATCH',
                        'items' => [
                            ['verb' => 'Added', 'text' => 'Relevé du sol autour de l’équipe, interruption si la liaison est refusée'],
                            ['verb' => 'Added', 'text' => 'Altitude du sol sous l’opérateur, en plus de sa propre hauteur'],
                            ['verb' => 'Added', 'text' => 'Localisation de téléphone, ordres de déplacement IA, panneaux Zeus, coupure de suivi ACE'],
                            ['verb' => 'Tweaked', 'text' => 'Moins de bips, overlays de liaison, fenêtre d’accès au menu principal'],
                            ['verb' => 'Fixed', 'text' => 'Photo ATAK qui gelait, note ouverte en double, overlay Zeus trop envahissant'],
                            ['verb' => 'Changed', 'text' => 'Overwatch 1.4.63 · Athena ATAK 1.0.45'],
                        ],
                    ],
                    [
                        'title' => 'LIAISON',
                        'items' => [
                            ['verb' => 'Added', 'text' => 'Le relevé de relief est bien accusé dès l’envoi ; un refus d’accès arrête la rafale'],
                            ['verb' => 'Changed', 'text' => 'Liaison 2.0.12'],
                        ],
                    ],
                    [
                        'title' => 'SSE',
                        'items' => [
                            ['verb' => 'Added', 'text' => 'Atelier de modèles de mission, exploitation Zeus, file à traiter'],
                            ['verb' => 'Fixed', 'text' => 'Transmissions personne / biométrie, terminal Android, photos, chemises PDF'],
                        ],
                    ],
                ],
            ],
        ], [
            $pr(212, '2026-08-26', 'IA alliées distinctes sur la carte', 'Chaque IA garde sa pastille : plus de regroupement sous le même indicatif', [
                'Les unités alliées sont suivies une par une, même dans le même groupe',
            ], [
                'L’indicatif affiché reste lisible ; il ne sert plus de clé de suivi',
            ], [
                'Plusieurs IA du même groupe se superposaient, et l’indicatif de l’opérateur pouvait écraser une IA',
            ], ['atak'], [
                'Rechargez le pack jeu Overwatch 1.4.74, puis relancez Arma complètement — pas seulement la mission.',
            ], 'Portail 1.5.48 · Overwatch 1.4.74'),
            $pr(211, '2026-08-24', 'Relevé de relief une fois Athena liée', 'Le sol n’est plus relevé tant que le compte n’est pas lié au portail', [
                'Message clair si le relevé est demandé avant la liaison Athena',
            ], [
                'Le relevé attend que le compte soit lié, au lieu de partir trop tôt',
            ], [
                'Des blocs de relief partaient alors qu’Athena n’était pas encore liée : le poste les refusait, le journal Overwatch se remplissait',
            ], ['atak'], [
                'Reliez Athena (Steam en multijoueur, ou le code fourni par votre administrateur), rechargez le pack jeu Overwatch 1.4.74, puis relancez Arma complètement — pas seulement la mission.',
            ], 'Portail 1.5.48 · Overwatch 1.4.74'),
            $pr(210, '2026-08-24', 'File SSE, carte et fenêtre de mise à jour', 'La file à exploiter s’ouvre, Traces ne recouvre plus l’état de carte, la fenêtre de mise à jour est centrée', [
                'Type de support toujours nommé en français dans la file d’exploitation',
                'Légende Traces empilée au-dessus de Grille / Échelle / Contacts / Réseau',
                'Fenêtre de mise à jour centrée, claire sur le portail et sombre sur ATAK',
            ], [
                'Appui aérien : un aéronef occupé apparaît même sans déclaration de vol',
                'Charges : tout déclencher ne concerne que vos charges ATAK en jeu, et le mode ATAK au poste',
            ], [
                'La file « À exploiter » tombait en erreur dès qu’un type de support manquait',
                'Traces recouvrait le bandeau grille et réseau en bas à droite',
            ], ['atak', 'intel', 'platform'], [
                'Mise à jour du portail. Pour l’appui aérien automatique et le mode de charge ATAK, recharger le pack jeu Overwatch 1.4.73, puis relancer Arma complètement.',
            ], 'Portail 1.5.47 · Overwatch 1.4.73'),
            $pr(209, '2026-08-24', 'Profil d’itinéraire et visée JTAC', 'Le TOC trace un axe ou une visée : le sol, la montée et le masque du relief se lisent sur la carte', [
                'Analyse d’itinéraire : profil du sol, distance, montée et descente',
                'Visée observateur vers cible : verdict dégagé ou masqué par le relief',
            ], [], [], ['atak'], [
                'Le relevé déjà présent autour de l’équipe suffit. Un tronçon hors zone relevée s’affiche clairement comme non couvert. Pas besoin d’un nouveau pack jeu.',
            ], 'Portail 1.5.46'),
            $pr(208, '2026-08-24', 'Appui aérien sans déclaration de vol', 'Les aéronefs occupés apparaissent au poste même si personne n’a déclaré le vol', [
                'Un hélicoptère ou un avion avec équipage se lit dans Appui aérien et sur la carte',
            ], [
                'La déclaration de vol depuis Overwatch (touche K) reste possible pour ajouter indicatif, passagers et mission',
            ], [
                'La liste restait vide tant qu’un pilote n’avait pas déclaré le vol, alors que l’appareil était déjà en l’air',
            ], ['atak'], [
                'Recharger le pack jeu pour que les aéronefs occupés remontent tout seuls. Le poste affiche déjà les appareils déjà visibles sur la carte.',
            ], 'Portail 1.5.47 · Overwatch 1.4.73'),
            $pr(207, '2026-08-24', 'Symboles d’unité selon le type', 'Chars, aviation et artillerie ne s’affichent plus comme de l’infanterie', [
                'Sur la carte et dans la liste, le symbole suit le type : à pied, blindé, véhicule, artillerie, hélicoptère, avion, drone',
            ], [], [
                'Toutes les positions alliées s’affichaient avec le rectangle d’infanterie',
            ], ['atak'], [
                'Recharger le pack jeu pour distinguer chars, véhicules de combat et artillerie. Sans pack à jour, l’air et le sol se distinguent déjà ; un véhicule terrestre inconnu n’est plus dessiné comme un fantassin.',
            ], 'Portail 1.5.43 · Overwatch 1.4.70'),
            $pr(206, '2026-08-24', 'Charges uniquement depuis ATAK', 'Une charge peut n’être tirée que depuis la tablette et le poste, plus depuis le déclencheur porté', [
                'Choix « Uniquement depuis ATAK » au moment d’armer la charge (tablette en jeu et poste de commandement)',
                'Tout déclencher d’un coup, avec la même double confirmation qu’une charge seule',
            ], [], [], ['atak', 'command'], [
                'Le déclencheur porté ne fait plus sauter une charge passée dans ce mode. Une minuterie déjà lancée continue de compter. Seules vos propres charges ATAK partent d’un coup en jeu ; au poste, ce sont toutes les charges armées dans ce mode.',
                'Recharger le pack jeu Overwatch 1.4.73, puis relancer Arma complètement.',
            ], 'Portail 1.5.47 · Overwatch 1.4.73'),
            $pr(205, '2026-08-24', 'Indicatif des IA alliées', 'Le chef de mission choisit l’indicatif d’une IA posée sur ATAK, au lieu d’un numéro automatique', [
                'Champ Indicatif en préparation de mission et sous Zeus quand une IA est mise sur la carte',
            ], [
                'Sans indicatif saisi : le nom du groupe, ou le nom de l’IA, apparaît à la place du numéro automatique',
            ], [
                'Les IA déjà suivies affichent désormais un nom lisible dans la liste et sur la fiche, même sans nouveau réglage',
            ], ['atak'], [
                'Recharger le pack jeu pour poser ou modifier l’indicatif depuis l’éditeur ou Zeus. La carte web affiche déjà un nom lisible pour les IA déjà en place.',
            ], 'Portail 1.5.41 · Overwatch 1.4.71'),
            $pr(204, '2026-08-24', 'Fenêtre de mise à jour du portail', 'Quand le portail a changé, l’invitation à actualiser s’ouvre au milieu de l’écran', [
                'Fenêtre centrée pour actualiser le portail, ou le faire plus tard',
            ], [], [
                'L’invitation n’est plus un bandeau coincé en bas de l’écran',
            ], ['platform'], [
                'Mise à jour du portail uniquement : rien à recharger en session.',
            ], 'Portail 1.5.40'),
            $pr(203, '2026-08-24', 'Journal SPOTREP et TECHREP', 'Chaque mise à jour a désormais son bulletin public', [
                'Journal d’opérations sur Nouveautés, dans l’esprit des rapports Arma',
                'Un bulletin par mise à jour de la vague d’août',
            ], [], [], ['platform'], [
                'Les textes publics restent en langage métier : ce qui change en session et au poste, sans vocabulaire d’atelier.',
            ], 'Portail 1.5.38'),
            $pr(202, '2026-08-24', 'Ombrage et courbes de niveau', 'Le relief relevé autour de l’équipe se lit enfin sur la carte', [
                'Ombrage du sol, courbes 10 m et 50 m, couche des pentes',
                'Altitude au survol et progression du relevé',
            ], [], [
                'Un relevé de quelques kilomètres autour de l’équipe suffit : plus besoin d’attendre toute la carte',
            ], ['atak'], [
                'Pas besoin d’un nouveau pack jeu si le relevé autour de l’équipe est déjà en place.',
            ], 'Portail 1.5.37'),
            $pr(201, '2026-08-24', 'Carte des rôles en organigramme', 'Qui relève de qui se lit d’un coup d’œil', [
                'Organigramme des rôles (niveaux, cartes, nature des liens)',
            ], [], [], ['personnel'], [
                'Mise à jour du portail uniquement : rien à recharger en session.',
            ], 'Portail 1.5.36'),
            $pr(200, '2026-08-24', 'Parc de terminaux : heure et doublons', 'La dernière activité et les fiches jumelles sont enfin justes', [], [], [
                'Décalage de deux heures sur la dernière activité',
                'Deux fiches pour le même opérateur regroupées en une',
            ], ['atak', 'platform'], [
                'Mise à jour du portail. Les appareils déjà liés n’ont pas à être réassociés.',
            ], 'Portail 1.5.35'),
            $pr(199, '2026-08-24', 'Modèles de comptes rendus sur mesure', 'Chaque communauté rédige son après-action avec ses propres questions', [
                'Modèles de comptes rendus : question courte, liste, cases, texte libre',
            ], [], [
                'Les champs des rapports standard restent visibles à l’édition',
            ], ['command'], [
                'Mise à jour du portail. Les modèles déjà en place restent utilisables.',
            ], 'Portail 1.5.34'),
            $pr(198, '2026-08-24', 'Poste de situation, relief, pack 1.4.63', 'Poste de situation et relevé de sol autour de l’équipe', [
                'Poste de situation : dossiers avec identité et localisation de téléphone',
                'Relevé du relief autour de l’équipe plutôt que toute la carte',
                'Relecture : joueurs, IA alliées, téléphones et balises',
                'Pack Overwatch 1.4.63',
            ], [], [], ['atak', 'command', 'intel']),
            $pr(197, '2026-08-24', 'Planification de mission sur la carte', 'Le même ordre se prépare au bureau et se lit sur ATAK', [
                'Organisation de combat, affectations, documents d’ordre',
                'Paquet mission exportable et lecture sur la carte',
            ], [], [], ['command', 'atak']),
            $pr(196, '2026-08-24', 'Barre d’outils et bloc-notes', 'La carte ATAK web respire davantage', [], [
                'Barre d’outils et bloc-notes plus aérés',
            ], [], ['atak']),
            $pr(195, '2026-08-24', 'Relief lisible sur toutes les communautés', 'Le calque de relief s’affiche aussi sur les bases déjà en place', [], [], [
                'Lecture du relief cassée sur certaines communautés historiques',
            ], ['atak']),
            $pr(194, '2026-08-24', 'Exploitation Zeus et file à traiter', 'Le renseignement pris en session rejoint plus vite le bureau', [
                'Exploitation Zeus en direct',
                'File des éléments à traiter',
            ], [], [
                'Transmissions de fiches plus stables',
            ], ['intel', 'atak']),
            $pr(193, '2026-08-24', 'Journal produit, connexion, Overwatch', 'Nouveautés publiques, écran de connexion, pack jeu', [
                'Journal des nouveautés sur le site',
            ], [
                'Connexion en deux volets',
                'Fenêtre d’accès Overwatch au menu principal',
            ], [], ['platform', 'atak']),
            $pr(192, '2026-08-23', 'Atelier de modèles de mission (suite)', 'L’atelier SSE continue de s’étoffer', [], [
                'Parcours de création des modèles plus clair',
            ], [], ['intel']),
            $pr(191, '2026-08-23', 'Photos sans gel, carte plus fluide', 'Cliché unique, moins d’à-coups au poste, GPS et IA', [], [
                'Un seul cliché photo, plus de second qui figeait la session',
                'La carte interroge le poste sans le saturer',
            ], [
                'Suivi GPS et IA alliée',
            ], ['atak']),
            $pr(190, '2026-08-23', 'Atelier de modèles de mission', 'Préparer un modèle de mission Arma depuis le bureau SSE', [
                'Atelier de modèles de mission',
            ], [], [], ['intel']),
            $pr(189, '2026-08-23', 'Note Athena ouverte une seule fois', 'Fini le doublon de fenêtre de note', [], [], [
                'La note Athena s’ouvrait deux fois',
            ], ['atak']),
            $pr(188, '2026-08-23', 'Déclenchement d’une charge depuis le poste', 'Le TOC peut faire sauter une charge posée, sans toucher à la minuterie', [
                'Déclenchement au poste, double confirmation',
            ], [], [], ['atak', 'command']),
            $pr(187, '2026-08-23', 'Équipes de feu débloquées', 'L’alerte tactique est à nouveau reconnue', [], [], [
                'Les équipes de feu restaient bloquées',
            ], ['atak', 'command']),
            $pr(186, '2026-08-23', 'Fiches, rapports de théâtre, pack jeu', 'Le cycle terrain → bureau s’étoffe', [
                'Rapports de théâtre classés au poste',
            ], [
                'Fiches de renseignement',
            ], [], ['intel', 'atak']),
            $pr(185, '2026-08-19', 'Fiches de renseignement sur la carte', 'Le panneau des fiches s’ouvre sans quitter ATAK web', [
                'Panneau des fiches dans la vue carte',
            ], [], [], ['atak', 'intel']),
            $pr(184, '2026-08-18', 'Fiches de renseignement simplifiées', 'Rédiger plein écran, choisir un thème, transmettre', [
                'Fiches simplifiées, thèmes, pièces, suivi bureau',
            ], [], [], ['intel']),
            $pr(183, '2026-08-18', 'Installation neuve plus simple', 'Les équipes qui posent une instance Athena partent sur une base saine', [], [
                'Parcours d’installation neuve',
            ], [], ['platform']),
            $pr(182, '2026-08-17', 'Terminal Android, photos, chemises PDF', 'Liaison téléphone, reconnaissance et dossiers papier', [
                'Photos de reconnaissance plus fiables',
                'Chemises PDF des dossiers plus lisibles',
            ], [], [
                'Connexion du terminal Android',
            ], ['atak', 'intel']),
        ]);
    }
}
