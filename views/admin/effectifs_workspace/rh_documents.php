<?php
declare(strict_types=1);

$docs = is_array($hrDocuments ?? null) ? $hrDocuments : [];
$typeLabels = is_array($hrDocTypeLabels ?? null) ? $hrDocTypeLabels : [];
$users = is_array($orgUsers ?? null) ? $orgUsers : [];
$schemaReady = !empty($hrSchemaReady);
$canManage = !empty($canManage);
$count = (int) ($hrDocumentsCount ?? count($docs));
$csrf = htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8');
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<div class="eff-catalog">
    <div class="eff-catalog__head">
        <div class="min-w-0">
            <p class="eff-catalog__kicker">Dossier individuel</p>
            <h1 class="eff-catalog__title">Documents RH</h1>
            <p class="eff-catalog__lead">
                Coffre typé du dossier : candidature, charte, règlements, certificats, qualifications,
                décisions d’affectation et évaluations. Référencez un chemin fichier ou une URL interne.
            </p>
        </div>
        <div class="eff-catalog__tools">
            <span class="eff-catalog__btn"><?= $count ?> document<?= $count > 1 ? 's' : '' ?></span>
        </div>
    </div>

    <?php if (!$schemaReady): ?>
        <div class="eff-catalog__empty"><strong>Schéma non migrée.</strong> Relancez les migrations pour activer les documents RH.</div>
    <?php else: ?>
        <?php if ($canManage): ?>
        <form method="post" action="<?= $h(effectifs_workspace_url('documents-rh')) ?>" class="eff-catalog__tools" style="flex-wrap:wrap;gap:.75rem;margin-bottom:1.25rem;align-items:end">
            <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
            <label>
                <span class="eff-section-label">Membre</span>
                <select name="user_id" required>
                    <option value="">Choisir…</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int) ($u['id'] ?? 0) ?>"><?= $h(trim((string) ($u['display_name'] ?? '')) ?: (string) ($u['email'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span class="eff-section-label">Type</span>
                <select name="doc_type">
                    <?php foreach ($typeLabels as $k => $lab): ?>
                        <option value="<?= $h((string) $k) ?>"><?= $h((string) $lab) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span class="eff-section-label">Titre</span>
                <input type="text" name="title" maxlength="200" placeholder="Ex. Charte signée 2026">
            </label>
            <label>
                <span class="eff-section-label">Chemin / URL</span>
                <input type="text" name="file_path" maxlength="500" placeholder="/storage/… ou lien">
            </label>
            <label>
                <span class="eff-section-label">Visibilité</span>
                <select name="visibility">
                    <option value="STAFF">État-major uniquement</option>
                    <option value="MEMBER">Visible membre</option>
                </select>
            </label>
            <label style="flex:1;min-width:12rem">
                <span class="eff-section-label">Description</span>
                <input type="text" name="description" maxlength="500" placeholder="Contexte…">
            </label>
            <button type="submit" class="eff-catalog__btn eff-catalog__btn--primary">Ajouter</button>
        </form>
        <?php endif; ?>

        <?php if ($docs === []): ?>
            <div class="eff-catalog__empty"><strong>Aucun document RH pour l’instant.</strong></div>
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
                        ?>
                        <tr>
                            <td><strong class="eff-sheets__name"><?= $h($name) ?></strong></td>
                            <td><?= $h((string) ($typeLabels[$type] ?? $type)) ?></td>
                            <td><?= $h((string) ($d['title'] ?? '')) ?><?php if ($path !== ''): ?><br><span class="eff-sheets__meta"><?= $h($path) ?></span><?php endif; ?></td>
                            <td><?= ($d['visibility'] ?? '') === 'MEMBER' ? 'Membre' : 'État-major' ?></td>
                            <td><?= $h((string) ($d['created_at'] ?? '')) ?></td>
                            <td><?php if ($uid > 0): ?><a class="is-primary" href="<?= $h(effectifs_workspace_url('membres/' . $uid)) ?>">Fiche</a><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
