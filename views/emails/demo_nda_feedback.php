<?php

declare(strict_types=1);

/** @var string $brand */
/** @var array<string, string> $answers */

$brandSafe = htmlspecialchars($brand, ENT_QUOTES, 'UTF-8');

$rows = '';
$textLines = [];
foreach ($answers as $question => $answer) {
    $q = htmlspecialchars((string) $question, ENT_QUOTES, 'UTF-8');
    $a = nl2br(htmlspecialchars((string) $answer, ENT_QUOTES, 'UTF-8'));
    $rows .= '<tr>'
        . '<td style="padding:10px 12px 10px 0;vertical-align:top;font-weight:600;color:#0f172a;width:42%;">' . $q . '</td>'
        . '<td style="padding:10px 0;vertical-align:top;color:#334155;">' . $a . '</td>'
        . '</tr>';
    $textLines[] = (string) $question . "\n" . (string) $answer . "\n";
}

$body = '<p>Un retour a été envoyé depuis le <strong>questionnaire de démonstration</strong> '
    . '(<strong>' . $brandSafe . '</strong>).</p>'
    . '<table style="width:100%;border-collapse:collapse;font-size:14px;line-height:1.5;">'
    . $rows
    . '</table>';

$html = email_html_layout(
    'Retour démonstration — ' . $brand,
    'Questionnaire démo',
    $body,
    ['accent' => 'emerald']
);

$text = "Retour questionnaire — démonstration ({$brand})\n\n" . implode("\n", $textLines);

return ['html' => $html, 'text' => $text];
