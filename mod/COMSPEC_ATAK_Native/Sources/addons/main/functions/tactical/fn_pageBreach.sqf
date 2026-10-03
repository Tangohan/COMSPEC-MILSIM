/*
    App Breacher : bâtiment visé (portes, verrous, niveaux, pièces), état de chaque porte et distance,
    équipe en colonne (qui est près d'une porte), top synchronisé au groupe (compte à rebours à l'écran de chacun)
    avec mise à feu des charges cochées dans l'app Explosifs au top.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_Breach", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_Breach", _s];
private _grey = "#8a9a93";
private _card = { params ["_deg"]; ["N", "NE", "E", "SE", "S", "SO", "O", "NO"] select ((round (_deg / 45)) mod 8) };
private _b = _s getOrDefault ["building", objNull];
if (isNull _b || {!alive _b}) then { _b = [] call comspec_atak_native_fnc_breachBuilding; _s set ["building", _b]; };
private _rows = [];
private _doors = [];
if (isNull _b) then {
    _rows pushBack ["hero", "\z\comspec_atak_native\addons\main\data\app_breach.paa", format ["<t size='1.3' font='RobotoCondensedBold'>BREACHER</t><br/><t size='0.85' color='%1'>Regardez un bâtiment puis VISER</t>", _grey]];
} else {
    private _n = getNumber (configOf _b >> "numberOfDoors");
    for "_i" from 1 to _n do {
        private _mp = _b selectionPosition [format ["Door_%1_trigger", _i], "Memory"];
        private _pos = if (_mp isEqualTo [0, 0, 0]) then { getPosATL _b } else { _b modelToWorld _mp };
        private _open = (_b animationPhase format ["Door_%1_rot", _i]) > 0.5;
        private _lock = (_b getVariable [format ["bis_disabled_Door_%1", _i], 0]) isEqualTo 1;
        _doors pushBack [_i, _pos, _open, _lock];
    };
    (boundingBoxReal _b) params ["_b0", "_b1"];
    private _levels = (round (((_b1 select 2) - (_b0 select 2)) / 3.2)) max 1;
    private _locked = { _x select 3 } count _doors;
    private _openN = { _x select 2 } count _doors;
    _rows pushBack ["hero", "\z\comspec_atak_native\addons\main\data\app_breach.paa", format ["<t size='1.15' font='RobotoCondensedBold'>%1</t><br/><t size='0.85' color='%2'>%3 m · %4 · %5 porte(s), %6 verrouillée(s), %7 ouverte(s) · %8 niveau(x) · %9 positions</t>",
        [getText (configOf _b >> "displayName"), "Bâtiment"] select ((getText (configOf _b >> "displayName")) isEqualTo ""), _grey,
        round (player distance2D _b), [player getDir _b] call _card, count _doors, _locked, _openN, _levels, count (_b buildingPos -1)]];
};
_rows pushBack ["buttons", [["VISER UN BÂTIMENT", { ['aim'] call comspec_atak_native_fnc_breachAction; }, true], ["CARTE", { ['map'] call comspec_atak_native_fnc_breachAction; }, false, !isNull _b]]];

// Portes, de la plus proche à la plus lointaine.
if ((count _doors) > 0) then {
    private _sorted = _doors apply { [player distance (_x select 1), _x] };
    _sorted sort true;
    _rows pushBack ["section", "Portes", "La plus proche en premier"];
    {
        _x params ["_d", "_door"];
        _door params ["_i", "_pos", "_open", "_lock"];
        private _st = switch (true) do {
            case _lock: { "<t color='#e5483a'>VERROUILLÉE · charge ou fusil à pompe</t>" };
            case _open: { "<t color='#5cc76b'>ouverte</t>" };
            default { "<t color='#f2ab33'>fermée</t>" };
        };
        _rows pushBack ["info", format ["Porte %1", _i], format ["%1  <t color='%2'>%3 m · %4 · niv. %5</t>", _st, _grey, round _d, [player getDir _pos] call _card, (round (((_pos select 2) - ((getPosATL _b) select 2)) / 3.2)) max 0]];
    } forEach (_sorted select [0, 16]);
};

// Équipe : qui est en position (à moins de 6 m d'une porte).
private _team = units group player;
_rows pushBack ["section", "Équipe", format ["%1 opérateur(s)", count _team]];
{
    private _u = _x;
    private _best = 1e9;
    { _best = _best min (_u distance (_x select 1)); } forEach _doors;
    _rows pushBack ["info", name _u, if ((count _doors) isEqualTo 0) then { format ["<t color='%1'>%2 m de moi</t>", _grey, round (player distance _u)] } else {
        [format ["<t color='%1'>à %2 m de la porte la plus proche</t>", _grey, round _best], "<t color='#5cc76b'>EN POSITION</t>"] select (_best < 6) }];
} forEach (_team select [0, 10]);

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
