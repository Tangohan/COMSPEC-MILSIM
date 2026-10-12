<?php

declare(strict_types=1);

/**
 * Musique (Mon espace) : bibliothèque musicale partagée avec l'app Musique de l'ATAK natif.
 *
 * @var string $view global|community|org|playlists|mine|moderation
 */
$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
$view = (string) ($view ?? 'global');
$q = (string) ($q ?? '');
$tracks = is_array($tracks ?? null) ? $tracks : [];
$playlist = is_array($playlist ?? null) ? $playlist : null;
$playlists = is_array($playlists ?? null) ? $playlists : [];
$editablePlaylists = is_array($editablePlaylists ?? null) ? $editablePlaylists : [];
$isPlatformAdmin = (bool) ($isPlatformAdmin ?? false);
$isOrgManager = (bool) ($isOrgManager ?? false);
$canEditPlaylist = (bool) ($canEditPlaylist ?? false);
$canEditTrack = $canEditTrack ?? static fn (array $t): bool => false;
$canModerateTrack = $canModerateTrack ?? static fn (array $t): bool => false;
$userId = (int) ($userId ?? 0);
$usage = (int) ($usageBytes ?? 0);
$quota = max(1, (int) ($quotaBytes ?? 1));
$base = url('back-office/ma-situation/musique');
$csrf = \App\Core\Csrf::field();
$csrfToken = \App\Core\Csrf::token();
$back = '?' . http_build_query(array_filter(['vue' => $view, 'liste' => $playlist !== null && $view !== 'global' ? (int) $playlist['id'] : null, 'q' => $q !== '' ? $q : null]));
$mb = static fn (int $b): string => number_format($b / 1048576, $b >= 10485760 ? 0 : 1, ',', ' ') . ' Mo';
$dur = static function (int $s): string {
    if ($s <= 0) {
        return '–';
    }

    return intdiv($s, 60) . ':' . str_pad((string) ($s % 60), 2, '0', STR_PAD_LEFT);
};
$scopeLabel = ['community' => 'Communauté', 'org' => 'Organisation'];
$visLabel = ['global' => 'Globale', 'public' => 'Publique', 'org' => 'Organisation', 'private' => 'Privée'];
$visHelp = [
    'private' => 'Vous seul',
    'org' => 'Les membres de votre organisation',
    'public' => 'Toute la plateforme',
];
$tabs = [
    'global' => ['Playlist globale', 'La sélection de la plateforme'],
    'community' => ['Communauté', 'Toute la plateforme'],
    'org' => ['Mon organisation', $tenantName ?? ''],
    'playlists' => ['Playlists', 'Publiques, organisation, les miennes'],
    'mine' => ['Mes envois', $mb($usage) . ' / ' . $mb($quota)],
];
if ($isOrgManager || $isPlatformAdmin) {
    $tabs['moderation'] = ['Modération', 'Morceaux masqués'];
}
$titles = [
    'community' => ['Bibliothèque communautaire', 'Morceaux partagés avec toute la plateforme.'],
    'org' => ['Bibliothèque de mon organisation', 'Morceaux réservés aux membres de votre organisation.'],
    'mine' => ['Mes envois', 'Les morceaux que vous avez mis en ligne.'],
    'moderation' => ['Modération', $isPlatformAdmin ? 'Morceaux masqués de votre organisation et de la bibliothèque communautaire.' : 'Morceaux masqués de votre organisation.'],
];
?>
<div class="msc" data-music data-csrf="<?= $h($csrfToken) ?>" data-base="<?= $h($base) ?>">

  <nav class="msc-tabs" aria-label="Bibliothèque musicale">
    <?php foreach ($tabs as $k => [$label, $sub]) : ?>
      <a class="msc-tab<?= $view === $k ? ' is-active' : '' ?>" href="<?= $h($base . '?vue=' . $k) ?>">
        <span class="msc-tab__label"><?= $h($label) ?></span>
        <?php if ($sub !== '') : ?><span class="msc-tab__sub"><?= $h($sub) ?></span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <details class="msc-upload ath-form"<?= $view === 'mine' && $tracks === [] ? ' open' : '' ?>>
    <summary class="msc-upload__summary">
      <span class="msc-upload__plus" aria-hidden="true">+</span>
      <span><strong>Mettre un morceau en ligne</strong> <span class="msc-muted">MP3, M4A, AAC ou WAV, <?= $h($mb((int) ($maxBytes ?? 0))) ?> maximum. Écoutable sur le site et en jeu.</span></span>
    </summary>
    <form method="post" action="<?= $h($base . '/envoyer') ?>" enctype="multipart/form-data" class="msc-upload__form" data-music-upload>
      <?= $csrf ?>
      <input type="hidden" name="_back" value="<?= $h($back) ?>">
      <input type="hidden" name="duration" value="0" data-music-duration>
      <label class="msc-drop" data-music-drop>
        <input type="file" name="audio" accept=".mp3,.m4a,.aac,.wav,audio/mpeg,audio/mp4,audio/aac,audio/wav" required data-music-file>
        <span class="msc-drop__text" data-music-drop-text>Déposez un fichier audio ici ou cliquez pour choisir</span>
      </label>
      <div class="ath-form__grid">
        <label class="ath-field"><span class="ath-field__label">Titre</span><input class="ath-field__input" name="title" maxlength="160" placeholder="Repris du nom du fichier si vide" data-music-title></label>
        <label class="ath-field"><span class="ath-field__label">Artiste</span><input class="ath-field__input" name="artist" maxlength="120"></label>
        <label class="ath-field"><span class="ath-field__label">Genre</span><input class="ath-field__input" name="genre" maxlength="60" placeholder="Rock, ambiance, radio…"></label>
        <label class="ath-field"><span class="ath-field__label">Bibliothèque</span>
          <select class="ath-field__select" name="scope">
            <option value="org"<?= $view !== 'community' ? ' selected' : '' ?>>Mon organisation (ses membres seulement)</option>
            <option value="community"<?= $view === 'community' ? ' selected' : '' ?>>Communauté (toute la plateforme)</option>
          </select>
        </label>
        <?php if ($editablePlaylists !== []) : ?>
          <label class="ath-field"><span class="ath-field__label">Ajouter aussi à</span>
            <select class="ath-field__select" name="playlist_id">
              <option value="0">Aucune playlist</option>
              <?php foreach ($editablePlaylists as $p) : ?>
                <option value="<?= (int) $p['id'] ?>"<?= $playlist !== null && (int) $playlist['id'] === (int) $p['id'] ? ' selected' : '' ?>><?= $h($p['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        <?php endif; ?>
      </div>
      <p class="ath-field__help">Ne mettez en ligne que des morceaux que vous avez le droit de partager. Les gestionnaires de votre organisation (et l'administrateur de la plateforme pour la bibliothèque communautaire) peuvent masquer ou supprimer un morceau.</p>
      <div class="ath-form__actions"><button type="submit" class="ath-btn ath-btn--solid">Mettre en ligne</button></div>
    </form>
  </details>

  <?php if ($view === 'playlists' && $playlist === null) : ?>
    <section class="msc-card">
      <header class="msc-card__head">
        <div>
          <p class="msc-kicker">Playlists</p>
          <h2 class="msc-title">Playlists que vous pouvez écouter</h2>
        </div>
      </header>
      <div class="msc-plgrid">
        <?php foreach ($playlists as $p) : ?>
          <?php $vis = (string) $p['visibility']; ?>
          <a class="msc-pl msc-pl--<?= $h($vis) ?>" href="<?= $h($base . '?vue=' . ($vis === 'global' ? 'global' : 'playlists&liste=' . (int) $p['id'])) ?>">
            <span class="msc-pl__cover" aria-hidden="true">♪</span>
            <span class="msc-pl__body">
              <span class="msc-pl__name"><?= $h($p['name']) ?></span>
              <span class="msc-pl__meta"><span class="msc-badge msc-badge--<?= $h($vis) ?>"><?= $h($visLabel[$vis] ?? $vis) ?></span> <?= (int) $p['track_count'] ?> morceau(x)<?= $vis !== 'global' ? ' · ' . $h($p['owner_name']) : '' ?></span>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
      <form method="post" action="<?= $h($base . '/playlists/creer') ?>" class="msc-plcreate">
        <?= $csrf ?>
        <input type="hidden" name="_back" value="<?= $h($back) ?>">
        <h3 class="msc-subtitle">Nouvelle playlist</h3>
        <div class="ath-form__grid">
          <label class="ath-field"><span class="ath-field__label">Nom</span><input class="ath-field__input" name="name" maxlength="120" required></label>
          <label class="ath-field"><span class="ath-field__label">Visible par</span>
            <select class="ath-field__select" name="visibility">
              <?php foreach ($visHelp as $k => $label) : ?><option value="<?= $h($k) ?>"><?= $h($visLabel[$k] . ' : ' . $label) ?></option><?php endforeach; ?>
            </select>
          </label>
          <label class="ath-field ath-field--wide"><span class="ath-field__label">Description</span><input class="ath-field__input" name="description" maxlength="500"></label>
        </div>
        <div class="ath-form__actions"><button type="submit" class="ath-btn ath-btn--solid">Créer la playlist</button></div>
      </form>
    </section>
  <?php else : ?>
    <section class="msc-card">
      <header class="msc-card__head">
        <?php if ($playlist !== null) : ?>
          <?php $vis = (string) $playlist['visibility']; ?>
          <div class="msc-plhead">
            <span class="msc-pl__cover msc-pl__cover--lg msc-pl--<?= $h($vis) ?>" aria-hidden="true">♪</span>
            <div>
              <p class="msc-kicker"><?= $vis === 'global' ? 'Playlist globale · choisie par l\'administrateur de la plateforme' : 'Playlist ' . $h(mb_strtolower($visLabel[$vis] ?? $vis)) . ' · ' . $h($playlist['owner_name']) ?></p>
              <h2 class="msc-title"><?= $h($playlist['name']) ?></h2>
              <?php if (trim((string) ($playlist['description'] ?? '')) !== '') : ?><p class="msc-muted"><?= $h($playlist['description']) ?></p><?php endif; ?>
            </div>
          </div>
        <?php else : ?>
          <div>
            <p class="msc-kicker"><?= $h($tabs[$view][0] ?? '') ?></p>
            <h2 class="msc-title"><?= $h($titles[$view][0] ?? '') ?></h2>
            <p class="msc-muted"><?= $h($titles[$view][1] ?? '') ?></p>
          </div>
        <?php endif; ?>
        <div class="msc-card__tools">
          <?php if (in_array($view, ['community', 'org', 'mine'], true) && $playlist === null) : ?>
            <form method="get" action="<?= $h($base) ?>" class="msc-search">
              <input type="hidden" name="vue" value="<?= $h($view) ?>">
              <input class="ath-field__input" type="search" name="q" value="<?= $h($q) ?>" placeholder="Titre, artiste, genre">
              <button class="ath-btn" type="submit">Rechercher</button>
            </form>
          <?php endif; ?>
          <?php if ($tracks !== [] && $view !== 'moderation') : ?>
            <button type="button" class="ath-btn ath-btn--solid" data-music-playall>▶ Tout écouter</button>
          <?php endif; ?>
        </div>
      </header>

      <?php if ($playlist !== null && $canEditPlaylist) : ?>
        <details class="msc-pledit">
          <summary>Modifier la playlist</summary>
          <form method="post" action="<?= $h($base . '/playlists/' . (int) $playlist['id'] . '/modifier') ?>">
            <?= $csrf ?>
            <input type="hidden" name="_back" value="<?= $h($back) ?>">
            <div class="ath-form__grid">
              <label class="ath-field"><span class="ath-field__label">Nom</span><input class="ath-field__input" name="name" maxlength="120" value="<?= $h($playlist['name']) ?>"></label>
              <?php if (($playlist['visibility'] ?? '') !== 'global') : ?>
                <label class="ath-field"><span class="ath-field__label">Visible par</span>
                  <select class="ath-field__select" name="visibility">
                    <?php foreach ($visHelp as $k => $label) : ?><option value="<?= $h($k) ?>"<?= $playlist['visibility'] === $k ? ' selected' : '' ?>><?= $h($visLabel[$k] . ' : ' . $label) ?></option><?php endforeach; ?>
                  </select>
                </label>
              <?php endif; ?>
              <label class="ath-field ath-field--wide"><span class="ath-field__label">Description</span><input class="ath-field__input" name="description" maxlength="500" value="<?= $h($playlist['description'] ?? '') ?>"></label>
            </div>
            <div class="ath-form__actions"><button type="submit" class="ath-btn ath-btn--solid">Enregistrer</button></div>
          </form>
          <?php if (($playlist['visibility'] ?? '') !== 'global') : ?>
            <form method="post" action="<?= $h($base . '/playlists/' . (int) $playlist['id'] . '/supprimer') ?>" onsubmit="return confirm('Supprimer cette playlist ? Les morceaux restent dans la bibliothèque.');">
              <?= $csrf ?>
              <button type="submit" class="ath-btn msc-danger">Supprimer la playlist</button>
            </form>
          <?php endif; ?>
        </details>
        <?php if (($playlist['visibility'] ?? '') === 'global') : ?>
          <p class="msc-note">Vous tenez la playlist globale : ajoutez-y des morceaux depuis l'onglet Communauté (bouton « + Playlist »). Elle est proposée à toute la plateforme, sur le site et en jeu.</p>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ($tracks === []) : ?>
        <div class="msc-empty">
          <span class="msc-empty__icon" aria-hidden="true">♪</span>
          <p><?php
            echo match (true) {
                $view === 'global' => $isPlatformAdmin ? 'La playlist globale est vide. Ajoutez-y des morceaux depuis l\'onglet Communauté.' : 'La playlist globale est vide pour l\'instant.',
                $playlist !== null => 'Cette playlist est vide. Ajoutez-y des morceaux avec « + Playlist ».',
                $view === 'moderation' => 'Aucun morceau masqué.',
                $q !== '' => 'Aucun morceau ne correspond à « ' . $h($q) . ' ».',
                default => 'Aucun morceau ici pour l\'instant. Mettez le premier en ligne.',
            };
          ?></p>
        </div>
      <?php else : ?>
        <ol class="msc-tracks" data-music-list>
          <?php foreach ($tracks as $i => $t) : ?>
            <?php
              $tid = (int) $t['id'];
              $own = (int) $t['uploader_user_id'] === $userId;
              $editable = $canEditTrack($t);
              $moderable = $canModerateTrack($t);
              $hidden = ($t['status'] ?? '') === 'hidden';
            ?>
            <li class="msc-track<?= $hidden ? ' is-hidden' : '' ?>" data-music-track data-id="<?= $tid ?>" data-src="<?= $h($base . '/ecouter/' . $tid) ?>" data-title="<?= $h($t['title']) ?>" data-artist="<?= $h($t['artist'] ?? '') ?>" data-duration="<?= (int) $t['duration_seconds'] ?>">
              <button type="button" class="msc-play" data-music-play aria-label="Écouter <?= $h($t['title']) ?>"><span class="msc-play__n"><?= $i + 1 ?></span><span class="msc-play__icon" aria-hidden="true">▶</span></button>
              <div class="msc-track__main">
                <span class="msc-track__title"><?= $h($t['title']) ?></span>
                <span class="msc-track__meta">
                  <?= $h(($t['artist'] ?? '') !== '' ? $t['artist'] : 'Artiste inconnu') ?>
                  <?php if (($t['genre'] ?? '') !== '') : ?> · <?= $h($t['genre']) ?><?php endif; ?>
                  · <span class="msc-badge msc-badge--<?= $h($t['scope']) ?>"><?= $h($scopeLabel[$t['scope']] ?? $t['scope']) ?></span>
                  · <?= $own ? 'vous' : $h($t['uploader_name']) ?><?= ($t['scope'] ?? '') === 'community' && (int) $t['tenant_id'] !== (int) ($tenantId ?? 0) && ($t['tenant_name'] ?? '') !== '' ? ' (' . $h($t['tenant_name']) . ')' : '' ?>
                  <?php if ($hidden) : ?> · <strong class="msc-hiddenflag">masqué</strong><?php endif; ?>
                </span>
              </div>
              <span class="msc-track__dur" data-music-dur><?= $h($dur((int) $t['duration_seconds'])) ?></span>
              <span class="msc-track__plays" title="Écoutes"><?= (int) $t['play_count'] ?> ♫</span>
              <div class="msc-track__actions">
                <?php if ($editablePlaylists !== [] && !$hidden) : ?>
                  <details class="msc-pop">
                    <summary class="ath-btn">+ Playlist</summary>
                    <div class="msc-pop__panel">
                      <?php foreach ($editablePlaylists as $p) : ?>
                        <?php if (($p['visibility'] ?? '') === 'global' && ($t['scope'] ?? '') !== 'community') {
                            continue;
                        } ?>
                        <form method="post" action="<?= $h($base . '/playlists/' . (int) $p['id'] . '/ajouter') ?>">
                          <?= $csrf ?>
                          <input type="hidden" name="_back" value="<?= $h($back) ?>">
                          <input type="hidden" name="track_id" value="<?= $tid ?>">
                          <button type="submit" class="msc-pop__item"><?= $h($p['name']) ?> <span class="msc-muted"><?= $h($visLabel[$p['visibility']] ?? '') ?></span></button>
                        </form>
                      <?php endforeach; ?>
                      <a class="msc-pop__item msc-muted" href="<?= $h($base . '?vue=playlists') ?>">Nouvelle playlist…</a>
                    </div>
                  </details>
                <?php endif; ?>
                <?php if ($playlist !== null && $canEditPlaylist) : ?>
                  <form method="post" action="<?= $h($base . '/playlists/' . (int) $playlist['id'] . '/deplacer') ?>" class="msc-inline">
                    <?= $csrf ?><input type="hidden" name="_back" value="<?= $h($back) ?>"><input type="hidden" name="track_id" value="<?= $tid ?>">
                    <button type="submit" name="dir" value="-1" class="ath-btn msc-icon" title="Monter"<?= $i === 0 ? ' disabled' : '' ?>>↑</button>
                    <button type="submit" name="dir" value="1" class="ath-btn msc-icon" title="Descendre"<?= $i === count($tracks) - 1 ? ' disabled' : '' ?>>↓</button>
                  </form>
                  <form method="post" action="<?= $h($base . '/playlists/' . (int) $playlist['id'] . '/retirer') ?>" class="msc-inline">
                    <?= $csrf ?><input type="hidden" name="_back" value="<?= $h($back) ?>"><input type="hidden" name="track_id" value="<?= $tid ?>">
                    <button type="submit" class="ath-btn msc-icon" title="Retirer de la playlist">✕</button>
                  </form>
                <?php endif; ?>
                <?php if ($editable || $moderable) : ?>
                  <details class="msc-pop msc-pop--right">
                    <summary class="ath-btn msc-icon" title="Plus">⋯</summary>
                    <div class="msc-pop__panel msc-pop__panel--form">
                      <?php if ($editable) : ?>
                        <form method="post" action="<?= $h($base . '/morceaux/' . $tid . '/modifier') ?>">
                          <?= $csrf ?><input type="hidden" name="_back" value="<?= $h($back) ?>">
                          <label class="ath-field"><span class="ath-field__label">Titre</span><input class="ath-field__input" name="title" maxlength="160" value="<?= $h($t['title']) ?>"></label>
                          <label class="ath-field"><span class="ath-field__label">Artiste</span><input class="ath-field__input" name="artist" maxlength="120" value="<?= $h($t['artist'] ?? '') ?>"></label>
                          <label class="ath-field"><span class="ath-field__label">Genre</span><input class="ath-field__input" name="genre" maxlength="60" value="<?= $h($t['genre'] ?? '') ?>"></label>
                          <label class="ath-field"><span class="ath-field__label">Bibliothèque</span>
                            <select class="ath-field__select" name="scope">
                              <option value="org"<?= $t['scope'] === 'org' ? ' selected' : '' ?>>Mon organisation</option>
                              <option value="community"<?= $t['scope'] === 'community' ? ' selected' : '' ?>>Communauté (toute la plateforme)</option>
                            </select>
                          </label>
                          <button type="submit" class="ath-btn ath-btn--solid">Enregistrer</button>
                        </form>
                      <?php endif; ?>
                      <?php if ($moderable) : ?>
                        <form method="post" action="<?= $h($base . '/morceaux/' . $tid . '/moderer') ?>">
                          <?= $csrf ?><input type="hidden" name="_back" value="<?= $h($back) ?>">
                          <input type="hidden" name="status" value="<?= $hidden ? 'active' : 'hidden' ?>">
                          <?php if (!$hidden) : ?><input class="ath-field__input" name="note" maxlength="255" placeholder="Motif (facultatif)"><?php endif; ?>
                          <button type="submit" class="ath-btn"><?= $hidden ? 'Rétablir' : 'Masquer (modération)' ?></button>
                        </form>
                      <?php endif; ?>
                      <form method="post" action="<?= $h($base . '/morceaux/' . $tid . '/supprimer') ?>" onsubmit="return confirm('Supprimer définitivement ce morceau ? Il disparaît aussi des playlists.');">
                        <?= $csrf ?><input type="hidden" name="_back" value="<?= $h($back) ?>">
                        <button type="submit" class="ath-btn msc-danger">Supprimer</button>
                      </form>
                    </div>
                  </details>
                <?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <aside class="msc-ingame">
    <span class="msc-ingame__icon" aria-hidden="true">📱</span>
    <p><strong>En jeu :</strong> ouvrez l'app Musique de l'ATAK, onglet ATHENA. La playlist globale, la bibliothèque communautaire, celle de votre organisation, les playlists et vos envois y sont, une fois le téléphone lié à votre compte. En haut-parleur, les joueurs proches entendent le même morceau.</p>
  </aside>

  <div class="msc-player" data-music-player hidden>
    <button type="button" class="msc-player__btn" data-music-prev aria-label="Précédent">⏮</button>
    <button type="button" class="msc-player__btn msc-player__btn--main" data-music-toggle aria-label="Lecture / pause">⏸</button>
    <button type="button" class="msc-player__btn" data-music-next aria-label="Suivant">⏭</button>
    <div class="msc-player__info">
      <span class="msc-player__title" data-music-now>–</span>
      <div class="msc-player__bar">
        <span data-music-cur>0:00</span>
        <input type="range" min="0" max="1000" value="0" data-music-seek aria-label="Position">
        <span data-music-len>0:00</span>
      </div>
    </div>
    <label class="msc-player__vol" title="Volume">🔊<input type="range" min="0" max="100" value="80" data-music-vol aria-label="Volume"></label>
    <audio preload="none" data-music-audio></audio>
  </div>
</div>
<script>
(function () {
  var root = document.querySelector('[data-music]');
  if (!root) return;
  var audio = root.querySelector('[data-music-audio]');
  var bar = root.querySelector('[data-music-player]');
  var rows = Array.prototype.slice.call(root.querySelectorAll('[data-music-track]'));
  var cur = -1;
  var fmt = function (s) { s = Math.max(0, Math.floor(s || 0)); return Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2); };
  var $ = function (sel) { return root.querySelector(sel); };
  try { var v = parseInt(localStorage.getItem('athMusicVol') || '80', 10); audio.volume = v / 100; $('[data-music-vol]').value = v; } catch (e) {}
  function play(i) {
    if (i < 0 || i >= rows.length) { audio.pause(); return; }
    rows.forEach(function (r) { r.classList.remove('is-playing'); });
    cur = i;
    var r = rows[i];
    r.classList.add('is-playing');
    audio.src = r.dataset.src;
    audio.play().catch(function () {});
    $('[data-music-now]').textContent = r.dataset.title + (r.dataset.artist ? ' · ' + r.dataset.artist : '');
    bar.hidden = false;
  }
  rows.forEach(function (r, i) {
    r.querySelector('[data-music-play]').addEventListener('click', function () {
      if (cur === i) { if (audio.paused) audio.play(); else audio.pause(); return; }
      play(i);
    });
  });
  var all = $('[data-music-playall]');
  if (all) all.addEventListener('click', function () { play(0); });
  $('[data-music-toggle]').addEventListener('click', function () { if (audio.paused) audio.play(); else audio.pause(); });
  $('[data-music-next]').addEventListener('click', function () { play(cur + 1); });
  $('[data-music-prev]').addEventListener('click', function () { if (audio.currentTime > 5) audio.currentTime = 0; else play(Math.max(0, cur - 1)); });
  audio.addEventListener('ended', function () { play(cur + 1); });
  audio.addEventListener('play', function () { $('[data-music-toggle]').textContent = '⏸'; if (rows[cur]) rows[cur].classList.add('is-playing'); });
  audio.addEventListener('pause', function () { $('[data-music-toggle]').textContent = '▶'; });
  audio.addEventListener('timeupdate', function () {
    $('[data-music-cur]').textContent = fmt(audio.currentTime);
    if (audio.duration) $('[data-music-seek]').value = Math.round(audio.currentTime / audio.duration * 1000);
  });
  audio.addEventListener('loadedmetadata', function () {
    $('[data-music-len]').textContent = fmt(audio.duration);
    var r = rows[cur];
    // Durée inconnue à l'envoi : lue ici et enregistrée pour l'app du téléphone.
    if (r && r.dataset.duration === '0' && isFinite(audio.duration) && audio.duration > 0) {
      r.dataset.duration = String(Math.round(audio.duration));
      var d = r.querySelector('[data-music-dur]'); if (d) d.textContent = fmt(audio.duration);
      var fd = new FormData(); fd.append('_csrf_token', root.dataset.csrf); fd.append('duration', String(audio.duration));
      fetch(root.dataset.base + '/morceaux/' + r.dataset.id + '/duree', { method: 'POST', body: fd, credentials: 'same-origin' }).catch(function () {});
    }
  });
  $('[data-music-seek]').addEventListener('input', function (e) { if (audio.duration) audio.currentTime = e.target.value / 1000 * audio.duration; });
  $('[data-music-vol]').addEventListener('input', function (e) { audio.volume = e.target.value / 100; try { localStorage.setItem('athMusicVol', e.target.value); } catch (x) {} });
  // Envoi : durée et titre lus depuis le fichier choisi, glisser-déposer.
  var file = root.querySelector('[data-music-file]');
  if (file) {
    var drop = root.querySelector('[data-music-drop]');
    var pick = function () {
      var f = file.files && file.files[0];
      if (!f) return;
      root.querySelector('[data-music-drop-text]').textContent = f.name + ' · ' + (f.size / 1048576).toFixed(1) + ' Mo';
      var t = root.querySelector('[data-music-title]');
      if (t && !t.value) t.value = f.name.replace(/\.[^.]+$/, '').replace(/[_]+/g, ' ');
      try {
        var probe = new Audio(); probe.preload = 'metadata';
        probe.onloadedmetadata = function () { if (isFinite(probe.duration)) root.querySelector('[data-music-duration]').value = String(Math.round(probe.duration)); URL.revokeObjectURL(probe.src); };
        probe.src = URL.createObjectURL(f);
      } catch (e) {}
    };
    file.addEventListener('change', pick);
    ['dragenter', 'dragover'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('is-over'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function () { drop.classList.remove('is-over'); }); });
    drop.addEventListener('drop', function (e) { e.preventDefault(); if (e.dataTransfer.files.length) { file.files = e.dataTransfer.files; pick(); } });
  }
  // Un seul menu ouvert à la fois.
  root.addEventListener('toggle', function (e) {
    if (!e.target.classList || !e.target.classList.contains('msc-pop') || !e.target.open) return;
    root.querySelectorAll('.msc-pop[open]').forEach(function (d) { if (d !== e.target) d.open = false; });
  }, true);
})();
</script>
