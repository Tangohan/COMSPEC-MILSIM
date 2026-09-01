<?php
declare(strict_types=1);

/** @var list<array<string,mixed>> $templates */

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$templates = is_array($templates ?? null) ? $templates : [];
$count = count($templates);
$nouveauUrl = url('back-office/integration-membres/modeles/nouveau');
?>
<link href="<?= $h(asset_url('assets/css/member-integration.css')) ?>" rel="stylesheet">

<div class="mi-admin">
    <div class="ath-form__actions" style="border-top:0;margin:0 0 16px;padding-top:0;">
        <a class="ath-btn" href="<?= $h(url('back-office/integration-membres')) ?>">Parcours d’intégration</a>
    </div>

    <div class="ath-table-panel ath-rise">
        <div class="ath-table-toolbar">
            <span class="ath-table-toolbar__title">Modèles de parcours</span>
            <span class="ath-table-toolbar__count"><?= $count ?> modèle<?= $count > 1 ? 's' : '' ?></span>
            <span class="ath-table-toolbar__spacer" aria-hidden="true"></span>
            <a class="ath-btn ath-btn--accent" href="<?= $h($nouveauUrl) ?>">Nouveau modèle</a>
        </div>

        <?php if ($templates === []): ?>
            <div class="mi-empty">
                <strong>Aucun modèle pour le moment</strong>
                <p>Un parcours « Intégration recrue » peut être créé automatiquement. Vous pouvez aussi en composer un pour les prochaines arrivées.</p>
                <a class="ath-btn ath-btn--accent" href="<?= $h($nouveauUrl) ?>">Nouveau modèle</a>
            </div>
        <?php else: ?>
            <div class="ath-table-wrap">
                <table class="ath-table">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Version</th>
                            <th>Durée</th>
                            <th>Actif</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($templates as $t): ?>
                        <?php
                        $active = !empty($t['is_active']);
                        $editUrl = url('back-office/integration-membres/modeles/' . (int) ($t['id'] ?? 0));
                        ?>
                        <tr>
                            <td><a href="<?= $h($editUrl) ?>"><?= $h($t['name'] ?? '') ?></a></td>
                            <td class="ath-td-num"><?= (int) ($t['version'] ?? 1) ?></td>
                            <td><?= (int) ($t['duration_days'] ?? 0) ?> jours</td>
                            <td>
                                <?php if ($active): ?>
                                    <span class="ath-tag ath-tag--ok">Oui</span>
                                <?php else: ?>
                                    <span class="ath-tag ath-tag--neut">Non</span>
                                <?php endif; ?>
                            </td>
                            <td><a class="ath-btn" href="<?= $h($editUrl) ?>">Modifier</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
