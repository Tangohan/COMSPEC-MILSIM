<?php
declare(strict_types=1);
?>
<div class="max-w-3xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 tracking-tight uppercase mb-2">Données personnelles</h1>
    <p class="text-sm text-slate-500 mb-10">Comment Athena traite les informations liées à votre compte et à votre activité sur le portail.</p>

    <div class="space-y-8 text-sm text-slate-600 leading-relaxed">
        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">Responsable du traitement</h2>
            <p>Les traitements liés au fonctionnement général de la plateforme (hébergement, sécurité, comptes, facturation le cas échéant) sont réalisés par l’exploitant du service Athena, selon les informations de contact publiées sur ce site.</p>
            <p>Pour les activités propres à une communauté (recrutement, personnel, événements, forum, etc.), votre communauté peut également agir comme responsable de traitement ou co-responsable selon sa configuration ; en cas de doute, adressez-vous aux administrateurs de votre communauté.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">Données concernées</h2>
            <p>Selon les fonctionnalités utilisées, peuvent être traitées notamment : identité de connexion (adresse électronique), éléments de profil et de personnel, contenus que vous publiez (messages, pièces jointes), données de participation aux événements et formations, journaux techniques nécessaires à la sécurité et au bon fonctionnement du service.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">Finalités et bases légales</h2>
            <ul class="list-disc pl-5 space-y-2">
                <li><span class="text-slate-800 font-semibold">Fourniture du service</span> : création de compte, authentification, navigation dans les espaces autorisés, exécution des missions confiées par votre communauté.</li>
                <li><span class="text-slate-800 font-semibold">Obligations et sécurité</span> : prévention des abus, journalisation proportionnée, respect d’obligations légales.</li>
                <li><span class="text-slate-800 font-semibold">Consentement</span> : lorsque la loi l’exige (par exemple pour certaines communications ou traitements optionnels), recueilli de manière distincte et révocable.</li>
            </ul>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">Durées de conservation</h2>
            <p>Les données sont conservées pendant la durée nécessaire aux finalités ci-dessus, puis archivées ou supprimées selon les règles du service et, le cas échéant, de votre communauté. Certains journaux peuvent être conservés plus longtemps lorsque la loi l’impose ou pour la défense de droits en justice.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">Destinataires et sous-traitants</h2>
            <p>Les données sont accessibles aux équipes habilitées de l’exploitant et, selon les réglages de votre communauté, aux administrateurs et référents désignés. Des prestataires techniques (hébergement, envoi de courriels, paiement en ligne si activé) peuvent intervenir en tant que sous-traitants, dans le respect d’instructions contractuelles et de la réglementation.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">Vos droits</h2>
            <p>Vous pouvez demander l’accès, la rectification, l’effacement, la limitation ou la portabilité de vos données lorsque le droit applicable le prévoit, ainsi que retirer votre consentement lorsque le traitement en dépend. Vous pouvez également vous opposer à certains traitements fondés sur l’intérêt légitime, dans les limites prévues par la loi.</p>
            <p>Vous pouvez adresser une demande structurée via la page <a href="<?= htmlspecialchars(url('demande-donnees')) ?>" class="font-semibold text-emerald-700 hover:underline">Exercer vos droits</a> ; vous pouvez aussi écrire aux administrateurs de votre communauté ou à l’exploitant du service selon les coordonnées des mentions légales. Vous pouvez introduire une réclamation auprès de l’autorité de protection des données compétente (en France, la CNIL).</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">Transferts hors Union européenne</h2>
            <p>Si des transferts ont lieu vers des pays ne bénéficiant pas d’une décision d’adéquation, ils sont encadrés par les garanties appropriées prévues par le RGPD (clauses contractuelles types, mesures complémentaires le cas échéant).</p>
        </section>

    </div>

    <?php require base_path('views/partials/legal_crosslinks.php'); ?>
</div>
