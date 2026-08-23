<?php
declare(strict_types=1);
/**
 * Message flash — motif d’alerte institutionnel (titre + texte, filet coloré).
 * Variables : $flash_message (string), $flash_variant ('error'|'success'|'warning'|'info').
 * Optionnel : $flash_title, $flash_description, $flash_surface ('dark'),
 *             $flash_margin_class (défaut mb-6).
 */
$variant = $flash_variant ?? 'error';
$message = isset($flash_message) ? trim((string) $flash_message) : '';
if ($message === '') {
    return;
}

$eyebrowTitle = isset($flash_title) ? (string) $flash_title : null;
$description = isset($flash_description) ? (string) $flash_description : null;

if ($eyebrowTitle === null || $eyebrowTitle === '') {
    if ($variant === 'error') {
        if (preg_match('/confirmez votre adresse|e-mail avant de vous connecter|vérification.*e-mail/i', $message)) {
            $eyebrowTitle = 'Confirmation requise';
        } elseif (preg_match('/authentification|session|connecter|connecté/i', $message)) {
            $eyebrowTitle = 'Accès refusé';
            if ($description === null || $description === '') {
                $description = 'Cette action ou cette page nécessite une session valide.';
            }
        } else {
            $eyebrowTitle = 'Erreur';
        }
    } elseif ($variant === 'success') {
        $eyebrowTitle = 'Succès';
    } elseif ($variant === 'warning') {
        $eyebrowTitle = 'Attention';
    } else {
        $eyebrowTitle = 'Information';
    }
}

$tone = match ($variant) {
    'success' => 'success',
    'warning' => 'warning',
    'info' => 'info',
    default => 'error',
};
$onDark = !empty($flash_surface) && $flash_surface === 'dark';
$flash_margin_class = isset($flash_margin_class) ? (string) $flash_margin_class : 'mb-6';

if (empty($GLOBALS['__dsfr_service_css'])) {
    $GLOBALS['__dsfr_service_css'] = true;
    echo '<link rel="stylesheet" href="' . htmlspecialchars(asset_url('assets/css/dsfr-service.css'), ENT_QUOTES, 'UTF-8') . '">';
}
?>
<div
    class="<?= htmlspecialchars($flash_margin_class, ENT_QUOTES, 'UTF-8') ?> ds-alert ds-alert--<?= htmlspecialchars($tone, ENT_QUOTES, 'UTF-8') ?><?= $onDark ? ' ds-alert--on-dark' : '' ?>"
    role="alert"
>
    <p class="ds-alert__title"><?= htmlspecialchars($eyebrowTitle, ENT_QUOTES, 'UTF-8') ?></p>
    <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
    <?php if ($description !== null && $description !== ''): ?>
    <p><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
</div>
