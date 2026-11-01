/*
    Recharge les données du panneau Tenues Athena puis redessine la liste (arsenalOverlayFill). Depuis un spawn.
    Mes tenues : profil ACE local. Communauté : ListWardrobes, liste gardée sur ce PC (Sync\wardrobes.json) ;
    rechargée depuis le site seulement sur Actualiser ou après un envoi (COMSPEC_ArsenalForceList).
    Les champs vides de ListWardrobes sont conservés (splitString les fusionnait : la collection
    vide décalait les colonnes et le drapeau favori « 0 » devenait un nom de collection).

    État de synchro, sans rien renvoyer : chaque tenue locale est rattachée à la vôtre sur le site par son nom
    (arsenalAceStore « key ») puis comparée par son CONTENU (arsenalHash : mêmes objets = même empreinte, quel que soit
    le format, l'ordre ou les infos étendues). Le contenu d'une tenue du site est connu une fois chargée
    (COMSPEC_ArsenalSiteContent, ou colonne 16 de ListWardrobes si le site la fournit un jour) : celles qu'il faut
    comparer sont chargées en arrière-plan (au plus 40 à la fois, une fois par partie) puis la liste se met à jour
    (« Vérification… » entre-temps). Le registre des derniers envois / imports (arsenalLedger) dit de quel côté une
    tenue a changé. Une tenue locale identique à l'une des vôtres sur le site sous un autre nom est « Sur le site ».
      Mes tenues : new (pas sur le site), gone (retirée du site), synced, local (modifiée ici), remote (modifiée sur
                   le site), diff (les deux), comm (importée d'un autre membre), copy (ancienne copie « · Athena »),
                   check (vérification en cours), unknown (COMSPEC Link < 2.0.88), offline (Athena injoignable).
      Communauté : relation à mon arsenal in / diff / check / out, et « nouvelle » si jamais vue (COMSPEC_ArsenalSeenIds).
    Lignes locales : [nom, nom court, collection, données, état, empreinte, auteur, favori, miseAvant, obligatoire, pastille,
                      avatar, cover, siteId]
    Lignes communauté : [id, nom, nom court, collection, auteur, date, favori, octets, à moi, sha du site, relation,
                         nouvelle, miseAvant, obligatoire, pastille, avatar, cover, empreinte de contenu fournie]
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
private _keyOf = { ["key", _this] call comspec_overwatch_connect_fnc_arsenalAceStore };

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
    _f params [
        ["_id", ""], ["_name", ""], ["_gap", ""], ["_coll", ""], ["_fav", "0"], ["_bytes", "0"], ["_upd", ""],
        ["_owner", ""], ["_mine", ""], ["_sha", ""], ["_feat", "0"], ["_mand", "0"], ["_badge", ""], ["_avatar", ""], ["_cover", ""],
        ["_content", ""]
    ];
    if (_id isEqualTo "" || {_name isEqualTo ""}) then { continue };
    // Colonnes « mine » et « sha256 » : COMSPEC Link 2.0.88 et plus.
    if ((count _f) >= 9) then { _hasMine = true; };
    private _c = [_name, _coll] call comspec_overwatch_connect_fnc_arsenalCollectionName;
    _cloud pushBack [
        _id, _name, [_name, _c] call _leafOf, _c, _owner, _upd, _fav isEqualTo "1", parseNumber _bytes,
        _mine isEqualTo "1", toLower _sha, "out", false,
        _feat isEqualTo "1", _mand isEqualTo "1", _badge, _avatar, _cover,
        if ((count _content) == 64) then { toLower _content } else { "" }
    ];
} forEach _lines;
private _offline = false;
if (_lines isEqualTo []) then {
    private _probe = [["COMSPECExtension", "ListWardrobes", ["0", "cache"]] call comspec_overwatch_connect_fnc_extAwait] call comspec_overwatch_connect_fnc_extResult;
    _offline = !(_probe isEqualType "") || {(_probe find "OK|") != 0};
};

// Contenu des tenues du site déjà chargées (arsenalCloudLoadout), et celles déjà tentées cette partie.
private _content = missionNamespace getVariable ["COMSPEC_ArsenalSiteContent", createHashMap];
private _tried = missionNamespace getVariable ["COMSPEC_ArsenalContentTried", nil];
if (isNil "_tried") then { _tried = createHashMap; missionNamespace setVariable ["COMSPEC_ArsenalContentTried", _tried, false]; };
private _scOf = { params ["_r"]; private _c = _r param [17, ""]; if (_c isNotEqualTo "") exitWith { _c }; _content getOrDefault [(_r select 0) + "|" + (_r select 9), ""] };
private _need = createHashMap;
private _ask = { params ["_r"]; private _k = (_r select 0) + "|" + (_r select 9); if (!(_k in _tried) && {([_r] call _scOf) isEqualTo ""}) then { _need set [_k, [_r select 0, _r select 9]]; }; };

// Vos tenues sur le site, par nom et par contenu ; et toutes les tenues par nom (importées d'un autre membre).
private _mineByKey = createHashMap;
private _mineByContent = createHashMap;
private _anyByKey = createHashMap;
{
    private _k = (_x select 1) call _keyOf;
    if (!_hasMine || {_x select 8}) then {
        if !(_k in _mineByKey) then { _mineByKey set [_k, _x]; };
        private _sc = [_x] call _scOf;
        if (_sc isNotEqualTo "" && {!(_sc in _mineByContent)}) then { _mineByContent set [_sc, _x]; };
    };
    if !(_k in _anyByKey) then { _anyByKey set [_k, _x]; };
} forEach _cloud;

// Mes tenues.
private _ledger = ["map"] call comspec_overwatch_connect_fnc_arsenalLedger;
private _local = [];
private _localByKey = createHashMap;
private _anyNew = false;
{
    _x params ["_name", "_data"];
    private _c = [_name] call comspec_overwatch_connect_fnc_arsenalCollectionName;
    private _k = _name call _keyOf;
    private _lo = [_data] call comspec_overwatch_connect_fnc_arsenalNormalizeLoadout;
    private _sha = if (_lo isEqualTo []) then { "" } else { [_lo] call comspec_overwatch_connect_fnc_arsenalHash };
    private _site = _mineByKey getOrDefault [_k, []];
    private _led = _ledger getOrDefault [toLower trim _name, []];
    private _state = switch (true) do {
        case (_offline): { "offline" };
        case (_site isNotEqualTo []): {
            private _sr = _site select 9;
            private _sc = [_site] call _scOf;
            switch (true) do {
                case (_sha isEqualTo "" || {_sr isEqualTo ""}): { "unknown" };
                case (_sc isNotEqualTo "" && {_sha isEqualTo _sc}): {
                    if (_led isNotEqualTo [_sr, _sha]) then { ["set", _name, _sr, _sha] call comspec_overwatch_connect_fnc_arsenalLedger; };
                    "synced"
                };
                case (_led isEqualTo [_sr, _sha]): { "synced" };
                case (_sc isEqualTo "" && {!(((_site select 0) + "|" + _sr) in _tried)}): { [_site] call _ask; "check" };
                case (_led isEqualTo []): { "diff" };
                default {
                    private _here = _sha isNotEqualTo (_led select 1);
                    private _there = _sr isNotEqualTo (_led select 0);
                    switch (true) do {
                        case (_here && {!_there}): { "local" };
                        case (!_here && {_there}): { "remote" };
                        default { "diff" };
                    };
                };
            };
        };
        case (_k in _anyByKey): { "comm" };
        case (_sha isNotEqualTo "" && {_sha in _mineByContent}): { _site = _mineByContent get _sha; "synced" };
        case ((_k find " · athena") >= 0): { "copy" };
        case (_led isNotEqualTo []): { "gone" };
        default { _anyNew = true; "new" };
    };
    private _siteRow = if (_site isNotEqualTo []) then { _site } else { _anyByKey getOrDefault [_k, []] };
    private _owner = if (_siteRow isEqualTo []) then { "" } else { _siteRow select 4 };
    private _fav = if (_siteRow isEqualTo []) then { false } else { _siteRow select 6 };
    private _feat = if (_siteRow isEqualTo []) then { false } else { _siteRow param [12, false] };
    private _mand = if (_siteRow isEqualTo []) then { false } else { _siteRow param [13, false] };
    private _badge = if (_siteRow isEqualTo []) then { "" } else { _siteRow param [14, ""] };
    private _avatar = if (_siteRow isEqualTo []) then { "" } else { _siteRow param [15, ""] };
    private _cover = if (_siteRow isEqualTo []) then { "" } else { _siteRow param [16, ""] };
    private _siteId = if (_siteRow isEqualTo []) then { "" } else { _siteRow select 0 };
    _local pushBack [_name, [_name, _c] call _leafOf, _c, _data, _state, _sha, _owner, _fav, _feat, _mand, _badge, _avatar, _cover, _siteId];
    if !(_k in _localByKey) then { _localByKey set [_k, _sha]; };
} forEach ([] call comspec_overwatch_connect_fnc_arsenalLocalLoadouts);
// Une tenue « Pas sur le site » est peut-être l'une des vôtres sous un autre nom : contenu de vos tenues chargé.
if (_anyNew && {!_offline}) then { { if (!_hasMine || {_x select 8}) then { [_x] call _ask; }; } forEach _cloud; };

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
    private _k = (_x select 1) call _keyOf;
    if (_k in _localByKey) then {
        private _ls = _localByKey get _k;
        private _cs = _x select 9;
        private _sc = [_x] call _scOf;
        private _led = _ledger getOrDefault [toLower trim (_x select 1), []];
        _x set [10, switch (true) do {
            // Sans empreinte (COMSPEC Link ancien) : rattachée par le nom seulement.
            case (_ls isEqualTo "" || {_cs isEqualTo ""}): { "in" };
            case (_sc isNotEqualTo ""): { ["diff", "in"] select (_ls isEqualTo _sc) };
            case (_led isEqualTo [_cs, _ls]): { "in" };
            case (!(((_x select 0) + "|" + _cs) in _tried)): { [_x] call _ask; "check" };
            default { "diff" };
        }];
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
private _info = [(["COMSPECExtension", "WardrobesCacheInfo", []] call comspec_overwatch_connect_fnc_extDirect)] call comspec_overwatch_connect_fnc_extResult;
private _infoParts = if ((_info isEqualType "") && {(_info find "OK|") isEqualTo 0}) then { (_info select [3]) splitString toString [9] } else { [] };
private _savedAt = _infoParts param [0, ""];
private _localOk = (_infoParts param [2, "0"]) isEqualTo "1";
private _pathHint = _infoParts param [3, "Documents\\Arma 3 - COMSPEC\\Sync"];
_grp setVariable ["COMSPEC_ArsenalLoadedAt", [_savedAt, [dayTime, "HH:MM"] call BIS_fnc_timeToString] select (_savedAt isEqualTo "")];
_grp setVariable ["COMSPEC_ArsenalLocalSync", [_localOk, _pathHint, _savedAt]];
[] call comspec_overwatch_connect_fnc_saveProfile;
[_display] call comspec_overwatch_connect_fnc_arsenalOverlayFill;

// Tenues du site à comparer par leur contenu : chargées en arrière-plan (cache Sync de COMSPEC Link d'abord), puis
// la liste est redessinée sans réseau, la tenue choisie restant choisie.
if ((count _need) > 0 && {!_offline} && {(diag_tickTime - (missionNamespace getVariable ["COMSPEC_ArsenalContentBusy", -1e9])) > 120}) then {
    private _batch = (values _need) select [0, 40];
    { _tried set [(_x select 0) + "|" + (_x select 1), true]; } forEach _batch;
    missionNamespace setVariable ["COMSPEC_ArsenalContentBusy", diag_tickTime, false];
    [_display, _batch] spawn {
        params ["_d", "_batch"];
        private _got = 0;
        {
            if (isNull _d || {!(_d getVariable ["COMSPEC_ArsenalOverlayOpen", false])}) exitWith {};
            if (([_x select 0, _x select 1] call comspec_overwatch_connect_fnc_arsenalCloudLoadout) isNotEqualTo []) then { _got = _got + 1; };
        } forEach _batch;
        missionNamespace setVariable ["COMSPEC_ArsenalContentBusy", -1e9, false];
        if (!isNull _d && {_d getVariable ["COMSPEC_ArsenalOverlayOpen", false]}) then { ["redrawKeep"] call comspec_overwatch_connect_fnc_arsenalOverlayAction; };
    };
};
