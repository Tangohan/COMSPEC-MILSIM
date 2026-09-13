<?php

declare(strict_types=1);

$assignments = is_array($assignments ?? null) ? $assignments : [];
$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
?>
<div class="bo-member-situation">
    <div class="bo-member-situation__actions">
        <a class="ath-btn" href="<?= $h(url('back-office/ma-situation/ma-fiche') . '?onglet=unite') ?>">Voir l’unité dans ma fiche</a>
        <a class="ath-btn" href="<?= $h(url('back-office/ma-situation/ma-fiche')) ?>">Ouvrir ma fiche</a>
    </div>

    <?php if ($assignments === []): ?>
        <section class="bo-member-situation__card">
            <h2>Aucune affectation</h2>
            <p>Vous n’êtes pas encore rattaché à une unité. Un responsable peut vous affecter depuis les effectifs de la communauté.</p>
        </section>
    <?php else: ?>
        <div class="bo-member-situation__grid">
            <?php foreach ($assignments as $row): ?>
                <?php
                if (!is_array($row)) {
                    continue;
                }
                $role = trim((string) ($row['role_name'] ?? $row['role_label'] ?? $row['assignment_role'] ?? $row['job_role_display'] ?? ''));
                $primary = !empty($row['is_primary']);
                $path = trim((string) ($row['assignment_path'] ?? ''));
                $name = trim((string) ($row['unit_name'] ?? $row['name'] ?? ''));
                if ($path === '' && $name === '') {
                    continue;
                }
                ?>
                <article class="bo-member-situation__card">
                    <?php if ($primary): ?>
                        <p class="bo-member-situation__kicker">Affectation principale</p>
                    <?php endif; ?>
                    <h2><?= $h($name !== '' ? $name : $path) ?></h2>
                    <?php if ($path !== '' && $path !== $name): ?>
                        <p><?= $h($path) ?></p>
                    <?php endif; ?>
                    <?php if ($role !== ''): ?>
                        <p><strong>Fonction :</strong> <?= $h($role) ?></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
