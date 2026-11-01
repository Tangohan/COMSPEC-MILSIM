<?php
declare(strict_types=1);

/**
 * Tableur des effectifs : barre de recherche et de filtres (maquette « Effectifs - Athena »).
 * Inclus par roster.php : utilise ses variables ($filters, $filterQuery, $units, $roles…).
 */
$hq = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$groupNow = (string) ($rosterGroup ?? 'unite');
$unitNow = (int) ($rosterUnitFilter ?? 0);
$categoryNow = (string) ($rosterGradeCategory ?? '');
$hasCategories = is_array($rosterGradeCategories ?? null) && $rosterGradeCategories !== [];
$groupUrl = static function (string $g) use ($filterQuery): string {
    $q = $filterQuery(['groupe' => $g, 'tri' => null, 'page' => null]);

    return effectifs_workspace_url() . ($q ? '?' . http_build_query($q) : '');
};
$moreActive = array_filter([
    ($filters['status'] ?? '') !== '',
    !empty($filters['role_id']),
    !empty($filters['fonction']),
    !empty($filters['langue']),
    $groupNow === 'liste' && ($filters['tri'] ?? 'nom') !== 'nom',
]);
$presenceValues = array_map(static fn (array $r): int => (int) ($r['presence_score'] ?? 0), $rows);
$presenceAvg = $presenceValues !== [] ? (int) round(array_sum($presenceValues) / count($presenceValues)) : null;
$shown = count($rows);
?>
<form method="get" action="<?= $hq(effectifs_workspace_url()) ?>" class="eff-mq-bar" data-autosubmit role="search" aria-label="Rechercher et filtrer les effectifs">
    <?php if (!empty($filters['sans_affectation'])): ?><input type="hidden" name="sans_affectation" value="1"><?php endif; ?>
    <?php if (!empty($filters['sans_role'])): ?><input type="hidden" name="sans_role" value="1"><?php endif; ?>
    <?php if ($rosterView !== 'general'): ?><input type="hidden" name="vue" value="<?= $hq($rosterView) ?>"><?php endif; ?>
    <input type="hidden" name="groupe" value="<?= $hq($groupNow) ?>" data-eff-group-input>
    <label class="eff-mq-search">
        <svg viewBox="0 0 16 16" width="15" height="15" aria-hidden="true"><circle cx="7" cy="7" r="4.6" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="m10.4 10.4 3.6 3.6" stroke="currentColor" stroke-width="1.6"/></svg>
        <span class="sr-only">Rechercher un membre</span>
        <input id="eff-q" type="search" name="q" value="<?= $hq((string) ($filters['q'] ?? '')) ?>" placeholder="Nom, indicatif, matricule, e-mail…" data-eff-slash-focus>
        <kbd title="Appuyez sur / pour chercher">/</kbd>
    </label>
    <label class="sr-only" for="eff-unite">Unité</label>
    <select id="eff-unite" name="unite" class="eff-mq-sel<?= $unitNow > 0 ? ' is-set' : '' ?>">
        <option value="">Toutes les unités</option>
        <?php foreach ($units as $u): ?>
            <?php $uid = (int) ($u['id'] ?? 0); ?>
            <option value="<?= $uid ?>" <?= $uid === $unitNow ? 'selected' : '' ?>><?= $hq(trim((string) ($u['assignment_path'] ?? $u['name'] ?? ''))) ?></option>
        <?php endforeach; ?>
    </select>
    <?php if ($hasCategories || $categoryNow !== ''): ?>
    <label class="sr-only" for="eff-categorie">Catégorie de grade</label>
    <select id="eff-categorie" name="categorie" class="eff-mq-sel<?= $categoryNow !== '' ? ' is-set' : '' ?>">
        <option value="">Tous les grades</option>
        <option value="off" <?= $categoryNow === 'off' ? 'selected' : '' ?>>Officiers</option>
        <option value="nco" <?= $categoryNow === 'nco' ? 'selected' : '' ?>>Sous-officiers</option>
        <option value="enl" <?= $categoryNow === 'enl' ? 'selected' : '' ?>>Militaires du rang</option>
    </select>
    <?php endif; ?>
    <details class="eff-mq-more"<?= $moreActive !== [] ? ' open' : '' ?>>
        <summary>Filtres<?php if ($moreActive !== []): ?> <span class="bo-eff-badge"><?= count($moreActive) ?></span><?php endif; ?></summary>
        <div class="eff-mq-more__panel">
            <label>Statut du compte
                <select id="eff-status" name="status">
                    <option value="">Tous les statuts</option>
                    <option value="active" <?= (($filters['status'] ?? '') === 'active') ? 'selected' : '' ?>>Compte actif</option>
                    <option value="inactive" <?= (($filters['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Compte inactif</option>
                    <option value="pending_verification" <?= (($filters['status'] ?? '') === 'pending_verification') ? 'selected' : '' ?>>E-mail à vérifier</option>
                </select>
            </label>
            <label>Niveau d’accès
                <select id="eff-role" name="role_id">
                    <option value="">Tous les niveaux</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= (int) ($role['id'] ?? 0) ?>" <?= ((int) ($filters['role_id'] ?? 0) === (int) ($role['id'] ?? 0)) ? 'selected' : '' ?>><?= $hq((string) ($role['name'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if ($jobOptions !== []): ?>
            <label>Emploi
                <select id="eff-fonction" name="fonction">
                    <option value="">Tous les emplois</option>
                    <?php foreach ($jobOptions as $jobName): ?>
                        <option value="<?= $hq((string) $jobName) ?>" <?= $jobFilter === (string) $jobName ? 'selected' : '' ?>><?= $hq((string) $jobName) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php endif; ?>
            <?php if (count($rosterLanguageCodes) > 1 || $languageFilter !== ''): ?>
            <label>Langue
                <select id="eff-langue" name="langue">
                    <option value="">Toutes les langues</option>
                    <?php foreach (array_values(array_unique(array_merge($rosterLanguageCodes, $languageFilter !== '' ? [$languageFilter] : []))) as $langCode): ?>
                        <option value="<?= $hq($langCode) ?>" <?= $languageFilter === $langCode ? 'selected' : '' ?>><?= $hq(strtoupper(\App\Support\CommunityLanguages::countryCode($langCode) ?: $langCode) . ' · ' . \App\Support\CommunityLanguages::label($langCode)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php endif; ?>
            <label>Trier la liste par
                <select id="eff-tri" name="tri" data-eff-sort-select>
                    <?php foreach ($sortOptions as $value => $label): ?>
                        <option value="<?= $hq((string) $value) ?>" <?= ($groupNow === 'liste' ? $currentSort : 'nom') === (string) $value ? 'selected' : '' ?>><?= $hq((string) $label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="eff-mq-more__actions">
                <button type="submit" class="eff-catalog__btn eff-catalog__btn--primary">Appliquer</button>
                <?php if ($hasActiveFilters || $unitNow > 0 || $categoryNow !== ''): ?>
                <a href="<?= $hq(effectifs_workspace_url()) ?>" class="eff-catalog__btn">Tout effacer</a>
                <?php endif; ?>
            </div>
        </div>
    </details>
    <span class="eff-mq-sp" aria-hidden="true"></span>
    <nav class="eff-mq-seg" aria-label="Regrouper les membres">
        <?php foreach (['unite' => 'Par unité', 'grade' => 'Par grade', 'liste' => 'Liste'] as $gKey => $gLabel): ?>
            <a href="<?= $hq($groupUrl($gKey)) ?>" class="<?= $groupNow === $gKey ? 'is-on' : '' ?>"<?= $groupNow === $gKey ? ' aria-current="true"' : '' ?>><?= $hq($gLabel) ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="eff-mq-seg" role="group" aria-label="Densité des lignes">
        <button type="button" data-eff-density="0" class="is-on" aria-pressed="true" title="Lignes confortables">
            <svg viewBox="0 0 16 16" width="15" height="15" aria-hidden="true"><path d="M3 4h10M3 8h10M3 12h10" stroke="currentColor" stroke-width="1.5"/></svg><span class="sr-only">Lignes confortables</span>
        </button>
        <button type="button" data-eff-density="1" aria-pressed="false" title="Lignes compactes">
            <svg viewBox="0 0 16 16" width="15" height="15" aria-hidden="true"><path d="M3 3h10M3 6.5h10M3 10h10M3 13.5h10" stroke="currentColor" stroke-width="1.4"/></svg><span class="sr-only">Lignes compactes</span>
        </button>
    </div>
</form>

<div class="eff-mq-meta">
    <span class="eff-mq-meta__count"><b><?= $shown ?></b> membre<?= $shown > 1 ? 's' : '' ?> affiché<?= $shown > 1 ? 's' : '' ?><?= $totalPages > 1 ? ' sur ' . $total . ' (page ' . $page . ' / ' . $totalPages . ')' : '' ?><?php if ($hasActiveFilters || $unitNow > 0 || $categoryNow !== ''): ?> · <a href="<?= $hq(effectifs_workspace_url()) ?>">effacer les filtres</a><?php endif; ?></span>
    <nav class="eff-mq-views" aria-label="Colonnes du tableur">
        <?php foreach (['general' => ['Vue générale', 'Grade, affectation, emploi, accès, temps en mission, heures de vol'], 'medical' => ['Médical', 'Groupe sanguin, poids, taille, intolérances et allergies'], 'aviation' => ['Heures de vol', 'Heures aux commandes, par type d’appareil']] as $viewKey => [$viewLabel, $viewHint]): ?>
            <a class="<?= $rosterView === $viewKey ? 'is-on' : '' ?>" href="<?= $hq($queryUrlView($viewKey)) ?>" title="<?= $hq($viewHint) ?>"<?= $rosterView === $viewKey ? ' aria-current="page"' : '' ?>><?= $hq($viewLabel) ?></a>
        <?php endforeach; ?>
    </nav>
    <span class="eff-mq-meta__export" aria-label="Exporter ce tableau">
        <a href="<?= $hq($exportUrl) ?>" title="Tableur CSV (Excel), avec les filtres de recherche">CSV</a>
        <?php if ($canExportPdf): ?>
        <a href="<?= $hq($pdfExportUrl) ?>" title="PDF prêt à imprimer ou à envoyer : cette vue, avec les filtres et le tri affichés">PDF<?= $rosterView === 'medical' ? ' médical' : ($rosterView === 'aviation' ? ' heures de vol' : '') ?></a>
        <?php else: ?>
        <a class="is-locked" href="<?= $hq(url('platform/upgrade?from=pdf_exports')) ?>" title="Les exports PDF sont inclus dans les formules payantes">PDF</a>
        <?php endif; ?>
    </span>
    <?php if ($presenceAvg !== null): ?>
    <span class="eff-mq-meta__avg" title="Moyenne de présence des membres affichés">Moy. présence <b><?= $presenceAvg ?> %</b></span>
    <?php endif; ?>
</div>
