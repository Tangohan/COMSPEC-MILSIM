<?php

declare(strict_types=1);

/**
 * Bandeau d’orientation : l’utilisateur est dans un module Athena (formations ou recrutement).
 *
 * @var string|null $lmsAthenaBannerReturnUrl
 * @var string|null $lmsAthenaBannerReturnLabel
 */

$lmsAthenaBannerReturnUrl = trim((string) ($lmsAthenaBannerReturnUrl ?? ''));
if ($lmsAthenaBannerReturnUrl === '') {
    $lmsAthenaBannerReturnUrl = url('dashboard');
}
$lmsAthenaBannerReturnLabel = trim((string) ($lmsAthenaBannerReturnLabel ?? ''));
if ($lmsAthenaBannerReturnLabel === '') {
    $lmsAthenaBannerReturnLabel = 'Retour';
}
$hBanner = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<div class="lms-athena-banner" role="status">
    <p class="lms-athena-banner__text">
        <span class="lms-athena-banner__icon" aria-hidden="true">
            <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </span>
        <span>Vous visionnez un module ATHENA</span>
    </p>
    <a class="lms-athena-banner__back" href="<?= $hBanner($lmsAthenaBannerReturnUrl) ?>"><?= $hBanner($lmsAthenaBannerReturnLabel) ?></a>
</div>
