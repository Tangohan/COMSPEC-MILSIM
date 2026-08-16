<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $documents */
$documents = is_array($documents ?? null) ? $documents : [];
/** @var array<string,string> $typeLabels */
$typeLabels = is_array($typeLabels ?? null) ? $typeLabels : [];
/** @var array<string,string> $statusLabels */
$statusLabels = is_array($statusLabels ?? null) ? $statusLabels : [];
/** @var array<string,int> $statusCounts */
$statusCounts = is_array($statusCounts ?? null) ? $statusCounts : [];
$documentsTotal = (int) ($documentsTotal ?? count($documents));
$filterStatus = (string) ($filterStatus ?? '');
$filterType = (string) ($filterType ?? '');
$filterQ = (string) ($filterQ ?? '');
$canManage = (bool) ($canManage ?? false);
$hasFilters = $filterStatus !== '' || $filterType !== '' || trim($filterQ) !== '';

$typeMeta = [
    'flash' => ['code' => 'FLH', 'hint' => 'Alerte courte pour le poste de commandement'],
    'compte_rendu' => ['code' => 'CR', 'hint' => 'Situation · faits · personnel · matériel'],
    'note_analyse' => ['code' => 'NA', 'hint' => 'Hypothèses, croisements, incertitudes'],
    'synthese' => ['code' => 'SYN', 'hint' => 'Vue consolidée pour un cercle élargi'],
    'diffusion' => ['code' => 'DIF', 'hint' => 'Version expurgée destinée à un cercle élargi'],
];

$statusClass = static function (string $status): string {
    return match ($status) {
        'valide' => 'sse-doc-state--ok',
        'en_relecture' => 'sse-doc-state--review',
        'archive' => 'sse-doc-state--archive',
        default => 'sse-doc-state--draft',
    };
};

$classificationClass = static function (string $classification): string {
    return match ($classification) {
        'secret', 'tres_secret' => 'badge--red',
        'confidentiel' => 'badge--amber',
        default => 'badge--gray',
    };
};

$filterHref = static function (array $overrides) use ($filterStatus, $filterType, $filterQ): string {
    $params = array_filter([
        'status' => $overrides['status'] ?? $filterStatus,
        'type' => $overrides['type'] ?? $filterType,
        'q' => $overrides['q'] ?? $filterQ,
    ], static fn (string $v): bool => trim($v) !== '');

    return url('atak/sse/documents') . ($params !== [] ? '?' . http_build_query($params) : '');
};

$whenLabel = static function (?string $raw): string {
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '—';
    }
    $ts = strtotime($raw);
    if ($ts === false) {
        return $raw;
    }
    $diff = time() - $ts;
    if ($diff < 3600) {
        return 'il y a ' . max(1, intdiv($diff, 60)) . ' min';
    }
    if ($diff < 86400) {
        return 'il y a ' . intdiv($diff, 3600) . ' h';
    }
    if ($diff < 172800) {
        return 'hier, ' . date('H\hi', $ts);
    }
    if ($diff < 604800) {
        return 'il y a ' . intdiv($diff, 86400) . ' jours';
    }

    return date('d/m/Y', $ts);
};

$excerpt = static function (string $body): string {
    $clean = preg_replace('/\[\[[^\]]*\]\]/u', '[caviardé]', $body) ?? $body;
    $clean = preg_replace('/^\s*[A-ZÉÈÀÇ0-9 \-]{3,}\s*$/mu', ' ', $clean) ?? $clean;
    $clean = trim(preg_replace('/\s+/u', ' ', $clean) ?? '');

    return $clean === '' ? '' : mb_strimwidth($clean, 0, 150, '…', 'UTF-8');
};

$statusTabs = [['', 'Tous les états', $documentsTotal]];
foreach ($statusLabels as $key => $label) {
    $statusTabs[] = [$key, $label, (int) ($statusCounts[$key] ?? 0)];
}
?>
<section class="sse-desk-hero" aria-labelledby="sse-desk-title">
    <div class="sse-desk-hero__main">
        <div class="sse-desk-hero__kicker">
            <span class="interest-hero__ref">ATH-SSE-REDAC</span>
            <span class="badge badge--red">Diffusion restreinte</span>
        </div>
        <h1 id="sse-desk-title">Atelier de rédaction</h1>
        <p class="sse-desk-hero__lead">
            Flash, comptes rendus, notes d’analyse et synthèses : tout ce qui sort du compartiment
            est écrit, relu et validé ici. Les compteurs ci-dessous portent sur l’ensemble de l’atelier,
            indépendamment des filtres appliqués à la liste.
        </p>
        <ul class="sse-desk-stats">
            <?php foreach ($statusLabels as $key => $label): ?>
                <li>
                    <a href="<?= $h($filterHref(['status' => $key])) ?>" class="<?= $filterStatus === $key ? 'is-active' : '' ?>">
                        <strong><?= (int) ($statusCounts[$key] ?? 0) ?></strong>
                        <span><?= $h($label) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
            <li>
                <a href="<?= $h(url('atak/sse/documents')) ?>" class="<?= !$hasFilters ? 'is-active' : '' ?>">
                    <strong><?= $documentsTotal ?></strong>
                    <span>Total atelier</span>
                </a>
            </li>
        </ul>
    </div>
    <aside class="sse-desk-hero__side">
        <p class="interest-hero__side-label">Production</p>
        <?php if ($canManage): ?>
            <div class="interest-hero__actions">
                <a class="btn" href="<?= $h(url('atak/sse/documents/nouveau')) ?>">Rédiger un document</a>
                <a class="btn btn--ghost" href="<?= $h(url('atak/sse/rapports')) ?>">Voir les rapports</a>
            </div>
        <?php else: ?>
            <p class="sse-desk-hint" style="margin:0">
                Vous êtes en lecture seule sur cet atelier. Demandez une habilitation de rédaction
                si vous devez produire un document.
            </p>
        <?php endif; ?>
        <ol class="sse-desk-flow">
            <li><span>1</span> Brouillon</li>
            <li><span>2</span> Relecture</li>
            <li><span>3</span> Validation</li>
            <li><span>4</span> Diffusion</li>
        </ol>
    </aside>
</section>

<div class="security-notice">
    <div class="security-notice-code">SEC-DOC</div>
    <div>
        <strong>Produits classifiés</strong>
        <span>
            Chaque document porte une classification. Une diffusion plus large passe par une
            version expurgée et une validation explicite — jamais par copie libre.
        </span>
    </div>
</div>

<?php if ($canManage): ?>
<nav class="sse-desk-types" aria-label="Ouvrir un nouveau document">
    <?php foreach ($typeMeta as $typeKey => $meta): ?>
        <?php if (!isset($typeLabels[$typeKey]) || $typeKey === 'diffusion') { continue; } ?>
        <a class="sse-desk-type" href="<?= $h(url('atak/sse/documents/nouveau?type=' . rawurlencode($typeKey))) ?>">
            <span class="sse-desk-type__code"><?= $h($meta['code']) ?></span>
            <strong><?= $h($typeLabels[$typeKey]) ?></strong>
            <span><?= $h($meta['hint']) ?></span>
        </a>
    <?php endforeach; ?>
</nav>
<?php endif; ?>

<section class="panel sse-desk-panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">19.10</span> Documents en atelier</div>
        <div class="panel-meta">
            <?= count($documents) ?> pièce<?= count($documents) > 1 ? 's' : '' ?> affichée<?= count($documents) > 1 ? 's' : '' ?>
        </div>
    </div>

    <div class="panel-body sse-desk-toolbar">
        <nav class="sse-desk-tabs" aria-label="Filtrer par état">
            <?php foreach ($statusTabs as [$key, $label, $count]): ?>
                <a href="<?= $h($filterHref(['status' => $key])) ?>" class="<?= $filterStatus === $key ? 'is-active' : '' ?>">
                    <?= $h($label) ?><i><?= (int) $count ?></i>
                </a>
            <?php endforeach; ?>
        </nav>

        <form method="get" action="<?= $h(url('atak/sse/documents')) ?>" class="sse-filter-row sse-desk-filters">
            <?php if ($filterStatus !== ''): ?>
                <input type="hidden" name="status" value="<?= $h($filterStatus) ?>">
            <?php endif; ?>
            <label class="sr-only" for="doc-q">Rechercher un document</label>
            <input id="doc-q" name="q" type="search" value="<?= $h($filterQ) ?>" placeholder="Référence ou intitulé…">
            <label class="sr-only" for="doc-type">Type de document</label>
            <select id="doc-type" name="type">
                <option value="">Tous les types</option>
                <?php foreach ($typeLabels as $k => $lab): ?>
                    <option value="<?= $h($k) ?>" <?= $filterType === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn" type="submit">Filtrer</button>
            <?php if ($hasFilters): ?>
                <a class="btn btn--ghost" href="<?= $h(url('atak/sse/documents')) ?>">Tout afficher</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($documents === []): ?>
        <div class="empty-state">
            <div class="empty-state-inner">
                <div class="empty-symbol">DOC</div>
                <?php if ($hasFilters): ?>
                    <strong>Aucun document ne correspond</strong>
                    <p>
                        Aucune pièce de l’atelier ne répond à cette combinaison de recherche, d’état et de type.
                        Élargissez les critères pour retrouver la production existante.
                    </p>
                    <a class="btn btn--ghost" href="<?= $h(url('atak/sse/documents')) ?>">Retirer les filtres</a>
                <?php else: ?>
                    <strong>Aucun document pour l’instant</strong>
                    <p>Ouvrez un brouillon depuis un dossier, ou créez un document libre dans l’atelier.</p>
                    <?php if ($canManage): ?>
                        <a class="btn btn--ghost" href="<?= $h(url('atak/sse/documents/nouveau')) ?>">Rédiger un document</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="sse-doclist" role="list">
            <div class="sse-doclist__head" aria-hidden="true">
                <span>Pièce</span>
                <span>Rattachement</span>
                <span>État</span>
                <span>Dernière écriture</span>
                <span></span>
            </div>
            <?php foreach ($documents as $doc): ?>
                <?php
                $docId = (int) ($doc['id'] ?? 0);
                $st = (string) ($doc['status'] ?? '');
                $dtype = (string) ($doc['document_type'] ?? '');
                $code = $typeMeta[$dtype]['code'] ?? 'DOC';
                $href = url('atak/sse/documents/' . $docId);
                $lead = $excerpt((string) ($doc['body'] ?? ''));
                $author = trim((string) ($doc['author_label'] ?? ''));
                ?>
                <article class="sse-doc-row" role="listitem">
                    <div class="sse-doc-row__main">
                        <span class="sse-doc-row__code" aria-hidden="true"><?= $h($code) ?></span>
                        <div class="sse-doc-row__ident">
                            <p class="sse-doc-row__top">
                                <span class="record-id"><?= $h((string) ($doc['reference_code'] ?? '')) ?></span>
                                <span class="badge <?= $h($classificationClass((string) ($doc['classification'] ?? ''))) ?>">
                                    <?= $h((string) ($doc['classification_label'] ?? '')) ?>
                                </span>
                            </p>
                            <h2 class="sse-doc-row__title">
                                <a href="<?= $h($href) ?>"><?= $h((string) ($doc['title'] ?? 'Sans titre')) ?></a>
                            </h2>
                            <?php if ($lead !== ''): ?>
                                <p class="sse-doc-row__lead"><?= $h($lead) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="sse-doc-row__link">
                        <p class="sse-doc-row__type"><?= $h((string) ($doc['document_type_label'] ?? 'Document')) ?></p>
                        <?php if (!empty($doc['case_id'])): ?>
                            <p>
                                <a class="link" href="<?= $h(url('atak/sse/dossiers/' . (int) $doc['case_id'])) ?>">
                                    <?= $h((string) ($doc['case_reference'] ?: 'Dossier lié')) ?>
                                </a>
                            </p>
                        <?php else: ?>
                            <p class="muted">Hors dossier</p>
                        <?php endif; ?>
                        <?php if ($author !== ''): ?>
                            <p class="muted">Rédacteur : <?= $h($author) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="sse-doc-row__state">
                        <span class="sse-doc-state <?= $h($statusClass($st)) ?>"><?= $h((string) ($doc['status_label'] ?? '')) ?></span>
                    </div>

                    <div class="sse-doc-row__when">
                        <strong><?= $h($whenLabel((string) ($doc['updated_at'] ?? ''))) ?></strong>
                        <?php if (!empty($doc['created_at'])): ?>
                            <span>ouvert le <?= $h(date('d/m/Y', strtotime((string) $doc['created_at']) ?: time())) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="sse-doc-row__actions">
                        <a class="btn-open" href="<?= $h($href) ?>">Ouvrir</a>
                        <?php if ($canManage && $st !== 'archive'): ?>
                            <a class="sse-doc-row__edit" href="<?= $h($href . '/modifier') ?>">Modifier</a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
