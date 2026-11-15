<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Niveau d'alerte d'une fiche de vigilance ou d'un avis : UNE seule échelle, celle de la menace des avis
 * (SseWantedCatalog::THREATS), relevée par l'armement. Elle donne le titre du bloc d'alerte, sa couleur, sa forme (le niveau
 * ne dépend jamais de la seule couleur : rang, titre et forme l'écrivent aussi) et la consigne dominante déduite des mesures
 * codifiées (A-…). Sert au PDF, au portail et au contrat du jeu (ICD-SSE-ALERTES-001).
 *
 * Couleurs de notice : un avis dont le type porte une couleur est une « notice de couleur » (cadre plein sur tout le contour).
 * Rien de réel : ni nom, ni sigle, ni emblème d'organisation.
 */
final class SseAlertLevel
{
    /**
     * clé => rang, titres [fr, en], couleur (RGB), encre lisible sur la couleur (RGB), forme, explication [fr, en].
     *
     * @var array<string, array{rank: int, title: array{0: string, 1: string}, rgb: array{0: int, 1: int, 2: int}, ink: array{0: int, 1: int, 2: int}, shape: string, hint: array{0: string, 1: string}}>
     */
    public const LEVELS = [
        'watch' => [
            'rank' => 1, 'title' => ['VIGILANCE', 'BE ON THE LOOKOUT'], 'rgb' => [242, 194, 0], 'ink' => [20, 20, 20], 'shape' => 'triangle',
            'hint' => ['Menace faible ou non précisée : observer et rendre compte.', 'Low or unspecified threat: observe and report.'],
        ],
        'reinforced' => [
            'rank' => 2, 'title' => ['VIGILANCE RENFORCÉE', 'HEIGHTENED VIGILANCE'], 'rgb' => [240, 140, 0], 'ink' => [20, 20, 20], 'shape' => 'triangle',
            'hint' => ['Menace moyenne : attention soutenue, compte rendu sans délai.', 'Medium threat: sustained attention, report without delay.'],
        ],
        'caution' => [
            'rank' => 3, 'title' => ['APPROCHE AVEC PRÉCAUTION', 'APPROACH WITH CAUTION'], 'rgb' => [217, 72, 15], 'ink' => [255, 255, 255], 'shape' => 'diamond',
            'hint' => ['Menace élevée ou critique : jamais seul, appui demandé avant tout contact.', 'High or critical threat: never alone, request support before any contact.'],
        ],
        'danger' => [
            'rank' => 4, 'title' => ['DANGER — ARMÉ ET DANGEREUX', 'DANGER — ARMED AND DANGEROUS'], 'rgb' => [176, 18, 31], 'ink' => [255, 255, 255], 'shape' => 'octagon',
            'hint' => ['Sujet armé : protection des intervenants d’abord.', 'Armed subject: force protection first.'],
        ],
    ];

    /** Consigne dominante : clé => [fr, en]. */
    public const ORDERS = [
        'hold' => ['NE PAS APPRÉHENDER — SIGNALER', 'DO NOT APPREHEND — REPORT'],
        'order' => ['INTERVENTION SUR ORDRE', 'ACTION ON ORDER'],
    ];

    /** Mesures d'intervention : leur présence retire « ne pas appréhender ». */
    public const ACTION_CODES = ['A-ARR', 'A-DET', 'A-LIV', 'A-NEU'];

    /** Mesures d'observation : sans mesure d'intervention, elles donnent « ne pas appréhender ». */
    public const HOLD_CODES = ['A-NOI', 'A-OBS', 'A-TRK', 'A-PAT'];

    /** Mesures codifiées qui deviennent une ligne « À ne pas faire » : code => [fr, en]. */
    public const DONTS = [
        'A-NOI' => ['Ne pas intervenir seul.', 'Do not engage alone.'],
        'A-OBS' => ['Ne pas se faire repérer.', 'Do not get detected.'],
        'A-ARR' => ['Ne pas appréhender sans ordre.', 'Do not apprehend without orders.'],
        'A-NEU' => ['Ne pas neutraliser sans autorisation expresse.', 'Do not neutralize without express authorization.'],
        'A-DET' => ['Ne pas interroger le sujet.', 'Do not question the subject.'],
        'A-CIV' => ['Ne pas intervenir en zone peuplée.', 'Do not act in populated areas.'],
        'A-IED' => ['Ne pas toucher un objet suspect.', 'Do not touch any suspect item.'],
        'A-TRK' => ['Ne pas rompre la discrétion du suivi.', 'Do not break the discretion of the tail.'],
    ];

    /** Mesures proposées d'office par niveau (cochées à l'émission rapide). */
    public const DEFAULT_MEASURES = [
        'watch' => ['A-OBS', 'A-REP', 'A-PHO'],
        'reinforced' => ['A-OBS', 'A-NOI', 'A-REP', 'A-CHK'],
        'caution' => ['A-NOI', 'A-REP', 'A-QRF', 'A-CIV'],
        'danger' => ['A-DNG', 'A-NOI', 'A-QRF', 'A-BOD', 'A-REP'],
    ];

    /** Délais de compte rendu : clé => [fr, en]. */
    public const DELAYS = [
        'now' => ['Immédiatement', 'Immediately'],
        '15m' => ['Sous 15 minutes', 'Within 15 minutes'],
        '1h' => ['Sous 1 heure', 'Within 1 hour'],
        '4h' => ['Sous 4 heures', 'Within 4 hours'],
        '24h' => ['Sous 24 heures', 'Within 24 hours'],
    ];

    /** Certitude d'un signalement : clé => [fr, en]. */
    public const CERTAINTY = [
        'low' => ['Incertain', 'Unsure'],
        'medium' => ['Probable', 'Probable'],
        'high' => ['Certain', 'Certain'],
    ];

    /** États d'un signalement : clé => [fr, en]. */
    public const REPORT_STATUSES = [
        'new' => ['Nouveau', 'New'],
        'acknowledged' => ['Pris en compte', 'Acknowledged'],
        'corroborated' => ['Recoupé', 'Corroborated'],
        'dismissed' => ['Écarté', 'Dismissed'],
    ];

    /** Échéance « proche » : le document est marqué PÉRISSABLE. */
    public const PERISHABLE_HOURS = 72;

    /**
     * Couleurs de notice : clé => libellés [fr, en], titre de la notice [fr, en], couleur (RGB), encre sur la couleur.
     *
     * @var array<string, array{label: array{0: string, 1: string}, title: array{0: string, 1: string}, rgb: array{0: int, 1: int, 2: int}, ink: array{0: int, 1: int, 2: int}}>
     */
    public const NOTICE_COLORS = [
        'red' => ['label' => ['Rouge', 'Red'], 'title' => ['NOTICE ROUGE', 'RED NOTICE'], 'rgb' => [190, 18, 30], 'ink' => [255, 255, 255]],
        'blue' => ['label' => ['Bleue', 'Blue'], 'title' => ['NOTICE BLEUE', 'BLUE NOTICE'], 'rgb' => [20, 78, 152], 'ink' => [255, 255, 255]],
        'green' => ['label' => ['Verte', 'Green'], 'title' => ['NOTICE VERTE', 'GREEN NOTICE'], 'rgb' => [22, 112, 62], 'ink' => [255, 255, 255]],
        'yellow' => ['label' => ['Jaune', 'Yellow'], 'title' => ['NOTICE JAUNE', 'YELLOW NOTICE'], 'rgb' => [240, 196, 0], 'ink' => [20, 20, 20]],
        'orange' => ['label' => ['Orange', 'Orange'], 'title' => ['NOTICE ORANGE', 'ORANGE NOTICE'], 'rgb' => [232, 112, 0], 'ink' => [20, 20, 20]],
        'black' => ['label' => ['Noire', 'Black'], 'title' => ['NOTICE NOIRE', 'BLACK NOTICE'], 'rgb' => [22, 24, 28], 'ink' => [255, 255, 255]],
    ];

    /**
     * Type d'avis fourni => couleur de notice et sous-titre [fr, en]. Un type propre à la communauté prend la notice rouge.
     *
     * @var array<string, array{0: string, 1: array{0: string, 1: string}}>
     */
    public const TYPE_COLORS = [
        'arrest' => ['red', ['PERSONNE RECHERCHÉE', 'WANTED PERSON']],
        'hvt' => ['red', ['CIBLE DE HAUTE VALEUR', 'HIGH-VALUE TARGET']],
        'capture_alive' => ['red', ['À CAPTURER VIVANT', 'CAPTURE ALIVE']],
        'capture_or_kill' => ['red', ['À CAPTURER OU NEUTRALISER', 'CAPTURE OR NEUTRALIZE']],
        'escaped' => ['red', ['DÉTENU EN FUITE', 'ESCAPED DETAINEE']],
        'poi' => ['blue', ['LOCALISATION ET RENSEIGNEMENT', 'LOCATION AND INFORMATION']],
        'info' => ['blue', ['RECHERCHE D’INFORMATION', 'INFORMATION SOUGHT']],
        'surveillance' => ['green', ['MISE EN GARDE — SURVEILLANCE', 'WARNING — SURVEILLANCE']],
    ];

    /** Clé du niveau d'alerte pour une menace et un armement (valeurs des avis). */
    public static function key(?string $threat, ?string $armed): string
    {
        if ($armed === 'yes') {
            return 'danger';
        }

        return match ($threat) {
            'high', 'critical' => 'caution',
            'medium' => 'reinforced',
            default => 'watch',
        };
    }

    /** @return array{key: string, rank: int, title: array{0: string, 1: string}, rgb: array{0: int, 1: int, 2: int}, ink: array{0: int, 1: int, 2: int}, shape: string, hint: array{0: string, 1: string}} */
    public static function of(?string $threat, ?string $armed): array
    {
        $key = self::key($threat, $armed);

        return ['key' => $key] + self::LEVELS[$key];
    }

    /** @param list<string> $measureCodes @return ''|'hold'|'order' */
    public static function order(array $measureCodes): string
    {
        $codes = array_map('strtoupper', array_map('strval', $measureCodes));
        if (array_intersect($codes, self::ACTION_CODES) !== []) {
            return 'order';
        }

        return array_intersect($codes, self::HOLD_CODES) !== [] ? 'hold' : '';
    }

    /**
     * Lignes « À ne pas faire » déduites des mesures cochées (3 au plus).
     *
     * @param list<string> $measureCodes
     * @return list<string>
     */
    public static function donts(array $measureCodes, string $lang): array
    {
        $out = [];
        foreach ($measureCodes as $code) {
            $code = strtoupper((string) $code);
            if (isset(self::DONTS[$code])) {
                $out[] = self::DONTS[$code][$lang === 'en' ? 1 : 0];
            }
        }

        return array_slice(array_values(array_unique($out)), 0, 3);
    }

    public static function hex(string $key): string
    {
        $rgb = self::LEVELS[$key]['rgb'] ?? self::LEVELS['watch']['rgb'];

        return sprintf('#%02X%02X%02X', $rgb[0], $rgb[1], $rgb[2]);
    }

    /** Échéance dans moins de PERISHABLE_HOURS (et pas encore passée) ? Date « Y-m-d » ou « Y-m-d H:i:s », UTC. */
    public static function perishable(?string $until, ?int $now = null): bool
    {
        if ($until === null || trim($until) === '') {
            return false;
        }
        $ts = strtotime((strlen($until) <= 10 ? $until . ' 23:59:59' : $until) . ' UTC');
        $now ??= time();

        return $ts !== false && $ts >= $now && $ts - $now <= self::PERISHABLE_HOURS * 3600;
    }

    /**
     * Couleur de notice d'un avis : celle de son type quand la communauté imprime des notices (bannière « notice »), sinon aucune.
     *
     * @param array<string, mixed> $config réglages de la communauté
     * @return array{key: string, title: array{0: string, 1: string}, subtitle: array{0: string, 1: string}, rgb: array{0: int, 1: int, 2: int}, ink: array{0: int, 1: int, 2: int}}|null
     */
    public static function noticeColor(string $type, array $config = []): ?array
    {
        if ((string) ($config['banner'] ?? 'red') !== 'red') {
            return null;
        }
        [$key, $subtitle] = self::TYPE_COLORS[$type] ?? ['red', [
            mb_strtoupper(SseWantedCatalog::typeLabel($type, $config, 'fr'), 'UTF-8'),
            mb_strtoupper(SseWantedCatalog::typeLabel($type, $config, 'en'), 'UTF-8'),
        ]];
        $c = self::NOTICE_COLORS[$key];

        return ['key' => $key, 'title' => $c['title'], 'subtitle' => $subtitle, 'rgb' => $c['rgb'], 'ink' => $c['ink']];
    }

    public static function noticeHex(string $colorKey): string
    {
        $rgb = self::NOTICE_COLORS[$colorKey]['rgb'] ?? self::NOTICE_COLORS['red']['rgb'];

        return sprintf('#%02X%02X%02X', $rgb[0], $rgb[1], $rgb[2]);
    }
}
