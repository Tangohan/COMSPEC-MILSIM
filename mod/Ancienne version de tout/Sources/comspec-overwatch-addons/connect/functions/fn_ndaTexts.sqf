/*
    Corps et libellés NDA (FR / EN) pour l’écran d’accès anticipé.
    Param : "fr" | "en"
    Retour : [titleHtml, subtitleHtml, bodyHtml, acceptLabel, declineLabel, footerHtml, legalHtml]
*/
params [["_lang", "fr"]];

if (!(_lang isEqualType "")) then { _lang = "fr"; };
_lang = toLower _lang;
if (!(_lang in ["fr", "en"])) then { _lang = "fr"; };

if (_lang isEqualTo "en") exitWith {
    [
        "<t font='RobotoCondensedBold' size='1.05' align='left' color='#e8f4f0'>COMSPEC Overwatch</t><t size='0.58' color='#e8b84a'>  ·  BETA  ·  NDA</t>",
        "<t align='left' size='0.62' color='#8aa0b4'>Early-access confidentiality agreement — please read carefully before continuing.</t>",
        "<t size='0.72' color='#c8d8e4'>" +
            "<t font='RobotoCondensedBold' color='#5a9e88'>1. Purpose</t><br/>" +
            "COMSPEC Overwatch (the &quot;Mod&quot;), including related components such as Athena and any companion tools, is provided as early access / beta software for evaluation, testing and organised milsim use. By accepting, you enter a confidentiality commitment with COMSPEC regarding unreleased material.<br/><br/>" +
            "<t font='RobotoCondensedBold' color='#5a9e88'>2. Confidential information</t><br/>" +
            "You agree to treat as confidential: unreleased builds, internal tools, documentation not published for the general public, design of features still under development, and any non-public operational details shared in beta channels.<br/><br/>" +
            "<t font='RobotoCondensedBold' color='#5a9e88'>3. No redistribution</t><br/>" +
            "You must not redistribute, rehost, resell or publicly share beta builds, packages, keys or installers without prior written permission from COMSPEC. Sharing private download links outside authorised testers is prohibited.<br/><br/>" +
            "<t font='RobotoCondensedBold' color='#5a9e88'>4. Media and unreleased features</t><br/>" +
            "Screenshots, video, streams or posts that reveal unreleased or embargoed features require prior permission from COMSPEC. Public communication about publicly documented features remains allowed when it does not disclose confidential material.<br/><br/>" +
            "<t font='RobotoCondensedBold' color='#5a9e88'>5. No reverse engineering</t><br/>" +
            "You must not decompile, reverse-engineer, modify for redistribution, or attempt to extract secrets from the Mod’s native extension, protected assets or private services, except where mandatory law expressly permits it.<br/><br/>" +
            "<t font='RobotoCondensedBold' color='#5a9e88'>6. Feedback</t><br/>" +
            "Bug reports, suggestions and constructive feedback are welcome and encouraged. COMSPEC may use feedback freely to improve the product without obligation to you, unless a separate written agreement states otherwise.<br/><br/>" +
            "<t font='RobotoCondensedBold' color='#5a9e88'>7. Rights and ownership</t><br/>" +
            "All rights in the Mod, branding, Athena-related services and associated materials remain with COMSPEC and its licensors. Acceptance of this agreement does not transfer ownership or grant a commercial licence.<br/><br/>" +
            "<t font='RobotoCondensedBold' color='#5a9e88'>8. Access and termination</t><br/>" +
            "Early access may be limited, changed or withdrawn at any time. COMSPEC may suspend or terminate your beta access if these terms are breached or if testing needs change. Upon termination you should stop using confidential beta material and delete private builds when asked.<br/><br/>" +
            "<t font='RobotoCondensedBold' color='#5a9e88'>9. Data for access management</t><br/>" +
            "To manage early access, limited technical information may be recorded (for example Steam identity when available, player name, Mod version and related client details). This is used to operate the beta programme and related services, not for unrelated advertising.<br/><br/>" +
            "<t font='RobotoCondensedBold' color='#5a9e88'>10. Disclaimer</t><br/>" +
            "The Mod is provided &quot;as is&quot; for a game modification in beta. Features may change, break or be unavailable. To the fullest extent permitted by applicable law, COMSPEC disclaims liability for data loss, interrupted sessions, incompatibility with other mods, or indirect damages arising from beta use.<br/><br/>" +
            "<t font='RobotoCondensedBold' color='#5a9e88'>11. Conduct</t><br/>" +
            "Use the Mod responsibly during organised sessions. Do not abuse beta privileges to harm other players, communities or infrastructure connected to COMSPEC services.<br/><br/>" +
            "<t font='RobotoCondensedBold' color='#5a9e88'>12. Acceptance</t><br/>" +
            "By selecting Accept, you confirm that you have read this agreement, understand it, and agree to be bound by it for the duration of your early access. Declining closes this window without recording acceptance; the notice may appear again on a later launch." +
        "</t>",
        "I accept",
        "Decline",
        "<t align='left' size='0.55' color='#7a8c9e'>Accepting records your acknowledgement on this profile and registers early-access participation with Athena when the connection is available.</t>",
        "<t align='center' size='0.48' color='#4a5c6e'>Product agreement for a game mod beta — not a substitute for advice from a qualified lawyer.</t>"
    ]
};

[
    "<t font='RobotoCondensedBold' size='1.05' align='left' color='#e8f4f0'>COMSPEC Overwatch</t><t size='0.58' color='#e8b84a'>  ·  BÊTA  ·  NDA</t>",
    "<t align='left' size='0.62' color='#8aa0b4'>Accord de confidentialité — accès anticipé. Merci de lire attentivement avant de continuer.</t>",
    "<t size='0.72' color='#c8d8e4'>" +
        "<t font='RobotoCondensedBold' color='#5a9e88'>1. Objet</t><br/>" +
        "COMSPEC Overwatch (le « Mod »), y compris les composants associés tels qu’Athena et les outils compagnons, est fourni en accès anticipé / bêta pour évaluation, tests et usage milsim organisé. En acceptant, vous vous engagez à une obligation de confidentialité envers COMSPEC concernant le matériel non publié.<br/><br/>" +
        "<t font='RobotoCondensedBold' color='#5a9e88'>2. Informations confidentielles</t><br/>" +
        "Vous acceptez de traiter comme confidentiels : les builds non publiés, les outils internes, la documentation non destinée au public, la conception des fonctions encore en développement, et tout détail opérationnel non public communiqué dans les canaux bêta.<br/><br/>" +
        "<t font='RobotoCondensedBold' color='#5a9e88'>3. Interdiction de redistribution</t><br/>" +
        "Vous ne devez pas redistribuer, héberger, revendre ni partager publiquement les builds bêta, paquets, clés ou installateurs sans autorisation écrite préalable de COMSPEC. Diffuser des liens de téléchargement privés hors des testeurs autorisés est interdit.<br/><br/>" +
        "<t font='RobotoCondensedBold' color='#5a9e88'>4. Médias et fonctions non publiées</t><br/>" +
        "Captures d’écran, vidéos, streams ou publications révélant des fonctions non publiées ou sous embargo nécessitent l’autorisation préalable de COMSPEC. La communication publique sur des fonctions déjà documentées reste possible tant qu’elle ne divulgue pas de matériel confidentiel.<br/><br/>" +
        "<t font='RobotoCondensedBold' color='#5a9e88'>5. Pas de rétro-ingénierie</t><br/>" +
        "Vous ne devez pas décompiler, rétro-ingénierer, modifier en vue de redistribution, ni tenter d’extraire des secrets de l’extension native du Mod, des ressources protégées ou des services privés, sauf lorsque la loi applicable l’autorise expressément.<br/><br/>" +
        "<t font='RobotoCondensedBold' color='#5a9e88'>6. Retours d’expérience</t><br/>" +
        "Les signalements de bogues, suggestions et retours constructifs sont les bienvenus. COMSPEC peut utiliser librement ces retours pour améliorer le produit, sans obligation envers vous, sauf accord écrit distinct.<br/><br/>" +
        "<t font='RobotoCondensedBold' color='#5a9e88'>7. Droits et propriété</t><br/>" +
        "Tous les droits sur le Mod, la marque, les services liés à Athena et les matériels associés restent la propriété de COMSPEC et de ses concédants. L’acceptation de cet accord ne transfère aucune propriété ni licence commerciale.<br/><br/>" +
        "<t font='RobotoCondensedBold' color='#5a9e88'>8. Accès et résiliation</t><br/>" +
        "L’accès anticipé peut être limité, modifié ou retiré à tout moment. COMSPEC peut suspendre ou résilier votre accès bêta en cas de manquement à ces termes ou si les besoins de test évoluent. En cas de résiliation, vous devez cesser d’utiliser le matériel bêta confidentiel et supprimer les builds privés sur demande.<br/><br/>" +
        "<t font='RobotoCondensedBold' color='#5a9e88'>9. Données pour la gestion de l’accès</t><br/>" +
        "Pour gérer l’accès anticipé, des informations techniques limitées peuvent être enregistrées (par exemple l’identité Steam lorsqu’elle est disponible, le nom du joueur, la version du Mod et des détails clients associés). Elles servent au programme bêta et aux services liés, pas à de la publicité non liée.<br/><br/>" +
        "<t font='RobotoCondensedBold' color='#5a9e88'>10. Limitation de responsabilité</t><br/>" +
        "Le Mod est fourni « en l’état » pour une modification de jeu en bêta. Des fonctions peuvent changer, dysfonctionner ou être indisponibles. Dans toute la mesure permise par le droit applicable, COMSPEC décline sa responsabilité pour perte de données, sessions interrompues, incompatibilité avec d’autres mods, ou dommages indirects liés à l’usage bêta.<br/><br/>" +
        "<t font='RobotoCondensedBold' color='#5a9e88'>11. Conduite</t><br/>" +
        "Utilisez le Mod de façon responsable lors des sessions organisées. N’abusez pas des privilèges bêta pour nuire à d’autres joueurs, communautés ou infrastructures liées aux services COMSPEC.<br/><br/>" +
        "<t font='RobotoCondensedBold' color='#5a9e88'>12. Acceptation</t><br/>" +
        "En choisissant J’accepte, vous confirmez avoir lu cet accord, l’avoir compris, et y adhérer pour la durée de votre accès anticipé. Refuser ferme cette fenêtre sans enregistrer d’acceptation ; l’avis pourra réapparaître lors d’un prochain lancement." +
    "</t>",
    "J'accepte",
    "Refuser",
    "<t align='left' size='0.55' color='#7a8c9e'>L’acceptation enregistre votre accord sur ce profil et inscrit votre participation à l’accès anticipé auprès d’Athena lorsque la liaison est disponible.</t>",
    "<t align='center' size='0.48' color='#4a5c6e'>Accord produit pour une bêta de mod de jeu — ne remplace pas l’avis d’un professionnel du droit.</t>"
]
