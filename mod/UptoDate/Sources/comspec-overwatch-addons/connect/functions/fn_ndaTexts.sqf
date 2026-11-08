/*
    Note de service du menu principal (FR / EN) : accès au mod, licence d'utilisation, données et bêta publique, en une seule note.
    Affichée au premier lancement et à chaque nouvelle version de la licence (fn_licenceStatus).
    La note a son propre choix FR / EN : ses textes ne passent pas par le dictionnaire i18n/en.txt.
    Texte juridique, à valider par TTRD à chaque changement de fond : tenir LICENSE (mod/COMSPEC_ATAK_Native, @COMSPEC_ATAK_Native,
    @COMSPEC_SSE) cohérent, et monter la version (fn_licenceStatus).
    La section « Données » décrit ce que le code envoie, preuves : fn_registerBetaClient, fn_reportAllyPosition, fn_reportDiag,
    fn_serverPresence, fn_startupCheck, fn_uplinkHold et fn_uplinkOff (rien ne part avec le service sur « Non »), LogWrite et device-logs dans COMSPECExtension.
    Le paragraphe « Votre choix » suit le second bouton réel (fn_ndaSetLanguage, fn_ndaDecline).
    Param : "fr" | "en"
    Retour : [titleHtml, subtitleHtml, bodyHtml, acceptLabel, declineLabel, footerHtml, legalHtml,
              closeLabel, quitTitle, quitText, quitButton, backButton,
              summaryHtml, acceptLockedLabel, progressFormat, readDoneText, alreadyAcceptedText]
*/
params [["_lang", "fr"]];

if (!(_lang isEqualType "")) then { _lang = "fr"; };
_lang = toLower _lang;
if (!(_lang in ["fr", "en"])) then { _lang = "fr"; };

([] call comspec_overwatch_connect_fnc_licenceStatus) params ["_licVer", "_licOk"];
private _inMission = !isNull findDisplay 46;
private _h = "<t font='RobotoCondensedBold' size='1.05' color='#5cc76b'>%1</t><br/>";
private _em = "<t color='#f2f6f3' font='RobotoCondensedBold'>%1</t>";
private _no = "<t color='#e5483a'>•</t> ";
private _dot = "<t color='#5cc76b'>•</t> ";

if (_lang isEqualTo "en") exitWith {
    private _choice = switch (true) do {
        case _licOk: { format [_em, "Close"] + ": closes this note and changes nothing." };
        case _inMission: { format [_em, "Close"] + ": closes this note and saves nothing. The note comes back the next time you return to the main menu." };
        default { format [_em, "Quit the game"] + ": asks you to confirm, then closes Arma 3. Nothing is saved and this note comes back on the next launch." };
    };
    [
        "<t font='RobotoCondensedBold' size='1' align='left' color='#f2f6f3'>Service note: access to the COMSPEC ATAK mod</t>",
        "<t align='left' size='0.68' color='#a9b8b1'>Please read this note to the end before using the mod. It covers access, the licence, data and the public beta.</t>",
        "<t size='0.85' color='#d8e2dc'>" +
            format [_h, "1. The mod"] +
            "COMSPEC ATAK is a tactical phone for Arma 3: shared map, markers, messages, photos and reports. It can be linked to Athena, your unit's platform (COMSPEC Overwatch). It is a public beta: features may change or not work as expected.<br/><br/>" +
            format [_h, "2. Access"] +
            "The mod is only available by subscribing to its official Steam Workshop page. Linked features (Athena, link with the command post) need a player account linked to your unit. Update the mod whenever a new version is released.<br/><br/>" +
            format [_h, "3. Licence: what is allowed"] +
            "All rights reserved. The mod (code, images, textures, sounds, DLL) belongs to COMSPEC. You may play with the mod for your personal use, installed from the official Steam Workshop.<br/><br/>" +
            format [_h, "4. Licence: what is not allowed"] +
            "Without written permission from COMSPEC:<br/>" +
            _no + "copying the mod, re-uploading it or sharing it elsewhere (another Workshop page, website, file sharing);<br/>" +
            _no + "repacking it, in whole or in part, in another pack or modpack;<br/>" +
            _no + "modifying it, decompiling it or extracting files to reuse its code, images, textures or DLL;<br/>" +
            _no + "any commercial use.<br/><br/>" +
            format [_h, "5. Community packs"] +
            "A pack that includes the mod needs written permission from COMSPEC. Without it, ask your players to subscribe to the official Workshop page. The full text is in LICENSE.txt, in the mod folder.<br/><br/>" +
            format [_h, "6. Data recorded by Athena"] +
            "Only if you answer Yes to the Athena service. If you answer No, your game stays local and sends nothing to Athena. This choice covers your game, not a server linked to Athena. Recorded to run the service, and not used for advertising:<br/>" +
            _dot + format [_em, "Account and technical:"] + " Steam identity when available, player name, callsign, IP address seen by the server, mod, DLL and Arma versions.<br/>" +
            _dot + format [_em, "During play:"] + " position, heading and role, playtime and screen time, synchronised equipment, server name and address, mission and map, and what you send from the phone (markers, messages, photos, reports).<br/>" +
            _dot + format [_em, "Diagnostics:"] + " mod log lines, error reports and the mod start-up check, sent automatically. Report a problem attaches an extract of the mod log unless you untick the box. The Arma RPT file is not sent.<br/><br/>" +
            format [_h, "7. Report a problem"] +
            "In game, ACE self-interaction menu &gt; " + format [_em, "Report a problem"] + ". Describe what happened and when.<br/><br/>" +
            format [_h, "8. Your choice"] +
            format [_em, "I accept"] + ": you accept this licence. The button unlocks once this note has been read to the end. Your choice is saved on this profile, once per licence version.<br/>" +
            _choice + "<br/>" +
            "Without your agreement, the mod must not be used. You can read this note again from the main menu banner or on the phone: Settings &gt; About phone &gt; READ THE LICENCE." +
        "</t>",
        "I accept",
        "Quit the game",
        "<t align='left' size='0.62' color='#a9b8b1'>Accepting saves this note on your profile and, if your unit uses Athena, registers your beta participation once the link is available.</t>",
        format ["<t align='center' size='0.55' color='#8b9a93'>COMSPEC ATAK, licence version %1. © COMSPEC, all rights reserved.</t>", _licVer],
        "Close",
        "Licence not accepted",
        "Without your agreement, the COMSPEC ATAK mod must not be used.<br/><br/>Quit the game now, then remove the mod in the Arma 3 launcher if you do not want to use it.",
        "Quit the game",
        "Back to the note",
        "<t size='0.74' color='#d8e2dc'>" + format ["<t font='RobotoCondensedBold' color='#5cc76b'>%1</t><br/>", "THE ESSENTIALS"] +
            _dot + "Personal use, installed from the official Steam Workshop.<br/>" +
            _no + "No copying, repacking, modifying or commercial use without written permission from COMSPEC.<br/>" +
            _dot + "With Athena, game and technical data are recorded (section 6). If you answer No, your game sends it nothing.<br/>" +
            _dot + "Without your agreement, the mod must not be used." +
        "</t>",
        "I accept (read to the end first)",
        "Reading: %1 %%. Scroll to the end to unlock I accept.",
        "Read to the end: you can accept.",
        "Licence already accepted for this version."
    ]
};

private _choice = switch (true) do {
    case _licOk: { format [_em, "Fermer"] + " : ferme cette note et ne change rien." };
    case _inMission: { format [_em, "Fermer"] + " : ferme cette note sans rien enregistrer. Elle reviendra au prochain retour au menu principal." };
    default { format [_em, "Quitter le jeu"] + " : demande une confirmation, puis ferme Arma 3. Rien n’est enregistré et cette note reviendra au prochain lancement." };
};
[
    "<t font='RobotoCondensedBold' size='1' align='left' color='#f2f6f3'>Note de service : accès au mod COMSPEC ATAK</t>",
    "<t align='left' size='0.68' color='#a9b8b1'>Merci de lire cette note jusqu’à la fin avant d’utiliser le mod. Elle porte sur l’accès, la licence, les données et la bêta publique.</t>",
    "<t size='0.85' color='#d8e2dc'>" +
        format [_h, "1. Le mod"] +
        "COMSPEC ATAK est un téléphone tactique pour Arma 3 : carte partagée, marqueurs, messagerie, photos et rapports. Il peut être relié à Athéna, la plateforme de votre unité (COMSPEC Overwatch). Il est en bêta publique : des fonctions peuvent changer ou mal fonctionner.<br/><br/>" +
        format [_h, "2. Conditions d’accès"] +
        "Le mod s’obtient uniquement par un abonnement à sa page officielle du Steam Workshop. Les fonctions reliées (Athéna, liaison avec le poste) demandent un compte joueur lié à votre unité. Mettez le mod à jour à chaque nouvelle version.<br/><br/>" +
        format [_h, "3. Licence : ce qui est autorisé"] +
        "Tous droits réservés. Le mod (code, images, textures, sons, DLL) appartient à COMSPEC. Vous pouvez jouer avec le mod pour votre usage personnel, installé depuis le Steam Workshop officiel.<br/><br/>" +
        format [_h, "4. Licence : ce qui est interdit"] +
        "Sans l’autorisation écrite de COMSPEC :<br/>" +
        _no + "copier le mod, le ré-héberger ou le redistribuer (autre page Workshop, site, partage de fichiers) ;<br/>" +
        _no + "le ré-empaqueter, en tout ou en partie, dans un autre pack ou un modpack ;<br/>" +
        _no + "le modifier, le décompiler ou en extraire des fichiers pour réutiliser son code, ses images, ses textures ou sa DLL ;<br/>" +
        _no + "tout usage commercial.<br/><br/>" +
        format [_h, "5. Packs de communauté"] +
        "Un pack qui inclut le mod doit avoir l’autorisation écrite de COMSPEC. Sans elle, demandez à vos joueurs de s’abonner à la page officielle du Workshop. Le texte complet est dans LICENSE.txt, dans le dossier du mod.<br/><br/>" +
        format [_h, "6. Données enregistrées par Athéna"] +
        "Uniquement si vous répondez Oui au service Athéna. Si vous répondez Non, votre jeu reste en local et ne transmet rien à Athéna. Ce choix vaut pour votre jeu, pas pour un serveur relié à Athéna. Enregistré pour faire fonctionner le service, et sans usage publicitaire :<br/>" +
        _dot + format [_em, "Compte et technique :"] + " identité Steam si disponible, nom du joueur, indicatif, adresse IP vue par le serveur, versions du mod, de la DLL et d’Arma.<br/>" +
        _dot + format [_em, "Pendant la partie :"] + " position, cap et rôle, temps de jeu et temps d’écran, équipement synchronisé, nom et adresse du serveur, mission et carte, et ce que vous envoyez depuis le téléphone (marqueurs, messages, photos, rapports).<br/>" +
        _dot + format [_em, "Diagnostic :"] + " lignes du journal du mod, rapports d’erreur et contrôle de démarrage du mod, envoyés automatiquement. Signaler un problème joint un extrait du journal du mod, sauf si vous décochez la case. Le fichier RPT d’Arma n’est pas envoyé.<br/><br/>" +
        format [_h, "7. Signaler un problème"] +
        "En jeu, menu d’interaction ACE &gt; " + format [_em, "Signaler un problème"] + ". Décrivez ce qui s’est passé et quand.<br/><br/>" +
        format [_h, "8. Votre choix"] +
        format [_em, "J’accepte"] + " : vous acceptez cette licence. Le bouton s’active une fois cette note lue jusqu’à la fin. Votre choix est enregistré sur ce profil, une fois par version de la licence.<br/>" +
        _choice + "<br/>" +
        "Sans votre accord, le mod ne doit pas être utilisé. Vous pouvez relire cette note depuis le bandeau du menu principal ou sur le téléphone : Réglages &gt; À propos du téléphone &gt; RELIRE LA LICENCE." +
    "</t>",
    "J’accepte",
    "Quitter le jeu",
    "<t align='left' size='0.62' color='#a9b8b1'>L’acceptation enregistre cette note sur ce profil et, si votre unité utilise Athéna, inscrit votre participation à la bêta dès que la liaison est disponible.</t>",
    format ["<t align='center' size='0.55' color='#8b9a93'>COMSPEC ATAK, licence version %1. © COMSPEC, tous droits réservés.</t>", _licVer],
    "Fermer",
    "Licence non acceptée",
    "Sans votre accord, le mod COMSPEC ATAK ne doit pas être utilisé.<br/><br/>Quittez le jeu maintenant, puis retirez le mod dans le lanceur Arma 3 si vous ne souhaitez pas l’utiliser.",
    "Quitter le jeu",
    "Revenir à la note",
    "<t size='0.74' color='#d8e2dc'>" + format ["<t font='RobotoCondensedBold' color='#5cc76b'>%1</t><br/>", "L’ESSENTIEL"] +
        _dot + "Usage personnel, installé depuis le Steam Workshop officiel.<br/>" +
        _no + "Ni copie, ni ré-empaquetage, ni modification, ni usage commercial sans l’autorisation écrite de COMSPEC.<br/>" +
        _dot + "Avec Athéna, des données de jeu et techniques sont enregistrées (section 6). Si vous répondez Non, votre jeu ne lui transmet rien.<br/>" +
        _dot + "Sans votre accord, le mod ne doit pas être utilisé." +
    "</t>",
    "J’accepte (lisez d’abord jusqu’à la fin)",
    "Lecture : %1 %%. Faites défiler jusqu’à la fin pour activer J’accepte.",
    "Lu jusqu’à la fin : vous pouvez accepter.",
    "Licence déjà acceptée pour cette version."
]
