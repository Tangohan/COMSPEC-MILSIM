<?php
declare(strict_types=1);

/**
 * Zone vivante de Consignes et kit ATAK : page complète, ou fragment renvoyé après chaque action.
 * Tout texte de membre ou du jeu est échappé et marqué translate="no".
 *
 * @var array<string, mixed> $form
 */

use App\Support\AtakCommunityBrief as Brief;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$base = url('back-office/ressources/atak-consignes');
$csrf = \App\Core\Csrf::field();
$notes = is_array($form['notes'] ?? null) ? $form['notes'] : [];
$kit = is_array($form['kit'] ?? null) ? $form['kit'] : [];
$genre = (string) ($form['genre'] ?? '');
$notesRev = (string) ($form['notesRev'] ?? '');
$kitRev = (string) ($form['kitRev'] ?? '');
$unread = is_array($form['unread'] ?? null) ? $form['unread'] : ['notes_over' => 0, 'notes_cut' => 0, 'kit' => []];
$fit = is_array($form['fit'] ?? null) ? $form['fit'] : ['bytes' => 0, 'fits' => true, 'notes_kept' => 0, 'kit_kept' => 0];
$catalogSize = (int) ($form['catalogSize'] ?? 0);
$picks = is_array($form['picks'] ?? null) ? $form['picks'] : [];
$q = (string) ($form['q'] ?? '');
$ready = !empty($form['ready']);
$fmtDate = static function (mixed $raw): string {
    $t = is_string($raw) && $raw !== '' ? strtotime($raw) : false;

    return $t ? date('d/m/Y H:i', $t) : '';
};
$updated = $fmtDate($form['updatedAt'] ?? '');
$kindLabels = ['consigne' => 'Consigne', 'ordre' => 'Ordre', 'tip' => 'Tip'];
$kindPlurals = ['consigne' => 'Consignes', 'ordre' => 'Ordres', 'tip' => 'Tips'];
$kindCounts = array_fill_keys(Brief::KINDS, 0);
foreach ($notes as $n) {
    ++$kindCounts[$n['kind']];
}
$genreField = '<input type="hidden" name="genre" value="' . $h($genre) . '">';
$filterUrl = static fn (string $g): string => $g === '' ? $base . '#consignes' : $base . '?genre=' . $g . '#consignes';
$percent = (int) min(100, round($fit['bytes'] * 100 / Brief::MAX_PHONE_BYTES));

/** Formulaire d'une note : ajout (place null) ou modification. */
$noteForm = static function (?int $index, ?array $note) use ($h, $base, $csrf, $notesRev, $genreField, $kindLabels): string {
    $current = $note['kind'] ?? 'consigne';
    ob_start();
    ?>
    <form method="post" action="<?= $h($base . '/notes') ?>" class="bo-brief-form" data-brief-form>
      <?= $csrf ?>
      <input type="hidden" name="rev" value="<?= $h($notesRev) ?>">
      <input type="hidden" name="index" value="<?= $index === null ? '' : $index ?>">
      <?= $genreField ?>
      <label class="bo-brief-f"><span>Genre</span>
        <select name="kind">
          <?php foreach ($kindLabels as $value => $label): ?>
            <option value="<?= $h($value) ?>"<?= $current === $value ? ' selected' : '' ?>><?= $h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="bo-brief-f"><span>Titre <small data-count-out><?= Brief::MAX_TITLE ?> caractères au plus</small></span>
        <input type="text" name="note_title" maxlength="<?= Brief::MAX_TITLE ?>" value="<?= $h($note['title'] ?? '') ?>" translate="no" autocomplete="off" data-count>
      </label>
      <label class="bo-brief-f"><span>Texte <small data-count-out><?= Brief::MAX_BODY ?> caractères au plus</small></span>
        <textarea name="note_body" rows="5" maxlength="<?= Brief::MAX_BODY ?>" translate="no" data-count><?= $h($note['body'] ?? '') ?></textarea>
        <small>Texte brut, sur une seule ligne dans le téléphone : un retour à la ligne devient une espace. Titre ou texte, l’un des deux suffit.</small>
      </label>
      <div class="bo-brief-form__f">
        <button type="button" class="ath-btn" data-brief-close hidden>Annuler</button>
        <button type="submit" class="ath-btn ath-btn--solid"><?= $index === null ? 'Ajouter la note' : 'Enregistrer' ?></button>
      </div>
    </form>
    <?php

    return (string) ob_get_clean();
};

/** Ligne d'objet du sélecteur (mêmes balises que le script de recherche). */
$pickRow = static function (array $item, string $type, string $name, bool $checked) use ($h): string {
    $icon = (string) ($item['icon'] ?? '') !== ''
        ? '<img src="' . $h($item['icon']) . '" alt="" loading="lazy">'
        : '<span class="bo-brief-noicon" aria-hidden="true"></span>';
    $meta = trim((string) ($item['kindLabel'] ?? ''));
    $mod = trim((string) ($item['mod'] ?? ''));

    return '<label class="bo-brief-opt"><input type="' . $type . '" name="' . $name . '" value="' . $h($item['class']) . '"' . ($checked ? ' checked' : '') . '>'
        . '<span class="bo-brief-ico">' . $icon . '</span>'
        . '<span class="bo-brief-opt__t"><b translate="no">' . $h($item['name']) . '</b><code translate="no">' . $h($item['class']) . '</code>'
        . '<small>' . $h($meta) . ($mod !== '' ? ' · <span translate="no">' . $h($mod) . '</span>' : '') . '</small></span></label>';
};

/** Formulaire d'un objet du kit. Recherche et listes sont pilotées par le script ; sans lui, liste du serveur et saisie avancée. */
$kitForm = static function (?int $index, ?array $entry, array $list) use ($h, $base, $csrf, $kitRev, $genreField, $pickRow, $catalogSize): string {
    $items = $entry['items'] ?? [];
    $selected = $items[0] ?? null;
    $variants = array_slice($items, 1);
    ob_start();
    ?>
    <form method="post" action="<?= $h($base . '/kit') ?>" class="bo-brief-form" data-brief-form>
      <?= $csrf ?>
      <input type="hidden" name="rev" value="<?= $h($kitRev) ?>">
      <input type="hidden" name="index" value="<?= $index === null ? '' : $index ?>">
      <?= $genreField ?>
      <fieldset class="bo-brief-f" data-brief-pick="radio" data-pick-name="class_pick">
        <legend>Objet demandé</legend>
        <input type="search" placeholder="Chercher un nom, une classe ou un mod…" aria-label="Chercher un objet dans le catalogue" autocomplete="off" data-pick-q hidden>
        <div class="bo-brief-opts" data-pick-list>
          <?php if ($selected !== null): ?><?= $pickRow($selected, 'radio', 'class_pick', true) ?><?php endif; ?>
          <?php foreach ($list as $item): ?>
            <?php if ($selected === null || strcasecmp((string) $item['class'], (string) $selected['class']) !== 0): ?>
              <?= $pickRow($item, 'radio', 'class_pick', false) ?>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
        <p class="bo-brief-hint" role="status" data-pick-status></p>
      </fieldset>
      <div class="bo-brief-form__row">
        <label class="bo-brief-f"><span>Quantité</span>
          <input type="number" name="count" min="1" max="<?= Brief::MAX_COUNT ?>" value="<?= (int) ($entry['count'] ?? 1) ?>" required>
        </label>
        <label class="bo-brief-f bo-brief-f--grow"><span>Libellé <small data-count-out><?= Brief::MAX_TITLE ?> caractères au plus</small></span>
          <input type="text" name="label" maxlength="<?= Brief::MAX_TITLE ?>" value="<?= $h($entry['label'] ?? '') ?>" translate="no" autocomplete="off" placeholder="Facultatif : remplace le nom de l’objet" data-count>
          <small>Sans virgule ni point-virgule.</small>
        </label>
      </div>
      <fieldset class="bo-brief-f" data-brief-pick="checkbox" data-pick-name="variants[]">
        <legend>Variantes acceptées (facultatif)</legend>
        <input type="search" placeholder="Chercher une variante…" aria-label="Chercher une variante dans le catalogue" autocomplete="off" data-pick-q hidden>
        <div class="bo-brief-opts" data-pick-list>
          <?php foreach ($variants as $item): ?><?= $pickRow($item, 'checkbox', 'variants[]', true) ?><?php endforeach; ?>
        </div>
        <p class="bo-brief-hint" role="status" data-pick-status></p>
        <small><?= Brief::MAX_VARIANTS ?> classes au plus pour une entrée, objet compris. Le joueur en règle en porte une, ou un mélange pour atteindre la quantité.</small>
      </fieldset>
      <details class="bo-brief-adv"<?= $catalogSize < 1 ? ' open' : '' ?>>
        <summary>Option avancée : objet pas encore remonté du jeu</summary>
        <label class="bo-brief-f"><span>Classe Arma de l’objet</span>
          <input type="text" name="class_manual" maxlength="64" pattern="[A-Za-z0-9_]{1,64}" translate="no" autocomplete="off" spellcheck="false" placeholder="ACE_fieldDressing">
          <small>Remplace l’objet choisi ci-dessus. Lettres sans accent, chiffres et _.</small>
        </label>
        <label class="bo-brief-f"><span>Variantes, en classes Arma</span>
          <input type="text" name="variants_manual" translate="no" autocomplete="off" spellcheck="false" placeholder="ACE_elasticBandage ACE_packingBandage">
          <small>Séparées par une espace ou une virgule.</small>
        </label>
      </details>
      <div class="bo-brief-form__f">
        <button type="button" class="ath-btn" data-brief-close hidden>Annuler</button>
        <button type="submit" class="ath-btn ath-btn--solid"><?= $index === null ? 'Ajouter au kit' : 'Enregistrer' ?></button>
      </div>
    </form>
    <?php

    return (string) ob_get_clean();
};

/** Dépliant sans JavaScript, fenêtre avec : le script ouvre le formulaire dans la fenêtre au clic sur le résumé. */
$fold = static function (string $summary, string $kicker, string $title, string $inner, string $class = 'ath-btn ath-btn--sm') use ($h): string {
    return '<details class="bo-brief-fold" data-brief-fold data-kicker="' . $h($kicker) . '" data-title="' . $h($title) . '">'
        . '<summary class="' . $h($class) . '">' . $h($summary) . '</summary><div class="bo-brief-fold__b">' . $inner . '</div></details>';
};

/** Confirmation de suppression. */
$removeForm = static function (string $action, string $rev, int $index, string $question, string $label) use ($h, $csrf, $genreField): string {
    return '<form method="post" action="' . $h($action) . '" class="bo-brief-form" data-brief-form>' . $csrf
        . '<input type="hidden" name="rev" value="' . $h($rev) . '"><input type="hidden" name="index" value="' . $index . '">' . $genreField
        . '<p class="effk-md__txt">' . $h($question) . '</p>'
        . '<div class="bo-brief-form__f"><button type="button" class="ath-btn" data-brief-close hidden>Annuler</button>'
        . '<button type="submit" class="ath-btn ath-btn--danger">' . $h($label) . '</button></div></form>';
};
?>
<?php if (!$ready): ?>
  <p class="bo-brief-note-box is-bad">La base de données n’est pas à jour : rien ne peut être enregistré ici pour le moment. Contactez le support plateforme.</p>
<?php endif; ?>

<nav class="bo-brief-parts" aria-label="Parties de la page">
  <a href="#consignes">Consignes <b><?= count($notes) ?> / <?= Brief::MAX_NOTES ?></b></a>
  <a href="#kit">Kit obligatoire <b><?= count($kit) ?> / <?= Brief::MAX_KIT ?></b></a>
  <a href="<?= $h(url('back-office/alerts')) ?>">Centre de diffusion</a>
</nav>
<p class="bo-brief-hint">Ces consignes, ordres et tips apparaissent aussi dans le centre de diffusion, avec les annonces du site et les messages en jeu : ce sont les mêmes notes, à modifier ici ou là-bas.</p>

<?php if ($unread['notes_over'] > 0 || $unread['notes_cut'] > 0 || $unread['kit'] !== []): ?>
  <div class="bo-brief-note-box is-warn">
    <p><b>Une partie du texte déjà enregistré n’est pas lue par le jeu.</b> Elle disparaîtra à la prochaine modification de la liste concernée : recopiez-la si vous en avez besoin.</p>
    <?php if ($unread['notes_over'] > 0): ?><p><?= (int) $unread['notes_over'] ?> note(s) au-delà du plafond de <?= Brief::MAX_NOTES ?>.</p><?php endif; ?>
    <?php if ($unread['notes_cut'] > 0): ?><p><?= (int) $unread['notes_cut'] ?> note(s) raccourcie(s) aux plafonds de longueur.</p><?php endif; ?>
    <?php if ($unread['kit'] !== []): ?><p>Entrées du kit illisibles ou de trop : <code translate="no"><?= $h(implode(' · ', $unread['kit'])) ?></code></p><?php endif; ?>
  </div>
<?php endif; ?>

<div class="bo-brief-grid">
  <div class="bo-brief-main">
    <section class="ath-panel bo-brief-sec" id="consignes" aria-labelledby="bo-brief-notes-t">
      <header class="bo-brief-sec__h">
        <div>
          <h2 id="bo-brief-notes-t">Consignes, ordres et tips</h2>
          <p class="bo-brief-hint"><?= count($notes) ?> note(s) sur <?= Brief::MAX_NOTES ?>. Titre : <?= Brief::MAX_TITLE ?> caractères au plus. Texte : <?= Brief::MAX_BODY ?> caractères au plus.</p>
        </div>
        <?php if (count($notes) < Brief::MAX_NOTES): ?>
          <?= $fold('Ajouter une note', 'Nouvelle note', 'Ajouter une note', $noteForm(null, null), 'ath-btn ath-btn--solid') ?>
        <?php else: ?>
          <span class="ath-tag ath-tag--warn">Plafond atteint</span>
        <?php endif; ?>
      </header>
      <div class="bo-brief-filter" role="group" aria-label="Filtrer par genre">
        <a href="<?= $h($filterUrl('')) ?>" data-brief-filter=""<?= $genre === '' ? ' aria-current="true"' : '' ?>>Toutes <b><?= count($notes) ?></b></a>
        <?php foreach ($kindPlurals as $value => $label): ?>
          <a href="<?= $h($filterUrl($value)) ?>" data-brief-filter="<?= $h($value) ?>"<?= $genre === $value ? ' aria-current="true"' : '' ?>><?= $h($label) ?> <b><?= (int) $kindCounts[$value] ?></b></a>
        <?php endforeach; ?>
      </div>
      <?php if ($notes === []): ?>
        <p class="bo-brief-empty">Aucune note pour le moment. Ajoutez la première : les joueurs la liront dans l’app Communauté.</p>
      <?php elseif ($genre !== '' && $kindCounts[$genre] === 0): ?>
        <p class="bo-brief-empty">Aucune note de ce genre.</p>
      <?php endif; ?>
      <ol class="bo-brief-list">
        <?php foreach ($notes as $i => $note):
            if ($genre !== '' && $note['kind'] !== $genre) {
                continue;
            }
            $sameBefore = false;
            $sameAfter = false;
            foreach ($notes as $j => $other) {
                if ($genre === '' || $other['kind'] === $genre) {
                    $sameBefore = $sameBefore || $j < $i;
                    $sameAfter = $sameAfter || $j > $i;
                }
            }
            $name = $note['title'] !== '' ? $note['title'] : mb_substr($note['body'], 0, 60);
            ?>
          <li class="bo-brief-row" id="bo-brief-note-<?= $i ?>" tabindex="-1">
            <div class="bo-brief-row__t">
              <span class="bo-brief-kind is-<?= $h($note['kind']) ?>"><?= $h($kindLabels[$note['kind']]) ?></span>
              <?php if ($note['title'] !== ''): ?><b translate="no"><?= $h($note['title']) ?></b><?php endif; ?>
              <?php if ($note['body'] !== ''): ?><p translate="no"><?= $h($note['body']) ?></p><?php endif; ?>
            </div>
            <div class="bo-brief-row__a">
              <form method="post" action="<?= $h($base . '/notes/deplacer') ?>" class="bo-brief-move" data-brief-move>
                <?= $csrf ?>
                <input type="hidden" name="rev" value="<?= $h($notesRev) ?>">
                <input type="hidden" name="index" value="<?= $i ?>">
                <?= $genreField ?>
                <button type="submit" class="ath-btn ath-btn--sm" name="direction" value="up" aria-label="Monter cette note" title="Monter"<?= $sameBefore ? '' : ' disabled' ?>>↑</button>
                <button type="submit" class="ath-btn ath-btn--sm" name="direction" value="down" aria-label="Descendre cette note" title="Descendre"<?= $sameAfter ? '' : ' disabled' ?>>↓</button>
              </form>
              <?= $fold('Modifier', 'Modifier la note', $name, $noteForm($i, $note)) ?>
              <?= $fold('Supprimer', 'Supprimer la note', $name, $removeForm($base . '/notes/supprimer', $notesRev, $i, 'Cette note disparaîtra du téléphone de tous les joueurs à la prochaine lecture. Cette action ne peut pas être annulée.', 'Supprimer la note')) ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ol>
    </section>

    <section class="ath-panel bo-brief-sec" id="kit" aria-labelledby="bo-brief-kit-t">
      <header class="bo-brief-sec__h">
        <div>
          <h2 id="bo-brief-kit-t">Kit obligatoire</h2>
          <p class="bo-brief-hint"><?= count($kit) ?> objet(s) sur <?= Brief::MAX_KIT ?>. Comparé à ce que porte le joueur : équipé, sur lui ou dans son sac. Un kit posé par la mission reste prioritaire.</p>
        </div>
        <?php if (count($kit) >= Brief::MAX_KIT): ?>
          <span class="ath-tag ath-tag--warn">Plafond atteint</span>
        <?php endif; ?>
      </header>
      <?php if ($catalogSize < 1): ?>
        <div class="bo-brief-note-box is-info">
          <p><b>Le catalogue de votre communauté est vide.</b> Il se remplit tout seul quand des joueurs reliés à Athena entrent en partie avec le mod : ce qu’ils portent remonte, avec le nom et l’icône de chaque objet. Les tenues envoyées depuis l’arsenal en jeu le complètent aussi.</p>
          <p>Lancez une partie avec quelques joueurs, puis revenez ici. En attendant, ajoutez un objet par sa classe Arma, dans l’option avancée. <a href="<?= $h(url('back-office/equipement-joueurs')) ?>">Voir l’équipement des joueurs</a></p>
        </div>
      <?php endif; ?>
      <?php if (count($kit) < Brief::MAX_KIT): ?>
        <div class="bo-brief-add" id="kit-add">
          <?php
          $search = '<form method="get" action="' . $h($base) . '#kit-add" class="bo-brief-search" data-brief-nojs>'
              . '<label class="bo-brief-f"><span>Chercher dans le catalogue</span><input type="search" name="q" value="' . $h($q) . '" translate="no"></label>'
              . '<button type="submit" class="ath-btn ath-btn--sm">Chercher</button></form>';
          echo $fold('Ajouter un objet', 'Kit obligatoire', 'Ajouter un objet au kit', $search . $kitForm(null, null, $picks), 'ath-btn ath-btn--solid');
          ?>
        </div>
      <?php endif; ?>
      <?php if ($kit === []): ?>
        <p class="bo-brief-empty">Aucun kit obligatoire : les joueurs n’ont rien à vérifier.</p>
      <?php else: ?>
        <ul class="bo-brief-list">
          <?php foreach ($kit as $i => $entry):
              $first = $entry['items'][0];
              $others = array_slice($entry['items'], 1);
              $name = $entry['label'] !== '' ? $entry['label'] : $first['name'];
              ?>
            <li class="bo-brief-row" id="bo-brief-kit-<?= $i ?>" tabindex="-1">
              <span class="bo-brief-ico bo-brief-ico--lg"><?php if ($first['icon'] !== ''): ?><img src="<?= $h($first['icon']) ?>" alt="" loading="lazy"><?php else: ?><span class="bo-brief-noicon" aria-hidden="true"></span><?php endif; ?></span>
              <div class="bo-brief-row__t">
                <b translate="no"><?= $h($first['name']) ?></b> <span class="bo-brief-qty">x <?= (int) $entry['count'] ?></span>
                <?php if (!$first['known']): ?><span class="ath-tag ath-tag--warn">Pas encore vu en jeu</span><?php endif; ?>
                <div><code translate="no"><?= $h($first['class']) ?></code></div>
                <?php if ($entry['label'] !== ''): ?><p>Libellé : <span translate="no"><?= $h($entry['label']) ?></span></p><?php endif; ?>
                <?php if ($others !== []): ?>
                  <p>Variantes acceptées :
                    <?php foreach ($others as $n => $other): ?><?= $n > 0 ? ', ' : '' ?><span translate="no" title="<?= $h($other['class']) ?>"><?= $h($other['name']) ?></span><?php endforeach; ?>
                  </p>
                <?php endif; ?>
              </div>
              <div class="bo-brief-row__a">
                <?= $fold('Modifier', 'Modifier l’objet', $name, $kitForm($i, $entry, [])) ?>
                <?= $fold('Retirer', 'Retirer du kit', $name, $removeForm($base . '/kit/supprimer', $kitRev, $i, 'Cet objet ne sera plus demandé aux joueurs à la prochaine lecture. Cette action ne peut pas être annulée.', 'Retirer du kit')) ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>

  <aside class="ath-panel bo-brief-sec bo-brief-preview" aria-labelledby="bo-brief-prev-t">
    <h2 id="bo-brief-prev-t">Ce que voit le joueur</h2>
    <p class="bo-brief-hint">App Communauté du téléphone. Les téléphones relisent ces données toutes les 5 minutes environ.</p>
    <?php if ($updated !== ''): ?><p class="bo-brief-hint">Dernière modification : <?= $h($updated) ?>.</p><?php endif; ?>
    <div class="bo-brief-meter<?= $fit['fits'] ? ($percent >= 90 ? ' is-warn' : '') : ' is-bad' ?>">
      <span>Volume envoyé au téléphone : <b><?= (int) $fit['bytes'] ?> / <?= Brief::MAX_PHONE_BYTES ?></b> octets</span>
      <i style="width:<?= $percent ?>%"></i>
    </div>
    <?php if (!$fit['fits']): ?>
      <p class="bo-brief-note-box is-bad">Trop de texte : le téléphone reçoit les notes d’abord, puis le kit, et s’arrête au plafond. Ce qui est barré ci-dessous n’arrive pas au joueur.</p>
    <?php endif; ?>
    <div class="bo-brief-phone">
      <?php foreach (['consigne' => 'CONSIGNES', 'ordre' => 'ORDRES', 'tip' => 'TIPS'] as $value => $tab): ?>
        <h3><?= $h($tab) ?></h3>
        <?php if ($value === 'consigne' && (string) ($form['guide'] ?? '') !== ''): ?>
          <details class="bo-brief-guide"><summary>Guide de la communauté (affiché au-dessus des consignes)</summary><pre translate="no"><?= $h($form['guide']) ?></pre></details>
        <?php endif; ?>
        <?php if ($kindCounts[$value] === 0): ?><p class="bo-brief-phone__none">Rien pour le moment.</p><?php endif; ?>
        <?php foreach ($notes as $i => $note): if ($note['kind'] !== $value) { continue; } ?>
          <div class="bo-brief-phone__row<?= $i >= $fit['notes_kept'] ? ' is-lost' : '' ?>">
            <?php if ($note['title'] !== ''): ?><b translate="no"><?= $h($note['title']) ?></b><?php endif; ?>
            <?php if ($note['body'] !== ''): ?><p translate="no"><?= $h($note['body']) ?></p><?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endforeach; ?>
      <h3>KIT</h3>
      <?php if ($kit === []): ?><p class="bo-brief-phone__none">Aucun kit obligatoire</p><?php endif; ?>
      <?php foreach ($kit as $i => $entry):
          $names = $entry['label'] !== '' ? $entry['label'] : implode(' / ', array_map(static fn (array $it): string => (string) $it['name'], $entry['items']));
          ?>
        <div class="bo-brief-phone__row bo-brief-phone__kit<?= $i >= $fit['kit_kept'] ? ' is-lost' : '' ?>"><span translate="no"><?= $h($names) ?></span><span>x <?= (int) $entry['count'] ?></span></div>
      <?php endforeach; ?>
    </div>
    <p class="bo-brief-hint">Sans libellé, le jeu affiche son propre nom de l’objet, qui peut différer légèrement. Le téléphone ajoute aussi les notes posées pour la partie par un admin ou un Zeus.</p>
  </aside>
</div>
