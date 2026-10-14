<?php
declare(strict_types=1);

$boardPosts = is_array($boardPosts ?? null) ? $boardPosts : [];
$boardMyPosts = is_array($boardMyPosts ?? null) ? $boardMyPosts : [];
$boardMyResponses = is_array($boardMyResponses ?? null) ? $boardMyResponses : [];
$boardTenantId = (int) ($boardTenantId ?? 0);
$boardScope = (string) ($boardScope ?? 'open');
$boardCanPublish = !empty($boardCanPublish);
$boardCanRespond = !empty($boardCanRespond);
$boardReady = !empty($boardReady);
$needTypes = is_array($boardNeedTypes ?? null) ? $boardNeedTypes : [];
$csrf = (string) ($csrfToken ?? \App\Core\Csrf::token());
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');
$responseLabels = ['pending' => 'En attente de réponse', 'accepted' => 'Acceptée', 'declined' => 'Déclinée'];

/** Identité visuelle de chaque type de besoin : icône (tracé SVG 24×24) et teinte. */
$typeStyle = [
    'renfort' => ['M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM3 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 019.374 21c-2.331 0-4.512-.645-6.374-1.766z', 'emerald'],
    'adversaire' => ['M12 3v3m0 12v3m9-9h-3M6 12H3m15.364-6.364l-2.121 2.121M7.757 16.243l-2.121 2.121m12.728 0l-2.121-2.121M7.757 7.757L5.636 5.636M15 12a3 3 0 11-6 0 3 3 0 016 0z', 'rose'],
    'aviation' => ['M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5', 'sky'],
    'appui' => ['M15.362 5.214A8.252 8.252 0 0112 21 8.25 8.25 0 016.038 7.048 8.287 8.287 0 009 9.6a8.983 8.983 0 013.361-6.867 8.21 8.21 0 003 2.48z', 'amber'],
    'entrainement' => ['M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342', 'violet'],
    'operation' => ['M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z', 'teal'],
    'evenement' => ['M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5', 'indigo'],
    'autre' => ['M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z', 'slate'],
];
$icon = static fn (string $type): string => $typeStyle[$type][0] ?? $typeStyle['autre'][0];
$tint = static fn (string $type): string => $typeStyle[$type][1] ?? 'slate';

/** Échéance lisible : « Ce soir 21:00 », « Demain 20:30 », « Dans 4 jours », « Date libre ». */
$deadline = static function (?string $at): array {
    if ($at === null || $at === '') {
        return ['Date libre', 'free', null];
    }
    $t = strtotime($at);
    if ($t === false) {
        return ['Date libre', 'free', null];
    }
    $today = strtotime('today');
    $hour = date('H:i', $t);
    if ($t < time()) {
        return ['En cours', 'now', $t];
    }
    if ($t < $today + 86400) {
        return [((int) date('G', $t) >= 18 ? 'Ce soir ' : 'Aujourd’hui ') . $hour, 'hot', $t];
    }
    if ($t < $today + 2 * 86400) {
        return ['Demain ' . $hour, 'soon', $t];
    }
    $days = (int) floor(($t - $today) / 86400);
    if ($days < 7) {
        $weekday = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'][(int) date('w', $t)];

        return [ucfirst($weekday) . ' ' . $hour, 'week', $t];
    }

    return [date('d/m', $t) . ' · ' . $hour, 'later', $t];
};
$initials = static function (string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $out .= mb_strtoupper(mb_substr($p, 0, 1));
    }

    return $out !== '' ? $out : '?';
};

$others = array_values(array_filter($boardPosts, static fn (array $p): bool => (int) $p['tenant_id'] !== $boardTenantId));
$openOthers = array_values(array_filter($others, static fn (array $p): bool => (string) $p['status'] === 'open'));
$statOpen = 0;
$statSlots = 0;
$statProposals = 0;
$statUnits = [];
foreach ($boardPosts as $p) {
    if ((string) $p['status'] !== 'open') {
        continue;
    }
    $statOpen++;
    $statSlots += (int) ($p['slots'] ?? 0);
    $statProposals += (int) ($p['responses_count'] ?? 0);
    $statUnits[(int) $p['tenant_id']] = true;
}
$typeCounts = [];
foreach ($others as $p) {
    $typeCounts[(string) $p['need_type']] = ($typeCounts[(string) $p['need_type']] ?? 0) + 1;
}
$pendingOnMine = 0;
foreach ($boardMyPosts as $mp) {
    foreach ($mp['responses'] as $r) {
        if ($r['status'] === 'pending') {
            $pendingOnMine++;
        }
    }
}
?>
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/hub-board.css')) ?>">

<div class="bx" data-bx>
  <!-- ——— Bandeau ——— -->
  <section class="bx-hero" aria-labelledby="bx-title">
    <div class="bx-hero__grid" aria-hidden="true"></div>
    <div class="bx-radar" aria-hidden="true"><span class="bx-radar__sweep"></span><span class="bx-radar__blip" style="--x:62%;--y:30%"></span><span class="bx-radar__blip" style="--x:28%;--y:58%;--d:1.4s"></span><span class="bx-radar__blip" style="--x:70%;--y:72%;--d:2.6s"></span></div>
    <div class="bx-wrap bx-hero__inner">
      <a href="<?= $h(url('hub')) ?>" class="bx-back">← Hub des communautés</a>
      <p class="bx-eyebrow"><span class="bx-live" aria-hidden="true"></span> Bourse aux coopérations · en direct</p>
      <h1 id="bx-title" class="bx-hero__title">Il vous manque du monde <span>samedi soir&nbsp;?</span></h1>
      <p class="bx-hero__lead">Renforts, force adverse, appui aérien, entraînement commun : publiez votre besoin en une minute, les autres unités Athena vous répondent, et l’opération se monte ensemble.</p>
      <div class="bx-hero__cta">
        <?php if ($boardCanPublish && $boardReady): ?>
        <a class="bx-btn bx-btn--glow" href="#publier" data-bx-compose-open>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
          Lancer un appel
        </a>
        <?php endif; ?>
        <a class="bx-btn bx-btn--ghost" href="#besoins">Répondre à un appel</a>
      </div>
      <dl class="bx-stats">
        <div><dt>Appels ouverts</dt><dd data-bx-count="<?= $statOpen ?>"><?= $statOpen ?></dd></div>
        <div><dt>Unités en recherche</dt><dd data-bx-count="<?= count($statUnits) ?>"><?= count($statUnits) ?></dd></div>
        <div><dt>Places à pourvoir</dt><dd data-bx-count="<?= $statSlots ?>"><?= $statSlots ?></dd></div>
        <div><dt>Propositions envoyées</dt><dd data-bx-count="<?= $statProposals ?>"><?= $statProposals ?></dd></div>
      </dl>
    </div>
  </section>

  <div class="bx-wrap">
    <?php if ($flashOk): ?><p class="bx-flash bx-flash--ok" role="status"><?= $h((string) $flashOk) ?></p><?php endif; ?>
    <?php if ($flashErr): ?><p class="bx-flash bx-flash--err" role="alert"><?= $h((string) $flashErr) ?></p><?php endif; ?>

    <!-- ——— Mode d’emploi ——— -->
    <ol class="bx-steps" aria-label="Comment ça marche">
      <li><span class="bx-steps__n">01</span><strong>Lancez l’appel</strong><span>Type de besoin, date, nombre de joueurs, modpack : tout tient sur une carte.</span></li>
      <li><span class="bx-steps__n">02</span><strong>Recevez des propositions</strong><span>Les cadres des autres unités proposent leurs joueurs et un message.</span></li>
      <li><span class="bx-steps__n">03</span><strong>Montez l’opération</strong><span>Acceptez : la coopération s’ouvre avec le partenaire déjà sélectionné.</span></li>
    </ol>

    <?php if (!$boardReady): ?>
    <p class="bx-empty">La bourse sera disponible après la prochaine mise à jour de la base de données.</p>
    <?php else: ?>

    <!-- ——— Poste de commandement : mes appels ——— -->
    <?php if ($boardMyPosts !== []): ?>
    <section class="bx-section" id="mes-besoins" aria-labelledby="bx-mine">
      <div class="bx-section__head">
        <div>
          <p class="bx-kicker">Votre poste de commandement</p>
          <h2 id="bx-mine" class="bx-h2">Vos appels<?php if ($pendingOnMine > 0): ?> <span class="bx-ping"><?= $pendingOnMine ?> proposition<?= $pendingOnMine > 1 ? 's' : '' ?> à traiter</span><?php endif; ?></h2>
        </div>
      </div>
      <div class="bx-mine">
        <?php foreach ($boardMyPosts as $post):
          [$dlLabel, $dlClass] = $deadline($post['starts_at'] ?? null);
          $type = (string) $post['need_type'];
          $accepted = 0;
          foreach ($post['responses'] as $r) {
              if ($r['status'] === 'accepted') {
                  $accepted += (int) ($r['slots'] ?? 0);
              }
          }
        ?>
        <article class="bx-mine__card bx-tint--<?= $h($tint($type)) ?><?= $post['status'] !== 'open' ? ' is-closed' : '' ?>">
          <header class="bx-mine__head">
            <span class="bx-type"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $h($icon($type)) ?>"/></svg><?= $h($needTypes[$type] ?? 'Besoin') ?></span>
            <span class="bx-when bx-when--<?= $h($dlClass) ?>"><?= $h($dlLabel) ?></span>
            <span class="bx-state"><?= $post['status'] === 'open' ? 'Ouvert' : 'Clôturé' ?></span>
          </header>
          <h3 class="bx-mine__title"><?= $h((string) $post['title']) ?></h3>
          <?php if (!empty($post['slots'])): ?>
          <p class="bx-mine__fill"><strong><?= $accepted ?></strong> / <?= (int) $post['slots'] ?> joueurs confirmés</p>
          <?php endif; ?>
          <?php if ($post['responses'] === []): ?>
          <p class="bx-mine__wait">En attente des premières propositions. Partagez le lien de la bourse sur votre Discord pour accélérer.</p>
          <?php else: ?>
          <ul class="bx-offers">
            <?php foreach ($post['responses'] as $resp): ?>
            <li class="bx-offer bx-offer--<?= $h((string) $resp['status']) ?>">
              <span class="bx-avatar" aria-hidden="true"><?= $h($initials((string) $resp['tenant_name'])) ?></span>
              <div class="bx-offer__body">
                <p><a href="<?= $h(url('hub/c/' . $resp['tenant_slug'])) ?>"><?= $h((string) $resp['tenant_name']) ?></a><?php if (!empty($resp['slots'])): ?> · <strong><?= (int) $resp['slots'] ?> joueurs</strong><?php endif; ?></p>
                <?php if (!empty($resp['message'])): ?><p class="bx-offer__msg">« <?= $h((string) $resp['message']) ?> »</p><?php endif; ?>
                <p class="bx-offer__status"><?= $h($responseLabels[(string) $resp['status']] ?? (string) $resp['status']) ?></p>
              </div>
              <?php if ($resp['status'] === 'pending'): ?>
              <form method="post" action="<?= $h(url('hub/bourse/reponses/' . (int) $resp['id'])) ?>" class="bx-offer__actions">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <button type="submit" name="decision" value="accept" class="bx-btn bx-btn--small bx-btn--go">Accepter et monter l’opé</button>
                <button type="submit" name="decision" value="decline" class="bx-btn bx-btn--small bx-btn--quiet">Décliner</button>
              </form>
              <?php elseif ($resp['status'] === 'accepted'): ?>
              <a class="bx-btn bx-btn--small bx-btn--go" href="<?= $h(cooperation_mission_create_url() . '?' . http_build_query(['partenaire' => (int) $resp['tenant_id'], 'intitule' => (string) $post['title']])) ?>">Ouvrir la coopération</a>
              <?php endif; ?>
            </li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
          <form method="post" action="<?= $h(url('hub/bourse/' . (int) $post['id'] . '/cloturer')) ?>" class="bx-mine__foot">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
            <?php if ($post['status'] === 'open'): ?>
            <button type="submit" class="bx-link">Effectif complet : clôturer l’appel</button>
            <?php else: ?>
            <button type="submit" name="action" value="reopen" class="bx-link">Relancer cet appel</button>
            <?php endif; ?>
          </form>
        </article>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <!-- ——— Les appels des autres ——— -->
    <section class="bx-section" id="besoins" aria-labelledby="bx-open">
      <div class="bx-section__head">
        <div>
          <p class="bx-kicker">Sur le réseau</p>
          <h2 id="bx-open" class="bx-h2"><?= $boardScope === 'all' ? 'Tous les appels' : 'Ils ont besoin de vous' ?></h2>
        </div>
        <nav class="bx-scope" aria-label="Période">
          <a href="<?= $h(url('hub/bourse')) ?>#besoins"<?= $boardScope === 'open' ? ' aria-current="true"' : '' ?>>Ouverts</a>
          <a href="<?= $h(url('hub/bourse') . '?vue=tous') ?>#besoins"<?= $boardScope === 'all' ? ' aria-current="true"' : '' ?>>Historique</a>
        </nav>
      </div>

      <?php if ($others !== []): ?>
      <div class="bx-filters">
        <div class="bx-chips" role="group" aria-label="Filtrer par type">
          <button type="button" class="bx-chip" aria-pressed="true" data-bx-type="">Tout <span><?= count($others) ?></span></button>
          <?php foreach ($needTypes as $key => $label): if (empty($typeCounts[$key])) { continue; } ?>
          <button type="button" class="bx-chip bx-tint--<?= $h($tint((string) $key)) ?>" aria-pressed="false" data-bx-type="<?= $h((string) $key) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $h($icon((string) $key)) ?>"/></svg>
            <?= $h((string) $label) ?> <span><?= (int) $typeCounts[$key] ?></span>
          </button>
          <?php endforeach; ?>
        </div>
        <label class="bx-search"><span class="sr-only">Rechercher</span><input type="search" placeholder="Jeu, unité, mot-clé…" data-bx-q autocomplete="off"></label>
      </div>
      <?php endif; ?>

      <?php if ($others === []): ?>
      <div class="bx-void">
        <div class="bx-void__radar" aria-hidden="true"><span class="bx-radar__sweep"></span></div>
        <p class="bx-void__title">Le radar est calme… pour l’instant.</p>
        <p>Aucun appel ouvert par les autres communautés. Prenez l’initiative : la première unité qui lance un appel donne le ton.</p>
        <?php if ($boardCanPublish): ?><a class="bx-btn bx-btn--glow" href="#publier" data-bx-compose-open>Lancer le premier appel</a><?php endif; ?>
      </div>
      <?php else: ?>
      <div class="bx-grid" data-bx-grid>
        <?php foreach ($others as $post):
          $type = (string) $post['need_type'];
          [$dlLabel, $dlClass, $dlTs] = $deadline($post['starts_at'] ?? null);
          $slots = (int) ($post['slots'] ?? 0);
          $offered = (int) ($post['offered_slots'] ?? 0);
          $pct = $slots > 0 ? min(100, (int) round($offered / $slots * 100)) : 0;
          $myStatus = $boardMyResponses[(int) $post['id']] ?? null;
          $logo = (string) ($post['tenant_logo'] ?? '');
          $search = mb_strtolower(implode(' ', [(string) $post['title'], (string) $post['tenant_name'], (string) ($post['game_label'] ?? ''), (string) ($post['details'] ?? ''), $needTypes[$type] ?? '']));
        ?>
        <article class="bx-card bx-tint--<?= $h($tint($type)) ?><?= $post['status'] !== 'open' ? ' is-closed' : '' ?>" id="besoin-<?= (int) $post['id'] ?>" data-bx-card data-type="<?= $h($type) ?>" data-search="<?= $h($search) ?>">
          <header class="bx-card__head">
            <span class="bx-type"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $h($icon($type)) ?>"/></svg><?= $h($needTypes[$type] ?? 'Besoin') ?></span>
            <span class="bx-when bx-when--<?= $h($dlClass) ?>"<?= $dlTs !== null ? ' data-bx-ts="' . (int) $dlTs . '"' : '' ?>><?= $h($dlLabel) ?></span>
          </header>
          <h3 class="bx-card__title"><?= $h((string) $post['title']) ?></h3>
          <p class="bx-card__by">
            <?php if ($logo !== '' && filter_var($logo, FILTER_VALIDATE_URL)): ?>
            <img class="bx-avatar" src="<?= $h($logo) ?>" alt="" loading="lazy">
            <?php else: ?>
            <span class="bx-avatar" aria-hidden="true"><?= $h($initials((string) $post['tenant_name'])) ?></span>
            <?php endif; ?>
            <a href="<?= $h(url('hub/c/' . $post['tenant_slug'])) ?>"><?= $h((string) $post['tenant_name']) ?></a>
            <?php if (!empty($post['game_label'])): ?><span class="bx-game"><?= $h((string) $post['game_label']) ?></span><?php endif; ?>
          </p>
          <?php if (!empty($post['details'])): ?><p class="bx-card__details"><?= $h((string) $post['details']) ?></p><?php endif; ?>

          <div class="bx-gauge"<?= $slots > 0 ? '' : ' data-open-ended' ?>>
            <?php if ($slots > 0): ?>
            <div class="bx-gauge__bar" role="progressbar" aria-valuemin="0" aria-valuemax="<?= $slots ?>" aria-valuenow="<?= min($offered, $slots) ?>" aria-label="Joueurs proposés"><span style="--p: <?= $pct ?>%"></span></div>
            <p class="bx-gauge__label"><strong><?= $offered ?></strong> / <?= $slots ?> joueurs proposés · <?= (int) $post['responses_count'] ?> unité<?= (int) $post['responses_count'] > 1 ? 's' : '' ?> sur le coup</p>
            <?php else: ?>
            <p class="bx-gauge__label"><?= (int) $post['responses_count'] ?> unité<?= (int) $post['responses_count'] > 1 ? 's' : '' ?> sur le coup · effectif libre</p>
            <?php endif; ?>
          </div>

          <footer class="bx-card__foot">
            <?php if ($myStatus !== null): ?>
            <p class="bx-mine-status bx-mine-status--<?= $h($myStatus) ?>"><?= $myStatus === 'accepted' ? 'Votre proposition est acceptée : rendez-vous dans Coopérations.' : ($myStatus === 'declined' ? 'Proposition déclinée.' : 'Proposition envoyée, en attente de réponse.') ?></p>
            <?php endif; ?>
            <?php if ($post['status'] === 'open' && $boardCanRespond && $myStatus !== 'accepted'): ?>
            <details class="bx-respond">
              <summary class="bx-btn bx-btn--go"><?= $myStatus === null ? 'On est dispo, on vient' : 'Modifier notre proposition' ?></summary>
              <form method="post" action="<?= $h(url('hub/bourse/' . (int) $post['id'] . '/repondre')) ?>" class="bx-respond__form">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <label><span>Joueurs que vous engagez</span><input type="number" name="slots" min="1" max="999" placeholder="<?= $slots > 0 ? 'ex. ' . max(1, $slots - $offered) : 'ex. 6' ?>"></label>
                <label><span>Votre message</span><textarea name="message" maxlength="2000" rows="3" placeholder="Qui vous êtes, ce que vous apportez, comment vous joindre (Discord, TeamSpeak…)"></textarea></label>
                <div class="bx-respond__actions">
                  <button type="submit" class="bx-btn bx-btn--glow bx-btn--small">Envoyer la proposition</button>
                  <?php if ($myStatus === 'pending'): ?><button type="submit" name="action" value="withdraw" class="bx-btn bx-btn--quiet bx-btn--small">Retirer</button><?php endif; ?>
                </div>
              </form>
            </details>
            <?php elseif ($post['status'] === 'open' && !$boardCanRespond): ?>
            <p class="bx-hint">Seuls les cadres habilités aux coopérations peuvent répondre au nom de votre unité.</p>
            <?php elseif ($post['status'] !== 'open'): ?>
            <p class="bx-hint">Appel clôturé.</p>
            <?php endif; ?>
          </footer>
        </article>
        <?php endforeach; ?>
      </div>
      <p class="bx-empty" data-bx-none hidden>Aucun appel ne correspond à ce filtre.</p>
      <?php endif; ?>
    </section>

    <!-- ——— Lancer un appel (fenêtre avec aperçu en direct ; section classique sans JavaScript) ——— -->
    <?php if ($boardCanPublish): ?>
    <section class="bx-compose" id="publier" aria-labelledby="bx-compose-title" data-bx-compose>
      <div class="bx-compose__panel">
        <button type="button" class="bx-compose__close" data-bx-compose-close aria-label="Fermer">×</button>
        <div class="bx-compose__form">
          <p class="bx-kicker">Nouvel appel</p>
          <h2 id="bx-compose-title" class="bx-h2">Lancez votre appel au réseau</h2>
          <form method="post" action="<?= $h(url('hub/bourse')) ?>" data-bx-form>
            <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
            <fieldset class="bx-typepick">
              <legend>De quoi avez-vous besoin&nbsp;?</legend>
              <?php $first = true; foreach ($needTypes as $key => $label): ?>
              <label class="bx-typepick__opt bx-tint--<?= $h($tint((string) $key)) ?>">
                <input type="radio" name="need_type" value="<?= $h((string) $key) ?>"<?= $first ? ' checked' : '' ?>>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $h($icon((string) $key)) ?>"/></svg>
                <span><?= $h((string) $label) ?></span>
              </label>
              <?php $first = false; endforeach; ?>
            </fieldset>
            <label class="bx-field"><span>Votre appel en une phrase *</span><input name="title" required minlength="5" maxlength="160" placeholder="Cherche une escouade OPFOR pour l’op du samedi 21 h"></label>
            <div class="bx-field-row">
              <label class="bx-field"><span>Quand&nbsp;?</span><input type="datetime-local" name="starts_at"></label>
              <label class="bx-field"><span>Combien de joueurs&nbsp;?</span><input type="number" name="slots" min="1" max="999" placeholder="8"></label>
            </div>
            <label class="bx-field"><span>Jeu</span><input name="game_label" maxlength="80" placeholder="Arma 3, Arma Reforger, Squad…" list="bx-games"></label>
            <datalist id="bx-games"><option value="Arma 3"><option value="Arma Reforger"><option value="Squad"><option value="Ready or Not"><option value="DCS World"></datalist>
            <label class="bx-field"><span>Le brief</span><textarea name="details" maxlength="4000" rows="4" placeholder="Contexte, modpack, rôles attendus, serveur vocal, niveau requis…"></textarea></label>
            <button type="submit" class="bx-btn bx-btn--glow bx-btn--wide">Diffuser l’appel au réseau</button>
          </form>
        </div>
        <aside class="bx-compose__preview" aria-label="Aperçu">
          <p class="bx-kicker">Aperçu tel que les autres unités le verront</p>
          <article class="bx-card bx-tint--emerald" data-bx-preview>
            <header class="bx-card__head">
              <span class="bx-type"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $h($icon('renfort')) ?>" data-pv-icon/></svg><span data-pv-type><?= $h($needTypes['renfort'] ?? 'Renfort') ?></span></span>
              <span class="bx-when bx-when--free" data-pv-when>Date libre</span>
            </header>
            <h3 class="bx-card__title" data-pv-title>Votre appel apparaîtra ici</h3>
            <p class="bx-card__by"><span class="bx-avatar" aria-hidden="true">★</span><span>Votre communauté</span><span class="bx-game" data-pv-game hidden></span></p>
            <p class="bx-card__details" data-pv-details hidden></p>
            <div class="bx-gauge"><div class="bx-gauge__bar"><span style="--p: 0%"></span></div><p class="bx-gauge__label" data-pv-slots>Effectif libre</p></div>
          </article>
        </aside>
      </div>
    </section>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<script>
(function () {
  var root = document.querySelector('[data-bx]');
  if (!root) return;
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Compteurs animés du bandeau.
  if (!reduce && 'IntersectionObserver' in window) {
    root.querySelectorAll('[data-bx-count]').forEach(function (el) {
      var target = parseInt(el.getAttribute('data-bx-count'), 10) || 0;
      if (target === 0) return;
      el.textContent = '0';
      var io = new IntersectionObserver(function (entries) {
        if (!entries[0].isIntersecting) return;
        io.disconnect();
        var start = null;
        function step(ts) {
          if (start === null) start = ts;
          var k = Math.min(1, (ts - start) / 900);
          el.textContent = String(Math.round(target * (1 - Math.pow(1 - k, 3))));
          if (k < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
      });
      io.observe(el);
    });
  }

  // Compte à rebours sous 48 h.
  function tick() {
    var now = Date.now() / 1000;
    root.querySelectorAll('[data-bx-ts]').forEach(function (el) {
      var left = parseInt(el.getAttribute('data-bx-ts'), 10) - now;
      if (left <= 0 || left > 48 * 3600) return;
      var hrs = Math.floor(left / 3600), min = Math.floor((left % 3600) / 60);
      el.textContent = 'Dans ' + (hrs > 0 ? hrs + ' h ' : '') + (min < 10 && hrs > 0 ? '0' : '') + min + ' min';
    });
  }
  tick();
  setInterval(tick, 30000);

  // Filtres par type et recherche.
  var cards = Array.prototype.slice.call(root.querySelectorAll('[data-bx-card]'));
  var chips = Array.prototype.slice.call(root.querySelectorAll('[data-bx-type]'));
  var q = root.querySelector('[data-bx-q]');
  var none = root.querySelector('[data-bx-none]');
  var type = '';
  function fold(v) { var s = String(v || '').toLowerCase(); return s.normalize ? s.normalize('NFD').replace(/[̀-ͯ]/g, '') : s; }
  function apply() {
    var tokens = fold(q ? q.value : '').split(/\s+/).filter(Boolean), shown = 0;
    cards.forEach(function (c) {
      var hay = fold(c.getAttribute('data-search'));
      var ok = (!type || c.getAttribute('data-type') === type) && tokens.every(function (t) { return hay.indexOf(t) !== -1; });
      c.hidden = !ok;
      if (ok) shown++;
    });
    if (none) none.hidden = shown > 0;
  }
  chips.forEach(function (ch) {
    ch.addEventListener('click', function () {
      type = ch.getAttribute('data-bx-type');
      chips.forEach(function (x) { x.setAttribute('aria-pressed', x === ch ? 'true' : 'false'); });
      apply();
    });
  });
  if (q) q.addEventListener('input', apply);

  // Fenêtre « Lancer un appel » avec aperçu en direct.
  var compose = root.querySelector('[data-bx-compose]');
  if (!compose) return;
  compose.classList.add('is-modal');
  function openCompose(e) {
    if (e) e.preventDefault();
    compose.classList.add('is-open');
    document.body.style.overflow = 'hidden';
    var first = compose.querySelector('input[name="title"]');
    if (first) setTimeout(function () { first.focus(); }, 50);
  }
  function closeCompose() {
    compose.classList.remove('is-open');
    document.body.style.overflow = '';
  }
  root.querySelectorAll('[data-bx-compose-open]').forEach(function (a) { a.addEventListener('click', openCompose); });
  compose.querySelector('[data-bx-compose-close]').addEventListener('click', closeCompose);
  compose.addEventListener('click', function (e) { if (e.target === compose) closeCompose(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && compose.classList.contains('is-open')) closeCompose(); });
  if (location.hash === '#publier') openCompose();

  var form = compose.querySelector('[data-bx-form]');
  var pv = compose.querySelector('[data-bx-preview]');
  var icons = <?= json_encode(array_map(static fn (array $s): array => ['d' => $s[0], 't' => $s[1]], $typeStyle), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
  var labels = <?= json_encode($needTypes, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
  var days = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
  function whenLabel(v) {
    if (!v) return ['Date libre', 'free'];
    var d = new Date(v); if (isNaN(d)) return ['Date libre', 'free'];
    var t0 = new Date(); t0.setHours(0, 0, 0, 0);
    var diff = Math.floor((d - t0) / 86400000), hm = ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2);
    if (diff <= 0) return [(d.getHours() >= 18 ? 'Ce soir ' : 'Aujourd’hui ') + hm, 'hot'];
    if (diff === 1) return ['Demain ' + hm, 'soon'];
    if (diff < 7) { var w = days[d.getDay()]; return [w.charAt(0).toUpperCase() + w.slice(1) + ' ' + hm, 'week']; }
    return [('0' + d.getDate()).slice(-2) + '/' + ('0' + (d.getMonth() + 1)).slice(-2) + ' · ' + hm, 'later'];
  }
  function render() {
    var f = form.elements;
    var tp = (form.querySelector('input[name="need_type"]:checked') || {}).value || 'renfort';
    var meta = icons[tp] || icons.autre;
    pv.className = 'bx-card bx-tint--' + meta.t;
    pv.querySelector('[data-pv-icon]').setAttribute('d', meta.d);
    pv.querySelector('[data-pv-type]').textContent = labels[tp] || 'Besoin';
    pv.querySelector('[data-pv-title]').textContent = f.title.value.trim() || 'Votre appel apparaîtra ici';
    var w = whenLabel(f.starts_at.value), when = pv.querySelector('[data-pv-when]');
    when.textContent = w[0]; when.className = 'bx-when bx-when--' + w[1];
    var game = pv.querySelector('[data-pv-game]'); game.textContent = f.game_label.value.trim(); game.hidden = !game.textContent;
    var det = pv.querySelector('[data-pv-details]'); det.textContent = f.details.value.trim(); det.hidden = !det.textContent;
    var n = parseInt(f.slots.value, 10);
    pv.querySelector('[data-pv-slots]').innerHTML = n > 0 ? '<strong>0</strong> / ' + n + ' joueurs proposés' : 'Effectif libre';
  }
  form.addEventListener('input', render);
  form.addEventListener('change', render);
  render();
})();
</script>
