/*
    Ouvre la vue tablette classique (idd 9973) si elle est encore autorisée.
    Temporairement désactivée (petit modèle) — ne fait rien et notifie.
    Retourne true si ouverte, false sinon.
*/
if (!hasInterface) exitWith { false };

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
