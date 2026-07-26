<?php
declare(strict_types=1);

/**
 * @var list<array<string,mixed>> $briefingSlides
 * @var string $briefingSlidesFeedUrl
 * @var int $briefingSlidesTenantId
 * @var array{total:int,active:int,inactive:int} $briefingSlidesStats
 * @var array<int,int> $briefingCommentCounts
 * @var list<array{label:string,source:string,last_seen_at:int}> $briefingPresence
 * @var string $briefingPresenceUrl
 */

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
?>
<link href="<?= htmlspecialchars(asset_url('assets/css/back-office-briefing-slides.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">

<div class="bo-briefing" data-bo-briefing>
    <header class="bo-briefing__hero">
        <div class="bo-briefing__hero-inner">
            <div>
                <p class="bo-briefing__eyebrow">Tactique · ATAK / Arma</p>
                <h1 class="bo-briefing__title">Diapositives de briefing</h1>
                <p class="bo-briefing__lead">
                    Préparez les images du briefing, définissez leur ordre, enrichissez-les ensuite avec des précisions,
                    puis publiez-les pour le jeu et les téléphones ATAK.
                    Les diapositives marquées « Visible en jeu » sont récupérées automatiquement par le mod COMSPEC Overwatch.
                    Vous pouvez aussi publier un lien Google Slides partagé pour un affichage direct en jeu (dépend de Google).
                </p>
            </div>
            <div class="bo-briefing__hero-actions">
                <a href="#bo-briefing-create" class="bo-briefing__btn bo-briefing__btn--solid">Ajouter une diapositive</a>
                <a href="#bo-briefing-google" class="bo-briefing__btn bo-briefing__btn--ghost">Lien Google Slides</a>
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
            <div class="bo-briefing__kpi" id="bo-briefing-presence-kpi">
                <p class="bo-briefing__kpi-label">ATAK connectés</p>
                <p class="bo-briefing__kpi-value" data-presence-count><?= count($presence) ?></p>
                <p class="bo-briefing__kpi-meta">Pendant le briefing en cours</p>
            </div>
        </div>

        <div class="bo-briefing__layout">
            <div class="bo-briefing__stack">
                <section class="bo-briefing__panel" id="bo-briefing-google" aria-labelledby="bo-briefing-google-title">
                    <div class="bo-briefing__panel-head">
                        <h2 id="bo-briefing-google-title">Présentation Google Slides</h2>
                        <p>
                            Optionnel. Publiez un lien de présentation partagée avec toute personne disposant du lien.
                            Les opérateurs pourront la charger depuis la tablette Overwatch. Les diapositives images ci-dessous restent le chemin le plus fiable.
                        </p>
                    </div>
                    <div class="bo-briefing__panel-body">
                        <form
                            method="post"
                            action="<?= htmlspecialchars(url('back-office/atak/briefing-slides/google-url'), ENT_QUOTES, 'UTF-8') ?>"
                            class="bo-briefing__form-grid"
                        >
                            <?= \App\Core\Csrf::field() ?>
                            <div class="bo-briefing__field bo-briefing__span-2">
                                <label for="bs-google-url">Lien de la présentation</label>
                                <input
                                    type="url"
                                    id="bs-google-url"
                                    name="google_slides_url"
                                    maxlength="512"
                                    value="<?= htmlspecialchars($googleSlidesUrl, ENT_QUOTES, 'UTF-8') ?>"
                                    placeholder="https://docs.google.com/presentation/d/…"
                                >
                                <p class="bo-briefing__field-hint">
                                    Dans Google Slides : Partager → « Toute personne disposant du lien ».
                                    Laissez vide pour retirer le lien. Cette fonction dépend de Google et peut évoluer sans préavis.
                                </p>
                            </div>
                            <div class="bo-briefing__form-actions bo-briefing__span-2">
                                <button type="submit" class="bo-briefing__btn bo-briefing__btn--primary">Enregistrer le lien Google</button>
                            </div>
                        </form>
                    </div>
                </section>

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
                            <div class="bo-briefing__field bo-briefing__span-2">
                                <label for="bs-detail">Précisions (facultatif)</label>
                                <textarea id="bs-detail" name="detail_text" rows="3" maxlength="8000" placeholder="Contexte, consignes, points d’attention… Vous pourrez enrichir ce texte plus tard."></textarea>
                                <p class="bo-briefing__field-hint">Visible sur le téléphone ATAK et consultable après le briefing pour ajouter du détail.</p>
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
                                    $detailText = trim((string) ($row['detail_text'] ?? ''));
                                    $imagePath = trim((string) ($row['image_path'] ?? ''));
                                    $imageUrl = $imagePath !== '' ? asset_url($imagePath) : null;
                                    $isActive = !empty($row['is_active']);
                                    $sortOrder = (int) ($row['sort_order'] ?? 0);
                                    $displayTitle = $title !== '' ? $title : 'Sans titre';
                                    $commentCount = (int) ($commentCounts[$id] ?? 0);
                                    ?>
                                    <article class="bo-briefing__card" id="slide-<?= $id ?>">
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
                                                <span class="bo-briefing__pill"><?= $commentCount ?> commentaire<?= $commentCount > 1 ? 's' : '' ?></span>
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
                                                <div class="bo-briefing__field bo-briefing__span-2">
                                                    <label for="bs-detail-<?= $id ?>">Précisions / détail a posteriori</label>
                                                    <textarea id="bs-detail-<?= $id ?>" name="detail_text" rows="3" maxlength="8000" placeholder="Ajoutez ou enrichissez le détail après le briefing…"><?= htmlspecialchars($detailText, ENT_QUOTES, 'UTF-8') ?></textarea>
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
                                                action="<?= htmlspecialchars(url('back-office/atak/briefing-slides/' . $id . '/comment'), ENT_QUOTES, 'UTF-8') ?>"
                                                class="bo-briefing__form-grid"
                                                style="margin-top:.75rem;padding-top:.75rem;border-top:1px solid var(--bo-line);"
                                            >
                                                <?= \App\Core\Csrf::field() ?>
                                                <div class="bo-briefing__field bo-briefing__span-2">
                                                    <label for="bs-comment-<?= $id ?>">Commentaire sur cette diapositive</label>
                                                    <textarea id="bs-comment-<?= $id ?>" name="body" rows="2" maxlength="2000" placeholder="Note pour l’équipe ou précision partagée pendant / après le briefing"></textarea>
                                                </div>
                                                <div class="bo-briefing__field">
                                                    <label for="bs-comment-author-<?= $id ?>">Signé par</label>
                                                    <input type="text" id="bs-comment-author-<?= $id ?>" name="author_label" maxlength="80" placeholder="État-major">
                                                </div>
                                                <div class="bo-briefing__card-actions" style="align-items:flex-end;">
                                                    <button type="submit" class="bo-briefing__btn bo-briefing__btn--ghost">Publier le commentaire</button>
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
                <section class="bo-briefing__panel" aria-labelledby="bo-briefing-presence-title">
                    <div class="bo-briefing__panel-head">
                        <h2 id="bo-briefing-presence-title">ATAK connectés</h2>
                        <p>Appareils qui consultent actuellement le briefing (téléphone ou tableau en jeu).</p>
                    </div>
                    <div class="bo-briefing__panel-body">
                        <ul class="bo-briefing__steps" data-presence-list style="list-style:disc;padding-left:1.1rem;">
                            <?php if ($presence === []): ?>
                                <li style="list-style:none;margin-left:-1.1rem;color:#64748b;">Aucun appareil connecté pour le moment.</li>
                            <?php else: ?>
                                <?php foreach ($presence as $viewer): ?>
                                    <?php
                                    $label = trim((string) ($viewer['label'] ?? 'Opérateur'));
                                    $source = trim((string) ($viewer['source'] ?? 'phone'));
                                    $srcLabel = $source === 'arma' ? 'tableau en jeu' : ($source === 'admin' ? 'poste de commandement' : 'téléphone');
                                    ?>
                                    <li><?= htmlspecialchars($label . ' · ' . $srcLabel, ENT_QUOTES, 'UTF-8') ?></li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                </section>

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
                            Collez ce script dans le champ Init de l’écran. Il enregistre l’objet pour le briefing Athena et Google.
                            L’indice de sélection texturable (ici 0) dépend du modèle — vérifiez-le dans Eden / console développeur.
                            Pour plusieurs écrans : <em>[[ecran1, 0], [ecran2, 0]] call comspec_overwatch_connect_fnc_setBriefingScreens;</em>
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

    var presenceUrl = <?= json_encode($presenceUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    var countEl = root.querySelector('[data-presence-count]');
    var listEl = root.querySelector('[data-presence-list]');
    if (!presenceUrl || !countEl || !listEl) return;

    function renderPresence(data) {
        var viewers = (data && data.viewers) || [];
        var count = typeof data.count === 'number' ? data.count : viewers.length;
        countEl.textContent = String(count);
        listEl.innerHTML = '';
        if (!viewers.length) {
            var empty = document.createElement('li');
            empty.style.listStyle = 'none';
            empty.style.marginLeft = '-1.1rem';
            empty.style.color = '#64748b';
            empty.textContent = 'Aucun appareil connecté pour le moment.';
            listEl.appendChild(empty);
            return;
        }
        viewers.forEach(function (v) {
            var li = document.createElement('li');
            var label = (v && v.label) ? String(v.label) : 'Opérateur';
            var source = (v && v.source) ? String(v.source) : 'phone';
            var srcLabel = source === 'arma' ? 'tableau en jeu' : (source === 'admin' ? 'poste de commandement' : 'téléphone');
            li.textContent = label + ' · ' + srcLabel;
            listEl.appendChild(li);
        });
    }

    function pollPresence() {
        fetch(presenceUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(renderPresence)
            .catch(function () {});
    }

    window.setInterval(pollPresence, 15000);
})();
</script>
