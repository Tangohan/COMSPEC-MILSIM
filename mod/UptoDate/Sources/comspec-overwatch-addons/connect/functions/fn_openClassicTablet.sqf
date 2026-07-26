/*
    Ouvre la vue tablette classique (idd 9973) si elle est encore autorisée.
    Params: [_fromAtak]
    Temporairement désactivée (petit modèle) — ne fait rien et notifie.
    Retourne true si ouverte, false sinon.
*/
params [["_fromAtak", false, [true]]];

if (!hasInterface) exitWith { false };

if !([_fromAtak] call comspec_overwatch_connect_fnc_canOpenOverwatchUi) exitWith {
    private _msg = if (!(missionNamespace getVariable ["comspec_overwatch_require_item", false]) || {([player] call comspec_overwatch_connect_fnc_hasTerminal)}) then {
        "Ouvrez le téléphone ATAK Enhanced pour accéder à Overwatch."
    } else {
        "Équipement requis manquant — synchronisation et interface indisponibles."
    };
    ["COMSPEC_Warning", [_msg]] call comspec_overwatch_connect_fnc_showNotification;
    false
};

if (!(missionNamespace getVariable ["comspec_overwatch_classic_tablet_enabled", false])) exitWith {
    ["COMSPEC_Info", ["Vue classique temporairement désactivée — utilisez la tablette Athena."]] call comspec_overwatch_connect_fnc_showNotification;
    false
};

if (!isNull (findDisplay 9973)) exitWith { true };

private _ok = createDialog "COMSPEC_Device_Dialog";
if (_ok) then {
    ["start"] call comspec_overwatch_connect_fnc_playAtakNotification;
};
_ok
