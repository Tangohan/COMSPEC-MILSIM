/*
    Catalogue des patchs au dos (app PATCHES, fn_pagePatches ; ligne « Patch au dos » des Réglages).
    Actions :
      ["list"]          : tous les patchs, [[valeur profil, nom, texture, source, clé de recherche]...]
                          source : "ours" (nos patchs, "none" et "uniform" compris), "mods" (CfgUnitInsignia du jeu
                          et des mods chargés), "mission" (CfgUnitInsignia de la mission).
                          Valeur profil COMSPEC_ATAK_Patch : notre clé (fr, skull…) ou "ins:<classe>".
      ["fold", texte]   : texte en minuscules sans accents (recherche tolérante).
      ["name", valeur]  : nom affiché d'une valeur du profil.
      ["count"]         : nombre d'insignes d'unité (mods + mission).
    Insignes des mods : liste gardée dans uiNamespace COMSPEC_ATAK_InsList (la config ne change pas pendant la session) ;
    ceux de la mission : missionNamespace COMSPEC_ATAK_InsListMission (refaite à chaque mission).
*/
params [["_act", "list"], ["_arg", ""]];

private _fold = {
    params ["_t"];
    if !(_t isEqualType "") then { _t = str _t; };
    private _m = uiNamespace getVariable ["COMSPEC_ATAK_FoldMap", createHashMap];
    if ((count _m) isEqualTo 0) then {
        {
            _x params ["_to", "_from"];
            { _m set [_x, _to]; } forEach _from;
        } forEach [
            [97, [224, 225, 226, 227, 228, 229, 192, 193, 194, 195, 196, 197]],
            [99, [231, 199]],
            [101, [232, 233, 234, 235, 200, 201, 202, 203]],
            [105, [236, 237, 238, 239, 204, 205, 206, 207]],
            [110, [241, 209]],
            [111, [242, 243, 244, 245, 246, 248, 210, 211, 212, 213, 214, 216]],
            [117, [249, 250, 251, 252, 217, 218, 219, 220]],
            [121, [253, 255, 221]]
        ];
        uiNamespace setVariable ["COMSPEC_ATAK_FoldMap", _m];
    };
    toString ((toArray (toLower _t)) apply { _m getOrDefault [_x, _x] })
};
if (_act isEqualTo "fold") exitWith { [_arg] call _fold };

// Insignes CfgUnitInsignia d'une config : [classe, nom, texture].
private _scan = {
    params ["_root"];
    private _out = [];
    {
        private _t = getText (_x >> "texture");
        if (_t isNotEqualTo "" && {(_t select [0, 1]) isNotEqualTo "\"}) then { _t = "\" + _t; };
        if (_t isNotEqualTo "" && {(getNumber (_x >> "scope")) >= 1 || {!isNumber (_x >> "scope")}}) then {
            private _n = getText (_x >> "displayName");
            if (_n isEqualTo "") then { _n = configName _x; };
            _out pushBack [_n, configName _x, _t];
        };
    } forEach ("true" configClasses (_root >> "CfgUnitInsignia"));
    _out sort true;
    _out apply { [_x select 1, _x select 0, _x select 2] }
};
private _mods = uiNamespace getVariable ["COMSPEC_ATAK_InsList", []];
if (_mods isEqualTo [] || {(count (_mods select 0)) < 3}) then {
    _mods = [configFile] call _scan;
    uiNamespace setVariable ["COMSPEC_ATAK_InsList", _mods];
};
private _mis = missionNamespace getVariable "COMSPEC_ATAK_InsListMission";
if (isNil "_mis") then {
    _mis = [missionConfigFile] call _scan;
    missionNamespace setVariable ["COMSPEC_ATAK_InsListMission", _mis];
};
if (_act isEqualTo "count") exitWith { (count _mods) + (count _mis) };

private _ours = [
    ["none", "Aucun"], ["fr", "France"], ["fr_sub", "France BD"], ["us", "USA"], ["us_ir", "USA BD"],
    ["skull", "Crâne"], ["crusader", "Croix"], ["alien", "Alien"], ["comspec", "COMSPEC"], ["name", "Nominatif"], ["uniform", "Insigne de l'uniforme"]
];
if (_act isEqualTo "name") exitWith {
    if !(_arg isEqualType "") exitWith { "Aucun" };
    private _i = _ours findIf { (_x select 0) isEqualTo _arg };
    if (_i >= 0) exitWith { (_ours select _i) select 1 };
    if ((_arg select [0, 4]) isEqualTo "ins:") exitWith {
        private _c = _arg select [4];
        private _hit = (_mis + _mods) select { (_x select 0) isEqualTo _c };
        if ((count _hit) > 0) then { (_hit select 0) select 1 } else { _c }
    };
    _arg
};

// Liste complète, gardée pour la mission (clés de recherche déjà calculées).
private _all = missionNamespace getVariable ["COMSPEC_ATAK_PatchCatalog", []];
if (_all isEqualTo []) then {
    {
        _x params ["_v", "_n"];
        // L'insigne de l'uniforme change avec la tenue : sa texture est lue au moment de l'affichage.
        private _tex = if (_v isEqualTo "uniform") then { "" } else { ([player, _v] call comspec_atak_native_fnc_patchTexture) select 0 };
        _all pushBack [_v, _n, _tex, "ours", [_n + " " + _v] call _fold];
    } forEach _ours;
    { _all pushBack ["ins:" + (_x select 0), _x select 1, _x select 2, "mods", [(_x select 1) + " " + (_x select 0)] call _fold]; } forEach _mods;
    { _all pushBack ["ins:" + (_x select 0), _x select 1, _x select 2, "mission", [(_x select 1) + " " + (_x select 0)] call _fold]; } forEach _mis;
    missionNamespace setVariable ["COMSPEC_ATAK_PatchCatalog", _all];
};
_all
