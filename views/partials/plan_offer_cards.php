<?php
/**
 * Cartes « Comparer les formules » (page publique /platform/upgrade et aperçu de l'administration des formules).
 * Contenu généré par App\Services\Billing\PlanOfferSummary::cards() depuis les valeurs réelles des formules.
 *
 * @var list<array{slug: string, name: string, tagline: string, recommended: bool, inherits: ?string, items: list<array{label: string, value: ?string, suffix: ?string}>}> $planOfferCards
 * @var string|null $planOfferHighlight slug mis en évidence (aperçu admin)
 */
$planOfferCards = is_array($planOfferCards ?? null) ? $planOfferCards : [];
$planOfferHighlight = (string) ($planOfferHighlight ?? '');
$poH = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$poCount = count($planOfferCards);
$poGrid = $poCount >= 4 ? 'sm:grid-cols-2 lg:grid-cols-4' : ($poCount === 3 ? 'sm:grid-cols-3' : ($poCount === 2 ? 'sm:grid-cols-2' : ''));
?>
<div class="mt-4 grid gap-4 <?= $poGrid ?>">
    <?php foreach ($planOfferCards as $poCard): ?>
        <?php
        $poRec = !empty($poCard['recommended']);
        $poMark = (string) $poCard['slug'] === $planOfferHighlight && $planOfferHighlight !== '';
        $poDot = $poRec ? 'bg-emerald-500' : ((string) $poCard['slug'] === 'free' ? 'bg-slate-400' : 'bg-sky-500');
        ?>
        <div class="relative rounded-2xl border p-5 shadow-sm <?= $poRec ? 'border-emerald-200/80 bg-gradient-to-b from-white to-emerald-50/40 ring-1 ring-emerald-500/10' : 'border-slate-200 bg-white' ?><?= $poMark ? ' ring-2 ring-amber-400' : '' ?>" data-plan-card="<?= $poH((string) $poCard['slug']) ?>">
            <?php if ($poRec): ?>
                <span class="absolute right-4 top-4 rounded-full bg-emerald-600 px-2.5 py-0.5 text-[9px] font-black uppercase tracking-wider text-white">Recommandé</span>
            <?php endif; ?>
            <?php if ((string) $poCard['tagline'] !== ''): ?>
                <p class="text-[10px] font-black uppercase tracking-[0.28em] <?= $poRec ? 'text-emerald-700/80' : 'text-slate-400' ?>"><?= $poH((string) $poCard['tagline']) ?></p>
            <?php endif; ?>
            <p class="mt-2 text-xl font-black text-slate-900"><?= $poH((string) $poCard['name']) ?></p>
            <?php if (!empty($poCard['inherits'])): ?>
                <p class="mt-3 text-xs font-semibold text-slate-500"><span>Tout ce qu’offre</span> <strong class="text-slate-700"><?= $poH((string) $poCard['inherits']) ?></strong><span>, plus :</span></p>
            <?php endif; ?>
            <ul class="mt-3 space-y-2.5 text-sm text-slate-600">
                <?php foreach ($poCard['items'] as $poItem): ?>
                    <li class="flex gap-2">
                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full <?= $poDot ?>"></span>
                        <span>
                            <span><?= $poH((string) $poItem['label']) ?></span><?php if ($poItem['value'] !== null): ?><span> : </span><strong class="font-semibold text-slate-900"><?= $poH((string) $poItem['value']) ?></strong><?php endif; ?><?php if (!empty($poItem['suffix'])): ?> <span><?= $poH((string) $poItem['suffix']) ?></span><?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
                <?php if ($poCard['items'] === []): ?>
                    <li class="text-slate-400">Aucun ajout par rapport à la formule précédente</li>
                <?php endif; ?>
            </ul>
        </div>
    <?php endforeach; ?>
</div>
