/*
    Actions de l'app « Contrôle ID » (fn_idPage).
      "fold" clé        ouvre ou replie un bloc de la fiche (Contrôle, Signaler, Partager, détail)
      "tab" nom         onglet (check | history)          "select" i   rouvre la fiche i de l'historique
      "level" n         lecture N1 MRZ (3 s), N2 puce (4 s, 1,5 m), N3 base (3 s) : barre de progression ACE
      "view"            revoir le document (visionneuse)  "transmit"   envoi à Athena    "frm"  fiche FRM pré-remplie
      "photo"           photo du document (jointe à la FRM)    "salute" / "spotrep"   compte rendu pré-rempli
      "share" / "nfc"   fiche du contrôle partagée (Bluetooth, NFC)   "mobile"  QR « Sur mon téléphone »  (ATAK 3.0.2)
      "level" 4         N4 : comparaison du visage (durée : réglage « Durée de la comparaison du visage »)
      "pid"             confirmation PID de la personne contrôlée (HVT ou recherchée identifiée)
*/
params [["_act", ""], ["_arg", ""]];
private _st = uiNamespace getVariable ["comspec_sse_idApp", createHashMap];
uiNamespace setVariable ["comspec_sse_idApp", _st];
private _render = {
    [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "IDCHECK") then { ["IDCHECK"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame;
};
private _say = { params ["_type", "_msg"]; [_type, _msg call comspec_sse_fnc_tr, 4, 30, "IDCHECK"] call comspec_atak_native_fnc_notify; };
if (_act isEqualTo "fold") exitWith { private _k = "open_" + _arg; _st set [_k, !(_st getOrDefault [_k, false])]; call _render; true };
if (_act isEqualTo "tab") exitWith { _st set ["tab", _arg]; call _render; true };
if (_act isEqualTo "select") exitWith { ["select", [_arg]] call comspec_sse_fnc_idHistory; _st set ["tab", "check"]; call _render; true };
private _rec = ["current"] call comspec_sse_fnc_idHistory;
if ((count _rec) isEqualTo 0) exitWith { ["WARNING", "Aucun contrôle en cours"] call _say; false };

switch (_act) do {
    case "level": {
        private _lvl = (_arg max 1) min 4;
        private _h = _rec get "docId";
        private _subj = _rec getOrDefault ["subj", objNull];
        if (!(_subj isEqualType objNull) || {_subj isEqualTo player}) then { _subj = objNull; };
        ["send", ["begin", [player, format ["check%1", _lvl], _h]]] call comspec_sse_fnc_idClient;
        [[3, 4, 3, missionNamespace getVariable ["comspec_sse_id_bioTime", 8]] select (_lvl - 1), ["Lecture de la bande MRZ…", "Lecture de la puce NFC…", "Interrogation de la base…", "Photo et comparaison du visage…"] select (_lvl - 1), {
            params ["_h", "_lvl"];
            ["send", ["check", [player, _h, _lvl]]] call comspec_sse_fnc_idClient;
        }, [_h, _lvl], _subj] call comspec_sse_fnc_progressAction;
    };
    case "view": { [_rec get "doc", false] call comspec_sse_fnc_idViewerOpen; };
    case "pid": {
        private _subj = _rec getOrDefault ["subj", objNull];
        if (!(_subj isEqualType objNull) || {isNull _subj}) exitWith { ["WARNING", "Personne contrôlée introuvable"] call _say; };
        _rec set ["pid", true];
        ["send", ["pid", [player, _subj]]] call comspec_sse_fnc_idClient;
        call _render;
    };
    case "transmit": { [_rec] call comspec_sse_fnc_idAthenaSubmit; call _render; };
    case "frm": { [_rec] call comspec_sse_fnc_idFrm; };
    case "photo": { ["app"] call comspec_sse_fnc_idPhoto; };
    case "salute": { ["SALUTE"] call comspec_sse_fnc_idReport; };
    case "spotrep": { ["SPOTREP"] call comspec_sse_fnc_idReport; };
    // ATAK 3.0.2 et plus : la fiche (_rec de fn_idHistory) est acceptée telle quelle par l'app Bluetooth.
    case "share": { if ([3, 0, 2] call comspec_sse_fnc_idAtakAtLeast) then { ["shareIdCheck", _rec] call comspec_atak_native_fnc_btAction; }; };
    case "nfc": { if ([3, 0, 2] call comspec_sse_fnc_idAtakAtLeast) then { ["shareIdCheckNfc", _rec] call comspec_atak_native_fnc_btAction; }; };
    case "mobile": { if ([3, 0, 2] call comspec_sse_fnc_idAtakAtLeast) then { ["open", "controle"] call comspec_atak_native_fnc_mobileQr; }; };
};
true
