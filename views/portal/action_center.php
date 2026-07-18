<?php
/** @var array<string, mixed> $action_center_digest */
$action_center_digest = $action_center_digest ?? [];
$sections = $action_center_digest['sections'] ?? [];
?>
<div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
    <header class="mb-10">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Synthèse personnelle</p>
        <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Centre d’actions</h1>
        <p class="mt-3 text-sm leading-relaxed text-slate-600">
            Vue consolidée des éléments à traiter ou à consulter en priorité. Chaque lien ouvre l’écran métier concerné : les actions se poursuivent toujours au même endroit qu’à l’habitude.
        </p>
    </header>

    <div class="space-y-10">
        <?php foreach ($sections as $secIdx => $sec): ?>
        <?php
        $st = (string) ($sec['title'] ?? '');
        $items = $sec['items'] ?? [];
        if (! is_array($items) || $items === [] || $st === '') {
            continue;
        }
        $secDomId = 'action-center-sec-' . (int) $secIdx;
        ?>
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="<?= htmlspecialchars($secDomId, ENT_QUOTES, 'UTF-8') ?>">
            <h2 id="<?= htmlspecialchars($secDomId, ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-bold text-slate-900"><?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?></h2>
            <ul class="mt-4 space-y-2">
                <?php foreach ($items as $it): ?>
                <?php
                if (! is_array($it)) {
                    continue;
                }
                $label = (string) ($it['label'] ?? '');
                $href = (string) ($it['href'] ?? '');
                $hint = (string) ($it['hint'] ?? '');
                $count = isset($it['count']) ? (int) $it['count'] : null;
                if ($label === '' || $href === '') {
                    continue;
                }
                $meta = $count !== null && $count > 0 ? (string) $count : '';
                ?>
                <?php $ui_row_title = $label; $ui_row_href = $href; $ui_row_subtitle = $hint; $ui_row_meta = $meta; require base_path('views/partials/ui/list_row_link.php'); ?>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endforeach; ?>
    </div>

    <p class="mt-12 text-center text-sm text-slate-500">
        <a href="<?= htmlspecialchars(url('hub'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-700 underline decoration-emerald-200 underline-offset-2 hover:text-emerald-800">Retour au centre de commandement</a>
    </p>
</div>
