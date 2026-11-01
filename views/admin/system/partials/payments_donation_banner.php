<?php
declare(strict_types=1);

/**
 * Admin > Paiements > Dons : section « Bandeau de don ».
 * Choix du style avec aperçu de chacun tel que les visiteurs le voient (français ou anglais ; Portail clair en thème
 * clair et sombre), état, jauge des dons réels, formulaire de réglage (POST /admin/paiements/bandeau-don → SystemSettingsController::saveDonationBanner).
 * Données : DonationBanner::adminSnapshot().
 */

use App\Services\Platform\DonationBanner;

/** @var array{config: array{enabled: bool, message_fr: string, message_en: string, goal_eur: int, starts_on: string, ends_on: string, version: int}, status: string, raised_cents: int, goal_cents: int, percent: int, period: string, preview: array{fr: ?array<string, mixed>, en: ?array<string, mixed>}} $dnbAdmin */
$dnbCfg = $dnbAdmin['config'];
$dnbE = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$dnbDay = static fn (string $d): string => $d !== '' ? date('d/m/Y', (int) strtotime($d)) : '';
$dnbPlaceholderFr = DonationBanner::DEFAULT_MESSAGE_FR;
$dnbPlaceholderEn = DonationBanner::DEFAULT_MESSAGE_EN;
$dnbNotice = \App\Core\Session::getFlash('dnb_notice');
$dnbStatus = [
    'live' => ['ok', 'Affiché en ce moment', 'Les visiteurs le voient en haut du site public et du portail.'],
    'scheduled' => ['info', 'Programmé', 'Il apparaîtra le ' . $dnbDay($dnbCfg['starts_on']) . '.'],
    'ended' => ['neut', 'Terminé', 'Période close le ' . $dnbDay($dnbCfg['ends_on']) . ' : plus rien n’est affiché.'],
    'disabled' => ['neut', 'Désactivé', 'Personne ne le voit. Cochez « Afficher le bandeau » pour le lancer.'],
][$dnbAdmin['status']] ?? ['neut', 'Désactivé', ''];

// Aperçu : même rendu que sur le site, y compris hors période, avec un message d'exemple tant qu'aucun n'est écrit.
$dnbPreviewFor = static function (string $locale) use ($dnbAdmin, $dnbCfg, $dnbPlaceholderFr, $dnbPlaceholderEn): array {
    $v = $dnbAdmin['preview'][$locale] ?? null;
    if (is_array($v)) {
        return $v;
    }

    return DonationBanner::view(['enabled' => true, 'starts_on' => '', 'ends_on' => '', 'message_fr' => $dnbPlaceholderFr, 'message_en' => $dnbPlaceholderEn] + $dnbCfg, $dnbAdmin['raised_cents'], $locale)
        ?? ['message' => $locale === 'en' ? $dnbPlaceholderEn : $dnbPlaceholderFr, 'goal_cents' => 0, 'raised_cents' => 0, 'percent' => 0, 'key' => 'apercu', 'style' => $dnbCfg['style']];
};
?>
<section class="ath-panel ap-card dnba" id="bandeau-don" aria-labelledby="dnba-title">
  <header class="ap-card__head">
    <h2 class="ap-card__t" id="dnba-title">Bandeau de don</h2>
    <span class="ath-tag ath-tag--<?= $dnbE($dnbStatus[0]) ?>"><?= $dnbE($dnbStatus[1]) ?></span>
  </header>
  <p class="ap-muted"><?= $dnbE($dnbStatus[2]) ?> Il n’apparaît jamais dans l’administration, la connexion, le paiement, la page de soutien et les écrans du terrain. Un visiteur peut le fermer : il reste caché 5 jours pour lui, et revient dès que le message change.</p>

  <?php if (is_array($dnbNotice) && isset($dnbNotice[0], $dnbNotice[1])) {
      $flash_message = (string) $dnbNotice[1];
      $flash_variant = $dnbNotice[0] === 'success' ? 'success' : 'error';
      $flash_margin_class = '';
      include base_path('views/partials/flash_message.php');
  } ?>

  <div class="dnba__goal">
    <?php if ($dnbAdmin['goal_cents'] > 0): ?>
      <p class="dnba__sum"><b><?= $dnbE(DonationBanner::euros($dnbAdmin['raised_cents'])) ?></b> reçus sur <?= $dnbE(DonationBanner::euros($dnbAdmin['goal_cents'])) ?> <span class="ap-muted"><?= $dnbE($dnbAdmin['period']) ?></span></p>
      <div class="dnba__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) $dnbAdmin['percent'] ?>" aria-label="Objectif atteint à <?= (int) $dnbAdmin['percent'] ?> %"><span style="width: <?= max(1, (int) $dnbAdmin['percent']) ?>%"></span></div>
      <p class="ap-muted"><?= (int) $dnbAdmin['percent'] ?> % de l’objectif. Comptés : soutiens du cœur, dons par carte et dons enregistrés ici, payés et non remboursés. Les formules ne comptent pas. Le site met jusqu’à 5 minutes à suivre.</p>
    <?php else: ?>
      <p class="ap-muted">Pas d’objectif : le bandeau s’affiche sans jauge. Indiquez un montant ci-dessous pour suivre les dons reçus.</p>
    <?php endif; ?>
  </div>

  <form method="post" action="<?= $dnbE($base . '/bandeau-don') ?>" class="ap-form dnba__form" data-dnba-form>
    <input type="hidden" name="_csrf_token" value="<?= $dnbE(\App\Core\Csrf::token()) ?>">
    <label class="ap-checkline ap-field--wide"><input type="checkbox" name="enabled" value="1"<?= $dnbCfg['enabled'] ? ' checked' : '' ?>> Afficher le bandeau</label>
    <label class="ap-field"><span>Message en français</span><textarea name="message_fr" rows="2" maxlength="<?= DonationBanner::MESSAGE_MAX ?>" placeholder="<?= $dnbE($dnbPlaceholderFr) ?>" data-dnba-msg="fr"><?= $dnbE($dnbCfg['message_fr']) ?></textarea><small>180 caractères au plus.</small></label>
    <label class="ap-field"><span>Message en anglais</span><textarea name="message_en" rows="2" maxlength="<?= DonationBanner::MESSAGE_MAX ?>" placeholder="<?= $dnbE($dnbPlaceholderEn) ?>" data-dnba-msg="en"><?= $dnbE($dnbCfg['message_en']) ?></textarea><small>Facultatif : sans lui, le message français est repris.</small></label>
    <label class="ap-field"><span>Objectif (€)</span><input name="goal_eur" type="number" min="0" max="<?= DonationBanner::GOAL_MAX_EUR ?>" step="1" inputmode="numeric" value="<?= $dnbCfg['goal_eur'] > 0 ? (int) $dnbCfg['goal_eur'] : '' ?>" placeholder="300"><small>Vide : pas de jauge. Dons comptés depuis le début, sinon depuis le 1er du mois.</small></label>
    <label class="ap-field"><span>Début</span><input name="starts_on" type="date" value="<?= $dnbE($dnbCfg['starts_on']) ?>"><small>Facultatif.</small></label>
    <label class="ap-field"><span>Fin (incluse)</span><input name="ends_on" type="date" value="<?= $dnbE($dnbCfg['ends_on']) ?>"><small>Facultatif.</small></label>
    <fieldset class="ap-field--wide dnba__styles" data-dnba-stage>
      <legend>Style du bandeau <button type="button" class="ap-copy dnba__lang" data-dnba-toggle aria-expanded="false">Voir en anglais</button></legend>
      <div class="dnba__grid">
      <?php foreach (DonationBanner::STYLES as $styleKey => [$styleName, $styleDesc]):
          $styleId = 'dnba-style-' . $styleKey; ?>
        <div class="dnba__opt">
          <input type="radio" name="style" value="<?= $dnbE($styleKey) ?>" id="<?= $dnbE($styleId) ?>"<?= $dnbCfg['style'] === $styleKey ? ' checked' : '' ?>>
          <label for="<?= $dnbE($styleId) ?>">
            <span class="dnba__name"><?= $dnbE($styleName) ?></span>
            <span class="dnba__desc"><?= $dnbE($styleDesc) ?></span>
            <?php foreach ($styleKey === 'portail' ? ['light', 'dark'] : ['dark'] as $theme):
                foreach (['fr', 'en'] as $loc):
                    $dnb = ['style' => $styleKey] + $dnbPreviewFor($loc);
                    $dnbLocale = $loc;
                    $dnbId = '';
                    $dnbT = static fn (string $k, array $r = []): string => htmlspecialchars(__('site.dnb_' . $k, $r, $loc), ENT_QUOTES, 'UTF-8'); ?>
            <span class="dnb-preview" data-dnb-theme="<?= $dnbE($theme) ?>" data-dnba-lang="<?= $dnbE($loc) ?>"<?= $loc === 'en' ? ' hidden' : '' ?> inert><?php require base_path('views/partials/donation_banner_markup.php'); ?></span>
            <?php endforeach; endforeach; ?>
          </label>
        </div>
      <?php endforeach; ?>
      </div>
    </fieldset>
    <div class="ap-field--wide ap-formbar"><button class="ath-btn ath-btn--solid">Enregistrer le bandeau</button></div>
  </form>
</section>
<style>
.dnba__styles { margin: 0; padding: 0; border: 0; min-width: 0; }
.dnba__styles legend { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 8px; font-size: 12px; font-weight: 700; }
.dnba__grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 30rem), 1fr)); gap: 12px; }
.dnba__opt { position: relative; min-width: 0; }
.dnba__opt > input { position: absolute; top: 14px; left: 12px; margin: 0; accent-color: #059669; }
.dnba__opt > label { display: grid; align-content: start; gap: 6px; height: 100%; padding: 10px 10px 10px 34px; border: 1px solid var(--ath-line, #e5e7eb); background: var(--ath-surface, #fff); cursor: pointer; }
.dnba__opt > label:hover { border-color: var(--ath-muted, #9ca3af); }
.dnba__opt > input:checked + label { border-color: #059669; box-shadow: inset 0 0 0 1px #059669; }
.dnba__opt > input:focus-visible + label { outline: 2px solid var(--ath-accent, #1d4ed8); outline-offset: 2px; }
.dnba__name { font-size: 14px; font-weight: 700; }
.dnba__desc { font-size: 12px; color: var(--ath-muted, #6b7280); }
.dnba__opt .dnb-preview { display: block; margin-left: -24px; border: 1px solid var(--ath-line, #e5e7eb); overflow: hidden; pointer-events: none; }
.dnba__opt .dnb-preview[hidden] { display: none; }
.dnba__goal { margin: 12px 0; display: grid; gap: 6px; }
.dnba__sum { margin: 0; font-size: 14px; }
.dnba__sum b { font-size: 20px; font-variant-numeric: tabular-nums; }
.dnba__bar { height: 10px; background: var(--ath-bg, #f3f4f6); border: 1px solid var(--ath-line, #e5e7eb); overflow: hidden; }
.dnba__bar > span { display: block; height: 100%; background: linear-gradient(90deg, #059669, #34d399); }
.dnba__form textarea { width: 100%; padding: 7px 10px; border: 1px solid var(--ath-line, #d1d5db); background: var(--ath-surface, #fff); color: var(--ath-ink, #111827); font: inherit; font-size: 14px; resize: vertical; }
.dnba__form textarea:focus { outline: 2px solid var(--ath-accent, #1d4ed8); outline-offset: -1px; }
</style>
<script>
(function () {
  var root = document.getElementById('bandeau-don');
  if (!root) { return; }
  var toggle = root.querySelector('[data-dnba-toggle]');
  if (toggle) {
    toggle.addEventListener('click', function () {
      var open = toggle.getAttribute('aria-expanded') !== 'true';
      root.querySelectorAll('[data-dnba-lang]').forEach(function (el) { el.hidden = (el.getAttribute('data-dnba-lang') === 'en') !== open; });
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      toggle.textContent = open ? 'Voir en français' : 'Voir en anglais';
    });
  }
  // Aperçu en direct : le message suit la saisie (l'anglais reprend le français s'il est vide, comme sur le site).
  var fr = root.querySelector('[data-dnba-msg="fr"]');
  var en = root.querySelector('[data-dnba-msg="en"]');
  if (!fr || !en) { return; }
  var paint = function () {
    var vFr = fr.value.trim(), vEn = en.value.trim();
    var text = { fr: vFr || vEn || fr.placeholder, en: vEn || vFr || en.placeholder };
    root.querySelectorAll('[data-dnba-lang]').forEach(function (box) {
      var msg = box.querySelector('.dnb__msg');
      if (msg) { msg.textContent = text[box.getAttribute('data-dnba-lang')] || ''; }
    });
  };
  fr.addEventListener('input', paint);
  en.addEventListener('input', paint);
})();
</script>
