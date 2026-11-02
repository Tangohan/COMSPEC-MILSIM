<?php
declare(strict_types=1);

/**
 * Back-office — cycle de mission (/back-office/atak/cycle-mission).
 * Les sept étapes d'une opération (ce que fait chacune, comment elle se déclenche, où agir), l'état de chaque étape
 * pour les parties détectées en jeu avec leur rattachement à l'opération prévue, les réglages de détection, puis le
 * cycle manuel de la carte tactique (préparation → en cours → clôturée).
 *
 * @var list<array<string,mixed>> $missionCycleList
 * @var array<string,mixed>|null $missionCycleFocus
 * @var list<array{mapId:int,label:string}> $missionCycleWorkspaces
 * @var array<string,mixed>|null $missionCyclePlayed
 * @var array<string,mixed> $missionCycleSessionSettings
 * @var array<string,mixed>|null $missionCycleDebriefSettings
 * @var bool $missionCycleCanManage
 */

use App\Services\Atak\PlayedOperationsService as Svc;
use App\Support\AtakOpReconciler;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$list = is_array($missionCycleList ?? null) ? $missionCycleList : [];
$focus = is_array($missionCycleFocus ?? null) ? $missionCycleFocus : null;
$workspaces = is_array($missionCycleWorkspaces ?? null) ? $missionCycleWorkspaces : [];
$played = is_array($missionCyclePlayed ?? null) ? $missionCyclePlayed : null;
$ss = is_array($missionCycleSessionSettings ?? null) ? $missionCycleSessionSettings : [];
$ds = is_array($missionCycleDebriefSettings ?? null) ? $missionCycleDebriefSettings : null;
$canManage = !empty($missionCycleCanManage);
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');
$csrf = (string) ($csrfToken ?? \App\Core\Csrf::token());
$ops = $played['ops'] ?? [];

// Compteurs par étape sur les parties récentes (hors supprimées).
$stepCount = array_fill_keys(Svc::STEPS, ['ok' => 0, 'todo' => 0]);
foreach ($ops as $o) {
    foreach (Svc::STEPS as $st) {
        $state = $o['steps'][$st] ?? 'none';
        if (in_array($state, ['done', 'auto', 'live'], true)) {
            $stepCount[$st]['ok']++;
        } elseif ($state === 'todo' || ($st === 'plan' && $state === 'none' && !$o['ignored']['ignored'] && $o['link_mode'] !== 'none')) {
            $stepCount[$st]['todo']++;
        }
    }
}
$n = count($ops);
$link = static fn (string $path, string $label) => '<a href="' . htmlspecialchars(url($path), ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';

$steps = [
    'plan' => [
        'title' => 'Planification',
        'what' => 'L’opération est préparée : créneau, carte, organigramme et postes, événement du calendrier où les membres s’inscrivent.',
        'how' => 'Sur le site : créez l’opération dans Planification et liez-la à son événement.',
        'signal' => 'Opération planifiée ou événement proche du lancement de la partie.',
        'links' => [['back-office/missions?onglet=planification', 'Planification'], ['back-office/events', 'Événements']],
    ],
    'brief' => [
        'title' => 'Briefing',
        'what' => 'L’ordre d’opération est publié et présenté ; la carte de briefing est partagée sur les téléphones.',
        'how' => 'Sur le site : publiez l’opération, préparez les diapositives, ouvrez un cycle de mission ci-dessous. En jeu : app Plan de l’ATAK.',
        'signal' => 'Opération publiée, ou cycle de mission ouvert.',
        'links' => [['back-office/atak/briefing-slides', 'Diapositives de briefing'], ['tacmap', 'Carte tactique']],
    ],
    'start' => [
        'title' => 'Début détecté',
        'what' => 'Au lancement de la mission, le serveur donne une clé à la partie (mission, carte, heure UTC). Athena la voit au premier relevé de temps de jeu, 5 minutes au plus, ou à la première remontée.',
        'how' => 'Automatique avec l’ATAK natif sur le serveur (réglage « Temps d’écran et temps par rôle »). Les ordres émis avant le lancement ne sont plus livrés aux téléphones.',
        'signal' => 'Clé de partie reçue par Athena.',
        'links' => [['back-office/ressources/atak-config', 'Configuration ATAK']],
    ],
    'run' => [
        'title' => 'Déroulé',
        'what' => 'Positions, ordres, repères, remontées, pertes et temps par rôle sont rangés sous la clé de la partie.',
        'how' => 'Automatique. Si l’opération planifiée est « En cours », chaque joueur qui se connecte est reconnu sur son poste prévu.',
        'signal' => 'Joueurs et remontées de la partie.',
        'links' => [['atak', 'Poste ATAK'], ['tacmap', 'Carte tactique']],
    ],
    'end' => [
        'title' => 'Fin',
        'what' => 'La partie est considérée terminée quand le serveur n’envoie plus de relevé (délai dans « Réglages de la détection ») ; il en envoie un dernier en fin de mission.',
        'how' => 'Automatique. Le cycle de mission de la carte tactique, lui, se clôture à la main ci-dessous pour figer la relecture.',
        'signal' => 'Dernier relevé reçu.',
        'links' => [],
    ],
    'debrief' => [
        'title' => 'Débriefing',
        'what' => 'Chaque joueur remet son CR de fin d’opération sur son téléphone ; le commandement rédige le compte rendu post-op.',
        'how' => 'Sur le site : « Imposer un CR » dans le suivi. En jeu : app CR fin d’op, ou Zeus si le réglage le permet. Comptes rendus : liez-les à l’opération.',
        'signal' => 'CR imposé pour la partie, ou compte rendu lié à l’opération rattachée.',
        'links' => [['back-office/atak/comptes-rendus?onglet=suivi', 'Suivi des CR'], ['back-office/atak/comptes-rendus', 'Comptes rendus post-op']],
    ],
    'effects' => [
        'title' => 'Heures et présence',
        'what' => 'Le temps de jeu, le temps par rôle et les heures de vol de la partie s’ajoutent aux fiches ; ils comptent pour les qualifications automatiques et l’avancement. La présence pointée vient des sessions de jeu.',
        'how' => 'Automatique. Une partie ignorée ou supprimée ne compte plus, sans rien effacer.',
        'signal' => 'Temps de jeu reçu et partie non ignorée.',
        'links' => [['back-office/atak/heures', 'Heures'], ['back-office/roleplay/sessions', 'Sessions de jeu'], ['back-office/referentiels/qualifications', 'Qualifications'], ['back-office/rh/avancement', 'Avancement']],
    ],
];
$statusClass = static fn (string $status): string => match ($status) {
    'en_cours' => 'is-live',
    'cloturee' => 'is-done',
    default => 'is-prep',
};
?>
<div class="bo-aar bo-mcycle" data-bo-mcycle>
  <div class="bo-mcycle__deck">
    <?php if ($flashSuccess): ?><div class="bo-mcycle__flash bo-mcycle__flash--ok" role="status"><?= $h($flashSuccess) ?></div><?php endif; ?>
    <?php if ($flashError): ?><div class="bo-mcycle__flash bo-mcycle__flash--err" role="alert"><?= $h($flashError) ?></div><?php endif; ?>

    <div class="ath-kpi-grid bo-aar-kpis">
      <article class="ath-card ath-kpi">
        <p class="ath-kpi__label">Parties détectées</p>
        <p class="ath-kpi__value"><?= $n ?></p>
        <p class="ath-kpi__note">sur les 90 derniers jours</p>
      </article>
      <article class="ath-card ath-kpi<?= ($played['unlinked'] ?? 0) > 0 ? ' is-warn' : '' ?>">
        <p class="ath-kpi__label">Sans opération prévue</p>
        <p class="ath-kpi__value"><?= (int) ($played['unlinked'] ?? 0) ?></p>
        <p class="ath-kpi__note"><a href="#bo-mcycle-parties">à rattacher ou marquer « aucune »</a></p>
      </article>
      <article class="ath-card ath-kpi">
        <p class="ath-kpi__label">Débriefing à faire</p>
        <p class="ath-kpi__value"><?= (int) $stepCount['debrief']['todo'] ?></p>
        <p class="ath-kpi__note">parties terminées sans CR ni compte rendu</p>
      </article>
      <article class="ath-card ath-kpi">
        <p class="ath-kpi__label">En cours</p>
        <p class="ath-kpi__value"><?= count(array_filter($ops, static fn (array $o): bool => $o['live'])) ?></p>
        <p class="ath-kpi__note">relevé reçu il y a moins de <?= Svc::LIVE_MINUTES ?> min</p>
      </article>
    </div>

    <section class="ath-panel bo-mcycle__flow" aria-labelledby="bo-mcycle-flow-title">
      <h2 class="bo-po__title" id="bo-mcycle-flow-title">Les étapes d’une opération</h2>
      <p class="bo-po__lead">De la planification aux heures comptées. Pour chaque étape : ce qu’elle fait, comment elle se déclenche (site, mod, Zeus), le signal qu’Athena utilise, et combien des <?= $n ?> parties récentes l’ont passée.</p>
      <ol class="bo-mcycle__timeline">
        <?php $i = 0; foreach ($steps as $code => $s): $i++; $c = $stepCount[$code]; ?>
          <li class="bo-mcycle__tstep">
            <span class="bo-mcycle__tnum" aria-hidden="true"><?= $i ?></span>
            <div class="bo-mcycle__tbody">
              <h3 class="bo-mcycle__ttitle"><?= $h($s['title']) ?>
                <?php if ($n > 0): ?><span class="ath-tag ath-tag--<?= $c['todo'] > 0 ? 'warn' : ($c['ok'] > 0 ? 'ok' : 'neut') ?>"><?= (int) $c['ok'] ?>/<?= $n ?></span><?php endif; ?>
              </h3>
              <p><?= $h($s['what']) ?></p>
              <p class="bo-mcycle__thow"><b>Déclenchement</b> · <?= $h($s['how']) ?></p>
              <p class="bo-mcycle__thow"><b>Signal</b> · <span><?= $h($s['signal']) ?></span><?php if ($c['todo'] > 0): ?> · <span class="bo-po__unlinked"><?= (int) $c['todo'] ?> à faire</span><?php endif; ?></p>
              <?php if ($s['links'] !== []): ?>
                <p class="bo-mcycle__tlinks"><?= implode(' · ', array_map(static fn (array $l): string => $link($l[0], $l[1]), $s['links'])) ?></p>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ol>
    </section>

    <?php
    $playedOps = $played;
    $playedCanManage = $canManage;
    $playedCsrf = $csrf;
    $playedBack = 'cycle';
    $playedSteps = true;
    $playedId = 'bo-mcycle-parties';
    require base_path('views/admin/partials/played_ops_manager.php');
    ?>

    <details class="ath-panel bo-mcycle__settings" id="bo-mcycle-reglages">
      <summary class="bo-po__title">Réglages de la détection</summary>
      <dl class="bo-mcycle__dl">
        <div><dt>Partie terminée après</dt><dd><span><?= Svc::LIVE_MINUTES ?> min sans relevé</span> <small>(fixe : le serveur envoie toutes les 5 min)</small></dd></div>
        <div><dt>Rapprochement automatique</dt><dd><span>Lancement à ± <?= intdiv(AtakOpReconciler::WINDOW_MINUTES, 60) ?> h de l’heure prévue (ou pendant le créneau), carte et joueurs prévus présents ; proposé à partir de <?= AtakOpReconciler::AUTO_MIN ?>/100</span> <small>(fixe ; corrigez une partie avec « Gérer »)</small></dd></div>
        <div><dt>Sessions de jeu (pointage)</dt><dd><span><?= !empty($ss['sync_enabled']) ? 'Ouvertes automatiquement par le temps de jeu du serveur' : 'Synchronisation désactivée' ?></span> · <span>fin après <?= (int) ($ss['heartbeat_timeout_minutes'] ?? 15) ?> min sans signal</span> · <span>présence validée à <?= (int) ($ss['min_minutes'] ?? 45) ?> min et <?= (int) ($ss['min_percent'] ?? 50) ?> % de la session</span> · <span>retard toléré <?= (int) ($ss['late_tolerance_minutes'] ?? 15) ?> min</span> · <a href="<?= $h(url('back-office/roleplay/sessions')) ?>">modifier</a></dd></div>
        <div><dt>CR imposé par un Zeus</dt><dd><span><?= $ds === null ? '—' : (!empty($ds['zeus_allowed']) ? 'Autorisé' : 'Non autorisé') ?></span> · <a href="<?= $h(url('back-office/atak/comptes-rendus') . '?onglet=suivi&ouvrir=reglages#dbf-reglages') ?>">modifier</a> <small>(sur le serveur : réglage « Zeus peut imposer le CR de fin d’opération »)</small></dd></div>
        <div><dt>Temps de jeu et par rôle</dt><dd><span>Réglage du serveur « Temps d’écran et temps par rôle » (ATAK natif, activé par défaut)</span> <small>Sans lui, aucune partie n’est détectée par le temps de jeu.</small></dd></div>
      </dl>
    </details>

    <section class="ath-panel bo-mcycle__manual" aria-labelledby="bo-mcycle-manual-title">
      <h2 class="bo-po__title" id="bo-mcycle-manual-title">Cycle de la carte tactique</h2>
      <p class="bo-po__lead">À part de la détection : ouvrez une mission pour l’exécution sur la carte tactique, puis clôturez-la pour figer la relecture et le bilan après-action sur cette fenêtre. Une partie lancée pendant la fenêtre est rattachée à ce cycle.</p>

      <?php if ($focus): ?>
      <div class="bo-mcycle__panel" aria-labelledby="bo-mcycle-focus-title">
        <div class="bo-mcycle__panel-head">
          <div>
            <p class="bo-mcycle__panel-eyebrow">Mission sélectionnée</p>
            <h3 id="bo-mcycle-focus-title" class="bo-mcycle__panel-title"><?= $h($focus['title']) ?></h3>
          </div>
          <span class="bo-mcycle__status <?= $h($statusClass((string) ($focus['status'] ?? ''))) ?>"><?= $h($focus['status_label'] ?? '') ?></span>
        </div>
        <dl class="bo-mcycle__meta">
          <div><dt>Carte</dt><dd><?php
            $mapLabel = 'Carte ' . (int) ($focus['map_id'] ?? 1);
            foreach ($workspaces as $w) {
                if ((int) ($w['mapId'] ?? 0) === (int) ($focus['map_id'] ?? 0)) {
                    $mapLabel = (string) ($w['label'] ?? $mapLabel);
                    break;
                }
            }
            echo $h($mapLabel);
          ?></dd></div>
          <div><dt>Début</dt><dd><?= $h($focus['started_at'] ?? '—') ?></dd></div>
          <div><dt>Fin</dt><dd><?= $h($focus['ended_at'] ?? '—') ?></dd></div>
        </dl>
        <div class="bo-mcycle__actions">
          <?php if (($focus['status'] ?? '') === 'preparation'): ?>
            <form method="post" action="<?= $h(url('back-office/atak/cycle-mission/' . (int) $focus['id'] . '/ouvrir')) ?>">
              <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
              <button type="submit" class="ath-btn ath-btn--solid">Ouvrir la mission</button>
            </form>
          <?php elseif (($focus['status'] ?? '') === 'en_cours'): ?>
            <form method="post" action="<?= $h(url('back-office/atak/cycle-mission/' . (int) $focus['id'] . '/cloturer')) ?>" class="bo-mcycle__close-form">
              <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
              <label class="bo-mcycle__field">
                <span>Note de clôture (facultatif)</span>
                <textarea name="aar_summary" rows="3" maxlength="4000" placeholder="Points saillants pour le bilan après-action…"></textarea>
              </label>
              <button type="submit" class="ath-btn bo-aar-danger">Clôturer la mission</button>
            </form>
          <?php else: ?>
            <div class="bo-mcycle__aar-links">
              <?php if (!empty($focus['links']['replay'])): ?><a class="ath-btn ath-btn--solid" href="<?= $h($focus['links']['replay']) ?>">Relecture sur le poste ATAK</a><?php endif; ?>
              <?php if (!empty($focus['links']['aar'])): ?><a class="ath-btn" href="<?= $h($focus['links']['aar']) ?>" target="_blank" rel="noopener">Bilan après-action</a><?php endif; ?>
              <a class="ath-btn" href="<?= $h(url('back-office/atak/comptes-rendus/nouveau') . '?mission=' . (int) $focus['id']) ?>">Rédiger le compte rendu</a>
              <?php if (!empty($focus['aar_summary'])): ?><p class="bo-mcycle__note"><?= nl2br($h($focus['aar_summary'])) ?></p><?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <div class="bo-mcycle__split">
        <div class="bo-mcycle__panel" id="bo-mcycle-create">
          <h3 class="bo-mcycle__panel-title">Créer une mission</h3>
          <p class="bo-mcycle__panel-lead">La mission démarre en préparation. Ouvrez-la quand le briefing est prêt.</p>
          <form method="post" action="<?= $h(url('back-office/atak/cycle-mission')) ?>" class="bo-mcycle__create-form">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
            <label class="bo-mcycle__field"><span>Titre de la mission</span><input type="text" name="title" maxlength="200" required placeholder="Ex. Opération Aube — secteur nord"></label>
            <label class="bo-mcycle__field"><span>Carte / serveur</span>
              <select name="map_id" required>
                <?php foreach ($workspaces as $w): ?><option value="<?= (int) ($w['mapId'] ?? 1) ?>"><?= $h($w['label'] ?? 'Carte') ?></option><?php endforeach; ?>
              </select>
            </label>
            <button type="submit" class="ath-btn ath-btn--solid">Créer</button>
          </form>
        </div>
        <div class="bo-mcycle__panel">
          <h3 class="bo-mcycle__panel-title">Missions récentes</h3>
          <?php if ($list === []): ?>
            <p class="bo-mcycle__empty">Aucune mission pour le moment.</p>
          <?php else: ?>
            <ul class="bo-mcycle__list">
              <?php foreach ($list as $m): ?>
                <li class="bo-mcycle__list-item <?= ($focus && (int) $focus['id'] === (int) $m['id']) ? 'is-selected' : '' ?>">
                  <a href="<?= $h(url('back-office/atak/cycle-mission') . '?mission=' . (int) $m['id']) ?>">
                    <span class="bo-mcycle__list-title"><?= $h($m['title']) ?></span>
                    <span class="bo-mcycle__status <?= $h($statusClass((string) ($m['status'] ?? ''))) ?>"><?= $h($m['status_label'] ?? '') ?></span>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>
    </section>
  </div>
</div>
