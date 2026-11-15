<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $tenantName */
/** @var array<string, mixed> $recap */
/** @var string $pageUrl */

$esc = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$r = is_array($recap ?? null) ? $recap : [];
$base = is_array($r['base'] ?? null) ? $r['base'] : [];

$received = [];
foreach (['grade' => 'Grade', 'unit' => 'Unité', 'job' => 'Emploi'] as $k => $label) {
    if (trim((string) ($base[$k] ?? '')) !== '') {
        $received[] = [$label, (string) $base[$k]];
    }
}
if (($base['roles'] ?? []) !== []) {
    $received[] = ['Niveau d’accès', implode(', ', (array) $base['roles'])];
}
foreach ((array) ($r['qualifications'] ?? []) as $q) {
    $received[] = ['Qualification', (string) ($q['name'] ?? '') . (($q['status'] ?? '') === 'in_training' ? ' (en formation)' : '')];
}
$courses = array_values(array_filter(array_map('strval', (array) ($r['courses'] ?? []))));
$welcome = trim((string) ($r['welcome'] ?? ''));

$body = '<p>Bonjour ' . $esc($displayName) . ',</p>'
    . '<p>Votre intégration dans <strong>' . $esc($tenantName) . '</strong> est enregistrée. Voici ce que vous avez reçu.</p>';
if ($received !== []) {
    $body .= '<ul>';
    foreach ($received as [$label, $value]) {
        $body .= '<li><strong>' . $esc($label) . '</strong> : ' . $esc($value) . '</li>';
    }
    $body .= '</ul>';
}
if ($courses !== []) {
    $body .= '<p><strong>À faire</strong> : suivre ' . (count($courses) > 1 ? 'ces formations' : 'cette formation') . ' dans l’Academy.</p><ul>';
    foreach ($courses as $c) {
        $body .= '<li>' . $esc($c) . '</li>';
    }
    $body .= '</ul>';
}
if ($welcome !== '') {
    $body .= '<p>' . nl2br($esc($welcome)) . '</p>';
}
$body .= email_html_button($pageUrl, 'Voir mon intégration', 'emerald') . email_html_url_fallback($pageUrl);

$html = email_html_layout('Bienvenue', 'Ce que vous avez reçu', $body, ['accent' => 'emerald']);

$text = "Bonjour {$displayName},\n\nVotre intégration dans « {$tenantName} » est enregistrée.\n\n";
foreach ($received as [$label, $value]) {
    $text .= "- {$label} : {$value}\n";
}
if ($courses !== []) {
    $text .= "\nÀ faire : suivre " . implode(', ', $courses) . ".\n";
}
if ($welcome !== '') {
    $text .= "\n{$welcome}\n";
}
$text .= "\n{$pageUrl}\n";

return ['html' => $html, 'text' => $text];
