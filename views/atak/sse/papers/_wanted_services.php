<?php
declare(strict_types=1);
/**
 * Réglages de la notice > onglet « Services » : services demandeurs (et « à informer ») des avis, notices et fiches de vigilance.
 * Liste dense, triable et filtrable ; fiche du service choisi avec l'aperçu de l'en-tête de document qu'il produit ; édition dans
 * une fenêtre à onglets (_service_modal.php). Un service livré se personnalise comme un service créé (surcharge par communauté).
 * Lecture pour qui ouvre les réglages ; écriture réservée au niveau « Gérer » de la section Fichiers et sujets.
 *
 * @var array<string,mixed> $config
 * @var array<string,array<string,mixed>> $serviceList
 * @var array<string,array{fr:string,en:string}> $serviceFamilies
 * @var array<string,string> $servicePalette
 * @var array<string,int> $serviceUse
 * @var bool $serviceStoreReady
 * @var bool $serviceEmblemReady
 * @var array<string,array<string,mixed>> $serviceEmblemList
 * @var array<string,mixed> $serviceState
 */
use App\Core\Csrf;
use App\Services\Sse\SseServiceEmblems;
use App\Support\SseUsClassification;
use App\Support\SseWantedServices;

require_once __DIR__ . '/_service_emblem.php';

$h = $h ?? static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$lang = (string) ($lang ?? 'fr') === 'en' ? 'en' : 'fr';
$serviceList = is_array($serviceList ?? null) ? $serviceList : [];
$serviceFamilies = is_array($serviceFamilies ?? null) ? $serviceFamilies : [];
$servicePalette = is_array($servicePalette ?? null) ? $servicePalette : [];
$serviceUse = is_array($serviceUse ?? null) ? $serviceUse : [];
$serviceState = is_array($serviceState ?? null) ? $serviceState : [];
$serviceEmblemReady = !empty($serviceEmblemReady);
$serviceEmblemList = is_array($serviceEmblemList ?? null) ? $serviceEmblemList : [];
$serviceStoreReady = !empty($serviceStoreReady);
$canAdmin = isset($rights) && is_object($rights) && $rights->canAdmin();
$defaultCode = SseWantedServices::defaultCode($config);
$svcAction = url('atak/sse/avis/reglages/services');
$csrf = Csrf::field();
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$stateErrors = is_array($serviceState['errors'] ?? null) ? $serviceState['errors'] : [];
$report = is_array($serviceState['report'] ?? null) ? $serviceState['report'] : null;
$importOpen = ($serviceState['op'] ?? '') === 'import' && ($stateErrors !== [] || $report !== null);
$active = count(array_filter($serviceList, static fn (array $s): bool => $s['enabled'] === true));
$roleNames = [];
foreach (SseWantedServices::ROLES as $rk => $rl) {
    $roleNames[$rk] = $rl[$lang === 'en' ? 1 : 0];
}
$position = 0;
$rowData = static function (array $s, int $pos) use ($lang, $roleNames, $servicePalette, $serviceFamilies, $serviceUse, $serviceEmblemList, $defaultCode): array {
    $code = (string) $s['code'];
    $emblem = $serviceEmblemList[$code] ?? null;
    $data = array_intersect_key($s, array_flip(array_merge(['code', 'source', 'enabled', 'overridden', 'print_address'], SseWantedServices::OVERRIDE_FIELDS)));

    return $data + [
        'hex' => $servicePalette[$s['color']] ?? '#1F3A5F',
        'family_label' => $serviceFamilies[$s['family']][$lang] ?? $s['family'],
        'max_label' => SseUsClassification::abbr((string) $s['max']),
        'roles_label' => implode(', ', array_map(static fn (string $r): string => $roleNames[$r] ?? $r, $s['roles'])),
        'use' => (int) ($serviceUse[$code] ?? 0),
        'pos' => $pos,
        'is_default' => $code === $defaultCode,
        'image' => $emblem !== null ? [
            'url' => SseServiceEmblems::url($code, false, (int) $emblem['version']), 'thumb' => SseServiceEmblems::url($code, true, (int) $emblem['version']),
            'by' => (string) $emblem['confirmed_by_label'], 'at' => (string) ($emblem['confirmed_at'] ?? ''),
        ] : null,
        'update_url' => url('atak/sse/avis/services/' . $code . '/modifier'),
        'reset_url' => url('atak/sse/avis/services/' . $code . '/retablir'),
        'emblem_url' => url('atak/sse/avis/services/' . $code . '/embleme'),
    ];
};
$sortIcon = '<svg class="svc-sort__i" viewBox="0 0 12 12" width="12" height="12" aria-hidden="true" focusable="false"><path d="M6 1.5l3.2 3.6H2.8zM6 10.5L2.8 6.9h6.4z" fill="currentColor"/></svg>';
?>
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/sse-services.css')) ?>">
<div class="wc-settings-pane ws-admin svc" id="wc-pane-services" data-wc-pane="services" role="tabpanel" aria-labelledby="wc-stab-services" data-svc-admin data-svc-lang="<?= $h($lang) ?>" data-svc-can="<?= $canAdmin ? '1' : '0' ?>" data-svc-shapes="<?= $h(json_encode(sse_service_emblem_shapes(), $jsonFlags)) ?>">
    <div>
        <h2 class="ws-admin__title">Services demandeurs</h2>
        <p class="ws-admin__desc">Les services qui demandent un avis ou qu’un document dit d’informer : nom, emblème, description et coordonnées se règlent ici pour votre communauté.</p>
    </div>
    <?php if (!$canAdmin): ?>
        <div class="dos-note dos-note--info">Vous consultez les services. Les créer, les personnaliser ou les masquer demande le niveau « Gérer » de la section Fichiers et sujets.</div>
    <?php endif; ?>
    <?php if (!$serviceStoreReady): ?>
        <div class="dos-note dos-note--info">L’étape de base de données « processus des avis » n’est pas encore passée : le nombre d’avis par service n’est pas connu, et un service créé est désactivé plutôt que supprimé.</div>
    <?php endif; ?>
    <?php if (!$serviceEmblemReady): ?>
        <div class="dos-note dos-note--info" data-svc-noemblem><?= $h(SseServiceEmblems::NOT_READY) ?></div>
    <?php endif; ?>
    <?php if (!empty($serviceState['saved'])): ?>
        <div class="dos-note dos-note--info"><?= $h($serviceState['saved']) ?></div>
    <?php endif; ?>
    <?php if ($stateErrors !== [] && !$importOpen): ?>
        <ul class="ws-errors" role="alert"><?php foreach ($stateErrors as $e): ?><li><?= $h($e) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>

    <div class="svc-tools" role="search">
        <div class="svc-tools__q"><label class="sr-only" for="svc-q">Rechercher un service</label><input id="svc-q" type="search" placeholder="Rechercher un nom, un sigle ou un code" autocomplete="off" data-svc-q></div>
        <div class="svc-tools__sel"><label class="sr-only" for="svc-fam">Famille</label>
            <select id="svc-fam" data-svc-fam><option value="">Toutes les familles</option>
                <?php foreach ($serviceFamilies as $k => $f): ?><option value="<?= $h($k) ?>"><?= $h($f[$lang]) ?></option><?php endforeach; ?>
            </select></div>
        <div class="svc-tools__sel"><label class="sr-only" for="svc-state">État</label>
            <select id="svc-state" data-svc-state><option value="">Tous les états</option><option value="on">Actifs</option><option value="off">Masqués</option><option value="custom">Créés par la communauté</option><option value="changed">Personnalisés</option></select></div>
        <?php if ($canAdmin): ?>
            <div class="svc-tools__acts">
                <a class="btn btn--ghost ws-btn" href="<?= $h(url('atak/sse/avis/reglages/services/export')) ?>">Exporter</a>
                <button class="btn btn--ghost ws-btn" type="button" data-svc-open="#svc-import">Importer</button>
                <button class="btn ws-btn" type="button" data-svc-new>Nouveau service</button>
            </div>
        <?php endif; ?>
    </div>

    <div class="svc-layout">
        <div class="svc-list">
            <p class="svc-count" data-svc-count aria-live="polite" data-total="<?= count($serviceList) ?>"><?= count($serviceList) ?> services, <?= $active ?> actifs</p>
            <div class="svc-table-wrap">
                <table class="svc-table" data-svc-table>
                    <caption class="sr-only">Services demandeurs de la communauté</caption>
                    <thead><tr>
                        <th scope="col" class="svc-col-name" aria-sort="none"><button type="button" class="svc-sort" data-svc-sort="name">Service <?= $sortIcon ?></button></th>
                        <th scope="col" class="svc-col-ac" aria-sort="none"><button type="button" class="svc-sort" data-svc-sort="ac">Sigle <?= $sortIcon ?></button></th>
                        <th scope="col" class="svc-col-fam" aria-sort="none"><button type="button" class="svc-sort" data-svc-sort="family">Famille <?= $sortIcon ?></button></th>
                        <th scope="col" class="svc-col-use" aria-sort="none"><button type="button" class="svc-sort" data-svc-sort="use">Avis <?= $sortIcon ?></button></th>
                        <th scope="col" class="svc-col-state" aria-sort="none"><button type="button" class="svc-sort" data-svc-sort="state">État <?= $sortIcon ?></button></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($serviceList as $code => $s):
                        $position++;
                        $data = $rowData($s, $position);
                        $name = (string) ($lang === 'en' ? $s['en'] : $s['fr']);
                        $search = mb_strtolower($s['fr'] . ' ' . $s['en'] . ' ' . $s['ac'] . ' ' . $code);
                        $used = (int) $data['use'];
                        ?>
                        <tr class="svc-row<?= $s['enabled'] ? '' : ' is-off' ?>" data-svc-row data-code="<?= $h($code) ?>" data-family="<?= $h($s['family']) ?>" data-source="<?= $h($s['source']) ?>" data-enabled="<?= $s['enabled'] ? '1' : '0' ?>" data-changed="<?= !empty($s['overridden']) ? '1' : '0' ?>" data-search="<?= $h($search) ?>" data-svc-json="<?= $h(json_encode($data, $jsonFlags)) ?>">
                            <td class="svc-col-name">
                                <button type="button" class="svc-pick" data-svc-pick aria-label="Ouvrir la fiche du service <?= $h($name) ?>">
                                    <span class="svc-pick__mark" data-svc-mark><?php if ($data['image'] !== null): ?><img src="<?= $h($data['image']['thumb']) ?>" alt="" width="40" height="40" loading="lazy" decoding="async"><?php else: ?><?= sse_service_emblem_svg((string) $s['emblem'], (string) $data['hex'], (string) ($s['ac'] !== '' ? $s['ac'] : $name), 40) ?><?php endif; ?></span>
                                    <span class="svc-pick__text">
                                        <strong translate="no"><?= $h($name) ?></strong>
                                        <span class="svc-pick__meta"><span class="svc-mono" translate="no"><?= $h($code) ?></span><?php if ($s['ac'] !== ''): ?><span class="svc-mono svc-only-sm" translate="no"><?= $h($s['ac']) ?></span><?php endif; ?><span class="svc-pick__fam"><?= $h($data['family_label']) ?></span><?php if ($code === $defaultCode): ?><span class="svc-tag svc-tag--ok">Par défaut</span><?php endif; ?><?php if ($s['source'] === 'custom'): ?><span class="svc-tag svc-tag--info">Communauté</span><?php elseif (!empty($s['overridden'])): ?><span class="svc-tag svc-tag--info">Personnalisé</span><?php endif; ?></span>
                                    </span>
                                </button>
                            </td>
                            <td class="svc-col-ac" data-label="Sigle"><span class="svc-mono" translate="no"><?= $h($s['ac'] !== '' ? $s['ac'] : '—') ?></span></td>
                            <td class="svc-col-fam" data-label="Famille"><?= $h($data['family_label']) ?><?php if ($used > 0): ?><span class="svc-only-sm"> · <?= $used ?> avis</span><?php endif; ?></td>
                            <td class="svc-col-use" data-label="Avis"><?= $used > 0 ? $used : '—' ?></td>
                            <td class="svc-col-state" data-label="État"><span class="svc-state<?= $s['enabled'] ? ' is-on' : '' ?>"><?= $s['enabled'] ? 'Actif' : 'Masqué' ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="ws-empty" data-svc-none hidden>Aucun service ne correspond à ces filtres.</p>
            </div>
        </div>

        <aside class="svc-card" data-svc-card aria-label="Fiche du service" hidden>
            <div class="svc-card__head">
                <div class="svc-card__titles">
                    <h3 class="svc-card__name" data-b="name" translate="no"></h3>
                    <p class="svc-card__desc" data-b="desc"></p>
                </div>
                <?php if ($canAdmin): ?><button class="btn ws-btn" type="button" data-svc-edit>Modifier</button><?php else: ?><button class="btn btn--ghost ws-btn" type="button" data-svc-edit>Voir la fiche</button><?php endif; ?>
            </div>
            <div class="svc-card__block">
                <h4 class="svc-card__sub">Aperçu de l’en-tête</h4>
                <p class="svc-card__hint">Ce que portent les documents émis par ce service.</p>
                <div class="svc-doc" data-svc-doc></div>
            </div>
            <dl class="svc-facts">
                <div><dt>Famille</dt><dd data-b="family_label"></dd></div>
                <div><dt>Origine</dt><dd data-b="origin"></dd></div>
                <div><dt>Rôle</dt><dd data-b="roles_label"></dd></div>
                <div><dt>Classification maximale</dt><dd data-b="max_label" translate="no"></dd></div>
                <div><dt>Avis qui l’utilisent</dt><dd data-b="use"></dd></div>
                <div><dt>Emblème</dt><dd data-b="emblem_state"></dd></div>
            </dl>
            <?php if ($canAdmin): ?>
                <div class="svc-card__acts">
                    <form method="post" action="<?= $h($svcAction) ?>"><?= $csrf ?><input type="hidden" name="op" value="toggle"><input type="hidden" name="code" data-b-val="code"><input type="hidden" name="enabled" data-b-val="toggle_to"><button class="btn btn--ghost ws-btn" type="submit" data-b="toggle_label"></button></form>
                    <form method="post" action="<?= $h($svcAction) ?>" data-b-show="can_default"><?= $csrf ?><input type="hidden" name="op" value="default"><input type="hidden" name="code" data-b-val="code"><button class="btn btn--ghost ws-btn" type="submit">Définir par défaut</button></form>
                    <form method="post" action="<?= $h($svcAction) ?>"><?= $csrf ?><input type="hidden" name="op" value="duplicate"><input type="hidden" name="code" data-b-val="code"><button class="btn btn--ghost ws-btn" type="submit">Dupliquer</button></form>
                </div>
            <?php endif; ?>
        </aside>
    </div>

    <div hidden data-svc-words>
        <span data-k="count_all"><?= count($serviceList) ?> services, <?= $active ?> actifs</span>
        <span data-k="shown">affichés sur</span>
        <span data-k="origin_catalogue">Livré</span>
        <span data-k="origin_changed">Livré, personnalisé par votre communauté</span>
        <span data-k="origin_custom">Créé par votre communauté</span>
        <span data-k="no_desc">Aucune description pour l’instant.</span>
        <span data-k="hide">Masquer</span>
        <span data-k="show">Rendre actif</span>
        <span data-k="emblem_image">Image téléversée</span>
        <span data-k="emblem_generic">Générique : forme, couleur et sigle</span>
        <span data-k="inform">Service à informer</span>
        <span data-k="no_channel">Aucun canal affiché</span>
        <span data-k="title_create">Nouveau service</span>
        <span data-k="title_update">Modifier le service</span>
        <span data-k="title_read">Fiche du service</span>
        <span data-k="save_create">Créer le service</span>
        <span data-k="save_update">Enregistrer</span>
        <span data-k="saving">Enregistrement…</span>
        <span data-k="sending">Téléversement…</span>
        <span data-k="net_error">La demande n’a pas abouti : vérifiez la connexion puis recommencez.</span>
        <span data-k="need_file">Choisissez une image à téléverser.</span>
        <span data-k="need_confirm">Cochez la confirmation avant de téléverser.</span>
        <span data-k="too_big">Image refusée : 1 Mo au plus.</span>
        <span data-k="name_default">Nom du service</span>
        <span data-k="uploaded_by">Téléversé par</span>
        <span data-k="none">Aucun</span>
    </div>
</div>

<?php require __DIR__ . '/_service_modal.php'; ?>

<?php if ($canAdmin): ?>
    <dialog class="ws-dialog" id="svc-import" aria-labelledby="svc-import-title" <?= $importOpen ? 'data-svc-autoopen' : '' ?>>
        <form method="post" action="<?= $h($svcAction) ?>" enctype="multipart/form-data">
            <?= $csrf ?>
            <input type="hidden" name="op" value="import">
            <div class="ws-dialog__head">
                <div>
                    <h2 id="svc-import-title">Importer des services</h2>
                    <p>Un export JSON d’une autre communauté. Chaque service passe le même contrôle qu’une création : un nom réel est refusé.</p>
                </div>
                <button type="button" class="ws-iconbtn" data-svc-close aria-label="Fermer"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg></button>
            </div>
            <div class="ws-dialog__body">
                <?php if ($importOpen && $stateErrors !== []): ?><ul class="ws-errors" role="alert"><?php foreach ($stateErrors as $e): ?><li><?= $h($e) ?></li><?php endforeach; ?></ul><?php endif; ?>
                <?php if ($report !== null): ?>
                    <ul class="ws-report" aria-label="Résultat de l’import">
                        <li><strong><?= (int) $report['added'] ?> ajouté(s)</strong>, <?= (int) $report['skipped'] ?> déjà présent(s), <?= count($report['rejected']) ?> refusé(s).</li>
                        <?php foreach ($report['rejected'] as $rej): ?><li><strong translate="no"><?= $h($rej['name']) ?></strong> : <?= $h(implode(' ', $rej['reasons'])) ?></li><?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <div><label for="svc-import-file">Fichier d’export (.json)</label><input id="svc-import-file" type="file" name="import_file" accept="application/json,.json"></div>
                <div><label for="svc-import-json">Ou collez son contenu</label><textarea id="svc-import-json" name="import_json" rows="8" spellcheck="false" translate="no"></textarea><p class="ws-hint">200 Ko au plus. Les services déjà présents (même nom) sont ignorés.</p></div>
            </div>
            <div class="ws-dialog__foot">
                <button class="btn btn--ghost" type="button" data-svc-close>Annuler</button>
                <button class="btn" type="submit">Importer</button>
            </div>
        </form>
    </dialog>
<?php endif; ?>
<script src="<?= $h(asset_url('assets/js/sse-services.js')) ?>" defer></script>
