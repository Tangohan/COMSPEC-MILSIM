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
