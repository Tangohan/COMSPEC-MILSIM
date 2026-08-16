<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $entries */
$entries = is_array($entries ?? null) ? $entries : [];
/** @var array<string, list<array<string,mixed>>> $groupedEntries */
$groupedEntries = is_array($groupedEntries ?? null) ? $groupedEntries : [];
/** @var array<string,string> $libraryCategories */
$libraryCategories = is_array($libraryCategories ?? null) ? $libraryCategories : [];
/** @var array<string,string> $libraryContexts */
$libraryContexts = is_array($libraryContexts ?? null) ? $libraryContexts : [];
/** @var array<string,string> $libraryVariables */
$libraryVariables = is_array($libraryVariables ?? null) ? $libraryVariables : [];
/** @var array<string,int> $libraryCounts */
$libraryCounts = is_array($libraryCounts ?? null) ? $libraryCounts : [];
/** @var array<string,mixed>|null $editEntry */
$editEntry = is_array($editEntry ?? null) ? $editEntry : null;
$filters = is_array($filters ?? null) ? $filters : [];
$filterCat = (string) ($filters['category'] ?? '');
$filterCtx = (string) ($filters['context'] ?? '');
$filterQ = (string) ($filters['q'] ?? '');
$canManage = (bool) ($canManage ?? false);
$total = array_sum($libraryCounts);
$activeCount = 0;
foreach ($entries as $e) {
    if (!empty($e['is_active'])) {
        $activeCount++;
    }
}

$classifications = [
    '' => 'Aucun niveau minimal',
    'restreint' => 'Restreint',
    'confidentiel' => 'Confidentiel',
    'secret' => 'Secret',
];

$filterHref = static function (array $overrides) use ($filterCat, $filterCtx, $filterQ): string {
    $params = array_filter([
        'categorie' => $overrides['categorie'] ?? $filterCat,
        'contexte' => $overrides['contexte'] ?? $filterCtx,
        'q' => $overrides['q'] ?? $filterQ,
    ], static fn (string $v): bool => trim($v) !== '');

    return url('atak/sse/bibliotheque') . ($params !== [] ? '?' . http_build_query($params) : '');
};
?>
<div class="breadcrumb">
    Athena / SSE /
    <a class="link" href="<?= $h(url('atak/sse/documents')) ?>">Rédaction</a> /
    <strong>Mentions officielles</strong>
</div>

<section class="sse-desk-hero" aria-labelledby="sse-lib-adm-title">
    <div class="sse-desk-hero__main">
        <div class="sse-desk-hero__kicker">
            <span class="interest-hero__ref">ATH-SSE-BIBLIO</span>
            <span class="badge badge--gray">Administrable</span>
        </div>
        <h1 id="sse-lib-adm-title">Bibliothèque rédactionnelle</h1>
        <p class="sse-desk-hero__lead">
            Les phrases officielles utilisées dans les dossiers — manipulation, classification,
            appréciation du renseignement, caviardage, diffusion, archivage. Le rédacteur les insère
            depuis l’éditeur, puis reste libre de les adapter. Modifier une mention ici ne réécrit
            jamais un document déjà rédigé : le texte qui y avait été porté est conservé tel quel.
        </p>
        <ul class="sse-desk-stats">
            <li>
                <a href="<?= $h(url('atak/sse/bibliotheque')) ?>" class="<?= $filterCat === '' && $filterCtx === '' && trim($filterQ) === '' ? 'is-active' : '' ?>">
                    <strong><?= (int) $total ?></strong>
                    <span>Mentions au catalogue</span>
                </a>
            </li>
            <li>
                <span class="sse-desk-stat-static">
                    <strong><?= (int) $activeCount ?></strong>
                    <span>Proposées à la rédaction</span>
                </span>
            </li>
            <li>
                <span class="sse-desk-stat-static">
                    <strong><?= count($libraryCategories) ?></strong>
                    <span>Familles</span>
                </span>
            </li>
        </ul>
    </div>
    <aside class="sse-desk-hero__side">
        <p class="interest-hero__side-label">Utilisation</p>
        <div class="interest-hero__actions">
            <a class="btn" href="<?= $h(url('atak/sse/documents/nouveau')) ?>">Rédiger un document</a>
            <a class="btn btn--ghost" href="<?= $h(url('atak/sse/documents')) ?>">Atelier de rédaction</a>
        </div>
        <p class="sse-desk-hint" style="margin:.6rem 0 0">
            Dans l’éditeur, le bouton « Insérer une mention » ouvre ce catalogue. La recherche accepte
            aussi bien un code (RENS-03) qu’un mot du texte (recoupé, source, caviardage).
        </p>
    </aside>
</section>

<section class="panel sse-desk-panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">24.01</span> Catalogue</div>
        <div class="panel-meta"><?= count($entries) ?> mention<?= count($entries) > 1 ? 's' : '' ?> affichée<?= count($entries) > 1 ? 's' : '' ?></div>
    </div>

    <div class="panel-body sse-desk-toolbar">
        <nav class="sse-desk-tabs" aria-label="Filtrer par famille">
            <a href="<?= $h($filterHref(['categorie' => ''])) ?>" class="<?= $filterCat === '' ? 'is-active' : '' ?>">
                Toutes<i><?= (int) $total ?></i>
            </a>
            <?php foreach ($libraryCategories as $key => $label): ?>
                <?php $n = (int) ($libraryCounts[$key] ?? 0); ?>
                <?php if ($n === 0) { continue; } ?>
                <a href="<?= $h($filterHref(['categorie' => $key])) ?>" class="<?= $filterCat === $key ? 'is-active' : '' ?>">
                    <?= $h($label) ?><i><?= $n ?></i>
                </a>
            <?php endforeach; ?>
        </nav>

        <form method="get" action="<?= $h(url('atak/sse/bibliotheque')) ?>" class="sse-filter-row sse-desk-filters">
            <?php if ($filterCat !== ''): ?>
                <input type="hidden" name="categorie" value="<?= $h($filterCat) ?>">
            <?php endif; ?>
            <label class="sr-only" for="lib-q">Rechercher une mention</label>
            <input id="lib-q" name="q" type="search" value="<?= $h($filterQ) ?>" placeholder="Code ou mot du texte…">
            <label class="sr-only" for="lib-ctx">Contexte</label>
            <select id="lib-ctx" name="contexte">
                <option value="">Tous les contextes</option>
                <?php foreach ($libraryContexts as $key => $label): ?>
                    <option value="<?= $h($key) ?>" <?= $filterCtx === $key ? 'selected' : '' ?>><?= $h($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn--sm" type="submit">Filtrer</button>
            <a class="btn btn--ghost btn--sm" href="<?= $h(url('atak/sse/bibliotheque')) ?>">Réinitialiser</a>
        </form>
    </div>

    <div class="panel-body">
        <?php if ($entries === []): ?>
            <p class="muted">
                Aucune mention ne correspond à cette recherche. Retirez les filtres ou ajoutez
                une mention propre à votre unité depuis le bloc ci-dessous.
            </p>
        <?php else: ?>
            <?php foreach ($groupedEntries as $catKey => $catEntries): ?>
                <h2 class="sse-lib__cat" style="position:static"><?= $h($libraryCategories[$catKey] ?? 'Divers') ?></h2>
                <div class="sse-libadm-list">
                    <?php foreach ($catEntries as $entry): ?>
                        <article class="sse-libadm-item <?= empty($entry['is_active']) ? 'is-off' : '' ?>">
                            <div class="sse-libadm-item__head">
                                <span class="sse-lib__code"><?= $h($entry['code']) ?></span>
                                <strong><?= $h($entry['title']) ?></strong>
                                <?php if (!empty($entry['is_default'])): ?>
                                    <span class="badge badge--gray">Proposée d’office</span>
                                <?php endif; ?>
                                <?php if (empty($entry['is_active'])): ?>
                                    <span class="badge badge--amber">Retirée des propositions</span>
                                <?php endif; ?>
                                <span class="sse-libadm-item__meta">
                                    <?= $h($entry['context_label']) ?> · v<?= (int) $entry['version'] ?>
                                    <?php if ((int) $entry['usage_count'] > 0): ?>
                                        · <?= (int) $entry['usage_count'] ?> insertion<?= (int) $entry['usage_count'] > 1 ? 's' : '' ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <p class="sse-libadm-item__text"><?= $h($entry['content']) ?></p>
                            <?php if (!empty($entry['variable_list'])): ?>
                                <ul class="sse-libadm-vars">
                                    <?php foreach ($entry['variable_list'] as $var): ?>
                                        <li>{{<?= $h($var) ?>}}</li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <?php if ($canManage): ?>
                                <div class="sse-libadm-item__actions">
                                    <a class="btn btn--ghost btn--sm"
                                       href="<?= $h($filterHref([]) . (str_contains($filterHref([]), '?') ? '&' : '?') . 'modifier=' . (int) $entry['id']) ?>#lib-edit">
                                        Modifier le texte
                                    </a>
                                    <form method="post" action="<?= $h(url('atak/sse/bibliotheque/' . (int) $entry['id'] . '/etat')) ?>">
                                        <?= \App\Core\Csrf::field() ?>
                                        <button class="btn btn--ghost btn--sm" type="submit">
                                            <?= empty($entry['is_active']) ? 'Proposer de nouveau' : 'Retirer des propositions' ?>
                                        </button>
                                    </form>
                                    <?php if (empty($entry['is_seeded'])): ?>
                                        <form method="post" action="<?= $h(url('atak/sse/bibliotheque/' . (int) $entry['id'] . '/supprimer')) ?>"
                                              onsubmit="return confirm('Supprimer définitivement cette mention ? Les documents déjà rédigés ne sont pas touchés.');">
                                            <?= \App\Core\Csrf::field() ?>
                                            <button class="btn btn--danger btn--sm" type="submit">Supprimer</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<?php if ($canManage): ?>
<section class="panel sse-desk-panel" id="lib-edit">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">24.02</span>
            <?= $editEntry ? 'Modifier la mention ' . $h($editEntry['code']) : 'Ajouter une mention' ?>
        </div>
        <div class="panel-meta"><?= $editEntry ? 'Version ' . (int) $editEntry['version'] : 'Nouvelle entrée' ?></div>
    </div>
    <div class="panel-body">
        <form method="post"
              action="<?= $h($editEntry ? url('atak/sse/bibliotheque/' . (int) $editEntry['id']) : url('atak/sse/bibliotheque')) ?>">
            <?= \App\Core\Csrf::field() ?>
            <div class="sse-form-grid">
                <div>
                    <label for="lib-code">Code de la mention</label>
                    <?php if ($editEntry): ?>
                        <input id="lib-code" type="text" value="<?= $h($editEntry['code']) ?>" disabled>
                        <p class="sse-desk-hint">Le code ne change pas : c’est lui qui relie les insertions déjà tracées.</p>
                    <?php else: ?>
                        <input id="lib-code" name="code" type="text" maxlength="32" required placeholder="Ex. RENS-11">
                        <p class="sse-desk-hint">Lettres, chiffres et tirets. Il doit être unique dans le catalogue.</p>
                    <?php endif; ?>
                </div>
                <div>
                    <label for="lib-cat">Famille</label>
                    <select id="lib-cat" name="category" required>
                        <?php foreach ($libraryCategories as $key => $label): ?>
                            <option value="<?= $h($key) ?>" <?= ($editEntry['category'] ?? $filterCat) === $key ? 'selected' : '' ?>>
                                <?= $h($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="lib-context">Contexte d’emploi</label>
                    <select id="lib-context" name="context" required>
                        <?php foreach ($libraryContexts as $key => $label): ?>
                            <option value="<?= $h($key) ?>" <?= ($editEntry['context'] ?? 'dossier') === $key ? 'selected' : '' ?>>
                                <?= $h($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="lib-class">Niveau minimal</label>
                    <select id="lib-class" name="classification_min">
                        <?php foreach ($classifications as $key => $label): ?>
                            <option value="<?= $h($key) ?>" <?= (string) ($editEntry['classification_min'] ?? '') === $key ? 'selected' : '' ?>>
                                <?= $h($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <label for="lib-title">Intitulé lisible</label>
            <input id="lib-title" name="title" type="text" maxlength="180" required
                   value="<?= $h($editEntry['title'] ?? '') ?>"
                   placeholder="Ex. Renseignement recoupé par deux acquisitions distinctes">

            <label for="lib-content">Texte de la mention</label>
            <textarea id="lib-content" name="content" rows="7" required
                      placeholder="Rédigez la phrase telle qu’elle doit apparaître dans le dossier."><?= $h($editEntry['content'] ?? '') ?></textarea>
            <p class="sse-desk-hint">
                Vous pouvez insérer des variables entre doubles accolades : elles seront remplacées à
                l’insertion par les informations du dossier. Une variable sans valeur connue reste visible
                pour être complétée à la main.
            </p>
            <ul class="sse-libadm-vars">
                <?php foreach ($libraryVariables as $name => $desc): ?>
                    <li title="<?= $h($desc) ?>">{{<?= $h($name) ?>}}</li>
                <?php endforeach; ?>
            </ul>

            <div class="sse-form-grid" style="margin-top:.9rem">
                <div>
                    <label for="lib-order">Ordre d’affichage dans la famille</label>
                    <input id="lib-order" name="sort_order" type="number" min="0" max="9999"
                           value="<?= (int) ($editEntry['sort_order'] ?? 100) ?>">
                </div>
                <div>
                    <label>Disponibilité</label>
                    <label class="sse-check">
                        <input type="checkbox" name="is_default" value="1" <?= !empty($editEntry['is_default']) ? 'checked' : '' ?>>
                        Proposer cette mention d’office selon le contexte
                    </label>
                    <?php if ($editEntry): ?>
                        <label class="sse-check">
                            <input type="checkbox" name="is_active" value="1" <?= !empty($editEntry['is_active']) ? 'checked' : '' ?>>
                            Mention proposée à la rédaction
                        </label>
                    <?php endif; ?>
                </div>
            </div>

            <div class="toolbar-actions sse-desk-actions">
                <button class="btn" type="submit"><?= $editEntry ? 'Enregistrer la mention' : 'Ajouter au catalogue' ?></button>
                <?php if ($editEntry): ?>
                    <a class="btn btn--ghost" href="<?= $h(url('atak/sse/bibliotheque')) ?>">Annuler</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</section>
<?php endif; ?>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
