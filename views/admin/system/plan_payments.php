<?php
declare(strict_types=1);
$rows = is_array($paymentRows ?? null) ? $paymentRows : [];
$counts = is_array($paymentCounts ?? null) ? $paymentCounts : [];
$status = (string) ($paymentStatus ?? '');
$focus = (string) ($paymentFocus ?? '');
$pageError = isset($paymentError) ? (string) $paymentError : '';
$paypalBase = (string) ($paypalBaseUrl ?? \App\Support\ManualBilling::DEFAULT_PAYPAL_URL);
$monthTotal = (string) ($paymentMonthTotal ?? '0 €');
$subscriptions = is_array($paymentSubscriptions ?? null) ? $paymentSubscriptions : [];
$offers = is_array($paymentOffers ?? null) ? $paymentOffers : [];
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$fmt = static function (mixed $raw, string $pattern = 'd/m/Y H:i'): string {
    if ($raw === null || $raw === '') {
        return '—';
    }
    $t = strtotime((string) $raw);

    return $t ? date($pattern, $t) : (string) $raw;
};
$statusLabels = [
    'declared' => ['Payé, à vérifier', 'amber'],
    'awaiting' => ['En attente de paiement', 'slate'],
    'activated' => ['Activé', 'mint'],
    'rejected' => ['Refusé', 'rose'],
    'cancelled' => ['Annulé', 'slate'],
    'refunded' => ['Remboursé', 'blue'],
];
$filters = ['' => 'Tous', 'declared' => 'À vérifier', 'awaiting' => 'En attente', 'activated' => 'Activés', 'refunded' => 'Remboursés', 'rejected' => 'Refusés', 'cancelled' => 'Annulés'];
$declaredCount = (int) ($counts['declared'] ?? 0);
$awaitingCount = (int) ($counts['awaiting'] ?? 0);
$oldestDeclared = null;
foreach ($rows as $r) {
    if ((string) ($r['status'] ?? '') === 'declared' && !empty($r['declared_at'])) {
        $t = strtotime((string) $r['declared_at'] . ' UTC');
        if ($t !== false && ($oldestDeclared === null || $t < $oldestDeclared)) {
            $oldestDeclared = $t;
        }
    }
}
$declaredLate = $oldestDeclared !== null && $oldestDeclared < time() - 86400;
$money = static fn (?int $c): string => $c !== null ? \App\Support\ManualBilling::formatAmount($c) : '—';
$ok = \App\Core\Session::getFlash('success');
$err = \App\Core\Session::getFlash('error');
?>
<style>
.ppay-alert { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px 16px; padding: 14px 16px; border: 1px solid #f1dca8; border-left: 4px solid #c98a12; border-radius: 12px; background: #fdf7e8; color: #7a4f05; }
.ppay-alert--late { border-color: #f3c3bd; border-left-color: #c72e2e; background: #fdf1ef; color: #9b1c12; }
.ppay-alert strong { display: block; font-size: 15px; }
.ppay-alert span { font-size: 13px; }
.ppay-filters { display: flex; flex-wrap: wrap; gap: 6px; }
.ppay-list { display: grid; gap: 10px; }
.ppay-item { display: grid; grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr) auto; gap: 12px 18px; align-items: start; padding: 14px 16px; border: 1px solid var(--pa-line, #e2e8f0); border-radius: 12px; background: var(--pa-surface, #fff); }
.ppay-item--declared { border-left: 4px solid #c98a12; }
.ppay-item--focus { outline: 2px solid #047857; outline-offset: 1px; }
.ppay-item__ref { font-family: ui-monospace, monospace; font-weight: 700; font-size: 14px; }
.ppay-item__what { margin: 2px 0 0; font-weight: 600; }
.ppay-item__meta { margin: 2px 0 0; font-size: 12.5px; color: var(--pa-muted, #64748b); }
.ppay-item__amount { font-size: 18px; font-weight: 800; }
.ppay-item__txn { font-family: ui-monospace, monospace; font-size: 13px; }
.ppay-item__side { display: grid; gap: 6px; justify-items: end; text-align: right; }
.ppay-actions { grid-column: 1 / -1; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding-top: 10px; border-top: 1px dashed var(--pa-line, #e2e8f0); }
.ppay-actions form { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 0; }
.ppay-actions input[type="text"] { min-width: 14rem; flex: 1 1 14rem; min-height: 30px; padding: 4px 10px; border: 1px solid var(--pa-line, #cbd5e1); border-radius: 8px; font: inherit; font-size: 13px; }
.ppay-actions details { width: 100%; }
.ppay-actions summary { cursor: pointer; font-size: 13px; font-weight: 700; color: var(--pa-muted, #475569); }
.ppay-actions details form { margin-top: 8px; }
.ppay-check { display: inline-flex; gap: 6px; align-items: center; font-size: 13px; }
.ppay-subs td, .ppay-subs th { vertical-align: middle; }
.ppay-days { font-weight: 700; }
.ppay-days--soon { color: #b45309; }
.ppay-sale { display: grid; gap: 8px; margin: 0 0 14px; padding: 0; list-style: none; }
.ppay-sale li { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px; padding: 10px 12px; border: 1px solid var(--pa-line, #e2e8f0); border-radius: 10px; }
@media (max-width: 760px) { .ppay-item { grid-template-columns: 1fr; } .ppay-item__side { justify-items: start; text-align: left; } }
</style>
<div class="pa">
    <div class="pa__frame">
        <header class="pa-hero">
            <p class="pa-crumb">
                <a href="<?= $h(url('admin')) ?>">Administration du site</a>
                <span aria-hidden="true"> / </span>
                <a href="<?= $h(url('admin/tenants')) ?>">Communautés</a>
                <span aria-hidden="true"> / </span>
                Paiements PayPal
            </p>
            <h1 class="pa-hero__title">Paiements PayPal</h1>
            <p class="pa-hero__lead">
                Chaque formule se paie par PayPal avec une référence ATH-…, puis la personne déclare son identifiant de transaction.
                Vérifiez la réception sur PayPal et activez : la formule s’applique pour un mois ou un an et prolonge la période en cours.
                Vous pouvez aussi refuser ou annuler une demande, marquer un paiement remboursé et arrêter une formule.
            </p>
            <div class="pa-hero__actions">
                <a class="pa-btn pa-btn--ghost" href="https://www.paypal.com/myaccount/transactions" target="_blank" rel="noopener noreferrer">Mes transactions PayPal</a>
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/system/subscription-plans')) ?>">Prix des formules</a>
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/formules-offertes')) ?>">Formules offertes</a>
            </div>
        </header>

        <div class="pa-panel">
            <?php if ($ok): ?>
                <p class="pa-flash pa-flash--ok"><?= $h((string) $ok) ?></p>
            <?php endif; ?>
            <?php if ($err): ?>
                <p class="pa-flash pa-flash--err"><?= $h((string) $err) ?></p>
            <?php endif; ?>
            <?php if ($pageError !== ''): ?>
                <p class="pa-flash pa-flash--warn"><?= $h($pageError) ?></p>
            <?php endif; ?>

            <ul class="pa-kpi-strip">
                <li><a class="pa-kpi<?= $declaredCount > 0 ? ($declaredLate ? ' pa-kpi--alert' : ' pa-kpi--warn') : '' ?>" href="<?= $h(url('admin/paiements?statut=declared')) ?>">
                    <span class="pa-kpi__label">À vérifier</span>
                    <span class="pa-kpi__value"><?= $declaredCount ?></span>
                    <span class="pa-kpi__hint"><?= $declaredLate ? 'Le plus ancien attend depuis plus de 24 h' : 'Paiements déclarés' ?></span>
                </a></li>
                <li><a class="pa-kpi" href="<?= $h(url('admin/paiements?statut=awaiting')) ?>">
                    <span class="pa-kpi__label">En attente</span>
                    <span class="pa-kpi__value"><?= $awaitingCount ?></span>
                    <span class="pa-kpi__hint">Pas encore payés</span>
                </a></li>
                <li><div class="pa-kpi">
                    <span class="pa-kpi__label">Activé ce mois</span>
                    <span class="pa-kpi__value"><?= $h($monthTotal) ?></span>
                    <span class="pa-kpi__hint">Hors remboursements</span>
                </div></li>
                <li><a class="pa-kpi" href="#abonnements">
                    <span class="pa-kpi__label">Formules en cours</span>
                    <span class="pa-kpi__value"><?= count($subscriptions) ?></span>
                    <span class="pa-kpi__hint">Payées par PayPal</span>
                </a></li>
            </ul>

            <?php if ($declaredCount > 0): ?>
                <div class="ppay-alert<?= $declaredLate ? ' ppay-alert--late' : '' ?>" role="status">
                    <div>
                        <strong><?= $declaredCount ?> paiement<?= $declaredCount > 1 ? 's' : '' ?> pas encore validé<?= $declaredCount > 1 ? 's' : '' ?></strong>
                        <span><?= $declaredLate
                            ? 'Au moins une personne attend depuis plus de 24 h. Une relance par e-mail part automatiquement.'
                            : 'Les personnes attendent l’activation de leur formule.' ?></span>
                    </div>
                    <a class="pa-btn pa-btn--sm" href="<?= $h(url('admin/paiements?statut=declared')) ?>">Vérifier maintenant</a>
                </div>
            <?php endif; ?>

            <nav class="ppay-filters" aria-label="Filtrer les paiements">
                <?php foreach ($filters as $key => $label): ?>
                    <?php $n = $key === '' ? array_sum($counts) : (int) ($counts[$key] ?? 0); ?>
                    <a class="pa-btn pa-btn--sm <?= $status === $key ? '' : 'pa-btn--line' ?>" href="<?= $h(url('admin/paiements') . ($key !== '' ? '?statut=' . $key : '')) ?>">
                        <?= $h($label) ?> (<?= $n ?>)
                    </a>
                <?php endforeach; ?>
            </nav>

            <?php if ($rows === []): ?>
                <p class="pa-card__help">Aucun paiement pour ce filtre.</p>
            <?php else: ?>
                <div class="ppay-list">
                <?php foreach ($rows as $r): ?>
                    <?php
                    $st = (string) ($r['status'] ?? '');
                    [$stLabel, $stTone] = $statusLabels[$st] ?? [$st, 'slate'];
                    $open = in_array($st, ['awaiting', 'declared'], true);
                    $ref = (string) ($r['reference'] ?? '');
                    $id = (int) ($r['id'] ?? 0);
                    $isHeart = ((string) ($r['kind'] ?? '')) === 'heart';
                    ?>
                    <article id="<?= $h($ref) ?>" class="ppay-item<?= $st === 'declared' ? ' ppay-item--declared' : '' ?><?= $focus !== '' && $focus === $ref ? ' ppay-item--focus' : '' ?>">
                        <div>
                            <span class="ppay-item__ref"><?= $h($ref) ?></span>
                            <p class="ppay-item__what"><?= $h((string) ($r['_description'] ?? '')) ?></p>
                            <p class="ppay-item__meta">
                                <?= $h((string) ($r['tenant_name'] ?? '—')) ?>
                                <?php if (trim((string) ($r['user_display_name'] ?? '')) !== ''): ?> · <?= $h((string) $r['user_display_name']) ?><?php endif; ?>
                                <?php if (trim((string) ($r['payer_email'] ?? '')) !== ''): ?> · <?= $h((string) $r['payer_email']) ?><?php endif; ?>
                            </p>
                            <p class="ppay-item__meta">Créée le <?= $h($fmt($r['created_at'] ?? null)) ?></p>
                        </div>
                        <div>
                            <?php if (trim((string) ($r['transaction_ref'] ?? '')) !== ''): ?>
                                <span class="ppay-item__txn"><?= $h((string) $r['transaction_ref']) ?></span>
                                <p class="ppay-item__meta">Déclaré le <?= $h($fmt($r['declared_at'] ?? null)) ?></p>
                                <?php if (trim((string) ($r['payer_note'] ?? '')) !== ''): ?>
                                    <p class="ppay-item__meta">« <?= $h((string) $r['payer_note']) ?> »</p>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="ppay-item__meta">Transaction pas encore déclarée</p>
                            <?php endif; ?>
                            <?php if (trim((string) ($r['admin_note'] ?? '')) !== ''): ?>
                                <p class="ppay-item__meta">Note : <?= $h((string) $r['admin_note']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="ppay-item__side">
                            <span class="ppay-item__amount"><?= $h((string) ($r['_amount'] ?? '')) ?></span>
                            <span class="pa-pill pa-pill--<?= $h($stTone) ?>"><?= $h($stLabel) ?></span>
                            <?php if ($st === 'activated' && !empty($r['period_ends_at'])): ?>
                                <span class="ppay-item__meta">Jusqu’au <?= $h($fmt($r['period_ends_at'], 'd/m/Y')) ?></span>
                            <?php elseif ($st === 'refunded' && !empty($r['refunded_at'])): ?>
                                <span class="ppay-item__meta">Le <?= $h($fmt($r['refunded_at'])) ?></span>
                            <?php elseif (!$open && !empty($r['decided_at'])): ?>
                                <span class="ppay-item__meta"><?= $h($fmt($r['decided_at'])) ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if ($open): ?>
                            <div class="ppay-actions">
                                <form method="post" action="<?= $h(url('admin/paiements/' . $id . '/activer')) ?>"
                                      onsubmit="return confirm('Activer <?= $h($ref) ?> ? Vérifiez d’abord la réception du paiement sur PayPal.');">
                                    <?= \App\Core\Csrf::field() ?>
                                    <input type="text" name="admin_note" maxlength="500" placeholder="Note pour la personne (facultatif)">
                                    <button type="submit" class="pa-btn pa-btn--mint pa-btn--sm"><?= $isHeart ? 'Confirmer la réception' : 'Activer la formule' ?></button>
                                    <button type="submit" class="pa-btn pa-btn--line pa-btn--sm"
                                            formaction="<?= $h(url('admin/paiements/' . $id . '/refuser')) ?>"
                                            onclick="return confirm('Refuser <?= $h($ref) ?> ? La personne sera prévenue par e-mail.');">Refuser</button>
                                    <button type="submit" class="pa-btn pa-btn--line pa-btn--sm"
                                            formaction="<?= $h(url('admin/paiements/' . $id . '/annuler')) ?>"
                                            onclick="return confirm('Annuler la demande <?= $h($ref) ?> ? Elle ne pourra plus être payée.');">Annuler la demande</button>
                                </form>
                            </div>
                        <?php elseif ($st === 'activated'): ?>
                            <div class="ppay-actions">
                                <details>
                                    <summary>Rembourser ce paiement</summary>
                                    <form method="post" action="<?= $h(url('admin/paiements/' . $id . '/rembourser')) ?>"
                                          onsubmit="return confirm('Marquer <?= $h($ref) ?> comme remboursé ? Faites d’abord le remboursement depuis PayPal.');">
                                        <?= \App\Core\Csrf::field() ?>
                                        <input type="text" name="admin_note" maxlength="500" placeholder="Motif (envoyé à la personne, facultatif)">
                                        <?php if (!$isHeart): ?>
                                            <label class="ppay-check"><input type="checkbox" name="end_plan" value="1" checked> Arrêter aussi la formule</label>
                                        <?php endif; ?>
                                        <button type="submit" class="pa-btn pa-btn--danger pa-btn--sm">Marquer remboursé</button>
                                    </form>
                                </details>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <section id="abonnements" class="pa-card" style="margin-top:1.5rem;">
                <p class="pa-card__kicker">Suivi</p>
                <h2 class="pa-card__title">Formules payées en cours</h2>
                <p class="pa-card__help">Les responsables sont prévenus 7 jours avant la fin puis la veille (e-mail, bandeau, alerte). « Arrêter » ramène tout de suite la communauté à la formule gratuite.</p>
                <?php if ($subscriptions === []): ?>
                    <p class="pa-card__help">Aucune formule payée par PayPal en cours.</p>
                <?php else: ?>
                    <div class="pa-table-wrap">
                        <table class="pa-table ppay-subs">
                            <thead><tr><th>Communauté</th><th>Formule</th><th>Fin</th><th>Arrêter</th></tr></thead>
                            <tbody>
                            <?php foreach ($subscriptions as $sub): ?>
                                <tr>
                                    <td><a href="<?= $h(url('admin/tenants/' . (int) $sub['tenant_id'] . '/edit')) ?>"><?= $h((string) $sub['tenant_name']) ?></a></td>
                                    <td><?= $h((string) $sub['plan_name']) ?> <span class="pa-sub"><?= $sub['interval'] === 'yearly' ? '1 an' : '1 mois' ?> · <?= $h((string) $sub['reference']) ?></span></td>
                                    <td>
                                        <?= $h($fmt($sub['ends_at'], 'd/m/Y')) ?>
                                        <span class="pa-sub ppay-days<?= (int) $sub['days_left'] <= 7 ? ' ppay-days--soon' : '' ?>">dans <?= (int) $sub['days_left'] ?> j</span>
                                    </td>
                                    <td>
                                        <form method="post" action="<?= $h(url('admin/paiements/communaute/' . (int) $sub['tenant_id'] . '/arreter')) ?>"
                                              onsubmit="return confirm('Arrêter la formule <?= $h(addslashes((string) $sub['plan_name'])) ?> de <?= $h(addslashes((string) $sub['tenant_name'])) ?> maintenant ?');"
                                              style="display:flex;gap:6px;flex-wrap:wrap;">
                                            <?= \App\Core\Csrf::field() ?>
                                            <input type="text" name="admin_note" maxlength="500" placeholder="Motif (facultatif)" style="min-width:10rem;">
                                            <button type="submit" class="pa-btn pa-btn--line pa-btn--sm">Arrêter</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <div class="pa-narrow" style="margin-top:1.5rem;">
                <form id="reglages" class="pa-card" method="post" action="<?= $h(url('admin/paiements/reglages')) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <p class="pa-card__kicker">Réglages</p>
                    <h2 class="pa-card__title">Formules en vente</h2>
                    <p class="pa-card__help">Décochez une formule pour la retirer de la vente : plus aucune nouvelle souscription, les formules déjà payées continuent jusqu’à leur fin.</p>
                    <ul class="ppay-sale">
                        <?php foreach ($offers as $o): ?>
                            <li>
                                <label class="ppay-check">
                                    <input type="checkbox" name="on_sale[]" value="<?= $h((string) $o['slug']) ?>" <?= !empty($o['on_sale']) ? 'checked' : '' ?>>
                                    <strong><?= $h((string) $o['name']) ?></strong>
                                </label>
                                <span class="pa-sub"><?= $h($money($o['monthly'])) ?> / mois · <?= $h($money($o['yearly'])) ?> / an</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <h2 class="pa-card__title">Compte PayPal qui reçoit les paiements</h2>
                    <p class="pa-card__help">Lien paypal.me utilisé sur la page de paiement des formules et sur la page de dons publique (<a href="<?= $h(url('soutenir-atak')) ?>" target="_blank" rel="noopener">Soutenir</a>, avec son QR code) ; le montant y est ajouté automatiquement. Laissez vide pour revenir au compte par défaut (<?= $h(\App\Support\ManualBilling::DEFAULT_PAYPAL_URL) ?>).</p>
                    <div class="pa-field">
                        <label for="pp_url">Lien paypal.me</label>
                        <input id="pp_url" name="paypal_url" type="url" maxlength="200" value="<?= $h($paypalBase) ?>" placeholder="https://www.paypal.me/MonCompte">
                    </div>
                    <div class="pa-actions">
                        <button type="submit" class="pa-btn">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
