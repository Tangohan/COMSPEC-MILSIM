/*
    Transmet immédiatement position + données vers Athena (bouton hub TERRAIN).
    Force un envoi hors filtres de batch / état mission ; ItemAndroid reste obligatoire.
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

// Marquer le cooldown avant tout, y compris en cas d'échec (terminal absent) — sinon un joueur
// sans S7 Android peut spammer le bouton sans aucune limite (message répété en boucle).
missionNamespace setVariable ["COMSPEC_ForceSyncAt", _now, false];

if (!([player] call comspec_overwatch_connect_fnc_hasTerminal)) exitWith {
    ["Equip S7 Android phone", "link", "warn", true] call comspec_overwatch_connect_fnc_announce;
    false
};

private _result = [player, true] call comspec_overwatch_connect_fnc_updatePosition;

private _ok = (_result isEqualTo "ok");
if (_ok) then {
    ["Position et données transmises.", "link", "info", true] call comspec_overwatch_connect_fnc_announce;
} else {
    private _msg = switch (_result) do {
        case "no_android": { "Equip S7 Android phone" };
        case "origin": { "Position non valide — déplacez-vous un peu." };
        case "dead": { "Impossible de transmettre (opérateur hors service)." };
        default { "Transmission impossible pour le moment." };
    };
    [_msg, "link", "warn", true] call comspec_overwatch_connect_fnc_announce;
};

_ok
