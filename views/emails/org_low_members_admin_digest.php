<?php

declare(strict_types=1);

/** @var list<array<string, mixed>> $orgs */
/** @var string $listUrl */

$orgs = is_array($orgs ?? null) ? $orgs : [];
$e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$day = static function (mixed $raw): string {
    $ts = \App\Support\UtcDateTime::timestamp($raw);

    return $ts !== null ? gmdate('d/m/Y', $ts) : '—';
};
$n = count($orgs);

$rowsHtml = '';
$rowsText = '';
foreach (array_slice($orgs, 0, 30) as $o) {
    $name = (string) ($o['name'] ?? '');
    $members = (int) ($o['members'] ?? 0);
    $created = $day($o['created_at'] ?? null);
    $last = $day($o['last_activity'] ?? null);
    $rowsHtml .= '<li style="margin:0 0 6px;"><strong>' . $e($name) . '</strong> — ' . $members . ' membre' . ($members > 1 ? 's' : '')
        . ', créée le ' . $e($created) . ', dernière activité ' . $e($last) . '</li>';
    $rowsText .= '- ' . $name . ' — ' . $members . ' membre' . ($members > 1 ? 's' : '') . ', créée le ' . $created . ', dernière activité ' . $last . "\n";
}
if ($n > 30) {
    $rowsHtml .= '<li>… et ' . ($n - 30) . ' autre(s)</li>';
    $rowsText .= '… et ' . ($n - 30) . " autre(s)\n";
}

$lead = $n . ' organisation' . ($n > 1 ? 's comptent' : ' compte') . ' toujours un ou deux membres après les deux rappels envoyés à leurs responsables.';
$body = '<p>' . $e($lead) . '</p>'
    . '<ul style="margin:0 0 16px 18px;padding:0;">' . $rowsHtml . '</ul>'
    . '<p>Rien n’est supprimé automatiquement. Ouvrez la liste pour supprimer (archivage puis suppression définitive, avec confirmation) ou garder chaque organisation.</p>'
    . email_html_button($listUrl, 'Ouvrir la liste', 'amber')
    . email_html_url_fallback($listUrl);

$html = email_html_layout($lead, 'Organisations à supprimer', $body, ['accent' => 'amber', 'kicker' => 'Administration du site']);

$text = $lead . "\n\n" . $rowsText . "\nRien n’est supprimé automatiquement. Liste : " . $listUrl . "\n"
    . "Ce résumé part au plus une fois par semaine.\n";

return ['html' => $html, 'text' => $text];
