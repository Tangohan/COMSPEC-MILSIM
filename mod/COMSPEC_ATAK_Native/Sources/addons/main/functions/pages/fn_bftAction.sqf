/*
    Actions de l'app BFT sur la piste choisie. Params : [action, argument]
      "center" | "route" | "sms" | "cam"
      "buzz"        : ouvre le vibreur (message rapide ou texte libre) dans la fiche de la piste
      "buzzSend" t  : fait vibrer son téléphone avec le message t (sans t : texte saisi, ou vibreur seul)
      "buzzCancel"  : referme le vibreur
    Le destinataire reçoit l'événement comspec_atak_native_buzz [indicatif, grille, texte, position, émetteur] :
    vibrations, alerte à l'écran de son téléphone (fn_buzzAlert) et accusé « VU » renvoyé à l'émetteur.
*/
params [["_act", "center"], ["_arg", ""]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _e = ((uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["units", createHashMap]) getOrDefault [_s getOrDefault ["bftSel", ""], createHashMap];
if ((count _e) isEqualTo 0) exitWith { false };
private _obj = _e getOrDefault ["object", objNull];
switch (_act) do {
    case "route": {
        private _p = [_e getOrDefault ["position", [0, 0, 0]], getPosATL _obj] select !isNull _obj;
        [[_p select 0, _p select 1, 0], _e getOrDefault ["callsign", "Piste"]] spawn comspec_atak_native_fnc_routeCompute;
        ["MAP"] call comspec_atak_native_fnc_navigate;
    };
    case "buzz": {
        if (isNull _obj || {!isPlayer _obj}) exitWith {};
        _s set ["bftBuzz", _s getOrDefault ["bftSel", ""]];
        uiNamespace setVariable ["COMSPEC_ATAK_BuzzDraft", ""];
        ["BFT"] call comspec_atak_native_fnc_pageRender;
    };
    case "buzzCancel": { _s set ["bftBuzz", ""]; ["BFT"] call comspec_atak_native_fnc_pageRender; };
    case "buzzSend": {
        if (isNull _obj || {!isPlayer _obj}) exitWith {};
        private _txt = _arg;
        if (_txt isEqualTo "") then {
            private _ed = uiNamespace getVariable ["COMSPEC_ATAK_BuzzEdit", controlNull];
            if (!isNull _ed) then { _txt = trim (ctrlText _ed); };
        };
        _txt = (_txt regexReplace ["[<>]", ""]) select [0, 80];
        private _cd = missionNamespace getVariable ["COMSPEC_ATAK_BuzzAt", createHashMap];
        private _k = netId _obj;
        if ((diag_tickTime - (_cd getOrDefault [_k, -1e9])) < 5) exitWith { ["INFO", "Patientez quelques secondes avant de refaire vibrer.", 3] call comspec_atak_native_fnc_notify; };
        _cd set [_k, diag_tickTime];
        missionNamespace setVariable ["COMSPEC_ATAK_BuzzAt", _cd];
        [{ params ["_t", "_from", "_grid", "_txt", "_pos", "_me"]; ["comspec_atak_native_buzz", [_from, _grid, _txt, _pos, _me], _t] call CBA_fnc_targetEvent; },
            [_obj, [player] call comspec_atak_native_fnc_unitCallsign, [getPosASL player, 6] call comspec_atak_native_fnc_gridRef, _txt, getPosASL player, player], "Vibreur", 1] call comspec_atak_native_fnc_netSend;
        _s set ["bftBuzz", ""];
        uiNamespace setVariable ["COMSPEC_ATAK_BuzzDraft", ""];
        ["INFO", format ["Vibreur envoyé à %1%2", _e getOrDefault ["callsign", name _obj], ["", format [" : %1", _txt]] select (_txt isNotEqualTo "")], 3] call comspec_atak_native_fnc_notify;
        ["BFT"] call comspec_atak_native_fnc_pageRender;
    };
    // VOIR CAMÉRA : carte centrée sur l'équipier, sa caméra poitrine en petite fenêtre (fn_pageMap, clé mapCam).
    case "cam": {
        if (isNull _obj || {!isPlayer _obj} || {!alive _obj}) exitWith {};
        _s set ["mapCam", netId _obj];
        ["MAP"] call comspec_atak_native_fnc_navigate;
        [_obj, 0.03] call comspec_atak_native_fnc_mapCenter;
    };
    case "sms": {
        if (isNull _obj) exitWith {};
        _s set ["chatPeer", name _obj];
        _s set ["chatView", "CONV"];
        ["CHAT"] call comspec_atak_native_fnc_navigate;
    };
    default {
        ["MAP"] call comspec_atak_native_fnc_navigate;
        [[_obj, _e getOrDefault ["position", [0, 0, 0]]] select (isNull _obj), 0.03] call comspec_atak_native_fnc_mapCenter;
    };
};
true
