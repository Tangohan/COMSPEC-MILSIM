/*
    Force l’envoi des photos locales encore en attente (jusqu’à 8),
    sans tenir compte des échecs précédents.
*/
if (!hasInterface) exitWith {};

if (missionNamespace getVariable ["COMSPEC_Athena_PhotoForceBusy", false]) exitWith {
    [
        "Un envoi de photos est déjà en cours.",
        "warn",
        4
    ] call comspec_overwatch_atak_athena_fnc_athena_setPanelFeedback;
};

if (isNil "comspec_overwatch_atak_athena_fnc_athena_bridgeIcemanPhoto") exitWith {
    [
        "L’envoi de photos n’est pas disponible pour le moment.",
        "error",
        5
    ] call comspec_overwatch_atak_athena_fnc_athena_setPanelFeedback;
};

private _photos = [];
if (!isNil "comspec_overwatch_atak_athena_fnc_athena_collectLocalPhotos") then {
    _photos = [] call comspec_overwatch_atak_athena_fnc_athena_collectLocalPhotos;
};
if (!(_photos isEqualType [])) then { _photos = []; };

if ((count _photos) == 0) exitWith {
    [
        "Aucune photo à envoyer — prenez d’abord une vue depuis l’app Photos.",
        "warn",
        6
    ] call comspec_overwatch_atak_athena_fnc_athena_setPanelFeedback;
};

missionNamespace setVariable ["COMSPEC_Athena_PhotoForceBusy", true, false];
[
    format ["Envoi forcé de %1 photo%2…", count _photos, if ((count _photos) > 1) then { "s" } else { "" }],
    "info",
    4
] call comspec_overwatch_atak_athena_fnc_athena_setPanelFeedback;

[_photos] spawn {
    params ["_photos"];
    private _okN = 0;
    private _failN = 0;
    private _max = (count _photos) min 8;
    for "_i" from 0 to (_max - 1) do {
        private _rec = _photos select _i;
        if (!(_rec isEqualType []) || {(count _rec) < 1}) then { continue };
        private _path = _rec select 0;
        private _fileName = if ((count _rec) > 1) then { _rec select 1 } else { "" };
        if (_path isEqualTo "") then { continue };
        private _ok = [_path, _fileName, false, true] call comspec_overwatch_atak_athena_fnc_athena_bridgeIcemanPhoto;
        if (!(_ok isEqualType true)) then { _ok = false; };
        if (_ok) then { _okN = _okN + 1 } else { _failN = _failN + 1 };
        uiSleep 0.2;
    };
    missionNamespace setVariable ["COMSPEC_Athena_PhotoForceBusy", false, false];
    private _msg = if (_okN > 0 && {_failN == 0}) then {
        format ["%1 photo%2 mise%2 en file vers le poste.", _okN, if (_okN > 1) then { "s" } else { "" }]
    } else {
        if (_okN > 0) then {
            format ["%1 photo(s) en file, %2 en échec — vérifiez le dossier des photos.", _okN, _failN]
        } else {
            "Aucune photo n’a pu partir. Vérifiez le dossier des photos, puis réessayez."
        }
    };
    private _tone = if (_okN > 0 && {_failN == 0}) then { "ok" } else { if (_okN > 0) then { "warn" } else { "error" } };
    [_msg, _tone, 7] call comspec_overwatch_atak_athena_fnc_athena_setPanelFeedback;
    [] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
};
