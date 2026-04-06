<?php
declare(strict_types=1);
?>
<footer class="relative z-10 mt-auto border-t border-slate-200/80 bg-white/60 py-6 px-4">
    <div class="max-w-6xl mx-auto flex flex-col sm:flex-row items-center justify-center sm:justify-between gap-4 text-[9px] font-black uppercase tracking-[0.25em] text-slate-400">
        <span>Athena — plateforme communautaire</span>
        <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-2 opacity-90 normal-case tracking-normal font-semibold text-[11px] text-slate-500 max-w-full">
            <?php
            $legal_link_class = 'hover:text-emerald-700';
            require base_path('views/partials/legal_site_links.php');
            ?>
        </div>
    </div>
</footer>
<?php require base_path('views/partials/cookie_banner.php'); ?>
