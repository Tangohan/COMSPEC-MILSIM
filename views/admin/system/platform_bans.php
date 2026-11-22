<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Services\Account\PlatformBanNotice;

$h = static fn (string|int|null $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$bh = $h;
$durationLabels = ['1j' => '1 jour', '7j' => '7 jours', '30j' => '30 jours', '90j' => '90 jours', 'definitif' => 'Sans date de fin'];
$base = url('admin/sanctions');
$bSupportsEnd = $supportsEnd;
?>
<div class="pa pu ps">
    <div class="pa__frame">
        <header class="pa-hero">
            <h1 class="pa-hero__title">Bannissements et contestations</h1>
            <p class="pa-hero__lead">Bloquer l’accès à Athena, expliquer la décision et traiter les demandes de réexamen.</p>
            <div class="pa-hero__actions">
                <button type="button" class="pa-btn pa-btn--solid" data-pu-open="ps-ban-account" aria-haspopup="dialog">Bannir un compte</button>
                <a class="pa-btn pa-btn--ghost" href="#organisation">Bannir par organisation</a>
                <a class="pa-btn pa-btn--ghost" href="#contestations">Examiner les contestations</a>
            </div>
        </header>
        <?php if ($success): ?><p class="pa-flash pa-flash--ok" role="status"><?= $h($success) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="pa-flash pa-flash--err" role="alert"><?= $h($error) ?></p><?php endif; ?>
        <noscript><p><a href="<?= $h(url('admin/users')) ?>">Bannir un compte depuis l’annuaire</a></p></noscript>

        <nav class="pa-tabs" aria-label="Outils de modération du site">
            <a class="pa-tab" href="#compte">Bannissements actifs</a>
            <a class="pa-tab" href="#organisation">Organisations</a>
            <a class="pa-tab" href="#contestations">Contestations</a>
            <a class="pa-tab" href="#automatique">Règle automatique</a>
        </nav>

        <?php if (!$workflowReady): ?>
            <p class="pa-flash pa-flash--warn">Les décisions groupées, les avis publics et les contestations nécessitent la migration « Sanctions du site ». Les bannissements individuels restent disponibles.</p>
        <?php elseif (empty($supportsAppealClosure)): ?>
            <p class="pa-flash pa-flash--warn">La fermeture des contestations est disponible après la migration « Sanctions du site : fermeture et réouverture des contestations par décision ».</p>
        <?php endif; ?>

        <div class="pa-panel">
            <section id="compte" class="pa-card" aria-labelledby="ps-active-title">
                <div class="pa-card__head">
                    <div>
                        <h2 class="pa-card__title" id="ps-active-title">Bannissements actifs · <?= $activeCount ?></h2>
                        <?php if ($activeCount > count($activeBans)): ?><p class="pa-card__help">Les <?= count($activeBans) ?> décisions les plus récentes.</p><?php endif; ?>
                    </div>
                    <a class="pa-btn pa-btn--line" href="<?= $h(url('admin/users') . '?status=banned') ?>">Ouvrir l’annuaire</a>
                </div>
                <?php if ($activeBans === []): ?>
                    <p class="pa-empty-state">Aucun bannissement en cours.</p>
                <?php else: ?>
                    <ul class="ps-records">
                        <?php foreach ($activeBans as $activeBan): ?>
                            <li>
                                <div>
                                    <strong><?= $h((string) ($activeBan['display_label'] ?: $activeBan['email_norm'])) ?></strong>
                                    <p class="pu-muted"><?= $h($activeBan['email_norm']) ?> · Décision n° <?= (int) $activeBan['id'] ?></p>
                                    <p><?= $h(PlatformBanNotice::endLabel($activeBan['ends_at'] ?? null)) ?></p>
                                    <?php if (!empty($supportsAppealClosure)):
                                        $appealDecision = $activeBan;
                                        $fromPersonDossier = false;
                                        require base_path('views/admin/system/partials/platform_appeal_access.php');
                                    endif; ?>
                                </div>
                                <a class="pa-btn pa-btn--line pa-btn--sm" href="<?= $h(url('admin/users/person') . '?email=' . rawurlencode($activeBan['email_norm']) . '#bannissement') ?>">Ouvrir le dossier</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <?php if ($workflowReady): ?>
                <section id="organisation" class="pa-card" aria-labelledby="ps-organization-title">
                    <h2 class="pa-card__title" id="ps-organization-title">Bannissement par organisation</h2>
                    <p class="pa-card__help">Prévisualisez les personnes rattachées à l’organisation. La décision touchera leurs fiches sur tout Athena, y compris dans d’autres organisations.</p>
                    <form method="get" action="<?= $h($base . '#organisation') ?>" class="ps-inline">
                        <label class="pu-dialog__field">
                            <span>Organisation</span>
                            <select id="ps-org-value" name="organisation" required>
                                <option value="">Choisir une organisation</option>
                                <?php foreach ($tenants as $tenant): ?>
                                    <option value="<?= (int) $tenant['id'] ?>"<?= (int) ($selectedTenant['id'] ?? 0) === (int) $tenant['id'] ? ' selected' : '' ?>><?= $h($tenant['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <button type="button" class="pa-btn pa-btn--line" data-pu-open="ps-org-picker" aria-haspopup="dialog">Rechercher une organisation</button>
                        <button type="submit" class="pa-btn pa-btn--solid">Prévisualiser les comptes</button>
                    </form>
                    <?php if ($selectedTenant !== null): ?>
                        <p><strong><?= $h($selectedTenant['name']) ?></strong> · <?= count($targets) ?> adresse(s) recensée(s) · <?= $alreadyBanned ?> déjà bannie(s).</p>
                        <?php if ($targets !== []): ?>
                            <details class="ps-preview">
                                <summary>Consulter les adresses recensées</summary>
                                <ul class="ps-targets"><?php foreach ($targets as $target): ?><li><?= $h($target) ?></li><?php endforeach; ?></ul>
                            </details>
                            <p class="pa-card__help">Les comptes techniques et les administrateurs du site sont protégés. Les décisions déjà en cours gardent leur durée.</p>
                            <form method="post" action="<?= $h($base . '/organisation') ?>" class="ps-form" data-pu-ban>
                                <?= Csrf::field() ?>
                                <input type="hidden" name="organisation" value="<?= (int) $selectedTenant['id'] ?>">
                                <input type="hidden" name="targets_signature" value="<?= $h($targetsSignature) ?>">
                                <input type="hidden" name="batch_nonce" value="<?= $h($batchNonce) ?>">
                                <?php $defaultBanDuration = $supportsEnd ? '7j' : 'definitif'; require base_path('views/admin/system/partials/platform_ban_duration_fields.php'); ?>
                                <label class="pu-dialog__field">
                                    <span>Motif interne</span>
                                    <textarea name="reason" rows="3" minlength="5" maxlength="500" required></textarea>
                                </label>
                                <?php
                                $banNoticeSupportsClosure = !empty($supportsAppealClosure);
                                require base_path('views/admin/system/partials/platform_ban_notice_fields.php');
                                ?>
                                <label class="pu-check"><input type="checkbox" name="block_steam" value="1" checked> Bloquer aussi les identifiants Steam liés</label>
                                <label class="pu-check pu-confirm-check"><input type="checkbox" name="confirm_ban" value="1" required> Je confirme le bannissement sur tout Athena des comptes admissibles de cette liste.</label>
                                <button type="submit" class="pa-btn pa-btn--danger">Bannir les comptes de cette organisation</button>
                            </form>
                        <?php else: ?>
                            <p>Aucun compte concerné.</p>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>

                <section id="lots" class="pa-card" aria-labelledby="ps-batches-title">
                    <h2 class="pa-card__title" id="ps-batches-title">Dernières décisions par organisation</h2>
                    <?php if ($batches === []): ?><p class="pa-empty-state">Aucune décision groupée.</p><?php endif; ?>
                    <?php foreach ($batches as $batch): ?>
                        <details class="ps-preview">
                            <summary>
                                <strong><?= $h($batch['tenant_name'] ?? ('Organisation n° ' . $batch['source_tenant_id'])) ?></strong>
                                · <?= (int) $batch['remaining'] ?> actif(s) sur <?= (int) $batch['total'] ?> · <?= $h($batch['created_at']) ?> UTC
                            </summary>
                            <?php if ((int) $batch['remaining'] > 0): ?>
                                <form method="post" action="<?= $h($base . '/lots/lever') ?>" class="ps-form">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="batch" value="<?= $h($batch['batch_ref']) ?>">
                                    <label class="pu-dialog__field"><span>Motif de levée</span><input name="note" type="text" minlength="5" maxlength="500" required></label>
                                    <label class="pu-check"><input type="checkbox" name="confirm_lift" value="1" required> Je confirme la levée des décisions encore actives de ce lot.</label>
                                    <button type="submit" class="pa-btn pa-btn--solid">Lever le lot</button>
                                </form>
                            <?php else: ?>
                                <p>Toutes les décisions de ce lot ont été levées ou sont arrivées à échéance.</p>
                            <?php endif; ?>
                        </details>
                    <?php endforeach; ?>
                </section>

                <section id="contestations" class="pa-card" aria-labelledby="ps-appeals-title">
                    <div class="pa-card__head">
                        <h2 class="pa-card__title" id="ps-appeals-title">Contestations</h2>
                        <a class="pa-btn pa-btn--line" href="<?= $h($base . ($showReviewed ? '' : '?recours=traites') . '#contestations') ?>"><?= $showReviewed ? 'Voir les demandes en attente' : 'Voir les demandes traitées' ?></a>
                    </div>
                    <?php if ($appeals === []): ?><p class="pa-empty-state"><?= $showReviewed ? 'Aucune demande traitée.' : 'Aucune demande en attente.' ?></p><?php endif; ?>
                    <?php foreach ($appeals as $appeal): ?>
                        <details class="ps-preview" id="contestation-<?= (int) $appeal['ban_id'] ?>"<?= (int) ($focusedAppeal ?? 0) === (int) $appeal['ban_id'] ? ' open' : '' ?>>
                            <summary><strong>Décision n° <?= (int) $appeal['ban_id'] ?></strong> · <?= $h($appeal['email_norm']) ?> · <?= $h($appeal['created_at']) ?> UTC</summary>
                            <dl class="ps-case">
                                <div><dt>Date de fin</dt><dd><?= $h(PlatformBanNotice::endLabel($appeal['ends_at'])) ?></dd></div>
                                <div><dt>Motif interne</dt><dd><?= $h($appeal['reason']) ?></dd></div>
                                <?php if ($appeal['rule_reference'] || $appeal['public_reason']): ?>
                                    <div><dt>Avis communiqué</dt><dd><?= $h(trim((string) $appeal['rule_reference'] . "\n" . (string) $appeal['public_reason'])) ?></dd></div>
                                <?php endif; ?>
                                <div><dt>Contestation</dt><dd><?= $h($appeal['statement']) ?></dd></div>
                            </dl>
                            <?php if ($appeal['reviewed_at'] !== null): ?>
                                <p><strong><?= $appeal['decision'] === 'lift' ? 'Contestation acceptée' : 'Décision maintenue' ?></strong></p>
                                <p class="ps-text"><?= $h($appeal['public_reply']) ?></p>
                            <?php else: ?>
                                <form method="post" action="<?= $h($base . '/contestations/' . (int) $appeal['ban_id']) ?>" class="ps-form">
                                    <?= Csrf::field() ?>
                                    <label class="pu-dialog__field">
                                        <span>Décision</span>
                                        <select name="decision" required>
                                            <option value="">Choisir</option>
                                            <option value="uphold">Maintenir le bannissement</option>
                                            <option value="lift">Accepter et lever cette décision</option>
                                        </select>
                                    </label>
                                    <label class="pu-dialog__field">
                                        <span>Réponse visible par la personne</span>
                                        <textarea name="public_reply" rows="4" minlength="5" maxlength="2000" required></textarea>
                                    </label>
                                    <button type="submit" class="pa-btn pa-btn--solid">Enregistrer la réponse</button>
                                </form>
                            <?php endif; ?>
                        </details>
                    <?php endforeach; ?>
                </section>

                <section id="automatique" class="pa-card" aria-labelledby="ps-automation-title">
                    <h2 class="pa-card__title" id="ps-automation-title">Bannissement automatique</h2>
                    <p class="pa-card__help">Après chaque suspension ou bannissement confirmé dans <a class="pa-card__link" href="<?= $h(url('admin/system/member-sanctions')) ?>">Sanctions membres</a>, Athena compte les mesures confirmées pour cette personne. Les mesures révoquées et celles qui précèdent son dernier bannissement du site sont écartées.</p>
                    <form method="post" action="<?= $h($base . '/automatique') ?>" class="ps-form">
                        <?= Csrf::field() ?>
                        <label class="pu-check"><input type="checkbox" name="enabled" value="1"<?= $autoRule['enabled'] ? ' checked' : '' ?>> Activer cette règle</label>
                        <div class="ps-input-grid">
                            <label class="pu-dialog__field"><span>Mesures confirmées requises</span><input name="threshold" type="number" value="<?= $autoRule['threshold'] ?>" min="1" max="100" required></label>
                            <label class="pu-dialog__field"><span>Période observée en jours</span><input name="window_days" type="number" value="<?= $autoRule['window_days'] ?>" min="1" max="365" required></label>
                            <label class="pu-dialog__field">
                                <span>Durée du bannissement</span>
                                <select name="duration">
                                    <?php foreach ($durationLabels as $duration => $label): ?>
                                        <option value="<?= $h($duration) ?>"<?= $duration === $autoRule['duration'] ? ' selected' : '' ?>><?= $h($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>
                        <button type="submit" class="pa-btn pa-btn--solid">Enregistrer la règle</button>
                    </form>
                </section>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($workflowReady): ?>
    <dialog class="pu-dialog" id="ps-org-picker" aria-labelledby="ps-org-picker-title">
        <div class="pu-dialog__form">
            <h2 class="pu-dialog__title" id="ps-org-picker-title">Choisir une organisation</h2>
            <?php
            $pickerType = 'organization';
            $pickerId = 'ps-org-query';
            $pickerTarget = 'ps-org-value';
            require base_path('views/admin/system/partials/platform_entity_picker.php');
            ?>
            <button type="button" class="pa-btn pa-btn--line" data-pu-close>Fermer</button>
        </div>
    </dialog>
    <?php endif; ?>
    <dialog class="pu-dialog pu-dialog--wide" id="ps-ban-account" aria-labelledby="ps-ban-account-title">
        <form class="pu-dialog__form ps-form" method="post" action="<?= $h(url('admin/users/bannir')) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="return_sanctions" value="1">
            <h2 class="pu-dialog__title" id="ps-ban-account-title">Bannir un compte sur tout Athena</h2>
            <label class="pu-dialog__field" data-entity-fallback>
                <span>Adresse du compte</span>
                <input id="ps-account-value" type="email" name="email" maxlength="255" required autofocus>
            </label>
            <?php
            $pickerType = 'account';
            $pickerId = 'ps-account-query';
            $pickerTarget = 'ps-account-value';
            require base_path('views/admin/system/partials/platform_entity_picker.php');
            $banForm = [
                'id' => 'ps-ban-account', 'label' => 'cette personne', 'email' => 'du compte ciblé',
                'supports_end' => $supportsEnd, 'supports_notice' => $workflowReady,
                'supports_appeal_closure' => !empty($supportsAppealClosure), 'shared' => true,
            ];
            require base_path('views/admin/system/partials/platform_ban_form.php');
            ?>
            <div class="pu-dialog__buttons">
                <button type="button" class="pa-btn pa-btn--line" data-pu-close>Annuler</button>
                <button type="submit" class="pa-btn pa-btn--danger">Bannir du site</button>
            </div>
        </form>
    </dialog>
</div>
<script src="<?= $h(asset_url('assets/js/platform-users-sheet.js')) ?>" defer></script>
<script src="<?= $h(asset_url('assets/js/platform-ban-notice.js')) ?>" defer></script>
<script src="<?= $h(asset_url('assets/js/platform-entity-picker.js')) ?>" defer></script>
