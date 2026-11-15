<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $tenantName */
/** @var int $year */
/** @var string $dueOn date limite AAAA-MM-JJ */
/** @var string $pageUrl */
/** @var string $originLine phrase d'origine (qui demande), déjà dans la langue du destinataire */
/** @var string $periodLine « du 15 novembre au 31 décembre 2026 » */
/** @var string $stateLine phrase d'état quand l'échéance est dépassée */

$e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$year = (int) ($year ?? date('Y'));
$name = trim((string) ($displayName ?? ''));
$dueRaw = trim((string) ($dueOn ?? ''));
$due = $dueRaw !== '' && strtotime($dueRaw) !== false ? date('d/m/Y', (int) strtotime($dueRaw)) : '';
$pageUrl = (string) ($pageUrl ?? '');
$originLine = trim((string) ($originLine ?? ''));
$periodLine = trim((string) ($periodLine ?? ''));
$stateLine = trim((string) ($stateLine ?? ''));
$heading = 'Vérifiez votre fiche ' . $year;
$intro = 'Votre communauté vérifie les fiches de ses membres.';
$ask = 'Prenez quelques minutes pour relire la vôtre : identité, matricules, affectation, qualifications et heures.';
$rule = 'Pour chaque partie, dites si c’est exact ou ce qui est à corriger. Rien n’est modifié sans validation de l’encadrement.';
$cta = 'Vérifier ma fiche';

$body = '<p>Bonjour' . ($name !== '' ? ' <span translate="no">' . $e($name) . '</span>' : '') . ',</p>'
    . '<p>' . $e($intro) . '</p>'
    . ($originLine !== '' ? '<p>' . $e($originLine) . '</p>' : '')
    . '<p>' . $e($ask) . '</p>'
    . '<p>' . $e($rule) . '</p>'
    . ($periodLine !== '' ? '<p><strong>' . $e(mb_strtoupper(mb_substr($periodLine, 0, 1)) . mb_substr($periodLine, 1)) . '</strong></p>' : '')
    . ($due !== '' && $periodLine === '' ? '<p>Date limite : <strong>' . $e($due) . '</strong></p>' : '')
    . ($stateLine !== '' ? '<p>' . $e($stateLine) . '</p>' : '')
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
    . $intro . "\n" . ($originLine !== '' ? $originLine . "\n" : '') . $ask . "\n" . $rule . "\n"
    . ($periodLine !== '' ? mb_strtoupper(mb_substr($periodLine, 0, 1)) . mb_substr($periodLine, 1) . "\n" : '')
    . ($due !== '' && $periodLine === '' ? 'Date limite : ' . $due . "\n" : '')
    . ($stateLine !== '' ? $stateLine . "\n" : '')
    . "\n" . $cta . ' : ' . $pageUrl . "\n";

return ['html' => $html, 'text' => $text];
