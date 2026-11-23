<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Thèmes de feuille des documents SSE : papier, encre, bandeaux de classification, filigrane, tampon et typographie.
 *
 * Tout est original : aucun logo, sceau, emblème ni nom d'agence réelle ; l'organisation est celle du portail (ATHENA / SSE).
 * Le papier reste clair dans tous les thèmes (le texte des modèles est sombre) ; les aplats sombres ne portent que des
 * bandeaux avec un texte clair, couple vérifié par SseDocumentTemplatesLegibilityTest (contraste d'au moins 4,5 pour 1).
 * Le même jeu de couleurs sert la feuille de l'éditeur, l'aperçu (public/assets/css/sse-doc-themes.css) et le PDF
 * (App\Support\Pdf\SseThemedTcpdf).
 *
 * Polices : uniquement des familles déjà présentes sur les postes ou chargées par le portail ; pas de fonte externe.
 */
final class SseSheetThemes
{
    public const DEFAULT = 'classique';

    public const FONT_SANS = '"Segoe UI", "IBM Plex Sans", Inter, Arial, sans-serif';
    public const FONT_SERIF = 'Georgia, "Times New Roman", serif';
    public const FONT_TYPEWRITER = '"Courier New", Courier, "JetBrains Mono", monospace';
    public const FONT_CONDENSED = '"Arial Narrow", "Barlow Condensed", "Helvetica Neue", Arial, sans-serif';

    /**
     * id => paper, ink, muted, accent (titres et filets), rule, band_bg/band_fg (bandeaux de classification), head_bg/head_fg
     * (bloc d'identification), stamp (tampon), mark (filigrane, très pâle), body (famille du corps), pdf_body (famille PDF :
     * sans|serif|mono), label, description.
     *
     * @var array<string, array<string,string>>
     */
    public const THEMES = [
        'classique' => [
            'label' => 'Classique', 'description' => 'Feuille blanche sobre, bandeaux anthracite.',
            'paper' => '#ffffff', 'ink' => '#0f172a', 'muted' => '#475569', 'accent' => '#334155', 'rule' => '#94a3b8',
            'band_bg' => '#0f172a', 'band_fg' => '#ffffff', 'head_bg' => '#e2e8f0', 'head_fg' => '#0f172a',
            'stamp' => '#b91c1c', 'mark' => '#e2e8f0', 'body' => self::FONT_SANS, 'pdf_body' => 'sans',
        ],
        'vert_sombre' => [
            'label' => 'Vert sombre et ivoire', 'description' => 'Papier ivoire, bandeau vert sombre, corps en empattements.',
            'paper' => '#fbf8ee', 'ink' => '#1c2a22', 'muted' => '#4a5a50', 'accent' => '#1f4d3a', 'rule' => '#8aa396',
            'band_bg' => '#1f4d3a', 'band_fg' => '#f5f1e0', 'head_bg' => '#1f4d3a', 'head_fg' => '#f5f1e0',
            'stamp' => '#8a1c1c', 'mark' => '#e8e4d2', 'body' => self::FONT_SERIF, 'pdf_body' => 'serif',
        ],
        'bleu_nuit' => [
            'label' => 'Bleu nuit et gris acier', 'description' => 'Papier gris très clair, bandeau bleu nuit, filets gris acier.',
            'paper' => '#f4f6f9', 'ink' => '#101828', 'muted' => '#475467', 'accent' => '#1b2a49', 'rule' => '#8a97ab',
            'band_bg' => '#1b2a49', 'band_fg' => '#e6ebf2', 'head_bg' => '#1b2a49', 'head_fg' => '#e6ebf2',
            'stamp' => '#1b2a49', 'mark' => '#dfe4ec', 'body' => self::FONT_SANS, 'pdf_body' => 'sans',
        ],
        'noir_tactique' => [
            'label' => 'Noir tactique', 'description' => 'Bandeaux noirs et ambre, corps en machine à écrire.',
            'paper' => '#f1f1ef', 'ink' => '#111111', 'muted' => '#4b4b4b', 'accent' => '#111111', 'rule' => '#6b6b6b',
            'band_bg' => '#111111', 'band_fg' => '#f2c744', 'head_bg' => '#111111', 'head_fg' => '#f2c744',
            'stamp' => '#111111', 'mark' => '#dededb', 'body' => self::FONT_TYPEWRITER, 'pdf_body' => 'mono',
        ],
        'rouge_secret' => [
            'label' => 'Rouge très secret', 'description' => 'Bandeaux et tampon rouge sombre, corps en machine à écrire.',
            'paper' => '#fdf6f4', 'ink' => '#1a1010', 'muted' => '#5a4545', 'accent' => '#8b1515', 'rule' => '#c49a9a',
            'band_bg' => '#8b1515', 'band_fg' => '#ffffff', 'head_bg' => '#8b1515', 'head_fg' => '#ffffff',
            'stamp' => '#8b1515', 'mark' => '#f2dcd8', 'body' => self::FONT_TYPEWRITER, 'pdf_body' => 'mono',
        ],
        'archives' => [
            'label' => 'Archives papier jaunies', 'description' => 'Papier jauni, encre brune, corps en machine à écrire.',
            'paper' => '#f0e4c1', 'ink' => '#2b2110', 'muted' => '#5a4a2a', 'accent' => '#5a4318', 'rule' => '#a8946a',
            'band_bg' => '#5a4318', 'band_fg' => '#f7ecce', 'head_bg' => '#5a4318', 'head_fg' => '#f7ecce',
            'stamp' => '#7a2a12', 'mark' => '#e2d4aa', 'body' => self::FONT_TYPEWRITER, 'pdf_body' => 'mono',
        ],
    ];

    /** @return array<string, array<string,string>> */
    public static function all(): array
    {
        return self::THEMES;
    }

    public static function normalize(?string $id): string
    {
        $id = strtolower(trim((string) $id));

        return isset(self::THEMES[$id]) ? $id : self::DEFAULT;
    }

    /** @return array<string,string> */
    public static function get(?string $id): array
    {
        return self::THEMES[self::normalize($id)];
    }

    /** Thème par défaut de chaque modèle du catalogue (les autres gardent le thème « classique »). */
    public static function defaultForTemplate(string $code): string
    {
        return match ($code) {
            'flash', 'fiche_recherche' => 'rouge_secret',
            'note_renseignement', 'sitrep' => 'vert_sombre',
            'compte_rendu_entretien', 'proces_verbal' => 'archives',
            'rapport_incident', 'fiche_controle_identite' => 'noir_tactique',
            'courrier_officiel' => 'bleu_nuit',
            default => self::DEFAULT,
        };
    }

    /** Classe CSS de la feuille. */
    public static function cssClass(?string $id): string
    {
        return 'sse-theme--' . self::normalize($id);
    }

    /** @return array{0:int,1:int,2:int} */
    public static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
    }
}
