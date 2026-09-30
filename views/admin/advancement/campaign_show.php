<?php
require __DIR__ . '/_helpers.php';
$campaign = is_array($campaign ?? null) ? $campaign : [];
$candidacies = is_array($candidacies ?? null) ? $candidacies : [];
$personnel = is_array($personnel ?? null) ? $personnel : [];
$billets = is_array($billets ?? null) ? $billets : [];
$id = (int) ($campaign['id'] ?? 0);
$status = (string) ($campaign['status'] ?? '');
$open = $status === 'ouverte';
$labels = [
    'ouverte' => 'Ouverte',
    'cloturee' => 'Clôturée',
    'en_commission' => 'En commission',
    'publiee' => 'Publiée',
    'archivee' => 'Archivée',
];
$format = static function (string $iso): string {
    $ts = strtotime(substr($iso, 0, 10));

    return $ts !== false ? date('d/m/Y', $ts) : $iso;
};
?>
<div class="adv-page" data-adv-page="campaign">
    <?php require __DIR__ . '/_flash.php'; ?>
    <section class="adv-panel">
        <p class="adv-kicker">Campagne au choix</p>
        <h2><?= adv_h((string) ($campaign['grade_label'] ?? 'Campagne')) ?> · <?= (int) ($campaign['year'] ?? 0) ?></h2>
        <p>Statut : <strong><?= adv_h($labels[$status] ?? $status) ?></strong>. Fenêtre du <?= adv_h($format((string) ($campaign['opens_at'] ?? ''))) ?> au <?= adv_h($format((string) ($campaign['closes_at'] ?? ''))) ?><?php if (($campaign['quota_slots'] ?? '') !== '' && $campaign['quota_slots'] !== null): ?> · quota <?= (int) $campaign['quota_slots'] ?><?php endif; ?>.</p>
        <p class="adv-muted">La fenêtre reçoit les candidatures. La commission classe et décide. La publication crée le grade : elle ne se corrige pas, on ajoute une nouvelle ligne d’historique.</p>
        <div class="adv-actions">
            <?php if ($open): ?>
                <form method="post" action="<?= adv_h(url('back-office/rh/avancement/' . $id . '/cloturer')) ?>"><?= \App\Core\Csrf::field() ?><button class="ath-btn" type="submit">Clôturer les candidatures</button></form>
            <?php endif; ?>
            <?php if (in_array($status, ['ouverte', 'cloturee'], true)): ?>
                <form method="post" action="<?= adv_h(url('back-office/rh/avancement/' . $id . '/commission')) ?>"><?= \App\Core\Csrf::field() ?><button class="ath-btn ath-btn--solid" type="submit">Passer en commission</button></form>
            <?php endif; ?>
            <?php if ($status === 'en_commission' || $status === 'publiee'): ?>
                <a class="ath-btn ath-btn--solid" href="<?= adv_h(url('back-office/rh/avancement/' . $id . '/commission')) ?>">Ouvrir la commission</a>
            <?php endif; ?>
            <?php if ($status !== 'publiee'): ?>
                <form method="post" action="<?= adv_h(url('back-office/rh/avancement/' . $id . '/reverifier')) ?>"><?= \App\Core\Csrf::field() ?><button class="ath-btn" type="submit">Revérifier l’éligibilité</button></form>
            <?php endif; ?>
        </div>
    </section>

    <?php require __DIR__ . '/_detections.php'; ?>

    <?php if ($open): ?>
    <section class="adv-panel">
        <h2>Ajouter une candidature <?= adv_info('Candidature', 'Le personnel peut être hors critères : il restera non éligible jusqu’à un passage exceptionnel en commission. Le poste visé n’est pas réservé.') ?></h2>
        <form method="post" action="<?= adv_h(url('back-office/rh/avancement/' . $id . '/candidatures')) ?>" class="adv-inline">
            <?= \App\Core\Csrf::field() ?>
            <select name="personnel_id" class="adv-search" data-placeholder="Rechercher un personnel" required>
                <option value="">Personnel</option>
                <?php foreach ($personnel as $person): ?>
                    <option value="<?= (int) $person['id'] ?>"><?= adv_h(adv_option($person)) ?></option>
                <?php endforeach; ?>
            </select>
            <label class="adv-check"><input type="checkbox" name="mobility_requested" value="1"> Mobilité</label>
            <select name="requested_billet_id" class="adv-search" data-placeholder="Poste visé (informatif)">
                <option value="">Poste visé (informatif)</option>
                <?php foreach ($billets as $billet): ?>
                    <option value="<?= (int) $billet['id'] ?>"><?= adv_h((string) ($billet['title'] ?? $billet['code'] ?? '')) ?></option>
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
                    <th>Ancienneté</th>
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
                $eligible = !empty($row['is_eligible']) || !empty($row['live_eligible']);
                $forced = !empty($row['is_forced']);
                ?>
                <tr>
                    <td><?= adv_h(adv_person($row)) ?></td>
                    <td>
                        <?php if ($eligible): ?>
                            <span class="adv-badge adv-badge--ok">Éligible</span>
                        <?php elseif ($forced): ?>
                            <span class="adv-badge adv-badge--exception">Exception</span>
                        <?php else: ?>
                            <span class="adv-badge adv-badge--bad"><?= adv_h((string) ($row['live_reason'] ?? $row['eligibility_reason'] ?? 'Non éligible')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= (int) ($row['months_in_grade'] ?? 0) ?> mois</td>
                    <td><?= adv_h($rankLabel) ?></td>
                    <td><?= !empty($row['mobility_requested']) ? 'Oui' : 'Non' ?></td>
                    <td><?= adv_h((string) ($row['commission_opinion'] ?? '—')) ?></td>
                    <td><?= adv_h((string) ($row['decision'] ?? '—')) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($candidacies === []): ?>
                <tr><td colspan="7">Aucune candidature.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script src="<?= adv_h(asset_url('assets/js/back-office-advancement.js')) ?>"></script>
