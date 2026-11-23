<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Briques HTML communes aux assistants de création SSE (barre de progression, cartes de choix, messages d'erreur).
 * Le comportement (étapes, validation, brouillon) est dans public/assets/js/sse-wizard.js, piloté par des attributs data-wz-*.
 */
final class SseWizardUi
{
    private static function h(mixed $v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Barre de progression : une pastille par étape, accessible au clavier.
     *
     * @param list<string> $titles intitulés des étapes
     */
    public static function progress(array $titles): string
    {
        $n = count($titles);
        $out = '<nav class="wz-progress" aria-label="Progression"><div class="wz-bar" role="progressbar" aria-label="Avancement"'
            . ' aria-valuemin="1" aria-valuemax="' . $n . '" aria-valuenow="1" data-wz-bar><span class="wz-bar__fill" data-wz-fill></span></div>'
            . '<ol class="wz-steps">';
        foreach ($titles as $i => $title) {
            $k = $i + 1;
            $out .= '<li class="wz-steps__item" data-wz-prog="' . $k . '"><button type="button" class="wz-steps__btn" data-wz-goto="' . $k . '">'
                . '<span class="wz-steps__n" aria-hidden="true">' . $k . '</span>'
                . '<span class="wz-steps__t">' . self::h($title) . '</span></button></li>';
        }

        return $out . '</ol><p class="wz-now" data-wz-now aria-live="polite"><span>Étape</span> <b data-wz-cur>1</b> <span>sur</span> <b>'
            . $n . '</b> <span aria-hidden="true">·</span> <span data-wz-cur-title></span></p></nav>';
    }

    /**
     * Messages d'erreur d'un champ, masqués jusqu'à ce que la validation en révèle un.
     *
     * @param array<string, string> $messages règle => message
     */
    public static function errors(string $id, array $messages): string
    {
        $out = '';
        foreach ($messages as $rule => $text) {
            $out .= '<p class="wz-error" id="' . self::h($id . '-err-' . $rule) . '" data-wz-err="' . self::h($rule) . '" hidden>'
                . self::h($text) . '</p>';
        }

        return $out;
    }

    /**
     * Groupe de cartes de choix (boutons radio).
     *
     * @param array<string, array<string, mixed>> $options clé => [label, desc, disabled?, note?]
     * @param array{required?:bool, help?:string, hint?:string, when?:string, seeded?:bool, tpl?:string, tplTarget?:string, error?:string, compact?:bool} $opt
     */
    public static function cards(string $name, string $legend, array $options, string $selected, array $opt = []): string
    {
        $required = !empty($opt['required']);
        $id = 'wz-' . trim((string) preg_replace('/[^a-z0-9]+/i', '-', $name), '-');
        $when = (string) ($opt['when'] ?? '');
        $out = '<fieldset class="wz-group" data-wz-field="' . self::h($name) . '"'
            . ($required ? ' data-wz-rules="required"' : '')
            . ($when !== '' ? ' data-wz-when="' . self::h($when) . '"' : '') . '>'
            . '<legend class="wz-legend">' . self::h($legend)
            . ' <span class="wz-flag' . ($required ? ' wz-flag--req' : '') . '">' . ($required ? 'obligatoire' : 'facultatif') . '</span></legend>';
        if (!empty($opt['hint'])) {
            $out .= '<p class="wz-hint" id="' . $id . '-hint">' . self::h($opt['hint']) . '</p>';
        }
        if (!empty($opt['help'])) {
            $out .= '<details class="wz-help"><summary>Besoin d’aide pour choisir ?</summary><p>' . self::h($opt['help']) . '</p></details>';
        }
        $out .= '<div class="wz-cards' . (!empty($opt['compact']) ? ' wz-cards--compact' : '') . '">';
        foreach ($options as $key => $o) {
            $disabled = !empty($o['disabled']);
            $out .= '<label class="wz-card' . ($disabled ? ' is-disabled' : '') . '">'
                . '<input type="radio" name="' . self::h($name) . '" value="' . self::h($key) . '"'
                . ((string) $key === $selected && !$disabled ? ' checked' : '')
                . ($disabled ? ' disabled' : '')
                . (!empty($opt['seeded']) ? ' data-wz-seed' : '')
                . (isset($o['tpl']) ? ' data-wz-tpl="' . self::h($o['tpl']) . '"' : '')
                . ' aria-describedby="' . $id . '-err-required">'
                . '<span class="wz-card__body"><span class="wz-card__title">' . self::h($o['label'] ?? $key) . '</span>'
                . '<span class="wz-card__desc">' . self::h($o['desc'] ?? '') . '</span>'
                . (!empty($o['note']) ? '<span class="wz-card__note">' . self::h($o['note']) . '</span>' : '')
                . '</span></label>';
        }
        $out .= '</div>';
        if ($required) {
            $out .= self::errors($id, ['required' => (string) ($opt['error'] ?? 'Choisissez une des options.')]);
        }

        return $out . '</fieldset>';
    }
}
