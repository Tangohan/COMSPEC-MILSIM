<?php
declare(strict_types=1);
$rows = is_array($planGiftRows ?? null) ? $planGiftRows : [];
$plans = is_array($planGiftPlans ?? null) ? $planGiftPlans : [];
$tenants = is_array($planGiftTenants ?? null) ? $planGiftTenants : [];
$selectedTenant = (int) ($planGiftSelectedTenant ?? 0);
$durations = is_array($planGiftDurations ?? null) ? $planGiftDurations : [30, 90, 180, 365, 0];
$pageError = isset($planGiftError) ? (string) $planGiftError : '';
$formAction = (string) ($planGiftFormAction ?? url('admin/formules-offertes'));
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$fmt = static function (mixed $raw): string {
    if ($raw === null || $raw === '') {
        return '—';
    }
    $t = strtotime((string) $raw);

    return $t ? date('d/m/Y', $t) : (string) $raw;
};
$durationLabels = [30 => '1 mois', 90 => '3 mois', 180 => '6 mois', 365 => '1 an', 0 => 'Sans limite'];
$now = time();
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
                Formules offertes
            </p>
            <h1 class="pa-hero__title">Offrir une formule</h1>
            <p class="pa-hero__lead">
                La formule offerte s’applique tout de suite, pour la durée choisie, sans toucher à l’abonnement payant.
                Les gestionnaires de la communauté reçoivent un e-mail et une fenêtre à leur prochaine connexion, en français ou en anglais selon leur profil.
            </p>
            <div class="pa-hero__actions">
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/tenants')) ?>">Annuaire des communautés</a>
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/system/subscription-plans')) ?>">Formules d’accès</a>
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/audit')) ?>">Journal d’audit</a>
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

            <div class="pa-narrow">
                <form id="offrir" class="pa-card" method="post" action="<?= $h($formAction) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <p class="pa-card__kicker">Nouvelle offre</p>
                    <h2 class="pa-card__title">Communauté, formule et durée</h2>
                    <p class="pa-card__help">Une nouvelle offre remplace celle qui court déjà pour cette communauté.</p>

                    <div class="pa-field">
                        <label for="gift_tenant">Communauté</label>
                        <select id="gift_tenant" name="tenant_id" required>
                            <option value="">Choisir une communauté</option>
                            <?php foreach ($tenants as $t): ?>
                                <?php $tid = (int) ($t['id'] ?? 0); ?>
                                <option value="<?= $tid ?>" <?= $tid === $selectedTenant ? 'selected' : '' ?>><?= $h((string) ($t['name'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="pa-field">
                        <label for="gift_plan">Formule offerte</label>
                        <select id="gift_plan" name="plan_slug" required>
                            <?php foreach ($plans as $p): ?>
                                <?php $slug = (string) ($p['slug'] ?? ''); ?>
                                <?php if ($slug !== ''): ?>
                                    <option value="<?= $h($slug) ?>"><?= $h((string) ($p['name'] ?? $slug)) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="pa-field">
                        <label for="gift_duration">Durée</label>
                        <select id="gift_duration" name="duration_days">
                            <?php foreach ($durations as $d): ?>
                                <option value="<?= (int) $d ?>"><?= $h($durationLabels[(int) $d] ?? ((int) $d . ' jours')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="pa-field">
                        <label for="gift_ends_on">Ou jusqu’à une date précise</label>
                        <p class="pa-hint">Facultatif. Si elle est remplie, cette date remplace la durée ci-dessus.</p>
                        <input id="gift_ends_on" name="ends_on" type="date" min="<?= $h(date('Y-m-d', $now + 86400)) ?>">
                    </div>

                    <div class="pa-field">
                        <label for="gift_message_fr">Message en français</label>
                        <p class="pa-hint">Facultatif. Affiché dans l’e-mail et la fenêtre des gestionnaires francophones.</p>
                        <textarea id="gift_message_fr" name="message_fr" rows="3" maxlength="1000"></textarea>
                    </div>

                    <div class="pa-field">
                        <label for="gift_message_en">Message en anglais</label>
                        <p class="pa-hint">Facultatif. Pour les gestionnaires anglophones ; à défaut, ils voient le message français.</p>
                        <textarea id="gift_message_en" name="message_en" rows="3" maxlength="1000"></textarea>
                    </div>

                    <div class="pa-actions">
                        <button type="submit" class="pa-btn pa-btn--ink" <?= $plans === [] ? 'disabled' : '' ?>>Offrir et prévenir les gestionnaires</button>
                    </div>
                </form>
            </div>

            <h2 class="pa-card__title" style="margin:2rem 0 0.75rem;">Offres récentes</h2>
            <?php if ($rows === []): ?>
                <p class="pa-card__help">Aucune formule offerte pour le moment.</p>
            <?php else: ?>
                <div class="pa-table-wrap">
                    <table class="pa-table">
                        <thead>
                            <tr>
                                <th>Communauté</th>
                                <th>Formule</th>
                                <th>Jusqu’au</th>
                                <th>État</th>
                                <th>Gestionnaires prévenus</th>
                                <th>Offerte par</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $r): ?>
                                <?php
                                $endsAt = (string) ($r['ends_at'] ?? '');
                                $endTs = $endsAt !== '' ? strtotime($endsAt) : false;
                                $revoked = !empty($r['revoked_at']);
                                $expired = !$revoked && $endTs !== false && $endTs <= $now;
                                $running = !$revoked && !$expired;
                                ?>
                                <tr>
                                    <td>
                                        <span class="pa-name"><?= $h((string) ($r['tenant_name'] ?? ('#' . (int) $r['tenant_id']))) ?></span>
                                        <span class="pa-sub">Le <?= $h($fmt($r['created_at'] ?? null)) ?></span>
                                    </td>
                                    <td><?= $h((string) (($r['plan_name'] ?? '') !== '' ? $r['plan_name'] : $r['plan_slug'])) ?></td>
                                    <td><?= $endsAt !== '' ? $h($fmt($endsAt)) : 'Sans limite' ?></td>
                                    <td>
                                        <?php if ($running): ?>
                                            <span class="pa-pill pa-pill--mint">En cours</span>
                                        <?php elseif ($revoked): ?>
                                            <span class="pa-pill pa-pill--slate">Retirée le <?= $h($fmt($r['revoked_at'])) ?></span>
                                        <?php else: ?>
                                            <span class="pa-pill pa-pill--amber">Terminée</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= (int) ($r['email_count'] ?? 0) ?> e-mail(s) sur <?= (int) ($r['recipient_count'] ?? 0) ?>
                                        <span class="pa-sub">Fenêtre vue : <?= (int) ($r['seen_count'] ?? 0) ?></span>
                                    </td>
                                    <td><?= $h((string) ($r['granted_by_name'] ?? '—')) ?></td>
                                    <td style="text-align:right;">
                                        <?php if ($running): ?>
                                            <form method="post" action="<?= $h(url('admin/formules-offertes/' . (int) $r['id'] . '/retirer')) ?>" onsubmit="return confirm('Retirer cette formule offerte ? La communauté revient tout de suite à sa formule habituelle.');">
                                                <?= \App\Core\Csrf::field() ?>
                                                <button type="submit" class="pa-btn pa-btn--line">Retirer</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
