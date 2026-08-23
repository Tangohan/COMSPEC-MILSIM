/*
    Raccourci Ctrl+Shift+S — ouvrir un terminal SSE visible.

    Priorité : rédacteur de fiche de renseignement (RENS). BII Identifi seulement
    si le BII-10 est en inventaire ET que la fenêtre s'ouvre vraiment. Sinon SEEK.
    Jamais de sortie silencieuse.
*/
if (!hasInterface) exitWith { false };

private _announce = {
    params ["_txt"];
    if (!isNil "comspec_overwatch_connect_fnc_announce") then {
        [_txt, "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
    } else {
        hint _txt;
    };
};

if (!isNil "comspec_overwatch_atak_athena_fnc_athena_openNote") exitWith {
    [""] call comspec_overwatch_atak_athena_fnc_athena_openNote
};

if (!isNil "comspec_overwatch_connect_fnc_intelNoteShow") exitWith {
    if (isNull (uiNamespace getVariable ["cTab_Android_dlg", displayNull])
        && {!isNil "comspec_overwatch_connect_fnc_openAtakEnhanced"}) then {
        [] call comspec_overwatch_connect_fnc_openAtakEnhanced;
    };
    [""] call comspec_overwatch_connect_fnc_intelNoteShow
};

private _opened = false;

if (
    missionNamespace getVariable ["comspec_sse_biiBridgeEnabled", true]
    && {!isNil "comspec_sse_fnc_biiIsPresent"}
    && {[] call comspec_sse_fnc_biiIsPresent}
    && {!isNil "comspec_sse_fnc_biiOpen"}
    && {!isNil "BII_fnc_identifi_hasDevice"}
    && {[player] call BII_fnc_identifi_hasDevice}
) then {
    private _ok = ["scan"] call comspec_sse_fnc_biiOpen;
    private _disp = uiNamespace getVariable ["BII_Identifi_Dialog", displayNull];
    if (isNull _disp) then { _disp = findDisplay 861010; };
    _opened = _ok && {!isNull _disp};
};

if (_opened) exitWith { true };

private _target = cursorObject;
if (isNull _target) then { _target = cursorTarget; };
if (isNull _target || {!(_target isKindOf "CAManBase")}) then {
    _target = missionNamespace getVariable ["comspec_sse_seekTarget", objNull];
};
if (isNull _target || {!(_target isKindOf "CAManBase")}) then {
    _target = objNull;
};

if (!isNil "comspec_overwatch_connect_fnc_ssePersonDialogShow") then {
    _opened = [_target] call comspec_overwatch_connect_fnc_ssePersonDialogShow;
};
if (_opened) exitWith { true };

if (isNull _target) then { _target = player; };

if (!isNil "comspec_sse_fnc_openSeek") then {
    _opened = [_target] call comspec_sse_fnc_openSeek;
};
if (_opened) exitWith { true };

["Aucun terminal SSE n'a pu s'ouvrir. Emportez un téléphone ATAK, un SEEK ou un BII-10."] call _announce;
false
