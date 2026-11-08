/*
    Guerre électronique du maillage (lot 11, réglage comspec_atak_native_mesh_ew) : émettre, c'est se montrer. Le chiffrement cache le contenu, pas l'émission.
    Complète l'app Guerre électronique (fn_ewAction), sans la remplacer. Aucun bilan de lien du graphe : les relevés lisent ce que les nœuds publient.
    Réservé à un rôle spécialiste (décision 14, réglage mesh_ew_role) : variable d'unité COMSPEC_ATAK_EwOperator ou rôle de mission contenant guerre, brouill, sigint, elint.
      "allowed"  -> vrai si le joueur peut utiliser goniomètre et analyseur
      "spectrum" -> 16 lignes [canal, émetteurs entendus, niveau 0-3, brouillé] (cache 5 s) ; ne dit ni le camp ni le contenu
      "df" [canal] : relevé au goniomètre (objet COMSPEC_ATAK_Goniometer) : direction approximative, erreur plus grande à faible marge. 10 émetteurs au plus.
      "cross" [position 1, cap 1, position 2, cap 2] -> point de croisement ou [] (sans effet, testé par fn_devMeshTest)
      "triangulate" : croise les deux derniers relevés de positions différentes, marqueur local
      "mode" [0 normal | 1 puissance réduite | 2 écoute seule] ; "impose" : le chef impose son mode à son groupe ; "tick" : canal de secours
      "jam" [position, rayon m, durée s, canal (0 large bande), "" | "gps"] (serveur ou Zeus)
      "rows" -> lignes de l'app Guerre électronique, onglet GONIO
*/
params [["_act", ""], ["_arg", []], ["_caller", objNull]];
private _state = { uiNamespace getVariable ["COMSPEC_ATAK_MpuSt", createHashMap] };
switch (_act) do {
    case "allowed": {
        if !((missionNamespace getVariable ["comspec_atak_native_mesh_ew", true]) isEqualTo true) exitWith { false };
        if !((missionNamespace getVariable ["comspec_atak_native_mesh_ew_role", true]) isEqualTo true) exitWith { true };
        if (player getVariable ["COMSPEC_ATAK_EwOperator", false]) exitWith { true };
        private _r = (toLower (roleDescription player)) + " ";
        (["guerre", "brouill", "sigint", "elint", "electronic warfare"] findIf { (_r find _x) >= 0 }) >= 0
    };
    case "spectrum": {
        private _c = uiNamespace getVariable ["COMSPEC_ATAK_MeshSpectrum", []];
        if ((count _c) isEqualTo 2 && {diag_tickTime - (_c select 0) < 5}) exitWith { _c select 1 };
        private _count = [];
        _count resize 16;
        _count = _count apply { 0 };
        private _lvl = +_count;
        private _r = missionNamespace getVariable ["comspec_atak_native_mpu5_range", 6000];
        {
            private _d = _x distance player;
            if (_x isEqualTo player || {_d > _r}) then { continue };
            (["node", _x] call comspec_atak_native_fnc_meshRead) params [["_key", ""], "", "", "", "", ["_a", 5]];
            if (_key isEqualTo "" || {_a <= 0}) then { continue };
            private _i = (parseNumber (_key select [1])) - 1;
            _count set [_i, (_count select _i) + 1];
            _lvl set [_i, (_lvl select _i) max (([1, 2, 3] select ((floor (3 * (1 - _d / _r))) min 2)))];
        } forEach allPlayers;
        private _tNow = [time, serverTime] select isMultiplayer;
        private _jams = (missionNamespace getVariable ["COMSPEC_ATAK_Jammers", []]) select { ((_x param [4, 1e9]) >= _tNow) && {(_x param [8, ""]) isNotEqualTo "gps"} };
        private _out = [];
        for "_i" from 1 to 16 do {
            private _ch = _i;
            private _jam = (_jams findIf {
                private _jp = _x param [0, [0, 0, 0]];
                if (_jp isEqualType objNull) then { _jp = getPosATL _jp; };
                private _jc = _x param [7, 0];
                (player distance2D _jp) < (_x param [1, 0]) && {_jc isEqualTo 0 || {_jc isEqualTo _ch}}
            }) >= 0;
            _out pushBack [_i, _count select (_i - 1), _lvl select (_i - 1), _jam];
        };
        uiNamespace setVariable ["COMSPEC_ATAK_MeshSpectrum", [diag_tickTime, _out]];
        _out
    };
    case "df": {
        if !(["allowed"] call comspec_atak_native_fnc_meshEW) exitWith { ["WARNING", "Réservé au spécialiste guerre électronique", 4, 20] call comspec_atak_native_fnc_notify; false };
        if !("COMSPEC_ATAK_Goniometer" in (items player)) exitWith { ["WARNING", "Il faut un goniomètre", 4, 20] call comspec_atak_native_fnc_notify; false };
        private _ch = _arg param [0, 1];
        private _key = format ["C%1", _ch];
        private _eye = eyePos player;
        private _r = missionNamespace getVariable ["comspec_atak_native_mpu5_range", 6000];
        private _side = side group player;
        private _found = [];
        {
            if (_x isEqualTo player || {side group _x isEqualTo _side} || {!alive _x}) then { continue };
            (["node", _x] call comspec_atak_native_fnc_meshRead) params [["_k", ""], "", "", "", "", ["_a", 5]];
            if (_k isEqualTo _key && {_a > 0} && {(_x distance player) < 2 * _r}) then { _found pushBack [_x distance player, _x]; };
        } forEach allPlayers;
        _found sort true;
        private _log = uiNamespace getVariable ["COMSPEC_ATAK_MeshDfLog", []];
        {
            private _u = _x select 1;
            private _m = ([_eye, (eyePos _u) vectorAdd [0, 0, 0.4], _r, "fast"] call comspec_atak_native_fnc_radioLink) select 0;
            if (_m < -6) then { continue };
            // Erreur : 3° à bonne marge, jusqu'à 18° à la limite. Choix de jeu.
            private _err = 3 + 15 * (1 - (((_m max 0) / 20) min 1));
            private _b = ((player getDir _u) + (random (2 * _err)) - _err + 360) mod 360;
            _log pushBack [round time, getPosASL player, _b, _err, _ch];
        } forEach (_found select [0, 10]);
        while { (count _log) > 12 } do { _log deleteAt 0; };
        uiNamespace setVariable ["COMSPEC_ATAK_MeshDfLog", _log];
        [format ["Goniomètre, canal %1 : %2 émetteur(s)", _ch, count _found], 4] call comspec_atak_native_fnc_toast;
        [{ ["EW"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
        true
    };
    case "cross": {
        _arg params ["_p1", "_b1", "_p2", "_b2"];
        private _d1 = [sin _b1, cos _b1];
        private _d2 = [sin _b2, cos _b2];
        private _det = (_d1 select 0) * (_d2 select 1) - (_d1 select 1) * (_d2 select 0);
        if ((abs _det) < 0.05) exitWith { [] };
        private _dx = (_p2 select 0) - (_p1 select 0);
        private _dy = (_p2 select 1) - (_p1 select 1);
        private _t = (_dx * (_d2 select 1) - _dy * (_d2 select 0)) / _det;
        private _u = (_dx * (_d1 select 1) - _dy * (_d1 select 0)) / _det;
        if (_t <= 0 || {_u <= 0}) exitWith { [] };
        [(_p1 select 0) + _t * (_d1 select 0), (_p1 select 1) + _t * (_d1 select 1)]
    };
    case "triangulate": {
        private _log = uiNamespace getVariable ["COMSPEC_ATAK_MeshDfLog", []];
        private _n = count _log;
        if (_n < 2) exitWith { ["INFO", "Il faut deux relevés", 4, 20] call comspec_atak_native_fnc_notify; false };
        private _last = _log select (_n - 1);
        private _i = _log findIf { ((_x select 1) distance2D (_last select 1)) > 50 && {(_x select 4) isEqualTo (_last select 4)} };
        if (_i < 0) exitWith { ["INFO", "Faites un relevé à plus de 50 m du précédent", 4, 20] call comspec_atak_native_fnc_notify; false };
        private _o = _log select _i;
        private _pt = ["cross", [_o select 1, _o select 2, _last select 1, _last select 2]] call comspec_atak_native_fnc_meshEW;
        if (_pt isEqualTo []) exitWith { ["INFO", "Relevés presque parallèles : déplacez-vous", 4, 20] call comspec_atak_native_fnc_notify; false };
        private _mk = createMarkerLocal ["COMSPEC_ATAK_MeshDf", _pt];
        if (_mk isEqualTo "") then { _mk = "COMSPEC_ATAK_MeshDf"; _mk setMarkerPosLocal _pt; };
        _mk setMarkerTypeLocal "mil_unknown";
        _mk setMarkerColorLocal "ColorOrange";
        _mk setMarkerTextLocal format ["Émetteur, canal %1", _last select 4];
        ["INFO", format ["Émetteur estimé : %1", mapGridPosition _pt], 6, 30] call comspec_atak_native_fnc_notify;
        true
    };
    case "mode": {
        private _st = call _state;
        private _m = ((_arg param [0, 0]) max 0) min 2;
        _st set ["mode", _m];
        uiNamespace setVariable ["COMSPEC_ATAK_MpuMeshAt", -100];
        ["INFO", ["MPU5 : émission normale", "MPU5 : puissance réduite", "MPU5 : écoute seule, elle ne relaie plus et n'émet plus"] select _m, 4, 20] call comspec_atak_native_fnc_notify;
    };
    case "impose": {
        if ((leader group player) isNotEqualTo player) exitWith { false };
        private _m = (call _state) getOrDefault ["mode", 0];
        ["comspec_atak_native_meshMode", [_m], (units group player) select { isPlayer _x }] call CBA_fnc_targetEvent;
        true
    };
    case "init": {
        if (!hasInterface) exitWith {};
        ["comspec_atak_native_meshMode", { ["mode", _this] call comspec_atak_native_fnc_meshEW; }] call CBA_fnc_addEventHandler;
    };
    case "tick": {
        // Canal de secours : brouillage d'un canal subi quelques secondes, réseau perdu : bascule une fois (les autres font de même de leur côté).
        private _bk = missionNamespace getVariable ["comspec_atak_native_mesh_backup_channel", 0];
        if !(_bk isEqualType 0) exitWith {};
        if (_bk <= 0) exitWith {};
        private _st = call _state;
        private _ch = _st getOrDefault ["chan", 1];
        if (_ch isEqualTo (round _bk)) exitWith {};
        private _tNow = [time, serverTime] select isMultiplayer;
        private _jam = ((missionNamespace getVariable ["COMSPEC_ATAK_Jammers", []]) findIf {
            private _jp = _x param [0, [0, 0, 0]];
            if (_jp isEqualType objNull) then { _jp = getPosATL _jp; };
            (_x param [7, 0]) isEqualTo _ch && {(_x param [4, 1e9]) >= _tNow} && {(player distance2D _jp) < (_x param [1, 0])}
        }) >= 0;
        if (!_jam) exitWith { uiNamespace setVariable ["COMSPEC_ATAK_MeshJamSince", -1]; };
        private _since = uiNamespace getVariable ["COMSPEC_ATAK_MeshJamSince", -1];
        if (_since < 0) exitWith { uiNamespace setVariable ["COMSPEC_ATAK_MeshJamSince", diag_tickTime]; };
        if (diag_tickTime - _since < (missionNamespace getVariable ["comspec_atak_native_mesh_backup_delay", 10])) exitWith {};
        uiNamespace setVariable ["COMSPEC_ATAK_MeshJamSince", -1];
        _st set ["chan", round _bk];
        uiNamespace setVariable ["COMSPEC_ATAK_MpuMeshAt", -100];
        ["WARNING", format ["Canal %1 brouillé : bascule sur le canal de secours %2", _ch, round _bk], 6, 40] call comspec_atak_native_fnc_notify;
    };
    case "jam": {
        if (!isServer) exitWith { ["comspec_atak_native_meshEwSrv", [player, "jam", _arg]] call CBA_fnc_serverEvent; };
        _arg params [["_pos", [0, 0, 0]], ["_rad", 500], ["_dur", 120], ["_chan", 0], ["_kind", ""]];
        private _tNow = [time, serverTime] select isMultiplayer;
        // [position, rayon, id, camp épargné, fin, filtrage ami, force, canal, genre]
        private _l = (missionNamespace getVariable ["COMSPEC_ATAK_Jammers", []]) select { (_x param [4, 1e9]) >= _tNow };
        _l pushBack [_pos, (_rad max 10) min 20000, format ["mesh:%1", floor (random 1e6)], "", _tNow + ((_dur max 1) min 3600), false, 1, ((round _chan) max 0) min 16, _kind];
        missionNamespace setVariable ["COMSPEC_ATAK_Jammers", _l, true];
        ["log", ["jam", format ["Brouilleur : %1, canal %2, %3 m", [_kind, "large bande"] select (_kind isEqualTo "" && {_chan isEqualTo 0}), _chan, round _rad]]] call comspec_atak_native_fnc_meshGraph;
    };
    case "rows": {
        if !(["allowed"] call comspec_atak_native_fnc_meshEW) exitWith { [] };
        private _ch = uiNamespace getVariable ["COMSPEC_ATAK_MeshDfChan", 1];
        private _rows = [["section", "Analyseur de spectre", "Occupation des 16 canaux, ni camp ni contenu"]];
        _rows pushBack ["text", format ["<t size='0.8' font='EtelkaMonospacePro'>%1</t>", ((["spectrum"] call comspec_atak_native_fnc_meshEW) apply {
            _x params ["_i", "_c", "_l", "_j"];
            format ["C%1 %2 %3 émetteur(s)%4", _i, ["····", "▂···", "▂▄··", "▂▄▆·"] select _l, _c, ["", " BROUILLÉ"] select _j]
        }) joinString "<br/>"]];
        _rows pushBack ["section", "Goniomètre", format ["Canal %1", _ch]];
        _rows pushBack ["buttons", [
            ["CANAL −", compile format ["uiNamespace setVariable ['COMSPEC_ATAK_MeshDfChan', %1]; ['EW'] call comspec_atak_native_fnc_pageRender;", ((_ch + 14) mod 16) + 1]],
            ["CANAL +", compile format ["uiNamespace setVariable ['COMSPEC_ATAK_MeshDfChan', %1]; ['EW'] call comspec_atak_native_fnc_pageRender;", (_ch mod 16) + 1]],
            ["RELEVÉ", compile format ["['df', [%1]] call comspec_atak_native_fnc_meshEW;", _ch], true],
            ["CROISER", { ["triangulate"] call comspec_atak_native_fnc_meshEW; }]
        ]];
        {
            _x params ["_t", "", "_b", "_e", "_c"];
            _rows pushBack ["info", format ["t=%1, canal %2", _t, _c], format ["%1° ± %2°", round _b, round _e]];
        } forEach (uiNamespace getVariable ["COMSPEC_ATAK_MeshDfLog", []]);
        _rows
    };
};
