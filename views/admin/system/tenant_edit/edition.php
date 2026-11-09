<?php
declare(strict_types=1);
/**
 * Onglet Édition : une section = un formulaire = un enregistrement (CSRF, validation serveur, audit avant/après).
 * Les intégrations ne montrent que leur ÉTAT, jamais un secret ni un jeton.
 */
$lim = is_array($platformLimits ?? null) ? $platformLimits : ['limits' => [], 'flags' => []];
$integrations = is_array($platformIntegrations ?? null) ? $platformIntegrations : [];
$stateLabels = ['ok' => ['Configurée', 'mint'], 'missing' => ['Non configurée', 'amber'], 'na' => ['Non disponible', 'slate']];
$localeCurrent = (string) ($platformLocale ?? 'fr');
$tzCurrent = $platformTimezone ?? null;
$logoUrl = trim((string) ($tenant['logo_url'] ?? ''));
$ownerId = (int) ($tenant['owner_user_id'] ?? 0);
$removalArchive = is_array($platformTenantArchive ?? null) ? $platformTenantArchive : null;
$removalUrl = (string) ($platformTenantRemovalUrl ?? '');
?>
<?php require __DIR__ . '/_forms_legacy.php'; ?>

<section id="logo" class="pa-card scroll-mt-24">
    <p class="pa-card__kicker">Identité</p>
    <h2 class="pa-card__title">Logo</h2>
    <?php if ($logoUrl === ''): ?>
        <p class="pa-card__help">Cette communauté n’a pas de logo. Il se téléverse depuis son back-office.</p>
    <?php else: ?>
        <p class="pa-card__help">Un logo est défini. Le retirer ramène l’emblème par défaut ; la communauté peut en téléverser un autre.</p>
        <form method="post" action="<?= $h((string) ($platformLogoAction ?? '')) ?>">
            <?= \App\Core\Csrf::field() ?>
            <label class="pa-choice">
                <input type="checkbox" name="confirm_logo_removal" value="1" required>
                <span><strong>Confirmation</strong><em>Je retire le logo de cette communauté.</em></span>
            </label>
            <div class="pa-actions">
                <button type="submit" class="pa-btn pa-btn--line">Retirer le logo</button>
            </div>
        </form>
    <?php endif; ?>
</section>

<form id="langue" class="pa-card scroll-mt-24" method="post" action="<?= $h((string) ($platformLocaleAction ?? '')) ?>">
    <?= \App\Core\Csrf::field() ?>
    <p class="pa-card__kicker">Réglages</p>
    <h2 class="pa-card__title">Langue et fuseau horaire</h2>
    <div class="pa-field">
        <label for="default_locale">Langue de référence</label>
        <select id="default_locale" name="default_locale">
            <?php foreach (\App\Support\CommunityLanguages::codes() as $code): ?>
                <option value="<?= $h($code) ?>" <?= $code === $localeCurrent ? 'selected' : '' ?>><?= $h(\App\Support\CommunityLanguages::nativeLabel($code)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php if ($tzCurrent !== null): ?>
        <div class="pa-field">
            <label for="default_timezone">Fuseau horaire</label>
            <select id="default_timezone" name="default_timezone">
                <?php foreach (\DateTimeZone::listIdentifiers() as $zone): ?>
                    <option value="<?= $h($zone) ?>" <?= $zone === $tzCurrent ? 'selected' : '' ?>><?= $h($zone) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>
    <div class="pa-actions">
        <button type="submit" class="pa-btn pa-btn--ink">Enregistrer la langue</button>
    </div>
</form>

<section id="limites" class="pa-card scroll-mt-24">
    <p class="pa-card__kicker">Formule appliquée : <?= $h($currentPlanLabel) ?></p>
    <h2 class="pa-card__title">Limites et fonctionnalités</h2>
    <p class="pa-card__help">Lecture seule : elles découlent de la formule (modifiable dans la section Formule d’accès ci-dessus, ou dans le catalogue des formules).</p>
    <?php if (($lim['limits'] ?? []) === [] && ($lim['flags'] ?? []) === []): ?>
        <p class="pa-card__help">Aucune limite déclarée pour cette formule.</p>
    <?php else: ?>
        <div class="pa-sheet__scroll">
            <table class="pa-sheet__grid">
                <thead><tr><th>Limite</th><th>Plafond</th><th>Utilisé</th></tr></thead>
                <tbody>
                    <?php foreach ($lim['limits'] as $l): ?>
                        <tr>
                            <td><?= $h((string) $l['key']) ?></td>
                            <td><?= (int) $l['limit'] === 0 ? 'Illimité' : (int) $l['limit'] ?></td>
                            <td>
                                <?php if ($l['used'] === null): ?>—<?php else: ?>
                                    <?= (int) $l['used'] ?>
                                    <?php if (!empty($l['reached'])): ?><span class="pa-pill pa-pill--rose">Atteinte</span><?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="pa-chips" style="margin-top:0.85rem;">
            <?php foreach ($lim['flags'] as $flag => $on): ?>
                <span class="pa-pill pa-pill--<?= $on ? 'mint' : 'slate' ?>"><?= $h((string) $flag) ?> : <?= $on ? 'activée' : 'désactivée' ?></span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<form id="contact" class="pa-card scroll-mt-24" method="post" action="<?= $h((string) ($platformOwnerAction ?? '')) ?>">
    <?= \App\Core\Csrf::field() ?>
    <p class="pa-card__kicker">Contact</p>
    <h2 class="pa-card__title">Propriétaire de la communauté</h2>
    <p class="pa-card__help">
        Actuel : <?= $h(($platformOwnerName ?? '') !== '' ? (string) $platformOwnerName : ($ownerId > 0 ? 'compte n° ' . $ownerId : 'non défini')) ?>.
        Seul un compte actif de cette communauté peut être choisi.
    </p>
    <div class="pa-field">
        <label for="owner_user_id">Nouveau propriétaire</label>
        <select id="owner_user_id" name="owner_user_id" required>
            <?php foreach (($platformOwners ?? []) as $o): ?>
                <option value="<?= (int) $o['id'] ?>" <?= (int) $o['id'] === $ownerId ? 'selected' : '' ?>><?= $h((string) $o['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="pa-actions">
        <button type="submit" class="pa-btn pa-btn--ink">Enregistrer le propriétaire</button>
    </div>
</form>

<section id="domaine" class="pa-card scroll-mt-24">
    <p class="pa-card__kicker">Adresse</p>
    <h2 class="pa-card__title">Domaine et URL</h2>
    <dl class="pa-stats">
        <div class="pa-stat"><dt>Page publique</dt><dd style="font-size:1rem;word-break:break-all;"><?= $publicUrl !== '' ? '<a href="' . $h($publicUrl) . '" target="_blank" rel="noopener">' . $h($publicUrl) . '</a>' : '—' ?></dd></div>
        <div class="pa-stat"><dt>Adresse courte</dt><dd style="font-size:1rem;"><?= $h($slug) ?></dd></div>
        <?php if ($communityCode !== ''): ?>
            <div class="pa-stat"><dt>Code pour rejoindre</dt><dd style="font-size:1rem;"><?= $h($communityCode) ?></dd></div>
        <?php endif; ?>
    </dl>
    <p class="pa-card__help">L’adresse courte se change dans la section Identité ; il n’y a pas de domaine personnalisé par communauté.</p>
</section>

<section id="integrations" class="pa-card scroll-mt-24">
    <p class="pa-card__kicker">Intégrations</p>
    <h2 class="pa-card__title">État des intégrations</h2>
    <p class="pa-card__help">État seulement : aucun jeton, clé ni secret n’est lu ni affiché ici.</p>
    <div class="pa-sheet__scroll">
        <table class="pa-sheet__grid">
            <thead><tr><th>Intégration</th><th>État</th><th>Détail</th></tr></thead>
            <tbody>
                <?php foreach ($integrations as $it): ?>
                    <?php [$stLabel, $stTone] = $stateLabels[$it['state']] ?? $stateLabels['na']; ?>
                    <tr>
                        <td><?= $h((string) $it['label']) ?></td>
                        <td><span class="pa-pill pa-pill--<?= $h($stTone) ?>"><?= $h($stLabel) ?></span></td>
                        <td><?= $h((string) $it['detail']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($removalUrl !== '' && $tenantId > 1 && $slug !== 'default'): ?>
    <section id="statut" class="pa-card scroll-mt-24">
        <p class="pa-card__kicker">Statut</p>
        <h2 class="pa-card__title"><?= $removalArchive !== null ? 'Communauté archivée' : 'Communauté active' ?></h2>
        <p class="pa-card__help">
            <?php if ($removalArchive !== null): ?>
                Cette communauté est archivée : elle n’est plus visible, ses données sont intactes. Vous pouvez la restaurer ou la supprimer définitivement.
            <?php else: ?>
                L’archivage ferme la communauté sans rien effacer (réversible). Il demande une confirmation sur une page dédiée, après un récapitulatif de ce qui serait retiré. La suspension temporaire n’existe pas encore.
            <?php endif; ?>
        </p>
        <div class="pa-actions">
            <a class="pa-btn pa-btn--line" href="<?= $h($removalUrl) ?>"><?= $removalArchive !== null ? 'Restaurer ou supprimer' : 'Archiver : voir ce qui serait retiré' ?></a>
        </div>
    </section>
<?php endif; ?>
