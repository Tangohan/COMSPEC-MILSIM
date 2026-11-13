<?php
declare(strict_types=1);
/**
 * Fichiers SSE : feuilles de style (kit « SSE Fichiers »), icônes, bandeau SIMULATION, toast d'annulation et petit script
 * commun (filtres instantanés, raccourcis « / » et Échap). Les pages sont dans la navigation du bureau, section
 * « Fichiers et contrôles ». Aucune animation au chargement d'une page.
 *
 * @var string|null $filesToast JSON {message, undo} posé par SseFilesContext::offerUndo
 */
$h = $h ?? static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$sseExtraStyles = ($sseExtraStyles ?? '')
    . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-papers.css')) . '">'
    . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-files.css')) . '">'
    . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-files-kit.css')) . '">';
$sseExtraScripts = ($sseExtraScripts ?? '') . '<script defer src="' . $h(asset_url('assets/js/sse-files.js')) . '"></script>';
$fxToast = is_string($filesToast ?? null) ? json_decode((string) $filesToast, true) : null;
?>
<svg style="display:none" aria-hidden="true">
<symbol id="fx-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></symbol>
<symbol id="fx-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/></symbol>
<symbol id="fx-car" viewBox="0 0 24 24"><path d="M5 17h14M3 13l2-6h14l2 6v4H3zM7 17v2M17 17v2"/></symbol>
<symbol id="fx-doc" viewBox="0 0 24 24"><path d="M14 3H6v18h12V7zM14 3v4h4M9 13h6M9 17h4"/></symbol>
<symbol id="fx-face" viewBox="0 0 24 24"><path d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3"/><circle cx="12" cy="11" r="3"/><path d="M8 17c1-1.5 2.4-2 4-2s3 .5 4 2"/></symbol>
<symbol id="fx-phone" viewBox="0 0 24 24"><rect x="6" y="2" width="12" height="20" rx="2"/><path d="M11 18h2"/></symbol>
<symbol id="fx-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
<symbol id="fx-alert" viewBox="0 0 24 24"><path d="M12 3 2 20h20zM12 10v4M12 17v.5"/></symbol>
<symbol id="fx-flag" viewBox="0 0 24 24"><path d="M5 21V4h11l-2 4 2 4H5"/></symbol>
<symbol id="fx-pen" viewBox="0 0 24 24"><path d="m15 4 5 5L9 20H4v-5z"/></symbol>
<symbol id="fx-list" viewBox="0 0 24 24"><path d="M8 6h13M8 12h13M8 18h13M3 6h.5M3 12h.5M3 18h.5"/></symbol>
<symbol id="fx-check" viewBox="0 0 24 24"><path d="M5 12l5 5 9-10"/></symbol>
<symbol id="fx-up" viewBox="0 0 24 24"><path d="M12 16V4M6 10l6-6 6 6M4 20h16"/></symbol>
<symbol id="fx-sync" viewBox="0 0 24 24"><path d="M20 11a8 8 0 0 0-14-5L4 8M4 4v4h4M4 13a8 8 0 0 0 14 5l2-2M20 20v-4h-4"/></symbol>
<symbol id="fx-copy" viewBox="0 0 24 24"><rect x="9" y="9" width="12" height="12" rx="1"/><path d="M5 15V4a1 1 0 0 1 1-1h11"/></symbol>
<symbol id="fx-eye" viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></symbol>
<symbol id="fx-print" viewBox="0 0 24 24"><path d="M6 9V3h12v6M6 18H4v-7h16v7h-2M8 14h8v7H8z"/></symbol>
<symbol id="fx-grid" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></symbol>
</svg>
<div class="fx">
    <div class="fx-sim" role="note">
        <b>SIMULATION</b>
        <span>Fichiers fictifs : seules les données du registre de votre communauté sont consultées. Aucune base réelle n’est interrogée.</span>
    </div>
</div>
<?php if (is_array($fxToast) && ($fxToast['message'] ?? '') !== ''): ?>
    <div class="fx-toast" role="status" data-fx-toast>
        <span><?= $h($fxToast['message']) ?></span>
        <?php if (($fxToast['undo'] ?? '') !== ''): ?>
            <form method="post" action="<?= $h(url('atak/sse/fichiers/annuler')) ?>">
                <input type="hidden" name="_csrf_token" value="<?= $h((string) ($csrfToken ?? '')) ?>">
                <input type="hidden" name="token" value="<?= $h((string) $fxToast['undo']) ?>">
                <button type="submit">Annuler</button>
            </form>
        <?php endif; ?>
        <button type="button" class="fx-x" data-fx-toast-close aria-label="Fermer">×</button>
    </div>
<?php endif; ?>
