<?php
$user = $user ?? [];
$errors = $errors ?? [];
$otpErrors = $otpErrors ?? [];
$success = $success ?? null;
$error = $error ?? null;
$hasOtpColumn = !empty($hasOtpColumn ?? false);
$loginOtpForcedByRole = !empty($loginOtpForcedByRole ?? false);
$emailLoginOtpEnabled = !empty($emailLoginOtpEnabled ?? false);
?>
<div class="relative mx-auto max-w-2xl px-4 py-10 sm:px-6 lg:px-8 lg:py-14">
    <div class="mb-8">
        <p class="text-[11px] font-black uppercase tracking-[0.3em] text-emerald-700/90">Compte</p>
        <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-900">Adresse e-mail</h1>
        <p class="mt-2 text-slate-600">Adresse utilisée pour vous connecter et pour recevoir le code de double vérification, si vous l’activez.</p>
    </div>

    <?php if ($success): ?>
    <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50/90 px-4 py-3 text-sm font-medium text-emerald-900"><?= htmlspecialchars((string) $success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-900"><?= htmlspecialchars((string) $error) ?></div>
    <?php endif; ?>

    <div class="space-y-8">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="mail-change-heading">
            <h2 id="mail-change-heading" class="text-lg font-black text-slate-900">Modifier l’adresse</h2>
            <p class="mt-1 text-sm text-slate-600">Une confirmation par votre mot de passe actuel est obligatoire.</p>
            <form method="post" action="<?= url('account/mail') ?>" class="mt-6 space-y-4">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="account_mail_section" value="email_change">
                <div>
                    <label for="email" class="mb-1 block text-sm font-medium text-slate-700">Nouvelle adresse e-mail</label>
                    <input type="email" name="email" id="email" value="<?= htmlspecialchars((string) ($user['email'] ?? '')) ?>" required class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-slate-900 focus:ring-2 focus:ring-slate-900" autocomplete="email">
                    <?php if (!empty($errors['email'])): foreach ($errors['email'] as $e): ?>
                    <p class="mt-1 text-sm text-red-600"><?= htmlspecialchars($e) ?></p>
                    <?php endforeach; endif; ?>
                </div>
                <div>
                    <label for="email_confirmation" class="mb-1 block text-sm font-medium text-slate-700">Confirmer l’adresse e-mail</label>
                    <input type="email" name="email_confirmation" id="email_confirmation" value="<?= htmlspecialchars((string) ($user['email'] ?? '')) ?>" required class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-slate-900 focus:ring-2 focus:ring-slate-900" autocomplete="email">
                    <?php if (!empty($errors['email_confirmation'])): foreach ($errors['email_confirmation'] as $e): ?>
                    <p class="mt-1 text-sm text-red-600"><?= htmlspecialchars($e) ?></p>
                    <?php endforeach; endif; ?>
                </div>
                <div>
                    <label for="password" class="mb-1 block text-sm font-medium text-slate-700">Mot de passe actuel</label>
                    <input type="password" name="password" id="password" required class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-slate-900 focus:ring-2 focus:ring-slate-900" autocomplete="current-password">
                    <?php if (!empty($errors['password'])): foreach ($errors['password'] as $e): ?>
                    <p class="mt-1 text-sm text-red-600"><?= htmlspecialchars($e) ?></p>
                    <?php endforeach; endif; ?>
                </div>
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-slate-900 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-slate-800 sm:w-auto">Mettre à jour l’adresse</button>
            </form>
        </section>

        <?php if ($hasOtpColumn): ?>
        <section class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm ring-1 ring-slate-900/[0.04]" aria-labelledby="mail-otp-heading">
            <div class="border-l-4 border-l-emerald-600 bg-gradient-to-br from-emerald-50/60 via-white to-white px-5 py-6 sm:px-8 sm:py-7">
                <h2 id="mail-otp-heading" class="text-lg font-black text-slate-900">Double vérification à la connexion</h2>
                <p class="mt-2 text-sm leading-relaxed text-slate-700">
                    Après votre mot de passe, le portail envoie un <strong>code à six chiffres</strong> sur cette adresse e-mail. Utile si vous souhaitez sécuriser davantage votre compte.
                </p>
                <?php if ($loginOtpForcedByRole): ?>
                <p class="mt-4 inline-flex w-fit items-center gap-2 rounded-full border border-emerald-200 bg-emerald-100/90 px-3 py-1.5 text-xs font-bold text-emerald-950">
                    <span class="h-2 w-2 shrink-0 rounded-full bg-emerald-600" aria-hidden="true"></span>
                    Déjà obligatoire pour votre rôle — ce réglage reste actif.
                </p>
                <?php endif; ?>

                <?php if (!empty($otpErrors)): ?>
                <div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                    <?php foreach ($otpErrors as $msgs): ?>
                        <?php if (is_array($msgs)): foreach ($msgs as $m): ?>
                        <p><?= htmlspecialchars((string) $m) ?></p>
                        <?php endforeach; endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if ($loginOtpForcedByRole): ?>
                <p class="mt-6 text-sm leading-relaxed text-slate-600">Vous n’avez pas besoin d’activer quoi que ce soit ici : votre rôle applique déjà cette protection sur toutes vos connexions.</p>
                <?php else: ?>
                <form method="post" action="<?= url('account/mail') ?>" class="mt-6 space-y-5">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="account_mail_section" value="email_login_otp">
                    <div class="rounded-xl border border-slate-200/80 bg-white/90 px-4 py-4 sm:px-5">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input type="checkbox" name="email_login_otp_enabled" value="1" class="mt-1 h-4 w-4 rounded border-slate-300 text-emerald-700 focus:ring-emerald-600" <?= $emailLoginOtpEnabled ? 'checked' : '' ?>>
                            <span class="text-sm leading-relaxed text-slate-800">
                                <strong>Demander un code par e-mail</strong> après chaque saisie du mot de passe (sur tous vos appareils).
                            </span>
                        </label>
                    </div>
                    <div>
                        <label for="otp_toggle_password" class="mb-1 block text-sm font-medium text-slate-700">Mot de passe actuel (pour confirmer le choix)</label>
                        <input type="password" name="otp_toggle_password" id="otp_toggle_password" required class="w-full max-w-md rounded-lg border border-slate-300 px-3 py-2 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/30" autocomplete="current-password">
                        <?php if (!empty($otpErrors['otp_toggle_password'])): foreach ($otpErrors['otp_toggle_password'] as $e): ?>
                        <p class="mt-1 text-sm text-red-600"><?= htmlspecialchars($e) ?></p>
                        <?php endforeach; endif; ?>
                    </div>
                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-emerald-700 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2">
                        Enregistrer la double vérification
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </section>
        <?php else: ?>
        <p class="rounded-xl border border-amber-200 bg-amber-50/80 px-4 py-3 text-sm text-amber-950">La double vérification optionnelle sera disponible après la prochaine mise à jour de la base de données sur ce serveur.</p>
        <?php endif; ?>
    </div>

    <p class="mt-10 text-sm text-slate-600"><a href="<?= url('account') ?>" class="font-semibold text-emerald-800 underline decoration-emerald-300 underline-offset-2 hover:text-emerald-950">Retour aux paramètres du compte</a></p>
</div>
