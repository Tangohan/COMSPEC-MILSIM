/*
    App Breacher (réaliste : rien n'est connu à distance).
      Bâtiment : celui que le joueur touche (porte à moins de 8 m), étages estimés et étage du joueur.
      Entrées : portes proches en cartes compactes (étage, distance, cap, ouverte / fermée, verrou). Le verrou n'est connu
      qu'après avoir essayé la poignée, au contact (moins de 2,5 m). CIBLER choisit la porte du calcul de charge.
      Charge : matériau et méthode → masse d'explosif et distances de sécurité (estimation d'entraînement), avec une
      méthode recommandée selon le matériau et l'état connu de la porte.
      Colonne : coéquipiers à moins de 15 m. Top synchronisé : compte à rebours sur l'écran de chaque membre du groupe,
      mise à feu des charges cochées au top (app Explosifs).
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_Breach", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_Breach", _s];
private _grey = "#8a9a93";
private _icon = "\z\comspec_atak_native\addons\main\data\app_breach.paa";
private _contact = [] call comspec_atak_native_fnc_breachBuilding;
private _near = ["near"] call comspec_atak_native_fnc_breachBuilding;
private _tested = _s getOrDefault ["tested", createHashMap];
private _card = { params ["_deg"]; ["N", "NE", "E", "SE", "S", "SO", "O", "NO"] select ((round (_deg / 45)) mod 8) };
private _floorTxt = { params ["_f"]; switch (_f) do { case 0: { "RDC" }; case 1: { "1er" }; default { format ["%1e", _f] }; } };
private _rows = [];

// État d'une porte : ouverte / fermée et verrou (seulement s'il a été essayé).
private _doorState = {
    params ["_b", "_i"];
    private _k = format ["%1|%2", _b call BIS_fnc_netId, _i];
    [(_b animationPhase format ["Door_%1_rot", _i]) > 0.5, _tested getOrDefault [_k, ""], _k]
};

// --- En-tête : bâtiment et étage, ou état vide ---
private _contactKey = "";
if ((count _near) isEqualTo 0) then {
    _rows pushBack ["hero", _icon, format ["<t size='1.3' font='RobotoCondensedBold'>BREACHER</t><br/><t size='0.9'>Aucun bâtiment au contact</t><br/><t size='0.8' color='%1'>Approchez-vous d'une entrée (moins de 8 m) : le Breacher ne voit que ce que vous avez devant vous.</t>", _grey]];
} else {
    _near params ["_b", "_doors", "_floors", "_myFloor"];
    private _name = getText (configOf _b >> "displayName");
    if (_name isEqualTo "") then { _name = "Bâtiment"; };
    _rows pushBack ["hero", _icon, format ["<t size='1.2' font='RobotoCondensedBold'>%1</t><br/><t size='0.9'>Vous : <t font='RobotoCondensedBold'>%2</t> · ≈ %3 niveau(x)</t><br/><t size='0.8' color='%4'>%5 entrée(s) à moins de 8 m · %6</t>",
        toUpper _name, [_myFloor] call _floorTxt, _floors, _grey, count _doors, [getPosASL _b, 8] call comspec_atak_native_fnc_gridRef]];
    if ((count _contact) > 0) then { _contactKey = ([_contact select 0, _contact select 1] call _doorState) select 2; };
};

// Étape suivante, en gros : essayer la poignée de la porte au contact.
if ((count _contact) > 0) then {
    ([_contact select 0, _contact select 1] call _doorState) params ["_open", "_lock"];
    if (!_open && {_lock isEqualTo ""}) then {
        _rows pushBack ["buttons", [[format ["ESSAYER LA POIGNÉE · PORTE %1", _contact select 1], { ['handle'] call comspec_atak_native_fnc_breachAction; }, true]]];
    };
};

// --- Entrées : cartes compactes ---
private _target = _s getOrDefault ["target", ""];
if ((count _near) > 0) then {
    _near params ["_b", "_doors"];
    _rows pushBack ["section", "Entrées", "Verrou connu seulement après essai de la poignée (au contact)"];
    if ((count _doors) isEqualTo 0) then { _rows pushBack ["text", format ["<t color='%1'>Aucune porte détectée sur ce bâtiment.</t>", _grey]]; };
    // La cible par défaut : la porte au contact, sinon la plus proche.
    if !(_target in (_doors apply { format ["%1|%2", _b call BIS_fnc_netId, _x select 0] })) then {
        _target = [format ["%1|%2", _b call BIS_fnc_netId, (_doors param [0, [0]]) select 0], _contactKey] select (_contactKey isNotEqualTo "");
    };
    {
        _x params ["_i", "_p", "_d", "_f"];
        ([_b, _i] call _doorState) params ["_open", "_lock", "_k"];
        private _isContact = _k isEqualTo _contactKey;
        private _isTarget = _k isEqualTo _target;
        private _st = switch (true) do {
            case _open: { "<t color='#5cc76b'>● ouverte</t>" };
            case (_lock isEqualTo "locked"): { "<t color='#e5483a'>● fermée · VERROUILLÉE</t>" };
            case (_lock isEqualTo "free"): { "<t color='#e8b84a'>● fermée · non verrouillée</t>" };
            default { format ["<t color='#f2ab33'>● fermée</t> <t color='%1'>· verrou inconnu</t>", _grey] };
        };
        private _col = switch (true) do { case _open: { [0.36, 0.78, 0.42, 1] }; case (_lock isEqualTo "locked"): { [0.9, 0.28, 0.23, 1] }; default { [0.95, 0.67, 0.2, 1] }; };
        private _btns = [];
        if (_isContact && {!_open}) then { _btns pushBack ["POIGNÉE", { ['handle'] call comspec_atak_native_fnc_breachAction; }, _lock isEqualTo ""]; };
        _btns pushBack [["CIBLER", "● CIBLE"] select _isTarget, compile format ["['target', %1] call comspec_atak_native_fnc_breachAction;", str _k], _isTarget];
        _rows pushBack ["person", _icon, format ["<t font='RobotoCondensedBold'>Porte %1</t>  <t size='0.85' color='%2'>%3 · %4 m %5%6</t><br/><t size='0.85'>%7</t>",
            _i, _grey, [_f] call _floorTxt, _d toFixed 1, [player getDir (ASLToAGL _p)] call _card, ["", "  <t color='#5cc76b'>· au contact</t>"] select _isContact, _st], _btns, _col];
    } forEach (_doors select [0, 8]);
};

// --- Charge recommandée ---
private _mat = _s getOrDefault ["mat", "WOOD"];
private _meth = _s getOrDefault ["meth", "LOCK"];
private _table = createHashMapFromArray [
    ["WOOD", [["LOCK", 30], ["STRIP", 60], ["FRAME", 120], ["WATER", 0]]],
    ["WOODR", [["LOCK", 80], ["STRIP", 150], ["FRAME", 250], ["WATER", 150]]],
    ["METAL", [["LOCK", 150], ["STRIP", 300], ["FRAME", 450], ["WATER", 250]]],
    ["ARMOR", [["LOCK", 0], ["STRIP", 0], ["FRAME", 800], ["WATER", 400]]]
];
private _methNames = createHashMapFromArray [["LOCK", "SERRURE"], ["STRIP", "LINÉAIRE"], ["FRAME", "CADRE"], ["WATER", "EAU"]];
private _recMeth = createHashMapFromArray [["WOOD", "LOCK"], ["WOODR", "STRIP"], ["METAL", "STRIP"], ["ARMOR", "FRAME"]] getOrDefault [_mat, "LOCK"];
private _g = 0;
{ if ((_x select 0) isEqualTo _meth) then { _g = _x select 1; }; } forEach (_table getOrDefault [_mat, []]);
// État connu de la porte ciblée : ouverte ou non verrouillée → pas besoin d'explosif.
private _tState = ["", false];
if ((count _near) > 0 && {_target isNotEqualTo ""}) then {
    private _ti = parseNumber ((_target splitString "|") param [1, "0"]);
    if (_ti > 0) then { ([_near select 0, _ti] call _doorState) params ["_o", "_lk"]; _tState = [_lk, _o]; };
};
_rows pushBack ["section", "Charge", format ["%1 · estimation d'entraînement, à valider par le chef d'équipe", ["aucune porte ciblée", format ["porte %1", (_target splitString "|") param [1, "?"]]] select (_target isNotEqualTo "")]];
_rows pushBack ["segment", "Matériau de la porte", [["BOIS", "WOOD"], ["RENFORCÉ", "WOODR"], ["MÉTAL", "METAL"], ["BLINDÉE", "ARMOR"]] apply { [_x select 0, compile format ["['mat', '%1'] call comspec_atak_native_fnc_breachAction;", _x select 1], (_x select 1) isEqualTo _mat] }];
_rows pushBack ["segment", "Méthode", ["LOCK", "STRIP", "FRAME", "WATER"] apply { [[_methNames get _x, (_methNames get _x) + " ★"] select (_x isEqualTo _recMeth), compile format ["['meth', '%1'] call comspec_atak_native_fnc_breachAction;", _x], _x isEqualTo _meth] }, "★ recommandée"];
switch (true) do {
    case (_tState select 1): { _rows pushBack ["text", "<t color='#5cc76b' font='RobotoCondensedBold'>Porte ouverte : pas de charge nécessaire.</t>"]; };
    case ((_tState select 0) isEqualTo "free"): { _rows pushBack ["text", "<t color='#e8b84a' font='RobotoCondensedBold'>Porte non verrouillée : entrée manuelle possible, pas de charge.</t>"]; };
    default {};
};
if (_g <= 0) then {
    _rows pushBack ["text", format ["<t color='#e5483a'>Méthode inadaptée à cette porte.</t> <t color='%1'>Recommandé : %2.</t>", _grey, _methNames get _recMeth]];
} else {
    // Distance de sécurité : k · (masse en kg)^(1/3), plus large à découvert.
    private _cube = (_g / 1000) ^ (1 / 3);
    private _cover = (9 * _cube) max 3;
    private _openAir = (14 * _cube) max 5;
    _rows pushBack ["text", format ["<t size='1.6' font='RobotoCondensedBold' color='#f2ab33'>%1 g</t><t color='%2'> TNT · %3</t><br/><t size='1.15' font='RobotoCondensedBold'>%4 m</t><t color='%2'> derrière un mur   </t><t size='1.15' font='RobotoCondensedBold'>%5 m</t><t color='%2'> à découvert</t>",
        _g, _grey, _methNames get _meth, ceil _cover, ceil _openAir]];
    _rows pushBack ["text", format ["<t size='0.8' color='%1'>Colonne du côté des gonds, hors de l'axe de la porte. Protection auditive et lunettes. Vérifier la cible derrière la porte.</t>", _grey]];
};
if (_meth isNotEqualTo _recMeth) then {
    _rows pushBack ["buttons", [[format ["APPLIQUER LA RECOMMANDATION : %1", _methNames get _recMeth], compile format ["['meth', '%1'] call comspec_atak_native_fnc_breachAction;", _recMeth]]]];
};

// --- Colonne : coéquipiers proches, sur une ligne ---
private _team = (units group player) select { alive _x && {(_x distance player) < 15} };
_rows pushBack ["section", "Colonne", format ["%1 opérateur(s) à moins de 15 m", count _team]];
if ((count _team) <= 1) then {
    _rows pushBack ["text", format ["<t color='%1'>Seul : rapprochez la colonne avant le top.</t>", _grey]];
} else {
    _rows pushBack ["text", ((_team select [0, 10]) apply { format ["<t font='RobotoCondensedBold'>%1</t> <t size='0.8' color='%2'>%3</t>", name _x, _grey, ["", format ["%1 m", round (player distance _x)]] select (_x isNotEqualTo player)] }) joinString "   "];
};

// --- Top synchronisé : action principale ---
private _cd = _s getOrDefault ["countdown", 5];
private _withCharges = _s getOrDefault ["charges", false];
private _nSel = count (((uiNamespace getVariable ["COMSPEC_ATAK_Explo", createHashMap]) getOrDefault ["sel", []]));
private _armed = (uiNamespace getVariable ["COMSPEC_ATAK_Explo", createHashMap]) getOrDefault ["armed", false];
_rows pushBack ["section", "Top synchronisé", "Compte à rebours sur l'écran de chaque membre du groupe"];
_rows pushBack ["segment", "Compte à rebours", [3, 5, 10] apply { [format ["%1 s", _x], compile format ["['countdown', %1] call comspec_atak_native_fnc_breachAction;", _x], _x isEqualTo _cd] }];
_rows pushBack ["switch", "Mettre à feu les charges au top", _withCharges, { ['charges'] call comspec_atak_native_fnc_breachAction; },
    format ["%1 charge(s) cochée(s) dans l'app Explosifs · %2", _nSel, ["<t color='#e5483a'>sécurité en place</t>", "<t color='#5cc76b'>sécurité levée</t>"] select _armed]];
if (_withCharges && {_nSel isEqualTo 0 || {!_armed}}) then {
    _rows pushBack ["text", "<t size='0.85' color='#e5483a'>Charges non prêtes : cochez-les et levez la sécurité dans l'app Explosifs.</t>"];
};
_rows pushBack ["buttons", [[format ["TOP AU GROUPE · %1 s%2", _cd, ["", " · FEU"] select _withCharges], { ['go'] call comspec_atak_native_fnc_breachAction; }, true]]];
_rows pushBack ["buttons", [["APP EXPLOSIFS", { ["EXPLO"] call comspec_atak_native_fnc_navigate; }]]];
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
