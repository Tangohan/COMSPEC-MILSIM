/*
    App Breacher (réaliste : rien n'est connu à distance).
      Porte au contact : seulement la porte devant vous (moins de 2,5 m) ; son verrou n'est connu qu'après avoir
      essayé la poignée. Calcul de charge : matériau de la porte et méthode → masse d'explosif et distance de
      sécurité de la colonne (estimation d'entraînement). Colonne : coéquipiers à moins de 15 m.
      Top synchronisé : compte à rebours sur l'écran de chaque membre du groupe, mise à feu des charges cochées au top.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_Breach", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_Breach", _s];
private _grey = "#8a9a93";
private _door = [] call comspec_atak_native_fnc_breachBuilding;
private _rows = [];

// Porte au contact.
if ((count _door) isEqualTo 0) then {
    _rows pushBack ["hero", "\z\comspec_atak_native\addons\main\data\app_breach.paa", format ["<t size='1.3' font='RobotoCondensedBold'>BREACHER</t><br/><t size='0.85' color='%1'>Aucune porte au contact : placez-vous contre la porte (moins de 2,5 m)</t>", _grey]];
} else {
    _door params ["_b", "_i", "_p"];
    private _open = (_b animationPhase format ["Door_%1_rot", _i]) > 0.5;
    private _key = format ["%1|%2", _b call BIS_fnc_netId, _i];
    private _tested = (_s getOrDefault ["tested", createHashMap]) getOrDefault [_key, ""];
    private _lockTxt = switch (_tested) do {
        case "locked": { "<t color='#e5483a'>VERROUILLÉE</t>" };
        case "free": { "<t color='#5cc76b'>non verrouillée</t>" };
        default { format ["<t color='%1'>inconnu : essayez la poignée</t>", _grey] };
    };
    _rows pushBack ["hero", "\z\comspec_atak_native\addons\main\data\app_breach.paa", format ["<t size='1.2' font='RobotoCondensedBold'>PORTE AU CONTACT</t><br/><t size='0.9'>%1 · verrou : %2</t><br/><t size='0.8' color='%3'>à %4 m · %5</t>",
        ["<t color='#f2ab33'>fermée</t>", "<t color='#5cc76b'>ouverte</t>"] select _open, _lockTxt, _grey, (player distance _p) toFixed 1,
        [getText (configOf _b >> "displayName"), "bâtiment"] select ((getText (configOf _b >> "displayName")) isEqualTo "")]];
    _rows pushBack ["buttons", [["ESSAYER LA POIGNÉE", { ['handle'] call comspec_atak_native_fnc_breachAction; }, true, !_open]]];
};

// Calcul de charge.
private _mat = _s getOrDefault ["mat", "WOOD"];
private _meth = _s getOrDefault ["meth", "LOCK"];
private _table = createHashMapFromArray [
    ["WOOD", [["LOCK", 30], ["STRIP", 60], ["FRAME", 120], ["WATER", 0]]],
    ["WOODR", [["LOCK", 80], ["STRIP", 150], ["FRAME", 250], ["WATER", 150]]],
    ["METAL", [["LOCK", 150], ["STRIP", 300], ["FRAME", 450], ["WATER", 250]]],
    ["ARMOR", [["LOCK", 0], ["STRIP", 0], ["FRAME", 800], ["WATER", 400]]]
];
private _g = 0;
{ if ((_x select 0) isEqualTo _meth) then { _g = _x select 1; }; } forEach (_table getOrDefault [_mat, []]);
_rows pushBack ["section", "Calcul de charge", "Estimation d'entraînement, à valider par le chef d'équipe"];
_rows pushBack ["segment", "Porte", [["BOIS", "WOOD"], ["BOIS RENFORCÉ", "WOODR"], ["MÉTAL", "METAL"], ["BLINDÉE", "ARMOR"]] apply { [_x select 0, compile format ["['mat', '%1'] call comspec_atak_native_fnc_breachAction;", _x select 1], (_x select 1) isEqualTo _mat] }];
_rows pushBack ["segment", "Méthode", [["SERRURE", "LOCK"], ["LINÉAIRE", "STRIP"], ["CADRE", "FRAME"], ["EAU", "WATER"]] apply { [_x select 0, compile format ["['meth', '%1'] call comspec_atak_native_fnc_breachAction;", _x select 1], (_x select 1) isEqualTo _meth] }];
if (_g <= 0) then {
    _rows pushBack ["text", "<t color='#e5483a'>Méthode inadaptée à cette porte : choisissez-en une autre.</t>"];
} else {
    // Distance de sécurité : k · (masse en kg)^(1/3), plus large à découvert.
    private _cube = (_g / 1000) ^ (1 / 3);
    private _cover = (9 * _cube) max 3;
    private _openAir = (14 * _cube) max 5;
    _rows pushBack ["text", format ["<t size='1.4' font='RobotoCondensedBold' color='#f2ab33'>%1 g</t><t color='%2'>  équivalent TNT</t><br/><t size='1.1' font='RobotoCondensedBold'>%3 m</t><t color='%2'> derrière un mur · </t><t size='1.1' font='RobotoCondensedBold'>%4 m</t><t color='%2'> à découvert</t>",
        _g, _grey, ceil _cover, ceil _openAir]];
    _rows pushBack ["text", format ["<t size='0.8' color='%1'>Colonne du côté des gonds, hors de l'axe de la porte. Protection auditive et lunettes. Vérifier la cible derrière la porte.</t>", _grey]];
};

// Colonne : coéquipiers proches.
private _near = (units group player) select { alive _x && {(_x distance player) < 15} };
_rows pushBack ["section", "Colonne", format ["%1 opérateur(s) à moins de 15 m", count _near]];
{ _rows pushBack ["info", name _x, format ["<t color='%1'>%2</t>", _grey, [format ["%1 m", round (player distance _x)], "moi"] select (_x isEqualTo player)]]; } forEach (_near select [0, 8]);

// Top synchronisé.
private _cd = _s getOrDefault ["countdown", 5];
private _withCharges = _s getOrDefault ["charges", false];
private _nSel = count (((uiNamespace getVariable ["COMSPEC_ATAK_Explo", createHashMap]) getOrDefault ["sel", []]));
_rows pushBack ["section", "Top synchronisé", "Compte à rebours sur l'écran de chaque membre du groupe"];
_rows pushBack ["segment", "Compte à rebours", [3, 5, 10] apply { [format ["%1 s", _x], compile format ["['countdown', %1] call comspec_atak_native_fnc_breachAction;", _x], _x isEqualTo _cd] }];
_rows pushBack ["switch", "Mettre à feu les charges au top", _withCharges, { ['charges'] call comspec_atak_native_fnc_breachAction; }, format ["%1 charge(s) cochée(s) dans l'app Explosifs (sécurité levée requise)", _nSel]];
_rows pushBack ["buttons", [["TOP AU GROUPE", { ['go'] call comspec_atak_native_fnc_breachAction; }, true], ["EXPLOSIFS", { ["EXPLO"] call comspec_atak_native_fnc_navigate; }]]];
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
