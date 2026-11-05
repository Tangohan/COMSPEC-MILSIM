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
<div id="nouveau" class="ev-create-launch">
    <button type="button" class="ath-btn ath-btn--solid" data-ev-open><?= $h(__('events.plan')) ?></button>
    <span><?= $h(__('events.calendar_hint')) ?></span>
</div>
<dialog class="ev-create-dialog" aria-labelledby="<?= $h($id('heading')) ?>" data-ev-dialog>
<button type="button" class="ath-btn ev-create-close" data-ev-close aria-label="<?= $h(__('events.close')) ?>"><?= $h(__('events.close')) ?> ×</button>
<form method="post" action="<?= $h(url('back-office/events')) ?>" enctype="multipart/form-data" class="ath-card ath-rise ev-create" data-ev-create>
    <input type="hidden" name="_csrf_token" value="<?= $h(\App\Core\Csrf::token()) ?>">
    <input type="hidden" name="return_vue" value="<?= $h($vue) ?>">

    <header class="ev-create__head">
        <div>
            <p class="ev-create__kicker"><?= $h(__('events.new')) ?></p>
            <h2 id="<?= $h($id('heading')) ?>" class="ev-create__title"><?= $h(__('events.plan')) ?></h2>
        </div>
        <p class="ev-create__lead"><?= $h(__('events.lead')) ?></p>
    </header>

    <div class="ev-create__grid">
        <div class="ev-create__field ev-create__field--wide">
            <label for="<?= $h($id('title')) ?>"><?= $h(__('events.title')) ?></label>
            <input id="<?= $h($id('title')) ?>" type="text" name="title" required maxlength="190" placeholder="<?= $h(__('events.title_placeholder')) ?>">
        </div>
        <div class="ev-create__field">
            <label for="<?= $h($id('type')) ?>"><?= $h(__('events.type')) ?></label>
            <select id="<?= $h($id('type')) ?>" name="event_type">
                <option value="operation"><?= $h(__('events.operation')) ?></option>
                <option value="evenement" selected><?= $h(__('events.event')) ?></option>
                <option value="formation"><?= $h(__('events.training')) ?></option>
                <option value="autre"><?= $h(__('events.other')) ?></option>
            </select>
        </div>
        <div class="ev-create__field">
            <label for="<?= $h($id('start')) ?>"><?= $h(__('events.start')) ?></label>
            <input id="<?= $h($id('start')) ?>" type="datetime-local" name="starts_at" required step="60" data-ev-start>
        </div>
        <div class="ev-create__field">
            <label for="<?= $h($id('end')) ?>"><?= $h(__('events.end')) ?> <span class="ev-create__opt"><?= $h(__('events.end_hint')) ?></span></label>
            <input id="<?= $h($id('end')) ?>" type="datetime-local" name="ends_at" step="60" class="ath-event-datetime-end" data-start-for="<?= $h($id('start')) ?>">
        </div>
        <div class="ev-create__field ev-create__field--half">
            <label for="<?= $h($id('loc')) ?>"><?= $h(__('events.location')) ?></label>
            <input id="<?= $h($id('loc')) ?>" type="text" name="location" maxlength="190" placeholder="<?= $h(__('events.location_placeholder')) ?>">
        </div>
        <div class="ev-create__field ev-create__field--wide">
            <label for="<?= $h($id('desc')) ?>"><?= $h(__('events.description')) ?> <span class="ev-create__opt"><?= $h(__('events.optional')) ?></span></label>
            <textarea id="<?= $h($id('desc')) ?>" name="description" rows="2" placeholder="<?= $h(__('events.description_placeholder')) ?>"></textarea>
        </div>
    </div>

    <fieldset class="ev-create__repeat" data-ev-repeat>
        <legend><?= $h(__('events.repeat')) ?></legend>
        <div class="ev-create__seg" role="radiogroup" aria-label="<?= $h(__('events.frequency')) ?>">
            <?php foreach (['' => __('events.once'), 'weekly' => __('events.weekly'), 'biweekly' => __('events.biweekly'), 'monthly' => __('events.monthly')] as $val => $lab): ?>
            <label class="ev-create__seg-opt">
                <input type="radio" name="repeat" value="<?= $h($val) ?>" <?= $val === '' ? 'checked' : '' ?> data-ev-freq>
                <span><?= $h($lab) ?></span>
            </label>
            <?php endforeach; ?>
        </div>

        <div class="ev-create__repeat-body" data-ev-repeat-body hidden>
            <div class="ev-create__days" data-ev-days>
                <span class="ev-create__sub"><?= $h(__('events.days')) ?></span>
                <div class="ev-create__day-list">
                    <?php foreach ($weekdays as $n => [$short, $long]): ?>
                    <label class="ev-create__day" title="<?= $h(\App\Support\LocalizedCalendar::weekday($n)) ?>">
                        <input type="checkbox" name="repeat_days[]" value="<?= $n ?>" data-ev-day>
                        <span aria-hidden="true"><?= $h(mb_substr(\App\Support\LocalizedCalendar::weekday($n), 0, 1)) ?></span>
                        <span class="sr-only"><?= $h(\App\Support\LocalizedCalendar::weekday($n)) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="ev-create__ends">
                <span class="ev-create__sub"><?= $h(__('events.series_end')) ?></span>
                <label class="ev-create__end-opt">
                    <input type="radio" name="repeat_end" value="count" checked data-ev-endmode>
                    <?= $h(__('events.after')) ?>
                    <input type="number" name="repeat_count" value="8" min="2" max="<?= \App\Support\EventRecurrence::MAX_OCCURRENCES ?>" class="ev-create__num" data-ev-count aria-label="<?= $h(__('events.count_label')) ?>">
                    <?= $h(__('events.occurrences')) ?>
                </label>
                <label class="ev-create__end-opt">
                    <input type="radio" name="repeat_end" value="until" data-ev-endmode>
                    <?= $h(__('events.until')) ?>
                    <input type="date" name="repeat_until" class="ev-create__date" data-ev-until aria-label="<?= $h(__('events.until_label')) ?>">
                </label>
            </div>
            <label class="ev-create__end-opt"><input type="radio" name="repeat_end" value="forever" data-ev-endmode> <?= $h(__('events.forever')) ?></label>
            <p class="ev-create__lead" data-ev-forever-hint hidden><?= $h(__('events.forever_hint')) ?></p>
            <p class="ev-create__preview" data-ev-preview aria-live="polite"></p>
        </div>
    </fieldset>

    <?php require base_path('views/partials/ath_event_discord_options.php'); ?>
    <div class="ev-create__foot">
        <label class="ev-create__check">
            <input type="checkbox" name="show_on_public_page" value="1">
            <?= $h(__('events.public')) ?>
        </label>
        <button type="submit" class="ath-btn ath-btn--solid" data-ev-submit><?= $h(__('events.publish')) ?></button>
    </div>
</form>
</dialog>
<?php if (empty($GLOBALS['__evCreateScript'])): $GLOBALS['__evCreateScript'] = true; ?>
<script>
/* Répétition : aperçu des dates générées (même logique que App\Support\EventRecurrence). */
(function () {
  var TEXT = <?= json_encode(array_combine(['publish', 'publish_many', 'publish_forever', 'publishing', 'choose_start', 'preview_count', 'preview_last', 'preview_forever', 'invalid_end', 'invalid_until', 'add_on'], array_map(static fn (string $key): string => __('events.' . $key), ['publish', 'publish_many', 'publish_forever', 'publishing', 'choose_start', 'preview_count', 'preview_last', 'preview_forever', 'invalid_end', 'invalid_until', 'add_on'])), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
  function text(key, values) { var value = TEXT[key]; Object.keys(values || {}).forEach(function (k) { value = value.replace(':' + k, values[k]); }); return value; }
  var MAX = <?= \App\Support\EventRecurrence::MAX_OCCURRENCES ?>;
  var dateFormatter = new Intl.DateTimeFormat(<?= json_encode(locale() === 'fr' ? 'fr-FR' : 'en-GB') ?>, { weekday: 'short', day: 'numeric', month: 'short' });
  function fmt(d) { return dateFormatter.format(d); }
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
    var endEl = form.querySelector('[name="ends_at"]');
    var previousStart = startEl.value;
    function syncSchedule() {
      endEl.setCustomValidity('');
      if (startEl.value) {
        var next = new Date(startEl.value);
        var previous = new Date(previousStart);
        var end = new Date(endEl.value);
        var duration = end - previous;
        if (!endEl.value || (previousStart && duration > 0)) {
          endEl.value = localValue(new Date(next.getTime() + (duration > 0 ? duration : 7200000)));
        }
        endEl.min = startEl.value;
        untilEl.min = startEl.value.slice(0, 10);
      }
      previousStart = startEl.value;
      if (endEl.value && startEl.value && endEl.value <= startEl.value) {
        endEl.setCustomValidity(text('invalid_end'));
      }
    }
    startEl.addEventListener('change', syncSchedule);
    endEl.addEventListener('input', function () {
      endEl.setCustomValidity(endEl.value && endEl.value <= startEl.value ? text('invalid_end') : '');
    });
    var dialog = form.closest('[data-ev-dialog]');
    var opener = dialog.previousElementSibling.querySelector('[data-ev-open]');
    function localValue(d) {
      function pad(n) { return String(n).padStart(2, '0'); }
      return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }
    function openForm(day) {
      if (day || !startEl.value) {
        var date = day ? new Date(day + 'T21:00:00') : new Date();
        if (!day) { date.setHours(21, 0, 0, 0); }
        previousStart = '';
        startEl.value = localValue(date);
        date.setHours(date.getHours() + 2);
        form.querySelector('[name="ends_at"]').value = localValue(date);
        startEl.dispatchEvent(new Event('change', { bubbles: true }));
      }
      dialog.showModal();
      form.querySelector('[name="title"]').focus();
    }
    opener.addEventListener('click', function () { openForm(); });
    dialog.querySelector('[data-ev-close]').addEventListener('click', function () { dialog.close(); });
    document.querySelectorAll('.ath-cal__day[data-ymd]').forEach(function (cell) {
      cell.addEventListener('contextmenu', function (event) {
        if (event.target.closest('a')) return;
        event.preventDefault();
        openForm(cell.dataset.ymd);
      });
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'ath-cal__add';
      button.textContent = '+';
      button.setAttribute('aria-label', text('add_on', { date: cell.dataset.ymd }));
      button.addEventListener('click', function () { openForm(cell.dataset.ymd); });
      cell.querySelector('.ath-cal__daynum').appendChild(button);
    });
    function openFromHash() { if (window.location.hash === '#nouveau' && !dialog.open) openForm(); }
    window.addEventListener('hashchange', openFromHash);
    openFromHash();


    function freq() { var r = form.querySelector('[data-ev-freq]:checked'); return r ? r.value : ''; }
    function endMode() { var r = form.querySelector('[data-ev-endmode]:checked'); return r ? r.value : 'count'; }

    function update() {
      var f = freq();
      body.hidden = f === '';
      var mode = endMode();
      countEl.disabled = f === '' || mode !== 'count';
      countEl.required = f !== '' && mode === 'count';
      untilEl.disabled = f === '' || mode !== 'until';
      untilEl.required = f !== '' && mode === 'until';
      untilEl.setCustomValidity(untilEl.value && startEl.value && untilEl.value < startEl.value.slice(0, 10) && f !== '' && mode === 'until' ? text('invalid_until') : '');
      daysWrap.hidden = f === 'monthly';
      var start = startEl.value ? new Date(startEl.value) : null;
      if (start && !touchedDays && f !== '' && f !== 'monthly') {
        var dow = String(start.getDay() || 7);
        form.querySelectorAll('[data-ev-day]').forEach(function (c) { c.checked = c.value === dow; });
      }
      if (f === '') { preview.textContent = ''; submit.textContent = text('publish'); return; }
      if (!start || isNaN(start.getTime())) { preview.textContent = text('choose_start'); return; }
      var days = [];
      form.querySelectorAll('[data-ev-day]:checked').forEach(function (c) { days.push(parseInt(c.value, 10)); });
      days.sort();
      form.querySelector('[data-ev-forever-hint]').hidden = mode !== 'forever';
      var count = mode === 'forever' ? 12 : mode === 'count' ? Math.max(1, parseInt(countEl.value, 10) || 1) : null;
      var until = mode === 'until' && untilEl.value ? new Date(untilEl.value + 'T23:59:59') : null;
      var list = expand(start, f, days, count, until);
      var shown = list.slice(0, 6).map(fmt).join(' · ');
      shown += ' · ' + startEl.value.slice(11, 16);
      preview.innerHTML = '';
      var strong = document.createElement('strong');
      strong.textContent = mode === 'forever' ? text('preview_forever') : text('preview_count', { count: list.length });
      preview.appendChild(strong);
      preview.appendChild(document.createTextNode(((mode === 'forever' ? ' ' : ' : ') + shown + (list.length > 6 ? ' … ' + text('preview_last', { date: fmt(list[list.length - 1]) }) : '')).replace(/\.?$/, '.')));
      submit.textContent = mode === 'forever' ? text('publish_forever') : list.length > 1 ? text('publish_many', { count: list.length }) : text('publish');
    }

    form.addEventListener('change', function (e) {
      if (e.target.name === 'discord_rsvp' && e.target.checked) form.querySelector('[name="discord_publish"]').checked = true;
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
    form.addEventListener('submit', function () {
      submit.disabled = true;
      submit.textContent = text('publishing');
    });
    window.addEventListener('pageshow', function () { submit.disabled = false; update(); });
    syncSchedule();
    update();
  });
})();
</script>
<?php endif; ?>
