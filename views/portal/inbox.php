<?php
/** @var list<array{title: string, description?: string, links: list<array{label: string, href: string, hint?: string}>}> $inbox_sections */
$inbox_sections = $inbox_sections ?? [];
?>
<div class="bg-slate-50 pb-16 sm:pb-24">
    <div class="border-b border-slate-200 bg-white">
        <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Commandement</p>
            <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Boîte de réception</h1>
            <p class="mt-3 text-sm leading-relaxed text-slate-600">
                Retrouvez ici les canaux d’échanges et la synthèse des éléments à traiter dans votre communauté.
            </p>
        </div>
    </div>

    <div class="mx-auto max-w-3xl space-y-8 px-4 py-10 sm:px-6 lg:px-8">
        <?php foreach ($inbox_sections as $sec): ?>
            <?php
            $title = (string) ($sec['title'] ?? '');
            $desc = (string) ($sec['description'] ?? '');
            $links = $sec['links'] ?? [];
            if ($title === '' || !is_array($links) || $links === []) {
                continue;
            }
            ?>
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <h2 class="text-lg font-bold text-slate-900"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h2>
                <?php if ($desc !== ''): ?>
                    <p class="mt-2 text-sm text-slate-600"><?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <ul class="mt-6 space-y-2">
                    <?php foreach ($links as $link): ?>
                        <?php
                        $label = (string) ($link['label'] ?? '');
                        $href = (string) ($link['href'] ?? '');
                        $hint = (string) ($link['hint'] ?? '');
                        if ($label === '' || $href === '') {
                            continue;
                        }
                        $ui_row_title = $label;
                        $ui_row_href = $href;
                        $ui_row_subtitle = $hint;
                        $ui_row_meta = '';
                        require base_path('views/partials/ui/list_row_link.php');
                        ?>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>

        <p class="text-center text-sm text-slate-500">
            <a href="<?= htmlspecialchars(url('hub'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-700 underline decoration-emerald-200 underline-offset-2 hover:text-emerald-800">Retour au centre de commandement</a>
        </p>
    </div>
</div>
