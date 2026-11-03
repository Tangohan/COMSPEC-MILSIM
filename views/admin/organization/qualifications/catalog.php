<?php
declare(strict_types=1);

/**
 * Catalogue de référence des qualifications (onglet de la page Qualifications) : toutes les qualifications des packs
 * par pays et de l’OTAN, filtrables et triables depuis l’adresse (pays, catégorie, niveau, émetteur, cours Academy,
 * déjà importée ou non, recherche FR / EN / intitulé officiel), avec leur niveau, leurs emplois liés et l’import au
 * choix, sans doublon. « Voir la fiche » ouvre la fiche détaillée commune avec la fenêtre des emplois
 * (assets/js/qualification-sheet.js). Contenu du catalogue servi dans la langue du lecteur (translate="no").
 * Drapeaux et emblème OTAN dessinés en CSS (job-catalog.css, .jobcat__flag).
 */
use App\Controllers\Admin\Organization\QualificationReferentielController as Ctrl;
use App\Support\QualificationCatalog\QualificationCatalog as QC;
use App\Support\QualificationCatalog\QualificationCatalogExplorer as QX;

$rows = is_array($catalogRows ?? null) ? $catalogRows : [];
$imported = is_array($catalogImported ?? null) ? $catalogImported : [];
$countries = is_array($catalogCountries ?? null) ? $catalogCountries : [];
$issuers = is_array($catalogIssuers ?? null) ? $catalogIssuers : [];
$f = is_array($catalogFilters ?? null) ? $catalogFilters : QX::filters([]);
$lang = (string) ($catalogLang ?? 'fr');
$total = (int) ($catalogTotal ?? count($rows));
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$base = url('back-office/referentiels/qualifications/catalogue');
$query = static function (array $change) use ($f): string {
    $q = array_filter(array_merge($f, $change), static fn ($v): bool => $v !== '' && $v !== null);
    if (($q['tri'] ?? '') === 'pays') {
        unset($q['tri']);
    }

    return http_build_query($q);
};
$currentQuery = $query([]);
$available = count(array_filter($rows, static fn (array $r): bool => !isset($imported[strtoupper((string) $r['code'])])));
$active = array_filter(array_diff_key($f, ['tri' => 1]), static fn ($v): bool => $v !== '');
$categories = QC::CATEGORIES;
// Formule : plafond d’imports (compteur, jamais de perte) et « Tout importer » d’un pays en un clic.
$quota = is_array($catalogQuota ?? null) ? $catalogQuota : null;
$bulk = !empty($catalogBulk);
$bulkOffer = is_array($catalogBulkOffer ?? null) ? $catalogBulkOffer : null;
$packOf = '';
foreach ($countries as $c) {
    if (($c['code'] ?? '') === $f['pays']) {
        $packOf = (string) $c['pack'];
    }
}
?>
<div class="rq rqc" data-rqc data-qualification-url="<?= $h(url('back-office/referentiels/qualifications/catalogue/fiche')) ?>" data-csrf="<?= $h(\App\Core\Csrf::token()) ?>" data-detail="<?= $h((string) ($catalogDetail ?? '')) ?>">
    <div class="rq-top">
        <?php $rqTab = 'catalogue'; require __DIR__ . '/_tabs.php'; ?>
        <div class="rq-top__row">
            <div>
                <p class="rq-kicker">PERSONNEL · QUALIFICATIONS · CATALOGUE DE RÉFÉRENCE</p>
                <h1>Catalogue de référence</h1>
                <p class="rq-lead">Brevets et qualifications réels par pays et de l’OTAN, avec leur intitulé officiel, l’école ou l’organisme qui les délivre, leurs prérequis, les emplois du catalogue d’emplois qui les exigent et les formations Academy qui y préparent. Importez ceux qui servent à votre communauté : chacun devient une fiche de votre référentiel, rattachée à son émetteur, que vous réglez ensuite. Une qualification déjà présente n’est jamais importée deux fois.</p>
                <p class="rq-lead"><a href="<?= $h(url('documentation/qualifications')) ?>" data-qualification-guides>? Comment ça marche</a> · <a href="<?= $h(url('documentation/qualifications/prerequis-automatiques')) ?>">Prérequis automatiques possibles</a> · <a href="<?= $h(url('documentation/qualifications/prerequis-attestes')) ?>">Prérequis manuels (attestation)</a></p>
            </div>
        </div>
    </div>

    <nav class="rqc-tabs" aria-label="Pays du catalogue">
        <a class="rqc-tab<?= $f['pays'] === '' ? ' is-on' : '' ?>" href="<?= $h($base . '?' . $query(['pays' => ''])) ?>"<?= $f['pays'] === '' ? ' aria-current="page"' : '' ?>><span>Tous les pays</span><span class="rqc-tab__count"><?= $total ?></span></a>
        <?php foreach ($countries as $c): $on = $c['code'] === $f['pays']; ?>
        <a class="rqc-tab<?= $on ? ' is-on' : '' ?>" href="<?= $h($base . '?' . $query(['pays' => (string) $c['code']])) ?>"<?= $on ? ' aria-current="page"' : '' ?>><span class="jobcat__flag" data-flag="<?= $h((string) $c['code']) ?>" aria-hidden="true"><?= $h((string) $c['code']) ?></span><span translate="no"><?= $h((string) $c['label']) ?></span><span class="rqc-tab__count"><?= (int) $c['count'] ?></span></a>
        <?php endforeach; ?>
    </nav>

    <form method="get" action="<?= $h($base) ?>" class="rqc-filters" role="search" data-rqc-filters>
        <?php if ($f['pays'] !== ''): ?><input type="hidden" name="pays" value="<?= $h($f['pays']) ?>"><?php endif; ?>
        <label class="rqc-filters__search"><span>Rechercher</span><input type="search" name="q" value="<?= $h($f['q']) ?>" placeholder="Nom, sigle, intitulé officiel, école…"></label>
        <label><span>Catégorie</span><select name="categorie"><option value="">Toutes</option><?php foreach ($categories as $key => $labels): ?><option value="<?= $h($key) ?>"<?= $f['categorie'] === $key ? ' selected' : '' ?> translate="no"><?= $h(QC::categoryLabel($key, $lang)) ?></option><?php endforeach; ?></select></label>
        <label><span>Niveau</span><select name="niveau"><option value="">Tous</option><?php foreach (QX::LEVELS as $key => $level): ?><option value="<?= $h($key) ?>"<?= $f['niveau'] === $key ? ' selected' : '' ?> translate="no"><?= (int) $level['rank'] ?> · <?= $h(QX::levelLabel($key, $lang)) ?></option><?php endforeach; ?></select></label>
        <label><span>Émetteur</span><select name="emetteur"><option value="">Tous</option><?php foreach ($issuers as $issuer): ?><option value="<?= $h((string) $issuer) ?>"<?= $f['emetteur'] === $issuer ? ' selected' : '' ?> translate="no"><?= $h((string) $issuer) ?></option><?php endforeach; ?></select></label>
        <label><span>Dans le référentiel</span><select name="etat"><option value="">Toutes</option><option value="a-importer"<?= $f['etat'] === 'a-importer' ? ' selected' : '' ?>>À importer</option><option value="importee"<?= $f['etat'] === 'importee' ? ' selected' : '' ?>>Déjà importées</option></select></label>
        <label class="rqc-filters__sort"><span>Trier par</span><select name="tri"><option value="pays"<?= $f['tri'] === 'pays' ? ' selected' : '' ?>>Pays puis catégorie</option><option value="nom"<?= $f['tri'] === 'nom' ? ' selected' : '' ?>>Nom</option><option value="categorie"<?= $f['tri'] === 'categorie' ? ' selected' : '' ?>>Catégorie</option><option value="niveau"<?= $f['tri'] === 'niveau' ? ' selected' : '' ?>>Niveau (le plus élevé d’abord)</option><option value="emplois"<?= $f['tri'] === 'emplois' ? ' selected' : '' ?>>Nombre d’emplois liés</option></select></label>
        <div class="rqc-filters__check"><input type="checkbox" id="rqc-academy" name="academy" value="1"<?= $f['academy'] === '1' ? ' checked' : '' ?>><label for="rqc-academy">Avec une formation Academy</label></div>
        <div class="rqc-filters__actions"><button type="submit" class="rq-btn rq-btn--primary">Filtrer</button><?php if ($active !== []): ?><a class="rq-btn" href="<?= $h($base) ?>">Réinitialiser les filtres</a><?php endif; ?></div>
    </form>

    <details class="rqc-levels">
        <summary>Comment le niveau est-il établi ?</summary>
        <p>Les packs ne donnent pas de niveau officiel : il est estimé d’après l’intitulé et les prérequis, sur une échelle commune à tous les pays. Une qualification qui porterait un niveau sourcé dans son pack l’affiche sans la mention « estimé ».</p>
        <dl class="rqc-levels__scale">
            <?php foreach ([
                'initial' => 'Sans prérequis.',
                'qualified' => 'Demande des prérequis.',
                'confirmed' => 'Cours avancé, de chef ou de chef d’équipe, ou dont un prérequis demande lui-même une qualification.',
                'instructor' => 'Instructeur ou moniteur.',
                'expert' => 'Instructeur évaluateur, niveau « master », ou instructeur qui doit déjà être instructeur.',
            ] as $key => $rule): ?>
            <div><dt><span class="rqc-level" data-level="<?= $h($key) ?>"><b><?= (int) QX::LEVELS[$key]['rank'] ?></b> <span translate="no"><?= $h(QX::levelLabel($key, $lang)) ?></span></span></dt><dd><?= $h($rule) ?></dd></div>
            <?php endforeach; ?>
        </dl>
    </details>

    <p class="rqc-warn" role="note">Qualifications de jeu inspirées des brevets réels, sans valeur réelle : la mention de chaque pack est reprise dans la description des qualifications importées. Les références citées sont publiques ; rien de classifié.</p>

    <form method="post" action="<?= $h(url('back-office/referentiels/qualifications/catalogue/importer')) ?>" class="rqc-form" data-rqc-import>
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="return_query" value="<?= $h($currentQuery) ?>">
        <div class="rqc-bar rqc-bar--sticky">
            <p class="rqc-count" role="status"><span><?= count($rows) ?> qualification(s) sur <?= $total ?> · <?= $available ?> à importer</span><span class="rqc-count__selected" data-rqc-selected hidden></span></p>
            <?php if ($quota !== null && !$quota['unlimited']): ?>
            <p class="rqc-quota<?= $quota['can_add'] ? '' : ' is-full' ?>"><b><?= (int) $quota['used'] ?> / <?= (int) $quota['limit'] ?></b> <span>qualifications importées sur votre formule</span><?php if ($quota['offer'] !== null): ?> · <span>Formule <b translate="no"><?= $h((string) $quota['offer']['name']) ?></b> : <?= $quota['offer']['value'] === 0 ? 'illimité' : (int) $quota['offer']['value'] ?></span> <a href="<?= $h((string) $quota['upgrade_url']) ?>">Voir les formules</a><?php endif; ?></p>
            <?php endif; ?>
            <div class="rqc-actions">
                <?php if ($packOf !== ''): ?>
                    <?php if ($bulk): ?>
                    <input type="hidden" name="pack" value="<?= $h($packOf) ?>">
                    <button type="submit" name="all" value="1" class="rq-btn">Tout importer ce pays</button>
                    <?php else: ?>
                    <span class="rqc-locked" title="Importer toutes les qualifications d’un pays en un clic est inclus dans la formule <?= $h((string) ($bulkOffer['name'] ?? 'Pro')) ?>."><button type="button" class="rq-btn" disabled>Tout importer ce pays</button> <a class="rqc-plan" href="<?= $h(\App\Services\Billing\QualificationCatalogQuota::upgradeUrl()) ?>">Formule <span translate="no"><?= $h((string) ($bulkOffer['name'] ?? 'Pro')) ?></span></a></span>
                    <?php endif; ?>
                <?php endif; ?>
                <button type="button" class="rq-btn" data-rqc-select-all<?= $available === 0 ? ' disabled' : '' ?>>Tout cocher</button>
                <button type="submit" class="rq-btn rq-btn--primary" data-rqc-import-selection>Importer la sélection</button>
            </div>
        </div>

        <?php if ($rows === []): ?>
            <p class="rqc-empty">Aucune qualification du catalogue ne correspond à ces filtres. <a href="<?= $h($base) ?>">Réinitialiser les filtres</a></p>
        <?php else: ?>
        <ul class="rqc-list rqc-grid">
            <?php foreach ($rows as $r):
                $definitionId = (int) ($imported[strtoupper((string) $r['code'])] ?? 0);
                $done = $definitionId > 0;
                $inputId = 'rqc-' . $h((string) $r['id']);
                $official = (string) $r['official_name'];
                $title = $official !== '' ? $official : (string) $r['name'];
                $level = $r['level'];
            ?>
            <li class="rqc-item rqc-card<?= $done ? ' is-done' : '' ?>" data-rqc-row="<?= $h((string) $r['id']) ?>">
                <input type="checkbox" name="ids[]" value="<?= $h((string) $r['id']) ?>" id="<?= $inputId ?>"<?= $done ? ' disabled' : '' ?> aria-label="Cocher pour importer : <?= $h($title) ?>">
                <div class="rqc-item__body">
                    <p class="rqc-card__origin" translate="no"><span class="jobcat__flag" data-flag="<?= $h((string) $r['country']) ?>" aria-hidden="true"><?= $h((string) $r['country']) ?></span><span><?= $h((string) $r['country_label']) ?></span><span>·</span><span><?= $h((string) $r['category_label']) ?></span></p>
                    <label for="<?= $inputId ?>" class="rqc-item__name"><span translate="no"><?= $h($title) ?></span> <span class="rqc-code rq-mono" translate="no"><?= $h((string) $r['code']) ?></span></label>
                    <?php if ($official !== '' && $official !== $r['name']): ?><p class="rqc-item__translated" translate="no"><?= $h((string) $r['name']) ?></p><?php endif; ?>
                    <p class="rqc-card__tags">
                        <span class="rqc-level" data-level="<?= $h((string) $level['key']) ?>" title="<?= $level['estimated'] ? 'Niveau estimé d’après l’intitulé et les prérequis' : 'Niveau donné par le pack' ?>"><b><?= (int) $level['rank'] ?></b> <span translate="no"><?= $h((string) $level['label']) ?></span><?php if ($level['estimated']): ?> <small>estimé</small><?php endif; ?></span>
                        <?php if ($r['issuer'] !== ''): ?><span class="rqc-issuer" translate="no"><?= $h((string) $r['issuer']) ?></span><?php endif; ?>
                        <?php if ($r['academy'] !== null): $course = Ctrl::academyUrls($r['academy']); ?><a class="rqc-academy" href="<?= $h((string) $course['url']) ?>">Formation Academy<span translate="no"> · <?= $h((string) $course['title']) ?></span></a><?php endif; ?>
                    </p>
                    <p class="rqc-item__desc" translate="no"><?= $h((string) $r['description']) ?></p>
                    <div class="rqc-card__foot">
                        <?php if ((int) $r['jobs_count'] > 0): ?><a class="rqc-jobs" href="<?= $h(Ctrl::jobCatalogUrl('', (string) $r['id'])) ?>"><?= (int) $r['jobs_count'] ?> emploi(s) lié(s)</a><?php else: ?><span class="rqc-jobs is-none">Aucun emploi lié</span><?php endif; ?>
                        <?php if ($done): ?><span class="rqc-done">Déjà dans votre référentiel</span> <a class="rqc-open" href="<?= $h(url('back-office/referentiels/qualifications/' . $definitionId)) ?>">Ouvrir sa fiche</a><?php else: ?><button type="submit" name="one" value="<?= $h((string) $r['id']) ?>" class="rq-btn rq-btn--sm">Importer</button><?php endif; ?>
                        <a class="rq-btn rq-btn--sm" href="<?= $h($base . '?' . $query(['fiche' => (string) $r['id']])) ?>" data-rqc-open="<?= $h((string) $r['id']) ?>" aria-haspopup="dialog">Voir la fiche</a>
                    </div>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </form>

    <dialog class="rqc-dialog" data-rqc-dialog aria-label="Fiche de la qualification du catalogue">
        <div class="rqc-dialog__bar"><button type="button" class="rqc-dialog__close" data-rqc-close aria-label="Fermer la fiche">×</button></div>
        <div class="rqc-sheet" data-rqc-sheet tabindex="-1"></div>
    </dialog>
</div>
<script src="<?= $h(asset_url('assets/js/qualification-sheet.js')) ?>" defer></script>
<script src="<?= $h(asset_url('assets/js/qualification-catalog-page.js')) ?>" defer></script>
