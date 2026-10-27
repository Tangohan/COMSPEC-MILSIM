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
// Copie superficielle : les pistes ne sont que lues ici, seules les clés sont retirées (+_units recopiait toutes les pistes).
private _athena = createHashMapFromArray (_units toArray false);
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
// Piste Athena voisine d'une unité sans indicatif (IA surtout) : [clé, instant], recherchée au plus toutes les 5 s
// (la recherche parcourt toutes les pistes Athena pour chaque unité, quatre fois par seconde carte en main).
private _nearCache = uiNamespace getVariable ["COMSPEC_ATAK_NearCache", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_NearCache", _nearCache];
// Unités disparues (morts, respawns, IA supprimées) retirées toutes les 30 s : ces tables grossissaient toute la partie.
if (isMultiplayer && {diag_tickTime - (uiNamespace getVariable ["COMSPEC_ATAK_BftPruneAt", 0]) > 30}) then {
    uiNamespace setVariable ["COMSPEC_ATAK_BftPruneAt", diag_tickTime];
    { if (isNull (objectFromNetId _x)) then { _devCache deleteAt _x; }; } forEach (keys _devCache);
    { if (isNull (objectFromNetId _x)) then { _last deleteAt _x; }; } forEach (keys _last);
    { if (isNull (objectFromNetId _x)) then { _nearCache deleteAt _x; }; } forEach (keys _nearCache);
};
// Liste des alliés vivants relevée toutes les 2 s (parcours de allUnits, ennemis compris) ; les positions restent
// relues à chaque passage, et un allié mort entre deux relevés est écarté dans la boucle.
private _side = side group player;
private _fl = uiNamespace getVariable ["COMSPEC_ATAK_FriendList", [[], -1e9, sideUnknown, objNull]];
if (diag_tickTime - (_fl select 1) > 2 || {(_fl select 2) isNotEqualTo _side} || {(_fl select 3) isNotEqualTo player}) then {
    _fl = [allUnits select { alive _x && {side group _x isEqualTo _side} }, diag_tickTime, _side, player];
    uiNamespace setVariable ["COMSPEC_ATAK_FriendList", _fl];
};
private _carries = {
    params ["_u"];
    private _k = netId _u;
    if (_k in ["", "0:0"]) then { _k = str _u; };
    private _c = _devCache getOrDefault [_k, [false, -1e9]];
    // Échéances étalées (10 à 14 s) : toutes les unités ne sont pas revérifiées dans le même passage.
    if ((diag_tickTime - (_c select 1)) > 10) then { _c = [[_u] call comspec_atak_native_fnc_hasDevice, diag_tickTime + random 4]; _devCache set [_k, _c]; };
    _c select 0
};
private _showFriends = profileNamespace getVariable ["COMSPEC_ATAK_LayerFriends", true];
{
    private _obj = _x;
    if (isNull _obj || {!alive _obj}) then { continue };
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
    if (_near isEqualTo "" && {(count _athena) > 0}) then {
        private _nc = _nearCache getOrDefault [_id, ["", -1e9]];
        if (diag_tickTime - (_nc select 1) < 5) then {
            if ((_nc select 0) in _athena) then { _near = _nc select 0; };
        } else {
            private _best = 30;
            {
                private _d = (_y getOrDefault ["position",[0,0,0]]) distance2D _obj;
                if (_d < _best) then { _best = _d; _near = _x; };
            } forEach _athena;
            _nearCache set [_id, [_near, diag_tickTime]];
        };
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
            // IA équipée (fn_aiAtak) : ses points viennent du serveur, comme ceux d'un joueur, identiques pour tous.
            if (isPlayer _obj || {(_obj getVariable ["COMSPEC_ATAK_Pli", []]) isNotEqualTo []}) then {
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
} forEach (_fl select 0);

// Aucune IA ennemie sur la carte, même repérée (choix de la communauté) : ni calcul local, ni contact relayé par Athena.
// Filtres du BFT (fn_bftFilter : IA, aériens, véhicules, pistes anciennes ou hors ligne, distance) : masquées sur la carte aussi.
// Une seule passe sur les clés (+_units recopiait en profondeur toutes les pistes, deux fois par passage).
{
    private _e = _units get _x;
    if ((_e getOrDefault ["affiliation", ""]) isEqualTo "hostile" || {!([_e] call comspec_atak_native_fnc_bftFilter)}) then { _units deleteAt _x; };
} forEach (keys _units);
// Signature fournie : la table des unités change à chaque passage (positions), la comparer par « str » ne servait qu'à
// convertir en texte toutes les unités du camp, une ou quatre fois par seconde.
["units",_units,-1,str diag_tickTime] call comspec_atak_native_fnc_storeSet;

// Les marqueurs de la mission sont déjà dessinés par la carte Arma : on les garde pour la sélection
// et l'inspecteur (local = true) et on n'ajoute que les marqueurs Athena absents du jeu.
// Seuls les marqueurs changés sont relus (carnet COMSPEC_ATAK_MkDirtyLd tenu par les EH MarkerCreated / Deleted /
// Updated, XEH_postInitClient) ; tous au premier passage et toutes les 30 s. Avant, un seul marqueur déplacé (piste
// Athena redessinée par Overwatch, marqueur suivi) faisait refaire une table par marqueur de la mission, puis comparer
// chaque marqueur Athena à chaque marqueur du jeu : la saccade régulière, carte ouverte ou téléphone porté.
private _mkRemote = _data getOrDefault ["remoteMarkers",createHashMap];
private _remRev = (_data getOrDefault ["revisions",createHashMap]) getOrDefault ["remoteMarkers",0];
private _dirty = missionNamespace getVariable ["COMSPEC_ATAK_MkDirtyLd", createHashMap];
private _mk = missionNamespace getVariable ["COMSPEC_ATAK_MkLocal", []];
private _full = (count _mk) < 4 || {diag_tickTime > (_mk select 0)} || {"" in _dirty};
if (!_full && {(count _dirty) isEqualTo 0} && {_remRev isEqualTo (_mk select 2)}) exitWith {};
missionNamespace setVariable ["COMSPEC_ATAK_MkDirtyLd", createHashMap];
private _entry = {
    createHashMapFromArray [
        ["id",_this],["position",getMarkerPos _this],["text",markerText _this],
        ["type",markerType _this],["color",markerColor _this],["shape",markerShape _this],
        ["size",markerSize _this],["dir",markerDir _this],["alpha",markerAlpha _this],["local",true]
    ]
};
private _local = createHashMap;
if (_full) then {
    { _local set [_x, _x call _entry]; } forEach allMapMarkers;
    _mk = [diag_tickTime + 30, _local, _remRev, (_mk param [3, 0]) + 1];
} else {
    _local = _mk select 1;
    { if ((markerShape _x) isEqualTo "") then { _local deleteAt _x; } else { _local set [_x, _x call _entry]; }; } forEach (keys _dirty);
    _mk set [2, _remRev];
    _mk set [3, (_mk select 3) + 1];
};
missionNamespace setVariable ["COMSPEC_ATAK_MkLocal", _mk];
private _markers = createHashMap;
_markers merge _local;
if ((count _mkRemote) > 0) then {
    // Doublon Athena / jeu : même texte (ou sans texte) à moins de 15 m. Marqueurs du jeu rangés par cases de 15 m :
    // chaque marqueur Athena ne regarde que les 9 cases autour de lui, au lieu de tous les marqueurs du jeu.
    private _cells = createHashMap;
    {
        private _p = _y get "position";
        private _k = [floor ((_p select 0) / 15), floor ((_p select 1) / 15)];
        private _c = _cells getOrDefault [_k, []];
        if ((count _c) isEqualTo 0) then { _cells set [_k, _c]; };
        _c pushBack _y;
    } forEach _local;
    {
        private _r = _y;
        private _rp = _r getOrDefault ["position",[0,0,0]];
        private _rt = toLower (_r getOrDefault ["text",""]);
        private _cx = floor ((_rp select 0) / 15);
        private _cy = floor ((_rp select 1) / 15);
        private _dup = false;
        for "_i" from -1 to 1 do {
            for "_j" from -1 to 1 do {
                if (!_dup) then {
                    _dup = ((_cells getOrDefault [[_cx + _i, _cy + _j], []]) findIf { ((_x get "position") distance2D _rp) < 15 && {(toLower (_x get "text")) isEqualTo _rt || {_rt isEqualTo ""}} }) >= 0;
                };
            };
        };
        if (!_dup) then { _markers set [_x,_r]; };
    } forEach _mkRemote;
};
["markers",_markers,-1,str (_mk select 3)] call comspec_atak_native_fnc_storeSet;
