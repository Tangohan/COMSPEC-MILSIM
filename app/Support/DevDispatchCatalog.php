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
                'number' => 3,
                'date' => '2026-09-01',
                'from' => 'État-major COMSPEC',
                'to' => 'Communautés Athena, opérateurs ATAK, cellule S1, Zeus, commandement',
                'category' => 'Opérations et poste',
                'activity' => 'Dossier de mission, tablette lisible, bureau plus clair',
                'size' => 'Overwatch 1.4.97 · Athena 1.0.58',
                'title' => 'Le plan se publie, la tablette se lit, le bureau s’aère',
                'featured' => true,
                'companion_kind' => self::KIND_TECHREP,
                'companion_number' => 3,
                'groups' => ['atak', 'command', 'personnel'],
                'notes' => [
                    'Le commandement prépare plan, renseignement et ordres dans un même espace : les opérateurs ne voient que ce qui a été publié.',
                    'Sur la tablette ATAK Enhanced, la carte se lit en charbon et cyan : grille, distance, cap et unité suivie, sans recouvrir le tiroir d’applications.',
                    'Au lancement, Overwatch demande le compte Athena. Rien n’est transmis tant que l’environnement n’est pas prêt.',
                    'Rechargez le pack jeu, puis relancez Arma complètement (pas seulement la mission).',
                ],
                'sections' => [
                    [
                        'title' => 'CARTE TACTIQUE',
                        'items' => [
                            ['verb' => 'Added', 'text' => 'Tablette IceMan : fond charbon, chiffres cyan, cartouches curseur et unité suivie sur la carte'],
                            ['verb' => 'Added', 'text' => 'Cap en degrés vrais et zoom plus / moins sur le bord de la carte'],
                            ['verb' => 'Tweaked', 'text' => 'Le tiroir d’applications, Drone Ops et les fenêtres caméra déjà présentes reprennent la même lecture'],
                            ['verb' => 'Added', 'text' => 'Rapports du théâtre en pastilles compactes sur la carte du poste, comme en mission'],
                            ['verb' => 'Added', 'text' => 'Outil Route : itinéraire transmis aux opérateurs, points atteints en gris'],
                            ['verb' => 'Fixed', 'text' => 'La barre Position / Annoter / Tracer reste visible ; seul Masquer la replie'],
                        ],
                    ],
                    [
                        'title' => 'OVERWATCH',
                        'items' => [
                            ['verb' => 'Added', 'text' => 'Connexion Athena au menu : e-mail, code temporaire, ou Steam déjà associé'],
                            ['verb' => 'Changed', 'text' => 'La communauté n’est plus saisie dans le jeu : Athena la choisit d’après le compte'],
                            ['verb' => 'Fixed', 'text' => 'La tablette ATAK Enhanced se charge à nouveau'],
                            ['verb' => 'Fixed', 'text' => 'Zeus : SSE, ATAK et OVERWATCH en haut du panneau d’une unité, plus sur les filtres'],
                            ['verb' => 'Fixed', 'text' => 'Arsenal : bandeau des tenues Athena en haut, sans masquer Mes équipements'],
                        ],
                    ],
                    [
                        'title' => 'EFFECTIFS ET ÉQUIPEMENT',
                        'items' => [
                            ['verb' => 'Added', 'text' => 'Espace opération : synthèse, plan, objectifs et ordres rattachés à une même mission'],
                            ['verb' => 'Added', 'text' => 'Collections de tenues, photos de présentation, tenues envoyées depuis l’arsenal'],
                            ['verb' => 'Added', 'text' => 'Dépôt des pièces RH dans le coffre du dossier'],
                            ['verb' => 'Added', 'text' => 'Charte des formations dans l’espace compte : parcours, puis confirmation'],
                            ['verb' => 'Tweaked', 'text' => 'Grade attribué et portrait opérateur dans les effectifs ; page des rôles plus aérée'],
                            ['verb' => 'Tweaked', 'text' => 'Fiches jumelles, dossier public et correction de fiche se lisent comme le reste du bureau'],
                        ],
                    ],
                ],
            ],
            [
                'kind' => self::KIND_TECHREP,
                'number' => 3,
                'date' => '2026-09-01',
                'from' => 'Commissaire outils',
                'to' => 'Intégrateurs Athena, responsables de pack',
                'category' => 'Outils',
                'activity' => 'Pack 1.4.97, tablette IceMan, espace opération, bureau effectifs',
                'size' => 'Overwatch 1.4.97 · Athena 1.0.58',
                'title' => 'Outils — tablette lisible et dossier de mission',
                'companion_kind' => self::KIND_SPOTREP,
                'companion_number' => 3,
                'groups' => ['atak', 'platform', 'personnel'],
                'notes' => [
                    'Charger le pack Overwatch 1.4.97, puis relancer Arma complètement. La tablette IceMan affiche les cartouches sur la carte, jamais sur le tiroir.',
                    'La connexion Athena précède toute transmission. Steam exige un poste déjà associé, pas seulement l’identifiant.',
                    'Les communautés déjà en place n’ont rien à reconfigurer de force pour l’espace opération, les collections de tenues ou le coffre RH.',
                ],
                'sections' => [
                    [
                        'title' => 'SESSION ARMA',
                        'items' => [
                            ['verb' => 'Added', 'text' => 'Lecture IceMan : grille, distance, altitude, gisement, portée, unité suivie, cap et zoom'],
                            ['verb' => 'Added', 'text' => 'Identification Athena avant le suivi, la messagerie et le renseignement'],
                            ['verb' => 'Fixed', 'text' => 'Chargement de la tablette, boutons Zeus et bandeau d’arsenal sans recouvrement'],
                        ],
                    ],
                    [
                        'title' => 'PORTAIL',
                        'items' => [
                            ['verb' => 'Added', 'text' => 'Dossier de mission : calques en brouillon, revue, approuvés, puis publiés sur la vue terrain'],
                            ['verb' => 'Added', 'text' => 'Pastilles de rapports, icônes de communauté et barre d’outils de la carte du poste'],
                            ['verb' => 'Added', 'text' => 'Collections d’équipement, coffre de pièces, charte des formations'],
                            ['verb' => 'Tweaked', 'text' => 'Tableau de bord : offres, dossier RH, barre d’identité sous le menu, invitation à actualiser'],
                        ],
                    ],
                ],
            ],
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
                'featured' => false,
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
            $pr(380, '2026-09-02', 'L’aperçu des documents PDF s’affiche à nouveau', 'Dans la bibliothèque, un document PDF s’ouvre de nouveau à l’écran. Si l’aperçu ne peut pas s’afficher, un message propose de le télécharger. Rechargez la page du document', [], [], [
                'L’aperçu d’un PDF publié restait vide',
                'Un échec d’affichage n’affiche plus une page hors ligne à la place du document',
            ], ['platform'], [
                'Ouvrez un document PDF publié dans la bibliothèque : les pages s’affichent, avec page suivante et zoom. Si ce n’est pas le cas, utilisez Télécharger.',
            ], 'Portail 1.6.08'),
            $pr(375, '2026-09-02', 'Les kits d’accès se cochent clairement avant l’enregistrement', 'Sur la page Kits d’accès, chaque carte affiche une case. Une carte retenue passe en Sélectionné et le pied de page indique combien de kits sont cochés. Enregistrez ensuite pour les attribuer aux membres', [], [], [
                'Cliquer une carte ne changeait pas l’affichage : pas de case visible, le pied de page restait sur Aucun kit coché',
            ], ['personnel'], [
                'Ouvrez Effectifs, puis Kits d’accès. Cochez les packs voulus : le compteur se met à jour, puis Enregistrer les kits.',
            ], 'Portail 1.6.03'),
            $pr(374, '2026-09-02', 'Le tableau des effectifs affiche la disponibilité sur 90 jours', 'Sur le tableau rapide du tableau de bord, chaque membre montre son taux de disponibilité : participations annoncées et présences validées sur les trois derniers mois, sous forme de barre colorée. Rechargez le tableau de bord', [
                'Colonne Disponibilité, avec le pourcentage et une barre du rouge au vert',
                'Un membre sans activité récente affiche un tiret, pas un taux à zéro',
            ], [], [], ['personnel'], [
                'Ouvrez le tableau de bord, rubrique Effectifs. Le détail s’affiche en survolant la barre.',
            ], 'Portail 1.6.02'),
            $pr(373, '2026-09-02', 'Les responsables de communauté composent la vitrine de tenues', 'Choisir les tenues du tableau de bord relève de l’administration de l’organisation, pas de celle du site. Les gestionnaires de communauté y accèdent depuis le tableau de bord, sans compte d’administration du site. Rechargez le tableau de bord', [], [], [
                'Les responsables d’organisation ne voyaient pas le lien Choisir les tenues, ou ne pouvaient pas ouvrir la page',
            ], ['platform'], [
                'Connectez-vous avec un compte de gestion de la communauté, ouvrez le tableau de bord, puis Choisir les tenues.',
            ], 'Portail 1.6.01'),
            $pr(372, '2026-09-02', 'Les tenues de la communauté s’affichent comme le catalogue', 'Sur le tableau de bord, les organisateurs choisissent les tenues mises en avant. Un personnage à fond transparent se place devant le fond, comme les cartes de formations. Rechargez le tableau de bord', [
                'Rangée Nos tenues, sur le même modèle que le catalogue des formations',
                'Personnage à fond transparent, avec une photo de fond si vous le souhaitez',
            ], [], [], ['platform'], [
                'Ouvrez le tableau de bord. Pour composer la rangée : Choisir les tenues, puis ajoutez une tenue et le personnage.',
            ], 'Portail 1.6.00'),
            $pr(371, '2026-09-02', 'Les photos de présentation des collections s’enregistrent à nouveau', 'Sur Équipement, la photo d’une collection ou d’une tenue s’enregistre même lorsque le dépôt habituel est indisponible. Rechargez la page Équipement', [], [], [
                'L’envoi d’une photo de présentation échouait avec le message Stockage des photos indisponible pour le moment',
            ], ['platform'], [
                'Ouvrez Équipement, une collection, puis Modifier. Envoyez de nouveau la photo en JPG ou PNG.',
            ], 'Portail 1.5.99'),
            $pr(370, '2026-09-02', 'Des kits d’accès pour choisir qui peut faire quoi', 'Les responsables cochent des packs simples — lecture, modification, recrutement, paramètres — puis les attribuent aux membres. Plusieurs kits se cumulent. Rechargez la page Kits d’accès', [
                'Packs lecture / modification / recrutement / paramètres, multi-sélectionnables',
                'Attribution directe aux membres : les droits s’ajoutent à leurs accès',
            ], [
                'Les listes d’emplois métier ne sont plus filtrées par domaine militaire',
            ], [], ['personnel'], [
                'Ouvrez Effectifs, puis Kits d’accès. Cochez les kits, enregistrez, puis attribuez-les aux membres.',
            ], 'Portail 1.5.98'),
            $pr(369, '2026-09-02', 'L’avancement du dossier ne demande plus un second prénom et nom', 'Sur la fiche, l’avancement du dossier ne signale plus Prénom et nom à compléter lorsque le prénom et le nom du personnage sont déjà renseignés. Rechargez la page du dossier', [], [], [
                'Un second point Prénom et nom restait à compléter alors que l’identité du personnage était déjà remplie',
            ], ['personnel'], [
                'Ouvrez la fiche. Avancement du dossier : un seul point Prénom et nom du personnage, coché si l’identité est renseignée.',
            ], 'Portail 1.5.97'),
            $pr(368, '2026-09-02', 'La barre Vue reste visible sous Outils, Réseau et Journal sont lisibles', 'La barre N, 2D, 3D et Zoom reste à droite, sous Outils, sans passer derrière la carte. En bas, Réseau et Journal s’affichent dans une bande lisible. Rechargez la page du poste', [], [], [
                'La barre de vue passait sous Outils et sous la carte',
                'Réseau et Journal s’affichaient collés, sans présentation',
            ], ['atak'], [
                'Rechargez la page du poste (éventuellement vider le cache du navigateur). La barre de vue doit rester entière à droite, sous Outils. Réseau et Journal forment une bande en bas de la carte.',
            ], 'Portail 1.5.96'),
            $pr(367, '2026-09-02', 'Le poste n’affiche plus comme en liaison un opérateur sans signal', 'Un opérateur bloqué à l’écran de connexion, ou dont le signal n’est plus reçu, n’apparaît plus En liaison. Les anciennes positions et les anciens indicatifs quittent la carte et le relief. Rechargez la page du poste', [], [], [
                'Les fiches Terminaux affichaient En liaison d’après le parc d’appareils, même sans signal récent',
                'La carte et le relief gardaient les dernières positions et d’anciens indicatifs superposés',
            ], ['atak'], [
                'Rechargez la page du poste (éventuellement vider le cache du navigateur). Sans signal récent, la fiche passe Hors liaison et le contact disparaît de la carte.',
            ], 'Portail 1.5.95'),
            $pr(366, '2026-09-02', 'Les fiches terminaux du poste montrent le certificat et permettent de le renouveler', 'Sur l’onglet Terminaux du poste, chaque appareil indique l’état du certificat, sa référence et son échéance. Vous pouvez en émettre un nouveau : l’ancien n’est plus accepté. L’opérateur en liaison le reçoit ensuite', [
                'État, référence et échéance du certificat sur chaque fiche terminal',
                'Bouton pour émettre ou renouveler le certificat depuis le poste',
            ], [], [], ['atak'], [
                'Rechargez la page du poste. Ouvrez l’onglet Terminaux : le certificat apparaît sous les versions. Un compte connecté peut renouveler.',
            ], 'Portail 1.5.94'),
            $pr(365, '2026-09-02', 'Une charge ATAK n’est marquée explosée que lorsqu’elle saute en jeu', 'Quand vous déclenchez une charge réglée sur Uniquement depuis ATAK, elle saute en jeu. Le poste n’écrit A explosé qu’après. Le choix n’apparaît plus deux fois dans le menu ACE', [], [
                'Une seule entrée Uniquement depuis ATAK dans la liste des déclencheurs ACE',
            ], [
                'Le poste indiquait qu’une charge avait explosé alors qu’elle était encore posée en jeu',
                'Le choix Uniquement depuis ATAK apparaissait deux fois dans ACE',
            ], ['atak'], [
                'Rechargez le pack jeu, quittez Arma complètement puis relancez. Dans ACE, Uniquement depuis ATAK n’apparaît qu’une fois. Après un déclenchement depuis la tablette ou le poste, la charge doit sauter en jeu ; le poste passe à A explosé seulement ensuite.',
            ], 'Overwatch 1.5.12'),
            $pr(364, '2026-09-02', 'Les opérateurs redeviennent lisibles sur la carte du poste', 'Sur la carte du poste, chaque opérateur en liaison affiche son indicatif sous le symbole. Les positions restent visibles même si l’affichage tactique met un instant à se charger. Rechargez la page du poste', [
                'Indicatif lisible sous chaque opérateur sur la carte du poste',
            ], [], [
                'Les symboles d’effectif restaient trop petits, sans nom, au milieu du reste de la carte',
            ], ['atak'], [
                'Rechargez la page du poste (éventuellement vider le cache du navigateur). Un opérateur en liaison doit apparaître sur la carte avec son indicatif, comme dans le tableau des effectifs.',
            ], 'Portail 1.5.93'),
            $pr(363, '2026-09-01', 'Un bandeau prévient quand le compte n’est pas connecté', 'Si le compte n’est pas associé, un bandeau « Compte non connecté » s’affiche sur la carte, au-dessus de l’indicatif, et en haut de la tuile Athena. Ouvrez Connexion Athena pour associer le compte', [
                'Bandeau Compte non connecté sur la carte et sur Athena tant que le compte n’est pas associé',
            ], [], [], ['atak'], [
                'Rechargez le pack jeu, quittez Arma complètement puis relancez. Sans compte associé, le bandeau ambre doit apparaître. Une fois en liaison, il disparaît.',
            ], 'Overwatch 1.5.11 · Athena ATAK 1.0.75'),
            $pr(362, '2026-09-01', 'Le rôle se saisit librement dans les paramètres du téléphone', 'Dans Paramètres, le rôle n’est plus une liste fermée : vous tapez ce que vous voulez (Breacher, médecin, chef d’équipe…). Un réglage permet d’afficher l’indicatif seul ou l’indicatif suivi du rôle sur la carte', [
                'Saisie libre du rôle dans Paramètres',
                'Affichage carte : indicatif seul, ou indicatif et rôle',
            ], [], [], ['atak'], [
                'Rechargez le pack jeu, quittez Arma complètement puis relancez. Ouvrez Paramètres, saisissez votre rôle, choisissez l’affichage carte, puis Enregistrer.',
            ], 'Overwatch 1.5.11 · Athena ATAK 1.0.74'),
            $pr(361, '2026-09-01', 'Les photos du téléphone sont toutes copiées dans un même dossier', 'Chaque photo prise depuis le téléphone ou le casque est recopiée dans Documents\Arma 3 - COMSPEC\Captures. Le bouton Dossier photos copie ce chemin. Les clichés arrivent de nouveau au poste même si un autre outil photo annonce un fichier qui n’existe pas', [], [
                'Un seul dossier de photos, indépendant du profil Arma et des autres outils',
                'Dossier photos copie le chemin dans le presse-papiers',
            ], [
                'La photo était mise en file puis introuvable : le poste ne la recevait pas',
                'Plusieurs dossiers possibles, sans indication claire de celui à ouvrir',
            ], ['atak'], [
                'Rechargez le pack jeu, quittez Arma complètement puis relancez. Prenez une photo : elle doit apparaître dans Documents\Arma 3 - COMSPEC\Captures, puis au poste. Sur Athena, Dossier photos copie ce chemin.',
            ], 'Overwatch 1.5.11 · Athena ATAK 1.0.73'),
            $pr(360, '2026-09-01', 'La boussole et les outils carte du téléphone ne sont plus recouverts', 'Sur la carte du téléphone, la boussole en haut à gauche et le menu des outils carte en bas à gauche restent utilisables. Les cartouches de grille et d’unité suivie sont décalés à droite. Le zoom plus et moins reste disponible en haut à droite', [], [], [
                'Un carré sombre recouvrait la boussole',
                'Le menu des outils carte passait sous les cartouches d’indicatif et de grille',
            ], ['atak'], [
                'Rechargez le pack jeu, quittez Arma complètement puis relancez. Ouvrez la carte du téléphone : la boussole doit être lisible en haut à gauche, et le bouton des outils carte en bas à gauche doit ouvrir le menu sans passer sous un encadré.',
            ], 'Overwatch 1.5.10 · Athena ATAK 1.0.72'),
            $pr(359, '2026-09-01', 'La tuile Athena affiche le journal de liaison, le compte et les photos', 'Ouvrir Athena sur le téléphone montre désormais le journal de la session : liaisons, erreurs et envois, ligne par ligne avec l’heure. Le bandeau indique le compte connecté et combien d’opérateurs sont en liaison. Depuis le même écran, on peut forcer l’envoi des photos et vérifier le dossier où elles sont enregistrées', [
                'Journal de session dans la tuile Athena (liaison, erreurs, envois)',
                'Compte connecté et nombre d’opérateurs en liaison dans le bandeau',
                'Envoi forcé des photos depuis l’écran Journal',
                'Vérification du dossier où les photos sont enregistrées',
            ], [], [
                'L’écran Journal restait vide alors que la session écrivait déjà les événements',
                'Après une réapparition, la même ligne de grâce ou de clôture médicale se répétait',
            ], ['atak'], [
                'Rechargez le pack jeu, quittez Arma complètement puis relancez. Ouvrez la tuile Athena : le journal doit se remplir au fil de la mission. Le bandeau affiche votre compte. Envoyer photos et Dossier photos sont en haut de l’écran Journal.',
            ], 'Overwatch 1.5.10 · Athena ATAK 1.0.71'),
            $pr(358, '2026-09-01', 'Les adresses e-mail restent entre le titulaire et l’administration du site', 'Sur le portail, les adresses e-mail des membres ne s’affichent plus pour l’encadrement d’une communauté. Seule l’administration du site les voit en clair. Un organisateur peut demander la suppression d’un compte : un opérateur du site valide, l’accès est retiré, l’historique reste sous « Ancien membre »', [
                'Les organisateurs peuvent demander la suppression d’un compte de leur communauté, y compris lorsqu’il est encore actif',
            ], [
                'Les adresses e-mail n’apparaissent plus dans les listes, fiches et exports, sauf pour l’administration du site',
            ], [], ['personnel', 'platform'], [
                'Pour voir une adresse, connectez-vous avec un compte d’administration du site. Pour retirer un membre : fiche membre, Demander la suppression du compte.',
            ], 'Athena 1.0.59'),
            $pr(357, '2026-09-01', 'Le signal d’urgence et les opérateurs à terre remontent de nouveau au poste', 'Un appui sur PANIC depuis le téléphone arrive au poste de commandement. Un opérateur inconscient ou hors combat est aussi signalé, même si la liaison Athena s’est établie après le début de mission. La tuile Connexion Athena reste le secours en cas de coupure', [
                'Signal d’urgence du téléphone visible dans le journal de liaison du poste',
                'Inconscient et hors combat signalés dès que la liaison est en place',
            ], [], [
                'Le téléphone enregistrait le signal, le poste ne recevait rien',
                'Sans session dès le lancement, les alertes médicales ne partaient plus',
            ], ['atak'], [
                'Rechargez le pack jeu, quittez Arma complètement puis relancez. Une fois en liaison, appuyez sur PANIC : une ligne « Opérateur à terre » doit apparaître au poste. Un KO ACE doit aussi y figurer.',
            ], 'Overwatch 1.5.9'),
            $pr(356, '2026-09-01', 'La mission reconnaît votre Steam et charge Athena toute seule', 'Dès l’entrée en mission, Overwatch lit le compte Steam du joueur et récupère la fiche Athena déjà associée. Plus besoin de se connecter à la main à chaque partie. La tuile Connexion Athena reste disponible si la liaison a été coupée', [
                'Reconnaissance du compte Steam au lancement de la mission',
                'Chargement de la fiche et des données Athena sans saisie',
            ], [], [
                'Sans session enregistrée sur l’ordinateur, le joueur restait hors liaison, même si Steam était déjà associé à son compte',
            ], ['atak'], [
                'Rechargez le pack jeu, quittez Arma complètement puis relancez. Si votre Steam est lié au portail, vous devez arriver déjà identifié. En cas de coupure, ouvrez la tuile Connexion Athena.',
            ], 'Overwatch 1.5.8'),
            $pr(355, '2026-09-01', 'La carte du téléphone affiche l’indicatif, plus le numéro de groupe', 'Sur la carte ATAK, le symbole d’un opérateur reprend son indicatif (TA1, YB1…). Le numéro de groupe Arma et le nom d’éditeur ne s’affichent plus à la place. Le bandeau d’unité suivie et le symbole disent la même chose', [], [], [
                'Le bandeau indiquait le bon indicatif, le symbole à côté montrait encore 01 ou le nom du groupe Arma',
            ], ['atak'], [
                'Rechargez le pack jeu, quittez Arma complètement puis relancez. Ouvrez la carte du téléphone : votre symbole doit afficher votre indicatif, comme le bandeau.',
            ], 'Overwatch 1.5.7'),
            $pr(354, '2026-09-01', 'Les photos et les pièces de fiche partent jusqu’au poste', 'Une photo prise en jeu s’enregistre bien dans le dossier de captures Arma, puis arrive au poste. Une fiche de renseignement emporte aussi sa photo jointe. Les clichés d’un autre pack qui n’existent pas sur le disque ne bloquent plus l’envoi', [
                'Photo terrain : capture Arma envoyée au poste, même si un autre outil a annoncé un cliché introuvable',
                'Fiche de renseignement : la photo jointe est bien reçue par le bureau SSE',
            ], [], [
                'Certaines photos restaient absentes du poste alors que le journal indiquait un envoi',
                'La fiche partait, mais la pièce jointe était refusée',
            ], ['atak'], [
                'Rechargez le pack jeu, quittez Arma complètement puis relancez. Prenez une photo depuis le téléphone ou le casque : elle doit apparaître au poste. Envoyez une fiche avec photo : la pièce doit s’afficher sur la fiche.',
            ], 'Overwatch 1.5.7'),
            $pr(353, '2026-09-01', 'Les tenues de l’arsenal se regroupent par collection', 'Dans la fenêtre Athena de l’arsenal, vos tenues et celles de la communauté sont rangées par collection. Cliquez une collection pour l’ouvrir ou la refermer. Le bouton Supprimer retire la tenue choisie de votre arsenal, ou de la communauté si c’est vous qui l’avez envoyée', [
                'Collections repliables à gauche (votre arsenal) et à droite (communauté)',
                'Supprimer, avec une confirmation avant le retrait',
            ], [], [
                'Toutes les tenues s’affichaient en une seule liste, sans pouvoir en retirer',
            ], ['atak'], [
                'Rechargez le pack jeu, quittez Arma complètement puis relancez. À l’arsenal, bouton Athena. Cliquez une collection pour voir les tenues. Choisissez-en une, puis Supprimer.',
            ], 'Overwatch 1.5.6'),
            $pr(352, '2026-09-01', 'Le choix de vue du dossier n’ouvre plus un vide sous les cartes', 'Sur le dossier personnel, après Vue publique et Vue RH, le pied de page arrive tout de suite. Plus de bande vide sous les cartes, ni de trou en bas d’écran', [], [], [
                'Le choix de vue laissait encore un grand vide avant le pied de page',
            ], ['personnel'], [
                'Ouvrez un dossier avec accès RH, sans choisir la vue. Les deux cartes doivent coller au pied de page.',
            ], 'Athena Effectifs'),
            $pr(351, '2026-09-01', 'La signature s’enregistre depuis le bureau Courrier', 'Depuis le tableau de bord Courrier, vous dessinez et enregistrez votre signature. Elle est ensuite proposée lorsque vous signez un courrier, sans tout refaire à chaque fois', [
                'Page Ma signature : dessin, nom, signature principale',
                'Réutilisation à la signature d’un courrier',
            ], [], [
                'La signature ne pouvait s’enregistrer qu’au moment de signer un document',
            ], ['platform'], [
                'Ouvrez le bureau Courrier, puis Ma signature. Dessinez, enregistrez. En signant un courrier, choisissez Ma signature enregistrée.',
            ], 'Athena Courrier'),
            $pr(350, '2026-09-01', 'L’en-tête du courrier reprend la communauté, l’unité et le groupe', 'Dans le bureau Courrier, l’en-tête papier se remplit avec le nom de la communauté, l’unité d’affectation et le groupe de l’opérateur. Les exemples figés ne s’affichent plus à la place des vraies données', [
                'Communauté, unité et groupe repris depuis la fiche de l’opérateur',
            ], [], [
                'L’en-tête proposait des exemples (ministère, unité d’illustration) au lieu des données de la communauté',
            ], ['platform'], [
                'Ouvrez un courrier. L’en-tête doit afficher votre communauté, votre unité et votre groupe. Vous pouvez encore les modifier pour ce document.',
            ], 'Athena Courrier'),
            $pr(349, '2026-09-01', 'L’aperçu PDF des documents s’ouvre sans script extérieur', 'Dans la bibliothèque, l’aperçu d’un PDF s’affiche même lorsque le site n’autorise que ses propres scripts. Plus besoin d’un chargement extérieur bloqué', [], [], [
                'L’aperçu PDF restait vide : le lecteur était refusé par la politique de scripts du site',
            ], ['platform'], [
                'Ouvrez un PDF publié dans Documents. Les pages doivent s’afficher, avec le changement de page et le zoom.',
            ], 'Athena Documents'),
            $pr(348, '2026-09-01', 'Les manuels se rédigent dans Athena, avec page de garde et signatures', 'Dans la bibliothèque, un document peut désormais s’écrire directement : page de garde, avant-propos avec signatures, puis le texte. Joindre un fichier déjà prêt reste possible', [
                'Choix Joindre un fichier ou Rédiger le document à la création',
                'Page de garde : numéros de publication, titre, date, diffusion, destruction, autorité émettrice',
                'Page des signatures, puis le corps du texte',
            ], [], [
                'La création n’offrait qu’un dépôt de fichier, sans rédaction',
            ], ['platform'], [
                'Ouvrez la gestion documentaire, puis Ajouter un document. Choisissez Rédiger le document. Les lecteurs voient le manuel comme un document imprimé.',
            ], 'Athena Documents'),
            $pr(347, '2026-09-01', 'Le journal radio aligne toutes les bulles', 'Dans le journal radio du poste, les messages de groupe, les messages du poste et la zone d’écriture occupent la même largeur, sans décalage', [], [
                'Même marge à gauche pour toutes les bulles et la zone d’écriture',
            ], [
                'Les messages du poste étaient décalés vers la droite par rapport aux messages de groupe',
            ], ['atak'], [
                'Rechargez la page ATAK du poste. Ouvrez le journal radio : les bulles et le champ d’émission doivent démarrer sur la même ligne à gauche.',
            ], 'Athena ATAK'),
            $pr(346, '2026-09-01', 'Le tchat du téléphone reste lisible pendant la saisie', 'Sur le tchat du téléphone, la zone d’écriture n’est plus vidée pendant le suivi des messages. Les messages de groupe et les alertes médicales s’affichent en clair, avec un texte plus grand', [], [
                'Zone d’écriture plus haute, pour un message long',
            ], [
                'La saisie disparaissait toutes les quelques secondes, sur téléphone comme sur ordinateur',
                'Les messages de groupe et les alertes médicales s’affichaient en une ligne illisible',
            ], ['atak'], [
                'Rechargez la page tchat du téléphone. Écrivez un message long : le texte doit rester. Les messages de groupe montrent l’indicatif et le texte, pas une ligne technique.',
            ], 'Athena ATAK'),
            $pr(345, '2026-09-01', 'Les rapports, l’appui et la réparation reviennent dans ACE', 'Dans ACE (sur soi), sous COMSPEC Athena, le menu ATAK Tactique est de nouveau là : rapports, demande d’appui, service véhicule. La réparation du téléphone est dans ACE, rubrique Équipement. Connexion Athena reste en tête', [], [], [
                'Les rapports, l’appui et la réparation du téléphone n’apparaissaient plus dans ACE',
            ], ['atak'], [
                'Rechargez le pack jeu, quittez Arma complètement puis relancez. ACE sur soi : COMSPEC Athena, puis ATAK Tactique. Équipement : rallumer / réparer l’écran du téléphone.',
            ], 'Overwatch 1.5.5'),
            $pr(344, '2026-09-01', 'Le menu ACE ouvre la vraie connexion Athena', 'Dans ACE (sur soi), COMSPEC Athena → Connexion Athena ouvre l’écran avec l’e-mail et le mot de passe, comme la tuile du téléphone. Ce n’est plus l’écran de code court', [], [], [
                'ACE Compte Athena ouvrait l’écran de liaison par code, sans e-mail ni mot de passe',
            ], ['atak'], [
                'Rechargez le pack jeu, quittez Arma complètement puis relancez. ACE sur soi : COMSPEC Athena, puis Connexion Athena. Vous devez voir les champs e-mail et mot de passe.',
            ], 'Overwatch'),
            $pr(343, '2026-09-01', 'La connexion Athena s’ouvre depuis le téléphone, plus au lancement', 'La fenêtre de connexion n’apparaît plus toute seule en début de mission. Sur le bureau du téléphone, la tuile Connexion Athena ouvre l’écran de liaison', [], [
                'Tuile Connexion Athena sur le bureau du téléphone',
            ], [
                'La fenêtre de connexion s’ouvrait dès le début de mission',
            ], ['atak'], [
                'Rechargez le pack jeu, quittez Arma complètement puis relancez. La mission démarre sans écran de connexion. Ouvrez le téléphone, tuile Connexion Athena.',
            ], 'Overwatch 1.5.4'),
            $pr(342, '2026-09-01', 'Le suivi d’effectif affiche l’indicatif et l’affectation, pas le nom de la communauté', 'Sur le téléphone et au poste, le groupe du suivi d’effectif reprend l’indicatif et l’affectation de la fiche, pas le titre de la communauté', [], [], [
                'Le suivi d’effectif prenait le nom de la communauté comme nom de groupe',
            ], ['atak'], [
                'Rechargez le pack jeu, quittez Arma complètement puis relancez. Sur le téléphone, le groupe du suivi doit coller à l’indicatif et à l’affectation du tableau Effectifs.',
            ], 'Overwatch 1.5.3 · Liaison 1.18.2'),
            $pr(341, '2026-09-01', 'Sons d’ordre et ombrage du relief : la carte du poste reste utilisable', 'Les sons d’ordre et d’accusé se jouent au poste. Si l’ombrage du sol n’est pas prêt, la carte reste affichée, sans écran bloqué', [], [], [
                'Les sons d’ordre et d’accusé ne se chargeaient pas',
                'L’ombrage du relief faisait échouer le chargement de la carte',
            ], ['atak'], [
                'Rechargez la page ATAK du poste. Les alertes sonores et l’ombrage du sol doivent se charger sans bloquer la carte.',
            ], 'Athena ATAK'),
            $pr(340, '2026-09-01', 'Les fiches FRS/FRM se rédigent dans le téléphone, texte lisible', 'Une tuile FRS/FRM ouvre le rédacteur de fiche à la taille de l’écran du téléphone. Le bouton Valider et transmettre et les champs de contexte se lisent clairement', [
                'Tuile FRS/FRM sur le bureau du téléphone',
            ], [], [
                'Le texte de validation et le contexte étaient trop petits',
                'La fiche s’ouvrait hors du téléphone, en overlay',
            ], ['atak'], [
                'Rechargez le pack jeu, quittez Arma complètement puis relancez. Sur le bureau du téléphone : tuile FRS/FRM. La fiche tient dans l’écran. Valider et transmettre doit se lire sans zoomer.',
            ], 'Overwatch 1.5.2'),
            $pr(339, '2026-09-01', 'Caméra du téléphone : on marche encore, les photos arrivent au poste', 'En caméra plein écran du téléphone, l’opérateur peut se déplacer. La photo prise part vers le poste au lieu de rester bloquée', [], [], [
                'Une fois la caméra ouverte, le déplacement était bloqué',
                'Les photos prises depuis cette caméra n’apparaissaient pas au poste',
            ], ['atak'], [
                'Rechargez le pack jeu, quittez Arma complètement puis relancez. Ouvrez le téléphone, caméra plein écran : marchez, prenez une photo, elle doit apparaître au poste.',
            ], 'Overwatch 1.5.1 · Extension 1.18.1'),
            $pr(338, '2026-09-01', 'Les contacts restent visibles en passant de la vue relief à la carte à plat', 'Un effectif affiché sur le relief reste sur la carte à plat. Le point ne clignote plus pour disparaître au changement de vue', [], [], [
                'En quittant la vue relief, le point d’un contact apparaissait un instant puis disparaissait, alors qu’il restait en liaison',
            ], ['atak'], [
                'Rechargez la page du poste. Passez de 3D à 2D : le contact doit rester à sa place.',
            ], 'Overwatch'),
            $pr(337, '2026-09-01', 'Les écrans du téléphone ne se marchent plus dessus', 'Athena ne reste plus collée sur Comptes-rendus, RENS ou le bureau. Un compte rendu n’affiche plus tous les formulaires à la fois. Le menu RENS ouvre bien le rédacteur de fiche', [], [], [
                'Athena recouvrait les autres applications du téléphone',
                'L’onglet Nouveau empilait TIC, Eagle Down et le bilan sur le même écran',
                'Le menu RENS n’ouvrait pas le rédacteur de fiche',
            ], ['atak'], [
                'Rechargez le pack jeu, relancez Arma complètement. Sur le téléphone : Athena, puis Comptes-rendus (Nouveau / TIC), puis RENS.',
            ], 'Overwatch'),
            $pr(336, '2026-09-01', 'La fiche d’une tenue liste son équipement', 'Sur la page d’une tenue, l’arme, les vêtements, le gilet, le sac et le contenu des poches s’affichent, tels qu’envoyés depuis l’arsenal', [
                'Liste de l’équipement sur la fiche d’une tenue',
            ], [], [], ['atak'], [
                'Ouvrez Équipement, puis une tenue : l’équipement apparaît sous la photo de présentation.',
            ], 'Portail'),
            $pr(335, '2026-09-01', 'La photo de présentation d’une collection s’enregistre', 'Lorsque vous créez une collection avec une photo, celle-ci est conservée. Si le fichier est trop lourd ou vient d’un iPhone, un message indique clairement comment le renvoyer', [], [], [
                'La collection était créée mais la photo de présentation échouait, avec un message trop vague',
            ], ['atak'], [
                'Sur Équipement, ouvrez la collection, puis Modifier : ajoutez de nouveau la photo en JPG ou PNG. Si le message indique que le fichier est trop lourd, choisissez une image plus légère.',
            ], 'Portail'),
            $pr(334, '2026-09-01', 'À l’arsenal, les tenues Athena s’ouvrent en grand', 'Le bouton Athena ouvre une fenêtre large : vos tenues d’un côté, celles de la communauté de l’autre, avec les icônes d’équipement. Vous pouvez envoyer ou récupérer une seule tenue, ou toutes', [
                'Fenêtre large au clic Athena, sans recouvrir la liste Mes équipements',
                'Aperçu des icônes d’arme, tenue, gilet, casque et sac au clic d’une tenue',
                'Envoyer cette tenue ou toutes ; récupérer cette tenue ou toutes',
            ], [], [], ['atak'], [
                'Rechargez le pack jeu, relancez Arma complètement. Ouvrez l’arsenal, cliquez Athena : deux colonnes, icônes, puis Envoyer cette / Envoyer toutes et Récupérer cette / Récupérer toutes.',
            ], 'Overwatch'),
            $pr(333, '2026-09-01', 'L’envoi des tenues depuis l’arsenal fonctionne à nouveau', 'Depuis l’arsenal, Envoyer vers Athena enregistre les tenues de la communauté. Le journal ne se remplit plus d’échecs à chaque tenue', [], [], [
                'Le bouton Envoyer vers Athena n’enregistrait aucune tenue et le journal se remplissait d’échecs',
            ], ['atak'], [
                'Rechargez le pack jeu, relancez Arma complètement. Ouvrez l’arsenal, Athena en haut à droite, puis Envoyer vers Athena. Les tenues apparaissent dans l’espace équipement du poste.',
            ], 'Overwatch'),
            $pr(332, '2026-09-01', 'Les photos ne saturent plus le journal à la connexion', 'À l’entrée en mission, Overwatch n’essaie plus d’envoyer les captures tant que la session Athena n’est pas ouverte. Une photo prise ensuite part une seule fois, sans relance en boucle', [], [], [
                'À la connexion, le journal se remplissait d’échecs d’envoi de photos, y compris après l’ouverture de session',
            ], ['atak'], [
                'Rechargez le pack jeu, puis relancez Arma complètement. À l’entrée en mission, le journal ne doit plus défiler d’envois de photos tant que la session n’est pas prête.',
            ], 'Overwatch'),
            $pr(331, '2026-09-01', 'Le parcours d’intégration des nouveaux membres s’installe correctement', 'Sur les communautés déjà en place, le suivi d’intégration des nouveaux membres se pose maintenant sans erreur. Relancez la mise à jour du portail pour activer les tables manquantes', [], [], [
                'La mise à jour du portail n’installait pas le suivi d’intégration sur certaines communautés',
            ], ['personnel'], [
                'Relancez la mise à jour du portail (même procédure que d’habitude). Pas de pack jeu.',
            ], 'Portail'),
            $pr(330, '2026-09-01', 'COMSPEC Athena reste dans le menu ACE', 'Dans le menu d’interaction ACE (sur soi), l’entrée COMSPEC Athena est de nouveau visible : Compte Athena et ouverture du téléphone. Les rapports ATAK supplémentaires restent un réglage optionnel', [], [], [
                'Le menu ACE ne montrait plus COMSPEC Athena, sauf si un réglage étendu était activé',
            ], ['atak'], [
                'Rechargez le pack jeu, relancez Arma complètement. ACE sur soi : COMSPEC Athena, puis Compte Athena. Pas besoin d’activer les menus étendus.',
            ], 'Overwatch'),
            $pr(328, '2026-09-01', 'Le tableau des effectifs reste visible sous la carte', 'Sur la carte du poste, le journal d’analyse et le bandeau du bas ne recouvrent plus le tableau des effectifs. Le journal démarre replié. Le bouton Unités à gauche de la carte ouvre ce tableau', [], [], [
                'Le journal d’analyse et le bandeau de contexte masquaient le tableau des contacts',
            ], ['atak', 'command'], [
                'Ouvrez la carte du poste. Le tableau des effectifs est sous la carte. Unités à gauche l’affiche s’il était réduit. Le journal d’analyse s’ouvre seulement si vous cliquez son titre.',
            ], 'Portail'),
            $pr(329, '2026-09-01', 'Les cartes Absence, Élévation et Avancement s’ouvrent', 'Sur le tableau de bord, un clic sur Absence, Élévation ou Avancement ouvre le formulaire correspondant. Le parcours ne reste plus bloqué sur le choix de la démarche', [], [], [
                'Cliquer sur une des trois cartes n’ouvrait pas le formulaire',
            ], ['personnel'], [
                'Ouvrez le tableau de bord, descendez jusqu’à Mon dossier RH, puis cliquez sur Absence, Élévation ou Avancement.',
            ], 'Portail'),
            $pr(327, '2026-09-01', 'Les opérateurs en liaison restent visibles sur la carte', 'Un opérateur présent dans les effectifs et en liaison disparaissait parfois de la carte d’un coup, puis réapparaissait. La liste des effectifs et la carte restent désormais alignées : tant qu’il est en liaison, son symbole reste affiché', [
                'Les opérateurs en liaison restent visibles sur la carte, même quand le poste actualise les effectifs',
            ], [], [
                'Le symbole d’un opérateur en liaison clignotait ou disparaissait alors qu’il figurait encore dans les effectifs',
            ], ['atak'], [
                'Rechargez la page ATAK du poste. Pas de pack jeu.',
            ], 'ATAK'),
            $pr(326, '2026-09-01', 'Connexion Steam depuis le tableau de bord', 'Une tuile Connexion Steam ouvre Steam pour associer votre compte au portail. Après validation, Overwatch vous reconnaît en jeu. Une fois Steam associé, la tuile disparaît ; vous pouvez encore changer de compte depuis Mon compte', [
                'Tuile Connexion Steam sur le tableau de bord tant que Steam n’est pas associé',
                'Changement de compte Steam depuis Mon compte',
            ], [], [], ['atak', 'platform'], [
                'Ouvrez le tableau de bord. Si Steam n’est pas encore associé, la tuile Connexion Steam apparaît. Une fois lié, elle disparaît. Pour changer de compte, ouvrez Mon compte. Pas de pack jeu.',
            ], 'Portail'),
            $pr(325, '2026-09-01', 'La connexion Athena en jeu associe Steam toute seule', 'Si Steam n’était pas encore enregistré sur le compte, la connexion par e-mail en jeu l’associe à partir de la session. L’opérateur est prévenu à l’écran et par courriel ; l’encadrement reçoit aussi un courriel', [
                'Connexion par e-mail en jeu : l’identifiant Steam de la session est enregistré s’il manquait',
                'L’opérateur voit la confirmation à l’écran et reçoit un courriel ; l’encadrement est informé',
            ], [], [
                'Sans identifiant Steam déjà enregistré, la connexion en jeu échouait au lieu d’associer Steam',
            ], ['atak'], [
                'Rechargez le pack jeu, relancez Arma complètement, connectez-vous avec votre e-mail. Si Steam n’était pas associé, il l’est après cette connexion.',
            ], 'Overwatch'),
            $pr(322, '2026-09-01', 'L’ombrage d’un théâtre est le même pour toutes les communautés', 'Le sol, l’ombrage et les volumes du jeu relevés sur un théâtre — Altis, Malden, Stratis… — apparaissent désormais sur le poste de chaque communauté. Une communauté n’a plus à tout relever à nouveau si une autre l’a déjà fait. Les positions, les notes et les effectifs restent propres à chacune', [], [], [
                'Deux communautés sur le même théâtre : l’une voyait l’ombrage et le relief, l’autre « pas encore sur le poste »',
            ], ['atak'], [
                'Rechargez la carte du poste. Pas de nouveau pack jeu. Les positions et les notes restent propres à chaque communauté.',
            ], 'Portail'),
            $pr(321, '2026-09-01', 'La session Overwatch se rouvre même sans Steam', 'Au relancement d’Arma, Overwatch retrouve la session Athena même si Steam n’est pas associé au compte. Plus d’incident qui referme la connexion', [], [], [
                'Au relancement, la session enregistrée plantait dès qu’aucun identifiant Steam n’était connu',
            ], ['atak'], [
                'Relancez Arma avec Overwatch. Si vous étiez déjà connecté à Athena sans Steam associé, la session se rouvre. Pas besoin d’un nouveau pack jeu.',
            ], 'Athena'),
            $pr(318, '2026-09-01', 'Overwatch 1.5.0 : votre identité, plus le nom de la communauté', 'Le pack jeu affiche l’opérateur, pas le titre de la communauté. L’indicatif vient de la fiche Effectifs. Les messages du poste restent dans le téléphone et le journal, plus dans le chat de bord d’Arma. Sur le téléphone, le journal Athena se lit enfin', [
                'Écran prêt : photo si elle existe, prénom, nom, indicatif, rôle, grade et fonction',
            ], [
                'Paramètres du téléphone et bandeau de carte : même indicatif que les Effectifs',
            ], [
                'Le chat de bord d’Arma ne recopie plus les messages du poste',
                'Le journal du téléphone n’est plus un rectangle noir',
            ], ['atak'], [
                'Rechargez le pack jeu, relancez Arma complètement. Connectez-vous : l’écran prêt et Paramètres doivent coller à votre fiche. Un message du poste n’apparaît plus dans le chat latéral.',
            ], 'Overwatch 1.5.0 · Athena 1.0.63'),
            $pr(308, '2026-09-01', 'Le point carte Zeus se pose sans erreur', 'Depuis Intelligence numérique ou le module Poser un point carte, le formulaire s’ouvre. Le point apparaît sur la carte du bureau, pas sur celle des joueurs', [], [], [
                'Un message d’erreur apparaissait en posant un point carte, et le point n’était pas créé',
            ], ['command'], [
                'Rechargez le pack SSE, relancez Arma complètement, ouvrez Zeus, puis Intelligence numérique → Poser un point carte.',
            ], 'SSE'),
            $pr(307, '2026-09-01', 'Le journal Athena du téléphone se lit enfin', 'Sur le téléphone, l’application Athena affiche le filtre, la liste et le détail. Plus de grand rectangle noir : même sans message, une phrase l’indique. Contact, comptes-rendus et compte restent chacun sur leur écran', [
                'Liste et détail lisibles dès l’ouverture du journal',
            ], [], [
                'Le journal n’affichait plus que deux zones noires, avec seulement la flèche du filtre',
            ], ['atak'], [
                'Rechargez le pack jeu, relancez Arma complètement, ouvrez le téléphone puis Athena. Le journal doit montrer le filtre et le texte, même s’il est vide.',
            ], 'Overwatch'),
            $pr(305, '2026-09-01', 'Les modèles d’arrivée se lisent comme le reste du bureau', 'La liste des modèles de parcours et le formulaire de création ou de modification reprennent le bureau clair : texte sombre sur fond blanc, tableau lisible, cartes d’étapes nettement séparées. La durée en jours tient sur une courte case, les cases à cocher restent visibles', [], [
                'Liste des modèles, création et modification alignées sur le bureau effectifs',
            ], [
                'Les noms de parcours et les libellés du formulaire étaient gris pâle, presque invisibles',
            ], ['personnel'], [
                'Ouvrez Intégration des nouveaux membres, puis Modèles. Les lignes du tableau se lisent. Ouvrez un modèle : chaque étape est un bloc distinct, la durée n’occupe plus toute la largeur.',
            ], 'Portail'),
            $pr(306, '2026-09-01', 'Les espaces opérationnels se lisent comme le reste du portail', 'La page des opérations a le même fond clair que le tableau de bord. Quand aucune opération n’est ouverte, un encadré l’explique. Le formulaire d’ouverture reste à côté, avec un indicatif court du type AEGIS, pas le nom de la communauté', [], [], [
                'La page était presque noire, avec une phrase isolée au milieu du vide',
            ], ['platform'], [
                'Ouvrez Opérations dans le menu. Le fond est clair. Sans opération, l’encadré d’accueil et le formulaire se lisent ensemble.',
            ], 'Portail'),
            $pr(304, '2026-09-01', 'Les symboles des opérateurs suivent l’apparence de la carte', 'Dans Réglages du poste, la taille des icônes et des libellés s’applique enfin aux opérateurs. L’indicatif se lit sous le symbole, sans clignoter à chaque mise à jour de position. Le rôle et l’état de liaison s’affichent au survol', [
                'Indicatif lisible sous le symbole, détail au survol',
            ], [
                'Taille des icônes et des libellés depuis Apparence de la carte',
            ], [
                'Les symboles apparaissaient et disparaissaient, et les trois lignes sous l’opérateur étaient illisibles',
            ], ['atak'], [
                'Ouvrez la carte du poste, Réglages, Apparence de la carte. Déplacez Icônes et Libellés des unités : les opérateurs changent tout de suite, sans clignoter.',
            ], 'Portail'),
            $pr(303, '2026-09-01', 'Le téléphone ATAK reprend l’indicatif des Effectifs', 'Sur Paramètres, l’indicatif est celui de votre fiche (YB1, TA1…), pas le nom de la communauté. Le groupe en jeu reste votre groupe Arma, ou « groupe actuel » s’il n’a pas d’équipe. Le bandeau de la carte suit la même lecture', [], [], [
                'Indicatif et groupe affichaient le titre de la communauté, y compris en bas de la carte',
            ], ['atak'], [
                'Ouvrez le téléphone, Paramètres. L’indicatif doit coller à la colonne Indicatif des Effectifs. Relancez Arma complètement après un nouveau pack jeu.',
            ], 'Overwatch'),
            $pr(302, '2026-09-01', 'Le chat du jeu reste silencieux pour le poste', 'Un message envoyé depuis le poste, Overwatch ou ATAK n’apparaît plus dans le chat de bord d’Arma. Il reste dans le téléphone, les messages de groupe et le journal ATAK. Les messages que vous tapez vous-même dans le chat du jeu ne changent pas', [], [], [
                'Un message du poste s’écrivait dans le chat latéral, souvent avec le nom de la communauté et la mention TOC, comme un indicatif radio',
            ], ['atak'], [
                'Envoyez un message depuis le poste pendant une mission. Le chat d’Arma reste vide. Ouvrez le téléphone : le fil est bien là. Rechargez le pack jeu, puis relancez Arma complètement.',
            ], 'Overwatch'),
            $pr(301, '2026-09-01', 'Demander un accès quand la carte reste fermée', 'Si vous êtes bien en liaison en jeu mais que votre grade, votre rôle ou votre fonction n’ouvrent pas certaines vues de la carte, une fenêtre l’explique. Vous pouvez demander les autorisations : l’encadrement reçoit le courrier et valide depuis le bureau effectifs', [
                'Fenêtre sur la carte du poste, uniquement en liaison, uniquement s’il manque un accès',
            ], [], [], ['atak', 'personnel'], [
                'Ouvrez la carte du poste une fois en liaison. Si des vues restent fermées, Demander les autorisations d’accès transmet la demande à l’encadrement.',
            ], 'Portail'),
            $pr(300, '2026-09-01', 'L’écran prêt Overwatch affiche votre identité', 'Quand l’environnement est prêt, vous voyez votre photo si elle existe, votre prénom et votre nom, votre indicatif, puis le rôle, le grade et la fonction. Le nom de la communauté reste à part, et le pied de fenêtre indique le pack en service', [], [], [
                'L’indicatif affichait le nom de la communauté, et l’unité une adresse interne',
                'Le pied de fenêtre montrait des versions vides',
            ], ['atak'], [
                'Reconnectez-vous depuis Overwatch. Sur Environnement prêt : photo (si vous en avez une), nom, indicatif, rôle, grade et fonction. Relancez Arma complètement après un nouveau pack jeu.',
            ], 'Overwatch'),
            $pr(299, '2026-09-01', 'Les listes Serveur et Carte se lisent clairement', 'Dans le bandeau de la carte du poste, ouvrir Serveur ou Carte affiche chaque théâtre (Altis, Stratis, Malden…) de façon lisible. Plus de texte blanc sur fond gris clair', [], [], [
                'Les lignes non sélectionnées étaient presque invisibles dans la liste déroulante',
            ], ['atak'], [
                'Ouvrez la carte du poste, puis Serveur. Chaque théâtre se lit sans survol.',
            ], 'Portail'),
            $pr(298, '2026-09-01', 'Une seule démarche RH à la fois sur le tableau de bord', 'En bas de page, vous choisissez d’abord Absence, Élévation ou Avancement. Les deux formulaires ne s’affichent plus côte à côte au milieu de l’écran', [], [], [
                'Les demandes d’élévation et d’avancement occupaient toute la largeur, en même temps, au-dessus du reste du tableau de bord',
            ], ['personnel'], [
                'Ouvrez le tableau de bord, descendez jusqu’à Mon dossier RH. Trois cartes, puis un seul formulaire.',
            ], 'Portail'),
            $pr(297, '2026-09-01', 'Les positions en mission ne remplissent plus le journal', 'Sur la carte du poste, le panneau d’activité ne crée plus une carte à chaque position reçue. La carte et les effectifs restent à jour ; le journal garde les connexions, les messages et les vrais événements.', [], [
                'Une connexion ou un changement d’indicatif continue d’apparaître',
            ], [
                'Une carte « Position reçue » s’ajoutait environ toutes les trente secondes pour le même opérateur',
            ], ['atak', 'command'], [
                'Ouvrez la carte du poste pendant une mission. Le panneau d’activité ne se remplit plus de positions répétées. L’opérateur reste visible sur la carte.',
            ], 'Portail 1.5.98'),
            $pr(296, '2026-09-01', 'La connexion Athena n’exige plus Steam pour le mot de passe', 'Un opérateur peut ouvrir Overwatch avec son e-mail et son mot de passe, même si Steam n’est pas encore associé au compte', [], [], [
                'La fenêtre de connexion se fermait en incident dès qu’aucun identifiant Steam n’était connu',
            ], ['atak'], [
                'Depuis Overwatch, connectez-vous avec l’e-mail Athena. Sans Steam associé, la session s’ouvre. Pas besoin d’un nouveau pack jeu.',
            ], 'Athena'),
            $pr(295, '2026-09-01', 'Le choix de vue du dossier ne laisse plus de vide', 'Sur le dossier personnel, après Vue publique et Vue RH, le pied de page arrive tout de suite : plus de bande blanche ni de trou sous les cartes', [], [], [
                'Grand vide entre le choix de vue et le pied de page',
            ], ['personnel'], [
                'Ouvrez un dossier personnel avec un accès RH. Les deux cartes restent, le pied de page est collé dessous.',
            ], 'Portail 1.5.92'),
            $pr(294, '2026-09-01', 'Le dossier RH se choisit en trois étapes', 'En bas du tableau de bord, une seule démarche à la fois : d’abord Absence, Élévation ou Avancement, puis le formulaire', [
                'Trois cartes de choix en bas de page, puis le formulaire correspondant',
                'Déclaration d’absence, demande d’élévation et souhait d’avancement depuis le tableau de bord',
            ], [
                'Le lien vers l’espace RH complet reste disponible à côté du parcours',
            ], [
                'Les deux formulaires n’apparaissent plus en même temps, avec l’absence reléguée en bas de carte',
            ], ['personnel'], [
                'Ouvrez le tableau de bord, descendez jusqu’à Mon dossier RH, choisissez Absence, Élévation ou Avancement, puis transmettez.',
            ], 'Portail 1.5.91'),
            $pr(293, '2026-09-01', 'Affichage dit vraiment ce qui est sur le poste', 'Dans Réglages carte, plus de texte d’atelier. Si les bâtiments sont déjà là et pas l’ombrage, le poste le dit clairement, au lieu de prétendre que rien n’a été relevé', [], [
                'Les cases Villes et villages, Routes, sans notice technique',
            ], [
                'Deux pavés d’atelier au milieu des réglages',
                'Ombrage et relevé du sol affichés absents alors que bâtiments, forêts et une date de relevé étaient déjà là',
            ], ['atak'], [
                'Ouvrez Réglages → Carte. Plus de pavé gris sous la vue relief. S’il y a des bâtiments sans ombrage, le bandeau le dit : bâtiments reçus, ombrage du sol pas encore sur le poste.',
            ], 'Portail 1.5.90'),
            $pr(292, '2026-09-01', 'Un vrai parcours d’arrivée pour les nouveaux membres', 'Après l’acceptation d’une candidature, la création d’un compte ou une invitation, l’encadrement suit l’arrivée : étapes, dossier personnel, rendez-vous et référent. Le membre voit son propre parcours, sans les notes internes', [
                'Tableau de suivi des arrivées, avec filtres et vue par étape en cours',
                'Fiche de parcours : dossier personnel, groupes de suivi, bilans, rendez-vous et journal',
                'Modèles de parcours publiés, repris à l’identique pour chaque nouvel arrivant',
                'Page « Mon intégration » : étapes visibles, invitations et message du référent',
                'Invitation par e-mail avec réponse Oui / Peut-être / Non et ajout au calendrier personnel',
            ], [
                'L’ancien écran d’accueil des arrivants ouvre désormais ce suivi',
            ], [], ['personnel', 'command'], [
                'Ouvrez Intégration des nouveaux membres dans le back-office. Vérifiez le modèle Intégration recrue, puis acceptez une candidature ou créez un compte : le parcours s’ouvre. Le membre consulte Mon intégration.',
            ], 'Portail 1.5.97'),
            $pr(291, '2026-09-01', 'Les comptes-rendus du téléphone ne se chevauchent plus', 'Sur le téléphone, la page Comptes-rendus aligne titre, reçus, liste, Localiser / Effacer et le détail. Plus de rectangle noir ni de boutons coupés en deux', [], [
                'Reçus, Nouveau, liste et détail tiennent chacun leur ligne',
                'Retour, Localiser et Effacer restent lisibles en bas du téléphone',
            ], [
                'La liste recouvrait Localiser et Effacer, et un grand cadre vide masquait le milieu de l’écran',
            ], ['atak'], [
                'Ouvrez le téléphone, application Comptes-rendus. Sans message reçu, le texte d’absence est sous les boutons, pas dessus. Relancer Arma après le nouveau pack.',
            ], 'Overwatch 1.4.98 · Athena 1.0.61'),
            $pr(290, '2026-09-01', 'Même vocabulaire sur la carte et dans les paramètres', 'Sur le téléphone, le cartouche de l’unité reprend les intitulés des paramètres : indicatif, rôle, groupe et grille. Plus de titres anglais, plus de chiffre sans nom', [
                'Indicatif, rôle, groupe et grille, comme dans Paramètres',
                'L’indicatif affiché est celui enregistré, pas le nom de profil',
            ], [], [
                'Le cartouche disait GROUP et CALLSIGN, la grille n’avait pas de titre, et l’indicatif montrait le nom du personnage',
            ], ['atak'], [
                'Ouvrez le téléphone sur la carte. Le cartouche de droite reprend Indicatif, Rôle, Groupe et Grille. Vérifiez dans Paramètres que l’indicatif est le même.',
            ], 'Overwatch 1.4.98 · Athena 1.0.60'),
            $pr(289, '2026-09-01', 'L’écran Athena du téléphone a quatre vues claires', 'Sur le téléphone ATAK, Athena n’empile plus tout. Journal, alertes, comptes-rendus et poste sont quatre écrans distincts, avec un vrai fil de lecture', [
                'Quatre boutons en haut : Journal, Alerter, Rapporter, Poste',
                'Le journal se filtre dans une liste, et le détail se lit sous la ligne choisie',
            ], [
                'Les boutons d’alerte, de compte rendu et de liaison n’apparaissent que sur leur écran',
            ], [
                'Tout était visible en même temps : huit filtres, trois zones vides, puis une file de boutons hors écran',
            ], ['atak'], [
                'Ouvrez le téléphone, application Athena. Journal pour lire. Alerter pour un contact ou un opérateur à terre. Rapporter pour un FRAGO ou une photo. Poste pour le compte et l’appui.',
            ], 'Overwatch 1.4.98 · Athena 1.0.59'),
            $pr(288, '2026-09-01', 'Plus de file d’attente pour les tenues hors liaison', 'Sans compte relié, l’envoi des tenues depuis l’arsenal ne remplit plus une file inutile. Un seul message l’indique, et le tampon reste libre pour les vrais comptes rendus', [], [], [
                'Sans liaison, chaque tenue locale était mise en attente l’une après l’autre jusqu’à saturer le tampon',
            ], ['atak'], [
                'Rechargez le pack. Sans session Athena, Envoyer les tenues affiche un seul avertissement. Le journal de session ne se remplit plus de transmissions hors ligne.',
            ], 'Overwatch 1.4.98'),
            $pr(287, '2026-09-01', 'La vue relief n’incline plus le plan de la carte', 'La carte à plat reste un plan ; le relief se voit dans une vue séparée, collines et unités posées sur le sol', [
                'La carte à plat reste parfaitement plane : plus de trapèze ni de bandes étirées',
                'La vue relief montre le sol relevé, avec la grille et les unités posées sur le terrain',
            ], [
                'Passer de la carte à plat au relief reprend le même cadrage',
            ], [
                'L’ancienne vue inclinée déformait tout le plan, sans collines réelles',
            ], ['atak'], [
                'Sur la carte du poste, choisissez À plat pour le plan, ou Relief 3D pour voir le sol relevé. Le cadrage suit le centre déjà affiché.',
            ], 'Portail 1.5.96'),
            $pr(286, '2026-09-01', 'Les tenues Athena à l’arsenal tiennent dans un tiroir', 'À l’arsenal, un petit bouton Athena ouvre les tenues de la communauté. La fenêtre n’apparaît plus toute seule, et les textes se lisent correctement', [], [
                'La fenêtre est plus étroite, collée à côté de Mes équipements, sans barrer le personnage',
                'Les intitulés et la liste ont une taille de texte lisible',
            ], [
                'Un bandeau trop large s’ouvrait dès l’entrée à l’arsenal, avec un texte d’aide presque illisible',
            ], ['atak'], [
                'Ouvrez l’arsenal. Cliquez Athena en haut à droite du centre. Envoyez ou récupérez les tenues, puis Fermer.',
            ], 'Overwatch 1.4.97'),
            $pr(285, '2026-09-01', 'Plus de bandeau Overwatch dans le menu pause', 'Le menu Échap en session ne montre plus le bandeau du pack en haut de l’écran', [], [], [
                'Le nom du pack s’affichait en bandeau au-dessus du menu pause, sans action utile',
            ], ['atak'], [
                'Rechargez le pack Overwatch, relancez Arma, puis ouvrez Échap en session : le bandeau a disparu. Le bouton de gestion du pack reste disponible.',
            ], 'Overwatch 1.4.97'),
            $pr(284, '2026-09-01', 'Le QR de la fenêtre détachée se scanne enfin', 'Pour ouvrir un module sur le téléphone, le code à scanner est désormais grand, sur fond blanc, à côté du visuel', [
                'Le code occupe un carré lisible, avec une marge claire autour',
            ], [], [
                'Le code était collé sur l’écran du téléphone dessiné : trop petit pour l’appareil photo',
            ], ['atak'], [
                'Sur la carte du poste, ouvrez Affichage déporté, puis Fenêtre détachée sur téléphone. Présentez le grand carré blanc à l’appareil photo.',
            ], 'Portail 1.5.95'),
            $pr(283, '2026-09-01', 'Pack actuel et pack exigé, visibles à l’écran', 'Si la communauté n’accepte plus ce pack, la fenêtre de connexion indique le pack installé et celui qui est demandé', [
                'Message clair : pack actuel et version exigée par la communauté',
                'Pied de fenêtre : liaison, pack actuel, et pack exigé dès que la communauté en a fixé un',
            ], [], [
                'Le bas de fenêtre affichait un numéro figé, sans dire ce que le poste attendait',
            ], ['atak'], [
                'Après le prochain pack Overwatch, relancez Arma. Si la connexion est refusée pour le pack, lisez les deux numéros. Le gestionnaire peut baisser l’exigence dans Cartographie, Expérience en jeu, Pack Overwatch minimal.',
            ], 'Overwatch 1.5.0 · Extension 1.18.0'),
            $pr(282, '2026-09-01', 'Une fenêtre propose d’actualiser après une mise à jour', 'Sur le tableau de bord, comme ailleurs sur le site, une fenêtre invite à actualiser la page lorsqu’une nouvelle version du portail est en place', [
                'La fenêtre propose Actualiser ou Plus tard, sans bloquer la navigation',
            ], [], [], ['platform'], [
                'Laissez le tableau de bord ouvert pendant une mise à jour, ou rechargez-le : la fenêtre apparaît comme sur le reste du site.',
            ], 'Portail 1.5.95'),
            $pr(281, '2026-09-01', 'La barre d’identité reste collée sous le menu', 'Sur le tableau de bord, communauté, grade, matricule et raccourcis restent sous le menu principal, y compris au défilement', [], [
                'Les raccourcis (fiche, demande à l’encadrement, signaler une anomalie) restent au même endroit',
            ], [
                'La barre d’identité n’apparaît plus après le visuel de briefing'
            ], ['personnel'], [
                'Ouvrez le tableau de bord. La barre d’identité se trouve juste sous Dashboard / Hub / Forum.',
            ], 'Portail 1.5.95'),
            $pr(276, '2026-09-01', 'La barre d’outils de la carte reste visible', 'Sur la carte du poste, la barre Position, Annoter, Tracer, Analyse et Vue ne disparaît plus à chaque visite', [
                'Le bouton Outils ramène la barre si elle a été repliée',
            ], [
                'Le choix Masquer est mémorisé : au prochain chargement, la barre reste repliée tant qu’on ne la rappelle pas',
            ], [
                'La barre disparaissait dès l’ouverture, sans moyen de la faire revenir',
            ], ['atak'], [
                'Ouvrez la carte du poste. La barre d’outils reste. Masquer la replie ; Outils la fait réapparaître.',
            ], 'Portail 1.5.95'),
            $pr(280, '2026-09-01', 'Les rapports se lisent comme en mission sur la carte du poste', 'Observation, situation et renseignement immédiat apparaissent en pastilles compactes, comme les libellés que les opérateurs voient déjà dans Arma', [
                'Chaque rapport posé sur le théâtre affiche son type en capitales (SPOTREP, SITREP, IMINI, CONTACT…) et le temps écoulé depuis l’émission',
                'La barre colorée en tête distingue d’un coup d’œil une observation, un renseignement immédiat ou un contact',
                'Les pastilles se superposent quand plusieurs signalements sont proches, sans masquer le terrain',
            ], [], [], ['atak', 'command'], [
                'Ouvrez la carte du poste. Un rapport transmis depuis le jeu apparaît à sa position, avec le même langage visuel qu’en mission. Pas besoin d’un nouveau pack jeu.',
            ], 'Portail 1.5.95'),
            $pr(279, '2026-09-01', 'Le tableau de bord réunit offres, dossier RH et annonces', 'Depuis le tableau de bord, les membres voient les postes ouverts de la communauté, gèrent leur propre dossier RH, et les organisateurs rédigent une annonce sans quitter cet écran', [
                'Les offres actuellement publiées de l’organisation s’affichent, avec un accès à la fiche publique et à la candidature',
                'Chaque membre peut demander une élévation (grade, rôle, fonction) ou un avancement, sans passer par le tableur des effectifs',
                'Les organisateurs habilités ouvrent le formulaire d’annonce déjà en place, pour un article court visible des membres',
            ], [
                'L’espace RH complet, les absences et le suivi des accès restent accessibles en un clic',
            ], [], ['personnel'], [
                'Ouvrez le tableau de bord. Les offres, votre dossier RH et, si vous organisez la communauté, la rédaction d’une annonce se trouvent sous les transmissions.',
            ], 'Portail 1.5.95'),
            $pr(277, '2026-09-01', 'Le dossier public se lit d’un coup d’œil', 'Sur la fiche d’un opérateur, le nom, l’indicatif et le portrait se tiennent : la photo de compte n’erre plus à côté, et Signaler un problème reste discret', [], [
                'Le nom, l’indicatif, le surnom et le matricule se lisent l’un sous l’autre, avec l’unité juste en dessous',
                'Les pastilles (compte, habilitation, déploiement) restent sur une ligne lisible',
            ], [
                'La photo de compte est collée au portrait opérateur, dans le même cadre',
                'Le signalement d’un problème n’occupe plus toute la largeur du bandeau',
                'Les onglets de votre espace (compétences, formations, compte) n’apparaissent plus sur la fiche d’un autre membre',
                'À l’arrivée sur une fiche, les annonces, le bandeau et le choix de vue s’enchaînent sans grand vide blanc',
            ], ['personnel'], [
                'Ouvrez une fiche depuis l’annuaire. Aucune action n’est demandée : le bandeau se lit simplement plus clairement.',
            ], 'Portail 1.5.95'),
            $pr(275, '2026-09-01', 'La tablette ATAK se lit comme un vrai poste de terrain', 'Sur la tablette IceMan, la carte passe en charbon et cyan : grille, distance, cap et unité suivie se lisent d’un coup d’œil, sans recouvrir le tiroir d’applications', [
                'Sous le curseur : grille, distance, altitude du sol, gisement, portée et écart d’altitude',
                'Sur l’unité suivie : groupe, indicatif, grille, altitude, vitesse et heure',
                'Cap en degrés vrais en haut à gauche, et zoom plus / moins sur le bord de la carte',
            ], [
                'Le tiroir d’applications, Drone Ops et les fenêtres caméra déjà présentes reprennent le même charbon / cyan',
            ], [], ['atak'], [
                'Rechargez le pack jeu Overwatch, puis relancez Arma complètement. Ouvrez la tablette ATAK Enhanced : les cartouches sont sur la carte, jamais sur le tiroir de droite.',
            ], 'Overwatch 1.4.97 · Athena 1.0.58'),
            $pr(274, '2026-09-01', 'Les icônes de la communauté se règlent depuis le poste', 'Dans Réglages du poste, on voit les icônes en vigueur ; le gestionnaire ouvre la bibliothèque pour en choisir ou en ajouter', [
                'Le panneau Réglages du poste montre les icônes choisies pour les opérateurs, les véhicules, les aéronefs et les téléphones',
                'Le gestionnaire poursuit vers la bibliothèque de la communauté : envoi d’une image, ou choix parmi celles déjà présentes',
            ], [
                'Les calques villes et routes, et la vue en relief, se lisent en langage de poste',
            ], [], ['atak'], [
                'Ouvrez la carte, puis Réglages du poste. Les icônes de la communauté sont sous Apparence de la carte. Le gestionnaire peut les changer dans la bibliothèque, puis recharger la carte.',
            ], 'Portail 1.5.95'),
            $pr(273, '2026-09-01', 'Les fiches jumelles occupent enfin le bureau', 'La page des dossiers identiques se lit comme les rôles : indicateurs, réglage compact, puis un vrai panneau de résultat', [
                'Les champs à surveiller se choisissent sur des cartes courtes, collées au texte, pas sur des bandes trop larges',
                'Le résultat — aucune fiche jumelle, ou les groupes à relire — tient un panneau à côté du réglage',
            ], [
                'La détection active ou en pause, le nombre de critères et les groupes à traiter se lisent d’emblée',
            ], [], ['personnel'], [
                'Ouvrez Bureau effectifs, puis Fiches jumelles. Cochez ce qui ne doit jamais se répéter, enregistrez, et relisez le panneau résultat.',
            ], 'Portail 1.5.95'),
            $pr(278, '2026-09-01', 'Corriger sa fiche se lit enfin', 'Le signalement d’anomalie reprend la présentation du dossier, et le prénom, le nom et la présentation du personnage peuvent être proposés', [
                'Le formulaire de correction RH se lit comme le reste du dossier : titre visible, champs clairs, sans trou dans la grille',
                'Le membre peut proposer le prénom, le nom, la présentation, les indicatifs secondaires, les autres surnoms, la fonction du dossier et l’échéance de visite médicale',
                'Le groupe sanguin, le sexe, la situation familiale et le statut opérateur se choisissent dans une liste',
            ], [], [], ['personnel'], [
                'Ouvrez votre fiche, puis Signaler un problème. Proposez les corrections, envoyez : rien n’est écrit tant qu’un organisateur n’a pas confirmé.',
            ], 'Portail 1.5.95'),
            $pr(272, '2026-09-01', 'Itinéraire du poste vers les opérateurs', 'L’outil Route du poste trace un itinéraire : les opérateurs le voient en jeu, avec les points déjà atteints', [
                'Depuis la barre d’outils de la carte, Route pose des points de passage numérotés',
                'Une fois transmis, l’itinéraire apparaît sur la carte des opérateurs en mission',
                'Les points déjà atteints passent en gris sur le poste comme en jeu',
            ], [], [], ['atak'], [
                'Rechargez la carte du poste. Tracez au moins deux points, puis transmettez. En jeu : pack Overwatch déjà à jour pour le guidage.',
            ], 'Portail 1.5.95'),
            $pr(271, '2026-09-01', 'Les fiches terminal affichent le pack en clair', 'Dans Terminaux, l’état de liaison reste calé à droite, et la version Overwatch se lit sur sa propre ligne', [
                'La version Overwatch (et la liaison Athena si elle est connue) apparaît sous le type d’appareil, plus collée dans la même phrase',
            ], [], [
                'Le bandeau Hors liaison ne saute plus quand l’indicatif est court ou long',
            ], ['atak'], [
                'Ouvrez la carte du poste, puis l’onglet Terminaux. Rechargez la page. Pas besoin d’un nouveau pack jeu.',
            ], 'Portail 1.5.95'),
            $pr(270, '2026-09-01', 'La page des rôles a plus d’air', 'Dans le bureau effectifs, la liste des rôles n’est plus collée : cartes, indicateurs et boutons se lisent sans se serrer', [], [
                'Les indicateurs (nombre de rôles, communauté, intra-unité) ont plus d’espace entre le libellé et la valeur',
                'Le bandeau de pilotage et les cartes de rôles ont davantage de marge intérieure, et les cartes sont plus écartées les unes des autres',
                'Le rappel « Deux couches, un même principe » n’est plus collé au texte',
            ], [], ['personnel'], [
                'Ouvrez Bureau effectifs, puis Rôles. Aucune action n’est demandée : la page se parcourt simplement plus confortablement.',
            ], 'Portail 1.5.94'),
            $pr(269, '2026-09-01', 'La charte des formations se lit comme un vrai document', 'La page d’engagement rejoint l’espace compte : lecture plus claire, parcours, puis confirmation', [
                'La charte s’affiche dans l’espace personnel, avec la durée de lecture et l’état de confirmation',
                'Une barre de parcours suit la lecture ; la case s’active une fois le texte parcouru',
            ], [
                'Après confirmation, le texte reste relisible, et le catalogue des formations est à un clic',
            ], [], ['personnel'], [
                'Ouvrez Mon compte, puis Charte des formations. Lisez jusqu’en bas, cochez, enregistrez. Le catalogue s’ouvre ensuite.',
            ], 'Portail 1.5.94'),
            $pr(268, '2026-09-01', 'Les pièces RH se déposent dans le coffre', 'Sur Documents RH, vous pouvez désormais déposer le fichier, pas seulement indiquer où le retrouver', [
                'Dépôt d’une pièce (PDF, image ou document Word) dans le coffre du dossier',
                'Ouverture depuis le registre, réservée à l’état-major — et au membre si la pièce lui est visible',
            ], [
                'Vous pouvez toujours indiquer un emplacement si le document n’est pas déposé ici',
            ], [], ['personnel'], [
                'Ouvrez Effectifs, puis Documents RH. Choisissez le membre, déposez le fichier, puis ajoutez-le au dossier.',
            ], 'Portail 1.5.93'),
            $pr(267, '2026-09-01', 'Les fiches jumelles se lisent comme le reste du bureau', 'La page des dossiers identiques reprend le langage du bureau effectifs : synthèse, réglage, puis liste à traiter', [
                'Synthèse : détection active ou en pause, champs retenus, groupes à relire',
                'Chaque champ se choisit sur une carte, avec une phrase d’aide',
            ], [
                'Les fiches jumelles s’ouvrent directement dans le bureau, pas sur une fiche isolée',
            ], [], ['personnel'], [
                'Ouvrez Bureau effectifs, puis Fiches jumelles. Cochez ce qui ne doit jamais se répéter, enregistrez, et relisez les groupes s’il y en a.',
            ], 'Portail 1.5.91'),
            $pr(266, '2026-09-01', 'Le grade et le portrait opérateur se lisent enfin', 'Dans les effectifs, le grade affiché est celui attribué par la communauté, et la photo est le portrait opérateur', [
                'Le grade du tableau et de la fiche reprend le grade attribué (par exemple Colonel), pas le rôle d’administration',
                'Le portrait opérateur s’affiche dans le tableau des effectifs',
            ], [], [
                'Un code court personnalisé ne remplace plus le grade dans la colonne Grade',
            ], ['personnel'], [
                'Ouvrez Effectifs. Le grade est celui du dossier, la photo est celle du portrait opérateur. Le rôle d’accès reste une information à part.',
            ], 'Portail 1.5.91'),
            $pr(265, '2026-09-01', 'Les tenues se rangent en collections', 'La page Équipement devient un catalogue : collections, photos de présentation, et tenues envoyées depuis l’arsenal', [
                'Collections de tenues, avec une photo de présentation',
                'Chaque tenue peut recevoir sa propre photo, une note, et une collection',
                'Qui peut s’en servir : vous, votre unité, ou toute la communauté',
            ], [
                'Les fiches matériel restent accessibles depuis la même page',
            ], [], ['platform'], [
                'Ouvrez Équipement. Créez une collection, ajoutez une photo, rangez-y les tenues envoyées depuis l’arsenal. En jeu, le bandeau Athena en haut de l’arsenal envoie et récupère ces tenues.',
            ], 'Portail 1.5.90'),
            $pr(264, '2026-09-01', 'L’opération devient le dossier de mission', 'Plan, renseignement, ordres et vue terrain se retrouvent dans un même espace : la carte ne montre plus que ce que le commandement a publié', [
                'Espace opérationnel : synthèse, plan, objectifs, ordres, personnel et tâches, rattachés à une même mission',
                'Graphiques de manœuvre, d’appuis et de mesures de contrôle, posés sur le plan',
                'Calques en brouillon, en revue, approuvés, puis publiés sur la vue terrain — y compris en session',
                'Vue terrain simplifiée sur téléphone, avec les tâches de la phase en cours',
            ], [
                'Le poste de situation reste la représentation temps réel du plan, pas un outil séparé',
                'Un ordre d’opération cite un calque : s’il a évolué depuis, Athena signale l’annexe périmée',
            ], [], ['atak'], [
                'Ouvrez Commandement, puis Opérations. Créez un espace, posez les graphiques sur le plan, publiez uniquement ce que les opérateurs doivent voir. La vue terrain et la session reçoivent ces calques publiés.',
            ], 'Portail 1.5.89'),
            $pr(263, '2026-09-01', 'Connexion Athena avant d’entrer en session', 'Le pack Overwatch s’ouvre sur votre compte Athena : votre communauté, votre indicatif et vos habilitations arrivent tout seuls', [
                'Fenêtre de connexion Athena au lancement : e-mail, code temporaire, ou Steam déjà associé à ce poste',
                'Chargement réel de l’environnement : identité, communauté, profil, puis services Overwatch',
                'Personnalisation de la fenêtre en jeu depuis le back-office (image, message, fonctions autorisées)',
            ], [
                'La communauté n’est plus une saisie dans les réglages du jeu : Athena la choisit d’après votre compte',
                'Le suivi, la messagerie et le renseignement ne partent qu’une fois l’environnement prêt',
            ], [], ['atak'], [
                'Relancer Arma après le pack Overwatch 1.5.0. Au menu, identifiez-vous. En session, rien n’est transmis tant que la fenêtre n’affiche pas l’environnement prêt.',
            ], 'Overwatch 1.5.0 · Extension 1.18.0'),
            $pr(262, '2026-09-01', 'Tablette, Zeus et arsenal : plus de recouvrement', 'La tablette ATAK Enhanced se charge à nouveau, et les boutons COMSPEC ne recouvrent plus les fenêtres Zeus ni la liste des tenues', [
                'Tablette ATAK Enhanced compatible avec le pack actuel',
                'Bandeau des tenues Athena en haut de l’arsenal : toute la communauté, sans masquer la liste locale',
                'Barre SSE, ATAK et Overwatch au-dessus du titre, sur une personne ou un véhicule seulement',
            ], [
                'Les tenues enregistrées par les membres se lisent dans l’arsenal et sur le portail',
                'Les mêmes actions restent disponibles au clic droit Zeus',
            ], [
                'Les boutons COMSPEC recouvraient les filtres Zeus, le titre de la fiche, et la liste des tenues à l’arsenal',
            ], ['atak'], [
                'Recharger le pack jeu Overwatch 1.4.96, puis relancer Arma complètement. Double-clic une personne : la barre est au-dessus du titre. Les autres écrans Zeus n’affichent plus ces boutons.',
            ], 'Overwatch 1.4.96 · Athena 1.0.57'),
            $pr(261, '2026-09-01', 'Administration du site, enfin complète', 'Le centre opérateur du site liste tous les outils de gestion, et chaque communauté s’administre depuis une fiche : nom, profil d’outils et formule d’accès', [
                'Carte complète des écrans d’administration du site : communautés, comptes, communication, référentiels et exploitation',
                'Fiche d’une communauté : identité, profil Complet / Effectifs / carte ATAK, et formule d’accès',
                'Menu latéral et raccourcis alignés sur tous les outils déjà disponibles',
            ], [], [], ['personnel'], [
                'Ouvrez Administration du site. L’annuaire des communautés mène à une fiche complète. Les tuiles du tableau de bord couvrent l’ensemble des réglages du site.',
            ], 'Portail 1.5.88'),
            $pr(260, '2026-09-01', 'Catalogue : tout administrer, tout retracer', 'Le catalogue d’organisation devient un poste de commandement : administration de la structure, actions sur vos modèles, et journal complet de chaque application', [
                'Tuiles pour administrer unités, grades, fonctions, rôles et droits, avec le volume actuel',
                'Fiche complète d’un modèle : organigramme, fonctions, rôles, et actions (renommer, actualiser, retirer, restaurer)',
                'Journal de toutes les applications : qui, quand, ce qui a été ajouté, ce qui était déjà en place',
            ], [], [], ['personnel'], [
                'Ouvrez Structure et effectifs, puis Catalogue de l’organisation. Les tuiles du haut mènent à l’administration. Le journal complet liste chaque copie de modèle.',
            ], 'Portail 1.5.87'),
            $pr(259, '2026-09-01', 'Catalogue de l’organisation', 'Des modèles officiels d’organigramme, de grades, de fonctions et de rôles se copient dans votre communauté, sans rien partager avec une autre', [
                'Page Catalogue dans le back-office, avec aperçu chiffré puis application de ce qui manque',
                'Deux modèles officiels : compagnie d’infanterie légère, et communauté gaming',
                'Enregistrement d’un modèle privé à partir de l’organisation actuelle',
                'Option « Démarrer avec un modèle » à la création d’une communauté',
            ], [], [], ['personnel'], [
                'Ouvrez Structure et effectifs, puis Catalogue de l’organisation. Choisissez un modèle, lisez l’aperçu, puis appliquez. Rien de déjà en place n’est écrasé.',
            ], 'Portail 1.5.86'),
            $pr(258, '2026-09-01', 'Dossier RH plus lisible', 'Documents, mobilité, vivier et alertes du bureau effectifs s’ouvrent avec un briefing, des tuiles et des aides au survol', [
                'En-tête de page avec le volume à suivre, et tuiles pour filtrer ou ouvrir le bon registre',
                'Listes déroulantes et boutons plus nets, avec une courte explication au survol des champs importants',
            ], [], [], ['personnel'], [
                'Ouvrez Effectifs, puis Documents RH, Mobilité, Vivier ou Alertes. Survolez le i à côté d’un libellé pour lire l’aide.',
            ], 'Portail 1.5.85'),
            $pr(257, '2026-09-01', 'Signaler une anomalie depuis le tableau de bord', 'Chaque membre peut transmettre un dysfonctionnement à la gestion de l’organisation, depuis une tuile du tableau de bord', [
                'Tuile Signaler une anomalie sur le tableau de bord, ouverte à tous les membres de la communauté',
                'Le message arrive à la gestion de l’organisation, avec un accusé de réception',
            ], [], [], ['personnel'], [
                'Ouvrez le tableau de bord. La tuile Signaler une anomalie se trouve à gauche, et aussi sous votre identité.',
            ], 'Portail 1.5.84'),
            $pr(256, '2026-08-31', 'Fiche membre et compte, enfin alignés', 'La fiche Effectifs et le compte d’un membre se complètent, avec les mêmes actions RH à portée de main', [
                'Fiche Effectifs : identité, ancienneté, unité, rôles, grade, statut et départ sur un même écran',
                'Passage direct entre fiche Effectifs, compte et dossier personnel',
                'Ancienneté saisissable aussi depuis le compte',
            ], [], [], ['personnel'], [
                'Ouvrez un membre depuis Effectifs. Les trois onglets en tête de page mènent à la fiche, au compte et au dossier.',
            ], 'Portail 1.5.83'),
            $pr(255, '2026-08-31', 'Ancienneté réelle de l’organisation et des membres', 'L’ancienneté tient compte de la création de l’unité et de l’arrivée des membres avant le site, y compris depuis Effectifs', [
                'Date de création de l’organisation, même antérieure à Athena, enregistrée pour tous les membres',
                'Saisie de l’arrivée avant le site depuis le tableur Effectifs et la fiche membre',
                'L’ancienneté affichée reprend la date la plus ancienne (communauté ou avant le site)',
            ], [], [], ['personnel'], [
                'Ouvrez Effectifs. Renseignez la création de l’organisation, puis l’arrivée réelle de chaque membre qui était là avant le site.',
            ], 'Portail 1.5.82'),
            $pr(254, '2026-08-31', 'Recherche complète dans le back-office', 'La barre de recherche du back-office trouve les pages, les membres, les documents et les manœuvres', [
                'Raccourci Ctrl ou Cmd + K pour ouvrir la recherche',
                'Résultats limités à ce que vous avez le droit de voir',
            ], [], [], ['personnel'], [
                'Depuis le back-office, ouvrez la recherche en haut de page. Tapez un nom, un indicatif ou le titre d’une page.',
            ], 'Portail 1.5.82'),
            $pr(253, '2026-08-31', 'Aperçu des annonces et plusieurs emplacements', 'Lors de la création d’une annonce, chaque emplacement se prévisualise, et plusieurs emplacements peuvent être combinés', [
                'Aperçu visuel de chaque type et de chaque emplacement, plus un aperçu avec le titre saisi',
                'Une même annonce peut s’afficher à la fois en bandeau, sous le menu et en fenêtre',
            ], [], [], ['platform'], [
                'Rechargez la page de création d’annonce. Cochez les emplacements voulus : l’aperçu se met à jour en direct.',
            ], 'Portail 1.5.81'),
            $pr(252, '2026-08-31', 'Ancienneté dans le tableau rapide des effectifs', 'Le tableau rapide des effectifs affiche l’ancienneté dans la communauté, à côté du temps en mission', [
                'Colonne Ancienneté sur le tableau rapide des effectifs, comme sur la fiche',
            ], [], [], ['personnel'], [
                'Rechargez le tableau de bord. L’ancienneté reprend la date d’arrivée dans la communauté, ou à défaut la date d’engagement du dossier.',
            ], 'Portail 1.5.80'),
            $pr(251, '2026-08-31', 'Profils à compléter dès l’aperçu', 'Le tableau de bord liste les membres auxquels il manque une fonction, un grade, un rôle, une image opérateur, ou dont l’absence n’est pas indiquée', [
                'Sur l’aperçu du back-office, un tableau rapide des dossiers incomplets, comme le tableur des effectifs',
                'Filtres par fonction, grade, rôle, image opérateur et absence non indiquée',
            ], [], [], ['personnel'], [
                'Rechargez le tableau de bord du back-office. Chaque ligne ouvre la fiche du membre pour corriger le dossier.',
            ], 'Portail 1.5.79'),
            $pr(250, '2026-08-30', 'Itinéraire GPS et zones du poste en jeu', 'Les points d’un itinéraire et les zones posées au poste se voient en mission', [
                'Points numérotés et trait d’itinéraire visibles en jeu',
                'Zones du poste (poser, danger, ralliement) visibles en mission',
                'Alerte à l’entrée d’une zone dangereuse',
            ], [
                'Un opérateur inconscient ou hors combat apparaît dans la liste d’alerte',
            ], [], ['atak'], [
                'Mise à jour du pack jeu Overwatch 1.4.95. Relancer Arma complètement, pas seulement la mission.',
            ]),
            $pr(249, '2026-08-27', 'Symboles de la communauté, section IA, boutons Zeus', 'Le gestionnaire choisit les icônes de la carte ; une section IA propose chef ou tout le groupe ; SSE ATAK Overwatch s’ouvrent à nouveau depuis l’édition Zeus', [
                'Bibliothèque d’icônes : envoi d’une image ou choix dans celles déjà présentes, mémorisé pour la communauté',
                'IA alliée dans une section : chef seulement, ou toute la section',
                'Les fiches de renseignement portent 17 thèmes opérationnels colorés (terrorisme, armement, mouvements, etc.), un objet, un recueil et quatre degrés d’urgence',
            ], [
                'Une IA qui s’éloigne reste sur la carte à sa dernière position connue',
                'La géolocalisation téléphone utilise un pin smartphone, sur le poste et dans l’ATAK en jeu',
                'Les photos terrain relancent une capture si l’image n’est pas trouvée sur le poste de jeu',
                'La photo de visage SEEK va sur la fiche, plus vers le canal reconnaissance',
                'Une grande photo terrain ne sature plus le poste à l’envoi',
                'Faire vibrer un terminal lève l’écran « liaison perdue » simulé (pas un brouillage Zeus)',
                'Depuis le back-office, un bouton ramène au tableau de bord du portail',
                'Sur le poste, la communauté affichée se lit et se change (compte dans plusieurs organisations)',
                'Sur le téléphone ATAK, Accepter et Refuser restent visibles ; les textes Athena se lisent à nouveau',
                'À l’acceptation d’une candidature, le pseudo de la personne qui valide n’est plus recopié sur le dossier du nouveau membre',
                'Changer de communauté depuis le tableau de bord ouvre le poste de celle choisie, et les positions du jeu s’y lisent',
                'L’écran Contexte du terminal SEEK occupe toute la vitre : champs plus grands, couleurs plus contrastées',
                'Sur le terminal SEEK, ADN, iris et empreintes demandent le kit d’exploitation correspondant ; l’appareil se ferme le temps du relevé puis se rouvre',
                'Dans INTEL, Fiches et Personnes ont chacun une couleur (ambre / cyan) pour se lire d’un coup d’œil',
                'Sur une fiche personne, photo, empreintes, iris et qualité n’affichent plus de valeurs inventées : seulement ce que le terminal a vraiment transmis',
                'Le journal des transmissions se télécharge en PDF et peut être relayé vers un ou plusieurs salons Discord',
                'Les opérateurs inconscients et hors combat apparaissent dans la liste d’alerte du téléphone ATAK, avec leur position',
                'L’administration du site affiche l’occupation du disque et permet de vider les historiques volumineux, sans toucher aux comptes ni aux communautés',
                'Sur le dossier, prénom, nom et présentation courte décrivent le personnage, et non plus une identité hors jeu',
                'En haut du site, le code de grade suit le libellé court saisi sur le dossier personnage',
                'Dans le bureau effectifs, une demande d’élévation s’ouvre au centre de l’écran : le grade proposé n’est plus coupé par le tableur',
                'Les travaux récurrents du site (escalade des rapports, rappels, nettoyage) partent toutes les cinq minutes, y compris sans clic sur la page d’administration',
                'Sur Intégrations, chaque type d’événement (candidatures, effectifs, opérations, formation, modération, renseignement) a son relais Discord, en plus du salon par défaut et des transmissions terrain',
                'Un même parcours proposé à toute la plateforme et à la communauté n’apparaît plus deux fois au catalogue ni sur le tableau de bord',
            ], [
                'Les boutons SSE, ATAK et OVERWATCH du panneau d’édition Zeus n’ouvraient plus rien',
                'L’escalade des rapports tactiques échouait à chaque passage automatique',
                'Un nom et un prénom saisis dans le profil SSE Zeus (génération automatique) n’étaient pas repris par l’identité, alors que le terminal les affichait',
                'L’inconscience et la mort ne remplissaient pas la liste d’alerte du téléphone ATAK',
                'Sur l’écran Sons du téléphone ATAK, les boutons n’avaient plus de fond : seul le texte restait visible',
                'Les cartes « Nos formations » en double quand le parcours était visible pour toute la plateforme et pour la communauté',
                'À la connexion, le mot de passe s’affichait en clair au lieu de points',
                'Dans Affichage, inclinaison, amplification du relief et volumes du jeu ne changeaient rien sur la carte',
            ], ['atak', 'personnel'], [
                'Rechargez la carte. Pour Zeus, les photos, Vibrer, les boutons d’ordre sur le téléphone, le suivi IA, les thèmes des fiches, le nom saisi sur une identité SSE, l’écran du terminal SEEK, les relevés biométriques avec kit, la liste d’alerte (inconscients / hors combat) et l’écran Sons : nouveau pack Overwatch, puis relancer Arma complètement. Pour le journal des transmissions (PDF et Discord) : rechargez le bureau SSE. Pour l’espace disque du serveur : administration du site, pas le back-office communauté. Pour le grade affiché en haut du site, la demande d’élévation du bureau effectifs, les tâches automatiques, les relais Discord et le bandeau des formations : rechargez le portail. Les relais se règlent dans Intégrations (back-office). Sur le serveur, installez le passage automatique depuis la page Tâches automatiques (administration du site). Pour le mot de passe à la connexion : rechargez la page de connexion. Pour le relief et les volumes en vue inclinée : rechargez la carte, ouvrez Affichage, puis changez la vue, l’inclinaison et l’amplification ; les bâtiments apparaissent dès qu’ils sont déjà sur le poste, même si l’ombrage n’est pas encore arrivé.',
            ], 'Portail 1.5.64 · Overwatch 1.4.94 · Athena 1.0.56'),
            $pr(248, '2026-08-27', 'Le poste ne se fige plus pour un engin isolé', 'Un suivi d’engin indisponible ne coupe plus toute la carte : les effectifs et les pastilles continuent de se mettre à jour', [], [
                'Le bandeau « le poste n’atteint pas ses données » ne revient plus en boucle si seul le suivi des engins est en panne',
                'Les pastilles d’effectifs et de liaison restent à jour pendant ce temps',
            ], [
                'Un refus sur les engins faisait croire que tout le poste était coupé, toutes les quelques secondes',
            ], ['atak'], [
                'Rechargez la carte du poste. Pas besoin d’un nouveau pack jeu.',
            ], 'Portail 1.5.52'),
            $pr(247, '2026-08-27', 'Relief, bâtiments et mer sans plaques blanches', 'En vue inclinée, le sol se soulève à nouveau, les volumes du jeu se voient, et une tuile manquante n’ouvre plus un rectangle blanc', [], [
                'L’exagération du relief déforme bien le théâtre déjà relevé',
                'Bâtiments et forêts déjà reçus se dessinent sur la carte, y compris en vue d’ensemble',
                'Une case de mer sans fond de carte reste sombre, plus une plaque blanche',
            ], [
                'La carte s’inclinait mais restait plate, malgré des milliers de volumes déjà au poste',
            ], ['atak'], [
                'Rechargez la carte du poste (vidage du cache du navigateur si l’ancienne vue reste). Pas besoin d’un nouveau pack jeu.',
            ], 'Portail 1.5.51'),
            $pr(246, '2026-08-27', 'Effectifs : unités expliquées, identité claire', 'Survoler une affectation indique sa place dans l’organigramme ; le nom de compte ne se confond plus avec un personnage', [], [
                'Info-bulle sur l’affectation : chemin dans l’organigramme, code d’unité et présentation',
                'Le nom affiché du compte reste principal ; le personnage n’apparaît que s’il est différent',
            ], [
                'Un mauvais nom de scène ne fait plus croire qu’on ouvre le dossier d’un autre membre',
            ], ['personnel', 'command'], [
                'Si un dossier a encore un mauvais nom de personnage, corrigez-le sur la fiche d’édition.',
            ], 'Portail 1.5.50'),
            $pr(245, '2026-08-27', 'Messages d’erreur plus clairs', 'Connexion, accès et fiches absentes se comprennent sans jargon', [], [
                'Un besoin de connexion affiche « Connexion requise », plus un faux refus d’accès',
                'Une fiche personnel absente ouvre une page claire avec retour à l’annuaire',
                'Les pages d’erreur du portail indiquent quoi faire ensuite',
            ], [
                'Les titres des notifications ne confondent plus session, connexion et refus d’accès',
            ], ['command', 'personnel'], [
                'Aucune action côté opérateurs ATAK. Rechargez le portail pour voir les nouveaux libellés.',
            ], 'Portail 1.5.50'),
            $pr(244, '2026-08-27', 'Portail missions au poste', 'Missions, participants, état des communications et liaisons se lisent depuis le back-office', [
                'Un portail missions regroupe les plans, le cycle de mission, les effectifs affectés et l’état de liaison ATAK',
                'Chaque mission ouvre un récapitulatif avec progression, participants, communications et liaisons',
            ], [
                'La navigation Opérations pointe vers ce portail, à côté de la planification',
            ], [], ['command', 'atak'], [
                'Ouvrez Administration → Opérations → Portail missions. Pas besoin d’un nouveau pack jeu.',
            ], 'Portail 1.5.49'),
            $pr(242, '2026-08-26', 'Le menu d’actions ne s’empile plus à l’écran', 'Ouvrir les actions personnelles n’affiche plus la même liste recopiée sur toute la hauteur', [], [], [
                'Après un retour au combat, chaque action (Overwatch, photo, carte, santé) n’apparaît plus des dizaines de fois les unes sous les autres',
            ], ['atak'], [
                'Relancez Arma avec le pack jeu Overwatch à jour. Une simple relance de mission ne suffit pas.',
            ], 'Portail 1.5.48 · Overwatch 1.4.86'),
            $pr(243, '2026-08-26', 'La barre d’outils et Affichage se lisent à nouveau', 'Les commandes restent sous chaque intitulé, et l’inventaire du relief se lit ligne par ligne', [], [
                'Position, Annoter, Tracer, Analyse et Vue montrent leurs boutons, sans menu compact',
                'Affichage, Personnaliser et Masquer restent sur une seule ligne',
                'Dans Affichage, chaque donnée du poste a son état à droite ; l’ombrage suit la couverture déjà affichée',
            ], [
                'On ne voyait que les intitulés, et l’inventaire du relief collait les libellés aux états',
            ], ['atak'], [
                'Rechargez la carte du poste. Les boutons Grille, Mesurer, Trait et les autres reviennent sous chaque groupe. Pas besoin d’un nouveau pack jeu.',
            ], 'Portail 1.5.48'),
            $pr(241, '2026-08-26', 'La croix de perte de liaison reste discrète', 'Un contact silencieux se signale par une petite marque en coin : le symbole de l’unité reste lisible', [], [
                'La croix de perte de liaison est une petite marque d’angle, plus un grand X sur le symbole',
            ], [
                'La croix recouvrait tout le symbole de l’unité, jusqu’à le rendre illisible',
            ], ['atak'], [
                'Rechargez la carte du poste. Pas besoin d’un nouveau pack jeu.',
            ], 'Portail 1.5.48'),
            $pr(240, '2026-08-26', 'La carte Kimmirut est disponible au poste', 'Le théâtre Kimmirut peut être choisi sur la carte du poste, comme Altis ou Malden', [
                'Kimmirut dans la liste des cartes du poste',
            ], [], [], ['atak'], [
                'Rechargez la carte du poste, puis choisissez Kimmirut. Pas besoin d’un nouveau pack jeu.',
            ], 'Portail 1.5.48'),
            $pr(239, '2026-08-26', 'La vue inclinée montre enfin le relief', 'Passer en vue inclinée ou 3D actif soulève le sol déjà relevé : les hauteurs se voient, sans case cachée à cocher', [
                'Le sol suit l’altitude relevée dès que la vue est inclinée, y compris si Relief et profondeur est décoché',
            ], [
                'L’affichage ne dit plus que le théâtre n’est pas relevé quand l’ombrage est déjà là',
            ], [
                'La carte s’inclinait, mais le sol restait plat comme une photo',
            ], ['atak'], [
                'Rechargez la carte du poste, choisissez Vue inclinée, poussez l’exagération. Pas besoin d’un nouveau pack jeu.',
            ], 'Portail 1.5.48'),
            $pr(238, '2026-08-26', 'La fiche de mise à jour s’ouvre à nouveau', 'Ouvrir un bulletin du journal ne tombe plus en incident : le titre de la fiche s’affiche, que l’on vienne du journal ou d’un lien direct', [], [], [
                'La fiche d’un bulletin (mise à jour, compte rendu) s’ouvrait sur une page d’incident au lieu du texte',
            ], ['platform'], [
                'Rechargez Nouveautés, puis ouvrez le bulletin. Pas besoin d’un nouveau pack jeu.',
            ], 'Portail 1.5.48'),
            $pr(237, '2026-08-26', 'Affichage compte les bâtiments et forêts déjà reçus', 'Dans Affichage, les volumes déjà arrivés au poste se lisent en nombre, au lieu d’un faux « pas encore sur le poste »', [], [
                'Si le décompte n’est pas lisible un instant, le poste le dit clairement, sans faire croire que le relevé n’est pas arrivé',
            ], [
                'Les bâtiments et les forêts déjà en base s’affichaient comme absents, alors que l’ombrage du relief était bien présent',
            ], ['atak'], [
                'Rechargez la carte du poste. Pas besoin d’un nouveau pack jeu. La case Bâtiments et forêts du jeu reste un choix d’affichage, distinct du compteur du relevé.',
            ], 'Portail 1.5.48'),
            $pr(236, '2026-08-26', 'La carte reste lisible si le poste tousse un instant', 'Une coupure ponctuelle ne vide plus la carte : les lectures reprennent, les pastilles et le relevé restent affichés', [
                'Les lectures (effectifs, marqueurs, relevé, demandes médicales) continuent d’essayer, même après un refus temporaire',
            ], [
                'Le bandeau Différé · mauvaise connexion reste affiché le temps que la liaison se rétablisse',
            ], [
                'Une seule coupure faisait échouer toutes les lectures suivantes, et la carte paraissait vide',
            ], ['atak'], [
                'Rechargez la carte du poste. Pas besoin d’un nouveau pack jeu pour ce correctif. Si Zeus indique que le poste n’a pas encore la vérification du relevé, attendez cette mise à jour du site puis relancez Vérifier et renvoyer.',
            ], 'Portail 1.5.48'),
            $pr(234, '2026-08-26', 'Liaison différée quand la donnée a du mal à passer', 'Si plusieurs envois n’atteignent pas le poste, Overwatch ralentit tout petit à petit, et la carte marque une mauvaise connexion', [
                'Après plusieurs échecs, les envois (position, caméras, relevés, occupants) passent à 45 s, puis 1 min 15, 2 min 30, jusqu’à 10 min',
                'Sur la carte du poste : Différé · mauvaise connexion, au lieu d’une coupure',
            ], [
                'Deux envois réussis baissent d’un cran : on ne reprend pas le rythme normal d’un seul coup',
            ], [
                'Une pause du poste faisait croire que tout le monde était hors liaison',
            ], ['atak'], [
                'Rechargez la carte du poste. Pour le jeu : pack Overwatch 1.4.84, puis relancez Arma complètement. Si la liaison est difficile, attendez : le rythme reprend tout seul dès que ça passe.',
            ], 'Portail 1.5.48 · Overwatch 1.4.84 · liaison 1.17.7'),
            $pr(235, '2026-08-26', 'L’écran « Liaison perdue » reprend le look du poste', 'Quand la liaison tombe, un panneau sobre indique la reconnexion, sans l’ancien habillage ni deux compte à rebours', [], [
                'Un seul message : Liaison perdue, puis Reconnexion dans quelques secondes',
            ], [
                'L’ancien habillage recouvrait le terminal, avec deux durées de reconnexion différentes',
            ], ['atak'], [
                'Rechargez la carte du poste. En session, un nouveau pack est nécessaire pour le terminal dans Arma.',
            ], 'Portail 1.5.48'),
            $pr(233, '2026-08-26', 'Les signalements du jeu restent discrets si le poste est occupé', 'Quand le poste est en mise à jour, Overwatch reçoit un refus temporaire au lieu d’un écran d’incident', [], [], [
                'Un signalement d’erreur en jeu ouvrait une page technique illisible, au lieu d’un simple refus à réessayer plus tard',
            ], ['atak'], [
                'Mise à jour du portail uniquement : rien à recharger en session. Si un signalement échoue pendant une mise à jour, réessayez quelques instants plus tard.',
            ], 'Portail 1.5.48'),
            $pr(232, '2026-08-26', 'Les terminaux restent sur la carte, un bandeau pour le tchat', 'Les opérateurs encore en liaison ne clignotent plus, et un message radio s’annonce à l’écran', [
                'Un bandeau discret quand un message arrive : indicatif et texte, ou le nombre s’il en arrive plusieurs d’un coup',
            ], [
                'Le premier chargement du journal radio ne déclenche plus de bandeau pour l’historique déjà là',
            ], [
                'Les pastilles des terminaux encore en liaison disparaissaient un instant puis revenaient',
            ], ['atak'], [
                'Rechargez la carte du poste. Les opérateurs en liaison restent visibles même si le poste reprend son souffle. Un message reçu affiche un bandeau ; le mode silencieux coupe le son, pas le bandeau.',
            ], 'Portail 1.5.48'),
            $pr(231, '2026-08-26', 'Les modules carte se posent jusqu’au bout', 'La mise à jour du poste installe rapports, points d’intérêt, zones et analyses sans s’arrêter en cours de route', [], [], [
                'Sur certaines installations, une partie des modules carte n’était pas posée, avec le même avertissement répété des dizaines de fois',
            ], ['atak', 'platform'], [
                'Mise à jour du portail uniquement : rien à recharger en session. Relancer une fois la mise à jour du poste suffit ; si elle a déjà tourné, elle ne casse rien.',
            ], 'Portail 1.5.48'),
            $pr(230, '2026-08-26', 'Affichage dit ce que le poste a déjà reçu', 'Dans Apparence de la carte, on voit si l’ombrage, le relevé, les bâtiments et les forêts sont bien présents', [
                'Sous la couverture du relief : présence de l’ombrage, du relevé (pentes, courbes), des bâtiments et des forêts, et la date du dernier relevé',
            ], [], [], ['atak'], [
                'Ouvrez Affichage sur la carte du poste. Les cases plus haut restent des choix d’affichage ; le bloc du bas décrit ce qui est réellement arrivé pour cette carte.',
            ], 'Portail 1.5.48'),
            $pr(229, '2026-08-26', 'La barre d’outils de la carte se clique à nouveau', 'Position, Annoter, Tracer, Analyse et Vue montrent enfin leurs actions', [], [
                'Les commandes restent visibles sous chaque intitulé, sans menu fantôme',
            ], [
                'On voyait les intitulés mais rien ne se cliquait : les actions étaient hors écran, et les libellés ne répondaient pas',
            ], ['atak'], [
                'Rechargez la carte du poste. Les boutons Grille, Mesurer, Trait, Itinéraire et les autres reviennent sous chaque groupe.',
            ], 'Portail 1.5.48'),
            $pr(228, '2026-08-26', 'Vérifier que le relevé est bien arrivé au poste', 'Après un relevé de carte, on contrôle ce que le poste a reçu et on renvoie ce qui manque', [
                'Dans la fenêtre de relevé : Vérifier et renvoyer, pour comparer le jeu et le poste',
            ], [
                'Si des bâtiments, forêts ou portions de relief manquent, le renvoi part tout seul',
            ], [
                'Un relevé affiché comme terminé en jeu n’était pas forcément tout arrivé au poste',
            ], ['atak'], [
                'Pack Overwatch 1.4.83, puis relancez Arma complètement. Ouvrez le relevé de la carte, puis Vérifier et renvoyer.',
            ], 'Portail 1.5.48 · Overwatch 1.4.83 · liaison 1.17.6'),
            $pr(227, '2026-08-26', 'Vue inclinée dans Affichage, pause après un refus d’accès', 'La vue de la carte se règle dans Affichage, et un refus temporaire ne sature plus le poste ni le jeu', [
                'Dans Affichage : vue à plat ou inclinée, amplitude du relief et inclinaison, sans passer par le bouton 3D',
                'Après un refus d’accès, le poste et Overwatch marquent une pause puis reprennent tout seuls',
            ], [
                'Le bouton Vue 3D reste aligné avec le choix Inclinée',
            ], [
                'Les réglages de vue disparaissaient d’Affichage',
                'Un refus d’accès en rafale bloquait le poste et le jeu en même temps',
            ], ['atak'], [
                'Rechargez la carte du poste. Pour le jeu : pack Overwatch 1.4.82, puis relancez Arma complètement. Si le poste reste figé, attendez une minute ou reconnectez-vous.',
            ], 'Portail 1.5.48 · Overwatch 1.4.82 · liaison 1.17.5'),
            $pr(226, '2026-08-26', 'Les caméras ne saturent plus le journal', 'Une coupure sur les caméras casque ne déclenche plus d’alerte, et la position reste prioritaire', [
                'Une caméra injoignable n’écrit plus d’avertissement dans le journal de liaison',
                'Le suivi des unités continue même si le roster caméras n’atteint pas le poste',
            ], [], [
                'Un échec caméra relançait tout de suite le même envoi et remplissait le journal',
            ], ['atak'], [
                'Rechargez le pack jeu Overwatch 1.4.81, puis relancez Arma complètement — pas seulement la mission.',
            ], 'Portail 1.5.48 · Overwatch 1.4.81 · liaison 1.17.4'),
            $pr(225, '2026-08-26', 'Le hub se reconnecte si la base a coupé la session', 'Quand la base a fermé la session, le hub la rouvre tout seul au lieu d’afficher un incident technique', [
                'Le hub reprend silencieusement si la base a coupé la session pendant une pause',
            ], [], [
                'Ouverture du hub après une attente : plus de page d’erreur illisible',
            ], ['platform'], [
                'Si l’accès reste impossible, un message clair invite à réessayer dans quelques instants, sans détail technique.',
            ], 'Portail'),
            $pr(224, '2026-08-26', 'Pack jeu SSE prêt à publier', 'Le pack renseignement SSE s’envoie sur le Workshop comme Overwatch : un dossier propre, sans les fichiers d’atelier', [
                'Un dossier de publication du pack SSE, prêt à envoyer depuis le Publisher Arma 3',
            ], [
                'Même geste que le pack Overwatch : assembler le dossier propre, puis mettre en ligne',
            ], [], ['intel'], [
                'Le pack SSE se charge avec CBA et ACE3. Overwatch reste optionnel pour remonter vers Athena. Relancez Arma complètement après abonnement ou mise à jour.',
            ], 'Pack SSE 0.7.15'),
            $pr(220, '2026-08-26', 'Menus Zeus et éditeur au rendez-vous', 'Les outils COMSPEC réapparaissent dans Zeus Enhanced, et le relevé de carte ne bloque plus le curseur', [
                'Catégories Zeus Enhanced : COMSPEC Roleplay, COMSPEC Outils, COMSPEC SSE — même si l’outil Zeus arrive un peu après le début de mission',
                'Relevé de toute la carte : la fenêtre d’avancement se ferme sans laisser Zeus coincé ; on peut aussi l’ouvrir au clic droit',
            ], [
                'Éditeur : « Au début de la mission » et « Afficher les IA ennemies » s’appliquent même si la liste a enregistré un choix numérique',
            ], [
                'Les menus COMSPEC pouvaient rester vides si Zeus Enhanced n’était pas encore prêt au premier essai',
            ], ['atak'], [
                'Rechargez le pack jeu Overwatch 1.4.80, puis relancez Arma complètement — pas seulement la mission. Athena doit être liée pour le relevé. Zeus : COMSPEC Outils (relevé) et COMSPEC Roleplay (balise, téléphone, IA, contacts ennemis, ATAK joueur). Éditeur : mêmes catégories, plus les cases sur l’unité ou le véhicule.',
            ], 'Portail 1.5.48 · Overwatch 1.4.80'),
            $pr(217, '2026-08-26', 'Contacts ennemis masqués par défaut', 'Les IA ennemies n’apparaissent plus sur la carte tant que Zeus ou l’éditeur ne les a pas demandées', [
                'Les losanges hostiles restent hors de la carte et du journal d’analyse, sauf demande du chef de mission',
                'Tant que Zeus ne les a pas demandés, les contacts ennemis ne sont pas suivis : rien ne part vers le poste, la liaison n’est pas saturée',
                'Zeus : « Afficher les IA ennemies sur la carte » allume le suivi et l’affichage ; masquer les coupe tous les deux',
                'Éditeur : module « Contacts ennemis sur l’ATAK », masqués par défaut ou visibles dès le début',
            ], [
                'Les opérateurs et les IA alliées restent affichés et suivis comme avant',
            ], [], ['atak'], [
                'Rechargez le pack jeu Overwatch 1.4.79, puis relancez Arma complètement — pas seulement la mission. Sur le poste, rechargez la carte. Dans Zeus : COMSPEC Roleplay ou COMSPEC ATAK. Dans l’éditeur : modules COMSPEC Outils.',
            ], 'Portail 1.5.48 · Overwatch 1.4.79'),
            $pr(215, '2026-08-26', 'Photos et position plus fiables', 'Les clichés casque et drone partent, et une coupure réseau n’est plus prise pour une saturation du poste', [
                'Les photos prises au casque ou au drone attendent que le cliché soit vraiment écrit avant de partir vers le poste',
            ], [
                'Le relevé de théâtre et la position de l’équipe ne se marchent plus dessus : la position reste prioritaire',
                'Un flux caméra injoignable n’arrête plus la position ni la fiche de l’appareil',
            ], [
                'Une coupure de liaison faisait croire que le poste était saturé, et les photos partaient trop tôt (cliché encore vide)',
            ], ['atak'], [
                'Rechargez le pack jeu Overwatch 1.4.78 (liaison 1.17.3), puis relancez Arma complètement — pas seulement la mission. Une coupure ou une attente réseau n’est plus annoncée comme une saturation du poste.',
            ], 'Portail 1.5.48 · Overwatch 1.4.78 · liaison 1.17.3'),
            $pr(221, '2026-08-26', 'Tout dégager, indicatifs lisibles en vue inclinée', 'Le poste vide traces et annotations d’un geste, et les icônes restent face à l’écran quand la carte est inclinée', [
                'Un bouton « Tout dégager » dans Position retire traces, traits, croix de perte de liaison, journal d’analyse et fiches hors liaison',
                'Les contacts encore en liaison restent sur la carte',
                'En vue inclinée, les icônes et indicatifs se tiennent face à l’écran, ancrés à la position',
            ], [
                'Les commandes de vue inclinée restent dans Affichage (À plat ou Inclinée, amplitude du relief, inclinaison)',
            ], [], ['atak'], [
                'Pas de nouveau pack jeu. Rechargez la carte du poste. Le bouton est dans Position, à côté de Grille et Suivre. Une confirmation courte précède l’effacement.',
            ], 'Portail 1.5.48'),
            $pr(218, '2026-08-26', 'Tirs et missiles dans le journal d’analyse', 'Le poste voit les départs de coup, les échanges de feu, les impacts et les tentatives de missile', [
                'Le journal d’analyse note qui ouvre le feu, un échange, un impact, un verrouillage ou une tentative de missile',
                'Un clic sur la ligne centre la carte à l’endroit du coup, sans saturer le journal : les rafales sont regroupées',
            ], [], [], ['atak'], [
                'Rechargez le pack jeu Overwatch 1.4.79, puis relancez Arma complètement — pas seulement la mission. Les tirs de l’opérateur lié apparaissent ensuite dans le journal d’analyse, en bas de la carte.',
            ], 'Portail 1.5.48 · Overwatch 1.4.79'),
            $pr(216, '2026-08-26', 'Qui est à bord du véhicule', 'La fiche de l’appareil liste le pilote, le tireur et les passagers', [
                'Ouvrir un hélicoptère ou un véhicule sur la carte montre les personnes à bord, avec leur place',
                'L’onglet Personnel de la fiche indique le nom de l’appareil et le nombre à bord',
            ], [
                'La liste des personnes à bord se met à jour en quelques secondes, pas après une longue attente',
            ], [], ['atak'], [
                'Rechargez le pack jeu Overwatch 1.4.78, puis relancez Arma complètement — pas seulement la mission. Sur la carte, ouvrez la fiche de l’appareil : l’onglet Personnel s’ouvre si des personnes sont à bord.',
            ], 'Portail 1.5.48 · Overwatch 1.4.78'),
            $pr(214, '2026-08-26', 'Relevé de toute la carte depuis Zeus', 'Zeus et l’éditeur parcourent le théâtre : bâtiments, forêts et relief, avec une fenêtre d’avancement', [
                'Un outil Zeus et un module éditeur « Relever la carte du théâtre » parcourent tout le théâtre, pas seulement le voisinage de l’équipe',
                'Une fenêtre indique la durée, le nombre de bâtiments, forêts et portions de relief, le secteur en cours, et la date du dernier relevé',
                'On peut interrompre le parcours ; Zeus reste utilisable pendant le relevé',
            ], [
                'Le relevé local autour de l’équipe (menu ACE) reste disponible pour un complément rapide',
            ], [], ['atak'], [
                'Rechargez le pack jeu Overwatch 1.4.78, puis relancez Arma complètement — pas seulement la mission. Athena doit être liée. Dans Zeus : catégorie COMSPEC Outils. Dans l’éditeur : modules COMSPEC Outils.',
            ], 'Portail 1.5.48 · Overwatch 1.4.78'),
            $pr(213, '2026-08-26', 'Bâtiments et forêts en vue inclinée', 'Autour de l’équipe, les bâtiments et les couverts se dressent sur la carte du poste', [
                'La vue 3D de la carte montre les bâtiments et les forêts relevés autour des opérateurs, de la carte ouverte et de la caméra Zeus',
                'Menu ACE : relever les volumes autour de l’équipe, comme pour le relief',
            ], [
                'Le relevé suit les déplacements : il n’envoie que ce qui a changé autour de vous',
                'Position → Affichage : vue à plat ou inclinée, amplitude du relief et inclinaison, sans passer d’abord par le bouton 3D',
            ], [
                'Le journal Overwatch ne se remplissait plus en boucle pendant la prise de liaison',
                'Le menu molette ACE ne se cassait plus à l’ouverture sur une unité',
                'Le relevé des bâtiments ne cassait plus le script à l’ouverture d’un panneau Zeus',
            ], ['atak'], [
                'Rechargez le pack jeu Overwatch 1.4.76, puis relancez Arma complètement — pas seulement la mission. Sur la carte, ouvrez Position → Affichage : choisissez la vue inclinée et « Bâtiments et forêts du jeu ».',
            ], 'Portail 1.5.48 · Overwatch 1.4.76'),
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
