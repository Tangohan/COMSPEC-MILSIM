<?php
declare(strict_types=1);

/** @var list<array<string, mixed>> $catalogInstalls */

$installs = is_array($catalogInstalls ?? null) ? $catalogInstalls : [];
$flashOk = trim((string) ($flashOk ?? ''));
$flashErr = trim((string) ($flashErr ?? ''));
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<div class="bo-catalog">
    <header class="bo-catalog__hero">
        <p class="bo-catalog__kicker">Journal</p>
        <h1 class="bo-catalog__title">Historique des applications</h1>
        <p class="bo-catalog__lead">
            Chaque copie de modèle dans cette communauté est conservée : qui a appliqué, quand,
            quels éléments ont été ajoutés, et lesquels étaient déjà en place.
        </p>
        <div class="bo-catalog__hero-actions">
            <a class="ath-btn ath-btn--solid" href="<?= $h(url('back-office/organisation/catalogue')) ?>">Retour au catalogue</a>
        </div>
    </header>

    <?php if ($flashOk !== ''): ?>
        <p class="ath-flash ath-flash--ok" role="status"><?= $h($flashOk) ?></p>
    <?php endif; ?>
    <?php if ($flashErr !== ''): ?>
        <p class="ath-flash ath-flash--err" role="alert"><?= $h($flashErr) ?></p>
    <?php endif; ?>

    <?php if ($installs === []): ?>
        <p class="bo-catalog__empty">Aucune application n’a encore été enregistrée. Appliquez un modèle depuis le catalogue pour ouvrir le journal.</p>
    <?php else: ?>
        <ol class="bo-catalog__journal">
            <?php foreach ($installs as $row): ?>
                <?php
                $code = (string) ($row['code'] ?? '');
                $available = !empty($row['model_available']);
                $parts = is_array($row['parts_labels'] ?? null) ? $row['parts_labels'] : [];
                ?>
                <li class="bo-catalog__journal-item">
                    <div class="bo-catalog__journal-meta">
                        <strong><?= $h((string) ($row['title'] ?? 'Modèle')) ?></strong>
                        <span>
                            <?= $h((string) ($row['applied_at_label'] ?? '')) ?>
                            · <?= $h((string) ($row['actor'] ?? '')) ?>
                        </span>
                        <?php if ($parts !== []): ?>
                            <span class="bo-catalog__journal-parts"><?= $h(implode(' · ', array_map('strval', $parts))) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (trim((string) ($row['summary'] ?? '')) !== ''): ?>
                        <p class="bo-catalog__journal-summary"><?= $h((string) $row['summary']) ?></p>
                    <?php endif; ?>
                    <div class="bo-catalog__journal-counts">
                        <span><?= (int) ($row['units_added'] ?? 0) ?> unité<?= (int) ($row['units_added'] ?? 0) > 1 ? 's' : '' ?> ajoutée<?= (int) ($row['units_added'] ?? 0) > 1 ? 's' : '' ?></span>
                        <span><?= (int) ($row['units_kept'] ?? 0) ?> déjà en place</span>
                        <span><?= (int) ($row['functions_added'] ?? 0) ?> fonction<?= (int) ($row['functions_added'] ?? 0) > 1 ? 's' : '' ?> ajoutée<?= (int) ($row['functions_added'] ?? 0) > 1 ? 's' : '' ?></span>
                        <span><?= (int) ($row['roles_added'] ?? 0) ?> rôle<?= (int) ($row['roles_added'] ?? 0) > 1 ? 's' : '' ?> ajouté<?= (int) ($row['roles_added'] ?? 0) > 1 ? 's' : '' ?></span>
                    </div>
                    <?php
                    $addedUnits = is_array($row['units_added_names'] ?? null) ? $row['units_added_names'] : [];
                    $keptUnits = is_array($row['units_kept_names'] ?? null) ? $row['units_kept_names'] : [];
                    $addedFn = is_array($row['functions_added_names'] ?? null) ? $row['functions_added_names'] : [];
                    $keptFn = is_array($row['functions_kept_names'] ?? null) ? $row['functions_kept_names'] : [];
                    $addedRoles = is_array($row['roles_added_names'] ?? null) ? $row['roles_added_names'] : [];
                    $keptRoles = is_array($row['roles_kept_names'] ?? null) ? $row['roles_kept_names'] : [];
                    $grades = trim((string) ($row['grades'] ?? ''));
                    ?>
                    <?php if ($addedUnits !== [] || $keptUnits !== [] || $addedFn !== [] || $keptFn !== [] || $addedRoles !== [] || $keptRoles !== [] || $grades !== ''): ?>
                        <details class="bo-catalog__details">
                            <summary>Détail de cette application</summary>
                            <?php if ($addedUnits !== []): ?>
                                <p class="bo-catalog__group-title">Unités ajoutées</p>
                                <ul class="bo-catalog__plain"><?php foreach ($addedUnits as $n): ?><li><?= $h((string) $n) ?></li><?php endforeach; ?></ul>
                            <?php endif; ?>
                            <?php if ($keptUnits !== []): ?>
                                <p class="bo-catalog__group-title">Unités déjà présentes</p>
                                <ul class="bo-catalog__plain"><?php foreach ($keptUnits as $n): ?><li><?= $h((string) $n) ?></li><?php endforeach; ?></ul>
                            <?php endif; ?>
                            <?php if ($addedFn !== []): ?>
                                <p class="bo-catalog__group-title">Fonctions ajoutées</p>
                                <ul class="bo-catalog__plain"><?php foreach ($addedFn as $n): ?><li><?= $h((string) $n) ?></li><?php endforeach; ?></ul>
                            <?php endif; ?>
                            <?php if ($keptFn !== []): ?>
                                <p class="bo-catalog__group-title">Fonctions déjà présentes</p>
                                <ul class="bo-catalog__plain"><?php foreach ($keptFn as $n): ?><li><?= $h((string) $n) ?></li><?php endforeach; ?></ul>
                            <?php endif; ?>
                            <?php if ($addedRoles !== []): ?>
                                <p class="bo-catalog__group-title">Rôles ajoutés</p>
                                <ul class="bo-catalog__plain"><?php foreach ($addedRoles as $n): ?><li><?= $h((string) $n) ?></li><?php endforeach; ?></ul>
                            <?php endif; ?>
                            <?php if ($keptRoles !== []): ?>
                                <p class="bo-catalog__group-title">Rôles déjà présents</p>
                                <ul class="bo-catalog__plain"><?php foreach ($keptRoles as $n): ?><li><?= $h((string) $n) ?></li><?php endforeach; ?></ul>
                            <?php endif; ?>
                            <?php if ($grades !== ''): ?>
                                <p class="bo-catalog__group-title">Grades</p>
                                <p><?= $h($grades) ?></p>
                            <?php endif; ?>
                        </details>
                    <?php endif; ?>
                    <div class="bo-catalog__card-actions">
                        <?php if ($available && $code !== ''): ?>
                            <a class="ath-btn ath-btn--solid" href="<?= $h(url('back-office/organisation/catalogue/apercu?modele=' . rawurlencode($code))) ?>">Réappliquer ce modèle</a>
                            <a class="ath-btn" href="<?= $h(url('back-office/organisation/catalogue/modele?modele=' . rawurlencode($code))) ?>">Voir le contenu</a>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</div>
