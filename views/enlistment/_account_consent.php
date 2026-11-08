<?php
/**
 * Une seule case de consentement, avec le nom de la communauté. L'e-mail du compte est toujours transmis à l'équipe :
 * il n'y a plus d'option de masquage.
 *
 * @var string $tenantName
 * @var string $accountTheme 'dark' ou 'light'
 */
declare(strict_types=1);

$accountTheme = ($accountTheme ?? 'dark') === 'light' ? 'light' : 'dark';
$consentTenant = htmlspecialchars((string) ($tenantName ?? ''), ENT_QUOTES, 'UTF-8');
?>
<?php if ($accountTheme === 'dark'): ?>
<div class="ce-consent">
    <label class="ce-check" for="consent_data_sharing">
        <input type="checkbox" name="consent_data_sharing" value="1" id="consent_data_sharing" required>
        <span>J’accepte que mon dossier, mon nom et l’e-mail de mon compte soient transmis à l’équipe de <strong><?= $consentTenant ?></strong>.</span>
    </label>
</div>
<?php else: ?>
<div class="rounded-xl bg-slate-900 p-4 text-white">
    <label class="flex cursor-pointer items-start gap-3" for="consent_data_sharing">
        <input type="checkbox" name="consent_data_sharing" value="1" id="consent_data_sharing" class="mt-1 rounded border-white/30" required>
        <span class="text-sm leading-relaxed">J’accepte que mon dossier, mon nom et l’e-mail de mon compte soient transmis à l’équipe de <strong><?= $consentTenant ?></strong>.</span>
    </label>
</div>
<?php endif; ?>
