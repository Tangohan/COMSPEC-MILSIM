<?php

declare(strict_types=1);

/**
 * Modale d’entrée module LMS (passage tableau de bord → formations / recrutement / effectifs).
 *
 * Variables optionnelles :
 * @var string|null $lmsModuleEntryAuto  Clé module à ouvrir automatiquement à l’arrivée (formation|recrutement|effectifs)
 */

use App\Support\LmsModuleEntry;

$lmsModuleEntryAuto = isset($lmsModuleEntryAuto) && is_string($lmsModuleEntryAuto)
    ? trim($lmsModuleEntryAuto)
    : null;
if ($lmsModuleEntryAuto === '') {
    $lmsModuleEntryAuto = null;
}

$lmsModuleEntryConfig = LmsModuleEntry::clientConfig($lmsModuleEntryAuto);
$lmsModuleEntryJsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
    $lmsModuleEntryJsonFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
}
$lmsModuleEntryJson = json_encode($lmsModuleEntryConfig, $lmsModuleEntryJsonFlags);
if (!is_string($lmsModuleEntryJson) || $lmsModuleEntryJson === '') {
    $lmsModuleEntryJson = '{"autoOpen":null,"profile":{"display_name":"Opérateur","callsign":"","community":"Communauté","role":"Membre"},"modules":{}}';
}
?>
<?php if (is_file(base_path('public/assets/css/lms_module_entry_modal.css'))): ?>
<link rel="stylesheet" href="<?= htmlspecialchars(asset_url('assets/css/lms_module_entry_modal.css'), ENT_QUOTES, 'UTF-8') ?>">
<?php endif; ?>
<div
    id="lms-module-entry-modal"
    class="lms-entry-modal hidden"
    role="dialog"
    aria-modal="true"
    aria-labelledby="lms-entry-modal-title"
    aria-hidden="true"
    data-lms-module-entry-modal
>
    <div class="lms-entry-modal__backdrop" data-lms-entry-dismiss tabindex="-1" aria-hidden="true"></div>
    <div class="lms-entry-modal__panel" role="document" data-lms-entry-panel>
        <div class="lms-entry-modal__accent" data-lms-entry-accent aria-hidden="true"></div>
        <div class="lms-entry-modal__head">
            <div class="lms-entry-modal__head-copy">
                <p class="lms-entry-modal__kicker" data-lms-entry-kicker>Passage portail → module</p>
                <h2 id="lms-entry-modal-title" class="lms-entry-modal__title" data-lms-entry-title>Module</h2>
            </div>
            <button type="button" class="lms-entry-modal__close" data-lms-entry-dismiss aria-label="Fermer">×</button>
        </div>
        <div class="lms-entry-modal__body">
            <p class="lms-entry-modal__lead" data-lms-entry-lead></p>

            <section class="lms-entry-modal__profile" aria-label="Profil du compte">
                <p class="lms-entry-modal__section-label">Votre profil</p>
                <div class="lms-entry-modal__profile-card">
                    <p class="lms-entry-modal__profile-name" data-lms-entry-profile-name></p>
                    <p class="lms-entry-modal__profile-meta" data-lms-entry-profile-meta></p>
                </div>
            </section>

            <section class="lms-entry-modal__rights" aria-label="Droits d’accès">
                <p class="lms-entry-modal__section-label">Accès dans ce module</p>
                <ul class="lms-entry-modal__rights-list" data-lms-entry-rights></ul>
            </section>

            <label class="lms-entry-modal__skip">
                <input type="checkbox" data-lms-entry-skip>
                <span>Ne plus afficher pour ce module</span>
            </label>
        </div>
        <div class="lms-entry-modal__foot">
            <button type="button" class="lms-entry-modal__btn lms-entry-modal__btn--ghost" data-lms-entry-dismiss data-lms-entry-secondary>
                Rester sur le portail
            </button>
            <button type="button" class="lms-entry-modal__btn lms-entry-modal__btn--primary" data-lms-entry-continue>
                Continuer
            </button>
        </div>
    </div>
</div>
<script>
window.__lmsModuleEntry = <?= $lmsModuleEntryJson ?>;
</script>
<?php if (is_file(base_path('public/assets/js/lms_module_entry_modal.js'))): ?>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/lms_module_entry_modal.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endif; ?>
