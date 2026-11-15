<?php
/**
 * Cycle de 24 h des marqueurs et « Missions archivées » (poste TOC web, ATAK web / Overwatch, TACMAP). Le comportement est
 * dans public/assets/js/atak-marker-cycle.js (un seul module pour les trois postes) ; ce fichier ne porte que le balisage.
 *
 * Variables :
 *   $cycleId           identifiant de l'élément racine (ex. « toc-cycle »)
 *   $cycleMap          carte par défaut
 *   $cycleMapSelector  sélecteur CSS d'une liste « terrain » de la page (la carte suit son choix), sinon vide
 *   $cycleClass        classes ajoutées au bouton d'état (ex. « toc-btn » pour reprendre le style de la barre)
 *   $cycleFloating     true : bouton posé en bas à gauche de la page ; false : dans le flux de la page
 */
$cycleId = (string) ($cycleId ?? 'atak-cycle');
$cycleMap = max(1, (int) ($cycleMap ?? 1));
$cycleMapSelector = (string) ($cycleMapSelector ?? '');
$cycleClass = trim((string) ($cycleClass ?? ''));
$cycleFloating = !empty($cycleFloating);
$cycleH = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$cycleCss = function_exists('asset_url') ? asset_url('assets/css/atak-marker-cycle.css') : url('assets/css/atak-marker-cycle.css');
$cycleJs = function_exists('asset_url') ? asset_url('assets/js/atak-marker-cycle.js') : url('assets/js/atak-marker-cycle.js');
?>
<link rel="stylesheet" href="<?= $cycleH($cycleCss) ?>">
<div class="mcy<?= $cycleFloating ? ' mcy--float' : '' ?>" id="<?= $cycleH($cycleId) ?>" data-mcy data-base="<?= $cycleH(rtrim(url(''), '/')) ?>" data-map="<?= $cycleH($cycleMap) ?>" data-map-selector="<?= $cycleH($cycleMapSelector) ?>" data-csrf="<?= $cycleH(\App\Core\Csrf::token()) ?>">
  <button type="button" class="mcy-chip<?= $cycleClass !== '' ? ' ' . $cycleH($cycleClass) : '' ?>" data-mcy-open hidden aria-haspopup="dialog">
    <i class="mcy-dot" aria-hidden="true"></i>
    <span class="mcy-chip-text" data-mcy-chip>Missions archivées</span>
  </button>

  <dialog class="mcy-dlg" data-mcy-dialog aria-labelledby="<?= $cycleH($cycleId) ?>-title">
    <div class="mcy-head">
      <h2 class="mcy-title" id="<?= $cycleH($cycleId) ?>-title">Missions archivées</h2>
      <button type="button" class="mcy-x" data-mcy-close aria-label="Fermer">×</button>
    </div>
    <p class="mcy-note" data-mcy-current></p>

    <section data-mcy-view="list">
      <form class="mcy-search" data-mcy-search role="search">
        <label class="mcy-field"><span>Chercher</span><input type="search" name="q" maxlength="60" autocomplete="off" placeholder="Date, heure…"></label>
        <label class="mcy-field"><span>Du</span><input type="date" name="from"></label>
        <label class="mcy-field"><span>Au</span><input type="date" name="to"></label>
        <button type="submit" class="mcy-btn">Rechercher</button>
      </form>
      <p class="mcy-msg" data-mcy-msg role="status" hidden></p>
      <ul class="mcy-list" data-mcy-list></ul>

      <details class="mcy-settings" data-mcy-settings hidden>
        <summary>Réglages du cycle</summary>
        <form class="mcy-form" data-mcy-form>
          <label class="mcy-check"><input type="checkbox" name="enabled"> <span>Archiver les marqueurs à chaque bascule</span></label>
          <label class="mcy-field"><span>Heure de bascule</span><input type="time" name="switch_time" value="12:00" required></label>
          <label class="mcy-field"><span>Durée d’un cycle</span>
            <select name="duration_days">
              <option value="1">24 heures</option>
              <option value="2">48 heures</option>
              <option value="3">3 jours</option>
              <option value="7">7 jours</option>
            </select>
          </label>
          <label class="mcy-field"><span>Conservation des archives</span>
            <select name="retention_days">
              <option value="365">1 an</option>
              <option value="730">2 ans</option>
              <option value="1095">3 ans</option>
              <option value="1825">5 ans</option>
              <option value="0">Sans limite</option>
            </select>
          </label>
          <p class="mcy-note" data-mcy-tz></p>
          <p class="mcy-note">Désactivé : tous les marqueurs restent affichés, comme avant. Les éléments épinglés restent affichés d’un cycle à l’autre.</p>
          <button type="submit" class="mcy-btn mcy-btn--primary">Enregistrer les réglages</button>
        </form>
      </details>
    </section>

    <section data-mcy-view="detail" hidden>
      <div class="mcy-bar">
        <button type="button" class="mcy-btn" data-mcy-back>Retour à la liste</button>
        <span class="mcy-ro">Lecture seule</span>
      </div>
      <h3 class="mcy-sub" data-mcy-detail-title></h3>
      <p class="mcy-note" data-mcy-detail-note></p>
      <p class="mcy-note" data-mcy-detail-cut hidden>Affichage limité aux premiers éléments : l’export est complet.</p>
      <p class="mcy-note">Cette mission est archivée : rien ici ne modifie la carte.</p>
      <div class="mcy-plot" data-mcy-plot role="img" aria-label="Carte de la mission archivée"></div>
      <div class="mcy-actions" data-mcy-detail-actions></div>
      <ul class="mcy-items" data-mcy-items></ul>
    </section>
  </dialog>
</div>
<script src="<?= $cycleH($cycleJs) ?>" defer></script>
