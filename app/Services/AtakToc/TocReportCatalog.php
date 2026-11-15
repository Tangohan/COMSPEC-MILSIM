<?php

declare(strict_types=1);

namespace App\Services\AtakToc;

use App\Support\AtakIcemanReportCatalog;
use App\Support\SseUsClassification;

/**
 * Rapports tactiques lus par le poste TOC web : familles, libellés, champs de chaque fiche et lecture des valeurs.
 *
 * Les clés des champs sont celles que le jeu envoie dans structured_data (catalogue de l'app Comptes rendus du
 * COMSPEC ATAK natif, fn_reportTypes.sqf, et app BDA, fn_bdaAction.sqf) et celles des formulaires Iceman
 * (AtakIcemanReportCatalog). Une clé inconnue n'est jamais perdue : elle part dans « Autres informations ».
 *
 * Familles du panneau : BDA, comptes rendus (contact, observation, patrouille, blessé, fin d'opération), SITREP,
 * 9-line (CAS, 5-line, MEDEVAC / CASEVAC) et autres (logistique, engin explosif, NRBC, ordre de conduite, libre).
 */
final class TocReportCatalog
{
    public const FAMILIES = [
        'bda' => 'BDA',
        'cr' => 'Comptes rendus',
        'sitrep' => 'SITREP',
        'nineline' => '9-line',
        'other' => 'Autres',
    ];

    /** Type de rapport tactique => famille. */
    private const TYPE_FAMILY = [
        'BDA' => 'bda',
        'SITREP' => 'sitrep',
        'TIC' => 'cr', 'CONTACT' => 'cr', 'SALUTE' => 'cr', 'SPOTREP' => 'cr', 'PATROLREP' => 'cr', 'DEBRIEF' => 'cr', 'EAGLE_DOWN' => 'cr',
        'LOGREP' => 'other', 'UXO' => 'other', 'NBC1' => 'other', 'FRAGO' => 'other', 'OTHER' => 'other',
    ];

    /** Icône de la carte (toc-tracks.js) par type. */
    private const TYPE_ICON = [
        'BDA' => 'destroy', 'SITREP' => 'report', 'TIC' => 'warning', 'CONTACT' => 'warning', 'SALUTE' => 'eye', 'SPOTREP' => 'eye',
        'PATROLREP' => 'flag', 'DEBRIEF' => 'flag', 'EAGLE_DOWN' => 'medical', 'LOGREP' => 'ammo', 'UXO' => 'mine', 'NBC1' => 'warning',
        'FRAGO' => 'order', 'OTHER' => 'report', 'CAS' => 'plane', 'FIVELINE' => 'plane', 'MEDEVAC' => 'heli',
    ];

    public const PRIORITIES = ['ROUTINE' => 'Routine', 'PRIORITY' => 'Prioritaire', 'IMMEDIATE' => 'Immédiat', 'FLASH' => 'FLASH'];

    /** Statut => [libellé, clos ?]. */
    public const STATUSES = [
        'DRAFT' => ['Brouillon', false], 'SUBMITTED' => ['Reçu', false], 'ACKNOWLEDGED' => ['Accusé', false], 'ACTIONED' => ['Traité', true],
        'ARCHIVED' => ['Archivé', true], 'CHECKING' => ['Contrôle des lignes', false], 'TARGET_ACQUIRED' => ['Cible acquise', false],
        'INBOUND' => ['En approche', false], 'CLEARED_HOT' => ['Feu autorisé', false], 'ENGAGED' => ['Engagé', false],
        'BDA_PENDING' => ['BDA attendu', false], 'COMPLETE' => ['Terminé', true], 'ABORTED' => ['Annulé', true],
        'REQUESTED' => ['Demandé', false], 'LAUNCHED' => ['Appareil lancé', false], 'ON_SCENE' => ['Sur zone', false], 'CANCELLED' => ['Annulé', true],
    ];

    /** Lignes des 9-line : [libellé] par ligne. */
    public const NINE_LINES = [
        'cas' => ['IP / BP', 'Cap', 'Distance', 'Altitude cible', 'Description', 'Position cible', 'Marquage', 'Amis', 'Dégagement'],
        'fiveline' => ['Observateur / ordre', 'Position amie', 'Position cible', 'Description cible', 'Remarques'],
        'medevac' => ['Zone de poser', 'Fréquence / indicatif', 'Blessés par urgence', 'Équipement spécial', 'Couchés / assis', 'Sécurité zone', 'Marquage', 'Nationalité / statut', 'NBC / terrain'],
    ];

    /** Ligne portant la grille de la cible (1 à 9) par genre de 9-line. */
    public const NINE_GRID_LINE = ['cas' => 6, 'fiveline' => 3, 'medevac' => 1];

    /**
     * Champs d'un type : clé => [libellé, genre]. Genre : « s » court (grille de faits), « l » long (bloc de texte),
     * « v » niveau (VERT / ORANGE / ROUGE / NOIR) ou valeur à pastille.
     *
     * @var array<string, array<string, array{0: string, 1: string}>>
     */
    private const FIELDS = [
        'TIC' => [
            'nature' => ['Nature du contact', 's'], 'grid' => ['Position de l’ennemi', 's'], 'dir' => ['Direction depuis le rapporteur', 's'],
            'dist' => ['Distance', 's'], 'size' => ['Volume ennemi', 's'], 'weapons' => ['Armement observé', 's'], 'status' => ['Situation', 's'],
            'friendly' => ['Pertes amies', 's'], 'need' => ['Besoin immédiat', 's'], 'unit' => ['Unité', 's'], 'desc' => ['Description', 'l'],
        ],
        'SALUTE' => [
            'size' => ['Volume (Size)', 's'], 'activity' => ['Activité (Activity)', 's'], 'movement' => ['Direction de déplacement', 's'],
            'location' => ['Localisation (Location)', 's'], 'unit' => ['Unité, tenue (Unit)', 's'], 'time' => ['Heure d’observation (Time)', 's'],
            'equipment' => ['Équipement (Equipment)', 's'], 'confidence' => ['Fiabilité', 's'], 'remarks' => ['Remarques', 'l'],
        ],
        'SPOTREP' => [
            'what' => ['Objet observé', 's'], 'count' => ['Nombre', 's'], 'grid' => ['Position', 's'], 'movement' => ['Déplacement', 's'],
            'time' => ['Heure', 's'], 'action' => ['Action prise', 's'], 'remarks' => ['Détail', 'l'],
        ],
        'SITREP' => [
            'time' => ['Groupe date-heure', 's'], 'grid' => ['Position de l’élément', 's'], 'activity' => ['Activité', 's'],
            'fit' => ['Personnel apte', 's'], 'wia' => ['Blessés', 's'], 'kia' => ['Tués', 's'],
            'ammo' => ['Munitions', 'v'], 'fuel' => ['Carburant', 'v'], 'capability' => ['Capacité opérationnelle', 's'],
            'enemy' => ['Situation ennemie', 'l'], 'friendly' => ['Situation amie', 'l'], 'next' => ['Intentions', 'l'],
        ],
        'PATROLREP' => [
            'start' => ['Heure de départ', 's'], 'end' => ['Heure de retour', 's'], 'route' => ['Itinéraire suivi', 's'], 'result' => ['Résultat de la mission', 's'],
            'wia' => ['Blessés', 's'], 'kia' => ['Tués', 's'],
            'observations' => ['Observations', 'l'], 'contacts' => ['Contacts et incidents', 'l'], 'recommendations' => ['Recommandations', 'l'],
        ],
        'BDA' => [
            'type' => ['Cible', 's'], 'target' => ['Cible / objectif', 's'], 'grid' => ['Grille de la cible', 's'], 'result' => ['Résultat', 's'],
            'ekia' => ['Ennemis tués (EKIA)', 's'], 'equip' => ['Matériel détruit', 's'], 'ordnance' => ['Munition / moyen', 's'],
            'munitions' => ['Munitions tirées', 's'], 'platform' => ['Plateforme', 's'], 'accuracy' => ['Précision', 's'],
            'collateral' => ['Dommages collatéraux', 's'], 'reattack' => ['Nouvelle frappe', 's'], 'observer' => ['Observateur', 's'],
            'unit' => ['Unité', 's'], 'time' => ['Heure', 's'], 'trn' => ['Référence de cible', 's'],
            'damage' => ['Dégâts observés', 'l'], 'enemy' => ['Effets sur l’ennemi', 'l'], 'friendly' => ['Effets sur les amis et les civils', 'l'], 'remarks' => ['Remarques', 'l'],
        ],
        'EAGLE_DOWN' => [
            'casualty' => ['Nom ou indicatif', 's'], 'status' => ['État', 's'], 'grid' => ['Position du blessé', 's'], 'mechanism' => ['Mécanisme', 's'],
            'injuries' => ['Blessures', 's'], 'signs' => ['Signes (conscience)', 's'], 'treatment' => ['Traitement en cours', 's'],
            'situation' => ['Situation', 's'], 'medevac' => ['Évacuation', 's'], 'lz' => ['Zone de poser', 's'], 'remarks' => ['Remarques', 'l'],
        ],
        'LOGREP' => [
            'rations' => ['Rations', 'v'], 'water' => ['Eau', 'v'], 'fuel' => ['Carburant', 'v'], 'ammo' => ['Munitions', 'v'], 'medical' => ['Matériel médical', 'v'],
            'batteries' => ['Piles et batteries', 'v'], 'vehicles' => ['Véhicules', 's'], 'fit' => ['Personnel apte', 's'], 'wia' => ['Blessés', 's'], 'kia' => ['Tués', 's'],
            'needs' => ['Besoins', 'l'],
        ],
        'UXO' => [
            'time' => ['Date-heure de découverte', 's'], 'grid' => ['Lieu', 's'], 'contact' => ['Indicatif et moyen de contact', 's'], 'type' => ['Type d’engin', 's'],
            'cbrn' => ['Contamination NRBC', 's'], 'threatened' => ['Ressources menacées', 's'], 'impact' => ['Impact sur la mission', 's'],
            'protection' => ['Mesures de protection', 's'], 'priority' => ['Priorité recommandée', 's'], 'remarks' => ['Description de l’engin', 'l'],
        ],
        'NBC1' => [
            'grid' => ['Position de l’observateur', 's'], 'direction' => ['Direction de l’attaque', 's'], 'time' => ['Heure de l’attaque', 's'],
            'impact' => ['Lieu de l’attaque', 's'], 'delivery' => ['Vecteur', 's'], 'agent' => ['Nature', 's'], 'symptoms' => ['Victimes ou symptômes', 's'],
            'mopp' => ['Tenue de protection', 's'], 'clues' => ['Indices', 'l'],
        ],
        'FRAGO' => [
            'reference' => ['Ordre modifié', 's'], 'acknowledge' => ['Accusé de réception', 's'],
            'situation' => ['1 · Situation', 'l'], 'mission' => ['2 · Mission', 'l'], 'execution' => ['3 · Exécution', 'l'], 'support' => ['4 · Soutien', 'l'],
            'command' => ['5 · Commandement et transmissions', 'l'],
        ],
        'OTHER' => ['title' => ['Objet', 's'], 'grid' => ['Position', 's'], 'text' => ['Texte', 'l']],
    ];

    /** Clés qui ne se montrent pas : mécanique du jeu, pas une information de rapport. */
    private const HIDDEN_KEYS = ['kind', 'source', 'photo', 'jnet_escalate', 'send_to', 'reports', 'dtg', 'debrief_event', 'debrief_id', 'debrief_ref', 'mode', 'mission_key'];

    public static function familyOfType(string $type): string
    {
        return self::TYPE_FAMILY[AtakIcemanReportCatalog::normalizeType($type)] ?? 'other';
    }

    public static function normalizeType(string $type): string
    {
        return AtakIcemanReportCatalog::normalizeType($type);
    }

    public static function typeLabel(string $type): string
    {
        $norm = AtakIcemanReportCatalog::normalizeType($type);
        $labels = [
            'TIC' => 'Contact (TIC)', 'CONTACT' => 'Contact', 'SALUTE' => 'SALUTE', 'SPOTREP' => 'Observation (SPOTREP)', 'SITREP' => 'Situation (SITREP)',
            'PATROLREP' => 'Fin de patrouille', 'BDA' => 'Bilan des dégâts (BDA)', 'EAGLE_DOWN' => 'Opérateur à terre', 'LOGREP' => 'État logistique',
            'UXO' => 'Engin explosif (UXO)', 'NBC1' => 'Attaque NRBC (NBC-1)', 'FRAGO' => 'Ordre de conduite (FRAGO)', 'DEBRIEF' => 'Fin d’opération',
            'OTHER' => 'Compte rendu libre',
        ];

        return $labels[$norm] ?? AtakIcemanReportCatalog::labelFr($norm);
    }

    /**
     * Texte d'un résumé du jeu sans son en-tête de type : le téléphone écrit « Contact (TIC) · 0419 1883 · TIRS DIRECTS » ;
     * le type s'affiche déjà à part, il n'a pas à se répéter dans le titre.
     */
    public static function stripLead(string $summary, string $type): string
    {
        $parts = preg_split('/\s·\s/u', trim($summary), 2) ?: [];
        if (count($parts) < 2) {
            return trim($summary);
        }
        $lead = self::fold($parts[0]);
        $known = [self::fold(self::typeLabel($type)), self::fold(AtakIcemanReportCatalog::labelFr($type))];
        foreach (['Contact (TIC)', 'SALUTE', 'Observation (SPOTREP)', 'Situation (SITREP)', 'Fin de patrouille (PATROLREP)', 'Bilan des dégâts (BDA)', 'Opérateur à terre (EAGLE_DOWN)',
            'Opérateur à terre', 'État logistique (LOGREP)', 'Engin explosif (9 lignes)', 'Attaque NRBC (NBC-1)', 'Ordre de conduite (FRAGO)', 'Compte rendu libre', 'Fin d’opération'] as $label) {
            $known[] = self::fold($label);
        }

        return in_array($lead, $known, true) && trim($parts[1]) !== '' ? trim($parts[1]) : trim($summary);
    }

    public static function nineKindLabel(string $kind): string
    {
        return match ($kind) {
            'medevac' => 'MEDEVAC / CASEVAC (9 lignes)',
            'fiveline' => '5-LINE (appui hélicoptère)',
            default => '9-LINE CAS (appui aérien)',
        };
    }

    public static function icon(string $type): string
    {
        return self::TYPE_ICON[strtoupper($type)] ?? 'report';
    }

    public static function priorityLabel(string $priority): string
    {
        return self::PRIORITIES[strtoupper($priority)] ?? 'Routine';
    }

    public static function priorityRank(string $priority): int
    {
        return match (strtoupper($priority)) {
            'FLASH' => 3, 'IMMEDIATE' => 2, 'PRIORITY' => 1, default => 0,
        };
    }

    /** @return array{0: string, 1: bool} libellé et « clos » */
    public static function statusInfo(string $status): array
    {
        return self::STATUSES[strtoupper($status)] ?? [$status !== '' ? $status : 'Reçu', false];
    }

    /**
     * Niveau de lecture exigé par un rapport (échelle SSE, SseUsClassification) : la classification du rapport
     * (UNCLASSIFIED, RESTRICTED, CONFIDENTIAL, SECRET) et sa visibilité (ALL, COMMAND_ONLY, RESTRICTED) retiennent le plus haut.
     * UNCLASSIFIED et RESTRICTED (diffusion restreinte, sous le CUI) restent lisibles par tout opérateur du poste.
     */
    public static function requiredLevel(string $classification, string $visibility = 'ALL'): string
    {
        $byClass = match (strtoupper(trim($classification))) {
            'CONFIDENTIAL' => 'encadrement',
            'SECRET' => 'confidentiel',
            'TOP_SECRET', 'TOPSECRET' => 'tres_restreint',
            default => 'interne',
        };
        $byVisibility = match (strtoupper(trim($visibility))) {
            'COMMAND_ONLY' => 'encadrement',
            'RESTRICTED' => 'confidentiel',
            default => 'interne',
        };

        return self::rank($byClass) >= self::rank($byVisibility) ? $byClass : $byVisibility;
    }

    public static function rank(string $level): int
    {
        return array_search($level, SseUsClassification::codes(), true) === false ? 0 : (int) array_search($level, SseUsClassification::codes(), true);
    }

    /** Marquage de bannière américain (fictif) d'un rapport : UNCLASSIFIED pour un rapport sans restriction. */
    public static function banner(string $classification, string $visibility = 'ALL'): string
    {
        $c = strtoupper(trim($classification));
        if (($c === '' || $c === 'UNCLASSIFIED') && strtoupper(trim($visibility)) === 'ALL') {
            return 'UNCLASSIFIED';
        }

        return SseUsClassification::banner(self::requiredLevel($classification, $visibility));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function fields(string $type): array
    {
        return self::FIELDS[AtakIcemanReportCatalog::normalizeType($type)] ?? [];
    }

    /** Minuscules sans accent : comparaison des textes du jeu (« DÉTRUIT », « détruit »). */
    public static function fold(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');

        return strtr($s, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ù' => 'u', 'û' => 'u', 'ç' => 'c', '’' => "'"]);
    }

    /**
     * Résultat d'un BDA lu dans la fiche, quel que soit l'émetteur (app Comptes rendus : result ; app BDA : desc ;
     * Iceman : rating ou damage).
     *
     * @param array<string, mixed> $sd
     * @return array{code: string, label: string, tone: string, raw: string}
     */
    public static function bdaResult(array $sd): array
    {
        $raw = '';
        foreach (['result', 'res', 'desc', 'rating', 'damage'] as $k) {
            $v = isset($sd[$k]) && is_scalar($sd[$k]) ? trim((string) $sd[$k]) : '';
            if ($v !== '') {
                $raw = $v;
                break;
            }
        }
        $f = self::fold($raw);
        $code = 'unknown';
        if ($f !== '') {
            if (preg_match('/sans effet|aucun effet|no effect|^none$|ineffective/', $f) === 1) {
                $code = 'none';
            } elseif (preg_match('/neutral/', $f) === 1) {
                $code = 'neutralized';
            } elseif (preg_match('/detrui|destroy/', $f) === 1) {
                $code = 'destroyed';
            } elseif (preg_match('/endommag|damag|degrad/', $f) === 1) {
                $code = 'damaged';
            }
        }
        [$label, $tone] = match ($code) {
            'destroyed' => ['Détruit', 'accent'], 'damaged' => ['Endommagé', 'amber'], 'neutralized' => ['Neutralisé', 'accent'],
            'none' => ['Aucun effet', 'red'], default => [$raw !== '' ? $raw : 'Non communiqué', 'grey'],
        };

        return ['code' => $code, 'label' => $label, 'tone' => $tone, 'raw' => $raw];
    }

    /** Ton d'une pastille pour une valeur de la fiche (niveaux, états). */
    public static function valueTone(string $value): string
    {
        $f = self::fold($value);

        return match (true) {
            in_array($f, ['rouge', 'noir', 'critique', 'urgent', 'urgente', 'immediate', 'bloquant', 'tues', 'non remplie', 'reprise requise', 'confirmee', 'non sure'], true) => 'red',
            in_array($f, ['orange', 'prioritaire', 'degrades', 'immobilises', 'partielle', 'mineur', 'indirecte', 'douteux', 'blesses'], true) => 'amber',
            in_array($f, ['vert', 'stable', 'remplie', 'operationnels', 'sans menace', 'sure', 'pas de reprise', 'aucun', 'aucune', 'probable'], true) => 'accent',
            default => '',
        };
    }

    /**
     * Fiche lisible d'un rapport tactique : faits courts, blocs de texte, autres informations.
     *
     * @param array<string, mixed> $sd structured_data décodé
     * @return array{facts: list<array{key: string, label: string, value: string, tone: string}>, blocks: list<array{key: string, label: string, text: string}>, extra: list<array{label: string, value: string}>}
     */
    public static function render(string $type, array $sd, string $details = ''): array
    {
        $norm = AtakIcemanReportCatalog::normalizeType($type);
        $facts = [];
        $blocks = [];
        $used = [];
        foreach (self::fields($norm) as $key => [$label, $kind]) {
            $v = self::scalar($sd[$key] ?? null);
            if ($v === '') {
                continue;
            }
            $used[$key] = true;
            $tone = '';
            if ($key === 'result' && $norm === 'BDA') {
                $r = self::bdaResult($sd);
                $v = $r['label'];
                $tone = $r['tone'] === 'grey' ? '' : $r['tone'];
            }
            if ($kind === 'l') {
                $blocks[] = ['key' => $key, 'label' => $label, 'text' => $v];
            } else {
                $facts[] = ['key' => $key, 'label' => $label, 'value' => $v, 'tone' => $tone !== '' ? $tone : ($kind === 'v' || in_array($key, ['status', 'result', 'priority', 'need', 'medevac', 'reattack', 'friendly', 'collateral'], true) ? self::valueTone($v) : '')];
            }
        }
        if ($norm === 'BDA' && !isset($used['result'])) {
            $r = self::bdaResult($sd);
            if ($r['code'] !== 'unknown' || $r['raw'] !== '') {
                array_unshift($facts, ['key' => 'result', 'label' => 'Résultat', 'value' => $r['label'], 'tone' => $r['tone'] === 'grey' ? '' : $r['tone']]);
                unset($used['desc']);
                $used['desc'] = true;
            }
        }
        $extra = [];
        foreach ($sd as $key => $value) {
            $key = (string) $key;
            if (isset($used[$key]) || in_array($key, self::HIDDEN_KEYS, true)) {
                continue;
            }
            $v = self::scalar($value);
            if ($v === '') {
                continue;
            }
            // Un texte long (réponses d'un compte rendu de fin d'opération, clés inconnues) se lit comme un bloc, pas dans une grille.
            if (mb_strlen($v, 'UTF-8') > 90 || str_contains($v, "\n")) {
                $blocks[] = ['key' => $key, 'label' => self::humanKey($key), 'text' => $v];

                continue;
            }
            $extra[] = ['label' => self::humanKey($key), 'value' => $v];
        }
        if ($facts === [] && $blocks === [] && $extra === [] && trim($details) !== '') {
            $blocks[] = ['key' => 'details', 'label' => 'Détail', 'text' => trim($details)];
        }

        return ['facts' => $facts, 'blocks' => $blocks, 'extra' => $extra];
    }

    /** Valeur d'une fiche en texte : liste en « a, b », tableau associatif en « clé : valeur » par ligne. */
    public static function scalar(mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        if (is_bool($v)) {
            return $v ? 'Oui' : 'Non';
        }
        if (is_scalar($v)) {
            return trim(str_replace(["\r\n", "\r"], "\n", (string) $v));
        }
        if (is_array($v)) {
            $parts = [];
            foreach ($v as $k => $item) {
                $s = self::scalar($item);
                if ($s === '') {
                    continue;
                }
                $parts[] = is_int($k) ? $s : self::humanKey((string) $k) . ' : ' . $s;
            }

            return implode(array_is_list($v) && count(array_filter($v, 'is_array')) === 0 ? ', ' : "\n", $parts);
        }

        return '';
    }

    public static function humanKey(string $key): string
    {
        if (preg_match('/^a_(\d+)$/', $key, $m) === 1) {
            return 'Réponse ' . $m[1];
        }
        if ($key === 'label') {
            return 'Questionnaire';
        }
        $k = trim(str_replace(['_', '-'], ' ', $key));

        return $k === '' ? 'Champ' : mb_strtoupper(mb_substr($k, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($k, 1, null, 'UTF-8');
    }
}
