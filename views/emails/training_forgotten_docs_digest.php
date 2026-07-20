<?php

declare(strict_types=1);

/** @var string $staffDisplayName */
/** @var string $tenantName */
/** @var list<array{title:string,updated_at:?string}> $forgottenDrafts */
/** @var list<array{title:string,updated_at:?string}> $neverViewed */
/** @var string $docsUrl */

$staff = htmlspecialchars((string) $staffDisplayName, ENT_QUOTES, 'UTF-8');
$tn = htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8');

$renderList = static function (array $rows): string {
    $html = '<ul>';
    foreach ($rows as $r) {
        $title = htmlspecialchars((string) ($r['title'] ?? ''), ENT_QUOTES, 'UTF-8');
        $updatedAt = (string) ($r['updated_at'] ?? '');
        $dateLabel = $updatedAt !== '' ? date('d/m/Y', strtotime($updatedAt)) : '';
        $html .= '<li>' . $title . ($dateLabel !== '' ? ' <em>(' . $dateLabel . ')</em>' : '') . '</li>';
    }
    $html .= '</ul>';

    return $html;
};

$sections = '';
if ($forgottenDrafts !== []) {
    $sections .= '<p><strong>Brouillons sans modification depuis plus de 30 jours (' . count($forgottenDrafts) . ')</strong></p>'
        . $renderList($forgottenDrafts);
}
if ($neverViewed !== []) {
    $sections .= '<p><strong>Documents publiés jamais consultés (' . count($neverViewed) . ')</strong></p>'
        . $renderList($neverViewed);
}

$body = '<p>Bonjour ' . $staff . ',</p>'
    . '<p>Voici les Documentations HTML à relancer pour <strong>' . $tn . '</strong> :</p>'
    . $sections
    . email_html_button($docsUrl, 'Ouvrir les Documentations', 'emerald')
    . email_html_url_fallback($docsUrl);

$html = email_html_layout(
    'Documentations LMS à relancer — ' . $tenantName,
    'Studio LMS',
    $body,
    ['accent' => 'blue']
);

$textLines = [];
if ($forgottenDrafts !== []) {
    $textLines[] = 'Brouillons oubliés (' . count($forgottenDrafts) . ') :';
    foreach ($forgottenDrafts as $r) {
        $textLines[] = '- ' . ($r['title'] ?? '');
    }
}
if ($neverViewed !== []) {
    $textLines[] = 'Jamais consultés (' . count($neverViewed) . ') :';
    foreach ($neverViewed as $r) {
        $textLines[] = '- ' . ($r['title'] ?? '');
    }
}

$text = "Bonjour {$staffDisplayName},\n\n"
    . "Documentations LMS à relancer pour « {$tenantName} » :\n\n"
    . implode("\n", $textLines) . "\n\n"
    . "Documentations : {$docsUrl}\n";

return ['html' => $html, 'text' => $text];
