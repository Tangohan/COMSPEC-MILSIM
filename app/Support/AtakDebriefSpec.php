<?php

declare(strict_types=1);

namespace App\Support;

/**
 * CR de fin d'opération envoyé au téléphone ATAK (fn_debrief du mod natif).
 *
 * Le téléphone reçoit la demande par « AdminControl.Poll » (avis de genre « debrief ») : le texte de l'avis porte la fiche
 * sous forme de tableau SQF lu par parseSimpleArray (aucun code exécuté) :
 *   ["DBF1", mode, titre, introduction, texte à lire, [[id, type, libellé, aide, requis (0/1), [options…]], …]]
 * Une liste de questions vide = questionnaire intégré au mod (traduit dans la langue du joueur).
 * Les réponses reviennent par POST /api/atak/reports (type DEBRIEF, structured_data).
 */
final class AtakDebriefSpec
{
    public const MODES = ['fill' => 'À remplir', 'read' => 'À lire'];

    /** Fiche au plus (octets UTF-8) : la réponse de « AdminControl.Poll » passe par la DLL (8 000 octets au total). */
    public const MAX_BYTES = 3200;

    /** Questionnaire intégré au mod (fn_debrief), repris ici pour lire les réponses sur le site. */
    public const BUILTIN = [
        ['id' => 'summary', 'type' => AarCustomForm::TYPE_TEXTAREA, 'label' => 'Déroulé de votre mission', 'help' => '', 'required' => true, 'options' => []],
        ['id' => 'went_well', 'type' => AarCustomForm::TYPE_TEXTAREA, 'label' => 'Ce qui a bien marché', 'help' => '', 'required' => false, 'options' => []],
        ['id' => 'improve', 'type' => AarCustomForm::TYPE_TEXTAREA, 'label' => 'Ce qui doit progresser', 'help' => '', 'required' => false, 'options' => []],
        ['id' => 'incidents', 'type' => AarCustomForm::TYPE_TEXT, 'label' => 'Pertes, blessés, matériel perdu', 'help' => '', 'required' => false, 'options' => []],
        ['id' => 'comms', 'type' => AarCustomForm::TYPE_SELECT, 'label' => 'Liaisons radio et ATAK', 'help' => '', 'required' => false, 'options' => ['Bonnes', 'Correctes', 'Difficiles', 'Coupées']],
        ['id' => 'rating', 'type' => AarCustomForm::TYPE_SELECT, 'label' => 'Note de la mission', 'help' => '', 'required' => false, 'options' => ['1', '2', '3', '4', '5']],
    ];

    /**
     * Fiche pour le téléphone. Les questions qui dépassent la taille permise sont retirées (les dernières d'abord).
     *
     * @param list<array<string, mixed>> $questions questions normalisées (AarCustomForm::normalizeFields), [] = intégré
     */
    public static function phoneSpec(string $mode, string $label, string $intro, string $readText, array $questions): string
    {
        $mode = isset(self::MODES[$mode]) ? $mode : 'fill';
        $readText = $mode === 'read' ? self::clip($readText, 2000) : '';
        $rows = [];
        foreach ($mode === 'fill' ? $questions : [] as $q) {
            $type = (string) ($q['type'] ?? AarCustomForm::TYPE_TEXT);
            $opts = array_map(static fn ($o): string => self::str(self::clip((string) $o, 60)), array_slice((array) ($q['options'] ?? []), 0, 8));
            $rows[] = '[' . implode(',', [
                self::str(self::clip((string) ($q['id'] ?? ''), 40)),
                self::str(in_array($type, AarCustomForm::TYPES, true) ? $type : AarCustomForm::TYPE_TEXT),
                self::str(self::clip((string) ($q['label'] ?? ''), 160)),
                self::str(self::clip((string) ($q['help'] ?? ''), 160)),
                !empty($q['required']) ? '1' : '0',
                '[' . implode(',', $opts) . ']',
            ]) . ']';
        }
        $build = static fn (array $r, string $text): string => '[' . implode(',', [
            '"DBF1"', self::str($mode), self::str(self::clip($label, 120)), self::str(self::clip($intro, 400)), self::str($text), '[' . implode(',', $r) . ']',
        ]) . ']';
        $spec = $build($rows, $readText);
        while (strlen($spec) > self::MAX_BYTES && $rows !== []) {
            array_pop($rows);
            $spec = $build($rows, $readText);
        }
        while (strlen($spec) > self::MAX_BYTES && mb_strlen($readText) > 20) {
            $readText = rtrim(mb_substr($readText, 0, (int) floor(mb_strlen($readText) * 0.85), 'UTF-8'), ' …') . '…';
            $spec = $build($rows, $readText);
        }

        return $spec;
    }

    /** Texte affiché par un ATAK trop ancien pour ouvrir la fiche (avis classique). */
    public static function legacyBody(string $mode, string $label): string
    {
        return $mode === 'read'
            ? 'Compte rendu de fin d’opération à lire : ' . $label . '. Mettez à jour COMSPEC ATAK pour l’ouvrir dans le téléphone.'
            : 'Compte rendu de fin d’opération à remplir : ' . $label . '. Mettez à jour COMSPEC ATAK pour le remplir dans le téléphone.';
    }

    /**
     * Réponses lues dans structured_data (clé « a_<id> » ou « answers » {id: valeur}).
     *
     * @param array<string, mixed> $structured
     * @return array<string, string>
     */
    public static function answers(array $structured): array
    {
        $out = [];
        $raw = is_array($structured['answers'] ?? null) ? $structured['answers'] : [];
        foreach ($structured as $k => $v) {
            if (is_string($k) && str_starts_with($k, 'a_')) {
                $raw[substr($k, 2)] = $v;
            }
        }
        foreach ($raw as $k => $v) {
            $key = preg_replace('/[^a-z0-9_\-]/i', '', (string) $k);
            if ($key === '' || $key === null || count($out) >= 40) {
                continue;
            }
            $val = is_array($v) ? implode(', ', array_map('strval', $v)) : (is_scalar($v) ? (string) $v : '');
            $out[$key] = self::clip($val, 4000);
        }

        return $out;
    }

    /**
     * Questions et réponses pour l'affichage (questionnaire de la demande, sinon intégré ; réponses hors questionnaire à la fin).
     *
     * @param list<array<string, mixed>> $questions
     * @param array<string, string> $answers
     * @return list<array{label: string, value: string}>
     */
    public static function present(array $questions, array $answers): array
    {
        $out = [];
        $seen = [];
        foreach ($questions !== [] ? $questions : self::BUILTIN as $q) {
            $id = (string) ($q['id'] ?? '');
            $seen[$id] = true;
            $out[] = ['label' => (string) ($q['label'] ?? $id), 'value' => (string) ($answers[$id] ?? '')];
        }
        foreach ($answers as $id => $v) {
            if (!isset($seen[$id]) && $v !== '') {
                $out[] = ['label' => (string) $id, 'value' => $v];
            }
        }

        return $out;
    }

    /** Liste d'UID Steam (64 bits) tirée d'un texte libre. @return list<string> */
    public static function parseUids(string $raw): array
    {
        preg_match_all('/\b7656\d{13}\b/', $raw, $m);

        return array_values(array_unique($m[0] ?? []));
    }

    private static function str(string $v): string
    {
        $v = (string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $v);
        $v = str_replace('|', '/', $v);

        return '"' . str_replace('"', '""', $v) . '"';
    }

    private static function clip(string $v, int $max): string
    {
        $v = trim((string) preg_replace('/\s+/u', ' ', $v));

        return mb_strlen($v) > $max ? rtrim(mb_substr($v, 0, $max - 1)) . '…' : $v;
    }
}
