<?php
declare(strict_types=1);

/** @var string|null $paypalUrl lien validé (null : non réglé) */
/** @var string $paypalRaw */
/** @var bool $paypalEnabled */
/** @var bool $settingsReady */
/** @var bool $stripeReady */

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$flashErr = \App\Core\Session::getFlash('error');
$flashOk = \App\Core\Session::getFlash('success');
$live = $paypalUrl !== null && !empty($paypalEnabled);
?>
<div class="max-w-4xl mx-auto px-4 sm:px-6 py-10 space-y-6">
    <header class="pa-head">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-amber-800 mb-1">Administration du site — pas une communauté</p>
        <h1 class="text-2xl font-black text-slate-900">Dons</h1>
        <p class="mt-2 text-sm text-slate-600 leading-relaxed max-w-3xl">
            Les dons sont proposés sur la page publique <a href="<?= $h(url('soutenir-atak')) ?>" class="font-semibold text-emerald-700 hover:underline" target="_blank" rel="noopener">Soutenir</a>
            (liée depuis le pied de page, l’accueil et la carte ATAK). Deux moyens possibles : PayPal (lien de don, réglé ici) et carte bancaire via Stripe (avec badge donateur automatique).
        </p>
    </header>

    <?php if ($flashErr): ?><p class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert"><?= $h($flashErr) ?></p><?php endif; ?>
    <?php if ($flashOk): ?><p class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status"><?= $h($flashOk) ?></p><?php endif; ?>
    <?php if (empty($settingsReady)): ?>
        <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">La table des réglages du site n’existe pas encore : lancez <strong>run-migrations.php</strong>, ou renseignez <code>PAYPAL_DONATE_URL</code> dans le fichier .env.</p>
    <?php endif; ?>

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="rounded-2xl border <?= $live ? 'border-emerald-200 bg-emerald-50/60' : 'border-slate-200 bg-white' ?> p-5">
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">PayPal</p>
            <p class="mt-1 text-lg font-black <?= $live ? 'text-emerald-800' : 'text-slate-700' ?>"><?= $live ? 'Affiché au public' : ($paypalUrl !== null ? 'Réglé, mais masqué' : 'Non réglé') ?></p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Carte bancaire (Stripe)</p>
            <p class="mt-1 text-lg font-black text-slate-700"><?= !empty($stripeReady) ? 'Actif' : 'Non configuré (clé Stripe absente)' ?></p>
        </div>
    </div>

    <form method="post" action="<?= $h(url('admin/system/dons')) ?>" class="rounded-2xl border border-slate-200 bg-white p-6 space-y-5">
        <?= \App\Core\Csrf::field() ?>
        <h2 class="text-sm font-black uppercase tracking-[0.18em] text-slate-500">Lien de don PayPal</h2>
        <div>
            <label for="paypal-url" class="block text-sm font-bold text-slate-800">Lien</label>
            <input id="paypal-url" name="paypal_url" type="url" inputmode="url" value="<?= $h($paypalRaw !== '' ? $paypalRaw : ($paypalUrl ?? '')) ?>" placeholder="https://www.paypal.com/donate/?hosted_button_id=… ou https://paypal.me/votrenom" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm">
            <p class="mt-1 text-xs text-slate-500 leading-relaxed">
                Où le trouver : dans PayPal, <strong>Outils → Bouton « Faire un don »</strong> (copiez le lien de partage), ou votre lien <strong>paypal.me</strong>.
                C’est le lien contenu dans le QR code PayPal que vous avez peut-être déjà. Seuls les liens paypal.com et paypal.me sont acceptés. Laissez vide pour retirer PayPal.
            </p>
        </div>
        <label class="flex items-start gap-2 text-sm text-slate-700">
            <input type="checkbox" name="paypal_enabled" value="1" class="mt-1 rounded border-slate-300"<?= !empty($paypalEnabled) ? ' checked' : '' ?>>
            <span>Afficher le don PayPal (bouton et QR code) sur la page Soutenir</span>
        </label>
        <div class="flex flex-wrap gap-2">
            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Enregistrer</button>
            <?php if ($paypalUrl !== null): ?>
                <a href="<?= $h($paypalUrl) ?>" target="_blank" rel="noopener noreferrer" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Tester le lien ↗</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($paypalUrl !== null): ?>
        <section class="rounded-2xl border border-slate-200 bg-white p-6 flex flex-col gap-4 sm:flex-row sm:items-center">
            <img src="<?= $h(url('soutenir-atak/paypal-qr.png') . '?apercu=1&v=' . substr(md5($paypalUrl), 0, 8)) ?>" width="160" height="160" alt="QR code généré pour le lien PayPal" class="h-40 w-40 rounded-xl border border-slate-200 bg-white p-1.5">
            <div class="text-sm text-slate-600 leading-relaxed">
                <p class="font-bold text-slate-900">QR code généré par Athena</p>
                <p>Il pointe vers <span class="font-mono break-all"><?= $h($paypalUrl) ?></span>. Scannez-le avec un téléphone pour vérifier qu’il ouvre bien votre page de don PayPal.</p>
                <p class="mt-1">Clic droit → « Enregistrer l’image » pour l’utiliser ailleurs (Discord, affiche, briefing).</p>
            </div>
        </section>
    <?php endif; ?>
</div>
