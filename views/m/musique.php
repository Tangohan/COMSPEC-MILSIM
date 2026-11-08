<?php

declare(strict_types=1);

/**
 * Lecteur de musique du téléphone : bibliothèque d'Athena, lue ici par le navigateur.
 * Sans JavaScript, chaque morceau garde son lecteur natif ; le script enchaîne simplement les morceaux.
 *
 * @var string $vue
 * @var string $q
 * @var string $heading
 * @var list<array{id: int, title: string, artist: string, duration: int, url: string, by: string}> $tracks
 * @var list<array{id: int, name: string, owner: string, count: int}> $playlists
 * @var bool $failed
 */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$clock = static fn (int $s): string => $s > 0 ? sprintf('%d:%02d', intdiv($s, 60), $s % 60) : '';
$views = ['global' => 'Globale', 'org' => 'Ma communauté', 'community' => 'Partagés', 'mine' => 'Mes envois', 'playlists' => 'Playlists'];
$subSet = 'radio';
$subActive = 'music';
require __DIR__ . '/_head.php';
require __DIR__ . '/_sub.php';
?>
    <p class="mm-muted">Ce lecteur joue sur ce téléphone. Ce que vous écoutez dans le jeu n’est pas transmis à Athena et n’apparaît pas ici.</p>
    <nav class="mm-chips" aria-label="Bibliothèque">
      <?php foreach ($views as $key => $label): ?>
        <a class="mm-chip<?= $vue === $key ? ' is-on' : '' ?>" href="<?= $h(url('m/musique?vue=' . $key)) ?>"<?= $vue === $key ? ' aria-current="page"' : '' ?>><?= $h($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <?php if (in_array($vue, ['org', 'community', 'mine'], true)): ?>
      <form class="mm-row" method="get" action="<?= $h(url('m/musique')) ?>">
        <input type="hidden" name="vue" value="<?= $h($vue) ?>">
        <input class="mm-input mm-grow" type="search" name="q" value="<?= $h($q) ?>" maxlength="80" placeholder="Titre, artiste, genre" aria-label="Rechercher un morceau">
        <button class="mm-btn" type="submit">Chercher</button>
      </form>
    <?php endif; ?>
    <h1 class="mm-h1" translate="no"><?= $h($heading) ?></h1>
    <?php if ($failed): ?>
      <section class="mm-card mm-card--warn"><p class="mm-muted">La bibliothèque ne répond pas pour le moment. Réessayez dans un instant.</p></section>
    <?php elseif ($vue === 'playlists'): ?>
      <?php if ($playlists === []): ?>
        <p class="mm-muted">Aucune playlist disponible.</p>
      <?php else: ?>
        <div class="mm-tiles">
          <?php foreach ($playlists as $p): ?>
            <a class="mm-tile" href="<?= $h(url('m/musique?vue=' . rawurlencode('pl:' . $p['id']))) ?>"><strong translate="no"><?= $h($p['name']) ?></strong><span><?= (int) $p['count'] ?> morceau(x)<?php if ($p['owner'] !== ''): ?> · <span translate="no"><?= $h($p['owner']) ?></span><?php endif; ?></span></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php elseif ($tracks === []): ?>
      <p class="mm-muted">Aucun morceau ici.</p>
    <?php else: ?>
      <ol class="mm-tracks" data-mm-tracks>
        <?php foreach ($tracks as $t): ?>
          <li class="mm-track">
            <div class="mm-track__head">
              <strong translate="no"><?= $h($t['title']) ?></strong>
              <span class="mm-mono"><?= $h($clock($t['duration'])) ?></span>
            </div>
            <span class="mm-muted" translate="no"><?= $h($t['artist'] !== '' ? $t['artist'] : $t['by']) ?></span>
            <audio controls preload="none" src="<?= $h($t['url']) ?>"></audio>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>
<?php require __DIR__ . '/_foot.php'; ?>
