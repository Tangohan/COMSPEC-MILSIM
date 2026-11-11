<?php
declare(strict_types=1);

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array{id: int, name: string, callsign: string, unit: string, steam_linked: bool, issued: int, kit: bool, last_check: ?string}> $members */
/** @var array<int, string> $units */
/** @var array{q: string, unit: int, steam: string, kit: string} $filters */
require __DIR__ . '/_subnav.php';
$yesNo = ['' => 'Tous', 'oui' => 'Oui', 'non' => 'Non'];
?>

<div class="page-heading">
    <div>
        <h1>Papiers des membres</h1>
        <p>Identités et papiers fictifs que chaque joueur porte en mission, kit de mission et contrôles subis. Seuls les papiers et le nom affiché sont montrés ici, jamais le dossier du membre.</p>
    </div>
</div>

<form class="toolbar" method="get" action="<?= $h(url('atak/sse/papiers/membres')) ?>">
    <div class="toolbar-field">
        <label for="q">Recherche</label>
        <input id="q" name="q" type="search" value="<?= $h($filters['q']) ?>" placeholder="Nom, indicatif…">
    </div>
    <div class="toolbar-field">
        <label for="unit">Unité</label>
        <select id="unit" name="unit">
            <option value="0">Toutes les unités</option>
            <?php foreach ($units as $uid => $label): ?>
                <option value="<?= (int) $uid ?>" <?= $filters['unit'] === (int) $uid ? 'selected' : '' ?>><?= $h($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="toolbar-field">
        <label for="steam">Compte Steam lié</label>
        <select id="steam" name="steam">
            <?php foreach ($yesNo as $k => $label): ?>
                <option value="<?= $h($k) ?>" <?= $filters['steam'] === $k ? 'selected' : '' ?>><?= $h($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="toolbar-field">
        <label for="kit">Kit de mission défini</label>
        <select id="kit" name="kit">
            <?php foreach ($yesNo as $k => $label): ?>
                <option value="<?= $h($k) ?>" <?= $filters['kit'] === $k ? 'selected' : '' ?>><?= $h($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="toolbar-actions"><button class="btn" type="submit">Appliquer</button></div>
</form>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">01</span> Membres</div>
        <div class="panel-meta"><?= count($members) ?> membre(s)</div>
    </div>
    <?php if ($members === []): ?>
        <div class="empty-state"><div class="empty-state-inner"><strong>Aucun membre</strong><p>Aucun membre de la communauté ne correspond à ces filtres.</p></div></div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Membre</th><th>Indicatif</th><th>Unité</th><th>Steam</th><th>Papiers émis</th><th>Kit</th><th>Dernier contrôle</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($members as $m): ?>
                    <tr>
                        <td><?= $h($m['name'] !== '' ? $m['name'] : '#' . $m['id']) ?></td>
                        <td><?= $h($m['callsign'] !== '' ? $m['callsign'] : '—') ?></td>
                        <td><?= $h($m['unit'] !== '' ? $m['unit'] : '—') ?></td>
                        <td><?= $m['steam_linked'] ? 'Lié' : '<span class="muted">Non lié</span>' ?></td>
                        <td><?= (int) $m['issued'] ?></td>
                        <td><?= $m['kit'] ? 'Défini' : '<span class="muted">Non défini</span>' ?></td>
                        <td><?= $h($m['last_check'] ?? '—') ?></td>
                        <td><a class="btn-open" href="<?= $h(url('atak/sse/papiers/membres/' . (int) $m['id'])) ?>">Ouvrir</a></td>
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
