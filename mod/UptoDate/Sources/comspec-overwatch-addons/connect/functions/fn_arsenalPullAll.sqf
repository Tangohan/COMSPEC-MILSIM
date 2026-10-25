/*
    Tire la liste cloud + fusionne dans ace_arsenal_saved_loadouts (profile).
    params: [applyId optional, onlyIds optional, keepLocal optional] — onlyIds vide = toutes.
    keepLocal (import groupé, « Tout basculer dans l'arsenal ACE ») : une tenue locale du même nom mais différente
    n'est jamais remplacée ; la version du site arrive à côté, sous « nom · Athena ». Identique : rien ne change.
*/
params [["_applyId", "", [""]], ["_onlyIds", [], [[]]], ["_keepLocal", false, [false]]];

if (!hasInterface) exitWith { false };

if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {
    ["Liaison Athena requise pour récupérer les tenues.", "arsenal", "warn", true] call comspec_overwatch_connect_fnc_announce;
    false
};

if (missionNamespace getVariable ["COMSPEC_ArsenalPullBusy", false]) exitWith {
    ["Récupération des tenues déjà en cours.", "arsenal", "info", true] call comspec_overwatch_connect_fnc_announce;
    false
};

private _lines = [] call comspec_overwatch_connect_fnc_arsenalListWardrobes;
if (_lines isEqualTo []) exitWith {
    private _probe = ["COMSPECExtension" callExtension ["ListWardrobes", ["0"]]] call comspec_overwatch_connect_fnc_extResult;
    if (!(_probe isEqualType "") || {_probe find "OK|" != 0}) then {
        private _err = if (_probe isEqualType "") then { _probe } else { str _probe };
        [format ["Impossible de lister les tenues (%1).", _err], "arsenal", "warn", true] call comspec_overwatch_connect_fnc_announce;
    } else {
        [
            if (_onlyIds isEqualTo []) then { "Aucune tenue dans la communauté." } else { "Cette tenue n’est plus disponible." },
            "arsenal",
            "info",
            true
        ] call comspec_overwatch_connect_fnc_announce;
    };
    false
};

private _meta = [];
{
    if (_x isEqualTo "") then { continue };
    // Champs vides conservés (splitString les fusionne et décale la collection).
    private _s = _x; private _parts = []; private _tab = toString [9]; private _i = _s find _tab;
    while { _i >= 0 } do { _parts pushBack (_s select [0, _i]); _s = _s select [_i + 1]; _i = _s find _tab; };
    _parts pushBack _s;
    if (count _parts < 2) then { continue };
    _meta pushBack [
        _parts select 0,
        _parts select 1,
        if (count _parts > 3) then { _parts select 3 } else { "" },
        _parts param [9, ""]
    ];
} forEach _lines;

if (_onlyIds isNotEqualTo []) then {
    _meta = _meta select { (_x select 0) in _onlyIds };
};

if (_meta isEqualTo []) exitWith {
    [
        if (_onlyIds isEqualTo []) then { "Aucune tenue dans la communauté." } else { "Cette tenue n’est plus disponible." },
        "arsenal",
        "info",
        true
    ] call comspec_overwatch_connect_fnc_announce;
    false
};

missionNamespace setVariable ["COMSPEC_ArsenalCloudMeta", _meta, false];
missionNamespace setVariable ["COMSPEC_ArsenalPullBusy", true, false];

private _merged = [] call comspec_overwatch_connect_fnc_arsenalLocalLoadouts;
private _names = _merged apply { toLower (_x select 0) };
private _pulled = 0;
private _same = 0;
// Tenues réellement importées (clé = nom en minuscules) : seules elles sont écrites dans le profil, à la fin.
private _writes = createHashMap;
private _skippedDense = 0;

{
    _x params ["_id", "_name", "", ["_siteSha", ""]];
    private _loadout = [_id] call comspec_overwatch_connect_fnc_arsenalCloudLoadout;
    if (_loadout isEqualTo []) then {
        if ((missionNamespace getVariable ["COMSPEC_ArsenalCloudLoadoutError", ""]) isEqualTo "too_large") then {
            _skippedDense = _skippedDense + 1;
        };
        continue;
    };

    private _idx = _names find toLower _name;
    private _copy = false;
    if (_idx >= 0 && {_keepLocal}) then {
        // Même règle que le panneau (arsenalOverlayRefresh) : texte identique, empreinte du site, ou registre.
        private _localLo = [(_merged select _idx) select 1] call comspec_overwatch_connect_fnc_arsenalNormalizeLoadout;
        private _localSha = [str _localLo] call comspec_overwatch_connect_fnc_arsenalHash;
        private _identical = ((str _localLo) isEqualTo (str _loadout))
            || {_siteSha isNotEqualTo "" && {_localSha isEqualTo _siteSha}}
            || {_siteSha isNotEqualTo "" && {(["get", _name] call comspec_overwatch_connect_fnc_arsenalLedger) isEqualTo [_siteSha, _localSha]}};
        if (_identical) then { _idx = -2; } else {
            _name = _name + " · Athena";
            _idx = _names find toLower _name;
            _copy = true;
        };
    };
    if (_idx isEqualTo -2) then { _same = _same + 1; continue; };
    if (_idx >= 0) then {
        _merged set [_idx, [_name, _loadout]];
    } else {
        _merged pushBack [_name, _loadout];
        _names pushBack toLower _name;
    };
    _writes set [toLower _name, [_name, _loadout]];
    _pulled = _pulled + 1;
    // Registre de synchro : la tenue importée est celle du site (sha du site) ; sha local tel qu'il sera relu.
    // Copie « · Athena » posée à côté de votre tenue : elle ne porte pas ce nom sur le site, pas de registre.
    if (_copy) then { continue; };
    ["set", _name, _siteSha, [str _loadout] call comspec_overwatch_connect_fnc_arsenalHash] call comspec_overwatch_connect_fnc_arsenalLedger;
} forEach _meta;

// Le chargement des tenues prend du temps (réseau) : le joueur a pu enregistrer ou supprimer une tenue dans ACE
// entre-temps. On relit donc la liste du profil maintenant et on n'y pose que les tenues importées, sans toucher
// au reste (une liste figée au début de l'import effaçait ses nouvelles tenues, et les entrées qu'on ne sait pas
// lire disparaissaient).
if (count _writes > 0) then {
    private _saved = profileNamespace getVariable ["ace_arsenal_saved_loadouts", []];
    if !(_saved isEqualType []) then { _saved = []; };
    private _done = createHashMap;
    {
        if ((_x isEqualType []) && {(_x param [0, ""]) isEqualType ""}) then {
            private _k = toLower (_x select 0);
            if (_k in _writes && {!(_k in _done)}) then { _saved set [_forEachIndex, _writes get _k]; _done set [_k, true]; };
        };
    } forEach _saved;
    { if !(_x in _done) then { _saved pushBack _y; }; } forEach _writes;
    profileNamespace setVariable ["ace_arsenal_saved_loadouts", _saved];
    saveProfileNamespace;
};
missionNamespace setVariable ["COMSPEC_ArsenalLastPullAt", diag_tickTime, false];
missionNamespace setVariable ["COMSPEC_ArsenalPullBusy", false, false];

private _msg = format ["%1 tenue(s) de la communauté ajoutée(s) à l’arsenal.", _pulled];
if (_same > 0) then {
    _msg = _msg + format [" %1 déjà identique(s).", _same];
};
if (_skippedDense > 0) then {
    _msg = _msg + format [" %1 trop dense(s) pour l’import en jeu.", _skippedDense];
};
[_msg, "arsenal", if (_pulled > 0 || {_same > 0}) then { "ok" } else { "warn" }, true] call comspec_overwatch_connect_fnc_announce;

if (_applyId != "") then {
    [_applyId] call comspec_overwatch_connect_fnc_arsenalApplyCloud;
};

_pulled > 0
