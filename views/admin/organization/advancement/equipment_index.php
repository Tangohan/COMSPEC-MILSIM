<?php
declare(strict_types=1);

use App\Services\GameEquipment\RoleKitConformity;

/**
 * Référentiels › Dotation — alimentée par les remontées du mod (dernier équipement porté, objets vus en jeu)
 * et par les kits par rôle (dotation type). Le suivi nominatif (n° de série, statut, carnet) reste en bas.
 *
 * Données : EquipmentReferentielController::index → DotationOverviewService.
 * Styles : public/assets/css/back-office-dotation.css.
 */

$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
$int = static fn (int $n): string => number_format($n, 0, ',', ' ');
$definitions = is_array($definitions ?? null) ? $definitions : [];
$members = is_array($members ?? null) ? $members : [];
$overview = is_array($overview ?? null) ? $overview : [];
$catalogue = is_array($catalogue ?? null) ? $catalogue : [];
$catalogueOptions = is_array($catalogueOptions ?? null) ? $catalogueOptions : $catalogue;
$linkedByClass = is_array($linkedByClass ?? null) ? $linkedByClass : [];
$gameClassReady = !empty($gameClassReady);
$days = (int) ($days ?? 30);
$periods = is_array($periods ?? null) ? $periods : [7, 30, 90];
$kind = (string) ($kind ?? '');
$search = (string) ($search ?? '');
$kindLabels = is_array($kindLabels ?? null) ? $kindLabels : [];

$summary = is_array($overview['summary'] ?? null) ? $overview['summary'] : [];
$players = is_array($overview['players'] ?? null) ? $overview['players'] : [];
$kits = is_array($overview['kits'] ?? null) ? $overview['kits'] : [];
$articles = is_array($overview['articles'] ?? null) ? $overview['articles'] : [];
$levels = is_array($summary['levels'] ?? null) ? $summary['levels'] : ['conforme' => 0, 'partiel' => 0, 'ecart' => 0];
$last = is_array($summary['last'] ?? null) ? $summary['last'] : null;

$base = url('back-office/referentiels/dotation');
$periodUrl = static fn (int $d): string => $base . '?' . http_build_query(array_filter(
    ['jours' => $d, 'famille' => $kind, 'q' => $search],
    static fn ($v): bool => $v !== '' && $v !== null
));
$fmtDate = static function (string $raw, bool $time = false): string {
    $ts = $raw !== '' ? strtotime($raw) : false;

    return $ts ? date($time ? 'd/m/Y H:i' : 'd/m/Y', $ts) : '—';
};
$ago = static function (string $raw): string {
    $ts = $raw !== '' ? strtotime($raw) : false;
    if (!$ts) {
        return 'jamais';
    }
    $d = time() - $ts;
    if ($d < 3600) {
        return 'il y a ' . max(1, (int) floor($d / 60)) . ' min';
    }
    if ($d < 86400) {
        return 'il y a ' . (int) floor($d / 3600) . ' h';
    }

    return 'il y a ' . (int) floor($d / 86400) . ' j';
};
$levelLabel = [
    'conforme' => 'Conforme',
    'partiel' => 'Partiel',
    'ecart' => 'Écart important',
];
$personLabel = static function (array $u): string {
    $n = trim((string) ($u['display_name'] ?? ''));

    return $n !== '' ? $n : (string) ($u['username'] ?? ('#' . ($u['id'] ?? '')));
};
$modLabel = static fn (string $m): string => strcasecmp(trim($m), 'Arma 3') === 0 ? 'Arma 3 (base)' : trim($m);
$nameList = static function (array $names, int $max = 4) use ($h): string {
    if ($names === []) {
        return '';
    }
    $shown = array_slice($names, 0, $max);
    $more = count($names) - count($shown);

    return implode(', ', array_map($h, $shown)) . ($more > 0 ? ' <span class="bo-dot__more">+' . $more . '</span>' : '');
};
$activeDefs = array_values(array_filter($definitions, static fn (array $d): bool => empty($d['archived_at'])));
?>
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/back-office-dotation.css')) ?>">
<div class="bo-adv bo-dot">
    <?php include __DIR__ . '/_flash.php'; ?>

    <nav class="bo-dot__tabs" aria-label="Sections de la page">
        <a href="#synthese">Synthèse</a>
        <a href="#conformite">Conformité des joueurs</a>
        <a href="#kits">Dotation type par fonction</a>
        <a href="#catalogue">Objets vus en jeu</a>
        <a href="#articles">Articles et attribution</a>
        <span class="bo-dot__periods" role="group" aria-label="Période">
            <?php foreach ($periods as $p): ?>
                <a href="<?= $h($periodUrl((int) $p)) ?>" class="<?= (int) $p === $days ? 'is-active' : '' ?>"<?= (int) $p === $days ? ' aria-current="true"' : '' ?>><?= (int) $p ?> j</a>
            <?php endforeach; ?>
        </span>
    </nav>

    <section id="synthese" class="bo-dot__kpis" aria-label="Synthèse">
        <div class="bo-dot__kpi">
            <span class="bo-dot__kpi-label">Dernière remontée du jeu</span>
            <strong class="bo-dot__kpi-value"><?= $last !== null ? $h($ago($last['at'])) : 'Aucune' ?></strong>
            <span class="bo-dot__kpi-sub"><?= $last !== null ? $h($last['player'] . ($last['mission'] !== '' ? ' · ' . $last['mission'] : '')) : 'Aucun joueur n’a encore synchronisé son équipement sur la période.' ?></span>
        </div>
        <div class="bo-dot__kpi">
            <span class="bo-dot__kpi-label">Joueurs remontés (<?= $days ?> j)</span>
            <strong class="bo-dot__kpi-value"><?= $int((int) ($summary['players'] ?? 0)) ?></strong>
            <span class="bo-dot__kpi-sub"><?= $int((int) ($summary['linked'] ?? 0)) ?> rattaché(s) à un compte du site</span>
        </div>
        <div class="bo-dot__kpi">
            <span class="bo-dot__kpi-label">Conformité moyenne</span>
            <strong class="bo-dot__kpi-value"><?= ($summary['coverage'] ?? null) !== null ? (int) $summary['coverage'] . ' %' : '—' ?></strong>
            <span class="bo-dot__kpi-sub">
                <span class="bo-dot__pill bo-dot__pill--conforme" title="Conformes"><?= (int) ($levels['conforme'] ?? 0) ?></span>
                <span class="bo-dot__pill bo-dot__pill--partiel" title="Partiels"><?= (int) ($levels['partiel'] ?? 0) ?></span>
                <span class="bo-dot__pill bo-dot__pill--ecart" title="En écart"><?= (int) ($levels['ecart'] ?? 0) ?></span>
                conformes · partiels · en écart
            </span>
        </div>
        <div class="bo-dot__kpi">
            <span class="bo-dot__kpi-label">Objets connus</span>
            <strong class="bo-dot__kpi-value"><?= $int((int) ($summary['catalogue'] ?? 0)) ?></strong>
            <span class="bo-dot__kpi-sub"><?= $int((int) ($summary['kits'] ?? 0)) ?> kit(s) de dotation type · <?= $int(count($activeDefs)) ?> article(s) au référentiel</span>
        </div>
    </section>

    <section id="conformite" class="bo-adv__panel">
        <div class="bo-adv__panel-head">
            <h2>Conformité des joueurs</h2>
            <p>Dernier équipement porté en jeu par chaque joueur, comparé au kit de sa fonction (ou au kit « toutes fonctions » à défaut). On vérifie la présence des objets, pas les quantités.</p>
        </div>
        <?php if ($players === []): ?>
            <p class="bo-dot__empty">Aucun équipement remonté sur les <?= $days ?> derniers jours. Il apparaît dès qu’un joueur équipé du mod rejoint une partie connectée à la communauté.</p>
        <?php else: ?>
            <div class="bo-adv__table-wrap">
                <table class="bo-adv__table bo-dot__table">
                    <thead>
                        <tr>
                            <th>Joueur</th>
                            <th>Fonction</th>
                            <th>Kit de référence</th>
                            <th>Conformité</th>
                            <th>Manque</th>
                            <th>En plus</th>
                            <th>Vu en jeu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($players as $p):
                            $res = is_array($p['result'] ?? null) ? $p['result'] : null;
                            $functions = array_values(array_filter(array_map('strval', $p['functions'] ?? [])));
                            ?>
                            <tr>
                                <td>
                                    <strong><?= $h($p['player_name'] ?? '') ?></strong>
                                    <?php if ((int) ($p['user_id'] ?? 0) < 1): ?>
                                        <span class="bo-dot__tag">Compte non rattaché</span>
                                    <?php endif; ?>
                                    <?php if (($p['group_name'] ?? '') !== ''): ?>
                                        <span class="bo-dot__sub"><?= $h($p['group_name']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $functions !== [] ? $h(implode(', ', $functions)) : '<span class="bo-dot__muted">Aucune fonction</span>' ?>
                                    <?php if (($p['game_role'] ?? '') !== ''): ?>
                                        <span class="bo-dot__sub">En jeu : <?= $h($p['game_role']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (is_array($p['kit'] ?? null)): ?>
                                        <a href="<?= $h(url('back-office/kits-roles/' . (int) $p['kit']['id'])) ?>"><?= $h($p['kit']['name']) ?></a>
                                        <span class="bo-dot__sub"><?= $h($p['kit']['role_name'] ?? 'Toutes fonctions') ?></span>
                                    <?php else: ?>
                                        <span class="bo-dot__muted">Aucun kit pour cette fonction</span>
                                    <?php endif; ?>
                                </td>
                                <td class="bo-dot__nowrap">
                                    <?php if ($res !== null): ?>
                                        <span class="bo-dot__meter bo-dot__meter--<?= $h($res['level']) ?>" title="<?= (int) $res['matched'] ?> objet(s) sur <?= (int) $res['expected'] ?>">
                                            <span style="width: <?= max(0, min(100, (int) $res['coverage'])) ?>%"></span>
                                        </span>
                                        <span class="bo-dot__pct"><?= (int) $res['coverage'] ?> %</span>
                                        <span class="bo-dot__sub"><?= $h($levelLabel[$res['level']] ?? '') ?></span>
                                    <?php else: ?>
                                        <span class="bo-dot__muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="bo-dot__names"><?= $res !== null && ($p['missing_names'] ?? []) !== [] ? $nameList($p['missing_names']) : '<span class="bo-dot__muted">—</span>' ?></td>
                                <td class="bo-dot__names"><?= $res !== null && ($p['extra_names'] ?? []) !== [] ? $nameList($p['extra_names'], 3) : '<span class="bo-dot__muted">—</span>' ?></td>
                                <td>
                                    <span title="<?= $h($fmtDate((string) $p['last_seen_at'], true)) ?>"><?= $h($ago((string) $p['last_seen_at'])) ?></span>
                                    <?php if (($p['mission_name'] ?? '') !== ''): ?>
                                        <span class="bo-dot__sub"><?= $h($p['mission_name']) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="bo-dot__hint">Détail de chaque kit porté et enregistrement comme tenue d’arsenal : <a href="<?= $h(url('back-office/equipement-joueurs') . '?jours=' . $days . '#kits-portes') ?>">Équipement des joueurs</a>.</p>
        <?php endif; ?>
    </section>

    <section id="kits" class="bo-adv__panel">
        <div class="bo-adv__panel-head">
            <h2>Dotation type par fonction</h2>
            <p>Les kits par rôle servent de dotation de référence. Pour chacun : joueurs comparés sur la période, conformité moyenne et objets le plus souvent absents.</p>
        </div>
        <?php if ($kits === []): ?>
            <p class="bo-dot__empty">Aucun kit défini. <a href="<?= $h(url('back-office/kits-roles/nouveau')) ?>">Composer un premier kit</a> à partir des objets vus en jeu : il servira de dotation type et sera proposé dans l’arsenal en jeu.</p>
        <?php else: ?>
            <div class="bo-dot__kits">
                <?php foreach ($kits as $k): ?>
                    <article class="bo-dot__kit">
                        <header>
                            <a href="<?= $h(url('back-office/kits-roles/' . (int) $k['id'])) ?>"><strong><?= $h($k['name']) ?></strong></a>
                            <span class="bo-dot__sub"><?= $h($k['role_name'] ?? 'Toutes fonctions') ?> · <?= (int) $k['item_count'] ?> objet(s)</span>
                        </header>
                        <?php if ((int) $k['players'] > 0): ?>
                            <p class="bo-dot__kit-score">
                                <span class="bo-dot__meter bo-dot__meter--<?= $h(RoleKitConformity::level((int) $k['coverage'])) ?>"><span style="width: <?= max(0, min(100, (int) $k['coverage'])) ?>%"></span></span>
                                <span class="bo-dot__pct"><?= (int) $k['coverage'] ?> %</span>
                                <span class="bo-dot__sub"><?= (int) $k['players'] ?> joueur(s)</span>
                            </p>
                            <?php if ($k['top_missing'] !== []): ?>
                                <ul class="bo-dot__missing">
                                    <?php foreach ($k['top_missing'] as $m): ?>
                                        <li><?= $h($m['name']) ?> <span class="bo-dot__muted">manque à <?= (int) $m['players'] ?></span></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="bo-dot__ok">Rien ne manque aux joueurs comparés.</p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="bo-dot__muted">Aucun joueur comparé à ce kit sur la période.</p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section id="catalogue" class="bo-adv__panel">
        <div class="bo-adv__panel-head">
            <h2>Objets vus en jeu</h2>
            <p>Catalogue constitué automatiquement : équipement porté par les joueurs et tenues envoyées depuis l’arsenal en jeu, avec le nom affiché dans Arma.</p>
        </div>
        <form method="get" action="<?= $h($base) ?>#catalogue" class="bo-dot__filters">
            <input type="hidden" name="jours" value="<?= $days ?>">
            <label>
                <span>Famille</span>
                <select name="famille">
                    <option value="">Toutes</option>
                    <?php foreach ($kindLabels as $k => $label): ?>
                        <option value="<?= $h($k) ?>"<?= $k === $kind ? ' selected' : '' ?>><?= $h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="bo-dot__grow">
                <span>Rechercher</span>
                <input type="search" name="q" value="<?= $h($search) ?>" placeholder="Nom, mod…">
            </label>
            <button type="submit" class="ath-btn">Filtrer</button>
        </form>
        <?php if ($catalogue === []): ?>
            <p class="bo-dot__empty"><?= $kind !== '' || $search !== '' ? 'Aucun objet ne correspond à ce filtre.' : 'Aucun objet vu en jeu pour l’instant.' ?></p>
        <?php else: ?>
            <div class="bo-adv__table-wrap bo-dot__scroll">
                <table class="bo-adv__table bo-dot__table">
                    <thead>
                        <tr>
                            <th>Objet</th>
                            <th>Famille</th>
                            <th>Origine</th>
                            <th>Joueurs (<?= $days ?> j)</th>
                            <th>Dernière fois</th>
                            <th>Référentiel</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($catalogue as $c):
                            $def = $linkedByClass[strtolower((string) $c['class'])] ?? null;
                            ?>
                            <tr>
                                <td><strong><?= $h($c['name']) ?></strong><span class="bo-dot__class"><?= $h($c['class']) ?></span></td>
                                <td><?= $h($kindLabels[$c['kind']] ?? 'Objet') ?></td>
                                <td><?= $c['mod'] !== '' ? $h($modLabel($c['mod'])) : '<span class="bo-dot__muted">—</span>' ?></td>
                                <td><?= (int) $c['players'] > 0 ? $int((int) $c['players']) : '<span class="bo-dot__muted">0</span>' ?></td>
                                <td><?= $h($fmtDate((string) $c['last_seen'])) ?></td>
                                <td>
                                    <?php if ($def !== null): ?>
                                        <a href="<?= $h($base . '/' . (int) $def['id']) ?>"><?= $h($def['name']) ?></a>
                                    <?php elseif ($gameClassReady): ?>
                                        <form method="post" action="<?= $h($base) ?>" class="bo-dot__inline">
                                            <?= \App\Core\Csrf::field() ?>
                                            <input type="hidden" name="game_class_name" value="<?= $h($c['class']) ?>">
                                            <input type="hidden" name="name" value="<?= $h($c['name']) ?>">
                                            <input type="hidden" name="category" value="<?= $h($kindLabels[$c['kind']] ?? '') ?>">
                                            <button type="submit" class="bo-adv__linkbtn">Ajouter au référentiel</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="bo-dot__muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (count($catalogue) >= 300): ?>
                <p class="bo-dot__hint">Les 300 objets les plus portés sont affichés : affinez avec la famille ou la recherche.</p>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <div id="articles" class="bo-adv__grid2">
        <section class="bo-adv__panel">
            <div class="bo-adv__panel-head">
                <h2>Nouvel article</h2>
                <p>Matériel suivi nominativement (numéro de série, statut, carnet). Liez-le à un objet du jeu pour voir qui le porte réellement.</p>
            </div>
            <form method="post" action="<?= $h($base) ?>" class="bo-adv__stack">
                <?= \App\Core\Csrf::field() ?>
                <input type="text" name="code" required placeholder="Code" maxlength="40">
                <input type="text" name="name" required placeholder="Nom" maxlength="180">
                <input type="text" name="category" placeholder="Catégorie (radio, arme, outil…)">
                <?php if ($gameClassReady): ?>
                    <input type="text" name="game_class_name" list="bo-dot-objets" placeholder="Objet du jeu correspondant (facultatif)" maxlength="128">
                <?php endif; ?>
                <textarea name="description" rows="3" class="bo-adv__textarea" placeholder="Description"></textarea>
                <button class="ath-btn ath-btn--solid" type="submit">Créer</button>
            </form>
        </section>
        <section class="bo-adv__panel">
            <div class="bo-adv__panel-head">
                <h2>Attribuer nominativement</h2>
                <p>Numéro de série fictif, statut en dotation, historique conservé.</p>
            </div>
            <form method="post" action="<?= $h($base . '/attribuer') ?>" class="bo-adv__stack">
                <?= \App\Core\Csrf::field() ?>
                <select name="personnel_id" required>
                    <option value="">Personnel</option>
                    <?php foreach ($members as $u): ?>
                        <option value="<?= (int) ($u['id'] ?? 0) ?>"><?= $h($personLabel($u)) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="definition_id" required>
                    <option value="">Article</option>
                    <?php foreach ($activeDefs as $d): ?>
                        <option value="<?= (int) ($d['id'] ?? 0) ?>"><?= $h((string) ($d['name'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="serial_number" placeholder="N° de série">
                <input type="date" name="assigned_at" value="<?= $h(date('Y-m-d')) ?>">
                <textarea name="notes" rows="2" class="bo-adv__textarea" placeholder="Notes"></textarea>
                <button class="ath-btn ath-btn--solid" type="submit">Doter</button>
            </form>
        </section>
    </div>

    <?php if ($gameClassReady): ?>
        <datalist id="bo-dot-objets">
            <?php foreach ($catalogueOptions as $c): ?>
                <option value="<?= $h($c['class']) ?>"><?= $h($c['name']) ?></option>
            <?php endforeach; ?>
        </datalist>
    <?php endif; ?>

    <div class="bo-adv__table-wrap">
        <table class="bo-adv__table bo-dot__table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Article</th>
                    <th>Catégorie</th>
                    <th>En dotation</th>
                    <th>Objet en jeu</th>
                    <th>Porté en jeu (<?= $days ?> j)</th>
                    <th>Statut</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($definitions as $d):
                    $did = (int) ($d['id'] ?? 0);
                    $art = $articles[$did] ?? null;
                    $archived = !empty($d['archived_at']);
                    ?>
                    <tr class="<?= $archived ? 'is-archived' : '' ?>">
                        <td class="bo-adv__mono"><?= $h((string) ($d['code'] ?? '')) ?></td>
                        <td><strong><?= $h((string) ($d['name'] ?? '')) ?></strong></td>
                        <td><?= $h((string) ($d['category'] ?? '—')) ?></td>
                        <td><?= (int) ($d['issued_count'] ?? 0) ?></td>
                        <td>
                            <?php if ($art !== null): ?>
                                <?= $h($art['game_name']) ?><span class="bo-dot__class"><?= $h($art['class']) ?></span>
                            <?php endif; ?>
                            <?php if ($gameClassReady && !$archived): ?>
                                <details class="bo-dot__link">
                                    <summary><?= $art !== null ? 'Modifier' : 'Lier à un objet du jeu' ?></summary>
                                    <form method="post" action="<?= $h($base . '/' . $did . '/objet-jeu') ?>" class="bo-dot__inline">
                                        <?= \App\Core\Csrf::field() ?>
                                        <input type="text" name="game_class_name" list="bo-dot-objets" value="<?= $h($art['class'] ?? '') ?>" placeholder="Objet du jeu (vide pour délier)" maxlength="128">
                                        <button type="submit" class="bo-adv__linkbtn">Enregistrer</button>
                                    </form>
                                </details>
                            <?php elseif ($art === null): ?>
                                <span class="bo-dot__muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($art !== null): ?>
                                <strong><?= (int) $art['wearers'] ?></strong> joueur(s)
                                <?php if ((int) $art['without_issue'] > 0): ?>
                                    <span class="bo-dot__warn" title="<?= $h(implode(', ', $art['without_issue_names'])) ?>"><?= (int) $art['without_issue'] ?> sans dotation nominative</span>
                                <?php endif; ?>
                                <?php if ((int) $art['issued_not_worn'] > 0): ?>
                                    <span class="bo-dot__warn"><?= (int) $art['issued_not_worn'] ?> doté(s) mais non porté(s)</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="bo-dot__muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $archived ? 'Archivé' : 'Actif' ?></td>
                        <td class="bo-adv__actions">
                            <a href="<?= $h($base . '/' . $did) ?>">Carnet</a>
                            <?php if (!$archived): ?>
                                <form method="post" action="<?= $h($base . '/' . $did . '/archive') ?>">
                                    <?= \App\Core\Csrf::field() ?>
                                    <button type="submit" class="bo-adv__linkbtn">Archiver</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($definitions === []): ?>
                    <tr><td colspan="8" class="bo-adv__empty">Aucun article de dotation. Ajoutez-en un depuis la liste des objets vus en jeu ou avec le formulaire ci-dessus.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
