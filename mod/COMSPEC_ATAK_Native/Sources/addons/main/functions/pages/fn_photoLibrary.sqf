/*
    Photothèque : captures présentes sur le poste (dossier COMSPEC + Screenshots), et retransmission vers Athena.
    Params : [action, arg]
      "list"    : relit le disque (DLL) et met la liste en cache, renvoie [[chemin, nom]...]
      "send"    : retransmet la photo n° arg de la liste en cache
      "sendAll" : retransmet toutes les photos de la liste en cache (une toutes les 2 s)
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
        _out
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
