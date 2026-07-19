<?php
declare(strict_types=1);

$schemaReady = !empty($senioritySchemaReady);
$definitions = is_array($seniorityDefinitions ?? null) ? $seniorityDefinitions : [];
$stats = is_array($seniorityDefinitionStats ?? null)
    ? $seniorityDefinitionStats
    : ['total' => 0, 'active' => 0, 'visible' => 0, 'inactive' => 0, 'hidden' => 0];
$csrf = htmlspecialchars((string) ($seniorityCsrf ?? ''), ENT_QUOTES, 'UTF-8');
$flashErr = \App\Core\Session::getFlash('error');
$flashOk = \App\Core\Session::getFlash('success');

$scopeMeta = static function (string $scope): array {
    return match ($scope) {
        'user' => ['label' => 'Membre', 'class' => 'bo-seniority__badge--scope'],
        'tenant' => ['label' => 'Communauté', 'class' => 'bo-seniority__badge--scope'],
        'unit' => ['label' => 'Unité', 'class' => 'bo-seniority__badge--scope'],
        'group' => ['label' => 'Groupe', 'class' => 'bo-seniority__badge--scope'],
        'mission' => ['label' => 'Mission', 'class' => 'bo-seniority__badge--scope'],
        'qualification' => ['label' => 'Formation', 'class' => 'bo-seniority__badge--scope'],
        'grade' => ['label' => 'Grade', 'class' => 'bo-seniority__badge--scope'],
        'role' => ['label' => 'Rôle', 'class' => 'bo-seniority__badge--scope'],
        'campaign' => ['label' => 'Campagne', 'class' => 'bo-seniority__badge--scope'],
        'custom' => ['label' => 'Personnalisé', 'class' => 'bo-seniority__badge--scope'],
        'org' => ['label' => 'Organisation', 'class' => 'bo-seniority__badge--scope'],
        'global' => ['label' => 'Transverse', 'class' => 'bo-seniority__badge--scope'],
        default => ['label' => 'Autre', 'class' => 'bo-seniority__badge--scope'],
    };
};

$calcMeta = static function (string $mode): array {
    return match ($mode) {
        'from_start' => ['label' => 'Depuis la première date', 'short' => 'Première date'],
        'sum_periods' => ['label' => 'Somme des périodes', 'short' => 'Périodes cumulées'],
        'active_only' => ['label' => 'Périodes en cours seulement', 'short' => 'En cours'],
        'custom_rule' => ['label' => 'Règle personnalisée', 'short' => 'Personnalisé'],
        default => ['label' => 'Calcul standard', 'short' => 'Standard'],
    };
};

$sourceLabel = static function (string $raw): string {
    return match ($raw) {
        'manual' => 'Saisie sur le dossier',
        'inferred', 'dossier', 'auto_dossier' => 'Complété depuis le dossier',
        'auto', 'system' => 'Calcul automatique',
        default => 'Saisie sur le dossier',
    };
};

$scopeOptions = [];
$calcOptions = [];
foreach ($definitions as $def) {
    $s = (string) ($def['scope'] ?? '');
    $c = (string) ($def['calc_mode'] ?? '');
    if ($s !== '' && !isset($scopeOptions[$s])) {
        $scopeOptions[$s] = $scopeMeta($s)['label'];
    }
    if ($c !== '' && !isset($calcOptions[$c])) {
        $calcOptions[$c] = $calcMeta($c)['short'];
    }
}
asort($scopeOptions, SORT_NATURAL | SORT_FLAG_CASE);
asort($calcOptions, SORT_NATURAL | SORT_FLAG_CASE);

$rowsForJs = [];
foreach ($definitions as $def) {
    $id = (int) ($def['id'] ?? 0);
    if ($id < 1) {
        continue;
    }
    $label = (string) ($def['label'] ?? 'Indicateur');
    $scope = (string) ($def['scope'] ?? '');
    $calc = (string) ($def['calc_mode'] ?? '');
    $source = (string) ($def['source_type'] ?? 'manual');
    $scopeInfo = $scopeMeta($scope);
    $calcInfo = $calcMeta($calc);
    $sourceTxt = $sourceLabel($source);
    $hay = function_exists('mb_strtolower')
        ? mb_strtolower($label . ' ' . $scopeInfo['label'] . ' ' . $calcInfo['label'] . ' ' . $sourceTxt, 'UTF-8')
        : strtolower($label . ' ' . $scopeInfo['label'] . ' ' . $calcInfo['label'] . ' ' . $sourceTxt);
    $rowsForJs[] = [
        'id' => $id,
        'label' => $label,
        'scope' => $scope,
        'scopeLabel' => $scopeInfo['label'],
        'calc' => $calc,
        'calcLabel' => $calcInfo['short'],
        'calcTitle' => $calcInfo['label'],
        'sourceLabel' => $sourceTxt,
        'active' => !empty($def['is_active']),
        'visible' => !empty($def['is_visible']),
        'sort' => (int) ($def['sort_order'] ?? 0),
        'hay' => $hay,
    ];
}

$total = (int) ($stats['total'] ?? 0);
$active = (int) ($stats['active'] ?? 0);
$visible = (int) ($stats['visible'] ?? 0);
$inactive = (int) ($stats['inactive'] ?? 0);
$hidden = (int) ($stats['hidden'] ?? 0);
?>
<link href="<?= htmlspecialchars(asset_url('assets/css/back-office-seniority.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">

<?php
$rowsJson = json_encode(
    $rowsForJs,
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
);
if ($rowsJson === false) {
    $rowsJson = '[]';
}
?>
<div class="bo-seniority" x-data="boSeniorityFilters(<?= $rowsJson ?>)">
    <header class="bo-seniority__hero">
        <div class="bo-seniority__hero-inner">
            <div>
                <p class="bo-seniority__eyebrow">Communauté · Effectifs & RH</p>
                <h1 class="bo-seniority__title">Ancienneté</h1>
                <p class="bo-seniority__lead">
                    Choisissez les indicateurs affichés sur les fiches personnel et dans l’espace RH.
                    Les durées se calculent à partir des périodes enregistrées sur chaque dossier.
                </p>
            </div>
            <div class="bo-seniority__hero-actions">
                <a href="<?= htmlspecialchars(url('back-office/organisation-effectifs'), ENT_QUOTES, 'UTF-8') ?>" class="bo-seniority__btn bo-seniority__btn--ghost">Organisation des effectifs</a>
                <a href="<?= htmlspecialchars(url('back-office'), ENT_QUOTES, 'UTF-8') ?>" class="bo-seniority__btn bo-seniority__btn--solid">Centre de pilotage</a>
            </div>
        </div>
    </header>

    <div class="bo-seniority__deck">
        <?php if ($flashOk): ?>
            <div class="bo-seniority__flash bo-seniority__flash--ok" role="status"><?= htmlspecialchars((string) $flashOk, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($flashErr): ?>
            <div class="bo-seniority__flash bo-seniority__flash--err" role="alert"><?= htmlspecialchars((string) $flashErr, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if (!$schemaReady): ?>
            <div class="bo-seniority__warn" role="status">
                Le module d’ancienneté n’est pas encore disponible sur cette communauté.
                Une mise à jour de la plateforme est nécessaire avant de publier les indicateurs.
            </div>
        <?php else: ?>
            <div class="bo-seniority__kpi-grid" aria-label="Synthèse des indicateurs">
                <div class="bo-seniority__kpi">
                    <p class="bo-seniority__kpi-label">Indicateurs</p>
                    <p class="bo-seniority__kpi-value"><?= $total ?></p>
                    <p class="bo-seniority__kpi-meta">Catalogue de la communauté</p>
                </div>
                <div class="bo-seniority__kpi">
                    <p class="bo-seniority__kpi-label">Actifs</p>
                    <p class="bo-seniority__kpi-value"><?= $active ?></p>
                    <p class="bo-seniority__kpi-meta"><?= $inactive ?> inactif<?= $inactive > 1 ? 's' : '' ?></p>
                </div>
                <div class="bo-seniority__kpi">
                    <p class="bo-seniority__kpi-label">Visibles</p>
                    <p class="bo-seniority__kpi-value"><?= $visible ?></p>
                    <p class="bo-seniority__kpi-meta">Affichés sur les fiches</p>
                </div>
                <div class="bo-seniority__kpi">
                    <p class="bo-seniority__kpi-label">Masqués</p>
                    <p class="bo-seniority__kpi-value"><?= $hidden ?></p>
                    <p class="bo-seniority__kpi-meta">Prêts, non publiés</p>
                </div>
            </div>

            <section class="bo-seniority__panel" aria-labelledby="bo-seniority-tools-title">
                <div class="bo-seniority__panel-head">
                    <h2 id="bo-seniority-tools-title">Actions RH</h2>
                    <p>
                        Installez le catalogue standard, alignez l’ancienneté dans la communauté pour tous les membres actifs,
                        ou proposez des dates de départ à partir des informations déjà présentes sur les dossiers.
                    </p>
                </div>
                <div class="bo-seniority__tools">
                    <div class="bo-seniority__tool">
                        <div class="bo-seniority__tool-body">
                            <p class="bo-seniority__tool-title">Installer les indicateurs standards</p>
                            <p class="bo-seniority__tool-desc">
                                Ajoute le catalogue prêt à l’emploi (communauté, service, unité, grade, rôles, etc.).
                                Les indicateurs déjà présents ne sont pas dupliqués.
                            </p>
                        </div>
                        <form method="post" action="<?= htmlspecialchars(url('back-office/organisation/anciennete/initialiser'), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                            <button type="submit" class="bo-seniority__btn bo-seniority__btn--indigo">Installer ou compléter</button>
                        </form>
                    </div>
                    <div class="bo-seniority__tool">
                        <div class="bo-seniority__tool-body">
                            <p class="bo-seniority__tool-title">Mettre à jour tout le personnel</p>
                            <p class="bo-seniority__tool-desc">
                                Recalcule « Ancienneté dans la communauté » pour chaque membre actif, à partir des dates
                                d’incorporation, d’enrôlement ou d’entrée présentes sur le dossier.
                            </p>
                        </div>
                        <form
                            method="post"
                            action="<?= htmlspecialchars(url('back-office/organisation/anciennete/synchroniser-effectifs'), ENT_QUOTES, 'UTF-8') ?>"
                            onsubmit="return confirm('Lancer la mise à jour pour tous les membres actifs ? Cela peut prendre quelques secondes sur une grande communauté.');"
                        >
                            <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                            <button type="submit" class="bo-seniority__btn bo-seniority__btn--primary">Mettre à jour le personnel</button>
                        </form>
                    </div>
                    <div class="bo-seniority__tool">
                        <div class="bo-seniority__tool-body">
                            <p class="bo-seniority__tool-title">Compléter depuis le dossier</p>
                            <p class="bo-seniority__tool-desc">
                                Propose une date de départ pour l’unité, le groupe, le rôle ou le grade lorsqu’aucune période
                                n’a encore été saisie. Les saisies manuelles de l’encadrement restent prioritaires.
                            </p>
                        </div>
                        <form
                            method="post"
                            action="<?= htmlspecialchars(url('back-office/organisation/anciennete/completer-depuis-dossier'), ENT_QUOTES, 'UTF-8') ?>"
                            onsubmit="return confirm('Lancer le complément pour tous les membres actifs ? Cela peut prendre quelques secondes sur une grande communauté.');"
                        >
                            <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                            <button type="submit" class="bo-seniority__btn bo-seniority__btn--quiet">Compléter depuis le dossier</button>
                        </form>
                    </div>
                </div>
            </section>

            <?php if ($definitions === []): ?>
                <section class="bo-seniority__panel" aria-labelledby="bo-seniority-empty-title">
                    <div class="bo-seniority__empty">
                        <div class="bo-seniority__empty-icon" aria-hidden="true">∅</div>
                        <p id="bo-seniority-empty-title">Aucun indicateur pour l’instant</p>
                        <span>Utilisez « Installer ou compléter » ci-dessus pour ajouter le catalogue standard de votre communauté.</span>
                    </div>
                </section>
            <?php else: ?>
                <form method="post" action="<?= htmlspecialchars(url('back-office/organisation/anciennete'), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                    <section class="bo-seniority__panel" aria-labelledby="bo-seniority-sheet-title">
                        <div class="bo-seniority__panel-head">
                            <h2 id="bo-seniority-sheet-title">Table des indicateurs</h2>
                            <p>
                                Cochez « Afficher » pour que les membres voient la ligne sur leur fiche.
                                Décochez « Actif » pour retirer un indicateur du calcul sans effacer l’historique.
                            </p>
                        </div>

                        <div class="bo-seniority__toolbar">
                            <div class="bo-seniority__filters">
                                <div class="bo-seniority__filter-row">
                                    <span class="bo-seniority__filter-label">Statut</span>
                                    <button type="button" class="bo-seniority__chip" :class="status === '' ? 'is-active' : ''" @click="status = ''">Tous</button>
                                    <button type="button" class="bo-seniority__chip" :class="status === 'active' ? 'is-active-soft' : ''" @click="status = 'active'">Actifs</button>
                                    <button type="button" class="bo-seniority__chip" :class="status === 'inactive' ? 'is-active-soft' : ''" @click="status = 'inactive'">Inactifs</button>
                                    <button type="button" class="bo-seniority__chip" :class="status === 'visible' ? 'is-active-soft' : ''" @click="status = 'visible'">Visibles</button>
                                    <button type="button" class="bo-seniority__chip" :class="status === 'hidden' ? 'is-active-soft' : ''" @click="status = 'hidden'">Masqués</button>
                                </div>
                            </div>
                            <div class="bo-seniority__toolbar-side">
                                <?php if ($scopeOptions !== []): ?>
                                <div class="bo-seniority__select-wrap">
                                    <label for="bo-seniority-scope">Domaine</label>
                                    <select id="bo-seniority-scope" x-model="scope">
                                        <option value="">Tous les domaines</option>
                                        <?php foreach ($scopeOptions as $scopeVal => $scopeLab): ?>
                                        <option value="<?= htmlspecialchars($scopeVal, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($scopeLab, ENT_QUOTES, 'UTF-8') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php endif; ?>
                                <?php if ($calcOptions !== []): ?>
                                <div class="bo-seniority__select-wrap">
                                    <label for="bo-seniority-calc">Calcul</label>
                                    <select id="bo-seniority-calc" x-model="calc">
                                        <option value="">Tous les calculs</option>
                                        <?php foreach ($calcOptions as $calcVal => $calcLab): ?>
                                        <option value="<?= htmlspecialchars($calcVal, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($calcLab, ENT_QUOTES, 'UTF-8') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php endif; ?>
                                <div class="bo-seniority__search">
                                    <label for="bo-seniority-search">Rechercher</label>
                                    <input
                                        id="bo-seniority-search"
                                        type="search"
                                        x-model="q"
                                        placeholder="Nom, domaine, calcul…"
                                        autocomplete="off"
                                    >
                                </div>
                            </div>
                        </div>

                        <div
                            class="bo-seniority__empty"
                            x-show="visibleCount === 0"
                            x-cloak
                        >
                            <div class="bo-seniority__empty-icon" aria-hidden="true">∅</div>
                            <p>Aucun indicateur ne correspond à ces filtres</p>
                            <span>Élargissez le statut, le domaine ou la recherche.</span>
                        </div>

                        <div
                            class="bo-seniority__sheet-wrap"
                            x-show="visibleCount > 0"
                        >
                            <table class="bo-seniority__sheet">
                                <thead>
                                    <tr>
                                        <th scope="col">Indicateur</th>
                                        <th scope="col">Domaine</th>
                                        <th scope="col">Calcul</th>
                                        <th scope="col" class="bo-seniority__col-num">Ordre</th>
                                        <th scope="col" class="bo-seniority__col-check">Actif</th>
                                        <th scope="col" class="bo-seniority__col-check">Afficher</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rowsForJs as $row):
                                        $id = (int) $row['id'];
                                    ?>
                                    <tr
                                        class="<?= $row['active'] ? '' : 'is-inactive' ?>"
                                        x-show="matchById(<?= $id ?>)"
                                    >
                                        <td class="bo-seniority__col-name" data-label="Indicateur">
                                            <span class="bo-seniority__name"><?= htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="bo-seniority__meta"><?= htmlspecialchars($row['sourceLabel'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </td>
                                        <td data-label="Domaine">
                                            <span class="bo-seniority__badge bo-seniority__badge--scope"><?= htmlspecialchars($row['scopeLabel'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </td>
                                        <td data-label="Calcul">
                                            <span class="bo-seniority__badge bo-seniority__badge--calc" title="<?= htmlspecialchars($row['calcTitle'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($row['calcLabel'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </td>
                                        <td class="bo-seniority__col-num" data-label="Ordre">
                                            <input
                                                type="number"
                                                name="rows[<?= $id ?>][sort]"
                                                value="<?= (int) $row['sort'] ?>"
                                                min="0"
                                                max="9999"
                                                class="bo-seniority__sort"
                                                aria-label="Ordre d’affichage pour <?= htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8') ?>"
                                            >
                                        </td>
                                        <td class="bo-seniority__col-check" data-label="Actif">
                                            <label class="bo-seniority__check">
                                                <input type="checkbox" name="rows[<?= $id ?>][active]" value="1" <?= $row['active'] ? 'checked' : '' ?>>
                                                <span>Actif</span>
                                            </label>
                                        </td>
                                        <td class="bo-seniority__col-check" data-label="Afficher">
                                            <label class="bo-seniority__check">
                                                <input type="checkbox" name="rows[<?= $id ?>][visible]" value="1" <?= $row['visible'] ? 'checked' : '' ?>>
                                                <span>Afficher</span>
                                            </label>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="bo-seniority__footer">
                            <p class="bo-seniority__footer-hint">
                                Plus l’ordre est petit, plus l’indicateur apparaît en haut de la fiche.
                                Les indicateurs masqués restent disponibles pour un affichage ultérieur.
                            </p>
                            <button type="submit" class="bo-seniority__btn bo-seniority__btn--ink">Enregistrer les réglages</button>
                        </div>
                    </section>
                </form>
            <?php endif; ?>

            <p class="bo-seniority__hint">
                Les périodes détaillées se gèrent sur chaque
                <a href="<?= htmlspecialchars(url('personnel'), ENT_QUOTES, 'UTF-8') ?>">fiche personnel</a>.
                Revenez à l’
                <a href="<?= htmlspecialchars(url('back-office/organisation-effectifs'), ENT_QUOTES, 'UTF-8') ?>">organisation des effectifs</a>
                pour les autres réglages RH.
            </p>
        <?php endif; ?>
    </div>
</div>
<script>
function boSeniorityFilters(rows) {
    return {
        q: '',
        status: '',
        scope: '',
        calc: '',
        rows: Array.isArray(rows) ? rows : [],
        match: function (row) {
            var needle = (this.q || '').trim().toLowerCase();
            if (needle && !(row.hay || '').includes(needle)) return false;
            if (this.scope && row.scope !== this.scope) return false;
            if (this.calc && row.calc !== this.calc) return false;
            if (this.status === 'active' && !row.active) return false;
            if (this.status === 'inactive' && row.active) return false;
            if (this.status === 'visible' && !row.visible) return false;
            if (this.status === 'hidden' && row.visible) return false;
            return true;
        },
        matchById: function (id) {
            var row = this.rows.find(function (r) { return r.id === id; });
            return row ? this.match(row) : false;
        },
        get visibleCount() {
            var self = this;
            return this.rows.filter(function (r) { return self.match(r); }).length;
        }
    };
}
</script>
