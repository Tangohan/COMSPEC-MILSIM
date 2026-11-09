<?php

declare(strict_types=1);

namespace App\Services\BackOffice\Home;

use App\Services\Community\PublicPageBuilder;
use App\Support\VideoEmbed;

/**
 * Contenu du « bloc personnalisé » de l'accueil du back-office : une pile de blocs de la même famille que l'éditeur
 * de la vitrine publique (PublicPageBuilder). Les six types de la vitrine (bandeau, cartes, encadré, chiffres, texte et
 * image, appel à l'action) sont normalisés par PublicPageBuilder ; les autres types sont normalisés ici.
 *
 * Tout ce qui arrive du navigateur passe par normalize() : types, clés, longueurs et valeurs connus, liens (http, https,
 * mailto, ancre, chemin du site), images (https ou fichier du site), vidéos (YouTube et Vimeo seulement). Le texte riche
 * reste du texte (balisage léger interprété à l'affichage par App\Support\RichTextLite). Rien n'est rendu tel quel.
 */
final class HomeBlocks
{
    public const VERSION = 1;
    public const MAX_BLOCKS = 30;
    public const MAX_ITEMS = 12;
    public const MAX_TABLE_LINES = 21;
    public const MAX_TABLE_COLS = 6;
    /** Taille maximale du contenu enregistré (JSON), en octets. */
    public const MAX_BYTES = 200_000;

    /** Types repris tels quels de la vitrine. */
    public const SHARED_TYPES = ['hero', 'tiles', 'infobox', 'stats', 'textmedia', 'cta'];

    /** Bibliothèque de l'accueil : type => libellé et description. */
    public const TYPES = [
        'hero' => ['label' => 'Bandeau', 'desc' => 'Grand titre, texte, bouton et image de fond.'],
        'article' => ['label' => 'Article', 'desc' => 'Titre, texte riche et image.'],
        'richtext' => ['label' => 'Texte riche', 'desc' => 'Titres, listes, gras, liens.'],
        'quote' => ['label' => 'Citation', 'desc' => 'Phrase mise en valeur avec son auteur.'],
        'infobox' => ['label' => 'Encadré d’information', 'desc' => 'Message mis en avant : info, alerte, succès.'],
        'links' => ['label' => 'Liste de liens', 'desc' => 'Boutons ou liens vers des pages utiles.'],
        'tiles' => ['label' => 'Cartes', 'desc' => 'Grille de 2 à 4 cartes avec image, titre et texte.'],
        'gallery' => ['label' => 'Galerie d’images', 'desc' => 'Plusieurs images avec légendes.'],
        'video' => ['label' => 'Vidéo', 'desc' => 'Vidéo YouTube ou Vimeo.'],
        'stats' => ['label' => 'Chiffres clés', 'desc' => 'Jusqu’à six compteurs avec libellé.'],
        'table' => ['label' => 'Tableau simple', 'desc' => 'Quelques lignes et colonnes de texte.'],
        'sheet' => ['label' => 'Tableau de données', 'desc' => 'Affiche un de vos tableaux enregistrés.'],
        'textmedia' => ['label' => 'Texte et image', 'desc' => 'Paragraphe à côté d’une image.'],
        'cta' => ['label' => 'Appel à l’action', 'desc' => 'Bandeau avec un ou deux boutons.'],
        'divider' => ['label' => 'Séparateur', 'desc' => 'Ligne de séparation avec un titre facultatif.'],
    ];

    /** Couleurs d'accent : valeur => jeton --ath-* (voir back-office-blocks.css). */
    public const ACCENTS = ['default', 'emerald', 'info', 'warn', 'danger', 'neutral'];

    private const OWN_STYLE_OPTIONS = [
        'accent' => self::ACCENTS,
        'layout' => ['top', 'left', 'right'],
        'display' => ['buttons', 'list'],
        'line' => ['solid', 'dashed', 'dotted'],
        'striped' => ['on', 'off'],
        'vratio' => ['16x9', '4x3', '21x9'],
    ];

    /** Réglages permis par type. */
    private const STYLE_KEYS = [
        'hero' => ['height', 'align', 'overlay', 'accent'],
        'tiles' => ['theme', 'spacing', 'align', 'columns', 'ratio', 'variant', 'accent'],
        'infobox' => ['spacing', 'tone', 'icon', 'size'],
        'stats' => ['theme', 'spacing', 'align', 'accent'],
        'textmedia' => ['theme', 'spacing', 'image_side', 'accent'],
        'cta' => ['theme', 'spacing', 'align', 'accent'],
        'article' => ['theme', 'spacing', 'align', 'layout', 'accent'],
        'richtext' => ['theme', 'spacing', 'align', 'accent'],
        'quote' => ['spacing', 'align', 'accent'],
        'links' => ['theme', 'spacing', 'align', 'display', 'accent'],
        'gallery' => ['spacing', 'columns', 'ratio'],
        'video' => ['spacing', 'align', 'vratio'],
        'divider' => ['spacing', 'line', 'accent'],
        'table' => ['theme', 'spacing', 'striped', 'accent'],
        'sheet' => ['spacing', 'accent'],
    ];

    /** @return array<string, list<string>> */
    public static function styleOptions(): array
    {
        return array_merge(PublicPageBuilder::styleOptions(), self::OWN_STYLE_OPTIONS);
    }

    /** @return list<string> */
    public static function styleKeysFor(string $type): array
    {
        return self::STYLE_KEYS[$type] ?? [];
    }

    /** @return array<string, list<string>> */
    public static function allStyleKeys(): array
    {
        return self::STYLE_KEYS;
    }

    /**
     * @param array<string, mixed> $raw
     * @return array{version: int, blocks: list<array<string, mixed>>}
     */
    public static function normalize(array $raw): array
    {
        $blocks = [];
        $seen = [];
        foreach (is_array($raw['blocks'] ?? null) ? $raw['blocks'] : [] as $b) {
            if (!is_array($b) || count($blocks) >= self::MAX_BLOCKS) {
                continue;
            }
            $block = self::normalizeBlock($b);
            if ($block === null) {
                continue;
            }
            while (isset($seen[$block['id']])) {
                $block['id'] = PublicPageBuilder::newId();
            }
            $seen[$block['id']] = true;
            $blocks[] = $block;
        }

        return ['version' => self::VERSION, 'blocks' => $blocks];
    }

    /** @param array<string, mixed> $b */
    public static function normalizeBlock(array $b): ?array
    {
        $type = (string) ($b['type'] ?? '');
        if (!isset(self::TYPES[$type])) {
            return null;
        }
        if (in_array($type, self::SHARED_TYPES, true)) {
            $out = PublicPageBuilder::normalizeBlock($b);
            if ($out === null || $out['type'] !== $type) {
                return null;
            }
            $props = is_array($b['props'] ?? null) ? $b['props'] : [];
        } else {
            $props = is_array($b['props'] ?? null) ? $b['props'] : [];
            $id = preg_match('/^[a-z]_[A-Za-z0-9_]{1,32}$/', (string) ($b['id'] ?? '')) === 1 && !str_starts_with((string) $b['id'], 's_')
                ? (string) $b['id'] : PublicPageBuilder::newId();
            $out = [
                'id' => $id,
                'type' => $type,
                'hidden' => !empty($b['hidden']),
                'nav_label' => '',
                'style' => [],
                'props' => self::ownProps($type, $props),
            ];
        }
        $out['nav_label'] = '';
        $out['style'] = self::style(is_array($b['style'] ?? null) ? $b['style'] : [], $type);

        return $out;
    }

    /**
     * Bloc neuf, prérempli pour que l'accueil ne montre jamais un cadre vide.
     */
    public static function starterBlock(string $type): ?array
    {
        if (!isset(self::TYPES[$type])) {
            return null;
        }
        $props = match ($type) {
            'hero' => ['kicker' => 'Communauté', 'title' => 'Bienvenue à bord', 'text' => 'Une phrase courte pour situer la période ou la mission.', 'button_label' => 'Voir l’agenda', 'button_url' => '/agenda'],
            'tiles' => ['kicker' => 'À la une', 'title' => 'Nos rendez-vous', 'items' => [
                ['title' => 'Briefing', 'text' => 'Le point avant chaque opération.'],
                ['title' => 'Formation', 'text' => 'Parcours encadré pour les recrues.'],
                ['title' => 'Retour d’expérience', 'text' => 'Ce qui a marché, ce qu’on corrige.'],
            ]],
            'infobox' => ['title' => 'À savoir', 'text' => 'Un message important pour toute l’équipe.'],
            'stats' => ['kicker' => 'En chiffres', 'title' => 'La communauté', 'items' => [
                ['value' => '40+', 'label' => 'Membres'],
                ['value' => '2', 'label' => 'Soirées par semaine'],
                ['value' => '12', 'label' => 'Opérations cette saison'],
            ]],
            'textmedia' => ['kicker' => 'Notre histoire', 'title' => 'D’où nous venons', 'text' => 'Racontez en quelques lignes ce qui fait votre identité.'],
            'cta' => ['kicker' => 'Équipe', 'title' => 'Une action attend', 'text' => 'Dites ce qu’il y a à faire et où.', 'button_label' => 'Ouvrir', 'button_url' => '/back-office'],
            'article' => ['kicker' => 'Actualité', 'title' => 'Titre de l’article', 'text' => "Introduction en une phrase.\n\n## Un sous-titre\nUn paragraphe avec du **gras**, de l’*italique* et un [lien](https://example.org).\n\n- premier point\n- second point"],
            'richtext' => ['title' => 'Consignes du mois', 'text' => "## Priorités\n- Confirmer sa présence avant jeudi\n- Relire le briefing\n\nPour toute question, écrivez à l’état-major."],
            'quote' => ['text' => 'Un bon plan simple vaut mieux qu’un plan parfait.', 'name' => 'Le commandant', 'meta' => 'Briefing du lundi'],
            'links' => ['title' => 'Liens utiles', 'items' => [
                ['label' => 'Agenda', 'url' => '/agenda', 'hint' => 'Les prochaines opérations'],
                ['label' => 'Règlement', 'url' => '/reglement', 'hint' => ''],
            ]],
            'gallery' => ['title' => 'En images', 'items' => []],
            'video' => ['title' => 'Vidéo', 'text' => ''],
            'table' => ['title' => 'Planning', 'text' => "Créneau | Lieu | Responsable\nSamedi 20 h | Terrain nord | Alpha\nDimanche 14 h | Salle de briefing | Bravo"],
            'sheet' => ['title' => 'Tableau'],
            'divider' => ['title' => ''],
            default => [],
        };

        return self::normalizeBlock(['id' => PublicPageBuilder::newId(), 'type' => $type, 'props' => $props, 'style' => match ($type) {
            'stats', 'cta' => ['theme' => 'dark'],
            'tiles' => ['variant' => 'card', 'columns' => '3'],
            default => [],
        }]);
    }

    /**
     * Données d'un bloc « tableau simple » : première ligne = en-têtes, cellules séparées par « | ».
     *
     * @return list<list<string>>
     */
    public static function tableRows(string $text): array
    {
        $rows = [];
        foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $text)) as $line) {
            if (trim($line) === '' || count($rows) >= self::MAX_TABLE_LINES) {
                continue;
            }
            $cells = array_map('trim', explode('|', trim($line, " \t|")));
            $rows[] = array_slice($cells, 0, self::MAX_TABLE_COLS);
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $p
     * @return array<string, mixed>
     */
    private static function ownProps(string $type, array $p): array
    {
        $t = static fn (string $k, int $max, bool $multi = false): string => PublicPageBuilder::text($p[$k] ?? '', $max, $multi);
        switch ($type) {
            case 'article':
                return [
                    'kicker' => $t('kicker', 80, false), 'title' => $t('title', 200, false), 'text' => $t('text', 8000, true),
                    'image' => PublicPageBuilder::image($p['image'] ?? ''), 'image_alt' => $t('image_alt', 160, false),
                    'button_label' => $t('button_label', 60, false), 'button_url' => PublicPageBuilder::link($p['button_url'] ?? ''),
                ];
            case 'richtext':
                return ['title' => $t('title', 200, false), 'text' => $t('text', 8000, true)];
            case 'quote':
                return ['text' => $t('text', 600, true), 'name' => $t('name', 120, false), 'meta' => $t('meta', 120, false)];
            case 'links':
                $items = [];
                foreach (is_array($p['items'] ?? null) ? $p['items'] : [] as $it) {
                    if (!is_array($it) || count($items) >= self::MAX_ITEMS) {
                        continue;
                    }
                    $label = PublicPageBuilder::text($it['label'] ?? '', 60, false);
                    $url = PublicPageBuilder::link($it['url'] ?? '');
                    if ($label === '' && $url === '') {
                        continue;
                    }
                    $items[] = ['label' => $label, 'url' => $url, 'hint' => PublicPageBuilder::text($it['hint'] ?? '', 120, false)];
                }

                return ['title' => $t('title', 200, false), 'items' => $items];
            case 'gallery':
                $items = [];
                foreach (is_array($p['items'] ?? null) ? $p['items'] : [] as $it) {
                    if (!is_array($it) || count($items) >= self::MAX_ITEMS) {
                        continue;
                    }
                    $img = PublicPageBuilder::image($it['image'] ?? '');
                    $caption = PublicPageBuilder::text($it['caption'] ?? '', 160, false);
                    if ($img === '' && $caption === '') {
                        continue;
                    }
                    $items[] = ['image' => $img, 'alt' => PublicPageBuilder::text($it['alt'] ?? '', 160, false), 'caption' => $caption];
                }

                return ['title' => $t('title', 200, false), 'items' => $items];
            case 'video':
                return ['title' => $t('title', 200, false), 'text' => $t('text', 400, true), 'video_url' => VideoEmbed::canonical($p['video_url'] ?? '')];
            case 'table':
                $lines = array_map(static fn (array $r): string => implode(' | ', array_map(static fn (string $c): string => mb_substr(str_replace('|', '/', $c), 0, 120), $r)), self::tableRows(PublicPageBuilder::text($p['text'] ?? '', 16000, true)));

                return ['title' => $t('title', 200, false), 'text' => implode("\n", $lines)];
            case 'sheet':
                $rawId = $p['sheet_id'] ?? 0;
                $sid = is_int($rawId) ? $rawId : (is_string($rawId) && ctype_digit($rawId) && strlen($rawId) <= 10 ? (int) $rawId : 0);

                return ['title' => $t('title', 200, false), 'sheet_id' => $sid > 0 && $sid <= 2147483647 ? $sid : 0];
            case 'divider':
                return ['title' => $t('title', 80, false)];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $style
     * @return array<string, string>
     */
    private static function style(array $style, string $type): array
    {
        $options = self::styleOptions();
        $out = [];
        foreach (self::STYLE_KEYS[$type] ?? [] as $k) {
            $opts = $options[$k];
            $v = isset($style[$k]) && is_scalar($style[$k]) ? (string) $style[$k] : $opts[0];
            $out[$k] = in_array($v, $opts, true) ? $v : $opts[0];
        }
        // Les blocs de l'accueil restent dans la colonne du tableau de bord : pas de pleine largeur.
        $out['width'] = 'standard';

        return $out;
    }
}
