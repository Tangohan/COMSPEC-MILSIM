<?php
declare(strict_types=1);
/**
 * Dossiers d'intérêt — petites fonctions de présentation partagées par la file, la fiche et les modales.
 * Définies une fois par rendu : ($diH, $diStatusTone, $diDate, $diPrio, $diClass, $diIcon, $diField…).
 */
$diH = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$diStatusTone = static fn (string $status): string => match (true) {
    in_array($status, ['signalement_recu', 'a_qualifier', 'en_collecte', 'en_analyse'], true) => 'open',
    in_array($status, ['rapprochements_detectes', 'en_validation', 'correspondance_probable'], true) => 'validate',
    in_array($status, ['brouillon', 'en_attente'], true) => 'hold',
    $status === 'identite_infirme' => 'rejected',
    $status === 'archive' => 'archived',
    default => 'closed',
};
/** Valeur d'un fait : le texte, ou un tiret discret quand il est vide. */
$diV = static fn (mixed $v): string => trim((string) $v) !== '' ? $diH($v) : '<span class="di-muted-text">—</span>';
$diDate = static function (?string $raw, string $format = 'd/m/Y H:i'): string {
    $raw = trim((string) $raw);
    $ts = $raw !== '' ? strtotime($raw) : false;

    return $ts !== false ? date($format, $ts) : '';
};
$diStatus = static function (array $c) use ($diH, $diStatusTone): string {
    return '<span class="di-status di-status--' . $diStatusTone((string) ($c['status'] ?? '')) . '">' . $diH($c['status_label'] ?? '') . '</span>';
};
$diPrio = static function (array $c) use ($diH): string {
    $code = (string) ($c['priority_code'] ?? 'P4');

    return '<span class="di-prio di-prio--' . strtolower($code) . '" title="' . $diH($c['interest_label'] ?? '') . '">' . $diH($code) . ' <span>' . $diH($c['interest_label'] ?? '') . '</span></span>';
};
$diClass = static function (array $c) use ($diH): string {
    $full = (string) ($c['classification_label'] ?? '');
    $short = preg_match('/\(([^)]+)\)\s*$/u', $full, $m) === 1 ? $m[1] : $full;

    return '<span class="di-tag di-tag--class di-tag--class-' . $diH($c['classification'] ?? 'interne') . '" title="' . $diH($full) . '">' . $diH($short) . '</span>';
};
$diIcon = static function (string $name): string {
    $paths = [
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'close' => '<path d="M6 6l12 12M18 6L6 18"/>',
        'edit' => '<path d="M4 20h4l11-11-4-4L4 16v4zM14 6l4 4"/>',
        'pdf' => '<path d="M7 3h7l5 5v13H7zM14 3v5h5M9 14h6M9 17h6"/>',
        'eye' => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'trash' => '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 11v6M14 11v6"/>',
        'flow' => '<path d="M5 6h6v4H5zM13 14h6v4h-6zM11 8h3a2 2 0 0 1 2 2v4"/>',
        'link' => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
        'restore' => '<path d="M4 12a8 8 0 1 0 3-6.2M4 4v5h5"/>',
    ];

    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ($paths[$name] ?? '') . '</svg>';
};
/** Valeur d'un champ de formulaire : saisie précédente (erreur) sinon valeur du dossier sinon défaut. */
$diOld = static function (string $key, mixed $default = '') use (&$form): mixed {
    $old = is_array($form['old'] ?? null) ? $form['old'] : [];

    return array_key_exists($key, $old) ? $old[$key] : $default;
};
$diErr = static function (string $key) use (&$form): string {
    $errors = is_array($form['errors'] ?? null) ? $form['errors'] : [];

    return (string) ($errors[$key] ?? '');
};
/** Bloc d'erreur d'un champ (texte + classe), rendu côté serveur. */
$diFieldError = static function (string $key) use ($diErr, $diH): string {
    $e = $diErr($key);

    return $e === '' ? '' : '<p class="di-error" id="err-' . $diH($key) . '" role="alert"><span aria-hidden="true">!</span><span>' . $diH($e) . '</span></p>';
};
