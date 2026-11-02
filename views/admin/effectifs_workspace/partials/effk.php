<?php
declare(strict_types=1);

/*
 * Kit commun des pages Effectifs (maquettes « Effectifs Chaîne » et « Effectifs Affectations », 2026-11-02) :
 * en-tête de page, bande de chiffres, pastilles, avatars, grade, état vide, icônes.
 * Styles : public/assets/css/effectifs-kit.css (classes .effk-*, jetons --ath-*, mode nuit compris).
 * Réutilisable par toute page Effectifs : require_once ce fichier, puis appeler les fonctions effk_*().
 */

if (!function_exists('effk_e')) {
    function effk_e(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    }

    /** Icône au trait (même dessin que les maquettes). */
    function effk_icon(string $name, string $class = ''): string
    {
        $paths = [
            'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
            'x' => '<path d="M18 6 6 18M6 6l12 12"/>',
            'plus' => '<path d="M12 5v14M5 12h14"/>',
            'move' => '<path d="M5 9l-3 3 3 3M19 9l3 3-3 3M2 12h20"/>',
            'down' => '<path d="m6 9 6 6 6-6"/>',
            'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
            'check' => '<path d="M5 12l5 5 9-10"/>',
            'alert' => '<path d="M12 3 2 20h20zM12 10v4M12 17v.5"/>',
            'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4-6 8-6s7 2 8 6"/>',
            'route' => '<circle cx="6" cy="19" r="2"/><circle cx="18" cy="5" r="2"/><path d="M8 19h7a3 3 0 0 0 0-6H9a3 3 0 0 1 0-6h7"/>',
            'tree' => '<rect x="9" y="3" width="6" height="5" rx="1"/><rect x="3" y="16" width="6" height="5" rx="1"/><rect x="15" y="16" width="6" height="5" rx="1"/><path d="M12 8v4M6 16v-4h12v4"/>',
        ];

        return '<svg class="effk-i' . ($class !== '' ? ' ' . effk_e($class) : '') . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
            . ($paths[$name] ?? $paths['user']) . '</svg>';
    }

    /** Couleur stable d'une unité (repère visuel, sans signification). */
    function effk_unit_color(int $unitId): string
    {
        $palette = ['#059669', '#0284c7', '#7c3aed', '#d97706', '#db2777', '#0891b2', '#65a30d', '#475569'];

        return $palette[abs($unitId) % count($palette)];
    }

    /** Couleur stable de l'avatar d'un membre. */
    function effk_member_color(int $userId): string
    {
        $palette = ['#059669', '#1d4ed8', '#d97706', '#7c3aed', '#db2777', '#0891b2', '#475569', '#b45309'];

        return $palette[abs($userId) % count($palette)];
    }

    function effk_initials(string $name): string
    {
        $parts = preg_split('/[\s.\-]+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($parts === []) {
            return '?';
        }
        $first = mb_substr($parts[0], 0, 1, 'UTF-8');
        $second = isset($parts[1]) ? mb_substr($parts[1], 0, 1, 'UTF-8') : mb_substr($parts[0], 1, 1, 'UTF-8');

        return mb_strtoupper($first . $second, 'UTF-8');
    }

    /** Avatar à initiales ; $size : '' (30 px), 'sm' (22 px), 'lg' (44 px). */
    function effk_avatar(int $userId, string $name, string $size = ''): string
    {
        return '<span class="effk-av' . ($size !== '' ? ' effk-av--' . effk_e($size) : '') . '" style="--av:' . effk_member_color($userId) . '" aria-hidden="true">'
            . effk_e(effk_initials($name)) . '</span>';
    }

    function effk_grade(string $grade): string
    {
        $grade = trim($grade);

        return $grade === '' ? '' : '<span class="effk-gd" translate="no">' . effk_e($grade) . '</span>';
    }

    /** Libellé lisible du type d'unité enregistré. */
    function effk_unit_type_label(string $typeRaw): string
    {
        return match (mb_strtolower(trim($typeRaw), 'UTF-8')) {
            'company', 'compagnie' => 'Compagnie',
            'platoon', 'peloton' => 'Peloton',
            'section' => 'Section',
            'squad', 'groupe', 'group' => 'Groupe',
            'hq', 'etat_major', 'état-major' => 'État-major',
            'team', 'equipe', 'équipe' => 'Équipe',
            'battalion', 'bataillon' => 'Bataillon',
            'squadron', 'escadron' => 'Escadron',
            'troop' => 'Troop',
            '' => '',
            default => trim($typeRaw),
        };
    }

    /** Pastille de confidentialité d'une unité pour un lecteur habilité (rendu commun ConfidentialDisplay). */
    function effk_unit_level_badge(string $level): string
    {
        return $level === '' || $level === 'normal' ? '' : \App\Support\ConfidentialDisplay::levelBadge($level, 'unit', true);
    }

    /** Date courte « 2 nov. » (année ajoutée si elle diffère de l'année en cours), dans la langue du lecteur. */
    function effk_short_date(string $ymd): string
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $ymd, $m)) {
            return '';
        }
        $out = (int) $m[3] . ' ' . \App\Support\LocalizedCalendar::month((int) $m[2], true);

        return $m[1] !== date('Y') ? $out . ' ' . $m[1] : $out;
    }

    /**
     * En-tête de page : sur-titre, titre, chapeau et actions (HTML déjà échappé).
     */
    function effk_page_head(string $kicker, string $title, string $lead, string $actionsHtml = '', string $titleId = ''): string
    {
        return '<header class="effk-ph">'
            . '<div class="effk-ph__copy">'
            . ($kicker !== '' ? '<span class="effk-k">' . effk_e($kicker) . '</span>' : '')
            . '<h2' . ($titleId !== '' ? ' id="' . effk_e($titleId) . '"' : '') . '>' . effk_e($title) . '</h2>'
            . ($lead !== '' ? '<p>' . effk_e($lead) . '</p>' : '')
            . '</div>'
            . ($actionsHtml !== '' ? '<div class="effk-ph__acts">' . $actionsHtml . '</div>' : '')
            . '</header>';
    }

    /**
     * Bande de chiffres. Chaque entrée : label, value, hint, tone ('' | 'warn' | 'bad' | 'ok'), href ('' = non cliquable).
     *
     * @param list<array{label:string,value:int|string,hint?:string,tone?:string,href?:string}> $items
     */
    function effk_stats(array $items, string $ariaLabel): string
    {
        $html = '<div class="effk-stats" role="list" aria-label="' . effk_e($ariaLabel) . '">';
        foreach ($items as $it) {
            $tone = (string) ($it['tone'] ?? '');
            $href = (string) ($it['href'] ?? '');
            $inner = '<span class="effk-k">' . effk_e((string) $it['label']) . '</span>'
                . '<b' . ($tone !== '' ? ' class="is-' . effk_e($tone) . '"' : '') . '>' . effk_e((string) $it['value']) . '</b>'
                . (($it['hint'] ?? '') !== '' ? '<small>' . effk_e((string) $it['hint']) . '</small>' : '');
            $html .= $href !== ''
                ? '<a class="effk-stat is-link" role="listitem" href="' . effk_e($href) . '">' . $inner . '</a>'
                : '<div class="effk-stat" role="listitem">' . $inner . '</div>';
        }

        return $html . '</div>';
    }

    /**
     * Pastille d'alerte ou d'état. $tone : 'warn' | 'ok' | 'info' | ''. $attrs : attributs HTML supplémentaires (valeurs échappées ici).
     * Avec href : lien ; avec $button : bouton ; sinon simple étiquette.
     *
     * @param array<string, string> $attrs
     */
    function effk_pill(string $label, string $tone = '', string $icon = '', string $href = '', bool $button = false, array $attrs = []): string
    {
        $extra = '';
        foreach ($attrs as $k => $v) {
            $extra .= ' ' . effk_e($k) . '="' . effk_e($v) . '"';
        }
        $cls = 'effk-pill' . ($tone !== '' ? ' is-' . effk_e($tone) : '');
        $inner = ($icon !== '' ? effk_icon($icon) : '') . '<span>' . effk_e($label) . '</span>';
        if ($href !== '') {
            return '<a class="' . $cls . '" href="' . effk_e($href) . '"' . $extra . '>' . $inner . '</a>';
        }
        if ($button) {
            return '<button type="button" class="' . $cls . '"' . $extra . '>' . $inner . '</button>';
        }

        return '<span class="' . $cls . '"' . $extra . '>' . $inner . '</span>';
    }

    function effk_empty(string $title, string $text = '', string $extraHtml = ''): string
    {
        return '<div class="effk-empty"><b>' . effk_e($title) . '</b>' . ($text !== '' ? effk_e($text) : '') . $extraHtml . '</div>';
    }
}
