<?php
declare(strict_types=1);
/**
 * Une extraction de téléphone dans une liste : référence, appareil, propriétaire, lieu, ancienneté, nombre d'éléments.
 *
 * @var array<string,mixed> $e extraction hydratée (SsePhoneExtractionService::hydrate)
 */
$h = $h ?? static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$total = (int) array_sum(array_intersect_key($e['counts'], \App\Services\Sse\SsePhoneExtractionService::CATEGORIES));
?>
<a class="fx-hit" href="<?= $h(url('atak/sse/fichiers/telephones/' . (int) $e['id'])) ?>" style="grid-template-columns:44px minmax(0,1fr) auto">
    <span class="fx-mug" style="width:44px;height:44px"><svg class="i"><use href="#fx-phone"></use></svg></span>
    <span style="min-width:0">
        <span class="fx-hn">
            <b translate="no"><?= $h($e['ref']) ?> · <?= $h($e['device_label'] ?? $e['device_uid']) ?></b>
            <?php if ($e['owner_kind'] === 'ai'): ?><span class="fx-flg b">Saisi sur une IA</span><?php endif; ?>
            <?php if (!$e['complete']): ?><span class="fx-flg a">Copie partielle</span><?php endif; ?>
            <?php if ($e['status'] !== 'visible'): ?><span class="fx-flg a"><?= $e['status'] === 'hidden' ? 'Masquée' : 'À valider' ?></span><?php endif; ?>
            <?php if ($e['identity_id'] !== null): ?><span class="fx-flg g">Rattachée IDT-<?= (int) $e['identity_id'] ?></span><?php endif; ?>
        </span>
        <span class="fx-kv">
            <span translate="no"><?= $h($e['owner_label'] ?? 'Propriétaire inconnu') ?></span>
            <?php if (($e['grid'] ?? null) !== null): ?><span>GRILLE <b><?= $h($e['grid']) ?></b></span><?php endif; ?>
            <span><?= $h(\App\Services\Sse\SseFileRecordService::ago((string) $e['created_at'], gmdate('Y-m-d H:i:s'))) ?></span>
        </span>
    </span>
    <span class="fx-sc"><small>éléments</small><b><?= $total ?></b></span>
</a>
