<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $tenantName */
/** @var int $year */
/** @var string $dueOn date limite AAAA-MM-JJ */
/** @var string $pageUrl */

$e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$year = (int) ($year ?? date('Y'));
$name = trim((string) ($displayName ?? ''));
$dueRaw = trim((string) ($dueOn ?? ''));
$due = $dueRaw !== '' && strtotime($dueRaw) !== false ? date('d/m/Y', (int) strtotime($dueRaw)) : '';
$pageUrl = (string) ($pageUrl ?? '');
$heading = 'Vérifiez votre fiche ' . $year;
$intro = 'Votre communauté vérifie les fiches de ses membres.';
$ask = 'Prenez quelques minutes pour relire la vôtre : identité, matricules, affectation, qualifications et heures.';
$rule = 'Pour chaque partie, dites si c’est exact ou ce qui est à corriger. Rien n’est modifié sans validation de l’encadrement.';
$cta = 'Vérifier ma fiche';

$body = '<p>Bonjour' . ($name !== '' ? ' <span translate="no">' . $e($name) . '</span>' : '') . ',</p>'
    . '<p>' . $e($intro) . '</p>'
    . '<p>' . $e($ask) . '</p>'
    . '<p>' . $e($rule) . '</p>'
    . ($due !== '' ? '<p>Date limite : <strong>' . $e($due) . '</strong></p>' : '')
    . email_html_button($pageUrl, $cta, 'emerald')
    . email_html_url_fallback($pageUrl);

$html = email_html_layout(
    $heading,
    $heading,
    $body,
    [
        'accent' => 'emerald',
        'footer_note' => 'Vous recevez ce rappel tant que votre fiche n’est pas confirmée. Vous pouvez le couper dans Mon compte, préférences de notification.',
    ]
);

$text = 'Bonjour' . ($name !== '' ? ' ' . $name : '') . ",\n\n"
    . $intro . "\n" . $ask . "\n" . $rule . "\n"
    . ($due !== '' ? 'Date limite : ' . $due . "\n" : '')
    . "\n" . $cta . ' : ' . $pageUrl . "\n";

return ['html' => $html, 'text' => $text];
