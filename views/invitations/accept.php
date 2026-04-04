<?php
/** @var array $invitation */
/** @var string $token */
?>
<div class="max-w-md mx-auto px-4 py-12">
    <h1 class="text-2xl font-black text-white mb-2">Rejoindre la communauté</h1>
    <p class="text-neutral-400 text-sm mb-6">Invitation pour <strong class="text-white"><?= htmlspecialchars((string) $invitation['email']) ?></strong></p>
    <?php $err = \App\Core\Session::getFlash('error'); if ($err): ?>
        <p class="text-red-400 text-sm mb-4"><?= htmlspecialchars($err) ?></p>
    <?php endif; ?>
    <form method="post" action="<?= url('invitations/accept') ?>" class="space-y-4">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <p class="text-xs text-neutral-500">Si vous avez déjà un compte COMSPEC avec cet email, saisissez votre mot de passe habituel pour dupliquer le profil dans cette communauté. Sinon, choisissez un nouveau mot de passe.</p>
        <div>
            <label class="block text-xs font-bold text-neutral-500 mb-1">Mot de passe</label>
            <input type="password" name="password" required minlength="8" class="w-full bg-neutral-900 border border-white/10 rounded px-3 py-2 text-sm text-white">
        </div>
        <div>
            <label class="block text-xs font-bold text-neutral-500 mb-1">Confirmation</label>
            <input type="password" name="password_confirmation" required minlength="8" class="w-full bg-neutral-900 border border-white/10 rounded px-3 py-2 text-sm text-white">
        </div>
        <button type="submit" class="w-full py-2.5 rounded bg-emerald-600 hover:bg-emerald-500 text-sm font-bold text-white">Accepter et continuer</button>
    </form>
</div>
