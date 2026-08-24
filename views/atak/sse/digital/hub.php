<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array{devices:int,acquisitions:int,artifacts:int,findings_pending:int} $counts */
/** @var list<array<string,mixed>> $recentDevices */
/** @var list<array<string,mixed>> $pendingFindings */
$counts = is_array($counts ?? null) ? $counts : [];
$recentDevices = is_array($recentDevices ?? null) ? $recentDevices : [];
$pendingFindings = is_array($pendingFindings ?? null) ? $pendingFindings : [];
$canManage = (bool) ($canManage ?? false);

$nDevices = (int) ($counts['devices'] ?? 0);
$nAcq = (int) ($counts['acquisitions'] ?? 0);
$nArt = (int) ($counts['artifacts'] ?? 0);
$nPending = (int) ($counts['findings_pending'] ?? 0);
$nPackets = (int) ($counts['packets_pending'] ?? 0);
$pendingPackets = is_array($pendingPackets ?? null) ? $pendingPackets : [];
$isEmpty = $nDevices < 1;

require __DIR__ . '/_subnav.php';
?>
<div class="breadcrumb">Athena / SSE / <strong>Exploitation numérique</strong></div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">Laboratoire numérique</div>
        <h1>Exploitation numérique</h1>
        <p>
            Traitez ici les matériels saisis en mission : téléphone, ordinateur, clé USB, carte mémoire…
            Vous enregistrez le support, vous y rattachez une acquisition, puis vous examinez les
            données et les signaux avant de produire un compte rendu.
        </p>
    </div>
    <?php if ($canManage): ?>
        <div class="page-reference">
            <a class="btn" href="<?= $h(url('atak/sse/exploitation-numerique/supports/nouveau')) ?>">
                Enregistrer un support
            </a>
        </div>
    <?php endif; ?>
</div>

<?php if ($isEmpty): ?>
<section class="lab-start panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">00</span> Par où commencer</div>
        <div class="panel-meta">Chaîne courte</div>
    </div>
    <div class="panel-body">
        <ol class="lab-start__steps">
            <li>
                <strong>1. Enregistrer le support</strong>
                <span>Fiche du matériel saisi (type, état, rattachement éventuel à un dossier).</span>
            </li>
            <li>
                <strong>2. Déclarer une acquisition</strong>
                <span>Copie logique ou extraction déjà réalisée en simulation / import — Athena n’ouvre pas l’appareil réel.</span>
            </li>
            <li>
                <strong>3. Examiner puis valider</strong>
                <span>Les signaux automatiques sont des propositions. Seule une validation humaine consolide le renseignement.</span>
            </li>
        </ol>
        <div class="lab-start__actions">
            <?php if ($canManage): ?>
                <a class="btn" href="<?= $h(url('atak/sse/exploitation-numerique/supports/nouveau')) ?>">Commencer — enregistrer un support</a>
            <?php endif; ?>
            <a class="btn btn--ghost" href="<?= $h(url('atak/sse/collecte')) ?>">Voir la collecte terrain</a>
        </div>
    </div>
</section>
<?php else: ?>
<div class="metrics-grid lab-metrics">
    <a class="metric lab-metric" href="<?= $h(url('atak/sse/exploitation-numerique/supports')) ?>">
        <div class="metric-label">Supports</div>
        <div class="metric-value"><?= $nDevices ?></div>
        <div class="metric-detail">Matériels enregistrés →</div>
    </a>
    <a class="metric lab-metric" href="<?= $h(url('atak/sse/exploitation-numerique/acquisitions')) ?>">
        <div class="metric-label">Acquisitions</div>
        <div class="metric-value"><?= $nAcq ?></div>
        <div class="metric-detail">Copies / extractions →</div>
    </a>
    <a class="metric lab-metric" href="<?= $h(url('atak/sse/exploitation-numerique/artefacts')) ?>">
        <div class="metric-label">Artefacts</div>
        <div class="metric-value"><?= $nArt ?></div>
        <div class="metric-detail">Données indexées →</div>
    </a>
    <a class="metric lab-metric<?= $nPending > 0 ? ' is-attention' : '' ?>" href="<?= $h(url('atak/sse/exploitation-numerique/analyses')) ?>">
        <div class="metric-label">À examiner</div>
        <div class="metric-value"><?= $nPending ?></div>
        <div class="metric-detail">Signaux en attente →</div>
    </a>
    <a class="metric lab-metric<?= $nPackets > 0 ? ' is-attention' : '' ?>" href="<?= $h(url('atak/sse/exploitation-numerique/a-exploiter')) ?>">
        <div class="metric-label">À exploiter</div>
        <div class="metric-value"><?= $nPackets ?></div>
        <div class="metric-detail">Paquets de mission →</div>
    </a>
</div>
<?php endif; ?>

<section class="panel lab-flow">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">01</span> Chaîne d’exploitation</div>
        <div class="panel-meta">Quatre étapes utiles</div>
    </div>
    <div class="panel-body">
        <ol class="lab-flow__track">
            <li class="lab-flow__step<?= $nDevices > 0 ? ' is-done' : ' is-current' ?>">
                <span class="lab-flow__num">1</span>
                <strong>Support</strong>
                <span>Fiche du matériel saisi</span>
                <a class="link" href="<?= $h(url('atak/sse/exploitation-numerique/supports')) ?>">Registre</a>
            </li>
            <li class="lab-flow__step<?= $nAcq > 0 ? ' is-done' : ($nDevices > 0 ? ' is-current' : '') ?>">
                <span class="lab-flow__num">2</span>
                <strong>Acquisition</strong>
                <span>Copie / extraction rattachée</span>
                <a class="link" href="<?= $h(url('atak/sse/exploitation-numerique/acquisitions')) ?>">Acquisitions</a>
            </li>
            <li class="lab-flow__step<?= $nPending > 0 ? ' is-current' : ($nArt > 0 ? ' is-done' : '') ?>">
                <span class="lab-flow__num">3</span>
                <strong>Analyse</strong>
                <span>Artefacts et signaux à valider</span>
                <a class="link" href="<?= $h(url('atak/sse/exploitation-numerique/analyses')) ?>">Signaux</a>
            </li>
            <li class="lab-flow__step">
                <span class="lab-flow__num">4</span>
                <strong>Compte rendu</strong>
                <span>Produit après relecture humaine</span>
                <a class="link" href="<?= $h(url('atak/sse/exploitation-numerique/rapports')) ?>">Rapports</a>
            </li>
        </ol>
        <p class="lab-flow__note">
            Athena n’ouvre pas un appareil réel : le module ingère des données déjà acquises
            (simulation Arma ou import documentaire). Un signal automatique n’est jamais une conclusion.
        </p>
    </div>
</section>

<div class="iw-tower-grid">
    <section class="panel">
        <div class="panel-header">
            <div class="panel-title"><span class="panel-index">02</span> Supports récents</div>
            <a class="link" href="<?= $h(url('atak/sse/exploitation-numerique/supports')) ?>">Tous les supports</a>
        </div>
        <?php if ($recentDevices === []): ?>
            <div class="empty-state">
                <div class="empty-state-inner">
                    <div class="empty-symbol">SUP</div>
                    <strong>Aucun support enregistré</strong>
                    <p>Commencez par créer la fiche du matériel saisi sur le terrain.</p>
                    <?php if ($canManage): ?>
                        <a class="btn btn--ghost" href="<?= $h(url('atak/sse/exploitation-numerique/supports/nouveau')) ?>">Enregistrer un support</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Référence</th><th>Type</th><th>Statut</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($recentDevices as $d): ?>
                        <tr>
                            <td class="record-id"><?= $h($d['reference_code'] ?? '') ?></td>
                            <td><?= $h(trim((string) ($d['device_type_label'] ?? '')) ?: 'Inconnu') ?></td>
                            <td><span class="badge"><?= $h($d['status_label'] ?? '') ?></span></td>
                            <td><a class="btn-open" href="<?= $h(url('atak/sse/exploitation-numerique/supports/' . (int) ($d['id'] ?? 0))) ?>">Ouvrir</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div class="panel-title"><span class="panel-index">03</span> Signaux à examiner</div>
            <?php if ($nPending > 0): ?>
                <a class="link" href="<?= $h(url('atak/sse/exploitation-numerique/analyses')) ?>">Voir la file (<?= $nPending ?>)</a>
            <?php else: ?>
                <a class="link" href="<?= $h(url('atak/sse/exploitation-numerique/analyses')) ?>">Ouvrir les analyses</a>
            <?php endif; ?>
        </div>
        <?php if ($pendingFindings === []): ?>
            <div class="empty-state">
                <div class="empty-state-inner">
                    <div class="empty-symbol">OK</div>
                    <strong>Rien en attente</strong>
                    <p>
                        <?= $isEmpty
                            ? 'Les signaux apparaîtront après acquisition et extraction sur un support.'
                            : 'Aucun signal automatique n’attend votre validation pour le moment.' ?>
                    </p>
                </div>
            </div>
        <?php else: ?>
            <div class="panel-body">
                <?php foreach (array_slice($pendingFindings, 0, 6) as $f): ?>
                    <div class="iw-alert is-moderee" style="margin-bottom:8px">
                        <strong><?= $h($f['title'] ?? '') ?></strong>
                        <p><?= $h(mb_substr((string) ($f['detail'] ?? ''), 0, 160)) ?><?= mb_strlen((string) ($f['detail'] ?? '')) > 160 ? '…' : '' ?></p>
                        <em>Confiance : <?= $h($f['confidence_label'] ?? '') ?> — validation humaine requise</em>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php if ($pendingPackets !== []): ?>
<section class="panel" style="margin-top:10px">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">03b</span> Renseignement à exploiter</div>
        <a class="link" href="<?= $h(url('atak/sse/exploitation-numerique/a-exploiter')) ?>">Ouvrir la file (<?= $nPackets ?>)</a>
    </div>
    <div class="panel-body">
        <?php foreach (array_slice($pendingPackets, 0, 5) as $p): ?>
            <div class="iw-alert is-moderee" style="margin-bottom:8px">
                <strong><?= $h($p['support_label'] ?? '') ?> — <?= $h($p['packet_type_label'] ?? '') ?></strong>
                <p><?= $h(mb_substr((string) ($p['body_text'] ?? ''), 0, 160)) ?><?= mb_strlen((string) ($p['body_text'] ?? '')) > 160 ? '…' : '' ?></p>
                <em><?= $h($p['quality_label'] ?? '') ?> · <?= $h($p['confidence_label'] ?? '') ?></em>
                <div><a class="link" href="<?= $h(url('atak/sse/exploitation-numerique/a-exploiter/' . (int) ($p['id'] ?? 0))) ?>">Ouvrir</a></div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="panel" style="margin-top:10px">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">04</span> Lexique rapide</div>
    </div>
    <div class="panel-body lab-lexicon">
        <dl>
            <div>
                <dt>Support</dt>
                <dd>L’objet saisi (téléphone, PC, clé…).</dd>
            </div>
            <div>
                <dt>Acquisition</dt>
                <dd>La copie ou l’extraction rattachée à ce support.</dd>
            </div>
            <div>
                <dt>Artefact</dt>
                <dd>Une donnée utile extraite (message, fichier, contact…).</dd>
            </div>
            <div>
                <dt>Paquet</dt>
                <dd>Un renseignement scénarisé (message, document, point…) préparé en mission, à traiter ici.</dd>
            </div>
            <div>
                <dt>Signal</dt>
                <dd>Une proposition automatique à confirmer ou écarter.</dd>
            </div>
        </dl>
    </div>
</section>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
