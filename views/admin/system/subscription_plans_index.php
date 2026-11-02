<?php
declare(strict_types=1);

use App\Services\Billing\SubscriptionPlanFeaturesCatalog as PlanCatalog;

$rows = is_array($subscriptionPlansRows ?? null) ? $subscriptionPlansRows : [];
$values = is_array($subscriptionPlansValues ?? null) ? $subscriptionPlansValues : [];
$subscribers = is_array($subscriptionPlansSubscribers ?? null) ? $subscriptionPlansSubscribers : [];
$search = (string) ($subscriptionPlansSearch ?? '');
$defs = PlanCatalog::definitions();
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$ok = \App\Core\Session::getFlash('success');
$err = \App\Core\Session::getFlash('error');
$csrf = \App\Core\Csrf::token();
?>
<div class="pa spl">
    <div class="pa__frame">
        <header class="pa-hero">
            <p class="pa-crumb">
                <a href="<?= $h(url('admin')) ?>">Administration du site</a>
                <span aria-hidden="true"> / </span>
                Formules d’accès
            </p>
            <h1 class="pa-hero__title">Formules d’accès</h1>
            <p class="pa-hero__lead">
                Comparez les formules paramètre par paramètre, puis ouvrez une formule pour la modifier. Un quota « Illimité » n’a pas de plafond.
                L’identifiant interne d’une formule ne change pas, pour ne pas désynchroniser les communautés.
            </p>
            <div class="pa-hero__actions">
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/formules-offertes')) ?>">Formules offertes</a>
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/paiements')) ?>">Paiements</a>
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('platform/upgrade')) ?>">Page publique des formules</a>
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/audit') . '?entity_type=subscription_plan') ?>">Journal d’audit</a>
            </div>
        </header>

        <div class="pa-panel">
            <?php if ($ok): ?>
                <p class="pa-flash pa-flash--ok"><?= $h((string) $ok) ?></p>
            <?php endif; ?>
            <?php if ($err): ?>
                <p class="pa-flash pa-flash--err"><?= $h((string) $err) ?></p>
            <?php endif; ?>

            <?php if ($rows === []): ?>
                <p class="pa-empty">Aucune formule enregistrée.</p>
            <?php else: ?>
            <div class="spl-toolbar">
                <label class="spl-search">
                    <span class="spl-sr">Rechercher un paramètre</span>
                    <input type="search" data-spl-search value="<?= $h($search) ?>" placeholder="Rechercher un paramètre (ex. membres, ATAK)" autocomplete="off">
                </label>
                <div class="spl-toolbar__actions">
                    <button type="button" class="pa-btn pa-btn--line pa-btn--sm" data-spl-expand="1">Tout déplier</button>
                    <button type="button" class="pa-btn pa-btn--line pa-btn--sm" data-spl-expand="0">Tout replier</button>
                </div>
            </div>
            <p class="spl-search-empty" data-spl-search-empty hidden>Aucun paramètre ne correspond à la recherche.</p>

            <div class="pa-table-wrap spl-compare-wrap">
                <table class="spl-compare">
                    <thead>
                        <tr>
                            <th scope="col" class="spl-compare__param">Paramètre</th>
                            <?php foreach ($rows as $r): ?>
                                <?php
                                $rid = (int) ($r['id'] ?? 0);
                                $slug = (string) ($r['slug'] ?? '');
                                $count = count($subscribers[$slug] ?? []);
                                ?>
                                <th scope="col" class="spl-compare__plan">
                                    <a class="spl-compare__name" href="<?= $h(url('admin/system/subscription-plans/' . $rid . '/edit')) ?>"><?= $h((string) ($r['name'] ?? $slug)) ?></a>
                                    <span class="spl-compare__slug" translate="no"><?= $h($slug) ?></span>
                                    <span class="pa-pill <?= $count > 0 ? 'pa-pill--blue' : 'pa-pill--slate' ?>"><?= (int) $count ?> <span>communauté(s)</span></span>
                                    <span class="spl-compare__actions">
                                        <a class="pa-btn pa-btn--ink pa-btn--sm" href="<?= $h(url('admin/system/subscription-plans/' . $rid . '/edit')) ?>">Modifier</a>
                                        <form method="post" action="<?= $h(url('admin/system/subscription-plans/' . $rid . '/dupliquer')) ?>">
                                            <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                                            <button type="submit" class="pa-btn pa-btn--line pa-btn--sm">Dupliquer</button>
                                        </form>
                                    </span>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <?php foreach (PlanCatalog::keysByGroup() as $group => $keys): ?>
                        <?php if ($keys === []) { continue; } ?>
                        <tbody class="spl-group" data-spl-group="<?= $h($group) ?>">
                            <tr class="spl-group__head">
                                <th colspan="<?= count($rows) + 1 ?>" scope="rowgroup">
                                    <button type="button" class="spl-group__toggle" aria-expanded="true" data-spl-toggle>
                                        <span class="spl-group__chev" aria-hidden="true"></span>
                                        <?= $h(PlanCatalog::GROUPS[$group]['label']) ?>
                                        <span class="spl-group__count"><?= count($keys) ?></span>
                                    </button>
                                </th>
                            </tr>
                            <?php foreach ($keys as $key): ?>
                                <?php $def = $defs[$key]; ?>
                                <tr class="spl-row" data-spl-row data-spl-text="<?= $h(mb_strtolower($def['label'] . ' ' . $def['label_en'] . ' ' . $def['help'] . ' ' . $key)) ?>">
                                    <th scope="row" class="spl-compare__param">
                                        <span class="spl-param__label"><?= $h($def['label']) ?></span>
                                        <?php if (!$def['read']): ?>
                                            <span class="pa-pill pa-pill--amber" title="Enregistré dans les formules, mais aucun écran ne le contrôle encore : le modifier ne change rien pour les communautés.">Non contrôlé</span>
                                        <?php endif; ?>
                                        <span class="spl-param__help"><?= $h($def['help']) ?></span>
                                    </th>
                                    <?php foreach ($rows as $r): ?>
                                        <?php
                                        $pv = $values[(string) $r['slug']] ?? ['values' => [], 'defaulted' => []];
                                        $v = $pv['values'][$key] ?? null;
                                        $isDefault = in_array($key, $pv['defaulted'], true);
                                        ?>
                                        <td class="spl-cell<?= $isDefault ? ' spl-cell--default' : '' ?>">
                                            <?php if ($def['type'] === 'bool'): ?>
                                                <?php if ($v): ?>
                                                    <span class="spl-yes"><span aria-hidden="true">✓</span> Inclus</span>
                                                <?php else: ?>
                                                    <span class="spl-no">Non inclus</span>
                                                <?php endif; ?>
                                            <?php elseif ((int) $v === 0): ?>
                                                <span class="spl-unlimited">Illimité</span>
                                            <?php else: ?>
                                                <span class="spl-num"><?= $h(number_format((int) $v, 0, ',', ' ')) ?></span>
                                                <span class="spl-unit"><?= $h((string) ($def['unit'] ?? '')) ?></span>
                                            <?php endif; ?>
                                            <?php if ($isDefault): ?>
                                                <span class="spl-default-mark" title="Valeur par défaut du palier : pas encore enregistrée dans cette formule.">*</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    <?php endforeach; ?>
                </table>
            </div>
            <p class="spl-legend">
                <span>* Valeur par défaut du palier, appliquée tant que la formule ne l’a pas enregistrée.</span>
                <span>« Non contrôlé » : paramètre enregistré, qu’aucun écran ne vérifie encore.</span>
            </p>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="<?= $h(asset_url('assets/js/admin-subscription-plans.js')) ?>" defer></script>
