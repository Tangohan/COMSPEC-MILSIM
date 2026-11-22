<?php declare(strict_types=1); ?>
<dialog class="pu-dialog pu-dialog--wide pu-bulk-dialog" data-pu-bulk aria-labelledby="pu-bulk-title">
    <form class="pu-dialog__form" method="post" action="<?= $h(url('admin/users/selection/appliquer')) ?>"
          data-preview-url="<?= $h(url('admin/users/selection/apercu')) ?>" data-inactivity="<?= $h($inactivityFilter) ?>" data-tenant-id="<?= $h($tenantFilter) ?>" data-pu-ban>
        <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="preview_nonce" value="">
        <header class="pu-bulk-dialog__header">
            <h2 class="pu-dialog__title" id="pu-bulk-title"><?= $h(__('roster_bulk.title')) ?></h2>
            <p class="pa-flash" role="status" data-bulk-status data-roster-checking="<?= $h(__('roster_bulk.checking')) ?>"
               data-roster-processing="<?= $h(__('roster_bulk.processing')) ?>"
               data-roster-results="<?= $h(__('roster_bulk.results')) ?>"
               data-roster-none="<?= $h(__('roster_bulk.none')) ?>"
               data-roster-eligible-one="<?= $h(__('roster_bulk.eligible_one')) ?>"
               data-roster-eligible-many="<?= $h(__('roster_bulk.eligible_many')) ?>" hidden></p>
        </header>
        <div class="pu-bulk-dialog__body">
            <label class="pu-dialog__field">
                <span>Action groupée</span>
                <select data-bulk-operation>
                    <option value="">Choisir une action</option>
                    <option value="ban">Bannir du site (CGU ou autre motif)</option>
                    <option value="ban-inactive">Bannir pour inactivité</option>
                    <option value="remove-roster"><?= $h(__('roster_bulk.remove')) ?></option>
                    <option value="verify">Renvoyer les liens de vérification</option>
                </select>
            </label>
            <p class="pu-muted" data-bulk-threshold hidden></p>
            <p class="pu-muted" data-bulk-roster-help hidden><?= $h(__('roster_bulk.help')) ?></p>
            <button type="button" class="pa-btn pa-btn--line" data-bulk-roster-shortcut hidden><?= $h(__('roster_bulk.remove')) ?></button>
            <details class="pu-bulk-selection" data-bulk-selection hidden>
                <summary data-bulk-selection-summary data-roster-label="<?= $h(__('roster_bulk.review')) ?>">Voir les comptes et les motifs</summary>
                <ul class="pu-bulk-results" data-bulk-results></ul>
            </details>
            <fieldset class="pu-bulk-options" data-bulk-ban hidden disabled>
                <legend>Paramètres du bannissement</legend>
                <p class="pu-muted">Le bannissement s’applique à tout Athena. Les décisions du lot pourront être levées ensemble depuis la console des sanctions.</p>
                <?php $bSupportsEnd = $banSupportsEnd; $defaultBanDuration = $bSupportsEnd ? '7j' : 'definitif';
                require base_path('views/admin/system/partials/platform_ban_duration_fields.php'); ?>
                <label class="pu-dialog__field">
                    <span>Motif interne</span>
                    <textarea name="reason" rows="3" minlength="5" maxlength="500"></textarea>
                </label>
                <?php if (!empty($platformBanSupportsNotice)):
                    $bh = $h;
                    $banNoticeSupportsClosure = !empty($platformBanSupportsAppealClosure);
                    require base_path('views/admin/system/partials/platform_ban_notice_fields.php');
                endif; ?>
                <label class="pu-check"><input type="checkbox" name="block_steam" value="1"> Bloquer aussi les identifiants Steam liés</label>
            </fieldset>
        </div>
        <footer class="pu-bulk-dialog__footer">
            <label class="pu-check pu-confirm-check" data-bulk-confirm hidden>
                <input type="checkbox" name="confirm_bulk" value="1" required disabled>
                <span data-bulk-confirm-label data-roster-label="<?= $h(__('roster_bulk.confirm')) ?>">Je confirme l’action sur les comptes admissibles.</span>
            </label>
            <div class="pu-dialog__buttons">
                <button type="button" class="pa-btn pa-btn--line" data-pu-close>Fermer</button>
                <button type="submit" class="pa-btn pa-btn--solid" data-bulk-apply hidden disabled>Appliquer</button>
                <button type="button" class="pa-btn pa-btn--line" data-bulk-retry hidden>Relancer la vérification</button>
                <a class="pa-btn pa-btn--solid" href="<?= $h($directoryUrl()) ?>" data-bulk-refresh hidden>Actualiser l’annuaire</a>
            </div>
        </footer>
    </form>
</dialog>
