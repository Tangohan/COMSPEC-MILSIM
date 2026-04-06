<?php
declare(strict_types=1);
?>
<div class="max-w-3xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 tracking-tight uppercase mb-2">Conditions générales de vente</h1>
    <p class="text-sm text-slate-500 mb-10">Règles applicables aux offres payantes proposées sur la plateforme (abonnements ou prestations associées au service Athena).</p>

    <div class="space-y-8 text-sm text-slate-600 leading-relaxed">
        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">1. Champ d’application</h2>
            <p>Les présentes conditions générales de vente (« CGV ») s’appliquent aux commandes conclues en ligne sur le portail Athena pour des offres payantes proposées par l’éditeur du service (par exemple création ou montée en gamme d’une communauté, options facturées au fil de l’eau). Elles complètent les <a href="<?= htmlspecialchars(url('cgu')) ?>" class="font-semibold text-emerald-700 hover:underline">conditions générales d’utilisation</a>.</p>
            <p>Les caractéristiques essentielles, le prix TTC et la durée de l’engagement sont présentés avant validation de la commande.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">2. Commande et formation du contrat</h2>
            <p>La commande est formée après confirmation du paiement ou, le cas échéant, après acceptation expresse de la proposition affichée à l’écran. Un accusé de réception ou une confirmation peut vous être adressé sur l’adresse électronique associée à votre compte.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">3. Prix et paiement</h2>
            <p>Les prix sont indiqués en euros toutes taxes comprises lorsque la TVA est due. Le paiement est réalisé via un prestataire de paiement sécurisé ; vous acceptez les conditions de ce prestataire dans la mesure où elles s’appliquent à la transaction.</p>
            <p>En cas de refus de paiement ou de fraude avérée, la commande peut être annulée et l’accès aux fonctionnalités concernées suspendu.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">4. Fourniture du service</h2>
            <p>L’accès aux fonctionnalités payantes est ouvert après encaissement effectif ou selon les délais techniques habituels (quelques minutes dans la plupart des cas). En cas de difficulté persistante, vous pouvez contacter le support via les coordonnées publiées dans les mentions légales.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">5. Droit de rétractation</h2>
            <p>Lorsque vous agissez en tant que consommateur au sens du Code de la consommation, vous disposez d’un délai de quatorze jours pour exercer votre droit de rétractation sans avoir à motiver votre décision, sauf exceptions légales (notamment lorsque l’exécution du service a commencé avec votre accord exprès avant la fin du délai et que vous avez reconnu perdre votre droit de rétractation).</p>
            <p>Pour exercer ce droit, adressez une demande claire aux coordonnées de contact de l’éditeur (courriel ou courrier) dans le délai imparti. Les remboursements, lorsqu’ils sont dus, interviennent selon les modalités prévues par la loi.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">6. Réabonnements et résiliation</h2>
            <p>Lorsqu’une offre est périodique, les modalités de renouvellement, de résiliation et d’échéance sont rappelées au moment de la souscription et dans l’espace compte ou les communications associées. Une résiliation à l’échéance n’ouvre pas droit au remboursement de la période entamée, sauf disposition contraire affichée au moment de l’achat.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">7. Garanties légales</h2>
            <p>Indépendamment des présentes CGV, vous bénéficiez des garanties légales (notamment conformité du bien ou du service numérique dans les conditions du Code de la consommation lorsque vous êtes consommateur).</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">8. Réclamations et médiation</h2>
            <p>Pour toute réclamation relative à une commande, adressez-vous en priorité au service indiqué dans les mentions légales. À défaut de solution amiable, vous pouvez recourir à une médiation de la consommation ou à toute autre voie prévue par la loi.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">9. Données personnelles</h2>
            <p>Les données collectées dans le cadre de la vente sont traitées conformément à la <a href="<?= htmlspecialchars(url('donnees-personnelles')) ?>" class="font-semibold text-emerald-700 hover:underline">politique données personnelles</a>.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">10. Droit applicable</h2>
            <p>Sauf disposition impérative plus favorable, les présentes CGV sont régies par le droit français. Les litiges relèvent des tribunaux compétents selon les règles de droit commun.</p>
        </section>
    </div>

    <?php require base_path('views/partials/legal_crosslinks.php'); ?>
</div>
