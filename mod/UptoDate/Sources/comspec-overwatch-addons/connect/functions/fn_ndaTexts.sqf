/*
    Note de service du menu principal (FR / EN) : accès au mod, licence d'utilisation et bêta publique, en une seule note.
    Affichée au premier lancement et à chaque nouvelle version de la licence (fn_licenceStatus).
    La note a son propre choix FR / EN : ses textes ne passent pas par le dictionnaire i18n/en.txt.
    Param : "fr" | "en"
    Retour : [titleHtml, subtitleHtml, bodyHtml, acceptLabel, declineLabel, footerHtml, legalHtml,
              closeLabel, quitTitle, quitText, quitButton, backButton]
*/
params [["_lang", "fr"]];

if (!(_lang isEqualType "")) then { _lang = "fr"; };
_lang = toLower _lang;
if (!(_lang in ["fr", "en"])) then { _lang = "fr"; };

private _licVer = ([] call comspec_overwatch_connect_fnc_licenceStatus) select 0;
private _h = "<t font='RobotoCondensedBold' color='#5a9e88'>%1</t><br/>";
private _no = "<t color='#e5483a'>•</t> ";

if (_lang isEqualTo "en") exitWith {
    [
        "<t font='RobotoCondensedBold' size='0.9' align='left' color='#e8f4f0'>Service note — Access to the COMSPEC ATAK mod</t>",
        "<t align='left' size='0.62' color='#8aa0b4'>Please read this note before using the mod. It covers access, the licence and the public beta.</t>",
        "<t size='0.72' color='#c8d8e4'>" +
            format [_h, "1. The mod"] +
            "COMSPEC ATAK is a tactical phone for Arma 3: shared map, markers, messages, photos and reports. It can be linked to Athena, your unit's platform (COMSPEC Overwatch). It is a public beta: features may change or not work as expected.<br/><br/>" +
            format [_h, "2. Access"] +
            "The mod is only available by subscribing to its official Steam Workshop page. Linked features (Athena, link with the command post) need a player account linked to your unit. Update the mod whenever a new version is released.<br/><br/>" +
            format [_h, "3. Licence — all rights reserved"] +
            "The mod (code, images, textures, sounds, DLL) belongs to COMSPEC. <t color='#e8f4f0'>Allowed:</t> playing with the mod for your personal use, installed from the official Steam Workshop.<br/>" +
            "<t color='#e8f4f0'>Not allowed</t> without written permission from COMSPEC:<br/>" +
            _no + "copying the mod, re-uploading it or sharing it elsewhere (another Workshop page, website, file sharing);<br/>" +
            _no + "repacking it, in whole or in part, in another pack or modpack;<br/>" +
            _no + "modifying it, decompiling it or extracting files to reuse its code, images, textures or DLL;<br/>" +
            _no + "any commercial use.<br/>" +
            "Community packs: a pack that includes the mod needs written permission from COMSPEC. Without it, ask your players to subscribe to the official Workshop page. The full text is in LICENSE.txt, in the mod folder.<br/><br/>" +
            format [_h, "4. Report a problem"] +
            "In game, ACE self-interaction menu → <t color='#e8f4f0'>Report a problem</t>. Describe what happened and when.<br/><br/>" +
            format [_h, "5. Limited technical data"] +
            "If your unit uses the Athena service, limited technical information is recorded to run it (Steam identity when available, player name, mod version). It is not used for advertising.<br/><br/>" +
            format [_h, "6. Your choice"] +
            "<t color='#e8f4f0'>I accept</t>: you accept this licence; your choice is saved on this profile. <t color='#e8f4f0'>Quit the game</t>: without your agreement the mod cannot be used. You can read this note again from the main menu banner or on the phone: Settings → About phone → READ THE LICENCE." +
        "</t>",
        "I accept",
        "Quit the game",
        "<t align='left' size='0.55' color='#7a8c9e'>Accepting saves this note on your profile and, if your unit uses Athena, registers your beta participation once the link is available.</t>",
        format ["<t align='center' size='0.48' color='#4a5c6e'>COMSPEC ATAK — licence version %1 — © COMSPEC, all rights reserved.</t>", _licVer],
        "Close",
        "Licence not accepted",
        "Without your agreement, the COMSPEC ATAK mod cannot be used.<br/><br/>Quit the game now, then remove the mod in the Arma 3 launcher if you do not want to use it.",
        "Quit the game",
        "Back to the note"
    ]
};

[
    "<t font='RobotoCondensedBold' size='0.9' align='left' color='#e8f4f0'>Note de service — Accès au mod COMSPEC ATAK</t>",
    "<t align='left' size='0.62' color='#8aa0b4'>Merci de lire cette note avant d’utiliser le mod. Elle porte sur l’accès, la licence et la bêta publique.</t>",
    "<t size='0.72' color='#c8d8e4'>" +
        format [_h, "1. Le mod"] +
        "COMSPEC ATAK est un téléphone tactique pour Arma 3 : carte partagée, marqueurs, messagerie, photos et rapports. Il peut être relié à Athéna, la plateforme de votre unité (COMSPEC Overwatch). Il est en bêta publique : des fonctions peuvent changer ou mal fonctionner.<br/><br/>" +
        format [_h, "2. Conditions d’accès"] +
        "Le mod s’obtient uniquement par un abonnement à sa page officielle du Steam Workshop. Les fonctions reliées (Athéna, liaison avec le poste) demandent un compte joueur lié à votre unité. Mettez le mod à jour à chaque nouvelle version.<br/><br/>" +
        format [_h, "3. Licence — tous droits réservés"] +
        "Le mod (code, images, textures, sons, DLL) appartient à COMSPEC. <t color='#e8f4f0'>Autorisé :</t> jouer avec le mod pour votre usage personnel, installé depuis le Steam Workshop officiel.<br/>" +
        "<t color='#e8f4f0'>Interdit</t> sans l’autorisation écrite de COMSPEC :<br/>" +
        _no + "copier le mod, le ré-héberger ou le redistribuer (autre page Workshop, site, partage de fichiers) ;<br/>" +
        _no + "le ré-empaqueter, en tout ou en partie, dans un autre pack ou un modpack ;<br/>" +
        _no + "le modifier, le décompiler ou en extraire des fichiers pour réutiliser son code, ses images, ses textures ou sa DLL ;<br/>" +
        _no + "tout usage commercial.<br/>" +
        "Packs de communauté : un pack qui inclut le mod doit avoir l’autorisation écrite de COMSPEC. Sans elle, demandez à vos joueurs de s’abonner à la page officielle du Workshop. Le texte complet est dans LICENSE.txt, dans le dossier du mod.<br/><br/>" +
        format [_h, "4. Signaler un problème"] +
        "En jeu, menu d’interaction ACE → <t color='#e8f4f0'>Signaler un problème</t>. Décrivez ce qui s’est passé et quand.<br/><br/>" +
        format [_h, "5. Données techniques limitées"] +
        "Si votre unité utilise le service Athéna, des informations techniques limitées sont enregistrées pour le faire fonctionner (identité Steam si disponible, nom du joueur, version du mod). Elles ne servent pas à de la publicité.<br/><br/>" +
        format [_h, "6. Votre choix"] +
        "<t color='#e8f4f0'>J’accepte</t> : vous acceptez cette licence ; votre choix est enregistré sur ce profil. <t color='#e8f4f0'>Quitter le jeu</t> : sans votre accord, le mod ne peut pas être utilisé. Vous pouvez relire cette note depuis le bandeau du menu principal ou sur le téléphone : Réglages → À propos du téléphone → RELIRE LA LICENCE." +
    "</t>",
    "J’accepte",
    "Quitter le jeu",
    "<t align='left' size='0.55' color='#7a8c9e'>L’acceptation enregistre cette note sur ce profil et, si votre unité utilise Athéna, inscrit votre participation à la bêta dès que la liaison est disponible.</t>",
    format ["<t align='center' size='0.48' color='#4a5c6e'>COMSPEC ATAK — licence version %1 — © COMSPEC, tous droits réservés.</t>", _licVer],
    "Fermer",
    "Licence non acceptée",
    "Sans votre accord, le mod COMSPEC ATAK ne peut pas être utilisé.<br/><br/>Quittez le jeu maintenant, puis retirez le mod dans le lanceur Arma 3 si vous ne souhaitez pas l’utiliser.",
    "Quitter le jeu",
    "Revenir à la note"
]
