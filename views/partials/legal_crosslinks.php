<?php
declare(strict_types=1);
?>
<nav class="mt-12 pt-8 border-t border-slate-200" aria-label="Autres pages légales">
    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-3">Pages associées</p>
    <div class="flex flex-wrap gap-x-4 gap-y-2 text-xs">
        <?php
        $legal_link_class = 'text-emerald-700 font-semibold hover:underline';
        require base_path('views/partials/legal_site_links.php');
        ?>
    </div>
</nav>
