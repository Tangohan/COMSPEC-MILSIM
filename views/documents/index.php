<?php
$documents = $documents ?? [];
$categories = $categories ?? [];
$currentCategoryId = $currentCategoryId ?? null;
$search = $search ?? '';
$documentType = $documentType ?? '';
$sort = $sort ?? 'title_asc';
$entity_type = $entity_type ?? null;
$entity_id = $entity_id ?? null;
$collections = $collections ?? [];
$canManageCollections = (bool) ($canManageCollections ?? false);
/** Même droit que la gestion documentaire : documents.upload ou accès administration */
$canUploadDocuments = (bool) ($canUploadDocuments ?? $canManageCollections);
$focus = (string) ($focus ?? '');
/** @var array<int, list<array{label: string, href: string}>> $documentTrainingRefs */
$documentTrainingRefs = $documentTrainingRefs ?? [];
$totalDocs = count($documents);
$totalCategories = count($categories);
$documentTypes = [
    'manuel' => 'Manuel',
    'procedure' => 'Procédure',
    'note' => 'Note',
    'annexe' => 'Annexe',
    'support_formation' => 'Support formation',
    'fiche_equipement' => 'Fiche équipement',
    'document_operationnel' => 'Document opérationnel',
    'piece_jointe' => 'Pièce jointe',
    'collection' => 'Collection documentaire',
];
$sortLabels = [
    'title_asc' => 'Titre (A → Z)',
    'title_desc' => 'Titre (Z → A)',
    'updated_desc' => 'Plus récents',
    'updated_asc' => 'Plus anciens',
];
$hasActiveFilters = ($search !== '' || $currentCategoryId !== null || $documentType !== '' || $sort !== 'title_asc');
$baseUrlList = url('documents');
$hasEntityScope = $entity_type !== null && $entity_type !== '' && $entity_id !== null;

/** Lien vers la liste en conservant les filtres courants, sauf ceux remplacés par $overrides (null = retiré). */
$dlibUrl = static function (array $overrides = []) use ($baseUrlList, $search, $currentCategoryId, $documentType, $sort, $hasEntityScope, $entity_type, $entity_id): string {
    $query = [
        'q' => $search !== '' ? $search : null,
        'category' => $currentCategoryId,
        'document_type' => $documentType !== '' ? $documentType : null,
        'sort' => $sort !== 'title_asc' ? $sort : null,
    ];
    if ($hasEntityScope) {
        $query['entity_type'] = $entity_type;
        $query['entity_id'] = (int) $entity_id;
    }
    foreach ($overrides as $key => $value) {
        $query[$key] = $value;
    }
    $query = array_filter($query, static fn ($v) => $v !== null && $v !== '');

    return $baseUrlList . ($query !== [] ? '?' . http_build_query($query) : '');
};

$currentCategory = null;
foreach ($categories as $c) {
    if ($currentCategoryId !== null && (int) ($c['id'] ?? 0) === (int) $currentCategoryId) {
        $currentCategory = $c;
    }
}

$recentThreshold = strtotime('-30 days');
$recentCount = 0;
$downloadableCount = 0;
foreach ($documents as $d) {
    $ts = strtotime((string) ($d['updated_at'] ?? $d['created_at'] ?? ''));
    if ($ts !== false && $ts >= $recentThreshold) {
        $recentCount++;
    }
    if ((int) ($d['download_allowed'] ?? 1) === 1 && !empty($d['file_path'])) {
        $downloadableCount++;
    }
}

/** Format du fichier courant : icône + libellé court. */
$dlibFormat = static function (array $doc): array {
    $mime = strtolower((string) ($doc['mime_type'] ?? ''));
    $ext = strtolower(pathinfo((string) ($doc['file_path'] ?? ''), PATHINFO_EXTENSION));
    if (empty($doc['file_path'])) {
        return ['kind' => 'txt', 'label' => 'WEB'];
    }
    if ($mime === 'application/pdf' || $ext === 'pdf') {
        return ['kind' => 'pdf', 'label' => 'PDF'];
    }
    if (str_starts_with($mime, 'image/') || in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)) {
        return ['kind' => 'img', 'label' => $ext !== '' ? strtoupper($ext === 'jpeg' ? 'jpg' : $ext) : 'IMG'];
    }

    return ['kind' => 'doc', 'label' => $ext !== '' ? strtoupper(substr($ext, 0, 4)) : 'DOC'];
};
$dlibSize = static function ($bytes): string {
    $bytes = (int) $bytes;
    if ($bytes <= 0) {
        return '';
    }
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1, ',', ' ') . ' Mo';
    }

    return max(1, (int) round($bytes / 1024)) . ' Ko';
};
$dlibCss = static fn (string $color): string => preg_match('/^#[0-9a-fA-F]{3,8}$/', $color) === 1 ? $color : '#12d18e';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<link rel="stylesheet" href="<?= $e(asset_url('assets/css/documents-library.css')) ?>">

<div class="dlib" data-doc-protect>
    <div class="dlib__shell">

        <section class="dlib-summary" aria-label="Bibliothèque documentaire">
            <div class="dlib-hero">
                <p class="dlib-hero__kicker">Athena · Bibliothèque</p>
                <h1 class="dlib-hero__title"><?= $currentCategory !== null ? $e($currentCategory['name'] ?? 'Documents') : 'Documents de la communauté' ?></h1>
                <p class="dlib-hero__lead">
                    Doctrine, SOP, manuels et ressources opérationnelles publiés pour vous. Seuls les documents auxquels votre compte a accès apparaissent ici.
                </p>
                <div class="dlib-hero__links">
                    <?php if ($canUploadDocuments): ?>
                    <a href="<?= url('documents/gestion/ajout') ?>" class="dlib-hero__link dlib-hero__link--primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                        Ajouter un document
                    </a>
                    <?php endif; ?>
                    <a href="<?= url('documents/mes-documents') ?>" class="dlib-hero__link">Mes documents</a>
                    <?php if ($canUploadDocuments): ?>
                    <a href="<?= url('documents/gestion') ?>" class="dlib-hero__link">Gestion documentaire</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="dlib-stats">
                <div class="dlib-stat">
                    <span class="dlib-stat__label">Documents</span>
                    <span class="dlib-stat__value"><?= (int) $totalDocs ?></span>
                    <span class="dlib-stat__hint"><?= $hasActiveFilters ? 'Avec vos filtres' : 'Accessibles pour vous' ?></span>
                </div>
                <div class="dlib-stat">
                    <span class="dlib-stat__label">Récents</span>
                    <span class="dlib-stat__value"><?= (int) $recentCount ?></span>
                    <span class="dlib-stat__hint">Mis à jour sous 30 jours</span>
                </div>
                <div class="dlib-stat">
                    <span class="dlib-stat__label">Catégories</span>
                    <span class="dlib-stat__value"><?= (int) $totalCategories ?></span>
                    <span class="dlib-stat__hint">Dans la communauté</span>
                </div>
                <div class="dlib-stat">
                    <span class="dlib-stat__label">Téléchargeables</span>
                    <span class="dlib-stat__value"><?= (int) $downloadableCount ?></span>
                    <span class="dlib-stat__hint">Les autres se lisent en ligne</span>
                </div>
            </div>
        </section>

        <?php if ($categories !== []): ?>
        <nav class="dlib-cats" aria-label="Catégories">
            <a href="<?= $e($dlibUrl(['category' => null])) ?>" class="dlib-cat<?= $currentCategoryId === null ? ' is-active' : '' ?>"<?= $currentCategoryId === null ? ' aria-current="page"' : '' ?>>Toutes</a>
            <?php foreach ($categories as $c):
                $cid = (int) ($c['id'] ?? 0);
                $isActive = $currentCategoryId !== null && $cid === (int) $currentCategoryId;
                $isDoctrine = (string) ($c['slug'] ?? '') === 'doctrine';
                ?>
            <a href="<?= $e($isDoctrine ? url('documents') . '?category=' . $cid : $dlibUrl(['category' => $cid])) ?>" class="dlib-cat<?= $isActive ? ' is-active' : '' ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
                <span class="dlib-cat__dot" style="background: <?= $e($dlibCss((string) ($c['color'] ?? ''))) ?>"></span>
                <?= $e($c['name'] ?? '') ?>
                <?php if ($isDoctrine): ?><span class="dlib-cat__tag">Référentiel</span><?php endif; ?>
            </a>
            <?php endforeach; ?>
        </nav>
        <?php endif; ?>

        <form method="get" action="<?= $e($baseUrlList) ?>" class="dlib-toolbar" id="doc-filter-form" data-doc-catalog-form role="search">
            <?php if ($hasEntityScope): ?>
            <input type="hidden" name="entity_type" value="<?= $e($entity_type) ?>">
            <input type="hidden" name="entity_id" value="<?= (int) $entity_id ?>">
            <?php endif; ?>
            <?php if ($currentCategoryId !== null): ?>
            <input type="hidden" name="category" value="<?= (int) $currentCategoryId ?>">
            <?php endif; ?>
            <div class="dlib-field dlib-field--search">
                <label for="doc-q" class="dlib-field__label">Recherche</label>
                <div class="dlib-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input id="doc-q" type="search" name="q" value="<?= $e($search) ?>" placeholder="Titre, description, résumé…" autocomplete="off" class="dlib-input">
                </div>
            </div>
            <div class="dlib-field">
                <label for="doc-type" class="dlib-field__label">Type</label>
                <select id="doc-type" name="document_type" class="dlib-select" data-dlib-autosubmit>
                    <option value="">Tous les types</option>
                    <?php foreach ($documentTypes as $k => $label): ?>
                    <option value="<?= $e($k) ?>" <?= $documentType === $k ? 'selected' : '' ?>><?= $e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="dlib-field">
                <label for="doc-sort" class="dlib-field__label">Tri</label>
                <select id="doc-sort" name="sort" class="dlib-select" data-dlib-autosubmit>
                    <?php foreach ($sortLabels as $k => $label): ?>
                    <option value="<?= $e($k) ?>" <?= $sort === $k ? 'selected' : '' ?>><?= $e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="dlib-toolbar__actions">
                <button type="submit" class="dlib-btn dlib-btn--primary">Rechercher</button>
                <?php if ($hasActiveFilters): ?>
                <a href="<?= $e($baseUrlList . ($hasEntityScope ? '?' . http_build_query(['entity_type' => $entity_type, 'entity_id' => $entity_id]) : '')) ?>" class="dlib-btn">Réinitialiser</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($hasActiveFilters): ?>
        <div class="dlib-active">
            <span class="dlib-active__label">Filtres</span>
            <?php if ($search !== ''): ?>
            <a href="<?= $e($dlibUrl(['q' => null])) ?>" class="dlib-chip" title="Retirer ce filtre">« <?= $e(mb_substr($search, 0, 40)) ?><?= mb_strlen($search) > 40 ? '…' : '' ?> » <span class="dlib-chip__x" aria-hidden="true">×</span></a>
            <?php endif; ?>
            <?php if ($currentCategory !== null): ?>
            <a href="<?= $e($dlibUrl(['category' => null])) ?>" class="dlib-chip" title="Retirer ce filtre"><?= $e($currentCategory['name'] ?? '') ?> <span class="dlib-chip__x" aria-hidden="true">×</span></a>
            <?php endif; ?>
            <?php if ($documentType !== '' && isset($documentTypes[$documentType])): ?>
            <a href="<?= $e($dlibUrl(['document_type' => null])) ?>" class="dlib-chip" title="Retirer ce filtre"><?= $e($documentTypes[$documentType]) ?> <span class="dlib-chip__x" aria-hidden="true">×</span></a>
            <?php endif; ?>
            <?php if ($sort !== 'title_asc'): ?>
            <a href="<?= $e($dlibUrl(['sort' => null])) ?>" class="dlib-chip" title="Revenir au tri par titre"><?= $e($sortLabels[$sort] ?? $sort) ?> <span class="dlib-chip__x" aria-hidden="true">×</span></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($collections !== []): ?>
        <section id="collections" class="dlib-shortcuts<?= $focus === 'collections' ? ' is-focus' : '' ?>" aria-label="Collections">
            <div class="dlib-section-head">
                <div>
                    <p class="dlib-section-head__kicker">Collections</p>
                    <h2 class="dlib-section-head__title">Accès rapides</h2>
                </div>
            </div>
            <div class="dlib-shortcuts__grid">
                <?php foreach ($collections as $col):
                    $colTitle = (string) ($col['title'] ?? 'Collection');
                    $colDesc = (string) ($col['description'] ?? '');
                    if (preg_match('/[?&]document_type=([a-z_]+)/', (string) ($col['href'] ?? ''), $m) === 1 && isset($documentTypes[$m[1]])) {
                        $colTitle = $documentTypes[$m[1]];
                        $colDesc = 'Tous les documents de ce type.';
                    }
                    ?>
                <a href="<?= $e($col['href'] ?? '#') ?>" class="dlib-shortcut">
                    <span class="dlib-shortcut__count"><?= (int) ($col['count'] ?? 0) ?></span>
                    <span>
                        <span class="dlib-shortcut__title"><?= $e($colTitle) ?></span>
                        <span class="dlib-shortcut__desc"><?= $e($colDesc) ?></span>
                    </span>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php else: ?>
        <span id="collections" hidden></span>
        <?php endif; ?>

        <section class="dlib-shortcuts" aria-labelledby="dlib-catalog-title">
            <div class="dlib-section-head">
                <div>
                    <p class="dlib-section-head__kicker">Catalogue</p>
                    <h2 class="dlib-section-head__title" id="dlib-catalog-title">Documents disponibles</h2>
                </div>
                <div class="dlib-section-head__side">
                    <span class="dlib-count"><?= (int) $totalDocs ?> document<?= $totalDocs > 1 ? 's' : '' ?></span>
                    <?php if ($documents !== []): ?>
                    <div class="dlib-viewswitch" role="group" aria-label="Affichage">
                        <button type="button" data-dlib-view="grid" aria-pressed="true" title="Cartes">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="7" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/><rect x="13" y="13" width="7" height="7" rx="1.5"/></svg>
                        </button>
                        <button type="button" data-dlib-view="list" aria-pressed="false" title="Liste">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01"/></svg>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="doc-catalog-skeleton" class="dlib-skeleton hidden" aria-hidden="true">
                <?php for ($__i = 0; $__i < 6; $__i++): ?>
                <div class="dlib-skeleton__card"></div>
                <?php endfor; ?>
            </div>

            <?php if (empty($documents)): ?>
            <div class="dlib-empty">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/></svg>
                <p class="dlib-empty__title">Aucun document à afficher</p>
                <p class="dlib-empty__text">
                    <?= $hasActiveFilters
                        ? 'Aucun document publié ne correspond à vos filtres. Élargissez la recherche ou retirez un filtre.'
                        : 'Aucun document publié n’est encore accessible pour votre compte.' ?>
                </p>
                <div class="dlib-empty__actions">
                    <?php if ($hasActiveFilters): ?>
                    <a href="<?= $e($baseUrlList) ?>" class="dlib-btn">Voir tout le catalogue</a>
                    <?php endif; ?>
                    <?php if ($canUploadDocuments): ?>
                    <a href="<?= url('documents/gestion/ajout') ?>" class="dlib-btn dlib-btn--primary">Ajouter un document</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <div id="doc-catalog-root" class="dlib-results" data-view="grid">
                <?php foreach ($documents as $doc):
                    $catName = (string) ($doc['category_name'] ?? '');
                    $categoryColor = $dlibCss((string) ($doc['category_color'] ?? ''));
                    $snippet = trim((string) ($doc['short_description'] ?? ''));
                    if ($snippet === '' && !empty($doc['description'])) {
                        $snippet = trim(strip_tags((string) $doc['description']));
                        if (mb_strlen($snippet) > 180) {
                            $snippet = mb_substr($snippet, 0, 177) . '…';
                        }
                    }
                    $slug = (string) ($doc['slug'] ?? '');
                    $docId = (int) ($doc['id'] ?? 0);
                    $docUrl = $slug !== '' ? url('documents/' . $slug) : '#';
                    $updatedRaw = (string) ($doc['updated_at'] ?? $doc['created_at'] ?? '');
                    $updatedTs = $updatedRaw !== '' ? strtotime($updatedRaw) : false;
                    $updated = $updatedTs !== false ? date('d/m/Y', $updatedTs) : '';
                    $isNew = $updatedTs !== false && $updatedTs >= strtotime('-14 days');
                    $format = $dlibFormat($doc);
                    $size = $dlibSize($doc['size'] ?? 0);
                    $version = trim((string) ($doc['version_number'] ?? ''));
                    $typeKey = (string) ($doc['document_type'] ?? '');
                    $typeLabel = $documentTypes[$typeKey] ?? '';
                    $classification = trim((string) ($doc['classification_level'] ?? ''));
                    $canDownload = (int) ($doc['download_allowed'] ?? 1) === 1 && !empty($doc['file_path']);
                    $trainingRefs = $documentTrainingRefs[$docId] ?? [];
                    ?>
                <article class="dlib-doc" style="--dlib-cat: <?= $e($categoryColor) ?>">
                    <div class="dlib-doc__top">
                        <span class="dlib-doc__icon dlib-doc__icon--<?= $e($format['kind']) ?>" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/></svg>
                            <?= $e($format['label']) ?>
                        </span>
                        <div class="dlib-doc__head">
                            <div class="dlib-doc__meta-top">
                                <?php if ($catName !== ''): ?><span class="dlib-doc__cat"><?= $e($catName) ?></span><?php endif; ?>
                                <?php if ($typeLabel !== ''): ?><span class="dlib-badge"><?= $e($typeLabel) ?></span><?php endif; ?>
                                <?php if ($classification !== '' && !in_array(strtolower($classification), ['public', 'non_classifie', 'unclassified', 'nc'], true)): ?><span class="dlib-badge dlib-badge--class"><?= $e(str_replace('_', ' ', $classification)) ?></span><?php endif; ?>
                                <?php if ($isNew): ?><span class="dlib-badge dlib-badge--new">Nouveau</span><?php endif; ?>
                            </div>
                            <h3 class="dlib-doc__title"><a href="<?= $e($docUrl) ?>"><?= $e($doc['title'] ?? '') ?></a></h3>
                        </div>
                    </div>
                    <?php if ($snippet !== ''): ?>
                    <p class="dlib-doc__snippet"><?= $e($snippet) ?></p>
                    <?php else: ?>
                    <p class="dlib-doc__snippet is-placeholder">Pas de résumé pour ce document.</p>
                    <?php endif; ?>
                    <?php if ($trainingRefs !== []): ?>
                    <div class="dlib-doc__training">
                        <span class="dlib-doc__training-label">Formations</span>
                        <?php foreach ($trainingRefs as $tr): ?>
                        <a href="<?= $e($tr['href']) ?>"><?= $e($tr['label']) ?></a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <div class="dlib-doc__foot">
                        <div class="dlib-doc__facts">
                            <?php if ($updated !== ''): ?><span>Maj <?= $e($updated) ?></span><?php endif; ?>
                            <?php if ($version !== ''): ?><span>v<?= $e(ltrim($version, 'vV')) ?></span><?php endif; ?>
                            <?php if ($size !== ''): ?><span><?= $e($size) ?></span><?php endif; ?>
                        </div>
                        <div class="dlib-doc__actions">
                            <?php if ($canDownload): ?>
                            <a href="<?= url('documents/' . $docId . '/download') ?>" class="dlib-iconbtn" title="Télécharger" aria-label="Télécharger <?= $e($doc['title'] ?? '') ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11m0 0-4-4m4 4 4-4M5 19h14"/></svg>
                            </a>
                            <?php endif; ?>
                            <a href="<?= $e($docUrl) ?>" class="dlib-iconbtn" title="Ouvrir" aria-label="Ouvrir <?= $e($doc['title'] ?? '') ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m0 0-5-5m5 5-5 5"/></svg>
                            </a>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>

        <p class="dlib-back"><a href="<?= url('dashboard') ?>">← Retour au tableau de bord</a></p>
    </div>
</div>
<script>
(function () {
    var form = document.getElementById('doc-filter-form');
    if (form) {
        form.querySelectorAll('[data-dlib-autosubmit]').forEach(function (el) {
            el.addEventListener('change', function () {
                if (typeof form.requestSubmit === 'function') { form.requestSubmit(); } else { form.submit(); }
            });
        });
    }
    var root = document.getElementById('doc-catalog-root');
    var buttons = document.querySelectorAll('[data-dlib-view]');
    if (!root || !buttons.length) { return; }
    function apply(view) {
        root.setAttribute('data-view', view);
        buttons.forEach(function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-dlib-view') === view ? 'true' : 'false'); });
    }
    var saved = null;
    try { saved = localStorage.getItem('athena.documents.view'); } catch (e) {}
    if (saved === 'list' || saved === 'grid') { apply(saved); }
    buttons.forEach(function (b) {
        b.addEventListener('click', function () {
            var view = b.getAttribute('data-dlib-view');
            apply(view);
            try { localStorage.setItem('athena.documents.view', view); } catch (e) {}
        });
    });
})();
</script>
<script defer src="<?= $e(url('')) ?>/assets/js/doc_catalog_loading.js"></script>
<?php require base_path('views/partials/documents_copy_protection.php'); ?>
