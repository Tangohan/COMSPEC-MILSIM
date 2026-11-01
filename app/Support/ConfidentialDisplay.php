<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Rendu unique des éléments masqués, anonymisés ou restreints (unités, membres, affectations, comptes).
 *
 * Ne décide JAMAIS qui voit quoi (voir OrgVisibilityService / OrgVisibilityCapabilities) :
 * fournit seulement le même libellé, la même icône et la même explication partout
 * (pages publiques, back-office, ORBAT JavaScript, exports).
 *
 * Styles : public/assets/css/tailwind.input.css (bloc « .ath-confid », compilé dans tailwind.css).
 */
final class ConfidentialDisplay
{
    /** Partie de l’organisation absente pour le lecteur (branche masquée). */
    public const UNIT_HIDDEN = 'unit_hidden';
    /** Unité affichée sous un libellé générique. */
    public const UNIT_ANONYMIZED = 'unit_anonymized';
    /** Unité visible, détail et noms réservés. */
    public const UNIT_RESTRICTED = 'unit_restricted';
    /** Membre affiché sans identité. */
    public const PERSON_ANONYMIZED = 'person_anonymized';
    /** Responsable dont l’identité est réservée. */
    public const IDENTITY_RESTRICTED = 'identity_restricted';
    /** Noms réduits aux initiales (ancien mode de masquage ORBAT). */
    public const NAMES_ABBREVIATED = 'names_abbreviated';
    /** Affectation (unité, poste) non communiquée. */
    public const ASSIGNMENT_RESTRICTED = 'assignment_restricted';
    /** Effectif non communiqué. */
    public const STRENGTH_RESTRICTED = 'strength_restricted';
    /** Compte supprimé par son titulaire : données personnelles effacées. */
    public const ACCOUNT_DELETED = 'account_deleted';

    /** @var array<string, array{label: string, hint: string, tone: string, icon: string}> */
    private const KINDS = [
        self::UNIT_HIDDEN => [
            'label' => 'Structure non affichée',
            'hint' => 'Cette partie de l’organisation existe, mais elle n’est pas visible avec votre profil. Si vous devez la consulter, rapprochez-vous de votre encadrement.',
            'tone' => 'hidden',
            'icon' => 'eye-off',
        ],
        self::UNIT_ANONYMIZED => [
            'label' => 'Unité anonymisée',
            'hint' => 'Le nom et le détail de cette unité ne sont pas affichés avec votre profil : seule sa place dans l’organisation apparaît.',
            'tone' => 'anonymized',
            'icon' => 'mask',
        ],
        self::UNIT_RESTRICTED => [
            'label' => 'Informations limitées',
            'hint' => 'Cette unité est visible, mais son détail et le nom de ses membres sont réservés aux personnes habilitées.',
            'tone' => 'restricted',
            'icon' => 'lock',
        ],
        self::PERSON_ANONYMIZED => [
            'label' => 'Personnel anonymisé',
            'hint' => 'Ce membre occupe bien sa place, mais son identité n’est pas affichée avec votre profil.',
            'tone' => 'anonymized',
            'icon' => 'mask',
        ],
        self::IDENTITY_RESTRICTED => [
            'label' => 'Identité restreinte',
            'hint' => 'L’identité de cette personne est réservée aux personnes habilitées.',
            'tone' => 'restricted',
            'icon' => 'lock',
        ],
        self::NAMES_ABBREVIATED => [
            'label' => 'Noms abrégés',
            'hint' => 'Les noms de cette unité sont réduits aux initiales : votre profil ne permet pas d’afficher l’identité complète des personnes.',
            'tone' => 'anonymized',
            'icon' => 'mask',
        ],
        self::ASSIGNMENT_RESTRICTED => [
            'label' => 'Affectation restreinte',
            'hint' => 'L’unité et le poste exacts de ce membre ne sont pas affichés avec votre profil.',
            'tone' => 'restricted',
            'icon' => 'lock',
        ],
        self::STRENGTH_RESTRICTED => [
            'label' => 'Effectif restreint',
            'hint' => 'Le nombre exact de membres de cette unité n’est pas communiqué.',
            'tone' => 'restricted',
            'icon' => 'lock',
        ],
        self::ACCOUNT_DELETED => [
            'label' => 'Compte anonymisé',
            'hint' => 'Ce membre a supprimé son compte : ses données personnelles ont été effacées. Son historique reste signé « Ancien membre ».',
            'tone' => 'deleted',
            'icon' => 'user-off',
        ],
    ];

    /**
     * Anciens libellés encore stockés ou renvoyés par du code historique → type canonique.
     *
     * @var array<string, string>
     */
    private const LEGACY_TEXT = [
        'restreinte' => self::ASSIGNMENT_RESTRICTED,
        'affectation restreinte' => self::ASSIGNMENT_RESTRICTED,
        'restreint' => self::IDENTITY_RESTRICTED,
        'identité restreinte' => self::IDENTITY_RESTRICTED,
        'unité restreinte' => self::UNIT_ANONYMIZED,
        'unité anonymisée' => self::UNIT_ANONYMIZED,
        'structure anonymisée' => self::UNIT_ANONYMIZED,
        'structure non affichée' => self::UNIT_HIDDEN,
        'personnel anonymisé' => self::PERSON_ANONYMIZED,
        'informations limitées' => self::UNIT_RESTRICTED,
        'effectif restreint' => self::STRENGTH_RESTRICTED,
        'compte supprimé' => self::ACCOUNT_DELETED,
        'compte anonymisé' => self::ACCOUNT_DELETED,
    ];

    /** @return list<string> */
    public static function kinds(): array
    {
        return array_keys(self::KINDS);
    }

    public static function isKind(string $kind): bool
    {
        return isset(self::KINDS[$kind]);
    }

    public static function label(string $kind): string
    {
        return self::KINDS[$kind]['label'] ?? self::KINDS[self::UNIT_RESTRICTED]['label'];
    }

    public static function hint(string $kind): string
    {
        return self::KINDS[$kind]['hint'] ?? self::KINDS[self::UNIT_RESTRICTED]['hint'];
    }

    public static function tone(string $kind): string
    {
        return self::KINDS[$kind]['tone'] ?? 'restricted';
    }

    /**
     * Libellé d’une personne anonymisée : libellé choisi par l’organisation, sinon libellé commun.
     */
    public static function personLabel(?string $customLabel = null): string
    {
        $t = trim((string) $customLabel);

        return $t !== '' ? $t : self::label(self::PERSON_ANONYMIZED);
    }

    /**
     * Type canonique d’un texte de remplacement (« Restreinte », « Personnel anonymisé »…), sinon null.
     */
    public static function kindFromText(?string $text): ?string
    {
        $t = mb_strtolower(trim((string) $text));
        if ($t === '') {
            return null;
        }

        return self::LEGACY_TEXT[$t] ?? null;
    }

    /**
     * Type canonique décrivant un lecteur NON habilité face à un niveau de confidentialité.
     */
    public static function kindForLevel(string $level, string $subject = 'unit'): ?string
    {
        $level = VisibilityLevel::normalize($level);
        if ($level === VisibilityLevel::NORMAL) {
            return null;
        }
        if ($subject === 'personnel') {
            return match ($level) {
                VisibilityLevel::ANONYMIZED => self::PERSON_ANONYMIZED,
                default => self::IDENTITY_RESTRICTED,
            };
        }
        if ($subject === 'assignment') {
            return self::ASSIGNMENT_RESTRICTED;
        }

        return match ($level) {
            VisibilityLevel::HIDDEN => self::UNIT_HIDDEN,
            VisibilityLevel::ANONYMIZED => self::UNIT_ANONYMIZED,
            default => self::UNIT_RESTRICTED,
        };
    }

    /**
     * Données sérialisables (ORBAT JavaScript, API) : même texte que le badge serveur.
     *
     * @return array{kind: string, label: string, hint: string, tone: string}
     */
    public static function payload(string $kind, ?string $label = null): array
    {
        $kind = self::isKind($kind) ? $kind : self::UNIT_RESTRICTED;
        $l = trim((string) $label);

        return [
            'kind' => $kind,
            'label' => $l !== '' ? $l : self::label($kind),
            'hint' => self::hint($kind),
            'tone' => self::tone($kind),
        ];
    }

    /**
     * Pastille pour un lecteur habilité qui voit un élément confidentiel (« Masquée », « Anonymisée »…).
     *
     * @return array{kind: string, label: string, hint: string, tone: string}|null
     */
    public static function staffPayload(string $level, string $subject = 'unit'): ?array
    {
        $level = VisibilityLevel::normalize($level);
        if ($level === VisibilityLevel::NORMAL) {
            return null;
        }

        return [
            'kind' => 'staff_' . $level,
            'label' => VisibilityLevel::label($level),
            'hint' => 'Visible pour vous grâce à vos droits. ' . VisibilityLevel::consequence($level, $subject),
            'tone' => $level,
        ];
    }

    /** Catalogue complet (pour le JavaScript). @return array<string, array{kind: string, label: string, hint: string, tone: string}> */
    public static function catalog(): array
    {
        $out = [];
        foreach (self::kinds() as $k) {
            $out[$k] = self::payload($k);
        }

        return $out;
    }

    /**
     * Pastille HTML : icône + libellé, explication en infobulle et pour les lecteurs d’écran.
     *
     * @param string|null $label Libellé personnalisé (ex. libellé anonymisé choisi par l’organisation)
     */
    public static function badge(string $kind, ?string $label = null, bool $compact = false): string
    {
        return self::renderPayload(self::payload($kind, $label), $compact);
    }

    /** Pastille « niveau » pour un lecteur habilité ; chaîne vide si niveau normal. */
    public static function levelBadge(string $level, string $subject = 'unit', bool $compact = false): string
    {
        $p = self::staffPayload($level, $subject);

        return $p === null ? '' : self::renderPayload($p, $compact);
    }

    /**
     * Texte d’affichage : pastille si la valeur est un libellé de remplacement connu, sinon texte échappé.
     */
    public static function text(?string $value, string $fallback = '—'): string
    {
        $v = trim((string) $value);
        if ($v === '') {
            return self::e($fallback);
        }
        $kind = self::kindFromText($v);

        return $kind !== null ? self::badge($kind, null, true) : self::e($v);
    }

    /**
     * @param array{kind: string, label: string, hint: string, tone: string} $p
     */
    public static function renderPayload(array $p, bool $compact = false): string
    {
        $tone = preg_replace('/[^a-z_-]/', '', strtolower((string) ($p['tone'] ?? 'restricted'))) ?: 'restricted';
        $kind = preg_replace('/[^a-z_-]/', '', strtolower((string) ($p['kind'] ?? ''))) ?: 'unit_restricted';
        $label = (string) ($p['label'] ?? '');
        $hint = (string) ($p['hint'] ?? '');
        $icon = self::KINDS[$kind]['icon'] ?? match ($tone) {
            'hidden' => 'eye-off',
            'anonymized' => 'mask',
            'deleted' => 'user-off',
            default => 'lock',
        };

        return '<span class="ath-confid ath-confid--' . $tone . ($compact ? ' ath-confid--sm' : '') . '"'
            . ' data-confidential="' . self::e($kind) . '"'
            . ' title="' . self::e($hint) . '" tabindex="0">'
            . self::icon($icon)
            . '<span class="ath-confid__label">' . self::e($label) . '</span>'
            . '<span class="sr-only"> — ' . self::e($hint) . '</span>'
            . '</span>';
    }

    public static function icon(string $name): string
    {
        $paths = match ($name) {
            'eye-off' => '<path d="M3 3l18 18"/><path d="M10.6 5.1A10 10 0 0 1 12 5c5 0 9 4.5 10 7a12.6 12.6 0 0 1-3.2 4.3"/><path d="M6.6 6.6C4.4 8 2.8 10.1 2 12c1 2.5 5 7 10 7a9.9 9.9 0 0 0 5.4-1.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
            'mask' => '<path d="M3 7c3-1 6-1.5 9-1.5S18 6 21 7c0 6-3.5 10-9 10S3 13 3 7z"/><circle cx="8.5" cy="10.5" r="1.3"/><circle cx="15.5" cy="10.5" r="1.3"/>',
            'user-off' => '<circle cx="10" cy="8" r="4"/><path d="M2 21c.8-4 4-6 8-6 1.4 0 2.6.2 3.7.7"/><path d="M16 16l5 5M21 16l-5 5"/>',
            default => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
        };

        return '<svg class="ath-confid__icon" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
            . $paths . '</svg>';
    }

    private static function e(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    }
}
