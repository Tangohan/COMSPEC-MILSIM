<?php
declare(strict_types=1);
/** @var array<string,mixed> $cycle */
/** @var callable $h */
/** @var bool $canManage */
/** @var string $csrfToken */
/** @var int $selectedCaseId */
$cycle = is_array($cycle ?? null) ? $cycle : [];
$requirements = is_array($cycle['requirements'] ?? null) ? $cycle['requirements'] : [];
$taskings = is_array($cycle['taskings'] ?? null) ? $cycle['taskings'] : [];
$products = is_array($cycle['products'] ?? null) ? $cycle['products'] : [];
$catalog = is_array($cycle['catalog'] ?? null) ? $cycle['catalog'] : [];
$reqTypes = is_array($catalog['requirement_types'] ?? null) ? $catalog['requirement_types'] : [];
$priorities = is_array($catalog['priorities'] ?? null) ? $catalog['priorities'] : [];
$productTypes = is_array($catalog['product_types'] ?? null) ? $catalog['product_types'] : [];
$releaseLevels = is_array($catalog['release_levels'] ?? null) ? $catalog['release_levels'] : [];
$counts = is_array($cycle['counts'] ?? null) ? $cycle['counts'] : [];
$caseId = (int) ($selectedCaseId ?? 0);
?>
<header class="iw-intel-col-head">
    <h2>Cycle de renseignement</h2>
    <span class="iw-folder-meta">
        <span><?= (int) ($counts['requirements'] ?? 0) ?> exigences</span>
        <span><?= (int) ($counts['taskings_open'] ?? 0) ?> ordres ouverts</span>
        <span><?= (int) ($counts['products_pending'] ?? 0) ?> produits en cours</span>
    </span>
</header>

<p class="muted" style="margin:0 0 12px">
    Priorités de renseignement, ordres de collecte, validation, sanitisation et diffusion —
    sans mélanger propositions automatiques et faits confirmés.
</p>

<section class="iw-cycle-block">
    <h3>Exigences (PIR / besoins / éléments essentiels)</h3>
    <?php if ($requirements === []): ?>
        <p class="iw-intel-empty">Aucune exigence ouverte pour ce périmètre.</p>
    <?php else: ?>
        <ul class="iw-intel-list">
            <?php foreach ($requirements as $req): ?>
                <?php if (!is_array($req)) {
                    continue;
                } ?>
                <li>
                    <strong><?= $h((string) ($req['req_type_label'] ?? '')) ?></strong>
                    — <?= $h((string) ($req['title'] ?? '')) ?>
                    <em><?= $h((string) ($req['status_label'] ?? '')) ?> · <?= (int) ($req['coverage_pct'] ?? 0) ?> %</em>
                    <span class="record-sub"><?= $h((string) ($req['question'] ?? '')) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($canManage): ?>
        <form method="post" action="<?= $h(url('atak/sse/workspace/cycle/exigences')) ?>" class="iw-cycle-form">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
            <input type="hidden" name="case_id" value="<?= (int) $caseId ?>">
            <label>Type
                <select name="req_type" required>
                    <?php foreach ($reqTypes as $code => $label): ?>
                        <option value="<?= $h((string) $code) ?>"><?= $h((string) $label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Titre
                <input type="text" name="title" required maxlength="220" placeholder="Ex. Localisation du réseau de soutien">
            </label>
            <label>Question
                <textarea name="question" required rows="2" placeholder="Quelle information doit être obtenue ?"></textarea>
            </label>
            <label>Priorité
                <select name="priority">
                    <?php foreach ($priorities as $code => $label): ?>
                        <option value="<?= $h((string) $code) ?>" <?= $code === 'normale' ? 'selected' : '' ?>><?= $h((string) $label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Responsable
                <input type="text" name="assignee_label" maxlength="160" placeholder="Cellule / équipe">
            </label>
            <label>Critère de satisfaction
                <input type="text" name="confirmation_criterion" maxlength="500" placeholder="Quand l’exigence est-elle considérée comme couverte ?">
            </label>
            <label>Coordonnée terrain X
                <input type="number" step="any" name="pos_x" placeholder="Optionnel — pour la carte">
            </label>
            <label>Coordonnée terrain Y
                <input type="number" step="any" name="pos_y" placeholder="Optionnel — pour la carte">
            </label>
            <label class="iw-cycle-check">
                <input type="checkbox" name="visible_on_atak" value="1" checked>
                Afficher sur la carte tactique
            </label>
            <button type="submit" class="iw-btn iw-btn--solid">Enregistrer l’exigence</button>
        </form>
    <?php endif; ?>
</section>

<section class="iw-cycle-block">
    <h3>Ordres de collecte</h3>
    <?php if ($taskings === []): ?>
        <p class="iw-intel-empty">Aucun ordre de collecte.</p>
    <?php else: ?>
        <ul class="iw-intel-list">
            <?php foreach ($taskings as $task): ?>
                <?php if (!is_array($task)) {
                    continue;
                } ?>
                <li>
                    <strong><?= $h((string) ($task['title'] ?? '')) ?></strong>
                    <em><?= $h((string) ($task['status_label'] ?? '')) ?></em>
                    <?php if (!empty($task['tasked_unit'])): ?>
                        <span class="record-sub">Vers <?= $h((string) $task['tasked_unit']) ?></span>
                    <?php endif; ?>
                    <span class="record-sub"><?= $h((string) ($task['instruction'] ?? '')) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($canManage && $requirements !== []): ?>
        <form method="post" action="<?= $h(url('atak/sse/workspace/cycle/ordres')) ?>" class="iw-cycle-form">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
            <input type="hidden" name="case_id" value="<?= (int) $caseId ?>">
            <label>Exigence liée
                <select name="requirement_id" required>
                    <?php foreach ($requirements as $req): ?>
                        <?php if (!is_array($req)) {
                            continue;
                        } ?>
                        <option value="<?= (int) ($req['id'] ?? 0) ?>">
                            <?= $h((string) ($req['req_type_label'] ?? '') . ' — ' . (string) ($req['title'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Titre de l’ordre
                <input type="text" name="title" required maxlength="220">
            </label>
            <label>Consigne
                <textarea name="instruction" required rows="2"></textarea>
            </label>
            <label>Unité / équipe
                <input type="text" name="tasked_unit" maxlength="160">
            </label>
            <label>Indicatif
                <input type="text" name="tasked_callsign" maxlength="80">
            </label>
            <label>Coordonnée terrain X
                <input type="number" step="any" name="pos_x" placeholder="Optionnel — pour la carte">
            </label>
            <label>Coordonnée terrain Y
                <input type="number" step="any" name="pos_y" placeholder="Optionnel — pour la carte">
            </label>
            <label class="iw-cycle-check">
                <input type="checkbox" name="visible_on_atak" value="1" checked>
                Afficher sur la carte tactique
            </label>
            <button type="submit" class="iw-btn iw-btn--solid">Émettre l’ordre</button>
        </form>
    <?php endif; ?>
</section>

<section class="iw-cycle-block">
    <h3>Produits — validation, sanitisation, diffusion</h3>
    <?php if ($products === []): ?>
        <p class="iw-intel-empty">Aucun produit de cycle pour l’instant.</p>
    <?php else: ?>
        <ul class="iw-intel-list">
            <?php foreach ($products as $prod): ?>
                <?php if (!is_array($prod)) {
                    continue;
                } ?>
                <li>
                    <strong><?= $h((string) ($prod['product_type_label'] ?? '')) ?></strong>
                    — <?= $h((string) ($prod['title'] ?? '')) ?>
                    <em><?= $h((string) ($prod['status_label'] ?? '')) ?> · <?= $h((string) ($prod['release_level_label'] ?? '')) ?></em>
                    <?php if ($canManage): ?>
                        <div class="iw-cycle-product-actions">
                            <?php if (in_array($prod['status'] ?? '', ['brouillon', 'en_relecture'], true)): ?>
                                <form method="post" action="<?= $h(url('atak/sse/workspace/cycle/produits/' . (int) ($prod['id'] ?? 0))) ?>">
                                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                    <input type="hidden" name="case_id" value="<?= (int) $caseId ?>">
                                    <input type="hidden" name="action" value="valider">
                                    <button type="submit" class="iw-btn iw-btn--ghost">Valider</button>
                                </form>
                            <?php endif; ?>
                            <?php if (in_array($prod['status'] ?? '', ['brouillon', 'en_relecture', 'valide'], true)): ?>
                                <form method="post" action="<?= $h(url('atak/sse/workspace/cycle/produits/' . (int) ($prod['id'] ?? 0))) ?>">
                                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                    <input type="hidden" name="case_id" value="<?= (int) $caseId ?>">
                                    <input type="hidden" name="action" value="sanitiser">
                                    <select name="release_level" aria-label="Niveau de diffusion">
                                        <?php foreach ($releaseLevels as $code => $label): ?>
                                            <option value="<?= $h((string) $code) ?>" <?= ((string) ($prod['release_level'] ?? '') === (string) $code) ? 'selected' : '' ?>><?= $h((string) $label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="iw-btn iw-btn--ghost">Sanitiser</button>
                                </form>
                            <?php endif; ?>
                            <?php if (in_array($prod['status'] ?? '', ['valide', 'sanitise'], true)): ?>
                                <form method="post" action="<?= $h(url('atak/sse/workspace/cycle/produits/' . (int) ($prod['id'] ?? 0))) ?>">
                                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                    <input type="hidden" name="case_id" value="<?= (int) $caseId ?>">
                                    <input type="hidden" name="action" value="diffuser">
                                    <input type="text" name="recipients_text" required placeholder="Destinataires (séparés par virgule)" maxlength="400">
                                    <button type="submit" class="iw-btn iw-btn--solid">Diffuser</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($canManage && $caseId > 0): ?>
        <form method="post" action="<?= $h(url('atak/sse/workspace/cycle/produits/generer')) ?>" class="iw-cycle-form">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
            <input type="hidden" name="case_id" value="<?= (int) $caseId ?>">
            <label>Type de compte rendu
                <select name="product_type">
                    <?php foreach ($productTypes as $code => $label): ?>
                        <option value="<?= $h((string) $code) ?>"><?= $h((string) $label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Niveau de diffusion
                <select name="release_level">
                    <?php foreach ($releaseLevels as $code => $label): ?>
                        <option value="<?= $h((string) $code) ?>"><?= $h((string) $label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if ($requirements !== []): ?>
                <label>Exigence liée (optionnel)
                    <select name="requirement_id">
                        <option value="">—</option>
                        <?php foreach ($requirements as $req): ?>
                            <?php if (!is_array($req)) {
                                continue;
                            } ?>
                            <option value="<?= (int) ($req['id'] ?? 0) ?>"><?= $h((string) ($req['title'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
            <button type="submit" class="iw-btn iw-btn--solid">Composer le compte rendu</button>
        </form>
    <?php elseif ($canManage): ?>
        <p class="muted">Ouvrez une chemise dossier pour composer un compte rendu lié au cycle.</p>
    <?php endif; ?>
</section>
