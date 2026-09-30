<?php
declare(strict_types=1);

use App\Support\AdvancementCodes;

$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
$campaign = is_array($campaign ?? null) ? $campaign : [];
$commission = is_array($commission ?? null) ? $commission : [];
$commissionMembers = is_array($commissionMembers ?? null) ? $commissionMembers : [];
$candidacies = is_array($candidacies ?? null) ? $candidacies : [];
$members = is_array($members ?? null) ? $members : [];
$cid = (int) ($campaign['id'] ?? 0);
$published = (string) ($campaign['status'] ?? '') === AdvancementCodes::CAMPAIGN_PUBLISHED;
$personLabel = static function (array $u): string {
    $n = trim((string) ($u['display_name'] ?? ''));
    if ($n !== '') {
        return $n;
    }

    return (string) ($u['username'] ?? ('#' . ($u['id'] ?? '')));
};
?>
<div class="bo-adv">
    <?php include __DIR__ . '/_flash.php'; ?>
    <p class="bo-adv__back"><a href="<?= $h(url('back-office/rh/avancement/' . $cid)) ?>">← Candidatures</a></p>

    <section class="bo-adv__intro">
        <div>
            <p class="bo-adv__kicker">Commission</p>
            <h2><?= $h((string) ($campaign['grade_label'] ?? '')) ?></h2>
            <p>Réunion <?= $h((string) ($commission['meeting_date'] ?? '—')) ?>
                <?= $published ? ' · tableau publié le ' . $h((string) ($campaign['published_at'] ?? '')) : '' ?></p>
        </div>
        <?php if (!$published): ?>
            <form method="post" action="<?= $h(url('back-office/rh/avancement/' . $cid . '/publier')) ?>" onsubmit="return confirm('Publier le tableau ? Les grades inscrits sont attribués de façon irréversible.');">
                <?= \App\Core\Csrf::field() ?>
                <button class="ath-btn ath-btn--solid" type="submit">Publier le tableau d’avancement</button>
            </form>
        <?php endif; ?>
    </section>

    <section class="bo-adv__panel">
        <div class="bo-adv__panel-head">
            <h2>Membres de la commission</h2>
        </div>
        <ul class="bo-adv__list">
            <?php foreach ($commissionMembers as $m): ?>
                <li><?= $h((string) ($m['display_name'] ?? '')) ?> — <?= (string) ($m['role'] ?? '') === AdvancementCodes::MEMBER_DEPUTY ? 'Suppléant' : 'Titulaire' ?></li>
            <?php endforeach; ?>
            <?php if ($commissionMembers === []): ?><li class="bo-adv__muted">Aucun membre.</li><?php endif; ?>
        </ul>
        <?php if (!$published): ?>
            <form method="post" action="<?= $h(url('back-office/rh/avancement/' . $cid . '/commission/membres')) ?>" class="bo-adv__inline-form">
                <?= \App\Core\Csrf::field() ?>
                <select name="personnel_id" required>
                    <option value="">Personnel</option>
                    <?php foreach ($members as $u): ?>
                        <option value="<?= (int) ($u['id'] ?? 0) ?>"><?= $h($personLabel($u)) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="role">
                    <option value="<?= $h(AdvancementCodes::MEMBER_TITULAR) ?>">Titulaire</option>
                    <option value="<?= $h(AdvancementCodes::MEMBER_DEPUTY) ?>">Suppléant</option>
                </select>
                <button class="ath-btn" type="submit">Ajouter</button>
            </form>
        <?php endif; ?>
    </section>

    <?php foreach ($candidacies as $row):
        $eval = is_array($row['eval'] ?? null) ? $row['eval'] : [];
        ?>
        <article class="bo-adv__panel">
            <div class="bo-adv__panel-head">
                <h2><?= $h((string) ($row['display_name'] ?? $row['username'] ?? '')) ?></h2>
                <p>
                    <?= !empty($eval['is_eligible']) || !empty($row['is_eligible']) ? 'Éligible' : 'Non éligible' ?>
                    · <?= $h((string) ($eval['eligibility_reason'] ?? $row['eligibility_reason'] ?? '')) ?>
                    <?php if (isset($eval['months_in_grade'])): ?>
                        · <?= (int) $eval['months_in_grade'] ?> mois de grade
                    <?php endif; ?>
                    <?php if (!empty($row['mobility_requested'])): ?>
                        · mobilité <?= $h((string) ($row['requested_billet_title'] ?? 'demandée')) ?>
                    <?php endif; ?>
                </p>
            </div>
            <?php if (!$published): ?>
                <form method="post" action="<?= $h(url('back-office/rh/avancement/' . $cid . '/commission/avis')) ?>" class="bo-adv__inline-form">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="candidacy_id" value="<?= (int) ($row['id'] ?? 0) ?>">
                    <input type="number" name="preference_rank" min="1" placeholder="Rang" value="<?= $h((string) ($row['preference_rank'] ?? '')) ?>">
                    <select name="commission_opinion">
                        <option value="">Avis</option>
                        <option value="<?= $h(AdvancementCodes::OPINION_PROPOSED) ?>" <?= (string) ($row['commission_opinion'] ?? '') === AdvancementCodes::OPINION_PROPOSED ? 'selected' : '' ?>>Proposé</option>
                        <option value="<?= $h(AdvancementCodes::OPINION_NOT_PROPOSED) ?>" <?= (string) ($row['commission_opinion'] ?? '') === AdvancementCodes::OPINION_NOT_PROPOSED ? 'selected' : '' ?>>Non proposé</option>
                    </select>
                    <select name="decision">
                        <option value="">Décision</option>
                        <option value="<?= $h(AdvancementCodes::DECISION_INSCRIBED) ?>" <?= (string) ($row['decision'] ?? '') === AdvancementCodes::DECISION_INSCRIBED ? 'selected' : '' ?>>Inscrit</option>
                        <option value="<?= $h(AdvancementCodes::DECISION_NOT_INSCRIBED) ?>" <?= (string) ($row['decision'] ?? '') === AdvancementCodes::DECISION_NOT_INSCRIBED ? 'selected' : '' ?>>Non inscrit</option>
                    </select>
                    <button class="ath-btn ath-btn--solid" type="submit">Enregistrer</button>
                </form>
            <?php else: ?>
                <p>Avis <?= $h(AdvancementCodes::opinionLabel(isset($row['commission_opinion']) ? (string) $row['commission_opinion'] : null)) ?>
                    · décision <?= $h(AdvancementCodes::decisionLabel(isset($row['decision']) ? (string) $row['decision'] : null)) ?>
                    · rang <?= $h((string) ($row['preference_rank'] ?? '—')) ?></p>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
    <?php if ($candidacies === []): ?>
        <p class="bo-adv__empty">Aucun candidat.</p>
    <?php endif; ?>
</div>
