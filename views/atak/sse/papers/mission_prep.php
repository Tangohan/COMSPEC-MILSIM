<?php
declare(strict_types=1);
use App\Support\SseIdentityCatalog as C;
use App\Support\SseIdWorkshopCatalog as W;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $countries */
/** @var list<array{id:int,label:string}> $maps */
/** @var array{map:int,op:string,set:bool} $scope */
/** @var list<array{context_id:?int,op_key:?string}> $scopes */
/** @var array<string,mixed>|null $params */
/** @var int $seed */
/** @var array{profiles:list<array<string,mixed>>,summary:array<string,mixed>}|null $preview */
/** @var array<string,mixed>|null $board */
/** @var array{view:bool,manage:bool,truth:bool} $rights */
$p = $params ?? [
    'label' => '', 'countries' => [], 'doc_types' => ['id_card'], 'language' => '', 'zone' => '', 'age_min' => 18, 'age_max' => 65,
    'female_pct' => 50, 'visible_sides' => ['west'], 'categories' => [
        'civilian' => ['count' => 20, 'side' => 'civ', 'faction' => '', 'profession' => '', 'forged' => 0, 'expired' => 5, 'stolen' => 0],
        'military' => ['count' => 0, 'side' => 'east', 'faction' => '', 'profession' => '', 'forged' => 0, 'expired' => 0, 'stolen' => 0],
        'suspect' => ['count' => 5, 'side' => 'guer', 'faction' => '', 'profession' => '', 'forged' => $rights['truth'] ? 30 : 0, 'expired' => 10, 'stolen' => 10],
        'hvt' => ['count' => 1, 'side' => 'guer', 'faction' => '', 'profession' => '', 'forged' => $rights['truth'] ? 60 : 0, 'expired' => 0, 'stolen' => 0],
    ],
];
$mapLabel = static function (?int $id) use ($maps): string {
    foreach ($maps as $m) {
        if ($m['id'] === $id) {
            return $m['label'];
        }
    }

    return $id !== null && $id > 0 ? '#' . $id : 'Toutes les cartes';
};
$flagLabels = ['genuine' => 'En règle', 'forged' => 'Faux', 'expired' => 'Expiré', 'stolen' => 'Volé'];
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-id-workshop.css')) . '">';
require __DIR__ . '/_subnav.php';
?>

<div class="page-heading">
    <div>
        <h1>Identités et couvertures de l’opération</h1>
        <p>Générez en un coup la population d’une opération (civils, militaires, suspects, cibles prioritaires) avec leurs papiers, vérifiez l’aperçu, puis validez : le serveur de jeu donne ces identités à ses IA au lieu d’en inventer. Les recherchés et les HVT de l’opération, et les camps qui y ont accès, se règlent ici.</p>
    </div>
    <div class="page-reference"><strong><?= $h($scope['set'] ? ($scope['op'] !== '' ? $scope['op'] : 'Hors opération') . ' · ' . $mapLabel($scope['map'] > 0 ? $scope['map'] : null) : 'Choisissez une opération') ?></strong></div>
</div>

<form class="toolbar" method="get" action="<?= $h(url('atak/sse/papiers/preparation')) ?>">
    <div class="toolbar-field">
        <label for="s-map">Carte</label>
        <select id="s-map" name="map">
            <option value="0">Toutes les cartes</option>
            <?php foreach ($maps as $m): ?><option value="<?= (int) $m['id'] ?>" <?= $scope['map'] === $m['id'] ? 'selected' : '' ?>><?= $h($m['label']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="toolbar-field">
        <label for="s-op">Opération (code)</label>
        <input id="s-op" name="op" value="<?= $h($scope['op']) ?>" maxlength="64" list="s-ops" placeholder="OP-EXEMPLE">
        <datalist id="s-ops"><?php foreach ($scopes as $s): ?><?php if ($s['op_key'] !== null): ?><option value="<?= $h($s['op_key']) ?>"></option><?php endif; ?><?php endforeach; ?></datalist>
    </div>
    <div class="toolbar-actions"><button class="btn" type="submit">Ouvrir l’opération</button></div>
</form>

<?php if ($scope['set']): ?>
    <form class="panel idw-form" style="margin-top:10px" method="get" action="<?= $h(url('atak/sse/papiers/preparation')) ?>">
        <div class="panel-header"><div class="panel-title"><span class="panel-index">01</span> Générer la population</div><div class="panel-meta">Aperçu sans rien enregistrer</div></div>
        <div class="panel-body">
            <input type="hidden" name="apercu" value="1">
            <input type="hidden" name="map" value="<?= (int) $scope['map'] ?>">
            <input type="hidden" name="op" value="<?= $h($scope['op']) ?>">
            <div class="idp-statuses">
                <div><label for="g-label">Nom du lot</label><input id="g-label" name="label" value="<?= $h($p['label']) ?>" maxlength="120" placeholder="Population du secteur nord"></div>
                <div><label for="g-zone">Zone (marqueur ou secteur)</label><input id="g-zone" name="zone" value="<?= $h($p['zone']) ?>" maxlength="64"></div>
                <div><label for="g-lang">Langue (code)</label><input id="g-lang" name="language" value="<?= $h($p['language']) ?>" maxlength="8" placeholder="Langue du pays"></div>
                <div><label for="g-amin">Âge minimum</label><input id="g-amin" type="number" min="16" max="90" name="age_min" value="<?= (int) $p['age_min'] ?>"></div>
                <div><label for="g-amax">Âge maximum</label><input id="g-amax" type="number" min="16" max="95" name="age_max" value="<?= (int) $p['age_max'] ?>"></div>
                <div><label for="g-fem">Part de femmes (%)</label><input id="g-fem" type="number" min="0" max="100" name="female_pct" value="<?= (int) $p['female_pct'] ?>"></div>
                <div><label for="g-seed">Graine (vide = nouveau tirage)</label><input id="g-seed" name="seed" value="<?= $preview !== null ? (int) $seed : '' ?>" inputmode="numeric" maxlength="8"></div>
            </div>
            <div class="idp-statuses" style="margin-top:8px">
                <div>
                    <span class="idw-note">Pays d’origine</span>
                    <div class="idw-chips">
                        <?php foreach ($countries as $c): ?>
                            <label><input type="checkbox" name="countries[]" value="<?= $h($c['code']) ?>" <?= in_array($c['code'], $p['countries'], true) || ($p['countries'] === [] && $c === $countries[0]) ? 'checked' : '' ?>> <?= $h($c['name']) ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div>
                    <span class="idw-note">Papiers portés</span>
                    <div class="idw-chips">
                        <?php foreach (C::DOC_TYPES as $k => $label): ?>
                            <label><input type="checkbox" name="doc_types[]" value="<?= $h($k) ?>" <?= in_array($k, $p['doc_types'], true) ? 'checked' : '' ?>> <?= $h($label) ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div>
                    <span class="idw-note">Camps qui voient les recherchés et HVT du lot (aucun = tous)</span>
                    <div class="idw-chips">
                        <?php foreach (W::SIDES as $k => $label): ?>
                            <label><input type="checkbox" name="visible_sides[]" value="<?= $h($k) ?>" <?= in_array($k, $p['visible_sides'], true) ? 'checked' : '' ?>> <?= $h($label) ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="table-wrap" style="margin-top:10px">
                <table class="idw-pop">
                    <thead><tr><th>Catégorie</th><th>Nombre</th><th>Camp</th><th>Faction</th><th>Profession</th><?php if ($rights['truth']): ?><th>% faux</th><?php endif; ?><th>% expirés</th><th>% volés</th></tr></thead>
                    <tbody>
                    <?php foreach (W::POP_CATEGORIES as $cat => $label): $row = $p['categories'][$cat]; ?>
                        <tr>
                            <td><?= $h($label) ?></td>
                            <td><input type="number" min="0" max="100" name="cat[<?= $h($cat) ?>][count]" value="<?= (int) $row['count'] ?>" aria-label="Nombre"></td>
                            <td>
                                <select name="cat[<?= $h($cat) ?>][side]" aria-label="Camp">
                                    <?php foreach (W::SIDES as $k => $sl): ?><option value="<?= $h($k) ?>" <?= $row['side'] === $k ? 'selected' : '' ?>><?= $h($sl) ?></option><?php endforeach; ?>
                                </select>
                            </td>
                            <td><input name="cat[<?= $h($cat) ?>][faction]" value="<?= $h($row['faction']) ?>" maxlength="64" aria-label="Faction"></td>
                            <td><input name="cat[<?= $h($cat) ?>][profession]" value="<?= $h($row['profession']) ?>" maxlength="120" placeholder="Au hasard" aria-label="Profession"></td>
                            <?php if ($rights['truth']): ?><td><input type="number" min="0" max="100" name="cat[<?= $h($cat) ?>][forged]" value="<?= (int) $row['forged'] ?>" aria-label="Part de faux"></td><?php endif; ?>
                            <td><input type="number" min="0" max="100" name="cat[<?= $h($cat) ?>][expired]" value="<?= (int) $row['expired'] ?>" aria-label="Part d’expirés"></td>
                            <td><input type="number" min="0" max="100" name="cat[<?= $h($cat) ?>][stolen]" value="<?= (int) $row['stolen'] ?>" aria-label="Part de volés"></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="idw-note">100 personnes au plus par catégorie, 250 en tout. Le serveur de jeu charge 500 papiers au plus par carte.<?= $rights['truth'] ? '' : ' Les faux papiers demandent l’habilitation Confidentiel.' ?></p>
            <div class="idp-actions"><button class="btn" type="submit">Voir l’aperçu</button></div>
        </div>
    </form>

    <?php if ($preview !== null): $sum = $preview['summary']; ?>
        <section class="panel" style="margin-top:10px">
            <div class="panel-header"><div class="panel-title"><span class="panel-index">02</span> Aperçu de la population</div><div class="panel-meta">Graine <?= (int) $seed ?> : même graine, même population</div></div>
            <div class="panel-body">
                <div class="metrics-grid">
                    <div class="metric"><div class="metric-label">Personnes</div><div class="metric-value"><?= (int) $sum['people'] ?></div></div>
                    <div class="metric"><div class="metric-label">Papiers</div><div class="metric-value"><?= (int) $sum['documents'] ?></div></div>
                    <?php foreach ($sum['by_flag'] as $flag => $n): ?>
                        <?php if ($flag === 'forged' && !$rights['truth']) { continue; } ?>
                        <div class="metric"><div class="metric-label"><?= $h($flagLabels[$flag]) ?></div><div class="metric-value"><?= (int) $n ?></div></div>
                    <?php endforeach; ?>
                </div>
                <?php if ($preview['profiles'] === []): ?>
                    <p class="muted">Rien à générer : indiquez au moins une personne et un pays.</p>
                <?php else: ?>
                    <div class="table-wrap" style="max-height:420px;overflow:auto;margin-top:8px">
                        <table>
                            <thead><tr><th>Catégorie</th><th>Camp</th><th>Identité</th><th>Âge</th><th>Pays</th><th>Profession</th><th>Papiers</th></tr></thead>
                            <tbody>
                            <?php foreach ($preview['profiles'] as $pr): ?>
                                <tr>
                                    <td><?= $h(W::label(W::POP_CATEGORIES, $pr['category'])) ?><?= $pr['alias'] !== '' ? ' · « ' . $h($pr['alias']) . ' »' : '' ?></td>
                                    <td><?= $h(W::label(W::SIDES, $pr['side'])) ?><?= $pr['faction'] !== '' ? ' · ' . $h($pr['faction']) : '' ?></td>
                                    <td><?= $h(mb_strtoupper($pr['last_name']) . ' ' . $pr['first_name']) ?> (<?= $h($pr['sex']) ?>)</td>
                                    <td><?= (int) $pr['age'] ?></td>
                                    <td><?= $h($pr['country_name']) ?></td>
                                    <td><?= $h($pr['profession']) ?></td>
                                    <td>
                                        <?php foreach ($pr['documents'] as $d): ?>
                                            <span class="badge"><?= $h(C::label(C::DOC_TYPES, $d['doc_type'])) ?><?php if ($d['flag'] !== 'genuine' && ($d['flag'] !== 'forged' || $rights['truth'])): ?> · <?= $h($flagLabels[$d['flag']]) ?><?php endif; ?></span>
                                        <?php endforeach; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($rights['manage']): ?>
                        <form method="post" action="<?= $h(url('atak/sse/papiers/preparation')) ?>" class="idp-actions" style="margin-top:10px">
                            <?= \App\Core\Csrf::field() ?>
                            <input type="hidden" name="params_json" value="<?= $h(json_encode($params, JSON_UNESCAPED_UNICODE)) ?>">
                            <input type="hidden" name="seed" value="<?= (int) $seed ?>">
                            <input type="hidden" name="map" value="<?= (int) $scope['map'] ?>">
                            <input type="hidden" name="op" value="<?= $h($scope['op']) ?>">
                            <label class="idw-check"><input type="checkbox" name="confirm" value="1" required> J’ai vérifié l’aperçu</label>
                            <button class="btn" type="submit">Valider et enregistrer la population</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($board !== null): ?>
        <div class="idp-grid" style="margin-top:10px">
            <section class="panel">
                <div class="panel-header"><div class="panel-title"><span class="panel-index">03</span> Qui voit quoi</div><div class="panel-meta">Par camp</div></div>
                <div class="panel-body">
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Camp</th><th>Recherchés et HVT visibles</th><th>Population (IA)</th></tr></thead>
                            <tbody>
                            <?php foreach ($board['visibility'] as $side => $v): ?>
                                <tr>
                                    <td><?= $h($v['label']) ?></td>
                                    <td><?= (int) $v['wanted'] ?></td>
                                    <td><?php $n = 0; foreach ($board['population'] as $k => $cnt) { if (str_starts_with($k, $side . '|')) { $n += $cnt; } } ?><?= $n ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="idw-note">Le serveur de jeu garde pour lui la vérité (faux, couvertures). Les joueurs ne voient que les recherchés de leur camp et leur propre brief ; sur le site, les responsables RENS voient tout, la vérité restant réservée à l’habilitation Confidentiel.</p>
                </div>
            </section>
            <section class="panel">
                <div class="panel-header"><div class="panel-title"><span class="panel-index">04</span> Lots générés</div><div class="panel-meta"><?= count($board['batches']) ?> lot(s)</div></div>
                <div class="panel-body">
                    <?php if ($board['batches'] === []): ?>
                        <p class="muted">Aucun lot pour cette opération.</p>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table>
                                <tbody>
                                <?php foreach ($board['batches'] as $b): ?>
                                    <tr<?= $b['archived'] ? ' class="muted"' : '' ?>>
                                        <td><code translate="no"><?= $h($b['ref']) ?></code> <?= $h($b['label']) ?><?php if ($b['archived']): ?> <span class="badge">Retiré</span><?php endif; ?></td>
                                        <td><?= (int) $b['identities_count'] ?> pers. · <?= (int) $b['documents_count'] ?> papiers</td>
                                        <td>
                                            <?php if ($rights['manage'] && !$b['archived']): ?>
                                                <form method="post" action="<?= $h(url('atak/sse/papiers/preparation/lots/' . (int) $b['id'] . '/retirer')) ?>">
                                                    <?= \App\Core\Csrf::field() ?>
                                                    <button class="btn btn--ghost" type="submit">Retirer le lot</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    <p class="idw-note"><?= count($board['briefs']) ?> brief(s) de couverture pour cette opération. <a class="link" href="<?= $h(url('atak/sse/papiers/briefs')) ?>">Gérer les briefs</a></p>
                </div>
            </section>
        </div>

        <section class="panel" style="margin-top:10px">
            <div class="panel-header"><div class="panel-title"><span class="panel-index">05</span> Recherchés et HVT de l’opération</div><div class="panel-meta"><?= count($board['wanted']) ?> fiche(s)</div></div>
            <div class="panel-body">
                <?php if ($board['wanted'] === []): ?>
                    <p class="muted">Aucun recherché pour cette opération.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th></th><th>Identité</th><th>Alias</th><th>Niveau</th><th>Accès</th><th>Papiers connus</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($board['wanted'] as $w): ?>
                                <tr>
                                    <td><?php if ($w['photo_path'] !== null): ?><img class="idw-avatar" src="<?= $h(url($w['photo_path'])) ?>" alt=""><?php endif; ?></td>
                                    <td><a class="link" href="<?= $h(url('atak/sse/papiers/identites/' . (int) $w['identity_id'])) ?>"><?= $h(mb_strtoupper($w['last_name']) . ' ' . $w['first_name']) ?></a></td>
                                    <td><?= $h($w['alias'] !== '' ? $w['alias'] : '—') ?></td>
                                    <td><span class="badge idp-verdict--<?= $h($w['level'] === 'hvt' ? 'hvt' : ($w['level'] === 'recherche' ? 'recherche' : 'signale')) ?>"><?= $h($w['level_label']) ?></span></td>
                                    <td><?= $h($w['sides'] === [] ? 'Tous les camps' : implode(', ', array_map(static fn (string $s): string => W::label(W::SIDES, $s), $w['sides']))) ?></td>
                                    <td><?php foreach ($w['documents'] as $d): ?><a class="link" href="<?= $h(url('atak/sse/papiers/atelier/' . $d)) ?>"><?= $h(C::documentRef($d)) ?></a> <?php endforeach; ?><?= $w['documents'] === [] ? '—' : '' ?></td>
                                    <td>
                                        <?php if ($rights['manage']): ?>
                                            <form method="post" action="<?= $h(url('atak/sse/papiers/preparation/recherches/' . (int) $w['id'] . '/retirer')) ?>">
                                                <?= \App\Core\Csrf::field() ?>
                                                <input type="hidden" name="map" value="<?= (int) $scope['map'] ?>">
                                                <input type="hidden" name="op" value="<?= $h($scope['op']) ?>">
                                                <button class="btn btn--ghost" type="submit">Retirer</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                <?php if ($rights['manage']): ?>
                    <form method="post" action="<?= $h(url('atak/sse/papiers/preparation/recherches')) ?>" class="idw-form" style="margin-top:10px">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="map" value="<?= (int) $scope['map'] ?>">
                        <input type="hidden" name="op" value="<?= $h($scope['op']) ?>">
                        <div class="idp-statuses">
                            <div><label for="w-ref">Identité (référence IDT)</label><input id="w-ref" name="identity_ref" placeholder="IDT-000012" required maxlength="20"></div>
                            <div>
                                <label for="w-level">Niveau</label>
                                <select id="w-level" name="level"><?php foreach (W::WANTED_LEVELS as $k => $label): ?><option value="<?= $h($k) ?>"><?= $h($label) ?></option><?php endforeach; ?></select>
                            </div>
                            <div><label for="w-alias">Alias</label><input id="w-alias" name="alias" maxlength="120"></div>
                            <div><label for="w-notes">Notes</label><input id="w-notes" name="notes" maxlength="1000"></div>
                            <div>
                                <span class="idw-note">Camps qui y ont accès (aucun = tous)</span>
                                <div class="idw-chips"><?php foreach (W::SIDES as $k => $label): ?><label><input type="checkbox" name="sides[]" value="<?= $h($k) ?>"> <?= $h($label) ?></label><?php endforeach; ?></div>
                            </div>
                        </div>
                        <div class="idp-actions" style="margin-top:8px"><button class="btn" type="submit">Ajouter aux recherchés</button></div>
                    </form>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
<?php else: ?>
    <section class="panel" style="margin-top:10px">
        <div class="empty-state"><div class="empty-state-inner"><strong>Choisissez une carte et une opération</strong><p>La population, les recherchés et les briefs sont rangés par opération : ils partent au serveur de jeu quand il charge cette carte et cette opération.</p></div></div>
    </section>
<?php endif; ?>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
