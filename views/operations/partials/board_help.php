<?php
declare(strict_types=1);
/**
 * Panneau d’aide mur / tableau opérationnel (pilotage ou consultation).
 * @var bool $boardHelpIsPilotage
 */
$boardHelpIsPilotage = !empty($boardHelpIsPilotage);
$guideUrl = url('documentation') . '#mur-operationnel';
$refUrl = url('documentation/fichier/tableau-operationnel');
?>
<details class="ops-board__help rounded-xl border border-slate-200 bg-white shadow-sm">
    <summary class="cursor-pointer list-none px-4 py-3 text-sm font-bold text-slate-900 hover:bg-slate-50 rounded-xl [&::-webkit-details-marker]:hidden flex items-center justify-between gap-3">
        <span>Aide — comment fonctionne <?= $boardHelpIsPilotage ? 'le tableau opérationnel' : 'le mur opérationnel' ?> ?</span>
        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500 shrink-0">Ouvrir</span>
    </summary>
    <div class="border-t border-slate-100 px-4 py-4 text-sm text-slate-700 space-y-3 leading-relaxed">
        <?php if ($boardHelpIsPilotage): ?>
            <p>
                Cet écran est le <strong>pilotage</strong> : vous créez des fiches en brouillon, les validez, les mettez sur le mur, suivez le statut, puis clôturez ou retirez.
                La <strong>vue membres</strong> (mur) ne montre que les fiches déjà publiées.
            </p>
            <ol class="list-decimal pl-5 space-y-1.5">
                <li><strong>Nouvelle entrée</strong> (ou modèle / publication depuis un événement) → brouillon.</li>
                <li>Compléter public, sensibilité, dates, priorité → <strong>Enregistrer</strong>.</li>
                <li><strong>Approuver</strong> ou <strong>Mettre en ligne</strong> pour diffuser sur le mur.</li>
                <li>Suivre Planifié / En cours ; cocher les <strong>points de contrôle</strong> ; <strong>Clôturer</strong>.</li>
                <li>Au besoin : <strong>Mise à jour opérationnelle</strong>, <strong>Copier en brouillon</strong>, ou <strong>Retirer du mur</strong>.</li>
            </ol>
            <p>
                La <strong>posture</strong> (Normale · Vigilance · Alerte · Crise) s’applique ici et s’affiche aussi sur le mur.
                Utilisez les filtres Publication / période / type et les modes <strong>synthèse crise</strong> ou <strong>briefing</strong> pour cadrer l’affichage.
            </p>
        <?php else: ?>
            <p>
                Cet écran est la <strong>consultation</strong> : permanences, consignes et activités <strong>déjà publiées</strong> pour la période affichée.
                Vous ne créez pas de fiche ici. Si vous êtes habilité, utilisez <strong>Ouvrir le pilotage</strong>.
            </p>
            <ul class="list-disc pl-5 space-y-1.5">
                <li>Lisez d’abord la <strong>posture</strong> et les <strong>flashs</strong>.</li>
                <li>Parcourez les colonnes : permanences du jour, infos pratiques, manifestations, missions.</li>
                <li>Vous ne voyez que ce qui vous est destiné (communauté, unité, emploi, sensibilité).</li>
            </ul>
        <?php endif; ?>
        <p class="flex flex-wrap gap-x-4 gap-y-2 pt-1">
            <a href="<?= htmlspecialchars($guideUrl, ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-800 hover:underline">Guide du portail — section complète</a>
            <a href="<?= htmlspecialchars($refUrl, ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-slate-700 hover:underline">Documentation détaillée (référence)</a>
        </p>
    </div>
</details>
