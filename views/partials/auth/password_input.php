<?php
declare(strict_types=1);
/**
 * Champ mot de passe avec bouton œil (aria-pressed) et alerte « Verr. Maj activée ».
 * Attend : $pwId, $pwName, $pwAutocomplete ; optionnels : $pwDescribedBy, $pwAttrs (attributs bruts déjà échappés).
 */
$pwT = static fn (string $key): string => htmlspecialchars(__($key), ENT_QUOTES, 'UTF-8');
$pwIdSafe = htmlspecialchars((string) $pwId, ENT_QUOTES, 'UTF-8');
$pwDescribed = trim(($pwDescribedBy ?? '') . ' ' . $pwId . '-caps');
?>
<div class="rg-password">
    <input type="password" name="<?= htmlspecialchars((string) $pwName, ENT_QUOTES, 'UTF-8') ?>" id="<?= $pwIdSafe ?>" required
           autocomplete="<?= htmlspecialchars((string) $pwAutocomplete, ENT_QUOTES, 'UTF-8') ?>" spellcheck="false" autocapitalize="none"
           class="rg-input rg-input--password"
           aria-describedby="<?= htmlspecialchars($pwDescribed, ENT_QUOTES, 'UTF-8') ?>"
           data-password-input data-caps-watch<?= isset($pwAttrs) && $pwAttrs !== '' ? ' ' . $pwAttrs : '' ?>>
    <button type="button" class="rg-eye" data-password-toggle="<?= $pwIdSafe ?>"
            aria-controls="<?= $pwIdSafe ?>" aria-pressed="false"
            data-label-show="<?= $pwT('auth.show_password') ?>"
            data-label-hide="<?= $pwT('auth.hide_password') ?>"
            aria-label="<?= $pwT('auth.show_password') ?>"
            title="<?= $pwT('auth.show_password') ?>"><svg class="rg-eye__show" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.8"/></svg><svg class="rg-eye__hide" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M3 3l18 18M10.6 5.1A10.6 10.6 0 0 1 12 5c6.4 0 10 7 10 7a17 17 0 0 1-3.2 4.1M6.6 6.6A16.5 16.5 0 0 0 2 12s3.6 7 10 7a9.8 9.8 0 0 0 5.4-1.6M9.9 9.9a3 3 0 0 0 4.2 4.2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
</div>
<p class="au-caps" id="<?= $pwIdSafe ?>-caps" data-caps-for="<?= $pwIdSafe ?>" aria-live="polite" hidden><?= $pwT('auth.caps_lock_on') ?></p>
<?php
unset($pwAttrs, $pwDescribedBy);
