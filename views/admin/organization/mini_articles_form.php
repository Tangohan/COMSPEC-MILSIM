<?php
declare(strict_types=1);

use App\Core\Csrf;
use App\Support\MiniArticleHtml;

/**
 * Mini-article : rédaction à gauche (titre, résumé, éditeur riche), réglages et aperçu de la carte à droite.
 *
 * @var array<string, mixed>|null $miniArticle
 * @var string $formAction
 */
$row = is_array($miniArticle ?? null) ? $miniArticle : null;
$isEdit = $row !== null;
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$title = (string) ($row['title'] ?? '');
$excerpt = (string) ($row['excerpt'] ?? '');
$bodyHtml = (string) ($row['body_html'] ?? '');
$status = (string) ($row['status'] ?? 'draft');
$pinned = !empty($row['pinned']);
$tags = [];
$rawTags = $row['tags_json'] ?? null;
if (is_string($rawTags) && $rawTags !== '') {
    $decoded = json_decode($rawTags, true);
    if (is_array($decoded)) {
        $tags = array_values(array_filter(array_map('strval', $decoded)));
    }
}
$tagsValue = implode(', ', $tags);
$coverUrl = MiniArticleHtml::publicUrl(isset($row['cover_path']) ? (string) $row['cover_path'] : null);
$gallery = [];
$rawGal = $row['gallery_json'] ?? null;
if (is_string($rawGal) && $rawGal !== '') {
    $decoded = json_decode($rawGal, true);
    if (is_array($decoded)) {
        foreach ($decoded as $path) {
            $path = (string) $path;
            $url = MiniArticleHtml::publicUrl($path);
            if ($url !== null) {
                $gallery[] = ['path' => $path, 'url' => $url];
            }
        }
    }
}
?>
<div class="bo-ma">
    <form id="mini-article-form" method="post" action="<?= $h((string) $formAction) ?>" enctype="multipart/form-data" class="bo-ma__layout">
        <input type="hidden" name="_csrf_token" value="<?= $h(Csrf::token()) ?>">

        <div class="bo-ma__main">
            <section class="bo-ma__card bo-ma__writer">
                <label class="sr-only" for="ma-title">Titre</label>
                <input type="text" id="ma-title" name="title" required maxlength="255" value="<?= $h($title) ?>" class="bo-ma__title-input" placeholder="Titre de l’article">
                <label class="sr-only" for="ma-excerpt">Résumé</label>
                <textarea id="ma-excerpt" name="excerpt" rows="2" maxlength="2000" class="bo-ma__excerpt-input" placeholder="En une ou deux phrases : de quoi parle cet article ? (affiché sur la liste et le tableau de bord)"><?= $h($excerpt) ?></textarea>
                <div class="bo-ma__body">
                    <label class="bo-ma__label" for="ma-body">Contenu</label>
                    <textarea id="ma-body" name="body_html" rows="16" placeholder="Rédigez le corps de l’article…"><?= $h($bodyHtml) ?></textarea>
                    <p class="bo-ma__hint">Titres, listes, liens, tableaux et images. Le contenu est nettoyé à l’enregistrement.</p>
                </div>
            </section>
        </div>

        <aside class="bo-ma__aside">
            <section class="bo-ma__card">
                <h2 class="bo-ma__card-title">Publication</h2>
                <div class="bo-ma__seg" role="radiogroup" aria-label="Statut">
                    <label><input type="radio" name="status" value="draft" <?= $status !== 'published' ? 'checked' : '' ?>><span>Brouillon</span></label>
                    <label><input type="radio" name="status" value="published" <?= $status === 'published' ? 'checked' : '' ?>><span>Publié</span></label>
                </div>
                <p class="bo-ma__hint" data-ma-status-hint></p>
                <label class="bo-ma__check">
                    <input type="checkbox" name="pinned" value="1" <?= $pinned ? 'checked' : '' ?>>
                    <span>Épingler en tête de la liste</span>
                </label>
                <div class="bo-ma__submit">
                    <button type="submit" class="ath-btn ath-btn--solid"><?= $isEdit ? 'Enregistrer' : 'Créer l’article' ?></button>
                    <a href="<?= $h(url('back-office/articles')) ?>" class="ath-btn">Annuler</a>
                </div>
            </section>

            <section class="bo-ma__card">
                <h2 class="bo-ma__card-title">Image de couverture</h2>
                <label class="bo-ma__drop<?= $coverUrl !== null ? ' has-image' : '' ?>" for="ma-cover" data-ma-drop>
                    <img src="<?= $h((string) $coverUrl) ?>" alt="" data-ma-cover-img<?= $coverUrl === null ? ' hidden' : '' ?>>
                    <span class="bo-ma__drop-text" data-ma-cover-text<?= $coverUrl !== null ? ' hidden' : '' ?>>Choisir une image<br><small>JPG, PNG ou WebP, format paysage conseillé</small></span>
                </label>
                <input type="file" id="ma-cover" name="cover" accept="image/jpeg,image/png,image/webp" class="bo-ma__file">
                <?php if ($coverUrl !== null): ?>
                <label class="bo-ma__check"><input type="checkbox" name="remove_cover" value="1"><span>Retirer la couverture</span></label>
                <?php endif; ?>
            </section>

            <section class="bo-ma__card">
                <h2 class="bo-ma__card-title">Mots-clés</h2>
                <label class="sr-only" for="ma-tags">Mots-clés</label>
                <input type="text" id="ma-tags" name="tags" value="<?= $h($tagsValue) ?>" class="bo-ma__input" placeholder="ops, formation, radio">
                <div class="bo-ma__chips" data-ma-chips aria-hidden="true"></div>
                <p class="bo-ma__hint">Séparés par des virgules, 12 au maximum.</p>
            </section>

            <section class="bo-ma__card">
                <h2 class="bo-ma__card-title">Galerie <span class="bo-ma__hint">(6 images max.)</span></h2>
                <?php if ($gallery !== []): ?>
                <div class="bo-ma__gallery">
                    <?php foreach ($gallery as $g): ?>
                    <label class="bo-ma__gallery-item">
                        <img src="<?= $h($g['url']) ?>" alt="">
                        <span><input type="checkbox" name="remove_gallery[]" value="<?= $h($g['path']) ?>"> Retirer</span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <input type="file" id="ma-gallery" name="gallery[]" accept="image/jpeg,image/png,image/webp" multiple class="bo-ma__file bo-ma__file--visible" aria-label="Ajouter des images à la galerie">
            </section>

            <section class="bo-ma__card">
                <h2 class="bo-ma__card-title">Aperçu dans la liste</h2>
                <div class="bo-ma__preview">
                    <div class="bo-ma__preview-cover" data-ma-pv-cover<?= $coverUrl !== null ? ' style="background-image:url(\'' . $h($coverUrl) . '\')"' : '' ?>></div>
                    <div class="bo-ma__preview-body">
                        <p class="bo-ma__preview-title" data-ma-pv-title><?= $h($title !== '' ? $title : 'Titre de l’article') ?></p>
                        <p class="bo-ma__preview-excerpt" data-ma-pv-excerpt><?= $h($excerpt) ?></p>
                        <div class="bo-ma__chips" data-ma-pv-tags></div>
                    </div>
                </div>
            </section>
        </aside>
    </form>
</div>
<script>
(function () {
  var form = document.getElementById('mini-article-form');
  if (!form) return;
  var q = function (s) { return form.querySelector(s); };
  var title = q('#ma-title'), excerpt = q('#ma-excerpt'), tags = q('#ma-tags'), cover = q('#ma-cover');
  function chips(target) {
    target.textContent = '';
    tags.value.split(',').map(function (t) { return t.trim(); }).filter(Boolean).slice(0, 12).forEach(function (t) {
      var s = document.createElement('span'); s.className = 'bo-ma__chip'; s.textContent = '#' + t; target.appendChild(s);
    });
  }
  function render() {
    q('[data-ma-pv-title]').textContent = title.value.trim() || 'Titre de l’article';
    q('[data-ma-pv-excerpt]').textContent = excerpt.value.trim();
    chips(q('[data-ma-chips]'));
    chips(q('[data-ma-pv-tags]'));
    var published = (q('input[name="status"]:checked') || {}).value === 'published';
    q('[data-ma-status-hint]').textContent = published ? 'Visible par tous les membres dans « Articles ».' : 'Visible seulement des responsables tant qu’il reste en brouillon.';
  }
  if (cover) {
    cover.addEventListener('change', function () {
      var f = cover.files && cover.files[0];
      if (!f) return;
      var url = URL.createObjectURL(f);
      var img = q('[data-ma-cover-img]');
      img.src = url; img.hidden = false;
      q('[data-ma-cover-text]').hidden = true;
      q('[data-ma-drop]').classList.add('has-image');
      q('[data-ma-pv-cover]').style.backgroundImage = 'url("' + url + '")';
    });
  }
  form.addEventListener('input', render);
  form.addEventListener('change', render);
  render();
})();
</script>
<script src="<?= htmlspecialchars(asset_url('assets/js/mini_article_editor.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
