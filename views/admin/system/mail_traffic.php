<?php
declare(strict_types=1);

use App\Services\Email\MailBudget;

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$cfg = is_array($mailConfig ?? null) ? $mailConfig : MailBudget::configFrom([]);
$sentToday = (int) ($mailSentToday ?? 0);
$failedToday = (int) ($mailFailedToday ?? 0);
$deferred = (int) ($mailDeferredPending ?? 0);
$digestPending = (int) ($mailDigestPending ?? 0);
$daily = is_array($mailDaily ?? null) ? $mailDaily : [];
$events = is_array($mailEvents ?? null) ? $mailEvents : [];
$failures = is_array($mailFailures ?? null) ? $mailFailures : [];
$transport = is_array($mailTransport ?? null) ? $mailTransport : [];
$opsInboxes = is_array($mailOpsInboxes ?? null) ? $mailOpsInboxes : [];
$pageError = isset($mailError) ? (string) $mailError : '';
$limit = (int) $cfg['limit'];
$reserve = (int) $cfg['reserve'];
$ceiling = $limit > 0 ? max(0, $limit - $reserve) : 0;
$pct = $limit > 0 ? min(100, (int) round($sentToday * 100 / max(1, $limit))) : 0;
$digestLabels = [0 => 'Une alerte = un e-mail (comme avant)', 60 => 'Toutes les heures', 120 => 'Toutes les 2 heures', 180 => 'Toutes les 3 heures', 360 => 'Toutes les 6 heures', 720 => 'Toutes les 12 heures', 1440 => 'Une fois par jour'];
$priorityPill = [
    MailBudget::PRIORITY_CRITICAL => 'pa-pill--mint',
    MailBudget::PRIORITY_OPS => 'pa-pill--amber',
    MailBudget::PRIORITY_DIGEST => 'pa-pill--blue',
    MailBudget::PRIORITY_NORMAL => 'pa-pill--slate',
];
$mailerLabels = ['file' => 'Fichier local (aucun envoi réel)', 'smtp' => 'SMTP', 'brevo' => 'Brevo', 'sendgrid' => 'SendGrid', 'mailgun' => 'Mailgun', 'ses' => 'Amazon SES'];
$mailer = (string) ($transport['mailer'] ?? 'file');
$host = (string) ($transport['host'] ?? '');
$isHostinger = str_contains(strtolower($host), 'hostinger');
$maxDay = 1;
foreach ($daily as $d) {
    $maxDay = max($maxDay, (int) $d['total']);
}
$fmt = static function (mixed $raw): string {
    $t = strtotime((string) $raw);

    return $t ? date('d/m H:i', $t) : (string) $raw;
};
$ok = \App\Core\Session::getFlash('success');
$err = \App\Core\Session::getFlash('error');
?>
<div class="pa">
    <div class="pa__frame">
        <header class="pa-hero">
            <p class="pa-crumb">
                <a href="<?= $h(url('admin')) ?>">Administration du site</a>
                <span aria-hidden="true"> / </span>
                Communication
                <span aria-hidden="true"> / </span>
                Envois d’e-mails
            </p>
            <h1 class="pa-hero__title">Envois d’e-mails</h1>
            <p class="pa-hero__lead">
                Le fournisseur limite le nombre d’e-mails par jour. Les messages essentiels (lien d’inscription, code de connexion,
                mot de passe, invitation) partent toujours tout de suite. Les alertes adressées à l’exploitation sont regroupées
                dans un récapitulatif, et les autres e-mails repartent le lendemain quand le quota du jour est atteint.
            </p>
            <div class="pa-hero__actions">
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/system/cron')) ?>">Tâches automatiques</a>
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/settings')) ?>">Paramètres système</a>
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

            <ul class="pa-kpi-strip" style="margin-bottom:1.25rem;">
                <li class="pa-kpi<?= $limit > 0 && $sentToday >= $ceiling ? ' pa-kpi--warn' : '' ?>">
                    <span class="pa-kpi__label">Envoyés aujourd’hui</span>
                    <span class="pa-kpi__value"><?= $sentToday ?><?= $limit > 0 ? ' / ' . $limit : '' ?></span>
                    <span class="pa-kpi__hint"><?= $limit > 0 ? $pct . ' % du quota' : 'Pas de quota réglé' ?></span>
                </li>
                <li class="pa-kpi">
                    <span class="pa-kpi__label">Alertes en attente</span>
                    <span class="pa-kpi__value"><?= $digestPending ?></span>
                    <span class="pa-kpi__hint">Prochain récapitulatif</span>
                </li>
                <li class="pa-kpi<?= $deferred > 0 ? ' pa-kpi--warn' : '' ?>">
                    <span class="pa-kpi__label">Reportés</span>
                    <span class="pa-kpi__value"><?= $deferred ?></span>
                    <span class="pa-kpi__hint">Repartent après minuit</span>
                </li>
                <li class="pa-kpi<?= $failedToday > 0 ? ' pa-kpi--alert' : '' ?>">
                    <span class="pa-kpi__label">Échecs aujourd’hui</span>
                    <span class="pa-kpi__value"><?= $failedToday ?></span>
                    <span class="pa-kpi__hint">Refusés par le fournisseur</span>
                </li>
            </ul>

            <div class="pa-card">
                <p class="pa-card__kicker">Fournisseur</p>
                <h2 class="pa-card__title"><?= $h($mailerLabels[$mailer] ?? $mailer) ?><?= $host !== '' ? ' · ' . $h($host) : '' ?></h2>
                <p class="pa-card__help">
                    Expéditeur : <?= $h((string) ($transport['from'] ?? '')) ?>.
                    Boîte de l’exploitation (alertes regroupées) : <?= $opsInboxes === [] ? 'aucune réglée' : $h(implode(', ', $opsInboxes)) ?>.
                    <?php if ($isHostinger): ?>
                        La boîte Hostinger est limitée à 100 e-mails par jour : un relais d’envoi (Brevo, 300 par jour gratuits) se règle dans le fichier .env du serveur, voir .env.example.
                    <?php endif; ?>
                </p>
            </div>

            <form class="pa-card" method="post" action="<?= $h(url('admin/system/e-mails/reglages')) ?>">
                <?= \App\Core\Csrf::field() ?>
                <p class="pa-card__kicker">Réglages</p>
                <h2 class="pa-card__title">Quota et récapitulatif</h2>

                <div class="pa-field">
                    <label for="mail_daily_limit">Quota quotidien du fournisseur</label>
                    <p class="pa-hint">Hostinger : 100. Gardez une marge (90). 0 = aucun quota.</p>
                    <input id="mail_daily_limit" type="number" name="daily_limit" min="0" max="100000" value="<?= $limit ?>">
                </div>
                <div class="pa-field">
                    <label for="mail_reserve">Réserve pour les messages essentiels</label>
                    <p class="pa-hint">Les e-mails aux membres s’arrêtent à <?= $limit > 0 ? (int) $ceiling : '—' ?> par jour pour garder cette réserve aux codes de connexion et liens d’inscription.</p>
                    <input id="mail_reserve" type="number" name="critical_reserve" min="0" max="1000" value="<?= $reserve ?>">
                </div>
                <div class="pa-field">
                    <label for="mail_digest">Récapitulatif des alertes d’exploitation</label>
                    <p class="pa-hint">Incidents techniques, nouveaux comptes, nouvelles communautés, signalements du mod, alertes sécurité.</p>
                    <select id="mail_digest" name="digest_minutes">
                        <?php foreach (MailBudget::DIGEST_CHOICES as $m): ?>
                            <option value="<?= (int) $m ?>" <?= (int) $cfg['digest_minutes'] === $m ? 'selected' : '' ?>><?= $h($digestLabels[$m] ?? ($m . ' min')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <h3 class="pa-label" style="margin:1.5rem 0 0.5rem;">Volume des 7 derniers jours, par type</h3>
                <p class="pa-hint" style="margin-bottom:0.5rem;">Cochez « Couper » pour ne plus envoyer un type d’e-mail (il reste noté dans le journal). Les messages essentiels ne peuvent pas être coupés.</p>
                <?php if ($events === []): ?>
                    <p class="pa-card__help">Aucun envoi sur la période.</p>
                <?php else: ?>
                    <div class="pa-table-wrap">
                        <table class="pa-table">
                            <thead>
                                <tr><th>Type</th><th>Catégorie</th><th class="pa-num">Envoyés</th><th class="pa-num">Regroupés</th><th class="pa-num">Reportés</th><th class="pa-num">Coupés</th><th class="pa-num">Échecs</th><th>Couper</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($events as $e): ?>
                                    <?php $code = (string) $e['code']; $isMuted = in_array($code, $cfg['muted'], true); ?>
                                    <tr>
                                        <td>
                                            <span class="pa-name"><?= $h((string) $e['label']) ?></span>
                                            <span class="pa-sub"><?= $h($code) ?></span>
                                        </td>
                                        <td><span class="pa-pill <?= $priorityPill[$e['priority']] ?? 'pa-pill--slate' ?>"><?= $h(MailBudget::priorityLabel((string) $e['priority'])) ?></span></td>
                                        <td class="pa-num"><?= (int) $e['sent'] ?></td>
                                        <td class="pa-num"><?= (int) $e['digest'] ?></td>
                                        <td class="pa-num"><?= (int) $e['deferred'] ?></td>
                                        <td class="pa-num"><?= (int) $e['muted'] ?></td>
                                        <td class="pa-num"><?= (int) $e['failed'] ?></td>
                                        <td>
                                            <?php if (MailBudget::canMute($code)): ?>
                                                <label><input type="checkbox" name="muted[]" value="<?= $h($code) ?>" <?= $isMuted ? 'checked' : '' ?>> Couper</label>
                                            <?php else: ?>
                                                <span class="pa-sub">Toujours envoyé</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <div class="pa-actions" style="margin-top:1rem;">
                    <button type="submit" class="pa-btn pa-btn--ink">Enregistrer</button>
                </div>
            </form>

            <?php if ($digestPending > 0): ?>
                <form class="pa-card" method="post" action="<?= $h(url('admin/system/e-mails/recapitulatif')) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <p class="pa-card__kicker">Récapitulatif</p>
                    <h2 class="pa-card__title"><?= $digestPending ?> alerte(s) en attente</h2>
                    <div class="pa-actions">
                        <button type="submit" class="pa-btn pa-btn--line">Envoyer le récapitulatif maintenant</button>
                    </div>
                </form>
            <?php endif; ?>

            <h3 class="pa-label" style="margin:1.5rem 0 0.5rem;">14 derniers jours</h3>
            <div class="pa-table-wrap">
                <table class="pa-table">
                    <thead><tr><th>Jour</th><th class="pa-num">Envoyés</th><th class="pa-num">Tous (regroupés, reportés…)</th><th style="width:45%;"></th></tr></thead>
                    <tbody>
                        <?php for ($i = 0; $i < 14; $i++): ?>
                            <?php $day = date('Y-m-d', strtotime('-' . $i . ' days')); $d = $daily[$day] ?? ['sent' => 0, 'total' => 0]; ?>
                            <tr>
                                <td><?= $h(date('d/m', strtotime($day))) ?></td>
                                <td class="pa-num"><?= (int) $d['sent'] ?></td>
                                <td class="pa-num"><?= (int) $d['total'] ?></td>
                                <td>
                                    <span style="display:block;height:8px;border-radius:4px;background:var(--pa-line);overflow:hidden;">
                                        <span style="display:block;height:100%;width:<?= (int) round(((int) $d['sent']) * 100 / $maxDay) ?>%;background:<?= $limit > 0 && (int) $d['sent'] >= $limit ? 'var(--pa-tone-rose-fg)' : 'var(--pa-mint-bright)' ?>;"></span>
                                    </span>
                                </td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($failures !== []): ?>
                <h3 class="pa-label" style="margin:1.5rem 0 0.5rem;">Derniers échecs</h3>
                <div class="pa-table-wrap">
                    <table class="pa-table">
                        <thead><tr><th>Quand</th><th>Type</th><th>Destinataire</th><th>Erreur du fournisseur</th></tr></thead>
                        <tbody>
                            <?php foreach ($failures as $f): ?>
                                <tr>
                                    <td><?= $h($fmt($f['created_at'] ?? '')) ?></td>
                                    <td><?= $h(MailBudget::label((string) ($f['event_code'] ?? ''))) ?></td>
                                    <td><?= $h((string) ($f['recipient'] ?? '')) ?></td>
                                    <td><span class="pa-sub"><?= $h(mb_substr((string) ($f['error_message'] ?? ''), 0, 200)) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
