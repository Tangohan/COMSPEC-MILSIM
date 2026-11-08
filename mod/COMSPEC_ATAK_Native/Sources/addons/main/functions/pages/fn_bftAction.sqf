/*
    Actions de l'app BFT sur la piste choisie. Params : [action, argument]
      "center" | "route" | "sms" | "cam"
      "buzz"        : ouvre le vibreur (message rapide ou texte libre) dans la fiche de la piste
      "buzzSend" t  : fait vibrer son téléphone avec le message t (sans t : texte saisi, ou vibreur seul)
      "buzzCancel"  : referme le vibreur
      "vibe"        : vibreur seul, depuis le volet « Unité sélectionnée » de l'app Carte (pas de rendu de l'app BFT)
      "ping"        : ping de carte sur sa position, visible de tout le camp (fn_mapPing)
    Personne de l'ORBAT du site sans piste (clé « p:… », fn_bftOrbat) : seuls SMS et vibreur, et seulement si elle est en jeu ;
    jamais de carte, d'itinéraire ni de caméra (position inconnue), rien du tout pour un membre hors ligne.
    Le destinataire reçoit l'événement comspec_atak_native_buzz [indicatif, grille, texte, position, émetteur] :
    vibrations, alerte à l'écran de son téléphone (fn_buzzAlert) et accusé « VU » renvoyé à l'émetteur.
*/
params [["_act", "center"], ["_arg", ""]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _sid = _s getOrDefault ["bftSel", ""];
private _e = ((uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["units", createHashMap]) getOrDefault [_sid, createHashMap];
// Allié sans piste (calque des alliés masqué, IA filtrée) : entité de passage donnée par la fiche unique (fn_unitCard).
if ((count _e) isEqualTo 0) then { _e = _s getOrDefault ["bftSelEnt", createHashMap]; };
if ((count _e) isEqualTo 0 && {(_sid select [0, 2]) isEqualTo "p:"} && {_act in ["sms", "buzz", "buzzSend", "buzzCancel"]}) then {
    private _seen = ["presence"] call comspec_atak_native_fnc_bftOrbat;
    private _o = (_seen select 1) getOrDefault [_sid, objNull];
    // Joueur hors ORBAT sans piste : clé « p:s » + identifiant de l'objet.
    if (isNull _o && {(_sid select [0, 3]) isEqualTo "p:s"}) then { _o = ((_seen select 2) select { (netId _x) isEqualTo (_sid select [3]) }) param [0, objNull]; };
    if (!isNull _o && {_o isNotEqualTo player} && {[_o] call comspec_atak_native_fnc_hasDevice}) then {
        _e = createHashMapFromArray [["object", _o], ["callsign", [_o, true] call comspec_atak_native_fnc_unitCallsign]];
    };
};
if ((count _e) isEqualTo 0) exitWith {
    // Vibreur ouvert sur une personne qui vient de quitter le serveur : on referme.
    if (_act isEqualTo "buzzCancel") then { _s set ["bftBuzz", ""]; ["BFT"] call comspec_atak_native_fnc_pageRender; };
    false
};
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
    case "vibe": {
        if (isNull _obj || {!isPlayer _obj} || {!([_obj] call comspec_atak_native_fnc_hasDevice)}) exitWith { ["INFO", "Pas de téléphone ATAK chez cet allié", 3] call comspec_atak_native_fnc_notify; };
        private _cd = missionNamespace getVariable ["COMSPEC_ATAK_BuzzAt", createHashMap];
        private _k = netId _obj;
        if ((diag_tickTime - (_cd getOrDefault [_k, -1e9])) < 5) exitWith { ["INFO", "Patientez quelques secondes avant de refaire vibrer.", 3] call comspec_atak_native_fnc_notify; };
        _cd set [_k, diag_tickTime];
        missionNamespace setVariable ["COMSPEC_ATAK_BuzzAt", _cd];
        private _res = [{ params ["_t", "_from", "_grid", "_txt", "_pos", "_me"]; ["comspec_atak_native_buzz", [_from, _grid, _txt, _pos, _me], _t] call CBA_fnc_targetEvent; },
            [_obj, [player] call comspec_atak_native_fnc_unitCallsign, [getPosASL player, 6] call comspec_atak_native_fnc_gridRef, "", getPosASL player, player], "Vibreur", 1] call comspec_atak_native_fnc_netSend;
        [["INFO", "WARNING"] select (_res isEqualTo "QUEUED"), [format ["Vibreur envoyé à %1", _e getOrDefault ["callsign", name _obj]], "Pas de réseau : le vibreur partira au retour du signal"] select (_res isEqualTo "QUEUED"), 3] call comspec_atak_native_fnc_notify;
    };
    case "ping": {
        private _p = [_e getOrDefault ["position", [0, 0, 0]], getPosATL _obj] select !isNull _obj;
        ["send", _p] call comspec_atak_native_fnc_mapPing;
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
        _s set ["chatPick", false];
        ["CHAT"] call comspec_atak_native_fnc_navigate;
    };
    // CARTE : l'unité arrive sélectionnée (crochets et fiche), plus seulement centrée. Depuis une autre app, le centrage
    // attend que la carte ait pris sa place à l'écran.
    default {
        _s set ["selectedEntity", _e];
        _s set ["selectedMarker", ""];
        private _target = [_obj, _e getOrDefault ["position", [0, 0, 0]]] select (isNull _obj);
        if ((_s getOrDefault ["activePage", ""]) isEqualTo "MAP") then {
            [_target, 0.03] call comspec_atak_native_fnc_mapCenter;
            ["mapCard"] call comspec_atak_native_fnc_unitCard;
            [] call comspec_atak_native_fnc_inspectorUpdate;
        } else {
            ["MAP"] call comspec_atak_native_fnc_navigate;
            [{ [_this, 0.03] call comspec_atak_native_fnc_mapCenter; }, _target, 0.25] call comspec_atak_native_fnc_waitAndExec;
        };
    };
};
true
