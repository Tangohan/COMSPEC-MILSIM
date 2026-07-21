<?php
declare(strict_types=1);
/** @var array<string,mixed> $tenant */
/** @var list<array{id:int,type:string,label:string,options:list<string>,required:bool}> $discordQuestions */
$tenant = $tenant ?? [];
$formAction = $formAction ?? url('enlistment');
$tenantName = trim((string) ($tenant['name'] ?? 'Communauté'));
$discordInviteUrl = trim((string) ($discordInviteUrl ?? ''));
$discordQuestions = is_array($discordQuestions ?? null) ? $discordQuestions : [];
?>
<div class="max-w-2xl mx-auto px-4 py-12">
    <h1 class="text-2xl font-black uppercase tracking-tight text-slate-900 mb-2">Rejoindre <?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?> sur Discord</h1>
    <p class="text-sm text-slate-500 mb-6">Le recrutement de cette communauté se fait via un échange sur Discord. Ouvrez le serveur, puis indiquez votre pseudo et répondez aux quelques questions ci-dessous pour créer votre fiche de candidature.</p>

    <?php if ($discordInviteUrl !== ''): ?>
    <div class="mb-6 rounded-2xl border border-indigo-200 bg-indigo-50 p-5">
        <p class="text-[10px] font-black uppercase tracking-widest text-indigo-800 mb-2">Étape 1</p>
        <p class="text-sm text-indigo-950 leading-relaxed mb-3">Ouvrez le Discord de la communauté pour vous présenter à l’équipe recrutement.</p>
        <a href="<?= htmlspecialchars($discordInviteUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="inline-flex items-center rounded-xl bg-indigo-700 px-4 py-2.5 text-xs font-black uppercase tracking-widest text-white shadow-sm transition hover:bg-indigo-800">Ouvrir le Discord →</a>
    </div>
    <?php else: ?>
    <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-5">
        <p class="text-sm text-amber-950 leading-relaxed">Le lien Discord de cette communauté n’est pas encore renseigné. Contactez l’équipe recrutement après avoir déposé votre candidature ci-dessous.</p>
    </div>
    <?php endif; ?>

    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5">
        <p class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">Étape 2</p>
        <p class="text-sm text-slate-700 leading-relaxed">Complétez la fiche ci-dessous. Elle nous permet de caler un rendez-vous Discord avec vous.</p>
    </div>

    <form method="post" action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') ?>" class="space-y-5 bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
        <?= \App\Core\Csrf::field() ?>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1.5" for="discord_pseudo">Pseudo Discord <span class="text-rose-600">*</span></label>
            <input type="text" id="discord_pseudo" name="discord_pseudo" required maxlength="100" placeholder="pseudo#0000 ou @pseudo" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1.5" for="display_name">Votre nom / pseudo affiché <span class="text-rose-600">*</span></label>
            <input type="text" id="display_name" name="display_name" required maxlength="100" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1.5" for="email">Email de contact <span class="text-rose-600">*</span></label>
            <input type="email" id="email" name="email" required maxlength="255" placeholder="pour le suivi de votre candidature" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100">
        </div>

        <?php foreach ($discordQuestions as $q): ?>
            <?php
            $fieldId = 'discord_q_' . (int) $q['id'];
            $fieldName = 'discord_q_' . (int) $q['id'];
            $required = !empty($q['required']);
            $type = (string) ($q['type'] ?? 'open');
            ?>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5" for="<?= htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars((string) $q['label'], ENT_QUOTES, 'UTF-8') ?>
                    <?php if ($required): ?><span class="text-rose-600">*</span><?php endif; ?>
                </label>
                <?php if ($type === 'select'): ?>
                    <select id="<?= htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8') ?>" name="<?= htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') ?>" <?= $required ? 'required' : '' ?> class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100">
                        <option value="">— Sélectionnez —</option>
                        <?php foreach ((array) ($q['options'] ?? []) as $opt): ?>
                            <option value="<?= htmlspecialchars((string) $opt, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $opt, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ($type === 'closed'): ?>
                    <div class="flex gap-4 pt-1">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="radio" name="<?= htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') ?>" value="Oui" <?= $required ? 'required' : '' ?>> Oui</label>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="radio" name="<?= htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') ?>" value="Non"> Non</label>
                    </div>
                <?php elseif ($type === 'open'): ?>
                    <textarea id="<?= htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8') ?>" name="<?= htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') ?>" rows="3" <?= $required ? 'required' : '' ?> class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"></textarea>
                <?php else: ?>
                    <input type="text" id="<?= htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8') ?>" name="<?= htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') ?>" maxlength="4000" <?= $required ? 'required' : '' ?> class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100">
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <button type="submit" class="w-full py-3.5 bg-slate-900 text-white text-xs font-black uppercase tracking-widest rounded-xl hover:bg-indigo-700">Envoyer ma candidature</button>
    </form>
</div>
