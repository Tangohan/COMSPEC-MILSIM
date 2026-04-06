<?php
declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Session;

/** @var array<string, string> $gdprRequestKinds */
$gdprRequestKinds = $gdprRequestKinds ?? [];
$privacyInboxConfigured = !empty($privacyInboxConfigured);

$error = Session::getFlash('error');
$success = Session::getFlash('success');
$mailto = legal_public_contact_email();
?>
<div class="max-w-2xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 tracking-tight uppercase mb-2">Exercer vos droits sur vos données</h1>
    <p class="text-sm text-slate-500 mb-8">Envoyez une demande relative à vos données personnelles (accès, rectification, effacement, etc.). Nous vous répondrons sur l’adresse e-mail que vous indiquez ci-dessous.</p>

    <?php if ($error): ?>
        <?php $flash_variant = 'error'; $flash_message = (string) $error; require base_path('views/partials/flash_message.php'); ?>
    <?php endif; ?>
    <?php if ($success): ?>
        <?php $flash_variant = 'success'; $flash_message = (string) $success; require base_path('views/partials/flash_message.php'); ?>
    <?php endif; ?>

    <?php if (!$privacyInboxConfigured): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 text-amber-950 px-4 py-3 text-sm mb-8 leading-relaxed">
            <p class="font-semibold mb-1">Envoi en ligne non configuré</p>
            <p class="text-amber-900/90">L’administrateur du site n’a pas encore renseigné la boîte de réception des demandes. Vous pouvez écrire directement
                <?php if ($mailto !== null): ?>
                    à <a href="mailto:<?= htmlspecialchars($mailto, ENT_QUOTES, 'UTF-8') ?>?subject=<?= rawurlencode('Demande relative à mes données personnelles') ?>" class="font-semibold underline hover:no-underline"><?= htmlspecialchars($mailto, ENT_QUOTES, 'UTF-8') ?></a>
                <?php else: ?>
                    aux coordonnées publiées dans les <a href="<?= htmlspecialchars(url('mentions-legales')) ?>" class="font-semibold underline hover:no-underline">mentions légales</a>
                <?php endif; ?>
                ou contacter les administrateurs de votre communauté.</p>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= htmlspecialchars(url('demande-donnees')) ?>" class="relative space-y-6">
        <?= Csrf::field() ?>

        <div class="absolute -left-[9999px] top-auto w-px h-px overflow-hidden" aria-hidden="true">
            <label for="company_website">Laissez ce champ vide</label>
            <input type="text" name="company_website" id="company_website" value="" tabindex="-1" autocomplete="off">
        </div>

        <div>
            <label for="request_kind" class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">Type de demande</label>
            <select name="request_kind" id="request_kind" required class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none">
                <option value="" disabled selected>Choisissez…</option>
                <?php foreach ($gdprRequestKinds as $key => $label): ?>
                    <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="from_email" class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">Adresse e-mail de réponse</label>
            <input type="email" name="from_email" id="from_email" required maxlength="254" autocomplete="email"
                   class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none"
                   placeholder="vous@exemple.com">
        </div>

        <div>
            <label for="full_name" class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">Nom ou pseudonyme utilisé sur le portail <span class="text-slate-400 font-normal normal-case tracking-normal">(facultatif)</span></label>
            <input type="text" name="full_name" id="full_name" maxlength="160" autocomplete="name"
                   class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none">
        </div>

        <div>
            <label for="community_hint" class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">Communauté concernée <span class="text-slate-400 font-normal normal-case tracking-normal">(facultatif)</span></label>
            <input type="text" name="community_hint" id="community_hint" maxlength="200"
                   class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none"
                   placeholder="Nom de l’unité ou indice pour vous identifier">
        </div>

        <div>
            <label for="message" class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">Votre demande</label>
            <textarea name="message" id="message" required rows="6" maxlength="4000" minlength="10"
                      class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none resize-y"
                      placeholder="Décrivez précisément ce que vous souhaitez (par exemple : obtenir une copie de vos données, corriger une information, supprimer votre compte…)."></textarea>
            <p class="mt-1.5 text-xs text-slate-400">Minimum 10 caractères, maximum 4&nbsp;000.</p>
        </div>

        <button type="submit" <?= $privacyInboxConfigured ? '' : 'disabled' ?>
                class="w-full rounded-xl bg-slate-900 text-white text-[11px] font-black uppercase tracking-widest py-3.5 hover:bg-emerald-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-slate-900">
            Envoyer la demande
        </button>
    </form>

    <p class="mt-8 text-xs text-slate-500 leading-relaxed">
        Pour rappel : les administrateurs de votre communauté peuvent aussi traiter certaines demandes qui ne concernent que l’activité interne de cette communauté. En cas d’urgence liée à la sécurité de votre compte, changez votre mot de passe et contactez le support selon les mentions légales.
    </p>

    <?php require base_path('views/partials/legal_crosslinks.php'); ?>
</div>
