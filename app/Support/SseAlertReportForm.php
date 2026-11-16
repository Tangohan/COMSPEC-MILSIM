<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Formulaire de signalement adapté à l'alerte : combine les règles de config/sse_alert_report_form.php (par conduite à tenir « A-… »
 * et par niveau) en un contrat unique : champs montrés, champs obligatoires, urgence, consignes. Le même contrat sert à la page
 * (ce qu'elle affiche) et au serveur (ce qu'il accepte et exige) : un champ non affiché n'est ni lu, ni exigé, ni enregistré.
 */
final class SseAlertReportForm
{
    /** Champs d'observation qui n'ont pas de colonne : ils sont ajoutés à la description, sous forme de lignes « libellé : valeur ». */
    public const FOLDED = ['people', 'armed', 'distance', 'duration', 'behaviour'];

    /** Champs communs exigibles même s'ils ne sont pas adaptables. */
    private const BASE_REQUIRED = ['place' => ['Indiquez le lieu.', 'Say where.']];

    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    /** @return array<string, mixed> */
    public static function config(): array
    {
        if (self::$config === null) {
            $data = require dirname(__DIR__, 2) . '/config/sse_alert_report_form.php';
            self::$config = is_array($data) ? $data : [];
        }

        return self::$config;
    }

    /** Remplace la configuration (tests) ; null la recharge. @param array<string, mixed>|null $config */
    public static function useConfig(?array $config): void
    {
        self::$config = $config;
    }

    /**
     * @param list<string> $measureCodes conduites à tenir de la fiche (« A-NOI »…)
     * @param string $armed « yes », « no »… (fiche déclarée armée)
     * @return array{fields: list<string>, required: list<string>, urgent: bool, notes: list<array{0: string, 1: string}>, codes: list<string>}
     */
    public static function resolve(array $measureCodes, int $rank, string $armed = ''): array
    {
        $cfg = self::config();
        $known = array_keys((array) ($cfg['fields'] ?? []));
        $rules = [];
        $codes = [];
        foreach ($measureCodes as $code) {
            $code = strtoupper((string) $code);
            if (isset($cfg['measures'][$code]) && !in_array($code, $codes, true)) {
                $codes[] = $code;
                $rules[] = $cfg['measures'][$code];
            }
        }
        $rules[] = $cfg['levels'][max(1, min(4, $rank))] ?? [];
        if ($armed === 'yes') {
            $rules[] = $cfg['armed'] ?? [];
        }
        $show = (array) ($cfg['default'] ?? []);
        $drop = [];
        $require = [];
        $urgent = false;
        $notes = [];
        foreach ($rules as $rule) {
            $show = array_merge($show, (array) ($rule['add'] ?? []));
            $drop = array_merge($drop, (array) ($rule['drop'] ?? []));
            $require = array_merge($require, (array) ($rule['require'] ?? []));
            $urgent = $urgent || !empty($rule['urgent']);
            if (is_array($rule['note'] ?? null) && count($rule['note']) === 2) {
                $notes[] = [(string) $rule['note'][0], (string) $rule['note'][1]];
            }
        }
        // Ordre du catalogue ; ce qui est retiré l'emporte sur ce qui est ajouté.
        $fields = array_values(array_filter($known, static fn (string $f): bool => in_array($f, $show, true) && !in_array($f, $drop, true)));
        // Jamais d'obligation sur un champ qui n'est pas affiché.
        $required = array_values(array_filter(array_unique($require), static fn (string $f): bool => in_array($f, $fields, true) || isset(self::BASE_REQUIRED[$f])));

        return ['fields' => $fields, 'required' => $required, 'urgent' => $urgent, 'notes' => $notes, 'codes' => $codes];
    }

    /** Définition d'un champ adaptable, ou null. @return array<string, mixed>|null */
    public static function field(string $id): ?array
    {
        $f = self::config()['fields'][$id] ?? null;

        return is_array($f) ? $f : null;
    }

    /**
     * Valeurs saisies des SEULS champs affichés (les autres sont ignorés), nettoyées selon leur type.
     *
     * @param array{fields: list<string>} $form
     * @param array<string, mixed> $raw
     * @return array<string, string>
     */
    public static function sanitize(array $form, array $raw): array
    {
        $out = [];
        foreach ($form['fields'] as $id) {
            $def = self::field($id);
            if ($def === null || $def['type'] === 'photo') {
                continue;
            }
            $v = $raw[$id] ?? '';
            $v = is_scalar($v) ? trim((string) $v) : '';
            $out[$id] = match ($def['type']) {
                'count' => ctype_digit($v) && (int) $v >= 1 ? (string) min((int) $v, (int) $def['max']) : '',
                'choice' => isset($def['options'][$v]) ? $v : '',
                default => SseWantedCatalog::line($v, (int) $def['max']),
            };
        }

        return $out;
    }

    /**
     * Messages des champs obligatoires restés vides.
     *
     * @param array{required: list<string>} $form
     * @param array<string, string> $values valeurs (champs communs « place » incluse)
     * @return list<string>
     */
    public static function missing(array $form, array $values, string $lang = 'fr'): array
    {
        $i = $lang === 'en' ? 1 : 0;
        $out = [];
        foreach ($form['required'] as $id) {
            if (trim((string) ($values[$id] ?? '')) === '') {
                $out[] = (string) (self::BASE_REQUIRED[$id][$i] ?? self::field($id)['missing'][$i] ?? '');
            }
        }

        return array_values(array_filter($out, static fn (string $m): bool => $m !== ''));
    }

    /**
     * Lignes ajoutées à la description pour les champs d'observation sans colonne.
     *
     * @param array<string, string> $values
     * @return list<string>
     */
    public static function foldedLines(array $values, string $lang = 'fr'): array
    {
        $i = $lang === 'en' ? 1 : 0;
        $lines = [];
        foreach (self::FOLDED as $id) {
            $v = (string) ($values[$id] ?? '');
            $def = self::field($id);
            if ($v === '' || $def === null) {
                continue;
            }
            $shown = $def['type'] === 'choice' ? (string) ($def['options'][$v][$i] ?? $v) : $v;
            $lines[] = $def['label'][$i] . ' : ' . $shown;
        }

        return $lines;
    }
}
