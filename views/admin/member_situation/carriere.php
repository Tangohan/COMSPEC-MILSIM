<?php
declare(strict_types=1);

use App\Support\AdvancementCodes;

$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
$timeline = is_array($timeline ?? null) ? $timeline : [];
$gradeHistory = is_array($gradeHistory ?? null) ? $gradeHistory : [];
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

    <div class="bo-member-situation__actions bo-dossier-hero__actions">
        <a class="ath-btn ath-btn--solid" href="<?= $h(url('back-office/ma-situation/avancement')) ?>">Mon avancement</a>
        <a class="ath-btn" href="<?= $h(url('back-office/ma-situation/qualifications')) ?>">Mes qualifications</a>
    </div>

    <?php if ($gradeHistory !== []): ?>
        <section class="bo-doc-section">
            <div class="bo-doc-section__head"><h2>Historique de grade</h2></div>
            <div class="bo-career-timeline">
                <?php foreach ($gradeHistory as $row): ?>
                    <article class="bo-career-item">
                        <div class="bo-career-item__date"><?= $h(date('d/m/Y', strtotime((string) ($row['obtained_at'] ?? 'now')) ?: time())) ?></div>
                        <div>
                            <div class="bo-career-item__kind"><?= $h(AdvancementCodes::viaLabel((string) ($row['obtained_via'] ?? ''))) ?></div>
                            <h3><?= $h((string) ($row['label'] ?? $row['grade_label'] ?? '')) ?></h3>
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
