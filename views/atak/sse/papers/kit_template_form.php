<?php
declare(strict_types=1);
use App\Support\SseIdentityCatalog as C;
use App\Support\SseIdWorkshopCatalog as W;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed> $template */
/** @var list<array<string,mixed>> $countries */
/** @var list<array{id:int,name:string,callsign:string,unit:string}> $members */
/** @var list<array{id:int,label:string}> $maps */
/** @var array{view:bool,manage:bool,truth:bool} $rights */
$t = $template;
$id = (int) $t['id'];
$dis = $rights['manage'] ? '' : ' disabled';
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-id-workshop.css')) . '">';
require __DIR__ . '/_subnav.php';
?>
<div class="breadcrumb">Athena / SSE / <a class="link" href="<?= $h(url('atak/sse/papiers')) ?>">Identités &amp; documents</a> / <a class="link" href="<?= $h(url('atak/sse/papiers/kits-types')) ?>">Kits types</a> / <strong><?= $h($id > 0 ? $t['name'] : 'Nouveau kit') ?></strong></div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">Kit documentaire type // <?= $h(W::label(W::KIT_CATEGORIES, (string) $t['category'])) ?></div>
        <h1><?= $h($id > 0 ? $t['name'] : 'Nouveau kit type') ?></h1>
        <p>Le contenu du kit sert de point de départ : chaque membre équipé reçoit ses propres papiers, que l’on peut ensuite retoucher dans l’atelier.</p>
    </div>
    <?php if ($id > 0): ?><div class="page-reference"><strong translate="no"><?= $h($t['ref']) ?></strong></div><?php endif; ?>
</div>

<form class="panel idw-form" method="post" action="<?= $h(url('atak/sse/papiers/kits-types' . ($id > 0 ? '/' . $id : ''))) ?>">
    <div class="panel-header"><div class="panel-title"><span class="panel-index">01</span> Contenu du kit</div></div>
    <div class="panel-body">
        <?= \App\Core\Csrf::field() ?>
        <fieldset>
            <legend>Papiers</legend>
            <div class="idp-statuses">
                <div><label for="k-name">Nom du kit</label><input id="k-name" name="name" value="<?= $h($t['name']) ?>" maxlength="120" required<?= $dis ?>></div>
                <div>
                    <label for="k-cat">Catégorie</label>
                    <select id="k-cat" name="category"<?= $dis ?>>
                        <?php foreach (W::KIT_CATEGORIES as $k => $label): ?><option value="<?= $h($k) ?>" <?= $t['category'] === $k ? 'selected' : '' ?>><?= $h($label) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="k-country">Pays émetteur</label>
                    <select id="k-country" name="country_code"<?= $dis ?>>
                        <?php foreach ($countries as $c): ?><option value="<?= $h($c['code']) ?>" <?= ($t['country_code'] ?? '') === $c['code'] ? 'selected' : '' ?>><?= $h($c['name']) ?> (<?= $h($c['code']) ?>)</option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="k-mode">Identité qui porte les papiers</label>
                    <select id="k-mode" name="identity_mode"<?= $dis ?>>
                        <?php foreach (W::IDENTITY_MODES as $k => $label): ?>
                            <?php if ($k === 'cover' && !$rights['truth'] && $t['identity_mode'] !== 'cover') { continue; } ?>
                            <option value="<?= $h($k) ?>" <?= $t['identity_mode'] === $k ? 'selected' : '' ?>><?= $h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="k-layout">Modèle d’impression</label>
                    <select id="k-layout" name="layout"<?= $dis ?>>
                        <?php foreach (W::LAYOUTS as $k => $label): ?><option value="<?= $h($k) ?>" <?= $t['layout'] === $k ? 'selected' : '' ?>><?= $h($label) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div><label for="k-prof">Profession</label><input id="k-prof" name="profession" value="<?= $h($t['profession'] ?? '') ?>" maxlength="120"<?= $dis ?>></div>
                <div style="grid-column: 1 / -1">
                    <span class="idw-note">Types de papiers</span>
                    <div class="idw-chips">
                        <?php foreach (C::DOC_TYPES as $k => $label): ?>
                            <label><input type="checkbox" name="doc_types[]" value="<?= $h($k) ?>" <?= in_array($k, $t['doc_types'], true) ? 'checked' : '' ?><?= $dis ?>> <?= $h($label) ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div style="grid-column: 1 / -1"><label for="k-descr">Description</label><input id="k-descr" name="description" value="<?= $h($t['description'] ?? '') ?>" maxlength="500"<?= $dis ?>></div>
            </div>
        </fieldset>
        <fieldset>
            <legend>Champs pré-remplis</legend>
            <div class="idp-statuses">
                <?php foreach (W::PRINT_FIELDS as $k => [$label, $max]): ?>
                    <?php if (in_array($k, ['phone', 'address'], true)) { continue; } ?>
                    <div><label for="k-p-<?= $h($k) ?>"><?= $h($label) ?></label><input id="k-p-<?= $h($k) ?>" name="print[<?= $h($k) ?>]" value="<?= $h($t['print'][$k] ?? '') ?>" maxlength="<?= (int) $max ?>"<?= $dis ?>></div>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <fieldset>
            <legend>Couverture et légende</legend>
            <div class="idp-statuses">
                <div><label for="k-phone">Téléphone simulé</label><input id="k-phone" name="phone" value="<?= $h($t['phone'] ?? '') ?>" maxlength="40" placeholder="+000 00 00 00 00"<?= $dis ?>></div>
                <div><label for="k-address">Adresse (fictive)</label><input id="k-address" name="address" value="<?= $h($t['address'] ?? '') ?>" maxlength="255"<?= $dis ?>></div>
                <div style="grid-column: 1 / -1"><label for="k-bio">Biographie</label><textarea id="k-bio" name="biography" maxlength="2000"<?= $dis ?>><?= $h($t['biography'] ?? '') ?></textarea></div>
                <div style="grid-column: 1 / -1"><label for="k-legend">Légende (histoire à tenir)</label><textarea id="k-legend" name="legend" maxlength="2000"<?= $dis ?>><?= $h($t['legend'] ?? '') ?></textarea></div>
                <div style="grid-column: 1 / -1"><label for="k-contacts">Contacts</label><textarea id="k-contacts" name="contacts" maxlength="1000"<?= $dis ?>><?= $h($t['contacts'] ?? '') ?></textarea></div>
                <div style="grid-column: 1 / -1"><label for="k-instr">Consignes</label><textarea id="k-instr" name="instructions" maxlength="2000"<?= $dis ?>><?= $h($t['instructions'] ?? '') ?></textarea></div>
            </div>
        </fieldset>
        <?php if ($rights['manage']): ?><div class="idp-actions"><button class="btn" type="submit">Enregistrer le kit</button></div><?php endif; ?>
    </div>
</form>

<?php if ($id > 0 && $rights['manage'] && !$t['archived']): ?>
    <form class="panel idw-form" style="margin-top:10px" method="post" action="<?= $h(url('atak/sse/papiers/kits-types/' . $id . '/affecter')) ?>">
        <div class="panel-header"><div class="panel-title"><span class="panel-index">02</span> Affecter à des membres</div><div class="panel-meta">Crée les papiers, le kit de mission et le brief de chaque membre</div></div>
        <div class="panel-body">
            <?= \App\Core\Csrf::field() ?>
            <div class="idp-statuses">
                <div>
                    <label for="a-map">Carte</label>
                    <select id="a-map" name="context_id">
                        <option value="0">Toutes les cartes (kit par défaut)</option>
                        <?php foreach ($maps as $m): ?><option value="<?= (int) $m['id'] ?>"><?= $h($m['label']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div><label for="a-op">Opération (code)</label><input id="a-op" name="op_key" maxlength="64" placeholder="Aucune"></div>
                <div><label class="idw-check"><input type="checkbox" name="issue" value="1" checked> Émettre les papiers tout de suite (sinon brouillons)</label></div>
            </div>
            <?php if ($members === []): ?>
                <p class="muted">Aucun membre trouvé.</p>
            <?php else: ?>
                <div class="table-wrap" style="max-height:340px;overflow:auto;margin-top:8px">
                    <table>
                        <thead><tr><th></th><th>Membre</th><th>Indicatif</th><th>Unité</th></tr></thead>
                        <tbody>
                        <?php foreach ($members as $m): ?>
                            <tr>
                                <td><input type="checkbox" name="user_ids[]" value="<?= (int) $m['id'] ?>" aria-label="Choisir <?= $h($m['name']) ?>"></td>
                                <td><?= $h($m['name']) ?></td>
                                <td><?= $h($m['callsign'] !== '' ? $m['callsign'] : '—') ?></td>
                                <td><?= $h($m['unit'] !== '' ? $m['unit'] : '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($t['identity_mode'] === 'primary'): ?><p class="idw-note">Les membres sans identité principale sont ignorés : créez-la dans leurs papiers.</p><?php endif; ?>
                <div class="idp-actions" style="margin-top:8px"><button class="btn" type="submit">Affecter le kit</button></div>
            <?php endif; ?>
        </div>
    </form>
<?php endif; ?>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
