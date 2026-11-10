<?php
declare(strict_types=1);
use App\Support\SseFileCatalog as F;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var string $queryType */
/** @var array<string,string> $criteria */
/** @var array<string,mixed>|null $result */
/** @var list<array<string,mixed>> $recent */
$filesTab = 'consultation';
require __DIR__ . '/_nav.php';
$c = $criteria;
$types = [
    'QW' => 'Nom, prénom et date de naissance déclarés.',
    'QV' => 'Plaque d’immatriculation.',
    'QA' => 'Numéro du papier ; pays et type si connus, sinon cherché partout.',
    'QB' => 'Clé biométrique ou numéro d’enregistrement SEEK II (REC-…).',
];
?>
<div class="breadcrumb">Athena / SSE / <a class="link" href="<?= $h(url('atak/sse/papiers')) ?>">Identités &amp; documents</a> / <strong>Passage aux fichiers</strong></div>

<div class="page-heading">
    <div>
        <h1>Passage aux fichiers</h1>
        <p>Interrogez les fichiers de la communauté comme depuis un terminal de patrouille : personne, véhicule, document ou biométrie. Chaque consultation est inscrite au journal, et ce que vous changez ici se voit en jeu à la consultation suivante.</p>
    </div>
    <div class="page-reference"><strong>SIMULATION</strong> Données fictives</div>
</div>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title">Nouvelle requête</div>
        <div class="panel-meta"><?= $h($types[$queryType]) ?></div>
    </div>
    <div class="panel-body">
        <nav class="fq-tabs" aria-label="Type de requête">
            <?php foreach (F::QUERY_TYPES as $code => $label): ?>
                <a class="<?= $queryType === $code ? 'is-active' : '' ?>" href="<?= $h(url('atak/sse/fichiers') . '?type=' . $code) ?>" <?= $queryType === $code ? 'aria-current="page"' : '' ?>><?= $h($label) ?> <span class="fq-code"><?= $h($code) ?></span></a>
            <?php endforeach; ?>
        </nav>
        <form class="fq-form" method="get" action="<?= $h(url('atak/sse/fichiers/requete')) ?>">
            <input type="hidden" name="type" value="<?= $h($queryType) ?>">
            <?php if ($queryType === 'QW'): ?>
                <div><label for="fq-last">Nom</label><input id="fq-last" name="last" type="text" value="<?= $h($c['last'] ?? '') ?>" autocomplete="off" required></div>
                <div><label for="fq-first">Prénom</label><input id="fq-first" name="first" type="text" value="<?= $h($c['first'] ?? '') ?>" autocomplete="off"></div>
                <div><label for="fq-dob">Date de naissance</label><input id="fq-dob" name="dob" type="date" value="<?= $h($c['dob'] ?? '') ?>"></div>
            <?php elseif ($queryType === 'QV'): ?>
                <div><label for="fq-number">Immatriculation</label><input id="fq-number" name="number" type="text" value="<?= $h($c['number'] ?? '') ?>" autocomplete="off" required></div>
            <?php elseif ($queryType === 'QB'): ?>
                <div><label for="fq-number">Clé biométrique ou REC-…</label><input id="fq-number" name="number" type="text" value="<?= $h($c['number'] ?? '') ?>" autocomplete="off" required></div>
            <?php else: ?>
                <div><label for="fq-number">Numéro du papier</label><input id="fq-number" name="number" type="text" value="<?= $h($c['number'] ?? '') ?>" autocomplete="off" required></div>
                <div><label for="fq-country">Pays (code)</label><input id="fq-country" name="country" type="text" maxlength="3" value="<?= $h($c['country'] ?? '') ?>" placeholder="Tous les pays"></div>
                <div>
                    <label for="fq-doctype">Type de papier</label>
                    <select id="fq-doctype" name="doc_type">
                        <option value="">Tous les types</option>
                        <?php foreach ($docTypes as $k => $label): ?>
                            <option value="<?= $h($k) ?>" <?= ($c['doc_type'] ?? '') === $k ? 'selected' : '' ?>><?= $h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label for="fq-last">Nom présenté (facultatif)</label><input id="fq-last" name="last" type="text" value="<?= $h($c['last'] ?? '') ?>" autocomplete="off"></div>
            <?php endif; ?>
            <div class="fq-form__submit"><button class="btn" type="submit">Interroger les fichiers</button></div>
        </form>
        <?php if ($queryType === 'QV'): ?>
            <p class="fq-hint">Le registre de la communauté n’a pas de fichier des véhicules : la réponse est NO HIT, avec le code no_vehicle_file.</p>
        <?php endif; ?>
    </div>
</section>

<?php if (is_array($result)): ?>
    <?php require __DIR__ . '/_result.php'; ?>
<?php endif; ?>

<?php if ($recent !== []): ?>
    <section class="panel">
        <div class="panel-header">
            <div class="panel-title">Dernières consultations</div>
            <div class="panel-meta"><a class="link" href="<?= $h(url('atak/sse/fichiers/journal')) ?>">Tout le journal</a></div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Requête</th><th>Heure</th><th>Type</th><th>Source</th><th>Réponse</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($recent as $r): ?>
                    <tr>
                        <td><span translate="no"><?= $h($r['qrn']) ?></span></td>
                        <td><?= $h($r['created_at']) ?></td>
                        <td><?= $h($r['query_type_label']) ?> <span class="fq-code"><?= $h($r['query_type']) ?></span></td>
                        <td><?= $h($r['source_label']) ?></td>
                        <td><span class="fq-hit <?= $r['hit'] ? 'fq-hit--yes' : 'fq-hit--no' ?>"><?= $r['hit'] ? 'HIT' : 'NO HIT' ?></span></td>
                        <td><a class="btn-open" href="<?= $h(\App\Controllers\Web\SseFileController::replayUrl((string) $r['query_type'], \App\Controllers\Web\SseFileController::replayCriteria($r['criteria']))) ?>">Rejouer</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
