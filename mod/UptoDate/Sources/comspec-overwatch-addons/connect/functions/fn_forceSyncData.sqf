/*
    Transmet immédiatement position + données vers Athena (bouton hub TERRAIN).
    Force un envoi hors filtres de batch / état mission.
    Cooldown ~8 s pour éviter le spam.
*/
if (!hasInterface) exitWith { false };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {
    ["Overwatch est désactivé.", "link", "warn", true] call comspec_overwatch_connect_fnc_announce;
    false
};
if (isNull player || {!alive player}) exitWith {
    ["Impossible de transmettre (opérateur hors service).", "link", "warn", true] call comspec_overwatch_connect_fnc_announce;
    false
};

private _cooldown = 8;
private _now = diag_tickTime;
private _last = missionNamespace getVariable ["COMSPEC_ForceSyncAt", -1e9];
private _remain = ceil (_cooldown - (_now - _last));
if (_remain > 0) exitWith {
    [format ["Patientez %1 s avant de retransmettre.", _remain], "link", "info", true] call comspec_overwatch_connect_fnc_announce;
    false
};

missionNamespace setVariable ["COMSPEC_ForceSyncAt", _now, false];

private _result = [player, true] call comspec_overwatch_connect_fnc_updatePosition;

// Aussi renvoyer tous les marqueurs Marker Widget / Dropper / cTab
private _mkCount = 0;
if (!isNil "comspec_overwatch_connect_fnc_forceSyncMapMarkers") then {
    _mkCount = [false] call comspec_overwatch_connect_fnc_forceSyncMapMarkers;
    if (!(_mkCount isEqualType 0)) then { _mkCount = 0; };
};

private _ok = (_result isEqualTo "ok");
if (_ok || {_mkCount > 0}) then {
    if (_mkCount > 0) then {
        [format ["Position transmise — %1 marqueur(s) renvoyé(s).", _mkCount], "link", "info", true] call comspec_overwatch_connect_fnc_announce;
    } else {
        ["Position et données transmises.", "link", "info", true] call comspec_overwatch_connect_fnc_announce;
    };
} else {
    private _msg = switch (_result) do {
        case "origin": { "Position non valide — déplacez-vous un peu." };
        case "dead": { "Impossible de transmettre (opérateur hors service)." };
        default { "Transmission impossible pour le moment." };
    };
    [_msg, "link", "warn", true] call comspec_overwatch_connect_fnc_announce;
};

(_ok || {_mkCount > 0})
