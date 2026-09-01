<?php
declare(strict_types=1);
/** @var list<array<string, mixed>> $operations */
$operations = $operations ?? [];
$canPlan = !empty($canPlan);
$statusOptions = $statusOptions ?? [];
$classificationOptions = $classificationOptions ?? [];
$csrfToken = (string) ($csrfToken ?? '');
$flashOk = trim((string) ($flash_success ?? ''));
$flashErr = trim((string) ($flash_error ?? ''));
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="ops-ws">
    <div class="ops-ws__shell">
        <header class="ops-ws__head">
            <p class="ops-ws__crumb">Athena · Opérations</p>
            <h1>Espaces opérationnels</h1>
            <p class="ops-ws__lead">Chaque opération rassemble le plan, le renseignement, les ordres et la vue terrain. La carte ATAK affiche ce qui a été publié, pas le travail d’état-major encore en cours.</p>
        </header>

        <?php if ($flashOk !== ''): ?>
        <p class="ops-ws__flash ops-ws__flash--ok"><?= $h($flashOk) ?></p>
        <?php endif; ?>
        <?php if ($flashErr !== ''): ?>
        <p class="ops-ws__flash ops-ws__flash--err"><?= $h($flashErr) ?></p>
        <?php endif; ?>

        <?php if ($canPlan): ?>
        <form class="ops-ws__create" method="post" action="<?= $h(url('operations')) ?>">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
            <h2>Ouvrir une opération</h2>
            <div class="ops-ws__grid">
                <label>Nom
                    <input type="text" name="name" required maxlength="191" placeholder="Opération Aegis">
                </label>
                <label>Indicatif
                    <input type="text" name="code" maxlength="32" placeholder="AEGIS">
                </label>
                <label>Classification
                    <select name="classification" class="bo-select">
                        <?php foreach ($classificationOptions as $opt): ?>
                        <option value="<?= $h($opt['value']) ?>"><?= $h($opt['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Statut
                    <select name="status" class="bo-select">
                        <?php foreach ($statusOptions as $opt): ?>
                        <option value="<?= $h($opt['value']) ?>"><?= $h($opt['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <label>Intention
                <textarea name="description" rows="2" placeholder="Objet, théâtre, contrainte principale."></textarea>
            </label>
            <button type="submit" class="ops-ws__btn">Créer l’espace</button>
        </form>
        <?php endif; ?>

        <?php if ($operations === []): ?>
        <p class="ops-ws__empty">Aucune opération n’est ouverte pour le moment.</p>
        <?php else: ?>
        <ul class="ops-ws__list">
            <?php foreach ($operations as $op): ?>
            <li>
                <a class="ops-ws__card" href="<?= $h(url('operations/' . $op['uuid'])) ?>">
                    <span class="ops-ws__code"><?= $h($op['code'] ?? '') ?></span>
                    <strong><?= $h($op['name'] ?? '') ?></strong>
                    <span class="ops-ws__meta">
                        <?= $h($op['status_label'] ?? '') ?>
                        · <?= $h($op['classification_label'] ?? '') ?>
                        · <?= $h($op['phase_label'] ?? '') ?>
                    </span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>
