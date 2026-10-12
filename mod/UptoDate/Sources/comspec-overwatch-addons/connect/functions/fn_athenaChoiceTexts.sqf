/*
    Textes de l'écran du choix Athena (FR / EN).
    Params : [langue "fr" | "en", choix actuel "on" | "off" | ""]
    Retour : [titre, question, carte Oui, bouton Oui, carte Non, bouton Non, pied, choix actuel, bouton Fermer]
*/
params [["_lang", "fr"], ["_mode", ""]];

private _li = { params ["_t"]; format ["<t color='#5a9e88'>•</t> %1<br/>", _t] };

if (_lang isEqualTo "en") exitWith {
    [
        "<t font='RobotoCondensedBold' size='1.05' color='#e8f4f0'>COMSPEC ATAK</t><t size='0.6' color='#8aa0b4'>  ·  ATHENA SERVICE</t>",
        "<t size='1.15' font='RobotoCondensedBold' color='#e8f4f0'>Does your unit use the Athena service?</t><br/><t size='0.68' color='#8aa0b4'>Athena is the web platform of COMSPEC units (command post, accounts, reports). If you are not sure, ask your unit or answer No: you can change this later.</t>",
        "<t font='RobotoCondensedBold' size='0.95' color='#5cc7a0'>YES · my unit uses Athena</t><br/><br/><t size='0.78' color='#c8d8e4'>" +
            (["COMSPEC Link connects the phone to your Athena account"] call _li) +
            (["Two-way sync with the command post: positions, BFT, chat, orders, reports, photos"] call _li) +
            (["Unit settings and Athena apps on the phone"] call _li) +
            (["You can link your account right away"] call _li) +
        "</t>",
        "YES",
        "<t font='RobotoCondensedBold' size='0.95' color='#e8b84a'>NO · play without Athena</t><br/><br/><t size='0.78' color='#c8d8e4'>" +
            (["The ATAK stays local: map, BFT, chat and apps between players on this server"] call _li) +
            (["No connection, no sync, no data sent outside the game"] call _li) +
            (["For a regular server with the usual Arma mods"] call _li) +
        "</t>",
        "NO",
        "<t size='0.7' color='#8aa0b4'>You can change this choice at any time: ATAK phone, Settings, Athena service, or the Escape menu, Athena service.</t>",
        ["", format ["<t size='0.72' color='#8aa0b4'>Current choice: </t><t size='0.72' color='#e8f4f0'>%1</t>", ["No (local)", "Yes (Athena)"] select (_mode isEqualTo "on")]] select (_mode isNotEqualTo ""),
        "Close"
    ]
};

[
    "<t font='RobotoCondensedBold' size='1.05' color='#e8f4f0'>COMSPEC ATAK</t><t size='0.6' color='#8aa0b4'>  ·  SERVICE ATHÉNA</t>",
    "<t size='1.15' font='RobotoCondensedBold' color='#e8f4f0'>Votre unité utilise-t-elle le service Athéna ?</t><br/><t size='0.68' color='#8aa0b4'>Athéna est la plateforme web des unités COMSPEC (poste de commandement, comptes, comptes rendus). Dans le doute, demandez à votre unité ou répondez Non : vous pourrez changer plus tard.</t>",
    "<t font='RobotoCondensedBold' size='0.95' color='#5cc7a0'>OUI · mon unité utilise Athéna</t><br/><br/><t size='0.78' color='#c8d8e4'>" +
        (["COMSPEC Link relie le téléphone à votre compte Athéna"] call _li) +
        (["Synchro dans les deux sens avec le poste de commandement : positions, BFT, messages, ordres, comptes rendus, photos"] call _li) +
        (["Réglages de l'unité et apps Athéna sur le téléphone"] call _li) +
        (["Vous pouvez lier votre compte tout de suite"] call _li) +
    "</t>",
    "OUI",
    "<t font='RobotoCondensedBold' size='0.95' color='#e8b84a'>NON · jouer sans Athéna</t><br/><br/><t size='0.78' color='#c8d8e4'>" +
        (["L'ATAK reste en local : carte, BFT, messages et apps entre joueurs du serveur"] call _li) +
        (["Aucune connexion, aucune synchro, aucune donnée envoyée hors du jeu"] call _li) +
        (["Pour un serveur classique avec les mods Arma habituels"] call _li) +
    "</t>",
    "NON",
    "<t size='0.7' color='#8aa0b4'>Vous pourrez changer ce choix à tout moment : téléphone ATAK, Réglages, Service Athéna, ou menu Échap, Service Athéna.</t>",
    ["", format ["<t size='0.72' color='#8aa0b4'>Choix actuel : </t><t size='0.72' color='#e8f4f0'>%1</t>", ["Non (local)", "Oui (Athéna)"] select (_mode isEqualTo "on")]] select (_mode isNotEqualTo ""),
    "Fermer"
]
