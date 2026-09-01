<?php
declare(strict_types=1);

require base_path('views/admin/effectifs_workspace/partials/rh_ui_helpers.php');

$docs = is_array($hrDocuments ?? null) ? $hrDocuments : [];
$typeLabels = is_array($hrDocTypeLabels ?? null) ? $hrDocTypeLabels : [];
$users = is_array($orgUsers ?? null) ? $orgUsers : [];
$schemaReady = !empty($hrSchemaReady);
$canManage = !empty($canManage);
$count = (int) ($hrDocumentsCount ?? count($docs));
$csrf = htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8');

$typeCounts = [];
foreach ($docs as $d) {
    $tk = (string) ($d['doc_type'] ?? 'autre');
    $typeCounts[$tk] = ($typeCounts[$tk] ?? 0) + 1;
}
arsort($typeCounts);
$topTypes = array_slice($typeCounts, 0, 3, true);
$memberVisible = 0;
foreach ($docs as $d) {
    if (($d['visibility'] ?? '') === 'MEMBER') {
        $memberVisible++;
    }
}
?>
<section class="eff-rh-hero">
    <p class="eff-page-kicker">Dossier RH</p>
    <h1 class="eff-page-title">Documents RH</h1>
    <p class="eff-page-lead">
        Coffre du dossier individuel : chartes, certificats, décisions d’affectation et évaluations.
        Chaque pièce est classée, datée, et sa visibilité est choisie.
    </p>
    <div class="eff-rh-tiles" aria-label="Aperçu des documents">
        <article class="eff-rh-tile">
            <span class="eff-rh-tile__kicker">Coffre</span>
            <strong class="eff-rh-tile__value"><?= $count ?></strong>
            <span class="eff-rh-tile__label">document<?= $count > 1 ? 's' : '' ?> au dossier</span>
        </article>
        <article class="eff-rh-tile">
            <span class="eff-rh-tile__kicker">
                Visibilité
                <?php $rhTip('tip-docs-vis-hero', 'À propos de la visibilité', 'État-major uniquement : seuls les gestionnaires voient la pièce. Visible du membre : le titulaire du dossier la consulte aussi.'); ?>
            </span>
            <strong class="eff-rh-tile__value"><?= $memberVisible ?></strong>
            <span class="eff-rh-tile__label">visible<?= $memberVisible > 1 ? 's' : '' ?> du membre</span>
        </article>
        <?php foreach ($topTypes as $tk => $n): ?>
            <article class="eff-rh-tile">
                <span class="eff-rh-tile__kicker">Type</span>
                <strong class="eff-rh-tile__value"><?= (int) $n ?></strong>
                <span class="eff-rh-tile__label"><?= $h((string) ($typeLabels[$tk] ?? $tk)) ?></span>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<?php if (!$schemaReady): ?>
    <div class="eff-catalog">
        <div class="eff-catalog__empty">
            <strong>Documents RH indisponibles</strong>
            <?= $h($rhUnavailable) ?>
        </div>
    </div>
<?php else: ?>
    <?php if ($canManage): ?>
    <section class="eff-rh-form" aria-labelledby="eff-docs-add-title">
        <div class="eff-rh-form__head">
            <h2 id="eff-docs-add-title" class="eff-rh-form__title">Ajouter une pièce</h2>
            <p class="eff-rh-form__lead">Classez le document, indiquez qui peut le voir, puis donnez un titre clair.</p>
        </div>
        <form method="post" action="<?= $h(effectifs_workspace_url('documents-rh')) ?>" class="eff-rh-form__grid">
            <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
            <div class="eff-rh-field">
                <span class="eff-rh-field__label">Membre</span>
                <select name="user_id" required aria-label="Membre">
                    <option value="">Choisir un membre…</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int) ($u['id'] ?? 0) ?>"><?= $h(trim((string) ($u['display_name'] ?? '')) ?: (string) ($u['email'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="eff-rh-field">
                <span class="eff-rh-field__label">
                    Type
                    <?php $rhTip('tip-docs-type', 'À propos du type', 'Choisissez le rôle de la pièce dans le dossier : charte, certificat, évaluation, décision d’affectation…'); ?>
                </span>
                <select name="doc_type" aria-label="Type">
                    <?php foreach ($typeLabels as $k => $lab): ?>
                        <option value="<?= $h((string) $k) ?>"><?= $h((string) $lab) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="eff-rh-field">
                <span class="eff-rh-field__label">Titre</span>
                <input type="text" name="title" maxlength="200" placeholder="Ex. Charte signée 2026" aria-label="Titre">
            </div>
            <div class="eff-rh-field">
                <span class="eff-rh-field__label">
                    Emplacement
                    <?php $rhTip('tip-docs-loc', 'À propos de l’emplacement', 'Indiquez où retrouver la pièce : dossier partagé de la communauté, archives, ou lien interne déjà publié.'); ?>
                </span>
                <input type="text" name="file_path" maxlength="500" placeholder="Dossier partagé, archives, lien interne…" aria-label="Emplacement">
            </div>
            <div class="eff-rh-field">
                <span class="eff-rh-field__label">
                    Visibilité
                    <?php $rhTip('tip-docs-vis', 'À propos de la visibilité', 'État-major uniquement : réservé aux gestionnaires. Visible du membre : le titulaire du dossier peut aussi consulter cette pièce.'); ?>
                </span>
                <select name="visibility" aria-label="Visibilité">
                    <option value="STAFF">État-major uniquement</option>
                    <option value="MEMBER">Visible du membre</option>
                </select>
            </div>
            <div class="eff-rh-field eff-rh-field--wide">
                <span class="eff-rh-field__label">Description</span>
                <input type="text" name="description" maxlength="500" placeholder="Contexte, période, décision associée…" aria-label="Description">
            </div>
            <div class="eff-rh-form__actions">
                <button type="submit" class="eff-rh-btn eff-rh-btn--primary">Ajouter au dossier</button>
            </div>
        </form>
    </section>
    <?php endif; ?>

    <div class="eff-catalog">
        <div class="eff-catalog__head">
            <div class="min-w-0">
                <p class="eff-catalog__kicker">Registre</p>
                <h2 class="eff-catalog__title">Pièces enregistrées</h2>
                <p class="eff-catalog__lead">Les plus récentes d’abord. Ouvrez la fiche pour le dossier complet du membre.</p>
            </div>
        </div>
        <?php if ($docs === []): ?>
            <div class="eff-catalog__empty"><strong>Aucune pièce pour l’instant.</strong> Ajoutez un premier document ci-dessus.</div>
        <?php else: ?>
            <div class="eff-sheets" role="region" aria-label="Documents RH" tabindex="0">
                <table class="eff-sheets__table">
                    <thead>
                        <tr>
                            <th>Membre</th>
                            <th>Type</th>
                            <th>Titre</th>
                            <th>Visibilité</th>
                            <th>Date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($docs as $d): ?>
                        <?php
                        $uid = (int) ($d['user_id'] ?? 0);
                        $name = trim((string) ($d['user_display_name'] ?? '')) ?: (string) ($d['user_email'] ?? 'Membre');
                        $type = (string) ($d['doc_type'] ?? 'autre');
                        $path = trim((string) ($d['file_path'] ?? ''));
                        $visMember = ($d['visibility'] ?? '') === 'MEMBER';
                        ?>
                        <tr>
                            <td><strong class="eff-sheets__name"><?= $h($name) ?></strong></td>
                            <td><span class="eff-rh-chip"><?= $h((string) ($typeLabels[$type] ?? $type)) ?></span></td>
                            <td><?= $h((string) ($d['title'] ?? '')) ?><?php if ($path !== ''): ?><br><span class="eff-sheets__meta"><?= $h($path) ?></span><?php endif; ?></td>
                            <td><span class="eff-rh-chip <?= $visMember ? 'eff-rh-chip--info' : '' ?>"><?= $visMember ? 'Visible du membre' : 'État-major' ?></span></td>
                            <td><?= $h($rhWhen((string) ($d['created_at'] ?? ''))) ?></td>
                            <td><?php if ($uid > 0): ?><a class="is-primary" href="<?= $h(effectifs_workspace_url('membres/' . $uid)) ?>">Fiche</a><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php $rhShortcutCurrent = 'documents'; require base_path('views/admin/effectifs_workspace/partials/rh_shortcuts.php'); ?>
