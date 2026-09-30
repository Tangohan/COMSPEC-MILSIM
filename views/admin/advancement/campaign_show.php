<?php
$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
$campaign = is_array($campaign ?? null) ? $campaign : [];
$candidacies = is_array($candidacies ?? null) ? $candidacies : [];
$personnel = is_array($personnel ?? null) ? $personnel : [];
$billets = is_array($billets ?? null) ? $billets : [];
$id = (int) ($campaign['id'] ?? 0);
$status = (string) ($campaign['status'] ?? '');
$open = $status === 'ouverte';
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
?>
<div class="adv-page">
    <?php require __DIR__ . '/_flash.php'; ?>
    <section class="adv-panel">
        <h2><?= $h((string) ($campaign['grade_label'] ?? 'Campagne')) ?> · <?= (int) ($campaign['year'] ?? 0) ?></h2>
        <p>Statut : <?= $h($status) ?>. Fenêtre du <?= $h((string) ($campaign['opens_at'] ?? '')) ?> au <?= $h((string) ($campaign['closes_at'] ?? '')) ?>.</p>
        <div class="adv-actions">
            <?php if ($open): ?>
                <form method="post" action="<?= $h(url('back-office/rh/avancement/' . $id . '/cloturer')) ?>"><?= \App\Core\Csrf::field() ?><button class="ath-btn" type="submit">Clôturer les candidatures</button></form>
            <?php endif; ?>
            <?php if (in_array($status, ['ouverte', 'cloturee'], true)): ?>
                <form method="post" action="<?= $h(url('back-office/rh/avancement/' . $id . '/commission')) ?>"><?= \App\Core\Csrf::field() ?><button class="ath-btn ath-btn--solid" type="submit">Passer en commission</button></form>
            <?php endif; ?>
            <?php if ($status === 'en_commission' || $status === 'publiee'): ?>
                <a class="ath-btn ath-btn--solid" href="<?= $h(url('back-office/rh/avancement/' . $id . '/commission')) ?>">Ouvrir la commission</a>
            <?php endif; ?>
            <?php if ($status !== 'publiee'): ?>
                <form method="post" action="<?= $h(url('back-office/rh/avancement/' . $id . '/reverifier')) ?>"><?= \App\Core\Csrf::field() ?><button class="ath-btn" type="submit">Revérifier l’éligibilité</button></form>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($open): ?>
    <section class="adv-panel">
        <h2>Ajouter une candidature</h2>
        <form method="post" action="<?= $h(url('back-office/rh/avancement/' . $id . '/candidatures')) ?>" class="adv-inline">
            <?= \App\Core\Csrf::field() ?>
            <select name="personnel_id" required>
                <option value="">Personnel</option>
                <?php foreach ($personnel as $person): ?>
                    <option value="<?= (int) $person['id'] ?>"><?= $h($personLabel($person)) ?></option>
                <?php endforeach; ?>
            </select>
            <label class="adv-check"><input type="checkbox" name="mobility_requested" value="1"> Mobilité</label>
            <select name="requested_billet_id">
                <option value="">Poste visé (informatif)</option>
                <?php foreach ($billets as $billet): ?>
                    <option value="<?= (int) $billet['id'] ?>"><?= $h((string) ($billet['title'] ?? $billet['code'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="ath-btn" type="submit">Enregistrer</button>
        </form>
    </section>
    <?php endif; ?>

    <div class="adv-table-wrap">
        <table class="adv-table">
            <thead>
                <tr>
                    <th>Personnel</th>
                    <th>Éligibilité</th>
                    <th>Classement</th>
                    <th>Mobilité</th>
                    <th>Avis</th>
                    <th>Décision</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($candidacies as $row): ?>
                <?php
                $rank = $row['preference_rank'] ?? null;
                $rankLabel = ($rank === null || $rank === '') ? '—' : ((int) $rank . '/' . max(1, (int) ($rankedTotal ?? 0)));
                ?>
                <tr>
                    <td><?= $h($personLabel($row)) ?></td>
                    <td><?= !empty($row['is_eligible']) ? 'Éligible' : $h((string) ($row['eligibility_reason'] ?? 'Non éligible')) ?></td>
                    <td><?= $h($rankLabel) ?></td>
                    <td><?= !empty($row['mobility_requested']) ? 'Oui' : 'Non' ?></td>
                    <td><?= $h((string) ($row['commission_opinion'] ?? '—')) ?></td>
                    <td><?= $h((string) ($row['decision'] ?? '—')) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($candidacies === []): ?>
                <tr><td colspan="6">Aucune candidature.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
