/*
    Recharge les données du panneau Tenues Athena puis redessine la liste (arsenalOverlayFill).
    Mes tenues : profil ACE local. Communauté : ListWardrobes, liste gardée sur ce PC (Sync\wardrobes.json) ;
    rechargée depuis le site seulement sur Actualiser ou après un envoi (COMSPEC_ArsenalForceList).
    Les champs vides de ListWardrobes sont conservés (splitString les fusionnait : la collection
    vide décalait les colonnes et le drapeau favori « 0 » devenait un nom de collection).

    État de synchro, sans rien renvoyer : chaque tenue locale est comparée à la vôtre sur le site (même nom, colonne
    « mine » de ListWardrobes) par son empreinte sha256 (arsenalHash, colonne payload_sha256), et au registre des
    derniers envois / imports (arsenalLedger) pour savoir de quel côté elle a changé.
      Mes tenues : new (pas sur le site), gone (retirée du site), synced, local (modifiée ici), remote (modifiée sur
                   le site), diff (les deux), comm (importée d'un autre membre), unknown (COMSPEC Link < 2.0.88),
                   offline (Athena injoignable).
      Communauté : relation à mon arsenal in / diff / out, et « nouvelle » si jamais vue (COMSPEC_ArsenalSeenIds).
    Lignes locales : [nom, nom court, collection, données, état, sha local]
    Lignes communauté : [id, nom, nom court, collection, auteur, date, favori, octets, à moi, sha, relation, nouvelle]
*/
params [["_display", displayNull, [displayNull]]];
if (isNull _display) exitWith {};
private _grp = _display getVariable ["COMSPEC_ArsenalOverlay", controlNull];
if (isNull _grp) exitWith {};

private _split = {
    params ["_s"];
    private _tab = toString [9];
    private _out = [];
    private _i = _s find _tab;
    while { _i >= 0 } do { _out pushBack (_s select [0, _i]); _s = _s select [_i + 1]; _i = _s find _tab; };
    _out pushBack _s;
    _out apply { trim _x }
};
private _leafOf = {
    params ["_name", "_coll"];
    private _p = _coll + " - ";
    private _leaf = if (_coll isNotEqualTo "Autres" && {((toLower _name) find (toLower _p)) isEqualTo 0}) then { _name select [count _p] } else { _name };
    if ((trim _leaf) isEqualTo "") then { _name } else { _leaf }
};

// Communauté.
private _lines = missionNamespace getVariable ["COMSPEC_ArsenalWardrobeLines", []];
private _force = _display getVariable ["COMSPEC_ArsenalForceList", false];
if (_force || {!(_lines isEqualType [])} || {_lines isEqualTo []}) then {
    _lines = [["cache", "refresh"] select _force] call comspec_overwatch_connect_fnc_arsenalListWardrobes;
    missionNamespace setVariable ["COMSPEC_ArsenalWardrobeLines", _lines, false];
    missionNamespace setVariable ["COMSPEC_ArsenalWardrobeAt", diag_tickTime, false];
    _display setVariable ["COMSPEC_ArsenalForceList", false];
};
private _cloud = [];
private _hasMine = false;
{
    if (_x isEqualTo "") then { continue };
    private _f = [_x] call _split;
    _f params [["_id", ""], ["_name", ""], ["", ""], ["_coll", ""], ["_fav", "0"], ["_bytes", "0"], ["_upd", ""], ["_owner", ""], ["_mine", ""], ["_sha", ""]];
    if (_id isEqualTo "" || {_name isEqualTo ""}) then { continue };
    // Colonnes « mine » et « sha256 » : COMSPEC Link 2.0.88 et plus.
    if ((count _f) >= 9) then { _hasMine = true; };
    private _c = [_name, _coll] call comspec_overwatch_connect_fnc_arsenalCollectionName;
    _cloud pushBack [_id, _name, [_name, _c] call _leafOf, _c, _owner, _upd, _fav isEqualTo "1", parseNumber _bytes, _mine isEqualTo "1", toLower _sha, "out", false];
} forEach _lines;
private _offline = false;
if (_lines isEqualTo []) then {
    private _probe = [["COMSPECExtension", "ListWardrobes", ["0", "cache"]] call comspec_overwatch_connect_fnc_extAwait] call comspec_overwatch_connect_fnc_extResult;
    _offline = !(_probe isEqualType "") || {(_probe find "OK|") != 0};
};

// Vos tenues sur le site, par nom ; et toutes les tenues par nom (importées d'un autre membre).
private _mineByKey = createHashMap;
private _anyByKey = createHashMap;
{
    private _k = toLower trim (_x select 1);
    if (!_hasMine || {_x select 8}) then { if !(_k in _mineByKey) then { _mineByKey set [_k, _x]; }; };
    if !(_k in _anyByKey) then { _anyByKey set [_k, _x]; };
} forEach _cloud;

// Mes tenues.
private _ledger = ["map"] call comspec_overwatch_connect_fnc_arsenalLedger;
private _local = [];
private _localByKey = createHashMap;
{
    _x params ["_name", "_data"];
    private _c = [_name] call comspec_overwatch_connect_fnc_arsenalCollectionName;
    private _k = toLower trim _name;
    private _lo = [_data] call comspec_overwatch_connect_fnc_arsenalNormalizeLoadout;
    private _sha = if (_lo isEqualTo []) then { "" } else { [str _lo] call comspec_overwatch_connect_fnc_arsenalHash };
    private _site = _mineByKey getOrDefault [_k, []];
    private _led = _ledger getOrDefault [_k, []];
    private _state = switch (true) do {
        case (_offline): { "offline" };
        case (_site isNotEqualTo []): {
            private _cs = _site select 9;
            switch (true) do {
                case (_sha isEqualTo "" || {_cs isEqualTo ""}): { "unknown" };
                case (_sha isEqualTo _cs): { ["set", _name, _cs, _sha] call comspec_overwatch_connect_fnc_arsenalLedger; "synced" };
                case (_led isEqualTo []): { "diff" };
                default {
                    private _here = _sha isNotEqualTo (_led select 1);
                    private _there = _cs isNotEqualTo (_led select 0);
                    switch (true) do {
                        case (!_here && {!_there}): { "synced" };
                        case (_here && {!_there}): { "local" };
                        case (!_here && {_there}): { "remote" };
                        default { "diff" };
                    };
                };
            };
        };
        case (_k in _anyByKey): { "comm" };
        case (_led isNotEqualTo []): { "gone" };
        default { "new" };
    };
    _local pushBack [_name, [_name, _c] call _leafOf, _c, _data, _state, _sha];
    _localByKey set [_k, _sha];
} forEach ([] call comspec_overwatch_connect_fnc_arsenalLocalLoadouts);

// Communauté : relation à mon arsenal, et nouvelles depuis la dernière visite (premier passage : tout est « vu »).
private _seen = profileNamespace getVariable ["COMSPEC_ArsenalSeenIds", nil];
private _first = isNil "_seen" || {!(_seen isEqualType [])};
if (_first && {!_offline} && {_cloud isNotEqualTo []}) then {
    profileNamespace setVariable ["COMSPEC_ArsenalSeenIds", _cloud apply { _x select 0 }];
    _seen = _cloud apply { _x select 0 };
};
if (isNil "_seen" || {!(_seen isEqualType [])}) then { _seen = []; };
private _seenMap = createHashMapFromArray (_seen apply { [_x, true] });
{
    private _k = toLower trim (_x select 1);
    if (_k in _localByKey) then {
        private _ls = _localByKey get _k;
        private _cs = _x select 9;
        // Importée telle quelle : le texte relu peut différer du texte du site, le registre fait foi.
        private _led = _ledger getOrDefault [_k, []];
        _x set [10, ["diff", "in"] select (_ls isEqualTo "" || {_cs isEqualTo ""} || {_ls isEqualTo _cs} || {_led isEqualTo [_cs, _ls]})];
    };
    _x set [11, !((_x select 0) in _seenMap)];
} forEach _cloud;

missionNamespace setVariable ["COMSPEC_ArsenalSeenPending", _cloud apply { _x select 0 }, false];
_grp setVariable ["COMSPEC_ArsenalLocalRows", _local];
_grp setVariable ["COMSPEC_ArsenalCloudRows", _cloud];
_grp setVariable ["COMSPEC_ArsenalCloudMeta", _cloud apply { [_x select 0, _x select 1, _x select 3] }];
_grp setVariable ["COMSPEC_ArsenalOffline", _offline];
_grp setVariable ["COMSPEC_ArsenalHasHash", _hasMine && {!(uiNamespace getVariable ["COMSPEC_ArsenalHashOff", false])}];
// Date de la liste du site gardée sur ce PC (heure du PC) ; COMSPEC Link plus ancien : heure de chargement.
private _info = ["COMSPECExtension" callExtension ["WardrobesCacheInfo", []]] call comspec_overwatch_connect_fnc_extResult;
private _savedAt = if ((_info isEqualType "") && {(_info find "OK|") isEqualTo 0}) then { ((_info select [3]) splitString toString [9]) param [0, ""] } else { "" };
_grp setVariable ["COMSPEC_ArsenalLoadedAt", [_savedAt, [dayTime, "HH:MM"] call BIS_fnc_timeToString] select (_savedAt isEqualTo "")];
[] call comspec_overwatch_connect_fnc_saveProfile;
[_display] call comspec_overwatch_connect_fnc_arsenalOverlayFill;
