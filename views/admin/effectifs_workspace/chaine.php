<?php
declare(strict_types=1);

require base_path('views/admin/effectifs_workspace/partials/rh_ui_helpers.php');

$units = is_array($chainUnits ?? null) ? $chainUnits : [];
$members = is_array($chainMembers ?? null) ? $chainMembers : [];
$options = is_array($chainMemberOptions ?? null) ? $chainMemberOptions : [];
$canManage = !empty($canManageAssignments);
$missingChefs = (int) ($chainMissingCommanders ?? 0);
$missingSuperiors = (int) ($chainMissingSuperiors ?? 0);
$withoutUnit = (int) ($chainWithoutUnit ?? 0);
$csrf = htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8');

$typeLabel = static function (string $typeRaw): string {
    return match ($typeRaw) {
        'company', 'compagnie' => 'Compagnie',
        'platoon', 'peloton' => 'Peloton',
        'section' => 'Section',
        'squad', 'groupe' => 'Groupe',
        'hq', 'etat_major', 'état-major' => 'État-major',
        'team', 'equipe', 'équipe' => 'Équipe',
        'battalion', 'bataillon' => 'Bataillon',
        default => $typeRaw !== '' ? $typeRaw : '—',
    };
};
?>
<section class="eff-rh-hero">
    <p class="eff-page-kicker">Dossier RH</p>
    <h2 class="eff-page-title">Chaîne de commandement</h2>
    <p class="eff-page-lead">
        Désignez le chef de chaque unité. Un membre relève du chef de son unité.
        Un chef relève du chef de l’unité immédiatement au-dessus, telle qu’elle est rangée dans l’organigramme.
    </p>
    <div class="eff-rh-tiles" aria-label="Aperçu de la chaîne">
        <article class="eff-rh-tile">
            <span class="eff-rh-tile__kicker">Unités</span>
            <strong class="eff-rh-tile__value"><?= count($units) ?></strong>
            <span class="eff-rh-tile__label">unité<?= count($units) > 1 ? 's' : '' ?></span>
        </article>
        <article class="eff-rh-tile">
            <span class="eff-rh-tile__kicker">Chefs à désigner</span>
            <strong class="eff-rh-tile__value"><?= $missingChefs ?></strong>
            <span class="eff-rh-tile__label">unité<?= $missingChefs > 1 ? 's' : '' ?> sans chef</span>
        </article>
        <article class="eff-rh-tile">
            <span class="eff-rh-tile__kicker">Relève</span>
            <strong class="eff-rh-tile__value"><?= $missingSuperiors ?></strong>
            <span class="eff-rh-tile__label">membre<?= $missingSuperiors > 1 ? 's' : '' ?> sans chef lisible</span>
        </article>
    </div>
</section>

<?php if ($units === []): ?>
    <div class="eff-catalog">
        <div class="eff-catalog__empty">
            <strong>Aucune unité définie</strong>
            Créez d’abord les unités de rattachement, puis revenez ici pour désigner qui commande chacune.
            <?php if ($canManage): ?>
                <p style="margin-top:1rem">
                    <a class="eff-catalog__btn eff-catalog__btn--primary" href="<?= $h(url('back-office/organisation/structure')) ?>">Ouvrir la structure</a>
                </p>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <form method="post" action="<?= $h(effectifs_workspace_url('chaine')) ?>" class="eff-chain">
        <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">

        <section class="bo-eff-settings__block" aria-labelledby="eff-chain-setup">
            <h2 id="eff-chain-setup">Chefs d’unité</h2>
            <p>
                Pour chaque unité, choisissez la personne qui la commande. Laissez vide si le poste n’est pas encore pourvu.
                Pour rattacher une unité à une autre, ouvrez la structure.
            </p>
            <div class="eff-sheets" role="region" aria-label="Chefs d’unité" tabindex="0">
                <table class="eff-sheets__table" id="eff-chain-units">
                    <colgroup>
                        <col style="width:18rem">
                        <col style="width:10rem">
                        <col style="width:14rem">
                        <col style="width:16rem">
                        <col style="width:7rem">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Unité</th>
                            <th>Type</th>
                            <th>Rattachée à</th>
                            <th>Chef</th>
                            <th>Effectif</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($units as $u):
                        $uid = (int) ($u['id'] ?? 0);
                        $depth = max(0, (int) ($u['depth'] ?? 0));
                        $name = trim((string) ($u['name'] ?? ''));
                        $parentName = trim((string) ($u['parent_name'] ?? ''));
                        $commanderId = (int) ($u['commander_user_id'] ?? 0);
                        $memberIds = array_values(array_filter(array_map('intval', is_array($u['member_ids'] ?? null) ? $u['member_ids'] : [])));
                        $memberIdSet = array_fill_keys($memberIds, true);
                        $label = $typeLabel((string) ($u['type'] ?? ''));
                        ?>
                        <tr>
                            <td>
                                <span class="eff-chain__unit" style="--eff-chain-depth:<?= $depth ?>">
                                    <span class="eff-sheets__cell-text"><?= $h($name !== '' ? $name : '—') ?></span>
                                </span>
                            </td>
                            <td>
                                <?php if ($label !== '—'): ?>
                                    <span class="eff-sheets__badge eff-sheets__badge--muted"><?= $h($label) ?></span>
                                <?php else: ?>
                                    <span class="eff-sheets__path-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($parentName !== ''): ?>
                                    <span class="eff-sheets__cell-text"><?= $h($parentName) ?></span>
                                <?php else: ?>
                                    <span class="eff-sheets__path-muted">Sommet de l’organigramme</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($canManage): ?>
                                    <select class="eff-chain__select" id="eff-chain-chef-<?= $uid ?>" name="commanders[<?= $uid ?>]" aria-label="Chef de <?= $h($name !== '' ? $name : 'l’unité') ?>">
                                        <option value="0">Aucun chef pour le moment</option>
                                        <?php
                                        $inUnit = [];
                                        $others = [];
                                        foreach ($options as $opt) {
                                            $oid = (int) ($opt['id'] ?? 0);
                                            if ($oid < 1) {
                                                continue;
                                            }
                                            if (isset($memberIdSet[$oid])) {
                                                $inUnit[] = $opt;
                                            } else {
                                                $others[] = $opt;
                                            }
                                        }
                                        ?>
                                        <?php if ($inUnit !== []): ?>
                                            <optgroup label="Membres de l’unité">
                                                <?php foreach ($inUnit as $opt): ?>
                                                    <option value="<?= (int) $opt['id'] ?>" <?= $commanderId === (int) $opt['id'] ? 'selected' : '' ?>>
                                                        <?= $h((string) $opt['label']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endif; ?>
                                        <?php if ($others !== []): ?>
                                            <optgroup label="Autres membres">
                                                <?php foreach ($others as $opt): ?>
                                                    <option value="<?= (int) $opt['id'] ?>" <?= $commanderId === (int) $opt['id'] ? 'selected' : '' ?>>
                                                        <?= $h((string) $opt['label']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endif; ?>
                                    </select>
                                <?php else: ?>
                                    <span class="eff-sheets__cell-text"><?= $h(trim((string) ($u['commander_label'] ?? '')) !== '' ? (string) $u['commander_label'] : 'Aucun chef') ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="eff-sheets__meta"><?= (int) ($u['member_count'] ?? 0) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($canManage): ?>
                <div class="eff-rh-form__actions" style="display:flex;gap:0.6rem;flex-wrap:wrap;justify-content:flex-start;margin-top:1rem">
                    <button type="submit" class="eff-rh-btn eff-rh-btn--primary">Enregistrer les chefs</button>
                    <a class="eff-catalog__btn" href="<?= $h(url('back-office/organisation/structure')) ?>">Ouvrir la structure</a>
                </div>
            <?php endif; ?>
        </section>
    </form>

    <section class="bo-eff-settings__block" aria-labelledby="eff-chain-people" style="margin-top:1.15rem">
        <h2 id="eff-chain-people">Qui relève de qui</h2>
        <p>
            Lecture de la chaîne à partir des chefs désignés ci-dessus et de l’organigramme.
            Un membre sans unité n’a pas de chef tant qu’il n’est pas rattaché.
        </p>
        <?php if ($members === []): ?>
            <p class="eff-catalog__empty" style="margin:0">Aucun membre actif à afficher.</p>
        <?php else: ?>
            <div class="eff-sheets" role="region" aria-label="Qui relève de qui" tabindex="0">
                <table class="eff-sheets__table" id="eff-chain-people">
                    <thead>
                        <tr>
                            <th>Membre</th>
                            <th>Unité</th>
                            <th>Relève de</th>
                            <th>Chaîne</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($members as $m):
                        $mid = (int) ($m['user_id'] ?? 0);
                        $unitName = trim((string) ($m['unit_name'] ?? ''));
                        $reports = trim((string) ($m['reports_to_label'] ?? ''));
                        $commands = is_array($m['commands'] ?? null) ? $m['commands'] : [];
                        $chain = is_array($m['chain_labels'] ?? null) ? $m['chain_labels'] : [];
                        $chainText = $chain !== [] ? implode(' → ', $chain) : '';
                        ?>
                        <tr>
                            <td>
                                <a class="eff-sheets__cell-text" href="<?= $h(effectifs_workspace_url('membres/' . $mid)) ?>"><?= $h((string) ($m['label'] ?? 'Membre')) ?></a>
                                <?php if ($commands !== []): ?>
                                    <span class="eff-sheets__meta">Chef de <?= $h(implode(', ', $commands)) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($unitName !== ''): ?>
                                    <span class="eff-sheets__cell-text"><?= $h($unitName) ?></span>
                                <?php else: ?>
                                    <span class="eff-sheets__path-muted">Sans unité</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($reports !== ''): ?>
                                    <span class="eff-sheets__cell-text"><?= $h($reports) ?></span>
                                <?php elseif ($unitName === '' && $commands === []): ?>
                                    <span class="eff-sheets__path-muted">À rattacher</span>
                                <?php else: ?>
                                    <span class="eff-sheets__path-muted">Chef non désigné</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($chainText !== ''): ?>
                                    <span class="eff-chain__path"><?= $h($chainText) ?></span>
                                <?php else: ?>
                                    <span class="eff-sheets__path-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($withoutUnit > 0): ?>
                <p class="eff-catalog-foot" style="margin-top:0.85rem">
                    <a class="eff-catalog__btn" href="<?= $h(effectifs_workspace_url() . '?sans_affectation=1') ?>">Traiter les membres sans unité (<?= $withoutUnit ?>)</a>
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </section>
<?php endif; ?>
