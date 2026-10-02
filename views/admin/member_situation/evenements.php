<?php
declare(strict_types=1);

/**
 * Ma situation › Événements — résumé, onglets À venir / Calendrier / Passés.
 * L’onglet « À venir » réutilise la liste communautaire (réponses, postes, détails).
 *
 * @var list<array<string, mixed>> $events
 * @var list<array<string, mixed>> $memberEventsPast
 * @var array<string, mixed>|null $memberEventsCalendar
 * @var string $memberEventsVue
 */

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$events = is_array($events ?? null) ? $events : [];
$past = is_array($memberEventsPast ?? null) ? $memberEventsPast : [];
$cal = is_array($memberEventsCalendar ?? null) ? $memberEventsCalendar : null;
$vue = (string) ($memberEventsVue ?? 'a_venir');
$base = url('back-office/ma-situation/evenements');
$subUrl = (string) ($calendar_subscription_url ?? '');

$typeLabels = ['operation' => 'Opération', 'formation' => 'Formation', 'autre' => 'Autre', 'evenement' => 'Événement'];
$typeOf = static fn (array $ev): string => isset($typeLabels[(string) ($ev['event_type'] ?? '')]) ? (string) $ev['event_type'] : 'evenement';
$jours = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'];
$mois = ['', 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
$moisLong = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
$fmtDay = static function (int $ts) use ($jours, $mois): string {
    return $jours[(int) date('w', $ts)] . ' ' . date('j', $ts) . ' ' . $mois[(int) date('n', $ts)];
};
$tsOf = static function (array $ev): int {
    $t = strtotime((string) ($ev['starts_at'] ?? ''));

    return $t !== false ? $t : 0;
};

// Indicateurs
$upcomingCount = count($events);
$unanswered = 0;
foreach ($events as $ev) {
    if (!in_array((string) ($ev['rsvp_status'] ?? ''), ['yes', 'maybe', 'no'], true)) {
        $unanswered++;
    }
}
$attended = 0;
$engaged = 0;
foreach ($past as $ev) {
    $st = (string) ($ev['rsvp_status'] ?? '');
    if (!empty($ev['rsvp_checked_in_at'])) {
        $attended++;
    }
    if ($st === 'yes' || !empty($ev['rsvp_checked_in_at'])) {
        $engaged++;
    }
}
$pastCount = count($past);
$rate = $pastCount > 0 ? (int) round($attended / $pastCount * 100) : null;

$next = $events[0] ?? null;
$nextTs = is_array($next) ? $tsOf($next) : 0;
$countdown = '';
if ($nextTs > 0) {
    $diffDays = (int) floor((strtotime(date('Y-m-d', $nextTs)) - strtotime(date('Y-m-d'))) / 86400);
    $countdown = $diffDays <= 0 ? 'Aujourd’hui' : ($diffDays === 1 ? 'Demain' : 'Dans ' . $diffDays . ' jours');
}
$rsvpLabel = static fn (string $s): string => match ($s) {
    'yes' => 'Vous participez',
    'maybe' => 'Peut-être',
    'no' => 'Vous êtes absent',
    default => 'Réponse attendue',
};

$tabs = [
    'a_venir' => ['À venir', $upcomingCount],
    'calendrier' => ['Calendrier', null],
    'passes' => ['Passés', $pastCount],
];
?>
<div class="mev">
    <section class="mev-summary" aria-label="Résumé">
        <article class="mev-next<?= $next === null ? ' mev-next--empty' : '' ?>">
            <p class="mev-next__kicker">Prochain rendez-vous</p>
            <?php if (is_array($next)): ?>
            <h2 class="mev-next__title"><?= $h((string) ($next['title'] ?? 'Événement')) ?></h2>
            <p class="mev-next__when">
                <span class="mev-next__count"><?= $h($countdown) ?></span>
                <?= $h(ucfirst($fmtDay($nextTs)) . ' · ' . date('H\hi', $nextTs)) ?>
                <?php if (trim((string) ($next['location'] ?? '')) !== ''): ?> · <?= $h((string) $next['location']) ?><?php endif; ?>
            </p>
            <div class="mev-next__foot">
                <span class="mev-pill mev-pill--<?= $h((string) ($next['rsvp_status'] ?? 'none') ?: 'none') ?>"><?= $h($rsvpLabel((string) ($next['rsvp_status'] ?? ''))) ?></span>
                <a class="mev-next__link" href="<?= $h($base . '?vue=a_venir#event-' . (int) ($next['id'] ?? 0)) ?>">Répondre ou voir le détail <span aria-hidden="true">→</span></a>
            </div>
            <?php else: ?>
            <h2 class="mev-next__title">Rien de prévu pour l’instant</h2>
            <p class="mev-next__when">Les prochaines opérations et formations apparaîtront ici dès leur publication.</p>
            <?php endif; ?>
        </article>

        <div class="mev-stats">
            <div class="mev-stat">
                <span class="mev-stat__label">À venir</span>
                <strong class="mev-stat__value"><?= $upcomingCount ?></strong>
                <span class="mev-stat__note"><?= $unanswered > 0 ? $unanswered . ' sans réponse de votre part' : 'Vous avez répondu à tout' ?></span>
            </div>
            <div class="mev-stat">
                <span class="mev-stat__label">Assiduité</span>
                <strong class="mev-stat__value"><?= $rate === null ? '—' : $rate . ' %' ?></strong>
                <span class="mev-stat__note"><?= $pastCount > 0 ? $attended . ' présence' . ($attended > 1 ? 's' : '') . ' pointée' . ($attended > 1 ? 's' : '') . ' sur ' . $pastCount . ' événement' . ($pastCount > 1 ? 's' : '') : 'Pas encore d’historique' ?></span>
            </div>
            <?php if ($subUrl !== ''): ?>
            <div class="mev-stat mev-stat--sub">
                <span class="mev-stat__label">Dans votre agenda</span>
                <span class="mev-stat__note">Abonnez votre calendrier (Google, Outlook, téléphone) pour recevoir les événements automatiquement.</span>
                <button type="button" class="mev-copy" data-mev-copy="<?= $h($subUrl) ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>
                    <span data-mev-copy-label>Copier le lien d’abonnement</span>
                </button>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <nav class="mev-tabs" aria-label="Affichage des événements">
        <?php foreach ($tabs as $key => [$label, $n]): ?>
        <a href="<?= $h($base . '?vue=' . $key) ?>" class="mev-tab<?= $vue === $key ? ' is-active' : '' ?>"<?= $vue === $key ? ' aria-current="page"' : '' ?>>
            <?= $h($label) ?>
            <?php if ($n !== null): ?><span class="mev-tab__n"><?= (int) $n ?></span><?php endif; ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($vue === 'calendrier' && $cal !== null): ?>
    <section class="mev-cal" aria-labelledby="mev-cal-title">
        <header class="mev-cal__head">
            <h2 id="mev-cal-title" class="mev-cal__title"><?= $h(ucfirst((string) $cal['label'])) ?></h2>
            <div class="mev-cal__nav">
                <a class="mev-cal__btn" href="<?= $h($base . '?vue=calendrier&mois=' . $cal['prev']) ?>" aria-label="Mois précédent">←</a>
                <a class="mev-cal__btn mev-cal__btn--today" href="<?= $h($base . '?vue=calendrier') ?>">Aujourd’hui</a>
                <a class="mev-cal__btn" href="<?= $h($base . '?vue=calendrier&mois=' . $cal['next']) ?>" aria-label="Mois suivant">→</a>
            </div>
        </header>
        <div class="mev-cal__grid" role="grid" aria-labelledby="mev-cal-title">
            <div class="mev-cal__row mev-cal__row--dow" role="row">
                <?php foreach (['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'] as $d): ?>
                <span role="columnheader"><?= $h($d) ?></span>
                <?php endforeach; ?>
            </div>
            <?php $nowTs = time(); ?>
            <?php foreach ($cal['weeks'] as $week): ?>
            <div class="mev-cal__row" role="row">
                <?php foreach ($week as $day): ?>
                <div class="mev-cal__day<?= $day['in_month'] ? '' : ' is-out' ?><?= $day['is_today'] ? ' is-today' : '' ?><?= $day['events'] !== [] ? ' has-events' : '' ?>" role="gridcell">
                    <span class="mev-cal__num"><?= (int) $day['day'] ?></span>
                    <?php foreach ($day['events'] as $ev):
                        $evTs = $tsOf($ev);
                        $isPast = strtotime((string) ($ev['ends_at'] ?? $ev['starts_at'] ?? '')) < $nowTs;
                        $href = $isPast ? $base . '?vue=passes#past-' . (int) $ev['id'] : $base . '?vue=a_venir#event-' . (int) $ev['id'];
                        $st = (string) ($ev['rsvp_status'] ?? '');
                        ?>
                    <a class="mev-chip mev-chip--<?= $h($typeOf($ev)) ?><?= $isPast ? ' is-past' : '' ?>" href="<?= $h($href) ?>" title="<?= $h((string) ($ev['title'] ?? '') . ' — ' . $rsvpLabel($st)) ?>">
                        <span class="mev-chip__dot mev-chip__dot--<?= $h($st !== '' ? $st : 'none') ?>" aria-hidden="true"></span>
                        <span class="mev-chip__time"><?= $h(date('H\hi', $evTs)) ?></span>
                        <span class="mev-chip__title"><?= $h((string) ($ev['title'] ?? '')) ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <ul class="mev-cal__legend" aria-label="Légende">
            <li><span class="mev-chip__dot mev-chip__dot--yes"></span>Vous participez</li>
            <li><span class="mev-chip__dot mev-chip__dot--maybe"></span>Peut-être</li>
            <li><span class="mev-chip__dot mev-chip__dot--no"></span>Absent</li>
            <li><span class="mev-chip__dot mev-chip__dot--none"></span>Réponse attendue</li>
        </ul>
    </section>

    <?php elseif ($vue === 'passes'): ?>
    <section class="mev-past" aria-label="Événements passés">
        <?php if ($past === []): ?>
        <p class="mev-empty">Aucun événement passé pour l’instant. Votre historique de participation s’affichera ici.</p>
        <?php else: ?>
            <?php
            $currentMonth = '';
            foreach ($past as $ev):
                $ts = $tsOf($ev);
                $monthKey = date('Y-m', $ts);
                $st = (string) ($ev['rsvp_status'] ?? '');
                $checked = !empty($ev['rsvp_checked_in_at']);
                [$outLabel, $outTone] = $checked
                    ? ['Présent · pointé', 'ok']
                    : match ($st) {
                        'yes' => ['Inscrit, non pointé', 'warn'],
                        'maybe' => ['Peut-être, non pointé', 'muted'],
                        'no' => ['Absent (prévenu)', 'muted'],
                        default => ['Sans réponse', 'bad'],
                    };
                if ($monthKey !== $currentMonth):
                    if ($currentMonth !== ''): ?></ol><?php endif;
                    $currentMonth = $monthKey; ?>
        <h2 class="mev-past__month"><?= $h(ucfirst($moisLong[(int) date('n', $ts)]) . ' ' . date('Y', $ts)) ?></h2>
        <ol class="mev-past__list">
                <?php endif; ?>
            <li class="mev-past__item" id="past-<?= (int) ($ev['id'] ?? 0) ?>">
                <span class="mev-past__date"><b><?= date('j', $ts) ?></b><?= $h($jours[(int) date('w', $ts)]) ?></span>
                <span class="mev-past__main">
                    <span class="mev-past__type mev-past__type--<?= $h($typeOf($ev)) ?>"><?= $h($typeLabels[$typeOf($ev)]) ?></span>
                    <strong><?= $h((string) ($ev['title'] ?? '')) ?></strong>
                    <span class="mev-past__meta"><?= $h(date('H\hi', $ts)) ?><?php if (trim((string) ($ev['location'] ?? '')) !== ''): ?> · <?= $h((string) $ev['location']) ?><?php endif; ?></span>
                </span>
                <span class="mev-pill mev-pill--<?= $h($outTone) ?>"><?= $h($outLabel) ?></span>
            </li>
            <?php endforeach; ?>
        </ol>
        <?php if ($pastCount >= 50): ?>
        <p class="mev-note">Seuls les 50 derniers événements sont affichés.</p>
        <?php endif; ?>
        <?php endif; ?>
    </section>

    <?php else: ?>
    <div class="mev-list">
        <?php $eventsHideSubscription = true; require base_path('views/community/events.php'); ?>
    </div>
    <?php endif; ?>
</div>
<script>
(function () {
  document.querySelectorAll('[data-mev-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var url = btn.getAttribute('data-mev-copy') || '';
      var label = btn.querySelector('[data-mev-copy-label]');
      var done = function (ok) {
        if (!label) return;
        var prev = 'Copier le lien d’abonnement';
        label.textContent = ok ? 'Lien copié' : 'Copie impossible : ' + url;
        if (ok) setTimeout(function () { label.textContent = prev; }, 2200);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(function () { done(true); }, function () { done(false); });
      } else {
        done(false);
      }
    });
  });
})();
</script>
