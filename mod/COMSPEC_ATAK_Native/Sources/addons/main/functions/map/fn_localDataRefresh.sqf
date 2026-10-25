if (!hasInterface || {isNull player}) exitWith {};

private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data",createHashMap];
private _units = createHashMap;
{
    private _remote = _y;
    private _age = diag_tickTime - (_remote getOrDefault ["updated",diag_tickTime]);
    private _freshness = switch (true) do {
        case (_age < 5): {"LIVE"};
        case (_age < 20): {"STALE"};
        case (_age < 60): {"LOST"};
        default {"OFFLINE"};
    };
    _remote set ["freshness",_freshness];
    _units set [_x,_remote];
} forEach (_data getOrDefault ["remoteUnits",createHashMap]);
// Filtre des alliés (réglage) : tous, mon groupe, ou mon rattachement ORBAT Athena (repli : groupe).
private _filter = profileNamespace getVariable ["COMSPEC_ATAK_AllyFilter", "ALL"];
private _myOrbat = player getVariable ["COMSPEC_ATAK_Orbat", ""];
private _hideAi = profileNamespace getVariable ["COMSPEC_ATAK_HideAllyAi", false];
private _keepFriend = {
    params ["_obj"];
    if (_obj isEqualTo player) exitWith { true };
    if (_hideAi && {!isPlayer _obj}) exitWith { false };
    if (_filter isEqualTo "ALL") exitWith { true };
    if (_filter isEqualTo "ORBAT" && {_myOrbat isNotEqualTo ""}) exitWith { (_obj getVariable ["COMSPEC_ATAK_Orbat", ""]) isEqualTo _myOrbat || {group _obj isEqualTo group player} };
    group _obj isEqualTo group player
};
// Unités Athena sans objet en jeu : on ne peut pas les filtrer, elles ne restent qu'en mode TOUS.
if (_filter isNotEqualTo "ALL") then { _units = createHashMap; };
private _athena = +_units;
// Liaison BFT : sans réseau je ne reçois plus rien ; un allié dont le téléphone est éteint, cassé ou sans signal
// (balise COMSPEC_ATAK_Beacon publiée par son téléphone) reste figé à sa dernière position connue.
private _last = uiNamespace getVariable ["COMSPEC_ATAK_BftLast", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_BftLast", _last];
private _meOn = (([] call comspec_atak_native_fnc_linkQuality) getOrDefault ["bars", 1]) > 0;
// Positions par points (fn_pli) : un allié joueur est affiché à son dernier point reçu (sa position GPS, avec son
// erreur), une IA alliée à une position relevée au même rythme ; l'âge du point donne en direct / ancien / perdu.
private _pli = ["pli"] call comspec_atak_native_fnc_netCond;
private _rate = (["pliRate"] call comspec_atak_native_fnc_netCond) max 1;
private _tNow = ["now"] call comspec_atak_native_fnc_pli;
private _liveAge = [5, _rate * 1.6 + 2] select _pli;
private _staleAge = [20, _rate * 4 + 10] select _pli;

// Personne n'apparaît sans téléphone ATAK : un allié (joueur ou IA) n'est visible que s'il en porte un
// (résultat gardé 10 s par unité). Les ennemis n'apparaissent (réglage « ennemis ») que repérés par mon camp.
private _devCache = uiNamespace getVariable ["COMSPEC_ATAK_DevCache", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_DevCache", _devCache];
// Unités disparues (morts, respawns, IA supprimées) retirées toutes les 30 s : ces deux tables grossissaient toute la partie.
if (isMultiplayer && {diag_tickTime - (uiNamespace getVariable ["COMSPEC_ATAK_BftPruneAt", 0]) > 30}) then {
    uiNamespace setVariable ["COMSPEC_ATAK_BftPruneAt", diag_tickTime];
    { if (isNull (objectFromNetId _x)) then { _devCache deleteAt _x; }; } forEach (keys _devCache);
    { if (isNull (objectFromNetId _x)) then { _last deleteAt _x; }; } forEach (keys _last);
};
private _carries = {
    params ["_u"];
    private _k = netId _u;
    if (_k in ["", "0:0"]) then { _k = str _u; };
    private _c = _devCache getOrDefault [_k, [false, -1e9]];
    if ((diag_tickTime - (_c select 1)) > 10) then { _c = [[_u] call comspec_atak_native_fnc_hasDevice, diag_tickTime]; _devCache set [_k, _c]; };
    _c select 0
};
private _showFriends = profileNamespace getVariable ["COMSPEC_ATAK_LayerFriends", true];
{
    private _obj = _x;
    if (isNull _obj) then { continue };
    if (side group _obj isEqualTo side group player && {_obj isNotEqualTo player} && {!_showFriends || {!([_obj] call _carries)}}) then { continue };
    if (side group _obj isEqualTo side group player && {!([_obj] call _keepFriend)}) then { continue };
    private _id = netId _obj;
    if (_id isEqualTo "0:0") then { _id = str _obj; };
    private _affiliation = if (side group _obj isEqualTo side group player) then {
        "friend"
    } else {
        switch (side group _obj) do {
            case east: {"hostile"};
            case civilian: {"neutral"};
            default {"unknown"};
        }
    };
    private _type = if (_obj isKindOf "CAManBase") then {
        "infantry"
    } else {
        if (_obj isKindOf "Air") then {"air"} else {if (_obj isKindOf "Tank") then {"armor"} else {"vehicle"}}
    };
    // Indicatif d'abord ; sinon celui de l'unité Athena au même endroit (qu'on masque pour ne pas doubler) ;
    // le pseudo ne sert que sans indicatif ni liaison.
    private _callsign = [_obj,true] call comspec_atak_native_fnc_unitCallsign;
    private _near = "";
    // Même indicatif qu'une piste Athena : même personne, une seule piste, même si la position Athena est en retard
    // de plus de 30 m (en véhicule, liaison lente). Sans ça le BFT montrait « Star 4-1 » deux fois.
    if (_callsign isNotEqualTo "" && {("athena:" + toLower _callsign) in _athena}) then { _near = "athena:" + toLower _callsign; };
    // Indicatif déjà trouvé : inutile de parcourir toutes les pistes Athena pour chaque unité amie.
    if (_near isEqualTo "") then {
        private _best = 30;
        {
            private _d = (_y getOrDefault ["position",[0,0,0]]) distance2D _obj;
            if (_d < _best) then { _best = _d; _near = _x; };
        } forEach _athena;
    };
    if (_near isNotEqualTo "") then {
        if (_callsign isEqualTo "") then { _callsign = (_athena get _near) getOrDefault ["callsign",""]; };
        _athena deleteAt _near;
        _units deleteAt _near;
    };
    if (_callsign isEqualTo "") then { _callsign = [_obj] call comspec_atak_native_fnc_unitCallsign; };
    private _pos = getPosASL _obj; private _hd = getDir _obj; private _upd = diag_tickTime; private _fr = "LIVE"; private _acc = 0;
    if (_obj isNotEqualTo player && {_affiliation isEqualTo "friend"}) then {
        private _on = !isPlayer _obj || {_obj getVariable ["COMSPEC_ATAK_Beacon", true]};
        if (_pli) then {
            if (isPlayer _obj) then {
                // Pas encore de point reçu (arrivée en cours de partie) : position en direct en attendant.
                private _p = _obj getVariable ["COMSPEC_ATAK_Pli", []];
                if (_p isEqualType [] && {(count _p) >= 3}) then {
                    _pos = _p select 0; _hd = _p select 1; _upd = diag_tickTime - ((_tNow - (_p select 2)) max 0); _acc = _p param [3, 0];
                };
            } else {
                private _prev = _last getOrDefault [_id, []];
                if ((count _prev) >= 3 && {diag_tickTime - (_prev select 2) < _rate}) then { _pos = _prev select 0; _hd = _prev select 1; _upd = _prev select 2; _acc = _prev param [3, 0]; } else {
                    // IA alliée : aucun point publié ; son point (et l'anneau de son équipe) prend l'erreur typique d'un
                    // GPS de téléphone à découvert, comme les joueurs, au lieu de sa position exacte.
                    if (["gps"] call comspec_atak_native_fnc_netCond) then {
                        private _e = (3 + random 5) * ((["gpsK"] call comspec_atak_native_fnc_netCond) max 0) / 100;
                        private _a = random 360;
                        _pos = _pos vectorAdd [_e * sin _a, _e * cos _a, 0];
                        _acc = round _e;
                    };
                };
            };
        };
        if (_meOn && _on) then { _last set [_id, [_pos, _hd, _upd, _acc]]; } else {
            if !(_id in _last) then { _last set [_id, [_pos, _hd, _upd, _acc]]; };
            (_last get _id) params ["_lp", "_lh", "_lt", ["_la", 0]];
            _pos = _lp; _hd = _lh; _upd = _lt; _acc = _la;
        };
        private _age = diag_tickTime - _upd;
        _fr = if (!_on) then { "OFFLINE" } else { switch (true) do { case (_age < _liveAge): { "LIVE" }; case (_age < _staleAge): { "STALE" }; default { "LOST" }; } };
    };
    _units set [_id,createHashMapFromArray [
        ["id",_id],["object",_obj],["self",_obj isEqualTo player],
        ["callsign",_callsign],
        ["position",_pos],["heading",_hd],
        ["affiliation",_affiliation],["type",_type],["freshness",_fr],["updated",_upd],["acc",_acc],
        ["icon",_obj getVariable ["COMSPEC_ATAK_Icon",""]],["orbat",_obj getVariable ["COMSPEC_ATAK_Orbat",""]]
    ]];
} forEach (allUnits select { alive _x && {side group _x isEqualTo side group player} });

// Aucune IA ennemie sur la carte, même repérée (choix de la communauté) : ni calcul local, ni contact relayé par Athena.
{ if ((_y getOrDefault ["affiliation", ""]) isEqualTo "hostile") then { _units deleteAt _x; }; } forEach +_units;
// Filtres du BFT (fn_bftFilter : IA, aériens, véhicules, pistes anciennes ou hors ligne, distance) : masquées sur la carte aussi.
{ if !([_y] call comspec_atak_native_fnc_bftFilter) then { _units deleteAt _x; }; } forEach +_units;
["units",_units] call comspec_atak_native_fnc_storeSet;

// Les marqueurs de la mission sont déjà dessinés par la carte Arma : on les garde pour la sélection
// et l'inspecteur (local = true) et on n'ajoute que les marqueurs Athena absents du jeu.
// Relu quand un marqueur change, ou tout de suite si un marqueur apparaît ou disparaît : refaire une table par
// marqueur quatre fois par seconde coûtait de plus en plus cher à mesure que les dessins s'accumulaient.
// Compteur des changements de marqueurs (EH MarkerCreated / Deleted / Updated) : rien de changé, rien à refaire
// (marqueurs Athena reçus, qui bougent sans événement : relus toutes les 2 s ; sans eux, sécurité toutes les 10 s).
private _mkRemote = count (_data getOrDefault ["remoteMarkers",createHashMap]);
private _mkSig = [count allMapMarkers, _mkRemote, missionNamespace getVariable ["COMSPEC_ATAK_MkRev", 0]];
private _mkPrev = uiNamespace getVariable ["COMSPEC_ATAK_MkRefresh", [[], -1e9]];
if ((_mkPrev select 0) isEqualTo _mkSig && {diag_tickTime - (_mkPrev select 1) < ([10, 2] select (_mkRemote > 0))}) exitWith {};
uiNamespace setVariable ["COMSPEC_ATAK_MkRefresh", [_mkSig, diag_tickTime]];
private _markers = createHashMap;
{
    private _name = _x;
    _markers set [_name,createHashMapFromArray [
        ["id",_name],["position",getMarkerPos _name],["text",markerText _name],
        ["type",markerType _name],["color",markerColor _name],["shape",markerShape _name],
        ["size",markerSize _name],["dir",markerDir _name],["alpha",markerAlpha _name],["local",true]
    ]];
} forEach allMapMarkers;
private _local = values _markers;
{
    private _r = _y;
    private _rp = _r getOrDefault ["position",[0,0,0]];
    private _rt = toLower (_r getOrDefault ["text",""]);
    if ((_local findIf { ((_x get "position") distance2D _rp) < 15 && {(toLower (_x get "text")) isEqualTo _rt || {_rt isEqualTo ""}} }) < 0) then {
        _markers set [_x,_r];
    };
} forEach (_data getOrDefault ["remoteMarkers",createHashMap]);
["markers",_markers] call comspec_atak_native_fnc_storeSet;
