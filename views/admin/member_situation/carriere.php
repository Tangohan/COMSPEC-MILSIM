<?php
declare(strict_types=1);

use App\Support\AdvancementCodes;

$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
$timeline = is_array($timeline ?? null) ? $timeline : [];
$gradeHistory = is_array($gradeHistory ?? null) ? $gradeHistory : [];
$advancementBanner = is_array($advancementBanner ?? null) ? $advancementBanner : null;
$success = trim((string) ($success ?? ''));
$error = trim((string) ($error ?? ''));
$kindLabel = static fn (string $k): string => match ($k) {
    'grade' => 'Grade',
    'qualification' => 'Qualification',
    'award' => 'Décoration',
    'billet' => 'Poste',
    'equipment' => 'Dotation',
    default => $k,
};
?>
<div class="bo-member-situation bo-member-situation--dossier">
    <?php if ($success !== ''): ?><p class="bo-member-situation__flash bo-member-situation__flash--ok"><?= $h($success) ?></p><?php endif; ?>
    <?php if ($error !== ''): ?><p class="bo-member-situation__flash bo-member-situation__flash--err"><?= $h($error) ?></p><?php endif; ?>

    <header class="bo-dossier-hero">
        <div>
            <p class="bo-dossier-hero__kicker">Dossier individuel</p>
            <h2 class="bo-dossier-hero__title">Dossier de carrière</h2>
            <p class="bo-dossier-hero__lead">Vue chronologique unique : grades, postes, qualifications, décorations et dotation.</p>
        </div>
    </header>

    <?php if (is_array($advancementBanner) && !empty($advancementBanner['next'])): ?>
        <?php
        $nextLabel = trim((string) ($advancementBanner['next']['label'] ?? ''));
        $eval = is_array($advancementBanner['eval'] ?? null) ? $advancementBanner['eval'] : [];
        $campaign = is_array($advancementBanner['campaign'] ?? null) ? $advancementBanner['campaign'] : null;
        ?>
        <aside class="bo-adv-banner">
            <div>
                <p class="bo-adv-banner__title">Avancement au grade de <?= $h($nextLabel) ?></p>
                <?php if (!empty($eval['is_eligible']) && $campaign && empty($eval['already'])): ?>
                    <p class="bo-adv-banner__meta">Vous êtes éligible. Portez-vous volontaire pour la campagne en cours.</p>
                <?php else: ?>
                    <p class="bo-adv-banner__meta"><?= $h((string) ($eval['eligibility_reason'] ?? ($eval['already'] ? 'Candidature déjà déposée.' : ''))) ?></p>
                <?php endif; ?>
            </div>
            <?php if (!empty($eval['is_eligible']) && $campaign && empty($eval['already'])): ?>
                <form method="post" action="<?= $h(url('back-office/ma-situation/avancement/candidater')) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="campaign_id" value="<?= (int) $campaign['id'] ?>">
                    <button class="ath-btn ath-btn--solid" type="submit">Me porter volontaire</button>
                </form>
            <?php endif; ?>
        </aside>
    <?php endif; ?>

    <?php if ($gradeHistory !== []): ?>
        <section class="bo-doc-section">
            <div class="bo-doc-section__head"><h2>Historique de grade</h2></div>
            <div class="bo-career-timeline">
                <?php foreach ($gradeHistory as $row): ?>
                    <article class="bo-career-item">
                        <div class="bo-career-item__date"><?= $h(date('d/m/Y', strtotime((string) ($row['obtained_at'] ?? 'now')) ?: time())) ?></div>
                        <div>
                            <div class="bo-career-item__kind"><?= $h(AdvancementCodes::viaLabel((string) ($row['obtained_via'] ?? ''))) ?></div>
                            <h3><?= $h((string) ($row['grade_label'] ?? '')) ?></h3>
                            <p><?= !empty($row['ends_at']) ? 'Jusqu’au ' . $h(date('d/m/Y', strtotime((string) $row['ends_at']) ?: time())) : 'Grade actuel' ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="bo-doc-section">
        <div class="bo-doc-section__head"><h2>Timeline</h2></div>
        <?php if ($timeline === []): ?>
            <p class="bo-member-situation__empty">Aucun événement de carrière pour l’instant.</p>
        <?php else: ?>
            <div class="bo-career-timeline">
                <?php foreach ($timeline as $item): ?>
                    <article class="bo-career-item">
                        <div class="bo-career-item__date"><?= $h(($item['at'] ?? '') !== '' ? date('d/m/Y', strtotime((string) $item['at']) ?: time()) : '—') ?></div>
                        <div>
                            <div class="bo-career-item__kind"><?= $h($kindLabel((string) ($item['kind'] ?? ''))) ?></div>
                            <h3><?= $h((string) ($item['title'] ?? '')) ?></h3>
                            <p><?= $h((string) ($item['detail'] ?? '')) ?><?= !empty($item['via']) ? ' · ' . $h((string) $item['via']) : '' ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
