<?php
declare(strict_types=1);

$brand = email_brand_name();
$updatedAt = '18 juillet 2026';
$contact = legal_public_contact_email();

$pubName = trim((string) env('APP_PUBLISHER_NAME', ''));
$pubAddr = trim((string) env('APP_PUBLISHER_ADDRESS', ''));
$pubForm = trim((string) env('APP_PUBLISHER_LEGAL_FORM', ''));
$pubVat = trim((string) env('APP_PUBLISHER_VAT_ID', ''));
$pubId = trim((string) env('APP_PUBLISHER_IDENTIFIER', ''));
$pubRcs = trim((string) env('APP_PUBLISHER_RCS', ''));
$pubDirector = trim((string) env('APP_PUBLISHER_PUBLICATION_DIRECTOR', ''));
$hostName = trim((string) env('APP_HOSTING_NAME', ''));
$hostAddr = trim((string) env('APP_HOSTING_ADDRESS', ''));
$hostPhone = trim((string) env('APP_HOSTING_PHONE', ''));
?>
<h1>Politique et conditions</h1>
<p class="legal-updated">Dernière mise à jour : <?= htmlspecialchars($updatedAt, ENT_QUOTES, 'UTF-8') ?></p>

<p>
    Cette page s’adresse à toutes les personnes qui utilisent <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?> —
    membres, administrateurs de communauté et visiteurs. Vous y trouverez les conditions d’utilisation du service,
    les règles applicables aux offres payantes, le traitement de vos données personnelles, les mentions légales
    et la gestion des cookies.
</p>

<div class="legal-callout legal-callout-tip">
    <strong>En résumé</strong> —
    <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?> est un portail de gestion communautaire pour le milsim et les organisations associées :
    compte personnel, espaces de communauté, formations, documents et coordination.
    Les offres payantes éventuelles sont encadrées par les conditions de vente ; vos données sont traitées selon la réglementation applicable.
</div>

<section id="presentation">
    <h2>Présentation du service</h2>
    <p>
        <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?> est un service en ligne qui permet à une communauté
        de centraliser le recrutement, la présence, les formations, la documentation et la coordination opérationnelle
        dans une interface claire et fiable.
    </p>
    <p>
        Chaque communauté dispose de ses propres espaces et règles internes. La plateforme fournit l’infrastructure technique ;
        les administrateurs de communauté restent responsables de l’organisation locale et des contenus qu’ils publient.
    </p>
    <?php if ($contact !== null): ?>
        <p>
            Pour toute question : <a href="mailto:<?= htmlspecialchars($contact, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($contact, ENT_QUOTES, 'UTF-8') ?></a>.
        </p>
    <?php endif; ?>
</section>

<section id="mentions">
    <h2>Mentions légales</h2>

    <h3>Éditeur du site</h3>
    <div class="legal-meta-grid">
        <div class="legal-meta-row">
            <span class="legal-meta-label">Dénomination</span>
            <span><?= $pubName !== '' ? htmlspecialchars($pubName, ENT_QUOTES, 'UTF-8') : '—' ?></span>
        </div>
        <?php if ($pubForm !== ''): ?>
        <div class="legal-meta-row">
            <span class="legal-meta-label">Forme juridique</span>
            <span><?= htmlspecialchars($pubForm, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <?php endif; ?>
        <div class="legal-meta-row">
            <span class="legal-meta-label">Adresse</span>
            <span><?= $pubAddr !== '' ? nl2br(htmlspecialchars($pubAddr, ENT_QUOTES, 'UTF-8')) : '—' ?></span>
        </div>
        <?php if ($pubId !== ''): ?>
        <div class="legal-meta-row">
            <span class="legal-meta-label">Identifiant d’entreprise</span>
            <span><?= htmlspecialchars($pubId, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <?php endif; ?>
        <?php if ($pubRcs !== ''): ?>
        <div class="legal-meta-row">
            <span class="legal-meta-label">Immatriculation</span>
            <span><?= htmlspecialchars($pubRcs, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <?php endif; ?>
        <?php if ($pubVat !== ''): ?>
        <div class="legal-meta-row">
            <span class="legal-meta-label">TVA intracommunautaire</span>
            <span><?= htmlspecialchars($pubVat, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <?php endif; ?>
    </div>

    <h3>Direction de la publication</h3>
    <p><?= $pubDirector !== '' ? htmlspecialchars($pubDirector, ENT_QUOTES, 'UTF-8') : '—' ?></p>

    <h3>Contact et support</h3>
    <?php if ($contact !== null): ?>
        <p>
            Pour toute question (fonctionnement du site, signalement, protection des données, exercice des droits) :
            <a href="mailto:<?= htmlspecialchars($contact, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($contact, ENT_QUOTES, 'UTF-8') ?></a>
        </p>
    <?php else: ?>
        <p>—</p>
    <?php endif; ?>

    <h3>Hébergement</h3>
    <div class="legal-meta-grid">
        <div class="legal-meta-row">
            <span class="legal-meta-label">Prestataire</span>
            <span><?= $hostName !== '' ? htmlspecialchars($hostName, ENT_QUOTES, 'UTF-8') : '—' ?></span>
        </div>
        <div class="legal-meta-row">
            <span class="legal-meta-label">Adresse</span>
            <span><?= $hostAddr !== '' ? nl2br(htmlspecialchars($hostAddr, ENT_QUOTES, 'UTF-8')) : '—' ?></span>
        </div>
        <?php if ($hostPhone !== ''): ?>
        <div class="legal-meta-row">
            <span class="legal-meta-label">Téléphone</span>
            <span><?= htmlspecialchars($hostPhone, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <?php endif; ?>
    </div>

    <h3>Statut de l’intermédiaire technique</h3>
    <p>
        Le portail peut héberger des contenus publiés par les utilisateurs et les communautés.
        L’éditeur agit en qualité d’hébergeur pour ces contenus au sens de la réglementation applicable
        et peut retirer ou rendre inaccessible tout contenu manifestement illicite après signalement ou détection.
    </p>

    <h3>Propriété intellectuelle</h3>
    <p>
        La structure du portail, ses interfaces, ses marques, textes, éléments graphiques, logos, icônes et bases de données
        (le cas échéant) sont protégés par les droits de propriété intellectuelle.
    </p>
    <p>
        Toute reproduction, extraction, réutilisation, représentation ou adaptation non autorisée est interdite,
        hors exceptions légales ou autorisation écrite préalable.
    </p>

    <h3>Responsabilité</h3>
    <p>
        L’éditeur met en œuvre des moyens raisonnables pour assurer la disponibilité, la sécurité et la fiabilité
        des informations publiées, sans garantir l’absence totale d’erreurs, d’indisponibilités ou d’altération liées à Internet.
    </p>
    <p>Vous restez responsable de l’usage que vous faites des informations et fonctionnalités proposées sur le portail.</p>

    <h3>Droit applicable</h3>
    <p>
        Sauf règles impératives contraires, le présent site et ses mentions légales sont soumis au droit français.
        En cas de litige, les juridictions compétentes sont déterminées selon les règles de procédure applicables.
    </p>
</section>

<section id="cgu">
    <h2>Conditions générales d’utilisation</h2>

    <h3>Objet et champ d’application</h3>
    <p>
        Les présentes conditions générales d’utilisation encadrent l’accès et l’usage du service en ligne
        <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?>, incluant les fonctionnalités mises à disposition des utilisateurs
        et des communautés hébergées sur la plateforme.
    </p>
    <p>
        En créant un compte, en vous connectant ou en utilisant le service, vous acceptez les présentes conditions
        et les règles complémentaires publiées sur le portail, sous réserve de leur compatibilité avec les présentes.
    </p>
    <p>Pour les offres payantes, les <a href="#cgv">conditions générales de vente</a> complètent ces conditions d’utilisation.</p>

    <h3>Description du service</h3>
    <p>
        <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?> fournit des outils de gestion communautaire, de formation,
        de documentation, de communication et de coordination opérationnelle à vocation ludique, pédagogique ou organisationnelle.
    </p>
    <p>Le périmètre des modules disponibles dépend de la configuration et des droits attribués dans votre communauté.</p>

    <h3>Compte utilisateur</h3>
    <p>Vous vous engagez à fournir des informations exactes, à mettre à jour vos données et à conserver la confidentialité de vos identifiants.</p>
    <p>
        Vous êtes responsable des actions réalisées depuis votre compte.
        En cas de suspicion d’accès frauduleux, vous devez immédiatement modifier votre mot de passe et informer le support.
    </p>

    <h3>Règles de conduite</h3>
    <p>
        Vous vous engagez à respecter les lois en vigueur et à ne pas publier de contenus illicites, diffamatoires,
        haineux, discriminatoires, violents, harcelants, frauduleux ou portant atteinte aux droits de tiers.
    </p>
    <p>
        Sont également interdits : l’usurpation d’identité, la tentative d’accès non autorisé, la perturbation du service,
        l’introduction de code malveillant et l’extraction massive de données sans autorisation.
    </p>

    <h3>Contenus utilisateurs et modération</h3>
    <p>
        Vous restez responsable des contenus que vous publiez (messages, pièces jointes, textes, médias).
        Vous garantissez disposer des droits nécessaires pour leur diffusion sur la plateforme.
    </p>
    <p>
        Les administrateurs de communauté et l’éditeur peuvent, selon leurs prérogatives, modérer, retirer ou restreindre
        l’accès à des contenus non conformes aux présentes conditions, à la loi, ou aux règles internes de la communauté.
    </p>

    <h3>Disponibilité et évolution</h3>
    <p>
        Le service est fourni avec une obligation de moyens. Des interruptions peuvent survenir
        (maintenance, incident technique, force majeure, évolution d’infrastructure, sécurité).
    </p>
    <p>L’éditeur peut faire évoluer, suspendre ou retirer certaines fonctionnalités pour des raisons techniques, légales ou de sécurité.</p>

    <h3>Propriété intellectuelle</h3>
    <p>
        Les éléments constitutifs du service (logiciels, architecture, interfaces, documentation, marques)
        restent la propriété de l’éditeur ou de ses partenaires.
    </p>
    <p>Aucune cession de droits n’est consentie par les présentes, hors droit d’usage strictement nécessaire à l’utilisation du service.</p>

    <h3>Suspension et résiliation</h3>
    <p>
        L’éditeur peut suspendre temporairement ou définitivement un compte en cas de manquement grave,
        de risque pour la sécurité, de fraude présumée ou sur demande légitime d’une autorité compétente.
    </p>
    <p>
        L’utilisateur peut demander la fermeture de son compte selon les procédures prévues par la plateforme,
        sous réserve des obligations légales de conservation.
    </p>

    <h3>Responsabilité</h3>
    <p>
        Dans les limites prévues par la loi, la responsabilité de l’éditeur ne saurait être engagée pour les dommages indirects,
        pertes de chance, pertes d’exploitation ou pertes de données résultant d’un usage non conforme du service,
        d’un fait de tiers ou d’un cas de force majeure.
    </p>

    <h3>Droit applicable et litiges</h3>
    <p>
        Sauf disposition impérative contraire, les présentes conditions sont régies par le droit français.
        En cas de litige, les parties recherchent d’abord une solution amiable avant toute action contentieuse.
    </p>

    <h3>Modification</h3>
    <p>
        Les conditions peuvent être mises à jour pour tenir compte d’évolutions techniques, légales ou fonctionnelles.
        La version en vigueur est publiée sur cette page avec sa date de mise à jour.
    </p>
    <p>La poursuite de l’utilisation du service après publication d’une nouvelle version vaut acceptation de celle-ci.</p>
</section>

<section id="cgv">
    <h2>Conditions générales de vente</h2>

    <h3>Champ d’application</h3>
    <p>
        Les présentes conditions générales de vente s’appliquent aux commandes conclues en ligne sur le portail
        pour des offres payantes proposées par l’éditeur du service
        (par exemple création ou montée en gamme d’une communauté, options facturées au fil de l’eau).
        Elles complètent les <a href="#cgu">conditions générales d’utilisation</a>.
    </p>
    <p>Les caractéristiques essentielles, le prix TTC et la durée de l’engagement sont présentés avant validation de la commande.</p>

    <h3>Commande et formation du contrat</h3>
    <p>
        La commande est formée après confirmation du paiement ou, le cas échéant, après acceptation expresse de la proposition affichée à l’écran.
        Un accusé de réception ou une confirmation peut vous être adressé sur l’adresse électronique associée à votre compte.
    </p>

    <h3>Prix et paiement</h3>
    <p>
        Les prix sont indiqués en euros toutes taxes comprises lorsque la TVA est due.
        Le paiement est réalisé via un prestataire de paiement sécurisé ; vous acceptez les conditions de ce prestataire
        dans la mesure où elles s’appliquent à la transaction.
    </p>
    <p>En cas de refus de paiement ou de fraude avérée, la commande peut être annulée et l’accès aux fonctionnalités concernées suspendu.</p>

    <h3>Fourniture du service</h3>
    <p>
        L’accès aux fonctionnalités payantes est ouvert après encaissement effectif ou selon les délais techniques habituels
        (quelques minutes dans la plupart des cas). En cas de difficulté persistante, vous pouvez contacter le support
        via les coordonnées publiées dans les <a href="#mentions">mentions légales</a>.
    </p>

    <h3>Droit de rétractation</h3>
    <p>
        Lorsque vous agissez en tant que consommateur au sens du Code de la consommation, vous disposez d’un délai de quatorze jours
        pour exercer votre droit de rétractation sans avoir à motiver votre décision, sauf exceptions légales
        (notamment lorsque l’exécution du service a commencé avec votre accord exprès avant la fin du délai
        et que vous avez reconnu perdre votre droit de rétractation).
    </p>
    <p>
        Pour exercer ce droit, adressez une demande claire aux coordonnées de contact de l’éditeur dans le délai imparti.
        Les remboursements, lorsqu’ils sont dus, interviennent selon les modalités prévues par la loi.
    </p>

    <h3>Réabonnements et résiliation</h3>
    <p>
        Lorsqu’une offre est périodique, les modalités de renouvellement, de résiliation et d’échéance sont rappelées
        au moment de la souscription et dans l’espace compte ou les communications associées.
        Une résiliation à l’échéance n’ouvre pas droit au remboursement de la période entamée,
        sauf disposition contraire affichée au moment de l’achat.
    </p>

    <h3>Garanties légales</h3>
    <p>
        Indépendamment des présentes conditions, vous bénéficiez des garanties légales
        (notamment conformité du bien ou du service numérique dans les conditions du Code de la consommation lorsque vous êtes consommateur).
    </p>

    <h3>Réclamations et médiation</h3>
    <p>
        Pour toute réclamation relative à une commande, adressez-vous en priorité au service indiqué dans les mentions légales.
        À défaut de solution amiable, vous pouvez recourir à une médiation de la consommation ou à toute autre voie prévue par la loi.
    </p>

    <h3>Droit applicable</h3>
    <p>
        Sauf disposition impérative plus favorable, les présentes conditions de vente sont régies par le droit français.
        Les litiges relèvent des tribunaux compétents selon les règles de droit commun.
    </p>
</section>

<section id="rgpd">
    <h2>Protection des données personnelles</h2>

    <h3>Responsable de traitement</h3>
    <p>
        Les traitements nécessaires à l’exploitation technique de la plateforme
        (compte, authentification, sécurité, administration, support) sont effectués par l’exploitant du service
        <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?>.
    </p>
    <p>
        Selon l’organisation mise en place, votre communauté peut agir en tant que responsable de traitement distinct
        ou conjoint pour certaines finalités locales (recrutement interne, suivi de formation, gestion des membres).
    </p>

    <h3>Catégories de données traitées</h3>
    <ul>
        <li>Données d’identification et de contact (adresse e-mail, identifiants de compte, pseudonyme, informations de profil).</li>
        <li>Données d’usage et de contribution (messages, pièces jointes, interactions, progression formation, participation aux événements).</li>
        <li>Données techniques et de sécurité (journaux applicatifs, informations de session, traces de connexion, indicateurs anti-abus).</li>
        <li>Données administratives ou de facturation lorsqu’une fonctionnalité payante est activée.</li>
    </ul>

    <h3>Finalités et bases légales</h3>
    <ul>
        <li><strong>Exécution du service</strong> — création et gestion du compte, accès aux espaces autorisés (exécution contractuelle).</li>
        <li><strong>Sécurité et continuité</strong> — détection des incidents, prévention de la fraude, journalisation (intérêt légitime et obligations légales).</li>
        <li><strong>Conformité réglementaire</strong> — réponse aux autorités, obligations comptables, preuve et défense en justice (obligation légale).</li>
        <li><strong>Fonctionnalités optionnelles</strong> — traitements activés sur consentement (par exemple certains traceurs non essentiels), révocable à tout moment.</li>
    </ul>

    <h3>Destinataires</h3>
    <p>
        Les données sont accessibles aux personnels habilités de l’exploitant, et aux administrateurs autorisés de votre communauté,
        dans la limite de leurs missions.
    </p>
    <p>
        Des sous-traitants techniques peuvent intervenir (hébergement, courrier électronique transactionnel, paiement).
        Ils agissent sur instruction et avec des obligations contractuelles de sécurité et de confidentialité.
    </p>

    <h3>Durées de conservation</h3>
    <p>
        Les données sont conservées pendant la durée nécessaire à la finalité du traitement, puis supprimées,
        anonymisées ou archivées de manière restreinte selon les obligations légales et les besoins de preuve.
    </p>

    <h3>Transferts hors Union européenne</h3>
    <p>
        Lorsque des données sont transférées hors EEE, ces transferts sont encadrés par des garanties appropriées
        prévues par la réglementation (décision d’adéquation, clauses contractuelles types, mesures complémentaires le cas échéant).
    </p>

    <h3>Sécurité</h3>
    <p>
        Le service met en œuvre des mesures techniques et organisationnelles adaptées :
        contrôle d’accès, chiffrement en transit lorsque disponible, journalisation, cloisonnement des accès et sauvegardes.
    </p>

    <h3>Vos droits</h3>
    <p>Vous pouvez exercer, selon votre situation et le cadre légal applicable, les droits suivants :</p>
    <ul>
        <li>droit d’accès à vos données et aux informations sur les traitements ;</li>
        <li>droit de rectification des données inexactes ou incomplètes ;</li>
        <li>droit à l’effacement dans les cas prévus par la loi ;</li>
        <li>droit à la limitation du traitement dans certaines situations ;</li>
        <li>droit d’opposition aux traitements fondés sur l’intérêt légitime ;</li>
        <li>droit à la portabilité des données fournies lorsque techniquement applicable ;</li>
        <li>droit de retirer votre consentement à tout moment pour les traitements qui en dépendent.</li>
    </ul>
    <p>
        Pour exercer ces droits, utilisez le formulaire
        <a href="<?= htmlspecialchars(url('demande-donnees'), ENT_QUOTES, 'UTF-8') ?>">Exercer vos droits</a>.
        Vous disposez également du droit d’introduire une réclamation auprès de la CNIL
        (<a href="https://www.cnil.fr" rel="noopener noreferrer" target="_blank">www.cnil.fr</a>).
    </p>
</section>

<section id="cookies">
    <h2>Cookies et traceurs</h2>

    <h3>Que recouvre cette rubrique ?</h3>
    <p>
        Cette rubrique décrit l’usage des cookies et mécanismes équivalents déposés ou lus sur votre terminal,
        ainsi que la façon dont nous mémorisons votre choix de consentement.
    </p>

    <h3>Cookies strictement nécessaires</h3>
    <p>
        Ces traceurs sont indispensables au fonctionnement de base : maintien de session, protection des formulaires,
        équilibrage technique, sécurité et mémorisation de préférences critiques.
    </p>
    <p>
        Ils sont utilisés sur la base de notre intérêt légitime à fournir un service sécurisé
        et ne nécessitent pas de consentement préalable au sens des règles applicables aux traceurs strictement nécessaires.
    </p>

    <h3>Catégories optionnelles</h3>
    <ul>
        <li><strong>Mesure d’audience</strong> — statistiques de fréquentation pour améliorer le service.</li>
        <li><strong>Personnalisation</strong> — adaptation de l’interface selon vos usages.</li>
        <li><strong>Publicité tierce</strong> — uniquement si cette brique est activée et si vous l’acceptez explicitement.</li>
    </ul>
    <p>Ces catégories restent désactivées tant que vous n’avez pas donné votre accord.</p>

    <h3>Gestion de vos choix</h3>
    <p>
        Vous pouvez accepter, refuser ou personnaliser vos préférences à tout moment depuis le bandeau
        ou le lien « Préférences cookies » présent en bas de page.
    </p>
    <p>Le choix est conservé sur cet appareil pendant une durée maximale de 180 jours, puis un nouveau recueil peut être demandé.</p>
    <p>
        <button type="button" data-cookie-preferences class="legal-btn">Modifier mes préférences</button>
    </p>

    <h3>Gestion côté navigateur</h3>
    <p>Vous pouvez bloquer, autoriser ou supprimer les cookies via votre navigateur. Ces réglages sont propres à chaque navigateur et appareil.</p>
    <p>En effaçant les données du site, le bandeau réapparaît et un nouveau choix est nécessaire.</p>
</section>

<div class="legal-callout legal-callout-tip" style="margin-top:2.5rem">
    <strong>Besoin d’agir ?</strong> —
    Pour une demande relative à vos données personnelles,
    <a href="<?= htmlspecialchars(url('demande-donnees'), ENT_QUOTES, 'UTF-8') ?>">utilisez le formulaire dédié</a>.
    Pour toute autre question, reportez-vous aux coordonnées des <a href="#mentions">mentions légales</a>.
</div>
