<?php
declare(strict_types=1);
?>
<footer class="relative z-10 mt-auto border-t border-slate-200/80 bg-gradient-to-b from-white to-slate-50/70 px-4 py-7">
    <div class="mx-auto flex max-w-6xl flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="text-center sm:text-left">
            <p class="text-[10px] font-black uppercase tracking-[0.26em] text-emerald-700">Athena Compsec</p>
            <p class="mt-1 text-xs text-slate-500">Portail communautaire MILSIM — accès sécurisé &amp; gestion centralisée.</p>
        </div>
        <div class="flex max-w-full flex-wrap items-center justify-center gap-x-4 gap-y-2 text-[11px] font-semibold text-slate-600 sm:justify-end">
            <?php
            $legal_link_class = 'transition hover:text-emerald-700';
            require base_path('views/partials/legal_site_links.php');
            ?>
        </div>
    </div>
</footer>
<?php require base_path('views/partials/cookie_banner.php'); ?>
