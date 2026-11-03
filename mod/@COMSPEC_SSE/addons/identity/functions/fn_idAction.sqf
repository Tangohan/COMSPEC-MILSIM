/*
    Actions de l'app « Contrôle ID » (fn_idPage).
      "tab" nom         onglet (check | history)          "select" i   rouvre la fiche i de l'historique
      "level" n         lecture N1 MRZ (3 s), N2 puce (4 s, 1,5 m), N3 base (3 s) : barre de progression ACE
      "view"            revoir le document (visionneuse)  "transmit"   envoi à Athena    "frm"  fiche FRM pré-remplie
*/
params [["_act", ""], ["_arg", ""]];
private _st = uiNamespace getVariable ["comspec_sse_idApp", createHashMap];
uiNamespace setVariable ["comspec_sse_idApp", _st];
private _render = {
    [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "IDCHECK") then { ["IDCHECK"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame;
};
private _say = { params ["_type", "_msg"]; [_type, _msg call comspec_sse_fnc_tr, 4, 30, "IDCHECK"] call comspec_atak_native_fnc_notify; };
if (_act isEqualTo "tab") exitWith { _st set ["tab", _arg]; call _render; true };
if (_act isEqualTo "select") exitWith { ["select", [_arg]] call comspec_sse_fnc_idHistory; _st set ["tab", "check"]; call _render; true };
private _rec = ["current"] call comspec_sse_fnc_idHistory;
if ((count _rec) isEqualTo 0) exitWith { ["WARNING", "Aucun contrôle en cours"] call _say; false };

switch (_act) do {
    case "level": {
        private _lvl = (_arg max 1) min 3;
        private _h = _rec get "docId";
        private _subj = _rec getOrDefault ["subj", objNull];
        if (!(_subj isEqualType objNull) || {_subj isEqualTo player}) then { _subj = objNull; };
        ["send", ["begin", [player, format ["check%1", _lvl], _h]]] call comspec_sse_fnc_idClient;
        [[3, 4, 3] select (_lvl - 1), ["Lecture de la bande MRZ…", "Lecture de la puce NFC…", "Interrogation de la base…"] select (_lvl - 1), {
            params ["_h", "_lvl"];
            ["send", ["check", [player, _h, _lvl]]] call comspec_sse_fnc_idClient;
        }, [_h, _lvl], _subj] call comspec_sse_fnc_progressAction;
    };
    case "view": { [_rec get "doc", false] call comspec_sse_fnc_idViewerOpen; };
    case "transmit": { [_rec] call comspec_sse_fnc_idAthenaSubmit; call _render; };
    case "frm": { [_rec] call comspec_sse_fnc_idFrm; };
};
true
