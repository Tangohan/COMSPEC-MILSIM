<?php

declare(strict_types=1);

/**
 * Administration de la musique : /admin/musique (plateforme) et /back-office/ressources/musique (gestionnaires).
 *
 * @var bool $platform
 * @var string $tab morceaux|playlists|stats
 */
$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
$platform = (bool) ($platform ?? false);
$base = (string) ($base ?? '');
$tab = (string) ($tab ?? 'morceaux');
$filters = is_array($filters ?? null) ? $filters : [];
$page = max(1, (int) ($page ?? 1));
$perPage = max(1, (int) ($perPage ?? 50));
$tracks = is_array($tracks ?? null) ? $tracks : [];
$total = (int) ($total ?? 0);
$stats = is_array($stats ?? null) ? $stats : [];
$uploaders = is_array($uploaders ?? null) ? $uploaders : [];
$tenants = is_array($tenants ?? null) ? $tenants : [];
$playlists = is_array($playlists ?? null) ? $playlists : [];
$globalPlaylist = is_array($globalPlaylist ?? null) ? $globalPlaylist : [];
$globalIds = array_flip(array_map('intval', is_array($globalIds ?? null) ? $globalIds : []));
$quota = max(1, (int) ($quotaBytes ?? 1));
$libraryUrl = (string) ($libraryUrl ?? url('back-office/ma-situation/musique'));
$csrf = \App\Core\Csrf::field();
$mb = static fn (int $b): string => number_format($b / 1048576, $b >= 10485760 ? 0 : 1, ',', ' ') . ' Mo';
$dur = static fn (int $s): string => $s <= 0 ? '–' : intdiv($s, 60) . ':' . str_pad((string) ($s % 60), 2, '0', STR_PAD_LEFT);
$date = static function (mixed $raw): string {
    try {
        return (new \DateTimeImmutable((string) $raw))->format('d/m/Y');
    } catch (\Throwable) {
        return '–';
    }
};
$scopeLabel = ['community' => 'Communauté', 'org' => 'Organisation'];
$visLabel = ['global' => 'Globale', 'public' => 'Publique', 'org' => 'Organisation', 'private' => 'Privée'];
$query = array_filter([
    'q' => (string) ($filters['q'] ?? ''),
    'portee' => (string) ($filters['scope'] ?? ''),
    'etat' => (string) ($filters['status'] ?? ''),
    'organisation' => (int) ($filters['tenant'] ?? 0) ?: '',
], static fn ($v): bool => $v !== '' && $v !== 0);
$back = '?' . http_build_query($query + ($page > 1 ? ['page' => $page] : []));
$pages = max(1, (int) ceil($total / $perPage));
$pageUrl = static fn (int $p): string => $base . '?' . http_build_query($query + ['page' => $p]);
$tabs = [
    'morceaux' => 'Morceaux (' . (int) ($stats['tracks'] ?? 0) . ')',
    'playlists' => 'Playlists (' . count($playlists) . ')',
    'stats' => 'Statistiques',
];
?>
<div class="msc msca">
  <?php if ($platform) : ?>
    <header class="msca-hero">
      <p class="msc-kicker">Plateforme · Musique</p>
      <h1 class="msca-hero__title">Bibliothèque musicale</h1>
      <p class="msca-hero__lead">Tous les morceaux et playlists mis en ligne sur Athena, écoutés sur le site et en jeu dans l'app Musique de l'ATAK natif (onglet ATHENA). Vous seul composez la playlist globale.</p>
    </header>
    <?php foreach (['error' => 'err', 'success' => 'ok'] as $k => $cls) : ?>
      <?php if (!empty(${$k})) : ?><div class="msca-flash msca-flash--<?= $cls ?>" role="<?= $k === 'error' ? 'alert' : 'status' ?>"><?= $h(${$k}) ?></div><?php endif; ?>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="msca-kpis" aria-label="Synthèse">
    <div class="msca-kpi"><span class="msca-kpi__label">Morceaux</span><span class="msca-kpi__value"><?= (int) ($stats['tracks'] ?? 0) ?></span><span class="msca-kpi__sub">+<?= (int) ($stats['week'] ?? 0) ?> cette semaine</span></div>
    <div class="msca-kpi"><span class="msca-kpi__label">Masqués</span><span class="msca-kpi__value"><?= (int) ($stats['hidden'] ?? 0) ?></span><span class="msca-kpi__sub">invisibles sur le site et en jeu</span></div>
    <div class="msca-kpi"><span class="msca-kpi__label">Communauté</span><span class="msca-kpi__value"><?= (int) ($stats['community'] ?? 0) ?></span><span class="msca-kpi__sub">partagés avec toute la plateforme</span></div>
    <div class="msca-kpi"><span class="msca-kpi__label">Stockage</span><span class="msca-kpi__value"><?= $h($mb((int) ($stats['bytes'] ?? 0))) ?></span><span class="msca-kpi__sub"><?= (int) ($stats['uploaders'] ?? 0) ?> contributeur(s), <?= $h($mb($quota)) ?> max chacun</span></div>
    <div class="msca-kpi"><span class="msca-kpi__label">Écoutes</span><span class="msca-kpi__value"><?= (int) ($stats['plays'] ?? 0) ?></span><span class="msca-kpi__sub">site et jeu</span></div>
  </div>

  <nav class="msc-tabs" aria-label="Administration de la musique">
    <?php foreach ($tabs as $k => $label) : ?>
      <a class="msc-tab<?= $tab === $k ? ' is-active' : '' ?>" href="<?= $h($base . '?onglet=' . $k) ?>"<?= $tab === $k ? ' aria-current="page"' : '' ?>><span class="msc-tab__icon" aria-hidden="true"><?= ['morceaux' => '♪', 'playlists' => '≡', 'stats' => '▤'][$k] ?></span><span class="msc-tab__label"><?= $h($label) ?></span></a>
    <?php endforeach; ?>
    <a class="msc-tab" href="<?= $h($libraryUrl . '?vue=global') ?>"><span class="msc-tab__icon" aria-hidden="true">★</span><span class="msc-tab__label">Ouvrir la bibliothèque</span><span class="msc-tab__sub"><?= $platform ? 'Composer la playlist globale' : 'Écouter, mettre en ligne' ?></span></a>
  </nav>

  <?php if ($tab === 'morceaux') : ?>
    <details class="msc-upload ath-form msc-card"<?= (int) ($stats['tracks'] ?? 0) === 0 ? ' open' : '' ?>>
      <summary class="msc-upload__summary">
        <span class="msc-upload__plus" aria-hidden="true">+</span>
        <span><strong>Mettre des morceaux en ligne</strong> <span class="msc-muted">Un ou plusieurs fichiers MP3, M4A, AAC ou WAV, 25 Mo maximum chacun<?= $platform ? ', sans limite de quota pour vous' : '' ?>. Écoutables sur le site et en jeu.</span></span>
      </summary>
      <form method="post" action="<?= $h($base . '/envoyer') ?>" enctype="multipart/form-data" class="msc-upload__form" data-msca-upload>
        <?= $csrf ?>
        <input type="hidden" name="durations" value="" data-msca-durations>
        <label class="msc-drop" data-msca-drop>
          <input type="file" name="audio[]" accept="audio/mpeg,audio/mp4,audio/aac,audio/wav,audio/x-wav,.mp3,.m4a,.aac,.wav" multiple required data-msca-file>
          <span class="msc-drop__icon" aria-hidden="true">♫</span>
          <span class="msc-drop__text" data-msca-drop-text>Déposez des fichiers audio ici ou cliquez pour choisir </span>
          <span class="msc-drop__hint">Plusieurs fichiers à la fois · MP3, M4A, AAC ou WAV · 25 Mo maximum chacun</span>
        </label>
        <div class="ath-form__grid">
          <label class="ath-field"><span class="ath-field__label">Titre</span><input class="ath-field__input" name="title" maxlength="160" placeholder="Nom du fichier si vide ou si plusieurs fichiers"></label>
          <label class="ath-field"><span class="ath-field__label">Artiste</span><input class="ath-field__input" name="artist" maxlength="120" placeholder="Pour tous les fichiers envoyés"></label>
          <label class="ath-field"><span class="ath-field__label">Genre</span><input class="ath-field__input" name="genre" maxlength="60" placeholder="Rock, ambiance, radio…"></label>
          <label class="ath-field"><span class="ath-field__label">Pseudo affiché</span><input class="ath-field__input" name="uploader_alias" maxlength="80" value="<?= $h((string) ($uploaderDefaultAlias ?? '')) ?>" placeholder="Votre indicatif par défaut"><span class="ath-field__help">Nom montré comme auteur de l’envoi sur le site et dans l’ATAK. Vide : votre indicatif.</span></label>
          <label class="ath-field"><span class="ath-field__label">Bibliothèque</span>
            <select class="ath-field__select" name="scope" data-msca-scope>
              <option value="community"<?= $platform ? ' selected' : '' ?>>Communauté (toute la plateforme)</option>
              <option value="org"<?= $platform ? '' : ' selected' ?>>Organisation seulement</option>
            </select>
          </label>
          <?php $plChoices = array_values(array_filter($playlists, static fn (array $p): bool => ($p['visibility'] ?? '') !== 'global')); ?>
          <?php if ($plChoices !== []) : ?>
            <label class="ath-field"><span class="ath-field__label">Ajouter aussi à</span>
              <select class="ath-field__select" name="playlist_id">
                <option value="0">Aucune playlist</option>
                <?php foreach ($plChoices as $p) : ?><option value="<?= (int) $p['id'] ?>"><?= $h($p['name']) ?><?= $platform && ($p['tenant_name'] ?? '') !== '' ? ' (' . $h($p['tenant_name']) . ')' : '' ?></option><?php endforeach; ?>
              </select>
            </label>
          <?php endif; ?>
        </div>
        <?php if ($platform) : ?>
          <label class="msca-check"><input type="checkbox" name="global" value="1" data-msca-global> Ajouter directement à la <strong>playlist globale</strong> (le morceau passe en bibliothèque communautaire)</label>
        <?php endif; ?>
        <p class="ath-field__help">Ne mettez en ligne que des morceaux que vous avez le droit de partager.</p>
        <div class="msca-dup" data-msca-dup hidden role="alert">
          <p class="msca-dup__title">Doublons probables</p>
          <p class="msca-dup__text">Ces fichiers ressemblent à des morceaux déjà en ligne. Vérifiez avant d’envoyer : un doublon occupe du stockage et encombre les playlists.</p>
          <ul class="msca-dup__list" data-msca-dup-list></ul>
          <label class="msca-check"><input type="checkbox" data-msca-dup-ok> Je confirme qu’il ne s’agit pas de doublons (autre version, autre morceau du même nom…)</label>
          <div class="ath-form__actions">
            <button type="button" class="ath-btn" data-msca-dup-cancel>Annuler et revoir la sélection</button>
            <button type="button" class="ath-btn ath-btn--solid" data-msca-dup-force disabled>Mettre en ligne quand même</button>
          </div>
        </div>
        <input type="hidden" name="confirm_duplicates" value="" data-msca-dup-flag>
        <div class="msca-progress" data-msca-progress hidden></div>
        <div class="ath-form__actions"><button type="submit" class="ath-btn ath-btn--solid" data-msca-submit>Mettre en ligne</button></div>
      </form>
    </details>
    <script>
    (function () {
      var form = document.querySelector('[data-msca-upload]');
      if (!form || !window.fetch || !window.FormData) return;
      var input = form.querySelector('[data-msca-file]');
      var text = form.querySelector('[data-msca-drop-text]');
      var drop = form.querySelector('[data-msca-drop]');
      var progress = form.querySelector('[data-msca-progress]');
      var submit = form.querySelector('[data-msca-submit]');
      var global = form.querySelector('[data-msca-global]');
      var scope = form.querySelector('[data-msca-scope]');
      var durations = [];
      if (global && scope) global.addEventListener('change', function () { if (global.checked) scope.value = 'community'; scope.disabled = global.checked; });
      ['dragenter', 'dragover'].forEach(function (e) { drop.addEventListener(e, function () { drop.classList.add('is-over'); }); });
      ['dragleave', 'drop'].forEach(function (e) { drop.addEventListener(e, function () { drop.classList.remove('is-over'); }); });
      function probe(file) {
        return new Promise(function (resolve) {
          var a = document.createElement('audio'); var u = URL.createObjectURL(file);
          var done = function (d) { URL.revokeObjectURL(u); resolve(isFinite(d) ? Math.round(d) : 0); };
          a.preload = 'metadata'; a.onloadedmetadata = function () { done(a.duration); }; a.onerror = function () { done(0); }; a.src = u;
          setTimeout(function () { done(0); }, 8000);
        });
      }
      input.addEventListener('change', function () {
        var files = Array.prototype.slice.call(input.files || []);
        text.textContent = files.length === 0 ? 'Déposez des fichiers audio ici ou cliquez pour choisir'
          : files.length === 1 ? files[0].name : files.length + ' fichiers choisis';
        durations = files.map(function () { return 0; });
        files.forEach(function (f, i) { probe(f).then(function (d) { durations[i] = d; }); });
      });
      var dupBox = form.querySelector('[data-msca-dup]');
      var dupList = form.querySelector('[data-msca-dup-list]');
      var dupOk = form.querySelector('[data-msca-dup-ok]');
      var dupForce = form.querySelector('[data-msca-dup-force]');
      var dupCancel = form.querySelector('[data-msca-dup-cancel]');
      var dupFlag = form.querySelector('[data-msca-dup-flag]');
      var titleInput = form.querySelector('input[name="title"]');
      var esc = function (v) { var d = document.createElement('div'); d.textContent = v == null ? '' : String(v); return d.innerHTML; };
      function titleFor(f, count) {
        if (count === 1 && titleInput && titleInput.value.trim() !== '') return titleInput.value.trim();
        return f.name.replace(/\.[^.]+$/, '').replace(/_+/g, ' ').trim();
      }
      if (dupOk) dupOk.addEventListener('change', function () { dupForce.disabled = !dupOk.checked; });
      if (dupCancel) dupCancel.addEventListener('click', function () { dupBox.hidden = true; dupOk.checked = false; dupForce.disabled = true; submit.disabled = false; });
      if (dupForce) dupForce.addEventListener('click', function () {
        // Seconde confirmation explicite avant de forcer l'envoi.
        if (!window.confirm('Mettre en ligne ces morceaux malgré les titres similaires déjà présents ?')) return;
        dupFlag.value = '1';
        dupBox.hidden = true;
        form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true }));
      });
      // Les titres changent : la confirmation précédente ne vaut plus.
      input.addEventListener('change', function () { dupFlag.value = ''; });
      if (titleInput) titleInput.addEventListener('input', function () { dupFlag.value = ''; });

      form.addEventListener('submit', function (ev) {
        var files = Array.prototype.slice.call(input.files || []);
        if (files.length === 0) return;
        ev.preventDefault();
        if (dupFlag.value !== '1') {
          submit.disabled = true;
          var check = new FormData();
          check.append('_csrf_token', (form.querySelector('input[name="_csrf_token"]') || {}).value || '');
          files.forEach(function (f) { check.append('titles[]', titleFor(f, files.length)); });
          fetch(form.action.replace(/\/envoyer$/, '/doublons'), { method: 'POST', body: check, credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (j) {
              var matches = (j && j.matches) || {};
              var keys = Object.keys(matches);
              if (keys.length === 0) { dupFlag.value = 'checked'; startUpload(files); return; }
              dupList.innerHTML = keys.map(function (k) {
                return '<li><strong>' + esc(k) + '</strong> ressemble à ' + matches[k].map(function (m) {
                  return '« ' + esc(m.title) + ' »' + (m.artist ? ' — ' + esc(m.artist) : '') + ' <span class="msc-muted">(envoyé par ' + esc(m.uploader_name) + (m.tenant_name ? ', ' + esc(m.tenant_name) : '') + ')</span>';
                }).join(', ') + '</li>';
              }).join('');
              dupOk.checked = false; dupForce.disabled = true; dupBox.hidden = false;
              dupBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
            })
            .catch(function () { dupFlag.value = 'checked'; startUpload(files); });
          return;
        }
        startUpload(files);
      });

      function startUpload(files) {
        if (scope) scope.disabled = false;
        submit.disabled = true; progress.hidden = false;
        var ok = 0, errors = [], i = 0;
        var next = function () {
          if (i >= files.length) {
            progress.textContent = ok + ' morceau(x) en ligne' + (errors.length ? ', ' + errors.length + ' refusé(s) : ' + errors.join(' ') : '.');
            if (errors.length === 0) { window.location.reload(); } else { submit.disabled = false; setTimeout(function () { if (ok > 0) window.location.reload(); }, 6000); }
            return;
          }
          var f = files[i];
          progress.textContent = 'Envoi ' + (i + 1) + ' / ' + files.length + ' : ' + f.name + '…';
          var fd = new FormData(form);
          fd.delete('audio[]'); fd.append('audio[]', f, f.name);
          fd.set('durations', String(durations[i] || 0));
          if (files.length > 1) fd.set('title', '');
          fetch(form.action, { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json().catch(function () { return { done: 0, errors: [f.name + ' : réponse inattendue du serveur (' + r.status + ').'] }; }); })
            .then(function (j) { ok += j.done || 0; errors = errors.concat(j.errors || []); })
            .catch(function () { errors.push(f.name + ' : envoi interrompu.'); })
            .then(function () { i++; next(); });
        };
        next();
      }
    })();
    </script>
    <section class="msc-card">
      <form method="get" action="<?= $h($base) ?>" class="msca-filters">
        <label class="ath-field"><span class="ath-field__label">Recherche</span><input class="ath-field__input" type="search" name="q" value="<?= $h($filters['q'] ?? '') ?>" placeholder="Titre, artiste, genre, indicatif"></label>
        <label class="ath-field"><span class="ath-field__label">Bibliothèque</span>
          <select class="ath-field__select" name="portee">
            <option value="">Toutes</option>
            <?php foreach ($scopeLabel as $k => $l) : ?><option value="<?= $k ?>"<?= ($filters['scope'] ?? '') === $k ? ' selected' : '' ?>><?= $h($l) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label class="ath-field"><span class="ath-field__label">État</span>
          <select class="ath-field__select" name="etat">
            <option value="">Tous</option>
            <option value="active"<?= ($filters['status'] ?? '') === 'active' ? ' selected' : '' ?>>Visibles</option>
            <option value="hidden"<?= ($filters['status'] ?? '') === 'hidden' ? ' selected' : '' ?>>Masqués</option>
          </select>
        </label>
        <?php if ($platform) : ?>
          <label class="ath-field"><span class="ath-field__label">Organisation</span>
            <select class="ath-field__select" name="organisation">
              <option value="">Toutes</option>
              <?php foreach ($tenants as $t) : ?><option value="<?= (int) $t['tenant_id'] ?>"<?= (int) ($filters['tenant'] ?? 0) === (int) $t['tenant_id'] ? ' selected' : '' ?>><?= $h($t['tenant_name'] ?: ('#' . (int) $t['tenant_id'])) ?> (<?= (int) $t['tracks'] ?>)</option><?php endforeach; ?>
            </select>
          </label>
        <?php endif; ?>
        <div class="msca-filters__actions"><button type="submit" class="ath-btn ath-btn--solid">Filtrer</button><?php if ($query !== []) : ?> <a class="ath-btn" href="<?= $h($base) ?>">Effacer</a><?php endif; ?></div>
      </form>

      <form method="post" action="<?= $h($base . '/actions') ?>" class="msca-bulk-form">
        <?= $csrf ?>
        <input type="hidden" name="_back" value="<?= $h($back) ?>">
        <div class="msca-bulk">
          <span class="msc-muted"><?= $total ?> morceau(x)<?= $pages > 1 ? ', page ' . $page . ' sur ' . $pages : '' ?>. Pour la sélection :</span>
          <select class="ath-field__select" name="action" required>
            <option value="">Choisir une action</option>
            <option value="hide">Masquer</option>
            <option value="restore">Rétablir</option>
            <option value="community">Passer en bibliothèque communautaire</option>
            <option value="org">Passer en bibliothèque d'organisation</option>
            <?php if ($platform) : ?>
              <option value="global_add">Ajouter à la playlist globale</option>
              <option value="global_remove">Retirer de la playlist globale</option>
            <?php endif; ?>
            <option value="delete">Supprimer définitivement</option>
          </select>
          <input class="ath-field__input msca-bulk__note" name="note" maxlength="255" placeholder="Motif (masquage, facultatif)">
          <button type="submit" class="ath-btn ath-btn--solid" onclick="var a=this.form.elements['action'].value;return a!=='delete'||confirm('Supprimer définitivement les morceaux cochés et leurs fichiers ?');">Appliquer</button>
        </div>

        <?php if ($tracks === []) : ?>
          <p class="msc-empty">Aucun morceau ne correspond.</p>
        <?php else : ?>
          <div class="msca-table-wrap">
            <table class="msca-table">
              <thead>
                <tr>
                  <th scope="col"><input type="checkbox" aria-label="Tout cocher" onclick="for (const c of this.closest('table').querySelectorAll('input[name=&quot;ids[]&quot;]')) c.checked = this.checked;"></th>
                  <th scope="col">Morceau</th>
                  <th scope="col">Envoyé par</th>
                  <th scope="col">Bibliothèque</th>
                  <th scope="col">Durée</th>
                  <th scope="col">Taille</th>
                  <th scope="col">Écoutes</th>
                  <th scope="col">Ajouté</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($tracks as $t) : ?>
                  <?php $id = (int) $t['id']; $hidden = ($t['status'] ?? '') === 'hidden'; ?>
                  <tr class="<?= $hidden ? 'is-hidden' : '' ?>">
                    <td><input type="checkbox" name="ids[]" value="<?= $id ?>" aria-label="Sélectionner <?= $h($t['title']) ?>"></td>
                    <td>
                      <strong><?= $h($t['title']) ?></strong>
                      <?php if (isset($globalIds[$id])) : ?> <span class="msc-badge msc-badge--global">Playlist globale</span><?php endif; ?>
                      <?php if ($hidden) : ?> <span class="msc-badge">Masqué</span><?php endif; ?>
                      <span class="msca-sub"><?= $h(trim(($t['artist'] ?? '') . (($t['genre'] ?? '') !== '' ? ' · ' . $t['genre'] : ''))) ?: '&nbsp;' ?></span>
                      <?php if ($hidden && trim((string) ($t['moderation_note'] ?? '')) !== '') : ?><span class="msca-sub">Motif : <?= $h($t['moderation_note']) ?></span><?php endif; ?>
                    </td>
                    <td><?= $h($t['uploader_name']) ?><?php if ($platform) : ?><span class="msca-sub"><?= $h($t['tenant_name'] ?? '') ?></span><?php endif; ?></td>
                    <td><span class="msc-badge msc-badge--<?= $h($t['scope']) ?>"><?= $h($scopeLabel[$t['scope']] ?? $t['scope']) ?></span><?php if ((int) ($t['playlist_count'] ?? 0) > 0) : ?><span class="msca-sub"><?= (int) $t['playlist_count'] ?> playlist(s)</span><?php endif; ?></td>
                    <td><?= $h($dur((int) ($t['duration_seconds'] ?? 0))) ?></td>
                    <td><?= $h($mb((int) ($t['size_bytes'] ?? 0))) ?><span class="msca-sub"><?= $h(strtoupper((string) ($t['file_ext'] ?? ''))) ?></span></td>
                    <td><?= (int) ($t['play_count'] ?? 0) ?></td>
                    <td><?= $h($date($t['created_at'] ?? '')) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </form>

      <?php if ($pages > 1) : ?>
        <nav class="msca-pager" aria-label="Pages">
          <?php if ($page > 1) : ?><a class="ath-btn" href="<?= $h($pageUrl($page - 1)) ?>">Précédent</a><?php endif; ?>
          <span class="msc-muted">Page <?= $page ?> sur <?= $pages ?></span>
          <?php if ($page < $pages) : ?><a class="ath-btn" href="<?= $h($pageUrl($page + 1)) ?>">Suivant</a><?php endif; ?>
        </nav>
      <?php endif; ?>
      <p class="ath-field__help">Un morceau masqué disparaît du site et de l'ATAK sans être supprimé. Un morceau repassé en bibliothèque d'organisation sort de la playlist globale. <?= $platform ? 'Seuls les morceaux communautaires visibles peuvent entrer dans la playlist globale.' : 'Vous n\'agissez que sur les morceaux de votre organisation.' ?></p>
    </section>

  <?php elseif ($tab === 'playlists') : ?>
    <section class="msc-card">
      <header class="msc-card__head">
        <div>
          <p class="msc-kicker">Playlists</p>
          <h2 class="msc-title"><?= $platform ? 'Toutes les playlists de la plateforme' : 'Playlists de votre organisation' ?></h2>
        </div>
      </header>
      <?php if ($playlists === []) : ?>
        <p class="msc-empty">Aucune playlist pour l'instant.</p>
      <?php else : ?>
        <div class="msca-table-wrap">
          <table class="msca-table">
            <thead><tr><th scope="col">Playlist</th><th scope="col">Visible par</th><th scope="col">Créée par</th><th scope="col">Morceaux</th><th scope="col">Mise à jour</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
            <tbody>
              <?php foreach ($playlists as $p) : ?>
                <?php $vis = (string) ($p['visibility'] ?? 'private'); ?>
                <tr>
                  <td><strong><?= $h($p['name']) ?></strong><?php if (trim((string) ($p['description'] ?? '')) !== '') : ?><span class="msca-sub"><?= $h($p['description']) ?></span><?php endif; ?></td>
                  <td><span class="msc-badge msc-badge--<?= $h($vis) ?>"><?= $h($visLabel[$vis] ?? $vis) ?></span></td>
                  <td><?= $h($vis === 'global' ? 'Administrateur' : $p['owner_name']) ?><?php if ($platform && $vis !== 'global') : ?><span class="msca-sub"><?= $h($p['tenant_name'] ?? '') ?></span><?php endif; ?></td>
                  <td><?= (int) ($p['track_count'] ?? 0) ?></td>
                  <td><?= $h($date($p['updated_at'] ?? '')) ?></td>
                  <td class="msca-actions">
                    <?php if ($vis === 'global') : ?>
                      <a class="ath-btn" href="<?= $h($libraryUrl . '?vue=global') ?>"><?= $platform ? 'Composer' : 'Écouter' ?></a>
                    <?php else : ?>
                      <?php if ($vis !== 'private') : ?><a class="ath-btn" href="<?= $h($libraryUrl . '?vue=playlists&liste=' . (int) $p['id']) ?>">Ouvrir</a><?php endif; ?>
                      <form method="post" action="<?= $h($base . '/playlists/' . (int) $p['id'] . '/supprimer') ?>" onsubmit="return confirm('Supprimer cette playlist ? Les morceaux restent dans la bibliothèque.');">
                        <?= $csrf ?>
                        <button type="submit" class="ath-btn ath-btn--danger">Supprimer</button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
      <p class="ath-field__help">Supprimer une playlist ne supprime pas ses morceaux. La playlist globale ne peut pas être supprimée<?= $platform ? ' : composez-la depuis la bibliothèque (onglet Playlist globale).' : '.' ?></p>
    </section>

  <?php else : ?>
    <?php if ($platform) : ?>
      <section class="msc-card">
        <header class="msc-card__head"><div><p class="msc-kicker">Répartition</p><h2 class="msc-title">Par organisation</h2></div></header>
        <?php if ($tenants === []) : ?>
          <p class="msc-empty">Aucun morceau en ligne.</p>
        <?php else : ?>
          <div class="msca-table-wrap">
            <table class="msca-table">
              <thead><tr><th scope="col">Organisation</th><th scope="col">Morceaux</th><th scope="col">Communauté</th><th scope="col">Masqués</th><th scope="col">Stockage</th><th scope="col">Écoutes</th></tr></thead>
              <tbody>
                <?php foreach ($tenants as $t) : ?>
                  <tr>
                    <td><a href="<?= $h($base . '?organisation=' . (int) $t['tenant_id']) ?>"><?= $h($t['tenant_name'] ?: ('#' . (int) $t['tenant_id'])) ?></a></td>
                    <td><?= (int) $t['tracks'] ?></td>
                    <td><?= (int) $t['community'] ?></td>
                    <td><?= (int) $t['hidden'] ?></td>
                    <td><?= $h($mb((int) $t['bytes'])) ?></td>
                    <td><?= (int) $t['plays'] ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>
    <section class="msc-card">
      <header class="msc-card__head"><div><p class="msc-kicker">Contributeurs</p><h2 class="msc-title">Plus gros envois</h2></div></header>
      <?php if ($uploaders === []) : ?>
        <p class="msc-empty">Aucun morceau en ligne.</p>
      <?php else : ?>
        <div class="msca-table-wrap">
          <table class="msca-table">
            <thead><tr><th scope="col">Membre</th><?php if ($platform) : ?><th scope="col">Organisation</th><?php endif; ?><th scope="col">Morceaux</th><th scope="col">Quota utilisé</th><th scope="col">Écoutes</th></tr></thead>
            <tbody>
              <?php foreach ($uploaders as $u) : ?>
                <?php $pct = min(100, (int) round(((int) $u['bytes']) * 100 / $quota)); ?>
                <tr>
                  <td><?= $h($u['name']) ?></td>
                  <?php if ($platform) : ?><td><?= $h($u['tenant_name'] ?? '') ?></td><?php endif; ?>
                  <td><?= (int) $u['tracks'] ?></td>
                  <td><span class="msca-meter" aria-hidden="true"><span style="width: <?= $pct ?>%"></span></span> <?= $h($mb((int) $u['bytes'])) ?> (<?= $pct ?> %)</td>
                  <td><?= (int) $u['plays'] ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</div>
