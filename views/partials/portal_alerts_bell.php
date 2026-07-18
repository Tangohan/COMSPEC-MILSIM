<?php
declare(strict_types=1);

/**
 * Cloche « annonces et alertes » (même contenu que le header portail).
 *
 * @var array<string, mixed> $ctx Résultat de portal_header_context()
 * @var string|null $portal_alerts_dropdown_id Optionnel, id du panneau (défaut : portal-alerts-dropdown)
 */
$alertsSeverity = (string) ($ctx['alerts_severity'] ?? 'info');
$dropdownId = isset($portal_alerts_dropdown_id) && is_string($portal_alerts_dropdown_id) && $portal_alerts_dropdown_id !== ''
    ? $portal_alerts_dropdown_id
    : 'portal-alerts-dropdown';
?>
<div class="relative" data-portal-alerts-wrap>
    <button type="button"
            class="relative inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500"
            data-portal-alerts-trigger
            aria-expanded="false"
            aria-controls="<?= htmlspecialchars($dropdownId, ENT_QUOTES, 'UTF-8') ?>"
            aria-haspopup="dialog"
            aria-label="Annonces et alertes">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        <?php if (($ctx['alerts_count'] ?? 0) > 0): ?>
            <span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-[1rem] items-center justify-center rounded-full px-1 text-[10px] font-black <?= $alertsSeverity === 'urgent' ? 'bg-rose-600 text-white' : 'bg-amber-500 text-slate-950' ?>">
                <?= (int) $ctx['alerts_count'] ?>
            </span>
        <?php endif; ?>
    </button>
    <div id="<?= htmlspecialchars($dropdownId, ENT_QUOTES, 'UTF-8') ?>"
         class="portal-alerts-panel absolute right-0 top-full z-[130] mt-2 max-h-[min(70vh,420px)] overflow-y-auto rounded-2xl p-0"
         data-portal-alerts-panel
         hidden
         role="dialog"
         aria-label="Liste des annonces">
        <?php if (($ctx['alerts'] ?? []) === []): ?>
            <p class="px-4 py-6 text-center text-sm text-slate-500">Aucune annonce active.</p>
        <?php else: ?>
            <?php foreach ($ctx['alerts'] as $a): ?>
                <?php if (!is_array($a)) {
                    continue;
                } ?>
                <div class="portal-alerts-panel__item px-4 py-3">
                    <?php
                    $aScope = (string) ($a['scope'] ?? '');
                    $aKind = (string) ($a['kind'] ?? '');
                    $aKindLabel = match ($aKind) {
                        'urgent' => 'Urgent',
                        'discount' => 'Offre',
                        'novelty' => 'Nouveauté',
                        'rappel' => 'Rappel',
                        'info' => 'Info',
                        default => $aKind !== '' ? $aKind : 'Info',
                    };
                    $isPlatformAlert = $aScope === 'platform';
                    ?>
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400"><?= htmlspecialchars($aKindLabel, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php if ($isPlatformAlert): ?>
                        <span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-1.5 py-0.5 text-[9px] font-black uppercase tracking-wider text-emerald-800" title="Annonce officielle du site Athena">
                            <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            Site vérifié
                        </span>
                        <?php endif; ?>
                    </div>
                    <p class="mt-1 text-sm font-bold text-slate-900"><?= htmlspecialchars((string) ($a['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if (trim((string) ($a['body'] ?? '')) !== ''): ?>
                        <p class="mt-1 text-xs leading-relaxed text-slate-600"><?= htmlspecialchars((string) $a['body'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <?php if ((!empty($a['cta_label']) && !empty($a['cta_url'])) || (!empty($a['cta_secondary_label']) && !empty($a['cta_secondary_url']))): ?>
                        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1">
                            <?php if (!empty($a['cta_label']) && !empty($a['cta_url'])): ?>
                                <a href="<?= htmlspecialchars((string) $a['cta_url'], ENT_QUOTES, 'UTF-8') ?>" class="inline-flex text-xs font-bold text-emerald-700 hover:underline">
                                    <?= htmlspecialchars((string) $a['cta_label'], ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($a['cta_secondary_label']) && !empty($a['cta_secondary_url'])): ?>
                                <a href="<?= htmlspecialchars((string) $a['cta_secondary_url'], ENT_QUOTES, 'UTF-8') ?>" class="inline-flex text-xs font-semibold text-slate-600 hover:text-slate-900 hover:underline">
                                    <?= htmlspecialchars((string) $a['cta_secondary_label'], ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
