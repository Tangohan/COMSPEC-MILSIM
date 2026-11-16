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
 * @var string $mnpLayout « rows » : un tableau Listes / Fiche / Carte tactique au lieu de la phrase (facultatif)
 */
$mnpSettings = \App\Services\Personnel\MemberNameSettings::forTenant((int) \App\Core\Session::get('tenant_id'));
$mnpAccount = trim((string) ($mnpAccount ?? ''));
if (\App\Support\MemberName::looksLikeEmail($mnpAccount)) {
    $mnpAccount = '';
}
$mnpH = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<?php $mnpRows = ($mnpLayout ?? '') === 'rows'; ?>
<<?= $mnpRows ? 'dl' : 'p' ?> class="<?= $mnpH((string) ($mnpClass ?? 'text-[11px] text-slate-600')) ?>"
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
<?php if ($mnpRows): ?>
    <div><dt>Listes</dt><dd data-mnp="list" translate="no"></dd></div>
    <div><dt>Fiche</dt><dd data-mnp="card" translate="no"></dd></div>
    <div><dt>Carte tactique</dt><dd data-mnp="c2" translate="no"></dd></div>
</dl>
<?php else: ?>
    <span>Votre nom s’affichera ainsi :</span>
    <strong data-mnp="list"></strong> <span>dans les listes,</span>
    <strong data-mnp="card"></strong> <span>sur la fiche,</span>
    <strong data-mnp="c2"></strong> <span>sur la carte tactique.</span>
</p>
<?php endif; ?>
<script src="<?= $mnpH(asset_url('assets/js/member-name-preview.js')) ?>" defer></script>
