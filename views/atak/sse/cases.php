<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $cases */
/** @var array{status:string,classification:string,q:string} $filters */
/** @var array<string,string> $classifications */
/** @var array<string,string> $statuses */
/** @var array{total:int,active:int,archive:int} $indexCounts */
/** @var array<int, array{persons:int,notes:int,evidence:int}> $caseCounts */
/** @var bool $canManage */
/** @var bool $caseLockEnabled */
/** @var bool $screensRedacted */
/** @var int $lockedForMe */
/** @var string $myClearance */
$total = count($cases);
$activeCount = 0;
foreach ($cases as $c) {
    $st = (string) ($c['status'] ?? '');
    if (in_array($st, ['ouvert', 'en_cours'], true)) {
        $activeCount++;
    }
}
$classBadge = static function (string $key): string {
    return match ($key) {
        'confidentiel' => 'badge badge--amber',
        'tres_restreint' => 'badge badge--red',
        'interne' => 'badge badge--gray',
        default => 'badge',
    };
};
$byClass = [];
foreach ($cases as $c) {
    $k = (string) ($c['classification'] ?? 'encadrement');
    $byClass[$k] = ($byClass[$k] ?? 0) + 1;
}
$statusBadge = static function (string $key): string {
    return match ($key) {
        'clos', 'archive' => 'badge badge--gray',
        'en_cours' => 'badge badge--amber',
        default => 'badge',
    };
};
$frStamp = static function (string $raw): string {
    if ($raw === '') {
        return '—';
    }
    $ts = strtotime(substr($raw, 0, 19));
    if ($ts === false) {
        return substr($raw, 0, 16);
    }

    return date('d/m/Y · H:i', $ts);
};
$folders = array_values(array_filter($cases, static fn (array $c): bool => !empty($c['is_folder'])));
$affairs = array_values(array_filter($cases, static fn (array $c): bool => empty($c['is_folder'])));
$viewStatus = (string) ($filters['status'] ?? '');
?>
<div class="breadcrumb">
    Athena / SSE /
    <a class="link" href="<?= $h(url('atak/sse/operations')) ?>">Opérations</a> /
    <strong>Dossiers</strong>
</div>

<header class="sse-cases-hero">
    <div>
        <p class="sse-cases-hero__kicker">Exploitation // Registre</p>
        <h1>Dossiers d’affaire</h1>
        <p class="sse-cases-hero__lead">
            Affaires du périmètre de votre session. Chaque consultation et chaque modification sont journalisées.
        </p>
    </div>
    <div class="sse-cases-hero__actions">
        <?php if ($canManage): ?>
            <a class="btn" href="<?= $h(url('atak/sse/dossiers/nouveau')) ?>">Ouvrir une affaire</a>
            <a class="btn btn--ghost" href="<?= $h(url('atak/sse/dossiers/importer')) ?>">Importer un scénario</a>
        <?php endif; ?>
        <span class="sse-cases-hero__ref">ATH-SSE-DOSSIERS</span>
    </div>
</header>

<div class="sse-cases-metrics" aria-label="Indicateurs du registre">
    <div class="sse-cases-metric">
        <span class="sse-cases-metric__label">Visibles</span>
        <strong class="sse-cases-metric__value"><?= (int) $total ?></strong>
        <span class="sse-cases-metric__hint">Dans cette vue</span>
    </div>
    <div class="sse-cases-metric">
        <span class="sse-cases-metric__label">Actifs</span>
        <strong class="sse-cases-metric__value"><?= (int) $activeCount ?></strong>
        <span class="sse-cases-metric__hint">Ouverts / en cours</span>
    </div>
    <div class="sse-cases-metric">
        <span class="sse-cases-metric__label">Accès</span>
        <strong class="sse-cases-metric__value"><?= $canManage ? 'Gestion' : 'Lecture' ?></strong>
        <span class="sse-cases-metric__hint">Session courante</span>
    </div>
    <div class="sse-cases-metric">
        <span class="sse-cases-metric__label">Verrou classification</span>
        <strong class="sse-cases-metric__value <?= !empty($caseLockEnabled) ? 'is-armed' : '' ?>">
            <?= !empty($caseLockEnabled) ? 'Armé' : 'Désarmé' ?>
        </strong>
        <span class="sse-cases-metric__hint"><?= (int) $lockedForMe ?> fermé(s) pour vous</span>
    </div>
</div>

<div class="case-workspace sse-cases-layout">
<aside class="case-aside case-aside--compact" aria-label="Filtres rapides">
    <div class="case-aside-head"><span>Filtres</span><strong>Vues</strong></div>
    <form class="case-search" method="get" action="<?= $h(url('atak/sse/dossiers')) ?>" role="search">
        <label for="case-q">Recherche</label>
        <div class="case-search-control">
            <input id="case-q" name="q" type="search" value="<?= $h($filters['q'] ?? '') ?>" placeholder="Réf., titre…">
            <button type="submit" aria-label="Lancer la recherche">→</button>
        </div>
    </form>
    <nav class="case-aside-nav" aria-label="Vues du registre">
        <a class="<?= $viewStatus === '' ? 'is-active' : '' ?>" href="<?= $h(url('atak/sse/dossiers')) ?>"><span>Complet</span><b><?= (int) $indexCounts['total'] ?></b></a>
        <a class="<?= $viewStatus === 'en_cours' ? 'is-active' : '' ?>" href="<?= $h(url('atak/sse/dossiers?status=en_cours')) ?>"><span>En exploitation</span><b><?= (int) $indexCounts['active'] ?></b></a>
        <a class="<?= $viewStatus === 'archive' ? 'is-active' : '' ?>" href="<?= $h(url('atak/sse/dossiers?status=archive')) ?>"><span>Archives</span><b><?= (int) $indexCounts['archive'] ?></b></a>
    </nav>
</aside>

<div class="case-workspace-main">
<form class="sse-cases-toolbar" method="get" action="<?= $h(url('atak/sse/dossiers')) ?>">
    <input type="hidden" name="q" value="<?= $h($filters['q'] ?? '') ?>">
    <div class="sse-cases-toolbar__fields">
        <div class="sse-cases-field">
            <label for="status">Statut opérationnel</label>
            <select id="status" name="status">
                <option value="">Tous les statuts</option>
                <?php foreach ($statuses as $k => $lab): ?>
                    <option value="<?= $h($k) ?>" <?= $viewStatus === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="sse-cases-field">
            <label for="classification">Niveau de diffusion</label>
            <select id="classification" name="classification">
                <option value="">Toutes les classifications</option>
                <?php foreach ($classifications as $k => $lab): ?>
                    <option value="<?= $h($k) ?>" <?= ($filters['classification'] ?? '') === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="sse-cases-toolbar__actions">
        <button class="btn" type="submit">Appliquer</button>
        <?php if (($filters['q'] ?? '') !== '' || $viewStatus !== '' || ($filters['classification'] ?? '') !== ''): ?>
            <a class="btn btn--ghost" href="<?= $h(url('atak/sse/dossiers')) ?>">Réinitialiser</a>
        <?php endif; ?>
    </div>
</form>

<details id="securite" class="panel sse-lock-panel <?= !empty($caseLockEnabled) ? 'is-armed' : '' ?>">
    <summary>
        <div class="panel-header">
            <div class="panel-title">
                <span class="panel-index">01</span>
                Verrou d’ouverture par classification
            </div>
            <div class="panel-meta"><?= !empty($caseLockEnabled) ? 'Armé' : 'Désarmé' ?></div>
        </div>
    </summary>
    <div class="panel-body">
        <?php if (!empty($caseLockEnabled)): ?>
            <p>
                Un dossier dont la classification dépasse l’habilitation du lecteur
                <strong>ne s’ouvre pas</strong> pour lui — ni la fiche, ni les personnes
                rattachées, ni les notes, ni les corrélations, ni le compte rendu.
            </p>
        <?php else: ?>
            <p>
                La classification <strong>signale sans fermer</strong> : elle s’affiche en
                badge et noircit les catégories concernées sur les versions expurgées, mais
                n’empêche aucune ouverture.
            </p>
            <p class="sse-note">
                Avant d’armer, relisez la colonne « Qui pourra encore l’ouvrir » ci-dessous.
            </p>
        <?php endif; ?>

        <div class="sse-release-summary">
            <div>
                <div class="sse-block-title">Répartition actuelle</div>
                <p>
                    <?php
                    $parts = [];
                    foreach ($byClass as $ck => $n) {
                        $parts[] = $n . ' × ' . ($classifications[$ck] ?? $ck);
                    }
                    echo $parts === [] ? 'Aucun dossier.' : $h(implode(' · ', $parts));
                    ?>
                </p>
            </div>
            <div>
                <div class="sse-block-title">Effet sur vous</div>
                <p>
                    <?php if ((int) $lockedForMe === 0): ?>
                        Aucun de ces dossiers ne vous serait fermé
                        (habilitation : <?= $h(\App\Services\Sse\SseRedactionService::levelLabel($myClearance)) ?>).
                    <?php else: ?>
                        <strong><?= (int) $lockedForMe ?></strong> dossier<?= (int) $lockedForMe > 1 ? 's' : '' ?>
                        vous <?= (int) $lockedForMe > 1 ? 'seraient fermés' : 'serait fermé' ?>
                        (habilitation : <?= $h(\App\Services\Sse\SseRedactionService::levelLabel($myClearance)) ?>).
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <?php if (!empty($canGrant)): ?>
            <form method="post" action="<?= $h(url('atak/sse/dossiers/verrou-classification')) ?>">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="reglage" value="verrou">
                <input type="hidden" name="enable" value="<?= !empty($caseLockEnabled) ? '0' : '1' ?>">
                <button class="btn <?= !empty($caseLockEnabled) ? 'btn--ghost' : '' ?>" type="submit">
                    <?= !empty($caseLockEnabled) ? 'Désarmer le verrou' : 'Armer le verrou' ?>
                </button>
            </form>
        <?php else: ?>
            <p class="sse-muted">
                Seuls les détenteurs du droit d’octroi peuvent armer ce verrou.
            </p>
        <?php endif; ?>

        <hr class="sse-sep">

        <div class="sse-block-title">
            Caviardage des écrans de travail —
            <?= !empty($screensRedacted) ? 'Armé' : 'Désarmé' ?>
        </div>
        <?php if (!empty($screensRedacted)): ?>
            <p>
                Le registre des personnes, la fiche dossier et les corrélations sont
                rabattus sur l’habilitation du lecteur.
            </p>
        <?php else: ?>
            <p>
                Les documents de diffusion restent toujours rabattus sur l’habilitation.
                Les écrans de travail restent intégraux.
            </p>
        <?php endif; ?>

        <?php if (!empty($canGrant)): ?>
            <form method="post" action="<?= $h(url('atak/sse/dossiers/verrou-classification')) ?>">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="reglage" value="ecrans">
                <input type="hidden" name="enable" value="<?= !empty($screensRedacted) ? '0' : '1' ?>">
                <button class="btn <?= !empty($screensRedacted) ? 'btn--ghost' : '' ?>" type="submit">
                    <?= !empty($screensRedacted) ? 'Rendre les écrans intégraux' : 'Caviarder les écrans de travail' ?>
                </button>
            </form>
        <?php endif; ?>
    </div>
</details>

<section id="registre" class="panel sse-cases-register" aria-labelledby="registre-title">
    <div class="panel-header">
        <div class="panel-title" id="registre-title">
            <span class="panel-index">02</span>
            Registre des dossiers
        </div>
        <div class="panel-meta"><?= count($affairs) ?> affaire<?= count($affairs) !== 1 ? 's' : '' ?><?= $folders !== [] ? ' · ' . count($folders) . ' dossier(s)' : '' ?></div>
    </div>

    <?php if ($folders !== []): ?>
        <div class="panel-body">
            <div class="sse-block-title">Dossiers (conteneurs)</div>
            <div class="sse-folder-grid">
                <?php foreach ($folders as $c): ?>
                    <a class="sse-folder-card is-folder" href="<?= $h(url('atak/sse/dossiers/' . (int) $c['id'])) ?>">
                        <div class="sse-folder-card-top">
                            <span class="sse-folder-kind">Dossier</span>
                            <?php if (!empty($c['has_unlock_code'])): ?><span class="badge badge--gray">Protégé</span><?php endif; ?>
                        </div>
                        <strong><?= $h($c['title'] ?? '') ?></strong>
                        <span class="record-id"><?= $h($c['reference_code'] ?? '') ?></span>
                        <div class="sse-folder-card-meta">
                            <span class="<?= $h($classBadge((string) ($c['classification'] ?? ''))) ?>"><?= $h($c['classification_label'] ?? '') ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($affairs === [] && $folders === []): ?>
        <div class="empty-state">
            <div class="empty-state-inner">
                <div class="empty-symbol">SSE</div>
                <strong>Aucun dossier d’affaire</strong>
                <p>
                    Ouvrez une affaire ou importez un scénario pour commencer l’exploitation.
                </p>
                <?php if ($canManage): ?>
                    <div class="sse-cases-empty-actions">
                        <a class="btn" href="<?= $h(url('atak/sse/dossiers/nouveau')) ?>">Ouvrir une affaire</a>
                        <a class="btn btn--ghost" href="<?= $h(url('atak/sse/interet/nouveau')) ?>">Dossier d’intérêt</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php elseif ($affairs !== []): ?>
        <div class="sse-cases-list" role="list">
            <?php foreach ($affairs as $c):
                $cid = (int) ($c['id'] ?? 0);
                $cnt = $caseCounts[$cid] ?? ['persons' => 0, 'notes' => 0, 'evidence' => 0];
                $stamp = (string) ($c['updated_at'] ?? $c['created_at'] ?? '');
                $classKey = (string) ($c['classification'] ?? '');
                $who = \App\Services\Sse\SseClearanceService::whoCanOpen($classKey);
                ?>
                <article class="sse-cases-card" role="listitem">
                    <div class="sse-cases-card__main">
                        <div class="sse-cases-card__id">
                            <span class="record-id"><?= $h($c['reference_code'] ?? '') ?></span>
                            <?php if (!empty($c['has_unlock_code'])): ?>
                                <span class="badge badge--gray">Protégé</span>
                            <?php endif; ?>
                        </div>
                        <h3 class="sse-cases-card__title">
                            <a class="link" href="<?= $h(url('atak/sse/dossiers/' . $cid)) ?>"><?= $h($c['title'] ?? '') ?></a>
                        </h3>
                        <p class="sse-cases-card__who"><?= $h($who) ?></p>
                        <div class="sse-cases-card__tags">
                            <span class="<?= $h($classBadge($classKey)) ?>"><?= $h($c['classification_label'] ?? '') ?></span>
                            <span class="<?= $h($statusBadge((string) ($c['status'] ?? ''))) ?>"><?= $h($c['status_label'] ?? '') ?></span>
                        </div>
                    </div>
                    <div class="sse-cases-card__side">
                        <div class="sse-cases-card__counts" aria-label="Contenu">
                            <span><b><?= (int) $cnt['persons'] ?></b> pers.</span>
                            <span><b><?= (int) $cnt['notes'] ?></b> notes</span>
                            <span><b><?= (int) $cnt['evidence'] ?></b> pièces</span>
                        </div>
                        <time class="sse-cases-card__time" datetime="<?= $h(substr($stamp, 0, 19)) ?>"><?= $h($frStamp($stamp)) ?></time>
                        <a class="btn btn--ghost sse-cases-card__open" href="<?= $h(url('atak/sse/dossiers/' . $cid)) ?>">Ouvrir</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<nav class="sse-cases-ops" aria-label="Raccourcis d’exploitation">
    <a href="<?= $h(url('atak/sse/croisements')) ?>">
        <strong>Correspondances à valider</strong>
        <span>File opérateur et facteurs de rapprochement</span>
    </a>
    <a href="<?= $h(url('atak/sse/interet')) ?>">
        <strong>Acquisitions terrain</strong>
        <span>Signalements reçus et sujets à qualifier</span>
    </a>
    <a href="<?= $h(url('atak/sse/personnes')) ?>">
        <strong>Contrôles récents</strong>
        <span>Personnes et observations disponibles</span>
    </a>
    <a href="<?= $h(url('atak/sse/interet?status=en_analyse')) ?>">
        <strong>Dossiers prioritaires</strong>
        <span>Analyse et levées de doute en cours</span>
    </a>
    <a href="<?= $h(url('atak/sse/interet?status=en_collecte')) ?>">
        <strong>Collectes en attente</strong>
        <span>Besoins de renseignement à transmettre</span>
    </a>
</nav>
</div>
</div>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
