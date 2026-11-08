<?php
declare(strict_types=1);

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $briefs */
/** @var list<array{id:int,name:string}> $members */
/** @var array<string,mixed>|null $edit */
/** @var list<array{id:int,label:string}> $maps */
/** @var array{view:bool,manage:bool,truth:bool} $rights */
$e = $edit ?? ['user_id' => 0, 'context_id' => null, 'op_key' => null, 'title' => '', 'biography' => '', 'legend' => '', 'contacts' => '', 'instructions' => '', 'phone' => '', 'address' => ''];
$mapLabel = static function (?int $id) use ($maps): string {
    foreach ($maps as $m) {
        if ($m['id'] === $id) {
            return $m['label'];
        }
    }

    return 'Toutes les cartes';
};
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-id-workshop.css')) . '">';
require __DIR__ . '/_subnav.php';
?>
<div class="breadcrumb">Athena / SSE / <a class="link" href="<?= $h(url('atak/sse/papiers')) ?>">Identités &amp; documents</a> / <strong>Briefs de couverture</strong></div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">Préparation // Briefs de couverture</div>
        <h1>Briefs de couverture</h1>
        <p>La fiche de couverture remise à un joueur : biographie, légende à tenir, contacts, consignes, téléphone et adresse simulés. Il la lit dans Ma situation › Mes papiers ; le serveur de jeu la reçoit avec le pack, reliée à son compte Steam. Un brief par joueur et par portée (par défaut, carte ou opération).</p>
    </div>
    <div class="page-reference"><strong><?= count($briefs) ?> brief(s)</strong> Réf. ATH-SSE-IDENT-BRF</div>
</div>

<?php if ($rights['manage']): ?>
    <form class="panel idw-form" method="post" action="<?= $h(url('atak/sse/papiers/briefs')) ?>">
        <div class="panel-header"><div class="panel-title"><span class="panel-index">01</span> <?= $edit !== null ? 'Modifier le brief' : 'Nouveau brief' ?></div></div>
        <div class="panel-body">
            <?= \App\Core\Csrf::field() ?>
            <div class="idp-statuses">
                <div>
                    <label for="b-user">Membre</label>
                    <select id="b-user" name="user_id" required>
                        <option value="">Choisir…</option>
                        <?php foreach ($members as $m): ?><option value="<?= (int) $m['id'] ?>" <?= (int) $e['user_id'] === $m['id'] ? 'selected' : '' ?>><?= $h($m['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="b-map">Carte</label>
                    <select id="b-map" name="context_id">
                        <option value="0">Toutes les cartes</option>
                        <?php foreach ($maps as $m): ?><option value="<?= (int) $m['id'] ?>" <?= (int) ($e['context_id'] ?? 0) === $m['id'] ? 'selected' : '' ?>><?= $h($m['label']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div><label for="b-op">Opération (code)</label><input id="b-op" name="op_key" value="<?= $h($e['op_key'] ?? '') ?>" maxlength="64" placeholder="Aucune"></div>
                <div><label for="b-title">Titre</label><input id="b-title" name="title" value="<?= $h($e['title']) ?>" maxlength="160" placeholder="Fiche de couverture"></div>
                <div><label for="b-phone">Téléphone simulé</label><input id="b-phone" name="phone" value="<?= $h($e['phone'] ?? '') ?>" maxlength="40"></div>
                <div><label for="b-address">Adresse (fictive)</label><input id="b-address" name="address" value="<?= $h($e['address'] ?? '') ?>" maxlength="255"></div>
                <div style="grid-column: 1 / -1"><label for="b-bio">Biographie</label><textarea id="b-bio" name="biography" maxlength="2000"><?= $h($e['biography'] ?? '') ?></textarea></div>
                <div style="grid-column: 1 / -1"><label for="b-legend">Légende</label><textarea id="b-legend" name="legend" maxlength="2000"><?= $h($e['legend'] ?? '') ?></textarea></div>
                <div style="grid-column: 1 / -1"><label for="b-contacts">Contacts</label><textarea id="b-contacts" name="contacts" maxlength="1000"><?= $h($e['contacts'] ?? '') ?></textarea></div>
                <div style="grid-column: 1 / -1"><label for="b-instr">Consignes</label><textarea id="b-instr" name="instructions" maxlength="2000"><?= $h($e['instructions'] ?? '') ?></textarea></div>
            </div>
            <p class="idw-note">Un brief du même membre pour la même portée est remplacé.</p>
            <div class="idp-actions"><button class="btn" type="submit">Enregistrer le brief</button></div>
        </div>
    </form>
<?php endif; ?>

<section class="panel" style="margin-top:10px">
    <div class="panel-header"><div class="panel-title"><span class="panel-index">02</span> Briefs remis</div></div>
    <div class="panel-body">
        <?php if ($briefs === []): ?>
            <p class="muted">Aucun brief. Ils se créent ici ou en affectant un kit type à des membres.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Membre</th><th>Titre</th><th>Portée</th><th>Mis à jour</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($briefs as $b): ?>
                        <tr>
                            <td><?= $h($b['user_name'] !== '' ? $b['user_name'] : '#' . $b['user_id']) ?><?php if (!$b['steam_linked']): ?> <span class="badge" title="Le serveur de jeu ne peut pas lui remettre ce brief tant que son compte n’est pas lié à Steam.">Steam non lié</span><?php endif; ?></td>
                            <td><?= $h($b['title']) ?></td>
                            <td><?= $h($b['op_key'] !== null ? 'Opération ' . $b['op_key'] : $mapLabel($b['context_id'])) ?></td>
                            <td><?= $h(substr((string) ($b['updated_at'] ?? $b['created_at']), 0, 16)) ?></td>
                            <td class="idp-actions">
                                <?php if ($rights['manage']): ?>
                                    <a class="btn-open" href="<?= $h(url('atak/sse/papiers/briefs') . '?modifier=' . (int) $b['id']) ?>">Modifier</a>
                                    <form method="post" action="<?= $h(url('atak/sse/papiers/briefs/' . (int) $b['id'] . '/supprimer')) ?>">
                                        <?= \App\Core\Csrf::field() ?>
                                        <button class="btn btn--ghost" type="submit">Supprimer</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
