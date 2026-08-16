<?php
declare(strict_types=1);
/**
 * Modales dossier SSE — synthèse, identités, pièces (+ remontées Arma).
 * @var string $caseUrl
 * @var array $case
 * @var bool $canManage
 * @var array $classifications
 * @var array $statuses
 * @var list $availablePeople
 * @var list $linkedIds
 * @var list $armaInbox
 * @var list $armaSeizures
 * @var list $identityQuick
 * @var list $identityStatuses
 * @var list $evidencePresets
 */
if (empty($canManage)) {
    return;
}
$h = $h ?? static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$armaInbox = is_array($armaInbox ?? null) ? $armaInbox : [];
$armaSeizures = is_array($armaSeizures ?? null) ? $armaSeizures : [];
$identityQuick = is_array($identityQuick ?? null) ? $identityQuick : [];
$identityStatuses = is_array($identityStatuses ?? null) ? $identityStatuses : [];
$evidencePresets = is_array($evidencePresets ?? null) ? $evidencePresets : [];
$availablePeople = is_array($availablePeople ?? null) ? $availablePeople : [];
$linkedIds = is_array($linkedIds ?? null) ? $linkedIds : [];
$classifications = is_array($classifications ?? null) ? $classifications : [];
$statuses = is_array($statuses ?? null) ? $statuses : [];
?>
<dialog class="sse-modal" id="sse-modal-summary">
    <form method="post" action="<?= $h($caseUrl) ?>" class="sse-modal__card">
        <?= \App\Core\Csrf::field() ?>
        <header class="sse-modal__head">
            <div>
                <p class="sse-modal__kicker">Dossier</p>
                <h2>Synthèse et classification</h2>
            </div>
            <button type="button" class="sse-modal__close" data-sse-modal-close aria-label="Fermer">×</button>
        </header>
        <div class="sse-modal__body">
            <label for="modal-title">Intitulé</label>
            <input id="modal-title" name="title" type="text" required value="<?= $h($case['title'] ?? '') ?>">
            <div class="grid-2">
                <div>
                    <label for="modal-classification">Qui a le droit de le lire</label>
                    <select id="modal-classification" name="classification">
                        <?php foreach ($classifications as $k => $lab): ?>
                            <option value="<?= $h($k) ?>" <?= ($case['classification'] ?? '') === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="modal-status">Où en est le dossier</label>
                    <select id="modal-status" name="status">
                        <?php foreach ($statuses as $k => $lab): ?>
                            <option value="<?= $h($k) ?>" <?= ($case['status'] ?? '') === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <label for="modal-summary">Synthèse</label>
            <textarea id="modal-summary" name="summary" rows="6"><?= $h($case['summary'] ?? '') ?></textarea>
        </div>
        <footer class="sse-modal__foot">
            <button type="button" class="btn btn--ghost" data-sse-modal-close>Annuler</button>
            <button class="btn" type="submit">Enregistrer</button>
        </footer>
    </form>
</dialog>

<dialog class="sse-modal" id="sse-modal-identity">
    <div class="sse-modal__card">
        <header class="sse-modal__head">
            <div>
                <p class="sse-modal__kicker">Identités</p>
                <h2>Ajouter au dossier</h2>
            </div>
            <button type="button" class="sse-modal__close" data-sse-modal-close aria-label="Fermer">×</button>
        </header>
        <div class="sse-modal__tabs" role="tablist">
            <button type="button" class="is-active" data-sse-tab="create" role="tab">Créer</button>
            <button type="button" data-sse-tab="link" role="tab">Rattacher</button>
            <button type="button" data-sse-tab="arma" role="tab">
                Remontées Arma<?php if ($armaInbox !== []): ?> (<?= count($armaInbox) ?>)<?php endif; ?>
            </button>
        </div>
        <div class="sse-modal__body" data-sse-tab-panel="create">
            <?php if ($identityQuick !== []): ?>
                <p class="sse-modal__label">Modèles rapides</p>
                <div class="sse-preset-row">
                    <?php foreach ($identityQuick as $q): ?>
                        <button type="button" class="sse-preset"
                                data-id-preset
                                data-alias="<?= $h($q['alias'] ?? '') ?>"
                                data-status="<?= $h($q['status'] ?? 'civil') ?>"
                                data-circumstances="<?= $h($q['circumstances'] ?? '') ?>">
                            <?= $h($q['label'] ?? '') ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="post" action="<?= $h($caseUrl . '/personnes/creer') ?>">
                <?= \App\Core\Csrf::field() ?>
                <div class="grid-2">
                    <div>
                        <label for="id-last">Nom</label>
                        <input id="id-last" name="last_name" type="text" placeholder="Nom de famille">
                    </div>
                    <div>
                        <label for="id-first">Prénom</label>
                        <input id="id-first" name="first_name" type="text" placeholder="Prénom">
                    </div>
                </div>
                <label for="id-alias">Alias / indicatif</label>
                <input id="id-alias" name="alias" type="text" placeholder="Ex. ABU KARIM">
                <div class="grid-2">
                    <div>
                        <label for="id-status">Statut</label>
                        <select id="id-status" name="status">
                            <?php foreach ($identityStatuses as $st): ?>
                                <option value="<?= $h($st['key'] ?? '') ?>"><?= $h($st['label'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="id-nat">Nationalité</label>
                        <input id="id-nat" name="nationality" type="text">
                    </div>
                </div>
                <label for="id-circ">Circonstances</label>
                <textarea id="id-circ" name="circumstances" rows="3" placeholder="Contexte du rattachement"></textarea>
                <label for="id-aff">Affiliation</label>
                <input id="id-aff" name="affiliation" type="text">
                <footer class="sse-modal__foot sse-modal__foot--inline">
                    <button class="btn" type="submit">Créer et rattacher</button>
                </footer>
            </form>
        </div>
        <div class="sse-modal__body" data-sse-tab-panel="link" hidden>
            <form method="post" action="<?= $h($caseUrl . '/personnes') ?>">
                <?= \App\Core\Csrf::field() ?>
                <label for="person_id_modal">Fiche existante</label>
                <select id="person_id_modal" name="person_id" required>
                    <option value="">Choisir…</option>
                    <?php foreach ($availablePeople as $p): ?>
                        <?php if (in_array((int) ($p['id'] ?? 0), $linkedIds, true)) {
                            continue;
                        } ?>
                        <option value="<?= (int) ($p['id'] ?? 0) ?>">
                            <?= $h($p['display_name'] ?? '') ?>
                            <?= !empty($p['from_arma']) ? ' · Terrain' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <footer class="sse-modal__foot sse-modal__foot--inline">
                    <button class="btn" type="submit">Rattacher</button>
                </footer>
            </form>
        </div>
        <div class="sse-modal__body" data-sse-tab-panel="arma" hidden>
            <?php if ($armaInbox === []): ?>
                <p class="muted">Aucune fiche remontée d’Arma en attente. Les transmissions SEEK / terminal apparaîtront ici.</p>
            <?php else: ?>
                <ul class="sse-arma-list">
                    <?php foreach ($armaInbox as $p): ?>
                        <li>
                            <div>
                                <strong><?= $h($p['display_name'] ?? '') ?></strong>
                                <span>
                                    <?= $h($p['status_label'] ?? '') ?>
                                    <?php if (!empty($p['submitter_callsign'])): ?>
                                        · saisie par <?= $h($p['submitter_callsign']) ?>
                                    <?php endif; ?>
                                    <?php if (!empty($p['grid_reference'])): ?>
                                        · <?= $h($p['grid_reference']) ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <form method="post" action="<?= $h($caseUrl . '/personnes') ?>">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="person_id" value="<?= (int) ($p['id'] ?? 0) ?>">
                                <input type="hidden" name="link_note" value="Rattachement depuis remontée Arma">
                                <button class="btn btn--sm" type="submit">Rattacher</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</dialog>

<dialog class="sse-modal" id="sse-modal-evidence">
    <div class="sse-modal__card">
        <header class="sse-modal__head">
            <div>
                <p class="sse-modal__kicker">Pièces</p>
                <h2>Verser au dossier</h2>
            </div>
            <button type="button" class="sse-modal__close" data-sse-modal-close aria-label="Fermer">×</button>
        </header>
        <div class="sse-modal__tabs" role="tablist">
            <button type="button" class="is-active" data-sse-tab="manual" role="tab">Nouvelle pièce</button>
            <button type="button" data-sse-tab="seizure" role="tab">
                Saisies terrain<?php if ($armaSeizures !== []): ?> (<?= count($armaSeizures) ?>)<?php endif; ?>
            </button>
        </div>
        <div class="sse-modal__body" data-sse-tab-panel="manual">
            <?php if ($evidencePresets !== []): ?>
                <p class="sse-modal__label">Types fréquents</p>
                <div class="sse-preset-row">
                    <?php foreach ($evidencePresets as $ep): ?>
                        <button type="button" class="sse-preset"
                                data-ev-preset
                                data-label="<?= $h($ep['label'] ?? '') ?>"
                                data-caption="<?= $h($ep['caption'] ?? '') ?>">
                            <?= $h($ep['label'] ?? '') ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="post" action="<?= $h($caseUrl . '/preuves') ?>" enctype="multipart/form-data">
                <?= \App\Core\Csrf::field() ?>
                <label for="ev-label">De quoi s’agit-il</label>
                <input id="ev-label" name="label" type="text" required placeholder="Ex. Téléphone saisi au point nord">
                <label for="ev-caption">Précision utile</label>
                <input id="ev-caption" name="caption" type="text" placeholder="Où, quand, par qui">
                <label for="ev-image">Photographie</label>
                <input id="ev-image" name="image" type="file" accept="image/*">
                <footer class="sse-modal__foot sse-modal__foot--inline">
                    <button class="btn" type="submit">Verser au dossier</button>
                </footer>
            </form>
        </div>
        <div class="sse-modal__body" data-sse-tab-panel="seizure" hidden>
            <?php if ($armaSeizures === []): ?>
                <p class="muted">Aucune saisie sur les sites rattachés. Les saisies enregistrées en jeu pourront être versées ici.</p>
            <?php else: ?>
                <ul class="sse-arma-list">
                    <?php foreach ($armaSeizures as $sz): ?>
                        <li>
                            <div>
                                <strong><?= $h($sz['label'] ?? 'Saisie') ?></strong>
                                <span>
                                    <?= $h($sz['site_name'] ?? '') ?>
                                    <?php if ((int) ($sz['quantity'] ?? 1) > 1): ?>
                                        · ×<?= (int) $sz['quantity'] ?>
                                    <?php endif; ?>
                                    <?php if (!empty($sz['notes'])): ?>
                                        · <?= $h($sz['notes']) ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <form method="post" action="<?= $h($caseUrl . '/preuves/saisie') ?>">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="seizure_id" value="<?= (int) ($sz['id'] ?? 0) ?>">
                                <input type="hidden" name="site_id" value="<?= (int) ($sz['site_id'] ?? 0) ?>">
                                <button class="btn btn--sm" type="submit">Verser</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</dialog>
