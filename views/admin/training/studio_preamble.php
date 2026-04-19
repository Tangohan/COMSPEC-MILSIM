<?php
declare(strict_types=1);
$preamblePostUrl = url(training_studio_path() . '/preamble-ack');
$studioPreambleBgUrl = 'https://i.redd.it/zjiqhy0q5lx61.png';
?>
<div class="training-studio-panel overflow-hidden border border-slate-200/90 shadow-lg">
    <div class="relative min-h-[min(70vh,520px)] px-6 py-12 md:px-12 md:py-16 text-white">
        <div class="pointer-events-none absolute inset-0 z-0 bg-cover bg-center bg-no-repeat" style="background-image:url('<?= htmlspecialchars($studioPreambleBgUrl, ENT_QUOTES, 'UTF-8') ?>')" aria-hidden="true"></div>
        <div class="pointer-events-none absolute inset-0 z-0 bg-gradient-to-br from-slate-950/90 via-slate-900/82 to-emerald-950/88" aria-hidden="true"></div>
        <div class="pointer-events-none absolute inset-0 z-0 opacity-[0.1]" style="background-image:radial-gradient(circle at 20% 20%,#fff 0.5px,transparent 0.6px),radial-gradient(circle at 80% 60%,#fff 0.5px,transparent 0.6px);background-size:20px 20px" aria-hidden="true"></div>
        <div class="relative z-[1] max-w-2xl">
            <p class="text-[0.65rem] font-black uppercase tracking-[0.35em] text-emerald-300/90 mb-4">Studio LMS</p>
            <h1 class="text-2xl md:text-4xl font-black tracking-tight leading-tight mb-5">Accès à l’atelier de conception</h1>
            <div class="space-y-4 text-sm md:text-base text-slate-200/95 leading-relaxed">
                <p>Vous entrez dans l’espace où sont créés et structurés les parcours pour votre communauté. Les changements s’appliquent au contexte de la communauté active et peuvent impacter le catalogue des apprenants une fois publié.</p>
                <ul class="list-disc pl-5 space-y-2 text-slate-200/90">
                    <li>Ne partagez pas votre session sur un poste partagé sans vous déconnecter ensuite.</li>
                    <li>Les brouillons restent visibles côté staff : vérifiez la visibilité avant publication.</li>
                    <li>En cas de doute sur une modification sensible, coordonnez-vous avec le commandement ou le pôle formation.</li>
                </ul>
            </div>
            <form method="post" action="<?= htmlspecialchars($preamblePostUrl, ENT_QUOTES, 'UTF-8') ?>" class="mt-10 flex flex-wrap items-center gap-4">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-emerald-500 px-6 py-3 text-sm font-black uppercase tracking-[0.12em] text-slate-950 shadow-lg shadow-emerald-900/30 transition hover:bg-emerald-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/80">
                    Entrer dans le Studio
                </button>
                <a href="<?= htmlspecialchars(training_lms_admin_url(), ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-semibold text-slate-300 underline decoration-slate-500 underline-offset-4 hover:text-white">Retour au pilotage des formations</a>
            </form>
        </div>
    </div>
</div>
