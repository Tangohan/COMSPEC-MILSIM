/*
    Groupes de conversation du maillage (lot 5, décisions 7 et 8, réglage comspec_atak_native_mesh_groups) : 16 groupes.
    Variables de mission (facultatives, posées par le serveur, module Eden du lot 12) :
      COMSPEC_ATAK_MeshGroupTable = [[numéro 1-16, nom, nom de clé ("" : en clair)]...]
      COMSPEC_ATAK_MeshFreqTable  = [[fréquence (MHz ou texte), numéro de groupe]...] ; sans ligne : groupe déduit de la fréquence.
    Abonnement : par la fréquence de la radio pontée (automatique), ou par choix dans l'app Wave Relay. Écoute de plusieurs groupes, émission sur
    deux au plus. Publié dans COMSPEC_ATAK_Talkgroup (liste de numéros, sur changement, tick de 2 s).
    Voix : ACRE2 et TFAR filtrent déjà par fréquence ; ici, pas de rallonge sans groupe commun. Messages : un seul envoi par émetteur avec la
    liste des abonnés joignables. Filtre appliqué d'abord aux messages et à la voix (décision 8) : marqueurs, plans et positions ne changent pas.
      "on"                                 -> vrai si le réglage est coché
      "names" -> [nom du groupe 1, ...16]  "name" [numéro] -> nom     "keyOf" [numéro] -> nom de clé ou ""
      "ofFreq" [fréquence] -> numéro (table de mission, sinon déduit)
      "mine" -> mes groupes écoutés        "tx" -> groupes d'émission (2 au plus)
      "toggle" [numéro] ; "txToggle" [numéro] ; "tick" ; "init"
      "shared" [unité] -> vrai si un groupe est commun (vrai aussi si l'un des deux n'a rien publié : comportement d'origine)
      "send" [texte] ; "rx" [...] : réception ; "rows" -> lignes de l'app Wave Relay
*/
params [["_act", ""], ["_arg", []]];
switch (_act) do {
    case "on": { (missionNamespace getVariable ["comspec_atak_native_mesh_groups", true]) isEqualTo true };
    case "names": {
        private _out = ["CMD", "ALPHA", "BRAVO", "CHARLIE", "FEUX", "LOG", "SAN", "G8", "G9", "G10", "G11", "G12", "G13", "G14", "G15", "G16"];
        {
            if !(_x isEqualType []) then { continue };
            _x params [["_n", 0], ["_nm", ""]];
            if (_n isEqualType 0 && {_n >= 1} && {_n <= 16} && {_nm isEqualType ""} && {_nm isNotEqualTo ""}) then { _out set [(round _n) - 1, _nm select [0, 24]]; };
        } forEach (missionNamespace getVariable ["COMSPEC_ATAK_MeshGroupTable", []]);
        _out
    };
    case "name": { (["names"] call comspec_atak_native_fnc_meshGroups) param [(_arg param [0, 1]) - 1, "?"] };
    case "keyOf": {
        private _idx = _arg param [0, 0];
        private _rows = (missionNamespace getVariable ["COMSPEC_ATAK_MeshGroupTable", []]) select { _x isEqualType [] && {(_x param [0, 0]) isEqualTo _idx} };
        (_rows param [0, []]) param [2, ""]
    };
    case "ofFreq": {
        private _f = _arg param [0, 0];
        private _num = if (_f isEqualType "") then { parseNumber _f } else { _f };
        if (!(_num isEqualType 0) || {!finite _num} || {_num <= 0}) exitWith { 0 };
        private _i = (missionNamespace getVariable ["COMSPEC_ATAK_MeshFreqTable", []]) findIf {
            _x isEqualType [] && {private _t = _x param [0, 0]; abs (([_t, parseNumber _t] select (_t isEqualType "")) - _num) < 0.001}
        };
        if (_i >= 0) exitWith { (((missionNamespace getVariable "COMSPEC_ATAK_MeshFreqTable") select _i) param [1, 1]) };
        // Déduit : même fréquence, même groupe, sur tous les clients.
        ((round (_num * 1000)) mod 16) + 1
    };
    case "mine": {
        private _l = +(uiNamespace getVariable ["COMSPEC_ATAK_MeshSubs", []]);
        private _pub = player getVariable ["COMSPEC_ATAK_Talkgroup", []];
        if (_pub isEqualType []) then { { _l pushBackUnique _x; } forEach _pub; };
        _l
    };
    case "tx": { uiNamespace getVariable ["COMSPEC_ATAK_MeshTx", []] };
    case "toggle": {
        private _i = _arg param [0, 0];
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_MeshSubs", []];
        if (_i in _s) then { _s = _s - [_i]; } else { _s pushBack _i; };
        uiNamespace setVariable ["COMSPEC_ATAK_MeshSubs", _s];
        uiNamespace setVariable ["COMSPEC_ATAK_MeshTx", (uiNamespace getVariable ["COMSPEC_ATAK_MeshTx", []]) select { _x in _s }];
        ["tick"] call comspec_atak_native_fnc_meshGroups;
        [{ ["WAVERELAY"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
    };
    case "txToggle": {
        private _i = _arg param [0, 0];
        private _t = uiNamespace getVariable ["COMSPEC_ATAK_MeshTx", []];
        if (_i in _t) then { _t = _t - [_i]; } else {
            if ((count _t) >= 2) then { _t deleteAt 0; };
            _t pushBack _i;
        };
        uiNamespace setVariable ["COMSPEC_ATAK_MeshTx", _t];
        [{ ["WAVERELAY"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
    };
    case "tick": {
        if !(hasInterface) exitWith {};
        private _pub = +(uiNamespace getVariable ["COMSPEC_ATAK_MeshSubs", []]);
        // Radio pontée : son groupe s'ajoute tout seul.
        (missionNamespace getVariable ["COMSPEC_ATAK_VoiceMe", []]) params [["_me", []]];
        if (_me isNotEqualTo [] && {["on"] call comspec_atak_native_fnc_meshGroups}) then {
            private _f = if ((_me select 0) isEqualTo "acre") then { (["freq", [_me select 1]] call comspec_atak_native_fnc_mpu5) param [0, 0] } else { _me select 2 };
            private _g = ["ofFreq", [_f]] call comspec_atak_native_fnc_meshGroups;
            if (_g > 0) then { _pub pushBackUnique _g; };
        };
        _pub sort true;
        if (_pub isNotEqualTo (player getVariable ["COMSPEC_ATAK_Talkgroup", []])) then { player setVariable ["COMSPEC_ATAK_Talkgroup", _pub, true]; };
    };
    case "shared": {
        if !(["on"] call comspec_atak_native_fnc_meshGroups) exitWith { true };
        private _peer = ["talkgroup", _arg param [0, objNull]] call comspec_atak_native_fnc_meshRead;
        private _mine = ["mine"] call comspec_atak_native_fnc_meshGroups;
        _peer isEqualTo [] || {_mine isEqualTo []} || {(_peer arrayIntersect _mine) isNotEqualTo []}
    };
    case "init": {
        if (!hasInterface) exitWith {};
        ["comspec_atak_native_meshGroupMsg", { ["rx", _this] call comspec_atak_native_fnc_meshGroups; }] call CBA_fnc_addEventHandler;
    };
    case "send": {
        private _text = trim (_arg param [0, ""]);
        if (_text isEqualTo "") exitWith { false };
        _text = _text select [0, 400];
        private _tx = ["tx"] call comspec_atak_native_fnc_meshGroups;
        if (_tx isEqualTo []) exitWith { ["WARNING", "Choisissez d'abord un groupe d'émission", 4, 20] call comspec_atak_native_fnc_notify; false };
        private _real = ["active"] call comspec_atak_native_fnc_mpuLink;
        private _side = side group player;
        private _time = [dayTime, "HH:MM"] call BIS_fnc_timeToString;
        private _date = [] call comspec_atak_native_fnc_p2pDate;
        private _cs = [player, true] call comspec_atak_native_fnc_unitCallsign;
        {
            private _g = _x;
            // Abonnés joignables : le graphe en liaison réaliste, sinon les joueurs du camp avec un téléphone.
            private _to = allPlayers select {
                _x isNotEqualTo player && {alive _x} && {side group _x isEqualTo _side} && {_g in (["talkgroup", _x] call comspec_atak_native_fnc_meshRead)}
                    && {[[_x] call comspec_atak_native_fnc_hasDevice, (["peer", [_x]] call comspec_atak_native_fnc_meshGraph) isNotEqualTo []] select _real}
            };
            private _name = ["name", [_g]] call comspec_atak_native_fnc_meshGroups;
            private _peer = format ["GROUPE %1", _name];
            private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap];
            private _p2p = _data getOrDefault ["p2p", []];
            _p2p pushBack createHashMapFromArray [["peer", _peer], ["dir", "out"], ["body", _text], ["time", _time], ["date", _date], ["read", true], ["id", ""], ["status", ["FAILED", "SENT"] select (_to isNotEqualTo [])], ["sentAt", time]];
            _data set ["p2p", _p2p];
            if (_to isEqualTo []) then {
                ["INFO", format ["%1 : aucun abonné joignable", _peer], 4, 20] call comspec_atak_native_fnc_notify;
                continue
            };
            // Clé du groupe (lot 6) : étiquette et époque, jamais un secret. "" : en clair.
            private _kk = ["stamp", [_g]] call comspec_atak_native_fnc_meshKeys;
            [{
                params ["_msg", "_targets"];
                ["comspec_atak_native_meshGroupMsg", _msg, _targets] call CBA_fnc_targetEvent;
            }, [[_g, _peer, format ["%1 : %2", _cs, _text], _time, player, _date, _kk], _to], format ["Message %1", _peer], 1] call comspec_atak_native_fnc_netSend;
        } forEach _tx;
        true
    };
    case "rx": {
        _arg params [["_g", 0], ["_peer", ""], ["_body", ""], ["_time", "--:--"], ["_sender", objNull], ["_date", ""], ["_kk", []]];
        if !(_body isEqualType "") exitWith {};
        // Abonnement changé pendant l'envoi : message ignoré.
        if !(_g in (["mine"] call comspec_atak_native_fnc_meshGroups)) exitWith {};
        private _shown = ["open", [_kk, _body select [0, 440]]] call comspec_atak_native_fnc_meshKeys;
        [_peer, _shown, _time, "", _sender, _date, true] call comspec_atak_native_fnc_p2pReceive;
    };
    case "rows": {
        private _on = ["on"] call comspec_atak_native_fnc_meshGroups;
        if (!_on) exitWith { [] };
        private _mine = ["mine"] call comspec_atak_native_fnc_meshGroups;
        private _subs = uiNamespace getVariable ["COMSPEC_ATAK_MeshSubs", []];
        private _tx = ["tx"] call comspec_atak_native_fnc_meshGroups;
        private _side = side group player;
        private _peers = allPlayers select { alive _x && {_x isNotEqualTo player} && {side group _x isEqualTo _side} };
        private _rows = [["section", "Groupes de conversation", format ["%1 écouté(s), émission sur %2", count _mine, count _tx]]];
        {
            private _idx = _forEachIndex + 1;
            private _cnt = { _idx in (["talkgroup", _x] call comspec_atak_native_fnc_meshRead) } count _peers;
            // Sans abonné ni écoute, le groupe reste dans une rangée repliable : seuls les groupes actifs ou cités prennent de la place.
            if (_cnt isEqualTo 0 && {!(_idx in _mine)} && {_idx > 7}) then { continue };
            private _in = _idx in _mine;
            private _fixed = _in && {!(_idx in _subs)};
            private _btns = [];
            if (!_fixed) then { _btns pushBack [["ÉCOUTER", "QUITTER"] select _in, compile format ["['toggle', [%1]] call comspec_atak_native_fnc_meshGroups;", _idx], !_in]; };
            if (_in) then { _btns pushBack [["ÉMETTRE", "N'ÉMET PLUS"] select (_idx in _tx), compile format ["['txToggle', [%1]] call comspec_atak_native_fnc_meshGroups;", _idx]]; };
            _rows pushBack ["info", format ["%1 · %2", _idx, _x], format ["%1 abonné(s)%2%3", _cnt, ["", " · radio pontée"] select _fixed, ["", " · émission"] select (_idx in _tx)]];
            if (_btns isNotEqualTo []) then { _rows pushBack ["buttons", _btns]; };
        } forEach (["names"] call comspec_atak_native_fnc_meshGroups);
        _rows pushBack ["field", "meshGroupText", "Message aux groupes d'émission", "", "ENVOYER", {
            ["send", [["meshGroupText", ""] call comspec_atak_native_fnc_formValue]] call comspec_atak_native_fnc_meshGroups;
        }, true];
        _rows
    };
};
