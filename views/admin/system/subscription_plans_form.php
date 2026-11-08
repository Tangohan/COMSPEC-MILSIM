<?php
declare(strict_types=1);

use App\Services\Billing\SubscriptionPlanFeaturesCatalog as PlanCatalog;

$row = is_array($subscriptionPlanRow ?? null) ? $subscriptionPlanRow : null;
if ($row === null) {
    echo '<p class="p-8 text-slate-600">Données manquantes.</p>';

    return;
}
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$action = (string) ($subscriptionPlanFormAction ?? '');
$previewAction = (string) ($subscriptionPlanPreviewAction ?? '');
$duplicateAction = (string) ($subscriptionPlanDuplicateAction ?? '');
$csrf = \App\Core\Csrf::token();
$planValues = is_array($subscriptionPlanValues ?? null) ? $subscriptionPlanValues : PlanCatalog::planValues($row);
$values = $planValues['values'];
$defaulted = $planValues['defaulted'];
$unknown = is_array($subscriptionPlanUnknownKeys ?? null) ? $subscriptionPlanUnknownKeys : [];
$subs = is_array($subscriptionPlanSubscribers ?? null) ? $subscriptionPlanSubscribers : [];
$history = is_array($subscriptionPlanHistory ?? null) ? $subscriptionPlanHistory : [];
$preview = is_array($subscriptionPlanPreview ?? null) ? $subscriptionPlanPreview : ['cards' => [], 'isPublic' => true, 'slug' => ''];
$id = (int) ($row['id'] ?? 0);
$name = (string) ($row['name'] ?? '');
$slug = (string) ($row['slug'] ?? '');
$sort = (int) ($row['sort_order'] ?? 0);
$defs = PlanCatalog::definitions();
$groups = PlanCatalog::keysByGroup();
$tabs = ['general' => 'Général et prix'];
foreach ($groups as $g => $keys) {
    if ($keys !== []) {
        $tabs[$g] = PlanCatalog::GROUPS[$g]['label'];
    }
}
$tabs['apercu'] = 'Aperçu client';
$tabs['historique'] = 'Historique';
$tab = (string) ($subscriptionPlanTab ?? 'members');
if (!isset($tabs[$tab])) {
    $tab = 'members';
}
$centsToEuros = static function ($cents): string {
    $cents = is_numeric($cents) ? (int) $cents : 0;
    if ($cents <= 0) {
        return '';
    }

    return $cents % 100 === 0 ? (string) intdiv($cents, 100) : number_format($cents / 100, 2, ',', '');
};
$priceM = $centsToEuros($row['price_monthly_cents'] ?? null);
$priceY = $centsToEuros($row['price_yearly_cents'] ?? null);
$limitsVal = '';
$lj = $row['limits_json'] ?? null;
if (is_string($lj) && trim($lj) !== '') {
    $d = json_decode($lj, true);
    $limitsVal = is_array($d) ? (string) json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $lj;
}
$fmtDate = static function (string $raw): string {
    $t = strtotime($raw);

    return $t ? date('d/m/Y H:i', $t) : $raw;
};
$ok = \App\Core\Session::getFlash('success');
$warn = \App\Core\Session::getFlash('warning');
$err = \App\Core\Session::getFlash('error');
?>
<div class="pa spl">
    <div class="pa__frame">
        <header class="pa-hero">
            <p class="pa-crumb">
                <a href="<?= $h(url('admin')) ?>">Administration du site</a>
                <span aria-hidden="true"> / </span>
                <a href="<?= $h(url('admin/system/subscription-plans')) ?>">Formules d’accès</a>
                <span aria-hidden="true"> / </span>
                <?= $h($name) ?>
            </p>
            <h1 class="pa-hero__title"><?= $h($name) ?></h1>
            <p class="pa-hero__lead">
                <span>Identifiant interne :</span> <code translate="no"><?= $h($slug) ?></code> ·
                <strong><?= count($subs) ?></strong> <span>communauté(s) sur cette formule (formules offertes comprises).</span>
            </p>
            <div class="pa-hero__actions">
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/system/subscription-plans')) ?>">Comparer les formules</a>
                <form method="post" action="<?= $h($duplicateAction) ?>">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                    <button type="submit" class="pa-btn pa-btn--ghost">Dupliquer cette formule</button>
                </form>
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('platform/upgrade')) ?>">Page publique des formules</a>
            </div>
        </header>

        <div class="pa-panel">
            <?php if ($ok): ?>
                <p class="pa-flash pa-flash--ok"><?= $h((string) $ok) ?></p>
            <?php endif; ?>
            <?php if ($warn): ?>
                <p class="pa-flash pa-flash--warn"><?= $h((string) $warn) ?></p>
            <?php endif; ?>
            <?php if ($err): ?>
                <p class="pa-flash pa-flash--err"><?= $h((string) $err) ?></p>
            <?php endif; ?>

            <form id="spl-form" class="spl-form" method="post" action="<?= $h($action) ?>" data-spl-form data-preview-url="<?= $h($previewAction) ?>" novalidate>
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <input type="hidden" name="onglet" value="<?= $h($tab) ?>" data-spl-tab-input>

                <div class="spl-layout">
                    <aside class="spl-side">
                        <label class="spl-search">
                            <span class="spl-sr">Rechercher un paramètre</span>
                            <input type="search" data-spl-search placeholder="Rechercher un paramètre" autocomplete="off">
                        </label>
                        <nav class="spl-tabs" role="tablist" aria-label="Groupes de paramètres" data-spl-tabs>
                            <?php foreach ($tabs as $tk => $tl): ?>
                                <button type="button" role="tab" class="spl-tab" id="spl-tab-<?= $h($tk) ?>" aria-controls="spl-panel-<?= $h($tk) ?>" aria-selected="<?= $tk === $tab ? 'true' : 'false' ?>" data-spl-tab="<?= $h($tk) ?>">
                                    <?= $h($tl) ?>
                                    <?php if (isset($groups[$tk])): ?><span class="spl-tab__count"><?= count($groups[$tk]) ?></span><?php endif; ?>
                                </button>
                            <?php endforeach; ?>
                        </nav>
                    </aside>

                    <div class="spl-panels">
                        <p class="spl-search-empty" data-spl-search-empty hidden>Aucun paramètre ne correspond à la recherche.</p>

                        <section class="spl-panel pa-card" role="tabpanel" id="spl-panel-general" aria-labelledby="spl-tab-general" data-spl-panel="general">
                            <p class="pa-card__kicker">Général et prix</p>
                            <div class="spl-grid2">
                                <div class="pa-field">
                                    <label for="sp_name">Libellé</label>
                                    <input id="sp_name" name="name" type="text" required maxlength="100" value="<?= $h($name) ?>">
                                </div>
                                <div class="pa-field">
                                    <label for="sp_sort">Ordre d’affichage</label>
                                    <input id="sp_sort" name="sort_order" type="number" step="1" value="<?= (int) $sort ?>" class="spl-input">
                                </div>
                                <div class="pa-field">
                                    <label for="sp_price_m">Prix pour 1 mois (€)</label>
                                    <input id="sp_price_m" name="price_monthly" type="text" inputmode="decimal" maxlength="10" value="<?= $h($priceM) ?>" placeholder="Ex. 9,99">
                                </div>
                                <div class="pa-field">
                                    <label for="sp_price_y">Prix pour 1 an (€)</label>
                                    <input id="sp_price_y" name="price_yearly" type="text" inputmode="decimal" maxlength="10" value="<?= $h($priceY) ?>" placeholder="Ex. 99">
                                </div>
                            </div>
                            <p class="pa-hint">Montants demandés sur la page de paiement d’Athena (PayPal). Laissez vide pour ne pas proposer la période ; l’activation se fait dans Admin › Paiements.</p>
                            <details class="spl-advanced"<?= $limitsVal !== '' ? ' open' : '' ?>>
                                <summary>Quotas périodiques (avancé)</summary>
                                <p class="pa-hint">Plafonds remis à zéro chaque mois, semaine ou jour, pour un module non inclus (ex. événements de la formule gratuite). Format JSON ; laissez vide si non utilisé.</p>
                                <textarea id="sp_limits" name="limits_json" rows="7" class="spl-code" spellcheck="false" translate="no"><?= $h($limitsVal) ?></textarea>
                            </details>
                            <?php if ($unknown !== []): ?>
                                <p class="pa-flash pa-flash--warn spl-unknown">
                                    <span>Autres réglages enregistrés dans cette formule, inconnus du catalogue, conservés tels quels :</span>
                                    <code translate="no"><?= $h(implode(', ', $unknown)) ?></code>
                                </p>
                            <?php endif; ?>
                        </section>

                        <?php foreach ($groups as $group => $keys): ?>
                            <?php if ($keys === []) { continue; } ?>
                            <section class="spl-panel pa-card" role="tabpanel" id="spl-panel-<?= $h($group) ?>" aria-labelledby="spl-tab-<?= $h($group) ?>" data-spl-panel="<?= $h($group) ?>">
                                <p class="pa-card__kicker"><?= $h(PlanCatalog::GROUPS[$group]['label']) ?></p>
                                <?php foreach ($keys as $key): ?>
                                    <?php
                                    $def = $defs[$key];
                                    $v = $values[$key];
                                    $fid = 'feat_' . $key;
                                    ?>
                                    <div class="spl-param" data-spl-row data-spl-text="<?= $h(mb_strtolower($def['label'] . ' ' . $def['label_en'] . ' ' . $def['help'] . ' ' . $key)) ?>">
                                        <div class="spl-param__text">
                                            <label class="spl-param__label" for="<?= $h($fid) ?>"><?= $h($def['label']) ?></label>
                                            <p class="spl-param__help"><?= $h($def['help']) ?></p>
                                            <p class="spl-param__meta">
                                                <code translate="no"><?= $h($key) ?></code>
                                                <?php if (!$def['read']): ?>
                                                    <span class="pa-pill pa-pill--amber">Non contrôlé par le site : sans effet aujourd’hui</span>
                                                <?php endif; ?>
                                                <?php if (in_array($key, $defaulted, true) && in_array($key, \App\Services\Billing\SubscriptionPlanFeaturesCatalog::OWNER_DECIDES, true)): ?>
                                                    <span class="pa-pill pa-pill--amber">Pas encore défini : illimité tant que vous ne l’enregistrez pas</span>
                                                <?php elseif (in_array($key, $defaulted, true)): ?>
                                                    <span class="pa-pill pa-pill--slate">Valeur par défaut du palier</span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <div class="spl-param__control">
                                            <?php if ($def['type'] === 'bool'): ?>
                                                <label class="spl-switch">
                                                    <input type="checkbox" role="switch" id="<?= $h($fid) ?>" name="feature_<?= $h($key) ?>" value="1"<?= $v ? ' checked' : '' ?>>
                                                    <span class="spl-switch__track" aria-hidden="true"></span>
                                                    <span class="spl-switch__on">Inclus</span>
                                                    <span class="spl-switch__off">Non inclus</span>
                                                </label>
                                            <?php else: ?>
                                                <?php $unl = (int) $v === 0; ?>
                                                <div class="spl-quota" data-spl-quota>
                                                    <input type="number" class="spl-input spl-quota__num" id="<?= $h($fid) ?>" name="feature_<?= $h($key) ?>" min="<?= (int) $def['min'] ?>" max="<?= (int) $def['max'] ?>" step="1" inputmode="numeric" value="<?= $unl ? '' : (int) $v ?>"<?= $unl ? ' disabled' : ' required' ?> aria-describedby="<?= $h($fid) ?>_unit">
                                                    <span class="spl-quota__unit" id="<?= $h($fid) ?>_unit"><?= $h((string) $def['unit']) ?></span>
                                                    <label class="spl-quota__unl">
                                                        <input type="checkbox" name="feature_<?= $h($key) ?>_unlimited" value="1"<?= $unl ? ' checked' : '' ?> data-spl-unlimited>
                                                        <span>Illimité</span>
                                                    </label>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </section>
                        <?php endforeach; ?>

                        <section class="spl-panel pa-card" role="tabpanel" id="spl-panel-apercu" aria-labelledby="spl-tab-apercu" data-spl-panel="apercu">
                            <p class="pa-card__kicker">Ce que voit le client</p>
                            <p class="pa-hint">Comparatif affiché sur la page des formules, avec les valeurs saisies ci-contre (mis à jour à chaque changement, avant l’enregistrement).</p>
                            <div class="spl-preview" data-spl-preview aria-live="polite">
                                <?php
                                $previewData = $preview;
                                $previewErrors = [];
                                $previewOverages = [];
                                $previewSubscribers = count($subs);
                                require base_path('views/admin/system/partials/subscription_plan_preview.php');
                                ?>
                            </div>
                        </section>

                        <section class="spl-panel pa-card" role="tabpanel" id="spl-panel-historique" aria-labelledby="spl-tab-historique" data-spl-panel="historique">
                            <p class="pa-card__kicker">Historique des modifications</p>
                            <?php if ($history === []): ?>
                                <p class="pa-empty">Aucune modification enregistrée dans le journal d’audit.</p>
                            <?php else: ?>
                                <ol class="spl-history">
                                    <?php foreach ($history as $e): ?>
                                        <li class="spl-history__item">
                                            <p class="spl-history__head">
                                                <strong><?= $h($fmtDate((string) $e['at'])) ?></strong>
                                                <span>·</span>
                                                <span><?= $h((string) $e['actor']) ?></span>
                                                <span>·</span>
                                                <span><?= $h(\App\Services\Audit\AuditActionLabel::toFrench((string) $e['action'])) ?></span>
                                                <a class="spl-history__link" href="<?= $h(url('admin/audit/' . (int) $e['id'])) ?>">Détail et restauration</a>
                                            </p>
                                            <?php if ($e['changes'] === []): ?>
                                                <p class="pa-hint">Enregistrée sans changement.</p>
                                            <?php else: ?>
                                                <ul class="spl-history__changes">
                                                    <?php foreach ($e['changes'] as $c): ?>
                                                        <li>
                                                            <span class="spl-history__label"><?= $h((string) $c['label']) ?></span>
                                                            <span class="spl-history__from"><?= $h((string) $c['from']) ?></span>
                                                            <span aria-hidden="true">→</span>
                                                            <span class="spl-history__to"><?= $h((string) $c['to']) ?></span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php endif; ?>
                        </section>
                    </div>
                </div>

                <div class="spl-savebar">
                    <div class="spl-savebar__impact" data-spl-impact aria-live="polite"></div>
                    <div class="spl-savebar__actions">
                        <a class="pa-btn pa-btn--line" href="<?= $h(url('admin/system/subscription-plans')) ?>">Annuler</a>
                        <button type="submit" class="pa-btn pa-btn--mint">Enregistrer la formule</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="<?= $h(asset_url('assets/js/admin-subscription-plans.js')) ?>" defer></script>
