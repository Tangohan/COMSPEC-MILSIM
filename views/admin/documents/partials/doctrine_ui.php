<?php
declare(strict_types=1);

/*
 * Doctrine — éléments communs des pages du module (pilotage, publier, nouvelle version, types, nomenclature),
 * dans le langage visuel des pages Effectifs (Documents RH, Chaîne, Affectations).
 * Styles : public/assets/css/doctrine-hub.css (classes .dct-*, jetons --ath-*, mode nuit compris).
 * Le Suivi de diffusion (doctrine_compliance.php) a sa propre feuille : seuls des liens y mènent.
 */

if (!function_exists('dct_e')) {
    function dct_e(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    }

    /** Icône au trait (même dessin que les maquettes Effectifs). */
    function dct_icon(string $name, string $class = ''): string
    {
        $paths = [
            'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
            'x' => '<path d="M18 6 6 18M6 6l12 12"/>',
            'plus' => '<path d="M12 5v14M5 12h14"/>',
            'down' => '<path d="m6 9 6 6 6-6"/>',
            'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
            'back' => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
            'check' => '<path d="M5 12l5 5 9-10"/>',
            'alert' => '<path d="M12 3 2 20h20zM12 10v4M12 17v.5"/>',
            'file' => '<path d="M14 3H6v18h12V7zM14 3v4h4"/>',
            'pen' => '<path d="M4 20h4L19 9l-4-4L4 16z"/>',
            'up' => '<path d="M12 20V8M6 14l6-6 6 6M4 4h16"/>',
            'dl' => '<path d="M12 4v12M6 10l6 6 6-6M4 20h16"/>',
            'eye' => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
            'bell' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10 21a2 2 0 0 0 4 0"/>',
            'users' => '<circle cx="9" cy="8" r="4"/><path d="M2 21c1-4 3.5-6 7-6s6 2 7 6M16 4a4 4 0 0 1 0 8M22 21c-.5-3-2-5-4-5.5"/>',
            'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'layers' => '<path d="m12 3 9 5-9 5-9-5zM3 13l9 5 9-5"/>',
            'tag' => '<path d="M3 12V3h9l9 9-9 9zM7.5 7.5h.01"/>',
            'archive' => '<rect x="3" y="4" width="18" height="4" rx="1"/><path d="M5 8v12h14V8M10 12h4"/>',
            'dots' => '<circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/>',
            'book' => '<path d="M4 4h6a2 2 0 0 1 2 2v14a2 2 0 0 0-2-2H4zM20 4h-6a2 2 0 0 0-2 2v14a2 2 0 0 1 2-2h6z"/>',
            'send' => '<path d="M22 2 11 13M22 2l-7 20-4-9-9-4z"/>',
            'job' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
        ];

        return '<svg class="dct-i' . ($class !== '' ? ' ' . dct_e($class) : '') . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
            . ($paths[$name] ?? $paths['file']) . '</svg>';
    }

    /** « 2 nov. 2026 » (année omise si c’est l’année en cours), dans la langue du lecteur ; avec l’heure si demandé. */
    function dct_date(?string $raw, bool $withTime = false): string
    {
        $raw = trim((string) $raw);
        $ts = $raw !== '' ? strtotime($raw) : false;
        if (!$ts) {
            return '';
        }
        $out = (int) date('j', $ts) . ' ' . \App\Support\LocalizedCalendar::month((int) date('n', $ts), true);
        if (date('Y', $ts) !== date('Y')) {
            $out .= ' ' . date('Y', $ts);
        }

        return $withTime ? $out . ' · ' . date('H:i', $ts) : $out;
    }

    /**
     * En-tête commun : sur-titre, titre, chapeau, actions (HTML déjà échappé), puis onglets du module.
     *
     * @param array<string, bool> $can create, manage, compliance
     */
    function dct_head(string $kicker, string $title, string $lead, string $actionsHtml, string $current, array $can): string
    {
        $tabs = [
            'hub' => ['Vue d’ensemble', 'back-office/doctrine', true],
            'publish' => ['Publier', 'back-office/documents/publier', !empty($can['create'])],
            'compliance' => ['Suivi de diffusion', 'back-office/documents/compliance', !empty($can['compliance'])],
            'types' => ['Types', 'back-office/documents/types', !empty($can['manage'])],
            'nomenclature' => ['Nomenclature', 'back-office/documents/nomenclature', !empty($can['manage'])],
            'referential' => ['Référentiel doctrinal', 'back-office/doctrine/referentiel', true],
        ];
        $nav = '';
        foreach ($tabs as $key => [$label, $path, $ok]) {
            if (!$ok) {
                continue;
            }
            $nav .= '<a href="' . dct_e(url($path)) . '"' . ($key === $current ? ' aria-current="page" class="is-on"' : '') . '>' . dct_e($label) . '</a>';
        }

        return '<header class="dct-ph">'
            . '<div class="dct-ph__copy">'
            . '<span class="dct-k">' . dct_e($kicker) . '</span>'
            . '<h1 class="dct-ph__title">' . dct_e($title) . '</h1>'
            . ($lead !== '' ? '<p class="dct-ph__lead">' . dct_e($lead) . '</p>' : '')
            . '</div>'
            . ($actionsHtml !== '' ? '<div class="dct-ph__acts">' . $actionsHtml . '</div>' : '')
            . '</header>'
            . '<nav class="dct-tabs" aria-label="Pages du module Doctrine">' . $nav . '</nav>';
    }

    /** Messages de session (succès, erreur) au format du module. */
    function dct_flashes(): string
    {
        $out = '';
        foreach (['error' => 'is-err', 'success' => 'is-ok'] as $key => $cls) {
            $msg = \App\Core\Session::getFlash($key);
            if (is_string($msg) && $msg !== '') {
                $out .= '<p class="dct-flash ' . $cls . '" role="' . ($key === 'error' ? 'alert' : 'status') . '">'
                    . dct_icon($key === 'error' ? 'alert' : 'check') . '<span>' . dct_e($msg) . '</span></p>';
            }
        }

        return $out;
    }

    /** Pastille d’état d’un document doctrinal. */
    function dct_state_pill(string $state, string $label): string
    {
        return '<span class="dct-st dct-st--' . dct_e($state) . '">' . dct_e($label) . '</span>';
    }

    /** Petite case colorée (type, domaine) : couleur enregistrée ou teinte neutre. */
    function dct_swatch(string $color): string
    {
        return '<span class="dct-sw" aria-hidden="true"' . ($color !== '' ? ' style="--c:' . dct_e($color) . '"' : '') . '></span>';
    }
}
