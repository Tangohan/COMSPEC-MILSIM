<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $prefabs */
$prefabs = is_array($prefabs ?? null) ? $prefabs : [];
/** @var array<string,string> $paperStyles */
$paperStyles = is_array($paperStyles ?? null) ? $paperStyles : [];
/** @var array<string,mixed>|null $editPrefab */
$editPrefab = is_array($editPrefab ?? null) ? $editPrefab : null;
$activePrefabCode = (string) ($activePrefabCode ?? '');
$canManage = (bool) ($canManage ?? false);
$edit = is_array($editPrefab) ? $editPrefab : [];
$previewDoc = [
    'title' => 'Aperçu — présentation active',
    'reference_code' => 'DOC-PREVIEW-0001',
    'classification' => 'confidentiel',
    'classification_label' => 'Confidentiel',
    'document_type_label' => 'Note',
    'status' => 'brouillon',
    'status_label' => 'Brouillon',
    'author_label' => 'Bureau SSE',
    'body' => "Ceci est un aperçu de la feuille telle qu’elle apparaît à la rédaction.\n\nModifiez le bandeau, le pied de page ou l’aspect du papier à gauche : le rendu se met à jour après enregistrement.",
    'created_at' => date('Y-m-d H:i:s'),
];
$document = $previewDoc;
$documentChrome = is_array($documentChrome ?? null) ? $documentChrome : $edit;
$livePreview = false;
?>
<div class="sse-page-head">
    <nav class="sse-breadcrumb" aria-label="Fil d’Ariane">
        <a class="link" href="<?= $h(url('atak/sse/documents')) ?>">Rédaction</a> /
        <span>Présentation</span>
    </nav>
    <div class="sse-page-head__row">
        <div>
            <h1>Présentation des documents</h1>
            <p class="sse-lede">
                Choisissez un modèle préfait ou personnalisez le bandeau, les titres, le pied de page
                (« Ne constitue pas une preuve… ») et l’aspect du papier. Ces réglages s’appliquent
                à l’aperçu papier du bureau. En mission, le chef de mission peut reprendre le même
                modèle depuis Eden ou Zeus.
            </p>
        </div>
    </div>
</div>

<div class="sse-presentation-grid">
    <section class="sse-card sse-presentation-models" aria-labelledby="sse-pres-models">
        <h2 id="sse-pres-models">Modèles préfaits</h2>
        <p class="sse-muted">Papier propre, taché, froissé ou jauni — avec textes déjà rédigés.</p>
        <ul class="sse-presentation-list">
            <?php foreach ($prefabs as $prefab): ?>
                <?php
                $code = (string) ($prefab['code'] ?? '');
                $isActive = $code === $activePrefabCode;
                $styleKey = (string) ($prefab['paper_style'] ?? 'clean');
                $styleLabel = $paperStyles[$styleKey] ?? $styleKey;
                ?>
                <li class="sse-presentation-item<?= $isActive ? ' is-active' : '' ?>">
                    <div class="sse-presentation-item__main">
                        <strong><?= $h((string) ($prefab['label'] ?? $code)) ?></strong>
                        <span class="sse-presentation-item__meta"><?= $h($styleLabel) ?></span>
                        <?php if (!empty($prefab['description'])): ?>
                            <p><?= $h((string) $prefab['description']) ?></p>
                        <?php endif; ?>
                        <?php if ($isActive): ?>
                            <span class="sse-pill">En service</span>
                        <?php endif; ?>
                    </div>
                    <div class="sse-presentation-item__actions">
                        <a class="btn btn--ghost btn--sm" href="<?= $h(url('atak/sse/presentation?modifier=' . rawurlencode($code))) ?>">Modifier</a>
                        <?php if ($canManage && !$isActive): ?>
                            <form method="post" action="<?= $h(url('atak/sse/presentation/actif')) ?>">
                                <input type="hidden" name="_csrf_token" value="<?= $h(\App\Core\Csrf::token()) ?>">
                                <input type="hidden" name="prefab_code" value="<?= $h($code) ?>">
                                <button type="submit" class="btn btn--sm">Utiliser</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="sse-card sse-presentation-editor" aria-labelledby="sse-pres-edit">
        <h2 id="sse-pres-edit"><?= $canManage ? 'Personnaliser' : 'Détail du modèle' ?></h2>
        <?php if (!$canManage): ?>
            <p class="sse-muted">Consultation seule — l’administration des modèles est réservée aux personnels habilités.</p>
        <?php endif; ?>
        <form method="post" action="<?= $h(url('atak/sse/presentation')) ?>" class="sse-form sse-presentation-form"<?= $canManage ? '' : ' onsubmit="return false;"' ?>>
            <input type="hidden" name="_csrf_token" value="<?= $h(\App\Core\Csrf::token()) ?>">
            <input type="hidden" name="code" value="<?= $h((string) ($edit['code'] ?? '')) ?>">

            <label for="pres-label">Nom du modèle</label>
            <input id="pres-label" name="label" type="text" required maxlength="160"
                   value="<?= $h((string) ($edit['label'] ?? '')) ?>" <?= $canManage ? '' : 'readonly' ?>>

            <label for="pres-desc">Description</label>
            <textarea id="pres-desc" name="description" rows="2" maxlength="400" <?= $canManage ? '' : 'readonly' ?>><?= $h((string) ($edit['description'] ?? '')) ?></textarea>

            <label for="pres-style">Aspect du papier</label>
            <select id="pres-style" name="paper_style" <?= $canManage ? '' : 'disabled' ?>>
                <?php foreach ($paperStyles as $styleKey => $styleLabel): ?>
                    <option value="<?= $h($styleKey) ?>"<?= ((string) ($edit['paper_style'] ?? '') === $styleKey) ? ' selected' : '' ?>>
                        <?= $h($styleLabel) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="pres-banner">Bandeau (en-tête de feuille)</label>
            <input id="pres-banner" name="banner" type="text" required maxlength="220"
                   value="<?= $h((string) ($edit['banner'] ?? '')) ?>" <?= $canManage ? '' : 'readonly' ?>>

            <div class="sse-form-grid-2">
                <div>
                    <label for="pres-title-person">Titre — fiche personne</label>
                    <input id="pres-title-person" name="title_person" type="text" maxlength="120"
                           value="<?= $h((string) ($edit['title_person'] ?? '')) ?>" <?= $canManage ? '' : 'readonly' ?>>
                </div>
                <div>
                    <label for="pres-title-docs">Titre — dossier documentaire</label>
                    <input id="pres-title-docs" name="title_docs" type="text" maxlength="120"
                           value="<?= $h((string) ($edit['title_docs'] ?? '')) ?>" <?= $canManage ? '' : 'readonly' ?>>
                </div>
            </div>

            <label for="pres-sub-dossier">Sous-titre — compte rendu</label>
            <input id="pres-sub-dossier" name="subtitle_dossier" type="text" maxlength="180"
                   value="<?= $h((string) ($edit['subtitle_dossier'] ?? '')) ?>" <?= $canManage ? '' : 'readonly' ?>>

            <label for="pres-sub-feuille">Sous-titre — feuille détaillée</label>
            <input id="pres-sub-feuille" name="subtitle_feuille" type="text" maxlength="180"
                   value="<?= $h((string) ($edit['subtitle_feuille'] ?? '')) ?>" <?= $canManage ? '' : 'readonly' ?>>

            <label for="pres-sub-docs">Sous-titre — pièces</label>
            <input id="pres-sub-docs" name="subtitle_docs" type="text" maxlength="180"
                   value="<?= $h((string) ($edit['subtitle_docs'] ?? '')) ?>" <?= $canManage ? '' : 'readonly' ?>>

            <label for="pres-footer">Pied de page (mention RP / preuve)</label>
            <textarea id="pres-footer" name="footer" rows="2" required maxlength="400" <?= $canManage ? '' : 'readonly' ?>><?= $h((string) ($edit['footer'] ?? '')) ?></textarea>

            <label for="pres-quality">Libellé qualité d’exploitation</label>
            <input id="pres-quality" name="quality_prefix" type="text" maxlength="80"
                   value="<?= $h((string) ($edit['quality_prefix'] ?? '')) ?>" <?= $canManage ? '' : 'readonly' ?>>

            <div class="sse-form-grid-2">
                <div>
                    <label for="pres-org">Ligne d’organisme</label>
                    <input id="pres-org" name="org_line" type="text" maxlength="120"
                           value="<?= $h((string) ($edit['org_line'] ?? '')) ?>" <?= $canManage ? '' : 'readonly' ?>>
                </div>
                <div>
                    <label for="pres-access">Note d’accès</label>
                    <input id="pres-access" name="access_note" type="text" maxlength="400"
                           value="<?= $h((string) ($edit['access_note'] ?? '')) ?>" <?= $canManage ? '' : 'readonly' ?>>
                </div>
            </div>

            <div class="sse-form-grid-2">
                <div>
                    <label for="pres-seal-top">Sceau — haut</label>
                    <input id="pres-seal-top" name="seal_top" type="text" maxlength="80"
                           value="<?= $h((string) ($edit['seal_top'] ?? '')) ?>" <?= $canManage ? '' : 'readonly' ?>>
                </div>
                <div>
                    <label for="pres-seal-bottom">Sceau — bas</label>
                    <input id="pres-seal-bottom" name="seal_bottom" type="text" maxlength="80"
                           value="<?= $h((string) ($edit['seal_bottom'] ?? '')) ?>" <?= $canManage ? '' : 'readonly' ?>>
                </div>
            </div>

            <div class="sse-form-grid-3">
                <div>
                    <label for="pres-btn-c">Bouton consultation</label>
                    <input id="pres-btn-c" name="btn_consult" type="text" maxlength="40"
                           value="<?= $h((string) ($edit['btn_consult'] ?? 'FEUILLE')) ?>" <?= $canManage ? '' : 'readonly' ?>>
                </div>
                <div>
                    <label for="pres-btn-t">Bouton transmettre</label>
                    <input id="pres-btn-t" name="btn_transmit" type="text" maxlength="40"
                           value="<?= $h((string) ($edit['btn_transmit'] ?? 'TRANSMETTRE')) ?>" <?= $canManage ? '' : 'readonly' ?>>
                </div>
                <div>
                    <label for="pres-btn-x">Bouton fermer</label>
                    <input id="pres-btn-x" name="btn_close" type="text" maxlength="40"
                           value="<?= $h((string) ($edit['btn_close'] ?? 'FERMER')) ?>" <?= $canManage ? '' : 'readonly' ?>>
                </div>
            </div>

            <?php if ($canManage): ?>
                <label class="sse-check">
                    <input type="checkbox" name="make_active" value="1" checked>
                    Utiliser ce modèle dès l’enregistrement
                </label>
                <div class="sse-form-actions">
                    <button type="submit" class="btn">Enregistrer la présentation</button>
                    <a class="btn btn--ghost" href="<?= $h(url('atak/sse/documents')) ?>">Retour rédaction</a>
                </div>
            <?php endif; ?>
        </form>

        <aside class="sse-presentation-mission" aria-label="En mission">
            <h3>En Eden / Zeus</h3>
            <p>
                Posez le module <strong>Présentation des documents SSE</strong> (catégorie COMSPEC SSE)
                et choisissez le même modèle, ou saisissez bandeau et pied de page librement.
                Les opérateurs voient alors la feuille personnalisée sur le terminal.
            </p>
        </aside>
    </section>

    <section class="sse-presentation-preview" aria-labelledby="sse-pres-preview">
        <h2 id="sse-pres-preview" class="sse-doc-preview-label">Aperçu papier</h2>
        <?php require __DIR__ . '/partials/document_paper.php'; ?>
    </section>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
