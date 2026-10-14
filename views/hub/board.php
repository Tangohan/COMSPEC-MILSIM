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
$date = static fn (?string $v): string => $v ? date('d/m/Y H:i', strtotime($v)) : 'Date libre';
$responseLabels = ['pending' => 'En attente', 'accepted' => 'Acceptée', 'declined' => 'Déclinée'];
$postLabels = ['open' => 'Ouvert', 'closed' => 'Clôturé'];
$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');
?>
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/hub-ecosystem.css')) ?>">

<div class="hubx">
  <div class="hx-wrap pt-8">
    <a href="<?= $h(url('hub')) ?>" class="text-sm font-semibold underline">← Hub des communautés</a>
    <div class="hx-section__head mt-4">
      <div>
        <p class="hx-kicker">Inter-team</p>
        <h1 class="hx-title" style="font-size: clamp(1.6rem, 3vw, 2.2rem)">Bourse aux coopérations</h1>
        <p class="hx-sub">Publiez un besoin ouvert à toutes les communautés, ou proposez votre aide à un besoin existant. Quand une proposition est acceptée, la coopération s’ouvre dans le module Coopérations avec l’unité partenaire déjà sélectionnée.</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <a class="hx-btn hx-btn--line<?= $boardScope === 'open' ? ' hx-btn--accent' : '' ?>" href="<?= $h(url('hub/bourse')) ?>">Besoins ouverts</a>
        <a class="hx-btn hx-btn--line<?= $boardScope === 'all' ? ' hx-btn--accent' : '' ?>" href="<?= $h(url('hub/bourse') . '?vue=tous') ?>">Tout l’historique</a>
      </div>
    </div>
    <?php if ($flashOk): ?><p class="hx-flash hx-flash--ok" role="status"><?= $h((string) $flashOk) ?></p><?php endif; ?>
    <?php if ($flashErr): ?><p class="hx-flash hx-flash--err" role="alert"><?= $h((string) $flashErr) ?></p><?php endif; ?>

    <?php if (!$boardReady): ?>
    <p class="hx-empty mt-6">La bourse sera disponible après la prochaine mise à jour de la base de données (migrations).</p>
    <?php else: ?>

    <?php if ($boardCanPublish): ?>
    <section class="hx-section" id="publier">
      <details class="hx-card"<?= $boardMyPosts === [] ? ' open' : '' ?>>
        <summary class="cursor-pointer text-base font-black">Publier un besoin</summary>
        <form method="post" action="<?= $h(url('hub/bourse')) ?>" class="mt-4 grid gap-3 md:grid-cols-2">
          <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
          <div class="hx-field md:col-span-2">
            <label for="hx-b-title">Intitulé *</label>
            <input id="hx-b-title" class="hx-input" name="title" required minlength="5" maxlength="160" placeholder="Ex. Cherche une escouade OPFOR pour l’op du samedi 21 h">
          </div>
          <div class="hx-field">
            <label for="hx-b-type">Type de besoin</label>
            <select id="hx-b-type" class="hx-select" name="need_type">
              <?php foreach ($needTypes as $k => $label): ?><option value="<?= $h((string) $k) ?>"><?= $h((string) $label) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="hx-field">
            <label for="hx-b-game">Jeu</label>
            <input id="hx-b-game" class="hx-input" name="game_label" maxlength="80" placeholder="Arma 3, Arma Reforger, Squad…">
          </div>
          <div class="hx-field">
            <label for="hx-b-date">Date et heure</label>
            <input id="hx-b-date" class="hx-input" type="datetime-local" name="starts_at">
          </div>
          <div class="hx-field">
            <label for="hx-b-slots">Places recherchées</label>
            <input id="hx-b-slots" class="hx-input" type="number" name="slots" min="1" max="999" placeholder="ex. 8">
          </div>
          <div class="hx-field md:col-span-2">
            <label for="hx-b-details">Détails</label>
            <textarea id="hx-b-details" class="hx-area" name="details" maxlength="4000" placeholder="Contexte, modpack, rôles attendus, serveur vocal, niveau requis…"></textarea>
          </div>
          <div class="md:col-span-2"><button type="submit" class="hx-btn hx-btn--accent">Publier dans la bourse</button></div>
        </form>
      </details>
    </section>
    <?php endif; ?>

    <?php if ($boardMyPosts !== []): ?>
    <section class="hx-section" id="mes-besoins" aria-labelledby="hx-mine">
      <h2 id="hx-mine" class="hx-title">Vos besoins publiés</h2>
      <p class="hx-sub">Acceptez une proposition pour ouvrir la coopération ; déclinez les autres ou clôturez le besoin quand il est couvert.</p>
      <div class="hx-board mt-3">
        <?php foreach ($boardMyPosts as $post): ?>
        <article class="hx-post">
          <div class="hx-post__meta">
            <span class="hx-pill<?= $post['status'] === 'open' ? '' : ' hx-pill--muted' ?>"><?= $h($postLabels[(string) $post['status']] ?? (string) $post['status']) ?></span>
            <span><?= $h($needTypes[(string) $post['need_type']] ?? 'Besoin') ?></span>
            <span><?= $h($date($post['starts_at'] ?? null)) ?></span>
          </div>
          <h3><?= $h((string) $post['title']) ?></h3>
          <?php if ($post['responses'] === []): ?>
          <p>Aucune proposition pour l’instant.</p>
          <?php else: ?>
          <div class="hx-resp">
            <?php foreach ($post['responses'] as $resp): ?>
            <div class="hx-resp__item">
              <div>
                <strong><a href="<?= $h(url('hub/c/' . $resp['tenant_slug'])) ?>"><?= $h((string) $resp['tenant_name']) ?></a></strong>
                <?php if (!empty($resp['slots'])): ?> · <?= (int) $resp['slots'] ?> joueurs<?php endif; ?>
                · <span class="hx-pill hx-pill--muted"><?= $h($responseLabels[(string) $resp['status']] ?? (string) $resp['status']) ?></span>
                <?php if (!empty($resp['message'])): ?><p class="mt-1"><?= $h((string) $resp['message']) ?></p><?php endif; ?>
              </div>
              <?php if ($resp['status'] === 'pending'): ?>
              <form method="post" action="<?= $h(url('hub/bourse/reponses/' . (int) $resp['id'])) ?>" class="flex gap-2">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <button type="submit" name="decision" value="accept" class="hx-btn hx-btn--accent hx-btn--small">Accepter et ouvrir la coopération</button>
                <button type="submit" name="decision" value="decline" class="hx-btn hx-btn--line hx-btn--small">Décliner</button>
              </form>
              <?php elseif ($resp['status'] === 'accepted'): ?>
              <a class="hx-btn hx-btn--line hx-btn--small" href="<?= $h(cooperation_mission_create_url() . '?' . http_build_query(['partenaire' => (int) $resp['tenant_id'], 'intitule' => (string) $post['title']])) ?>">Ouvrir la coopération</a>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          <form method="post" action="<?= $h(url('hub/bourse/' . (int) $post['id'] . '/cloturer')) ?>">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
            <?php if ($post['status'] === 'open'): ?>
            <button type="submit" class="hx-btn hx-btn--line hx-btn--small">Clôturer ce besoin</button>
            <?php else: ?>
            <button type="submit" name="action" value="reopen" class="hx-btn hx-btn--line hx-btn--small">Rouvrir</button>
            <?php endif; ?>
          </form>
        </article>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <section class="hx-section" aria-labelledby="hx-open">
      <h2 id="hx-open" class="hx-title"><?= $boardScope === 'all' ? 'Tous les besoins' : 'Besoins des autres communautés' ?></h2>
      <?php
      $others = array_values(array_filter($boardPosts, static fn (array $p): bool => (int) $p['tenant_id'] !== $boardTenantId));
      ?>
      <?php if ($others === []): ?>
      <p class="hx-empty mt-3">Aucun besoin publié par les autres communautés pour le moment.</p>
      <?php else: ?>
      <div class="hx-board mt-3">
        <?php foreach ($others as $post):
          $myStatus = $boardMyResponses[(int) $post['id']] ?? null;
        ?>
        <article class="hx-post" id="besoin-<?= (int) $post['id'] ?>">
          <div class="hx-post__meta">
            <span class="hx-pill"><?= $h($needTypes[(string) $post['need_type']] ?? 'Besoin') ?></span>
            <span><?= $h($date($post['starts_at'] ?? null)) ?></span>
            <?php if (!empty($post['slots'])): ?><span><?= (int) $post['slots'] ?> places</span><?php endif; ?>
            <?php if ($post['status'] !== 'open'): ?><span class="hx-pill hx-pill--muted"><?= $h($postLabels[(string) $post['status']] ?? '') ?></span><?php endif; ?>
          </div>
          <h3><?= $h((string) $post['title']) ?></h3>
          <p class="hx-post__by"><a href="<?= $h(url('hub/c/' . $post['tenant_slug'])) ?>"><?= $h((string) $post['tenant_name']) ?></a><?= !empty($post['game_label']) ? ' · ' . $h((string) $post['game_label']) : '' ?> · <?= (int) $post['responses_count'] ?> proposition(s)</p>
          <?php if (!empty($post['details'])): ?><p><?= $h((string) $post['details']) ?></p><?php endif; ?>
          <?php if ($myStatus !== null): ?>
          <p class="hx-post__meta">Votre proposition : <strong><?= $h($responseLabels[$myStatus] ?? $myStatus) ?></strong></p>
          <?php endif; ?>
          <?php if ($post['status'] === 'open' && $boardCanRespond && $myStatus !== 'accepted'): ?>
          <details>
            <summary><?= $myStatus === null ? 'Proposer notre aide' : 'Modifier notre proposition' ?></summary>
            <form method="post" action="<?= $h(url('hub/bourse/' . (int) $post['id'] . '/repondre')) ?>">
              <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
              <input class="hx-input" type="number" name="slots" min="1" max="999" placeholder="Nombre de joueurs proposés">
              <textarea class="hx-area" name="message" maxlength="2000" placeholder="Qui vous êtes, ce que vous apportez, comment vous joindre…"></textarea>
              <div class="flex flex-wrap gap-2">
                <button type="submit" class="hx-btn hx-btn--accent hx-btn--small">Envoyer la proposition</button>
                <?php if ($myStatus === 'pending'): ?>
                <button type="submit" name="action" value="withdraw" class="hx-btn hx-btn--line hx-btn--small">Retirer</button>
                <?php endif; ?>
              </div>
            </form>
          </details>
          <?php elseif ($post['status'] === 'open' && !$boardCanRespond): ?>
          <p class="hx-post__meta">Seuls les cadres habilités aux coopérations de votre communauté peuvent répondre.</p>
          <?php endif; ?>
        </article>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </section>
    <?php endif; ?>
  </div>
</div>
