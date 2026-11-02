<?php
/**
 * Aperçu d'une formule en cours de modification : bloc d'impact (data-spl-impact-src, recopié par le script dans la
 * barre d'enregistrement) et cartes de la page publique (même rendu que /platform/upgrade).
 *
 * @var array{cards: list<array<string, mixed>>, isPublic: bool, slug: string} $previewData
 * @var array<string, string> $previewErrors
 * @var list<array{tenant_id: int, tenant_name: string, key: string, label: string, used: int, limit: int}> $previewOverages
 * @var int $previewSubscribers
 */
$pvH = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$previewErrors = is_array($previewErrors ?? null) ? $previewErrors : [];
$previewOverages = is_array($previewOverages ?? null) ? $previewOverages : [];
$pvTenants = array_values(array_unique(array_column($previewOverages, 'tenant_name')));
?>
<div class="spl-impact-src" data-spl-impact-src hidden>
    <?php if ($previewErrors !== []): ?>
        <p class="spl-impact spl-impact--err">
            <?php foreach ($previewErrors as $pvKey => $pvMsg): ?>
                <span data-spl-error-key="<?= $pvH((string) $pvKey) ?>"><?= $pvH($pvMsg) ?></span>
            <?php endforeach; ?>
        </p>
    <?php elseif ($previewOverages !== []): ?>
        <div class="spl-impact spl-impact--warn">
            <p><strong><?= count($pvTenants) ?></strong> <span>communauté(s) dépassent déjà un nouveau plafond. Rien ne leur est retiré : seuls les nouveaux ajouts seront refusés.</span></p>
            <ul>
                <?php foreach (array_slice($previewOverages, 0, 12) as $pvO): ?>
                    <li>
                        <a href="<?= $pvH(url('admin/tenants/' . (int) $pvO['tenant_id'] . '/edit')) ?>"><?= $pvH((string) $pvO['tenant_name']) ?></a>
                        <span>·</span> <span><?= $pvH((string) $pvO['label']) ?></span>
                        <span>:</span> <strong><?= (int) $pvO['used'] ?></strong> <span>/</span> <span><?= (int) $pvO['limit'] ?></span>
                    </li>
                <?php endforeach; ?>
                <?php if (count($previewOverages) > 12): ?>
                    <li>…</li>
                <?php endif; ?>
            </ul>
        </div>
    <?php else: ?>
        <p class="spl-impact spl-impact--ok"><strong><?= (int) ($previewSubscribers ?? 0) ?></strong> <span>communauté(s) sur cette formule. Aucune ne dépasse les plafonds saisis.</span></p>
    <?php endif; ?>
</div>
<?php if (empty($previewData['isPublic'])): ?>
    <p class="pa-flash pa-flash--warn">Cette formule n’est pas proposée sur la page publique ; voici sa carte seule.</p>
<?php endif; ?>
<div class="spl-screen">
    <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Comparer les formules</p>
    <?php
    $planOfferCards = $previewData['cards'] ?? [];
    $planOfferHighlight = (string) ($previewData['slug'] ?? '');
    require base_path('views/partials/plan_offer_cards.php');
    ?>
</div>
