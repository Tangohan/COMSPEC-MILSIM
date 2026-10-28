<?php
declare(strict_types=1);

/** Bourse aux coopérations : publier un besoin, suivre ses propositions, répondre aux besoins des autres. */
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
$responseTone = ['pending' => 'warn', 'accepted' => 'ok', 'declined' => 'muted'];
$postLabels = ['open' => 'Ouvert', 'closed' => 'Clôturé'];
$others = array_values(array_filter($boardPosts, static fn (array $p): bool => (int) $p['tenant_id'] !== $boardTenantId));
$pendingOnMine = 0;
foreach ($boardMyPosts as $p) {
    foreach ($p['responses'] ?? [] as $r) {
        $pendingOnMine += ($r['status'] ?? '') === 'pending' ? 1 : 0;
    }
}
$myOpen = count(array_filter($boardMyPosts, static fn (array $p): bool => ($p['status'] ?? '') === 'open'));
$myAnswered = count($boardMyResponses);

$hxBack = [url('hub'), 'Hub des communautés'];
$hxKicker = 'Inter-team · Bourse aux coopérations';
$hxTitle = 'Bourse aux coopérations';
$hxLead = 'Publiez un besoin ouvert à toutes les communautés ou proposez votre aide. Une proposition acceptée ouvre la coopération avec l’unité partenaire déjà sélectionnée.';
$hxActions = $boardCanPublish && $boardReady ? '<a class="hx-cta hx-cta--accent" href="#publier">Publier un besoin</a>' : '';
require __DIR__ . '/partials/shell_open.php';
?>
      <?php if (!$boardReady): ?>
      <div class="hx-empty">
        <?= $hxIcon('bourse', 'hx-empty__icon') ?>
        <p class="hx-empty__title">Bourse indisponible</p>
        <p>La bourse sera disponible après la prochaine mise à jour de la base de données (migrations).</p>
      </div>
      <?php else: ?>

      <dl class="hx-stats" aria-label="Résumé de la bourse">
        <div><dt>Besoins des autres</dt><dd><?= count($others) ?></dd></div>
        <div><dt>Vos besoins ouverts</dt><dd><?= $myOpen ?></dd></div>
        <div class="<?= $pendingOnMine > 0 ? 'is-hot' : '' ?>"><dt>Propositions à traiter</dt><dd><?= $pendingOnMine ?></dd></div>
        <div><dt>Vos propositions</dt><dd><?= $myAnswered ?></dd></div>
      </dl>

      <nav class="hx-filterbar" aria-label="Portée">
        <a class="hx-filter<?= $boardScope === 'open' ? ' is-active' : '' ?>" href="<?= $h(url('hub/bourse')) ?>"<?= $boardScope === 'open' ? ' aria-current="page"' : '' ?>>Besoins ouverts</a>
        <a class="hx-filter<?= $boardScope === 'all' ? ' is-active' : '' ?>" href="<?= $h(url('hub/bourse') . '?vue=tous') ?>"<?= $boardScope === 'all' ? ' aria-current="page"' : '' ?>>Tout l’historique</a>
        <?php if ($boardMyPosts !== []): ?><a class="hx-filter" href="#mes-besoins">Vos besoins<?= $pendingOnMine > 0 ? ' <i class="hx-count">' . $pendingOnMine . '</i>' : '' ?></a><?php endif; ?>
      </nav>

      <?php if ($boardCanPublish): ?>
      <section class="hx-section" id="publier" aria-labelledby="hx-pub-title">
        <details class="hx-panel"<?= $boardMyPosts === [] ? ' open' : '' ?>>
          <summary class="hx-panel__summary"><?= $hxIcon('plus') ?><span><strong id="hx-pub-title">Publier un besoin</strong><em>Visible par toutes les communautés du réseau</em></span></summary>
          <form method="post" action="<?= $h(url('hub/bourse')) ?>" class="hx-form">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
            <div class="hx-field hx-field--wide">
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
            <div class="hx-field hx-field--wide">
              <label for="hx-b-details">Détails</label>
              <textarea id="hx-b-details" class="hx-area" name="details" maxlength="4000" placeholder="Contexte, mods requis, rôles attendus, serveur vocal, niveau requis…"></textarea>
            </div>
            <div class="hx-field--wide hx-form__bar"><button type="submit" class="hx-cta hx-cta--primary">Publier dans la bourse</button></div>
          </form>
        </details>
      </section>
      <?php endif; ?>

      <?php if ($boardMyPosts !== []): ?>
      <section class="hx-section" id="mes-besoins" aria-labelledby="hx-mine">
        <div class="hx-section__head">
          <div>
            <p class="hx-kicker">Votre communauté</p>
            <h2 id="hx-mine" class="hx-title">Vos besoins publiés</h2>
            <p class="hx-sub">Acceptez une proposition pour ouvrir la coopération ; déclinez les autres ou clôturez le besoin quand il est couvert.</p>
          </div>
        </div>
        <div class="hx-board">
          <?php foreach ($boardMyPosts as $post): ?>
          <article class="hx-post<?= $post['status'] === 'open' ? '' : ' is-closed' ?>">
            <div class="hx-post__meta">
              <span class="hx-badge hx-badge--<?= $post['status'] === 'open' ? 'ok' : 'muted' ?>"><?= $h($postLabels[(string) $post['status']] ?? (string) $post['status']) ?></span>
              <span class="hx-badge hx-badge--type"><?= $h($needTypes[(string) $post['need_type']] ?? 'Besoin') ?></span>
            </div>
            <h3 class="hx-card__title"><?= $h((string) $post['title']) ?></h3>
            <dl class="hx-meta"><div><dt>Quand</dt><dd><?= $h($date($post['starts_at'] ?? null)) ?></dd></div><div><dt>Propositions</dt><dd><?= count($post['responses']) ?></dd></div></dl>
            <?php if ($post['responses'] === []): ?>
            <p class="hx-muted">Aucune proposition pour l’instant.</p>
            <?php else: ?>
            <ul class="hx-resp">
              <?php foreach ($post['responses'] as $resp): $st = (string) $resp['status']; ?>
              <li class="hx-resp__item">
                <div class="hx-resp__who">
                  <a href="<?= $h(url('hub/c/' . $resp['tenant_slug'])) ?>"><?= $h((string) $resp['tenant_name']) ?></a>
                  <span class="hx-badge hx-badge--<?= $responseTone[$st] ?? 'muted' ?>"><?= $h($responseLabels[$st] ?? $st) ?></span>
                  <?php if (!empty($resp['slots'])): ?><small><?= (int) $resp['slots'] ?> joueurs</small><?php endif; ?>
                  <?php if (!empty($resp['message'])): ?><p><?= $h((string) $resp['message']) ?></p><?php endif; ?>
                </div>
                <?php if ($st === 'pending'): ?>
                <form method="post" action="<?= $h(url('hub/bourse/reponses/' . (int) $resp['id'])) ?>" class="hx-row">
                  <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                  <button type="submit" name="decision" value="accept" class="hx-cta hx-cta--accent hx-cta--small">Accepter et ouvrir la coopération</button>
                  <button type="submit" name="decision" value="decline" class="hx-cta hx-cta--small">Décliner</button>
                </form>
                <?php elseif ($st === 'accepted'): ?>
                <a class="hx-cta hx-cta--small" href="<?= $h(cooperation_mission_create_url() . '?' . http_build_query(['partenaire' => (int) $resp['tenant_id'], 'intitule' => (string) $post['title']])) ?>">Ouvrir la coopération</a>
                <?php endif; ?>
              </li>
              <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <form method="post" action="<?= $h(url('hub/bourse/' . (int) $post['id'] . '/cloturer')) ?>" class="hx-post__foot">
              <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
              <?php if ($post['status'] === 'open'): ?>
              <button type="submit" class="hx-cta hx-cta--small">Clôturer ce besoin</button>
              <?php else: ?>
              <button type="submit" name="action" value="reopen" class="hx-cta hx-cta--small">Rouvrir</button>
              <?php endif; ?>
            </form>
          </article>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <section class="hx-section" aria-labelledby="hx-open">
        <div class="hx-section__head">
          <div>
            <p class="hx-kicker">Réseau</p>
            <h2 id="hx-open" class="hx-title"><?= $boardScope === 'all' ? 'Tous les besoins' : 'Besoins des autres communautés' ?></h2>
          </div>
        </div>
        <?php if ($others === []): ?>
        <div class="hx-empty">
          <?= $hxIcon('bourse', 'hx-empty__icon') ?>
          <p class="hx-empty__title">Aucun besoin publié</p>
          <p>Les autres communautés n’ont publié aucun besoin pour le moment.<?= $boardCanPublish ? ' Lancez le mouvement en publiant le vôtre.' : '' ?></p>
        </div>
        <?php else: ?>
        <div class="hx-board">
          <?php foreach ($others as $post):
            $myStatus = $boardMyResponses[(int) $post['id']] ?? null;
          ?>
          <article class="hx-post<?= $post['status'] === 'open' ? '' : ' is-closed' ?>" id="besoin-<?= (int) $post['id'] ?>">
            <div class="hx-post__meta">
              <span class="hx-badge hx-badge--type"><?= $h($needTypes[(string) $post['need_type']] ?? 'Besoin') ?></span>
              <?php if (!empty($post['slots'])): ?><span class="hx-badge"><?= (int) $post['slots'] ?> places</span><?php endif; ?>
              <?php if ($post['status'] !== 'open'): ?><span class="hx-badge hx-badge--muted"><?= $h($postLabels[(string) $post['status']] ?? '') ?></span><?php endif; ?>
              <?php if ($myStatus !== null): ?><span class="hx-badge hx-badge--<?= $responseTone[$myStatus] ?? 'muted' ?>">Votre proposition : <?= $h($responseLabels[$myStatus] ?? $myStatus) ?></span><?php endif; ?>
            </div>
            <h3 class="hx-card__title"><?= $h((string) $post['title']) ?></h3>
            <dl class="hx-meta">
              <div><dt>Par</dt><dd><a href="<?= $h(url('hub/c/' . $post['tenant_slug'])) ?>"><?= $h((string) $post['tenant_name']) ?></a></dd></div>
              <?php if (!empty($post['game_label'])): ?><div><dt>Jeu</dt><dd><?= $h((string) $post['game_label']) ?></dd></div><?php endif; ?>
              <div><dt>Quand</dt><dd><?= $h($date($post['starts_at'] ?? null)) ?></dd></div>
              <div><dt>Propositions</dt><dd><?= (int) $post['responses_count'] ?></dd></div>
            </dl>
            <?php if (!empty($post['details'])): ?><p class="hx-post__text"><?= $h((string) $post['details']) ?></p><?php endif; ?>
            <?php if ($post['status'] === 'open' && $boardCanRespond && $myStatus !== 'accepted'): ?>
            <details class="hx-action-menu"<?= $myStatus === null ? '' : '' ?>>
              <summary><?= $myStatus === null ? 'Proposer notre aide' : 'Modifier notre proposition' ?></summary>
              <form method="post" action="<?= $h(url('hub/bourse/' . (int) $post['id'] . '/repondre')) ?>" class="hx-action-menu__panel">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <input class="hx-input" type="number" name="slots" min="1" max="999" placeholder="Nombre de joueurs proposés">
                <textarea class="hx-area" name="message" maxlength="2000" placeholder="Qui vous êtes, ce que vous apportez, comment vous joindre…"></textarea>
                <div class="hx-row">
                  <button type="submit" class="hx-cta hx-cta--primary hx-cta--small">Envoyer la proposition</button>
                  <?php if ($myStatus === 'pending'): ?>
                  <button type="submit" name="action" value="withdraw" class="hx-cta hx-cta--small hx-cta--danger">Retirer</button>
                  <?php endif; ?>
                </div>
              </form>
            </details>
            <?php elseif ($post['status'] === 'open' && !$boardCanRespond): ?>
            <p class="hx-muted">Seuls les cadres habilités aux coopérations de votre communauté peuvent répondre.</p>
            <?php endif; ?>
          </article>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </section>
      <?php endif; ?>
<?php require __DIR__ . '/partials/shell_close.php'; ?>
