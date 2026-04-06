<?php
declare(strict_types=1);
?>
<div class="max-w-3xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 tracking-tight uppercase mb-2">Conditions générales d’utilisation</h1>
    <p class="text-sm text-slate-500 mb-10">Règles applicables à l’usage du portail Athena et des espaces mis à disposition des communautés.</p>

    <div class="space-y-8 text-sm text-slate-600 leading-relaxed">
        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">1. Objet</h2>
            <p>Les présentes conditions générales d’utilisation (« CGU ») encadrent l’accès et l’usage du service en ligne Athena, incluant les fonctionnalités mises à disposition des utilisateurs et des organisations (communautés) hébergées sur la plateforme.</p>
            <p>En créant un compte ou en utilisant le service, vous acceptez sans réserve les présentes CGU, ainsi que les règles complémentaires affichées sur le site ou communiquées par votre communauté lorsqu’elles sont compatibles avec les présentes.</p>
            <p>Pour toute commande ou offre payante, les <a href="<?= htmlspecialchars(url('cgv')) ?>" class="font-semibold text-emerald-700 hover:underline">conditions générales de vente</a> s’appliquent également.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">2. Description synthétique du service</h2>
            <p>Athena propose des outils de pilotage communautaire à vocation ludique et pédagogique (personnel, documents, formations, forum, événements, etc.). Le périmètre exact dépend du paramétrage de chaque communauté et des droits qui vous sont attribués.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">3. Compte et sécurité</h2>
            <p>Vous vous engagez à fournir des informations exactes, à maintenir la confidentialité de vos identifiants et à signaler sans délai toute utilisation non autorisée de votre compte.</p>
            <p>L’éditeur peut suspendre ou clôturer un compte en cas de manquement grave aux présentes CGU, d’atteinte à la sécurité du service ou sur demande motivée des administrateurs de votre communauté, dans le respect de la réglementation.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">4. Règles de comportement et contenus</h2>
            <p>Vous restez responsable des contenus que vous publiez (textes, fichiers, messages). Il est notamment interdit de diffuser des contenus illicites, haineux, violents, à caractère discriminatoire, harcelants, ou portant atteinte aux droits de tiers.</p>
            <p>Les administrateurs de communauté peuvent modérer, retirer ou restreindre l’accès à certains contenus conformément à leurs propres règles internes, sans préjudice des mesures que l’éditeur peut prendre pour préserver le bon fonctionnement et la sécurité de la plateforme.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">5. Disponibilité et évolutions</h2>
            <p>Le service est fourni selon une obligation de moyens. Des interruptions (maintenance, mise à jour, cas de force majeure, défaillance de l’hébergeur ou des réseaux) peuvent survenir. L’éditeur s’efforce d’en limiter l’impact et peut faire évoluer les fonctionnalités pour des raisons techniques ou légales.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">6. Propriété intellectuelle</h2>
            <p>Les éléments fournis par Athena (marques, interfaces, documentation, bases de données éventuelles) restent la propriété de l’éditeur ou de ses partenaires. Les contenus que vous déposez restent les vôtres ; vous garantissez disposer des droits nécessaires à leur utilisation dans le service.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">7. Données personnelles</h2>
            <p>Le traitement des données personnelles est décrit dans la <a href="<?= htmlspecialchars(url('donnees-personnelles')) ?>" class="font-semibold text-emerald-700 hover:underline">page dédiée</a>. Vous pouvez exercer vos droits via le <a href="<?= htmlspecialchars(url('demande-donnees')) ?>" class="font-semibold text-emerald-700 hover:underline">formulaire prévu à cet effet</a> ou selon les modalités indiquées dans les mentions légales.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">8. Responsabilité</h2>
            <p>Dans les limites autorisées par la loi, la responsabilité de l’éditeur ne saurait être engagée pour les dommages indirects ou les pertes de données résultant d’un usage du service hors cadre, d’un tiers ou d’un cas de force majeure. Les litiges peuvent porter notamment sur la relation entre membres d’une même communauté : il appartient aux parties concernées de chercher une solution amiable, sans préjudice des voies de recours ordinaires.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">9. Droit applicable et litiges</h2>
            <p>Sauf disposition impérative contraire, les présentes CGU sont régies par le droit français. En cas de différend, les tribunaux compétents sont ceux prévus par les règles de compétence applicables après tentative de résolution amiable.</p>
            <p>Les présentes CGU peuvent être mises à jour ; la date de dernière mise à jour peut être précisée par l’éditeur sur cette page. La poursuite de l’usage du service vaut acceptation des CGU en vigueur.</p>
        </section>
    </div>

    <?php require base_path('views/partials/legal_crosslinks.php'); ?>
</div>
