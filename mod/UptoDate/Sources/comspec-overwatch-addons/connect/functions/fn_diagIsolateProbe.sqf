/*
    Pendant le dépannage : envoie un essai réel (message, repère, photo).
    Retardé de 2 s pour laisser le bandeau s’afficher ; un second essai
    à 10 s si la liaison n’était pas encore prête.
*/
params [
    ["_id", "", [""]],
    ["_token", -1]
];

if (!hasInterface) exitWith { false };
if !(_id in ["probe_chat", "probe_marker", "probe_photo"]) exitWith { false };

[_id, _token] spawn {
    params ["_id", "_token"];

    private _fnc_alive = {
        (missionNamespace getVariable ["COMSPEC_DiagIsolateToken", -1]) isEqualTo _token
        && {missionNamespace getVariable ["COMSPEC_DiagIsolateActive", false]}
    };

    private _fnc_note = {
        params ["_txt"];
        if (!(_txt isEqualType "") || {_txt isEqualTo ""}) exitWith {};
        missionNamespace setVariable ["COMSPEC_DiagIsolateProbeNote", _txt, false];
        ["INFO", "Diag", _txt] call comspec_overwatch_connect_fnc_log;
    };

    private _fnc_linkOk = {
        private _ok = true;
        if (!isNil "comspec_overwatch_connect_fnc_isReady") then {
            _ok = [] call comspec_overwatch_connect_fnc_isReady;
        };
        if (_ok && {!isNil "comspec_overwatch_connect_fnc_canStartSync"}) then {
            _ok = [] call comspec_overwatch_connect_fnc_canStartSync;
        };
        _ok
    };

    private _fnc_run = {
        params ["_id"];
        if !(call _fnc_alive) exitWith { false };

        switch (_id) do {
            case "probe_chat": {
                if (!(call _fnc_linkOk)) exitWith {
                    ["Liaison pas encore prête — essai message reporté."] call _fnc_note;
                    false
                };
                private _who = [] call comspec_overwatch_connect_fnc_getCallsign;
                if (_who isEqualTo "") then { _who = name player; };
                private _txt = format ["Dépannage liaison — essai message (%1)", _who];
                [player, "CHAT", _txt, "", "INFANTRY"] call comspec_overwatch_connect_fnc_sendIntel;
                ["probe_chat", 1, " · essai message"] call comspec_overwatch_connect_fnc_noteUplinkReturn;
                ["Message de test envoyé vers le poste."] call _fnc_note;
                true
            };
            case "probe_marker": {
                if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {
                    ["Overwatch encore coupé — essai repère reporté."] call _fnc_note;
                    false
                };
                private _pos = getPosATL player;
                private _name = format ["poi_local_diag_%1_%2", floor time, floor random 9999];
                private _ok = [_name, _pos, "mil_dot", "ColorYellow", "Dépannage", "diag"] call comspec_overwatch_connect_fnc_sendLocalTacticalMarker;
                if (markerType _name isEqualTo "") then {
                    createMarkerLocal [_name, _pos];
                    _name setMarkerTypeLocal "mil_dot";
                    _name setMarkerColorLocal "ColorYellow";
                    _name setMarkerTextLocal "Dépannage";
                };
                ["probe_marker", 1, " · essai repère"] call comspec_overwatch_connect_fnc_noteUplinkReturn;
                if (missionNamespace getVariable ["COMSPEC_AthenaReady", false]) then {
                    ["Repère de test posé et envoyé vers le poste."] call _fnc_note;
                } else {
                    ["Repère de test posé — envoi en attente de la liaison."] call _fnc_note;
                };
                _ok
            };
            case "probe_photo": {
                if (!(call _fnc_linkOk) || {!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])}) exitWith {
                    ["Session pas encore prête — essai photo reporté."] call _fnc_note;
                    false
                };
                (["COMSPEC_DiagIsolateHud"] call BIS_fnc_rscLayer) cutText ["", "PLAIN"];
                uiNamespace setVariable ["COMSPEC_DiagIsolateHudDisp", displayNull];
                uiSleep 0.28;
                if !(call _fnc_alive) exitWith { false };
                private _ok = ["", "Dépannage liaison — essai photo", "CTAB"] call comspec_overwatch_connect_fnc_captureReconImage;
                ["probe_photo", 1, " · essai photo"] call comspec_overwatch_connect_fnc_noteUplinkReturn;
                if (_ok || {missionNamespace getVariable ["COMSPEC_LastReconUploadOk", false]}) then {
                    ["Photo prise et transmise vers le poste."] call _fnc_note;
                    true
                } else {
                    private _detail = missionNamespace getVariable ["COMSPEC_LastReconUploadDetail", ""];
                    if (!(_detail isEqualType "")) then { _detail = str _detail; };
                    ["Essai photo manqué — vérifiez la liaison, puis relancez le dépannage."] call _fnc_note;
                    ["WARN", "Diag", format ["Essai photo : %1", _detail]] call comspec_overwatch_connect_fnc_log;
                    false
                }
            };
            default { false };
        }
    };

    uiSleep 2;
    if !(call _fnc_alive) exitWith {};
    private _ok = [_id] call _fnc_run;
    if (_ok) exitWith {};
    uiSleep 8;
    if !(call _fnc_alive) exitWith {};
    [_id] call _fnc_run;
};

true
