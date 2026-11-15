<?php
declare(strict_types=1);

/**
 * Aperçu en direct « Votre nom s'affichera ainsi » (liste, fiche, carte tactique), mis à jour pendant la saisie.
 *
 * @var string $mnpFirst sélecteur CSS du champ Prénom
 * @var string $mnpLast sélecteur CSS du champ Nom
 * @var string $mnpCallsignInput sélecteur CSS du champ Indicatif (facultatif)
 * @var string $mnpCallsign indicatif actuel (si pas de champ)
 * @var string $mnpAccount nom du compte actuel
 * @var string $mnpTrigram trigramme actuel
 * @var string $mnpClass classes CSS facultatives
 */
$mnpSettings = \App\Services\Personnel\MemberNameSettings::forTenant((int) \App\Core\Session::get('tenant_id'));
$mnpAccount = trim((string) ($mnpAccount ?? ''));
if (\App\Support\MemberName::looksLikeEmail($mnpAccount)) {
    $mnpAccount = '';
}
$mnpH = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<p class="<?= $mnpH((string) ($mnpClass ?? 'text-[11px] text-slate-600')) ?>"
   data-member-name-preview
   data-first="<?= $mnpH((string) ($mnpFirst ?? '')) ?>"
   data-last="<?= $mnpH((string) ($mnpLast ?? '')) ?>"
   data-callsign-input="<?= $mnpH((string) ($mnpCallsignInput ?? '')) ?>"
   data-callsign="<?= $mnpH((string) ($mnpCallsign ?? '')) ?>"
   data-account="<?= $mnpH($mnpAccount) ?>"
   data-trigram="<?= $mnpH($mnpSettings['trigrams_enabled'] ? (string) ($mnpTrigram ?? '') : '') ?>"
   data-list-format="<?= $mnpH($mnpSettings['list_format']) ?>"
   data-card-format="<?= $mnpH($mnpSettings['card_format']) ?>"
   data-fallback="<?= $mnpH(\App\Support\MemberName::FALLBACK) ?>">
    <span>Votre nom s’affichera ainsi :</span>
    <strong data-mnp="list"></strong> <span>dans les listes,</span>
    <strong data-mnp="card"></strong> <span>sur la fiche,</span>
    <strong data-mnp="c2"></strong> <span>sur la carte tactique.</span>
</p>
<script src="<?= $mnpH(asset_url('assets/js/member-name-preview.js')) ?>" defer></script>
