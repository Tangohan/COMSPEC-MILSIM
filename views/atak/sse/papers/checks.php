<?php
declare(strict_types=1);
use App\Support\SseIdentityCatalog as C;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $checks */
/** @var array<string,string> $filters */
require __DIR__ . '/_subnav.php';
?>

<div class="page-heading">
    <div>
        <h1>Contrôles reçus</h1>
        <p>Contrôles transmis depuis le jeu : examen visuel, lecture de la bande, de la puce et interrogation des fichiers, avec le verdict donné sur place.</p>
    </div>
</div>

<?php if (!empty($canManageSettings)): ?>
    <form class="toolbar" method="post" action="<?= $h(url('atak/sse/papiers/controles/reglages')) ?>">
        <input type="hidden" name="_csrf_token" value="<?= $h((string) ($csrfToken ?? '')) ?>">
        <label><input type="checkbox" name="auto_transmit" value="1" <?= !empty($autoTransmit) ? 'checked' : '' ?>> Envoyer automatiquement les contrôles depuis le jeu</label>
        <button type="submit" class="btn btn--ghost">Enregistrer</button>
    </form>
<?php endif; ?>

<form class="toolbar" method="get" action="<?= $h(url('atak/sse/papiers/controles')) ?>">
    <div class="toolbar-field">
        <label for="verdict">Verdict</label>
        <select id="verdict" name="verdict">
            <option value="">Tous les verdicts</option>
            <?php foreach (C::VERDICTS as $k => $label): ?>
                <option value="<?= $h($k) ?>" <?= ($filters['verdict'] ?? '') === $k ? 'selected' : '' ?>><?= $h($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="toolbar-field">
        <label for="q">Recherche</label>
        <input id="q" name="q" type="search" value="<?= $h($filters['q'] ?? '') ?>" placeholder="Numéro, nom, indicatif, carroyage…">
    </div>
    <div class="toolbar-actions"><button class="btn" type="submit">Appliquer</button></div>
</form>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">01</span> Contrôles</div>
        <div class="panel-meta"><?= count($checks) ?> contrôle(s)</div>
    </div>
    <?php if ($checks === []): ?>
        <div class="empty-state"><div class="empty-state-inner"><strong>Aucun contrôle reçu</strong><p>Les contrôles apparaissent ici quand un joueur les transmet depuis son terminal en jeu.</p></div></div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Contrôle</th><th>Reçu le</th><th>Papier présenté</th><th>Nom présenté</th><th>Contrôleur</th><th>Niveaux</th><th>Verdict</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($checks as $c): ?>
                    <tr>
                        <td><?= $h($c['ref']) ?></td>
                        <td><?= $h($c['created_at']) ?></td>
                        <td><?= $h(trim(($c['country_code'] ?? '') . ' ' . ($c['doc_number'] ?? '')) ?: '—') ?></td>
                        <td><?= $h(trim(mb_strtoupper((string) ($c['presented_last'] ?? '')) . ' ' . ($c['presented_first'] ?? '')) ?: '—') ?></td>
                        <td><?= $h($c['controller_callsign'] ?? '—') ?></td>
                        <td><?= $h(implode(' ', $c['levels'])) ?></td>
                        <td><span class="badge idp-verdict--<?= $h($c['verdict'] !== '' ? $c['verdict'] : 'none') ?>"><?= $h($c['verdict_label']) ?></span></td>
                        <td><a class="btn-open" href="<?= $h(url('atak/sse/papiers/controles/' . (int) $c['id'])) ?>">Ouvrir</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
