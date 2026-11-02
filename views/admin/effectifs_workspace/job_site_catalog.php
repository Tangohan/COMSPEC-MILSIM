<?php
declare(strict_types=1);
/**
 * Onglet « Fonctions du site » (Effectifs › Emplois & fonctions) : le référentiel fourni par Athena, que la communauté
 * propose ou masque. Masquer ne retire rien aux membres qui occupent la fonction.
 * Données : EffectifsWorkspaceController::siteJobCatalogData ; écriture : POST fonctions/masquage (slugs[], action).
 * Inclus par fonctions.php : $base, $csrf et $canManage y sont définis.
 * Styles : assets/css/job-site-catalog.css (kit effectifs-kit.css) ; script : assets/js/job-site-catalog.js.
 * Les nombres changés par le script sont dans leurs propres balises : les textes restent traduits par le serveur.
 */
use App\Support\JobRoleIconLibrary;

require_once __DIR__ . '/partials/effk.php';

$fsE = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$fsGroups = is_array($siteJobGroups ?? null) ? $siteJobGroups : [];
$fsReady = !empty($jobEnrichmentReady);
$fsManage = !empty($canManage) && $fsReady;
$fsInitial = !empty($jobShowHidden) ? 'masquees' : 'toutes';

$fsDomains = [];
$fsTotal = $fsHidden = $fsHeld = $fsUnused = 0;
$fsIndex = 0;
foreach ($fsGroups as $fsName => $fsItems) {
    $fsD = ['id' => 'd' . (++$fsIndex), 'name' => (string) $fsName, 'items' => [], 'total' => 0, 'hidden' => 0, 'held' => 0, 'icon' => null,
        'color' => effk_unit_color($fsIndex)];
    foreach ($fsItems as $fsItem) {
        $fsD['items'][] = $fsItem;
        $fsD['total']++;
        $fsD['icon'] ??= $fsItem['icon'] ?? null;
        if (!empty($fsItem['hidden'])) $fsD['hidden']++;
        if ((int) $fsItem['holders'] > 0) $fsD['held']++;
    }
    $fsTotal += $fsD['total'];
    $fsHidden += $fsD['hidden'];
    $fsHeld += $fsD['held'];
    $fsUnused += $fsD['total'] - $fsD['held'];
    $fsDomains[] = $fsD;
}
$fsFilters = ['toutes' => ['Toutes', $fsTotal], 'visibles' => ['Visibles', $fsTotal - $fsHidden], 'masquees' => ['Masquées', $fsHidden],
    'occupees' => ['Occupées', $fsHeld], 'inutilisees' => ['Jamais utilisées', $fsUnused]];
$fsDomainIcon = static fn (?string $icon, string $class): string => JobRoleIconLibrary::has($icon) ? JobRoleIconLibrary::svg($icon, $class) : effk_icon('tree', $class);
$fsEye = static fn (bool $off): string => '<svg class="effk-i" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
    . ($off ? '<path d="M3 3l18 18M10.6 5.1A10 10 0 0 1 12 5c5 0 9 4.5 10 7a12 12 0 0 1-2.6 3.6M6.6 6.6C4.3 8 2.7 10.2 2 12c1 2.5 5 7 10 7 1.8 0 3.4-.5 4.8-1.3M9.9 9.9a3 3 0 0 0 4.2 4.2"/>'
        : '<path d="M2 12c1-2.5 5-7 10-7s9 4.5 10 7c-1 2.5-5 7-10 7S3 14.5 2 12z"/><circle cx="12" cy="12" r="3"/>') . '</svg>';
$fsCard = 0;
?>
<section class="effk fs<?= $fsManage ? ' fs--manage' : '' ?>" data-fs data-fs-initial="<?= $fsE($fsInitial) ?>" aria-labelledby="fs-title">
<?= effk_page_head('Référentiel fourni par Athena', 'Fonctions du site',
    'Choisissez les fonctions du site proposées à votre communauté. Une fonction masquée disparaît des choix de fonction ; rien n’est supprimé et les membres qui l’occupent la gardent sur leur fiche et dans les effectifs.',
    '', 'fs-title') ?>

<?php if (!$fsReady): ?>
<p class="effk-note is-warn" role="status"><?= effk_icon('alert') ?><span>Le masquage des fonctions arrive avec la prochaine mise à jour du site.</span></p>
<?php elseif (empty($canManage)): ?>
<p class="effk-note is-info"><?= effk_icon('user') ?><span>Consultation seule : seuls les responsables habilités choisissent les fonctions proposées.</span></p>
<?php endif; ?>

<?php if ($fsDomains === []): ?>
<?= effk_empty('Aucune fonction du site', 'Le référentiel du site est vide pour le moment.') ?>
<?php else: ?>
<div class="effk-stats fs-stats" role="list" aria-label="Fonctions du site en chiffres">
    <button type="button" class="effk-stat is-link" role="listitem" data-fs-set="toutes"><span class="effk-k">Fonctions du site</span><b data-fs-stat="toutes"><?= $fsTotal ?></b><small>fournies par Athena</small></button>
    <button type="button" class="effk-stat is-link" role="listitem" data-fs-set="visibles"><span class="effk-k">Visibles</span><b class="is-ok" data-fs-stat="visibles"><?= $fsTotal - $fsHidden ?></b><small>proposées à la communauté</small></button>
    <button type="button" class="effk-stat is-link" role="listitem" data-fs-set="masquees"><span class="effk-k">Masquées</span><b class="is-warn" data-fs-stat="masquees"><?= $fsHidden ?></b><small>retirées des choix</small></button>
    <button type="button" class="effk-stat is-link" role="listitem" data-fs-set="occupees"><span class="effk-k">Occupées par vos membres</span><b data-fs-stat="occupees"><?= $fsHeld ?></b><small>au moins un titulaire</small></button>
</div>

<form method="post" action="<?= $fsE($base . '/masquage') ?>" class="fs-lay" data-fs-form>
    <input type="hidden" name="_csrf_token" value="<?= $fsE($csrf) ?>">
    <?php if ($fsInitial === 'masquees'): ?><input type="hidden" name="masquees" value="1"><?php endif; ?>
    <span id="fs-l-pick" hidden>Sélectionner</span><span id="fs-l-switch" hidden>Proposée à la communauté :</span>

    <nav class="effk-panel fs-rail" aria-label="Domaines">
        <p class="effk-k fs-rail__h">Domaines</p>
        <ul class="fs-rail__list">
            <li><a class="fs-rail__item is-on" href="#fs-title" data-fs-domain="" aria-current="true"><?= effk_icon('tree', 'fs-rail__i') ?><span class="fs-rail__name">Tous les domaines</span><span class="fs-rail__n"><b data-fs-dn=""><?= $fsTotal ?></b></span></a></li>
            <?php foreach ($fsDomains as $fsD): ?>
            <li><a class="fs-rail__item" href="#fs-g-<?= $fsE($fsD['id']) ?>" data-fs-domain="<?= $fsE($fsD['id']) ?>" style="--fs-c:<?= $fsE($fsD['color']) ?>"><?= $fsDomainIcon($fsD['icon'], 'fs-rail__i') ?><span class="fs-rail__name"><?= $fsE($fsD['name']) ?></span><span class="fs-rail__n"><b data-fs-dn="<?= $fsE($fsD['id']) ?>"><?= $fsD['total'] ?></b><small class="fs-rail__hid<?= $fsD['hidden'] > 0 ? '' : ' is-zero' ?>" data-fs-dh-wrap="<?= $fsE($fsD['id']) ?>"><span data-fs-dh="<?= $fsE($fsD['id']) ?>"><?= $fsD['hidden'] ?></span> <span>masquée(s)</span></small></span></a></li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <div class="fs-main">
        <div class="effk-bar fs-tools" role="search">
            <label class="effk-search effk-search--wide fs-search"><?= effk_icon('search') ?><input type="search" data-fs-q placeholder="Nom, abrégé ou domaine…" aria-label="Rechercher une fonction du site" autocomplete="off"></label>
            <div class="effk-seg fs-seg" role="group" aria-label="Afficher">
                <?php foreach ($fsFilters as $fsKey => [$fsLabel, $fsN]): ?>
                <button type="button" data-fs-filter="<?= $fsE($fsKey) ?>" aria-pressed="<?= $fsKey === 'toutes' ? 'true' : 'false' ?>" class="<?= $fsKey === 'toutes' ? 'is-on' : '' ?>"><span><?= $fsE($fsLabel) ?></span> <span class="fs-n" data-fs-fn="<?= $fsE($fsKey) ?>"><?= $fsN ?></span></button>
                <?php endforeach; ?>
            </div>
        </div>
        <p class="effk-count fs-count" aria-live="polite"><b data-fs-shown><?= $fsTotal ?></b> <span>fonction(s) affichée(s)</span><span class="fs-count__dom" data-fs-dom-label hidden> · <span data-fs-dom-name></span></span></p>

        <?php foreach ($fsDomains as $fsD): ?>
        <section class="fs-group" id="fs-g-<?= $fsE($fsD['id']) ?>" data-fs-group="<?= $fsE($fsD['id']) ?>" style="--fs-c:<?= $fsE($fsD['color']) ?>" aria-labelledby="fs-gt-<?= $fsE($fsD['id']) ?>">
            <header class="fs-group__h">
                <span class="fs-group__i"><?= $fsDomainIcon($fsD['icon'], 'fs-group__svg') ?></span>
                <h3 id="fs-gt-<?= $fsE($fsD['id']) ?>" data-fs-gname><?= $fsE($fsD['name']) ?></h3>
                <small class="fs-group__n"><b data-fs-gn="<?= $fsE($fsD['id']) ?>"><?= $fsD['total'] ?></b> <span>fonction(s)</span> · <b data-fs-gh="<?= $fsE($fsD['id']) ?>"><?= $fsD['hidden'] ?></b> <span>masquée(s)</span></small>
                <?php if ($fsManage): ?>
                <span class="fs-group__acts">
                    <label class="effk-check"><input type="checkbox" data-fs-pick-group="<?= $fsE($fsD['id']) ?>"><span>Tout sélectionner</span></label>
                    <button type="button" class="effk-btn is-sm" data-fs-hide-domain="<?= $fsE($fsD['id']) ?>"><?= $fsEye(true) ?><span>Tout masquer dans ce domaine</span></button>
                </span>
                <?php endif; ?>
            </header>
            <ul class="fs-grid">
            <?php foreach ($fsD['items'] as $fsItem):
                $fsCard++;
                $fsHid = !empty($fsItem['hidden']);
                $fsHolders = (int) $fsItem['holders'];
                $fsAbbr = trim((string) ($fsItem['abbreviation'] ?? ''));
                $fsDesc = trim((string) $fsItem['description']);
                $fsSub = trim((string) $fsItem['subcategory']);
                ?>
                <li class="fs-card<?= $fsHid ? ' is-hidden' : '' ?>" data-fs-card data-slug="<?= $fsE($fsItem['slug']) ?>" data-domain="<?= $fsE($fsD['id']) ?>" data-holders="<?= $fsHolders ?>" data-hidden="<?= $fsHid ? '1' : '0' ?>">
                    <?php if ($fsManage): ?><input class="fs-card__pick" type="checkbox" name="slugs[]" value="<?= $fsE($fsItem['slug']) ?>" data-fs-pick aria-labelledby="fs-l-pick fs-n-<?= $fsCard ?>"><?php endif; ?>
                    <span class="fs-card__i" aria-hidden="true"><?= $fsDomainIcon($fsItem['icon'] ?? $fsD['icon'], 'fs-card__svg') ?></span>
                    <div class="fs-card__body">
                        <h4 class="fs-card__name"><span id="fs-n-<?= $fsCard ?>" data-fs-name<?= $fsDesc !== '' ? ' title="' . $fsE($fsDesc) . '"' : '' ?>><?= $fsE($fsItem['name']) ?></span><?php if ($fsAbbr !== ''): ?> <abbr class="fs-abbr" translate="no" data-fs-abbr><?= $fsE($fsAbbr) ?></abbr><?php endif; ?></h4>
                        <?php if ($fsSub !== ''): ?><p class="fs-card__sub" data-fs-sub><?= $fsE($fsSub) ?></p><?php endif; ?>
                        <p class="fs-card__meta">
                            <?php if ($fsHolders > 0): ?><span class="fs-held"><?= effk_icon('user') ?><b><?= $fsHolders ?></b> <span>membre(s)</span></span>
                            <?php else: ?><span class="fs-unused">Jamais utilisée</span><?php endif; ?>
                            <?php if ((int) $fsItem['role_id'] > 0): ?><a class="fs-card__link" data-jobs-nav href="<?= $fsE($base . '?' . http_build_query(['emploi' => (int) $fsItem['role_id']] + ($fsHid ? ['masquees' => 1] : []))) ?>">Ouvrir la fiche</a><?php endif; ?>
                        </p>
                        <?php if ($fsHolders > 0): ?><p class="fs-card__warn" data-fs-warn<?= $fsHid ? '' : ' hidden' ?>><?= effk_icon('alert') ?><span><span>Occupée par</span> <b><?= $fsHolders ?></b> <span>membre(s) : elle reste sur leur fiche.</span></span></p><?php endif; ?>
                        <?php if ($fsDesc !== ''): ?><details class="fs-card__more"><summary>Description</summary><p><?= $fsE($fsDesc) ?></p></details><?php endif; ?>
                    </div>
                    <div class="fs-card__state">
                        <?php if ($fsManage): ?>
                        <button type="button" class="fs-switch" role="switch" aria-checked="<?= $fsHid ? 'false' : 'true' ?>" data-fs-switch aria-labelledby="fs-l-switch fs-n-<?= $fsCard ?>">
                            <span class="fs-switch__track" aria-hidden="true"><span class="fs-switch__thumb"></span></span>
                            <span class="fs-switch__on" aria-hidden="true">Visible</span><span class="fs-switch__off" aria-hidden="true">Masquée</span>
                        </button>
                        <?php else: ?>
                        <span class="fs-state"><span class="fs-switch__on">Visible</span><span class="fs-switch__off">Masquée</span></span>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
            </ul>
        </section>
        <?php endforeach; ?>

        <div class="effk-empty fs-empty" data-fs-empty hidden><b>Aucune fonction ne correspond</b><span>Modifiez la recherche, le domaine ou le filtre.</span><button type="button" class="effk-btn is-sm" data-fs-reset>Tout afficher</button></div>

        <?php if ($fsManage): ?>
        <div class="fs-bar" data-fs-bar>
            <p class="fs-bar__n"><b data-fs-sel>0</b> <span>sélectionnée(s)</span></p>
            <button type="submit" class="effk-btn is-dark" name="action" value="hide" data-fs-bulk="hide"><?= $fsEye(true) ?><span>Masquer</span> <span class="fs-n" data-fs-bn="hide">0</span></button>
            <button type="submit" class="effk-btn" name="action" value="show" data-fs-bulk="show"><?= $fsEye(false) ?><span>Réafficher</span> <span class="fs-n" data-fs-bn="show">0</span></button>
            <button type="button" class="effk-btn is-sm fs-bar__clear" data-fs-clear>Désélectionner</button>
        </div>
        <?php endif; ?>
    </div>
</form>

<?php if ($fsManage): ?>
<dialog class="effk-md fs-dlg" data-fs-dialog aria-labelledby="fs-dlg-title">
    <div class="effk-md__h"><div><span class="effk-k">Fonctions du site</span><h2 id="fs-dlg-title"><span data-fs-dlg-mode="hide">Masquer ces fonctions ?</span><span data-fs-dlg-mode="domain" hidden>Masquer tout le domaine ?</span></h2></div>
        <button type="button" class="effk-md__x" data-fs-dlg-cancel aria-label="Fermer"><?= effk_icon('x') ?></button></div>
    <div class="effk-md__b">
        <p><b data-fs-dlg-n>0</b> <span>fonction(s) ne seront plus proposées à la communauté.</span> <span data-fs-dlg-mode="domain" hidden>Domaine :</span> <strong data-fs-dlg-mode="domain" data-fs-dlg-domain hidden></strong></p>
        <div class="effk-note is-warn fs-dlg__held" data-fs-dlg-held hidden><?= effk_icon('alert') ?><div><p><span>Occupées par vos membres :</span> <b data-fs-dlg-held-n>0</b>. <span>Elles restent sur la fiche des membres qui les occupent.</span></p><ul class="fs-dlg__list" data-fs-dlg-list></ul></div></div>
        <p class="effk-muted">Vous pourrez les réafficher à tout moment depuis cet onglet.</p>
    </div>
    <div class="effk-md__f">
        <button type="button" class="effk-md__btn" data-fs-dlg-cancel>Annuler</button>
        <button type="button" class="effk-md__btn is-dark" data-fs-dlg-ok>Masquer</button>
    </div>
</dialog>
<div class="fs-toast" data-fs-toast role="status" aria-live="polite" hidden><span data-fs-toast-text></span><button type="button" class="effk-btn is-sm" data-fs-undo hidden>Annuler</button></div>
<?php endif; ?>
<?php endif; ?>
</section>
