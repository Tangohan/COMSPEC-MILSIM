<?php
require __DIR__ . '/_helpers.php';

$campaign = is_array($campaign ?? null) ? $campaign : [];
$candidacies = is_array($candidacies ?? null) ? $candidacies : [];
$personnel = is_array($personnel ?? null) ? $personnel : [];
$billets = is_array($billets ?? null) ? $billets : [];
$id = (int) ($campaign['id'] ?? 0);
$status = (string) ($campaign['status'] ?? '');
$open = $status === 'ouverte' || $status === \App\Support\AdvancementCodes::CAMPAIGN_OPEN;
$format = static function (string $iso): string {
    $ts = strtotime(substr($iso, 0, 10));

    return $ts !== false ? date('d/m/Y', $ts) : $iso;
};
$tone = match ($status) {
    'ouverte', \App\Support\AdvancementCodes::CAMPAIGN_OPEN, 'publiee', \App\Support\AdvancementCodes::CAMPAIGN_PUBLISHED => 'ok',
    'en_commission', \App\Support\AdvancementCodes::CAMPAIGN_IN_COMMISSION => 'wait',
    default => 'quiet',
};
?>
<div class="adv-page" data-adv-page="campaign">
    <?php require __DIR__ . '/_flash.php'; ?>
    <p class="adv-muted"><a href="<?= adv_h(url('back-office/rh/avancement')) ?>">Toutes les campagnes</a></p>
    <section class="adv-panel">
        <p class="adv-kicker">Campagne au choix</p>
        <h2><?= adv_h((string) ($campaign['grade_label'] ?? 'Campagne')) ?> · <?= (int) ($campaign['year'] ?? 0) ?></h2>
        <p>
            <span class="adv-badge adv-badge--<?= adv_h($tone) ?>"><?= adv_h(\App\Support\AdvancementCodes::campaignLabel($status)) ?></span>
            Candidatures du <?= adv_h($format((string) ($campaign['opens_at'] ?? ''))) ?> au <?= adv_h($format((string) ($campaign['closes_at'] ?? ''))) ?><?php if (($campaign['quota_slots'] ?? '') !== '' && $campaign['quota_slots'] !== null): ?> · <?= (int) $campaign['quota_slots'] ?> place<?= (int) $campaign['quota_slots'] > 1 ? 's' : '' ?><?php else: ?> · places non limitées<?php endif; ?>.
        </p>
        <p class="adv-muted">La période recueille les candidatures. La commission classe et décide. Publier le tableau crée le grade : on ne revient pas en arrière, on ajoute une ligne à l’historique.</p>
        <div class="adv-actions">
            <?php if ($open): ?>
                <form method="post" action="<?= adv_h(url('back-office/rh/avancement/' . $id . '/cloturer')) ?>"><?= \App\Core\Csrf::field() ?><button class="ath-btn" type="submit">Clôturer les candidatures</button></form>
            <?php endif; ?>
            <?php if (in_array($status, ['ouverte', 'cloturee', \App\Support\AdvancementCodes::CAMPAIGN_OPEN, \App\Support\AdvancementCodes::CAMPAIGN_CLOSED], true)): ?>
                <form method="post" action="<?= adv_h(url('back-office/rh/avancement/' . $id . '/commission')) ?>"><?= \App\Core\Csrf::field() ?><button class="ath-btn ath-btn--solid" type="submit">Passer en commission</button></form>
            <?php endif; ?>
            <?php if (in_array($status, ['en_commission', 'publiee', \App\Support\AdvancementCodes::CAMPAIGN_IN_COMMISSION, \App\Support\AdvancementCodes::CAMPAIGN_PUBLISHED], true)): ?>
                <a class="ath-btn ath-btn--solid" href="<?= adv_h(url('back-office/rh/avancement/' . $id . '/commission')) ?>">Ouvrir la commission</a>
            <?php endif; ?>
            <?php if (!in_array($status, ['publiee', \App\Support\AdvancementCodes::CAMPAIGN_PUBLISHED], true)): ?>
                <form method="post" action="<?= adv_h(url('back-office/rh/avancement/' . $id . '/reverifier')) ?>"><?= \App\Core\Csrf::field() ?><button class="ath-btn" type="submit">Revérifier l’éligibilité</button></form>
            <?php endif; ?>
        </div>
    </section>

    <?php require __DIR__ . '/_detections.php'; ?>

    <?php if ($open): ?>
    <section class="adv-panel">
        <h2>Ajouter une candidature <?= adv_info('Candidature', 'Un membre peut être hors critères : il reste non éligible jusqu’à un passage exceptionnel en commission. Le poste visé n’est pas réservé.') ?></h2>
        <form method="post" action="<?= adv_h(url('back-office/rh/avancement/' . $id . '/candidatures')) ?>" class="adv-inline">
            <?= \App\Core\Csrf::field() ?>
            <select name="personnel_id" class="adv-search" data-placeholder="Choisir un membre" required>
                <option value="">Choisir un membre</option>
                <?php foreach ($personnel as $person): ?>
                    <option value="<?= (int) $person['id'] ?>"><?= adv_h(adv_option($person)) ?></option>
                <?php endforeach; ?>
            </select>
            <label class="adv-check"><input type="checkbox" name="mobility_requested" value="1"> Mobilité</label>
            <select name="requested_billet_id" class="adv-search" data-placeholder="Poste visé, à titre indicatif">
                <option value="">Poste visé, à titre indicatif</option>
                <?php foreach ($billets as $billet): ?>
                    <option value="<?= (int) $billet['id'] ?>"><?= adv_h((string) ($billet['title'] ?? $billet['code'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="ath-btn" type="submit">Enregistrer</button>
        </form>
    </section>
    <?php endif; ?>

    <?php if ($candidacies === []): ?>
        <div class="adv-empty" role="status">
            <p class="adv-empty__title">Aucune candidature</p>
            <p><?= $open ? 'Ajoutez un membre pour ouvrir son dossier dans cette campagne.' : 'Personne ne s’est présenté pendant la période de candidatures.' ?></p>
        </div>
    <?php else: ?>
        <ul class="adv-people">
            <?php foreach ($candidacies as $row): ?>
                <?php
                $rank = $row['preference_rank'] ?? null;
                $rankLabel = ($rank === null || $rank === '') ? 'Pas encore classé' : ('Rang ' . (int) $rank . ' sur ' . max(1, (int) ($rankedTotal ?? 0)));
                $eligible = !empty($row['is_eligible']) || !empty($row['live_eligible']);
                $forced = !empty($row['is_forced']);
                $opinionRaw = trim((string) ($row['commission_opinion'] ?? ''));
                $decisionRaw = trim((string) ($row['decision'] ?? ''));
                $opinion = \App\Support\AdvancementCodes::opinionLabel($opinionRaw !== '' ? $opinionRaw : null);
                $decision = \App\Support\AdvancementCodes::decisionLabel($decisionRaw !== '' ? $decisionRaw : null);
                if ($opinion === '—') {
                    $opinion = 'Pas encore d’avis';
                }
                if ($decision === 'Inscrit') {
                    $decision = 'Inscrit au tableau';
                } elseif ($decision === '—') {
                    $decision = 'En attente';
                }
                ?>
                <li class="adv-person">
                    <div class="adv-person__who">
                        <strong><?= adv_h(adv_person($row)) ?></strong>
                        <?php if ($eligible): ?>
                            <span class="adv-badge adv-badge--ok">Éligible</span>
                        <?php elseif ($forced): ?>
                            <span class="adv-badge adv-badge--exception">Passage exceptionnel</span>
                        <?php else: ?>
                            <span class="adv-badge adv-badge--bad"><?= adv_h((string) ($row['live_reason'] ?? $row['eligibility_reason'] ?? 'Non éligible')) ?></span>
                        <?php endif; ?>
                    </div>
                    <dl class="adv-person__facts">
                        <div><dt>Ancienneté</dt><dd><?= (int) ($row['months_in_grade'] ?? 0) ?> mois de grade</dd></div>
                        <div><dt>Classement</dt><dd><?= adv_h($rankLabel) ?></dd></div>
                        <div><dt>Mobilité</dt><dd><?= !empty($row['mobility_requested']) ? 'Demandée' : 'Non demandée' ?></dd></div>
                        <div><dt>Avis</dt><dd><?= adv_h($opinion) ?></dd></div>
                        <div><dt>Décision</dt><dd><?= adv_h($decision) ?></dd></div>
                    </dl>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
<script src="<?= adv_h(asset_url('assets/js/back-office-advancement.js')) ?>"></script>
