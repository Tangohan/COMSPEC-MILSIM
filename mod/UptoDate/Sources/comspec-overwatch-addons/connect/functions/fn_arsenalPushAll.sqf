/*
    Sauvegarde toutes les wardrobes ACE Arsenal locales vers Athena.
*/
if (!hasInterface) exitWith { false };

private _key = missionNamespace getVariable ["comspec_overwatch_api_key", ""];
if (_key isEqualTo "") exitWith {
    ["Liaison Athena requise pour synchroniser les tenues.", "arsenal", "warn", true] call comspec_overwatch_connect_fnc_announce;
    false
};

if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {
    ["Compte Athena non relié — les tenues ne partent pas.", "arsenal", "warn", true] call comspec_overwatch_connect_fnc_announce;
    false
};

private _tx = [false] call comspec_overwatch_connect_fnc_canTransmit;
if (!(_tx getOrDefault ["can_transmit", false])) exitWith {
    ["Liaison indisponible — les tenues ne sont pas mises en attente.", "arsenal", "warn", true] call comspec_overwatch_connect_fnc_announce;
    false
};

private _entries = [] call comspec_overwatch_connect_fnc_arsenalLocalLoadouts;
if (_entries isEqualTo []) exitWith {
    ["Aucune tenue locale à envoyer.", "arsenal", "info", true] call comspec_overwatch_connect_fnc_announce;
    false
};

private _ok = 0;
private _fail = 0;
{
    _x params ["_name", "_data"];
    private _loadout = [_data] call comspec_overwatch_connect_fnc_arsenalNormalizeLoadout;
    if (_loadout isEqualTo []) then {
        _fail = _fail + 1;
        continue;
    };
    private _payload = str _loadout;
    private _parsed = ["SyncWardrobe", [_name, _payload], "SyncWardrobe", false, false, "arsenal", false]
        call comspec_overwatch_connect_fnc_callExtLogged;
    _parsed params [["_success", false], ["_status", ""], ["_detail", ""]];
    if (_success || {_status isEqualTo "QUEUED"}) then {
        _ok = _ok + 1;
    } else {
        _fail = _fail + 1;
        ["DEBUG", "ARSENAL", format ["Sync fail %1 → %2 %3", _name, _status, _detail]] call comspec_overwatch_connect_fnc_log;
    };
} forEach _entries;

private _msg = format ["Tenues Athena : %1 enregistrée(s)%2.", _ok, if (_fail > 0) then { format [", %1 échec(s)", _fail] } else { "" }];
[_msg, "arsenal", if (_fail > 0 && {_ok < 1}) then { "warn" } else { "ok" }, true] call comspec_overwatch_connect_fnc_announce;
missionNamespace setVariable ["COMSPEC_ArsenalLastPushAt", diag_tickTime, false];

_ok > 0
