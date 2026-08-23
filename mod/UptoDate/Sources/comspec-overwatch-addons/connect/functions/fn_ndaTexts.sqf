/*
    Corps et libellés de la note bêta (FR / EN) au premier lancement.
    Param : "fr" | "en"
    Retour : [titleHtml, subtitleHtml, bodyHtml, acceptLabel, declineLabel, footerHtml, legalHtml]
*/
params [["_lang", "fr"]];

if (!(_lang isEqualType "")) then { _lang = "fr"; };
_lang = toLower _lang;
if (!(_lang in ["fr", "en"])) then { _lang = "fr"; };

if (_lang isEqualTo "en") exitWith {
    [
        "<t font='RobotoCondensedBold' size='1.05' align='left' color='#e8f4f0'>COMSPEC Overwatch</t><t size='0.58' color='#e8b84a'>  ·  PUBLIC BETA</t>",
        "<t align='left' size='0.62' color='#8aa0b4'>Welcome — this is a public beta. Please read this short note before continuing.</t>",
        "<t size='0.72' color='#c8d8e4'>" +
            "<t font='RobotoCondensedBold' color='#5a9e88'>1. Public beta</t><br/>" +
            "COMSPEC Overwatch (the Mod), including Athena and companion tools, is available publicly as a beta. Features may change, break, or be temporarily unavailable. Expect rough edges — that is normal at this stage.<br/><br/>" +
            "<t font='RobotoCondensedBold' color='#5a9e88'>2. Report bugs</t><br/>" +
            "Found something wrong? In game: Esc → COMSPEC Overwatch — mod manager → <t color='#e8f4f0'>Report a problem</t>. Describe what happened and when. Reports go to the team that maintains the pack.<br/><br/>" +
            "<t font='RobotoCondensedBold' color='#5a9e88'>3. Changelog &amp; updates</t><br/>" +
            "Follow new builds and release notes on the Steam Workshop page for COMSPEC Overwatch. Update the pack when a new version is published so you stay compatible with Athena and organised sessions.<br/><br/>" +
            "<t font='RobotoCondensedBold' color='#5a9e88'>4. Organised play</t><br/>" +
            "Use the Mod responsibly during organised milsim sessions. Do not abuse connectivity or tools in ways that harm other players, communities, or COMSPEC services.<br/><br/>" +
            "<t font='RobotoCondensedBold' color='#5a9e88'>5. Limited technical data</t><br/>" +
            "To operate the beta and related services, limited technical information may be recorded when you continue (for example Steam identity when available, player name, Mod version and related client details). This is not used for unrelated advertising.<br/><br/>" +
            "<t font='RobotoCondensedBold' color='#5a9e88'>6. Continue</t><br/>" +
            "By selecting Got it, you confirm you have read this note. Choosing Later closes the window without recording confirmation; it may appear again on a later launch." +
        "</t>",
        "Got it",
        "Later",
        "<t align='left' size='0.55' color='#7a8c9e'>Confirming records this note on your profile and registers beta participation with Athena when the connection is available.</t>",
        "<t align='center' size='0.48' color='#4a5c6e'>Public beta notice for a game mod — thank you for helping improve Overwatch.</t>"
    ]
};

[
    "<t font='RobotoCondensedBold' size='1.05' align='left' color='#e8f4f0'>COMSPEC Overwatch</t><t size='0.58' color='#e8b84a'>  ·  BÊTA PUBLIQUE</t>",
    "<t align='left' size='0.62' color='#8aa0b4'>Bienvenue — version publique en bêta. Merci de lire cette courte note avant de continuer.</t>",
    "<t size='0.72' color='#c8d8e4'>" +
        "<t font='RobotoCondensedBold' color='#5a9e88'>1. Bêta publique</t><br/>" +
        "COMSPEC Overwatch (le Mod), y compris Athena et les outils compagnons, est disponible publiquement en bêta. Des fonctions peuvent évoluer, dysfonctionner ou être temporairement indisponibles. Des aspérités sont normales à ce stade.<br/><br/>" +
        "<t font='RobotoCondensedBold' color='#5a9e88'>2. Signaler un problème</t><br/>" +
        "Un bug, un gel, un comportement bizarre ? En jeu : Échap → COMSPEC Overwatch — gestion du mod → <t color='#e8f4f0'>Signaler un problème</t>. Décrivez ce qui s’est passé. Les signalements arrivent à l’équipe qui suit le pack.<br/><br/>" +
        "<t font='RobotoCondensedBold' color='#5a9e88'>3. Nouveautés &amp; mises à jour</t><br/>" +
        "Suivez les nouvelles versions et le journal des changements sur la page Steam Workshop de COMSPEC Overwatch. Mettez le pack à jour quand une version sort, pour rester compatible avec Athena et les sessions organisées.<br/><br/>" +
        "<t font='RobotoCondensedBold' color='#5a9e88'>4. Sessions organisées</t><br/>" +
        "Utilisez le Mod de façon responsable lors des sessions milsim. N’abusez pas de la liaison ni des outils pour nuire à d’autres joueurs, communautés ou services COMSPEC.<br/><br/>" +
        "<t font='RobotoCondensedBold' color='#5a9e88'>5. Données techniques limitées</t><br/>" +
        "Pour faire tourner la bêta et les services liés, des informations techniques limitées peuvent être enregistrées lorsque vous continuez (par exemple l’identité Steam lorsqu’elle est disponible, le nom du joueur, la version du Mod et des détails clients associés). Elles ne servent pas à de la publicité non liée.<br/><br/>" +
        "<t font='RobotoCondensedBold' color='#5a9e88'>6. Continuer</t><br/>" +
        "En choisissant Compris, vous confirmez avoir lu cette note. Plus tard ferme la fenêtre sans enregistrer la confirmation ; l’avis pourra réapparaître lors d’un prochain lancement." +
    "</t>",
    "Compris",
    "Plus tard",
    "<t align='left' size='0.55' color='#7a8c9e'>La confirmation enregistre cette note sur ce profil et inscrit votre participation à la bêta auprès d’Athena lorsque la liaison est disponible.</t>",
    "<t align='center' size='0.48' color='#4a5c6e'>Note de bêta publique pour un mod de jeu — merci d’aider à améliorer Overwatch.</t>"
]
