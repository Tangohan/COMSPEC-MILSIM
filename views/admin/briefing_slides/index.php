<?php
declare(strict_types=1);

/**
 * @var list<array<string,mixed>> $briefingSlides
 * @var string $briefingSlidesFeedUrl
 * @var int $briefingSlidesTenantId
 * @var array{total:int,active:int,inactive:int} $briefingSlidesStats
 */

$rows = is_array($briefingSlides ?? null) ? $briefingSlides : [];
$feedUrl = (string) ($briefingSlidesFeedUrl ?? '');
$tenantId = (int) ($briefingSlidesTenantId ?? 0);
$stats = is_array($briefingSlidesStats ?? null) ? $briefingSlidesStats : [];
$total = (int) ($stats['total'] ?? count($rows));
$active = (int) ($stats['active'] ?? 0);
$inactive = (int) ($stats['inactive'] ?? max(0, $total - $active));
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');

$edenScreenSnippet = <<<'SQF'
this setVariable ["comspec_briefingScreenIndex", 0];
[briefingScreen1, 0] spawn {
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
?>
<link href="<?= htmlspecialchars(asset_url('assets/css/back-office-briefing-slides.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">

<div class="bo-briefing" data-bo-briefing>
    <header class="bo-briefing__hero">
        <div class="bo-briefing__hero-inner">
            <div>
                <p class="bo-briefing__eyebrow">Tactique · ATAK / Arma</p>
                <h1 class="bo-briefing__title">Diapositives de briefing</h1>
                <p class="bo-briefing__lead">
                    Préparez les images du briefing, définissez leur ordre, puis publiez-les pour le jeu.
                    Les diapositives marquées « Visible en jeu » sont récupérées automatiquement par le mod COMSPEC Overwatch.
                </p>
            </div>
            <div class="bo-briefing__hero-actions">
                <a href="#bo-briefing-create" class="bo-briefing__btn bo-briefing__btn--solid">Ajouter une diapositive</a>
                <a href="#bo-briefing-arma" class="bo-briefing__btn bo-briefing__btn--ghost">Voir en jeu</a>
                <a href="<?= htmlspecialchars(url('admin/atak-config'), ENT_QUOTES, 'UTF-8') ?>" class="bo-briefing__btn bo-briefing__btn--ghost">Config ATAK</a>
            </div>
        </div>
    </header>

    <div class="bo-briefing__deck">
        <?php if ($flashSuccess): ?>
            <div class="bo-briefing__flash bo-briefing__flash--ok" role="status"><?= htmlspecialchars((string) $flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <div class="bo-briefing__flash bo-briefing__flash--err" role="alert"><?= htmlspecialchars((string) $flashError, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="bo-briefing__kpi-grid" aria-label="Synthèse des diapositives">
            <div class="bo-briefing__kpi">
                <p class="bo-briefing__kpi-label">Catalogue</p>
                <p class="bo-briefing__kpi-value"><?= $total ?></p>
                <p class="bo-briefing__kpi-meta">Diapositive<?= $total > 1 ? 's' : '' ?> enregistrée<?= $total > 1 ? 's' : '' ?></p>
            </div>
            <div class="bo-briefing__kpi">
                <p class="bo-briefing__kpi-label">Visibles en jeu</p>
                <p class="bo-briefing__kpi-value"><?= $active ?></p>
                <p class="bo-briefing__kpi-meta">Prêtes pour le tableau de briefing</p>
            </div>
            <div class="bo-briefing__kpi">
                <p class="bo-briefing__kpi-label">Brouillons</p>
                <p class="bo-briefing__kpi-value"><?= $inactive ?></p>
                <p class="bo-briefing__kpi-meta">Masquées côté Arma</p>
            </div>
        </div>

        <div class="bo-briefing__layout">
            <div class="bo-briefing__stack">
                <section class="bo-briefing__panel" id="bo-briefing-create" aria-labelledby="bo-briefing-create-title">
                    <div class="bo-briefing__panel-head">
                        <h2 id="bo-briefing-create-title">Ajouter une diapositive</h2>
                        <p>
                            JPG recommandé pour un rendu plus fiable en jeu. L’image est redimensionnée automatiquement
                            (largeur max. 1920&nbsp;px). Taille max. 12&nbsp;Mo.
                        </p>
                    </div>
                    <div class="bo-briefing__panel-body">
                        <form
                            method="post"
                            action="<?= htmlspecialchars(url('back-office/atak/briefing-slides'), ENT_QUOTES, 'UTF-8') ?>"
                            enctype="multipart/form-data"
                            class="bo-briefing__form-grid"
                        >
                            <?= \App\Core\Csrf::field() ?>
                            <div class="bo-briefing__field bo-briefing__span-2">
                                <label for="bs-title">Titre affiché</label>
                                <input type="text" id="bs-title" name="title" maxlength="160" placeholder="Ex. Ordre d’opération — Phase 1">
                            </div>
                            <div class="bo-briefing__field">
                                <label for="bs-order">Ordre d’affichage</label>
                                <input type="number" id="bs-order" name="sort_order" value="<?= (int) $nextOrder ?>">
                                <p class="bo-briefing__field-hint">Du plus petit au plus grand : 0, puis 1, 2…</p>
                            </div>
                            <div class="bo-briefing__field" style="display:flex;align-items:flex-end;">
                                <label class="bo-briefing__check">
                                    <input type="checkbox" name="is_active" value="1" checked>
                                    Visible en jeu
                                </label>
                            </div>
                            <div class="bo-briefing__field bo-briefing__span-2">
                                <label for="bs-image">Image (JPG ou PNG)</label>
                                <input type="file" id="bs-image" name="image_file" accept="image/jpeg,image/png" required>
                            </div>
                            <div class="bo-briefing__form-actions bo-briefing__span-2">
                                <button type="submit" class="bo-briefing__btn bo-briefing__btn--primary">Publier la diapositive</button>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="bo-briefing__panel" aria-labelledby="bo-briefing-list-title">
                    <div class="bo-briefing__panel-head">
                        <h2 id="bo-briefing-list-title">Catalogue</h2>
                        <p>
                            Modifiez le titre, l’ordre ou la visibilité, puis enregistrez.
                            Seules les diapositives « Visible en jeu » sont proposées aux opérateurs dans Arma.
                        </p>
                    </div>
                    <div class="bo-briefing__panel-body">
                        <?php if ($rows === []): ?>
                            <div class="bo-briefing__empty">
                                <div class="bo-briefing__empty-icon" aria-hidden="true">∅</div>
                                <p>Aucune diapositive pour le moment</p>
                                <span>Ajoutez une première image ci-dessus, cochez « Visible en jeu », puis testez l’action « Tableau de briefing » dans Arma après recompilation de l’extension.</span>
                            </div>
                        <?php else: ?>
                            <div class="bo-briefing__list">
                                <?php foreach ($rows as $index => $row): ?>
                                    <?php
                                    $id = (int) ($row['id'] ?? 0);
                                    $title = trim((string) ($row['title'] ?? ''));
                                    $imagePath = trim((string) ($row['image_path'] ?? ''));
                                    $imageUrl = $imagePath !== '' ? asset_url($imagePath) : null;
                                    $isActive = !empty($row['is_active']);
                                    $sortOrder = (int) ($row['sort_order'] ?? 0);
                                    $displayTitle = $title !== '' ? $title : 'Sans titre';
                                    ?>
                                    <article class="bo-briefing__card">
                                        <div class="bo-briefing__thumb">
                                            <?php if ($imageUrl): ?>
                                                <img src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($displayTitle, ENT_QUOTES, 'UTF-8') ?>">
                                            <?php endif; ?>
                                            <span class="bo-briefing__thumb-badge <?= $isActive ? 'bo-briefing__thumb-badge--on' : 'bo-briefing__thumb-badge--off' ?>">
                                                <?= $isActive ? 'Visible en jeu' : 'Brouillon' ?>
                                            </span>
                                        </div>
                                        <div class="bo-briefing__card-body">
                                            <div class="bo-briefing__card-meta">
                                                <span class="bo-briefing__pill">Position <?= $index + 1 ?></span>
                                                <span class="bo-briefing__pill">Ordre <?= $sortOrder ?></span>
                                            </div>
                                            <form
                                                method="post"
                                                action="<?= htmlspecialchars(url('back-office/atak/briefing-slides/' . $id . '/update'), ENT_QUOTES, 'UTF-8') ?>"
                                                enctype="multipart/form-data"
                                                class="bo-briefing__form-grid"
                                            >
                                                <?= \App\Core\Csrf::field() ?>
                                                <div class="bo-briefing__field bo-briefing__span-2">
                                                    <label for="bs-title-<?= $id ?>">Titre affiché</label>
                                                    <input type="text" id="bs-title-<?= $id ?>" name="title" value="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>" maxlength="160">
                                                </div>
                                                <div class="bo-briefing__field">
                                                    <label for="bs-order-<?= $id ?>">Ordre d’affichage</label>
                                                    <input type="number" id="bs-order-<?= $id ?>" name="sort_order" value="<?= $sortOrder ?>">
                                                </div>
                                                <div class="bo-briefing__field" style="display:flex;align-items:flex-end;">
                                                    <label class="bo-briefing__check">
                                                        <input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>>
                                                        Visible en jeu
                                                    </label>
                                                </div>
                                                <div class="bo-briefing__field bo-briefing__span-2">
                                                    <label for="bs-image-<?= $id ?>">Remplacer l’image (facultatif)</label>
                                                    <input type="file" id="bs-image-<?= $id ?>" name="image_file" accept="image/jpeg,image/png">
                                                </div>
                                                <div class="bo-briefing__card-actions bo-briefing__span-2">
                                                    <button type="submit" class="bo-briefing__btn bo-briefing__btn--ink">Enregistrer</button>
                                                </div>
                                            </form>
                                            <form
                                                method="post"
                                                action="<?= htmlspecialchars(url('back-office/atak/briefing-slides/' . $id . '/delete'), ENT_QUOTES, 'UTF-8') ?>"
                                                class="bo-briefing__card-actions"
                                                onsubmit="return confirm('Supprimer cette diapositive ? Cette action est définitive.');"
                                            >
                                                <?= \App\Core\Csrf::field() ?>
                                                <button type="submit" class="bo-briefing__btn bo-briefing__btn--danger">Supprimer</button>
                                            </form>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <aside class="bo-briefing__aside" id="bo-briefing-arma">
                <section class="bo-briefing__panel" aria-labelledby="bo-briefing-arma-title">
                    <div class="bo-briefing__panel-head">
                        <h2 id="bo-briefing-arma-title">Afficher dans Arma</h2>
                        <p>Parcours réel du mod COMSPEC Overwatch — sans étape inventée.</p>
                    </div>
                    <div class="bo-briefing__panel-body">
                        <ol class="bo-briefing__steps">
                            <li>
                                <strong>Publier ici</strong>
                                <span>Ajoutez vos images, réglez l’ordre, cochez « Visible en jeu ». Les brouillons restent invisibles pour les joueurs.</span>
                            </li>
                            <li>
                                <strong>Recompiler l’extension native</strong>
                                <span>
                                    Les diapositives passent par l’extension <em>COMSPECExtension</em> (SDK .NET).
                                    Après une mise à jour de cette extension, recompilez-la puis redéployez la DLL dans le mod avant de tester en jeu.
                                </span>
                            </li>
                            <li>
                                <strong>Tester en jeu</strong>
                                <span>
                                    Action joueur <em>« Tableau de briefing »</em> (menu d’actions),
                                    <em>ou</em> écran posé dans Eden avec le script ci-dessous.
                                </span>
                            </li>
                        </ol>

                        <div class="bo-briefing__callout">
                            <strong>Vérification in-game hors de cet outil</strong>
                            La recompilation de l’extension et le test Arma ne peuvent pas être validés depuis le back-office.
                            Après rebuild, ouvrez une mission avec le mod et utilisez « Tableau de briefing », ou posez un écran Eden.
                        </div>

                        <div class="bo-briefing__snippet" data-copy-block>
                            <div class="bo-briefing__snippet-bar">
                                <span>Eden — action sur un objet</span>
                                <button type="button" class="bo-briefing__btn bo-briefing__btn--copy" data-copy-target="bs-snippet-action">Copier</button>
                            </div>
                            <pre id="bs-snippet-action"><?= htmlspecialchars($edenActionSnippet, ENT_QUOTES, 'UTF-8') ?></pre>
                        </div>
                        <p class="bo-briefing__field-hint" style="margin-top:0.5rem;">
                            Collez ce script dans le champ Init d’un objet nommé (écran, tableau…). Les joueurs verront « Consulter le briefing ».
                        </p>

                        <div class="bo-briefing__snippet" data-copy-block>
                            <div class="bo-briefing__snippet-bar">
                                <span>Eden — texture sur un écran</span>
                                <button type="button" class="bo-briefing__btn bo-briefing__btn--copy" data-copy-target="bs-snippet-screen">Copier</button>
                            </div>
                            <pre id="bs-snippet-screen"><?= htmlspecialchars($edenScreenSnippet, ENT_QUOTES, 'UTF-8') ?></pre>
                        </div>
                        <p class="bo-briefing__field-hint" style="margin-top:0.5rem;">
                            Remplacez <em>briefingScreen1</em> par le nom de variable de votre objet.
                            L’indice de sélection texturable (ici 0) dépend du modèle — vérifiez-le dans Eden / console développeur.
                        </p>

                        <?php if ($feedUrl !== ''): ?>
                        <div class="bo-briefing__feed">
                            <p class="bo-briefing__field-hint" style="margin:0;">
                                Adresse utilisée par le mod pour synchroniser les diapositives de cette communauté
                                <?php if ($tenantId > 0): ?>
                                    (n°&nbsp;<?= (int) $tenantId ?>)
                                <?php endif; ?>
                                — utile pour un diagnostic technique, pas pour les joueurs.
                            </p>
                            <div class="bo-briefing__feed-row">
                                <div class="bo-briefing__feed-value" id="bs-feed-url"><?= htmlspecialchars($feedUrl, ENT_QUOTES, 'UTF-8') ?></div>
                                <button type="button" class="bo-briefing__btn bo-briefing__btn--copy" data-copy-target="bs-feed-url">Copier</button>
                            </div>
                        </div>
                        <?php endif; ?>

                        <ul class="bo-briefing__aside-links">
                            <li>
                                <a href="<?= htmlspecialchars(url('admin/atak-config'), ENT_QUOTES, 'UTF-8') ?>">
                                    Configuration ATAK
                                    <span>→</span>
                                </a>
                            </li>
                            <li>
                                <a href="<?= htmlspecialchars(url('atak/setup'), ENT_QUOTES, 'UTF-8') ?>">
                                    Assistant d’installation du mod
                                    <span>→</span>
                                </a>
                            </li>
                            <li>
                                <a href="<?= htmlspecialchars(url('atak'), ENT_QUOTES, 'UTF-8') ?>">
                                    Ouvrir la Tacmap
                                    <span>→</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </section>
            </aside>
        </div>
    </div>
</div>

<script>
(function () {
    var root = document.querySelector('[data-bo-briefing]');
    if (!root) return;

    root.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-copy-target]');
        if (!btn || !root.contains(btn)) return;
        var id = btn.getAttribute('data-copy-target');
        var el = id ? document.getElementById(id) : null;
        if (!el) return;
        var text = (el.textContent || '').trim();
        if (!text) return;

        var done = function () {
            var previous = btn.textContent;
            btn.textContent = 'Copié';
            btn.classList.add('is-copied');
            window.setTimeout(function () {
                btn.textContent = previous || 'Copier';
                btn.classList.remove('is-copied');
            }, 1600);
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done).catch(function () {
                window.prompt('Copiez ce texte :', text);
            });
        } else {
            window.prompt('Copiez ce texte :', text);
            done();
        }
    });
})();
</script>
