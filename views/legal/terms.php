<?php
declare(strict_types=1);
$updatedAt = '19 avril 2026';
?>
<div class="max-w-3xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 tracking-tight uppercase mb-2">Conditions générales d’utilisation</h1>
    <p class="text-sm text-slate-500 mb-2">Règles applicables à l’usage du portail Athena et des espaces mis à disposition des communautés.</p>
    <p class="text-xs text-slate-400 mb-10">Dernière mise à jour : <?= htmlspecialchars($updatedAt, ENT_QUOTES, 'UTF-8') ?></p>

    <div class="space-y-8 text-sm text-slate-600 leading-relaxed">
        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">1. Objet et champ d’application</h2>
            <p>Les présentes conditions générales d’utilisation (« CGU ») encadrent l’accès et l’usage du service en ligne Athena, incluant les fonctionnalités mises à disposition des utilisateurs et des communautés hébergées sur la plateforme.</p>
            <p>En créant un compte, en vous connectant ou en utilisant le service, vous acceptez les présentes CGU et les règles complémentaires publiées sur le portail, sous réserve de leur compatibilité avec les présentes.</p>
            <p>Pour les offres payantes, les <a href="<?= htmlspecialchars(url('cgv')) ?>" class="font-semibold text-emerald-700 hover:underline">CGV</a> complètent ces CGU.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">2. Description du service</h2>
            <p>Athena fournit des outils de gestion communautaire, de formation, de documentation, de communication et de coordination opérationnelle à vocation ludique, pédagogique ou organisationnelle.</p>
            <p>Le périmètre des modules disponibles dépend de la configuration technique et des droits attribués dans votre communauté.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">3. Conditions d’accès et compte utilisateur</h2>
            <p>Vous vous engagez à fournir des informations exactes, à mettre à jour vos données et à conserver la confidentialité de vos identifiants.</p>
            <p>Vous êtes responsable des actions réalisées depuis votre compte. En cas de suspicion d’accès frauduleux, vous devez immédiatement modifier votre mot de passe et informer le support.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">4. Règles de conduite</h2>
            <p>Vous vous engagez à respecter les lois en vigueur et à ne pas publier de contenus illicites, diffamatoires, haineux, discriminatoires, violents, harcelants, frauduleux ou portant atteinte aux droits de tiers.</p>
            <p>Sont également interdits : l’usurpation d’identité, la tentative d’accès non autorisé, la perturbation du service, l’introduction de code malveillant et l’extraction massive de données sans autorisation.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">5. Contenus utilisateurs et modération</h2>
            <p>Vous restez responsable des contenus que vous publiez (messages, pièces jointes, textes, médias). Vous garantissez disposer des droits nécessaires pour leur diffusion sur la plateforme.</p>
            <p>Les administrateurs de communauté et l’éditeur peuvent, selon leurs prérogatives, modérer, retirer ou restreindre l’accès à des contenus non conformes aux présentes CGU, à la loi, ou aux règles internes de la communauté.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">6. Disponibilité, maintenance et évolution</h2>
            <p>Le service est fourni avec une obligation de moyens. Des interruptions peuvent survenir (maintenance, incident technique, force majeure, évolution d’infrastructure, sécurité).</p>
            <p>L’éditeur peut faire évoluer, suspendre ou retirer certaines fonctionnalités pour des raisons techniques, légales ou de sécurité.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">7. Propriété intellectuelle</h2>
            <p>Les éléments constitutifs du service Athena (logiciels, architecture, interfaces, documentation, marques, bases de données) restent la propriété de l’éditeur ou de ses partenaires.</p>
            <p>Aucune cession de droits n’est consentie par les présentes, hors droit d’usage strictement nécessaire à l’utilisation du service.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">8. Données personnelles et cookies</h2>
            <p>Les traitements de données personnelles sont décrits dans la page <a href="<?= htmlspecialchars(url('donnees-personnelles')) ?>" class="font-semibold text-emerald-700 hover:underline">Données personnelles</a>.</p>
            <p>La gestion des traceurs est détaillée dans la page <a href="<?= htmlspecialchars(url('cookies')) ?>" class="font-semibold text-emerald-700 hover:underline">Cookies</a>. Vous pouvez exercer vos droits via la page <a href="<?= htmlspecialchars(url('demande-donnees')) ?>" class="font-semibold text-emerald-700 hover:underline">Exercer vos droits</a>.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">9. Suspension et résiliation</h2>
            <p>L’éditeur peut suspendre temporairement ou définitivement un compte en cas de manquement grave aux CGU, de risque pour la sécurité, de fraude présumée ou sur demande légitime d’une autorité compétente.</p>
            <p>L’utilisateur peut demander la fermeture de son compte selon les procédures prévues par la plateforme, sous réserve des obligations légales de conservation.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">10. Responsabilité</h2>
            <p>Dans les limites prévues par la loi, la responsabilité de l’éditeur ne saurait être engagée pour les dommages indirects, pertes de chance, pertes d’exploitation ou pertes de données résultant d’un usage non conforme du service, d’un fait de tiers ou d’un cas de force majeure.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">11. Droit applicable et règlement des litiges</h2>
            <p>Sauf disposition impérative contraire, les CGU sont régies par le droit français. En cas de litige, les parties recherchent d’abord une solution amiable avant toute action contentieuse.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">12. Modification des CGU</h2>
            <p>Les CGU peuvent être mises à jour pour tenir compte d’évolutions techniques, légales ou fonctionnelles. La version en vigueur est publiée sur cette page avec sa date de mise à jour.</p>
            <p>La poursuite de l’utilisation du service après publication d’une nouvelle version vaut acceptation de celle-ci.</p>
        </section>
    </div>

    <?php require base_path('views/partials/legal_crosslinks.php'); ?>
</div>
