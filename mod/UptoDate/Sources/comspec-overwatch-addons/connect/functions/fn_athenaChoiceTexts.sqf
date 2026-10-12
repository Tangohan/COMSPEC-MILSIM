/*
    Textes de l'écran du choix Athena (FR / EN).
    Params : [langue "fr" | "en", choix actuel "on" | "off" | ""]
    Retour : [titre, sous-titre, question, titre Oui, liste Oui, bouton Oui, titre Non, liste Non, bouton Non, pied, choix actuel, bouton Fermer]
*/
params [["_lang", "fr"], ["_mode", ""]];

private _list = {
    params ["_items", "_dot"];
    private _out = "<t size='0.75' color='#c8d8e4'>";
    { _out = _out + format ["<t color='%1' size='1.1'>•</t>  %2<br/>", _dot, _x]; } forEach _items;
    _out + "</t>"
};
private _title = "<t font='RobotoCondensedBold' size='1.15' color='#e8f4f0'>COMSPEC ATAK</t>";
private _cur = {
    params ["_label", "_yes", "_no"];
    if (_mode isEqualTo "") exitWith { "" };
    format ["<t size='0.75' color='#8aa0b4'>%1 </t><t size='0.75' font='RobotoCondensedBold' color='%2'>%3</t>", _label, ["#e8b84a", "#5cc7a0"] select (_mode isEqualTo "on"), [_no, _yes] select (_mode isEqualTo "on")]
};

if (_lang isEqualTo "en") exitWith {
    [
        _title,
        "<t size='0.7' color='#8aa0b4'>ATHENA SERVICE  ·  " + (["SETTINGS", "FIRST LAUNCH"] select (_mode isEqualTo "")) + "</t>",
        "<t size='1.35' font='RobotoCondensedBold' color='#ffffff'>Does your unit use the Athena service?</t><br/><t size='0.75' color='#9fb2c2'>Athena is the web platform of COMSPEC units: command post, accounts, reports. Not sure? Ask your unit or answer No, you can change it later.</t>",
        "<t size='1' font='RobotoCondensedBold' color='#5cc7a0'>YES</t><t size='1' color='#e8f4f0'>  my unit uses Athena</t>",
        [["Athena account linked through COMSPEC Link", "Sync with the command post: positions, BFT, chat, orders, reports, photos", "Unit settings and Athena apps", "Link your account right away"], "#5cc7a0"] call _list,
        "YES, CONNECT",
        "<t size='1' font='RobotoCondensedBold' color='#e8b84a'>NO</t><t size='1' color='#e8f4f0'>  play without Athena</t>",
        [["Local ATAK: map, BFT and chat between players on this server", "No connection, no sync", "No data sent outside the game", "Regular server, usual Arma mods"], "#e8b84a"] call _list,
        "NO, STAY LOCAL",
        "<t size='0.72' color='#8aa0b4'>Change it at any time: </t><t size='0.72' color='#c8d8e4'>ATAK phone › Settings › Athena service</t><t size='0.72' color='#8aa0b4'> or </t><t size='0.72' color='#c8d8e4'>Escape menu › Athena service</t>",
        ["Current choice:", "Yes, Athena", "No, local"] call _cur,
        "Close"
    ]
};

[
    _title,
    "<t size='0.7' color='#8aa0b4'>SERVICE ATHÉNA  ·  " + (["RÉGLAGES", "PREMIER LANCEMENT"] select (_mode isEqualTo "")) + "</t>",
    "<t size='1.35' font='RobotoCondensedBold' color='#ffffff'>Votre unité utilise-t-elle le service Athéna ?</t><br/><t size='0.75' color='#9fb2c2'>Athéna est la plateforme web des unités COMSPEC : poste de commandement, comptes, comptes rendus. Dans le doute, demandez à votre unité ou répondez Non, vous pourrez changer plus tard.</t>",
    "<t size='1' font='RobotoCondensedBold' color='#5cc7a0'>OUI</t><t size='1' color='#e8f4f0'>  mon unité utilise Athéna</t>",
    [["Compte Athéna relié par COMSPEC Link", "Synchro avec le poste : positions, BFT, messages, ordres, comptes rendus, photos", "Réglages de l'unité et apps Athéna", "Liaison du compte tout de suite"], "#5cc7a0"] call _list,
    "OUI, SE CONNECTER",
    "<t size='1' font='RobotoCondensedBold' color='#e8b84a'>NON</t><t size='1' color='#e8f4f0'>  jouer sans Athéna</t>",
    [["ATAK local : carte, BFT et messages entre joueurs du serveur", "Aucune connexion ni synchro", "Aucune donnée envoyée hors du jeu", "Serveur classique, mods Arma habituels"], "#e8b84a"] call _list,
    "NON, RESTER EN LOCAL",
    "<t size='0.72' color='#8aa0b4'>Modifiable à tout moment : </t><t size='0.72' color='#c8d8e4'>téléphone ATAK › Réglages › Service Athéna</t><t size='0.72' color='#8aa0b4'> ou </t><t size='0.72' color='#c8d8e4'>menu Échap › Service Athéna</t>",
    ["Choix actuel :", "Oui, Athéna", "Non, local"] call _cur,
    "Fermer"
]
