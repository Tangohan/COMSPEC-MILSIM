<?php
$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
$campaign = is_array($campaign ?? null) ? $campaign : [];
$candidacies = is_array($candidacies ?? null) ? $candidacies : [];
$commission = is_array($commission ?? null) ? $commission : [];
$personnel = is_array($personnel ?? null) ? $personnel : [];
$documents = is_array($documents ?? null) ? $documents : [];
$billets = is_array($billets ?? null) ? $billets : [];
$id = (int) ($campaign['id'] ?? 0);
$locked = (string) ($campaign['status'] ?? '') === 'publiee';
$ranked = 0;
foreach ($candidacies as $row) {
    if (($row['preference_rank'] ?? '') !== '' && $row['preference_rank'] !== null) {
        $ranked++;
    }
}
$personLabel = static function (array $row): string {
    $name = trim((string) ($row['display_name'] ?? ''));
    if ($name === '') {
        $name = trim((string) ($row['callsign'] ?? ''));
    }
    if ($name === '') {
        $name = trim((string) ($row['email'] ?? 'Personnel'));
    }

    return $name;
};
$billetTitle = [];
foreach ($billets as $billet) {
    $billetTitle[(int) $billet['id']] = (string) ($billet['title'] ?? $billet['code'] ?? '');
}
?>
<div class="adv-page">
    <?php require __DIR__ . '/_flash.php'; ?>
    <p class="adv-muted"><a href="<?= $h(url('back-office/rh/avancement/' . $id)) ?>">Retour aux candidatures</a></p>
    <form method="post" action="<?= $h(url('back-office/rh/avancement/' . $id . '/commission/enregistrer')) ?>" class="adv-stack">
        <?= \App\Core\Csrf::field() ?>
        <section class="adv-panel">
            <h2>Réunion</h2>
            <label>Date<input type="date" name="meeting_date" value="<?= $h((string) ($commission['meeting_date'] ?? '')) ?>"<?= $locked ? ' disabled' : '' ?>></label>
            <label>Procès-verbal
                <select name="minutes_document_id"<?= $locked ? ' disabled' : '' ?>>
                    <option value="">Aucun document</option>
                    <?php foreach ($documents as $document): ?>
                        <option value="<?= (int) $document['id'] ?>"<?= (string) ($commission['minutes_document_id'] ?? '') === (string) $document['id'] ? ' selected' : '' ?>><?= $h((string) ($document['title'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <h3>Membres</h3>
            <?php
            $members = is_array($commission['members'] ?? null) ? $commission['members'] : [];
            for ($i = 0; $i < 4; $i++):
                $member = $members[$i] ?? [];
            ?>
                <div class="adv-inline">
                    <select name="members[<?= $i ?>][personnel_id]"<?= $locked ? ' disabled' : '' ?>>
                        <option value="">—</option>
                        <?php foreach ($personnel as $person): ?>
                            <option value="<?= (int) $person['id'] ?>"<?= (string) ($member['personnel_id'] ?? '') === (string) $person['id'] ? ' selected' : '' ?>><?= $h($personLabel($person)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="members[<?= $i ?>][role]"<?= $locked ? ' disabled' : '' ?>>
                        <option value="titulaire"<?= (string) ($member['role'] ?? '') === 'titulaire' ? ' selected' : '' ?>>Titulaire</option>
                        <option value="suppleant"<?= (string) ($member['role'] ?? '') === 'suppleant' ? ' selected' : '' ?>>Suppléant</option>
                    </select>
                </div>
            <?php endfor; ?>
        </section>

        <?php foreach ($candidacies as $row): ?>
            <?php $cid = (int) $row['id']; ?>
            <article class="adv-panel">
                <h2><?= $h($personLabel($row)) ?></h2>
                <p><?= !empty($row['is_eligible']) ? 'Éligible' : $h((string) ($row['eligibility_reason'] ?? 'Non éligible')) ?></p>
                <p class="adv-muted">Temps de grade suivi à la revérification. <?php if (!empty($row['mobility_requested'])): ?>Mobilité demandée<?php if (!empty($row['requested_billet_id'])): ?> — poste visé : <?= $h($billetTitle[(int) $row['requested_billet_id']] ?? 'poste') ?><?php endif; ?>. Le poste n’est pas réservé.<?php endif; ?></p>
                <div class="adv-inline">
                    <label>Classement
                        <input type="number" name="candidacy[<?= $cid ?>][preference_rank]" value="<?= $h((string) ($row['preference_rank'] ?? '')) ?>"<?= $locked ? ' disabled' : '' ?>>
                    </label>
                    <label>Avis
                        <select name="candidacy[<?= $cid ?>][commission_opinion]"<?= $locked ? ' disabled' : '' ?>>
                            <option value="">—</option>
                            <option value="propose"<?= (string) ($row['commission_opinion'] ?? '') === 'propose' ? ' selected' : '' ?>>Proposé</option>
                            <option value="non_propose"<?= (string) ($row['commission_opinion'] ?? '') === 'non_propose' ? ' selected' : '' ?>>Non proposé</option>
                        </select>
                    </label>
                    <label>Décision
                        <select name="candidacy[<?= $cid ?>][decision]"<?= $locked ? ' disabled' : '' ?>>
                            <option value="">—</option>
                            <option value="inscrit"<?= (string) ($row['decision'] ?? '') === 'inscrit' ? ' selected' : '' ?>>Inscrit</option>
                            <option value="non_inscrit"<?= (string) ($row['decision'] ?? '') === 'non_inscrit' ? ' selected' : '' ?>>Non inscrit</option>
                        </select>
                    </label>
                </div>
                <?php if (($row['preference_rank'] ?? '') !== '' && $row['preference_rank'] !== null): ?>
                    <p class="adv-muted">Classement affiché <?= (int) $row['preference_rank'] ?>/<?= max(1, $ranked) ?></p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>

        <?php if (!$locked): ?>
            <div class="adv-actions">
                <button class="ath-btn" type="submit">Enregistrer la commission</button>
            </div>
        <?php endif; ?>
    </form>
    <?php if (!$locked && (string) ($campaign['status'] ?? '') === 'en_commission'): ?>
        <form method="post" action="<?= $h(url('back-office/rh/avancement/' . $id . '/publier')) ?>">
            <?= \App\Core\Csrf::field() ?>
            <button class="ath-btn ath-btn--solid" type="submit">Publier le tableau d’avancement</button>
        </form>
        <form method="post" action="<?= $h(url('back-office/rh/avancement/' . $id . '/reverifier')) ?>">
            <?= \App\Core\Csrf::field() ?>
            <button class="ath-btn" type="submit">Revérifier l’éligibilité</button>
        </form>
    <?php endif; ?>
</div>
