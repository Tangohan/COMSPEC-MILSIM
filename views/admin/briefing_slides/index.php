<?php
declare(strict_types=1);

/**
 * Diapositives de briefing — charte ATHENA.
 *
 * L’en-tête de page est rendu par la coque back-office.
 *
 * Chaque diapositive est une fiche : la vignette porte l’information, et l’édition tient
 * dans la fiche plutôt que dans un écran séparé — on règle l’ordre et la visibilité en
 * voyant les images côte à côte.
 *
 * @var list<array<string,mixed>> $briefingSlides
 * @var string $briefingSlidesFeedUrl
 * @var int $briefingSlidesTenantId
 * @var array{total:int,active:int,inactive:int} $briefingSlidesStats
 * @var array<int,int> $briefingCommentCounts
 * @var list<array{label:string,source:string,last_seen_at:int}> $briefingPresence
 * @var string $briefingPresenceUrl
 * @var string $briefingGoogleSlidesUrl
 */

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$rows = is_array($briefingSlides ?? null) ? $briefingSlides : [];
$feedUrl = (string) ($briefingSlidesFeedUrl ?? '');
$tenantId = (int) ($briefingSlidesTenantId ?? 0);
$stats = is_array($briefingSlidesStats ?? null) ? $briefingSlidesStats : [];
$total = (int) ($stats['total'] ?? count($rows));
$active = (int) ($stats['active'] ?? 0);
$inactive = (int) ($stats['inactive'] ?? max(0, $total - $active));
$commentCounts = is_array($briefingCommentCounts ?? null) ? $briefingCommentCounts : [];
$presence = is_array($briefingPresence ?? null) ? $briefingPresence : [];
$presenceUrl = (string) ($briefingPresenceUrl ?? '');
$googleSlidesUrl = trim((string) ($briefingGoogleSlidesUrl ?? ''));
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');

$baseUrl = url('back-office/atak/briefing-slides');
$slideCount = count($rows);

$commentTotal = 0;
foreach ($commentCounts as $count) {
    $commentTotal += (int) $count;
}

$edenScreenSnippet = <<<'SQF'
this setVariable ["comspec_briefingScreenIndex", 0];
[[this, 0]] call comspec_overwatch_connect_fnc_setBriefingScreens;
[this, 0] spawn {
    params ["_obj", "_selIdx"];
    waitUntil { !isNull _obj };
    private _slides = missionNamespace getVariable ["COMSPEC_BriefingSlides", []];
    if (count _slides == 0) then { _slides = [] call comspec_overwatch_connect_fnc_getBriefingSlides; };
    if (count _slides > 0) then {
        private _path = [_slides select 0] call comspec_overwatch_connect_fnc_downloadBriefingSlide;
        if (_path != "") then { _obj setObjectTexture [_selIdx, _path]; };
    };
};
SQF;

$edenActionSnippet = <<<'SQF'
this addAction ["Consulter le briefing", { [] call comspec_overwatch_connect_fnc_openBriefingBoard; }];
SQF;

$nextOrder = 0;
foreach ($rows as $r) {
    $nextOrder = max($nextOrder, (int) ($r['sort_order'] ?? 0) + 1);
}

/** Libellé de la provenance d’un appareil qui consulte le briefing. */
$sourceLabel = static function (string $source): string {
    return match ($source) {
        'arma' => 'tableau en jeu',
        'admin' => 'poste de commandement',
        default => 'téléphone',
    };
};
?>
<div data-bo-briefing>

<?php if ($flashError): ?>
<p class="ath-flash ath-flash--err" role="alert"><?= $h((string) $flashError) ?></p>
<?php endif; ?>
<?php if ($flashSuccess): ?>
<p class="ath-flash ath-flash--ok" role="status"><?= $h((string) $flashSuccess) ?></p>
<?php endif; ?>

<?php
$athKpis = [
    ['label' => 'DIAPOSITIVES', 'value' => (string) $total, 'delta' => '', 'tone' => '#1e4f80', 'pct' => $total > 0 ? '100%' : '0%', 'note' => 'dans le catalogue'],
    ['label' => 'VISIBLES EN JEU', 'value' => (string) $active, 'delta' => '', 'tone' => $active > 0 ? '#0b8a5c' : '#c98a12', 'pct' => $total > 0 ? (string) (int) round($active / max(1, $total) * 100) . '%' : '0%', 'note' => 'proposées aux opérateurs'],
    ['label' => 'BROUILLONS', 'value' => (string) $inactive, 'delta' => '', 'tone' => $inactive === 0 ? '#0b8a5c' : '#c98a12', 'pct' => $total > 0 ? (string) (int) round($inactive / max(1, $total) * 100) . '%' : '0%', 'note' => 'masquées côté Arma'],
    ['label' => 'COMMENTAIRES', 'value' => (string) $commentTotal, 'delta' => '', 'tone' => '#1e4f80', 'pct' => '100%', 'note' => 'toutes diapositives'],
    ['label' => 'ATAK CONNECTÉS', 'value' => (string) count($presence), 'delta' => '', 'tone' => count($presence) > 0 ? '#0b8a5c' : '#8c979b', 'pct' => '100%', 'note' => 'pendant le briefing en cours'],
];
require base_path('views/partials/ath_kpis.php');
?>

<?php // Le compteur d’appareils est rafraîchi par le script en fin de page. ?>
<span data-presence-count hidden><?= count($presence) ?></span>

<div class="ath-note">
    <p class="ath-note__title">Comment ça arrive en jeu</p>
    <p class="ath-note__text">
        Seules les diapositives marquées <strong>visibles en jeu</strong> sont proposées aux opérateurs
        dans Arma et sur les téléphones ATAK. Les brouillons restent invisibles pour les joueurs :
        vous pouvez préparer un briefing complet avant de le rendre disponible.
    </p>
</div>

<div class="ath-columns" style="margin-bottom:16px;">
    <form method="post" action="<?= $h($baseUrl) ?>" enctype="multipart/form-data" class="ath-form" id="bo-briefing-create">
        <div class="ath-form__head">
            <span class="ath-form__title">Ajouter une diapositive</span>
            <span class="ath-form__hint">JPEG ou PNG.</span>
        </div>
        <?= \App\Core\Csrf::field() ?>
        <div class="ath-form__grid ath-form__grid--wide">
            <label class="ath-field">
                <span class="ath-field__label">Titre affiché</span>
                <input type="text" name="title" maxlength="160" class="ath-field__input" placeholder="Ordre d’opération — Phase 1">
            </label>
            <label class="ath-field">
                <span class="ath-field__label">Précisions</span>
                <textarea name="detail_text" rows="3" maxlength="8000" class="ath-field__textarea" placeholder="Contexte, consignes, points d’attention…"></textarea>
                <span class="ath-field__help">Vous pourrez enrichir ce texte après le briefing.</span>
            </label>
            <label class="ath-field">
                <span class="ath-field__label">Ordre d’affichage</span>
                <input type="number" name="sort_order" value="<?= (int) $nextOrder ?>" class="ath-field__input">
            </label>
            <label class="ath-field">
                <span class="ath-field__label">Image *</span>
                <input type="file" name="image_file" accept="image/jpeg,image/png" required class="ath-field__input" style="height:auto;padding:7px 10px;">
            </label>
        </div>
        <div class="ath-check-grid" style="margin-top:11px;">
            <label class="ath-check">
                <input type="checkbox" name="is_active" value="1">
                <span>Visible en jeu dès l’ajout</span>
            </label>
        </div>
        <div class="ath-form__actions">
            <button type="submit" class="ath-btn ath-btn--solid">Ajouter la diapositive</button>
        </div>
    </form>

    <div>
        <form method="post" action="<?= $h($baseUrl . '/google-url') ?>" class="ath-form">
            <div class="ath-form__head">
                <span class="ath-form__title">Présentation Google Slides</span>
                <span class="ath-form__hint">Optionnel</span>
            </div>
            <?= \App\Core\Csrf::field() ?>
            <div class="ath-form__grid ath-form__grid--wide">
                <label class="ath-field">
                    <span class="ath-field__label">Lien de la présentation</span>
                    <input type="url" name="google_slides_url" maxlength="512" value="<?= $h($googleSlidesUrl) ?>" class="ath-field__input" placeholder="https://docs.google.com/presentation/d/…">
                    <span class="ath-field__help">
                        Dans Google Slides : Partager → « Toute personne disposant du lien ».
                        Laissez vide pour retirer le lien.
                    </span>
                </label>
            </div>
            <div class="ath-form__actions">
                <button type="submit" class="ath-btn">Enregistrer le lien</button>
            </div>
        </form>

        <div class="ath-form">
            <div class="ath-form__head">
                <span class="ath-form__title">ATAK connectés</span>
                <span class="ath-form__hint">Rafraîchi automatiquement</span>
            </div>
            <ul class="ath-list" data-presence-list>
                <?php if ($presence === []): ?>
                <li><span class="ath-list__meta">Aucun appareil connecté pour le moment.</span></li>
                <?php else: ?>
                    <?php foreach ($presence as $viewer): ?>
                        <?php
                        $label = trim((string) ($viewer['label'] ?? ''));
                        $source = trim((string) ($viewer['source'] ?? 'phone'));
                        ?>
                    <li>
                        <span class="ath-list__name"><?= $h($label !== '' ? $label : 'Opérateur') ?></span>
                        <span class="ath-tag ath-tag--neut"><?= $h($sourceLabel($source)) ?></span>
                    </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<h2 class="ath-section-title">Catalogue<?= $slideCount > 0 ? ' — ' . $slideCount . ' diapositive' . ($slideCount > 1 ? 's' : '') : '' ?></h2>

<?php if ($rows === []): ?>
<div class="ath-card" style="padding:20px 22px;">
    <p class="ath-item__name" style="margin:0 0 5px;">Aucune diapositive pour le moment</p>
    <p class="ath-panel__lead" style="margin:0;">
        Ajoutez une première image, cochez « Visible en jeu », puis testez l’action
        « Tableau de briefing » dans Arma après recompilation de l’extension.
    </p>
</div>
<?php else: ?>
<?php foreach ($rows as $index => $row): ?>
    <?php
    $id = (int) ($row['id'] ?? 0);
    $title = trim((string) ($row['title'] ?? ''));
    $detailText = trim((string) ($row['detail_text'] ?? ''));
    $imagePath = trim((string) ($row['image_path'] ?? ''));
    $imageUrl = $imagePath !== '' ? asset_url($imagePath) : null;
    $isActive = !empty($row['is_active']);
    $sortOrder = (int) ($row['sort_order'] ?? 0);
    $displayTitle = $title !== '' ? $title : 'Sans titre';
    $commentCount = (int) ($commentCounts[$id] ?? 0);
    ?>
<article class="ath-slide ath-rise" id="slide-<?= $id ?>">
    <div class="ath-slide__top">
        <div class="ath-slide__thumb">
            <?php if ($imageUrl): ?>
            <img src="<?= $h($imageUrl) ?>" alt="<?= $h($displayTitle) ?>" loading="lazy">
            <?php else: ?>
            <span class="ath-slide__thumb-empty">Image absente</span>
            <?php endif; ?>
        </div>
        <div class="ath-slide__head">
            <p class="ath-slide__order">Position <?= $index + 1 ?> / <?= $slideCount ?> · ordre <?= $sortOrder ?></p>
            <h3 class="ath-slide__title"><?= $h($displayTitle) ?></h3>
            <div class="ath-media__badges" style="margin-top:7px;">
                <span class="ath-tag <?= $isActive ? 'ath-tag--ok' : 'ath-tag--warn' ?>"><?= $isActive ? 'Visible en jeu' : 'Brouillon' ?></span>
                <span class="ath-tag ath-tag--neut"><?= $commentCount ?> commentaire<?= $commentCount > 1 ? 's' : '' ?></span>
            </div>
            <?php if ($detailText !== ''): ?>
            <p class="ath-slide__detail"><?= nl2br($h($detailText)) ?></p>
            <?php endif; ?>
            <div class="ath-form__actions" style="border-top:0;margin-top:11px;padding-top:0;">
                <form method="post" action="<?= $h($baseUrl . '/' . $id . '/move') ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="direction" value="up">
                    <button type="submit" class="ath-row-action" title="Monter dans l’ordre"<?= $index === 0 ? ' disabled' : '' ?>>↑ Monter</button>
                </form>
                <form method="post" action="<?= $h($baseUrl . '/' . $id . '/move') ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="direction" value="down">
                    <button type="submit" class="ath-row-action" title="Descendre dans l’ordre"<?= $index >= $slideCount - 1 ? ' disabled' : '' ?>>↓ Descendre</button>
                </form>
                <form method="post" action="<?= $h($baseUrl . '/' . $id . '/toggle-publish') ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <button type="submit" class="ath-row-action <?= $isActive ? '' : 'ath-row-action--accent' ?>">
                        <?= $isActive ? 'Repasser en brouillon' : 'Rendre visible en jeu' ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="ath-slide__body">
        <details class="ath-disclosure" style="margin-bottom:0;">
            <summary>
                <span>Modifier cette diapositive</span>
                <span aria-hidden="true">▼</span>
            </summary>
            <div style="padding:12px 13px 13px;">
                <form method="post" action="<?= $h($baseUrl . '/' . $id . '/update') ?>" enctype="multipart/form-data">
                    <?= \App\Core\Csrf::field() ?>
                    <div class="ath-form__grid ath-form__grid--wide">
                        <label class="ath-field">
                            <span class="ath-field__label">Titre affiché</span>
                            <input type="text" name="title" value="<?= $h($title) ?>" maxlength="160" class="ath-field__input">
                        </label>
                        <label class="ath-field">
                            <span class="ath-field__label">Précisions</span>
                            <textarea name="detail_text" rows="3" maxlength="8000" class="ath-field__textarea" placeholder="Ajoutez ou enrichissez le détail après le briefing…"><?= $h($detailText) ?></textarea>
                        </label>
                    </div>
                    <div class="ath-form__grid" style="margin-top:11px;">
                        <label class="ath-field">
                            <span class="ath-field__label">Ordre d’affichage</span>
                            <input type="number" name="sort_order" value="<?= $sortOrder ?>" class="ath-field__input">
                        </label>
                        <label class="ath-field">
                            <span class="ath-field__label">Remplacer l’image</span>
                            <input type="file" name="image_file" accept="image/jpeg,image/png" class="ath-field__input" style="height:auto;padding:7px 10px;">
                            <span class="ath-field__help">Laissez vide pour conserver l’image actuelle.</span>
                        </label>
                    </div>
                    <div class="ath-check-grid" style="margin-top:11px;">
                        <label class="ath-check">
                            <input type="checkbox" name="is_active" value="1"<?= $isActive ? ' checked' : '' ?>>
                            <span>Visible en jeu</span>
                        </label>
                    </div>
                    <div class="ath-form__actions">
                        <button type="submit" class="ath-btn ath-btn--solid">Enregistrer</button>
                    </div>
                </form>

                <form method="post" action="<?= $h($baseUrl . '/' . $id . '/comment') ?>" style="border-top:1px solid var(--ath-line);margin-top:14px;padding-top:13px;">
                    <?= \App\Core\Csrf::field() ?>
                    <div class="ath-form__grid ath-form__grid--wide">
                        <label class="ath-field">
                            <span class="ath-field__label">Commentaire sur cette diapositive</span>
                            <textarea name="body" rows="2" maxlength="2000" class="ath-field__textarea" placeholder="Note pour l’équipe ou précision partagée pendant le briefing"></textarea>
                        </label>
                    </div>
                    <div class="ath-form__grid" style="margin-top:9px;">
                        <label class="ath-field">
                            <span class="ath-field__label">Signé par</span>
                            <input type="text" name="author_label" maxlength="80" class="ath-field__input" placeholder="État-major">
                        </label>
                    </div>
                    <div class="ath-form__actions">
                        <button type="submit" class="ath-btn">Publier le commentaire</button>
                    </div>
                </form>

                <form method="post" action="<?= $h($baseUrl . '/' . $id . '/delete') ?>"
                      style="border-top:1px solid var(--ath-line);margin-top:14px;padding-top:13px;"
                      onsubmit="return confirm('Supprimer cette diapositive ? L’opération est définitive.');">
                    <?= \App\Core\Csrf::field() ?>
                    <button type="submit" class="ath-row-action ath-row-action--danger">Supprimer cette diapositive</button>
                </form>
            </div>
        </details>
    </div>
</article>
<?php endforeach; ?>
<?php endif; ?>

<h2 class="ath-section-title">Afficher dans Arma</h2>

<div class="ath-panel ath-rise">
    <p class="ath-panel__lead" style="margin-top:0;">Parcours réel du mod COMSPEC Overwatch, sans étape inventée.</p>
    <ol class="ath-steps" style="margin-top:11px;">
        <li>
            <span class="ath-steps__title">Publier ici</span>
            <span class="ath-steps__text">Ajoutez vos images, réglez l’ordre, cochez « Visible en jeu ». Les brouillons restent invisibles pour les joueurs.</span>
        </li>
        <li>
            <span class="ath-steps__title">Recompiler l’extension native</span>
            <span class="ath-steps__text">
                Les diapositives passent par l’extension <em>COMSPECExtension</em> (SDK .NET). Après une mise à jour
                de cette extension, recompilez-la puis redéployez la DLL dans le mod avant de tester en jeu.
            </span>
        </li>
        <li>
            <span class="ath-steps__title">Tester en jeu</span>
            <span class="ath-steps__text">
                Action joueur <em>« Tableau de briefing »</em> dans le menu d’actions, ou écran posé dans Eden
                avec l’un des scripts ci-dessous.
            </span>
        </li>
    </ol>

    <div class="ath-warn" style="margin-top:14px;">
        <p class="ath-warn__title">Vérification hors de cet outil</p>
        <p class="ath-warn__text">
            La recompilation de l’extension et le test dans Arma ne peuvent pas être validés depuis le
            back-office. Après recompilation, ouvrez une mission avec le mod et utilisez « Tableau de
            briefing », ou posez un écran dans Eden.
        </p>
    </div>

    <div class="ath-snippet" style="margin-top:14px;">
        <div class="ath-snippet__bar">
            <span class="ath-snippet__label">Eden — action sur un objet</span>
            <button type="button" class="ath-copy" data-copy-target="bs-snippet-action">Copier</button>
        </div>
        <pre class="ath-snippet__code" id="bs-snippet-action"><?= $h($edenActionSnippet) ?></pre>
    </div>

    <div class="ath-snippet">
        <div class="ath-snippet__bar">
            <span class="ath-snippet__label">Eden — texture sur un écran</span>
            <button type="button" class="ath-copy" data-copy-target="bs-snippet-screen">Copier</button>
        </div>
        <pre class="ath-snippet__code" id="bs-snippet-screen"><?= $h($edenScreenSnippet) ?></pre>
    </div>

    <p class="ath-field__help">
        L’indice de sélection texturable, ici 0, dépend du modèle : vérifiez-le dans Eden ou la console
        développeur. Pour plusieurs écrans :
        <span class="ath-mono">[[ecran1, 0], [ecran2, 0]] call comspec_overwatch_connect_fnc_setBriefingScreens;</span>
    </p>

    <?php if ($feedUrl !== ''): ?>
    <p class="ath-field__label" style="margin-top:14px;">Adresse de synchronisation du mod</p>
    <p class="ath-field__help" style="margin:5px 0 7px;">
        Utilisée par le mod pour cette communauté<?= $tenantId > 0 ? ' (n°&nbsp;' . (int) $tenantId . ')' : '' ?>.
        Utile pour un diagnostic technique, pas pour les joueurs.
    </p>
    <div class="ath-copyline">
        <span class="ath-copyline__value" id="bs-feed-url"><?= $h($feedUrl) ?></span>
        <button type="button" class="ath-copy" data-copy-target="bs-feed-url">Copier</button>
    </div>
    <?php endif; ?>

    <div class="ath-form__actions">
        <a href="<?= $h(url('admin/atak-config')) ?>" class="ath-btn">Configuration ATAK</a>
        <a href="<?= $h(url('atak/setup')) ?>" class="ath-btn">Assistant d’installation du mod</a>
    </div>
</div>

</div>

<script>
/*
 * Deux comportements : copier un extrait, et rafraîchir la liste des appareils connectés.
 * Le rafraîchissement est indépendant de la copie — un échec réseau ne doit pas priver la
 * page de ses boutons « Copier », d'où deux blocs séparés plutôt qu'un retour anticipé
 * commun.
 */
(function () {
  var root = document.querySelector('[data-bo-briefing]');
  if (!root) return;

  root.addEventListener('click', function (event) {
    var button = event.target.closest('[data-copy-target]');
    if (!button || !root.contains(button)) return;
    var target = document.getElementById(button.getAttribute('data-copy-target') || '');
    if (!target) return;
    var text = (target.textContent || '').trim();
    if (!text) return;

    var confirmCopy = function () {
      var previous = button.textContent;
      button.textContent = 'Copié';
      button.classList.add('is-copied');
      window.setTimeout(function () {
        button.textContent = previous || 'Copier';
        button.classList.remove('is-copied');
      }, 1600);
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(confirmCopy).catch(function () {
        window.prompt('Copiez ce texte :', text);
      });
      return;
    }
    window.prompt('Copiez ce texte :', text);
    confirmCopy();
  });
})();

(function () {
  var root = document.querySelector('[data-bo-briefing]');
  if (!root) return;
  var presenceUrl = <?= json_encode($presenceUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  var countEl = root.querySelector('[data-presence-count]');
  var listEl = root.querySelector('[data-presence-list]');
  if (!presenceUrl || !countEl || !listEl) return;

  var sourceLabel = function (source) {
    if (source === 'arma') return 'tableau en jeu';
    if (source === 'admin') return 'poste de commandement';
    return 'téléphone';
  };

  var render = function (data) {
    var viewers = (data && data.viewers) || [];
    var count = (data && typeof data.count === 'number') ? data.count : viewers.length;
    countEl.textContent = String(count);

    // Le compteur de la rangée d'indicateurs porte la même valeur : on le tient à jour
    // pour éviter deux chiffres contradictoires à l'écran.
    var kpiValues = root.querySelectorAll('.ath-kpi');
    kpiValues.forEach(function (card) {
      var label = card.querySelector('.ath-kpi__label');
      if (!label || label.textContent.indexOf('ATAK') === -1) return;
      var value = card.querySelector('.ath-kpi__value');
      if (value) value.textContent = String(count);
    });

    listEl.innerHTML = '';
    if (!viewers.length) {
      var empty = document.createElement('li');
      var emptyText = document.createElement('span');
      emptyText.className = 'ath-list__meta';
      emptyText.textContent = 'Aucun appareil connecté pour le moment.';
      empty.appendChild(emptyText);
      listEl.appendChild(empty);
      return;
    }
    viewers.forEach(function (viewer) {
      var li = document.createElement('li');
      var name = document.createElement('span');
      name.className = 'ath-list__name';
      name.textContent = (viewer && viewer.label) ? String(viewer.label) : 'Opérateur';
      var tag = document.createElement('span');
      tag.className = 'ath-tag ath-tag--neut';
      tag.textContent = sourceLabel(viewer && viewer.source ? String(viewer.source) : 'phone');
      li.appendChild(name);
      li.appendChild(tag);
      listEl.appendChild(li);
    });
  };

  var poll = function () {
    fetch(presenceUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then(function (response) { return response.json(); })
      .then(render)
      .catch(function () {});
  };

  window.setInterval(poll, 15000);
})();
</script>
