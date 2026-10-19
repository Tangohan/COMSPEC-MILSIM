/*
    Raccourci Ctrl+Shift+S — ouvrir l'outil SSE (biométrie, fiche du sujet).

    Priorité :
      1. téléphone ATAK porté : téléphone en main sur l'app SSE (même schéma que Overwatch connect fn_openNativePage) ;
      2. BII-10 Identifi en inventaire, si la fenêtre s'ouvre vraiment ;
      3. terminal SEEK sur la personne visée (6 m au plus, comme l'app SSE de l'ATAK).
    La fiche de renseignement (RENS) a son propre raccourci (Ctrl+Shift+R, comspec_sse_fnc_openIntelNoteKeybind) :
    elle passait avant tout le reste, et Ctrl+Shift+S n'ouvrait jamais le SEEK dès qu'Overwatch était chargé.
    Jamais de sortie silencieuse.
*/
if (!hasInterface) exitWith { false };

// 1. App SSE du téléphone ATAK natif.
if (!isNil "comspec_atak_native_fnc_hasDevice" && {[player] call comspec_atak_native_fnc_hasDevice}) exitWith {
    if (isNull ([] call comspec_atak_native_fnc_display) || {!((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["interactive", false])}) then {
        [true] call comspec_atak_native_fnc_open;
    };
    [{ ["SSE"] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame;
    true
};

private _opened = false;

// 2. BII-10 Identifi.
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

// 3. SEEK : la personne visée à portée de main, jamais un homme à 300 m ni le dernier sujet contrôlé.
private _target = cursorObject;
if (isNull _target) then { _target = cursorTarget; };
if (isNull _target || {!(_target isKindOf "CAManBase")} || {(player distance _target) > 6}) then { _target = objNull; };

if (!isNil "comspec_overwatch_connect_fnc_ssePersonDialogShow") then {
    _opened = [_target] call comspec_overwatch_connect_fnc_ssePersonDialogShow;
};
if (_opened) exitWith { true };

if (isNull _target) then { _target = player; };

if (!isNil "comspec_sse_fnc_openSeek") then {
    _opened = [_target] call comspec_sse_fnc_openSeek;
};
if (_opened) exitWith { true };

["Aucun terminal SSE n'a pu s'ouvrir. Emportez un téléphone ATAK, un SEEK ou un BII-10.", "WARNING"] call comspec_sse_fnc_notify;
false
