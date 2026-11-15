<?php

declare(strict_types=1);

use App\Services\Effectifs\Dossier\DossierCatalog as C;

/**
 * Suivi des dossiers : tous les membres actifs avec leur progression, leur prochaine étape et leurs retards.
 * Tableau sur grand écran, cartes sur mobile. Aucun surtitre. Relance groupée plafonnée, sans doublon.
 *
 * @var list<array<string, mixed>> $suiviRows
 * @var array<string, int> $suiviTotals
 * @var array<int, string> $suiviUnits
 * @var array<string, mixed> $suiviFilters
 * @var int $suiviCount
 * @var int $suiviPage
 * @var int $suiviPages
 * @var bool $canManageSuivi
 * @var string $csrfToken
 * @var int $bulkMax
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$en = function_exists('locale') && locale() === 'en';
$tr = static fn (string $fr, string $en_) => $en ? $en_ : $fr;
$rows = is_array($suiviRows ?? null) ? $suiviRows : [];
$f = is_array($suiviFilters ?? null) ? $suiviFilters : [];
$totals = is_array($suiviTotals ?? null) ? $suiviTotals : ['members' => 0, 'late' => 0, 'blocked' => 0, 'open' => 0, 'complete' => 0];
$canManage = (bool) ($canManageSuivi ?? false);
$today = date('Y-m-d');
$fd = static fn (?string $v): string => $v !== null && $v !== '' && strtotime($v) !== false ? date('d/m/Y', strtotime($v)) : '—';
$trackLabels = C::trackLabels();
$ownerFilters = ['moi' => 'Moi', C::OWNER_MEMBER => 'Le membre', C::OWNER_INSTRUCTOR => 'Un instructeur', C::OWNER_RH => 'Un responsable RH', C::OWNER_REFERENT => 'Le référent', C::OWNER_RECRUITMENT => 'Le recrutement'];
$ownerSelected = (string) ($f['owner'] ?? '');
if ($ownerSelected !== '' && ctype_digit($ownerSelected)) {
    $ownerSelected = 'moi';
}
$base = effectifs_workspace_url('suivi');
$query = static function (array $over = []) use ($f, $ownerSelected): string {
    $q = array_filter([
        'q' => $f['q'] ?? '', 'parcours' => $f['track'] ?? '', 'responsable' => $ownerSelected, 'etat' => $f['etat'] ?? '',
        'unite' => ($f['unit'] ?? 0) > 0 ? $f['unit'] : '', 'tri' => $f['sort'] ?? 'retard', 'ordre' => !empty($f['desc']) ? 'desc' : '',
    ], static fn ($v): bool => $v !== '' && $v !== null) + [];
    foreach ($over as $k => $v) {
        if ($v === '' || $v === null) {
            unset($q[$k]);
        } else {
            $q[$k] = $v;
        }
    }

    return $q === [] ? '' : '?' . http_build_query($q);
};
$sortUrl = static function (string $key) use ($base, $f, $query): string {
    $same = ($f['sort'] ?? '') === $key;

    return $base . $query(['tri' => $key, 'ordre' => $same && empty($f['desc']) ? 'desc' : '', 'page' => '']);
};
$sortMark = static fn (string $key): string => ($f['sort'] ?? '') === $key ? (!empty($f['desc']) ? ' ↓' : ' ↑') : '';
$daysLate = static fn (?string $due): int => $due === null || $due === '' ? 0 : max(0, (int) floor((strtotime($today) - strtotime($due)) / 86400));
$filtered = ($f['q'] ?? '') !== '' || ($f['track'] ?? '') !== '' || $ownerSelected !== '' || ($f['etat'] ?? '') !== '' || (int) ($f['unit'] ?? 0) > 0;
?>
<section class="eff-rh-hero eff-sv-hero">
    <div class="eff-pg__actions">
        <a href="<?= $h(effectifs_workspace_url()) ?>" class="eff-catalog__btn">Tableur des effectifs</a>
        <a href="<?= $h(effectifs_workspace_url('suivi/export') . $query()) ?>" class="eff-catalog__btn">Exporter en CSV</a>
    </div>
    <h2 class="eff-page-title">Étapes des dossiers</h2>
    <p class="eff-page-lead">
        L’avancement des étapes de chaque membre actif : intégration, qualifications, documents, carrière, fiabilisation.
        Repérez les retards, relancez ceux qui doivent agir, ouvrez le dossier pour modifier une étape.
    </p>
</section>

<div class="eff-sv eff-sv--team">
    <div class="eff-sv-sum eff-sv-sum--team" role="group" aria-label="Chiffres du suivi">
        <a class="eff-sv-tile eff-sv-tile--link" href="<?= $h($base) ?>">
            <h3 class="eff-sv-tile__title">Membres suivis</h3>
            <p class="eff-sv-tile__big"><?= (int) $totals['members'] ?></p>
            <p class="eff-sv-tile__sub"><?= (int) $totals['open'] ?> <?= $tr('ont des étapes ouvertes.', 'have open steps.') ?></p>
        </a>
        <a class="eff-sv-tile eff-sv-tile--link<?= (int) $totals['late'] > 0 ? ' is-alert' : '' ?>" href="<?= $h($base . '?etat=retard') ?>">
            <h3 class="eff-sv-tile__title">En retard</h3>
            <p class="eff-sv-tile__big"><?= (int) $totals['late'] ?></p>
            <p class="eff-sv-tile__sub"><?= $tr('membres avec une échéance dépassée.', 'members with a past-due step.') ?></p>
        </a>
        <a class="eff-sv-tile eff-sv-tile--link<?= (int) $totals['blocked'] > 0 ? ' is-warn' : '' ?>" href="<?= $h($base . '?etat=bloque') ?>">
            <h3 class="eff-sv-tile__title">Bloqués</h3>
            <p class="eff-sv-tile__big"><?= (int) $totals['blocked'] ?></p>
            <p class="eff-sv-tile__sub"><?= $tr('membres avec une étape bloquée.', 'members with a blocked step.') ?></p>
        </a>
        <div class="eff-sv-tile">
            <h3 class="eff-sv-tile__title">Dossiers à jour</h3>
            <p class="eff-sv-tile__big"><?= (int) $totals['complete'] ?></p>
            <p class="eff-sv-tile__sub"><?= $tr('toutes les étapes sont terminées.', 'every step is complete.') ?></p>
        </div>
    </div>

    <form class="eff-sv-filter eff-sv-filter--team" method="get" action="<?= $h($base) ?>" role="search" aria-label="Filtrer le suivi des dossiers">
        <label class="eff-sv-field eff-sv-field--grow">
            <span>Rechercher un membre</span>
            <input type="search" name="q" value="<?= $h($f['q'] ?? '') ?>" placeholder="Nom, indicatif, unité…" autocomplete="off">
        </label>
        <label class="eff-sv-field">
            <span>Parcours</span>
            <select name="parcours">
                <option value="">Tous les parcours</option>
                <?php foreach ($trackLabels as $k => $l): ?><option value="<?= $h($k) ?>"<?= ($f['track'] ?? '') === $k ? ' selected' : '' ?>><?= $h($l) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label class="eff-sv-field">
            <span>Responsable de l’étape</span>
            <select name="responsable">
                <option value="">Tous</option>
                <?php foreach ($ownerFilters as $k => $l): ?><option value="<?= $h($k) ?>"<?= $ownerSelected === $k ? ' selected' : '' ?>><?= $h($l) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label class="eff-sv-field">
            <span>État</span>
            <select name="etat">
                <option value="">Tous les dossiers</option>
                <option value="ouvert"<?= ($f['etat'] ?? '') === 'ouvert' ? ' selected' : '' ?>>Étapes ouvertes</option>
                <option value="retard"<?= ($f['etat'] ?? '') === 'retard' ? ' selected' : '' ?>>En retard</option>
                <option value="bloque"<?= ($f['etat'] ?? '') === 'bloque' ? ' selected' : '' ?>>Bloqués</option>
            </select>
        </label>
        <?php if ($suiviUnits ?? []): ?>
        <label class="eff-sv-field">
            <span>Unité</span>
            <select name="unite">
                <option value="">Toutes les unités</option>
                <?php foreach ($suiviUnits as $uid => $un): ?><option value="<?= (int) $uid ?>"<?= (int) ($f['unit'] ?? 0) === (int) $uid ? ' selected' : '' ?>><?= $h($un) ?></option><?php endforeach; ?>
            </select>
        </label>
        <?php endif; ?>
        <label class="eff-sv-field">
            <span>Trier par</span>
            <select name="tri">
                <?php foreach (['retard' => 'Retards d’abord', 'echeance' => 'Prochaine échéance', 'progression' => 'Progression', 'nom' => 'Nom'] as $k => $l): ?><option value="<?= $h($k) ?>"<?= ($f['sort'] ?? 'retard') === $k ? ' selected' : '' ?>><?= $h($l) ?></option><?php endforeach; ?>
            </select>
        </label>
        <div class="eff-sv-filter__go">
            <button type="submit" class="eff-sv-btn eff-sv-btn--primary">Filtrer</button>
            <?php if ($filtered): ?><a class="eff-sv-btn" href="<?= $h($base) ?>">Réinitialiser</a><?php endif; ?>
        </div>
    </form>

    <?php if ($canManage): ?>
        <form method="post" action="<?= $h(effectifs_workspace_url('suivi/relancer')) ?>" id="sv-bulk" class="eff-sv-bulk" data-sv-bulk data-max="<?= (int) ($bulkMax ?? 25) ?>" data-ui-confirm="1" data-ui-confirm-title="Relancer la sélection ?" data-ui-confirm-body="Chaque membre sélectionné reçoit une notification pour sa prochaine étape à faire. Un membre déjà relancé dans les 24 dernières heures est ignoré.">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken ?? '') ?>">
            <p class="eff-sv-bulk__text"><strong data-sv-bulk-count>0</strong> <?= $tr('membre sélectionné', 'member selected') ?><span data-sv-bulk-plural hidden>s</span>. <span class="eff-sv-faint">Chaque membre reçoit une notification pour sa prochaine étape à faire. Au plus <?= (int) ($bulkMax ?? 25) ?> relances par envoi ; un membre déjà relancé dans les 24 h est ignoré.</span></p>
            <button type="submit" class="eff-sv-btn eff-sv-btn--primary" data-sv-bulk-submit disabled>Relancer la sélection</button>
        </form>
    <?php endif; ?>

    <p class="eff-sv-result"><?= $tr((int) $suiviCount . ' membre' . ((int) $suiviCount > 1 ? 's' : '') . ($filtered ? ' correspondent à ces filtres.' : ' au total.'), (int) $suiviCount . ' member' . ((int) $suiviCount === 1 ? '' : 's') . ($filtered ? ' match these filters.' : ' in total.')) ?></p>

    <?php if ($rows === []): ?>
        <div class="eff-sv-empty">
            <strong>Aucun membre ne correspond.</strong>
            <span>Changez les filtres ou réinitialisez la recherche.</span>
        </div>
    <?php else: ?>
    <div class="eff-sv-tablewrap">
        <table class="eff-sv-table">
            <caption class="eff-sv-sr">Suivi des dossiers : progression et prochaine étape de chaque membre</caption>
            <thead>
                <tr>
                    <?php if ($canManage): ?><th scope="col" class="eff-sv-table__check"><label class="eff-sv-check"><input type="checkbox" aria-label="Tout sélectionner sur cette page" data-sv-bulk-all></label></th><?php endif; ?>
                    <th scope="col"><a href="<?= $h($sortUrl('nom')) ?>">Membre<?= $h($sortMark('nom')) ?></a></th>
                    <th scope="col"><a href="<?= $h($sortUrl('progression')) ?>">Progression<?= $h($sortMark('progression')) ?></a></th>
                    <th scope="col">Prochaine étape</th>
                    <th scope="col"><a href="<?= $h($sortUrl('echeance')) ?>">Échéance<?= $h($sortMark('echeance')) ?></a></th>
                    <th scope="col"><a href="<?= $h($sortUrl('retard')) ?>">Retards<?= $h($sortMark('retard')) ?></a></th>
                    <th scope="col"><span class="eff-sv-sr">Action</span></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <?php $s = $r['summary']; $n = $r['next']; $late = $n !== null ? $daysLate($n['due_on']) : 0; ?>
                <tr class="<?= (int) $s['late'] > 0 ? 'is-late' : '' ?>">
                    <?php if ($canManage): ?>
                        <td class="eff-sv-table__check" data-label="">
                            <label class="eff-sv-check"><input type="checkbox" form="sv-bulk" name="members[]" value="<?= (int) $r['id'] ?>" aria-label="Sélectionner <?= $h($r['name']) ?>"<?php if ((int) $r['remindable'] < 1): ?> disabled title="Aucune étape à faire par le membre"<?php endif; ?> data-sv-bulk-item></label>
                        </td>
                    <?php endif; ?>
                    <th scope="row" class="eff-sv-table__member" data-label="Membre">
                        <a href="<?= $h(effectifs_member_url((int) $r['id'], 'suivi')) ?>"><?= $h($r['name']) ?></a>
                        <?php if ($r['unit_name'] !== ''): ?><span class="eff-sv-faint"><?= $h($r['unit_name']) ?></span><?php endif; ?>
                    </th>
                    <td data-label="Progression">
                        <?php if ((int) $s['total'] === 0): ?>
                            <span class="eff-sv-faint">Aucune étape</span>
                        <?php else: ?>
                            <div class="eff-sv-bar eff-sv-bar--sm" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) $s['percent'] ?>" aria-label="Progression de <?= $h($r['name']) ?>"><i style="width:<?= (int) $s['percent'] ?>%"></i></div>
                            <span class="eff-sv-table__frac"><?= (int) $s['done'] ?> / <?= (int) $s['total'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Prochaine étape">
                        <?php if ($n !== null): ?>
                            <span class="eff-sv-table__next"><?= $h($n['title']) ?></span>
                            <span class="eff-sv-faint"><?= $h($trackLabels[$n['track']] ?? '') ?> · <?= $h($n['owner_label']) ?></span>
                        <?php else: ?>
                            <span class="eff-sv-faint"><?= (int) $s['total'] === 0 ? '—' : $tr('Rien à faire', 'Nothing to do') ?></span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Échéance">
                        <?php if ($n !== null && $n['due_on'] !== null): ?>
                            <?= $h($fd($n['due_on'])) ?>
                        <?php else: ?><span class="eff-sv-faint">—</span><?php endif; ?>
                    </td>
                    <td data-label="Retards">
                        <?php if ((int) $s['late'] > 0): ?>
                            <span class="eff-sv-badge is-overdue"><?= (int) $s['late'] ?> <?= $tr('en retard', 'overdue') ?><?= $late > 0 ? ' · ' . $late . ' ' . $tr('j', 'd') : '' ?></span>
                        <?php endif; ?>
                        <?php if ((int) $s['blocked'] > 0): ?><span class="eff-sv-badge is-blocked"><?= (int) $s['blocked'] ?> <?= $tr('bloquée', 'blocked') ?><?= (int) $s['blocked'] > 1 && !$en ? 's' : '' ?></span><?php endif; ?>
                        <?php if ((int) $s['late'] === 0 && (int) $s['blocked'] === 0): ?><span class="eff-sv-faint">—</span><?php endif; ?>
                    </td>
                    <td class="eff-sv-table__go" data-label="">
                        <a class="eff-sv-btn" href="<?= $h(effectifs_member_url((int) $r['id'], 'suivi')) ?>">Ouvrir le suivi<span class="eff-sv-sr"> de <?= $h($r['name']) ?></span></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ((int) $suiviPages > 1): ?>
        <nav class="eff-sv-pager" aria-label="Pages">
            <?php for ($p = 1; $p <= (int) $suiviPages; $p++): ?>
                <a class="eff-sv-btn<?= $p === (int) $suiviPage ? ' eff-sv-btn--primary' : '' ?>" href="<?= $h($base . $query(['page' => $p > 1 ? $p : ''])) ?>"<?= $p === (int) $suiviPage ? ' aria-current="page"' : '' ?>><?= $p ?></a>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
    <?php endif; ?>
</div>
<script src="<?= $h(asset_url('assets/js/eff-suivi.js')) ?>" defer></script>
