/*
    Téléphones ATAK ramassés par l'IA ennemie (serveur).
    - Ramassage : une IA ennemie à moins de comspec_atak_native_capture_radius m d'un téléphone de joueur (corps,
      joueur inconscient ou menotté, téléphone posé au sol, corps d'une IA qui en portait un) va le chercher et le prend.
      Le téléphone passe dans son inventaire et garde son numéro et son IMEI (on peut le retrouver par GÉOLOC).
    - Exploitation : tant que la compromission n'est pas validée, le téléphone volé reste sur le réseau du camp et voit
      le BFT : le groupe du porteur (et jusqu'à 3 groupes alliés à moins de comspec_atak_native_capture_share m)
      apprend la position des joueurs dont la balise BFT est active à moins de comspec_atak_native_capture_hunt_radius m
      et part les traquer (point de passage « chercher et détruire », réglage comspec_atak_native_capture_hunt).
      Code de verrouillage : l'IA met comspec_atak_native_capture_crack s à le casser. Téléphone effacé avant la capture :
      rien d'exploitable. Le réseau voit le téléphone se connecter : dernière position connue envoyée au camp.
    - Alerte : les ATAK du camp reçoivent « Compromission suspectée » (fn_compromise), avec l'IMEI et le numéro.
      Un chef de groupe, le propriétaire ou un Zeus valide (ou rejette) depuis l'app Alertes. Validée : le téléphone
      est exclu du réseau (plus d'exploitation), Athena révoque le certificat du terminal (COMSPEC Link
      « CompromiseTerminal », état « compromised »), et le propriétaire reçoit un nouvel IMEI sur son prochain téléphone.
    Mission : groupe ou unité IA avec la variable COMSPEC_ATAK_NoPickup (ne ramasse pas) ou COMSPEC_ATAK_NoHunt (ne traque pas).
    État partagé : missionNamespace COMSPEC_ATAK_CaptureList (public) = [[id, camp, indicatif, numéro, IMEI, grille de capture,
      heure, état ("suspected" | "validated" | "dismissed"), validé par, Athena ("" | "pending" | "ok" | "offline"),
      UID du propriétaire, dernière grille vue, dernière position vue, effacé, porteur actif, position de capture]...]
    Params : [action, arguments]
      "init" (postInit serveur)   "tick" (3 s)   "exploit" (5 s)   "dropped" [porte-objet]
      "validate" [id, unité]   "dismiss" [id, unité]   "athena" [id, ok, unité]
      "local" [mode, objet, valeur] : sur la machine où l'objet est local ("strip" | "give" | "move" | "pick" | "hunt")
*/
params [["_act", "tick"], ["_arg", []]];
private _now = [time, serverTime] select isMultiplayer;
private _sideOf = {
    params ["_s"];
    switch (_s) do { case "WEST": { west }; case "EAST": { east }; case "GUER": { resistance }; case "CIV": { civilian }; default { sideUnknown }; }
};
private _publish = {
    private _l = [];
    {
        private _c = _y;
        private _t = _c get "tag";
        _l pushBack [_c get "id", _t param [1, ""], _t param [2, ""], _t param [3, ""], _t param [4, ""], _c get "grid", _c get "hour",
            _c get "state", _c get "by", _c get "athena", _t param [0, ""], _c get "lastGrid", _c get "lastPos", _c get "wiped",
            !isNull (_c get "carrier") && {alive (_c get "carrier")}, _c get "pos"];
    } forEach (missionNamespace getVariable ["COMSPEC_ATAK_Captures", createHashMap]);
    _l sort true;
    missionNamespace setVariable ["COMSPEC_ATAK_CaptureList", _l select [((count _l) - 20) max 0, 20], true];
};
private _toSide = {
    params ["_side", "_kind", "_id"];
    private _l = missionNamespace getVariable ["COMSPEC_ATAK_CaptureList", []];
    private _i = _l findIf { (_x select 0) isEqualTo _id };
    private _row = if (_i >= 0) then { _l select _i } else { [] };
    private _to = allPlayers select { (str side group _x) isEqualTo _side };
    if (_to isNotEqualTo [] && {_row isNotEqualTo []}) then { ["comspec_atak_native_compromise", [_kind, _row], _to] call CBA_fnc_targetEvent; };
};
private _items = {
    params ["_o", "_kind"];
    private _cat = [] call comspec_atak_native_fnc_deviceCatalog;
    if (_kind isEqualTo "holder") exitWith { (itemCargo _o) select { (toLower _x) in _cat } };
    ((assignedItems _o) + (items _o)) select { (toLower _x) in _cat }
};

switch (_act) do {
    case "init": {
        if (!isServer) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_Captures", createHashMap];
        missionNamespace setVariable ["COMSPEC_ATAK_CapGo", createHashMap];
        missionNamespace setVariable ["COMSPEC_ATAK_CapHolders", []];
        missionNamespace setVariable ["COMSPEC_ATAK_CaptureList", [], true];
        [{ ["tick"] call comspec_atak_native_fnc_captureServer; }, 3] call CBA_fnc_addPerFrameHandler;
        [{ ["exploit"] call comspec_atak_native_fnc_captureServer; }, 5] call CBA_fnc_addPerFrameHandler;
    };
    case "dropped": {
        _arg params [["_h", objNull]];
        if (isNull _h) exitWith {};
        private _l = missionNamespace getVariable ["COMSPEC_ATAK_CapHolders", []];
        _l pushBackUnique _h;
        missionNamespace setVariable ["COMSPEC_ATAK_CapHolders", _l select { !isNull _x }];
    };
    case "local": {
        _arg params [["_mode", ""], ["_o", objNull], ["_v", ""]];
        if (isNull _o || {!local _o}) exitWith {};
        switch (_mode) do {
            case "strip": {
                if (_v in (assignedItems _o)) then { _o unlinkItem _v; } else { _o removeItem _v; };
            };
            case "give": {
                if (_o canAdd _v) then { _o addItem _v; };
                _o playActionNow "PutDown";
                _o doFollow (leader group _o);
            };
            case "move": { _o doMove _v; };
            case "hunt": {
                _v params [["_targets", []], ["_hunt", true]];
                private _g = group _o;
                { _g reveal [_x select 0, 2.5]; } forEach _targets;
                if (!_hunt || {_targets isEqualTo []} || {_g getVariable ["COMSPEC_ATAK_NoHunt", false]}) exitWith {};
                private _best = _targets apply { [(_x select 1) distance2D (leader _g), _x select 1] };
                _best sort true;
                private _p = (_best select 0) select 1;
                if (((_g getVariable ["COMSPEC_ATAK_HuntPos", [-1e5, -1e5, 0]]) distance2D _p) < 80) exitWith {};
                _g setVariable ["COMSPEC_ATAK_HuntPos", _p];
                [_g] call CBA_fnc_clearWaypoints;
                private _wp = _g addWaypoint [_p, 25];
                _wp setWaypointType "SAD";
                _wp setWaypointBehaviour "AWARE";
                _wp setWaypointCombatMode "RED";
                _wp setWaypointSpeed "FULL";
                _g setCurrentWaypoint _wp;
            };
        };
    };
    case "tick": {
        if (!isServer || {!(missionNamespace getVariable ["comspec_atak_native_capture_enabled", true])}) exitWith {};
        private _caps = missionNamespace getVariable ["COMSPEC_ATAK_Captures", createHashMap];
        private _go = missionNamespace getVariable ["COMSPEC_ATAK_CapGo", createHashMap];
        private _R = (missionNamespace getVariable ["comspec_atak_native_capture_radius", 100]) max 5;
        // Sources : [objet, genre, étiquette du téléphone, id de capture déjà ouverte]
        private _src = [];
        {
            private _tag = _x getVariable ["COMSPEC_ATAK_PhoneTag", []];
            if (_tag isNotEqualTo []) then { _src pushBack [_x, "unit", _tag, ""]; continue };
            // Corps d'une IA qui portait un téléphone volé : un autre peut le reprendre.
            private _cid = _x getVariable ["COMSPEC_ATAK_CapOf", ""];
            if (_cid isNotEqualTo "" && {_cid in _caps}) then { _src pushBack [_x, "unit", (_caps get _cid) get "tag", _cid]; };
        } forEach allDeadMen;
        {
            if (alive _x && {(_x getVariable ["ACE_isUnconscious", false]) || {_x getVariable ["ace_captives_isHandcuffed", false]} || {(lifeState _x) isEqualTo "INCAPACITATED"}}) then {
                private _tag = _x getVariable ["COMSPEC_ATAK_PhoneTag", []];
                if (_tag isNotEqualTo []) then { _src pushBack [_x, "unit", _tag, ""]; };
            };
        } forEach allPlayers;
        {
            private _own = _x getVariable ["COMSPEC_ATAK_PhoneOwner", objNull];
            private _tag = _x getVariable ["COMSPEC_ATAK_PhoneTag", []];
            if (_tag isEqualTo [] && {!isNull _own}) then { _tag = _own getVariable ["COMSPEC_ATAK_PhoneTag", []]; };
            if (_tag isNotEqualTo []) then { _src pushBack [_x, "holder", _tag, ""]; };
        } forEach ((missionNamespace getVariable ["COMSPEC_ATAK_CapHolders", []]) select { !isNull _x });
        {
            _x params ["_o", "_kind", "_tag", "_cid"];
            private _it = [_o, _kind] call _items;
            if (_it isEqualTo []) then { continue };
            private _key = netId _o;
            private _sideO = [_tag param [1, ""]] call _sideOf;
            if (_sideO isEqualTo sideUnknown) then { continue };
            private _pos = getPosATL _o;
            (_go getOrDefault [_key, []]) params [["_ai", objNull], ["_t0", 0]];
            if (!isNull _ai && {!alive _ai || {(_now - _t0) > 120} || {(_ai distance _o) > _R * 1.5} || {(vehicle _ai) isNotEqualTo _ai}}) then {
                _ai setVariable ["COMSPEC_ATAK_CapGoing", nil];
                _ai = objNull;
                _go deleteAt _key;
            };
            if (isNull _ai) then {
                private _cand = (_pos nearEntities [["CAManBase"], _R]) select {
                    alive _x && {!isPlayer _x} && {(vehicle _x) isEqualTo _x} && {(lifeState _x) in ["HEALTHY", "INJURED"]}
                    && {!(_x getVariable ["ACE_isUnconscious", false])} && {!captive _x}
                    && {[_sideO, side group _x] call BIS_fnc_sideIsEnemy}
                    && {!(_x getVariable ["COMSPEC_ATAK_NoPickup", false])} && {!((group _x) getVariable ["COMSPEC_ATAK_NoPickup", false])}
                    && {isNil { _x getVariable "COMSPEC_ATAK_CapGoing" }}
                };
                if (_cand isEqualTo []) exitWith {};
                _cand = _cand apply { [_x distance _o, _x] };
                _cand sort true;
                _ai = (_cand select 0) select 1;
                _go set [_key, [_ai, _now]];
                _ai setVariable ["COMSPEC_ATAK_CapGoing", _key];
                ["comspec_atak_native_capLocal", ["move", _ai, _pos], _ai] call CBA_fnc_targetEvent;
            };
            if (isNull _ai || {(_ai distance _o) > 4}) then { continue };
            // Ramassé.
            private _cls = _it select 0;
            _go deleteAt _key;
            _ai setVariable ["COMSPEC_ATAK_CapGoing", nil];
            if (_kind isEqualTo "holder") then {
                (getItemCargo _o) params [["_cl", []], ["_cn", []]];
                private _i = _cl findIf { _x isEqualTo _cls };
                if (_i >= 0) then {
                    _cn set [_i, (_cn select _i) - 1];
                    clearItemCargoGlobal _o;
                    { if ((_cn select _forEachIndex) > 0) then { _o addItemCargoGlobal [_x, _cn select _forEachIndex]; }; } forEach _cl;
                };
            } else {
                ["comspec_atak_native_capLocal", ["strip", _o, _cls], _o] call CBA_fnc_targetEvent;
                // Le corps n'a plus de téléphone à donner.
                if !(alive _o) then { _o setVariable ["COMSPEC_ATAK_PhoneTag", nil, true]; _o setVariable ["COMSPEC_ATAK_CapOf", nil, true]; };
            };
            ["comspec_atak_native_capLocal", ["give", _ai, _cls], _ai] call CBA_fnc_targetEvent;
            // Le téléphone garde son identité : on peut le géolocaliser par son numéro ou son IMEI.
            _ai setVariable ["COMSPEC_ATAK_Ident", [_tag param [3, ""], _tag param [4, ""], ""], true];
            private _grid = [_pos, 8] call comspec_atak_native_fnc_gridRef;
            if (_cid isNotEqualTo "") then {
                private _c = _caps get _cid;
                _c set ["carrier", _ai];
                _ai setVariable ["COMSPEC_ATAK_CapOf", _cid, true];
                ["INFO", "CAPTURE", format ["%1 : téléphone repris par %2 (%3)", _cid, typeOf _ai, _grid]] call comspec_atak_native_fnc_log;
            } else {
                private _seq = (missionNamespace getVariable ["COMSPEC_ATAK_CapSeq", 0]) + 1;
                missionNamespace setVariable ["COMSPEC_ATAK_CapSeq", _seq];
                private _id = format ["CAP%1", _seq];
                private _crack = [0, (missionNamespace getVariable ["comspec_atak_native_capture_crack", 120]) max 0] select (_tag param [6, false]);
                private _c = createHashMapFromArray [
                    ["id", _id], ["tag", _tag], ["carrier", _ai], ["state", "suspected"], ["by", ""], ["athena", ""],
                    ["grid", _grid], ["hour", [dayTime, "HH:MM"] call BIS_fnc_timeToString], ["pos", [_pos select 0, _pos select 1, 0]],
                    ["t", _now], ["exploitAt", _now + 10 + _crack], ["wiped", _tag param [7, false]],
                    ["lastGrid", ""], ["lastPos", []], ["lastAct", -1e9], ["tried", []]
                ];
                _caps set [_id, _c];
                _ai setVariable ["COMSPEC_ATAK_CapOf", _id, true];
                call _publish;
                [_tag param [1, ""], "new", _id] call _toSide;
                ["INFO", "CAPTURE", format ["%1 : téléphone de %2 (IMEI %3) ramassé par %4 %5 (%6)", _id, _tag param [2, ""], _tag param [4, ""], side group _ai, typeOf _ai, _grid]] call comspec_atak_native_fnc_log;
            };
        } forEach _src;
    };
    case "exploit": {
        if (!isServer) exitWith {};
        private _caps = missionNamespace getVariable ["COMSPEC_ATAK_Captures", createHashMap];
        if ((count _caps) isEqualTo 0) exitWith {};
        private _every = (missionNamespace getVariable ["comspec_atak_native_capture_refresh", 30]) max 5;
        private _huntR = (missionNamespace getVariable ["comspec_atak_native_capture_hunt_radius", 3000]) max 100;
        private _share = missionNamespace getVariable ["comspec_atak_native_capture_share", 1500];
        private _hunt = missionNamespace getVariable ["comspec_atak_native_capture_hunt", true];
        private _changed = false;
        {
            private _c = _y;
            if ((_c get "state") isEqualTo "validated") then { continue };
            private _ai = _c get "carrier";
            if (isNull _ai || {!alive _ai}) then { continue };
            if (_now < (_c get "exploitAt") || {_c get "wiped"}) then { continue };
            if ((_now - (_c getOrDefault ["lastRun", -1e9])) < _every) then { continue };
            _c set ["lastRun", _now];
            private _side = (_c get "tag") param [1, ""];
            // Le téléphone volé se connecte : le réseau du camp voit sa cellule.
            private _p = getPosATL _ai;
            _c set ["lastPos", [_p select 0, _p select 1, 0]];
            _c set ["lastGrid", [_p, 6] call comspec_atak_native_fnc_gridRef];
            _changed = true;
            if ((_now - (_c get "lastAct")) >= 300) then {
                _c set ["lastAct", _now];
                call _publish;
                [_side, "activity", _c get "id"] call _toSide;
            };
            private _targets = (allPlayers select {
                alive _x && {(str side group _x) isEqualTo _side} && {_x getVariable ["COMSPEC_ATAK_Beacon", false]}
                && {(_x distance2D _ai) < _huntR} && {[_x] call comspec_atak_native_fnc_hasDevice}
            }) apply { [_x, getPosATL _x] };
            if (_targets isEqualTo []) then { continue };
            private _grps = [group _ai];
            if (_share > 0) then {
                private _near = (allGroups select {
                    _x isNotEqualTo (group _ai) && {(side _x) isEqualTo (side group _ai)} && {alive leader _x} && {!isPlayer leader _x}
                    && {((leader _x) distance2D _ai) < _share} && {!(_x getVariable ["COMSPEC_ATAK_NoHunt", false])}
                }) apply { [(leader _x) distance2D _ai, _x] };
                _near sort true;
                _grps append ((_near select [0, 3]) apply { _x select 1 });
            };
            { ["comspec_atak_native_capLocal", ["hunt", leader _x, [_targets, _hunt]], leader _x] call CBA_fnc_targetEvent; } forEach _grps;
        } forEach _caps;
        if (_changed) then { call _publish; };
    };
    case "validate";
    case "dismiss": {
        _arg params [["_id", ""], ["_by", objNull]];
        private _c = (missionNamespace getVariable ["COMSPEC_ATAK_Captures", createHashMap]) getOrDefault [_id, createHashMap];
        if ((count _c) isEqualTo 0 || {isNull _by}) exitWith {};
        private _side = (_c get "tag") param [1, ""];
        if ((str side group _by) isNotEqualTo _side && {isNull (getAssignedCuratorLogic _by)}) exitWith {};
        if ((_c get "state") isEqualTo "validated") exitWith {};
        private _who = [_by] call comspec_atak_native_fnc_unitCallsign;
        _c set ["by", _who];
        if (_act isEqualTo "dismiss") exitWith {
            _c set ["state", "dismissed"];
            call _publish;
            [_side, "dismissed", _id] call _toSide;
        };
        _c set ["state", "validated"];
        _c set ["athena", "pending"];
        _c set ["tried", [getPlayerUID _by]];
        call _publish;
        [_side, "validated", _id] call _toSide;
        ["comspec_atak_native_compromise", ["revoke", [_id, (_c get "tag") param [5, ""], (_c get "tag") param [2, ""]]], _by] call CBA_fnc_targetEvent;
        // Propriétaire : nouvel IMEI sur son prochain téléphone.
        private _uid = (_c get "tag") param [0, ""];
        private _i = allPlayers findIf { (getPlayerUID _x) isEqualTo _uid };
        if (_i >= 0) then { ["comspec_atak_native_compromise", ["ownerReset", [_id]], allPlayers select _i] call CBA_fnc_targetEvent; };
        ["INFO", "CAPTURE", format ["%1 : compromission validée par %2", _id, _who]] call comspec_atak_native_fnc_log;
    };
    case "athena": {
        _arg params [["_id", ""], ["_ok", false], ["_from", objNull]];
        private _c = (missionNamespace getVariable ["COMSPEC_ATAK_Captures", createHashMap]) getOrDefault [_id, createHashMap];
        if ((count _c) isEqualTo 0) exitWith {};
        if (_ok) exitWith { _c set ["athena", "ok"]; call _publish; };
        // Pas de liaison Athena chez celui qui a validé : on essaie chez le propriétaire, puis chez un chef du camp.
        private _tried = _c getOrDefault ["tried", []];
        if (!isNull _from) then { _tried pushBackUnique getPlayerUID _from; };
        _c set ["tried", _tried];
        private _side = (_c get "tag") param [1, ""];
        private _uid = (_c get "tag") param [0, ""];
        private _cands = allPlayers select { (str side group _x) isEqualTo _side && {!((getPlayerUID _x) in _tried)} };
        private _k = _cands findIf { (getPlayerUID _x) isEqualTo _uid };
        if (_k < 0) then { _k = _cands findIf { (leader group _x) isEqualTo _x }; };
        if (_k < 0) exitWith { _c set ["athena", "offline"]; call _publish; };
        private _next = _cands select _k;
        ["comspec_atak_native_compromise", ["revoke", [_id, (_c get "tag") param [5, ""], (_c get "tag") param [2, ""]]], _next] call CBA_fnc_targetEvent;
    };
};
true
