/*
    Wave Relay : réseau maillé (MANET) entre les téléphones du camp.
      "scan"      : recalcule le maillage (cache 3 s, "force" pour ignorer le cache). Renvoie un HashMap :
                    nodes [[objet, position antenne ASL, indicatif, type ("me", "node", "gw")]...],
                    links [[index A, index B, qualité %]...], hops [sauts jusqu'à la passerelle, -1 si coupé],
                    me (mon index), gw (index de la passerelle ou -1), gwLabel.
                    Lien si la distance est sous la portée (COMSPEC_ATAK_MeshRange, 3 000 m par défaut),
                    portée réduite à 35 % quand le relief coupe la ligne entre les deux antennes.
                    Passerelle : relais Overwatch le plus proche s'il existe et est intact, sinon le chef de groupe.
      "talkgroup" : choisit le talkgroup data (variable publique COMSPEC_ATAK_Talkgroup) quand ACRE2 est absent.
      "channel"   : [id radio ACRE, canal] change le canal d'une radio ACRE2.
      "mapLinks"  : affiche / masque les liens du maillage sur la carte.
*/
params [["_act", "scan"], ["_k", ""], ["_v", 0]];
private _render = { if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "WAVERELAY") then { [{ ["WAVERELAY"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }; };
switch (_act) do {
    case "scan": {
        private _cache = uiNamespace getVariable ["COMSPEC_ATAK_Mesh", []];
        if ((count _cache) isEqualTo 2 && {diag_tickTime - (_cache select 0) < 3} && {_k isNotEqualTo "force"}) exitWith { _cache select 1 };
        private _range = missionNamespace getVariable ["COMSPEC_ATAK_MeshRange", 3000];
        private _side = side group player;
        private _units = (allPlayers select { alive _x && {side group _x isEqualTo _side} && {_x isNotEqualTo player} && {[_x] call comspec_atak_native_fnc_hasDevice} }) select [0, 63];
        private _ant = { params ["_u"]; (eyePos _u) vectorAdd [0, 0, 0.5] };
        private _nodes = [[player, [player] call _ant, [player, true] call comspec_atak_native_fnc_unitCallsign, "me", _range]];
        { _nodes pushBack [_x, [_x] call _ant, [_x, true] call comspec_atak_native_fnc_unitCallsign, "node", _range]; } forEach _units;
        // Passerelle : relais Overwatch intact, sinon chef de groupe (s'il porte un téléphone).
        private _gw = -1;
        private _gwLabel = "";
        if (!isNil "comspec_overwatch_connect_fnc_getNearestAtakRelay") then {
            private _r = [] call comspec_overwatch_connect_fnc_getNearestAtakRelay;
            if (_r isEqualType createHashMap && {(count _r) > 0} && {_r getOrDefault ["alive", false]}) then {
                private _o = _r get "obj";
                _gwLabel = _r getOrDefault ["name", "Relais"];
                _nodes pushBack [_o, (getPosASL _o) vectorAdd [0, 0, 4], _gwLabel, "gw", _r getOrDefault ["range", _range]];
                _gw = (count _nodes) - 1;
            };
        };
        if (_gw < 0) then {
            private _lead = leader group player;
            _gw = _nodes findIf { (_x select 0) isEqualTo _lead };
            if (_gw >= 0) then { _gwLabel = format ["Chef de groupe (%1)", (_nodes select _gw) select 2]; };
        };
        // Liens : portée de la paire = la plus petite des deux, réduite si le relief masque, pluie en léger malus.
        private _links = [];
        private _adj = _nodes apply { [] };
        private _n = count _nodes;
        for "_i" from 0 to (_n - 2) do {
            for "_j" from (_i + 1) to (_n - 1) do {
                private _a = _nodes select _i;
                private _b = _nodes select _j;
                private _pa = _a select 1;
                private _pb = _b select 1;
                private _d = _pa distance _pb;
                private _eff = (_a select 4) min (_b select 4);
                if (_d > _eff) then { continue };
                if (terrainIntersectASL [_pa, _pb]) then { _eff = _eff * 0.35; };
                if (_d > _eff) then { continue };
                private _q = round (100 * (1 - (_d / _eff) ^ 2) * (1 - 0.15 * rain));
                if (_q < 1) then { continue };
                _links pushBack [_i, _j, _q];
                (_adj select _i) pushBack _j;
                (_adj select _j) pushBack _i;
            };
        };
        // Sauts jusqu'à la passerelle : parcours en largeur depuis elle.
        private _hops = _nodes apply { -1 };
        if (_gw >= 0) then {
            _hops set [_gw, 0];
            private _queue = [_gw];
            while { (count _queue) > 0 } do {
                private _c = _queue deleteAt 0;
                {
                    if ((_hops select _x) < 0) then { _hops set [_x, (_hops select _c) + 1]; _queue pushBack _x; };
                } forEach (_adj select _c);
            };
        };
        private _res = createHashMapFromArray [["nodes", _nodes], ["links", _links], ["hops", _hops], ["me", 0], ["gw", _gw], ["gwLabel", _gwLabel], ["range", _range]];
        uiNamespace setVariable ["COMSPEC_ATAK_Mesh", [diag_tickTime, _res]];
        _res
    };
    case "talkgroup": {
        player setVariable ["COMSPEC_ATAK_Talkgroup", _k, true];
        ["INFO", format ["Talkgroup %1", _k], 3, 20] call comspec_atak_native_fnc_notify;
        call _render;
    };
    case "channel": {
        if (isNil "acre_api_fnc_setRadioChannel") exitWith {};
        private _ch = (round _v) max 1;
        private _ok = [_k, _ch] call acre_api_fnc_setRadioChannel;
        if (_ok isEqualType true && {!_ok}) then { ["WARNING", format ["Canal %1 indisponible sur cette radio", _ch], 3, 20] call comspec_atak_native_fnc_notify; };
        [{ call _this; }, _render, 0.3] call CBA_fnc_waitAndExecute;
    };
    case "mapLinks": {
        profileNamespace setVariable ["COMSPEC_ATAK_MeshOnMap", !(profileNamespace getVariable ["COMSPEC_ATAK_MeshOnMap", false])];
        call _render;
    };
};
