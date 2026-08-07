<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var string $objectLabel */
/** @var string $objectHint */
/** @var string $objectEmpty */
/** @var string $objectKind */
/** @var list<array<string,mixed>> $objects */
/** @var bool $canManage */
$objects = is_array($objects ?? null) ? $objects : [];
$objectKind = (string) ($objectKind ?? 'custom');
$objectEmpty = (string) ($objectEmpty ?? 'Aucun objet pour le moment.');
?>
<div class="page-heading">
    <div>
        <div class="page-heading-overline">Objets // <?= $h($objectLabel) ?></div>
        <h1><?= $h($objectLabel) ?></h1>
        <p><?= $h($objectHint) ?></p>
    </div>
    <div class="page-reference">
        <strong><?= count($objects) ?> au registre</strong>
        <?php if (!empty($canManage)): ?>
            <div style="margin-top:.65rem">
                <a class="btn" href="<?= $h(url('atak/sse/objets/nouveau?type=' . urlencode($objectKind))) ?>">Ajouter</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">OBJ</span> Registre — <?= $h($objectLabel) ?></div>
        <div class="panel-meta"><?= count($objects) ?> entrée<?= count($objects) > 1 ? 's' : '' ?></div>
    </div>
    <?php if ($objects === []): ?>
        <div class="empty-state">
            <div class="empty-state-inner">
                <div class="empty-symbol">OBJ</div>
                <strong>Registre vide</strong>
                <p><?= $h($objectEmpty) ?></p>
                <?php if (!empty($canManage)): ?>
                    <a class="btn" href="<?= $h(url('atak/sse/objets/nouveau?type=' . urlencode($objectKind))) ?>">Créer un objet</a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Type</th>
                    <th>Libellé</th>
                    <th>Précision</th>
                    <th>Investigation</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($objects as $o): ?>
                    <tr>
                        <td><span class="badge badge--gray"><?= $h($o['kind_label'] ?? '') ?></span></td>
                        <td><strong><?= $h($o['label'] ?? '') ?></strong></td>
                        <td class="muted"><?= $h($o['detail'] ?? '—') ?></td>
                        <td>
                            <span class="record-id"><?= $h($o['mesh_reference'] ?? '') ?></span>
                            <div class="muted"><?= $h($o['mesh_title'] ?? '') ?></div>
                        </td>
                        <td>
                            <?php if (!empty($o['href'])): ?>
                                <a class="btn-open" href="<?= $h($o['href']) ?>">Ouvrir</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
