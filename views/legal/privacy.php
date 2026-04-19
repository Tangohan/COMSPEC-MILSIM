<?php
declare(strict_types=1);
$updatedAt = '19 avril 2026';
?>
<div class="max-w-3xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 tracking-tight uppercase mb-2">Données personnelles (RGPD)</h1>
    <p class="text-sm text-slate-500 mb-2">Comment Athena collecte, utilise et protège les données liées à votre compte et à votre activité.</p>
    <p class="text-xs text-slate-400 mb-10">Dernière mise à jour : <?= htmlspecialchars($updatedAt, ENT_QUOTES, 'UTF-8') ?></p>

    <div class="space-y-8 text-sm text-slate-600 leading-relaxed">
        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">1. Responsable de traitement</h2>
            <p>Les traitements nécessaires à l’exploitation technique de la plateforme (compte, authentification, sécurité, administration, support) sont effectués par l’exploitant du service Athena.</p>
            <p>Selon l’organisation mise en place, votre communauté peut agir en tant que responsable de traitement distinct ou conjoint pour certaines finalités locales (recrutement interne, suivi de formation, gestion des membres).</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">2. Catégories de données traitées</h2>
            <ul class="list-disc pl-5 space-y-2">
                <li>Données d’identification et de contact (email, identifiants de compte, pseudonyme, informations de profil).</li>
                <li>Données d’usage et de contribution (messages, pièces jointes, interactions, progression formation, participation événements).</li>
                <li>Données techniques et de sécurité (logs applicatifs, informations de session, traces de connexion, indicateurs anti-abus).</li>
                <li>Données administratives ou de facturation lorsqu’une fonctionnalité payante est activée.</li>
            </ul>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">3. Finalités et bases légales</h2>
            <ul class="list-disc pl-5 space-y-2">
                <li><span class="text-slate-800 font-semibold">Exécution du service</span> : création et gestion du compte, accès aux espaces autorisés, exécution des fonctionnalités demandées (base légale : exécution contractuelle).</li>
                <li><span class="text-slate-800 font-semibold">Sécurité et continuité</span> : détection des incidents, prévention de la fraude, journalisation et supervision (base légale : intérêt légitime et obligations légales).</li>
                <li><span class="text-slate-800 font-semibold">Conformité réglementaire</span> : réponse aux demandes d’autorités, obligations comptables, preuve et défense en justice (base légale : obligation légale).</li>
                <li><span class="text-slate-800 font-semibold">Fonctionnalités optionnelles</span> : traitements activés sur consentement (ex. certains traceurs non essentiels), révocable à tout moment.</li>
            </ul>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">4. Destinataires des données</h2>
            <p>Les données sont accessibles aux personnels habilités de l’exploitant, et aux administrateurs autorisés de votre communauté, dans la limite de leurs missions.</p>
            <p>Des sous-traitants techniques peuvent intervenir (hébergement, e-mail transactionnel, paiement). Ils agissent sur instruction et avec des obligations contractuelles de sécurité et de confidentialité.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">5. Durées de conservation</h2>
            <p>Les données sont conservées pendant la durée nécessaire à la finalité du traitement, puis supprimées, anonymisées ou archivées de manière restreinte selon les obligations légales et les besoins de preuve.</p>
            <p>Les journaux de sécurité peuvent être conservés plus longtemps lorsque cela est justifié pour la détection d’abus, la réponse à incident ou la défense de droits.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">6. Transferts hors Union européenne</h2>
            <p>Lorsque des données sont transférées hors EEE, ces transferts sont encadrés par des garanties appropriées prévues par le RGPD (décision d’adéquation, clauses contractuelles types, mesures complémentaires le cas échéant).</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">7. Sécurité</h2>
            <p>Le service met en œuvre des mesures techniques et organisationnelles adaptées : contrôle d’accès, chiffrement en transit lorsque disponible, journalisation, cloisonnement des accès et sauvegardes.</p>
            <p>Aucune mesure n’offrant une sécurité absolue, nous réévaluons régulièrement les protections en fonction des risques.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">8. Vos droits RGPD</h2>
            <p>Vous pouvez exercer, selon votre situation et le cadre légal applicable, les droits suivants :</p>
            <ul class="list-disc pl-5 space-y-2">
                <li>Droit d’accès à vos données et aux informations sur les traitements.</li>
                <li>Droit de rectification des données inexactes ou incomplètes.</li>
                <li>Droit à l’effacement (« droit à l’oubli ») dans les cas prévus par la loi.</li>
                <li>Droit à la limitation du traitement dans certaines situations.</li>
                <li>Droit d’opposition aux traitements fondés sur l’intérêt légitime.</li>
                <li>Droit à la portabilité des données fournies lorsque techniquement applicable.</li>
                <li>Droit de retirer votre consentement à tout moment pour les traitements qui en dépendent.</li>
                <li>Droit de définir des directives relatives au sort de vos données après décès (droit français).</li>
            </ul>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">9. Exercer vos droits</h2>
            <p>Vous pouvez déposer une demande via la page <a href="<?= htmlspecialchars(url('demande-donnees')) ?>" class="font-semibold text-emerald-700 hover:underline">Exercer vos droits</a> en précisant l’objet de votre demande.</p>
            <p>En cas de doute raisonnable sur l’identité du demandeur, des informations complémentaires peuvent être demandées afin de sécuriser la réponse.</p>
            <p>Vous pouvez également contacter les administrateurs de votre communauté quand la demande concerne uniquement leur périmètre de gestion.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">10. Réclamation auprès d’une autorité</h2>
            <p>Si vous estimez que vos droits ne sont pas respectés, vous pouvez déposer une réclamation auprès de l’autorité de contrôle compétente (en France : CNIL).</p>
        </section>
    </div>

    <?php require base_path('views/partials/legal_crosslinks.php'); ?>
</div>
