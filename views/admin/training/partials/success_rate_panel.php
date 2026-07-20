<?php
declare(strict_types=1);
/**
 * Indicateurs de taux de réussite (communauté + plateforme).
 *
 * @var array{completed?:int,failed?:int,in_progress?:int,engaged?:int,rate_percent?:float|null} $successRateTenant
 * @var array{completed?:int,failed?:int,in_progress?:int,engaged?:int,rate_percent?:float|null} $successRatePlatform
 * @var string|null $successRatePanelClass classes CSS optionnelles sur le conteneur
 */
$successRateTenant = is_array($successRateTenant ?? null) ? $successRateTenant : [];
$successRatePlatform = is_array($successRatePlatform ?? null) ? $successRatePlatform : [];
$successRatePanelClass = trim((string) ($successRatePanelClass ?? 'lms-panel rounded-[2rem] p-5 md:p-6'));

$formatRate = static function (?float $rate): string {
    if ($rate === null) {
        return '—';
    }

    return rtrim(rtrim(number_format($rate, 1, ',', ''), '0'), ',') . ' %';
};

$tenantRate = array_key_exists('rate_percent', $successRateTenant)
    ? ($successRateTenant['rate_percent'] !== null ? (float) $successRateTenant['rate_percent'] : null)
    : null;
$platformRate = array_key_exists('rate_percent', $successRatePlatform)
    ? ($successRatePlatform['rate_percent'] !== null ? (float) $successRatePlatform['rate_percent'] : null)
    : null;

$tenantCompleted = (int) ($successRateTenant['completed'] ?? 0);
$tenantEngaged = (int) ($successRateTenant['engaged'] ?? 0);
$platformCompleted = (int) ($successRatePlatform['completed'] ?? 0);
$platformEngaged = (int) ($successRatePlatform['engaged'] ?? 0);
?>
<section class="<?= htmlspecialchars($successRatePanelClass) ?>" aria-labelledby="success-rate-heading">
    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4 mb-5">
        <div class="max-w-2xl">
            <h2 id="success-rate-heading" class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-600 m-0 mb-2">Taux de réussite</h2>
            <p class="text-sm text-slate-600 leading-relaxed m-0">
                Part des inscriptions <strong>terminées</strong> parmi celles déjà <strong>engagées</strong>
                (en cours, terminées ou non validées). Les demandes en attente, non démarrées, annulées, révoquées ou expirées ne sont pas comptées.
            </p>
        </div>
    </div>
    <div class="grid gap-3 sm:grid-cols-2" aria-label="Taux de réussite par périmètre">
        <div class="tc-stat">
            <p class="tc-stat__k">Dans cette communauté</p>
            <p class="tc-stat__v text-emerald-700 tabular-nums"><?= htmlspecialchars($formatRate($tenantRate)) ?></p>
            <p class="text-xs text-slate-500 mt-2 mb-0 leading-snug">
                <?= $tenantEngaged > 0
                    ? $tenantCompleted . ' terminée' . ($tenantCompleted > 1 ? 's' : '') . ' sur ' . $tenantEngaged . ' engagée' . ($tenantEngaged > 1 ? 's' : '')
                    : 'Aucune inscription engagée pour le moment.' ?>
            </p>
        </div>
        <div class="tc-stat">
            <p class="tc-stat__k">Sur toute la plateforme</p>
            <p class="tc-stat__v tabular-nums text-slate-900"><?= htmlspecialchars($formatRate($platformRate)) ?></p>
            <p class="text-xs text-slate-500 mt-2 mb-0 leading-snug">
                <?= $platformEngaged > 0
                    ? $platformCompleted . ' terminée' . ($platformCompleted > 1 ? 's' : '') . ' sur ' . $platformEngaged . ' engagée' . ($platformEngaged > 1 ? 's' : '')
                    : 'Aucune inscription engagée sur le site pour le moment.' ?>
            </p>
        </div>
    </div>
</section>
