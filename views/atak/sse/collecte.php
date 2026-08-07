<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $recentPeople */
/** @var list<array<string,mixed>> $recentSites */
/** @var bool $canManage */
$recentPeople = is_array($recentPeople ?? null) ? $recentPeople : [];
$recentSites = is_array($recentSites ?? null) ? $recentSites : [];
?>
<div class="page-heading">
    <div>
        <div class="page-heading-overline">Exploitation // Collecte</div>
        <h1>Collecte terrain</h1>
        <p>
            Entrées issues des terminaux et de la liaison Athena : identités, sites,
            photos et relevés. Chaque acquisition doit être rattachée à un dossier
            ou une investigation avant validation.
        </p>
    </div>
    <div class="page-reference">
        <strong>Vue // Collecte</strong>
        Réf. ATH-SSE-COLLECTE
    </div>
</div>

<div class="sse-ops-grid">
    <a href="<?= $h(url('atak/sse/identites')) ?>">
        <strong>Identités</strong>
        <span>Fiches personnes remontées depuis le terrain (SEEK / terminal)</span>
    </a>
    <a href="<?= $h(url('atak/sse/sites')) ?>">
        <strong>Sites</strong>
        <span>Lieux fouillés, pièces et saisies associées</span>
    </a>
    <a href="<?= $h(url('atak/sse/exploitation-numerique')) ?>">
        <strong>Exploitation numérique</strong>
        <span>Supports saisis, acquisitions et signaux à instruire</span>
    </a>
    <a href="<?= $h(url('atak')) ?>">
        <strong>Carte Athena</strong>
        <span>Photos terrain, positions et contexte cartographique</span>
    </a>
</div>

<div class="iw-tower-grid" style="margin-top:14px">
    <section class="panel">
        <div class="panel-header">
            <div class="panel-title"><span class="panel-index">C.01</span> Identités récentes</div>
            <div class="panel-meta"><?= count($recentPeople) ?></div>
        </div>
        <?php if ($recentPeople === []): ?>
            <div class="panel-body">
                <p class="muted">Aucune fiche identité récente. Les acquisitions terrain apparaîtront ici.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Nom</th><th>Indicatif</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($recentPeople as $p): ?>
                        <tr>
                            <td><?= $h($p['display_name'] ?? '') ?></td>
                            <td class="muted"><?= $h($p['submitter_callsign'] ?? '—') ?></td>
                            <td><a class="btn-open" href="<?= $h(url('atak/sse/identites/' . (int) ($p['id'] ?? 0))) ?>">Ouvrir</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div class="panel-title"><span class="panel-index">C.02</span> Sites récents</div>
            <div class="panel-meta"><?= count($recentSites) ?></div>
        </div>
        <?php if ($recentSites === []): ?>
            <div class="panel-body">
                <p class="muted">Aucun site récent. Les fouilles et localisations terrain alimentent ce panneau.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Référence</th><th>Libellé</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($recentSites as $s): ?>
                        <tr>
                            <td class="record-id"><?= $h($s['reference_code'] ?? '') ?></td>
                            <td><?= $h($s['name'] ?? '') ?></td>
                            <td><a class="btn-open" href="<?= $h(url('atak/sse/sites/' . (int) ($s['id'] ?? 0))) ?>">Ouvrir</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<div class="security-notice" style="margin-top:14px">
    <div class="security-notice-code">COL</div>
    <div>
        <strong>Règle de collecte</strong>
        <span>
            Une acquisition terrain n’est pas une preuve tant qu’elle n’a pas été rattachée,
            classée et validée par un opérateur habilité.
        </span>
    </div>
</div>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
