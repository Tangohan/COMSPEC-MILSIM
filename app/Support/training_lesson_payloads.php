<?php

declare(strict_types=1);

/**
 * Schémas JSON pour les leçons « quiz », « modals », « slideshow » + utilitaires vidéo embed.
 */
function training_lesson_default_quiz_json(): string
{
    return json_encode([
        'version' => 1,
        'passingPercent' => 70,
        'shuffle' => false,
        'questions' => [
            [
                'id' => 'q1',
                'prompt' => 'Votre question ?',
                'choices' => [
                    ['id' => 'a', 'label' => 'Réponse A'],
                    ['id' => 'b', 'label' => 'Réponse B'],
                ],
                'correct' => ['a'],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function training_lesson_default_modals_json(): string
{
    return json_encode([
        'version' => 1,
        'modals' => [
            [
                'title' => 'Titre de la fenêtre',
                'body' => 'Texte explicatif (HTML simple autorisé : &lt;p&gt;, &lt;strong&gt;, &lt;ul&gt;…).',
                'imageUrl' => '',
            ],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function training_lesson_default_slideshow_json(): string
{
    return json_encode([
        'version' => 1,
        'slides' => [
            [
                'imageUrl' => 'https://',
                'title' => 'Diapositive 1',
                'caption' => 'Légende',
            ],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/** @return non-empty-string|null JSON normalisé ou null si invalide */
function training_lesson_validate_quiz_content(string $raw): ?string
{
    $t = trim($raw);
    if ($t === '') {
        return training_lesson_default_quiz_json();
    }
    $j = json_decode($t, true);
    if (!is_array($j) || !isset($j['questions']) || !is_array($j['questions']) || $j['questions'] === []) {
        return null;
    }
    foreach ($j['questions'] as $q) {
        if (!is_array($q) || !isset($q['prompt'], $q['choices'], $q['correct']) || !is_array($q['choices']) || !is_array($q['correct'])) {
            return null;
        }
    }
    if (!isset($j['passingPercent'])) {
        $j['passingPercent'] = 70;
    }

    return json_encode($j, JSON_UNESCAPED_UNICODE);
}

/** @return non-empty-string|null */
function training_lesson_validate_modals_content(string $raw): ?string
{
    $t = trim($raw);
    if ($t === '') {
        return training_lesson_default_modals_json();
    }
    $j = json_decode($t, true);
    if (!is_array($j) || !isset($j['modals']) || !is_array($j['modals']) || $j['modals'] === []) {
        return null;
    }
    foreach ($j['modals'] as $m) {
        if (!is_array($m) || !isset($m['title'])) {
            return null;
        }
    }

    return json_encode($j, JSON_UNESCAPED_UNICODE);
}

/** @return non-empty-string|null */
function training_lesson_validate_slideshow_content(string $raw): ?string
{
    $t = trim($raw);
    if ($t === '') {
        return training_lesson_default_slideshow_json();
    }
    $j = json_decode($t, true);
    if (!is_array($j) || !isset($j['slides']) || !is_array($j['slides']) || $j['slides'] === []) {
        return null;
    }
    foreach ($j['slides'] as $s) {
        if (!is_array($s) || empty($s['imageUrl'])) {
            return null;
        }
    }

    return json_encode($j, JSON_UNESCAPED_UNICODE);
}

/**
 * Transforme une URL YouTube / Vimeo / lien embed en src iframe sûre.
 */
function training_video_embed_iframe_src(string $url): ?string
{
    $url = trim($url);
    if ($url === '') {
        return null;
    }
    if (preg_match('#youtube\.com/embed/([a-zA-Z0-9_-]+)#', $url, $m)) {
        return 'https://www.youtube.com/embed/' . $m[1];
    }
    if (preg_match('#[?&]v=([a-zA-Z0-9_-]{6,})#', $url, $m)) {
        return 'https://www.youtube.com/embed/' . $m[1];
    }
    if (preg_match('#youtu\.be/([a-zA-Z0-9_-]+)#', $url, $m)) {
        return 'https://www.youtube.com/embed/' . $m[1];
    }
    if (preg_match('#vimeo\.com/(?:video/)?(\d+)#', $url, $m)) {
        return 'https://player.vimeo.com/video/' . $m[1];
    }
    if (str_starts_with($url, 'http') && str_contains($url, 'player.vimeo.com')) {
        return $url;
    }

    return null;
}

/**
 * Adresse web utilisable dans une visionneuse (iframe) : Google Docs / Sheets / Slides / Drawings / Forms / Drive,
 * draw.io, Figma, Miro, Canva, YouTube, Vimeo, PDF et pages du site. Null si le site ne s’intègre pas
 * de façon connue (la plupart refusent l’affichage dans un cadre) : on propose alors le lien.
 */
function training_url_viewer_embed_src(string $url): ?string
{
    $url = trim(html_entity_decode(strip_tags($url), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($url === '') {
        return null;
    }
    if (str_starts_with($url, '/')) {
        return (str_starts_with($url, '//') || str_starts_with($url, '/\\')) ? null : $url;
    }
    $parts = parse_url($url);
    if ($parts === false || empty($parts['host']) || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)) {
        return null;
    }
    $host = strtolower((string) $parts['host']);
    $path = (string) ($parts['path'] ?? '');
    $query = (string) ($parts['query'] ?? '');
    $fragment = (string) ($parts['fragment'] ?? '');

    if ($host === 'docs.google.com') {
        // Documents publiés sur le web (/d/e/2PACX…/pub) : la variante « embed » ou « embedded=true ».
        if (preg_match('#^/(document|spreadsheets|presentation|drawings|forms)/d/e/([A-Za-z0-9_-]+)#', $path, $m)) {
            if ($m[1] === 'presentation') {
                return 'https://docs.google.com/presentation/d/e/' . $m[2] . '/embed?start=false&loop=false';
            }
            if ($m[1] === 'forms') {
                return 'https://docs.google.com/forms/d/e/' . $m[2] . '/viewform?embedded=true';
            }
            if ($m[1] === 'drawings') {
                return 'https://docs.google.com/drawings/d/e/' . $m[2] . '/pub?w=1440';
            }
            $tail = str_contains($path, '/pubhtml') ? 'pubhtml' : 'pub';

            return 'https://docs.google.com/' . $m[1] . '/d/e/' . $m[2] . '/' . $tail . '?embedded=true';
        }
        if (preg_match('#^/(document|spreadsheets|presentation|drawings|forms)/d/([A-Za-z0-9_-]+)#', $path, $m)) {
            if ($m[1] === 'forms') {
                return 'https://docs.google.com/forms/d/' . $m[2] . '/viewform?embedded=true';
            }
            $src = 'https://docs.google.com/' . $m[1] . '/d/' . $m[2] . '/preview';
            // Onglet ou diapositive visés : on garde la position de départ.
            if ($m[1] === 'spreadsheets' && preg_match('/(?:^|&)gid=(\d+)/', $query . '&' . $fragment, $g)) {
                $src .= '?gid=' . $g[1];
            } elseif ($m[1] === 'presentation' && preg_match('/slide=(id\.[A-Za-z0-9_]+)/', $query . '&' . $fragment, $s)) {
                $src .= '#slide=' . $s[1];
            }

            return $src;
        }

        return null;
    }
    if ($host === 'drive.google.com') {
        if (preg_match('#/file/d/([A-Za-z0-9_-]+)#', $path, $m)) {
            return 'https://drive.google.com/file/d/' . $m[1] . '/preview';
        }
        if (preg_match('/(?:^|&)id=([A-Za-z0-9_-]+)/', $query, $m)) {
            return 'https://drive.google.com/file/d/' . $m[1] . '/preview';
        }
        if (preg_match('#/drive/(?:u/\d+/)?folders/([A-Za-z0-9_-]+)#', $path, $m)) {
            return 'https://drive.google.com/embeddedfolderview?id=' . $m[1] . '#list';
        }

        return null;
    }
    if ($host === 'viewer.diagrams.net' || $host === 'embed.diagrams.net') {
        return $url;
    }
    if (in_array($host, ['app.diagrams.net', 'draw.io', 'www.draw.io', 'diagrams.net', 'www.diagrams.net'], true)) {
        if ($fragment !== '') {
            return 'https://viewer.diagrams.net/?nav=1&lightbox=1#' . $fragment;
        }
        if (preg_match('/(?:^|&)url=([^&]+)/', $query, $m)) {
            return 'https://viewer.diagrams.net/?nav=1&lightbox=1&url=' . $m[1];
        }

        return null;
    }
    if ($host === 'www.figma.com' || $host === 'figma.com') {
        if (preg_match('#^/(file|design|proto|board|slides)/#', $path)) {
            return 'https://www.figma.com/embed?embed_host=athena&url=' . rawurlencode($url);
        }

        return null;
    }
    if ($host === 'miro.com' && preg_match('#^/app/board/([^/]+)#', $path, $m)) {
        return 'https://miro.com/app/live-embed/' . $m[1] . '/';
    }
    if (($host === 'www.canva.com' || $host === 'canva.com') && preg_match('#^/design/[^/]+/[^/]+/view#', $path)) {
        return 'https://www.canva.com' . $path . '?embed';
    }
    $video = training_video_embed_iframe_src($url);
    if ($video !== null) {
        return $video;
    }
    if (preg_match('/\.pdf$/i', $path)) {
        return $url;
    }
    $appHost = strtolower((string) parse_url((string) url(''), PHP_URL_HOST));
    if ($appHost !== '' && $host === $appHost) {
        return $url;
    }

    return null;
}

/** Nom lisible du service derrière un lien (affiché au-dessus de la visionneuse). */
function training_url_viewer_provider_label(string $url): string
{
    $url = trim($url);
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    $path = (string) parse_url($url, PHP_URL_PATH);
    if ($host === 'docs.google.com') {
        return match (true) {
            str_starts_with($path, '/document/') => 'Google Docs',
            str_starts_with($path, '/spreadsheets/') => 'Google Sheets',
            str_starts_with($path, '/presentation/') => 'Google Slides',
            str_starts_with($path, '/drawings/') => 'Google Drawings',
            str_starts_with($path, '/forms/') => 'Google Forms',
            default => 'Google',
        };
    }

    return match (true) {
        $host === 'drive.google.com' => 'Google Drive',
        str_contains($host, 'diagrams.net') || str_contains($host, 'draw.io') => 'draw.io',
        str_contains($host, 'figma.com') => 'Figma',
        $host === 'miro.com' => 'Miro',
        str_contains($host, 'canva.com') => 'Canva',
        str_contains($host, 'youtube.com') || $host === 'youtu.be' => 'YouTube',
        str_contains($host, 'vimeo.com') => 'Vimeo',
        (bool) preg_match('/\.pdf$/i', $path) => 'PDF',
        $host !== '' => preg_replace('/^www\./', '', $host) ?? $host,
        default => 'Lien',
    };
}

/**
 * Le texte d’une leçon se résume-t-il à une seule adresse web (lien collé dans « Contenu ») ?
 * Retourne l’adresse, sinon null.
 */
function training_url_from_bare_content(string $content): ?string
{
    $text = trim(html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($text === '' || preg_match('/\s/u', $text)) {
        return null;
    }
    if (!preg_match('#^https?://[^\s<>"]+$#i', $text)) {
        return null;
    }

    return $text;
}
