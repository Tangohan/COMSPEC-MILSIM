<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Fiches de la page mobile /m/cr (« Sur mon téléphone », app Comptes rendus) : mêmes codes, mêmes clés, mêmes
 * libellés et mêmes choix que le catalogue de l'app en jeu (mod : functions/reports/fn_reportTypes.sqf). La fiche
 * remplie sur le téléphone est confiée à l'ATAK du joueur, qui l'envoie comme s'il l'avait remplie lui-même.
 *
 * Champ : [clé, genre, libellé, choix, requis]
 *   genres : big (gros boutons), seg / combo / dir (choix), edit (une ligne), memo (texte long), num (nombre),
 *            grid (grille : ma position ou la carte), dtg (heure : laissée vide, l'ATAK met l'heure du jeu).
 */
final class AtakMobileReportCatalog
{
    private const DIR = ['N', 'NE', 'E', 'SE', 'S', 'SO', 'O', 'NO', 'STATIQUE', 'INCONNUE'];

    public const PRIORITIES = ['ROUTINE', 'PRIORITAIRE', 'IMMÉDIAT', 'FLASH'];

    /**
     * @return array<string, array{label: string, help: string, prio: string, color: string, fields: list<array{0: string, 1: string, 2: string, 3: list<string>, 4: bool}>}>
     */
    public static function types(): array
    {
        return [
            'CONTACT' => [
                'label' => 'Contact (TIC)',
                'help' => 'Premier compte rendu au contact : où, quoi, combien, ce qu’il vous faut.',
                'prio' => 'IMMÉDIAT',
                'color' => '#e5483a',
                'fields' => [
                    ['nature', 'big', 'Nature du contact', ['TIRS DIRECTS', 'TIRS INDIRECTS', 'IED', 'OBSERVÉ'], true],
                    ['grid', 'grid', 'Position de l’ennemi', [], true],
                    ['dir', 'dir', 'Direction depuis moi', self::DIR, false],
                    ['dist', 'seg', 'Distance', ['< 100 M', '100-300 M', '300-600 M', '600 M-1 KM', '> 1 KM'], false],
                    ['size', 'combo', 'Volume ennemi', ['Inconnu', '1 à 2 personnes', 'Groupe (3 à 10)', 'Section (10 à 30)', 'Compagnie ou plus', 'Véhicule(s) seul(s)'], false],
                    ['weapons', 'combo', 'Armement observé', ['Armes légères', 'Mitrailleuses', 'Lance-roquettes (RPG)', "Tireurs d'élite", 'Mortiers / artillerie', 'Véhicules armés', 'Drones', 'Inconnu'], false],
                    ['status', 'seg', 'Situation', ['EN COURS', 'FIXÉ', 'ROMPU', 'TERMINÉ'], false],
                    ['friendly', 'seg', 'Pertes amies', ['AUCUNE', 'BLESSÉS', 'TUÉS', 'INCONNU'], false],
                    ['need', 'big', 'Besoin immédiat', ['AUCUN', 'APPUI FEU', 'RENFORTS', 'MEDEVAC', 'QRF'], false],
                    ['desc', 'memo', 'Description (facultatif)', [], false],
                ],
            ],
            'SALUTE' => [
                'label' => 'SALUTE',
                'help' => 'Observation ennemie détaillée : volume, activité, lieu, unité, heure, équipement.',
                'prio' => 'ROUTINE',
                'color' => '#e8c547',
                'fields' => [
                    ['size', 'edit', 'Volume (Size)', [], true],
                    ['activity', 'combo', 'Activité (Activity)', ['Patrouille', 'Déplacement', 'En position défensive', 'Retranchement / travaux', 'Embuscade', 'Rassemblement', 'Ravitaillement', 'Observation', 'Au repos', 'Inconnue'], true],
                    ['movement', 'dir', 'Direction de déplacement', self::DIR, false],
                    ['location', 'grid', 'Localisation (Location)', [], true],
                    ['unit', 'edit', 'Unité, tenue (Unit)', [], false],
                    ['time', 'dtg', 'Heure d’observation (Time)', [], true],
                    ['equipment', 'edit', 'Équipement (Equipment)', [], false],
                    ['confidence', 'seg', 'Fiabilité', ['CONFIRMÉ', 'PROBABLE', 'DOUTEUX'], false],
                    ['remarks', 'memo', 'Remarques', [], false],
                ],
            ],
            'SPOTREP' => [
                'label' => 'Observation (SPOTREP)',
                'help' => 'Observation ponctuelle : véhicule, mouvement, installation, activité suspecte.',
                'prio' => 'ROUTINE',
                'color' => '#e8c547',
                'fields' => [
                    ['what', 'combo', 'Objet observé', ['Personnel', 'Véhicule léger', 'Véhicule blindé', 'Aéronef', 'Drone', 'Installation / position', 'Engin explosif suspect', 'Civils', 'Activité suspecte', 'Autre'], true],
                    ['count', 'num', 'Nombre', [], false],
                    ['grid', 'grid', 'Position', [], true],
                    ['movement', 'dir', 'Déplacement', self::DIR, false],
                    ['time', 'dtg', 'Heure', [], true],
                    ['action', 'seg', 'Action prise', ['AUCUNE', 'SURVEILLANCE', 'ENGAGÉ', 'SIGNALÉ'], false],
                    ['remarks', 'memo', 'Détail', [], false],
                ],
            ],
            'EAGLE_DOWN' => [
                'label' => 'Opérateur à terre (blessé)',
                'help' => 'Blessé ou opérateur hors de combat : état, position, blessures, évacuation.',
                'prio' => 'IMMÉDIAT',
                'color' => '#e5483a',
                'fields' => [
                    ['casualty', 'edit', 'Nom ou indicatif', [], true],
                    ['status', 'big', 'État', ['STABLE', 'URGENT', 'CRITIQUE', 'DÉCÉDÉ'], true],
                    ['grid', 'grid', 'Position du blessé', [], true],
                    ['mechanism', 'combo', 'Mécanisme', ['Balle', 'Éclats / explosion', 'IED', 'Chute', 'Brûlure', 'Accident de véhicule', 'Autre'], false],
                    ['injuries', 'edit', 'Blessures', [], false],
                    ['signs', 'seg', 'Signes (conscience)', ['CONSCIENT', 'CONFUS', 'INCONSCIENT', 'ARRÊT'], false],
                    ['treatment', 'edit', 'Traitement en cours', [], false],
                    ['situation', 'seg', 'Situation', ['CONTACT EN COURS', 'ZONE SÛRE'], false],
                    ['medevac', 'big', 'Évacuation', ['URGENTE', 'PRIORITAIRE', 'ROUTINE', 'PAS BESOIN'], false],
                    ['lz', 'seg', 'Zone de poser', ['SÛRE', 'NON SÛRE', 'AUCUNE'], false],
                    ['remarks', 'memo', 'Remarques', [], false],
                ],
            ],
            'OTHER' => [
                'label' => 'Compte rendu libre',
                'help' => 'Tout ce qui n’entre pas dans une fiche.',
                'prio' => 'ROUTINE',
                'color' => '#8a9a93',
                'fields' => [
                    ['title', 'edit', 'Objet', [], true],
                    ['text', 'memo', 'Texte', [], true],
                    ['grid', 'grid', 'Position (facultatif)', [], false],
                ],
            ],
        ];
    }

    /**
     * Contrôle de la fiche reçue du téléphone (mêmes règles que l'envoi en jeu). Renvoie [valeurs propres, erreurs].
     *
     * @param array<string, mixed> $input
     * @return array{0: array<string, string>, 1: list<string>}
     */
    public static function clean(string $code, array $input): array
    {
        $type = self::types()[$code] ?? null;
        if ($type === null) {
            return [[], ['type']];
        }
        $vals = [];
        $errors = [];
        foreach ($type['fields'] as [$key, $kind, , $options, $required]) {
            $v = $input[$key] ?? '';
            $v = is_scalar($v) ? trim((string) $v) : '';
            $v = (string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $v);
            $v = mb_substr($v, 0, $kind === 'memo' ? 600 : 120);
            if ($options !== [] && $v !== '' && !in_array($v, $options, true)) {
                $errors[] = $key;
                continue;
            }
            if ($kind === 'num' && $v !== '' && preg_match('/^[0-9]{1,4}$/', $v) !== 1) {
                $errors[] = $key;
                continue;
            }
            if ($kind === 'grid' && $v !== '') {
                $g = (string) preg_replace('/[\s,.\-]+/', '', $v);
                if (preg_match('/^[0-9]{6,10}$/', $g) !== 1 || strlen($g) % 2 === 1) {
                    $errors[] = $key;
                    continue;
                }
            }
            // L'heure (dtg) est mise par l'ATAK du joueur, à l'heure du jeu : jamais exigée du téléphone.
            if ($required && $v === '' && $kind !== 'dtg') {
                $errors[] = $key;
                continue;
            }
            if ($v !== '') {
                $vals[$key] = $v;
            }
        }
        $prio = trim((string) ($input['prio'] ?? ''));
        $vals['prio'] = in_array($prio, self::PRIORITIES, true) ? $prio : $type['prio'];

        return [$vals, $errors];
    }

    /** Chaîne SQF lue par parseSimpleArray (guillemets doublés, une seule ligne). */
    public static function sqfString(string $s): string
    {
        $s = str_replace(["\r", "\n", "\t", '|'], [' ', ' ', ' ', '/'], $s);

        return '"' . str_replace('"', '""', $s) . '"';
    }

    /**
     * Corps de l'avis « mobile_report » lu par l'ATAK (fn_reportAction « mobile ») : ["MCR1", code, [[clé, valeur]…]].
     *
     * @param array<string, string> $vals
     */
    public static function noticeBody(string $code, array $vals): string
    {
        $pairs = [];
        foreach ($vals as $k => $v) {
            $pairs[] = '[' . self::sqfString((string) $k) . ',' . self::sqfString($v) . ']';
        }

        return '["MCR1",' . self::sqfString($code) . ',[' . implode(',', $pairs) . ']]';
    }
}
