<?php

declare(strict_types=1);

/** @var int $year */
/** @var string $displayName */
/** @var string $tenantName */
/** @var string $reviewUrl */
/** @var list<array{0: string, 1: string}> $teasers */

$e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$year = (int) ($year ?? date('Y'));
$name = trim((string) ($displayName ?? ''));
$teasers = is_array($teasers ?? null) ? $teasers : [];

$body = '<p>Salut ' . ($name !== '' ? '<span translate="no">' . $e($name) . '</span>' : 'opérateur') . ',</p>'
    . '<p>Ta rétrospective ' . $year . ' est prête : ton année de jeu en quelques écrans, à partager sur Discord.</p>';
if ($teasers !== []) {
    $body .= '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin:18px 0;border-collapse:separate;border-spacing:8px 0;"><tr>';
    foreach ($teasers as [$value, $label]) {
        $body .= '<td style="background:#0b0f0d;border-radius:10px;padding:14px 10px;text-align:center;">'
            . '<div style="font-size:24px;font-weight:800;color:#5cf600;line-height:1.1;">' . $e((string) $value) . '</div>'
            . '<div style="font-size:12px;color:#cbd5c0;text-transform:uppercase;letter-spacing:.06em;margin-top:4px;">' . $e((string) $label) . '</div>'
            . '</td>';
    }
    $body .= '</tr></table>';
}
$body .= '<p>Ton profil de l’année et ta place dans l’unité t’attendent dans la suite.</p>'
    . email_html_button($reviewUrl, 'Voir ma rétrospective', 'emerald')
    . email_html_url_fallback($reviewUrl);

$html = email_html_layout(
    'Ta rétrospective ' . $year . ' est prête',
    'Ton année ' . $year . ' sur le terrain',
    $body,
    [
        'accent' => 'emerald',
        'kicker' => 'Rétrospective ' . $year,
        'footer_note' => 'Un seul e-mail par an. Vous pouvez le couper dans Mon compte, préférences de notification.',
    ]
);

$text = 'Salut ' . ($name !== '' ? $name : 'opérateur') . ",\n\n"
    . 'Ta rétrospective ' . $year . " est prête : ton année de jeu en quelques écrans, à partager sur Discord.\n";
foreach ($teasers as [$value, $label]) {
    $text .= $value . ' ' . $label . "\n";
}
$text .= "\nVoir ma rétrospective : " . $reviewUrl . "\n";

return ['html' => $html, 'text' => $text];
