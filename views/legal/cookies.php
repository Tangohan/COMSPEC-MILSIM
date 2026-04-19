<?php
declare(strict_types=1);

$updatedAt = '19 avril 2026';
?>
<div class="max-w-3xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 tracking-tight uppercase mb-2">Cookies et préférences</h1>
    <p class="text-sm text-slate-500 mb-2">Informations sur les traceurs utilisés par Athena et comment gérer vos choix.</p>
    <p class="text-xs text-slate-400 mb-10">Dernière mise à jour : <?= htmlspecialchars($updatedAt, ENT_QUOTES, 'UTF-8') ?></p>

    <div class="space-y-8 text-sm text-slate-600 leading-relaxed">
        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">Que recouvre cette page ?</h2>
            <p>Cette page décrit l’usage des cookies et mécanismes équivalents déposés ou lus sur votre terminal (ordinateur, mobile, tablette), ainsi que la façon dont nous mémorisons votre choix de consentement.</p>
            <p>La politique s’applique au portail public, aux écrans d’authentification et aux espaces connectés Athena accessibles via ce domaine.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">Cookies strictement nécessaires (toujours actifs)</h2>
            <p>Ces traceurs sont indispensables au fonctionnement de base : maintien de session, protection anti-CSRF des formulaires, équilibrage technique, sécurité et mémorisation de préférences critiques.</p>
            <p>Ils sont utilisés sur la base de notre intérêt légitime à fournir un service sécurisé et ne nécessitent pas de consentement préalable au sens des règles ePrivacy / RGPD applicables aux traceurs strictement nécessaires.</p>
            <p>Leur blocage au niveau navigateur peut empêcher la connexion ou certaines actions protégées.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">Catégories optionnelles soumises à consentement</h2>
            <ul class="list-disc pl-5 space-y-2">
                <li><span class="text-slate-800 font-semibold">Mesure d’audience</span> : statistiques de fréquentation (pages vues, temps de consultation, parcours) pour améliorer le service.</li>
                <li><span class="text-slate-800 font-semibold">Personnalisation</span> : adaptation de l’interface et des contenus selon vos usages.</li>
                <li><span class="text-slate-800 font-semibold">Publicité tierce</span> : prévu pour des campagnes partenaires, uniquement si cette brique est activée et si vous l’acceptez explicitement.</li>
            </ul>
            <p>Ces catégories restent désactivées tant que vous n’avez pas donné votre accord.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">Gestion de vos choix</h2>
            <p>Vous pouvez accepter, refuser ou personnaliser vos préférences à tout moment depuis le bandeau ou le lien <strong>Préférences cookies</strong> présent en pied de page.</p>
            <p>Le choix est conservé sur cet appareil pendant une durée maximale de 180 jours, puis un nouveau recueil peut être demandé.</p>
            <p>
                <button type="button" data-cookie-preferences="" class="inline-flex items-center justify-center px-5 py-2.5 rounded-xl bg-slate-900 text-white text-[10px] font-black uppercase tracking-widest hover:bg-emerald-700 transition-colors">
                    Modifier mes préférences
                </button>
            </p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">Durée de vie des traceurs</h2>
            <p>Les durées sont limitées au strict nécessaire selon la finalité (session, mesure d’audience, personnalisation). Lorsqu’un cookie arrive à expiration ou est supprimé, une nouvelle préférence peut vous être demandée.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">Tiers et paiement</h2>
            <p>Si votre parcours inclut un paiement ou des services tiers, ces prestataires peuvent déposer leurs propres traceurs sous leur responsabilité. Nous vous invitons à consulter leurs politiques au moment de l’usage.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">Gestion côté navigateur</h2>
            <p>Vous pouvez bloquer, autoriser ou supprimer les cookies via votre navigateur. Ces réglages sont propres à chaque navigateur et appareil.</p>
            <p>En effaçant les données du site, le bandeau réapparaît et un nouveau choix est nécessaire.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">Vos droits</h2>
            <p>Pour les données personnelles traitées via des traceurs, vous disposez des droits prévus par le RGPD (accès, rectification, effacement, opposition, limitation, portabilité selon les cas).</p>
            <p>Consultez la page <a href="<?= htmlspecialchars(url('donnees-personnelles')) ?>" class="font-semibold text-emerald-700 hover:underline">Données personnelles</a> ou utilisez le formulaire <a href="<?= htmlspecialchars(url('demande-donnees')) ?>" class="font-semibold text-emerald-700 hover:underline">Exercer vos droits</a>.</p>
        </section>
    </div>

    <?php require base_path('views/partials/legal_crosslinks.php'); ?>
</div>
