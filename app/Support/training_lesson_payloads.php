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
