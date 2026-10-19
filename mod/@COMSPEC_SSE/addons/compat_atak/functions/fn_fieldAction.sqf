/*
    Actions de l'app « SSE terrain » (fn_fieldPage).
    [_action, _arg] call comspec_sse_fnc_fieldAction
      "tab" nom      : onglet affiché (target | site | custody | journal)
      "exploit"      : approfondir l'exploitation de la cible (résultat affiché dans l'app)
      "timeline"     : chronologie de la cible
      "docs"         : lecture des documents (visionneuse, téléphone rangé)
      "seek"         : terminal SEEK II sur la personne visée
      "dl7"          : branche le câble DL-7 sur la cible et ouvre l'app Extraction
      "transmit"     : envoi de la fiche à Athena          "frs" : fiche FRS pré-remplie
      "exploited"    : marque la cible exploitée            "chart" / "registry" / "triage"
*/
params [["_act", ""], ["_arg", ""]];
private _st = uiNamespace getVariable ["comspec_sse_field", createHashMap];
uiNamespace setVariable ["comspec_sse_field", _st];
private _render = {
    [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "SSEFIELD") then { ["SSEFIELD"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame;
};
private _say = { params ["_type", "_msg"]; [_type, _msg call comspec_sse_fnc_tr, 4, 30, "SSEFIELD"] call comspec_atak_native_fnc_notify; };
// Écrans à part (visionneuse, SEEK, tableau) : on range le téléphone pour leur laisser la souris.
private _away = { params ["_code", ["_args", []]]; [] call comspec_atak_native_fnc_close; [_code, _args, 0.1] call CBA_fnc_waitAndExecute; };

if (_act isEqualTo "tab") exitWith { _st set ["tab", _arg]; call _render; true };
if (_act isEqualTo "chart") exitWith { [{ [false] call comspec_sse_fnc_chartOpen; }] call _away; true };
if (_act isEqualTo "registry") exitWith { [{ ["evidence"] call comspec_sse_fnc_uiOpenScreen; }] call _away; true };
if (_act isEqualTo "triage") exitWith {
    private _res = [player, 50] call comspec_sse_fnc_triageSite;
    ["INFO", format ["Triage : %1 élément(s) classé(s)", count _res]] call _say;
    true
};

private _t = [] call comspec_sse_fnc_fieldTarget;
if (isNull _t) exitWith { ["WARNING", "Aucune cible SSE à portée"] call _say; false };

switch (_act) do {
    case "exploit": {
        private _r = [_t, player] call comspec_sse_fnc_advanceExploitation;
        _st set ["result", [_t, format ["Exploitation : niveau %1", _r getOrDefault ["level", "?"]], _r getOrDefault ["lines", []]]];
        call _render;
    };
    case "timeline": {
        private _ev = [_t] call comspec_sse_fnc_buildTimeline;
        if !(_ev isEqualType []) then { _ev = []; };
        _st set ["result", [_t, "Chronologie", (_ev select [0, 12]) apply {
            if (_x isEqualType createHashMap) then { format ["%1 · %2", _x getOrDefault ["when", "?"], _x getOrDefault ["text", ""]] } else { str _x }
        }]];
        call _render;
    };
    case "photo": {
        // App Photo de l'ATAK quand elle peut envoyer (Athena) ; sinon cliché gardé sur le téléphone.
        if ([] call comspec_atak_native_fnc_bridge && {missionNamespace getVariable ["COMSPEC_AthenaReady", false]}) exitWith {
            ["INFO", "Cadrez la pièce et déclenchez : elle est inscrite au dossier"] call _say;
            [{ ["PHOTOS"] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame;
        };
        [[[_t, player distance _t]], 1] call comspec_sse_fnc_evidencePhoto;
        call _render;
    };
    case "docs": { [{ _this call comspec_sse_fnc_doReadDocuments; }, [_t, player]] call _away; };
    case "seek": { [{ _this call comspec_sse_fnc_openSeek; }, [_t]] call _away; };
    case "dl7": {
        if !(["can", _t] call comspec_atak_native_fnc_dataAction) exitWith { ["WARNING", "DL-7 : approchez-vous du support (câble trop court) ou débranchez le câble en cours"] call _say; };
        ["connect", _t] call comspec_atak_native_fnc_dataAction;
        [{ ["DATALINK"] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame;
    };
    case "transmit": {
        [_t, true, false] call comspec_sse_fnc_transmitEntity;
        ["INFO", "Fiche envoyée vers Athena (file d'attente si hors liaison)"] call _say;
    };
    case "frs": { ["frs", "FRM"] call comspec_atak_native_fnc_sseAction; };
    case "exploited": { [_t, player] call comspec_sse_fnc_doMarkExploited; call _render; };
};
true
