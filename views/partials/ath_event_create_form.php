<?php
declare(strict_types=1);

/**
 * Formulaire « Planifier un créneau » de l’agenda back-office, avec répétition.
 *
 * @var string $evFormVue    vue de retour (calendrier, a_venir…)
 * @var string $evFormSuffix suffixe d’identifiants (plusieurs formulaires possibles)
 */

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$sfx = preg_replace('/[^a-z0-9_-]/i', '', (string) ($evFormSuffix ?? 'ath')) ?: 'ath';
$vue = (string) ($evFormVue ?? 'calendrier');
$id = static fn (string $name): string => 'ev-' . $name . '-' . $sfx;
$weekdays = [1 => ['L', 'Lundi'], 2 => ['M', 'Mardi'], 3 => ['M', 'Mercredi'], 4 => ['J', 'Jeudi'], 5 => ['V', 'Vendredi'], 6 => ['S', 'Samedi'], 7 => ['D', 'Dimanche']];
?>
<form method="post" action="<?= $h(url('back-office/events')) ?>" enctype="multipart/form-data" class="ath-card ath-rise ev-create" id="nouveau" data-ev-create>
    <input type="hidden" name="_csrf_token" value="<?= $h(\App\Core\Csrf::token()) ?>">
    <input type="hidden" name="return_vue" value="<?= $h($vue) ?>">

    <header class="ev-create__head">
        <div>
            <p class="ev-create__kicker">Nouveau créneau</p>
            <h2 class="ev-create__title">Planifier un événement</h2>
        </div>
        <p class="ev-create__lead">Les membres sont prévenus et peuvent répondre dès la publication. Postes, conditions et visuel s’ajoutent ensuite depuis la fiche.</p>
    </header>

    <div class="ev-create__grid">
        <div class="ev-create__field ev-create__field--wide">
            <label for="<?= $h($id('title')) ?>">Titre</label>
            <input id="<?= $h($id('title')) ?>" type="text" name="title" required maxlength="190" placeholder="Ex. Entraînement section, opération Forêt Noire…">
        </div>
        <div class="ev-create__field">
            <label for="<?= $h($id('type')) ?>">Type</label>
            <select id="<?= $h($id('type')) ?>" name="event_type">
                <option value="operation">Opération</option>
                <option value="evenement" selected>Événement</option>
                <option value="formation">Formation</option>
                <option value="autre">Autre</option>
            </select>
        </div>
        <div class="ev-create__field">
            <label for="<?= $h($id('start')) ?>">Début</label>
            <input id="<?= $h($id('start')) ?>" type="datetime-local" name="starts_at" required step="60" data-ev-start>
        </div>
        <div class="ev-create__field">
            <label for="<?= $h($id('end')) ?>">Fin <span class="ev-create__opt">facultatif · +2 h par défaut</span></label>
            <input id="<?= $h($id('end')) ?>" type="datetime-local" name="ends_at" step="60" class="ath-event-datetime-end" data-start-for="<?= $h($id('start')) ?>">
        </div>
        <div class="ev-create__field ev-create__field--half">
            <label for="<?= $h($id('loc')) ?>">Lieu</label>
            <input id="<?= $h($id('loc')) ?>" type="text" name="location" maxlength="190" placeholder="Serveur, carte, TeamSpeak…">
        </div>
        <div class="ev-create__field ev-create__field--wide">
            <label for="<?= $h($id('desc')) ?>">Description <span class="ev-create__opt">facultatif</span></label>
            <textarea id="<?= $h($id('desc')) ?>" name="description" rows="2" placeholder="Objectif, tenue, prérequis…"></textarea>
        </div>
    </div>

    <fieldset class="ev-create__repeat" data-ev-repeat>
        <legend>Répétition</legend>
        <div class="ev-create__seg" role="radiogroup" aria-label="Fréquence">
            <?php foreach (['' => 'Une seule fois', 'weekly' => 'Chaque semaine', 'biweekly' => 'Une semaine sur deux', 'monthly' => 'Chaque mois'] as $val => $lab): ?>
            <label class="ev-create__seg-opt">
                <input type="radio" name="repeat" value="<?= $h($val) ?>" <?= $val === '' ? 'checked' : '' ?> data-ev-freq>
                <span><?= $h($lab) ?></span>
            </label>
            <?php endforeach; ?>
        </div>

        <div class="ev-create__repeat-body" data-ev-repeat-body hidden>
            <div class="ev-create__days" data-ev-days>
                <span class="ev-create__sub">Jours</span>
                <div class="ev-create__day-list">
                    <?php foreach ($weekdays as $n => [$short, $long]): ?>
                    <label class="ev-create__day" title="<?= $h($long) ?>">
                        <input type="checkbox" name="repeat_days[]" value="<?= $n ?>" data-ev-day>
                        <span aria-hidden="true"><?= $h($short) ?></span>
                        <span class="sr-only"><?= $h($long) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="ev-create__ends">
                <span class="ev-create__sub">Fin de la série</span>
                <label class="ev-create__end-opt">
                    <input type="radio" name="repeat_end" value="count" checked data-ev-endmode>
                    Après
                    <input type="number" name="repeat_count" value="8" min="2" max="<?= \App\Support\EventRecurrence::MAX_OCCURRENCES ?>" class="ev-create__num" data-ev-count aria-label="Nombre d’occurrences">
                    occurrences
                </label>
                <label class="ev-create__end-opt">
                    <input type="radio" name="repeat_end" value="until" data-ev-endmode>
                    Jusqu’au
                    <input type="date" name="repeat_until" class="ev-create__date" data-ev-until aria-label="Date de fin de la série">
                </label>
            </div>
            <p class="ev-create__preview" data-ev-preview aria-live="polite"></p>
        </div>
    </fieldset>

    <div class="ev-create__foot">
        <label class="ev-create__check">
            <input type="checkbox" name="show_on_public_page" value="1">
            Afficher aussi sur la page publique de la communauté
        </label>
        <button type="submit" class="ath-btn ath-btn--solid" data-ev-submit>Publier le créneau</button>
    </div>
</form>
<?php if (empty($GLOBALS['__evCreateScript'])): $GLOBALS['__evCreateScript'] = true; ?>
<script>
/* Répétition : aperçu des dates générées (même logique que App\Support\EventRecurrence). */
(function () {
  var MAX = <?= \App\Support\EventRecurrence::MAX_OCCURRENCES ?>;
  var DAYS = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'];
  var MONTHS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
  function fmt(d) { return DAYS[d.getDay()] + ' ' + d.getDate() + ' ' + MONTHS[d.getMonth()]; }
  function iso(d) { return d.getFullYear() * 10000 + (d.getMonth() + 1) * 100 + d.getDate(); }

  function expand(start, freq, days, count, until) {
    var out = [start];
    var limit = count ? Math.min(count, MAX) : (until ? MAX : 12);
    var untilKey = until ? iso(until) : null;
    if (freq === 'monthly') {
      var y = start.getFullYear(), m = start.getMonth(), day = start.getDate();
      for (var g = 0; g < 240 && out.length < limit; g++) {
        m++; if (m > 11) { m = 0; y++; }
        var d = new Date(y, m, day, 12);
        if (d.getMonth() !== m) continue;
        if (untilKey && iso(d) > untilKey) break;
        out.push(d);
      }
      return out;
    }
    var step = freq === 'biweekly' ? 2 : 1;
    var isoDow = start.getDay() || 7;
    if (!days.length) days = [isoDow];
    var monday = new Date(start.getFullYear(), start.getMonth(), start.getDate() - (isoDow - 1), 12);
    var firstKey = iso(start);
    for (var w = 0; w < 600 && out.length < limit; w += step) {
      for (var i = 0; i < days.length; i++) {
        var dd = new Date(monday.getFullYear(), monday.getMonth(), monday.getDate() + w * 7 + days[i] - 1, 12);
        if (iso(dd) <= firstKey) continue;
        if (untilKey && iso(dd) > untilKey) return out;
        out.push(dd);
        if (out.length >= limit) return out;
      }
    }
    return out;
  }

  document.querySelectorAll('[data-ev-create]').forEach(function (form) {
    var startEl = form.querySelector('[data-ev-start]');
    var body = form.querySelector('[data-ev-repeat-body]');
    var daysWrap = form.querySelector('[data-ev-days]');
    var preview = form.querySelector('[data-ev-preview]');
    var submit = form.querySelector('[data-ev-submit]');
    var countEl = form.querySelector('[data-ev-count]');
    var untilEl = form.querySelector('[data-ev-until]');
    var touchedDays = false;

    function freq() { var r = form.querySelector('[data-ev-freq]:checked'); return r ? r.value : ''; }
    function endMode() { var r = form.querySelector('[data-ev-endmode]:checked'); return r ? r.value : 'count'; }

    function update() {
      var f = freq();
      body.hidden = f === '';
      daysWrap.hidden = f === 'monthly';
      var start = startEl.value ? new Date(startEl.value) : null;
      if (start && !touchedDays && f !== '' && f !== 'monthly') {
        var dow = String(start.getDay() || 7);
        form.querySelectorAll('[data-ev-day]').forEach(function (c) { c.checked = c.value === dow; });
      }
      if (f === '') { preview.textContent = ''; submit.textContent = 'Publier le créneau'; return; }
      if (!start || isNaN(start.getTime())) { preview.textContent = 'Choisissez d’abord la date de début pour voir les dates générées.'; return; }
      var days = [];
      form.querySelectorAll('[data-ev-day]:checked').forEach(function (c) { days.push(parseInt(c.value, 10)); });
      days.sort();
      var mode = endMode();
      var count = mode === 'count' ? Math.max(1, parseInt(countEl.value, 10) || 1) : null;
      var until = mode === 'until' && untilEl.value ? new Date(untilEl.value + 'T23:59:59') : null;
      var list = expand(start, f, days, count, until);
      var shown = list.slice(0, 6).map(fmt).join(' · ');
      preview.innerHTML = '';
      var strong = document.createElement('strong');
      strong.textContent = list.length + (list.length > 1 ? ' créneaux' : ' créneau');
      preview.appendChild(strong);
      preview.appendChild(document.createTextNode((' : ' + shown + (list.length > 6 ? ' … dernier le ' + fmt(list[list.length - 1]) : '')).replace(/\.?$/, '.')));
      submit.textContent = list.length > 1 ? 'Publier les ' + list.length + ' créneaux' : 'Publier le créneau';
    }

    form.addEventListener('change', function (e) {
      if (e.target.matches('[data-ev-day]')) touchedDays = true;
      if (e.target === untilEl && untilEl.value) {
        var r = form.querySelector('[data-ev-endmode][value="until"]'); if (r) r.checked = true;
      }
      update();
    });
    form.addEventListener('input', function (e) {
      if (e.target === countEl) { var r = form.querySelector('[data-ev-endmode][value="count"]'); if (r) r.checked = true; }
      if (e.target === countEl || e.target === startEl) update();
    });
    update();
  });
})();
</script>
<?php endif; ?>
