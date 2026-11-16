<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Sse\SseAccessCodeService;

/**
 * Menu « Espaces » de la barre Athena : regroupe les destinations, repère l'espace courant et fournit les icônes.
 * Une seule source pour la barre des membres, celle des pages d'accès et le menu mobile
 * (views/partials/athena_espaces_menu.php).
 *
 * Chaque entrée porte un code court stable (« abbr ») : c'est lui qui désigne son groupe et son icône.
 */
final class EspacesMenu
{
    /** Chemin du Bureau SSE : l'accueil du portail SSE (route GET /atak/sse/accueil). */
    public const SSE_PATH = 'atak/sse/accueil';

    /** Groupes, dans l'ordre d'affichage : id => [libellé, codes des entrées]. */
    private const GROUPS = [
        'operations' => ['Opérations', ['ATK', 'OPS', 'TOC', 'PLN']],
        'renseignement' => ['Renseignement et outils', ['SSE', 'OPR']],
        'unite' => ['Mon unité', ['JNT', 'DOC', 'ORB', 'FRM', 'MES']],
        'gestion' => ['Gestion', ['REC', 'BO']],
        'site' => ['Le site', ['ACC', 'APR', 'NOV', 'CNT']],
        'rejoindre' => ['Nous rejoindre', ['INS', 'INV']],
    ];

    /** Icônes (viewBox 24, trait) par code d'entrée. */
    private const ICONS = [
        'ATK' => '<path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0c0 5.4-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.4"/>',
        'OPS' => '<rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
        'TOC' => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>',
        'PLN' => '<path d="M5 19c0-5 5-4 7-8s-1-6 3-6"/><circle cx="5" cy="19" r="1.6"/><circle cx="16" cy="5" r="1.6"/>',
        'SSE' => '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.3-4.3M11 8.5v5M8.5 11h5"/>',
        'OPR' => '<path d="M14.5 6.5a4 4 0 0 0-5 5L4 17l3 3 5.5-5.5a4 4 0 0 0 5-5l-2.5 2.5-2.5-.5-.5-2.5z"/>',
        'JNT' => '<rect x="3.5" y="5" width="17" height="14" rx="2"/><path d="m4 7 8 6 8-6"/>',
        'DOC' => '<path d="M7 3.5h7l4 4V20a.5.5 0 0 1-.5.5h-10A.5.5 0 0 1 7 20z"/><path d="M14 3.5V8h4M9.5 12.5h5M9.5 16h5"/>',
        'ORB' => '<rect x="9" y="3.5" width="6" height="4.5" rx="1"/><rect x="3" y="16" width="6" height="4.5" rx="1"/><rect x="15" y="16" width="6" height="4.5" rx="1"/><path d="M12 8v4M6 16v-4h12v4"/>',
        'FRM' => '<path d="m3 9 9-4.5L21 9l-9 4.5z"/><path d="M7 11.5V16c0 1.2 2.2 2.5 5 2.5s5-1.3 5-2.5v-4.5"/>',
        'MES' => '<path d="M5 4.5h11a2 2 0 0 1 2 2V20H7a2 2 0 0 1-2-2z"/><path d="m9 11 2 2 3.5-4"/>',
        'REC' => '<circle cx="9" cy="8.5" r="3.2"/><path d="M3.5 19c.5-3.2 2.7-5 5.5-5s5 1.8 5.5 5M18 8v6M15 11h6"/>',
        'BO' => '<path d="M12 3.5 4.5 6.5v5c0 4.4 3 7.6 7.5 9 4.5-1.4 7.5-4.6 7.5-9v-5z"/><path d="m9.2 12 2 2 3.6-4"/>',
        'ACC' => '<path d="m4 11 8-6.5 8 6.5M6 9.5V20h12V9.5"/><path d="M10 20v-5h4v5"/>',
        'APR' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 11v5.5M12 7.8v.4"/>',
        'NOV' => '<path d="M5 4.5h11a2 2 0 0 1 2 2V20H7a2 2 0 0 1-2-2z"/><path d="M9 9h6M9 13h6"/>',
        'CNT' => '<rect x="3.5" y="5" width="17" height="14" rx="2"/><path d="m4 7 8 6 8-6"/>',
        'INS' => '<circle cx="10" cy="8.5" r="3.5"/><path d="M3.5 19.5c.6-3.6 3.1-5.5 6.5-5.5M18 14v6M15 17h6"/>',
        'INV' => '<path d="M10 14a4 4 0 0 1 0-5.7l2.3-2.3a4 4 0 0 1 5.7 5.7L17 12.7"/><path d="M14 10a4 4 0 0 1 0 5.7L11.7 18A4 4 0 0 1 6 12.3L7 11.3"/>',
    ];

    private const FALLBACK_ICON = '<rect x="4" y="4" width="16" height="16" rx="3"/><path d="M9 12h6"/>';

    /**
     * Droit d'entrer au Bureau SSE : la même porte que le portail (SsePortalAccessMiddleware, sas /atak/sse).
     * Jamais pour un invité ni pour un compte sans droit.
     */
    public static function canEnterSse(?SseAccessCodeService $door = null): bool
    {
        if ((int) (\App\Core\Session::get('user_id') ?? 0) < 1) {
            return false;
        }
        try {
            return ($door ?? new SseAccessCodeService())->canEnterWithoutCode();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Entrée « Bureau SSE » à insérer dans la liste des espaces.
     *
     * @return array{abbr: string, label: string, desc: string, href: string, path: string, status: string, match: string}
     */
    public static function sseEntry(): array
    {
        return [
            'abbr' => 'SSE',
            'label' => 'Bureau SSE',
            'desc' => 'Dossiers, personnes et lieux',
            'href' => function_exists('url') ? url(self::SSE_PATH) : '/' . self::SSE_PATH,
            'path' => self::SSE_PATH,
            'status' => 'Habilité',
            'match' => 'atak/sse',
        ];
    }

    /**
     * Rangée les entrées par groupe et marque l'espace courant (une seule entrée : la plus précise).
     *
     * @param list<array<string, mixed>> $links  abbr, label, desc, href, [tone], [status], [match : préfixe de chemin qui désigne l'espace courant]
     * @return list<array{id: string, label: string, items: list<array<string, mixed>>}>
     */
    public static function groups(array $links, string $currentPath): array
    {
        $activeAbbr = self::activeAbbr($links, $currentPath);
        $byGroup = [];
        foreach ($links as $link) {
            $abbr = (string) ($link['abbr'] ?? '');
            $gid = self::groupOf($abbr);
            $link['active'] = $abbr !== '' && $abbr === $activeAbbr;
            $link['icon'] = self::icon($abbr);
            $byGroup[$gid][] = $link;
        }
        $out = [];
        foreach (self::GROUPS as $id => [$label, $order]) {
            if (empty($byGroup[$id])) {
                continue;
            }
            $items = $byGroup[$id];
            usort($items, static fn (array $a, array $b): int => (int) array_search($a['abbr'], $order, true) <=> (int) array_search($b['abbr'], $order, true));
            $out[] = ['id' => $id, 'label' => $label, 'items' => $items];
        }
        if (!empty($byGroup['autres'])) {
            $out[] = ['id' => 'autres', 'label' => 'Autres espaces', 'items' => $byGroup['autres']];
        }

        return $out;
    }

    /** Code de l'entrée dont l'adresse est la plus proche du chemin courant (exact, sinon préfixe le plus long). */
    public static function activeAbbr(array $links, string $currentPath): ?string
    {
        $current = trim($currentPath, '/');
        if ($current === '') {
            return null;
        }
        $best = null;
        $bestScore = 0;
        foreach ($links as $link) {
            $p = isset($link['match']) ? trim((string) $link['match'], '/') : self::pathOf((string) ($link['href'] ?? ''));
            if ($p === '') {
                continue;
            }
            $score = $current === $p ? 1000 + strlen($p) : (str_starts_with($current, $p . '/') ? strlen($p) : 0);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = (string) ($link['abbr'] ?? '');
            }
        }

        return $best;
    }

    public static function groupOf(string $abbr): string
    {
        foreach (self::GROUPS as $id => [, $codes]) {
            if (in_array($abbr, $codes, true)) {
                return $id;
            }
        }

        return 'autres';
    }

    /** Contenu d'un <svg viewBox="0 0 24 24"> (trait). */
    public static function icon(string $abbr): string
    {
        return self::ICONS[$abbr] ?? self::FALLBACK_ICON;
    }

    /** Chemin relatif au site d'une adresse (sans base d'installation, requête ni fragment). */
    private static function pathOf(string $href): string
    {
        $path = (string) (parse_url($href, PHP_URL_PATH) ?? '');
        if (function_exists('url')) {
            $base = rtrim((string) (parse_url(url(''), PHP_URL_PATH) ?? ''), '/');
            if ($base !== '' && str_starts_with($path, $base . '/')) {
                $path = substr($path, strlen($base));
            }
        }

        return trim($path, '/');
    }
}
