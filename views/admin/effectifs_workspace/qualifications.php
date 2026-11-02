<?php
declare(strict_types=1);

/**
 * Effectifs > Qualifications : matrice membres × qualifications (toutes natures), délivrance et retrait sur place.
 *
 * @var array<string, mixed> $qualificationMatrix
 */
$mx = is_array($qualificationMatrix ?? null) ? $qualificationMatrix : [];
$defs = is_array($mx['definitions'] ?? null) ? $mx['definitions'] : [];
$members = is_array($mx['members'] ?? null) ? $mx['members'] : [];
$cells = is_array($mx['cells'] ?? null) ? $mx['cells'] : [];
$counts = is_array($mx['counts'] ?? null) ? $mx['counts'] : ['ok' => 0, 'soon' => 0, 'exp' => 0, 'train' => 0];
$units = is_array($mx['units'] ?? null) ? $mx['units'] : [];
$coverage = is_array($mx['coverage'] ?? null) ? $mx['coverage'] : [];
$totals = is_array($mx['totals'] ?? null) ? $mx['totals'] : [];
$unlinked = (int) ($mx['unlinked_academy'] ?? 0);
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$fd = static function (string $iso): string {
    $ts = $iso !== '' ? strtotime(substr($iso, 0, 10)) : false;

    return $ts === false ? '' : (int) date('j', $ts) . ' ' . \App\Support\LocalizedCalendar::month((int) date('n', $ts), true) . ' ' . date('Y', $ts);
};
$palette = ['#059669', '#0284c7', '#7c3aed', '#d97706', '#db2777', '#0891b2', '#b45309', '#4f46e5'];
$unitColor = [];
foreach ($units as $i => $u) {
    $unitColor[(int) $u['id']] = (int) $u['id'] === 0 ? '#94a3b8' : $palette[$i % count($palette)];
}
$avColor = static fn (int $id): string => $palette[$id % count($palette)];
$ini = static function (array $m): string {
    $base = trim((string) ($m['callsign'] ?? '')) ?: trim((string) ($m['display_name'] ?? '')) ?: '?';

    return mb_strtoupper(mb_substr($base, 0, 2));
};
$unitShort = static function (array $u): string {
    $s = trim((string) ($u['short'] ?? ''));
    if ($s !== '') {
        return $s;
    }
    $acr = '';
    foreach (preg_split('/\s+/u', trim((string) ($u['name'] ?? ''))) ?: [] as $w) {
        if ($w !== '') {
            $acr .= mb_strtoupper(mb_substr($w, 0, 1));
        }
    }

    return mb_substr($acr !== '' ? $acr : (string) ($u['name'] ?? ''), 0, 4);
};
$byUnit = [];
foreach ($members as $m) {
    $byUnit[(int) $m['unit_id']][] = $m;
}
$categories = [];
foreach ($defs as $d) {
    $cn = trim((string) ($d['category_name'] ?? ''));
    if ($cn !== '') {
        $categories[$cn] = true;
    }
}
ksort($categories, SORT_NATURAL | SORT_FLAG_CASE);
$icon = [
    'ok' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12l5 5 9-10"/></svg>',
    'soon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
    'exp' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 2 20h20zM12 10v4M12 17v.5"/></svg>',
    'off' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 12h12"/></svg>',
];
?>
<div class="qmx" data-qmx>
    <div class="qmx-ph">
        <div>
            <span class="qmx-k">Carrière</span>
            <h2>Qualifications</h2>
            <p>Qui sait faire quoi dans l’unité. Repérez les manques avant l’opération, renouvelez ce qui expire et délivrez les brevets. Cliquez une case pour la modifier.</p>
        </div>
        <div class="qmx-acts">
            <a class="qmx-btn" href="<?= $h(url('academy')) ?>">Ouvrir l’Academy</a>
            <a class="qmx-btn" href="<?= $h(url('back-office/referentiels/qualifications')) ?>">Référentiel</a>
            <a class="qmx-btn qmx-btn--dark" href="<?= $h(url('back-office/referentiels/qualifications/create')) ?>">Nouvelle qualification</a>
        </div>
    </div>

    <?php if ($unlinked > 0): ?>
    <form class="qmx-note qmx-note--info" method="post" action="<?= $h(url('back-office/referentiels/qualifications/academy/rattacher')) ?>">
        <?= \App\Core\Csrf::field() ?>
        <span><b><?= $unlinked ?></b> attestation(s) Academy ne sont rattachées à aucune qualification du référentiel.</span>
        <button type="submit" class="qmx-btn qmx-btn--sm">Créer les qualifications Academy</button>
    </form>
    <?php endif; ?>

    <div class="qmx-stats" role="group" aria-label="Mettre en évidence">
        <?php foreach ([
            ['valid', 'Qualifications valides', (int) $counts['ok'] + (int) $counts['soon'], 'tous membres confondus', 'var(--qx-ok)', ''],
            ['soon', 'À renouveler', (int) $counts['soon'], 'expirent bientôt ou en période de grâce', 'var(--qx-warn)', (int) $counts['soon'] > 0 ? 'warn' : ''],
            ['exp', 'Expirées', (int) $counts['exp'], 'à repasser', 'var(--qx-bad)', (int) $counts['exp'] > 0 ? 'bad' : ''],
            ['train', 'En cours', (int) $counts['train'], 'en formation, à valider ou en progression', 'var(--qx-info)', ''],
        ] as [$mode, $label, $value, $sub, $dot, $tone]): ?>
        <button type="button" class="qmx-stat" data-qmx-mode="<?= $h($mode) ?>" aria-pressed="false">
            <span class="qmx-k"><span class="qmx-dot" style="background:<?= $h($dot) ?>"></span><?= $h($label) ?></span>
            <b class="<?= $h($tone) ?>"><?= (int) $value ?></b>
            <small><?= $h($sub) ?></small>
        </button>
        <?php endforeach; ?>
    </div>

    <?php if ($defs === []): ?>
        <div class="qmx-panel"><div class="qmx-empty"><b>Aucune qualification dans le référentiel</b>Créez une qualification : délivrée à la main, liée à une formation Academy ou automatique d’après les heures de jeu, de vol, les missions…<br><a class="qmx-btn qmx-btn--dark" href="<?= $h(url('back-office/referentiels/qualifications/create')) ?>">Créer une qualification</a></div></div>
    <?php elseif ($members === []): ?>
        <div class="qmx-panel"><div class="qmx-empty"><b>Aucun membre</b>Les membres de la communauté apparaîtront ici.</div></div>
    <?php else: ?>
    <div class="qmx-bar">
        <label class="qmx-search">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <span class="sr-only">Rechercher un membre</span>
            <input type="search" placeholder="Indicatif ou nom…" data-qmx-q>
        </label>
        <label><span class="sr-only">Unité</span>
            <select class="qmx-sel" data-qmx-unit>
                <option value="">Toutes les unités</option>
                <?php foreach ($units as $u): ?>
                    <option value="<?= (int) $u['id'] ?>"><?= $h((int) $u['id'] === 0 ? 'Sans unité' : $u['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php if ($categories !== []): ?>
        <label><span class="sr-only">Catégorie</span>
            <select class="qmx-sel" data-qmx-cat>
                <option value="">Toutes les catégories</option>
                <?php foreach (array_keys($categories) as $cn): ?>
                    <option value="<?= $h($cn) ?>"><?= $h($cn) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php endif; ?>
        <span class="qmx-sp"></span>
        <small class="qmx-hint" data-qmx-hint hidden>
            <span data-qmx-hint-for="valid">Mise en évidence : qualifications valides. Cliquez à nouveau pour annuler.</span>
            <span data-qmx-hint-for="soon">Mise en évidence : à renouveler. Cliquez à nouveau pour annuler.</span>
            <span data-qmx-hint-for="exp">Mise en évidence : expirées. Cliquez à nouveau pour annuler.</span>
            <span data-qmx-hint-for="train">Mise en évidence : en cours. Cliquez à nouveau pour annuler.</span>
        </small>
    </div>

    <div class="qmx-panel">
        <div class="qmx-tw">
            <table class="qmx-t" data-qmx-table>
                <thead>
                    <tr>
                        <th scope="col"><span class="qmx-k">Membre</span></th>
                        <?php foreach ($defs as $d):
                            $id = (int) $d['id'];
                            $code = trim((string) ($d['short_name'] ?? '')) ?: (string) ($d['code'] ?? '');
                            $target = (int) ($d['min_holders_per_unit'] ?? 0);
                            $months = (int) ($d['default_validity_months'] ?? 0);
                            ?>
                        <th scope="col" class="qmx-qh" data-col="<?= $id ?>" data-cat="<?= $h(trim((string) ($d['category_name'] ?? ''))) ?>" data-code="<?= $h($code) ?>" data-manual="<?= !empty($d['allow_manual']) ? '1' : '0' ?>">
                            <a href="<?= $h(url('back-office/referentiels/qualifications/' . $id)) ?>" title="<?= $h($d['name'] ?? '') ?>">
                                <b translate="no"><?= $h($code) ?></b>
                                <small data-qmx-name><?= $h($d['name'] ?? '') ?></small>
                            </a>
                            <?php if ($target > 0): ?>
                            <div class="qmx-cov">
                                <?php foreach ($units as $u): if ((int) $u['id'] === 0) { continue; }
                                    $n = (int) ($coverage[$id][(int) $u['id']] ?? 0); ?>
                                    <span class="<?= $n >= $target ? 'ok' : 'ko' ?>" title="<?= $h($u['name']) ?> : <?= $n ?> / <?= $target ?>"><?= $h($unitShort($u)) ?> <?= $n ?>/<?= $target ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            <div class="qmx-tot"><span><?= (int) ($totals[$id] ?? 0) ?> au total</span><?php if (!empty($d['is_permanent'])): ?> · <span data-qmx-valid>Permanente</span><?php elseif ($months > 0): ?> · <span data-qmx-valid><?= $months ?> mois</span><?php endif; ?></div>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($units as $u):
                    $uid = (int) $u['id'];
                    $list = $byUnit[$uid] ?? [];
                    if ($list === []) {
                        continue;
                    }
                    ?>
                    <tr class="qmx-gr" data-qmx-group="<?= $uid ?>">
                        <th colspan="<?= count($defs) + 1 ?>" scope="colgroup"><span class="qmx-gt"><i style="background:<?= $h($unitColor[$uid] ?? '#94a3b8') ?>"></i><b><?= $h($uid === 0 ? 'Sans unité' : $u['name']) ?></b><span><?= count($list) ?> membre<?= count($list) > 1 ? 's' : '' ?></span></span></th>
                    </tr>
                    <?php foreach ($list as $m):
                        $mid = (int) $m['user_id'];
                        $cs = trim((string) $m['callsign']) ?: trim((string) $m['display_name']) ?: ('#' . $mid);
                        ?>
                    <tr data-qmx-row data-unit="<?= $uid ?>" data-search="<?= $h(mb_strtolower($m['callsign'] . ' ' . $m['display_name'])) ?>">
                        <th scope="row">
                            <a class="qmx-mrow" href="<?= $h(url('back-office/ressources/effectifs/membres/' . $mid)) ?>#qualifications">
                                <span class="qmx-av" style="background:<?= $h($avColor($mid)) ?>"><?= $h($ini($m)) ?></span>
                                <span><b translate="no"><span data-qmx-cs><?= $h($cs) ?></span><?php if ($m['grade_label'] !== ''): ?><span class="qmx-gd"><?= $h($m['grade_label']) ?></span><?php endif; ?></b><small translate="no"><?= $h($m['display_name'] !== $cs ? $m['display_name'] : '') ?></small></span>
                            </a>
                        </th>
                        <?php foreach ($defs as $d):
                            $id = (int) $d['id'];
                            $c = $cells[$mid][$id] ?? ['state' => 'none', 'expires_at' => '', 'obtained_at' => '', 'pct' => 0];
                            $state = (string) $c['state'];
                            $pct = (int) $c['pct'];
                            $exp = $fd((string) $c['expires_at']);
                            $who = $cs . ' · ' . (trim((string) ($d['short_name'] ?? '')) ?: (string) $d['code']);
                            // Libellé « Viper · JTAC : qualifié jusqu’au 3 mars 2027 » : l’état est un modèle %1 traduit.
                            $label = $who . ' : ' . strtr(match ($state) {
                                'ok' => $exp !== '' ? 'qualifié jusqu’au %1' : 'qualifié',
                                'soon' => 'expire le %1',
                                'exp' => 'expirée depuis le %1',
                                'train' => $pct > 0 && $pct < 100 ? 'en cours (%1 %)' : 'en cours',
                                'off' => 'retirée',
                                default => 'non qualifié',
                            }, ['%1' => $state === 'train' ? (string) $pct : $exp]);
                            $inner = $state === 'train'
                                ? '<span class="qmx-pct">' . ($pct > 0 && $pct < 100 ? $pct . '%' : 'EN') . '</span>'
                                : ($icon[$state] ?? '');
                            ?>
                        <td data-cat="<?= $h(trim((string) ($d['category_name'] ?? ''))) ?>"><button type="button" class="qmx-cell <?= $h($state) ?>" data-qmx-cell data-user="<?= $mid ?>" data-def="<?= $id ?>" data-state="<?= $h($state) ?>" data-obtained="<?= $h(substr((string) $c['obtained_at'], 0, 10)) ?>" title="<?= $h($label) ?>" aria-label="<?= $h($label) ?>"><?= $inner ?></button></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="qmx-empty" data-qmx-none hidden><b>Aucun membre</b>Aucun membre ne correspond à ces filtres.</p>
        </div>
        <div class="qmx-legend">
            <span><i class="qmx-cell ok"><?= $icon['ok'] ?></i>Qualifié</span>
            <span><i class="qmx-cell soon"><?= $icon['soon'] ?></i>Expire bientôt</span>
            <span><i class="qmx-cell exp"><?= $icon['exp'] ?></i>Expirée</span>
            <span><i class="qmx-cell train"></i>En cours (formation, à valider, progression)</span>
            <span><i class="qmx-cell off"><?= $icon['off'] ?></i>Retirée</span>
            <span><i class="qmx-cell none"></i>Non qualifié</span>
            <span class="qmx-sp"></span>
            <span>Pastilles d’en-tête : titulaires par unité / besoin minimum</span>
        </div>
    </div>

    <template data-qmx-pop>
        <form class="qmx-qp" method="post" action="<?= $h(url('back-office/referentiels/qualifications/matrice/case')) ?>">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="user_id" data-qp-user>
            <input type="hidden" name="definition_id" data-qp-def>
            <input type="hidden" name="return_to" value="matrix">
            <div class="qmx-qp__hd"><span class="qmx-av" data-qp-av></span><div><b data-qp-title translate="no"></b><small data-qp-sub></small></div></div>
            <p class="qmx-qp__status" data-qp-status></p>
            <div class="qmx-seg3" role="radiogroup" aria-label="État">
                <label><input type="radio" name="target" value="none"><span>Aucune</span></label>
                <label data-qp-manual><input type="radio" name="target" value="train"><span>En formation</span></label>
                <label data-qp-manual><input type="radio" name="target" value="ok"><span>Qualifié</span></label>
            </div>
            <p class="qmx-qp__exp qmx-qp__exp--warn" data-qp-manual-off hidden>Cette qualification s’obtient automatiquement : à la main, on peut seulement la retirer.</p>
            <div class="qmx-qp__part" data-qp-when="ok">
                <div class="qmx-fl"><label for="qmx-qp-date">Date d’obtention</label><input type="date" class="qmx-fin" id="qmx-qp-date" name="obtained_at" max="<?= $h(date('Y-m-d')) ?>" data-qp-date></div>
                <p class="qmx-qp__exp" data-qp-valid hidden><span>Validité :</span> <b data-qp-valid-v></b></p>
                <div class="qmx-fl"><label for="qmx-qp-notes">Commentaire ou preuve</label><textarea class="qmx-fin" id="qmx-qp-notes" name="notes" rows="2" placeholder="Stage du 12, évalué par l’instructeur…"></textarea></div>
                <label class="qmx-chk"><input type="checkbox" name="certificate" value="1"><span><b>Générer le brevet</b><small>PDF numéroté, à télécharger depuis la fiche du membre.</small></span></label>
                <label class="qmx-chk"><input type="checkbox" name="force" value="1"><span><b>Délivrer quand même</b><small>Si un prérequis (grade, fonction, autre qualification) manque.</small></span></label>
            </div>
            <div class="qmx-qp__part" data-qp-when="train">
                <div class="qmx-fl"><label for="qmx-qp-tnotes">Commentaire</label><textarea class="qmx-fin" id="qmx-qp-tnotes" name="notes" rows="2" placeholder="Inscrit au stage de novembre"></textarea></div>
            </div>
            <div class="qmx-qp__part" data-qp-when="none">
                <div class="qmx-fl"><label for="qmx-qp-reason">Motif du retrait</label><input class="qmx-fin" id="qmx-qp-reason" name="reason" maxlength="500" placeholder="Obligatoire si le membre la détient"></div>
            </div>
            <div class="qmx-qp__ft">
                <a class="qmx-btn qmx-btn--sm" data-qp-fiche href="#">Fiche de la qualification</a>
                <button type="submit" class="qmx-btn qmx-btn--sm qmx-btn--dark">Enregistrer</button>
            </div>
        </form>
    </template>
    <?php endif; ?>
</div>
<script src="<?= $h(asset_url('assets/js/qualifications-matrix.js')) ?>" defer></script>
