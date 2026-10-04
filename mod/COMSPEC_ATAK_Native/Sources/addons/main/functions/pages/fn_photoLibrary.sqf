/*
    Photothèque : captures présentes sur le poste (dossier COMSPEC + Screenshots), et retransmission vers Athena.
    Params : [action, arg]
      "list"    : relit le disque (DLL) et met la liste en cache, renvoie [[chemin, nom]...]
      "send"    : retransmet la photo n° arg de la liste en cache
      "sendAll" : retransmet toutes les photos de la liste en cache (une toutes les 2 s)
      "check"   : demande à Athena lesquelles sont visibles (DLL ReconImagesKnown, 2.0.59+) ;
                  uiNamespace COMSPEC_ATAK_PhotoKnown = noms en minuscules, ou nil si Athena n'a pas pu répondre
      "delete"  : supprime du poste la photo n° arg (second appui dans les 4 s pour confirmer)
      "deleteAll" : supprime du poste toutes les photos listées (second appui dans les 4 s pour confirmer)
*/
params [["_action", "list"], ["_arg", -1]];
private _fnc_send = {
    params ["_path", "_name"];
    // Un renvoi manuel doit repartir même si la photo a déjà été tentée.
    {
        _x params ["_var", "_ns"];
        private _list = ([missionNamespace, profileNamespace] select (_ns isEqualTo "profile")) getVariable [_var, []];
        if (_list isEqualType []) then {
            _list = _list - [toLower _path, toLower _name];
            ([missionNamespace, profileNamespace] select (_ns isEqualTo "profile")) setVariable [_var, _list];
        };
    } forEach [["COMSPEC_Athena_PhotoDead", "profile"], ["COMSPEC_Athena_PhotoFailed", "mission"], ["COMSPEC_Athena_PhotoSeen", "mission"], ["COMSPEC_Athena_PhotoUploaded", "mission"]];
    private _caption = format ["%1 · retransmise · %2", [player] call comspec_atak_native_fnc_unitCallsign, _name];
    [_path, _caption, "CTAB", "", true, false, false] call comspec_overwatch_connect_fnc_captureReconImage
};
switch (_action) do {
    case "list": {
        if !([] call comspec_atak_native_fnc_bridge) exitWith { uiNamespace setVariable ["COMSPEC_ATAK_PhotoLib", []]; [] };
        private _raw = if (isNil "comspec_overwatch_connect_fnc_listLocalScreenshots") then { [] } else { [] call comspec_overwatch_connect_fnc_listLocalScreenshots };
        private _out = (_raw select { _x isEqualType [] && {(count _x) >= 2} }) apply { [_x select 0, _x select 1] };
        uiNamespace setVariable ["COMSPEC_ATAK_PhotoLib", _out];
        uiNamespace setVariable ["COMSPEC_ATAK_PhotoLibAt", diag_tickTime];
        ["check"] call comspec_atak_native_fnc_photoLibrary;
        _out
    };
    case "check": {
        private _lib = uiNamespace getVariable ["COMSPEC_ATAK_PhotoLib", []];
        uiNamespace setVariable ["COMSPEC_ATAK_PhotoKnown", nil];
        if ((count _lib) isEqualTo 0 || {!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])}) exitWith { false };
        private _names = (_lib apply { _x select 1 }) select [0, 200];
        private _raw = "COMSPECExtension" callExtension ["ReconImagesKnown", [_names joinString "|"]];
        if (!isNil "comspec_overwatch_connect_fnc_extResult") then { _raw = [_raw] call comspec_overwatch_connect_fnc_extResult; };
        if (_raw isEqualType []) then { _raw = _raw param [0, ""]; };
        if !(_raw isEqualType "") then { _raw = str _raw; };
        if ((_raw select [0, 3]) isNotEqualTo "OK|") exitWith { false };
        uiNamespace setVariable ["COMSPEC_ATAK_PhotoKnown", ((_raw select [3]) splitString toString [10]) apply { toLower _x }];
        true
    };
    case "delete";
    case "deleteAll": {
        private _lib = uiNamespace getVariable ["COMSPEC_ATAK_PhotoLib", []];
        private _key = if (_action isEqualTo "deleteAll") then { "ALL" } else { str _arg };
        if (_action isEqualTo "delete" && {_arg < 0 || {_arg >= count _lib}}) exitWith { false };
        private _armed = uiNamespace getVariable ["COMSPEC_ATAK_PhotoDelArm", ["", -10]];
        // Premier appui : le bouton passe en CONFIRMER pendant 4 s.
        if ((_armed select 0) isNotEqualTo _key || {diag_tickTime - (_armed select 1) > 4}) exitWith {
            uiNamespace setVariable ["COMSPEC_ATAK_PhotoDelArm", [_key, diag_tickTime]];
            [{ ["PHOTOS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
            [{ if (diag_tickTime - ((uiNamespace getVariable ["COMSPEC_ATAK_PhotoDelArm", ["", -10]]) select 1) >= 4 && {((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "PHOTOS"}) then { ["PHOTOS"] call comspec_atak_native_fnc_pageRender; }; }, [], 4.1] call CBA_fnc_waitAndExecute;
            true
        };
        uiNamespace setVariable ["COMSPEC_ATAK_PhotoDelArm", ["", -10]];
        private _todo = if (_action isEqualTo "deleteAll") then { +_lib } else { [_lib select _arg] };
        private _done = 0;
        {
            _x params ["_path", "_name"];
            private _r = "COMSPECExtension" callExtension ["DeleteLocalFile", [_path]];
            if (_r isEqualType []) then { _r = _r param [0, ""]; };
            if ((_r isEqualType "") && {(_r select [0, 2]) isEqualTo "OK"}) then { _done = _done + 1; };
        } forEach _todo;
        private _fail = (count _todo) - _done;
        [["SUCCESS", "WARNING"] select (_fail > 0), if (_fail > 0) then { format ["%1 photo(s) supprimée(s), %2 refusée(s) (fichier hors des dossiers COMSPEC ou déjà ouvert).", _done, _fail] } else { format ["%1 photo(s) supprimée(s) du poste.", _done] }, 4, 20] call comspec_atak_native_fnc_notify;
        ["list"] call comspec_atak_native_fnc_photoLibrary;
        [{ ["PHOTOS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
        _done > 0
    };
    case "send": {
        private _lib = uiNamespace getVariable ["COMSPEC_ATAK_PhotoLib", []];
        if (_arg < 0 || {_arg >= count _lib}) exitWith { false };
        // Une photo pèse environ 600 Ko : avec un débit faible, la retransmission prend du temps.
        [{
            params ["_rec", "_fnc_send"];
            private _ok = _rec call _fnc_send;
            [["INFO", "WARNING"] select !_ok, ["Photo transmise vers Athena.", "Retransmission refusée : voir le détail dans Photos."] select !_ok, 4, 20] call comspec_atak_native_fnc_notify;
            [{ ["PHOTOS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
        }, [_lib select _arg, _fnc_send], "Photo", 600] call comspec_atak_native_fnc_netSend;
        true
    };
    case "sendAll": {
        private _lib = +(uiNamespace getVariable ["COMSPEC_ATAK_PhotoLib", []]);
        if ((count _lib) isEqualTo 0) exitWith { false };
        ["INFO", format ["%1 photo(s) remises en file, une toutes les 2 s.", count _lib], 4, 20] call comspec_atak_native_fnc_notify;
        [_lib, _fnc_send] spawn {
            params ["_lib", "_fnc_send"];
            { [{ (_this select 0) call (_this select 1); }, [_x, _fnc_send], format ["Photo %1/%2", _forEachIndex + 1, count _lib], 600] call comspec_atak_native_fnc_netSend; uiSleep 2; } forEach _lib;
            [{ ["PHOTOS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
        };
        true
    };
    default { false };
};
