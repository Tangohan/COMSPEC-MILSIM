<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Session;
use App\Services\Personnel\MemberNameSettings;

/**
 * POINT UNIQUE pour afficher le nom d’un membre (annuaire, fiche, effectifs, JNET, messagerie, notifications,
 * e-mails, recherche, classements, back-office). Détail et tableau des champs : docs/identites-membres.md.
 *
 * ORDRE DE PRIORITÉ (contextes « list » et « card ») :
 *   1. prénom et/ou nom d’état civil du personnage (user_profiles : first_name, last_name), au format de la
 *      communauté : « Prénom N. » en liste, « Prénom NOM » sur une fiche (MemberNameSettings) ;
 *   2. à défaut, l’indicatif (users.callsign) ;
 *   3. à défaut, le nom du compte (users.display_name, souvent un pseudo Discord ; jamais une adresse e-mail) ;
 *   4. à défaut, « Membre » ({@see MemberName::FALLBACK}).
 * Le nom du personnage (personnel_profiles.character_name, ancien champ) ne compte plus : il reste en base, mais
 * aucun affichage n’en dépend.
 *
 * CONTEXTE « c2 » (carte tactique, ATAK, ordres, données du jeu) : l’indicatif d’abord ({@see member_name_c2()}),
 * puis le nom abrégé de l’état civil, puis le nom du compte, puis « Membre ». Les contrats /api/atak et /api/game
 * ne passent pas par ici.
 *
 * ANONYMISATION : une ligne marquée « anonymized » (membre masqué pour le lecteur, voir ConfidentialDisplay) ne
 * donne que le libellé de remplacement : ni nom, ni indicatif, ni trigramme.
 *
 * Toutes les chaînes renvoyées sont du TEXTE BRUT : l’échappement HTML reste à la charge de la vue.
 *
 * Ligne attendue (toutes les clés facultatives) : first_name, last_name, callsign, display_name, public_name,
 * trigram, grade_short, anonymized, label.
 */
final class MemberIdentity
{
    public const LIST = 'list';
    public const CARD = 'card';
    public const C2 = 'c2';

    /** Origines possibles du nom principal. */
    public const SOURCE_CIVIL = 'civil';
    public const SOURCE_CALLSIGN = 'callsign';
    public const SOURCE_ACCOUNT = 'account';
    public const SOURCE_FALLBACK = 'fallback';
    public const SOURCE_MASKED = 'masked';

    /**
     * @param array<string, mixed> $row
     * @param ?int $tenantId communauté dont on suit les réglages de nom (session si null)
     * @param bool $allowGrade false là où le grade est déjà affiché à côté du nom
     * @param array{list_format: string, card_format: string, show_grade: bool, trigrams_enabled: bool}|null $settings réglages déjà chargés (tests, boucles)
     * @return array{primary: string, secondary: string, callsign: string, trigram: string, source: string}
     */
    public static function display(array $row, string $context = self::LIST, ?int $tenantId = null, bool $allowGrade = true, ?array $settings = null): array
    {
        $settings ??= MemberNameSettings::forTenant($tenantId ?? (int) Session::get('tenant_id'));
        $source = MemberName::source($row);
        if ($source === self::SOURCE_MASKED) {
            return [
                'primary' => MemberName::maskedLabel($row),
                'secondary' => '',
                'callsign' => '',
                'trigram' => '',
                'source' => $source,
            ];
        }
        if (!$settings['trigrams_enabled']) {
            $row['trigram'] = '';
        }
        $trigram = MemberName::trigram($row);
        $callsign = MemberName::callsign($row);

        if ($context === self::C2) {
            $primary = MemberName::c2($row);
            if ($callsign !== '') {
                $source = self::SOURCE_CALLSIGN;
            }
        } else {
            $withGrade = $allowGrade && $settings['show_grade'];
            $primary = MemberName::format($row, MemberNameSettings::formatFor($settings, $context), $withGrade);
        }

        return [
            'primary' => $primary,
            'secondary' => $context === self::C2 ? '' : self::secondaryLine($row, $primary, $context, $trigram, $callsign),
            'callsign' => $callsign,
            'trigram' => $trigram,
            'source' => $source,
        ];
    }

    /** Nom principal seul (cas le plus courant). @param array<string, mixed> $row */
    public static function name(array $row, string $context = self::LIST, ?int $tenantId = null, bool $allowGrade = true): string
    {
        return self::display($row, $context, $tenantId, $allowGrade)['primary'];
    }

    /**
     * Ligne secondaire unique (sous-titre, infobulle) : grade, indicatif, trigramme, et en fiche le nom du compte.
     * Jamais une valeur déjà écrite dans le nom principal ni dans le nom d’état civil.
     *
     * @param array<string, mixed> $row
     */
    private static function secondaryLine(array $row, string $primary, string $context, string $trigram, string $callsign): string
    {
        $seen = [];
        foreach ([$primary, MemberName::full($row), MemberName::short($row)] as $known) {
            $seen[self::fold($known)] = true;
        }
        $candidates = [];
        $grade = self::clean($row['grade_short'] ?? '');
        if ($grade !== '' && self::fold(mb_substr($primary, 0, mb_strlen($grade) + 1)) !== self::fold($grade . ' ')) {
            $candidates[] = $grade;
        }
        $candidates[] = $callsign;
        $candidates[] = $trigram;
        if ($context === self::CARD) {
            foreach (['display_name', 'public_name'] as $key) {
                $v = self::clean($row[$key] ?? '');
                if ($v !== '' && !MemberName::looksLikeEmail($v)) {
                    $candidates[] = $v;
                }
            }
        }
        $parts = [];
        foreach ($candidates as $c) {
            $k = self::fold($c);
            if ($c === '' || isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $parts[] = $c;
        }

        return implode(' · ', $parts);
    }

    private static function clean(mixed $raw): string
    {
        $v = trim((string) preg_replace('/\s+/u', ' ', (string) $raw));

        return ($v === '—' || $v === '-') ? '' : $v;
    }

    private static function fold(string $v): string
    {
        return mb_strtolower(trim($v), 'UTF-8');
    }
}
