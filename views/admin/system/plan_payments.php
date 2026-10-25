<?php
declare(strict_types=1);
$rows = is_array($paymentRows ?? null) ? $paymentRows : [];
$counts = is_array($paymentCounts ?? null) ? $paymentCounts : [];
$status = (string) ($paymentStatus ?? '');
$focus = (string) ($paymentFocus ?? '');
$pageError = isset($paymentError) ? (string) $paymentError : '';
$paypalBase = (string) ($paypalBaseUrl ?? \App\Support\ManualBilling::DEFAULT_PAYPAL_URL);
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$fmt = static function (mixed $raw): string {
    if ($raw === null || $raw === '') {
        return '—';
    }
    $t = strtotime((string) $raw);

    return $t ? date('d/m/Y H:i', $t) : (string) $raw;
};
$statusLabels = [
    'declared' => ['Payé, à vérifier', 'amber'],
    'awaiting' => ['En attente de paiement', 'slate'],
    'activated' => ['Activé', 'mint'],
    'rejected' => ['Refusé', 'rose'],
    'cancelled' => ['Annulé', 'slate'],
];
$filters = ['' => 'Tous', 'declared' => 'À vérifier', 'awaiting' => 'En attente', 'activated' => 'Activés', 'rejected' => 'Refusés'];
$ok = \App\Core\Session::getFlash('success');
$err = \App\Core\Session::getFlash('error');
?>
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
                Vérifiez la réception sur votre compte PayPal (montant, référence dans la note) et activez : la formule s’applique pour un mois ou un an,
                et prolonge la période en cours si elle n’est pas terminée.
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

            <div class="pa-actions" style="margin-bottom:1rem;flex-wrap:wrap;">
                <?php foreach ($filters as $key => $label): ?>
                    <?php $n = $key === '' ? array_sum($counts) : (int) ($counts[$key] ?? 0); ?>
                    <a class="pa-btn pa-btn--sm <?= $status === $key ? '' : 'pa-btn--line' ?>" href="<?= $h(url('admin/paiements') . ($key !== '' ? '?statut=' . $key : '')) ?>">
                        <?= $h($label) ?> (<?= $n ?>)
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if ($rows === []): ?>
                <p class="pa-card__help">Aucun paiement pour ce filtre.</p>
            <?php else: ?>
                <div class="pa-table-wrap">
                    <table class="pa-table">
                        <thead>
                            <tr><th>Référence</th><th>Achat</th><th>Montant</th><th>Transaction PayPal</th><th>Statut</th><th>Décision</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $st = (string) ($r['status'] ?? '');
                            [$stLabel, $stTone] = $statusLabels[$st] ?? [$st, 'slate'];
                            $open = in_array($st, ['awaiting', 'declared'], true);
                            $ref = (string) ($r['reference'] ?? '');
                            ?>
                            <tr id="<?= $h($ref) ?>" <?= $focus !== '' && $focus === $ref ? 'style="outline:2px solid #047857;outline-offset:-2px;"' : '' ?>>
                                <td>
                                    <span class="pa-name" style="font-family:ui-monospace,monospace;"><?= $h($ref) ?></span>
                                    <span class="pa-sub">Créée le <?= $h($fmt($r['created_at'] ?? null)) ?></span>
                                </td>
                                <td>
                                    <?= $h((string) ($r['_description'] ?? '')) ?>
                                    <span class="pa-sub">
                                        <?= $h((string) ($r['tenant_name'] ?? '—')) ?>
                                        · <?= $h((string) ($r['user_display_name'] ?? '')) ?>
                                        <?php if (trim((string) ($r['payer_email'] ?? '')) !== ''): ?> · <?= $h((string) $r['payer_email']) ?><?php endif; ?>
                                    </span>
                                </td>
                                <td><strong><?= $h((string) ($r['_amount'] ?? '')) ?></strong></td>
                                <td>
                                    <?php if (trim((string) ($r['transaction_ref'] ?? '')) !== ''): ?>
                                        <span class="pa-name" style="font-family:ui-monospace,monospace;"><?= $h((string) $r['transaction_ref']) ?></span>
                                        <span class="pa-sub">Déclaré le <?= $h($fmt($r['declared_at'] ?? null)) ?></span>
                                        <?php if (trim((string) ($r['payer_note'] ?? '')) !== ''): ?>
                                            <span class="pa-sub">« <?= $h((string) $r['payer_note']) ?> »</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="pa-sub">Pas encore déclaré</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="pa-pill pa-pill--<?= $h($stTone) ?>"><?= $h($stLabel) ?></span>
                                    <?php if ($st === 'activated' && !empty($r['period_ends_at'])): ?>
                                        <span class="pa-sub">Jusqu’au <?= $h($fmt($r['period_ends_at'])) ?></span>
                                    <?php elseif (!$open && !empty($r['decided_at'])): ?>
                                        <span class="pa-sub"><?= $h($fmt($r['decided_at'])) ?></span>
                                    <?php endif; ?>
                                    <?php if (trim((string) ($r['admin_note'] ?? '')) !== ''): ?>
                                        <span class="pa-sub"><?= $h((string) $r['admin_note']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="min-width:15rem;">
                                    <?php if ($open): ?>
                                        <form method="post" action="<?= $h(url('admin/paiements/' . (int) $r['id'] . '/activer')) ?>" style="display:grid;gap:0.4rem;"
                                              onsubmit="return confirm('Activer <?= $h($ref) ?> ? Vérifiez d’abord la réception du paiement sur PayPal.');">
                                            <?= \App\Core\Csrf::field() ?>
                                            <input type="text" name="admin_note" maxlength="500" placeholder="Note pour la personne (facultatif)">
                                            <div class="pa-actions">
                                                <button type="submit" class="pa-btn pa-btn--sm"><?= ((string) ($r['kind'] ?? '')) === 'heart' ? 'Confirmer la réception' : 'Activer la formule' ?></button>
                                                <button type="submit" class="pa-btn pa-btn--line pa-btn--sm"
                                                        formaction="<?= $h(url('admin/paiements/' . (int) $r['id'] . '/refuser')) ?>"
                                                        onclick="return confirm('Refuser <?= $h($ref) ?> ? La personne sera prévenue par e-mail.');">Refuser</button>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <span class="pa-sub">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <div class="pa-narrow" style="margin-top:2rem;">
                <form id="reglages" class="pa-card" method="post" action="<?= $h(url('admin/paiements/reglages')) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <p class="pa-card__kicker">Réglage</p>
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
