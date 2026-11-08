<?php
/**
 * Compte connecté : « Vous candidatez en tant que … » (portrait et nom repris du compte, non modifiables ici, avec un
 * lien vers le profil), puis les seuls champs que le profil ne connaît pas encore. Ces réponses complètent le profil.
 *
 * @var array<string, mixed> $enlistmentContext
 * @var string $accountTheme 'dark' (page d'enrôlement) ou 'light' (pages du portail)
 * @var bool $accountNeedsDiscord le pseudo Discord est demandé s'il manque (candidature par Discord)
 */
declare(strict_types=1);

$accountTheme = ($accountTheme ?? 'dark') === 'light' ? 'light' : 'dark';
$acc = is_array($enlistmentContext['account'] ?? null) ? $enlistmentContext['account'] : [];
$accName = (string) ($acc['display_name'] ?? '');
$accPortrait = (string) ($acc['portrait_url'] ?? '');
$accProfileUrl = (string) ($acc['profile_url'] ?? url('account'));
$accMissing = is_array($acc['missing'] ?? null) ? $acc['missing'] : [];
$accSuggestFirst = (string) ($acc['suggest_first_name'] ?? '');
$accSuggestLast = (string) ($acc['suggest_last_name'] ?? '');
$accountNeedsDiscord = !empty($accountNeedsDiscord);
if (!$accountNeedsDiscord) {
    $accMissing = array_values(array_diff($accMissing, ['discord_handle']));
}
$accFields = [
    'first_name' => ['profile_first_name', 'Prénom', 'text', 'given-name', $accSuggestFirst, true],
    'last_name' => ['profile_last_name', 'Nom', 'text', 'family-name', $accSuggestLast, true],
    'birth_date' => ['profile_birth_date', 'Date de naissance', 'date', 'bday', '', false],
    'nationality' => ['profile_nationality', 'Nationalité', 'text', 'off', '', false],
    'discord_handle' => ['profile_discord_handle', 'Pseudo Discord', 'text', 'off', '', true],
];
$accAsk = [];
foreach ($accFields as $key => $def) {
    if (in_array($key, $accMissing, true)) {
        $accAsk[$key] = $def;
    }
}
$accPortraitSafe = $accPortrait !== '' ? $accPortrait : url('assets/images/inconnu.svg');
?>
<?php if ($accountTheme === 'dark'): ?>
<div class="ce-identity" role="status">
    <img class="ce-identity__photo" src="<?= htmlspecialchars($accPortraitSafe, ENT_QUOTES, 'UTF-8') ?>" alt="" width="72" height="72" loading="lazy" decoding="async">
    <div class="ce-identity__body">
        <p class="ce-identity__kicker">Vous candidatez en tant que</p>
        <p class="ce-identity__name"><?= htmlspecialchars($accName, ENT_QUOTES, 'UTF-8') ?></p>
        <p class="ce-identity__help">Nom, portrait et e-mail sont repris de votre compte et ne se modifient pas ici. <a href="<?= htmlspecialchars($accProfileUrl, ENT_QUOTES, 'UTF-8') ?>">Voir mon profil</a></p>
    </div>
</div>
<?php if ($accAsk !== []): ?>
<p class="ce-help ce-identity__missing-note">Votre profil ne contient pas encore ces informations : elles le compléteront.</p>
<div class="ce-field-grid ce-identity__missing">
    <?php foreach ($accAsk as $def): ?>
    <div class="ce-field">
        <label class="ce-label" for="<?= htmlspecialchars($def[0], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($def[1], ENT_QUOTES, 'UTF-8') ?><?php if (!$def[5]): ?> <span class="ce-label-optional">(facultatif)</span><?php endif; ?></label>
        <input type="<?= htmlspecialchars($def[2], ENT_QUOTES, 'UTF-8') ?>" name="<?= htmlspecialchars($def[0], ENT_QUOTES, 'UTF-8') ?>" id="<?= htmlspecialchars($def[0], ENT_QUOTES, 'UTF-8') ?>" class="input-field track-field" maxlength="100" autocomplete="<?= htmlspecialchars($def[3], ENT_QUOTES, 'UTF-8') ?>"<?= $def[5] ? ' required' : '' ?> value="<?= htmlspecialchars($def[4], ENT_QUOTES, 'UTF-8') ?>">
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php else: ?>
<div class="mb-6 flex items-center gap-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-4" role="status">
    <img class="h-16 w-16 shrink-0 rounded-xl border border-emerald-200 bg-white object-cover" src="<?= htmlspecialchars($accPortraitSafe, ENT_QUOTES, 'UTF-8') ?>" alt="" width="64" height="64" loading="lazy" decoding="async">
    <div class="min-w-0">
        <p class="text-[10px] font-black uppercase tracking-widest text-emerald-800">Vous candidatez en tant que</p>
        <p class="truncate text-lg font-black text-slate-900"><?= htmlspecialchars($accName, ENT_QUOTES, 'UTF-8') ?></p>
        <p class="text-xs leading-relaxed text-slate-600">Nom, portrait et e-mail sont repris de votre compte et ne se modifient pas ici. <a class="font-semibold text-emerald-800 underline" href="<?= htmlspecialchars($accProfileUrl, ENT_QUOTES, 'UTF-8') ?>">Voir mon profil</a></p>
    </div>
</div>
<?php if ($accAsk !== []): ?>
<p class="mb-3 text-xs text-slate-600">Votre profil ne contient pas encore ces informations : elles le compléteront.</p>
<div class="mb-6 grid gap-4 sm:grid-cols-2">
    <?php foreach ($accAsk as $def): ?>
    <div>
        <label class="mb-1 block text-xs font-bold text-slate-700" for="<?= htmlspecialchars($def[0], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($def[1], ENT_QUOTES, 'UTF-8') ?><?php if (!$def[5]): ?> <span class="font-normal text-slate-500">(facultatif)</span><?php endif; ?></label>
        <input type="<?= htmlspecialchars($def[2], ENT_QUOTES, 'UTF-8') ?>" name="<?= htmlspecialchars($def[0], ENT_QUOTES, 'UTF-8') ?>" id="<?= htmlspecialchars($def[0], ENT_QUOTES, 'UTF-8') ?>" maxlength="100" autocomplete="<?= htmlspecialchars($def[3], ENT_QUOTES, 'UTF-8') ?>"<?= $def[5] ? ' required' : '' ?> value="<?= htmlspecialchars($def[4], ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm">
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>
