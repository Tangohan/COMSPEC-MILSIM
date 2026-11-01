<?php
declare(strict_types=1);

require_once __DIR__ . '/partials/unit_code.php';

$matrix = is_array($affMatrix ?? null) ? $affMatrix : [];
$members = is_array($matrix['members'] ?? null) ? $matrix['members'] : [];
$unitsList = is_array($matrix['units'] ?? null) ? $matrix['units'] : [];
$filtersData = is_array($matrix['filters'] ?? null) ? $matrix['filters'] : ['jobs' => [], 'roles' => [], 'access' => []];
$stats = is_array($matrix['stats'] ?? null) ? $matrix['stats'] : [];
$community = is_array($matrix['community'] ?? null) ? $matrix['community'] : null;
$canManageAssignments = (bool) ($canManageAssignments ?? false);
$canEditCommunityCode = (bool) ($canEditCommunityCode ?? false);
$csrf = (string) ($csrfToken ?? '');
$view = (string) ($affView ?? 'members');
$initialQuery = (string) ($affInitialQuery ?? '');
$initialUnit = (int) ($affInitialUnit ?? 0);
if ($initialUnit > 0) {
    $view = 'units';
}
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$norm = static fn (string $v): string => mb_strtolower($v, 'UTF-8');
$initialUnitCode = '';
foreach ($unitsList as $u) {
    if ((int) $u['id'] === $initialUnit) {
        $initialUnitCode = (string) $u['code'];
    }
}
$typeLabel = static function (string $typeRaw): string {
    return match (mb_strtolower($typeRaw, 'UTF-8')) {
        'company', 'compagnie' => 'Compagnie',
        'platoon', 'peloton' => 'Peloton',
        'section' => 'Section',
        'squad', 'groupe', 'group' => 'Groupe',
        'hq', 'etat_major', 'état-major' => 'État-major',
        'team', 'equipe', 'équipe' => 'Équipe',
        'battalion', 'bataillon' => 'Bataillon',
        'squadron', 'escadron' => 'Escadron',
        'troop' => 'Troop',
        '' => '',
        default => $typeRaw,
    };
};
?>
<div class="eff-aff" data-eff-aff>
    <section class="eff-aff-codes" id="eff-aff-codes" aria-labelledby="eff-aff-codes-title">
        <div class="eff-aff-codes__main">
            <h2 class="eff-aff-codes__title" id="eff-aff-codes-title">Codes des unités</h2>
            <p class="eff-aff-codes__lead">
                Chaque unité porte un code construit depuis l’organigramme : code de la communauté, puis un repère par échelon
                (par exemple <?= eff_unit_code_html(($community['code'] ?? 'SOAR') . '-B-A-1', '', 'sm') ?>).
                Le numéro à côté est l’identifiant de l’unité : il ne change jamais, même si l’unité est renommée ou déplacée.
            </p>
            <?php if ($community !== null): ?>
            <p class="eff-aff-codes__lead">Votre communauté : <?= eff_unit_code_html((string) $community['code'], 'n° ' . (string) $community['admin_id']) ?></p>
            <?php else: ?>
            <p class="eff-aff-codes__lead eff-aff-codes__warn" role="status">Les codes n’ont pas pu être calculés pour le moment. Rechargez la page ; si ce message reste affiché, signalez-le au support.</p>
            <?php endif; ?>
        </div>
        <?php if ($community !== null && $canEditCommunityCode): ?>
        <form method="post" action="<?= $h(effectifs_workspace_url('codes/communaute')) ?>" class="eff-aff-codes__form">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
            <label for="eff-aff-community-code">Code de la communauté
                <input id="eff-aff-community-code" name="community_code" value="<?= $h((string) $community['code']) ?>" required minlength="2" maxlength="10" pattern="[A-Za-z0-9]{2,10}" title="De 2 à 10 lettres ou chiffres" autocomplete="off" spellcheck="false">
            </label>
            <button type="submit" class="eff-catalog__btn eff-catalog__btn--primary">Enregistrer</button>
        </form>
        <?php endif; ?>
    </section>

    <div class="eff-aff-stats" aria-label="Aperçu des affectations">
        <div class="eff-aff-stat"><span class="eff-aff-stat__v"><?= (int) ($stats['members'] ?? 0) ?></span><span class="eff-aff-stat__k">membres actifs</span></div>
        <a class="eff-aff-stat<?= (int) ($stats['without_unit'] ?? 0) > 0 ? ' is-warn' : '' ?>" href="<?= $h(effectifs_workspace_url() . '?sans_affectation=1') ?>"><span class="eff-aff-stat__v"><?= (int) ($stats['without_unit'] ?? 0) ?></span><span class="eff-aff-stat__k">sans unité, à rattacher</span></a>
        <div class="eff-aff-stat<?= (int) ($stats['without_job'] ?? 0) > 0 ? ' is-warn' : '' ?>"><span class="eff-aff-stat__v"><?= (int) ($stats['without_job'] ?? 0) ?></span><span class="eff-aff-stat__k">sans emploi</span></div>
        <div class="eff-aff-stat"><span class="eff-aff-stat__v"><?= count($unitsList) ?></span><span class="eff-aff-stat__k">unités, dont <?= (int) ($stats['empty_units'] ?? 0) ?> sans titulaire</span></div>
    </div>

    <section class="eff-aff-panel" aria-label="Qui tient quoi">
        <form class="eff-aff-bar" role="search" data-eff-aff-filters>
            <label class="eff-mq-search">
                <svg viewBox="0 0 16 16" width="15" height="15" aria-hidden="true"><circle cx="7" cy="7" r="4.6" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="m10.4 10.4 3.6 3.6" stroke="currentColor" stroke-width="1.6"/></svg>
                <span class="sr-only">Rechercher</span>
                <input type="search" data-eff-aff-q value="<?= $h($initialQuery) ?>" placeholder="Membre, indicatif, unité, code, emploi, rôle…" data-eff-slash-focus>
            </label>
            <label class="sr-only" for="eff-aff-unit">Unité</label>
            <select id="eff-aff-unit" data-eff-aff-unit class="<?= $initialUnitCode !== '' ? 'is-set' : '' ?>">
                <option value="">Toutes les unités</option>
                <?php foreach ($unitsList as $u): ?>
                    <?php if ((string) $u['code'] === '') {
                        continue;
                    } ?>
                    <option value="<?= $h((string) $u['code']) ?>" <?= (int) $u['id'] === $initialUnit ? 'selected' : '' ?>><?= $h(str_repeat('  ', (int) $u['depth']) . $u['code'] . ' · ' . $u['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($filtersData['jobs'] !== []): ?>
            <label class="sr-only" for="eff-aff-job">Emploi</label>
            <select id="eff-aff-job" data-eff-aff-job>
                <option value="">Tous les emplois</option>
                <?php foreach ($filtersData['jobs'] as $job): ?><option value="<?= $h($norm($job)) ?>"><?= $h($job) ?></option><?php endforeach; ?>
            </select>
            <?php endif; ?>
            <?php if ($filtersData['roles'] !== []): ?>
            <label class="sr-only" for="eff-aff-role">Rôle dans l’unité</label>
            <select id="eff-aff-role" data-eff-aff-role>
                <option value="">Tous les rôles</option>
                <?php foreach ($filtersData['roles'] as $role): ?><option value="<?= $h($norm($role)) ?>"><?= $h($role) ?></option><?php endforeach; ?>
            </select>
            <?php endif; ?>
            <?php if ($filtersData['access'] !== []): ?>
            <label class="sr-only" for="eff-aff-access">Niveau d’accès</label>
            <select id="eff-aff-access" data-eff-aff-access>
                <option value="">Tous les accès</option>
                <?php foreach ($filtersData['access'] as $acc): ?><option value="<?= $h($norm($acc)) ?>"><?= $h($acc) ?></option><?php endforeach; ?>
            </select>
            <?php endif; ?>
            <label class="eff-aff-check"><input type="checkbox" data-eff-aff-gap value="unit"> Sans unité</label>
            <label class="eff-aff-check"><input type="checkbox" data-eff-aff-gap value="job"> Sans emploi</label>
            <span class="eff-mq-sp" aria-hidden="true"></span>
            <div class="eff-mq-seg" role="group" aria-label="Présentation">
                <button type="button" data-eff-aff-view="members" class="<?= $view === 'members' ? 'is-on' : '' ?>" aria-pressed="<?= $view === 'members' ? 'true' : 'false' ?>">Par membre</button>
                <button type="button" data-eff-aff-view="units" class="<?= $view === 'units' ? 'is-on' : '' ?>" aria-pressed="<?= $view === 'units' ? 'true' : 'false' ?>">Par unité</button>
            </div>
        </form>
        <p class="eff-aff-count" role="status" aria-live="polite" data-eff-aff-count>
            <b><?= count($members) ?></b> membre<?= count($members) > 1 ? 's' : '' ?><?= !empty($stats['truncated']) ? ' (les ' . \App\Services\Effectifs\AffectationsMatrixService::MAX_MEMBERS . ' premiers)' : '' ?>
        </p>

        <div data-eff-aff-pane="members"<?= $view !== 'members' ? ' hidden' : '' ?>>
            <?php if ($members === []): ?>
                <div class="eff-aff-empty"><b>Aucun membre actif</b>Ajoutez un membre ou réactivez un compte depuis le tableur.</div>
            <?php else: ?>
            <div class="eff-aff-scroll">
            <table class="eff-aff-table">
                <thead>
                    <tr>
                        <th scope="col">Membre</th>
                        <th scope="col">Unité</th>
                        <th scope="col">Rôle dans l’unité</th>
                        <th scope="col">Emplois</th>
                        <th scope="col">Accès</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($members as $m): ?>
                    <?php
                    $jobNames = array_map(static fn (array $j): string => (string) $j['name'], $m['jobs']);
                    $haystack = $norm(implode(' ', array_merge(
                        [$m['name'], $m['callsign'], $m['character_name'], $m['grade'], $m['unit_name'], $m['unit_path'], $m['unit_code'], $m['unit_admin_id'], $m['role']],
                        $jobNames,
                        $m['access']
                    )));
                    ?>
                    <tr data-eff-aff-row
                        data-search="<?= $h($haystack) ?>"
                        data-code="<?= $h((string) $m['unit_code']) ?>"
                        data-jobs="<?= $h('|' . implode('|', array_map($norm, $jobNames)) . '|') ?>"
                        data-role="<?= $h($norm((string) $m['role'])) ?>"
                        data-access="<?= $h('|' . implode('|', array_map($norm, $m['access'])) . '|') ?>">
                        <td>
                            <span class="eff-aff-who">
                                <a href="<?= $h(effectifs_workspace_url('membres/' . (int) $m['id'])) ?>"><?= $h((string) $m['name']) ?></a>
                                <small><?= $h(implode(' · ', array_filter([(string) $m['grade'], $m['callsign'] !== '' ? '« ' . $m['callsign'] . ' »' : '']))) ?></small>
                            </span>
                        </td>
                        <td>
                            <?php if ($m['unit_id'] !== null): ?>
                            <span class="eff-aff-where">
                                <?= eff_unit_code_html((string) $m['unit_code'], (string) $m['unit_admin_id'], 'sm') ?>
                                <small title="<?= $h((string) $m['unit_path']) ?>"><?= $h((string) ($m['unit_name'] !== '' ? $m['unit_name'] : $m['unit_path'])) ?></small>
                            </span>
                            <?php else: ?>
                                <span class="eff-aff-none is-warn">Sans unité</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($m['role'] !== ''): ?><span class="eff-aff-chip is-role"><?= $h((string) $m['role']) ?></span><?php else: ?><span class="eff-aff-none">—</span><?php endif; ?>
                        </td>
                        <td>
                            <?php if ($m['jobs'] === []): ?>
                                <span class="eff-aff-none is-warn">Sans emploi</span>
                            <?php else: ?>
                                <span class="eff-aff-chips">
                                    <?php foreach ($m['jobs'] as $j): ?>
                                        <span class="eff-aff-chip<?= $j['primary'] ? ' is-main' : '' ?>"<?= $j['primary'] ? ' title="Emploi principal"' : '' ?>><?= $h((string) $j['name'] . ($j['detail'] !== '' ? ' — ' . $j['detail'] : '')) ?></span>
                                    <?php endforeach; ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($m['access'] === []): ?><span class="eff-aff-none">—</span><?php else: ?><span class="eff-aff-chips"><?php foreach ($m['access'] as $acc): ?><span class="eff-aff-chip"><?= $h((string) $acc) ?></span><?php endforeach; ?></span><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <div class="eff-aff-empty" data-eff-aff-none hidden><b>Aucun membre ne correspond</b>Retirez un filtre ou cherchez autrement (indicatif, code unité, emploi).</div>
            <?php endif; ?>
        </div>

        <div data-eff-aff-pane="units" id="eff-aff-units"<?= $view !== 'units' ? ' hidden' : '' ?>>
            <?php if ($unitsList === []): ?>
                <div class="eff-aff-empty">
                    <b>Aucune unité dans l’organigramme</b>
                    Créez vos unités dans la structure pour leur attribuer un code.
                    <?php if ($canManageAssignments): ?><br><a href="<?= $h(url('back-office/organisation/structure')) ?>">Ouvrir la structure</a><?php endif; ?>
                </div>
            <?php else: ?>
            <div class="eff-ut" data-eff-ut>
                <?php
                $unitPalette = ['#0f172a', '#065f46', '#1d4ed8', '#b45309', '#6d28d9', '#0369a1', '#9f1239', '#3f6212'];
                $initialsOf = static function (string $name): string {
                    $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                    $first = mb_substr($parts[0] ?? '?', 0, 1, 'UTF-8');
                    $second = isset($parts[1]) ? mb_substr($parts[1], 0, 1, 'UTF-8') : mb_substr($parts[0] ?? '', 1, 1, 'UTF-8');

                    return mb_strtoupper($first . $second, 'UTF-8');
                };
                ?>
                <?php foreach ($unitsList as $u): ?>
                    <?php
                    $holders = $u['holders'];
                    $seats = is_array($u['seats'] ?? null) ? $u['seats'] : null;
                    $haystack = $norm(implode(' ', array_merge(
                        [$u['name'], $u['code'], $u['admin_id'], $u['path'], $u['commander']],
                        array_map(static fn (array $x): string => $x['name'] . ' ' . $x['callsign'] . ' ' . $x['grade'] . ' ' . $x['role'] . ' ' . implode(' ', array_map(static fn (array $j): string => (string) $j['name'], $x['jobs'])), $holders),
                        $seats['vacant'] ?? []
                    )));
                    $type = $typeLabel((string) $u['type']);
                    $color = $unitPalette[(int) $u['id'] % count($unitPalette)];
                    $depth = (int) $u['depth'];
                    $seatPct = $seats !== null && $seats['authorized'] > 0 ? (int) round($seats['filled'] * 100 / $seats['authorized']) : null;
                    ?>
                    <article class="eff-ut__unit<?= (int) $u['id'] === $initialUnit ? ' is-target' : '' ?><?= $depth === 0 ? ' is-root' : '' ?>" id="unite-<?= (int) $u['id'] ?>" style="--depth:<?= min($depth, 6) ?>;--unit:<?= $h($color) ?>" data-eff-aff-unit-row data-search="<?= $h($haystack) ?>" data-code="<?= $h((string) $u['code']) ?>" aria-labelledby="eff-ut-name-<?= (int) $u['id'] ?>">
                        <header class="eff-ut__head">
                            <div class="eff-ut__ident">
                                <?php if ((string) $u['code'] !== ''): ?>
                                    <?= eff_unit_code_html((string) $u['code'], '', 'lg') ?>
                                    <span class="eff-ut__admin" title="Identifiant administratif : ne change jamais"><span>ID</span><?= $h((string) $u['admin_id']) ?></span>
                                <?php else: ?>
                                    <span class="eff-ut__nocode">Code en cours d’attribution</span>
                                <?php endif; ?>
                            </div>
                            <div class="eff-ut__title">
                                <?php if ($u['children'] > 0): ?>
                                <button type="button" class="eff-ut__fold" aria-expanded="true" data-eff-ut-fold="<?= $h((string) $u['code']) ?>" title="Replier les sous-unités">
                                    <svg viewBox="0 0 12 12" width="12" height="12" aria-hidden="true"><path d="M2.5 4.5 6 8l3.5-3.5" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>
                                    <span class="sr-only">Replier les sous-unités de <?= $h((string) $u['name']) ?></span>
                                </button>
                                <?php endif; ?>
                                <h3 id="eff-ut-name-<?= (int) $u['id'] ?>"><?= $h((string) $u['name']) ?></h3>
                                <p><?= $h(implode(' · ', array_filter([
                                    $type,
                                    $u['commander'] !== '' ? 'Chef : ' . $u['commander'] : 'Sans chef désigné',
                                    $u['children'] > 0 ? $u['children'] . ' sous-unité' . ($u['children'] > 1 ? 's' : '') : '',
                                ]))) ?></p>
                            </div>
                            <div class="eff-ut__figures">
                                <span class="eff-ut__count"><b><?= count($holders) ?></b> titulaire<?= count($holders) > 1 ? 's' : '' ?></span>
                                <?php if ($seatPct !== null): ?>
                                <span class="eff-ut__seats" title="Postes de l’ORBAT : <?= (int) $seats['filled'] ?> pourvus sur <?= (int) $seats['authorized'] ?>">
                                    <span class="eff-ut__bar" aria-hidden="true"><i style="width:<?= $seatPct ?>%"></i></span>
                                    <?= (int) $seats['filled'] ?>/<?= (int) $seats['authorized'] ?> postes<?= $seats['authorized'] - $seats['filled'] > 0 ? ' · <b>' . ($seats['authorized'] - $seats['filled']) . ' vacant' . ($seats['authorized'] - $seats['filled'] > 1 ? 's' : '') . '</b>' : '' ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="eff-ut__acts">
                                <a href="<?= $h(effectifs_workspace_url() . '?unite=' . (int) $u['id']) ?>">Voir au tableur</a>
                                <?php if ($canManageAssignments): ?>
                                <details class="eff-aff-seg">
                                    <summary>Modifier le code</summary>
                                    <form method="post" action="<?= $h(effectifs_workspace_url('codes/unites/' . (int) $u['id'])) ?>" class="eff-aff-seg__panel">
                                        <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                                        <label for="eff-aff-seg-<?= (int) $u['id'] ?>">Repère de l’unité dans le code</label>
                                        <input id="eff-aff-seg-<?= (int) $u['id'] ?>" name="segment" value="<?= $h((string) $u['own_code']) ?>" maxlength="8" pattern="[A-Za-z0-9]{0,8}" placeholder="<?= $h((string) $u['segment']) ?>" autocomplete="off" spellcheck="false">
                                        <span>Lettres ou chiffres, 8 au plus. Laissez vide pour le calculer depuis le nom<?= (string) $u['segment'] !== '' ? ' (actuellement « ' . $h((string) $u['segment']) . ' »)' : '' ?>.</span>
                                        <button type="submit" class="eff-catalog__btn eff-catalog__btn--primary">Enregistrer</button>
                                    </form>
                                </details>
                                <?php endif; ?>
                            </div>
                        </header>
                        <?php if ($holders !== [] || ($seats['vacant'] ?? []) !== []): ?>
                        <ul class="eff-ut__people">
                            <?php foreach ($holders as $x): ?>
                                <?php
                                $jobLabel = $x['jobs'] !== [] ? (string) $x['jobs'][0]['name'] : '';
                                $sub = array_filter([(string) $x['role'], $jobLabel]);
                                ?>
                                <li>
                                    <a class="eff-ut__person" href="<?= $h(effectifs_workspace_url('membres/' . (int) $x['id'])) ?>">
                                        <span class="eff-ut__av" aria-hidden="true"><?= $h($initialsOf((string) $x['name'])) ?></span>
                                        <span class="eff-ut__who">
                                            <b><?php if ((string) $x['grade'] !== ''): ?><span class="eff-ut__grade"><?= $h((string) $x['grade']) ?></span><?php endif; ?><?= $h((string) $x['name']) ?></b>
                                            <small><?= $h($sub !== [] ? implode(' · ', $sub) : 'Sans rôle ni emploi') ?><?= (string) $x['callsign'] !== '' ? ' · « ' . $h((string) $x['callsign']) . ' »' : '' ?></small>
                                        </span>
                                        <?php if ((string) $x['role'] !== ''): ?><span class="eff-ut__role-dot" title="Rôle dans l’unité" aria-hidden="true"></span><?php endif; ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                            <?php foreach ($seats['vacant'] ?? [] as $vacant): ?>
                                <li class="eff-ut__vacant"><span class="eff-ut__av" aria-hidden="true">+</span><span class="eff-ut__who"><b><?= $h((string) $vacant) ?></b><small>Poste vacant</small></span></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php else: ?>
                        <p class="eff-ut__empty">Aucun titulaire dans cette unité.</p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="eff-aff-empty" data-eff-aff-none-units hidden><b>Aucune unité ne correspond</b>Retirez un filtre ou cherchez un autre code.</div>
            <?php endif; ?>
        </div>
    </section>
</div>
<script src="<?= $h(asset_url('assets/js/eff-affectations.js')) ?>" defer></script>
