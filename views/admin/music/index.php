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
      <a class="msc-tab<?= $tab === $k ? ' is-active' : '' ?>" href="<?= $h($base . '?onglet=' . $k) ?>"><span class="msc-tab__label"><?= $h($label) ?></span></a>
    <?php endforeach; ?>
    <a class="msc-tab" href="<?= $h($libraryUrl . '?vue=global') ?>"><span class="msc-tab__label">Ouvrir la bibliothèque</span><span class="msc-tab__sub"><?= $platform ? 'Composer la playlist globale' : 'Écouter, mettre en ligne' ?></span></a>
  </nav>

  <?php if ($tab === 'morceaux') : ?>
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
