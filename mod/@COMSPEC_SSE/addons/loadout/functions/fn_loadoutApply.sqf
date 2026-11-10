/*
    Pose une tenue (LOCAL à l'unité) : [_unit, _loadout, _name, _quiet] call comspec_sse_fnc_loadoutApply
      _loadout : tableau getUnitLoadout ou texte (JSON, str SQF, emballages de fn_loadoutParse)
      _name    : nom montré au joueur (facultatif)
      _quiet   : true = rien n'est montré au joueur (autotest, scripts)
    -> [ok, manquants] : ok = tenue posée ; manquants = [[classe, remplaçant ou ""]…].
    Chaque classe est cherchée dans sa table (CfgWeapons, CfgMagazines, CfgVehicles pour les sacs, CfgGlasses), sans
    tenir compte de la casse, et réécrite avec son nom exact (configName). Classe absente des mods chargés : retirée
    ET listée. Seul remplacement : un chargeur absent engagé dans une arme, par l'unique chargeur compatible de ce
    canon qui contient le même nombre de munitions (aucun ou plusieurs : retiré). Jamais d'objet inventé sans le dire.
    Munitions données conservées (chargeurs partiels) : CBA_fnc_setLoadout ou setUnitLoadout [tenue, false].
*/
params [["_unit", objNull, [objNull]], ["_src", []], ["_name", "", [""]], ["_quiet", false, [false]]];
if (isNull _unit || {!local _unit}) exitWith { [false, []] };
private _lo = [_src] call comspec_sse_fnc_loadoutParse;
private _say = {
    params ["_txt", "_type"];
    if (_quiet || {!hasInterface} || {_unit isNotEqualTo player}) exitWith {};
    // Notification du téléphone ; sans ATAK natif, RPT seul (jamais le chat ni une alerte d'Arma).
    if !([_txt, _type, "Équipement"] call comspec_sse_fnc_notify) then { diag_log text format ["[COMSPEC SSE] Équipement : %1", _txt regexReplace ["\\n", " "]]; };
};
if (_lo isEqualTo []) exitWith {
    diag_log text format ["[COMSPEC SSE] Équipement : tenue illisible (%1).", _name];
    [format ["Tenue %1 illisible : rien n'a été changé.", ["", format ["« %1 »", _name]] select (_name isNotEqualTo "")], "WARNING"] call _say;
    [false, []]
};
private _missing = [];
private _found = createHashMap;
// Nom exact de la classe dans l'une des tables (« BP » : sac de CfgVehicles), "" si absente.
private _find = {
    params ["_c", "_roots"];
    private _k = (_roots joinString ",") + "|" + toLower _c;
    private _r = _found get _k;
    if (!isNil "_r") exitWith { _r };
    _r = "";
    {
        private _cfg = if (_x isEqualTo "BP") then { configFile >> "CfgVehicles" >> _c } else { configFile >> _x >> _c };
        if (isClass _cfg && {_x isNotEqualTo "BP" || {getNumber (_cfg >> "isBackpack") == 1}}) exitWith { _r = configName _cfg; };
    } forEach _roots;
    _found set [_k, _r];
    _r
};
private _chk = {
    params ["_c", "_roots"];
    if (!(_c isEqualType "") || {_c isEqualTo ""}) exitWith { "" };
    private _r = [_c, _roots] call _find;
    if (_r isEqualTo "") then { _missing pushBack [_c, ""]; };
    _r
};
// Chargeur engagé : [classe, munitions] ; absent -> l'unique compatible de même contenance, sinon retiré.
private _wmag = {
    params ["_m", "_w", "_primary"];
    if (!(_m isEqualType []) || {(count _m) < 1} || {!((_m select 0) isEqualType "")} || {(_m select 0) isEqualTo ""}) exitWith { [] };
    _m params ["_mc", ["_n", 1]];
    if !(_n isEqualType 0) then { _n = 1; };
    private _e = [_mc, ["CfgMagazines"]] call _find;
    if (_e isNotEqualTo "") exitWith { [_e, _n] };
    private _muz = (getArray (configFile >> "CfgWeapons" >> _w >> "muzzles")) apply { toLower _x };
    private _cands = [];
    if (_primary) then {
        _cands = compatibleMagazines [_w, "this"];
        if (_cands isEqualTo []) then { _cands = compatibleMagazines _w; };
    } else {
        { if (_x isNotEqualTo "this") then { _cands append (compatibleMagazines [_w, _x]); }; } forEach _muz;
    };
    private _seen = createHashMap;
    private _same = [];
    {
        private _cfg = configFile >> "CfgMagazines" >> _x;
        if (isClass _cfg && {!((toLower _x) in _seen)} && {getNumber (_cfg >> "count") == _n}) then {
            _seen set [toLower _x, true];
            _same pushBack configName _cfg;
        };
    } forEach _cands;
    if ((count _same) == 1) exitWith { _missing pushBack [_mc, _same select 0]; [_same select 0, _n] };
    _missing pushBack [_mc, ""];
    []
};
// Arme : [arme, bouche, rail, optique, chargeur, chargeur 2, bipied] ; arme absente : tout l'emplacement est vidé.
private _wpn = {
    params ["_w"];
    if (!(_w isEqualType []) || {_w isEqualTo []}) exitWith { [] };
    _w params [["_c", ""], ["_mz", ""], ["_pt", ""], ["_op", ""], ["_m1", []], ["_m2", []], ["_bp", ""]];
    private _e = [_c, ["CfgWeapons"]] call _chk;
    if (_e isEqualTo "") exitWith {
        if (_c isEqualType "" && {_c isNotEqualTo ""}) then { diag_log text format ["[COMSPEC SSE] Équipement : arme %1 absente, ses accessoires et chargeurs ne sont pas posés.", _c]; };
        []
    };
    [_e, [_mz, ["CfgWeapons"]] call _chk, [_pt, ["CfgWeapons"]] call _chk, [_op, ["CfgWeapons"]] call _chk,
        [_m1, _e, true] call _wmag, [_m2, _e, false] call _wmag, [_bp, ["CfgWeapons"]] call _chk]
};
// Conteneur : [classe, contenu] ; contenu : [objet, n], [chargeur, n, munitions], [[arme…], n], [sac, true].
private _cont = {
    params ["_ct", "_roots"];
    if (!(_ct isEqualType []) || {_ct isEqualTo []}) exitWith { [] };
    _ct params [["_c", ""], ["_items", []]];
    private _e = [_c, _roots] call _chk;
    if (_e isEqualTo "") exitWith {
        if (_c isEqualType "" && {_c isNotEqualTo ""}) then { diag_log text format ["[COMSPEC SSE] Équipement : conteneur %1 absent, son contenu n'est pas posé.", _c]; };
        []
    };
    private _out = [];
    if !(_items isEqualType []) then { _items = []; };
    {
        if (_x isEqualType [] && {(count _x) >= 2}) then {
            private _a0 = _x select 0;
            private _a1 = _x select 1;
            switch (true) do {
                case (_a0 isEqualType []): {
                    private _w = [_a0] call _wpn;
                    if (_w isNotEqualTo []) then { _out pushBack [_w, [_a1, 1] select !(_a1 isEqualType 0)]; };
                };
                case (_a1 isEqualType true): {
                    private _b = [_a0, ["BP"]] call _chk;
                    if (_b isNotEqualTo "") then { _out pushBack [_b, _a1]; };
                };
                case ((count _x) >= 3): {
                    private _m = [_a0, ["CfgMagazines"]] call _chk;
                    if (_m isNotEqualTo "") then { _out pushBack [_m, _a1, _x select 2]; };
                };
                default {
                    private _i = [_a0, ["CfgWeapons", "CfgGlasses", "CfgMagazines"]] call _chk;
                    if (_i isNotEqualTo "") then { _out pushBack [_i, _a1]; };
                };
            };
        };
    } forEach _items;
    [_e, _out]
};
_lo params ["_pri", "_sec", "_hg", "_uni", "_vest", "_bp", "_hat", "_gog", "_bino", "_asg"];
private _new = [
    [_pri] call _wpn, [_sec] call _wpn, [_hg] call _wpn,
    [_uni, ["CfgWeapons"]] call _cont, [_vest, ["CfgWeapons"]] call _cont, [_bp, ["BP"]] call _cont,
    [_hat, ["CfgWeapons"]] call _chk, [_gog, ["CfgGlasses"]] call _chk, [_bino] call _wpn,
    _asg apply { [_x, ["CfgWeapons"]] call _chk }
];
if (isNil "CBA_fnc_setLoadout") then { _unit setUnitLoadout [_new, false]; } else { [_unit, _new, false] call CBA_fnc_setLoadout; };
private _label = ["la tenue", format ["« %1 »", _name]] select (_name isNotEqualTo "");
if (_missing isEqualTo []) then {
    [format ["Équipement : %1 est posée.", _label], "SUCCESS"] call _say;
} else {
    private _gone = (_missing select { (_x select 1) isEqualTo "" }) apply { _x select 0 };
    private _swap = (_missing select { (_x select 1) isNotEqualTo "" }) apply { format ["%1 -> %2", _x select 0, _x select 1] };
    diag_log text format ["[COMSPEC SSE] Équipement : %1 posée sur %2 ; absents retirés : %3 ; remplacés : %4.", _label, name _unit, _gone, _swap];
    private _txt = if (missionNamespace getVariable ["comspec_sse_lo_showReplaced", true]) then {
        format ["Équipement : %1 est posée.%2%3", _label,
            ["", format ["\nAbsents de vos mods (retirés) : %1.", _gone joinString ", "]] select (_gone isNotEqualTo []),
            ["", format ["\nChargeurs remplacés : %1.", _swap joinString ", "]] select (_swap isNotEqualTo [])]
    } else {
        format ["Équipement : %1 est posée, %2 objet(s) absent(s) ou remplacé(s) (détail au RPT).", _label, count _missing]
    };
    [_txt, "WARNING"] call _say;
};
[true, _missing]
