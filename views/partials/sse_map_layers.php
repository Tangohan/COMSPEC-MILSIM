<?php
/**
 * Panneau « Calques SSE » des cartes (poste TOC web, ATAK web / Overwatch, TACMAP) : contrôles d'identité, lieux, pistes de
 * téléphones. Le comportement est dans public/assets/js/sse-map-layers.js (un seul module pour les trois cartes) ; ce
 * fichier ne porte que le balisage et les textes (traduits avec la page, relus par le module dans [data-sse-ml-texts]).
 *
 * Variables :
 *   $sseMlId       identifiant de l'élément racine (ex. « toc-sse-ml »)
 *   $sseMlFloating true : fenêtre flottante ouverte par un bouton de la page ; false : bloc intégré à un panneau
 *   $sseMlBase     préfixe d'URL du site (url(''))
 *
 * Masqué au départ : le module ne l'affiche que si le lecteur franchit la porte du Bureau SSE, et seules les cases dont il a
 * le droit apparaissent. Calques désactivés au départ.
 */
$sseMlId = (string) ($sseMlId ?? 'sse-ml');
$sseMlFloating = !empty($sseMlFloating);
$sseMlBase = rtrim((string) ($sseMlBase ?? ''), '/');
$sseMlH = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$sseMlTexts = [
    'load_error' => 'Calques SSE indisponibles pour le moment.',
    'forbidden' => 'Accès à cette section refusé.',
    'audit_unavailable' => 'Pistes indisponibles : le journal d’audit SSE ne répond pas.',
    'truncated' => 'Affichage limité : rapprochez la carte ou réduisez la période.',
    'empty_none_controles' => 'Aucun contrôle d’identité positionné sur cette carte pour la période.',
    'empty_none_lieux' => 'Aucun lieu enregistré sur cette carte.',
    'empty_no_extraction' => 'Aucune piste : aucune extraction de téléphone sur cette carte pour la période.',
    'empty_no_position' => 'Aucune piste : les extractions de téléphones n’ont pas de positions.',
    'empty_no_movement' => 'Aucune piste : les extractions n’ont qu’une seule position, sans déplacement.',
    'empty_unmapped' => 'Aucune piste : les extractions existantes n’ont pas de carte d’origine.',
    'cutoff_all' => 'Jusqu’à maintenant',
    'cutoff_until' => 'Jusqu’au %1',
    'total' => '%1 élément(s) affiché(s)',
    'cluster' => '%1 éléments regroupés',
    'anon_check' => 'Contrôle',
    'place' => 'Lieu',
    'track' => 'Piste de téléphone',
    'ref' => 'Référence',
    'result' => 'Résultat',
    'when' => 'Date et heure',
    'game_time' => 'Heure de jeu',
    'grid' => 'Grille',
    'source' => 'Source',
    'src_check' => 'Contrôle d’identité',
    'src_site' => 'Site exploité',
    'kind' => 'Type',
    'state' => 'État',
    'recorded' => 'Reçue le',
    'positions' => 'Positions',
    'positions_count' => '%1 sur %2',
    'track_start' => 'Départ',
    'track_end' => 'Arrivée',
    'track_hint' => 'Le sens suit l’ordre de réception des positions ; les heures sont celles du jeu.',
    'vertex' => 'Position %1 sur %2',
    'open_sheet' => 'Ouvrir la fiche',
    'open_check' => 'Ouvrir le contrôle',
    'open_place' => 'Ouvrir le lieu',
    'open_extraction' => 'Ouvrir l’extraction',
];
?>
<section class="sse-ml<?= $sseMlFloating ? ' sse-ml--float' : '' ?>" id="<?= $sseMlH($sseMlId) ?>" data-sse-ml data-base="<?= $sseMlH($sseMlBase) ?>" hidden role="group" aria-labelledby="<?= $sseMlH($sseMlId) ?>-title">
  <div class="sse-ml__head">
    <h3 class="sse-ml__title" id="<?= $sseMlH($sseMlId) ?>-title">Calques SSE</h3>
    <?php if ($sseMlFloating): ?>
    <button type="button" class="sse-ml__close" data-sse-ml-close aria-label="Fermer les calques SSE">Fermer</button>
    <?php endif; ?>
  </div>
  <p class="sse-ml__note">Désactivés par défaut. Réservés aux membres habilités au Bureau SSE.</p>

  <div class="sse-ml__row" data-sse-ml-row="controles" hidden>
    <input type="checkbox" id="<?= $sseMlH($sseMlId) ?>-c" data-sse-ml-toggle="controles">
    <label for="<?= $sseMlH($sseMlId) ?>-c"><i class="sse-ml__sw sse-ml__sw--check" aria-hidden="true"></i>Contrôles d’identité</label>
    <b class="sse-ml__count" data-sse-ml-count="controles" aria-live="polite">0</b>
    <small class="sse-ml__state" data-sse-ml-state="controles" role="status" hidden></small>
  </div>
  <div class="sse-ml__row" data-sse-ml-row="lieux" hidden>
    <input type="checkbox" id="<?= $sseMlH($sseMlId) ?>-l" data-sse-ml-toggle="lieux">
    <label for="<?= $sseMlH($sseMlId) ?>-l"><i class="sse-ml__sw sse-ml__sw--place" aria-hidden="true"></i>Lieux</label>
    <b class="sse-ml__count" data-sse-ml-count="lieux" aria-live="polite">0</b>
    <small class="sse-ml__state" data-sse-ml-state="lieux" role="status" hidden></small>
  </div>
  <div class="sse-ml__row" data-sse-ml-row="pistes" hidden>
    <input type="checkbox" id="<?= $sseMlH($sseMlId) ?>-p" data-sse-ml-toggle="pistes">
    <label for="<?= $sseMlH($sseMlId) ?>-p"><i class="sse-ml__sw sse-ml__sw--track" aria-hidden="true"></i>Pistes de téléphones</label>
    <b class="sse-ml__count" data-sse-ml-count="pistes" aria-live="polite">0</b>
    <small class="sse-ml__state" data-sse-ml-state="pistes" role="status" hidden></small>
  </div>

  <div class="sse-ml__period">
    <label for="<?= $sseMlH($sseMlId) ?>-period">Période</label>
    <select id="<?= $sseMlH($sseMlId) ?>-period" data-sse-ml-period>
      <option value="24h">Dernières 24 h</option>
      <option value="7j">7 derniers jours</option>
      <option value="30j">30 derniers jours</option>
      <option value="tout">Tout</option>
    </select>
  </div>
  <div class="sse-ml__cursor">
    <label for="<?= $sseMlH($sseMlId) ?>-cursor">Curseur de temps</label>
    <input type="range" id="<?= $sseMlH($sseMlId) ?>-cursor" min="0" max="1000" step="10" value="1000" data-sse-ml-slider>
    <output class="sse-ml__cutoff" data-sse-ml-cutoff for="<?= $sseMlH($sseMlId) ?>-cursor"></output>
  </div>
  <p class="sse-ml__total" data-sse-ml-total role="status"></p>
  <p class="sse-ml__msg" data-sse-ml-msg role="status" hidden></p>

  <ul class="sse-ml__legend" aria-label="Légende des calques SSE">
    <li><i class="sse-ml__sw sse-ml__sw--fresh" aria-hidden="true"></i>Contrôle de moins de 24 h</li>
    <li><i class="sse-ml__sw sse-ml__sw--week" aria-hidden="true"></i>Contrôle de moins de 7 jours</li>
    <li><i class="sse-ml__sw sse-ml__sw--old" aria-hidden="true"></i>Contrôle plus ancien</li>
    <li><i class="sse-ml__sw sse-ml__sw--place" aria-hidden="true"></i>Lieu exploité</li>
    <li><i class="sse-ml__sw sse-ml__sw--track" aria-hidden="true"></i>Piste de téléphone, la flèche indique le sens</li>
    <li><i class="sse-ml__sw sse-ml__sw--start" aria-hidden="true"></i>Début de piste</li>
    <li><i class="sse-ml__sw sse-ml__sw--end" aria-hidden="true"></i>Fin de piste</li>
  </ul>

  <div hidden data-sse-ml-texts>
<?php foreach ($sseMlTexts as $k => $t): ?>
    <span data-k="<?= $sseMlH($k) ?>"><?= $sseMlH($t) ?></span>
<?php endforeach; ?>
  </div>
</section>
