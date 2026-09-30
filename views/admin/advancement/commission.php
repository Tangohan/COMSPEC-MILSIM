<?php
require __DIR__ . '/_helpers.php';
$campaign = is_array($campaign ?? null) ? $campaign : [];
$candidacies = is_array($candidacies ?? null) ? $candidacies : [];
$commission = is_array($commission ?? null) ? $commission : [];
$personnel = is_array($personnel ?? null) ? $personnel : [];
$documents = is_array($documents ?? null) ? $documents : [];
$billets = is_array($billets ?? null) ? $billets : [];
$id = (int) ($campaign['id'] ?? 0);
$locked = (string) ($campaign['status'] ?? '') === 'publiee';
$ranked = 0;
$exceptionCount = 0;
foreach ($candidacies as $row) {
    if (($row['preference_rank'] ?? '') !== '' && $row['preference_rank'] !== null) {
        $ranked++;
    }
    if (!empty($row['is_forced']) || !empty($row['exceptional_override'])) {
        $exceptionCount++;
    }
}
$billetTitle = [];
foreach ($billets as $billet) {
    $billetTitle[(int) $billet['id']] = (string) ($billet['title'] ?? $billet['code'] ?? '');
}
$quota = $campaign['quota_slots'] ?? null;
?>
<div class="adv-page" data-adv-page="commission">
    <?php require __DIR__ . '/_flash.php'; ?>
    <p class="adv-muted"><a href="<?= adv_h(url('back-office/rh/avancement/' . $id)) ?>">Retour aux candidatures</a></p>

    <section class="adv-panel">
        <p class="adv-kicker">Commission</p>
        <h2><?= adv_h((string) ($campaign['grade_label'] ?? 'Tableau')) ?> · <?= (int) ($campaign['year'] ?? 0) ?></h2>
        <p>Trois actes distincts : l’<strong>avis</strong> (proposition de la commission), la <strong>décision</strong> (inscrit ou non), puis la <strong>publication</strong> qui crée le nouveau grade. Un personnel hors critères peut être inscrit <strong>exceptionnellement</strong>, avec motif.</p>
    </section>

    <?php require __DIR__ . '/_detections.php'; ?>

    <form method="post" action="<?= adv_h(url('back-office/rh/avancement/' . $id . '/commission/enregistrer')) ?>" class="adv-stack" id="adv-commission-form">
        <?= \App\Core\Csrf::field() ?>
        <section class="adv-panel">
            <h2>Réunion <?= adv_info('Réunion', 'Date de séance et procès-verbal. Ces éléments documentent la commission ; ils ne publient pas le tableau.') ?></h2>
            <div class="adv-grid">
                <label>Date de séance
                    <input type="date" name="meeting_date" value="<?= adv_h((string) ($commission['meeting_date'] ?? '')) ?>"<?= $locked ? ' disabled' : '' ?>>
                </label>
                <label>Procès-verbal
                    <select name="minutes_document_id" class="adv-search" data-placeholder="Aucun document"<?= $locked ? ' disabled' : '' ?>>
                        <option value="">Aucun document</option>
                        <?php foreach ($documents as $document): ?>
                            <option value="<?= (int) $document['id'] ?>"<?= (string) ($commission['minutes_document_id'] ?? '') === (string) $document['id'] ? ' selected' : '' ?>><?= adv_h((string) ($document['title'] ?? 'Document #' . $document['id'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <h3>Membres <?= adv_info('Membres', 'Titulaires et suppléants qui délibèrent. Un membre ne devrait pas juger sa propre candidature : la détection le signale.') ?></h3>
            <?php
            $members = is_array($commission['members'] ?? null) ? $commission['members'] : [];
            for ($i = 0; $i < 6; $i++):
                $member = $members[$i] ?? [];
            ?>
                <div class="adv-inline adv-member-row">
                    <select name="members[<?= $i ?>][personnel_id]" class="adv-search" data-placeholder="Choisir un membre"<?= $locked ? ' disabled' : '' ?>>
                        <option value="">Aucun</option>
                        <?php foreach ($personnel as $person): ?>
                            <option value="<?= (int) $person['id'] ?>"<?= (string) ($member['personnel_id'] ?? '') === (string) $person['id'] ? ' selected' : '' ?>><?= adv_h(adv_option($person)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="members[<?= $i ?>][role]"<?= $locked ? ' disabled' : '' ?>>
                        <option value="titulaire"<?= (string) ($member['role'] ?? 'titulaire') === 'titulaire' ? ' selected' : '' ?>>Titulaire</option>
                        <option value="suppleant"<?= (string) ($member['role'] ?? '') === 'suppleant' ? ' selected' : '' ?>>Suppléant</option>
                    </select>
                </div>
            <?php endfor; ?>
        </section>

        <section class="adv-panel">
            <div class="adv-section-head">
                <h2>Classement <?= adv_info('Classement', 'Ordre 1, 2, 3… sans trou ni doublon. Le bouton aligne automatiquement : éligibles d’abord, puis mois de grade, puis date de candidature.') ?></h2>
                <?php if (!$locked): ?>
                    <button class="ath-btn" type="submit" form="adv-autorank" formaction="<?= adv_h(url('back-office/rh/avancement/' . $id . '/commission/classer')) ?>">Classer automatiquement</button>
                <?php endif; ?>
            </div>
            <p class="adv-muted"><?= $ranked ?> rang<?= $ranked > 1 ? 's' : '' ?> saisi<?= $ranked > 1 ? 's' : '' ?><?php if ($quota !== null && $quota !== ''): ?> · quota <?= (int) $quota ?><?php endif; ?><?php if ($exceptionCount > 0): ?> · <?= $exceptionCount ?> passage<?= $exceptionCount > 1 ? 's' : '' ?> exceptionnel<?= $exceptionCount > 1 ? 's' : '' ?><?php endif; ?>.</p>
        </section>

        <?php foreach ($candidacies as $row): ?>
            <?php
            $cid = (int) $row['id'];
            $eligible = !empty($row['is_eligible']) || !empty($row['live_eligible']);
            $forced = !empty($row['is_forced']) || !empty($row['exceptional_override']);
            $reasonLive = (string) ($row['live_reason'] ?? $row['eligibility_reason'] ?? 'Non éligible');
            $months = (int) ($row['months_in_grade'] ?? 0);
            $need = $row['months_required'] ?? null;
            ?>
            <article class="adv-panel adv-candidate<?= $eligible ? '' : ' is-ineligible' ?><?= $forced ? ' is-exception' : '' ?>" data-adv-candidate>
                <div class="adv-section-head">
                    <h2><?= adv_h(adv_person($row)) ?></h2>
                    <?php if ($eligible): ?>
                        <span class="adv-badge adv-badge--ok">Éligible</span>
                    <?php elseif ($forced): ?>
                        <span class="adv-badge adv-badge--exception">Exception</span>
                    <?php else: ?>
                        <span class="adv-badge adv-badge--bad">Non éligible</span>
                    <?php endif; ?>
                </div>
                <p><?= $eligible ? 'Les critères du grade visé sont réunis.' : adv_h($reasonLive) ?></p>
                <p class="adv-muted">
                    Temps de grade : <?= $months ?> mois<?php if ($need !== null && $need !== ''): ?> sur <?= (int) $need ?> requis<?php endif; ?>.
                    <?php if (!empty($row['mobility_requested'])): ?>
                        Mobilité demandée<?php if (!empty($row['requested_billet_id'])): ?> — poste visé : <?= adv_h($billetTitle[(int) $row['requested_billet_id']] ?? 'poste') ?><?php endif; ?> (informatif, non réservé).
                    <?php endif; ?>
                </p>
                <?php if (!$eligible): ?>
                    <div class="adv-exception">
                        <label class="adv-check">
                            <input type="checkbox" name="candidacy[<?= $cid ?>][exceptional_override]" value="1" data-adv-force<?= $forced ? ' checked' : '' ?><?= $locked ? ' disabled' : '' ?>>
                            Inscrire exceptionnellement <?= adv_info('Passage exceptionnel', 'La commission peut passer outre le temps de grade ou une qualification manquante. Le motif est obligatoire et reste dans le dossier. La publication n’est plus bloquée.') ?>
                        </label>
                        <label>Motif du passage exceptionnel
                            <textarea name="candidacy[<?= $cid ?>][exceptional_reason]" rows="2" maxlength="500" placeholder="Ex. besoin opérationnel, dérogation du commandement…"<?= $locked ? ' disabled' : '' ?>><?= adv_h((string) ($row['exceptional_reason'] ?? '')) ?></textarea>
                        </label>
                    </div>
                <?php endif; ?>
                <div class="adv-grid adv-grid--3">
                    <label>Classement
                        <input type="number" min="1" name="candidacy[<?= $cid ?>][preference_rank]" value="<?= adv_h((string) ($row['preference_rank'] ?? '')) ?>" data-adv-rank<?= $locked ? ' disabled' : '' ?>>
                    </label>
                    <label>Avis <?= adv_info('Avis', 'Proposition de la commission. Distinct de la décision d’inscription au tableau.') ?>
                        <select name="candidacy[<?= $cid ?>][commission_opinion]"<?= $locked ? ' disabled' : '' ?>>
                            <option value="">Non renseigné</option>
                            <option value="propose"<?= (string) ($row['commission_opinion'] ?? '') === 'propose' ? ' selected' : '' ?>>Proposé</option>
                            <option value="non_propose"<?= (string) ($row['commission_opinion'] ?? '') === 'non_propose' ? ' selected' : '' ?>>Non proposé</option>
                        </select>
                    </label>
                    <label>Décision <?= adv_info('Décision', 'Inscrit = promu à la publication, dans la limite du quota. Non inscrit = dossier clos pour cette campagne.') ?>
                        <select name="candidacy[<?= $cid ?>][decision]"<?= $locked ? ' disabled' : '' ?>>
                            <option value="">En attente</option>
                            <option value="inscrit"<?= (string) ($row['decision'] ?? '') === 'inscrit' ? ' selected' : '' ?>>Inscrit au tableau</option>
                            <option value="non_inscrit"<?= (string) ($row['decision'] ?? '') === 'non_inscrit' ? ' selected' : '' ?>>Non inscrit</option>
                        </select>
                    </label>
                </div>
                <?php if (($row['preference_rank'] ?? '') !== '' && $row['preference_rank'] !== null): ?>
                    <p class="adv-muted">Rang <?= (int) $row['preference_rank'] ?> / <?= max(1, $ranked) ?></p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>

        <?php if ($candidacies === []): ?>
            <p class="adv-muted">Aucune candidature à examiner.</p>
        <?php endif; ?>

        <?php if (!$locked): ?>
            <div class="adv-actions">
                <button class="ath-btn" type="submit">Enregistrer la commission</button>
            </div>
        <?php endif; ?>
    </form>

    <?php if (!$locked): ?>
        <form method="post" id="adv-autorank" action="<?= adv_h(url('back-office/rh/avancement/' . $id . '/commission/classer')) ?>">
            <?= \App\Core\Csrf::field() ?>
        </form>
    <?php endif; ?>

    <?php if (!$locked && (string) ($campaign['status'] ?? '') === 'en_commission'): ?>
        <section class="adv-panel">
            <h2>Publication <?= adv_info('Publication', 'Irréversible. Crée la ligne d’historique de grade. Un inscrit non éligible bloque, sauf passage exceptionnel motivé.') ?></h2>
            <form method="post" action="<?= adv_h(url('back-office/rh/avancement/' . $id . '/publier')) ?>" data-adv-publish="<?= $exceptionCount > 0 ? '1' : '0' ?>">
                <?= \App\Core\Csrf::field() ?>
                <button class="ath-btn ath-btn--solid" type="submit">Publier le tableau d’avancement</button>
            </form>
            <form method="post" action="<?= adv_h(url('back-office/rh/avancement/' . $id . '/reverifier')) ?>">
                <?= \App\Core\Csrf::field() ?>
                <button class="ath-btn" type="submit">Revérifier l’éligibilité</button>
            </form>
        </section>
    <?php endif; ?>
</div>
<script src="<?= adv_h(asset_url('assets/js/back-office-advancement.js')) ?>"></script>
