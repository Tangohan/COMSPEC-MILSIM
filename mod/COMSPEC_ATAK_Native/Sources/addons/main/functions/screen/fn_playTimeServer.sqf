/*
    Temps de jeu et temps par rôle comptés par le serveur Arma, pour chaque joueur, sans passer par son téléphone :
    batterie vide, téléphone cassé ou perdu, plus de réseau en jeu, plantage du client, rien de tout ça ne coupe le compteur.
    Envoi à Athena par la DLL du serveur (ScreenTime.Batch de COMSPEC Link 2.0.63) toutes les 5 min,
    au départ d'un joueur et en fin de mission. Ce qui n'est pas parti reste en mémoire serveur et repart au prochain envoi.
    Tant que le serveur arrive à envoyer (COMSPEC_ATAK_SrvPlayOK publique), il compte et les clients n'envoient plus que l'écran
    et les apps ; s'il n'y arrive plus, il arrête de compter (ce qu'il garde repart quand même) et les clients prennent le relais.
    Params : [mode : "tick" (toutes les 10 s) | "flush" (tout envoyer) | "leave" (envoyer un joueur), uid (pour "leave")]
    Mémoire : COMSPEC_ATAK_PlayAcc, uid -> [indicatif, [clé "type|code" -> [libellé, secondes]]]
*/
params [["_mode", "tick"], ["_only", ""]];
if (!isServer) exitWith { false };
private _now = diag_tickTime;
private _db = missionNamespace getVariable ["COMSPEC_ATAK_PlayAcc", createHashMap];
missionNamespace setVariable ["COMSPEC_ATAK_PlayAcc", _db];

if (_mode isEqualTo "tick") then {
    private _dt = (_now - (missionNamespace getVariable ["COMSPEC_ATAK_PlayTick", _now])) min 30;
    missionNamespace setVariable ["COMSPEC_ATAK_PlayTick", _now];
    // Le serveur ne compte que tant qu'il arrive à envoyer : sinon les téléphones prennent le relais (pas de double compte).
    if (_dt <= 0 || {!(missionNamespace getVariable ["comspec_atak_native_screen_time", true])} || {!(missionNamespace getVariable ["COMSPEC_ATAK_SrvPlayOK", false])}) exitWith {};
    {
        private _uid = getPlayerUID _x;
        if (_uid isNotEqualTo "" && {!(_x isKindOf "HeadlessClient_F")}) then {
            (_db getOrDefault [_uid, ["", createHashMap]]) params ["", "_items"];
            private _add = {
                params ["_k", "_label"];
                (_items getOrDefault [_k, [_label, 0]]) params ["", "_sec"];
                _items set [_k, [_label, _sec + _dt]];
            };
            ["play|total", "Temps de jeu"] call _add;
            if (alive _x) then {
                ([_x] call comspec_atak_native_fnc_roleKey) params ["_rk", "_rl"];
                if (_rk isNotEqualTo "") then { [format ["role|%1", _rk], _rl] call _add; };
            };
            _db set [_uid, [[_x] call comspec_atak_native_fnc_unitCallsign, _items]];
        };
    } forEach allPlayers;
};

// Envoi : toutes les 5 min, ou tout de suite (départ d'un joueur, fin de mission).
private _last = missionNamespace getVariable ["COMSPEC_ATAK_PlaySent", -1e9];
if (_mode isEqualTo "tick" && {(_now - _last) < 300}) exitWith { true };
missionNamespace setVariable ["COMSPEC_ATAK_PlaySent", _now];
private _uids = if (_mode isEqualTo "leave") then { [_only] select { _x in _db } } else { keys _db };
if (_uids isEqualTo []) exitWith {
    // Rien en attente et envoi en panne : on redemande à la DLL si elle sait envoyer.
    if (!(missionNamespace getVariable ["COMSPEC_ATAK_SrvPlayOK", false]) && {_mode isNotEqualTo "leave"}) then {
        private _p = "COMSPECExtension" callExtension ["ScreenTime.Batch", ["probe"]];
        if (_p isEqualType []) then { _p = _p param [0, ""]; };
        if ((_p select [0, 3]) isEqualTo "OK|") then { missionNamespace setVariable ["COMSPEC_ATAK_SrvPlayOK", true, true]; };
    };
    true
};
// Par lots de 25 joueurs ; au plus 2 h par élément et par envoi (plafond d'Athena) : le reste repart au suivant.
private _mk = missionNamespace getVariable ["COMSPEC_ATAK_MissionKey", format ["%1@%2", missionName, worldName]];
private _ok = true;
private _r = "";
private _tried = false;
for "_i" from 0 to ((count _uids) - 1) step 25 do {
    private _players = [];
    private _sent = [];
    {
        private _uid = _x;
        (_db get _uid) params ["_call", "_items"];
        private _list = [];
        {
            (_x splitString "|") params [["_kind", ""], ["_code", ""]];
            if ((_y select 1) >= 1) then { _list pushBack (createHashMapFromArray [["kind", _kind], ["key", _code], ["label", _y select 0], ["seconds", round ((_y select 1) min 7200)]]); };
        } forEach _items;
        if (_list isNotEqualTo []) then {
            _players pushBack (createHashMapFromArray [["player_uid", _uid], ["call_sign", _call], ["items", _list]]);
            _sent pushBack _uid;
        };
    } forEach (_uids select [_i, 25]);
    if (_players isNotEqualTo []) then {
        _tried = true;
        _r = "COMSPECExtension" callExtension ["ScreenTime.Batch", [[createHashMapFromArray [["mission_key", _mk], ["players", _players]]] call comspec_atak_native_fnc_json]];
        if (_r isEqualType []) then { _r = _r param [0, ""]; };
        if ((_r select [0, 3]) isEqualTo "OK|") then {
            {
                private _uid = _x;
                (_db get _uid) params ["", "_items"];
                { private _v = _items get _x; _items set [_x, [_v select 0, ((_v select 1) - 7200) max 0]]; } forEach (keys _items);
                { _items deleteAt _x; } forEach ((keys _items) select { ((_items get _x) select 1) < 1 });
                // Joueur parti et tout envoyé : on l'oublie.
                if ((count _items) isEqualTo 0 && {(allPlayers findIf { getPlayerUID _x isEqualTo _uid }) < 0}) then { _db deleteAt _uid; };
            } forEach _sent;
        } else {
            _ok = false;
        };
    };
};
if (_tried && {_ok isNotEqualTo (missionNamespace getVariable ["COMSPEC_ATAK_SrvPlayOK", false])}) then {
    missionNamespace setVariable ["COMSPEC_ATAK_SrvPlayOK", _ok, true];
};
if (!_ok) then {
    ["WARN", "PLAYTIME", format ["ScreenTime.Batch refusé (%1) : gardé en mémoire serveur, les téléphones envoient eux-mêmes", [_r, "COMSPEC Link 2.0.63 requis"] select (_r isEqualTo "")]] call comspec_atak_native_fnc_log;
};
true
