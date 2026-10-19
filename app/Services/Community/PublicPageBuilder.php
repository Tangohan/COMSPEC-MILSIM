<?php

declare(strict_types=1);

namespace App\Services\Community;

/**
 * Mise en page de la vitrine publique /c/{slug} : en-tête, ordre des sections et blocs personnalisés.
 *
 * Stockage dans settings.community :
 *  - public_page_builder        : version publiée (en-tête, thème, blocs) ;
 *  - public_page_builder_draft  : brouillon en cours (même forme + `fields`, les textes modifiés en place,
 *                                 recopiés dans settings.community à la publication).
 *
 * Tout ce qui arrive du navigateur passe par normalize() : types, clés et longueurs connus, liens et images
 * filtrés. Rien n’est rendu tel quel.
 */
final class PublicPageBuilder
{
    public const VERSION = 1;
    public const MAX_BLOCKS = 40;
    public const MAX_ITEMS = 12;

    /** Sections natives de la vitrine, dans l’ordre d’affichage par défaut. */
    public const SECTIONS = [
        'hero' => 'Bandeau d’accueil',
        'signals' => 'Annonce, créneaux et réseaux',
        'video' => 'Vidéo de présentation',
        'stats' => 'Chiffres clés',
        'about' => 'Présentation',
        'mission' => 'Mission et doctrine',
        'modules' => 'Fonctionnalités et accès',
        'command' => 'Chaîne de commandement',
        'organisation' => 'Organisation des unités',
        'agenda' => 'Agenda public',
        'recruitment' => 'Prérequis et étapes',
        'openings' => 'Offres de recrutement',
        'media' => 'Médias',
        'roster' => 'Effectifs publics',
        'faq' => 'Questions fréquentes',
        'testimonials' => 'Témoignages',
        'partners' => 'Partenaires',
        'contact' => 'Contact et appel à rejoindre',
    ];

    /** Blocs ajoutables depuis la bibliothèque. */
    public const BLOCK_TYPES = [
        'hero' => ['label' => 'Grand bandeau', 'desc' => 'Image plein cadre, titre et boutons.'],
        'tiles' => ['label' => 'Grandes miniatures', 'desc' => 'Grille d’images cliquables avec titre et texte.'],
        'infobox' => ['label' => 'Encadré d’information', 'desc' => 'Message mis en avant : info, alerte, succès.'],
        'stats' => ['label' => 'Chiffres', 'desc' => 'Jusqu’à six chiffres avec libellé.'],
        'textmedia' => ['label' => 'Texte et image', 'desc' => 'Paragraphe à côté d’une image.'],
        'cta' => ['label' => 'Appel à l’action', 'desc' => 'Bandeau avec un ou deux boutons.'],
    ];

    /** Réglages de style communs : clé => valeurs permises (la première est la valeur par défaut). */
    private const STYLE_OPTIONS = [
        'theme' => ['auto', 'light', 'tinted', 'dark', 'accent'],
        'spacing' => ['standard', 'compact', 'spacious'],
        'width' => ['standard', 'narrow', 'full'],
        'align' => ['left', 'center'],
        // Bandeaux
        'height' => ['standard', 'compact', 'tall'],
        'overlay' => ['medium', 'light', 'strong'],
        // Miniatures
        'columns' => ['3', '2', '4'],
        'ratio' => ['landscape', 'square', 'portrait', 'wide'],
        'variant' => ['overlay', 'card'],
        // Encadré
        'tone' => ['info', 'success', 'warning', 'danger', 'neutral', 'accent'],
        'icon' => ['info', 'alert', 'check', 'star', 'calendar', 'radio', 'shield', 'none'],
        'size' => ['standard', 'large'],
        // Texte et image
        'image_side' => ['right', 'left'],
    ];

    /** Réglages permis par type (« section » = sections natives). */
    private const STYLE_KEYS = [
        'section' => ['spacing'],
        'section:hero' => ['height', 'align', 'overlay'],
        'hero' => ['height', 'align', 'overlay', 'width'],
        'tiles' => ['theme', 'spacing', 'width', 'align', 'columns', 'ratio', 'variant'],
        'infobox' => ['spacing', 'width', 'tone', 'icon', 'size'],
        'stats' => ['theme', 'spacing', 'width', 'align'],
        'textmedia' => ['theme', 'spacing', 'width', 'image_side'],
        'cta' => ['theme', 'spacing', 'width', 'align'],
    ];

    /** Textes de bloc : clé => longueur maximale. */
    private const PROP_TEXT = [
        'kicker' => 80,
        'title' => 200,
        'text' => 4000,
        'button_label' => 60,
        'button2_label' => 60,
    ];

    private const PROP_LINK = ['button_url', 'button2_url'];

    /** Éléments répétés (miniatures, chiffres) : sous-clé => longueur maximale. */
    private const ITEM_TEXT = [
        'tiles' => ['title' => 120, 'text' => 600, 'badge' => 40],
        'stats' => ['value' => 24, 'label' => 80, 'note' => 120],
    ];

    /** Titres des sections natives modifiables en place. */
    private const SECTION_PROP_TEXT = ['kicker' => 80, 'title' => 200, 'lead' => 400, 'kicker2' => 80, 'title2' => 200];

    /**
     * Textes de settings.community modifiables directement sur la page : clé => longueur maximale.
     */
    public const FIELDS = [
        'public_hero_headline' => 200,
        'public_hero_subtitle' => 600,
        'public_recruitment_session_label' => 80,
        'public_access_label' => 160,
        'public_highlight_title' => 160,
        'public_highlight_body' => 1200,
        'public_schedule_label' => 80,
        'public_schedule_body' => 400,
        'public_video_title' => 200,
        'public_video_body' => 2000,
        'public_about_title' => 200,
        'public_about_body' => 8000,
        'public_about_body_secondary' => 8000,
        'public_mission' => 4000,
        'public_doctrine' => 400,
        'public_sections_title' => 200,
        'public_sections_lead' => 400,
        'public_cta_kicker' => 80,
        'public_cta_title' => 200,
        'public_cta_body' => 1200,
        'contact_intro' => 500,
    ];

    /** Listes de settings.community modifiables en place : clé => [sous-clé => longueur]. */
    public const LIST_FIELDS = [
        'public_pitch' => ['title' => 160, 'body' => 1200],
        'public_process_steps' => ['title' => 160, 'delay' => 60, 'body' => 600],
        'public_faq' => ['q' => 240, 'a' => 2000],
        'public_testimonials' => ['text' => 1200, 'name' => 120, 'meta' => 120],
    ];

    private const HEADER_STYLES = ['solid', 'glass', 'light'];
    private const HEADER_LAYOUTS = ['split', 'centered'];
    private const PAGE_THEMES = ['light', 'dark', 'auto'];

    /** Mise en page par défaut : toutes les sections natives, rien de masqué. */
    public static function defaults(): array
    {
        $blocks = [];
        foreach (array_keys(self::SECTIONS) as $key) {
            $blocks[] = ['id' => 's_' . $key, 'type' => 'section', 'key' => $key, 'hidden' => false, 'style' => [], 'props' => []];
        }

        return [
            'version' => self::VERSION,
            'theme' => 'light',
            'header' => self::normalizeHeader([]),
            'blocks' => $blocks,
        ];
    }

    /**
     * Mise en page effective (publiée, ou brouillon pour un gestionnaire en aperçu).
     *
     * @param array<string, mixed> $community settings.community
     */
    public static function resolve(array $community, bool $useDraft = false): array
    {
        $raw = $useDraft && is_array($community['public_page_builder_draft'] ?? null)
            ? $community['public_page_builder_draft']
            : ($community['public_page_builder'] ?? null);

        return self::normalize(is_array($raw) ? $raw : []);
    }

    /** @param array<string, mixed> $community */
    public static function hasDraft(array $community): bool
    {
        return is_array($community['public_page_builder_draft'] ?? null);
    }

    /**
     * Textes du brouillon appliqués à la configuration, pour l’aperçu.
     *
     * @param array<string, mixed> $community
     * @return array<string, mixed>
     */
    public static function applyDraftFields(array $community): array
    {
        $draft = $community['public_page_builder_draft'] ?? null;
        if (!is_array($draft) || !is_array($draft['fields'] ?? null)) {
            return $community;
        }

        return array_merge($community, self::normalizeFields($draft['fields']));
    }

    /**
     * Publie le brouillon : textes recopiés dans la configuration, mise en page publiée, brouillon effacé.
     *
     * @param array<string, mixed> $community
     * @return array<string, mixed>
     */
    public static function publish(array $community): array
    {
        $draft = $community['public_page_builder_draft'] ?? null;
        if (!is_array($draft)) {
            return $community;
        }
        $community = self::applyDraftFields($community);
        $layout = self::normalize($draft);
        $layout['published_at'] = gmdate('c');
        $community['public_page_builder'] = $layout;
        unset($community['public_page_builder_draft']);

        return $community;
    }

    /**
     * Brouillon reçu du navigateur.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function normalizeDraft(array $payload, int $userId): array
    {
        $draft = self::normalize($payload);
        $draft['fields'] = self::normalizeFields(is_array($payload['fields'] ?? null) ? $payload['fields'] : []);
        $draft['updated_at'] = gmdate('c');
        $draft['updated_by'] = $userId;

        return $draft;
    }

    /** @param array<string, mixed> $raw */
    public static function normalize(array $raw): array
    {
        $theme = in_array($raw['theme'] ?? null, self::PAGE_THEMES, true) ? (string) $raw['theme'] : 'light';
        $out = [
            'version' => self::VERSION,
            'theme' => $theme,
            'header' => self::normalizeHeader(is_array($raw['header'] ?? null) ? $raw['header'] : []),
            'blocks' => [],
        ];

        $seenIds = [];
        $seenSections = [];
        foreach (is_array($raw['blocks'] ?? null) ? $raw['blocks'] : [] as $b) {
            if (!is_array($b) || count($out['blocks']) >= self::MAX_BLOCKS) {
                continue;
            }
            $block = self::normalizeBlock($b);
            if ($block === null) {
                continue;
            }
            if ($block['type'] === 'section') {
                if (isset($seenSections[$block['key']])) {
                    continue;
                }
                $seenSections[$block['key']] = true;
            }
            while (isset($seenIds[$block['id']])) {
                $block['id'] = self::newId();
            }
            $seenIds[$block['id']] = true;
            $out['blocks'][] = $block;
        }

        // Une section native absente de la liste (nouvelle section, liste vide) reprend sa place par défaut.
        $keys = array_keys(self::SECTIONS);
        foreach ($keys as $i => $key) {
            if (isset($seenSections[$key])) {
                continue;
            }
            $insertAt = count($out['blocks']);
            for ($j = $i + 1, $n = count($keys); $j < $n; $j++) {
                $pos = self::sectionIndex($out['blocks'], $keys[$j]);
                if ($pos !== null) {
                    $insertAt = $pos;
                    break;
                }
            }
            array_splice($out['blocks'], $insertAt, 0, [[
                'id' => 's_' . $key, 'type' => 'section', 'key' => $key, 'hidden' => false, 'style' => [], 'props' => [],
            ]]);
        }

        return $out;
    }

    /** @param array<string, mixed> $h */
    public static function normalizeHeader(array $h): array
    {
        return [
            'style' => in_array($h['style'] ?? null, self::HEADER_STYLES, true) ? (string) $h['style'] : 'solid',
            'layout' => in_array($h['layout'] ?? null, self::HEADER_LAYOUTS, true) ? (string) $h['layout'] : 'split',
            'show_back' => !array_key_exists('show_back', $h) || !empty($h['show_back']),
            'show_nav' => !array_key_exists('show_nav', $h) || !empty($h['show_nav']),
            'show_badges' => !array_key_exists('show_badges', $h) || !empty($h['show_badges']),
            'tagline' => self::text($h['tagline'] ?? '', 120),
            'cta_label' => self::text($h['cta_label'] ?? '', 40),
            'cta_url' => self::link($h['cta_url'] ?? ''),
        ];
    }

    /** @param array<string, mixed> $b */
    private static function normalizeBlock(array $b): ?array
    {
        $type = (string) ($b['type'] ?? '');
        $id = preg_match('/^[a-z]_[A-Za-z0-9_]{1,32}$/', (string) ($b['id'] ?? '')) ? (string) $b['id'] : self::newId();
        if ($type === 'section') {
            $key = (string) ($b['key'] ?? '');
            if (!isset(self::SECTIONS[$key])) {
                return null;
            }
            $props = [];
            foreach (self::SECTION_PROP_TEXT as $pk => $max) {
                $v = self::text(is_array($b['props'] ?? null) ? ($b['props'][$pk] ?? '') : '', $max);
                if ($v !== '') {
                    $props[$pk] = $v;
                }
            }
            $styleKeys = $key === 'hero' ? self::STYLE_KEYS['section:hero'] : self::STYLE_KEYS['section'];

            return [
                'id' => 's_' . $key,
                'type' => 'section',
                'key' => $key,
                'hidden' => !empty($b['hidden']),
                'nav_label' => self::text($b['nav_label'] ?? '', 30),
                'style' => self::normalizeStyle(is_array($b['style'] ?? null) ? $b['style'] : [], $styleKeys),
                'props' => $props,
            ];
        }
        if (!isset(self::BLOCK_TYPES[$type])) {
            return null;
        }
        if (str_starts_with($id, 's_')) {
            $id = self::newId();
        }
        $p = is_array($b['props'] ?? null) ? $b['props'] : [];
        $props = [];
        foreach (self::PROP_TEXT as $pk => $max) {
            $props[$pk] = self::text($p[$pk] ?? '', $max);
        }
        foreach (self::PROP_LINK as $pk) {
            $props[$pk] = self::link($p[$pk] ?? '');
        }
        $props['image'] = self::image($p['image'] ?? '');
        $props['image_alt'] = self::text($p['image_alt'] ?? '', 160);
        if (isset(self::ITEM_TEXT[$type])) {
            $items = [];
            foreach (is_array($p['items'] ?? null) ? $p['items'] : [] as $it) {
                if (!is_array($it) || count($items) >= ($type === 'stats' ? 6 : self::MAX_ITEMS)) {
                    continue;
                }
                $row = [];
                foreach (self::ITEM_TEXT[$type] as $ik => $max) {
                    $row[$ik] = self::text($it[$ik] ?? '', $max);
                }
                if ($type === 'tiles') {
                    $row['image'] = self::image($it['image'] ?? '');
                    $row['url'] = self::link($it['url'] ?? '');
                }
                $items[] = $row;
            }
            $props['items'] = $items;
        }

        return [
            'id' => $id,
            'type' => $type,
            'hidden' => !empty($b['hidden']),
            'nav_label' => self::text($b['nav_label'] ?? '', 30),
            'style' => self::normalizeStyle(is_array($b['style'] ?? null) ? $b['style'] : [], self::STYLE_KEYS[$type]),
            'props' => $props,
        ];
    }

    /**
     * Bloc neuf, prérempli pour que la page ne montre jamais un cadre vide.
     */
    public static function starterBlock(string $type): ?array
    {
        $props = match ($type) {
            'hero' => ['kicker' => 'Opérations', 'title' => 'Un titre qui donne envie', 'text' => 'Une phrase courte pour situer l’action.', 'button_label' => 'Nous rejoindre', 'button_url' => '#contact'],
            'tiles' => ['kicker' => 'En images', 'title' => 'Nos spécialités', 'items' => [
                ['title' => 'Infanterie', 'text' => 'Manœuvre et combat débarqué.'],
                ['title' => 'Appui', 'text' => 'Feux, logistique et transmissions.'],
                ['title' => 'Formation', 'text' => 'Parcours encadré dès l’arrivée.'],
            ]],
            'infobox' => ['title' => 'À savoir', 'text' => 'Un message important pour les visiteurs.'],
            'stats' => ['kicker' => 'En chiffres', 'title' => 'La communauté', 'items' => [
                ['value' => '40+', 'label' => 'Opérateurs'],
                ['value' => '2', 'label' => 'Soirées par semaine'],
                ['value' => '2026', 'label' => 'Création'],
            ]],
            'textmedia' => ['kicker' => 'Notre histoire', 'title' => 'D’où nous venons', 'text' => 'Racontez en quelques lignes ce qui fait votre identité.'],
            'cta' => ['kicker' => 'Recrutement', 'title' => 'Prêt à passer le cap ?', 'text' => 'Les candidatures sont ouvertes.', 'button_label' => 'Candidater', 'button_url' => '#contact'],
            default => null,
        };
        if ($props === null) {
            return null;
        }

        return self::normalizeBlock(['id' => self::newId(), 'type' => $type, 'props' => $props, 'style' => match ($type) {
            'stats', 'cta' => ['theme' => 'dark'],
            'hero' => ['width' => 'full'],
            'tiles' => ['variant' => 'overlay'],
            default => [],
        }]);
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    public static function normalizeFields(array $fields): array
    {
        $out = [];
        foreach (self::FIELDS as $key => $max) {
            if (array_key_exists($key, $fields) && is_scalar($fields[$key])) {
                $out[$key] = self::text($fields[$key], $max, true);
            }
        }
        foreach (self::LIST_FIELDS as $key => $schema) {
            if (!array_key_exists($key, $fields) || !is_array($fields[$key])) {
                continue;
            }
            $rows = [];
            foreach ($fields[$key] as $row) {
                if (!is_array($row) || count($rows) >= 20) {
                    continue;
                }
                $clean = [];
                $empty = true;
                foreach ($schema as $sk => $max) {
                    $clean[$sk] = self::text($row[$sk] ?? '', $max, true);
                    if ($clean[$sk] !== '') {
                        $empty = false;
                    }
                }
                if ($key === 'public_process_steps') {
                    $clean['highlight'] = !empty($row['highlight']);
                }
                if (!$empty) {
                    $rows[] = $clean;
                }
            }
            $out[$key] = $rows;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $style
     * @param list<string> $keys
     * @return array<string, string>
     */
    private static function normalizeStyle(array $style, array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            $opts = self::STYLE_OPTIONS[$k];
            $v = isset($style[$k]) && is_scalar($style[$k]) ? (string) $style[$k] : $opts[0];
            $out[$k] = in_array($v, $opts, true) ? $v : $opts[0];
        }

        return $out;
    }

    /** Options de style permises (pour le panneau de réglages). */
    public static function styleOptions(): array
    {
        return self::STYLE_OPTIONS;
    }

    /** @return list<string> */
    public static function styleKeysFor(string $type, ?string $sectionKey = null): array
    {
        if ($type === 'section') {
            return $sectionKey === 'hero' ? self::STYLE_KEYS['section:hero'] : self::STYLE_KEYS['section'];
        }

        return self::STYLE_KEYS[$type] ?? [];
    }

    /** @param list<array<string, mixed>> $blocks */
    private static function sectionIndex(array $blocks, string $key): ?int
    {
        foreach ($blocks as $i => $b) {
            if (($b['type'] ?? '') === 'section' && ($b['key'] ?? '') === $key) {
                return $i;
            }
        }

        return null;
    }

    public static function newId(): string
    {
        return 'b_' . bin2hex(random_bytes(5));
    }

    private static function text(mixed $v, int $max, bool $multiline = true): string
    {
        if (!is_scalar($v)) {
            return '';
        }
        $s = str_replace(["\r\n", "\r"], "\n", (string) $v);
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';
        if (!$multiline) {
            $s = str_replace("\n", ' ', $s);
        }
        $s = preg_replace("/\n{3,}/", "\n\n", $s) ?? $s;
        $s = trim($s);

        return mb_substr($s, 0, $max);
    }

    /** Lien de bouton : https, mailto, ancre ou chemin du site. */
    public static function link(mixed $v): string
    {
        $s = is_scalar($v) ? trim((string) $v) : '';
        if ($s === '' || strlen($s) > 500 || preg_match('/[\s<>"\'`]/', $s)) {
            return '';
        }
        if (preg_match('#^https?://[^/]#i', $s) || preg_match('/^mailto:[^@]+@[^@]+$/i', $s)
            || preg_match('/^#[A-Za-z][\w-]{0,60}$/', $s) || preg_match('#^/(?!/)#', $s)) {
            return $s;
        }

        return '';
    }

    /** Image : adresse web, ou fichier du site (« assets/… », servi par url()). */
    public static function image(mixed $v): string
    {
        $s = is_scalar($v) ? trim((string) $v) : '';
        if ($s === '' || strlen($s) > 500 || preg_match('/[\s<>"\'`\\\\]/', $s)) {
            return '';
        }
        if (preg_match('#^https?://[^/]#i', $s) || preg_match('#^/?assets/[\w./%-]+$#', $s)) {
            return str_contains($s, '..') ? '' : $s;
        }

        return '';
    }

    /** Adresse affichable d’une image validée par image(). */
    public static function imageUrl(string $s): string
    {
        if ($s === '' || preg_match('#^https?://#i', $s)) {
            return $s;
        }

        return function_exists('url') ? url(ltrim($s, '/')) : '/' . ltrim($s, '/');
    }
}
