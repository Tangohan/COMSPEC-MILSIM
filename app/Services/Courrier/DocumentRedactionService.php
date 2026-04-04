<?php

declare(strict_types=1);

namespace App\Services\Courrier;

/**
 * Caviardage : marqueurs [[REDACT]]...[[/REDACT]] dans le corps HTML.
 * - Aperçu / export interne : surlignage noir (texte conservé).
 * - Export externe (PDF) : suppression irréversible du texte source dans le flux HTML.
 */
final class DocumentRedactionService
{
    private const PATTERN = '/\[\[REDACT\]\](.*?)\[\[\/REDACT\]\]/s';

    /** Surlignage noir pour prévisualisation et diffusion interne. */
    public function applyVisualMarkers(string $html): string
    {
        return preg_replace_callback(self::PATTERN, static function (array $m): string {
            $inner = htmlspecialchars($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return '<span class="courrier-redact-visual" title="Zone sensible (interne)">' . $inner . '</span>';
        }, $html) ?? $html;
    }

    /**
     * Export externe : remplace par un bloc noir sans texte extractible (pas de contenu dans le nœud).
     */
    public function applyIrreversibleForExport(string $html): string
    {
        return preg_replace_callback(self::PATTERN, static function (): string {
            return '<span class="courrier-redact-block" style="display:inline-block;min-width:4ch;min-height:1.15em;background:#0b1220;vertical-align:baseline;" title="Caviardé">&nbsp;</span>';
        }, $html) ?? $html;
    }
}
