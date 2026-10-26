<?php
/** @var array<string, mixed> $payment */
/** @var string $description */
/** @var string $amountLabel */
/** @var string $paypalUrl */
/** @var string $qrUrl */
/** @var string $tenantName */
/** @var string $csrfToken */
/** @var string|null $flashError */
/** @var string|null $flashSuccess */
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$htmlLang = function_exists('html_lang') ? html_lang() : 'fr';
$reference = (string) ($payment['reference'] ?? '');
$status = (string) ($payment['status'] ?? 'awaiting');
$isOpen = in_array($status, ['awaiting', 'declared'], true);
$statusLabels = [
    'awaiting' => ['À payer', 'pay-badge--wait'],
    'declared' => ['Paiement déclaré, vérification en cours', 'pay-badge--info'],
    'activated' => ['Paiement confirmé', 'pay-badge--ok'],
    'rejected' => ['Paiement non confirmé', 'pay-badge--ko'],
    'refunded' => ['Paiement remboursé', 'pay-badge--info'],
    'cancelled' => ['Demande annulée', 'pay-badge--ko'],
];
[$statusText, $statusClass] = $statusLabels[$status] ?? ['—', 'pay-badge--wait'];
$periodEnds = trim((string) ($payment['period_ends_at'] ?? ''));
?>
<!DOCTYPE html>
<html lang="<?= $h($htmlLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $h((string) ($title ?? 'Paiement — Athena')) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link href="<?= $h(asset_url('assets/css/payment-page.css')) ?>" rel="stylesheet">
</head>
<body class="pay-body">
<div class="pay-shell">
    <header class="pay-top">
        <a class="pay-brand" href="<?= $h(url('dashboard')) ?>">ATHENA</a>
        <span class="pay-secure">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
            Paiement via PayPal
        </span>
    </header>

    <main class="pay-card">
        <?php if (!empty($flashSuccess)): ?>
            <p class="pay-flash pay-flash--ok" role="status"><?= $h((string) $flashSuccess) ?></p>
        <?php endif; ?>
        <?php if (!empty($flashError)): ?>
            <p class="pay-flash pay-flash--ko" role="alert"><?= $h((string) $flashError) ?></p>
        <?php endif; ?>

        <section class="pay-summary">
            <div>
                <p class="pay-kicker">Votre achat</p>
                <h1 class="pay-title"><?= $h($description) ?></h1>
                <?php if ($tenantName !== ''): ?>
                    <p class="pay-sub">Pour <strong><?= $h($tenantName) ?></strong></p>
                <?php endif; ?>
            </div>
            <div class="pay-amount">
                <span class="pay-amount__value"><?= $h($amountLabel) ?></span>
                <span class="pay-badge <?= $h($statusClass) ?>"><?= $h($statusText) ?></span>
            </div>
        </section>

        <section class="pay-ref">
            <div>
                <p class="pay-kicker">Votre référence</p>
                <p class="pay-ref__code" id="pay-ref-code"><?= $h($reference) ?></p>
            </div>
            <button type="button" class="pay-btn pay-btn--ghost" data-copy="<?= $h($reference) ?>">Copier</button>
        </section>

        <?php if ($status === 'activated'): ?>
            <section class="pay-done">
                <p>Merci ! Votre paiement a été reçu<?= $periodEnds !== '' ? ' et votre formule est active jusqu’au <strong>' . $h(date('d/m/Y', (int) strtotime($periodEnds))) . '</strong>' : '' ?>.</p>
                <a class="pay-btn pay-btn--dark" href="<?= $h(url('dashboard')) ?>">Retour au tableau de bord</a>
            </section>
        <?php elseif ($status === 'refunded' || $status === 'cancelled'): ?>
            <section class="pay-done pay-done--ko">
                <p><?= $status === 'refunded'
                    ? 'Ce paiement vous a été remboursé sur PayPal.'
                    : 'L’équipe Athena a annulé cette demande de paiement.' ?><?= trim((string) ($payment['admin_note'] ?? '')) !== '' ? ' Message de l’équipe : ' . $h((string) $payment['admin_note']) : '' ?></p>
                <a class="pay-btn pay-btn--dark" href="<?= $h(url('platform/upgrade')) ?>">Voir les formules</a>
            </section>
        <?php elseif ($status === 'rejected'): ?>
            <section class="pay-done pay-done--ko">
                <p>Nous n’avons pas pu rapprocher ce paiement de votre demande.<?= trim((string) ($payment['admin_note'] ?? '')) !== '' ? ' Message de l’équipe : ' . $h((string) $payment['admin_note']) : '' ?></p>
                <a class="pay-btn pay-btn--dark" href="<?= $h(url('platform/upgrade')) ?>">Recommencer</a>
            </section>
        <?php else: ?>
            <ol class="pay-steps">
                <li class="pay-step">
                    <span class="pay-step__num">1</span>
                    <div class="pay-step__body">
                        <h2>Payez <?= $h($amountLabel) ?> avec PayPal</h2>
                        <p>Le bouton ouvre PayPal avec le montant déjà rempli. <strong>Indiquez votre référence <?= $h($reference) ?> dans la note du paiement.</strong> Choisissez de préférence « Entre proches » ou « Biens et services » selon votre situation.</p>
                        <div class="pay-paypal">
                            <a class="pay-btn pay-btn--paypal" href="<?= $h($paypalUrl) ?>" target="_blank" rel="noopener noreferrer">
                                Payer avec <span class="pay-paypal__logo"><b>Pay</b><i>Pal</i></span>
                            </a>
                            <figure class="pay-qr">
                                <img src="<?= $h($qrUrl) ?>" alt="QR code pour payer avec PayPal depuis votre téléphone" width="140" height="140" loading="lazy">
                                <figcaption>Ou scannez avec votre téléphone</figcaption>
                            </figure>
                        </div>
                        <p class="pay-small">Lien PayPal : <a href="<?= $h($paypalUrl) ?>" target="_blank" rel="noopener noreferrer"><?= $h($paypalUrl) ?></a></p>
                    </div>
                </li>
                <li class="pay-step">
                    <span class="pay-step__num">2</span>
                    <div class="pay-step__body">
                        <h2>Indiquez votre identifiant de transaction</h2>
                        <p>PayPal vous donne un <strong>identifiant de transaction</strong> (17 caractères, dans le reçu ou l’e-mail de confirmation PayPal). Collez-le ici : l’équipe Athena est prévenue et active votre achat après vérification.</p>
                        <form method="post" action="<?= $h(url('paiement/' . rawurlencode($reference) . '/declarer')) ?>" class="pay-form">
                            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                            <label class="pay-label" for="pay-txn">Identifiant de transaction PayPal</label>
                            <input class="pay-input" id="pay-txn" name="transaction_ref" required minlength="6" maxlength="40" autocomplete="off" spellcheck="false"
                                   placeholder="Ex. 5TY05013RG002845M" value="<?= $h((string) ($payment['transaction_ref'] ?? '')) ?>">
                            <label class="pay-label" for="pay-note">Message (facultatif)</label>
                            <textarea class="pay-input" id="pay-note" name="payer_note" rows="2" maxlength="500" placeholder="Nom du compte PayPal utilisé, précision…"><?= $h((string) ($payment['payer_note'] ?? '')) ?></textarea>
                            <button type="submit" class="pay-btn pay-btn--dark"><?= $status === 'declared' ? 'Mettre à jour ma déclaration' : 'J’ai payé, envoyer' ?></button>
                        </form>
                    </div>
                </li>
                <li class="pay-step">
                    <span class="pay-step__num">3</span>
                    <div class="pay-step__body">
                        <h2>Activation</h2>
                        <p>Dès que le paiement est vérifié, la formule est activée sur votre communauté et vous recevez un e-mail. Gardez cette page : son adresse contient votre référence.</p>
                    </div>
                </li>
            </ol>
        <?php endif; ?>
    </main>

    <footer class="pay-foot">
        <a href="<?= $h(url('dashboard')) ?>">Retour à Athena</a>
        <span>Une question ? Répondez à l’e-mail de confirmation ou contactez l’équipe Athena avec votre référence.</span>
    </footer>
</div>
<script>
document.querySelectorAll('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var text = btn.getAttribute('data-copy') || '';
        var done = function () { btn.textContent = 'Copié'; setTimeout(function () { btn.textContent = 'Copier'; }, 1800); };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done, done);
        } else {
            done();
        }
    });
});
</script>
</body>
</html>
