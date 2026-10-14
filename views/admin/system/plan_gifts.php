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
$endWatch = is_array($planEndWatch ?? null) ? $planEndWatch : ['ending' => [], 'grace' => [], 'gated' => []];
$endCfg = is_array($planEndConfig ?? null) ? $planEndConfig : null;
$endAction = (string) ($planEndSettingsAction ?? url('admin/formules-offertes/reglages'));
$kindLabels = ['trial' => 'Essai fondateur', 'gift' => 'Formule offerte'];
$gatedReasons = [
    'trial_ended' => 'Essai fondateur terminé',
    'gift_ended' => 'Formule offerte terminée',
    'past_due' => 'Paiement en retard',
    'base_plan' => 'Formule sans la carte',
];
$typeLabels = ['full' => 'Complet', 'effectifs' => 'Bureau des effectifs', 'atak' => 'Carte ATAK'];
$offerUrl = static fn (int $tid): string => url('admin/formules-offertes') . '?tenant_id=' . $tid . '#offrir';
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

            <section id="fins-de-formule" style="margin-top:2rem;">
                <h2 class="pa-card__title" style="margin:0 0 0.35rem;">Fins de formule</h2>
                <p class="pa-card__help" style="margin-bottom:1rem;">
                    Communautés dont l’essai fondateur ou la formule offerte se termine bientôt, celles en période de grâce,
                    et celles dont la liaison avec le jeu est refusée faute de carte dans leur formule.
                </p>

                <h3 class="pa-label" style="margin:1.25rem 0 0.5rem;">Se terminent dans les <?= max(14, (int) ($endCfg['warning_days'] ?? 7)) ?> prochains jours (<?= count($endWatch['ending']) ?>)</h3>
                <?php if ($endWatch['ending'] === []): ?>
                    <p class="pa-card__help">Aucune fin prévue.</p>
                <?php else: ?>
                    <div class="pa-table-wrap">
                        <table class="pa-table">
                            <thead>
                                <tr><th>Communauté</th><th>Ce qui se termine</th><th>Fin</th><th>Après la fin</th><th>Gestionnaires prévenus</th><th></th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($endWatch['ending'] as $r): ?>
                                    <tr>
                                        <td>
                                            <span class="pa-name"><?= $h((string) $r['name']) ?></span>
                                            <span class="pa-sub"><?= $h($typeLabels[$r['tenant_type']] ?? (string) $r['tenant_type']) ?> · <?= (int) $r['user_count'] ?> membre(s)</span>
                                        </td>
                                        <td><?= $h($kindLabels[$r['kind']] ?? (string) $r['kind']) ?> · <?= $h((string) $r['plan']) ?></td>
                                        <td>
                                            <?= $h($fmt($r['ends_at'])) ?>
                                            <span class="pa-sub">dans <?= (int) $r['days_left'] ?> jour(s)</span>
                                        </td>
                                        <td>
                                            <?php if (!empty($r['loses_atak'])): ?>
                                                <span class="pa-pill pa-pill--rose">Perd la liaison jeu</span>
                                            <?php else: ?>
                                                <span class="pa-pill pa-pill--slate">Garde la liaison jeu</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= !empty($r['warned']) ? '<span class="pa-pill pa-pill--mint">E-mail envoyé</span>' : '<span class="pa-sub">Pas encore</span>' ?></td>
                                        <td style="text-align:right;"><a class="pa-btn pa-btn--line pa-btn--sm" href="<?= $h($offerUrl((int) $r['id'])) ?>">Prolonger</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if ($endWatch['grace'] !== []): ?>
                    <h3 class="pa-label" style="margin:1.5rem 0 0.5rem;">En période de grâce (<?= count($endWatch['grace']) ?>)</h3>
                    <div class="pa-table-wrap">
                        <table class="pa-table">
                            <thead>
                                <tr><th>Communauté</th><th>Formule terminée</th><th>Terminée le</th><th>Coupure le</th><th></th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($endWatch['grace'] as $r): ?>
                                    <tr>
                                        <td><span class="pa-name"><?= $h((string) $r['name']) ?></span></td>
                                        <td><?= $h($kindLabels[$r['kind']] ?? (string) $r['kind']) ?> · <?= $h((string) $r['plan']) ?></td>
                                        <td><?= $h($fmt($r['ended_at'])) ?></td>
                                        <td><?= $h($fmt($r['grace_ends_at'])) ?> <span class="pa-sub">dans <?= (int) $r['days_left'] ?> jour(s)</span></td>
                                        <td style="text-align:right;"><a class="pa-btn pa-btn--line pa-btn--sm" href="<?= $h($offerUrl((int) $r['id'])) ?>">Offrir</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <h3 class="pa-label" style="margin:1.5rem 0 0.5rem;">Liaison avec le jeu refusée (<?= count($endWatch['gated']) ?>)</h3>
                <p class="pa-hint" style="margin-bottom:0.5rem;">Leur formule n’a pas la carte et les liaisons : photos, positions et comptes rendus envoyés depuis le jeu reçoivent un refus « fonction indisponible ».</p>
                <?php if ($endWatch['gated'] === []): ?>
                    <p class="pa-card__help">Aucune communauté bloquée.</p>
                <?php else: ?>
                    <div class="pa-table-wrap">
                        <table class="pa-table">
                            <thead>
                                <tr><th>Communauté</th><th>Formule appliquée</th><th>Raison</th><th>Depuis</th><th></th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($endWatch['gated'] as $r): ?>
                                    <tr>
                                        <td>
                                            <span class="pa-name"><?= $h((string) $r['name']) ?></span>
                                            <span class="pa-sub"><?= $h($typeLabels[$r['tenant_type']] ?? (string) $r['tenant_type']) ?> · <?= (int) $r['user_count'] ?> membre(s)</span>
                                        </td>
                                        <td><?= $h((string) $r['effective_plan']) ?></td>
                                        <td><?= $h($gatedReasons[$r['reason']] ?? (string) $r['reason']) ?></td>
                                        <td><?= $h($fmt($r['ended_at'] ?? null)) ?></td>
                                        <td style="text-align:right;"><a class="pa-btn pa-btn--line pa-btn--sm" href="<?= $h($offerUrl((int) $r['id'])) ?>">Offrir</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if ($endCfg !== null): ?>
                    <div class="pa-narrow" style="margin-top:1.5rem;">
                        <form class="pa-card" method="post" action="<?= $h($endAction) ?>">
                            <?= \App\Core\Csrf::field() ?>
                            <p class="pa-card__kicker">Réglages</p>
                            <h3 class="pa-card__title">Avertissement et période de grâce</h3>
                            <p class="pa-card__help">S’appliquent aux essais fondateurs et aux formules offertes. Les abonnements payants ne changent pas.</p>

                            <div class="pa-field">
                                <label for="end_warning_days">Prévenir combien de jours avant la fin</label>
                                <p class="pa-hint">Bandeau sur le site pour le propriétaire, les fondateurs et les administrateurs de la communauté. 0 = pas d’avertissement.</p>
                                <input id="end_warning_days" name="warning_days" type="number" min="0" max="<?= \App\Support\PlanAccessWindow::MAX_DAYS ?>" value="<?= (int) $endCfg['warning_days'] ?>">
                            </div>
                            <label class="pa-choice">
                                <input type="checkbox" name="warning_email" value="1" <?= !empty($endCfg['warning_email']) ? 'checked' : '' ?>>
                                <span>
                                    <strong>Envoyer aussi un e-mail aux gestionnaires</strong>
                                    <em>Une seule fois par fin, en français ou en anglais selon leur profil. Désactivé par défaut.</em>
                                </span>
                            </label>

                            <label class="pa-choice" style="margin-top:1rem;">
                                <input type="checkbox" name="grace_enabled" value="1" <?= !empty($endCfg['grace_enabled']) ? 'checked' : '' ?>>
                                <span>
                                    <strong>Période de grâce après la fin</strong>
                                    <em>Les modules de la formule terminée (dont la liaison avec le jeu) restent ouverts quelques jours. Désactivée par défaut : coupure le jour de la fin.</em>
                                </span>
                            </label>
                            <div class="pa-field">
                                <label for="end_grace_days">Durée de la grâce (jours)</label>
                                <input id="end_grace_days" name="grace_days" type="number" min="0" max="<?= \App\Support\PlanAccessWindow::MAX_DAYS ?>" value="<?= (int) $endCfg['grace_days'] ?>">
                            </div>

                            <div class="pa-actions">
                                <button type="submit" class="pa-btn pa-btn--ink">Enregistrer les réglages</button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </section>

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
