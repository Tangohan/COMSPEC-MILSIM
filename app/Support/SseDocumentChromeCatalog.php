<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Présentation des documents / fiches SSE (chrome + style papier).
 * Langage métier : bandeau, titre, pied de page, aspect du papier.
 */
final class SseDocumentChromeCatalog
{
    public const STYLE_CLEAN = 'clean';
    public const STYLE_STAINED = 'stained';
    public const STYLE_CRUMPLED = 'crumpled';
    public const STYLE_AGED = 'aged';

    /** @return array<string, string> */
    public static function paperStyles(): array
    {
        return [
            self::STYLE_CLEAN => 'Papier propre (bureau)',
            self::STYLE_STAINED => 'Papier taché (terrain)',
            self::STYLE_CRUMPLED => 'Papier froissé',
            self::STYLE_AGED => 'Papier jauni / usé',
        ];
    }

    /**
     * Modèles préfaits (chrome + style). Identifiants stables pour Eden / Zeus / portail.
     *
     * @return list<array<string, mixed>>
     */
    public static function builtInPrefabs(): array
    {
        return [
            [
                'code' => 'standard_restreint',
                'label' => 'Standard — diffusion restreinte',
                'description' => 'Présentation actuelle du dossier : bandeau rouge, feuille crème, mention RP.',
                'paper_style' => self::STYLE_CLEAN,
                'banner' => 'DIFFUSION RESTREINTE — EXPLOITATION TERRAIN',
                'title_person' => 'DOSSIER SSE',
                'title_docs' => 'DOSSIER DOCUMENTAIRE',
                'subtitle_dossier' => 'Compte rendu d’exploitation',
                'subtitle_feuille' => 'Feuille de consultation — lecture détaillée',
                'subtitle_docs' => 'Pièces saisies sur le terrain',
                'footer' => 'Ne constitue pas une preuve judiciaire — usage RP / renseignement uniquement.',
                'quality_prefix' => 'Qualité d’exploitation',
                'org_line' => 'ATHENA · COMPSEC',
                'seal_top' => 'BUREAU SSE',
                'seal_bottom' => 'RENSEIGNEMENT',
                'access_note' => 'L’accès est limité aux personnels habilités et inscrits au registre du bureau SSE.',
                'btn_consult' => 'FEUILLE',
                'btn_transmit' => 'TRANSMETTRE',
                'btn_close' => 'FERMER',
                'is_default' => true,
            ],
            [
                'code' => 'terrain_tache',
                'label' => 'Terrain — feuille tachée',
                'description' => 'Aspect saisie sur le terrain : papier taché, ton plus brut.',
                'paper_style' => self::STYLE_STAINED,
                'banner' => 'SAISIE TERRAIN — EXPLOITATION',
                'title_person' => 'FICHE D’EXPLOITATION',
                'title_docs' => 'PIÈCES SAISIES',
                'subtitle_dossier' => 'Relevé d’exploitation sur site',
                'subtitle_feuille' => 'Lecture détaillée — pièce terrain',
                'subtitle_docs' => 'Documents récupérés sur place',
                'footer' => 'Document de travail — ne vaut pas preuve judiciaire. Usage RP / renseignement.',
                'quality_prefix' => 'Qualité de saisie',
                'org_line' => 'ATHENA · CELLULE TERRAIN',
                'seal_top' => 'SSE TERRAIN',
                'seal_bottom' => 'SAISIE',
                'access_note' => 'Circulation limitée à l’équipe d’exploitation et au poste.',
                'btn_consult' => 'DÉTAIL',
                'btn_transmit' => 'ENVOYER',
                'btn_close' => 'FERMER',
                'is_default' => false,
            ],
            [
                'code' => 'brouillon_froisse',
                'label' => 'Brouillon — papier froissé',
                'description' => 'Notes de travail froissées, ton informel pour le RP.',
                'paper_style' => self::STYLE_CRUMPLED,
                'banner' => 'BROUILLON — NE PAS DIFFUSER',
                'title_person' => 'NOTES D’EXPLOITATION',
                'title_docs' => 'PIÈCES EN COURS',
                'subtitle_dossier' => 'Brouillon de compte rendu',
                'subtitle_feuille' => 'Relecture — brouillon',
                'subtitle_docs' => 'Documents non encore classés',
                'footer' => 'Brouillon RP — non opposable. À ne pas confondre avec un acte officiel.',
                'quality_prefix' => 'Avancement',
                'org_line' => 'ATHENA · BROUILLON',
                'seal_top' => 'TRAVAIL',
                'seal_bottom' => 'BROUILLON',
                'access_note' => 'Réservé à la cellule rédactionnelle.',
                'btn_consult' => 'LIRE',
                'btn_transmit' => 'TRANSMETTRE',
                'btn_close' => 'FERMER',
                'is_default' => false,
            ],
            [
                'code' => 'bureau_jauni',
                'label' => 'Archives — papier jauni',
                'description' => 'Aspect dossier d’archives : papier jauni, formulation plus formelle.',
                'paper_style' => self::STYLE_AGED,
                'banner' => 'ARCHIVES — CONSULTATION CONTRÔLÉE',
                'title_person' => 'FICHE D’ARCHIVES',
                'title_docs' => 'DOSSIER DOCUMENTAIRE',
                'subtitle_dossier' => 'Compte rendu classé',
                'subtitle_feuille' => 'Consultation d’archives',
                'subtitle_docs' => 'Pièces versées au dossier',
                'footer' => 'Exemplaire d’archives — usage renseignement / RP. Ne constitue pas une preuve judiciaire.',
                'quality_prefix' => 'Qualité d’exploitation',
                'org_line' => 'ATHENA · ARCHIVES SSE',
                'seal_top' => 'ARCHIVES',
                'seal_bottom' => 'SSE',
                'access_note' => 'Consultation sur inscription au registre des archives.',
                'btn_consult' => 'FEUILLE',
                'btn_transmit' => 'TRANSMETTRE',
                'btn_close' => 'FERMER',
                'is_default' => false,
            ],
            [
                'code' => 'formel_propre',
                'label' => 'Bureau — papier impeccable',
                'description' => 'Présentation soignée pour diffusion au poste.',
                'paper_style' => self::STYLE_CLEAN,
                'banner' => 'DIFFUSION CONTRÔLÉE — BUREAU SSE',
                'title_person' => 'FICHE D’IDENTITÉ',
                'title_docs' => 'ANNEXE DOCUMENTAIRE',
                'subtitle_dossier' => 'Compte rendu d’exploitation',
                'subtitle_feuille' => 'Feuille officielle',
                'subtitle_docs' => 'Pièces jointes',
                'footer' => 'Document de renseignement — usage RP uniquement. Ne constitue pas une preuve judiciaire.',
                'quality_prefix' => 'Qualité d’exploitation',
                'org_line' => 'ATHENA · COMPSEC',
                'seal_top' => 'BUREAU SSE',
                'seal_bottom' => 'OFFICIEL',
                'access_note' => 'Réservé aux personnels habilités du bureau.',
                'btn_consult' => 'FEUILLE',
                'btn_transmit' => 'TRANSMETTRE',
                'btn_close' => 'FERMER',
                'is_default' => false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultChrome(): array
    {
        foreach (self::builtInPrefabs() as $row) {
            if (!empty($row['is_default'])) {
                return $row;
            }
        }

        return self::builtInPrefabs()[0];
    }

    /**
     * @param array<string, mixed> $chrome
     * @return array<string, mixed>
     */
    public static function normalize(array $chrome): array
    {
        $base = self::defaultChrome();
        $styles = self::paperStyles();
        $out = $base;
        foreach ([
            'code', 'label', 'description', 'paper_style', 'banner',
            'title_person', 'title_docs', 'subtitle_dossier', 'subtitle_feuille', 'subtitle_docs',
            'footer', 'quality_prefix', 'org_line', 'seal_top', 'seal_bottom', 'access_note',
            'btn_consult', 'btn_transmit', 'btn_close',
        ] as $key) {
            if (array_key_exists($key, $chrome) && is_scalar($chrome[$key])) {
                $v = trim((string) $chrome[$key]);
                if ($v !== '') {
                    $out[$key] = $v;
                }
            }
        }
        $style = (string) ($out['paper_style'] ?? self::STYLE_CLEAN);
        if (!isset($styles[$style])) {
            $out['paper_style'] = self::STYLE_CLEAN;
        }
        $out['is_default'] = !empty($chrome['is_default']);
        $out['is_builtin'] = !empty($chrome['is_builtin']);
        $out['is_active'] = !array_key_exists('is_active', $chrome) || !empty($chrome['is_active']);

        return $out;
    }

    public static function findBuiltIn(string $code): ?array
    {
        $code = trim($code);
        foreach (self::builtInPrefabs() as $row) {
            if ((string) ($row['code'] ?? '') === $code) {
                return $row;
            }
        }

        return null;
    }
}
