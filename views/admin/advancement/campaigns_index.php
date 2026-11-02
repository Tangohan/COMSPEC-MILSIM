<?php
require __DIR__ . '/_helpers.php';

use App\Services\Advancement\AdvancementProposalEngine as Engine;
use App\Support\AdvancementCodes;

$campaigns = is_array($campaigns ?? null) ? $campaigns : [];
$running = is_array($running ?? null) ? $running : [];
$overview = is_array($overview ?? null) ? $overview : [];
$sources = is_array($sources ?? null) ? $sources : [];
$proposalSettings = is_array($proposalSettings ?? null) ? $proposalSettings : Engine::DEFAULTS;
$tone = static function (string $status): string {
    return match ($status) {
        'ouverte', AdvancementCodes::CAMPAIGN_OPEN, 'publiee', AdvancementCodes::CAMPAIGN_PUBLISHED => 'ok',
        'en_commission', AdvancementCodes::CAMPAIGN_IN_COMMISSION, 'cloturee', AdvancementCodes::CAMPAIGN_CLOSED => 'wait',
        default => 'quiet',
    };
};
$totalProposed = 0;
foreach ($overview as $row) {
    $totalProposed += (int) ($row['counts'][Engine::STATUS_PROPOSE] ?? 0);
}
?>
<div class="adv-page advc" data-adv-page="campaigns">
    <?php require __DIR__ . '/_flash.php'; ?>

    <section class="advc-intro">
        <div class="advc-intro__text">
            <p class="advc-kicker">Avancement</p>
            <h2>Faire monter en grade les bons membres, au bon moment</h2>
            <p>Deux voies existent. <strong>L’ancienneté</strong> : le grade suivant est attribué tout seul dès que le temps minimum est atteint, si le grade l’autorise. <strong>Le choix</strong> : une campagne pour un grade, avec une période de candidatures, une commission qui arbitre et un tableau publié.</p>
            <p>Pour chaque campagne, le système repère les membres à proposer à partir de leurs données réelles (temps dans le grade, présences, heures de jeu, activité, sanctions) et explique son choix. Rien n’est attribué sans votre validation.</p>
        </div>
        <div class="advc-intro__acts">
            <a class="ath-btn ath-btn--solid" href="<?= adv_h(url('back-office/rh/avancement/create')) ?>">Nouvelle campagne</a>
            <a class="ath-btn" href="<?= adv_h(url('back-office/organisation/grades')) ?>">Règles d’avancement</a>
            <a class="ath-btn" href="<?= adv_h(url('back-office/referentiels/grades')) ?>">Référentiel des grades</a>
        </div>
    </section>

    <section class="advc-panel" aria-labelledby="advc-how">
        <h2 id="advc-how">Comment se déroule une campagne</h2>
        <?php $stepperCurrent = 0; $stepperDone = false; $stepperCompact = false; require __DIR__ . '/_stepper.php'; ?>
    </section>

    <section class="advc-panel" aria-labelledby="advc-running">
        <div class="advc-head">
            <h2 id="advc-running">Campagnes en cours</h2>
            <?php if ($running !== []): ?>
                <span class="advc-count"><?= count($running) ?></span>
            <?php endif; ?>
        </div>
        <?php if ($running === []): ?>
            <div class="advc-empty">
                <?php if ($totalProposed > 0): ?>
                    <p><strong>Aucune campagne en cours.</strong> Le système a déjà repéré <?= $totalProposed ?> membre(s) à proposer : lancez une campagne pour les présenter.</p>
                <?php else: ?>
                    <p><strong>Aucune campagne en cours.</strong> Lancez une campagne quand un grade doit être pourvu.</p>
                <?php endif; ?>
                <a class="ath-btn ath-btn--solid" href="<?= adv_h(url('back-office/rh/avancement/create')) ?>">Lancer une campagne</a>
            </div>
        <?php else: ?>
            <ul class="advc-cards">
                <?php foreach ($running as $item): ?>
                    <?php
                    $c = is_array($item['campaign'] ?? null) ? $item['campaign'] : [];
                    $p = is_array($item['progress'] ?? null) ? $item['progress'] : [];
                    $status = (string) ($c['status'] ?? '');
                    $quota = $p['quota'] ?? null;
                    $daysLeft = $p['days_left'] ?? null;
                    ?>
                    <li class="advc-card">
                        <div class="advc-card__top">
                            <div>
                                <h3><a href="<?= adv_h(url('back-office/rh/avancement/' . (int) ($c['id'] ?? 0))) ?>"><?= adv_h((string) ($c['grade_label'] ?? 'Grade')) ?> · <?= (int) ($c['year'] ?? 0) ?></a></h3>
                                <p class="advc-muted">Candidatures du <?= adv_h(adv_date((string) ($c['opens_at'] ?? ''))) ?> au <?= adv_h(adv_date((string) ($c['closes_at'] ?? ''))) ?></p>
                            </div>
                            <span class="adv-badge adv-badge--<?= adv_h($tone($status)) ?>"><?= adv_h(AdvancementCodes::campaignLabel($status)) ?></span>
                        </div>
                        <?php $stepperCurrent = (int) ($p['step'] ?? 1); $stepperDone = !empty($p['done']); $stepperCompact = true; require __DIR__ . '/_stepper.php'; ?>
                        <dl class="advc-figures">
                            <div><dt>Candidats</dt><dd><?= (int) ($p['candidates'] ?? 0) ?></dd></div>
                            <div><dt>Éligibles</dt><dd><?= (int) ($p['eligible'] ?? 0) ?></dd></div>
                            <div><dt>Inscrits</dt><dd><?= (int) ($p['listed'] ?? 0) ?><?php if ($quota !== null): ?> / <?= (int) $quota ?><?php endif; ?></dd></div>
                            <div><dt>Clôture</dt><dd><?php if ($daysLeft === null): ?>Passée<?php elseif ((int) $daysLeft === 0): ?>Aujourd’hui<?php else: ?><?= (int) $daysLeft ?> j<?php endif; ?></dd></div>
                        </dl>
                        <p class="advc-next"><?= adv_h((string) ($p['next'] ?? '')) ?></p>
                        <a class="ath-btn" href="<?= adv_h(url('back-office/rh/avancement/' . (int) ($c['id'] ?? 0))) ?>">Ouvrir la campagne</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="advc-panel" id="detection" aria-labelledby="advc-detect">
        <h2 id="advc-detect">Ce que détecte le système aujourd’hui</h2>
        <p class="advc-lead">Pour chaque grade ouvert au choix : les membres du grade juste en dessous, et combien le système proposerait si une campagne s’ouvrait maintenant.</p>
        <?php if ($overview === []): ?>
            <div class="advc-empty">
                <p><strong>Aucun grade n’est ouvert à la voie choix.</strong> Cochez « Voie choix » sur les grades concernés dans les règles d’avancement.</p>
                <a class="ath-btn" href="<?= adv_h(url('back-office/organisation/grades')) ?>">Règles d’avancement</a>
            </div>
        <?php else: ?>
            <ul class="advc-grades">
                <?php foreach ($overview as $row): ?>
                    <?php
                    $g = is_array($row['grade'] ?? null) ? $row['grade'] : [];
                    $n = is_array($row['counts'] ?? null) ? $row['counts'] : [];
                    $campaign = is_array($row['campaign'] ?? null) ? $row['campaign'] : null;
                    $previous = trim((string) ($row['previous_label'] ?? ''));
                    $months = (int) ($g['min_time_in_previous_grade_months'] ?? 0);
                    $qual = trim((string) ($g['required_qualification_name'] ?? ''));
                    ?>
                    <li class="advc-grade">
                        <div class="advc-grade__who">
                            <strong><?= adv_h((string) ($g['label'] ?? '')) ?></strong>
                            <span class="advc-muted">
                                <?php if ($previous !== ''): ?>Depuis <?= adv_h($previous) ?><?php else: ?>Aucun membre au grade précédent<?php endif; ?><?php if ($months > 0): ?> · <?= $months ?> mois minimum<?php endif; ?><?php if ($qual !== ''): ?> · qualification <?= adv_h($qual) ?><?php endif; ?>
                            </span>
                        </div>
                        <ul class="advc-chips">
                            <li><b><?= (int) ($n['population'] ?? 0) ?></b> au grade précédent</li>
                            <li class="is-ok"><b><?= (int) ($n[Engine::STATUS_PROPOSE] ?? 0) ?></b> proposé(s)</li>
                            <li class="is-warn"><b><?= (int) ($n[Engine::STATUS_REVIEW] ?? 0) ?></b> à examiner</li>
                            <?php if ((int) ($n['near'] ?? 0) > 0): ?>
                                <li><b><?= (int) $n['near'] ?></b> bientôt éligible(s)</li>
                            <?php endif; ?>
                            <?php if ((int) ($n[Engine::STATUS_BLOCKED] ?? 0) > 0): ?>
                                <li class="is-bad"><b><?= (int) $n[Engine::STATUS_BLOCKED] ?></b> écarté(s)</li>
                            <?php endif; ?>
                        </ul>
                        <div class="advc-grade__act">
                            <?php if ($campaign !== null): ?>
                                <a class="ath-btn" href="<?= adv_h(url('back-office/rh/avancement/' . (int) $campaign['id'])) ?>">Voir la campagne</a>
                            <?php else: ?>
                                <a class="ath-btn<?= (int) ($n[Engine::STATUS_PROPOSE] ?? 0) > 0 ? ' ath-btn--solid' : '' ?>" href="<?= adv_h(url('back-office/rh/avancement/create') . '?grade=' . (int) ($g['id'] ?? 0)) ?>">Lancer une campagne</a>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="advc-panel" id="reglages" aria-labelledby="advc-criteria">
        <h2 id="advc-criteria">Critères et seuils de la détection</h2>
        <?php
        $criteriaSettings = $proposalSettings;
        $criteriaSources = $sources;
        $criteriaFieldsOnly = false;
        $criteriaFormAction = url('back-office/rh/avancement/reglages');
        $criteriaCampaignId = 0;
        require __DIR__ . '/_criteria.php';
        ?>
    </section>

    <section class="advc-panel" aria-labelledby="advc-all">
        <h2 id="advc-all">Toutes les campagnes</h2>
        <?php if ($campaigns === []): ?>
            <p class="advc-muted">Aucune campagne pour l’instant. Les campagnes publiées restent ici avec leur tableau.</p>
        <?php else: ?>
            <ul class="adv-campaigns">
                <?php foreach ($campaigns as $campaign): ?>
                    <?php
                    $status = (string) ($campaign['status'] ?? '');
                    $filiere = trim((string) ($campaign['filiere_label'] ?? ''));
                    $quota = $campaign['quota_slots'] ?? null;
                    $publishedAt = trim((string) ($campaign['published_at'] ?? ''));
                    ?>
                    <li>
                        <a class="adv-campaign" href="<?= adv_h(url('back-office/rh/avancement/' . (int) ($campaign['id'] ?? 0))) ?>">
                            <span class="adv-campaign__year"><?= (int) ($campaign['year'] ?? 0) ?></span>
                            <span class="adv-campaign__body">
                                <strong><?= adv_h((string) ($campaign['grade_label'] ?? 'Grade')) ?></strong>
                                <span><?= adv_h($filiere !== '' ? $filiere : 'Filière du grade') ?> · du <?= adv_h(adv_date((string) ($campaign['opens_at'] ?? ''))) ?> au <?= adv_h(adv_date((string) ($campaign['closes_at'] ?? ''))) ?> · <?php if ($quota === null || $quota === ''): ?>places non limitées<?php else: ?><?= (int) $quota ?> place(s)<?php endif; ?><?php if ($publishedAt !== ''): ?> · tableau publié le <?= adv_h(adv_date($publishedAt)) ?><?php endif; ?></span>
                            </span>
                            <span class="adv-badge adv-badge--<?= adv_h($tone($status)) ?>"><?= adv_h(AdvancementCodes::campaignLabel($status)) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
