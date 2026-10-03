/*
    Mode photo : viseur plein écran, on se déplace comme à pied (arme rangée).
    Clic gauche : photo envoyée sur ATAK web (plusieurs possibles). Espace : quitter et reprendre le téléphone.
    Params : ["ENTER" | "SHOOT" | "EXIT", légende]
*/
params [["_mode", "ENTER"], ["_caption", ""]];
if (!hasInterface) exitWith { false };
private _active = uiNamespace getVariable ["COMSPEC_ATAK_PhotoMode", false];
switch (toUpper _mode) do {
    case "ENTER": {
        if (_active) exitWith { false };
        if !([] call comspec_atak_native_fnc_bridge) exitWith { ["WARNING", "Photos : COMSPEC Overwatch doit être chargé pour l'envoi vers ATAK web", 4, 30] call comspec_atak_native_fnc_notify; false };
        if !(missionNamespace getVariable ["COMSPEC_AthenaReady", false]) exitWith { ["WARNING", "Photos : connectez-vous d'abord à Athena (app Athena)", 4, 30] call comspec_atak_native_fnc_notify; false };
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        uiNamespace setVariable ["COMSPEC_ATAK_PhotoMode", true];
        uiNamespace setVariable ["COMSPEC_ATAK_PhotoCtx", [_s getOrDefault ["interactive", false], uiNamespace getVariable ["COMSPEC_ATAK_HudWanted", false], currentWeapon player, _caption, 0]];
        uiNamespace setVariable ["COMSPEC_ATAK_HudWanted", false];
        [] call comspec_atak_native_fnc_close;
        // Arme rangée : le clic gauche ne tire pas.
        if ((currentWeapon player) isNotEqualTo "") then { player action ["SwitchWeapon", player, player, 299]; };
        ("COMSPEC_ATAK_Camera" call BIS_fnc_rscLayer) cutRsc ["COMSPEC_RscTitleCamera", "PLAIN", 0, false];
        private _d46 = findDisplay 46;
        uiNamespace setVariable ["COMSPEC_ATAK_PhotoEH", [
            _d46 displayAddEventHandler ["KeyDown", { params ["", "_key"]; if (_key isEqualTo 0x39) exitWith { ["EXIT"] call comspec_atak_native_fnc_photoMode; true }; false }],
            _d46 displayAddEventHandler ["MouseButtonDown", { params ["", "_button"]; if (_button isEqualTo 0 && {isNull curatorCamera} && {!visibleMap} && {isNull (findDisplay 49)}) then { ["SHOOT"] call comspec_atak_native_fnc_photoMode; }; false }]
        ]];
        // Sortie automatique si le joueur meurt, monte en véhicule ou perd le téléphone.
        uiNamespace setVariable ["COMSPEC_ATAK_PhotoPFH", [{
            if (!alive player || {vehicle player isNotEqualTo player} || {!([] call comspec_atak_native_fnc_hasDevice)}) then { ["EXIT"] call comspec_atak_native_fnc_photoMode; };
        }, 0.5] call CBA_fnc_addPerFrameHandler];
        true
    };
    case "SHOOT": {
        if (!_active) exitWith { false };
        if (diag_tickTime - (uiNamespace getVariable ["COMSPEC_ATAK_PhotoLast", -10]) < 3) exitWith {
            private _cam = uiNamespace getVariable ["COMSPEC_ATAK_CameraDisplay", displayNull];
            if (!isNull _cam) then { (_cam displayCtrl 4) ctrlSetText "Patientez : enregistrement de la photo précédente…"; };
            false
        };
        uiNamespace setVariable ["COMSPEC_ATAK_PhotoLast", diag_tickTime];
        [] spawn {
            disableSerialization;
            private _ctx = uiNamespace getVariable ["COMSPEC_ATAK_PhotoCtx", []];
            private _caption = _ctx param [3, ""];
            if (_caption isEqualTo "") then { _caption = format ["%1 · %2 · %3°", [player] call comspec_atak_native_fnc_unitCallsign, [player] call comspec_atak_native_fnc_gridRef, round getDir player]; };
            private _cam = uiNamespace getVariable ["COMSPEC_ATAK_CameraDisplay", displayNull];
            // Le viseur ne doit pas apparaître sur la photo.
            if (!isNull _cam) then { { (_cam displayCtrl _x) ctrlShow false; } forEach [1, 3, 4]; };
            uiSleep 0.12;
            private _ok = ["", _caption, "CTAB", "", false, false, true] call comspec_overwatch_connect_fnc_captureReconImage;
            uiSleep 0.08;
            if (!isNull _cam) then {
                { (_cam displayCtrl _x) ctrlShow true; } forEach [1, 3, 4];
                private _flash = _cam displayCtrl 2;
                _flash ctrlSetBackgroundColor [1, 1, 1, 0.55];
                _flash ctrlCommit 0;
                _flash ctrlSetBackgroundColor [1, 1, 1, 0];
                _flash ctrlCommit 0.35;
                private _n = (_ctx param [4, 0]) + ([0, 1] select _ok);
                _ctx set [4, _n];
                (_cam displayCtrl 4) ctrlSetText (["Photo non envoyée : voir l'app Photos", format ["%1 photo(s) envoyée(s) vers ATAK web", _n]] select _ok);
            };
            playSound "ClickSoft";
        };
        true
    };
    case "EXIT": {
        if (!_active) exitWith { false };
        uiNamespace setVariable ["COMSPEC_ATAK_PhotoMode", false];
        private _d46 = findDisplay 46;
        (uiNamespace getVariable ["COMSPEC_ATAK_PhotoEH", []]) params [["_k", -1], ["_m", -1]];
        if (_k >= 0) then { _d46 displayRemoveEventHandler ["KeyDown", _k]; };
        if (_m >= 0) then { _d46 displayRemoveEventHandler ["MouseButtonDown", _m]; };
        [uiNamespace getVariable ["COMSPEC_ATAK_PhotoPFH", -1]] call CBA_fnc_removePerFrameHandler;
        ("COMSPEC_ATAK_Camera" call BIS_fnc_rscLayer) cutText ["", "PLAIN"];
        (uiNamespace getVariable ["COMSPEC_ATAK_PhotoCtx", []]) params [["_interactive", true], ["_hud", false], ["_weapon", ""]];
        if (_weapon isNotEqualTo "" && {alive player} && {vehicle player isEqualTo player}) then { player selectWeapon _weapon; };
        if (alive player && {[] call comspec_atak_native_fnc_hasDevice}) then {
            uiNamespace setVariable ["COMSPEC_ATAK_HudWanted", _hud];
            [{
                params ["_interactive"];
                [_interactive] call comspec_atak_native_fnc_open;
                [{ ["PHOTOS"] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame;
            }, [_interactive || {!_hud}]] call CBA_fnc_execNextFrame;
        };
        true
    };
    default { false };
}
