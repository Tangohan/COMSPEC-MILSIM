<?php
/**
 * Bouton « Continuer avec Discord », affiché seulement si l'application Discord du site est réglée.
 *
 * @var string|null $discordNext chemin interne à rejoindre après la connexion (facultatif)
 */
declare(strict_types=1);

$discordReady = false;
try {
    $discordReady = (new \App\Services\Integrations\DiscordLinkedRoles())->configured();
} catch (\Throwable) {
}
if (!$discordReady) {
    return;
}
$discordHref = trim((string) ($discordHrefOverride ?? '')) !== ''
    ? (string) $discordHrefOverride
    : url('discord/connexion') . (trim((string) ($discordNext ?? '')) !== '' ? '?next=' . rawurlencode((string) $discordNext) : '');
?>
<link href="<?= htmlspecialchars(asset_url('assets/css/discord-recruitment.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
<div class="dc-wrap">
    <a class="dc-btn" href="<?= htmlspecialchars($discordHref, ENT_QUOTES, 'UTF-8') ?>">
        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M20.3 4.4A19.8 19.8 0 0 0 15.4 3l-.2.5a18.400 18.400 0 0 0-6.400 0L8.600 3A19.800 19.800 0 0 0 3.700 4.400C.6 9 -.200 13.500.2 18a20 20 0 0 0 6 3l1.300-2.100a13 13 0 0 1-2-1l.5-.4a14.200 14.200 0 0 0 12 0l.5.4c-.6.400-1.300.7-2 1L18 21a20 20 0 0 0 6-3c.5-5.200-.8-9.700-3.700-13.600ZM8.500 15.300c-1.200 0-2.100-1.100-2.100-2.400s.9-2.400 2.100-2.400 2.100 1.100 2.100 2.400-.9 2.400-2.100 2.400Zm7 0c-1.200 0-2.100-1.100-2.100-2.400s.9-2.400 2.100-2.400 2.100 1.100 2.100 2.400-.9 2.400-2.100 2.400Z"/></svg>
        <span>Continuer avec Discord</span>
    </a>
</div>
