<?php
declare(strict_types=1);
/**
 * Onglet Résumé : repères de tête de la communauté (lecture seule).
 *
 * @var array<string, mixed> $platformSummary
 */
$sum = is_array($platformSummary ?? null) ? $platformSummary : [];
$counts = is_array($sum['counts'] ?? null) ? $sum['counts'] : [];
$plan = is_array($sum['plan'] ?? null) ? $sum['plan'] : ['kind' => 'base', 'plan_slug' => $effectivePlan, 'ends_at' => null, 'days_left' => null];
$planKinds = ['gift' => 'Formule offerte', 'paid' => 'Formule payante', 'trial' => 'Essai fondateur', 'base' => 'Formule de base'];
$daysLeft = $plan['days_left'] ?? null;
$bytesLabel = static function (?int $b): string {
    if ($b === null) {
        return '—';
    }
    if ($b >= 1048576) {
        return number_format($b / 1048576, 1, ',', ' ') . ' Mo';
    }

    return number_format($b / 1024, 0, ',', ' ') . ' Ko';
};
?>
<dl class="pa-stats">
    <div class="pa-stat">
        <dt>Membres actifs</dt>
        <dd><a class="pa-count-link" href="<?= $h((string) ($platformMembersUrl ?? '#') . '?filter=active') ?>"><?= (int) ($counts['active'] ?? 0) ?></a></dd>
    </div>
    <div class="pa-stat">
        <dt>Jamais connectés</dt>
        <dd><a class="pa-count-link" href="<?= $h((string) ($platformMembersUrl ?? '#') . '?filter=never') ?>"><?= (int) ($counts['never'] ?? 0) ?></a></dd>
    </div>
    <div class="pa-stat">
        <dt>Administrateurs</dt>
        <dd><?= (int) ($sum['admins'] ?? 0) ?></dd>
    </div>
    <div class="pa-stat">
        <dt>Dernière activité</dt>
        <dd style="font-size:1rem;"><?= $h($fmtDate($sum['last_activity'] ?? null)) ?></dd>
    </div>
    <div class="pa-stat">
        <dt>Formule</dt>
        <dd style="font-size:1rem;"><?= $h($currentPlanLabel) ?></dd>
    </div>
    <div class="pa-stat">
        <dt>Jours restants</dt>
        <dd style="font-size:1rem;">
            <?php if ($daysLeft === null): ?>
                Sans échéance
            <?php elseif ($daysLeft < 0): ?>
                Échue
            <?php else: ?>
                <?= (int) $daysLeft ?> j
            <?php endif; ?>
        </dd>
    </div>
    <div class="pa-stat">
        <dt>Trafic ATAK (30 j)</dt>
        <dd style="font-size:1rem;"><?= $h($bytesLabel(isset($sum['ingest_bytes_30d']) ? (int) $sum['ingest_bytes_30d'] : null)) ?></dd>
    </div>
    <div class="pa-stat">
        <dt>Sessions de jeu (7 j)</dt>
        <dd style="font-size:1rem;"><?= isset($sum['game_sessions_7d']) && $sum['game_sessions_7d'] !== null ? (int) $sum['game_sessions_7d'] : '—' ?></dd>
    </div>
    <div class="pa-stat">
        <dt>Création</dt>
        <dd style="font-size:1rem;"><?= $h($createdLabel) ?></dd>
    </div>
    <?php if ($communityCode !== ''): ?>
        <div class="pa-stat">
            <dt>Code pour rejoindre</dt>
            <dd style="font-size:1rem;"><?= $h($communityCode) ?></dd>
        </div>
    <?php endif; ?>
    <?php if ($periodEnd !== ''): ?>
        <div class="pa-stat">
            <dt>Fin de période</dt>
            <dd style="font-size:1rem;"><?= $h($periodEnd) ?></dd>
        </div>
    <?php endif; ?>
</dl>

<section class="pa-card">
    <p class="pa-card__kicker">Formule</p>
    <h2 class="pa-card__title"><?= $h($planKinds[(string) ($plan['kind'] ?? 'base')] ?? 'Formule') ?> : <?= $h($currentPlanLabel) ?></h2>
    <p class="pa-card__help">
        <?php if (!empty($plan['ends_at'])): ?>
            Se termine le <?= $h($fmtDate($plan['ends_at'])) ?>.
        <?php else: ?>
            Aucune date de fin enregistrée.
        <?php endif; ?>
        Formule de base : <?= $h($basePlanLabel) ?>.
        <a href="<?= $h($editUrl . '?onglet=edition#formule') ?>">Modifier la formule</a>
    </p>
</section>

<section class="pa-card">
    <p class="pa-card__kicker">Agenda</p>
    <h2 class="pa-card__title">Derniers événements</h2>
    <?php $events = is_array($sum['events'] ?? null) ? $sum['events'] : []; ?>
    <?php if ($events === []): ?>
        <p class="pa-card__help">Aucun événement enregistré.</p>
    <?php else: ?>
        <div class="pa-sheet__scroll">
            <table class="pa-sheet__grid">
                <thead><tr><th>Date</th><th>Titre</th></tr></thead>
                <tbody>
                    <?php foreach ($events as $ev): ?>
                        <tr>
                            <td><?= $h($fmtDate($ev['starts_at'] ?? null)) ?></td>
                            <td><?= $h((string) ($ev['title'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<p class="pa-card__help">
    <a href="<?= $h($editUrl . '?onglet=membres') ?>">Voir et filtrer les membres</a>
    · <a href="<?= $h($editUrl . '?onglet=diagnostic') ?>">Ouvrir le diagnostic</a>
</p>
