<?php
/** @var list<array{label:string,state:string,detail:string}> $syncChannels */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$channels = is_array($syncChannels ?? null) ? $syncChannels : [];
?>
<div class="jnet-stack">
    <?php foreach ($channels as $ch): ?>
        <?php
        $state = (string) ($ch['state'] ?? '');
        $badge = $state === 'ok' ? 'jnet-badge--ok' : 'jnet-badge--watch';
        $stateLabel = match ($state) {
            'ok' => 'Nominal',
            'idle' => 'En attente',
            default => 'À vérifier',
        };
        ?>
        <div class="jnet-sync-row">
            <div>
                <strong><?= $h((string) ($ch['label'] ?? '')) ?></strong>
                <span><?= $h((string) ($ch['detail'] ?? '')) ?></span>
            </div>
            <span class="jnet-badge <?= $badge ?>"><?= $h($stateLabel) ?></span>
        </div>
    <?php endforeach; ?>
</div>
