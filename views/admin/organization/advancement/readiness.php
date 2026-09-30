<?php
declare(strict_types=1);
$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
$scores = is_array($scores ?? null) ? $scores : [];
?>
<div class="bo-adv">
    <p class="bo-adv__hint">Agrégation : effectif pourvu/autorisé (45 %) + qualifications à jour (40 %) + dernière activité (15 %). Aucune nouvelle saisie.</p>
    <div class="bo-adv__table-wrap">
        <table class="bo-adv__table">
            <thead>
                <tr>
                    <th>Unité</th>
                    <th>Score</th>
                    <th>Pourvu / autorisé</th>
                    <th>Vacants</th>
                    <th>Quals à jour</th>
                    <th>Dernière activité</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($scores as $s):
                    $score = (int) ($s['score'] ?? 0);
                    $tone = $score >= 75 ? 'ok' : ($score >= 50 ? 'warn' : 'bad');
                    ?>
                    <tr>
                        <td><strong><?= $h((string) ($s['name'] ?? '')) ?></strong></td>
                        <td><span class="bo-adv__score is-<?= $h($tone) ?>"><?= $score ?></span></td>
                        <td><?= (int) ($s['filled'] ?? 0) ?> / <?= (int) ($s['authorized'] ?? 0) ?></td>
                        <td><?= (int) ($s['vacant'] ?? 0) ?></td>
                        <td><?= (int) ($s['qual_ok'] ?? 0) ?> / <?= (int) ($s['qual_total'] ?? 0) ?></td>
                        <td><?= $h((string) ($s['last_activity'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($scores === []): ?>
                    <tr><td colspan="6" class="bo-adv__empty">Aucune unité à scorer pour l’instant.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
