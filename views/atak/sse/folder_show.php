<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed> $case */
/** @var list<array<string,mixed>> $children */
/** @var array<int,array{persons:int,notes:int,evidence:int}> $childCounts */
/** @var bool $canManage */
$classBadge = static function (string $key): string {
    return match ($key) {
        'confidentiel' => 'badge badge--amber',
        'tres_restreint' => 'badge badge--red',
        'interne' => 'badge badge--gray',
        default => 'badge',
    };
};
?>
<div class="breadcrumb">
    Athena / SSE /
    <a class="link" href="<?= $h(url('atak/sse/dossiers')) ?>">Dossiers</a> /
    <strong><?= $h($case['reference_code'] ?? '') ?></strong>
</div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">Dossier // Conteneur</div>
        <h1><?= $h($case['title'] ?? '') ?></h1>
        <p>Sous-dossiers et affaires rattachés à ce dossier.</p>
    </div>
    <div class="page-reference">
        <strong><?= $h($case['reference_code'] ?? '') ?></strong>
        <?= count($children) ?> élément<?= count($children) > 1 ? 's' : '' ?>
    </div>
</div>

<?php if ($canManage): ?>
<div class="toolbar">
    <a class="btn" href="<?= $h(url('atak/sse/dossiers/nouveau?parent=' . (int) $case['id'])) ?>">+ Affaire dans ce dossier</a>
</div>
<?php endif; ?>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">01</span> Contenu</div>
        <div class="panel-meta"><?= count($children) ?> entrée<?= count($children) > 1 ? 's' : '' ?></div>
    </div>
    <?php if ($children === []): ?>
        <div class="empty-state">
            <div class="empty-state-inner">
                <div class="empty-symbol">□</div>
                <strong>Dossier vide</strong>
                <p>Créez un sous-dossier depuis le rail, ou ouvrez une affaire ici.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="sse-folder-grid">
            <?php foreach ($children as $c):
                $cnt = $childCounts[(int) ($c['id'] ?? 0)] ?? ['persons' => 0, 'notes' => 0, 'evidence' => 0];
                $isFolder = !empty($c['is_folder']);
                ?>
                <a class="sse-folder-card <?= $isFolder ? 'is-folder' : '' ?>" href="<?= $h(url('atak/sse/dossiers/' . (int) $c['id'])) ?>">
                    <div class="sse-folder-card-top">
                        <span class="sse-folder-kind"><?= $isFolder ? 'Dossier' : 'Affaire' ?></span>
                        <?php if (!empty($c['has_unlock_code'])): ?><span class="badge badge--gray">Protégé</span><?php endif; ?>
                    </div>
                    <strong><?= $h($c['title'] ?? '') ?></strong>
                    <span class="record-id"><?= $h($c['reference_code'] ?? '') ?></span>
                    <div class="sse-folder-card-meta">
                        <span class="<?= $h($classBadge((string) ($c['classification'] ?? ''))) ?>"><?= $h($c['classification_label'] ?? '') ?></span>
                        <?php if (!$isFolder): ?>
                            <span class="sse-count-set">
                                <span class="sse-count"><span class="sse-count-n"><?= (int) $cnt['persons'] ?></span> pers.</span>
                                <span class="sse-count"><span class="sse-count-n"><?= (int) $cnt['evidence'] ?></span> pièces</span>
                            </span>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
