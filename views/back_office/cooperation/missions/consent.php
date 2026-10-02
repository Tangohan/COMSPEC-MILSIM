<?php
declare(strict_types=1);

use App\Support\CooperationDictionary;

/**
 * Autorisation de partage — parcours en deux étapes dans la même page :
 *   1. choix des familles de données (3 catégories, « tout sélectionner », justification si sensible) ;
 *   2. saisie du code reçu par e-mail (compte à rebours avant renvoi).
 * Sans JavaScript, les deux étapes restent utilisables (deux formulaires classiques).
 */

$m = $interteamMission ?? [];
$mid = (int) ($m['id'] ?? 0);
$return = trim((string) ($interteamConsentReturn ?? ''));
$csrf = $csrfToken ?? \App\Core\Csrf::token();
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$suggested = $cooperationSuggestedShareKeys ?? [];
$status = is_array($cooperationConsentStatus ?? null) ? $cooperationConsentStatus : ['state' => 'none', 'until' => null, 'keys' => [], 'justification' => ''];
$consentStep = (int) ($cooperationConsentStep ?? 1);
$resendIn = (int) ($cooperationConsentResendIn ?? 0);
$ttlHours = (int) ($cooperationConsentTtlHours ?? 72);
$otpMinutes = (int) ($cooperationConsentOtpTtlMinutes ?? 15);
$checked = $status['keys'] !== [] ? $status['keys'] : $suggested;
$untilTs = $status['until'] !== null ? strtotime((string) $status['until']) : false;
$returnField = $return !== '' ? '<input type="hidden" name="return" value="' . $h($return) . '">' : '';
$step1Url = cooperation_mission_consent_url($mid) . '?' . http_build_query(array_filter(['etape' => '1', 'return' => $return]));
?>
<div class="max-w-4xl mx-auto px-6 py-10">
    <?php require base_path('views/back_office/cooperation/missions/_nav.php'); ?>
    <h1 class="text-2xl font-black text-slate-900 mb-2">Autorisation de partage</h1>
    <p class="text-sm text-slate-600 mb-6 leading-relaxed max-w-2xl">Indiquez ce que votre unité accepte de partager dans la coopération <strong><?= $h((string) ($m['title'] ?? '')) ?></strong>, puis confirmez avec le code reçu par e-mail. L’autorisation est valable <?= $ttlHours ?> heures.</p>

    <?php if ($status['state'] === 'valid'): ?>
    <div class="coop-consent-state coop-consent-state--valid" role="status">
        <strong>Autorisation valide<?= $untilTs !== false ? ' jusqu’au ' . $h(date('d/m/Y à H:i', $untilTs)) : '' ?>.</strong>
        Vous pouvez lire et écrire sur l’espace commun. Vous pouvez la renouveler ou modifier son périmètre ci-dessous.
    </div>
    <?php elseif ($status['state'] === 'expired'): ?>
    <div class="coop-consent-state coop-consent-state--expired" role="alert">
        <strong>Autorisation expirée<?= $untilTs !== false ? ' depuis le ' . $h(date('d/m/Y à H:i', $untilTs)) : '' ?>.</strong>
        Renouvelez-la pour retrouver l’accès en écriture à l’espace commun.
    </div>
    <?php endif; ?>

    <div class="coop-progress" aria-label="Parcours d’autorisation">
        <p class="coop-progress__title">Étape <?= $consentStep ?> sur 2 — <?= $consentStep === 1 ? 'Ce que vous partagez' : 'Code de confirmation' ?></p>
        <div class="coop-progress__steps">
            <?php
            $steps = [
                ['label' => 'Ce que vous partagez', 'done' => $consentStep > 1, 'active' => $consentStep === 1],
                ['label' => 'Code de confirmation', 'done' => false, 'active' => $consentStep === 2],
            ];
            require base_path('views/partials/ui/stepper.php');
            ?>
        </div>
    </div>

    <?php if ($consentStep === 1): ?>
    <form method="post" action="<?= $h(cooperation_mission_consent_url($mid) . '/send-otp') ?>" class="coop-consent" data-coop-consent>
        <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
        <?= $returnField ?>
        <?php foreach (CooperationDictionary::dataSharingFamilyGroups() as $gkey => $group): ?>
        <fieldset class="coop-consent__group" data-consent-group>
            <legend class="coop-consent__legend"><?= $h($group['label']) ?></legend>
            <div class="coop-consent__group-head">
                <p class="coop-consent__group-desc"><?= $h($group['description']) ?></p>
                <label class="coop-consent__all">
                    <input type="checkbox" data-consent-all aria-controls="consent-group-<?= $h($gkey) ?>">
                    Tout sélectionner
                </label>
            </div>
            <div id="consent-group-<?= $h($gkey) ?>" class="coop-consent__items">
                <?php foreach ($group['keys'] as $k): ?>
                <?php $sensitive = CooperationDictionary::isSensitiveDataFamily($k); ?>
                <label class="coop-consent__item">
                    <input type="checkbox" name="share_<?= $h($k) ?>" value="1"<?= in_array($k, $checked, true) ? ' checked' : '' ?><?= $sensitive ? ' data-sensitive="1"' : '' ?>>
                    <span><?= $h(CooperationDictionary::dataSharingFamilyLabel($k)) ?></span>
                    <?php if ($sensitive): ?><span class="coop-consent__sensitive">Sensible</span><?php endif; ?>
                </label>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <?php endforeach; ?>

        <div class="coop-consent__justif" data-consent-justif>
            <label for="justification_sensitive" class="block text-sm font-semibold text-slate-900">Justification du partage sensible <span class="text-rose-700" aria-hidden="true">*</span></label>
            <textarea id="justification_sensitive" name="justification_sensitive" rows="3" maxlength="4000" aria-describedby="justif-msg" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Cadre opérationnel, accord de votre hiérarchie…"><?= $h($status['justification']) ?></textarea>
            <p id="justif-msg" class="fr-message">Obligatoire dès qu’une donnée marquée « Sensible » est cochée (10 caractères au moins).</p>
        </div>

        <p class="text-sm text-slate-600">Un code à six chiffres vous sera envoyé par e-mail ; il est valable <?= $otpMinutes ?> minutes.</p>
        <button type="submit" class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Recevoir le code par e-mail</button>
    </form>
    <?php else: ?>
    <section class="coop-consent coop-consent--code" aria-labelledby="otp-title">
        <h2 id="otp-title" class="text-base font-bold text-slate-900">Saisissez le code reçu par e-mail</h2>
        <p class="text-sm text-slate-600">Un code à six chiffres vient d’être envoyé à l’adresse de votre compte. Il est valable <?= $otpMinutes ?> minutes. Pensez à vérifier vos courriers indésirables.</p>
        <form method="post" action="<?= $h(cooperation_mission_consent_url($mid) . '/verify-otp') ?>" class="mt-4 flex flex-wrap items-end gap-3">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
            <?= $returnField ?>
            <div>
                <label for="otp_code" class="block text-sm font-semibold text-slate-900">Code à six chiffres</label>
                <input id="otp_code" name="otp_code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" autofocus required
                       class="coop-otp mt-2" placeholder="000000" aria-describedby="otp-help">
                <p id="otp-help" class="fr-message">Six chiffres, sans espace.</p>
            </div>
            <button type="submit" class="rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800">Valider</button>
        </form>
        <div class="mt-5 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">
            <form method="post" action="<?= $h(cooperation_mission_consent_url($mid) . '/send-otp') ?>" data-consent-resend data-wait="<?= $resendIn ?>">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <input type="hidden" name="resend" value="1">
                <?= $returnField ?>
                <button type="submit" class="font-semibold text-emerald-800 underline disabled:no-underline disabled:text-slate-400"<?= $resendIn > 0 ? ' disabled' : '' ?> data-resend-btn>
                    Renvoyer le code<span data-resend-count><?= $resendIn > 0 ? ' (possible dans ' . $resendIn . ' s)' : '' ?></span>
                </button>
            </form>
            <a href="<?= $h($step1Url) ?>" class="text-slate-600 underline">Modifier ce que je partage</a>
        </div>
    </section>
    <?php endif; ?>

    <p class="mt-6"><a href="<?= $h(cooperation_mission_show_url($mid)) ?>" class="text-sm text-slate-600 underline">Retour à la synthèse</a></p>
</div>
<script defer src="<?= $h(asset_url('assets/js/cooperation/consent.js')) ?>"></script>
